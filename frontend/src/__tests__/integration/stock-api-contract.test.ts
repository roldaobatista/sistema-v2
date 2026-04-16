import { beforeEach, describe, expect, it, vi } from 'vitest'

const { mockApi } = vi.hoisted(() => ({
    mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/lib/api', () => ({ default: mockApi }))

import { stockApi } from '@/lib/stock-api'

describe('Stock API Contract', () => {
    beforeEach(() => vi.clearAllMocks())

    it('movements.list returns paginated structure', async () => {
        mockApi.get.mockResolvedValue({
            data: { data: [{ id: 1, type: 'entry', quantity: '10' }], current_page: 1, last_page: 1 },
        })
        const result = await stockApi.movements.list({ page: 1, per_page: 25 })
        expect(mockApi.get).toHaveBeenCalledWith('/stock/movements', { params: { page: 1, per_page: 25 } })
        expect(result.data).toBeDefined()
    })

    it('movements.create sends POST', async () => {
        const payload = { product_id: 1, warehouse_id: 1, type: 'entry', quantity: '10' }
        mockApi.post.mockResolvedValue({ data: {} })
        await stockApi.movements.create(payload)
        expect(mockApi.post).toHaveBeenCalledWith('/stock/movements', payload)
    })

    it('movements.importXml sends FormData', async () => {
        const formData = new FormData()
        mockApi.post.mockResolvedValue({ data: { data: { imported: 5 } } })
        await stockApi.movements.importXml(formData)
        expect(mockApi.post).toHaveBeenCalledWith('/stock/import-xml', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
    })

    it('summary fetches stock summary', async () => {
        mockApi.get.mockResolvedValue({ data: { total_items: 500 } })
        await stockApi.summary()
        expect(mockApi.get).toHaveBeenCalledWith('/stock/summary')
    })

    it('kardex fetches product kardex', async () => {
        mockApi.get.mockResolvedValue({ data: { data: [] } })
        await stockApi.kardex(1, { from: '2026-01-01' })
        expect(mockApi.get).toHaveBeenCalledWith('/stock/products/1/kardex', { params: { from: '2026-01-01' } })
    })

    it('warehouses.list fetches warehouses', async () => {
        mockApi.get.mockResolvedValue({ data: { data: [] } })
        await stockApi.warehouses.list()
        expect(mockApi.get).toHaveBeenCalledWith('/warehouses', { params: undefined })
    })

    it('warehouses.create sends POST', async () => {
        const payload = { name: 'Deposito A' }
        mockApi.post.mockResolvedValue({ data: { id: 1 } })
        await stockApi.warehouses.create(payload)
        expect(mockApi.post).toHaveBeenCalledWith('/warehouses', payload)
    })

    it('transfers.create sends POST', async () => {
        const payload = { from_warehouse_id: 1, to_warehouse_id: 2, product_id: 1, quantity: 5 }
        mockApi.post.mockResolvedValue({ data: {} })
        await stockApi.transfers.create(payload)
        expect(mockApi.post).toHaveBeenCalledWith('/stock/transfers', payload)
    })
})
