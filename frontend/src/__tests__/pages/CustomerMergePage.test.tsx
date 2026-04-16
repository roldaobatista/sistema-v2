import { beforeEach, describe, expect, it, vi } from 'vitest'

import { render, screen } from '@/__tests__/test-utils'
import { CustomerMergePage } from '@/pages/cadastros/CustomerMergePage'

const mockNavigate = vi.fn()
const mockHasPermission = vi.fn<(permission: string) => boolean>()
const mockSearchDuplicates = vi.fn()
const mockMerge = vi.fn()

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    }
})

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('@/lib/customer-api', () => ({
    customerApi: {
        searchDuplicates: (...args: unknown[]) => mockSearchDuplicates(...args),
        merge: (...args: unknown[]) => mockMerge(...args),
    },
}))

describe('CustomerMergePage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('bloqueia a tela quando o usuario nao tem permissao de update', async () => {
        mockHasPermission.mockReturnValue(false)

        render(<CustomerMergePage />)

        expect(await screen.findByText(/Voce nao tem permissao para mesclar clientes/i)).toBeInTheDocument()
        expect(mockSearchDuplicates).not.toHaveBeenCalled()
    })
})
