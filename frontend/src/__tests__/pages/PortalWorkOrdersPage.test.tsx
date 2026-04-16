import { beforeEach, describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'

import { render, screen } from '@/__tests__/test-utils'
import { server } from '@/__tests__/mocks/server'
import { PortalWorkOrdersPage } from '@/pages/portal/PortalWorkOrdersPage'

function apiPattern(path: string): RegExp {
  const escapedPath = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return new RegExp(`http://(localhost|127\\.0\\.0\\.1):8000/api/v1${escapedPath}(\\?.*)?$`)
}

describe('PortalWorkOrdersPage', () => {
  beforeEach(() => {
    server.resetHandlers()
  })

  it('expõe a busca com nome acessivel', async () => {
    server.use(
      http.get(apiPattern('/portal/work-orders'), () =>
        HttpResponse.json({
          data: [
            {
              id: 9,
              number: 'OS-0009',
              status: 'open',
              created_at: '2026-03-20T10:00:00.000Z',
              description: 'Manutencao preventiva',
            },
          ],
        })
      )
    )

    render(<PortalWorkOrdersPage />)

    expect(await screen.findByText(/OS-0009/i)).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /Buscar ordens de serviço/i })).toBeInTheDocument()
  })
})
