import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@/__tests__/test-utils'
import { DashboardPage } from '@/pages/DashboardPage'

const {
    mockApiGet,
    mockHasPermission,
    mockNavigate,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
    },
    unwrapData: (response: { data?: { data?: unknown } | unknown }) => {
        const payload = response?.data
        if (payload && typeof payload === 'object' && 'data' in (payload as Record<string, unknown>)) {
            return (payload as { data?: unknown }).data
        }

        return payload
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
        user: { name: 'Operador' },
    }),
}))

vi.mock('@/hooks/useAppMode', () => ({
    useAppMode: () => ({
        currentMode: 'admin',
    }),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        Navigate: ({ to }: { to: string }) => <div>redirect:{to}</div>,
    }
})

describe('DashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockHasPermission.mockImplementation((permission: string) => [
            'platform.dashboard.view',
            'os.work_order.view',
            'os.work_order.create',
            'alerts.alert.view',
            'cadastros.customer.view',
            'customer.nps.view',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/dashboard-stats') {
                return Promise.resolve({
                    data: {
                        data: {
                            open_os: 0,
                            completed_month: 0,
                            revenue_month: 0,
                            prev_revenue_month: 0,
                            prev_open_os: 0,
                            prev_completed_month: 0,
                            in_progress_os: 0,
                            pending_commissions: 0,
                            stock_low: 0,
                            sla_response_breached: 0,
                            sla_resolution_breached: 0,
                            expenses_month: 0,
                            receivables_pending: 0,
                            receivables_overdue: 0,
                            payables_pending: 0,
                            payables_overdue: 0,
                            avg_completion_hours: 0,
                            recent_os: [],
                            top_technicians: [],
                            eq_alerts: [],
                            eq_overdue: 0,
                            eq_due_7: 0,
                            monthly_revenue: [],
                        },
                    },
                })
            }

            if (url === '/alerts/summary') {
                return Promise.resolve({ data: { critical: 0, high: 0, total_active: 0 } })
            }

            if (url === '/dashboard-nps') {
                return Promise.resolve({ data: { nps_score: 75, promoters: 80, total_responses: 12 } })
            }

            return Promise.resolve({ data: {} })
        })
    })

    it('redireciona o CTA de cliente para a rota existente de listagem', { timeout: 15000 }, async () => {
        render(<DashboardPage />)

        await screen.findByText(/bem-vindo ao kalibrium/i)
        const button = screen.getByRole('button', { name: /ir para cadastro de clientes/i })
        fireEvent.click(button)

        expect(mockNavigate).toHaveBeenCalledWith('/cadastros/clientes')
    })

    it('nao exibe CTA de nova OS sem permissao de criacao', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'platform.dashboard.view',
            'os.work_order.view',
            'cadastros.customer.view',
        ].includes(permission))

        render(<DashboardPage />)

        await screen.findByText(/bem-vindo ao kalibrium/i)
        expect(screen.queryByRole('button', { name: /^nova os$/i })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /criar os/i })).not.toBeInTheDocument()
    })

    it('nao consulta NPS quando o usuario nao possui permissao', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'platform.dashboard.view',
            'os.work_order.view',
            'alerts.alert.view',
            'cadastros.customer.view',
        ].includes(permission))

        render(<DashboardPage />)

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/dashboard-stats')
            expect(mockApiGet).toHaveBeenCalledWith('/alerts/summary')
        })

        expect(mockApiGet).not.toHaveBeenCalledWith('/dashboard-nps')
    })

    it('renderiza alertas e nps quando os endpoints respondem com payload envelopado', async () => {
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/dashboard-stats') {
                return Promise.resolve({
                    data: {
                        data: {
                            open_os: 1,
                            completed_month: 2,
                            revenue_month: 1000,
                            prev_revenue_month: 900,
                            prev_open_os: 0,
                            prev_completed_month: 1,
                            in_progress_os: 1,
                            pending_commissions: 0,
                            stock_low: 0,
                            sla_response_breached: 0,
                            sla_resolution_breached: 0,
                            expenses_month: 100,
                            receivables_pending: 0,
                            receivables_overdue: 0,
                            payables_pending: 0,
                            payables_overdue: 0,
                            avg_completion_hours: 2,
                            recent_os: [],
                            top_technicians: [],
                            eq_alerts: [],
                            eq_overdue: 0,
                            eq_due_7: 0,
                            monthly_revenue: [],
                        },
                    },
                })
            }

            if (url === '/alerts/summary') {
                return Promise.resolve({ data: { data: { critical: 3, high: 5, total_active: 8 } } })
            }

            if (url === '/dashboard-nps') {
                return Promise.resolve({ data: { data: { nps_score: 71, promoters: 76, total_responses: 14, avg_rating: 4.2 } } })
            }

            return Promise.resolve({ data: {} })
        })

        render(<DashboardPage />)

        expect(await screen.findByText('3')).toBeInTheDocument()
        expect(screen.getByText('8')).toBeInTheDocument()
        expect(screen.getByText('71')).toBeInTheDocument()
        expect(screen.getByText('14')).toBeInTheDocument()
        expect(screen.getByText('4.2')).toBeInTheDocument()
    })

    it('permite navegar pela lista de OS recentes via teclado quando o usuario tem permissao', async () => {
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/dashboard-stats') {
                return Promise.resolve({
                    data: {
                        data: {
                            open_os: 1,
                            completed_month: 2,
                            revenue_month: 1000,
                            prev_revenue_month: 900,
                            prev_open_os: 0,
                            prev_completed_month: 1,
                            in_progress_os: 1,
                            pending_commissions: 0,
                            stock_low: 0,
                            sla_response_breached: 0,
                            sla_resolution_breached: 0,
                            expenses_month: 100,
                            receivables_pending: 0,
                            receivables_overdue: 0,
                            payables_pending: 0,
                            payables_overdue: 0,
                            avg_completion_hours: 2,
                            recent_os: [
                                {
                                    id: 42,
                                    number: 'OS-0042',
                                    status: 'open',
                                    total: '150.00',
                                    customer: { name: 'Cliente Acessivel' },
                                    assignee: { name: 'Tecnico 1' },
                                },
                            ],
                            top_technicians: [],
                            eq_alerts: [],
                            eq_overdue: 0,
                            eq_due_7: 0,
                            monthly_revenue: [],
                        },
                    },
                })
            }

            if (url === '/alerts/summary') {
                return Promise.resolve({ data: { critical: 0, high: 0, total_active: 0 } })
            }

            if (url === '/dashboard-nps') {
                return Promise.resolve({ data: { nps_score: 75, promoters: 80, total_responses: 12 } })
            }

            return Promise.resolve({ data: {} })
        })

        render(<DashboardPage />)

        const rowButton = await screen.findByRole('button', { name: /abrir ordem de serviço os-0042 de cliente acessivel/i })

        fireEvent.keyDown(rowButton, { key: 'Enter' })

        expect(mockNavigate).toHaveBeenCalledWith('/os/42')
    })
})
