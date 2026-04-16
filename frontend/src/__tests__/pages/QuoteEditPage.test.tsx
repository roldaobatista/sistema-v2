import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { QuoteEditPage } from '@/pages/orcamentos/QuoteEditPage'
import type { Quote } from '@/types/quote'

const {
    mockApiGet,
    mockQuoteDetail,
    mockHasPermission,
    mockNavigate,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockQuoteDetail: vi.fn(),
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

vi.mock('@/lib/quote-api', () => ({
    quoteApi: {
        detail: mockQuoteDetail,
        update: vi.fn(),
        updateItem: vi.fn(),
        deleteItem: vi.fn(),
        addEquipmentItem: vi.fn(),
        addEquipment: vi.fn(),
        removeEquipment: vi.fn(),
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
        useParams: () => ({ id: '55' }),
    }
})

vi.mock('@/components/common/LookupCombobox', () => ({
    LookupCombobox: ({ label }: { label?: string }) => <div>{label}</div>,
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
        <div data-testid={`quote-edit-${type}-items`}>
            {items.map((item) => item.name).join(', ')}
        </div>
    ),
}))

describe('QuoteEditPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockQuoteDetail.mockResolvedValue({
            id: 55,
            tenant_id: 1,
            quote_number: 'Q-00055',
            revision: 1,
            customer_id: 1,
            seller_id: 1,
            status: 'draft',
            source: null,
            valid_until: null,
            discount_percentage: 0,
            discount_amount: 0,
            displacement_value: 0,
            subtotal: 0,
            total: 0,
            observations: null,
            internal_notes: null,
            payment_terms: null,
            payment_terms_detail: null,
            template_id: null,
            opportunity_id: null,
            currency: 'BRL',
            custom_fields: null,
            is_template: false,
            internal_approved_by: null,
            internal_approved_at: null,
            level2_approved_by: null,
            level2_approved_at: null,
            sent_at: null,
            approved_at: null,
            rejected_at: null,
            rejection_reason: null,
            last_followup_at: null,
            followup_count: 0,
            client_viewed_at: null,
            client_view_count: 0,
            is_installation_testing: false,
            created_at: '2026-03-20T00:00:00Z',
            updated_at: '2026-03-20T00:00:00Z',
            deleted_at: null,
            equipments: [{
                id: 999,
                tenant_id: 1,
                quote_id: 55,
                equipment_id: 10,
                description: null,
                sort_order: 0,
                created_at: '2026-03-20T00:00:00Z',
                updated_at: '2026-03-20T00:00:00Z',
                equipment: { id: 10, tag: 'EQ-10' },
                items: [],
            }],
        } satisfies Quote)
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/products') {
                return Promise.resolve({ data: { data: [{ id: 301, sell_price: 45 }] } })
            }

            if (url === '/services') {
                return Promise.resolve({ data: { data: [{ id: 401, default_price: 60 }] } })
            }

            if (url === '/customers/1') {
                return Promise.resolve({
                    data: {
                        data: {
                            id: 1,
                            equipments: [
                                {
                                    id: 10,
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

    it('normaliza itens de servico no formulario de novo item sem suppressions', async () => {
        const user = userEvent.setup()

        render(<QuoteEditPage />)

        await user.click(await screen.findByRole('button', { name: /adicionar item/i }))

        expect(await screen.findByTestId('quote-edit-service-items')).toHaveTextContent('Serviço #401')
    })

    it('mostra identificacao completa do equipamento na edicao do orcamento', async () => {
        render(<QuoteEditPage />)

        expect(await screen.findByText('Toledo - 9094 - SN-123 - 300,0 kg')).toBeInTheDocument()
    })
})
