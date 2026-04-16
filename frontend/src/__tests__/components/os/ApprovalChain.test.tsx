import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import ApprovalChain from '@/components/os/ApprovalChain'

const { mockApiGet, mockApiPost, mockHasPermission, toastSuccess, toastError } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
    mockHasPermission: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
    },
}))

vi.mock('@/lib/query-keys', () => ({
    queryKeys: {
        workOrders: {
            detail: (id: number) => ['work-orders', id],
            all: ['work-orders'],
        },
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('@/lib/utils', async () => {
    const actual = await vi.importActual<typeof import('@/lib/utils')>('@/lib/utils')
    return {
        ...actual,
        getApiErrorMessage: (err: unknown, fallback: string) => fallback,
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, disabled, loading, icon, ...props }: any) => (
        <button onClick={onClick} disabled={disabled || loading} {...props}>
            {icon}{children}
        </button>
    ),
}))

const pendingApprovals = [
    {
        id: 1,
        approver_id: 9,
        approver_name: 'Joao Silva',
        status: 'pending' as const,
        notes: 'Aguardando revisao',
        response_notes: null,
        responded_at: null,
        created_at: '2026-03-10T10:00:00Z',
    },
    {
        id: 2,
        approver_id: 7,
        approver_name: 'Maria Santos',
        status: 'approved' as const,
        notes: null,
        response_notes: 'LGTM',
        responded_at: '2026-03-11T14:00:00Z',
        created_at: '2026-03-10T10:00:00Z',
    },
]

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
                            { id: 7, name: 'Maria Santos' },
                            { id: 9, name: 'Joao Silva' },
                        ],
                    },
                })
            }
            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('shows message when no approvals and user lacks permission', async () => {
        mockHasPermission.mockReturnValue(false)

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText(/nenhuma aprovacao registrada para esta os/i)).toBeInTheDocument()
        })
        expect(screen.queryByRole('button', { name: /solicitar aprovacao/i })).not.toBeInTheDocument()
    })

    it('shows approval request form when user has permission and no approvals', async () => {
        mockHasPermission.mockReturnValue(true)

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        expect(await screen.findByRole('button', { name: /solicitar aprovacao/i })).toBeDisabled()
        expect(await screen.findByRole('button', { name: 'Maria Santos' })).toBeInTheDocument()
    })

    it('enables request button when approvers are selected', async () => {
        const user = userEvent.setup()
        mockHasPermission.mockReturnValue(true)

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        const approverBtn = await screen.findByRole('button', { name: 'Maria Santos' })
        await user.click(approverBtn)

        expect(screen.getByRole('button', { name: /solicitar aprovacao/i })).not.toBeDisabled()
    })

    it('renders approval chain with steps when approvals exist', async () => {
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('/approvals')) {
                return Promise.resolve({ data: { data: pendingApprovals } })
            }
            return Promise.resolve({ data: { data: [] } })
        })

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText('Joao Silva')).toBeInTheDocument()
            expect(screen.getByText('Maria Santos')).toBeInTheDocument()
        })
        expect(screen.getByText('Cadeia de Aprovacao')).toBeInTheDocument()
    })

    it('shows step numbers', async () => {
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('/approvals')) {
                return Promise.resolve({ data: { data: pendingApprovals } })
            }
            return Promise.resolve({ data: { data: [] } })
        })

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText(/Etapa 1/)).toBeInTheDocument()
            expect(screen.getByText(/Etapa 2/)).toBeInTheDocument()
        })
    })

    it('shows response notes on approved items', async () => {
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('/approvals')) {
                return Promise.resolve({ data: { data: pendingApprovals } })
            }
            return Promise.resolve({ data: { data: [] } })
        })

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText(/"LGTM"/)).toBeInTheDocument()
        })
    })

    it('shows "Responder" action for current user pending approval', async () => {
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('/approvals')) {
                return Promise.resolve({ data: { data: pendingApprovals } })
            }
            return Promise.resolve({ data: { data: [] } })
        })

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText(/Responder/)).toBeInTheDocument()
        })
    })

    it('shows approve/reject buttons after clicking Responder', async () => {
        const user = userEvent.setup()
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('/approvals')) {
                return Promise.resolve({ data: { data: pendingApprovals } })
            }
            return Promise.resolve({ data: { data: [] } })
        })

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText(/Responder/)).toBeInTheDocument()
        })

        await user.click(screen.getByText(/Responder/))

        expect(screen.getByText('Aprovar')).toBeInTheDocument()
        expect(screen.getByText('Rejeitar')).toBeInTheDocument()
    })

    it('calls approve API when approve button is clicked', async () => {
        const user = userEvent.setup()
        mockHasPermission.mockReturnValue(true)
        mockApiPost.mockResolvedValue({ data: {} })
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('/approvals')) {
                return Promise.resolve({ data: { data: pendingApprovals } })
            }
            return Promise.resolve({ data: { data: [] } })
        })

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText(/Responder/)).toBeInTheDocument()
        })

        await user.click(screen.getByText(/Responder/))
        await user.click(screen.getByText('Aprovar'))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith(
                '/work-orders/15/approvals/9/approve',
                expect.any(Object)
            )
        })
    })

    it('toggles collapse/expand of approval chain', async () => {
        const user = userEvent.setup()
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('/approvals')) {
                return Promise.resolve({ data: { data: pendingApprovals } })
            }
            return Promise.resolve({ data: { data: [] } })
        })

        render(<ApprovalChain workOrderId={15} currentUserId={9} />)

        await waitFor(() => {
            expect(screen.getByText('Joao Silva')).toBeInTheDocument()
        })

        // Click the toggle button (the heading area)
        const heading = screen.getByText('Cadeia de Aprovacao')
        await user.click(heading.closest('button')!)

        // Steps should be hidden after collapse
        expect(screen.queryByText('Joao Silva')).not.toBeInTheDocument()
    })
})
