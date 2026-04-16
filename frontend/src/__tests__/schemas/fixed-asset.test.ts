import { describe, expect, it } from 'vitest'
import { fixedAssetFormSchema, fixedAssetInventorySchema, fixedAssetMovementSchema, runDepreciationSchema } from '@/schemas/fixed-asset'

describe('fixedAssetFormSchema', () => {
    it('accepts a valid fixed asset payload', () => {
        const parsed = fixedAssetFormSchema.parse({
            name: 'Balança de precisão',
            description: 'Ativo crítico de laboratório',
            category: 'equipment',
            acquisition_date: '2026-01-15',
            acquisition_value: 45000,
            residual_value: 5000,
            useful_life_months: 120,
            depreciation_method: 'linear',
            location: 'Laboratório',
            ciap_credit_type: 'icms_48',
        })

        expect(parsed.name).toBe('Balança de precisão')
        expect(parsed.ciap_credit_type).toBe('icms_48')
    })

    it('rejects residual value above acquisition value', () => {
        const result = fixedAssetFormSchema.safeParse({
            name: 'Notebook',
            category: 'it',
            acquisition_date: '2026-02-01',
            acquisition_value: 1000,
            residual_value: 1500,
            useful_life_months: 24,
            depreciation_method: 'linear',
            ciap_credit_type: 'none',
        })

        expect(result.success).toBe(false)
    })
})

describe('runDepreciationSchema', () => {
    it('requires YYYY-MM format', () => {
        expect(runDepreciationSchema.safeParse({ reference_month: '2026-03' }).success).toBe(true)
        expect(runDepreciationSchema.safeParse({ reference_month: '03/2026' }).success).toBe(false)
    })
})

describe('fixed asset complementary schemas', () => {
    it('accepts movement payload', () => {
        expect(fixedAssetMovementSchema.safeParse({
            movement_type: 'transfer',
            to_location: 'Filial',
            moved_at: '2026-03-27T10:00',
            notes: 'Remanejamento',
        }).success).toBe(true)
    })

    it('accepts inventory payload', () => {
        expect(fixedAssetInventorySchema.safeParse({
            inventory_date: '2026-03-27',
            counted_location: 'Campo',
            counted_status: 'active',
            condition_ok: true,
            notes: 'Tudo ok',
        }).success).toBe(true)
    })
})
