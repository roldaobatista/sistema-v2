import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { fireEvent, render, screen, waitFor } from '@/__tests__/test-utils'
import TechFeedbackPage from '@/pages/tech/TechFeedbackPage'

const { mockNavigate, mockApiGet, mockApiPost, mockToast } = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockToast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    }
})

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        user: { id: 42, name: 'Tecnico Teste' },
    }),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
    },
    getApiErrorMessage: vi.fn((error: { response?: { data?: { message?: string } } } | undefined, fallback: string) =>
        error?.response?.data?.message ?? fallback
    ),
    buildStorageUrl: vi.fn((path: string | null | undefined) => (path ? `/storage/${path}` : null)),
}))

vi.mock('@/lib/utils', () => ({
    cn: (...args: Array<string | false | null | undefined>) => args.filter(Boolean).join(' '),
}))

vi.mock('sonner', () => ({
    toast: mockToast,
}))

describe('TechFeedbackPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({ data: { data: { manager_id: 7 } } })
    })

    it('envia feedback do técnico no contrato aceito pelo backend', async () => {
        mockApiPost.mockResolvedValue({ data: { data: { id: 1 } } })

        const user = userEvent.setup()
        render(<TechFeedbackPage />)

        await user.click(screen.getByRole('button', { name: /sugestão/i }))
        await user.click(screen.getByRole('button', { name: /processo/i }))
        fireEvent.change(screen.getByPlaceholderText('Título'), { target: { value: 'Rota insegura' } })
        fireEvent.change(screen.getByPlaceholderText('Mensagem'), { target: { value: 'Precisamos ajustar a ordem de atendimento.' } })
        await user.click(screen.getByRole('button', { name: /enviar feedback/i }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/tech/sync/batch', {
                mutations: [{
                    type: 'feedback',
                    data: {
                        to_user_id: 7,
                        content: '[Processo] Rota insegura\n\nPrecisamos ajustar a ordem de atendimento.',
                        type: 'suggestion',
                        visibility: 'manager_only',
                    },
                }],
            })
        })

        expect(mockToast.success).toHaveBeenCalledWith('Feedback enviado!')
    })

    it('carrega histórico filtrando apenas feedbacks enviados pelo técnico e exibe link de anexo', async () => {
        mockApiGet
            .mockResolvedValueOnce({ data: { data: { manager_id: 7 } } })
            .mockResolvedValueOnce({
                data: {
                    data: [
                        {
                            id: 11,
                            type: 'concern',
                            content: '[Segurança] Cabo exposto',
                            created_at: '2026-03-20T12:00:00Z',
                            fromUser_id: 42,
                            attachment_path: 'continuous-feedback/cabo.png',
                        },
                        {
                            id: 12,
                            type: 'praise',
                            content: 'Nao deve aparecer',
                            created_at: '2026-03-20T12:10:00Z',
                            fromUser_id: 77,
                        },
                    ],
                },
            })

        const user = userEvent.setup()
        render(<TechFeedbackPage />)

        await user.click(screen.getByRole('button', { name: /histórico/i }))

        expect(await screen.findByText('Cabo exposto')).toBeInTheDocument()
        expect(screen.queryByText('Nao deve aparecer')).not.toBeInTheDocument()
        expect(screen.getByRole('link', { name: /ver anexo/i })).toHaveAttribute('href', '/storage/continuous-feedback/cabo.png')
    })
})
