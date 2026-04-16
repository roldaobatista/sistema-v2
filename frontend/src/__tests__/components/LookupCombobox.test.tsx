import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import { LookupCombobox } from '@/components/common/LookupCombobox'

const { mockApiGet, mockApiPost } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: mockApiPost,
        },
        getApiErrorMessage: (err: unknown, fallback: string) => fallback,
        unwrapData: (r: any) => r?.data?.data ?? r?.data ?? r,
    }
})

vi.mock('@/lib/safe-array', () => ({
    safeArray: (data: any) => Array.isArray(data) ? data : (data?.data ?? []),
}))

vi.mock('sonner', () => ({
    toast: { success: vi.fn(), error: vi.fn() },
}))

// Mock Popover and Command
vi.mock('@/components/ui/popover', () => ({
    Popover: ({ children, open }: { children: React.ReactNode; open: boolean }) => (
        <div data-testid="popover" data-open={open}>{children}</div>
    ),
    PopoverTrigger: ({ children }: { children: React.ReactNode }) => <div data-testid="popover-trigger">{children}</div>,
    PopoverContent: ({ children }: { children: React.ReactNode }) => <div data-testid="popover-content">{children}</div>,
}))

vi.mock('@/components/ui/command', () => ({
    Command: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    CommandInput: ({ placeholder, value, onValueChange }: any) => (
        <input data-testid="lookup-search" placeholder={placeholder} value={value} onChange={(e: React.ChangeEvent<HTMLInputElement>) => onValueChange(e.target.value)} />
    ),
    CommandList: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    CommandEmpty: ({ children }: { children: React.ReactNode }) => <div data-testid="command-empty">{children}</div>,
    CommandGroup: ({ children }: { children: React.ReactNode }) => <div data-testid="command-group">{children}</div>,
    CommandItem: ({ children, onSelect, value }: any) => (
        <div data-testid="lookup-item" data-value={value} role="option" onClick={onSelect}>{children}</div>
    ),
}))

vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, disabled, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: string; size?: string; icon?: React.ReactNode; loading?: boolean }) => (
        <button onClick={onClick} disabled={disabled} {...props}>{children}</button>
    ),
}))

const mockLookups = [
    { id: 1, name: 'Cartao', slug: 'cartao', is_active: true },
    { id: 2, name: 'Boleto', slug: 'boleto', is_active: true },
    { id: 3, name: 'Pix', slug: 'pix', is_active: true },
    { id: 4, name: 'Inativo', slug: 'inativo', is_active: false },
]

describe('LookupCombobox', () => {
    const onChange = vi.fn()

    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({ data: { data: mockLookups } })
    })

    it('renders with placeholder when no value', () => {
        render(<LookupCombobox lookupType="payment-methods" value="" onChange={onChange} />)
        expect(screen.getByText('Selecione...')).toBeInTheDocument()
    })

    it('renders custom placeholder', () => {
        render(<LookupCombobox lookupType="payment-methods" value="" onChange={onChange} placeholder="Choose method" />)
        expect(screen.getByText('Choose method')).toBeInTheDocument()
    })

    it('renders label when provided', () => {
        render(<LookupCombobox lookupType="payment-methods" value="" onChange={onChange} label="Pagamento" />)
        expect(screen.getByText('Pagamento')).toBeInTheDocument()
    })

    it('renders active lookup items only (filters inactive)', async () => {
        render(<LookupCombobox lookupType="service-types" value="" onChange={onChange} />)

        await waitFor(() => {
            const items = screen.getAllByTestId('lookup-item')
            // 3 active items, not 4
            expect(items).toHaveLength(3)
        })
    })

    it('calls onChange with selected value on item click', async () => {
        const user = userEvent.setup()
        render(<LookupCombobox lookupType="service-types" value="" onChange={onChange} />)

        await waitFor(() => {
            expect(screen.getAllByTestId('lookup-item')).toHaveLength(3)
        })

        await user.click(screen.getAllByTestId('lookup-item')[0])
        expect(onChange).toHaveBeenCalled()
    })

    it('has combobox role on trigger button', () => {
        render(<LookupCombobox lookupType="service-types" value="" onChange={onChange} />)
        expect(screen.getByRole('combobox')).toBeInTheDocument()
    })
})
