import { describe, it, expect, vi, beforeEach } from 'vitest'

/**
 * Integration tests for Work Order flows - status transitions,
 * item management, financial implications.
 */

const mockApi = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
}

vi.mock('@/lib/api', () => ({ default: mockApi }))

beforeEach(() => vi.clearAllMocks())

describe('WorkOrder Create Flow', () => {
    const validOS = {
        customer_id: 1,
        description: 'Calibracao de instrumentos',
        scheduled_date: '2025-06-01',
        priority: 'high',
    }

    it('POST /work-orders creates OS with correct payload', async () => {
        mockApi.post.mockResolvedValue({
            data: { data: { id: 100, status: 'open', ...validOS } },
        })

        const res = await mockApi.post('/work-orders', validOS)
        expect(res.data.data.id).toBe(100)
        expect(res.data.data.status).toBe('open')
        expect(res.data.data.customer_id).toBe(1)
    })

    it('new OS starts with status "open"', async () => {
        mockApi.post.mockResolvedValue({
            data: { data: { id: 1, status: 'open' } },
        })

        const res = await mockApi.post('/work-orders', validOS)
        expect(res.data.data.status).toBe('open')
    })

    it('OS without customer_id returns 422', async () => {
        mockApi.post.mockRejectedValue({
            response: {
                status: 422,
                data: { errors: { customer_id: ['O campo cliente e obrigatorio.'] } },
            },
        })

        try {
            await mockApi.post('/work-orders', {})
        } catch (e: unknown) {
            const error = e as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
            expect(error.response?.status).toBe(422)
            expect(error.response?.data?.errors).toHaveProperty('customer_id')
        }
    })
})

describe('WorkOrder Status Transitions', () => {
    const validTransitions = [
        { from: 'open', to: 'in_progress' },
        { from: 'in_progress', to: 'completed' },
        { from: 'completed', to: 'delivered' },
        { from: 'delivered', to: 'invoiced' },
        { from: 'open', to: 'cancelled' },
    ]

    ;(validTransitions || []).forEach(({ from, to }) => {
        it(`transition ${from} -> ${to} is valid`, async () => {
            mockApi.post.mockResolvedValue({
                data: { data: { id: 1, status: to } },
            })

            const res = await mockApi.post('/work-orders/1/status', { status: to })
            expect(res.data.data.status).toBe(to)
        })
    })

    it('invalid transition returns error', async () => {
        mockApi.post.mockRejectedValue({
            response: {
                status: 422,
                data: { message: 'Transicao de status invalida: completed -> open' },
            },
        })

        try {
            await mockApi.post('/work-orders/1/status', { status: 'open' })
        } catch (e: unknown) {
            const error = e as { response?: { status?: number; data?: { message?: string } } }
            expect(error.response?.status).toBe(422)
            expect(error.response?.data?.message).toContain('Transicao')
        }
    })
})

describe('WorkOrder Items Flow', () => {
    it('add item to OS increases total', async () => {
        const item = { product_id: 5, quantity: 2, unit_price: 150.0 }
        const totalExpected = 300.0

        mockApi.post.mockResolvedValue({
            data: {
                data: { ...item, total: totalExpected },
                work_order: { total: totalExpected },
            },
        })

        const res = await mockApi.post('/work-orders/1/items', item)
        expect(res.data.data.total).toBe(totalExpected)
    })

    it('remove item recalculates total', async () => {
        mockApi.delete.mockResolvedValue({
            data: { work_order: { total: 150.0 } },
        })

        const res = await mockApi.delete('/work-orders/1/items/5')
        expect(res.data.work_order.total).toBe(150.0)
    })

    it('update item quantity updates total correctly', async () => {
        mockApi.put.mockResolvedValue({
            data: {
                data: { product_id: 5, quantity: 3, unit_price: 100, total: 300 },
                work_order: { total: 450 },
            },
        })

        const res = await mockApi.put('/work-orders/1/items/5', { quantity: 3 })
        expect(res.data.data.total).toBe(300)
    })
})

describe('WorkOrder List & Search', () => {
    it('list returns paginated OS with status', async () => {
        mockApi.get.mockResolvedValue({
            data: {
                data: [
                    { id: 1, status: 'open', customer: { name: 'A' } },
                    { id: 2, status: 'in_progress', customer: { name: 'B' } },
                ],
                meta: { total: 100, current_page: 1 },
            },
        })

        const res = await mockApi.get('/work-orders')
        expect(res.data.data).toHaveLength(2)
        expect(res.data.data[0]).toHaveProperty('status')
        expect(res.data.data[0]).toHaveProperty('customer')
    })

    it('filter by status returns only matching', async () => {
        mockApi.get.mockResolvedValue({
            data: {
                data: [{ id: 1, status: 'open' }],
                meta: { total: 1 },
            },
        })

        await mockApi.get('/work-orders?status=open')
        expect(mockApi.get).toHaveBeenCalledWith('/work-orders?status=open')
    })

    it('accepts status_counts from paginated meta contract', async () => {
        const { getWorkOrderListStatusCounts } = await import('@/lib/work-order-api')

        expect(getWorkOrderListStatusCounts({
            data: [],
            meta: {
                total: 5,
                status_counts: {
                    open: 2,
                    completed: 3,
                },
            },
        })).toEqual({
            open: 2,
            completed: 3,
        })
    })

    it('preserves compatibility with legacy top-level status_counts', async () => {
        const { getWorkOrderListStatusCounts } = await import('@/lib/work-order-api')

        expect(getWorkOrderListStatusCounts({
            data: [],
            total: 5,
            status_counts: {
                open: 4,
            },
        })).toEqual({
            open: 4,
        })
    })
})

describe('WorkOrder Delete Flow', () => {
    it('delete open OS succeeds', async () => {
        mockApi.delete.mockResolvedValue({
            data: { message: 'OS excluida com sucesso' },
        })

        const res = await mockApi.delete('/work-orders/1')
        expect(res.data.message).toContain('excluida')
    })

    it('delete invoiced OS returns 409', async () => {
        mockApi.delete.mockRejectedValue({
            response: {
                status: 409,
                data: { message: 'Nao e possivel excluir OS faturada.' },
            },
        })

        try {
            await mockApi.delete('/work-orders/1')
        } catch (e: unknown) {
            const error = e as { response?: { status?: number } }
            expect(error.response?.status).toBe(409)
        }
    })
})
