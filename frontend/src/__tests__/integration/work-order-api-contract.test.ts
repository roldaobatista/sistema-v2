import { beforeEach, describe, expect, it, vi } from 'vitest'

const { mockApi } = vi.hoisted(() => ({
    mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/lib/api', () => ({ default: mockApi }))

import { workOrderApi, getWorkOrderListStatusCounts } from '@/lib/work-order-api'

describe('Work Order API Contract', () => {
    beforeEach(() => vi.clearAllMocks())

    it('list returns paginated structure', async () => {
        const response = {
            data: {
                data: [{ id: 1, number: 'WO-001', status: 'open' }],
                last_page: 1,
                total: 1,
                status_counts: { open: 1 },
            },
        }
        mockApi.get.mockResolvedValue(response)
        const result = await workOrderApi.list({ page: 1, per_page: 20 })
        expect(mockApi.get).toHaveBeenCalledWith('/work-orders', { params: { page: 1, per_page: 20 } })
        expect(result.data).toBeDefined()
    })

    it('create sends correct payload', async () => {
        const payload = { customer_id: 1, description: 'Repair', priority: 'normal' }
        mockApi.post.mockResolvedValue({ data: { data: { id: 1, ...payload } } })
        await workOrderApi.create(payload)
        expect(mockApi.post).toHaveBeenCalledWith('/work-orders', payload)
    })

    it('detail fetches single work order', async () => {
        mockApi.get.mockResolvedValue({ data: { data: { id: 1, number: 'WO-001' } } })
        await workOrderApi.detail(1)
        expect(mockApi.get).toHaveBeenCalledWith('/work-orders/1')
    })

    it('destroy sends DELETE', async () => {
        mockApi.delete.mockResolvedValue({})
        await workOrderApi.destroy(1)
        expect(mockApi.delete).toHaveBeenCalledWith('/work-orders/1')
    })

    it('updateStatus sends correct status payload', async () => {
        const payload = { status: 'in_progress', notes: 'Starting work' }
        mockApi.post.mockResolvedValue({ data: {} })
        await workOrderApi.updateStatus(1, payload)
        expect(mockApi.post).toHaveBeenCalledWith('/work-orders/1/status', payload)
    })

    it('updateAssignee sends PUT with assignee_id', async () => {
        mockApi.put.mockResolvedValue({ data: {} })
        await workOrderApi.updateAssignee(1, 5)
        expect(mockApi.put).toHaveBeenCalledWith('/work-orders/1', { assigned_to: 5 })
    })

    it('update sends full edit payload', async () => {
        const payload = { description: 'Updated description' }
        mockApi.put.mockResolvedValue({ data: {} })
        await workOrderApi.update(1, payload as any)
        expect(mockApi.put).toHaveBeenCalledWith('/work-orders/1', payload)
    })

    it('addItem sends POST to items endpoint', async () => {
        const item = { type: 'product', reference_id: 1, quantity: '2', unit_price: '50' }
        mockApi.post.mockResolvedValue({ data: {} })
        await workOrderApi.addItem(1, item as any)
        expect(mockApi.post).toHaveBeenCalledWith('/work-orders/1/items', item)
    })

    it('deleteItem sends DELETE', async () => {
        mockApi.delete.mockResolvedValue({})
        await workOrderApi.deleteItem(1, 10)
        expect(mockApi.delete).toHaveBeenCalledWith('/work-orders/1/items/10')
    })

    it('importCsv sends FormData', async () => {
        const formData = new FormData()
        mockApi.post.mockResolvedValue({ data: { data: { created: 5, errors: [] } } })
        await workOrderApi.importCsv(formData)
        expect(mockApi.post).toHaveBeenCalledWith('/work-orders-import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
    })

    it('exportCsv fetches blob', async () => {
        mockApi.get.mockResolvedValue({ data: new Blob() })
        await workOrderApi.exportCsv({ status: 'open' })
        expect(mockApi.get).toHaveBeenCalledWith('/work-orders-export', { params: { status: 'open' }, responseType: 'blob' })
    })

    it('getWorkOrderListStatusCounts extracts from top-level', () => {
        const counts = getWorkOrderListStatusCounts({ data: [], status_counts: { open: 5, completed: 3 } })
        expect(counts).toEqual({ open: 5, completed: 3 })
    })

    it('getWorkOrderListStatusCounts extracts from meta', () => {
        const counts = getWorkOrderListStatusCounts({ data: [], meta: { status_counts: { open: 2 } } })
        expect(counts).toEqual({ open: 2 })
    })

    it('getWorkOrderListStatusCounts returns empty for null', () => {
        expect(getWorkOrderListStatusCounts(null)).toEqual({})
    })
})
