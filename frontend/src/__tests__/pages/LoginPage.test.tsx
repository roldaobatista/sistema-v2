import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { LoginPage } from '@/pages/LoginPage'

const {
    mockLogin,
    mockIsLoading,
} = vi.hoisted(() => ({
    mockLogin: vi.fn(),
    mockIsLoading: { value: false },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        login: mockLogin,
        isLoading: mockIsLoading.value,
    }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/lib/utils', () => ({
    cn: (...args: unknown[]) => args.filter(Boolean).join(' '),
}))

describe('LoginPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockIsLoading.value = false
    })

    it('renders email field', () => {
        render(<LoginPage />)
        expect(screen.getByLabelText('E-mail')).toBeInTheDocument()
    })

    it('renders password field', () => {
        render(<LoginPage />)
        expect(screen.getByLabelText('Senha')).toBeInTheDocument()
    })

    it('renders submit button', () => {
        render(<LoginPage />)
        expect(screen.getByText('Entrar')).toBeInTheDocument()
    })

    it('renders a main landmark for the authentication content', () => {
        render(<LoginPage />)
        expect(screen.getByRole('main')).toBeInTheDocument()
    })

    it('renders forgot password link', () => {
        render(<LoginPage />)
        expect(screen.getByText('Esqueceu sua senha?')).toBeInTheDocument()
    })

    it('shows password toggle button', async () => {
        render(<LoginPage />)
        const passwordInput = screen.getByLabelText('Senha')
        expect(passwordInput).toHaveAttribute('type', 'password')
        expect(screen.getByRole('button', { name: 'Mostrar senha' })).toBeInTheDocument()
    })

    it('toggles password visibility', async () => {
        const user = userEvent.setup()
        render(<LoginPage />)
        const passwordInput = screen.getByLabelText('Senha')
        const toggleButton = screen.getByRole('button', { name: 'Mostrar senha' })

        expect(passwordInput).toHaveAttribute('type', 'password')
        await user.click(toggleButton)

        expect(passwordInput).toHaveAttribute('type', 'text')
        expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument()
    })

    it('calls login on form submit', async () => {
        const user = userEvent.setup()
        mockLogin.mockResolvedValue(undefined)
        render(<LoginPage />)

        await user.type(screen.getByLabelText('E-mail'), 'test@email.com')
        await user.type(screen.getByLabelText('Senha'), 'password123')
        await user.click(screen.getByText('Entrar'))

        expect(mockLogin).toHaveBeenCalledWith('test@email.com', 'password123')
    })

    it('shows error message on wrong credentials', async () => {
        const user = userEvent.setup()
        mockLogin.mockRejectedValue({
            response: { data: { message: 'Credenciais inválidas.' } },
        })
        render(<LoginPage />)

        await user.type(screen.getByLabelText('E-mail'), 'test@email.com')
        await user.type(screen.getByLabelText('Senha'), 'wrongpass')
        await user.click(screen.getByText('Entrar'))

        await waitFor(() => {
            expect(screen.getByText('Credenciais inválidas.')).toBeInTheDocument()
        })
    })

    it('shows loading state during login', () => {
        mockIsLoading.value = true
        render(<LoginPage />)
        expect(screen.getByText('Entrando...')).toBeInTheDocument()
    })

    it('renders branding text', () => {
        render(<LoginPage />)
        expect(screen.getByText('Bem-vindo de volta')).toBeInTheDocument()
    })
})
