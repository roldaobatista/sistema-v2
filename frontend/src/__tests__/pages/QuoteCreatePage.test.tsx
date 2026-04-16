import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { QuoteCreatePage } from '@/pages/orcamentos/QuoteCreatePage'

const {
    mockApiGet,
    mockCustomerDetail,
    mockTemplates,
    mockHasPermission,
    mockNavigate,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockCustomerDetail: vi.fn(),
    mockTemplates: vi.fn(),
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
        },
    }
})

vi.mock('@/lib/customer-api', () => ({
    customerApi: {
        detail: mockCustomerDetail,
    },
}))

vi.mock('@/lib/quote-api', () => ({
    quoteApi: {
        templates: mockTemplates,
        create: vi.fn(),
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useSearchParams: () => [new URLSearchParams('customer_id=1'), vi.fn()],
    }
})

vi.mock('@/components/ui/async-select', () => ({
    AsyncSelect: ({ label }: { label?: string }) => <div>{label}</div>,
}))

vi.mock('@/components/common/LookupCombobox', () => ({
    LookupCombobox: ({ label }: { label?: string }) => <div>{label}</div>,
}))

vi.mock('@/components/common/QuickEquipmentModal', () => ({
    default: () => null,
}))

vi.mock('@/components/common/QuickProductServiceModal', () => ({
    default: () => null,
}))

vi.mock('@/components/common/PriceHistoryHint', () => ({
    default: () => null,
}))

vi.mock('@/components/common/CurrencyInput', () => ({
    CurrencyInput: () => <input aria-label="currency-input" />,
}))

vi.mock('@/components/common/DiscountInput', () => ({
    DiscountInput: () => <input aria-label="discount-input" />,
}))

vi.mock('@/components/common/ItemSearchCombobox', () => ({
    ItemSearchCombobox: ({ items, type }: { items: Array<{ id: number; name: string }>; type: 'product' | 'service' }) => (
        <div data-testid={`quote-create-${type}-items`}>
            {items.map((item) => item.name).join(', ')}
        </div>
    ),
}))

describe('QuoteCreatePage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockTemplates.mockResolvedValue([])
        mockCustomerDetail.mockResolvedValue({
            id: 1,
            name: 'Cliente Exemplo',
            document: '00.000.000/0001-00',
        })
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/settings') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/users') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/products') {
                return Promise.resolve({ data: { data: [{ id: 101, sell_price: 25 }] } })
            }

            if (url === '/services') {
                return Promise.resolve({ data: { data: [{ id: 201, default_price: 30 }] } })
            }

            if (url === '/customers/1') {
                return Promise.resolve({
                    data: {
                        data: {
                            id: 1,
                            name: 'Cliente Exemplo',
                            document: '00.000.000/0001-00',
                            equipments: [
                                {
                                    id: 99,
                                    manufacturer: 'Toledo',
                                    model: '9094',
                                    serial_number: 'SN-123',
                                    capacity: '300',
                                    capacity_unit: 'kg',
                                    resolution: '0.2',
                                },
                            ],
                        },
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('normaliza itens de catalogo para o combobox sem depender de name opcional', async () => {
        const user = userEvent.setup()

        render(<QuoteCreatePage />)

        await user.click(await screen.findByRole('button', { name: /próximo/i }))
        await user.click(await screen.findByRole('button', { name: /toledo - 9094 - sn-123 - 300,0 kg/i }))

        expect(await screen.findByTestId('quote-create-product-items')).toHaveTextContent('Produto #101')
        expect(screen.getByTestId('quote-create-service-items')).toHaveTextContent('Serviço #201')
    })
})
