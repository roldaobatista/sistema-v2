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

import { crmApi } from '@/lib/crm-api'

describe('CRM API Contract', () => {
    beforeEach(() => vi.clearAllMocks())

    it('getPipelines returns array', async () => {
        mockApi.get.mockResolvedValue({
            data: [
                { id: 1, name: 'Sales', slug: 'sales', is_default: true, stages: [] },
            ],
        })
        const result = await crmApi.getPipelines()
        expect(mockApi.get).toHaveBeenCalledWith('/crm/pipelines')
        expect(Array.isArray(result)).toBe(true)
    })

    it('getDeals returns array', async () => {
        mockApi.get.mockResolvedValue({
            data: [{ id: 1, title: 'Deal 1', value: 5000, status: 'open' }],
        })
        const result = await crmApi.getDeals({ pipeline_id: 1 })
        expect(mockApi.get).toHaveBeenCalledWith('/crm/deals', { params: { pipeline_id: 1 } })
        expect(Array.isArray(result)).toBe(true)
    })

    it('getDeal returns single deal', async () => {
        mockApi.get.mockResolvedValue({
            data: { data: { id: 1, title: 'Deal 1', value: 5000 } },
        })
        const result = await crmApi.getDeal(1)
        expect(mockApi.get).toHaveBeenCalledWith('/crm/deals/1')
        expect(result).toBeDefined()
    })

    it('createDeal sends correct payload', async () => {
        const payload = { title: 'New Deal', value: 10000, customer_id: 1, pipeline_id: 1, stage_id: 1 }
        mockApi.post.mockResolvedValue({ data: { id: 1, ...payload } })
        await crmApi.createDeal(payload)
        expect(mockApi.post).toHaveBeenCalledWith('/crm/deals', payload)
    })

    it('updateDeal sends PUT', async () => {
        const payload = { title: 'Updated Deal' }
        mockApi.put.mockResolvedValue({ data: { id: 1 } })
        await crmApi.updateDeal(1, payload)
        expect(mockApi.put).toHaveBeenCalledWith('/crm/deals/1', payload)
    })

    it('updateDealStage sends correct stage_id', async () => {
        mockApi.put.mockResolvedValue({ data: {} })
        await crmApi.updateDealStage(1, 3)
        expect(mockApi.put).toHaveBeenCalledWith('/crm/deals/1/stage', { stage_id: 3 })
    })

    it('markDealWon sends PUT', async () => {
        mockApi.put.mockResolvedValue({ data: {} })
        await crmApi.markDealWon(1)
        expect(mockApi.put).toHaveBeenCalledWith('/crm/deals/1/won')
    })

    it('markDealLost sends reason', async () => {
        mockApi.put.mockResolvedValue({ data: {} })
        await crmApi.markDealLost(1, 'Budget cut')
        expect(mockApi.put).toHaveBeenCalledWith('/crm/deals/1/lost', { lost_reason: 'Budget cut' })
    })

    it('deleteDeal sends DELETE', async () => {
        mockApi.delete.mockResolvedValue({})
        await crmApi.deleteDeal(1)
        expect(mockApi.delete).toHaveBeenCalledWith('/crm/deals/1')
    })

    it('getDashboard returns KPI data', async () => {
        mockApi.get.mockResolvedValue({
            data: { data: { kpis: { open_deals: 10, won_month: 5 } } },
        })
        const result = await crmApi.getDashboard()
        expect(mockApi.get).toHaveBeenCalledWith('/crm/dashboard', { params: undefined })
        expect(result).toBeDefined()
    })
})
