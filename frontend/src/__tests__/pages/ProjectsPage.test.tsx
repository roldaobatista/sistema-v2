import { describe, expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { ProjectsPage } from '@/pages/projects/ProjectsPage'

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: () => true,
    }),
}))

vi.mock('@/hooks/useProjects', () => ({
    useProjects: () => ({
        data: {
            data: [
                {
                    id: 1,
                    customer_id: 5,
                    created_by: 9,
                    code: 'PRJ-00001',
                    name: 'Implantação ERP Multiunidade',
                    status: 'active',
                    priority: 'high',
                    budget: '80000.00',
                    spent: '32000.00',
                    progress_percent: '40.00',
                    billing_type: 'milestone',
                    customer: { id: 5, business_name: 'Kalibrium Labs' },
                    manager: { id: 9, name: 'Ana Souza' },
                },
            ],
        },
        isLoading: false,
        refetch: vi.fn(),
    }),
    useProjectsDashboard: () => ({
        data: {
            total_projects: 1,
            active_projects: 1,
            completed_projects: 0,
            budget_total: 80000,
            spent_total: 32000,
            average_progress: 40,
            status_breakdown: { active: 1 },
        },
        refetch: vi.fn(),
    }),
    useProjectGantt: () => ({
        data: {
            project: { id: 1, name: 'Implantação ERP Multiunidade', status: 'active', start_date: '2026-04-01', end_date: '2026-06-30' },
            milestones: [{ id: 11, name: 'Kickoff', status: 'pending', order: 1, planned_start: '2026-04-01', planned_end: '2026-04-05', weight: 20 }],
            resources: [{ id: 21, user_id: 9, role: 'PM', allocation_percent: 100, user: { id: 9, name: 'Ana Souza' } }],
            time_entries: [{ id: 31, project_resource_id: 21, milestone_id: 11, work_order_id: null, date: '2026-04-02', hours: 6, billable: true }],
        },
    }),
    useProjectMilestones: () => ({
        data: { data: [{ id: 11, project_id: 1, name: 'Kickoff', status: 'pending', order: 1, billing_value: '15000.00' }] },
    }),
    useProjectResources: () => ({
        data: { data: [{ id: 21, project_id: 1, user_id: 9, role: 'PM', allocation_percent: '100', start_date: '2026-04-01', end_date: '2026-06-30', total_hours_planned: '120', total_hours_logged: '42', user: { id: 9, name: 'Ana Souza' } }] },
    }),
    useProjectTimeEntries: () => ({
        data: { data: [{ id: 31, project_id: 1, project_resource_id: 21, date: '2026-04-02', hours: '6', billable: true, description: 'Workshop inicial', resource: { user: { id: 9, name: 'Ana Souza' } }, milestone: { id: 11, name: 'Kickoff' } }] },
    }),
    useCreateProject: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useProjectLifecycleAction: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useCreateProjectMilestone: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useCompleteProjectMilestone: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useInvoiceProjectMilestone: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useCreateProjectResource: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useCreateProjectTimeEntry: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))

describe('ProjectsPage', () => {
    it('renders portfolio, cockpit and supporting sections', () => {
        const markup = renderToStaticMarkup(<ProjectsPage />)

        expect(markup).toContain('Projetos')
        expect(markup).toContain('PRJ-00001')
        expect(markup).toContain('Implantação ERP Multiunidade')
        expect(markup).toContain('Cockpit do projeto')
        expect(markup).toContain('Marcos')
        expect(markup).toContain('Recursos')
        expect(markup).toContain('Apontamentos')
    })
})
