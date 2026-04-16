import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { render, screen, waitFor } from '@/__tests__/test-utils'
import { server } from '@/__tests__/mocks/server'
import { PortalQuotesPage } from '@/pages/portal/PortalQuotesPage'

const { mockToast } = vi.hoisted(() => ({
    mockToast: {
        error: vi.fn(),
        success: vi.fn(),
    },
}))

vi.mock('sonner', () => ({
    toast: mockToast,
}))

function apiPattern(path: string): RegExp {
    const escapedPath = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    return new RegExp(`http://(localhost|127\\.0\\.0\\.1):8000/api/v1${escapedPath}(\\?.*)?$`)
}

describe('PortalQuotesPage', () => {
    beforeEach(() => {
        server.resetHandlers()
        vi.clearAllMocks()
    })

    it('permite rejeitar orcamento sem comentario no portal', async () => {
        let payload: { action?: string; comments?: string | null } | null = null

        server.use(
            http.get(apiPattern('/portal/quotes'), () =>
                HttpResponse.json({
                    data: [
                        {
                            id: 15,
                            quote_number: 'ORC-00015',
                            created_at: '2026-03-11T10:00:00.000Z',
                            status: 'sent',
                            total: 1500,
                            equipments: [],
                        },
                    ],
                })
            ),
            http.post(apiPattern('/portal/quotes/15/status'), async ({ request }) => {
                payload = await request.json() as { action?: string; comments?: string | null }

                return HttpResponse.json({
                    data: {
                        id: 15,
                        status: 'rejected',
                    },
                })
            })
        )

        const user = userEvent.setup()

        render(<PortalQuotesPage />)

        expect(await screen.findByText(/ORC-00015/i)).toBeInTheDocument()

        await user.click(screen.getByRole('button', { name: /Rejeitar/i }))
        await user.click(screen.getByRole('button', { name: /Confirmar Rejei..o/i }))

        await waitFor(() => {
            expect(payload).toEqual({
                action: 'reject',
            })
        })

        await waitFor(() => {
            expect(screen.queryByRole('button', { name: /Confirmar Rejei..o/i })).not.toBeInTheDocument()
        })
    })

    it('exibe o rotulo correto para status nao acionavel sem cair em pendente', async () => {
        server.use(
            http.get(apiPattern('/portal/quotes'), () =>
                HttpResponse.json({
                    data: [
                        {
                            id: 22,
                            quote_number: 'ORC-00022',
                            created_at: '2026-03-11T10:00:00.000Z',
                            status: 'installation_testing',
                            total: 2200,
                            equipments: [],
                        },
                    ],
                })
            ),
        )

        render(<PortalQuotesPage />)

        expect(await screen.findByText(/ORC-00022/i)).toBeInTheDocument()
        expect(screen.getByText(/Instalacao p\/ Teste/i)).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /Aprovar/i })).not.toBeInTheDocument()
        expect(screen.queryByText(/Pendente/i)).not.toBeInTheDocument()
    })

    it('mostra erro explicito e nao renderiza vazio falso quando a API falha', async () => {
        server.use(
            http.get(apiPattern('/portal/quotes'), () =>
                HttpResponse.json(
                    { message: 'Falha ao carregar orcamentos' },
                    { status: 500 }
                )
            )
        )

        render(<PortalQuotesPage />)

        expect(await screen.findByText(/Erro ao carregar orçamentos/i)).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Tentar novamente/i })).toBeInTheDocument()
        expect(screen.queryByText(/Nenhum orçamento encontrado/i)).not.toBeInTheDocument()
        expect(screen.queryByText(/Aprovar/i)).not.toBeInTheDocument()

        await waitFor(() => {
            expect(mockToast.error).toHaveBeenCalledWith('Falha ao carregar orcamentos')
        })
    })

    it('permite tentar novamente apos erro na busca de orcamentos', async () => {
        let attempts = 0

        server.use(
            http.get(apiPattern('/portal/quotes'), () => {
                attempts += 1

                if (attempts === 1) {
                    return HttpResponse.json(
                        { message: 'Falha temporaria ao carregar orcamentos' },
                        { status: 500 }
                    )
                }

                return HttpResponse.json({
                    data: [
                        {
                            id: 33,
                            quote_number: 'ORC-00033',
                            created_at: '2026-03-11T10:00:00.000Z',
                            status: 'sent',
                            total: 3300,
                            equipments: [],
                        },
                    ],
                })
            })
        )

        const user = userEvent.setup()

        render(<PortalQuotesPage />)

        expect(await screen.findByText(/Erro ao carregar orçamentos/i)).toBeInTheDocument()
        await user.click(screen.getByRole('button', { name: /Tentar novamente/i }))

        await waitFor(() => {
            expect(screen.getByText(/ORC-00033/i)).toBeInTheDocument()
        })
    })
})
