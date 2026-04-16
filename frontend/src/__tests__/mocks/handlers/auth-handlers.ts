import { http, HttpResponse } from 'msw'

const API = '/api/v1'

export const authHandlers = [
    http.post(`${API}/login`, () =>
        HttpResponse.json({
            token: 'test-token',
            user: { id: 1, name: 'Test User', email: 'test@example.com', current_tenant_id: 1 },
        })
    ),
    http.post(`${API}/logout`, () => HttpResponse.json({ message: 'Logged out' })),
    http.get(`${API}/user`, () =>
        HttpResponse.json({
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            current_tenant_id: 1,
            tenant_id: 1,
        })
    ),
    http.get(`${API}/me`, () =>
        HttpResponse.json({
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            current_tenant_id: 1,
            tenant_id: 1,
        })
    ),
    http.get(`${API}/dashboard-stats`, () =>
        HttpResponse.json({
            open_os: 0,
            in_progress_os: 0,
            completed_month: 0,
            revenue_month: 0,
            pending_commissions: 0,
            expenses_month: 0,
            recent_os: [],
            top_technicians: [],
            eq_overdue: 0,
            eq_due_7: 0,
            eq_alerts: [],
            crm_open_deals: 0,
            crm_won_month: 0,
            crm_revenue_month: 0,
            crm_pending_followups: 0,
            crm_avg_health: 0,
            stock_low: 0,
            stock_out: 0,
            receivables_pending: 0,
            receivables_overdue: 0,
            payables_pending: 0,
            payables_overdue: 0,
            net_revenue: 0,
            sla_total: 0,
            sla_response_breached: 0,
            sla_resolution_breached: 0,
            monthly_revenue: Array.from({ length: 6 }, (_, i) => ({ month: `M${i + 1}`, total: 0 })),
            avg_completion_hours: 0,
        })
    ),
]
