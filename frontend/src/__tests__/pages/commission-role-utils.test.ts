import { describe, expect, it } from 'vitest'
import { getCommissionRoleLabel, normalizeCommissionRole } from '@/pages/financeiro/commissions/utils'

describe('commission role semantics', () => {
    it('normaliza aliases ingleses para o backing value canonico', () => {
        expect(normalizeCommissionRole('technician')).toBe('tecnico')
        expect(normalizeCommissionRole('seller')).toBe('vendedor')
        expect(normalizeCommissionRole('driver')).toBe('motorista')
    })

    it('preserva valores canonicos e retorna label consistente', () => {
        expect(normalizeCommissionRole('tecnico')).toBe('tecnico')
        expect(getCommissionRoleLabel('seller')).toBe('Vendedor')
        expect(getCommissionRoleLabel('motorista')).toBe('Motorista')
    })
})
