import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http, HttpResponse } from 'msw'
import userEvent from '@testing-library/user-event'

import { render, screen, waitFor } from '@/__tests__/test-utils'
import { server } from '@/__tests__/mocks/server'
import { PortalFinancialsPage } from '@/pages/portal/PortalFinancialsPage'

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

describe('PortalFinancialsPage', () => {
    beforeEach(() => {
        server.resetHandlers()
        vi.clearAllMocks()
    })

    it('mostra erro explicito e nao exibe o estado vazio falso quando a API falha', async () => {
        let attempts = 0

        server.use(
            http.get(apiPattern('/portal/financials'), () => {
                attempts += 1

                if (attempts === 1) {
                    return HttpResponse.json({ message: 'Falha no financeiro' }, { status: 500 })
                }

                return HttpResponse.json({
                    data: [
                        {
                            id: 1,
                            description: 'Fatura de teste',
                            amount: 1200,
                            due_date: '2026-04-15',
                            status: 'pending',
                        },
                    ],
                })
            })
        )

        const user = userEvent.setup()

        render(<PortalFinancialsPage />)

        expect(await screen.findByText(/Erro ao carregar financeiro/i)).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Tentar novamente/i })).toBeInTheDocument()
        expect(screen.queryByText(/Nenhuma fatura encontrada/i)).not.toBeInTheDocument()
        expect(screen.queryByText(/Pendente/i)).not.toBeInTheDocument()
        expect(mockToast.error).toHaveBeenCalledWith(expect.stringContaining('Falha no financeiro'))

        await user.click(screen.getByRole('button', { name: /Tentar novamente/i }))

        await waitFor(() => {
            expect(screen.getByText(/Fatura de teste/i)).toBeInTheDocument()
        })
    })

    it('exibe os saldos e a lista de faturas quando a API responde com sucesso', async () => {
        server.use(
            http.get(apiPattern('/portal/financials'), () =>
                HttpResponse.json({
                    data: [
                        {
                            id: 1,
                            description: 'Fatura de teste',
                            amount: 1200,
                            due_date: '2026-04-15',
                            status: 'pending',
                        },
                        {
                            id: 2,
                            description: 'Fatura paga',
                            amount: 300,
                            due_date: '2026-03-10',
                            status: 'paid',
                        },
                    ],
                })
            )
        )

        render(<PortalFinancialsPage />)

        expect(await screen.findByText(/Fatura de teste/i)).toBeInTheDocument()
        expect(screen.getByText(/Fatura paga/i)).toBeInTheDocument()
        expect(screen.getAllByText(/Pendente/i).length).toBeGreaterThan(0)
        expect(screen.getAllByText(/Pago/i).length).toBeGreaterThan(0)
    })
})
