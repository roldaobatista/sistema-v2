import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { render, screen, waitFor } from '@/__tests__/test-utils'
import { server } from '@/__tests__/mocks/server'
import { PortalTicketsPage } from '@/pages/portal/PortalTicketsPage'

const { mockToast } = vi.hoisted(() => ({
  mockToast: {
    error: vi.fn(),
    success: vi.fn(),
    warning: vi.fn(),
  },
}))

vi.mock('sonner', () => ({
  toast: mockToast,
}))

function apiPattern(path: string): RegExp {
  const escapedPath = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return new RegExp(`http://(localhost|127\\.0\\.0\\.1):8000/api/v1${escapedPath}(\\?.*)?$`)
}

describe('PortalTicketsPage', () => {
  beforeEach(() => {
    server.resetHandlers()
    vi.clearAllMocks()
  })

  it('mostra erro explicito e permite tentar novamente quando a busca de tickets falha', async () => {
    let attempts = 0

    server.use(
      http.get(apiPattern('/portal/tickets'), () => {
        attempts += 1

        if (attempts === 1) {
          return HttpResponse.json({ message: 'Falha ao carregar tickets' }, { status: 500 })
        }

        return HttpResponse.json({
          data: [
            {
              id: 17,
              subject: 'Falha no equipamento',
              status: 'open',
              ticket_number: 'TK-00017',
              created_at: '2026-03-20T10:00:00.000Z',
            },
          ],
        })
      })
    )

    const user = userEvent.setup()

    render(<PortalTicketsPage />)

    expect(await screen.findByText(/Erro ao carregar tickets/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Tentar novamente/i })).toBeInTheDocument()
    expect(screen.queryByText(/Nenhum ticket encontrado/i)).not.toBeInTheDocument()
    expect(mockToast.error).toHaveBeenCalledWith('Falha ao carregar tickets')

    await user.click(screen.getByRole('button', { name: /Tentar novamente/i }))

    await waitFor(() => {
      expect(screen.getByText(/Falha no equipamento/i)).toBeInTheDocument()
    })
  })

  it('expõe campos do formulario com nomes acessiveis', async () => {
    server.use(
      http.get(apiPattern('/portal/tickets'), () => HttpResponse.json({ data: [] }))
    )

    const user = userEvent.setup()

    render(<PortalTicketsPage />)

    await user.click(await screen.findByRole('button', { name: /Novo Ticket/i }))

    expect(screen.getByRole('textbox', { name: /Assunto/i })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /Descricao do ticket/i })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: /Prioridade/i })).toBeInTheDocument()
  })
})
