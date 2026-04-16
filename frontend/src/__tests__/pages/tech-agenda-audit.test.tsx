import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechAgendaPage from '@/pages/tech/TechAgendaPage'

const {
    mockNavigate,
    mockApiGet,
    mockApiPost,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
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

describe('Auditoria da central tecnica', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/agenda/summary') {
                return Promise.resolve({
                    data: {
                        data: {
                            abertos: 1,
                            em_andamento: 0,
                            seguindo: 1,
                            concluidos: 0,
                        },
                    },
                })
            }

            if (url === '/agenda/items') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 5,
                                titulo: 'Item sem status mapeado',
                                tipo: 'task',
                                status: 'legacy_status',
                                prioridade: 'medium',
                                watchers: [],
                            },
                        ],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('nao quebra ao renderizar itens com status desconhecido', async () => {
        render(<TechAgendaPage />)

        expect(await screen.findByText('Item sem status mapeado')).toBeInTheDocument()
    })

    it('envia toggle follow sem duplicar o prefixo /api/v1', async () => {
        const user = userEvent.setup()
        mockApiPost.mockResolvedValue({ data: { data: { success: true } } })

        render(<TechAgendaPage />)

        await user.click(await screen.findByRole('button', { name: 'Seguir item' }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/agenda/items/5/toggle-follow')
        })
    })
})
