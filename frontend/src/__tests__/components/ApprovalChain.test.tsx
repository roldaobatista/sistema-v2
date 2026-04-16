import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import ApprovalChain from '@/components/os/ApprovalChain'

const { mockApiGet, mockApiPost, mockHasPermission } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockHasPermission: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

describe('ApprovalChain', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('/approvals')) {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/users') {
                return Promise.resolve({
                    data: {
                        data: [
                            { id: 7, name: 'Aprovador 1' },
                        ],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('nao oferece solicitacao de aprovacao sem permissao de update', async () => {
        mockHasPermission.mockImplementation((permission: string) => permission !== 'os.work_order.update')

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText(/nenhuma aprovacao registrada para esta os/i)).toBeInTheDocument()
        })

        expect(screen.queryByRole('button', { name: /solicitar aprovacao/i })).not.toBeInTheDocument()
        expect(mockApiGet).not.toHaveBeenCalledWith('/users', expect.anything())
    })

    it('permite selecionar aprovadores quando o usuario pode atualizar a OS', async () => {
        mockHasPermission.mockReturnValue(true)

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        expect(await screen.findByRole('button', { name: /solicitar aprovacao/i })).toBeDisabled()
        expect(await screen.findByRole('button', { name: 'Aprovador 1' })).toBeInTheDocument()
    })
})
