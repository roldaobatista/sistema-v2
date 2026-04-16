import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { WorkOrderCreatePage } from '@/pages/os/WorkOrderCreatePage'

const {
    mockApiGet,
    mockHasPermission,
    mockNavigate,
    mockWorkOrderCreate,
    mockCanViewPrices,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
    mockWorkOrderCreate: vi.fn(),
    mockCanViewPrices: { canViewPrices: true },
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: { get: mockApiGet, post: vi.fn(), put: vi.fn(), delete: vi.fn() },
    }
})

vi.mock('@/lib/work-order-api', () => ({
    workOrderApi: { create: mockWorkOrderCreate },
}))

vi.mock('@/lib/customer-api', () => ({
    customerApi: { detail: vi.fn() },
}))

vi.mock('@/lib/cross-tab-sync', () => ({ broadcastQueryInvalidation: vi.fn() }))
vi.mock('@/lib/safe-array', () => ({
    safeArray: (data: unknown) => {
        if (Array.isArray(data)) return data
        const obj = data as Record<string, unknown> | null | undefined
        if (obj?.data && Array.isArray(obj.data)) return obj.data
        return []
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({ hasPermission: mockHasPermission }),
}))

vi.mock('@/hooks/usePriceGate', () => ({
    usePriceGate: () => mockCanViewPrices,
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useSearchParams: () => [new URLSearchParams(), vi.fn()],
    }
})

vi.mock('sonner', () => ({
    toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
}))

vi.mock('@/components/ui/async-select', () => ({
    AsyncSelect: (props: Record<string, unknown>) => (
        <div data-testid="async-select">
            <label>{props.label as string}</label>
            <input placeholder={props.placeholder as string} />
        </div>
    ),
}))

vi.mock('@/components/common/ItemSearchCombobox', () => ({
    ItemSearchCombobox: (props: Record<string, unknown>) => {
        const items = (props.items as Array<{ name: string }> | undefined) ?? []

        return (
            <div>
                <select data-testid="item-search" onChange={(e: React.ChangeEvent<HTMLSelectElement>) => (props.onSelect as (v: number) => void)(Number(e.target.value))}><option>Select</option></select>
                <div data-testid={`item-search-${props.type as string}`}>{items.map((item) => item.name).join(', ')}</div>
            </div>
        )
    },
}))

vi.mock('@/components/common/LookupCombobox', () => ({
    LookupCombobox: (props: Record<string, unknown>) => <select data-testid={`lookup-${props.lookupType}`} value={props.value as string} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => (props.onChange as (v: string) => void)(e.target.value)}><option value="">Select</option></select>,
}))

vi.mock('@/components/common/CurrencyInput', () => ({
    CurrencyInput: (props: Record<string, unknown>) => <input data-testid={`currency-${props.label}`} value={props.value as string} onChange={(e: React.ChangeEvent<HTMLInputElement>) => (props.onChange as (v: number) => void)(Number(e.target.value))} />,
    CurrencyInputInline: (props: Record<string, unknown>) => <input data-testid={`currency-inline-${props.title}`} value={props.value as string} onChange={(e: React.ChangeEvent<HTMLInputElement>) => (props.onChange as (v: number) => void)(Number(e.target.value))} />,
}))

vi.mock('@/components/common/PriceHistoryHint', () => ({
    default: () => <div data-testid="price-history-hint" />,
}))

vi.mock('@/lib/utils', () => ({
    cn: (...args: unknown[]) => args.filter(Boolean).join(' '),
    formatCurrency: (v: number) => `R$ ${v.toFixed(2)}`,
}))

describe('WorkOrderCreatePage (enhanced)', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/products') {
                return Promise.resolve({ data: { data: [{ id: 501, sell_price: '19.9' }] } })
            }

            if (url === '/services') {
                return Promise.resolve({ data: { data: [{ id: 601, default_price: '29.9' }] } })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('renders page title', () => {
        render(<WorkOrderCreatePage />)
        expect(screen.getByText('Nova Ordem de Serviço')).toBeInTheDocument()
    })

    it('renders customer selection', () => {
        render(<WorkOrderCreatePage />)
        expect(screen.getByText('Cliente *')).toBeInTheDocument()
    })

    it('renders priority select', () => {
        render(<WorkOrderCreatePage />)
        expect(screen.getByTitle('Prioridade')).toBeInTheDocument()
    })

    it('renders description textarea', () => {
        render(<WorkOrderCreatePage />)
        expect(screen.getByPlaceholderText(/Descreva o problema/i)).toBeInTheDocument()
    })

    it('renders submit button', () => {
        render(<WorkOrderCreatePage />)
        expect(screen.getByText('Abrir OS')).toBeInTheDocument()
    })

    it('renders cancel button that navigates back', async () => {
        const user = userEvent.setup()
        render(<WorkOrderCreatePage />)
        const cancelBtn = screen.getByText('Cancelar')
        await user.click(cancelBtn)
        expect(mockNavigate).toHaveBeenCalledWith('/os')
    })

    it('shows add item button', () => {
        render(<WorkOrderCreatePage />)
        expect(screen.getByText('Adicionar')).toBeInTheDocument()
    })

    it('shows empty items message initially', () => {
        render(<WorkOrderCreatePage />)
        expect(screen.getByText('Nenhum item adicionado')).toBeInTheDocument()
    })

    it('shows permission denied when user cannot create', () => {
        mockHasPermission.mockReturnValue(false)
        render(<WorkOrderCreatePage />)
        expect(screen.getByText(/nao possui permissao para criar/i)).toBeInTheDocument()
    })

    it('has technician select', () => {
        render(<WorkOrderCreatePage />)
        expect(screen.getByTitle('Técnico')).toBeInTheDocument()
    })

    it('normaliza itens de catalogo para o combobox sem depender de name opcional', async () => {
        const user = userEvent.setup()

        render(<WorkOrderCreatePage />)

        await user.click(screen.getByText('Adicionar'))

        await waitFor(() => {
            expect(screen.getByTestId('item-search-product')).toHaveTextContent('Produto #501')
        })
    })
})
