import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { render } from '@/__tests__/test-utils'
import { PermissionsMatrixPage } from '../PermissionsMatrixPage'
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

describe('PermissionsMatrixPage', () => {
    const mockMatrix = {
        roles: [
            { id: 1, name: 'admin', display_name: 'Administrador' },
            { id: 2, name: 'tecnico', display_name: 'Técnico' },
        ],
        matrix: [
            {
                group: 'IAM',
                permissions: [
                    { id: 1, name: 'iam.user.view', criticality: 'LOW', roles: { admin: true, tecnico: true } },
                    { id: 2, name: 'iam.user.create', criticality: 'HIGH', roles: { admin: true, tecnico: false } },
                ],
            },
            {
                group: 'OS',
                permissions: [
                    { id: 3, name: 'os.view', criticality: 'LOW', roles: { admin: true, tecnico: true } },
                ],
            }
        ],
    }

    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(false)
        vi.mocked(api.get).mockImplementation(async (url: string) => {
            if (url === '/permissions/matrix') return { data: mockMatrix }
            return { data: {} }
        })
    })

    it('renderiza o título da página e subtítulo', async () => {
        render(<PermissionsMatrixPage />)

        await waitFor(() => {
            expect(screen.getByText('Matriz de Permissões')).toBeInTheDocument()
            expect(screen.getByText('Visualização de todas as permissões atribuídas a cada role')).toBeInTheDocument()
        })
    })

    it('renderiza headers de roles na tabela de forma dinâmica', async () => {
        render(<PermissionsMatrixPage />)

        await waitFor(() => {
            expect(screen.getByText('Administrador')).toBeInTheDocument()
            expect(screen.getByText('Técnico')).toBeInTheDocument()
        })
    })

    it('agrupa e renderiza as permissões por grupo (IAM, OS)', async () => {
        render(<PermissionsMatrixPage />)

        await waitFor(() => {
            // Encontra os headers dos grupos
            expect(screen.getByText('IAM')).toBeInTheDocument()
            expect(screen.getByText('OS')).toBeInTheDocument()

            // Encontra as permissões
            expect(screen.getByText('user.view')).toBeInTheDocument()
            expect(screen.getByText('user.create')).toBeInTheDocument()
            expect(screen.getByText('view')).toBeInTheDocument()
        })
    })

    it('exibe badges de criticidade coloridos', async () => {
        render(<PermissionsMatrixPage />)

        await waitFor(() => {
            expect(screen.getAllByText('HIGH').length).toBeGreaterThan(0)
            expect(screen.getAllByText('LOW').length).toBeGreaterThanOrEqual(2)
        })
    })

    it('mostra legenda para orientar o usuário sobre os ícones', async () => {
        render(<PermissionsMatrixPage />)

        await waitFor(() => {
            expect(screen.getByText('Concedida')).toBeInTheDocument()
            expect(screen.getByText('Negada')).toBeInTheDocument()
            expect(screen.getByText(/Criticidade alta/i)).toBeInTheDocument()
        })
    })

    it('filtra as permissões quando digita na busca e esconde grupos vazios', async () => {
        render(<PermissionsMatrixPage />)

        await waitFor(() => expect(screen.getByText('OS')).toBeInTheDocument())

        const searchInput = screen.getByPlaceholderText('Filtrar permissões...')

        // Digita algo que só existe no grupo IAM
        await userEvent.type(searchInput, 'iam.user.create')

        await waitFor(() => {
            // A permissão filtrada deve estar visível
            expect(screen.getByText('user.create')).toBeInTheDocument()
            expect(screen.getByText('IAM')).toBeInTheDocument()

            // O grupo OS e a permissão os.view não devem estar mais visíveis
            expect(screen.queryByText('OS')).not.toBeInTheDocument()
            expect(screen.queryByText(/^view$/)).not.toBeInTheDocument()
        })
    })

    it('chama API de toggle ao clicar em um botão editável que não seja super admin', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: { message: 'Toggle OK' } })

        // Permissão iam.permission.manage é necessária para alternar
        mockHasPermission.mockImplementation((permission: string) => permission === 'iam.permission.manage')
        render(<PermissionsMatrixPage />)

        await waitFor(() => expect(screen.getByText('Técnico')).toBeInTheDocument())

        const row = screen.getByText('user.create').closest('tr')

        const toggleButtons = within(row as HTMLElement).getAllByRole('button')

        await userEvent.click(toggleButtons[1])

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/permissions/toggle', {
                role_id: 2,
                permission_id: 2,
            })
        })
    })

    it('desabilita apenas a coluna de super admin por segurança', async () => {
        vi.mocked(api.get).mockImplementation(async (url: string) => {
            if (url === '/permissions/matrix') {
                return {
                    data: {
                        roles: [
                            { id: 1, name: 'super_admin', display_name: 'Super Admin' },
                            { id: 2, name: 'tecnico', display_name: 'Técnico' },
                        ],
                        matrix: [
                            {
                                group: 'IAM',
                                permissions: [
                                    { id: 2, name: 'iam.user.create', criticality: 'HIGH', roles: { super_admin: true, tecnico: false } },
                                ],
                            },
                        ],
                    }
                }
            }
            return { data: {} }
        })

        mockHasPermission.mockImplementation((permission: string) => permission === 'iam.permission.manage')
        render(<PermissionsMatrixPage />)

        await waitFor(() => expect(screen.getByText('user.create')).toBeInTheDocument())

        const row = screen.getByText('user.create').closest('tr')
        const toggleButtons = within(row as HTMLElement).getAllByRole('button')

        expect(toggleButtons[0]).toBeDisabled()
        expect(toggleButtons[1]).not.toBeDisabled()
    })
})
