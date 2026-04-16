import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import { ItemSearchCombobox } from '@/components/common/ItemSearchCombobox'

// Mock Popover and Command components
vi.mock('@/components/ui/popover', () => ({
    Popover: ({ children, open, onOpenChange }: { children: React.ReactNode; open: boolean; onOpenChange: (v: boolean) => void }) => (
        <div data-open={open} data-testid="popover">{children}</div>
    ),
    PopoverTrigger: ({ children, asChild }: { children: React.ReactNode; asChild?: boolean }) => <div data-testid="popover-trigger">{children}</div>,
    PopoverContent: ({ children }: { children: React.ReactNode }) => <div data-testid="popover-content">{children}</div>,
}))

vi.mock('@/components/ui/command', () => ({
    Command: ({ children, filter }: { children: React.ReactNode; filter?: any }) => <div data-testid="command">{children}</div>,
    CommandInput: ({ placeholder }: { placeholder: string }) => <input data-testid="command-input" placeholder={placeholder} />,
    CommandList: ({ children }: { children: React.ReactNode }) => <div data-testid="command-list">{children}</div>,
    CommandEmpty: ({ children }: { children: React.ReactNode }) => <div data-testid="command-empty">{children}</div>,
    CommandGroup: ({ children }: { children: React.ReactNode }) => <div data-testid="command-group">{children}</div>,
    CommandItem: ({ children, onSelect, value }: { children: React.ReactNode; onSelect: () => void; value?: string }) => (
        <div data-testid="command-item" data-value={value} role="option" onClick={onSelect}>{children}</div>
    ),
}))

vi.mock('@/components/ui/button', () => ({
    Button: ({ children, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: string; role?: string }) => (
        <button {...props}>{children}</button>
    ),
}))

const mockItems = [
    { id: 1, name: 'Calibracao de Balanca', sell_price: 150, code: 'SRV001' },
    { id: 2, name: 'Manutencao Preventiva', sell_price: 200, code: 'SRV002' },
    { id: 3, name: 'Verificacao Inicial', default_price: 80 },
]

const mockItemsWithStringPrices = [
    { id: 11, name: 'Produto legado', sell_price: '150.50', code: 'PRD001' },
    { id: 12, name: 'Servico legado', default_price: '80', code: 'SRV099' },
]

describe('ItemSearchCombobox', () => {
    const onSelect = vi.fn()

    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders with placeholder when no value selected', () => {
        render(<ItemSearchCombobox items={mockItems} type="service" onSelect={onSelect} placeholder="Selecione servico" />)
        expect(screen.getByText('Selecione servico')).toBeInTheDocument()
    })

    it('shows default placeholder when none provided', () => {
        render(<ItemSearchCombobox items={mockItems} type="service" onSelect={onSelect} />)
        expect(screen.getByText('Selecione...')).toBeInTheDocument()
    })

    it('displays selected item name when value matches', () => {
        render(<ItemSearchCombobox items={mockItems} type="service" value={1} onSelect={onSelect} />)
        // Appears in trigger and in the list
        const matches = screen.getAllByText('Calibracao de Balanca')
        expect(matches.length).toBeGreaterThanOrEqual(1)
    })

    it('renders all items as command options', () => {
        render(<ItemSearchCombobox items={mockItems} type="service" onSelect={onSelect} />)
        const items = screen.getAllByTestId('command-item')
        expect(items).toHaveLength(3)
    })

    it('displays item prices formatted as BRL', () => {
        render(<ItemSearchCombobox items={mockItems} type="service" onSelect={onSelect} />)
        expect(screen.getByText(/150,00/)).toBeInTheDocument()
        expect(screen.getByText(/200,00/)).toBeInTheDocument()
    })

    it('formats legacy string prices without losing selection support', () => {
        render(<ItemSearchCombobox items={mockItemsWithStringPrices} type="product" value={11} onSelect={onSelect} />)
        expect(screen.getAllByText('Produto legado').length).toBeGreaterThanOrEqual(1)
        expect(screen.getByText(/150,50/)).toBeInTheDocument()
        expect(screen.getByText(/80,00/)).toBeInTheDocument()
    })

    it('fires onSelect with item id when clicked', async () => {
        const user = userEvent.setup()
        render(<ItemSearchCombobox items={mockItems} type="service" onSelect={onSelect} />)

        const items = screen.getAllByTestId('command-item')
        await user.click(items[0])

        expect(onSelect).toHaveBeenCalledWith(1)
    })

    it('shows item code in sub-text', () => {
        render(<ItemSearchCombobox items={mockItems} type="service" onSelect={onSelect} />)
        expect(screen.getByText(/Ref: SRV001/)).toBeInTheDocument()
    })

    it('shows empty state text', () => {
        render(<ItemSearchCombobox items={mockItems} type="service" onSelect={onSelect} />)
        expect(screen.getByText('Nenhum resultado encontrado.')).toBeInTheDocument()
    })

    it('renders search input with correct placeholder', () => {
        render(<ItemSearchCombobox items={mockItems} type="product" onSelect={onSelect} />)
        expect(screen.getByPlaceholderText('Buscar por nome ou código...')).toBeInTheDocument()
    })

    it('has combobox role on trigger button', () => {
        render(<ItemSearchCombobox items={mockItems} type="product" onSelect={onSelect} />)
        expect(screen.getByRole('combobox')).toBeInTheDocument()
    })
})
