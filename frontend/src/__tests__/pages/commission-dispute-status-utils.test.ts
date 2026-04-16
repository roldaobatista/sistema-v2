import { describe, expect, it } from 'vitest'
import {
    getCommissionDisputeStatusLabel,
    getCommissionDisputeStatusVariant,
    normalizeCommissionDisputeStatus,
} from '@/pages/financeiro/commissions/utils'

describe('commission dispute status utils', () => {
    it('mantem resolved como alias legado explicito na borda', () => {
        expect(normalizeCommissionDisputeStatus('resolved')).toBe('resolved')
        expect(getCommissionDisputeStatusLabel('resolved')).toBe('Resolvida (legado)')
        expect(getCommissionDisputeStatusVariant('resolved')).toBe('default')
    })

    it('preserva status canonicos sem reclassificar disputa aberta', () => {
        expect(normalizeCommissionDisputeStatus('open')).toBe('open')
        expect(getCommissionDisputeStatusLabel('accepted')).toBe('Aceita')
        expect(getCommissionDisputeStatusVariant('rejected')).toBe('danger')
    })
})
