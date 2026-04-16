import { describe, expect, it } from 'vitest'
import {
    normalizeSettlementStatus,
    settlementStatusLabel,
    settlementStatusVariant,
} from '@/pages/financeiro/commissions/utils'

describe('commission settlement status utils', () => {
    it('normalizes pending_approval to closed for workflow decisions', () => {
        expect(normalizeSettlementStatus('pending_approval')).toBe('closed')
    })

    it('keeps legacy label explicit for pending_approval rows', () => {
        expect(settlementStatusLabel('pending_approval')).toBe('Aguard. aprovacao (legado)')
    })

    it('keeps warning variant for legacy pending_approval rows', () => {
        expect(settlementStatusVariant('pending_approval')).toBe('warning')
    })
})
