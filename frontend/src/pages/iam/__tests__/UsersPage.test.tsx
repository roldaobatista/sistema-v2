import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { render } from '@/__tests__/test-utils'
import { UsersPage } from '../UsersPage'
import api from '@/lib/api'

vi.mock('@/lib/api')
const { mockHasPermission } = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

describe('UsersPage', () => {
    const mockUsers = [
        { id: 1, name: 'João Silva', email: 'joao@example.com', phone: '11999999999', is_active: true, last_login_at: '2026-03-20T10:00:00Z', created_at: '2026-01-01T00:00:00Z', roles: [{ id: 1, name: 'admin', display_name: 'Administrador' }], branch_id: null, branch: null },
        { id: 2, name: 'Maria Souza', email: 'maria@example.com', phone: null, is_active: false, last_login_at: null, created_at: '2026-02-15T00:00:00Z', roles: [{ id: 2, name: 'tecnico', display_name: 'Técnico' }], branch_id: 1, branch: { id: 1, name: 'Filial SP' } },
    ]

    const mockStats = { total: 2, active: 1, inactive: 1, never_logged: 1, by_role: { admin: 1, tecnico: 1 }, recent_users: [] }

    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockImplementation((permission: string) => [
            'iam.user.view',
            'iam.user.export',
            'iam.user.create',
            'iam.user.update',
            'iam.user.delete',
        ].includes(permission))
        vi.mocked(api.get).mockImplementation(async (url: string) => {
            if (typeof url === 'string' && url.startsWith('/users/stats')) return { data: mockStats }
            if (typeof url === 'string' && url.startsWith('/users')) return { data: { data: mockUsers, last_page: 1, total: 2 } }
            if (url === '/roles') return { data: [{ id: 1, name: 'admin', display_name: 'Administrador' }, { id: 2, name: 'tecnico', display_name: 'Técnico' }] }
            if (url === '/branches') return { data: [{ id: 1, name: 'Filial SP' }] }
            return { data: [] }
        })
    })

    it('renderiza o cabeçalho correto', () => {
        render(<UsersPage />)
        expect(screen.getByText('Usuários')).toBeInTheDocument()
        expect(screen.getByText('Gerencie os usuários do sistema')).toBeInTheDocument()
    })

    it('renderiza a lista de usuários com dados', async () => {
        render(<UsersPage />)
        await waitFor(() => {
            expect(screen.getByText('João Silva')).toBeInTheDocument()
            expect(screen.getByText('maria@example.com')).toBeInTheDocument()
            expect(screen.getByText('Filial SP')).toBeInTheDocument()
        })
    })

    it('mostra badges de roles e status', async () => {
        render(<UsersPage />)
        await waitFor(() => {
            expect(screen.getByText('João Silva')).toBeInTheDocument()
            expect(screen.getByText('Maria Souza')).toBeInTheDocument()
            expect(screen.getAllByText('Ativo').length).toBeGreaterThan(0)
            expect(screen.getAllByText('Inativo').length).toBeGreaterThan(0)
        })
    })

    it('renderiza cards de estatísticas', async () => {
        render(<UsersPage />)
        await waitFor(() => {
            expect(screen.getByText('Total de usuários')).toBeInTheDocument()
            expect(screen.getAllByText('Ativos').length).toBeGreaterThan(0)
            expect(screen.getAllByText('Inativos').length).toBeGreaterThan(0)
            expect(screen.getByText('Nunca logaram')).toBeInTheDocument()
        })
    })

    it('filtra por status', async () => {
        render(<UsersPage />)

        await waitFor(() => expect(screen.getByRole('button', { name: /filtrar por ativos/i })).toBeInTheDocument())

        await userEvent.click(screen.getByRole('button', { name: /filtrar por ativos/i }))

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/users', expect.objectContaining({
                params: expect.objectContaining({ is_active: 1 })
            }))
        })
    })

    it('filtra por role', async () => {
        render(<UsersPage />)

        await waitFor(() => expect(screen.getByRole('combobox', { name: /filtrar por role/i })).toBeInTheDocument())
        await waitFor(() => expect(screen.getByRole('option', { name: /administrador/i })).toBeInTheDocument())

        await userEvent.selectOptions(screen.getByRole('combobox', { name: /filtrar por role/i }), 'admin')

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/users', expect.objectContaining({
                params: expect.objectContaining({ role: 'admin' })
            }))
        })
    })

    it('busca debounced funciona', async () => {
        render(<UsersPage />)

        const searchInput = screen.getByPlaceholderText('Buscar por nome ou email...')
        await userEvent.type(searchInput, 'joao')

        // Fast forward timers if we used fake timers, but simple waitFor is enough since it's 300ms
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/users', expect.objectContaining({
                params: expect.objectContaining({ search: 'joao' })
            }))
        }, { timeout: 1000 })
    })

    it('exportação csv dispara requisição', async () => {
        // Mock window.URL.createObjectURL and link.click
        const mockCreateObjectURL = vi.fn().mockReturnValue('blob:mock-url')
        const mockRevokeObjectURL = vi.fn()
        const mockClick = vi.fn()

        global.URL.createObjectURL = mockCreateObjectURL
        global.URL.revokeObjectURL = mockRevokeObjectURL

        // Setup document.createElement mock for 'a' tag to capture click
        const originalCreateElement = document.createElement.bind(document)
        vi.spyOn(document, 'createElement').mockImplementation((tagName) => {
            if (tagName === 'a') {
                return { href: '', download: '', click: mockClick } as unknown as HTMLAnchorElement
            }
            return originalCreateElement(tagName)
        })

        vi.mocked(api.get).mockImplementation(async (url: string) => {
            if (url === '/users/export') return { data: new Blob(['csv content']) }
            return { data: [] }
        })

        render(<UsersPage />)

        await waitFor(() => expect(screen.getByRole('button', { name: /exportar csv/i })).toBeInTheDocument())

        await userEvent.click(screen.getByRole('button', { name: /exportar csv/i }))

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/users/export', expect.objectContaining({ responseType: 'blob' }))
            expect(mockCreateObjectURL).toHaveBeenCalled()
            expect(mockClick).toHaveBeenCalled()
        })

        vi.restoreAllMocks()
    })

    it('exibe botão novo usuário apenas com permissão', () => {
        mockHasPermission.mockImplementation((permission: string) => permission === 'iam.user.view')
        const { unmount } = render(<UsersPage />)
        expect(screen.queryByRole('button', { name: /novo usuário/i })).not.toBeInTheDocument()
        unmount()

        mockHasPermission.mockImplementation((permission: string) => ['iam.user.view', 'iam.user.create'].includes(permission))
        render(<UsersPage />)
        expect(screen.getByRole('button', { name: /novo usuário/i })).toBeInTheDocument()
    })
})
