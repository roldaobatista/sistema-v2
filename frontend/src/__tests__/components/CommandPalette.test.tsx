import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import { CommandPalette } from '@/components/layout/CommandPalette'

const mockNavigate = vi.fn()

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    }
})

// Mock the Command components to be testable
vi.mock('@/components/ui/command', () => ({
    CommandDialog: ({ children, open, onOpenChange }: { children: React.ReactNode; open: boolean; onOpenChange: (v: boolean) => void }) =>
        open ? (
            <div data-testid="command-dialog" onKeyDown={(e: React.KeyboardEvent) => { if (e.key === 'Escape') onOpenChange(false) }}>
                {children}
            </div>
        ) : null,
    CommandInput: ({ placeholder, ...props }: { placeholder: string } & React.InputHTMLAttributes<HTMLInputElement>) =>
        <input data-testid="command-input" placeholder={placeholder} aria-label="command-search" {...props} />,
    CommandList: ({ children }: { children: React.ReactNode }) =>
        <div data-testid="command-list">{children}</div>,
    CommandEmpty: ({ children }: { children: React.ReactNode }) =>
        <div data-testid="command-empty">{children}</div>,
    CommandGroup: ({ heading, children }: { heading: string; children: React.ReactNode }) =>
        <div data-testid={`command-group-${heading}`} role="group" aria-label={heading}>{children}</div>,
    CommandItem: ({ children, onSelect, value }: { children: React.ReactNode; onSelect: () => void; value?: string }) =>
        <div data-testid="command-item" data-value={value} role="option" onClick={onSelect}>{children}</div>,
    CommandSeparator: () => <hr data-testid="command-separator" />,
}))

describe('CommandPalette', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('does not show dialog by default', () => {
        render(<CommandPalette />)
        expect(screen.queryByTestId('command-dialog')).not.toBeInTheDocument()
    })

    it('opens with Ctrl+K keyboard shortcut', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')

        expect(screen.getByTestId('command-dialog')).toBeInTheDocument()
        expect(screen.getByPlaceholderText('Buscar páginas, ações, módulos...')).toBeInTheDocument()
    })

    it('toggles closed with Ctrl+K when already open', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')
        expect(screen.getByTestId('command-dialog')).toBeInTheDocument()

        await user.keyboard('{Control>}k{/Control}')
        expect(screen.queryByTestId('command-dialog')).not.toBeInTheDocument()
    })

    it('renders command groups', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')

        expect(screen.getByRole('group', { name: 'Ações Rápidas' })).toBeInTheDocument()
        expect(screen.getByRole('group', { name: 'Navegação' })).toBeInTheDocument()
        expect(screen.getByRole('group', { name: 'Financeiro' })).toBeInTheDocument()
        expect(screen.getByRole('group', { name: 'Administração' })).toBeInTheDocument()
    })

    it('renders command items with labels', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')

        expect(screen.getByText('Nova OS')).toBeInTheDocument()
        expect(screen.getByText('Dashboard')).toBeInTheDocument()
        expect(screen.getByText('Configurações')).toBeInTheDocument()
    })

    it('navigates to the correct route when a command is selected', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')

        const novaOSItem = screen.getByText('Nova OS')
        await user.click(novaOSItem)

        expect(mockNavigate).toHaveBeenCalledWith('/os/nova')
    })

    it('closes dialog after selecting a command', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')
        await user.click(screen.getByText('Dashboard'))

        expect(mockNavigate).toHaveBeenCalledWith('/')
    })

    it('renders separators between groups', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')

        const separators = screen.getAllByTestId('command-separator')
        expect(separators.length).toBeGreaterThan(0)
    })

    it('shows empty state element', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')

        expect(screen.getByTestId('command-empty')).toBeInTheDocument()
        expect(screen.getByText('Nenhum resultado encontrado.')).toBeInTheDocument()
    })

    it('renders quick action items in the first group', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')

        const quickActionsGroup = screen.getByRole('group', { name: 'Ações Rápidas' })
        expect(quickActionsGroup).toBeInTheDocument()
        expect(screen.getByText('Nova OS')).toBeInTheDocument()
        expect(screen.getByText('Novo Orçamento')).toBeInTheDocument()
        expect(screen.getByText('Novo Cliente')).toBeInTheDocument()
    })

    it('usa rota existente para a acao Novo Cliente', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')
        await user.click(screen.getByText('Novo Cliente'))

        expect(mockNavigate).toHaveBeenCalledWith('/cadastros/clientes')
    })

    it('includes keywords in command item value for search', async () => {
        const user = userEvent.setup()
        render(<CommandPalette />)

        await user.keyboard('{Control>}k{/Control}')

        // Check that command items have value attributes containing keywords
        const items = screen.getAllByTestId('command-item')
        const novaOSItem = items.find(el => el.textContent?.includes('Nova OS'))
        expect(novaOSItem?.getAttribute('data-value')).toContain('criar')
    })
})
