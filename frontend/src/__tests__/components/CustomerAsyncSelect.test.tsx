import { describe, it, expect, vi, beforeEach } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { CustomerAsyncSelect } from '@/components/common/CustomerAsyncSelect'

const {
    mockApiGet,
    mockCustomerDetail,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockCustomerDetail: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

    return {
        ...actual,
        default: {
            ...actual.default,
            get: mockApiGet,
        },
    }
})

vi.mock('@/lib/customer-api', () => ({
    customerApi: {
        detail: mockCustomerDetail,
    },
}))

vi.mock('@/lib/sentry', () => ({
    captureError: vi.fn(),
}))

vi.mock('sonner', () => ({
    toast: {
        error: vi.fn(),
        success: vi.fn(),
        warning: vi.fn(),
    },
}))

describe('CustomerAsyncSelect', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({
            data: {
                data: [
                    { id: 1, name: 'Alpha Comercio', document: '12.345.678/0001-90', phone: '(65) 99999-0001' },
                ],
            },
        })
        mockCustomerDetail.mockResolvedValue({
            id: 74,
            name: 'Cliente Print',
            document: '12.345.678/0001-90',
            phone: '(65) 99999-0001',
            address_city: 'Cuiaba',
            address_state: 'MT',
        })
    })

    it('pre-carrega o cliente inicial por id e exibe seu nome', async () => {
        render(
            <CustomerAsyncSelect
                label="Cliente"
                customerId={74}
                onChange={vi.fn()}
            />,
        )

        expect(await screen.findByText('Cliente Print')).toBeInTheDocument()
        expect(mockCustomerDetail).toHaveBeenCalledWith(74)
    })

    it('pesquisa cliente por nome ou documento usando o endpoint padrao', async () => {
        const user = userEvent.setup()

        render(<CustomerAsyncSelect label="Cliente" onChange={vi.fn()} />)

        await user.click(screen.getByRole('combobox', { name: 'Cliente' }))
        const searchInput = await screen.findByPlaceholderText('Buscar...')
        await user.type(searchInput, '12345678')

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenLastCalledWith('/customers', {
                params: { search: '12345678', per_page: 20 },
            })
        })
    })
})
