import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import EquipmentListPage from '@/pages/equipamentos/EquipmentListPage'

const {
    mockHasPermission,
    mockNavigate,
    mockEquipmentApi,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
    mockEquipmentApi: {
        list: vi.fn(),
        dashboard: vi.fn(),
        constants: vi.fn(),
        destroy: vi.fn(),
        export: vi.fn(),
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({ hasPermission: mockHasPermission }),
}))

vi.mock('@/lib/equipment-api', () => ({
    equipmentApi: mockEquipmentApi,
}))

vi.mock('@/lib/api', () => ({
    getApiErrorMessage: (_: unknown, fb: string) => fb,
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

const makeEquipment = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    code: 'EQ-001',
    type: 'Balanca',
    brand: 'Toledo',
    model: 'Prix 3',
    serial_number: 'SN123456',
    status: 'active',
    category: 'rodoviaria',
    is_critical: false,
    next_calibration_at: '2026-06-01',
    customer: { id: 1, name: 'Cliente A' },
    ...overrides,
})

function setupResponses(equipments: ReturnType<typeof makeEquipment>[] = []) {
    mockEquipmentApi.list.mockResolvedValue({
        data: equipments,
        meta: { current_page: 1, last_page: 1, total: equipments.length },
    })
    mockEquipmentApi.dashboard.mockResolvedValue({
        total: equipments.length,
        overdue: 0,
        due_7_days: 0,
        due_30_days: 0,
        critical_count: 0,
        by_category: {},
        by_status: {},
    })
    mockEquipmentApi.constants.mockResolvedValue({
        categories: { rodoviaria: 'Rodoviaria', industrial: 'Industrial' },
    })
}

describe('EquipmentListPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
    })

    it('renders page title', async () => {
        setupResponses([])
        render(<EquipmentListPage />)
        expect(screen.getByText('Equipamentos')).toBeInTheDocument()
    })

    it('displays equipment list', async () => {
        setupResponses([makeEquipment(), makeEquipment({ id: 2, code: 'EQ-002', brand: 'Mettler' })])
        render(<EquipmentListPage />)
        await waitFor(() => {
            expect(screen.getByText('EQ-001')).toBeInTheDocument()
            expect(screen.getByText('EQ-002')).toBeInTheDocument()
        })
    })

    it('shows empty state', async () => {
        setupResponses([])
        render(<EquipmentListPage />)
        await waitFor(() => {
            expect(screen.getByText(/Nenhum equipamento encontrado/i)).toBeInTheDocument()
        })
    })

    it('has search input', async () => {
        setupResponses([])
        render(<EquipmentListPage />)
        expect(screen.getByPlaceholderText(/Buscar por código/i)).toBeInTheDocument()
    })

    it('has status filter', async () => {
        setupResponses([])
        render(<EquipmentListPage />)
        expect(screen.getByLabelText(/Filtrar por status/i)).toBeInTheDocument()
    })

    it('has category filter', async () => {
        setupResponses([])
        render(<EquipmentListPage />)
        expect(screen.getByLabelText(/Filtrar por categoria/i)).toBeInTheDocument()
    })

    it('shows dashboard stats', async () => {
        setupResponses([makeEquipment()])
        render(<EquipmentListPage />)
        await waitFor(() => {
            expect(screen.getAllByText('Total Ativos').length).toBeGreaterThanOrEqual(1)
            expect(screen.getAllByText('Vencidos').length).toBeGreaterThanOrEqual(1)
        })
    })

    it('has new equipment button', async () => {
        setupResponses([])
        render(<EquipmentListPage />)
        expect(screen.getByText('Novo Equipamento')).toBeInTheDocument()
    })
})
