import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import LeavesManagementPage from '@/pages/rh/LeavesManagementPage'

const {
    mockHasPermission,
    mockHasRole,
    mockHrApi,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn<(permission: string) => boolean>(),
    mockHasRole: vi.fn<(role: string) => boolean>(),
    mockHrApi: {
        leaves: {
            list: vi.fn(),
            create: vi.fn(),
            approve: vi.fn(),
            reject: vi.fn(),
        },
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
        hasRole: mockHasRole,
    }),
}))

vi.mock('@/lib/hr-api', () => ({
    hrApi: mockHrApi,
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

function makeLeave(overrides: Record<string, unknown> = {}) {
    return {
        id: 10,
        user_id: 5,
        type: 'medical',
        start_date: '2026-04-01',
        end_date: '2026-04-03',
        days_count: 3,
        status: 'pending',
        reason: 'Atestado',
        document_path: null,
        user: { id: 5, name: 'Maria' },
        ...overrides,
    }
}

describe('LeavesManagementPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasRole.mockReturnValue(false)
        mockHasPermission.mockImplementation((permission: string) =>
            ['hr.leave.create', 'hr.leave.approve'].includes(permission)
        )
        mockHrApi.leaves.list.mockResolvedValue({ data: { data: [makeLeave()] } })
        mockHrApi.leaves.create.mockResolvedValue({ data: {} })
        mockHrApi.leaves.approve.mockResolvedValue({ data: {} })
        mockHrApi.leaves.reject.mockResolvedValue({ data: {} })
    })

    it('aprova solicitacao usando o client oficial de RH', async () => {
        const user = userEvent.setup()

        render(<LeavesManagementPage />)

        await screen.findByText('Maria')
        await user.click(screen.getByRole('button', { name: /aprovar/i }))
        await user.click(screen.getByRole('button', { name: /confirmar/i }))

        await waitFor(() => {
            expect(mockHrApi.leaves.approve).toHaveBeenCalledWith(10)
        })
        expect(mockHrApi.leaves.reject).not.toHaveBeenCalled()
    })

    it('rejeita solicitacao com payload compativel com o backend', async () => {
        const user = userEvent.setup()

        render(<LeavesManagementPage />)

        await screen.findByText('Maria')
        await user.click(screen.getByRole('button', { name: /rejeitar/i }))
        await user.type(
            screen.getByPlaceholderText(/observações \(opcional para aprovação, recomendado para rejeição\)/i),
            'Saldo insuficiente'
        )
        await user.click(screen.getByRole('button', { name: /confirmar/i }))

        await waitFor(() => {
            expect(mockHrApi.leaves.reject).toHaveBeenCalledWith(10, {
                rejection_reason: 'Saldo insuficiente',
                reason: 'Saldo insuficiente',
            })
        })
        expect(mockHrApi.leaves.approve).not.toHaveBeenCalled()
    })

    it('oculta criacao quando o usuario nao tem permissao de solicitar', async () => {
        mockHasPermission.mockImplementation((permission: string) => permission === 'hr.leave.approve')

        render(<LeavesManagementPage />)

        await screen.findByText('Maria')
        expect(screen.queryByRole('button', { name: /nova solicitação/i })).not.toBeInTheDocument()
    })
})
