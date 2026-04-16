import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import AuditTrailTab from '@/components/os/AuditTrailTab'

const { mockApiGet } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
    },
}))

vi.mock('@/lib/utils', () => ({
    cn: (...args: Array<string | false | null | undefined>) => args.filter(Boolean).join(' '),
}))

vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: string }) => (
        <button onClick={onClick} {...props}>{children}</button>
    ),
}))

describe('AuditTrailTab', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('nao renderiza comments de suppressao na tela de acesso negado e permite tentar novamente', async () => {
        const user = userEvent.setup()
        mockApiGet
            .mockRejectedValueOnce({ response: { status: 403, data: { message: 'Forbidden' } } })
            .mockResolvedValueOnce({
                data: {
                    data: [
                        {
                            id: 1,
                            action: 'created',
                            action_label: 'Criado',
                            description: 'OS criada',
                            entity_type: 'WorkOrder',
                            entity_id: 1,
                            user: { id: 9, name: 'Admin' },
                            old_values: null,
                            new_values: { status: 'open' },
                            ip_address: null,
                            created_at: '2026-03-20T12:00:00Z',
                        },
                    ],
                },
            })

        render(<AuditTrailTab workOrderId={15} />)

        expect(await screen.findByText(/sem permissao para visualizar auditoria/i)).toBeInTheDocument()
        expect(screen.queryByText(/@ts-ignore/i)).not.toBeInTheDocument()

        await user.click(screen.getByRole('button', { name: /tentar novamente/i }))

        expect(await screen.findByText('OS criada')).toBeInTheDocument()

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledTimes(2)
        })
    })
})
