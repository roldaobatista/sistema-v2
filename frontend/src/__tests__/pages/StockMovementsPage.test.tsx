import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { StockMovementsPage } from '@/pages/estoque/StockMovementsPage'

const {
    mockHasPermission,
    mockStockApi,
    mockApiGet,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockStockApi: {
        movements: {
            list: vi.fn(),
            create: vi.fn(),
            importXml: vi.fn(),
        },
    },
    mockApiGet: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({ hasPermission: mockHasPermission }),
}))

vi.mock('@/lib/api', () => ({
    default: { get: mockApiGet, post: vi.fn() },
}))

vi.mock('@/lib/stock-api', () => ({
    stockApi: mockStockApi,
}))

vi.mock('@/lib/cross-tab-sync', () => ({ broadcastQueryInvalidation: vi.fn() }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } }))
vi.mock('@/lib/utils', () => ({
    cn: (...args: unknown[]) => args.filter(Boolean).join(' '),
}))

const makeMovement = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    type: 'entry',
    quantity: '10',
    unit_cost: '25.00',
    notes: '',
    reference: null,
    created_at: '2026-03-10T10:00:00Z',
    product: { id: 1, name: 'Parafuso M8', code: 'PRD-001' },
    warehouse: { id: 1, name: 'Deposito Central' },
    work_order: null,
    created_by_user: { name: 'Admin' },
    ...overrides,
})

function setupListResponse(movements: ReturnType<typeof makeMovement>[] = []) {
    mockStockApi.movements.list.mockResolvedValue({
        data: { data: movements, current_page: 1, last_page: 1 },
    })
    mockApiGet.mockResolvedValue({ data: { data: [] } })
}

describe('StockMovementsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
    })

    it('renders page title', async () => {
        setupListResponse([])
        render(<StockMovementsPage />)
        expect(screen.getByText('Movimentações de Estoque')).toBeInTheDocument()
    })

    it('displays movements list', async () => {
        setupListResponse([makeMovement(), makeMovement({ id: 2, product: { id: 2, name: 'Porca M8', code: 'PRD-002' }, type: 'exit' })])
        render(<StockMovementsPage />)
        await waitFor(() => {
            expect(screen.getByText('Parafuso M8')).toBeInTheDocument()
            expect(screen.getByText('Porca M8')).toBeInTheDocument()
        })
    })

    it('shows loading state', async () => {
        mockStockApi.movements.list.mockReturnValue(new Promise(() => {}))
        mockApiGet.mockResolvedValue({ data: { data: [] } })
        render(<StockMovementsPage />)
        expect(screen.getByText('Carregando...')).toBeInTheDocument()
    })

    it('shows empty state', async () => {
        setupListResponse([])
        render(<StockMovementsPage />)
        await waitFor(() => {
            expect(screen.getByText(/Nenhuma movimentação encontrada/i)).toBeInTheDocument()
        })
    })

    it('has search input', async () => {
        setupListResponse([])
        render(<StockMovementsPage />)
        expect(screen.getByPlaceholderText(/Buscar por produto/i)).toBeInTheDocument()
    })

    it('has type filter', async () => {
        setupListResponse([])
        render(<StockMovementsPage />)
        expect(screen.getByTitle(/Filtrar por tipo/i)).toBeInTheDocument()
    })

    it('shows entry type badge', async () => {
        setupListResponse([makeMovement({ type: 'entry' })])
        render(<StockMovementsPage />)
        await waitFor(() => {
            expect(screen.getByText('Entrada')).toBeInTheDocument()
        })
    })

    it('shows exit type badge', async () => {
        setupListResponse([makeMovement({ id: 2, type: 'exit' })])
        render(<StockMovementsPage />)
        await waitFor(() => {
            expect(screen.getByText('Saída')).toBeInTheDocument()
        })
    })
})
