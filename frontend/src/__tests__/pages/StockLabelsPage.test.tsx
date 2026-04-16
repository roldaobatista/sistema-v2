import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import StockLabelsPage from '@/pages/estoque/StockLabelsPage'

const { mockApiGet, mockToast } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockToast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: vi.fn(),
    },
    unwrapData: <T,>(response: { data?: { data?: T } | T }) => {
        const payload = response.data
        if (payload && typeof payload === 'object' && 'data' in payload) {
            return payload.data as T
        }

        return payload as T
    },
}))

vi.mock('sonner', () => ({ toast: mockToast }))

describe('StockLabelsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/stock/labels/formats') {
                return Promise.resolve({
                    data: [
                        { key: 'pdf-100x50', name: 'PDF 100x50', width_mm: 100, height_mm: 50, output: 'pdf' },
                    ],
                })
            }

            if (url === '/product-categories') {
                return Promise.resolve({
                    data: [{ id: 7, name: 'Parafusos' }],
                })
            }

            if (url === '/products') {
                return Promise.resolve({
                    data: [{ id: 10, name: 'Parafuso M8', code: 'PRD-001', category: { id: 7, name: 'Parafusos' } }],
                })
            }

            return Promise.reject(new Error(`Unexpected GET ${url}`))
        })
    })

    it('renderiza formatos, categorias e produtos quando o client devolve arrays normalizados', async () => {
        render(<StockLabelsPage />)

        await waitFor(() => {
            expect(screen.getByRole('option', { name: 'PDF 100x50' })).toBeInTheDocument()
            expect(screen.getByRole('option', { name: 'Parafusos' })).toBeInTheDocument()
            expect(screen.getByText('Parafuso M8')).toBeInTheDocument()
            expect(screen.getByText('#PRD-001')).toBeInTheDocument()
        })
    })
})
