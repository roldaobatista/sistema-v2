import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { WorkOrdersListPage } from '@/pages/os/WorkOrdersListPage'

const {
    mockHasPermission,
    mockNavigate,
    mockWorkOrderApi,
    mockApiGet,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
    mockWorkOrderApi: {
        list: vi.fn(),
        destroy: vi.fn(),
        updateStatus: vi.fn(),
        updateAssignee: vi.fn(),
        importCsv: vi.fn(),
        exportCsv: vi.fn(),
    },
    mockApiGet: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({ hasPermission: mockHasPermission }),
}))

vi.mock('@/lib/api', () => ({ default: { get: mockApiGet } }))

vi.mock('@/lib/work-order-api', () => ({
    workOrderApi: mockWorkOrderApi,
    getWorkOrderListStatusCounts: (data: Record<string, unknown> | null | undefined) => {
        const d = data as Record<string, unknown> | null | undefined
        return (d as Record<string, unknown>)?.status_counts ?? (d as Record<string, Record<string, unknown>>)?.meta?.status_counts ?? {}
    },
}))

vi.mock('@/lib/cross-tab-sync', () => ({ broadcastQueryInvalidation: vi.fn() }))
vi.mock('@/lib/utils', () => ({
    cn: (...args: unknown[]) => args.filter(Boolean).join(' '),
    formatCurrency: (v: number) => `R$ ${v.toFixed(2)}`,
    getApiErrorMessage: (_: unknown, fb: string) => fb,
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } }))

vi.mock('@/components/os/FavoriteButton', () => ({
    default: () => <span data-testid="favorite-btn" />,
}))

vi.mock('@/lib/status-config', () => ({
    workOrderStatus: {
        open: { label: 'Aberta', variant: 'info' },
        in_progress: { label: 'Em Andamento', variant: 'warning' },
        completed: { label: 'Concluida', variant: 'success' },
        cancelled: { label: 'Cancelada', variant: 'danger' },
    },
}))

const makeOrder = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    number: 'WO-001',
    os_number: null,
    business_number: null,
    description: 'Manutencao preventiva balanca',
    status: 'open',
    priority: 'normal',
    total: '1500.00',
    created_at: '2026-03-10T10:00:00Z',
    customer: { id: 1, name: 'Cliente Teste' },
    assignee: { id: 1, name: 'Tecnico A' },
    equipment: null,
    allowed_transitions: ['in_progress'],
    ...overrides,
})

function setupPermissions(perms: string[]) {
    mockHasPermission.mockImplementation((p: string) => perms.includes(p))
}

function setupListResponse(orders: ReturnType<typeof makeOrder>[] = [], meta = {}) {
    mockWorkOrderApi.list.mockResolvedValue({
        data: {
            data: orders,
            last_page: 1,
            total: orders.length,
            status_counts: { open: orders.filter(o => o.status === 'open').length },
            ...meta,
        },
    })
    mockApiGet.mockResolvedValue({ data: { data: [] } })
}

describe('WorkOrdersListPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        setupPermissions([
            'os.work_order.view', 'os.work_order.create',
            'os.work_order.delete', 'os.work_order.export',
            'os.work_order.change_status', 'os.work_order.update',
        ])
    })

    it('renders page title', async () => {
        setupListResponse([])
        render(<WorkOrdersListPage />)
        expect(screen.getByText('Ordens de Serviço')).toBeInTheDocument()
    })

    it('displays work orders list', async () => {
        setupListResponse([makeOrder(), makeOrder({ id: 2, number: 'WO-002', description: 'Calibracao' })])
        render(<WorkOrdersListPage />)
        await waitFor(() => {
            expect(screen.getByText('Manutencao preventiva balanca')).toBeInTheDocument()
            expect(screen.getByText('Calibracao')).toBeInTheDocument()
        })
    })

    it('shows loading skeleton while fetching', async () => {
        mockWorkOrderApi.list.mockReturnValue(new Promise(() => {}))
        mockApiGet.mockResolvedValue({ data: { data: [] } })
        render(<WorkOrdersListPage />)
        const skeletons = document.querySelectorAll('.animate-pulse')
        expect(skeletons.length).toBeGreaterThan(0)
    })

    it('shows empty state', async () => {
        setupListResponse([])
        render(<WorkOrdersListPage />)
        await waitFor(() => {
            expect(screen.getByText(/Nenhuma OS encontrada/i)).toBeInTheDocument()
        })
    })

    it('has Nova OS button', async () => {
        setupListResponse([])
        render(<WorkOrdersListPage />)
        await waitFor(() => {
            expect(screen.getByText('Nova OS')).toBeInTheDocument()
        })
    })

    it('has search input', async () => {
        setupListResponse([])
        render(<WorkOrdersListPage />)
        expect(screen.getByPlaceholderText(/Buscar OS, cliente/i)).toBeInTheDocument()
    })

    it('has status filter', async () => {
        setupListResponse([])
        render(<WorkOrdersListPage />)
        expect(screen.getByLabelText(/Filtrar por status/i)).toBeInTheDocument()
    })

    it('has priority filter', async () => {
        setupListResponse([])
        render(<WorkOrdersListPage />)
        expect(screen.getByLabelText(/Filtrar por prioridade/i)).toBeInTheDocument()
    })

    it('shows quick stats', async () => {
        setupListResponse([makeOrder()], { status_counts: { open: 5 } })
        render(<WorkOrdersListPage />)
        await waitFor(() => {
            expect(screen.getByText('Abertas')).toBeInTheDocument()
            expect(screen.getByText('Total')).toBeInTheDocument()
        })
    })

    it('shows permission denied when lacking view permission', async () => {
        setupPermissions([])
        setupListResponse([])
        render(<WorkOrdersListPage />)
        await waitFor(() => {
            expect(screen.getByText(/nao possui permissao para visualizar/i)).toBeInTheDocument()
        })
    })

    it('hides Nova OS button when lacking create permission', async () => {
        setupPermissions(['os.work_order.view'])
        setupListResponse([])
        render(<WorkOrdersListPage />)
        await waitFor(() => {
            expect(screen.queryByText('Nova OS')).not.toBeInTheDocument()
        })
    })

    it('shows pagination', async () => {
        setupListResponse([makeOrder()], { last_page: 5, total: 100 })
        render(<WorkOrdersListPage />)
        await waitFor(() => {
            expect(screen.getByText(/Página 1 de 5/)).toBeInTheDocument()
        })
    })
})
