import { beforeEach, describe, expect, it, vi } from 'vitest'

import { crmFeaturesApi } from '@/lib/crm-features-api'
import { getForgottenClients, getLatentOpportunities, getNegotiationHistory } from '@/lib/crm-field-api'

const mockGet = vi.fn()

vi.mock('@/lib/api', () => ({
    default: {
        get: (...args: unknown[]) => mockGet(...args),
    },
    unwrapData: <T,>(response: { data?: { data?: T } | T }): T | undefined => {
        const payload = response?.data
        if (payload && typeof payload === 'object' && 'data' in payload) {
            return payload.data as T
        }
        return payload as T | undefined
    },
}))

describe('CRM features and field API normalization', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('normaliza forgotten clients com lista, stats e meta', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: [{ id: 10, name: 'Cliente A', urgency: 'critical', days_since_contact: 120 }],
                current_page: 1,
                last_page: 3,
                per_page: 25,
                total: 51,
                from: 1,
                to: 25,
                stats: {
                    total_forgotten: 51,
                    critical: 10,
                    high: 20,
                    medium: 21,
                },
            },
        })

        const response = await getForgottenClients()

        expect(mockGet).toHaveBeenCalledWith('/crm-field/forgotten-clients')
        expect(response.customers).toHaveLength(1)
        expect(response.stats.total_forgotten).toBe(51)
        expect(response.stats.by_seller).toEqual({})
        expect(response.meta?.last_page).toBe(3)
    })

    it('desenvelopa negotiation history com timeline unificada', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: {
                    totals: {
                        total_quoted: 1200,
                        total_os: 800,
                        total_deals_won: 3000,
                        quotes_count: 1,
                        os_count: 1,
                        deals_count: 1,
                        activities_count: 2,
                        messages_count: 1,
                        avg_discount: 50,
                    },
                    timeline: [
                        {
                            id: 90,
                            entry_type: 'activity',
                            type: 'email',
                            customer_id: 1,
                            deal_id: 7,
                            contact_id: null,
                            user_id: 3,
                            title: 'Follow-up por email',
                            description: 'Cliente respondeu.',
                            scheduled_at: null,
                            completed_at: null,
                            duration_minutes: null,
                            outcome: 'sucesso',
                            channel: 'email',
                            is_automated: true,
                            metadata: null,
                            created_at: '2026-03-13T10:00:00Z',
                            user: { id: 3, name: 'Comercial 1' },
                            deal: { id: 7, title: 'Deal A' },
                        },
                        {
                            id: 91,
                            entry_type: 'quote',
                            type: 'quote',
                            quote_number: 'ORC-1001',
                            total: 1200,
                            status: 'approved',
                            created_at: '2026-03-12T10:00:00Z',
                        },
                    ],
                },
            },
        })

        const response = await getNegotiationHistory(1)

        expect(mockGet).toHaveBeenCalledWith('/crm-field/customers/1/negotiation-history')
        expect(response.totals.activities_count).toBe(2)
        expect(response.timeline[0].entry_type).toBe('activity')
        expect(response.timeline[1].type).toBe('quote')
    })

    it('desenvelopa latent opportunities', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: {
                    opportunities: [
                        {
                            type: 'inactive_customer',
                            customer: { id: 8, name: 'Cliente Dormente' },
                            detail: 'Sem contato ha 140 dias',
                            priority: 'high',
                        },
                    ],
                    summary: {
                        calibration_expiring: 0,
                        inactive_customers: 1,
                        contract_renewals: 0,
                        total: 1,
                    },
                },
            },
        })

        const response = await getLatentOpportunities()

        expect(mockGet).toHaveBeenCalledWith('/crm-field/opportunities')
        expect(response.summary.total).toBe(1)
        expect(response.opportunities[0].customer?.name).toBe('Cliente Dormente')
    })

    it('desenvelopa revenue intelligence', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: {
                    mrr: 1200,
                    contract_customers: 4,
                    one_time_revenue: 500,
                    churn_rate: 2.5,
                    ltv: 8000,
                    avg_deal_value: 2000,
                    monthly_revenue: [],
                    by_segment: [],
                },
            },
        })

        const response = await crmFeaturesApi.getRevenueIntelligence()

        expect(mockGet).toHaveBeenCalledWith('/crm-features/revenue-intelligence')
        expect(response.contract_customers).toBe(4)
        expect(response.mrr).toBe(1200)
    })

    it('desenvelopa lista de metas', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: [{
                    id: 1,
                    user_id: 3,
                    territory_id: null,
                    period_type: 'monthly',
                    period_start: '2026-03-01',
                    period_end: '2026-03-31',
                    target_revenue: 1000,
                    target_deals: 2,
                    target_new_customers: 1,
                    target_activities: 10,
                    achieved_revenue: 400,
                    achieved_deals: 1,
                    achieved_new_customers: 0,
                    achieved_activities: 5,
                }],
            },
        })

        const response = await crmFeaturesApi.getSalesGoals({ period_type: 'monthly' })

        expect(mockGet).toHaveBeenCalledWith('/crm-features/goals', { params: { period_type: 'monthly' } })
        expect(response).toHaveLength(1)
        expect(response[0].target_revenue).toBe(1000)
    })

    it('desenvelopa lista de propostas', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: [{
                    id: 7,
                    quote_id: 9,
                    deal_id: null,
                    token: 'abc123',
                    status: 'sent',
                    view_count: 0,
                    time_spent_seconds: 0,
                    first_viewed_at: null,
                    last_viewed_at: null,
                    accepted_at: null,
                    rejected_at: null,
                    expires_at: null,
                }],
            },
        })

        const response = await crmFeaturesApi.getProposals()

        expect(mockGet).toHaveBeenCalledWith('/crm-features/proposals', { params: undefined })
        expect(response).toHaveLength(1)
        expect(response[0].token).toBe('abc123')
    })

    it('desenvelopa web form options', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: {
                    pipelines: [{ id: 1, name: 'Pipeline Comercial' }],
                    sequences: [{ id: 2, name: 'Cadencia Inicial', status: 'active' }],
                    users: [{ id: 3, name: 'Vendedor A' }],
                },
            },
        })

        const response = await crmFeaturesApi.getWebFormOptions()

        expect(mockGet).toHaveBeenCalledWith('/crm-features/web-forms/options')
        expect(response.pipelines[0].name).toBe('Pipeline Comercial')
        expect(response.users[0].name).toBe('Vendedor A')
    })

    it('desenvelopa tracking stats', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: {
                    total_events: 7,
                    by_type: {
                        email_opened: 4,
                        form_submitted: 3,
                    },
                },
            },
        })

        const response = await crmFeaturesApi.getEmailTrackingStats()

        expect(mockGet).toHaveBeenCalledWith('/crm-features/tracking/stats')
        expect(response.total_events).toBe(7)
        expect(response.by_type.form_submitted).toBe(3)
    })

    it('desenvelopa leaderboard paginado', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: [{
                    id: 1,
                    customer_id: 2,
                    total_score: 98,
                    score_breakdown: [],
                    grade: 'A',
                    calculated_at: '2026-03-13 10:00:00',
                }],
                current_page: 1,
                last_page: 1,
            },
        })

        const response = await crmFeaturesApi.getLeaderboard({ per_page: 10 })

        expect(mockGet).toHaveBeenCalledWith('/crm-features/scoring/leaderboard', { params: { per_page: 10 } })
        expect(response).toHaveLength(1)
        expect(response[0].total_score).toBe(98)
    })

    it('desenvelopa resposta composta do calendario', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: {
                    events: [{ id: 1, title: 'Reuniao', type: 'meeting', start_at: '2026-03-13 10:00:00', end_at: '2026-03-13 11:00:00' }],
                    activities: [{ id: 'activity-1', title: 'Ligacao', type: 'call', start_at: '2026-03-13 12:00:00', end_at: '2026-03-13 12:30:00' }],
                    renewals: [{ id: 'renewal-1', title: 'Contrato', type: 'contract_renewal', start_at: '2026-03-15', end_at: '2026-03-15' }],
                },
            },
        })

        const response = await crmFeaturesApi.getCalendarEvents({ start: '2026-03-01', end: '2026-03-31' })

        expect(mockGet).toHaveBeenCalledWith('/crm-features/calendar', { params: { start: '2026-03-01', end: '2026-03-31' } })
        expect(response.events).toHaveLength(1)
        expect(response.activities).toHaveLength(1)
        expect(response.renewals).toHaveLength(1)
    })

    it('desenvelopa referrals, stats e options', async () => {
        mockGet
            .mockResolvedValueOnce({
                data: {
                    data: [{ id: 4, referrer_customer_id: 7, referred_name: 'Indicado', referred_phone: null, status: 'pending', reward_type: null, reward_value: null, reward_given: false }],
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: {
                        total: 5,
                        pending: 3,
                        converted: 2,
                        conversion_rate: 40,
                        total_rewards: 120,
                        total_reward_value: 120,
                        top_referrers: [{ id: 7, name: 'Cliente A', count: 2 }],
                    },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: {
                        customers: [{ id: 7, name: 'Cliente A' }],
                        deals: [{ id: 1, title: 'Deal X', status: 'open', value: 500 }],
                    },
                },
            })

        const referrals = await crmFeaturesApi.getReferrals({ status: 'pending' })
        const stats = await crmFeaturesApi.getReferralStats()
        const options = await crmFeaturesApi.getReferralOptions()

        expect(referrals).toHaveLength(1)
        expect(stats.total).toBe(5)
        expect(options.customers[0].name).toBe('Cliente A')
    })

    it('normaliza forecast, loss analytics e nps', async () => {
        mockGet
            .mockResolvedValueOnce({
                data: {
                    data: {
                        forecast: [{
                            period_start: '2026-03-01',
                            period_end: '2026-03-31',
                            pipeline_value: 1000,
                            weighted_value: 700,
                            best_case: 900,
                            worst_case: 500,
                            committed: 300,
                            deal_count: 4,
                            historical_win_rate: 55,
                        }],
                        historical_won: [],
                        period_type: 'monthly',
                    },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: {
                        by_reason: [],
                        by_competitor: [],
                        by_user: [{ name: 'Vendedor A', count: 2, total_value: 1000 }],
                        monthly_trend: [],
                    },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: {
                        nps_score: 42,
                        total_responses: 10,
                        promoters: 6,
                        passives: 2,
                        detractors: 2,
                    },
                },
            })

        const forecast = await crmFeaturesApi.getForecast({ months: 6 })
        const lossAnalytics = await crmFeaturesApi.getLossAnalytics({ months: 6 })
        const nps = await crmFeaturesApi.getNpsStats()

        expect(forecast.forecast[0].pipeline_value).toBe(1000)
        expect(lossAnalytics.byUser[0].name).toBe('Vendedor A')
        expect(nps.score).toBe(42)
    })

    it('desenvelopa velocity do pipeline', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: {
                    avg_cycle_days: 12.5,
                    avg_deal_value: 3200,
                    velocity_number: 640,
                    win_rate: 37.5,
                    total_deals: 18,
                    stages: [
                        {
                            name: 'Qualificacao',
                            deals_count: 8,
                            total_value: 15000,
                            avg_days_in_stage: 4.2,
                        },
                    ],
                },
            },
        })

        const response = await crmFeaturesApi.getPipelineVelocity({ months: 6, pipeline_id: 3 })

        expect(mockGet).toHaveBeenCalledWith('/crm-features/velocity', { params: { months: 6, pipeline_id: 3 } })
        expect(response.total_deals).toBe(18)
        expect(response.stages[0].name).toBe('Qualificacao')
    })
})
