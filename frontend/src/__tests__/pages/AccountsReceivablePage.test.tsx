import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { AccountsReceivablePage } from '@/pages/financeiro/AccountsReceivablePage'

const {
    mockHasPermission,
    mockHasRole,
    mockNavigate,
    mockFinancialApi,
    mockApiGet,
    mockApiPost,
    mockApiPut,
    mockApiDelete,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockHasRole: vi.fn(),
    mockNavigate: vi.fn(),
    mockFinancialApi: {
        receivables: {
            list: vi.fn(),
            summary: vi.fn(),
            create: vi.fn(),
            update: vi.fn(),
            destroy: vi.fn(),
            pay: vi.fn(),
            cancel: vi.fn(),
            generateFromOs: vi.fn(),
            generateInstallments: vi.fn(),
        },
        chartOfAccounts: {
            list: vi.fn(),
        },
    },
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockApiPut: vi.fn(),
    mockApiDelete: vi.fn(),
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
        post: mockApiPost,
        put: mockApiPut,
        delete: mockApiDelete,
    },
    getApiErrorMessage: (_err: unknown, fallback: string) => fallback,
    unwrapData: (r: { data?: { data?: unknown } | unknown } | null | undefined) => {
        const d = r?.data
        if (d != null && typeof d === 'object' && 'data' in d) return (d as { data: unknown }).data
        return d
    },
}))

vi.mock('@/lib/financial-api', () => ({
    financialApi: mockFinancialApi,
}))

vi.mock('@/lib/cross-tab-sync', () => ({
    broadcastQueryInvalidation: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('sonner', () => ({
    toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
}))

vi.mock('@/lib/constants', () => ({
    FINANCIAL_STATUS: {
        PENDING: 'pending',
        PARTIAL: 'partial',
        PAID: 'paid',
        OVERDUE: 'overdue',
        CANCELLED: 'cancelled',
        RENEGOTIATED: 'renegotiated',
    },
}))

vi.mock('@/components/financial/FinancialExportButtons', () => ({
    FinancialExportButtons: () => <div data-testid="export-buttons" />,
}))

vi.mock('@/components/common/LookupCombobox', () => ({
    LookupCombobox: (props: Record<string, unknown>) => (
        <select data-testid={`lookup-${props.lookupType}`} value={props.value as string} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => (props.onChange as (v: string) => void)(e.target.value)}>
            <option value="">Select</option>
        </select>
    ),
}))

vi.mock('@/components/common/CurrencyInput', () => ({
    CurrencyInput: (props: Record<string, unknown>) => <input data-testid="currency-input" value={props.value as string} onChange={(e: React.ChangeEvent<HTMLInputElement>) => (props.onChange as (v: number) => void)(Number(e.target.value))} />,
}))

const makeReceivable = (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    description: 'Servico de calibracao',
    amount: '1500.00',
    amount_paid: '0.00',
    due_date: '2026-04-15',
    status: 'pending',
    payment_method: 'pix',
    notes: '',
    chart_of_account_id: null,
    chart_of_account: null,
    customer: { id: 1, name: 'Cliente Teste' },
    work_order: null,
    payments: [],
    ...overrides,
})

function setupPermissions(perms: string[]) {
    mockHasPermission.mockImplementation((perm: string) => perms.includes(perm))
    mockHasRole.mockReturnValue(false)
}

function setupListResponse(records: ReturnType<typeof makeReceivable>[] = [], pagination = {}) {
    mockFinancialApi.receivables.list.mockResolvedValue({
        data: {
            data: records,
            current_page: 1,
            last_page: 1,
            total: records.length,
            ...pagination,
        },
    })
    mockFinancialApi.receivables.summary.mockResolvedValue({
        data: { pending: 5000, overdue: 2000, billed_this_month: 10000, paid_this_month: 8000, total_open: 7000 },
    })
    mockApiGet.mockResolvedValue({ data: { data: [] } })
}

describe('AccountsReceivablePage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        setupPermissions([
            'finance.receivable.view',
            'finance.receivable.create',
            'finance.receivable.update',
            'finance.receivable.delete',
            'finance.receivable.settle',
            'finance.chart.view',
        ])
    })

    it('renders page title', async () => {
        setupListResponse([makeReceivable()])
        render(<AccountsReceivablePage />)
        expect(screen.getByText('Contas a Receber')).toBeInTheDocument()
    })

    it('loads and displays receivables list', async () => {
        const records = [
            makeReceivable({ id: 1, description: 'Calibracao balanca' }),
            makeReceivable({ id: 2, description: 'Manutencao preventiva', customer: { id: 2, name: 'Empresa ABC' } }),
        ]
        setupListResponse(records)
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getByText('Calibracao balanca')).toBeInTheDocument()
            expect(screen.getByText('Manutencao preventiva')).toBeInTheDocument()
        })
    })

    it('shows loading state while fetching', async () => {
        mockFinancialApi.receivables.list.mockReturnValue(new Promise(() => {}))
        mockFinancialApi.receivables.summary.mockResolvedValue({ data: {} })
        mockApiGet.mockResolvedValue({ data: { data: [] } })
        render(<AccountsReceivablePage />)
        expect(screen.getByText('Carregando...')).toBeInTheDocument()
    })

    it('shows empty state when no records', async () => {
        setupListResponse([])
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getByText(/Nenhum título encontrado/i)).toBeInTheDocument()
        })
    })

    it('displays summary totals', async () => {
        setupListResponse([makeReceivable()])
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getAllByText('Pendente').length).toBeGreaterThanOrEqual(1)
            expect(screen.getAllByText('Vencido').length).toBeGreaterThanOrEqual(1)
        })
    })

    it('has a create new receivable button', async () => {
        setupListResponse([])
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getByText('Novo Título')).toBeInTheDocument()
        })
    })

    it('filters by status using select', async () => {
        setupListResponse([makeReceivable()])
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getByText('Todos os status')).toBeInTheDocument()
        })
        const select = screen.getByDisplayValue('Todos os status')
        expect(select).toBeInTheDocument()
    })

    it('has search input for customer/description', async () => {
        setupListResponse([makeReceivable()])
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getByPlaceholderText(/Buscar por descrição ou cliente/i)).toBeInTheDocument()
        })
    })

    it('shows pagination when multiple pages', async () => {
        setupListResponse([makeReceivable()], { last_page: 3, total: 150 })
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getByText(/150 registro/)).toBeInTheDocument()
            expect(screen.getByText('Anterior')).toBeInTheDocument()
            expect(screen.getByText('Próxima')).toBeInTheDocument()
        })
    })

    it('shows status badges with correct labels', async () => {
        const records = [
            makeReceivable({ id: 1, status: 'pending' }),
            makeReceivable({ id: 2, status: 'paid', description: 'Item pago' }),
        ]
        setupListResponse(records)
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getAllByText('Pendente').length).toBeGreaterThanOrEqual(1)
        })
    })

    it('shows permission denied message when user lacks view permission', async () => {
        setupPermissions(['finance.receivable.create'])
        mockFinancialApi.receivables.list.mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
        mockFinancialApi.receivables.summary.mockResolvedValue({ data: {} })
        mockApiGet.mockResolvedValue({ data: { data: [] } })
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.getByText(/nao possui permissao para listar/i)).toBeInTheDocument()
        })
    })

    it('hides create button when user lacks create permission', async () => {
        setupPermissions(['finance.receivable.view'])
        setupListResponse([])
        render(<AccountsReceivablePage />)
        await waitFor(() => {
            expect(screen.queryByText('Novo Título')).not.toBeInTheDocument()
        })
    })
})
