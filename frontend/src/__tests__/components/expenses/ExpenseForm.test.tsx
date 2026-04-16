import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import ExpenseForm from '@/components/expenses/ExpenseForm'

vi.mock('@/lib/compress-image', () => ({
    compressImage: vi.fn((file: File) => Promise.resolve(file)),
}))

const mockCategories = [
    { id: 1, name: 'Combustivel', color: '#ef4444' },
    { id: 2, name: 'Alimentacao', color: '#22c55e' },
    { id: 3, name: 'Material', color: '#3b82f6' },
]

describe('ExpenseForm', () => {
    const onSubmit = vi.fn()
    const onClose = vi.fn()
    const createReceiptFile = () => new File(['receipt'], 'receipt.jpg', { type: 'image/jpeg' })

    beforeEach(() => {
        vi.clearAllMocks()
        onSubmit.mockResolvedValue(undefined)
    })

    it('renders category selection chips', () => {
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)
        expect(screen.getByText('Combustivel')).toBeInTheDocument()
        expect(screen.getByText('Alimentacao')).toBeInTheDocument()
        expect(screen.getByText('Material')).toBeInTheDocument()
    })

    it('renders amount input field', () => {
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)
        expect(screen.getByPlaceholderText('0,00')).toBeInTheDocument()
    })

    it('renders description textarea', () => {
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)
        expect(screen.getByPlaceholderText('Detalhes da despesa...')).toBeInTheDocument()
    })

    it('save button is disabled when form is invalid (no category, no amount)', () => {
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)
        const saveBtn = screen.getByText('Salvar Despesa')
        expect(saveBtn.closest('button')).toBeDisabled()
    })

    it('mantem o botao desabilitado sem comprovante, mesmo com categoria e valor', async () => {
        const user = userEvent.setup()
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)

        // Select category
        await user.click(screen.getByText('Combustivel'))

        // Enter amount
        const amountInput = screen.getByPlaceholderText('0,00')
        await user.type(amountInput, '50.00')

        expect(screen.getByText('Salvar Despesa').closest('button')).toBeDisabled()
    })

    it('habilita o envio quando comprovante valido e anexado', async () => {
        const user = userEvent.setup()
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)

        await user.click(screen.getByText('Combustivel'))
        await user.type(screen.getByPlaceholderText('0,00'), '75.50')
        await user.upload(screen.getByLabelText('Selecionar comprovante'), createReceiptFile())

        expect(screen.getByText('Salvar Despesa').closest('button')).not.toBeDisabled()
    })

    it('calls onSubmit with correct data on save', async () => {
        const user = userEvent.setup()
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)

        await user.click(screen.getByText('Combustivel'))
        await user.type(screen.getByPlaceholderText('0,00'), '75.50')
        await user.type(screen.getByPlaceholderText('Detalhes da despesa...'), 'Gasolina posto')
        await user.upload(screen.getByLabelText('Selecionar comprovante'), createReceiptFile())
        await user.click(screen.getByText('Salvar Despesa'))

        expect(onSubmit).toHaveBeenCalledWith(
            expect.objectContaining({
                expense_category_id: 1,
                description: 'Gasolina posto',
                categoryName: 'Combustivel',
                photo: expect.any(File),
            })
        )
    })

    it('shows "Atualizar" button text when in edit mode', () => {
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} editingId={5} />)
        expect(screen.getByText('Atualizar')).toBeInTheDocument()
    })

    it('shows date field when showDateField is true', () => {
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} showDateField />)
        expect(screen.getByLabelText('Data da despesa')).toBeInTheDocument()
    })

    it('does not show date field by default', () => {
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)
        expect(screen.queryByLabelText('Data da despesa')).not.toBeInTheDocument()
    })

    it('shows photo upload area', () => {
        render(<ExpenseForm categories={mockCategories} onSubmit={onSubmit} />)
        expect(screen.getByText('Tirar foto ou selecionar')).toBeInTheDocument()
    })

    it('renders as sheet variant with close button', async () => {
        const user = userEvent.setup()
        render(
            <ExpenseForm categories={mockCategories} onSubmit={onSubmit} variant="sheet" onClose={onClose} />
        )
        expect(screen.getByText('Nova Despesa Avulsa')).toBeInTheDocument()

        await user.click(screen.getByLabelText('Fechar formulário'))
        expect(onClose).toHaveBeenCalled()
    })

    it('pre-fills data when initialData is provided', () => {
        render(
            <ExpenseForm
                categories={mockCategories}
                onSubmit={onSubmit}
                initialData={{ categoryId: 2, amount: '100', description: 'Almoco' }}
            />
        )
        const amountInput = screen.getByPlaceholderText('0,00') as HTMLInputElement
        expect(amountInput.value).toBe('R$\u00a0100,00')
        expect(screen.getByPlaceholderText('Detalhes da despesa...')).toHaveValue('Almoco')
    })
})
