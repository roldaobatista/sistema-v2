import { describe, expect, it } from 'vitest'

import { formatForecastWinRate } from '@/pages/crm/CrmForecastPage'

describe('CrmForecastPage helpers', () => {
    it('formata historical_win_rate que ja vem em percentual', () => {
        expect(formatForecastWinRate(37.5)).toBe('37.5%')
        expect(formatForecastWinRate(100)).toBe('100.0%')
    })
})
