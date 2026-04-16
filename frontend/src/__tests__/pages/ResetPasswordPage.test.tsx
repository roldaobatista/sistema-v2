import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { ResetPasswordPage } from '@/pages/ResetPasswordPage'

const { mockPost, mockToastSuccess, mockToastError, mockGetApiErrorMessage } = vi.hoisted(() => ({
    mockPost: vi.fn(),
    mockToastSuccess: vi.fn(),
    mockToastError: vi.fn(),
    mockGetApiErrorMessage: vi.fn(() => 'Erro ao redefinir senha.'),
}))

vi.mock('@/lib/api', () => ({
    default: {
        post: mockPost,
    },
    getApiErrorMessage: mockGetApiErrorMessage,
}))

vi.mock('sonner', () => ({
    toast: {
        success: mockToastSuccess,
        error: mockToastError,
    },
}))

describe('ResetPasswordPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockGetApiErrorMessage.mockReturnValue('Erro ao redefinir senha.')
    })

    it('renders a main landmark for the reset flow', () => {
        render(<ResetPasswordPage />, {
            route: '/resetar-senha?token=abc&email=test@email.com',
        })

        expect(screen.getByRole('main')).toBeInTheDocument()
    })

    it('associates the new password controls with accessible labels', () => {
        render(<ResetPasswordPage />, {
            route: '/resetar-senha?token=abc&email=test@email.com',
        })

        expect(screen.getByLabelText('Nova Senha')).toBeInTheDocument()
        expect(screen.getByLabelText('Confirmar Senha')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Mostrar senha' })).toBeInTheDocument()
    })

    it('toggles password visibility with an accessible control', async () => {
        const user = userEvent.setup()

        render(<ResetPasswordPage />, {
            route: '/resetar-senha?token=abc&email=test@email.com',
        })

        const passwordInput = screen.getByLabelText('Nova Senha')
        await user.click(screen.getByRole('button', { name: 'Mostrar senha' }))

        expect(passwordInput).toHaveAttribute('type', 'text')
        expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument()
    })
})
