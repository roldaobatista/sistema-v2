import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { Customer360Page } from '@/pages/cadastros/Customer360Page'
import { CrmForgottenClientsPage } from '@/pages/crm/CrmForgottenClientsPage'
import { CrmSmartAgendaPage } from '@/pages/crm/CrmSmartAgendaPage'
import { CrmOpportunitiesPage } from '@/pages/crm/CrmOpportunitiesPage'

const {
    mockNavigate,
    mockGetCachedCapsule,
    mockCacheCapsule,
    mockCustomer360,
    toastWarning,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockGetCachedCapsule: vi.fn(),
    mockCacheCapsule: vi.fn(),
    mockCustomer360: vi.fn(),
    toastWarning: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => ({ id: '15' }),
    }
})

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: () => true,
        hasRole: () => true,
    }),
}))

vi.mock('@/hooks/useOfflineStore', () => ({
    useOfflineStore: () => ({
        put: mockCacheCapsule,
        getById: mockGetCachedCapsule,
    }),
}))

vi.mock('@/lib/crm-api', () => ({
    crmApi: {
        getCustomer360: mockCustomer360,
        getPipelines: vi.fn().mockResolvedValue([{ id: 1, is_default: true, stages: [{ id: 10 }] }]),
    },
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: { delete: vi.fn() },
        getApiErrorMessage: (_error: unknown, fallback: string) => fallback,
        getApiOrigin: () => 'http://localhost:8000',
    }
})

vi.mock('@/lib/query-keys', () => ({
    queryKeys: {
        customers: {
            customer360: (id: number) => ['customers', '360', id],
        },
    },
}))

vi.mock('@/lib/crm-field-api', () => ({
    getForgottenClients: vi.fn().mockResolvedValue({
        stats: { total_forgotten: 1, critical: 1, high: 0, medium: 0, by_seller: {} },
        customers: [{ id: 9, name: 'Cliente Esquecido', urgency: 'critical', rating: 'A', address_city: 'Cuiaba', assigned_seller: { name: 'Vendedor' }, days_since_contact: 120 }],
    }),
    getSmartAgenda: vi.fn().mockResolvedValue([
        { id: 11, name: 'Cliente Agenda', days_since_contact: 20, max_days_allowed: 15, has_calibration_expiring: false, has_pending_quote: true, days_until_due: 2, suggested_action: 'Contato urgente', priority_score: 98, rating: 'B' },
    ]),
    getLatentOpportunities: vi.fn().mockResolvedValue({
        summary: { total: 1, calibration_expiring: 1, inactive_customers: 0, contract_renewals: 0 },
        opportunities: [{ type: 'calibration_expiring', customer: { id: 21, name: 'Cliente Oportunidade' }, detail: 'Calibração vence em 5 dias', priority: 'high' }],
    }),
}))

vi.mock('@/components/crm/CustomerHealthScore', () => ({
    CustomerHealthScore: () => <div>Health Score</div>,
}))

vi.mock('@/components/crm/CustomerTimeline', () => ({
    CustomerTimeline: () => <div>Timeline</div>,
}))

vi.mock('@/components/crm/ActivityForm', () => ({
    ActivityForm: () => null,
}))

vi.mock('@/components/crm/SendMessageModal', () => ({
    SendMessageModal: () => null,
}))

vi.mock('@/components/customers/CustomerEditSheet', () => ({
    CustomerEditSheet: () => null,
}))

vi.mock('@/components/customers/CustomerDocumentsTab', () => ({
    CustomerDocumentsTab: () => <div>Documentos</div>,
}))

vi.mock('@/components/inmetro/CustomerInmetroTab', () => ({
    CustomerInmetroTab: () => <div>INMETRO</div>,
}))

vi.mock('@/components/ui/badge', () => ({
    Badge: ({ children }: { children: ReactNode }) => <span>{children}</span>,
}))

vi.mock('@/components/ui/button', () => ({
    Button: ({ children, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: string; size?: string }) => <button {...props}>{children}</button>,
}))

vi.mock('recharts', () => ({
    ResponsiveContainer: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    BarChart: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    Bar: () => null,
    LineChart: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    Line: () => null,
    XAxis: () => null,
    YAxis: () => null,
    CartesianGrid: () => null,
    Tooltip: () => null,
    RadarChart: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    Legend: () => null,
    PolarGrid: () => null,
    PolarAngleAxis: () => null,
    Radar: () => null,
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
        warning: toastWarning,
    },
}))

describe('acessibilidade e resiliencia do Customer360 e CRM', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockGetCachedCapsule.mockResolvedValue({ id: 15, data: { invalid: true }, updated_at: '2026-03-20T10:00:00Z' })
        mockCustomer360.mockResolvedValue({
            customer: {
                name: 'Cliente 360',
                is_active: true,
                created_at: '2026-03-20T10:00:00Z',
                contacts: [],
            },
            health_breakdown: {},
            equipments: [],
            deals: [],
            timeline: [],
            work_orders: [{ id: 101, number: 'OS-101', created_at: '2026-03-20T10:00:00Z', status: 'Aberta', total: 120 }],
            service_calls: [{ id: 202, call_number: 'CH-202', priority: 'high', status: 'open', subject: 'Visita tecnica' }],
            quotes: [{ id: 303, quote_number: 'ORC-303', created_at: '2026-03-20T10:00:00Z', status: 'Pendente', total: 500 }],
            receivables: [],
            pending_receivables: 0,
            documents: [],
            fiscal_notes: [],
            metrics: {
                churn_risk: 'baixo',
                last_contact_days: 0,
                ltv: 0,
                conversion_rate: 0,
                forecast: [],
                trend: [],
                main_equipment_name: null,
                radar: [],
            },
        })
    })

    it('ignora capsula offline invalida e permite navegar por teclado nas linhas clicaveis', async () => {
        const user = userEvent.setup()
        render(<Customer360Page />)

        await user.click(await screen.findByRole('tab', { name: /serviços/i }))

        const workOrderRow = await screen.findByRole('link', { name: /abrir ordem de servico os-101/i })
        await user.type(workOrderRow, '{enter}')

        expect(mockNavigate).toHaveBeenCalledWith('/os/101')
        expect(toastWarning).toHaveBeenCalled()
    })

    it('expõe nome acessivel nos botoes de abrir Customer 360 em paginas CRM', async () => {
        render(
            <>
                <CrmForgottenClientsPage />
                <CrmSmartAgendaPage />
                <CrmOpportunitiesPage />
            </>
        )

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /abrir customer 360 de cliente esquecido/i })).toBeInTheDocument()
            expect(screen.getByRole('button', { name: /abrir customer 360 de cliente agenda/i })).toBeInTheDocument()
            expect(screen.getByRole('button', { name: /abrir customer 360 de cliente oportunidade/i })).toBeInTheDocument()
        })
    })
})
