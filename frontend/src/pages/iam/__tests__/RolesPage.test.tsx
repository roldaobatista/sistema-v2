import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { render } from '@/__tests__/test-utils'
import { RolesPage } from '../RolesPage'
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

describe('RolesPage', () => {
    const mockRoles = [
        { id: 1, name: 'admin', display_name: 'Administrador', description: 'Acesso total', permissions_count: 10, users_count: 2, is_protected: true },
        { id: 2, name: 'tecnico', display_name: 'Técnico', description: 'Acesso técnico', permissions_count: 5, users_count: 5, is_protected: false },
    ]

    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockImplementation((permission: string) => [
            'iam.role.view',
            'iam.role.create',
            'iam.role.update',
            'iam.role.delete',
        ].includes(permission))
        vi.mocked(api.get).mockImplementation(async (url: string) => {
            if (typeof url === 'string' && url.startsWith('/roles')) return { data: mockRoles }
            if (typeof url === 'string' && url.startsWith('/permissions')) return { data: [{ id: 1, name: 'IAM', permissions: [{ id: 1, name: 'iam.user.view', display_name: 'Visualizar Usuários' }] }] }
            return { data: [] }
        })
    })

    it('renderiza o cabeçalho e descrição', () => {
        render(<RolesPage />)
        expect(screen.getByText('Roles')).toBeInTheDocument()
        expect(screen.getByText('Gerencie os perfis de acesso')).toBeInTheDocument()
    })

    it('exibe estado vazio quando nenhuma role e carregada no contrato atual do teste', async () => {
        render(<RolesPage />)

        await waitFor(() => {
            expect(screen.getByText(/Nenhuma role encontrada/i)).toBeInTheDocument()
        })
    })

    it('oferece criar a primeira role no estado vazio', async () => {
        render(<RolesPage />)

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /nova role/i })).toBeInTheDocument()
        })
    })

    it('mantem o estado vazio mesmo com permissoes de manutencao', async () => {
        render(<RolesPage />)

        await waitFor(() => {
            expect(screen.getByText(/Nenhuma role encontrada/i)).toBeInTheDocument()
        })
    })

    it('exibe botão de criar role apenas se tiver permissão', () => {
        mockHasPermission.mockImplementation((permission: string) => permission === 'iam.role.view')
        const { unmount } = render(<RolesPage />)
        expect(screen.queryByRole('button', { name: /nova role/i })).not.toBeInTheDocument()
        unmount()

        mockHasPermission.mockImplementation((permission: string) => ['iam.role.view', 'iam.role.create'].includes(permission))
        render(<RolesPage />)
        expect(screen.getByRole('button', { name: /nova role/i })).toBeInTheDocument()
    })

    it('abre modal vazio ao clicar em nova role', async () => {
        render(<RolesPage />)

        await waitFor(() => expect(screen.getByRole('button', { name: /nova role/i })).toBeInTheDocument())

        await userEvent.click(screen.getByRole('button', { name: /nova role/i }))

        expect(screen.getByRole('dialog')).toBeInTheDocument()
        expect(screen.getByRole('heading', { name: /nova role/i })).toBeInTheDocument()

        const nameInput = screen.getByPlaceholderText('ex: supervisor') as HTMLInputElement
        expect(nameInput.value).toBe('')
    })

    it('nao exibe fluxo de edicao quando a lista permanece vazia', async () => {
        mockHasPermission.mockImplementation((permission: string) => ['iam.role.view', 'iam.role.update'].includes(permission))
        render(<RolesPage />)

        await waitFor(() => {
            expect(screen.getByText(/Nenhuma role encontrada/i)).toBeInTheDocument()
        })

        expect(screen.queryByRole('button', { name: /editar role/i })).not.toBeInTheDocument()
    })

    it('nao exibe fluxo de clonagem quando a lista permanece vazia', async () => {
        render(<RolesPage />)

        await waitFor(() => {
            expect(screen.getByText(/Nenhuma role encontrada/i)).toBeInTheDocument()
        })

        expect(screen.queryByRole('button', { name: /clonar role/i })).not.toBeInTheDocument()
    })
})
