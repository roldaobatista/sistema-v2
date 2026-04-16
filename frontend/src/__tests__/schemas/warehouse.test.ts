import { describe, it, expect } from 'vitest'
import { warehouseSchema } from '@/schemas/warehouse'

describe('warehouseSchema', () => {
    it('rejeita nome vazio', () => {
        const result = warehouseSchema.safeParse({ name: '', code: 'WH1', type: 'fixed' })
        expect(result.success).toBe(false)
    })

    it('rejeita código vazio', () => {
        const result = warehouseSchema.safeParse({ name: 'Warehouse', code: '', type: 'fixed' })
        expect(result.success).toBe(false)
    })

    it('aceita dados válidos', () => {
        const result = warehouseSchema.safeParse({
            name: 'Armazém Central',
            code: 'WH-01',
            type: 'fixed',
        })
        expect(result.success).toBe(true)
    })

    it('rejeita type inválido', () => {
        const result = warehouseSchema.safeParse({
            name: 'A',
            code: 'C',
            type: 'invalid',
        })
        expect(result.success).toBe(false)
    })

    it('aceita types fixed, vehicle, technician', () => {
        expect(warehouseSchema.safeParse({ name: 'A', code: 'C', type: 'fixed' }).success).toBe(true)
        expect(warehouseSchema.safeParse({ name: 'A', code: 'C', type: 'vehicle' }).success).toBe(true)
        expect(warehouseSchema.safeParse({ name: 'A', code: 'C', type: 'technician' }).success).toBe(true)
    })
})
