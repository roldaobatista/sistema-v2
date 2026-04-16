import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import QuickProductServiceModal from '@/components/common/QuickProductServiceModal'

const {
    mockApiGet,
    mockApiPost,
    toastSuccess,
    toastError,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
    },
    getApiErrorMessage: (err: unknown, fallback: string) =>
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback,
    unwrapData: (response: { data?: unknown } | null | undefined) => {
        const payload = response?.data
        if (payload && typeof payload === 'object' && 'data' in payload) {
            return (payload as { data: unknown }).data
        }
        return payload
    },
}))

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

describe('QuickProductServiceModal', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/product-categories') {
                return Promise.resolve({
                    data: {
                        data: [{ id: 10, name: 'Categoria API' }],
                    },
                })
            }

            if (url === '/service-categories') {
                return Promise.resolve({
                    data: {
                        data: [{ id: 20, name: 'Instalação' }],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('renderiza categorias de produto vindas no envelope Laravel data.data', async () => {
        render(
            <QuickProductServiceModal
                open
                onOpenChange={vi.fn()}
            />
        )

        expect(await screen.findByRole('option', { name: 'Categoria API' })).toBeInTheDocument()
    })

    it('propaga o produto criado usando o payload real do backend', async () => {
        const user = userEvent.setup({ delay: null })
        const onCreated = vi.fn()
        const onOpenChange = vi.fn()

        mockApiPost.mockImplementation((url: string) => {
            if (url === '/products') {
                return Promise.resolve({
                    data: {
                        data: {
                            id: 7,
                            name: 'Produto API',
                            sell_price: '9.90',
                        },
                    },
                })
            }

            return Promise.resolve({ data: { data: { id: 99, name: 'Categoria' } } })
        })

        render(
            <QuickProductServiceModal
                open
                onOpenChange={onOpenChange}
                onCreated={onCreated}
            />
        )

        await user.type(screen.getByLabelText('Nome *'), 'Produto API')
        await user.click(screen.getByRole('button', { name: 'Salvar Produto' }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/products', expect.objectContaining({
                name: 'Produto API',
            }))
            expect(onCreated).toHaveBeenCalledWith({
                type: 'product',
                id: 7,
                name: 'Produto API',
                price: 9.9,
            })
            expect(onOpenChange).toHaveBeenCalledWith(false)
        })
    })

    it('propaga o servico criado usando o payload real do backend', async () => {
        const user = userEvent.setup({ delay: null })
        const onCreated = vi.fn()

        mockApiPost.mockImplementation((url: string) => {
            if (url === '/services') {
                return Promise.resolve({
                    data: {
                        data: {
                            id: 12,
                            name: 'Serviço API',
                            default_price: '120.50',
                        },
                    },
                })
            }

            return Promise.resolve({ data: { data: { id: 99, name: 'Categoria' } } })
        })

        render(
            <QuickProductServiceModal
                open
                defaultTab="service"
                onOpenChange={vi.fn()}
                onCreated={onCreated}
            />
        )

        await user.type(screen.getByLabelText('Nome *'), 'Serviço API')
        await user.click(screen.getByRole('button', { name: 'Salvar Serviço' }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/services', expect.objectContaining({
                name: 'Serviço API',
            }))
            expect(onCreated).toHaveBeenCalledWith({
                type: 'service',
                id: 12,
                name: 'Serviço API',
                price: 120.5,
            })
        })
    })
})
