import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechCommissionsPage from '@/pages/tech/TechCommissionsPage'

const mockUser = { id: 1, name: 'Tech User' }

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: (selector?: (state: { user: typeof mockUser }) => unknown) => {
        const state = { user: mockUser }
        return typeof selector === 'function' ? selector(state) : state
    },
}))

vi.mock('@/lib/api', () => {
    const summaryData = { total_month: 1500, pending: 500, paid: 1000 }
    const eventsData = [
        { id: 1, notes: 'Comissao OS 100', commission_amount: 500, status: 'pending', created_at: '2026-03-01T00:00:00', work_order: { os_number: '100' }, rule: { name: 'Regra 10%' } },
        { id: 2, notes: 'Comissao OS 200', commission_amount: 1000, status: 'paid', created_at: '2026-03-02T00:00:00', work_order: { os_number: '200' }, rule: { name: 'Regra 15%' } },
    ]
    const settlementsData = [
        { id: 1, period: '2026-03', total_amount: 1500, paid_amount: 1000, status: 'paid', paid_at: '2026-03-15T00:00:00' },
    ]
    const disputesData = [
        { id: 1, reason: 'Valor incorreto no calculo', status: 'open', created_at: '2026-03-10T00:00:00', commission_event: { commission_amount: 500, work_order: { os_number: '100' } } },
    ]

    const mockApi = {
        get: vi.fn((url: string) => {
            if (url.includes('summary')) return Promise.resolve({ data: { data: summaryData } })
            if (url.includes('events')) return Promise.resolve({ data: { data: eventsData } })
            if (url.includes('settlements')) return Promise.resolve({ data: { data: settlementsData } })
            if (url.includes('disputes')) return Promise.resolve({ data: { data: disputesData } })
            return Promise.resolve({ data: { data: [] } })
        }),
        post: vi.fn(() => Promise.resolve({ data: { data: {} } })),
    }

    return {
        default: mockApi,
        getApiErrorMessage: (_err: unknown, fallback: string) => fallback,
        unwrapData: <T,>(res: { data: { data: T } }) => res.data.data,
    }
})

vi.mock('@/pages/financeiro/commissions/utils', () => ({
    getCommissionDisputeStatusLabel: (status: string) => status === 'open' ? 'Aberta' : status,
    normalizeCommissionDisputeStatus: (status: string) => status,
}))

describe('TechCommissionsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        localStorage.clear()
        window.history.pushState({}, 'Test', '/tech/comissoes')
    })

    it('renderiza o titulo da pagina', async () => {
        render(<TechCommissionsPage />, { route: '/tech/comissoes' })

        expect(screen.getByText('Comissoes')).toBeInTheDocument()
    })

    it('exibe os cards de resumo com valores', async () => {
        render(<TechCommissionsPage />, { route: '/tech/comissoes' })

        await waitFor(() => {
            const allText = document.body.textContent ?? ''
            expect(allText).toContain('1.500')
        })
    })

    it('exibe os filtros de periodo', () => {
        render(<TechCommissionsPage />, { route: '/tech/comissoes' })

        expect(screen.getByText('Mes Atual')).toBeInTheDocument()
        expect(screen.getByText('Mes Anterior')).toBeInTheDocument()
        expect(screen.getByText('Tudo')).toBeInTheDocument()
    })

    it('exibe as tabs de navegacao (Eventos, Fechamentos, Disputas)', () => {
        render(<TechCommissionsPage />, { route: '/tech/comissoes' })

        expect(screen.getByText('Eventos')).toBeInTheDocument()
        expect(screen.getByText('Fechamentos')).toBeInTheDocument()
        expect(screen.getByText('Disputas')).toBeInTheDocument()
    })

    it('exibe o botao Voltar', () => {
        render(<TechCommissionsPage />, { route: '/tech/comissoes' })

        expect(screen.getByText('Voltar')).toBeInTheDocument()
    })
})
