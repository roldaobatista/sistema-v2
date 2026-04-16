import { describe, expect, it } from 'vitest'

import { QUOTE_STATUS } from '@/lib/constants'
import { isMutableQuoteStatus } from '@/features/quotes/constants'

describe('quotes/constants', () => {
    it('treats renegotiation as mutable', () => {
        expect(isMutableQuoteStatus(QUOTE_STATUS.RENEGOTIATION)).toBe(true)
    })

    it('keeps sent quotes as immutable', () => {
        expect(isMutableQuoteStatus(QUOTE_STATUS.SENT)).toBe(false)
    })
})
