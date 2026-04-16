import api, { unwrapData } from '@/lib/api'
import type {
    PaginatedEnvelope,
    Project,
    ProjectFilters,
    ProjectGantt,
    ProjectMilestone,
    ProjectMilestonePayload,
    ProjectPayload,
    ProjectResource,
    ProjectResourcePayload,
    ProjectsDashboard,
    ProjectTimeEntry,
    ProjectTimeEntryPayload,
} from '@/types/projects'

function normalizePaginated<T>(payload: unknown): PaginatedEnvelope<T> {
    const data = payload as T[] & { meta?: PaginatedEnvelope<T>['meta'] }

    return {
        data: Array.isArray(data) ? data : [],
        meta: data?.meta,
    }
}

export const projectsApi = {
    async list(filters: ProjectFilters = {}): Promise<PaginatedEnvelope<Project>> {
        const response = await api.get('/projects', { params: filters })
        return normalizePaginated<Project>(response.data)
    },

    async create(payload: ProjectPayload): Promise<Project> {
        const response = await api.post('/projects', payload)
        return unwrapData<Project>(response)
    },

    async start(projectId: number): Promise<Project> {
        const response = await api.post(`/projects/${projectId}/start`)
        return unwrapData<Project>(response)
    },

    async pause(projectId: number): Promise<Project> {
        const response = await api.post(`/projects/${projectId}/pause`)
        return unwrapData<Project>(response)
    },

    async resume(projectId: number): Promise<Project> {
        const response = await api.post(`/projects/${projectId}/resume`)
        return unwrapData<Project>(response)
    },

    async complete(projectId: number): Promise<Project> {
        const response = await api.post(`/projects/${projectId}/complete`)
        return unwrapData<Project>(response)
    },

    async dashboard(): Promise<ProjectsDashboard> {
        const response = await api.get('/projects/dashboard')
        return unwrapData<ProjectsDashboard>(response)
    },

    async gantt(projectId: number): Promise<ProjectGantt> {
        const response = await api.get(`/projects/${projectId}/gantt`)
        return unwrapData<ProjectGantt>(response)
    },

    async listMilestones(projectId: number, perPage = 50): Promise<PaginatedEnvelope<ProjectMilestone>> {
        const response = await api.get(`/projects/${projectId}/milestones`, { params: { per_page: perPage } })
        return normalizePaginated<ProjectMilestone>(response.data)
    },

    async createMilestone(projectId: number, payload: ProjectMilestonePayload): Promise<ProjectMilestone> {
        const response = await api.post(`/projects/${projectId}/milestones`, payload)
        return unwrapData<ProjectMilestone>(response)
    },

    async completeMilestone(projectId: number, milestoneId: number): Promise<ProjectMilestone> {
        const response = await api.post(`/projects/${projectId}/milestones/${milestoneId}/complete`)
        return unwrapData<ProjectMilestone>(response)
    },

    async invoiceMilestone(projectId: number, milestoneId: number): Promise<ProjectMilestone> {
        const response = await api.post(`/projects/${projectId}/milestones/${milestoneId}/invoice`)
        return unwrapData<ProjectMilestone>(response)
    },

    async listResources(projectId: number, perPage = 50): Promise<PaginatedEnvelope<ProjectResource>> {
        const response = await api.get(`/projects/${projectId}/resources`, { params: { per_page: perPage } })
        return normalizePaginated<ProjectResource>(response.data)
    },

    async createResource(projectId: number, payload: ProjectResourcePayload): Promise<ProjectResource> {
        const response = await api.post(`/projects/${projectId}/resources`, payload)
        return unwrapData<ProjectResource>(response)
    },

    async listTimeEntries(projectId: number, perPage = 50): Promise<PaginatedEnvelope<ProjectTimeEntry>> {
        const response = await api.get(`/projects/${projectId}/time-entries`, { params: { per_page: perPage } })
        return normalizePaginated<ProjectTimeEntry>(response.data)
    },

    async createTimeEntry(projectId: number, payload: ProjectTimeEntryPayload): Promise<ProjectTimeEntry> {
        const response = await api.post(`/projects/${projectId}/time-entries`, payload)
        return unwrapData<ProjectTimeEntry>(response)
    },
}
