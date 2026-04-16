import api, { unwrapData } from './api'
import type { CrmActivity } from './crm-api'

// ─── Types ──────────────────────────────────────────

export interface VisitCheckin {
    id: number
    tenant_id: number
    customer_id: number
    user_id: number
    activity_id: number | null
    checkin_at: string
    checkin_lat: number | null
    checkin_lng: number | null
    checkin_address: string | null
    checkin_photo: string | null
    checkout_at: string | null
    checkout_lat: number | null
    checkout_lng: number | null
    checkout_photo: string | null
    duration_minutes: number | null
    distance_from_client_meters: number | null
    status: 'checked_in' | 'checked_out' | 'cancelled'
    notes: string | null
    created_at: string
    customer?: { id: number; name: string; phone?: string; address_city?: string }
    user?: { id: number; name: string }
}

export interface VisitRoute {
    id: number
    user_id: number
    route_date: string
    name: string | null
    status: 'planned' | 'in_progress' | 'completed' | 'cancelled'
    total_stops: number
    completed_stops: number
    total_distance_km: number | null
    estimated_duration_minutes: number | null
    notes: string | null
    created_at: string
    user?: { id: number; name: string }
    stops?: VisitRouteStop[]
}

export interface VisitRouteStop {
    id: number
    visit_route_id: number
    customer_id: number
    checkin_id: number | null
    stop_order: number
    status: 'pending' | 'visited' | 'skipped'
    estimated_duration_minutes: number | null
    objective: string | null
    notes: string | null
    customer?: { id: number; name: string; address_city?: string; latitude?: number; longitude?: number }
}

export interface VisitReport {
    id: number
    customer_id: number
    user_id: number
    checkin_id: number | null
    deal_id: number | null
    visit_date: string
    visit_type: string
    contact_name: string | null
    contact_role: string | null
    summary: string
    decisions: string | null
    next_steps: string | null
    overall_sentiment: 'positive' | 'neutral' | 'negative' | null
    topics: { topic: string; discussed: boolean; notes?: string }[] | null
    follow_up_scheduled: boolean
    next_contact_at: string | null
    next_contact_type: string | null
    created_at: string
    customer?: { id: number; name: string }
    user?: { id: number; name: string }
    commitments?: Commitment[]
}

export interface ContactPolicy {
    id: number
    name: string
    target_type: 'rating' | 'segment' | 'all'
    target_value: string | null
    max_days_without_contact: number
    warning_days_before: number
    preferred_contact_type: string | null
    is_active: boolean
    priority: number
}

export interface QuickNote {
    id: number
    customer_id: number
    user_id: number
    deal_id: number | null
    channel: string | null
    sentiment: 'positive' | 'neutral' | 'negative' | null
    content: string
    is_pinned: boolean
    tags: string[] | null
    created_at: string
    customer?: { id: number; name: string }
    user?: { id: number; name: string }
}

export interface Commitment {
    id: number
    customer_id: number
    user_id: number
    visit_report_id: number | null
    title: string
    description: string | null
    responsible_type: 'us' | 'client' | 'both'
    responsible_name: string | null
    due_date: string | null
    status: 'pending' | 'completed' | 'overdue' | 'cancelled'
    completed_at: string | null
    completion_notes: string | null
    priority: 'low' | 'normal' | 'high' | 'urgent'
    created_at: string
    customer?: { id: number; name: string }
    user?: { id: number; name: string }
}

export interface ImportantDate {
    id: number
    customer_id: number
    title: string
    type: 'birthday' | 'company_anniversary' | 'contract_start' | 'custom'
    date: string
    recurring_yearly: boolean
    remind_days_before: number
    contact_name: string | null
    notes: string | null
    is_active: boolean
    customer?: { id: number; name: string }
}

export interface VisitSurvey {
    id: number
    customer_id: number
    checkin_id: number | null
    user_id: number
    token: string
    rating: number | null
    comment: string | null
    status: 'pending' | 'answered' | 'expired'
    sent_at: string | null
    answered_at: string | null
    created_at: string
    customer?: { id: number; name: string }
    user?: { id: number; name: string }
}

export interface AccountPlan {
    id: number
    customer_id: number
    owner_id: number
    title: string
    objective: string | null
    status: 'active' | 'completed' | 'paused' | 'cancelled'
    start_date: string | null
    target_date: string | null
    revenue_target: number | null
    revenue_current: number | null
    progress_percent: number
    notes: string | null
    created_at: string
    customer?: { id: number; name: string; rating?: string }
    owner?: { id: number; name: string }
    actions?: AccountPlanAction[]
}

export interface AccountPlanAction {
    id: number
    account_plan_id: number
    assigned_to: number | null
    title: string
    description: string | null
    due_date: string | null
    status: 'pending' | 'in_progress' | 'completed' | 'cancelled'
    completed_at: string | null
    assignee?: { id: number; name: string }
}

export interface CustomerRfmScore {
    id: number
    customer_id: number
    recency_score: number
    frequency_score: number
    monetary_score: number
    rfm_segment: string
    total_score: number
    last_purchase_date: string | null
    purchase_count: number
    total_revenue: number
    calculated_at: string
    customer?: { id: number; name: string; rating?: string; health_score?: number; segment?: string }
}

export interface GamificationScore {
    id: number
    user_id: number
    period: string
    period_type: string
    visits_count: number
    deals_won: number
    deals_value: number
    new_clients: number
    activities_count: number
    coverage_percent: number
    csat_avg: number
    commitments_on_time: number
    commitments_total: number
    total_points: number
    rank_position: number | null
    user?: { id: number; name: string }
}

export interface GamificationBadge {
    id: number
    name: string
    slug: string
    description: string | null
    icon: string | null
    color: string | null
    category: string
    metric: string
    threshold: number
    is_active: boolean
}

export interface PortfolioMapCustomer {
    id: number
    name: string
    latitude: number
    longitude: number
    address_city: string | null
    address_state: string | null
    rating: string | null
    health_score: number | null
    last_contact_at: string | null
    segment: string | null
    days_since_contact: number
    alert_level: 'ok' | 'attention' | 'warning' | 'critical'
}

export interface SmartAgendaSuggestion {
    id: number
    name: string
    rating: string | null
    health_score: number | null
    last_contact_at: string | null
    segment: string | null
    latitude: number | null
    longitude: number | null
    priority_score: number
    days_since_contact: number
    max_days_allowed: number
    days_until_due: number
    has_calibration_expiring: boolean
    has_pending_quote: boolean
    suggested_action: string
}

export interface ForgottenClientsData {
    stats: {
        total_forgotten: number
        critical: number
        high: number
        medium: number
        by_seller: Record<string, number>
        by_rating: Record<string, number>
    }
    meta?: {
        current_page?: number
        last_page?: number
        per_page?: number
        total?: number
        from?: number | null
        to?: number | null
    }
    customers: Array<{
        id: number
        name: string
        rating: string | null
        health_score: number | null
        last_contact_at: string | null
        next_follow_up_at: string | null
        segment: string | null
        address_city: string | null
        days_since_contact: number
        urgency: 'critical' | 'high' | 'medium' | 'low'
        assigned_seller?: { id: number; name: string } | null
    }>
}

export interface LatentOpportunity {
    type: 'calibration_expiring' | 'inactive_customer' | 'contract_renewal'
    customer: {
        id: number
        name: string
        rating?: string | null
        health_score?: number | null
        contract_type?: string | null
        contract_end?: string | null
        last_contact_at?: string | null
    } | null
    detail: string
    date?: string | null
    priority: 'high' | 'medium' | 'low'
}

export interface LatentOpportunitiesData {
    opportunities: LatentOpportunity[]
    summary: {
        calibration_expiring: number
        inactive_customers: number
        contract_renewals: number
        total: number
    }
}

export interface NegotiationHistoryBusinessItem {
    entry_type?: 'quote' | 'work_order' | 'deal'
    type: 'quote' | 'work_order' | 'deal'
    id: number
    title?: string | null
    quote_number?: string | null
    os_number?: string | null
    business_number?: string | null
    total?: number | null
    value?: number | null
    status?: string | null
    created_at: string
    approved_at?: string | null
    completed_at?: string | null
    won_at?: string | null
    lost_at?: string | null
    lost_reason?: string | null
    discount_amount?: number | null
}

export type NegotiationHistoryTimelineItem =
    | NegotiationHistoryBusinessItem
    | (CrmActivity & { entry_type: 'activity' })

export interface NegotiationHistoryData {
    timeline: NegotiationHistoryTimelineItem[]
    totals: {
        total_quoted: number
        total_os: number
        total_deals_won: number
        quotes_count: number
        os_count: number
        deals_count: number
        activities_count: number
        messages_count: number
        avg_discount: number
    }
}

type ForgottenClientsCollection = ForgottenClientsData['customers'] & {
    current_page?: number
    last_page?: number
    per_page?: number
    total?: number
    from?: number | null
    to?: number | null
    meta?: ForgottenClientsData['meta']
    stats?: Partial<ForgottenClientsData['stats']>
}

// ─── API Functions ──────────────────────────────────

const BASE = '/crm-field'

// Constants
export const getFieldConstants = () => api.get(`${BASE}/constants`).then(r => r.data)

// Visit Checkins
export const getCheckins = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/checkins`, { params }).then(r => r.data)
export const doCheckin = (data: { customer_id: number; checkin_lat?: number; checkin_lng?: number; checkin_address?: string; notes?: string }) =>
    api.post(`${BASE}/checkins`, data).then(r => r.data)
export const doCheckout = (id: number, data?: { checkout_lat?: number; checkout_lng?: number; notes?: string }) =>
    api.put(`${BASE}/checkins/${id}/checkout`, data).then(r => r.data)

// Visit Routes
export const getRoutes = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/routes`, { params }).then(r => r.data)
export const createRoute = (data: { route_date: string; name?: string; notes?: string; stops: { customer_id: number; estimated_duration_minutes?: number; objective?: string }[] }) =>
    api.post(`${BASE}/routes`, data).then(r => r.data)
export const updateRoute = (id: number, data: Record<string, unknown>) =>
    api.put(`${BASE}/routes/${id}`, data).then(r => r.data)

// Visit Reports
export const getReports = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/reports`, { params }).then(r => r.data)
export const createReport = (data: Record<string, unknown>) =>
    api.post(`${BASE}/reports`, data).then(r => r.data)

// Portfolio Map
export const getPortfolioMap = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/portfolio-map`, { params }).then(r => r.data)

// Forgotten Clients
export const getForgottenClients = async (): Promise<ForgottenClientsData> => {
    const response = await api.get<{ data?: unknown }>(`${BASE}/forgotten-clients`)
    const payload = response.data
    const rawPayload = (payload != null && typeof payload === 'object' && !Array.isArray(payload))
        ? payload as {
            data?: ForgottenClientsData['customers']
            stats?: Partial<ForgottenClientsData['stats']>
            meta?: ForgottenClientsData['meta']
            current_page?: number
            last_page?: number
            per_page?: number
            total?: number
            from?: number | null
            to?: number | null
        }
        : null
    const customers = (Array.isArray(payload)
        ? payload
        : unwrapData<ForgottenClientsData['customers']>(response)) as ForgottenClientsCollection
    const stats = customers?.stats ?? rawPayload?.stats
    const meta = customers?.meta ?? rawPayload?.meta

    return {
        customers: Array.isArray(customers) ? customers : [],
        stats: {
            total_forgotten: stats?.total_forgotten ?? 0,
            critical: stats?.critical ?? 0,
            high: stats?.high ?? 0,
            medium: stats?.medium ?? 0,
            by_seller: stats?.by_seller ?? {},
            by_rating: stats?.by_rating ?? {},
        },
        meta: {
            current_page: customers?.current_page ?? meta?.current_page ?? rawPayload?.current_page,
            last_page: customers?.last_page ?? meta?.last_page ?? rawPayload?.last_page,
            per_page: customers?.per_page ?? meta?.per_page ?? rawPayload?.per_page,
            total: customers?.total ?? meta?.total ?? rawPayload?.total,
            from: customers?.from ?? meta?.from ?? rawPayload?.from,
            to: customers?.to ?? meta?.to ?? rawPayload?.to,
        },
    }
}

// Contact Policies
export const getPolicies = () => api.get(`${BASE}/policies`).then(r => r.data)
export const createPolicy = (data: Record<string, unknown>) =>
    api.post(`${BASE}/policies`, data).then(r => r.data)
export const updatePolicy = (id: number, data: Record<string, unknown>) =>
    api.put(`${BASE}/policies/${id}`, data).then(r => r.data)
export const deletePolicy = (id: number) => api.delete(`${BASE}/policies/${id}`)

// Smart Agenda
export const getSmartAgenda = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/smart-agenda`, { params }).then(r => r.data)

// Quick Notes
export const getQuickNotes = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/quick-notes`, { params }).then(r => r.data)
export const createQuickNote = (data: { customer_id: number; channel?: string; sentiment?: string; content: string; is_pinned?: boolean; tags?: string[] }) =>
    api.post(`${BASE}/quick-notes`, data).then(r => r.data)
export const updateQuickNote = (id: number, data: Record<string, unknown>) =>
    api.put(`${BASE}/quick-notes/${id}`, data).then(r => r.data)
export const deleteQuickNote = (id: number) => api.delete(`${BASE}/quick-notes/${id}`)

// Commitments
export const getCommitments = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/commitments`, { params }).then(r => r.data)
export const createCommitment = (data: Record<string, unknown>) =>
    api.post(`${BASE}/commitments`, data).then(r => r.data)
export const updateCommitment = (id: number, data: Record<string, unknown>) =>
    api.put(`${BASE}/commitments/${id}`, data).then(r => r.data)

// Negotiation History
export const getNegotiationHistory = (customerId: number) =>
    api.get(`${BASE}/customers/${customerId}/negotiation-history`).then(r => unwrapData<NegotiationHistoryData>(r))

// Client Summary
export const getClientSummary = (customerId: number) =>
    api.get(`${BASE}/customers/${customerId}/summary`).then(r => r.data)

// RFM
export const getRfmScores = () => api.get(`${BASE}/rfm`).then(r => r.data)
export const recalculateRfm = () => api.post(`${BASE}/rfm/recalculate`).then(r => r.data)

// Portfolio Coverage
export const getPortfolioCoverage = (params?: { period?: number }) =>
    api.get(`${BASE}/coverage`, { params }).then(r => r.data)

// Commercial Productivity
export const getCommercialProductivity = (params?: { period?: number }) =>
    api.get(`${BASE}/productivity`, { params }).then(r => r.data)

// Latent Opportunities
export const getLatentOpportunities = () =>
    api.get(`${BASE}/opportunities`).then(r => unwrapData<LatentOpportunitiesData>(r))

// Important Dates
export const getImportantDates = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/important-dates`, { params }).then(r => r.data)
export const createImportantDate = (data: Record<string, unknown>) =>
    api.post(`${BASE}/important-dates`, data).then(r => r.data)
export const updateImportantDate = (id: number, data: Record<string, unknown>) =>
    api.put(`${BASE}/important-dates/${id}`, data).then(r => r.data)
export const deleteImportantDate = (id: number) => api.delete(`${BASE}/important-dates/${id}`)

// Visit Surveys
export const getSurveys = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/surveys`, { params }).then(r => r.data)
export const sendSurvey = (data: { customer_id: number; checkin_id?: number }) =>
    api.post(`${BASE}/surveys`, data).then(r => r.data)

// Account Plans
export const getAccountPlans = (params?: Record<string, unknown>) =>
    api.get(`${BASE}/account-plans`, { params }).then(r => r.data)
export const createAccountPlan = (data: Record<string, unknown>) =>
    api.post(`${BASE}/account-plans`, data).then(r => r.data)
export const updateAccountPlan = (id: number, data: Record<string, unknown>) =>
    api.put(`${BASE}/account-plans/${id}`, data).then(r => r.data)
export const updateAccountPlanAction = (id: number, data: Record<string, unknown>) =>
    api.put(`${BASE}/account-plan-actions/${id}`, data).then(r => r.data)

// Gamification
export const getGamificationDashboard = () =>
    api.get(`${BASE}/gamification`).then(r => r.data)
export const recalculateGamification = () =>
    api.post(`${BASE}/gamification/recalculate`).then(r => r.data)
