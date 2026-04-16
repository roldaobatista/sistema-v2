import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http, HttpResponse } from 'msw'

import { render, screen, within } from '@/__tests__/test-utils'
import { server } from '@/__tests__/mocks/server'
import { PortalDashboardPage } from '@/pages/portal/PortalDashboardPage'

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

describe('PortalDashboardPage', () => {
    beforeEach(() => {
        server.resetHandlers()
        vi.clearAllMocks()
    })

    it('mostra erro explicito e nao cai em estado vazio quando a API falha', async () => {
        server.use(
            http.get(apiPattern('/portal/work-orders'), () =>
                HttpResponse.json({ message: 'Falha nas ordens de servico' }, { status: 500 })
            ),
            http.get(apiPattern('/portal/quotes'), () =>
                HttpResponse.json({
                    data: [
                        { id: 7, status: 'sent' },
                    ],
                })
            ),
            http.get(apiPattern('/portal/financials'), () =>
                HttpResponse.json({
                    data: [
                        { amount: 2500 },
                    ],
                })
            ),
        )

        render(<PortalDashboardPage />)

        expect(await screen.findByText(/Erro ao carregar dados do portal/i)).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Tentar novamente/i })).toBeInTheDocument()
        expect(screen.queryByText(/Nenhuma OS encontrada/i)).not.toBeInTheDocument()
        expect(screen.queryByText(/OS Abertas/i)).not.toBeInTheDocument()
        expect(mockToast.error).toHaveBeenCalledWith(expect.stringContaining('Falha nas ordens de servico'))
    })

    it('exibe os cards e as ordens recentes quando a API responde com sucesso', async () => {
        server.use(
            http.get(apiPattern('/portal/work-orders'), () =>
                HttpResponse.json({
                    data: [
                        {
                            id: 1,
                            number: 'OS-001',
                            description: 'Manutencao preventiva',
                            status: 'open',
                        },
                    ],
                })
            ),
            http.get(apiPattern('/portal/quotes'), () =>
                HttpResponse.json({
                    data: [
                        { id: 7, status: 'sent' },
                    ],
                })
            ),
            http.get(apiPattern('/portal/financials'), () =>
                HttpResponse.json({
                    data: [
                        { amount: 2500 },
                    ],
                })
            ),
        )

        render(<PortalDashboardPage />)

        expect(await screen.findByText(/OS-001/i)).toBeInTheDocument()
        expect(screen.getByText(/OS Abertas/i)).toBeInTheDocument()
        expect(screen.getByText(/Ultimas Ordens de Servico/i)).toBeInTheDocument()
        expect(within(screen.getByRole('button', { name: /OS Abertas/i })).getByText('1')).toBeInTheDocument()
    })
})
