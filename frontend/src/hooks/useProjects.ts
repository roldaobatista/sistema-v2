import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { projectsApi } from '@/lib/projects-api'
import type {
    ProjectFilters,
    ProjectMilestonePayload,
    ProjectPayload,
    ProjectResourcePayload,
    ProjectTimeEntryPayload,
} from '@/types/projects'

const projectQueryKeys = {
    all: ['projects'] as const,
    list: (filters: ProjectFilters) => ['projects', 'list', filters] as const,
    dashboard: ['projects', 'dashboard'] as const,
    gantt: (projectId: number) => ['projects', 'gantt', projectId] as const,
    milestones: (projectId: number) => ['projects', 'milestones', projectId] as const,
    resources: (projectId: number) => ['projects', 'resources', projectId] as const,
    timeEntries: (projectId: number) => ['projects', 'time-entries', projectId] as const,
}

export function useProjects(filters: ProjectFilters) {
    return useQuery({
        queryKey: projectQueryKeys.list(filters),
        queryFn: () => projectsApi.list(filters),
    })
}

export function useProjectsDashboard(enabled = true) {
    return useQuery({
        queryKey: projectQueryKeys.dashboard,
        queryFn: () => projectsApi.dashboard(),
        enabled,
    })
}

export function useProjectGantt(projectId: number | null) {
    return useQuery({
        queryKey: projectQueryKeys.gantt(projectId ?? 0),
        queryFn: () => projectsApi.gantt(projectId ?? 0),
        enabled: projectId !== null,
    })
}

export function useProjectMilestones(projectId: number | null) {
    return useQuery({
        queryKey: projectQueryKeys.milestones(projectId ?? 0),
        queryFn: () => projectsApi.listMilestones(projectId ?? 0),
        enabled: projectId !== null,
    })
}

export function useProjectResources(projectId: number | null) {
    return useQuery({
        queryKey: projectQueryKeys.resources(projectId ?? 0),
        queryFn: () => projectsApi.listResources(projectId ?? 0),
        enabled: projectId !== null,
    })
}

export function useProjectTimeEntries(projectId: number | null) {
    return useQuery({
        queryKey: projectQueryKeys.timeEntries(projectId ?? 0),
        queryFn: () => projectsApi.listTimeEntries(projectId ?? 0),
        enabled: projectId !== null,
    })
}

function useInvalidateProjects() {
    const queryClient = useQueryClient()

    return async (projectId?: number) => {
        await queryClient.invalidateQueries({ queryKey: projectQueryKeys.all })
        if (projectId) {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: projectQueryKeys.gantt(projectId) }),
                queryClient.invalidateQueries({ queryKey: projectQueryKeys.milestones(projectId) }),
                queryClient.invalidateQueries({ queryKey: projectQueryKeys.resources(projectId) }),
                queryClient.invalidateQueries({ queryKey: projectQueryKeys.timeEntries(projectId) }),
            ])
        }
    }
}

export function useCreateProject() {
    const invalidate = useInvalidateProjects()

    return useMutation({
        mutationFn: (payload: ProjectPayload) => projectsApi.create(payload),
        onSuccess: async project => invalidate(project.id),
    })
}

export function useProjectLifecycleAction(action: 'start' | 'pause' | 'resume' | 'complete') {
    const invalidate = useInvalidateProjects()

    return useMutation({
        mutationFn: (projectId: number) => projectsApi[action](projectId),
        onSuccess: async project => invalidate(project.id),
    })
}

export function useCreateProjectMilestone() {
    const invalidate = useInvalidateProjects()

    return useMutation({
        mutationFn: ({ projectId, payload }: { projectId: number; payload: ProjectMilestonePayload }) =>
            projectsApi.createMilestone(projectId, payload),
        onSuccess: async (_milestone, variables) => invalidate(variables.projectId),
    })
}

export function useCompleteProjectMilestone() {
    const invalidate = useInvalidateProjects()

    return useMutation({
        mutationFn: ({ projectId, milestoneId }: { projectId: number; milestoneId: number }) =>
            projectsApi.completeMilestone(projectId, milestoneId),
        onSuccess: async (_milestone, variables) => invalidate(variables.projectId),
    })
}

export function useInvoiceProjectMilestone() {
    const invalidate = useInvalidateProjects()

    return useMutation({
        mutationFn: ({ projectId, milestoneId }: { projectId: number; milestoneId: number }) =>
            projectsApi.invoiceMilestone(projectId, milestoneId),
        onSuccess: async (_milestone, variables) => invalidate(variables.projectId),
    })
}

export function useCreateProjectResource() {
    const invalidate = useInvalidateProjects()

    return useMutation({
        mutationFn: ({ projectId, payload }: { projectId: number; payload: ProjectResourcePayload }) =>
            projectsApi.createResource(projectId, payload),
        onSuccess: async (_resource, variables) => invalidate(variables.projectId),
    })
}

export function useCreateProjectTimeEntry() {
    const invalidate = useInvalidateProjects()

    return useMutation({
        mutationFn: ({ projectId, payload }: { projectId: number; payload: ProjectTimeEntryPayload }) =>
            projectsApi.createTimeEntry(projectId, payload),
        onSuccess: async (_entry, variables) => invalidate(variables.projectId),
    })
}
