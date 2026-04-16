import { beforeEach, describe, expect, it, vi } from 'vitest'

const postMock = vi.fn()

vi.mock('@/lib/api', () => ({
  default: {
    post: postMock,
  },
}))

describe('financialApi commissions', () => {
  beforeEach(() => {
    postMock.mockReset()
  })

  it('envia payload ao pagar fechamento de comissao', async () => {
    const { financialApi } = await import('@/lib/financial-api')

    const payload = {
      payment_method: 'pix',
      payment_notes: 'Pago via tesouraria',
    }

    await financialApi.commissions.paySettlement(42, payload)

    expect(postMock).toHaveBeenCalledWith('/commission-settlements/42/pay', payload)
  })
})
