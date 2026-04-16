import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@/__tests__/test-utils'
import TechChatDrawer from '@/components/tech/TechChatDrawer'

const { mockApiGet, mockApiPost, toastError } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    toastError: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        user: { id: 99 },
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
    }
})

vi.mock('sonner', () => ({
    toast: {
        error: toastError,
        success: vi.fn(),
    },
}))

describe('TechChatDrawer', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 10,
                        user_id: 99,
                        message: 'Mensagem inicial',
                        type: 'text',
                        created_at: '2026-03-13T10:00:00Z',
                    },
                ],
            },
        })
    })

    it('consome envelope padrao da API no carregamento do chat', async () => {
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText('Mensagem inicial')).toBeInTheDocument()
        })
    })

    it('bloqueia anexo invalido no chat tecnico', async () => {
        render(<TechChatDrawer workOrderId={1} isOpen onClose={vi.fn()} />)

        await waitFor(() => {
            expect(screen.getByText('Mensagem inicial')).toBeInTheDocument()
        })

        const input = screen.getByLabelText('Anexar arquivo no chat') as HTMLInputElement
        const invalidFile = new File(['conteudo'], 'arquivo.txt', { type: 'text/plain' })

        fireEvent.change(input, { target: { files: [invalidFile] } })

        expect(mockApiPost).not.toHaveBeenCalled()
        expect(toastError).toHaveBeenCalledWith('Envie JPG, PNG, WEBP ou PDF.')
    })
})
