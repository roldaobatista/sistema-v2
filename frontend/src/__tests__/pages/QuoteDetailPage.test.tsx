import { beforeEach, describe, expect, it, vi } from 'vitest'
import { Route, Routes } from 'react-router-dom'

import { fireEvent, render, screen, waitFor } from '@/__tests__/test-utils'
import { QuoteDetailPage } from '@/pages/orcamentos/QuoteDetailPage'

const {
    toastSuccess,
    toastError,
    writeTextMock,
    hasPermissionMock,
    duplicateMock,
    detailMock,
    timelineMock,
    installmentsMock,
    getPdfMock,
    createObjectUrlMock,
    revokeObjectUrlMock,
    windowOpenMock,
} = vi.hoisted(() => ({
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
    writeTextMock: vi.fn(),
    hasPermissionMock: vi.fn(),
    duplicateMock: vi.fn(),
    detailMock: vi.fn(),
    timelineMock: vi.fn(),
    installmentsMock: vi.fn(),
    getPdfMock: vi.fn(),
    createObjectUrlMock: vi.fn(),
    revokeObjectUrlMock: vi.fn(),
    windowOpenMock: vi.fn(),
}))

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: hasPermissionMock,
    }),
}))

vi.mock('@/lib/quote-api', () => ({
    quoteApi: {
        detail: detailMock,
        timeline: timelineMock,
        installments: installmentsMock,
        getPdf: getPdfMock,
        duplicate: duplicateMock,
        destroy: vi.fn(),
    },
}))

function makeQuoteDetail() {
    return {
            id: 123,
            tenant_id: 1,
            quote_number: 'ORC-00123',
            revision: 1,
            customer_id: 9,
            seller_id: 2,
            status: 'sent',
            source: null,
            valid_until: '2026-03-20',
            discount_percentage: 0,
            discount_amount: 0,
            displacement_value: 0,
            subtotal: 1000,
            total: 1000,
            observations: null,
            internal_notes: null,
            payment_terms: 'personalizado',
            payment_terms_detail: '153045-dias',
            payment_method_label: 'A combinar',
            payment_condition_summary: 'Pagamento em 3 parcelas com vencimentos programados após a emissão.',
            payment_detail_text: null,
            payment_schedule: [
                { title: '1a parcela', days: 15, due_date: '04/04/2026', text: '1a parcela: 15 dias após emissão (04/04/2026)' },
                { title: '2a parcela', days: 30, due_date: '19/04/2026', text: '2a parcela: 30 dias após emissão (19/04/2026)' },
            ],
            template_id: null,
            opportunity_id: null,
            currency: 'BRL',
            custom_fields: null,
            is_template: false,
            internal_approved_by: null,
            internal_approved_at: null,
            level2_approved_by: null,
            level2_approved_at: null,
            sent_at: '2026-03-14T10:00:00.000Z',
            approved_at: null,
            rejected_at: null,
            rejection_reason: null,
            last_followup_at: null,
            followup_count: 0,
            client_viewed_at: null,
            client_view_count: 0,
            is_installation_testing: false,
            approval_url: 'https://frontend.example.com/quotes/proposal/token-123',
            created_at: '2026-03-14T09:00:00.000Z',
            updated_at: '2026-03-14T09:00:00.000Z',
            deleted_at: null,
            customer: { id: 9, name: 'Cliente Teste' },
            seller: { id: 2, name: 'Vendedor Teste' },
            equipments: [],
            tags: [],
            emails: [],
            work_orders: [],
            service_calls: [],
    }
}

function renderPage() {
    return render(
        <Routes>
            <Route path="/orcamentos/:id" element={<QuoteDetailPage />} />
        </Routes>,
        { route: '/orcamentos/123' }
    )
}

describe('QuoteDetailPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        hasPermissionMock.mockImplementation((permission: string) => permission === 'quotes.quote.view')
        duplicateMock.mockResolvedValue({ data: { data: { id: 456 } } })
        detailMock.mockResolvedValue(makeQuoteDetail())
        timelineMock.mockResolvedValue([])
        installmentsMock.mockResolvedValue([])
        getPdfMock.mockResolvedValue({ data: new Blob(['pdf-content'], { type: 'application/pdf' }) })
        createObjectUrlMock.mockReturnValue('blob:quote-pdf')

        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: {
                writeText: writeTextMock,
            },
        })

        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: createObjectUrlMock,
        })

        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: revokeObjectUrlMock,
        })

        Object.defineProperty(window, 'open', {
            configurable: true,
            value: windowOpenMock,
        })
    })

    it('exibe erro quando a copia do link de aprovacao falha', async () => {
        writeTextMock.mockRejectedValueOnce(new Error('clipboard blocked'))

        renderPage()

        fireEvent.click(await screen.findByRole('button', { name: /Copiar Link/i }))

        await waitFor(() => {
            expect(writeTextMock).toHaveBeenCalledWith('https://frontend.example.com/quotes/proposal/token-123')
        })
        await waitFor(() => {
            expect(toastError).toHaveBeenCalledTimes(1)
            expect(String(toastError.mock.calls[0]?.[0] ?? '')).toMatch(/copiar o link/i)
        })
        expect(toastSuccess).not.toHaveBeenCalled()
    })

    it('permite duplicar o orcamento pelo detalhe', async () => {
        hasPermissionMock.mockImplementation((permission: string) => ['quotes.quote.create', 'quotes.quote.view'].includes(permission))

        renderPage()

        fireEvent.click(await screen.findByRole('button', { name: /Duplicar/i }))

        await waitFor(() => {
            expect(duplicateMock).toHaveBeenCalledWith(123)
        })
    })

    it('mostra erro explicito e permite tentar novamente quando o detalhe falha', async () => {
        detailMock.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'Falha ao carregar orçamento',
                },
            },
        })

        renderPage()

        expect(await screen.findByText('Falha ao carregar orçamento')).toBeInTheDocument()

        fireEvent.click(screen.getByRole('button', { name: /Tentar novamente/i }))

        await waitFor(() => {
            expect(detailMock).toHaveBeenCalledTimes(2)
        })
    })

    it('visualiza o pdf em nova aba', async () => {
        renderPage()

        fireEvent.click(await screen.findByRole('button', { name: /Visualizar/i }))

        await waitFor(() => {
            expect(getPdfMock).toHaveBeenCalledWith(123, true)
            expect(createObjectUrlMock).toHaveBeenCalled()
            expect(windowOpenMock).toHaveBeenCalledWith('blob:quote-pdf', '_blank')
        })
    })

    it('baixa o pdf e mostra feedback de sucesso', async () => {
        const clickMock = vi.fn()
        const anchorMock = {
            href: '',
            download: '',
            click: clickMock,
        }

        renderPage()

        const createElementSpy = vi.spyOn(document, 'createElement').mockImplementation((tagName: string) => {
            if (tagName.toLowerCase() === 'a') {
                return anchorMock as unknown as HTMLAnchorElement
            }

            return document.createElementNS('http://www.w3.org/1999/xhtml', tagName)
        })

        fireEvent.click(await screen.findByRole('button', { name: /^PDF$/i }))

        await waitFor(() => {
            expect(getPdfMock).toHaveBeenCalledWith(123)
            expect(clickMock).toHaveBeenCalledTimes(1)
            expect(toastSuccess).toHaveBeenCalledWith('PDF baixado!')
            expect(revokeObjectUrlMock).toHaveBeenCalledWith('blob:quote-pdf')
        })

        createElementSpy.mockRestore()
    })

    it('exibe as condicoes de pagamento formatadas', async () => {
        renderPage()

        expect(await screen.findByText('A combinar')).toBeInTheDocument()
        expect(screen.getByText(/Pagamento em 3 parcelas com vencimentos programados/i)).toBeInTheDocument()
        expect(screen.getByText(/1a parcela: 15 dias após emissão/i)).toBeInTheDocument()
        expect(screen.getByText(/2a parcela: 30 dias após emissão/i)).toBeInTheDocument()
    })
})
