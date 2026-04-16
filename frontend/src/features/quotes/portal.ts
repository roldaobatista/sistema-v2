import { QUOTE_STATUS } from '@/lib/constants'

export function isPortalQuoteActionable(status: string): boolean {
    return status === QUOTE_STATUS.SENT || status === QUOTE_STATUS.RENEGOTIATION
}
