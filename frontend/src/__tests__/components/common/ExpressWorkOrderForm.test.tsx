import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import { ExpressWorkOrderForm } from '@/components/common/ExpressWorkOrderForm'

const { mockApiPost, toastSuccess, toastError } = vi.hoisted(() => ({
    mockApiPost: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
        post: mockApiPost,
    },
}))

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

// Mock Select components
vi.mock('@/components/ui/select', () => ({
    Select: ({ children, value, onValueChange }: any) => (
        <div data-testid="select-root" data-value={value}>
            {typeof children === 'function' ? children({ value, onValueChange }) : children}
        </div>
    ),
    SelectTrigger: ({ children }: any) => <button data-testid="select-trigger">{children}</button>,
    SelectValue: ({ placeholder }: any) => <span>{placeholder}</span>,
    SelectContent: ({ children }: any) => <div data-testid="select-content">{children}</div>,
    SelectItem: ({ children, value }: any) => <option value={value}>{children}</option>,
}))

vi.mock('@/components/ui/input', () => ({
    Input: (props: React.InputHTMLAttributes<HTMLInputElement>) => <input {...props} />,
}))

vi.mock('@/components/ui/textarea', () => ({
    Textarea: (props: React.TextareaHTMLAttributes<HTMLTextAreaElement>) => <textarea {...props} />,
}))

vi.mock('@/components/ui/label', () => ({
    Label: ({ children, ...props }: any) => <label {...props}>{children}</label>,
}))

vi.mock('@/components/ui/button', () => ({
    Button: ({ children, disabled, ...props }: any) => (
        <button disabled={disabled} {...props}>{children}</button>
    ),
}))

describe('ExpressWorkOrderForm', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders form with title "OS Express"', () => {
        render(<ExpressWorkOrderForm />)
        expect(screen.getByText('OS Express')).toBeInTheDocument()
    })

    it('renders customer name field', () => {
        render(<ExpressWorkOrderForm />)
        expect(screen.getByLabelText('Cliente')).toBeInTheDocument()
    })

    it('renders description field', () => {
        render(<ExpressWorkOrderForm />)
        expect(screen.getByLabelText('O que será feito?')).toBeInTheDocument()
    })

    it('renders priority selector', () => {
        render(<ExpressWorkOrderForm />)
        // "Prioridade" appears in both Label and SelectValue placeholder
        const matches = screen.getAllByText('Prioridade')
        expect(matches.length).toBeGreaterThanOrEqual(1)
    })

    it('renders submit button with "Criar Agora" text', () => {
        render(<ExpressWorkOrderForm />)
        expect(screen.getByText('Criar Agora')).toBeInTheDocument()
    })

    it('allows typing in customer name', async () => {
        const user = userEvent.setup({ delay: null })
        render(<ExpressWorkOrderForm />)

        const input = screen.getByLabelText('Cliente')
        await user.type(input, 'Empresa Teste')
        expect(input).toHaveValue('Empresa Teste')
    })

    it('allows typing in description', async () => {
        const user = userEvent.setup({ delay: null })
        render(<ExpressWorkOrderForm />)

        const textarea = screen.getByLabelText('O que será feito?')
        await user.type(textarea, 'Calibracao de balanca')
        expect(textarea).toHaveValue('Calibracao de balanca')
    })

    it('submits form with correct data', async () => {
        const user = userEvent.setup({ delay: null })
        const onSuccess = vi.fn()
        mockApiPost.mockResolvedValue({ data: { data: { id: 1 } } })

        render(<ExpressWorkOrderForm onSuccess={onSuccess} />)

        await user.type(screen.getByLabelText('Cliente'), 'Empresa ABC')
        await user.type(screen.getByLabelText('O que será feito?'), 'Calibracao urgente')

        // Submit the form
        await user.click(screen.getByText('Criar Agora'))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/operational/work-orders/express', expect.objectContaining({
                customer_name: 'Empresa ABC',
                description: 'Calibracao urgente',
                priority: 'medium',
            }))
        })
    })

    it('shows success toast after successful submission', async () => {
        const user = userEvent.setup({ delay: null })
        mockApiPost.mockResolvedValue({ data: { data: { id: 1 } } })

        render(<ExpressWorkOrderForm />)

        await user.type(screen.getByLabelText('Cliente'), 'Test')
        await user.type(screen.getByLabelText('O que será feito?'), 'Test desc')
        await user.click(screen.getByText('Criar Agora'))

        await waitFor(() => {
            expect(toastSuccess).toHaveBeenCalledWith('OS Express criada com sucesso!')
        })
    })
})
