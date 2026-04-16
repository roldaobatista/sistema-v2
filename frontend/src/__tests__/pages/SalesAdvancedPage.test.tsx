import { beforeEach, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'

import { render, screen } from '@/__tests__/test-utils'
import { server } from '@/__tests__/mocks/server'
import SalesAdvancedPage from '@/pages/vendas/SalesAdvancedPage'

function apiPattern(path: string): RegExp {
  const escapedPath = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return new RegExp(`(http://(localhost|127\\.0\\.0\\.1):8000)?/api/v1${escapedPath}(\\?.*)?$`)
}

describe('SalesAdvancedPage', () => {
  beforeEach(() => {
    server.resetHandlers()
  })

  it('exibe follow-up com cliente, numero do orcamento e valor usando o contrato da API', async () => {
    server.use(
      http.get(apiPattern('/sales/follow-up-queue'), () =>
        HttpResponse.json({
          data: {
            data: [
              {
                id: 41,
                quote_number: 'ORC-FUP-041',
                number: 'ORC-FUP-041',
                customer_name: 'Cliente Follow-up',
                customer: { id: 8, name: 'Cliente Follow-up' },
                total: 1980.5,
                value: 1980.5,
                status: 'sent',
              },
            ],
            summary: {
              total: 1,
              expired: 0,
              urgent: 0,
              total_value: 1980.5,
            },
          },
        })
      ),
      http.get(apiPattern('/sales/loss-reasons'), () =>
        HttpResponse.json({ data: { data: [], summary: { total_lost: 0, total_value_lost: 0 } } })
      ),
      http.get(apiPattern('/sales/client-segmentation'), () =>
        HttpResponse.json({ data: { data: [], summary: {}, total_revenue: 0, total_customers: 0 } })
      ),
      http.get(apiPattern('/sales/discount-requests'), () =>
        HttpResponse.json({ data: [] })
      )
    )

    render(<SalesAdvancedPage />)

    expect(await screen.findByText('Cliente Follow-up')).toBeInTheDocument()
    expect(await screen.findByText('Orçamento: ORC-FUP-041')).toBeInTheDocument()
    expect(await screen.findByText('R$ 1.980,50')).toBeInTheDocument()
  })
})
