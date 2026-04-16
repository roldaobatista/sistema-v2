import { beforeEach, describe, expect, it, vi } from 'vitest'

const { mockApi } = vi.hoisted(() => ({
    mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/lib/api', () => ({
    default: mockApi,
    unwrapData: (r: any) => r?.data?.data ?? r?.data,
}))

import { financialApi } from '@/lib/financial-api'

describe('Financial API Contract', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    // --- Receivables ---
    describe('receivables', () => {
        it('list returns paginated structure', async () => {
            const response = {
                data: {
                    data: [{ id: 1, description: 'Test', amount: '100.00' }],
                    current_page: 1,
                    last_page: 1,
                    total: 1,
                },
            }
            mockApi.get.mockResolvedValue(response)
            const result = await financialApi.receivables.list({ page: 1, per_page: 50 })
            expect(mockApi.get).toHaveBeenCalledWith('/accounts-receivable', { params: { page: 1, per_page: 50 } })
            expect(result.data).toBeDefined()
        })

        it('create sends correct payload', async () => {
            const payload = { customer_id: 1, description: 'Calibracao', amount: '500', due_date: '2026-05-01' }
            mockApi.post.mockResolvedValue({ data: { id: 1, ...payload } })
            await financialApi.receivables.create(payload)
            expect(mockApi.post).toHaveBeenCalledWith('/accounts-receivable', payload)
        })

        it('update sends correct payload', async () => {
            const payload = { description: 'Updated', amount: '600' }
            mockApi.put.mockResolvedValue({ data: { id: 1, ...payload } })
            await financialApi.receivables.update(1, payload)
            expect(mockApi.put).toHaveBeenCalledWith('/accounts-receivable/1', payload)
        })

        it('destroy sends DELETE request', async () => {
            mockApi.delete.mockResolvedValue({})
            await financialApi.receivables.destroy(1)
            expect(mockApi.delete).toHaveBeenCalledWith('/accounts-receivable/1')
        })

        it('pay sends POST to correct endpoint', async () => {
            const payData = { amount: '500', payment_method: 'pix', payment_date: '2026-03-15' }
            mockApi.post.mockResolvedValue({ data: {} })
            await financialApi.receivables.pay(1, payData)
            expect(mockApi.post).toHaveBeenCalledWith('/accounts-receivable/1/pay', payData)
        })

        it('summary returns summary data', async () => {
            mockApi.get.mockResolvedValue({ data: { pending: 5000, overdue: 2000 } })
            const result = await financialApi.receivables.summary()
            expect(mockApi.get).toHaveBeenCalledWith('/accounts-receivable-summary')
            expect(result.data).toBeDefined()
        })

        it('generateFromOs sends correct payload', async () => {
            const data = { work_order_id: '1', due_date: '2026-05-01', payment_method: 'boleto' }
            mockApi.post.mockResolvedValue({ data: {} })
            await financialApi.receivables.generateFromOs(data)
            expect(mockApi.post).toHaveBeenCalledWith('/accounts-receivable/generate-from-os', data)
        })
    })

    // --- Payables ---
    describe('payables', () => {
        it('list returns paginated structure', async () => {
            mockApi.get.mockResolvedValue({
                data: { data: [{ id: 1, description: 'Rent' }], current_page: 1, last_page: 1, total: 1 },
            })
            const result = await financialApi.payables.list({ page: 1 })
            expect(mockApi.get).toHaveBeenCalledWith('/accounts-payable', { params: { page: 1 } })
            expect(result.data).toBeDefined()
        })

        it('create sends correct payload', async () => {
            const payload = { description: 'Aluguel', amount: '3000', due_date: '2026-04-01' }
            mockApi.post.mockResolvedValue({ data: { id: 1, ...payload } })
            await financialApi.payables.create(payload)
            expect(mockApi.post).toHaveBeenCalledWith('/accounts-payable', payload)
        })

        it('update sends correct payload', async () => {
            const payload = { description: 'Updated' }
            mockApi.put.mockResolvedValue({ data: { id: 1 } })
            await financialApi.payables.update(1, payload)
            expect(mockApi.put).toHaveBeenCalledWith('/accounts-payable/1', payload)
        })

        it('destroy sends DELETE', async () => {
            mockApi.delete.mockResolvedValue({})
            await financialApi.payables.destroy(1)
            expect(mockApi.delete).toHaveBeenCalledWith('/accounts-payable/1')
        })

        it('summary returns summary data', async () => {
            mockApi.get.mockResolvedValue({ data: { pending: 3000 } })
            await financialApi.payables.summary()
            expect(mockApi.get).toHaveBeenCalledWith('/accounts-payable-summary')
        })
    })

    // --- DRE & Cash Flow ---
    describe('financial reports', () => {
        it('dre sends correct date params', async () => {
            mockApi.get.mockResolvedValue({ data: {} })
            await financialApi.dre({ from: '2026-01-01', to: '2026-03-31' })
            expect(mockApi.get).toHaveBeenCalledWith('/financial/dre', { params: { from: '2026-01-01', to: '2026-03-31' } })
        })

        it('cashFlowWeekly sends params', async () => {
            mockApi.get.mockResolvedValue({ data: {} })
            await financialApi.cashFlowWeekly({ weeks: 4 })
            expect(mockApi.get).toHaveBeenCalledWith('/financial/cash-flow-weekly', { params: { weeks: 4 } })
        })
    })

    describe('bank reconciliation', () => {
        it('uses backend import route', async () => {
            const formData = new FormData()

            await financialApi.reconciliation.importStatement(formData)

            expect(mockApi.post).toHaveBeenCalledWith('/bank-reconciliation/import', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
        })

        it('uses backend bulk action route', async () => {
            const payload = { action: 'ignore', entry_ids: [1, 2] }

            await financialApi.reconciliation.bulkAction(payload)

            expect(mockApi.post).toHaveBeenCalledWith('/bank-reconciliation/bulk-action', payload)
        })
    })

    // --- Error handling ---
    describe('error handling', () => {
        it('handles 422 validation error', async () => {
            mockApi.post.mockRejectedValue({
                response: { status: 422, data: { message: 'Validation failed', errors: { amount: ['Required'] } } },
            })
            await expect(financialApi.receivables.create({})).rejects.toMatchObject({
                response: { status: 422 },
            })
        })

        it('handles 500 server error', async () => {
            mockApi.get.mockRejectedValue({
                response: { status: 500, data: { message: 'Internal Server Error' } },
            })
            await expect(financialApi.receivables.list()).rejects.toMatchObject({
                response: { status: 500 },
            })
        })
    })
})
