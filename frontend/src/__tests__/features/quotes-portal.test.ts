import { describe, expect, it } from 'vitest'

import { QUOTE_STATUS } from '@/lib/constants'
import { isPortalQuoteActionable } from '@/features/quotes/portal'

describe('quotes/portal', () => {
    it('permite acao do portal apenas para orcamentos enviados', () => {
        expect(isPortalQuoteActionable(QUOTE_STATUS.SENT)).toBe(true)
        expect(isPortalQuoteActionable(QUOTE_STATUS.DRAFT)).toBe(false)
        expect(isPortalQuoteActionable(QUOTE_STATUS.APPROVED)).toBe(false)
    })
})
