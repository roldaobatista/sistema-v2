export type ProjectStatus = 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled'
export type ProjectPriority = 'low' | 'medium' | 'high' | 'critical'
export type ProjectBillingType = 'milestone' | 'hourly' | 'fixed_price'
export type ProjectMilestoneStatus = 'pending' | 'completed' | 'invoiced'

export interface ProjectCustomer {
    id: number
    name?: string | null
    business_name?: string | null
}

export interface ProjectManager {
    id: number
    name: string
}

export interface ProjectCrmDeal {
    id: number
    title: string
    status: string
    value?: string | number | null
}

export interface Project {
    id: number
    customer_id: number
    crm_deal_id?: number | null
    created_by: number
    code: string
    name: string
    description?: string | null
    status: ProjectStatus
    priority: ProjectPriority
    start_date?: string | null
    end_date?: string | null
    actual_start_date?: string | null
    actual_end_date?: string | null
    budget?: string | number | null
    spent?: string | number | null
    progress_percent?: string | number | null
    billing_type: ProjectBillingType
    hourly_rate?: string | number | null
    tags?: string[] | null
    manager_id?: number | null
    customer?: ProjectCustomer | null
    manager?: ProjectManager | null
    crm_deal?: ProjectCrmDeal | null
}

export interface ProjectMilestone {
    id: number
    project_id: number
    name: string
    status: ProjectMilestoneStatus
    planned_start?: string | null
    planned_end?: string | null
    actual_start?: string | null
    actual_end?: string | null
    completed_at?: string | null
    billing_value?: string | number | null
    weight?: string | number | null
    order: number
    dependencies?: number[] | null
    deliverables?: string | null
    invoice_id?: number | null
}

export interface ProjectResourceUser {
    id: number
    name: string
}

export interface ProjectResource {
    id: number
    project_id: number
    user_id: number
    role: string
    allocation_percent: string | number
    start_date: string
    end_date: string
    hourly_rate?: string | number | null
    total_hours_planned?: string | number | null
    total_hours_logged?: string | number | null
    user?: ProjectResourceUser | null
}

export interface ProjectTimeEntryMilestone {
    id: number
    name: string
}

export interface ProjectTimeEntry {
    id: number
    project_id: number
    project_resource_id: number
    milestone_id?: number | null
    work_order_id?: number | null
    date: string
    hours: string | number
    description?: string | null
    billable: boolean
    resource?: ProjectResource | null
    milestone?: ProjectTimeEntryMilestone | null
    work_order?: {
        id: number
        number?: string | null
        os_number?: string | null
    } | null
}

export interface ProjectsDashboard {
    total_projects: number
    active_projects: number
    completed_projects: number
    budget_total: number
    spent_total: number
    average_progress: number
    status_breakdown: Record<string, number>
}

export interface ProjectGantt {
    project: {
        id: number
        name: string
        status: ProjectStatus
        start_date?: string | null
        end_date?: string | null
    }
    milestones: Array<{
        id: number
        name: string
        status: ProjectMilestoneStatus
        order: number
        planned_start?: string | null
        planned_end?: string | null
        weight?: string | number | null
    }>
    resources: Array<{
        id: number
        user_id: number
        role: string
        allocation_percent: string | number
        user?: ProjectResourceUser | null
    }>
    time_entries: Array<{
        id: number
        project_resource_id: number
        milestone_id?: number | null
        work_order_id?: number | null
        date: string
        hours: string | number
        billable: boolean
    }>
}

export interface PaginatedEnvelope<T> {
    data: T[]
    meta?: {
        current_page: number
        per_page: number
        total: number
        last_page?: number
    }
}

export interface ProjectFilters {
    status?: ProjectStatus | ''
    customer_id?: number
    per_page?: number
}

export interface ProjectPayload {
    customer_id: number
    name: string
    description?: string
    status: ProjectStatus
    priority: ProjectPriority
    start_date?: string
    end_date?: string
    budget?: number
    billing_type: ProjectBillingType
    hourly_rate?: number
    crm_deal_id?: number
    tags?: string[]
    manager_id?: number
}

export interface ProjectMilestonePayload {
    name: string
    planned_start?: string
    planned_end?: string
    billing_value?: number
    weight?: number
    order: number
    dependencies?: number[]
    deliverables?: string
}

export interface ProjectResourcePayload {
    user_id: number
    role: string
    allocation_percent: number
    start_date: string
    end_date: string
    hourly_rate?: number
    total_hours_planned?: number
}

export interface ProjectTimeEntryPayload {
    project_resource_id: number
    milestone_id?: number
    work_order_id?: number
    date: string
    hours: number
    description?: string
    billable: boolean
}
