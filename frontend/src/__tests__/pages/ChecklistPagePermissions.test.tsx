import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { ChecklistPage } from '@/pages/operational/checklists/ChecklistPage'

const { mockApiGet, mockHasPermission } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockHasPermission: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: vi.fn(),
            put: vi.fn(),
            delete: vi.fn(),
        },
    }
})

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

describe('ChecklistPage permissions', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        vi.stubGlobal('ResizeObserver', class {
            observe() {}
            unobserve() {}
            disconnect() {}
        })
        mockApiGet.mockResolvedValue({ data: [] })
    })

    it('bloqueia visualizacao sem technicians.checklist.view', () => {
        mockHasPermission.mockReturnValue(false)

        render(<ChecklistPage />)

        expect(screen.getByText(/nao possui permissao para visualizar checklists pre-visita/i)).toBeInTheDocument()
        expect(screen.queryByText(/novo checklist/i)).not.toBeInTheDocument()
    })

    it('oculta criacao e edicao sem technicians.checklist.manage', async () => {
        mockHasPermission.mockImplementation((permission: string) => permission === 'technicians.checklist.view')
        mockApiGet.mockResolvedValue({
            data: [
                {
                    id: 1,
                    name: 'Checklist Base',
                    description: 'Inspecao inicial',
                    is_active: true,
                    items: [{ id: 1 }],
                    created_at: '2026-03-13T10:00:00Z',
                },
            ],
        })

        render(<ChecklistPage />)

        expect(await screen.findByText('Checklist Base')).toBeInTheDocument()
        expect(screen.queryByText(/novo checklist/i)).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /editar/i })).not.toBeInTheDocument()
    })
})
