import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage'

const { mockPost, mockToastSuccess, mockToastError, mockGetApiErrorMessage } = vi.hoisted(() => ({
    mockPost: vi.fn(),
    mockToastSuccess: vi.fn(),
    mockToastError: vi.fn(),
    mockGetApiErrorMessage: vi.fn(() => 'Erro ao enviar e-mail.'),
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

describe('ForgotPasswordPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockGetApiErrorMessage.mockReturnValue('Erro ao enviar e-mail.')
    })

    it('renders a main landmark for the recovery flow', () => {
        render(<ForgotPasswordPage />)

        expect(screen.getByRole('main')).toBeInTheDocument()
    })

    it('submits the recovery email and shows the confirmation state', async () => {
        const user = userEvent.setup()
        mockPost.mockResolvedValue({})

        render(<ForgotPasswordPage />)

        await user.type(screen.getByLabelText('E-mail'), 'test@email.com')
        await user.click(screen.getByRole('button', { name: 'Enviar link' }))

        await waitFor(() => {
            expect(mockPost).toHaveBeenCalledWith('/forgot-password', { email: 'test@email.com' })
        })

        expect(screen.getByText('E-mail enviado')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Enviar novamente' })).toBeInTheDocument()
    })
})
