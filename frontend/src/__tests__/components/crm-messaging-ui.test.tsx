import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { fireEvent, render, screen, waitFor } from '@/__tests__/test-utils'
import { MessageHistory } from '@/components/crm/MessageHistory'
import { SendMessageModal } from '@/components/crm/SendMessageModal'
import { crmApi } from '@/lib/crm-api'

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

vi.mock('@/lib/crm-api', () => ({
    crmApi: {
        getMessageTemplates: vi.fn(),
        sendMessage: vi.fn(),
        getMessages: vi.fn(),
    },
}))

describe('CRM messaging UI', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('allows sending an email from template without a manual subject', async () => {
        vi.mocked(crmApi.getMessageTemplates).mockResolvedValue([
            {
                id: 10,
                name: 'Boas-vindas',
                slug: 'boas-vindas',
                channel: 'email',
                subject: '',
                body: 'Ola {{nome}}',
                variables: null,
                is_active: true,
            },
        ])
        vi.mocked(crmApi.sendMessage).mockResolvedValue({ data: { id: 99 } } as never)

        render(
            <SendMessageModal
                open
                onClose={vi.fn()}
                customerId={1}
                customerName="ACME"
                customerEmail="contato@acme.com"
            />
        )

        await waitFor(() => {
            expect(crmApi.getMessageTemplates).toHaveBeenCalledWith('email')
        })

        fireEvent.click(await screen.findByText(/boas-vindas/i))

        const sendButton = screen.getByRole('button', { name: /enviar e-mail/i })
        expect(sendButton).not.toBeDisabled()

        fireEvent.click(sendButton)

        await waitFor(() => {
            expect(crmApi.sendMessage).toHaveBeenCalledWith(
                expect.objectContaining({
                    customer_id: 1,
                    channel: 'email',
                    template_id: 10,
                    subject: undefined,
                })
            )
        })
    })

    it('shows retry state when message history fails', async () => {
        vi.mocked(crmApi.getMessages).mockRejectedValue(new Error('network'))

        const queryClient = new QueryClient({
            defaultOptions: {
                queries: { retry: false },
            },
        })

        render(
            <QueryClientProvider client={queryClient}>
                <MessageHistory customerId={1} />
            </QueryClientProvider>
        )

        expect(await screen.findByText(/erro ao carregar mensagens/i)).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /tentar novamente/i })).toBeInTheDocument()
    })
})
