import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import { AsyncSelect } from '@/components/ui/async-select'

const { mockApiGet } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

vi.mock('@/lib/sentry', () => ({
    captureError: vi.fn(),
}))

vi.mock('sonner', () => ({
    toast: { error: vi.fn(), success: vi.fn() },
}))

const mockOptions = [
    { id: 1, name: 'Customer A', price: 100 },
    { id: 2, name: 'Customer B', price: 200 },
    { id: 3, name: 'Customer C' },
]

describe('AsyncSelect', () => {
    const defaultProps = {
        onChange: vi.fn(),
        endpoint: '/api/customers',
    }

    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({ data: { data: mockOptions } })
    })

    it('renders with default placeholder', () => {
        render(<AsyncSelect {...defaultProps} />)
        expect(screen.getByText('Selecione...')).toBeInTheDocument()
    })

    it('shows custom placeholder', () => {
        render(<AsyncSelect {...defaultProps} placeholder="Pick a customer" />)
        expect(screen.getByText('Pick a customer')).toBeInTheDocument()
    })

    it('renders label when provided', () => {
        render(<AsyncSelect {...defaultProps} label="Cliente" />)
        expect(screen.getByText('Cliente')).toBeInTheDocument()
    })

    it('opens dropdown and shows search input on click', async () => {
        const user = userEvent.setup()
        render(<AsyncSelect {...defaultProps} />)

        await user.click(screen.getByText('Selecione...'))
        expect(screen.getByPlaceholderText('Buscar...')).toBeInTheDocument()
    })

    it('does not open when disabled', async () => {
        const user = userEvent.setup()
        render(<AsyncSelect {...defaultProps} disabled />)

        await user.click(screen.getByText('Selecione...'))
        expect(screen.queryByPlaceholderText('Buscar...')).not.toBeInTheDocument()
    })

    it('fetches options after debounce when opened', async () => {
        const user = userEvent.setup()
        render(<AsyncSelect {...defaultProps} />)

        await user.click(screen.getByText('Selecione...'))

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/api/customers', {
                params: { search: '', per_page: 20 },
            })
        })
    })

    it('displays fetched options', async () => {
        const user = userEvent.setup()
        render(<AsyncSelect {...defaultProps} />)

        await user.click(screen.getByText('Selecione...'))

        await waitFor(() => {
            expect(screen.getByText('Customer A')).toBeInTheDocument()
            expect(screen.getByText('Customer B')).toBeInTheDocument()
            expect(screen.getByText('Customer C')).toBeInTheDocument()
        })
    })

    it('debounces search input before fetching', async () => {
        const user = userEvent.setup()
        render(<AsyncSelect {...defaultProps} />)

        await user.click(screen.getByText('Selecione...'))
        const input = screen.getByPlaceholderText('Buscar...')
        await user.type(input, 'Cust')

        // Wait for debounce (300ms)
        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/api/customers', {
                params: { search: 'Cust', per_page: 20 },
            })
        })
    })

    it('selects an option and fires onChange', async () => {
        const user = userEvent.setup()
        render(<AsyncSelect {...defaultProps} />)

        await user.click(screen.getByText('Selecione...'))

        await waitFor(() => {
            expect(screen.getByText('Customer A')).toBeInTheDocument()
        })

        await user.click(screen.getByText('Customer A'))

        expect(defaultProps.onChange).toHaveBeenCalledWith(
            expect.objectContaining({ id: 1, label: 'Customer A' })
        )
        // Dropdown should close
        expect(screen.queryByPlaceholderText('Buscar...')).not.toBeInTheDocument()
        // Selected label should be displayed
        expect(screen.getByText('Customer A')).toBeInTheDocument()
    })

    it('clears selection when clear button is clicked', async () => {
        const user = userEvent.setup()
        render(
            <AsyncSelect
                {...defaultProps}
                initialOption={{ id: 1, label: 'Customer A', value: { id: 1 } }}
                value={1}
            />
        )

        expect(screen.getByText('Customer A')).toBeInTheDocument()

        // The X icon button to clear
        const clearButtons = screen.getByText('Customer A').closest('.relative')?.querySelectorAll('.rounded-full')
        const clearBtn = clearButtons?.[0]
        if (clearBtn) await user.click(clearBtn)

        expect(defaultProps.onChange).toHaveBeenCalledWith(null)
    })

    it('shows loading spinner while fetching', async () => {
        const user = userEvent.setup()
        // Create a promise that won't resolve immediately
        let resolvePromise: (v: unknown) => void
        mockApiGet.mockReturnValue(new Promise((resolve) => { resolvePromise = resolve }))

        const { container } = render(<AsyncSelect {...defaultProps} />)
        await user.click(screen.getByText('Selecione...'))

        // Should show loading indicator (Loader2 has animate-spin class)
        await waitFor(() => {
            expect(container.querySelector('.animate-spin')).toBeInTheDocument()
        })

        // Resolve the promise to clean up
        resolvePromise!({ data: { data: [] } })
    })

    it('shows "Nenhum resultado encontrado" when no options returned', async () => {
        const user = userEvent.setup()
        mockApiGet.mockResolvedValue({ data: { data: [] } })

        render(<AsyncSelect {...defaultProps} />)
        await user.click(screen.getByText('Selecione...'))

        await waitFor(() => {
            expect(screen.getByText('Nenhum resultado encontrado')).toBeInTheDocument()
        })
    })

    it('shows error toast on API failure', async () => {
        const { toast } = await import('sonner')
        const user = userEvent.setup()
        mockApiGet.mockRejectedValue(new Error('Network error'))

        render(<AsyncSelect {...defaultProps} />)
        await user.click(screen.getByText('Selecione...'))

        await waitFor(() => {
            expect(toast.error).toHaveBeenCalledWith('Erro ao carregar opções')
        })
    })

    it('displays subLabel when option has price', async () => {
        const user = userEvent.setup()
        render(<AsyncSelect {...defaultProps} />)

        await user.click(screen.getByText('Selecione...'))

        await waitFor(() => {
            expect(screen.getByText('R$ 100')).toBeInTheDocument()
        })
    })

    it('displays initialOption on mount', () => {
        render(
            <AsyncSelect
                {...defaultProps}
                initialOption={{ id: 5, label: 'Pre-selected', value: { id: 5 } }}
                value={5}
            />
        )
        expect(screen.getByText('Pre-selected')).toBeInTheDocument()
    })

    it('resincroniza a opcao exibida quando value e initialOption mudam sem desmontar', () => {
        const { rerender } = render(
            <AsyncSelect
                {...defaultProps}
                initialOption={{ id: 1, label: 'Customer A', value: { id: 1 } }}
                value={1}
            />
        )

        expect(screen.getByText('Customer A')).toBeInTheDocument()

        rerender(
            <AsyncSelect
                {...defaultProps}
                initialOption={{ id: 2, label: 'Customer B', value: { id: 2 } }}
                value={2}
            />
        )

        expect(screen.getByText('Customer B')).toBeInTheDocument()
    })
})
