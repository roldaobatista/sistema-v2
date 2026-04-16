import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { ServiceCallsPage } from '@/pages/chamados/ServiceCallsPage'

const {
    mockHasPermission,
    mockHasRole,
    mockNavigate,
    mockServiceCallApi,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockHasRole: vi.fn(),
    mockNavigate: vi.fn(),
    mockServiceCallApi: {
        list: vi.fn(),
        summary: vi.fn(),
        assignees: vi.fn(),
        destroy: vi.fn(),
        export: vi.fn(),
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
        hasRole: mockHasRole,
    }),
}))

vi.mock('@/lib/service-call-api', () => ({
    serviceCallApi: mockServiceCallApi,
}))

vi.mock('@/lib/cross-tab-sync', () => ({ broadcastQueryInvalidation: vi.fn() }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/lib/utils', () => ({
    cn: (...args: unknown[]) => args.filter(Boolean).join(' '),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('@/hooks/useDebounce', () => ({
    useDebounce: (val: string) => val,
}))

vi.mock('@/lib/status-config', () => ({
    serviceCallStatus: {
        pending_scheduling: { label: 'Pendente', variant: 'warning', icon: () => null },
        scheduled: { label: 'Agendado', variant: 'info', icon: () => null },
        converted_to_os: { label: 'Convertido', variant: 'success', icon: () => null },
        cancelled: { label: 'Cancelado', variant: 'danger', icon: () => null },
    },
    priorityConfig: {
        low: { label: 'Baixa', variant: 'default' },
        normal: { label: 'Normal', variant: 'info' },
        high: { label: 'Alta', variant: 'warning' },
        urgent: { label: 'Urgente', variant: 'danger' },
    },
    getStatusEntry: (_map: unknown, status: string) => ({
        label: status, variant: 'default', icon: () => null,
    }),
}))

const makeCall = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    call_number: 'CH-001',
    status: 'pending_scheduling',
    priority: 'normal',
    sla_breached: false,
    sla_remaining_minutes: 500,
    scheduled_date: null,
    created_at: '2026-03-10T10:00:00Z',
    customer: { id: 1, name: 'Cliente A' },
    technician: { id: 1, name: 'Tecnico X' },
    city: 'Sao Paulo',
    state: 'SP',
    ...overrides,
})

describe('ServiceCallsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockHasRole.mockReturnValue(false)
        mockServiceCallApi.assignees.mockResolvedValue({ technicians: [] })
        mockServiceCallApi.summary.mockResolvedValue({
            pending_scheduling: 5, scheduled: 3, rescheduled: 1,
            awaiting_confirmation: 2, converted_today: 1, sla_breached_active: 0,
        })
    })

    it('renders page title', async () => {
        mockServiceCallApi.list.mockResolvedValue({ data: { data: [], meta: {} } })
        render(<ServiceCallsPage />)
        expect(screen.getByText('Chamados Técnicos')).toBeInTheDocument()
    })

    it('displays service calls list', async () => {
        mockServiceCallApi.list.mockResolvedValue({
            data: { data: [makeCall(), makeCall({ id: 2, call_number: 'CH-002' })], meta: { current_page: 1, last_page: 1, total: 2 } },
        })
        render(<ServiceCallsPage />)
        await waitFor(() => {
            expect(screen.getByText('CH-001')).toBeInTheDocument()
            expect(screen.getByText('CH-002')).toBeInTheDocument()
        })
    })

    it('shows loading skeleton', async () => {
        mockServiceCallApi.list.mockReturnValue(new Promise(() => {}))
        render(<ServiceCallsPage />)
        const skeletons = document.querySelectorAll('.animate-pulse')
        expect(skeletons.length).toBeGreaterThan(0)
    })

    it('shows empty state', async () => {
        mockServiceCallApi.list.mockResolvedValue({ data: { data: [], meta: {} } })
        render(<ServiceCallsPage />)
        await waitFor(() => {
            expect(screen.getByText(/Nenhum chamado encontrado/i)).toBeInTheDocument()
        })
    })

    it('has search input', async () => {
        mockServiceCallApi.list.mockResolvedValue({ data: { data: [], meta: {} } })
        render(<ServiceCallsPage />)
        expect(screen.getByPlaceholderText(/Buscar por número ou cliente/i)).toBeInTheDocument()
    })

    it('has status filter', async () => {
        mockServiceCallApi.list.mockResolvedValue({ data: { data: [], meta: {} } })
        render(<ServiceCallsPage />)
        expect(screen.getByLabelText(/Filtrar por status/i)).toBeInTheDocument()
    })

    it('has priority filter', async () => {
        mockServiceCallApi.list.mockResolvedValue({ data: { data: [], meta: {} } })
        render(<ServiceCallsPage />)
        expect(screen.getByLabelText(/Filtrar por prioridade/i)).toBeInTheDocument()
    })

    it('has create button', async () => {
        mockServiceCallApi.list.mockResolvedValue({ data: { data: [], meta: {} } })
        render(<ServiceCallsPage />)
        await waitFor(() => {
            expect(screen.getByText('Novo Chamado')).toBeInTheDocument()
        })
    })
})
