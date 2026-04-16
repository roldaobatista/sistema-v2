import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import TechChatDrawer from '@/components/tech/TechChatDrawer'

const { mockApiGet, mockApiPost, toastError, toastSuccess } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    toastError: vi.fn(),
    toastSuccess: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        user: { id: 99, name: 'Tecnico Atual' },
    }),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: mockApiPost,
        },
        buildStorageUrl: (path: string) => `https://storage.test/${path}`,
    }
})

vi.mock('sonner', () => ({
    toast: {
        error: toastError,
        success: toastSuccess,
    },
}))

const mockMessages = [
    {
        id: 10,
        user_id: 99,
        user: { name: 'Tecnico Atual' },
        message: 'Chegou no local',
        type: 'text',
        created_at: '2026-03-15T10:00:00Z',
    },
    {
        id: 11,
        user_id: 50,
        user: { name: 'Supervisor' },
        message: 'Pode iniciar o servico',
        type: 'text',
        created_at: '2026-03-15T10:05:00Z',
    },
]

describe('TechChatDrawer', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({ data: { data: mockMessages } })
    })

    it('renders nothing when isOpen is false', () => {
        const { container } = render(
            <TechChatDrawer workOrderId={1} isOpen={false} onClose={vi.fn()} />
        )
        expect(container.firstChild).toBeNull()
    })

    it('shows chat header with OS number', async () => {
        render(<TechChatDrawer workOrderId={42} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText('Chat Interno')).toBeInTheDocument()
            expect(screen.getByText(/OS #42/)).toBeInTheDocument()
        })
    })

    it('loads and displays messages', async () => {
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText('Chegou no local')).toBeInTheDocument()
            expect(screen.getByText('Pode iniciar o servico')).toBeInTheDocument()
        })
    })

    it('shows loading spinner while fetching messages', () => {
        mockApiGet.mockReturnValue(new Promise(() => {})) // Never resolves
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        expect(screen.getByText('Carregando conversa...')).toBeInTheDocument()
    })

    it('shows empty state when no messages', async () => {
        mockApiGet.mockResolvedValue({ data: { data: [] } })
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText(/Nenhuma mensagem ainda/)).toBeInTheDocument()
        })
    })

    it('sends a text message', async () => {
        const user = userEvent.setup()
        mockApiPost.mockResolvedValue({
            data: {
                data: { id: 20, user_id: 99, message: 'Nova mensagem', type: 'text', created_at: '2026-03-15T10:10:00Z' },
            },
        })

        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText('Chegou no local')).toBeInTheDocument()
        })

        const input = screen.getByPlaceholderText('Escreva sua mensagem...')
        await user.type(input, 'Nova mensagem')
        await user.click(screen.getByLabelText('Enviar mensagem'))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/work-orders/1/chats', {
                message: 'Nova mensagem',
                type: 'text',
            })
        })
    })

    it('disables send button when message is empty', async () => {
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText('Chegou no local')).toBeInTheDocument()
        })

        expect(screen.getByLabelText('Enviar mensagem')).toBeDisabled()
    })

    it('calls onClose when close button is clicked', async () => {
        const user = userEvent.setup()
        const onClose = vi.fn()
        render(<TechChatDrawer workOrderId={1} isOpen onClose={onClose} />)

        await waitFor(() => {
            expect(screen.getByText('Chegou no local')).toBeInTheDocument()
        })

        await user.click(screen.getByLabelText('Fechar chat'))
        expect(onClose).toHaveBeenCalled()
    })

    it('rejects invalid file types', async () => {
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText('Chegou no local')).toBeInTheDocument()
        })

        const fileInput = screen.getByLabelText('Anexar arquivo no chat') as HTMLInputElement
        const invalidFile = new File(['data'], 'doc.txt', { type: 'text/plain' })
        fireEvent.change(fileInput, { target: { files: [invalidFile] } })

        expect(mockApiPost).not.toHaveBeenCalled()
        expect(toastError).toHaveBeenCalledWith('Envie JPG, PNG, WEBP ou PDF.')
    })

    it('shows access denied message on 403 error', async () => {
        mockApiGet.mockRejectedValue({ response: { status: 403 } })
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText(/Sem permissao para acessar o chat/)).toBeInTheDocument()
        })
    })

    it('displays other user name on received messages', async () => {
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText('Supervisor')).toBeInTheDocument()
        })
    })
})
