import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { AccountsPayablePage } from '@/pages/financeiro/AccountsPayablePage'

const {
    mockHasPermission,
    mockHasRole,
    mockNavigate,
    mockFinancialApi,
    mockApiGet,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockHasRole: vi.fn(),
    mockNavigate: vi.fn(),
    mockFinancialApi: {
        payables: {
            list: vi.fn(),
            summary: vi.fn(),
            create: vi.fn(),
            update: vi.fn(),
            detail: vi.fn(),
            pay: vi.fn(),
            cancel: vi.fn(),
            destroy: vi.fn(),
        },
        payablesCategories: { list: vi.fn() },
        chartOfAccounts: { list: vi.fn() },
    },
    mockApiGet: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
        hasRole: mockHasRole,
    }),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
    getApiErrorMessage: (_: unknown, fallback: string) => fallback,
    unwrapData: (r: { data?: { data?: unknown } | unknown } | null | undefined) => {
        const d = r?.data
        if (d != null && typeof d === 'object' && 'data' in d) return (d as { data: unknown }).data
        return d
    },
}))

vi.mock('@/lib/financial-api', () => ({
    financialApi: mockFinancialApi,
}))

vi.mock('@/lib/cross-tab-sync', () => ({ broadcastQueryInvalidation: vi.fn() }))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } }))

vi.mock('@/lib/constants', () => ({
    FINANCIAL_STATUS: {
        PENDING: 'pending', PARTIAL: 'partial', PAID: 'paid',
        OVERDUE: 'overdue', CANCELLED: 'cancelled', RENEGOTIATED: 'renegotiated',
    },
}))

vi.mock('@/components/financial/FinancialExportButtons', () => ({
    FinancialExportButtons: () => <div data-testid="export-buttons" />,
}))

vi.mock('@/components/common/LookupCombobox', () => ({
    LookupCombobox: (props: Record<string, unknown>) => <select data-testid={`lookup-${props.lookupType}`} value={props.value as string} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => (props.onChange as (v: string) => void)(e.target.value)}><option value="">Select</option></select>,
}))

vi.mock('@/components/common/CurrencyInput', () => ({
    CurrencyInput: (props: Record<string, unknown>) => <input data-testid="currency-input" value={props.value as string} onChange={(e: React.ChangeEvent<HTMLInputElement>) => (props.onChange as (v: number) => void)(Number(e.target.value))} />,
}))

const makePayable = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    description: 'Conta de energia',
    amount: '800.00',
    amount_paid: '0.00',
    due_date: '2026-04-20',
    status: 'pending',
    payment_method: null,
    notes: '',
    supplier_id: 1,
    supplier_relation: { name: 'Fornecedor X' },
    category_id: 1,
    category_relation: { name: 'Utilidades' },
    chart_of_account_id: null,
    chart_of_account: null,
    payments: [],
    ...overrides,
})

function setupPermissions(perms: string[]) {
    mockHasPermission.mockImplementation((p: string) => perms.includes(p))
    mockHasRole.mockReturnValue(false)
}

function setupListResponse(records: ReturnType<typeof makePayable>[] = [], pagination = {}) {
    mockFinancialApi.payables.list.mockResolvedValue({
        data: { data: records, current_page: 1, last_page: 1, total: records.length, ...pagination },
    })
    mockFinancialApi.payables.summary.mockResolvedValue({
        data: { pending: 3000, overdue: 1000, recorded_this_month: 5000, paid_this_month: 2000, total_open: 4000 },
    })
    mockFinancialApi.payablesCategories.list.mockResolvedValue({ data: { data: [] } })
    mockApiGet.mockResolvedValue({ data: { data: [] } })
}

describe('AccountsPayablePage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        setupPermissions([
            'finance.payable.view', 'finance.payable.create',
            'finance.payable.update', 'finance.payable.delete',
            'finance.payable.settle', 'finance.chart.view',
        ])
    })

    it('renders page title', async () => {
        setupListResponse([])
        render(<AccountsPayablePage />)
        expect(screen.getByText('Contas a Pagar')).toBeInTheDocument()
    })

    it('loads and displays payables list', async () => {
        setupListResponse([makePayable(), makePayable({ id: 2, description: 'Aluguel escritorio' })])
        render(<AccountsPayablePage />)
        await waitFor(() => {
            expect(screen.getByText('Conta de energia')).toBeInTheDocument()
            expect(screen.getByText('Aluguel escritorio')).toBeInTheDocument()
        })
    })

    it('shows loading state', async () => {
        mockFinancialApi.payables.list.mockReturnValue(new Promise(() => {}))
        mockFinancialApi.payables.summary.mockResolvedValue({ data: {} })
        mockFinancialApi.payablesCategories.list.mockResolvedValue({ data: [] })
        mockApiGet.mockResolvedValue({ data: { data: [] } })
        render(<AccountsPayablePage />)
        expect(screen.getByText('Carregando...')).toBeInTheDocument()
    })

    it('shows empty state', async () => {
        setupListResponse([])
        render(<AccountsPayablePage />)
        await waitFor(() => {
            expect(screen.getByText(/Nenhuma conta encontrada/i)).toBeInTheDocument()
        })
    })

    it('displays summary cards', async () => {
        setupListResponse([makePayable()])
        render(<AccountsPayablePage />)
        await waitFor(() => {
            expect(screen.getAllByText('Pendente').length).toBeGreaterThanOrEqual(1)
            expect(screen.getAllByText('Vencido').length).toBeGreaterThanOrEqual(1)
            expect(screen.getAllByText('Total em Aberto').length).toBeGreaterThanOrEqual(1)
        })
    })

    it('has create button', async () => {
        setupListResponse([])
        render(<AccountsPayablePage />)
        await waitFor(() => {
            expect(screen.getByText('Nova Conta')).toBeInTheDocument()
        })
    })

    it('has search input', async () => {
        setupListResponse([])
        render(<AccountsPayablePage />)
        expect(screen.getByPlaceholderText(/Buscar descrição ou fornecedor/i)).toBeInTheDocument()
    })

    it('has status filter', async () => {
        setupListResponse([])
        render(<AccountsPayablePage />)
        expect(screen.getByDisplayValue('Todos os status')).toBeInTheDocument()
    })

    it('has export CSV button', async () => {
        setupListResponse([])
        render(<AccountsPayablePage />)
        await waitFor(() => {
            expect(screen.getByText('Exportar CSV')).toBeInTheDocument()
        })
    })

    it('shows permission denied when lacking view permission', async () => {
        setupPermissions(['finance.payable.create'])
        setupListResponse([])
        render(<AccountsPayablePage />)
        await waitFor(() => {
            expect(screen.getByText(/nao possui permissao para listar/i)).toBeInTheDocument()
        })
    })
})
