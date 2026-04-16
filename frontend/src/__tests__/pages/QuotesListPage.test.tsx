import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http, HttpResponse } from 'msw'

import { render, screen } from '@/__tests__/test-utils'
import { server } from '@/__tests__/mocks/server'
import { QuotesListPage } from '@/pages/orcamentos/QuotesListPage'

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: (permission: string) => [
            'quotes.quote.convert',
            'quotes.quote.view',
        ].includes(permission),
    }),
}))

vi.mock('@/hooks/useAuvoExport', () => ({
    useAuvoExport: () => ({
        exportQuote: {
            mutate: vi.fn(),
            isPending: false,
        },
    }),
}))

function apiPattern(path: string): RegExp {
    const escapedPath = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    return new RegExp(`http://(localhost|127\\.0\\.0\\.1):8000/api/v1${escapedPath}(\\?.*)?$`)
}

describe('QuotesListPage', () => {
    beforeEach(() => {
        server.resetHandlers()
    })

    it('exibe acao de converter para orcamento aprovado internamente', async () => {
        server.use(
            http.get(apiPattern('/quotes-summary'), () => HttpResponse.json({
                data: {
                    draft: 0,
                    pending_internal_approval: 0,
                    internally_approved: 1,
                    sent: 0,
                    approved: 0,
                    rejected: 0,
                    expired: 0,
                    in_execution: 0,
                    installation_testing: 0,
                    renegotiation: 0,
                    invoiced: 0,
                    total_month: 0,
                    conversion_rate: 0,
                },
            })),
            http.get(apiPattern('/users'), () => HttpResponse.json({ data: [] })),
            http.get(apiPattern('/quote-tags'), () => HttpResponse.json({ data: [] })),
            http.get(apiPattern('/quotes'), () => HttpResponse.json({
                data: [
                    {
                        id: 41,
                        quote_number: 'ORC-00041',
                        revision: 1,
                        status: 'internally_approved',
                        total: 1750,
                        valid_until: '2026-03-20',
                        customer: { id: 1, name: 'Cliente Interno' },
                        seller: { id: 9, name: 'Vendedor 1' },
                        tags: [],
                    },
                ],
                last_page: 1,
                total: 1,
            })),
        )

        render(<QuotesListPage />)

        expect(await screen.findByText(/ORC-00041/i)).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Converter orçamento em ordem de serviço/i })).toBeInTheDocument()
    })
})
