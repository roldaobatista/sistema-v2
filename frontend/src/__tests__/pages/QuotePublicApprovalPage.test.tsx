import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router-dom'

import { render, screen, waitFor } from '@/__tests__/test-utils'
import { QuotePublicApprovalPage } from '@/pages/orcamentos/QuotePublicApprovalPage'

const { mockApiGet, mockApiPost } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
        defaults: { baseURL: 'http://127.0.0.1:8000/api/v1' },
    },
    getApiOrigin: () => 'http://127.0.0.1:8000',
    getApiErrorMessage: (err: unknown, fallback: string) => {
        const e = err as { response?: { data?: { message?: string } } }
        return e?.response?.data?.message ?? fallback
    },
    unwrapData: <T,>(r: { data?: { data?: T } | T }): T => {
        const d = r?.data
        if (d != null && typeof d === 'object' && 'data' in d) {
            return (d as { data: T }).data
        }
        return d as T
    },
}))

function renderPage(route = '/quotes/proposal/token-publico') {
    return render(
        <Routes>
            <Route path="/quotes/proposal/:magicToken" element={<QuotePublicApprovalPage />} />
        </Routes>,
        { route }
    )
}

describe('QuotePublicApprovalPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('carrega a proposta publica e aprova com sucesso pelo magic link', async () => {
        mockApiGet.mockResolvedValue({
            data: {
                data: {
                    id: 77,
                    quote_number: 'ORC-00077',
                    reference: 'Calibracao anual',
                    total: 1250,
                    valid_until: '2026-03-20',
                    customer_name: 'Cliente Portal',
                    company_name: 'Empresa Teste',
                    pdf_url: 'http://localhost/storage/quotes/orc-77.pdf',
                    items: [
                        {
                            id: 1,
                            description: 'Servico de calibracao',
                            quantity: 2,
                            unit_price: 625,
                            subtotal: 1250,
                        },
                    ],
                },
            },
        })

        mockApiPost.mockResolvedValue({
            data: {
                data: {
                    approved_at: '2026-03-13T12:00:00.000Z',
                },
                message: 'Proposta aprovada com sucesso!',
            },
        })

        const user = userEvent.setup({ delay: null })

        renderPage()

        expect(await screen.findByText(/ORC-00077/i)).toBeInTheDocument()
        expect(screen.getByText(/Cliente Portal/i)).toBeInTheDocument()
        expect(screen.getByText(/Servico de calibracao/i)).toBeInTheDocument()

        await user.click(screen.getByRole('checkbox', { name: /Aceitar termos da proposta/i }))
        await user.click(screen.getByRole('button', { name: /Aprovar proposta/i }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith(
                expect.stringContaining('/api/quotes/proposal/token-publico/approve'),
                { accept_terms: true },
            )
        })

        expect(await screen.findByText(/Aprovacao concluida/i)).toBeInTheDocument()
        expect(screen.getByText(/Proposta aprovada com sucesso/i)).toBeInTheDocument()
    })

    it('exibe mensagem de indisponibilidade quando a proposta nao existe', async () => {
        mockApiGet.mockRejectedValue({
            response: {
                status: 404,
                data: {
                    message: 'Proposta nao encontrada ou ja processada.',
                },
            },
        })

        renderPage('/quotes/proposal/token-invalido')

        expect(await screen.findByText(/Proposta indisponivel/i)).toBeInTheDocument()
        expect(screen.getByText(/Proposta nao encontrada ou ja processada/i)).toBeInTheDocument()
    })
})
