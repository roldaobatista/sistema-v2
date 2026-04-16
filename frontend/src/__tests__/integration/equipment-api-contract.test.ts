import { beforeEach, describe, expect, it, vi } from 'vitest'

const { mockApi } = vi.hoisted(() => ({
    mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/lib/api', () => ({
    default: mockApi,
    unwrapData: (r: any) => {
        const d = r?.data
        if (d != null && typeof d === 'object' && 'data' in d) return (d as any).data
        return d
    },
}))

import { equipmentApi } from '@/lib/equipment-api'

describe('Equipment API Contract', () => {
    beforeEach(() => vi.clearAllMocks())

    it('list returns paginated structure', async () => {
        const response = {
            data: {
                data: [{ id: 1, code: 'EQ-001', brand: 'Toledo' }],
                meta: { current_page: 1, last_page: 1, total: 1 },
            },
        }
        mockApi.get.mockResolvedValue(response)
        const result = await equipmentApi.list({ page: 1 })
        expect(mockApi.get).toHaveBeenCalledWith('/equipments', { params: { page: 1 } })
        expect(result).toBeDefined()
    })

    it('detail returns single equipment', async () => {
        mockApi.get.mockResolvedValue({ data: { data: { id: 1, code: 'EQ-001' } } })
        const result = await equipmentApi.detail(1)
        expect(mockApi.get).toHaveBeenCalledWith('/equipments/1')
        expect(result).toBeDefined()
    })

    it('create sends POST', async () => {
        const payload = { type: 'Balanca', brand: 'Toledo', model: 'Prix 3' }
        mockApi.post.mockResolvedValue({ data: { data: { id: 1, ...payload } } })
        await equipmentApi.create(payload)
        expect(mockApi.post).toHaveBeenCalledWith('/equipments', payload)
    })

    it('update sends PUT', async () => {
        const payload = { brand: 'Mettler' }
        mockApi.put.mockResolvedValue({ data: { data: { id: 1 } } })
        await equipmentApi.update(1, payload)
        expect(mockApi.put).toHaveBeenCalledWith('/equipments/1', payload)
    })

    it('destroy sends DELETE', async () => {
        mockApi.delete.mockResolvedValue({})
        await equipmentApi.destroy(1)
        expect(mockApi.delete).toHaveBeenCalledWith('/equipments/1')
    })

    it('dashboard returns stats', async () => {
        mockApi.get.mockResolvedValue({
            data: { data: { total: 50, overdue: 3, due_7_days: 5 } },
        })
        const result = await equipmentApi.dashboard()
        expect(mockApi.get).toHaveBeenCalledWith('/equipments-dashboard')
        expect(result.total).toBeDefined()
    })

    it('export returns blob', async () => {
        mockApi.get.mockResolvedValue({ data: new Blob() })
        await equipmentApi.export()
        expect(mockApi.get).toHaveBeenCalledWith('/equipments-export', { responseType: 'blob' })
    })

    it('calibrationHistory returns array', async () => {
        mockApi.get.mockResolvedValue({
            data: { data: { calibrations: [{ id: 1, date: '2026-01-15' }] } },
        })
        const result = await equipmentApi.calibrationHistory(1)
        expect(mockApi.get).toHaveBeenCalledWith('/equipments/1/calibrations')
        expect(result).toBeDefined()
    })
})
