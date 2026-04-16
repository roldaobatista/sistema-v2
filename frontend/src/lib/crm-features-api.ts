import api, { unwrapData } from './api'

// ─── Types ──────────────────────────────────────────

export interface CrmLeadScoringRule {
    id: number
    name: string
    field: string
    operator: string
    value: string
    points: number
    category: string
    is_active: boolean
    sort_order: number
}

export interface CrmLeadScore {
    id: number
    customer_id: number
    total_score: number
    score_breakdown: { rule_id: number; rule_name: string; points: number; field: string }[]
    grade: string
    calculated_at: string
    customer?: { id: number; name: string; email?: string; phone?: string; segment?: string; health_score?: number }
}

export interface CrmSequence {
    id: number
    name: string
    description: string | null
    status: string
    total_steps: number
    steps?: CrmSequenceStep[]
    enrollments_count?: number
    created_at: string
}

export interface CrmSequenceStep {
    id: number
    sequence_id: number
    step_order: number
    delay_days: number
    channel: string
    action_type: string
    template_id: number | null
    subject: string | null
    body: string | null
}

export interface CrmSequenceEnrollment {
    id: number
    sequence_id: number
    customer_id: number
    deal_id: number | null
    current_step: number
    status: string
    next_action_at: string | null
    started_at?: string | null
    completed_at?: string | null
    customer?: { id: number; name: string }
}

export interface CrmSmartAlert {
    id: number
    type: string
    priority: string
    title: string
    description: string | null
    status: string
    customer?: { id: number; name: string }
    deal?: { id: number; title: string }
    equipment?: { id: number; code: string; brand: string; model: string }
    assignee?: { id: number; name: string }
    metadata: Record<string, unknown> | null
    created_at: string
}

export interface CrmLossReason {
    id: number
    name: string
    category: string
    is_active: boolean
}

export interface CrmTerritory {
    id: number
    name: string
    description: string | null
    regions: string[] | null
    zip_code_ranges: string[] | null
    manager?: { id: number; name: string }
    members?: { id: number; user_id: number; role: string; user?: { id: number; name: string } }[]
    customers_count?: number
    is_active: boolean
}

export interface CrmSalesGoal {
    id: number
    user_id: number | null
    territory_id: number | null
    period_type: string
    period_start: string
    period_end: string
    target_revenue: number
    target_deals: number
    target_new_customers: number
    target_activities: number
    achieved_revenue: number
    achieved_deals: number
    achieved_new_customers: number
    achieved_activities: number
    user?: { id: number; name: string }
    territory?: { id: number; name: string }
}

export interface CrmContractRenewal {
    id: number
    customer_id: number
    deal_id: number | null
    contract_end_date: string
    status: string
    current_value: number
    renewal_value: number | null
    customer?: { id: number; name: string; contract_end?: string; contract_type?: string }
    deal?: { id: number; title: string; status: string }
}

export interface CrmWebForm {
    id: number
    name: string
    slug: string
    description: string | null
    fields: { name: string; type: string; label: string; required?: boolean }[]
    pipeline_id: number | null
    assign_to: number | null
    sequence_id: number | null
    redirect_url?: string | null
    success_message?: string | null
    is_active: boolean
    submissions_count: number
}

export interface CrmWebFormOptions {
    pipelines: Array<{ id: number; name: string }>
    sequences: Array<{ id: number; name: string; status: string }>
    users: Array<{ id: number; name: string }>
}

export interface CrmInteractiveProposal {
    id: number
    quote_id: number
    deal_id: number | null
    token: string
    status: string
    view_count: number
    time_spent_seconds: number
    first_viewed_at: string | null
    last_viewed_at: string | null
    accepted_at: string | null
    rejected_at: string | null
    expires_at: string | null
    quote?: { id: number; quote_number: string; total: number; status: string }
    deal?: { id: number; title: string }
}

export interface CrmTrackingEvent {
    id: number
    trackable_type: string
    trackable_id: number
    customer_id?: number | null
    deal_id?: number | null
    event_type: string
    ip_address: string | null
    metadata?: Record<string, unknown> | null
    customer?: { id: number; name: string }
    deal?: { id: number; title: string }
    created_at: string
}

export interface CrmTrackingStats {
    total_events: number
    by_type: Record<string, number>
}

export interface CrmReferral {
    id: number
    referrer_customer_id: number
    referred_customer_id: number | null
    referred_name: string
    referred_email: string | null
    referred_phone: string | null
    status: string
    reward_type: string | null
    reward_value: number | null
    reward_given: boolean
    notes?: string | null
    deal_id?: number | null
    referrer?: { id: number; name: string }
    referred?: { id: number; name: string }
    deal?: { id: number; title: string; status: string; value: number }
}

export interface CrmReferralOptions {
    customers: { id: number; name: string; document?: string | null }[]
    deals: { id: number; title: string; status: string; value: number; customer_id?: number | null; customer_name?: string | null }[]
}

export interface CrmReferralStats {
    total: number
    pending: number
    converted: number
    conversion_rate: number
    total_rewards: number
    total_reward_value: number
    top_referrers: Array<{
        id: number
        name: string
        count: number
        converted_count?: number
    }>
}

export interface CrmCalendarEvent {
    id: number | string
    title: string
    description?: string | null
    type: string
    start_at: string
    end_at: string
    all_day?: boolean
    location?: string | null
    customer?: { id: number; name: string }
    deal?: { id: number; title: string }
    user?: { id: number; name: string }
    color?: string | null
    is_activity?: boolean
    is_renewal?: boolean
    completed?: boolean
}

export interface CrmCalendarEventsResponse {
    events: CrmCalendarEvent[]
    activities: CrmCalendarEvent[]
    renewals: CrmCalendarEvent[]
}

export interface CrmDealCompetitor {
    competitor_name: string
    total_encounters: number
    wins: number
    losses: number
    win_rate: number
    avg_price: number
    our_avg_price: number
    price_diff: number | null
}

export interface CrmDealCompetitorEntry {
    id: number
    deal_id: number
    competitor_name: string
    competitor_price: number | null
    strengths: string | null
    weaknesses: string | null
    outcome: string | null
    deal?: {
        id: number
        title: string
        value: number
        status: string
        customer_id?: number | null
        customer?: { id: number; name: string } | null
    }
}

export interface CrmCompetitorOptions {
    deals: { id: number; title: string; status: string; value: number; customer_id?: number | null; customer_name?: string | null }[]
}

export interface CrmForecast {
    period_start: string
    period_end: string
    pipeline_value: number
    weighted_value: number
    best_case: number
    worst_case: number
    committed: number
    deal_count: number
    historical_win_rate: number
}

export interface CrmRevenueIntelligence {
    mrr: number
    contract_customers: number
    one_time_revenue: number
    churn_rate: number
    ltv: number
    avg_deal_value: number
    monthly_revenue: { month: string; revenue: number; deals: number }[]
    by_segment: { segment: string; revenue: number; deals: number }[]
}

export interface CrmGoalsDashboard {
    goals: CrmSalesGoal[]
    ranking: Array<{
        user?: { id: number; name: string }
        revenue_progress: number
        deals_progress: number
        target_revenue: number
        achieved_revenue: number
        target_deals: number
        achieved_deals: number
    }>
}

export interface CrmCohort {
    cohort: string
    created: number
    conversions: Record<string, number>
}

export interface CrmLossAnalytics {
    by_reason: { name: string; category: string; count: number; total_value: number }[]
    by_competitor: { competitor_name: string; count: number; total_value: number; avg_competitor_price: number }[]
    byUser: { name: string; count: number; total_value: number }[]
    monthly_trend: { month: string; count: number; total_value: number }[]
}

export interface CrmForecastResponse {
    forecast: CrmForecast[]
    historical_won: unknown[]
    period_type?: string
}

export interface CrmPipelineVelocity {
    avg_cycle_days: number
    avg_deal_value: number
    velocity_number: number
    win_rate: number
    total_deals: number
    stages: Array<{
        name: string
        deals_count: number
        total_value: number
        avg_days_in_stage: number
    }>
}

export interface CrmNpsStats {
    score: number
    total_responses: number
    promoters: number
    passives: number
    detractors: number
    by_month?: { month: string; score: number; responses: number }[]
    recent_feedback?: { customer_name: string; score: number; feedback: string; created_at: string }[]
}

export interface CrmFeaturesConstants {
    scoring_categories: Record<string, string>
    scoring_operators: string[]
    lead_grades: Record<string, { label: string; min: number; color: string }>
    sequence_statuses: Record<string, string>
    sequence_action_types: string[]
    enrollment_statuses: Record<string, string>
    alert_types: Record<string, string>
    alert_priorities: Record<string, string>
    loss_reason_categories: Record<string, string>
    competitor_outcomes: Record<string, string>
    territory_roles: Record<string, string>
    goal_period_types: Record<string, string>
    renewal_statuses: Record<string, string>
    proposal_statuses: Record<string, string>
    tracking_event_types: string[]
    referral_statuses: Record<string, string>
    referral_reward_types: Record<string, string>
    calendar_event_types: Record<string, string>
    forecast_period_types: Record<string, string>
}

export interface CrossSellRecommendation {
    type: 'cross_sell' | 'up_sell'
    title: string
    description: string
    estimated_value: number
    priority: string
}

const unwrapList = <T>(response: { data?: unknown }): T[] => {
    if (Array.isArray(response.data)) {
        return response.data as T[]
    }

    return unwrapData<T[]>(response) ?? []
}

const unwrapObject = <T>(response: { data?: unknown }): T => unwrapData<T>(response)

const normalizeLossAnalytics = (payload: CrmLossAnalytics & { by_user?: CrmLossAnalytics['byUser'] }): CrmLossAnalytics => ({
    by_reason: payload.by_reason ?? [],
    by_competitor: payload.by_competitor ?? [],
    byUser: payload.byUser ?? payload.by_user ?? [],
    monthly_trend: payload.monthly_trend ?? [],
})

// ─── API Functions ──────────────────────────────────

export const crmFeaturesApi = {
    // Constants
    getConstants: () => api.get<CrmFeaturesConstants>('/crm-features/constants'),

    // Lead Scoring
    getScoringRules: () => api.get('/crm-features/scoring/rules').then(unwrapList<CrmLeadScoringRule>),
    createScoringRule: (data: Partial<CrmLeadScoringRule>) => api.post<CrmLeadScoringRule>('/crm-features/scoring/rules', data),
    updateScoringRule: (id: number, data: Partial<CrmLeadScoringRule>) => api.put<CrmLeadScoringRule>(`/crm-features/scoring/rules/${id}`, data),
    deleteScoringRule: (id: number) => api.delete(`/crm-features/scoring/rules/${id}`),
    calculateScores: () => api.post('/crm-features/scoring/calculate'),
    getLeaderboard: (params?: Record<string, unknown>) => api.get('/crm-features/scoring/leaderboard', { params }).then(unwrapList<CrmLeadScore>),

    // Sequences
    getSequences: () => api.get('/crm-features/sequences').then(unwrapList<CrmSequence>),
    getSequence: (id: number) => api.get(`/crm-features/sequences/${id}`).then(unwrapObject<CrmSequence>),
    createSequence: (data: { name: string; description?: string; steps: Partial<CrmSequenceStep>[] }) => api.post<CrmSequence>('/crm-features/sequences', data),
    updateSequence: (id: number, data: Partial<CrmSequence>) => api.put<CrmSequence>(`/crm-features/sequences/${id}`, data),
    deleteSequence: (id: number) => api.delete(`/crm-features/sequences/${id}`),
    enrollInSequence: (data: { sequence_id: number; customer_id: number; deal_id?: number }) => api.post('/crm-features/sequences/enroll', data),
    cancelEnrollment: (id: number) => api.put(`/crm-features/enrollments/${id}/cancel`),
    getSequenceEnrollments: (sequenceId: number) => api.get(`/crm-features/sequences/${sequenceId}/enrollments`).then(unwrapList<CrmSequenceEnrollment>),

    // Email Tracking Stats (aggregated from tracking events)
    getEmailTrackingStats: () => api.get('/crm-features/tracking/stats').then(unwrapObject<CrmTrackingStats>),

    // Forecasting
    getForecast: (params?: { period?: string; months?: number }) =>
        api.get('/crm-features/forecast', { params }).then(unwrapObject<CrmForecastResponse>),
    createSnapshot: () => api.post('/crm-features/forecast/snapshot'),

    // Smart Alerts
    getSmartAlerts: (params?: Record<string, unknown>) => api.get('/crm-features/alerts', { params }).then(unwrapList<CrmSmartAlert>),
    acknowledgeAlert: (id: number) => api.put(`/crm-features/alerts/${id}/acknowledge`),
    resolveAlert: (id: number) => api.put(`/crm-features/alerts/${id}/resolve`),
    dismissAlert: (id: number) => api.put(`/crm-features/alerts/${id}/dismiss`),
    generateAlerts: () => api.post('/crm-features/alerts/generate'),

    // Cross-sell
    getRecommendations: (customerId: number) => api.get<CrossSellRecommendation[]>(`/crm-features/customers/${customerId}/recommendations`),

    // Loss Reasons
    getLossReasons: () => api.get('/crm-features/loss-reasons').then(unwrapList<CrmLossReason>),
    createLossReason: (data: Partial<CrmLossReason>) => api.post<CrmLossReason>('/crm-features/loss-reasons', data),
    updateLossReason: (id: number, data: Partial<CrmLossReason>) => api.put<CrmLossReason>(`/crm-features/loss-reasons/${id}`, data),
    getLossAnalytics: (params?: { months?: number }) =>
        api.get('/crm-features/loss-analytics', { params }).then((response) => normalizeLossAnalytics(unwrapObject(response))),

    // Territories
    getTerritories: () => api.get('/crm-features/territories').then(unwrapList<CrmTerritory>),
    createTerritory: (data: Partial<CrmTerritory> & { member_ids?: number[] }) => api.post<CrmTerritory>('/crm-features/territories', data),
    updateTerritory: (id: number, data: Partial<CrmTerritory> & { member_ids?: number[] }) => api.put<CrmTerritory>(`/crm-features/territories/${id}`, data),
    deleteTerritory: (id: number) => api.delete(`/crm-features/territories/${id}`),

    // Sales Goals
    getSalesGoals: (params?: Record<string, unknown>) =>
        api.get('/crm-features/goals', { params }).then(unwrapList<CrmSalesGoal>),
    getGoalsDashboard: () =>
        api.get('/crm-features/goals/dashboard').then(unwrapObject<CrmGoalsDashboard>),
    createSalesGoal: (data: Partial<CrmSalesGoal>) => api.post<CrmSalesGoal>('/crm-features/goals', data),
    updateSalesGoal: (id: number, data: Partial<CrmSalesGoal>) => api.put<CrmSalesGoal>(`/crm-features/goals/${id}`, data),
    recalculateGoals: () => api.post('/crm-features/goals/recalculate'),

    // Pipeline Velocity
    getPipelineVelocity: (params?: { months?: number; pipeline_id?: number }) =>
        api.get('/crm-features/velocity', { params }).then(unwrapObject<CrmPipelineVelocity>),

    // Contract Renewals
    getRenewals: (params?: Record<string, unknown>) =>
        api.get('/crm-features/renewals', { params }).then(unwrapList<CrmContractRenewal>),
    generateRenewals: () => api.post('/crm-features/renewals/generate'),
    updateRenewal: (id: number, data: Partial<CrmContractRenewal>) => api.put<CrmContractRenewal>(`/crm-features/renewals/${id}`, data),

    // Web Forms
    getWebForms: () => api.get('/crm-features/web-forms').then(unwrapList<CrmWebForm>),
    getWebFormOptions: () => api.get('/crm-features/web-forms/options').then(unwrapObject<CrmWebFormOptions>),
    createWebForm: (data: Partial<CrmWebForm>) => api.post('/crm-features/web-forms', data).then(unwrapObject<CrmWebForm>),
    updateWebForm: (id: number, data: Partial<CrmWebForm>) => api.put(`/crm-features/web-forms/${id}`, data).then(unwrapObject<CrmWebForm>),
    deleteWebForm: (id: number) => api.delete(`/crm-features/web-forms/${id}`),

    // Interactive Proposals
    getProposals: (params?: Record<string, unknown>) =>
        api.get('/crm-features/proposals', { params }).then(unwrapList<CrmInteractiveProposal>),
    createProposal: (data: { quote_id: number; deal_id?: number; expires_at?: string }) => api.post<CrmInteractiveProposal>('/crm-features/proposals', data),

    // Tracking
    getTrackingEvents: (params?: Record<string, unknown>) => api.get('/crm-features/tracking', { params }).then(unwrapList<CrmTrackingEvent>),

    // NPS
    getNpsStats: () =>
        api.get('/crm-features/nps/stats').then((response) => {
            const payload = unwrapObject<CrmNpsStats & { nps_score?: number }>(response)

            return {
                ...payload,
                score: payload.score ?? payload.nps_score ?? 0,
                by_month: payload.by_month ?? [],
                recent_feedback: payload.recent_feedback ?? [],
            } satisfies CrmNpsStats
        }),

    // Referrals
    getReferrals: (params?: Record<string, unknown>) => api.get('/crm-features/referrals', { params }).then(unwrapList<CrmReferral>),
    getReferralOptions: () => api.get('/crm-features/referrals/options').then(unwrapObject<CrmReferralOptions>),
    getReferralStats: () => api.get('/crm-features/referrals/stats').then(unwrapObject<CrmReferralStats>),
    createReferral: (data: Partial<CrmReferral>) => api.post<CrmReferral>('/crm-features/referrals', data),
    updateReferral: (id: number, data: Partial<CrmReferral>) => api.put<CrmReferral>(`/crm-features/referrals/${id}`, data),
    deleteReferral: (id: number) => api.delete(`/crm-features/referrals/${id}`),

    // Calendar
    getCalendarEvents: (params?: { start?: string; end?: string; user_id?: number }) =>
        api.get('/crm-features/calendar', { params }).then(unwrapObject<CrmCalendarEventsResponse>),
    createCalendarEvent: (data: Partial<CrmCalendarEvent>) => api.post<CrmCalendarEvent>('/crm-features/calendar', data),
    updateCalendarEvent: (id: number, data: Partial<CrmCalendarEvent>) => api.put<CrmCalendarEvent>(`/crm-features/calendar/${id}`, data),
    deleteCalendarEvent: (id: number) => api.delete(`/crm-features/calendar/${id}`),

    // Cohort Analysis
    getCohortAnalysis: (params?: { months?: number }) =>
        api.get('/crm-features/cohort', { params }).then(unwrapList<CrmCohort>),

    // Revenue Intelligence
    getRevenueIntelligence: () =>
        api.get('/crm-features/revenue-intelligence').then(unwrapObject<CrmRevenueIntelligence>),

    // Competitive Matrix
    getCompetitiveMatrix: (params?: { months?: number }) => api.get('/crm-features/competitors', { params }).then(unwrapList<CrmDealCompetitor>),
    getCompetitiveEntries: (params?: { months?: number; per_page?: number }) =>
        api.get('/crm-features/competitors', { params: { detailed: true, ...params } }).then(unwrapList<CrmDealCompetitorEntry>),
    getCompetitorOptions: () => api.get('/crm-features/competitors/options').then(unwrapObject<CrmCompetitorOptions>),
    addDealCompetitor: (data: { deal_id: number; competitor_name: string; competitor_price?: number; strengths?: string; weaknesses?: string }) => api.post('/crm-features/competitors', data),
    updateDealCompetitor: (id: number, data: Record<string, unknown>) => api.put(`/crm-features/competitors/${id}`, data),
    deleteDealCompetitor: (id: number) => api.delete(`/crm-features/competitors/${id}`),

    // Cross-Sell Recommendations (#13)
    getCrossSellRecommendations: (customerId: number) => api.get(`/crm-features/customers/${customerId}/recommendations`),

    // Deals CSV Import/Export (#15)
    exportDealsCsv: (params?: { pipeline_id?: number; status?: string }) =>
        api.get('/crm-features/deals/export-csv', { params, responseType: 'blob' }),
    importDealsCsv: (file: File) => {
        const formData = new FormData()
        formData.append('file', file)
        return api.post<{ imported: number; errors: string[] }>('/crm-features/deals/import-csv', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
    },

    // Activities for Calendar integration (#14)
    getActivitiesAsCalendarEvents: (params?: { start?: string; end?: string }) =>
        api.get('/crm-features/calendar/activities', { params }),
}
