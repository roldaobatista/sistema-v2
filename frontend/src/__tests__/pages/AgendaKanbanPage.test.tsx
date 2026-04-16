import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { AgendaKanbanPage } from '@/pages/agenda/AgendaKanbanPage'

const { mockApiGet } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        patch: vi.fn(),
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: () => true,
    }),
}))

vi.mock('@/components/ui/button', () => ({
    Button: ({ children, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement> & { icon?: React.ReactNode; variant?: string }) => (
        <button {...props}>{children}</button>
    ),
}))

describe('AgendaKanbanPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockApiGet.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 10,
                        titulo: 'Item legado sem catalogo',
                        tipo: 'visita-tecnica',
                        prioridade: 'critica',
                        status: 'open',
                        comments_count: 0,
                        tags: [],
                    },
                ],
            },
        })
    })

    it('renderiza item com fallbacks seguros para prioridade desconhecida sem quebrar o card', async () => {
        render(<AgendaKanbanPage />)

        expect(await screen.findByText('Item legado sem catalogo')).toBeInTheDocument()
        expect(screen.getByText('Média')).toBeInTheDocument()
    })
})
