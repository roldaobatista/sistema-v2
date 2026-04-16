import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'
import { FundTransfersPage } from '@/pages/financeiro/FundTransfersPage'
import { ExpensesPage } from '@/pages/financeiro/ExpensesPage'
import { ExpenseReimbursementsPage } from '@/pages/financeiro/ExpenseReimbursementsPage'
import { AccountPayableCategoriesPage } from '@/pages/financeiro/AccountPayableCategoriesPage'
import { AccountsPayablePage } from '@/pages/financeiro/AccountsPayablePage'
import { AccountsReceivablePage } from '@/pages/financeiro/AccountsReceivablePage'
import { InvoicesPage } from '@/pages/financeiro/InvoicesPage'
import { BankReconciliationPage } from '@/pages/financeiro/BankReconciliationPage'
import { CashFlowPage } from '@/pages/financeiro/CashFlowPage'
import { PaymentMethodsPage } from '@/pages/financeiro/PaymentMethodsPage'
import { SupplierAdvancesPage } from '@/pages/financeiro/SupplierAdvancesPage'
import { SupplierContractsPage } from '@/pages/financeiro/SupplierContractsPage'
import { FinancialChecksPage } from '@/pages/financeiro/FinancialChecksPage'
import { BatchPaymentApprovalPage } from '@/pages/financeiro/BatchPaymentApprovalPage'
import { PaymentsPage } from '@/pages/financeiro/PaymentsPage'
import DebtRenegotiationPage from '@/pages/financeiro/DebtRenegotiationPage'
import ReconciliationRulesPage from '@/pages/financeiro/ReconciliationRulesPage'

const {
    mockApiGet,
    mockApiPost,
    mockApiPut,
    mockApiDelete,
    mockHasPermission,
    mockHasRole,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockApiPut: vi.fn(),
    mockApiDelete: vi.fn(),
    mockHasPermission: vi.fn(),
    mockHasRole: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
        hasRole: mockHasRole,
    }),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: mockApiPost,
            put: mockApiPut,
            delete: mockApiDelete,
            defaults: { baseURL: 'http://localhost/api/v1' },
        },
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    },
}))

vi.mock('@/components/ui/iconbutton', () => ({
    IconButton: ({ label, onClick }: { label: string; onClick?: () => void }) => (
        <button type="button" aria-label={label} onClick={onClick}>
            {label}
        </button>
    ),
}))

describe('Auditoria de lookups financeiros', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasRole.mockReturnValue(false)
        mockHasPermission.mockImplementation((permission: string) => [
            'financial.fund_transfer.create',
            'expenses.expense.view',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/fund-transfers') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/fund-transfers/summary') {
                return Promise.resolve({ data: { data: { month_total: '0.00', total_all: '0.00', by_technician: [] } } })
            }

            if (url === '/bank-accounts') {
                return Promise.resolve({ data: { data: [{ id: 1, name: 'Conta 1', bank_name: 'Banco 1' }] } })
            }

            if (url === '/technicians/options') {
                return Promise.resolve({ data: { data: [{ id: 7, name: 'Tecnico 1' }] } })
            }

            if (url === '/payment-methods') {
                return Promise.resolve({ data: { data: [{ id: 1, code: 'pix', name: 'PIX' }] } })
            }

            if (url === '/financial/lookups/payment-methods') {
                return Promise.resolve({ data: { data: [{ id: 1, code: 'pix', name: 'PIX', is_active: true }] } })
            }

            if (url === '/payment-methods') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/expenses') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/expense-summary') {
                return Promise.resolve({ data: { data: { pending: 0, approved: 0, reimbursed: 0, month_total: 0, total_count: 0, pending_count: 0 } } })
            }

            if (url === '/financial/expense-reimbursements') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/expense-categories') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/accounts-payable') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/accounts-payable-summary') {
                return Promise.resolve({ data: { pending: 0, overdue: 0, recorded_this_month: 0, paid_this_month: 0, total_open: 0 } })
            }

            if (url === '/account-payable-categories') {
                return Promise.resolve({ data: { data: [] } })
            }


            if (url === '/financial/lookups/suppliers') {
                return Promise.resolve({ data: { data: [{ id: 5, name: 'Fornecedor 1' }] } })
            }

            if (url === '/accounts-receivable') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/accounts-receivable-summary') {
                return Promise.resolve({ data: { pending: 0, overdue: 0, billed_this_month: 0, paid_this_month: 0, total_open: 0 } })
            }

            if (url === '/financial/lookups/customers') {
                return Promise.resolve({ data: { data: [{ id: 9, name: 'Cliente 1' }] } })
            }

            if (url === '/financial/lookups/work-orders') {
                return Promise.resolve({ data: { data: [{ id: 11, number: 'OS-11', os_number: 'OS-11', total: '100.00', customer: { name: 'Cliente 1' } }] } })
            }

            if (url === '/financial/lookups/bank-accounts') {
                return Promise.resolve({ data: { data: [{ id: 3, name: 'Conta Operacional', bank_name: 'Banco 1', is_active: true }] } })
            }

            if (url === '/reconciliation-rules') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, per_page: 25, total: 0, from: null, to: null } })
            }

            if (url === '/bank-reconciliation/statements') {
                return Promise.resolve({ data: { data: { data: [], current_page: 1, last_page: 1, total: 0 } } })
            }

            if (url === '/bank-reconciliation/summary') {
                return Promise.resolve({ data: { data: { total_entries: 0, pending_count: 0, matched_count: 0, ignored_count: 0, duplicate_count: 0, total_credits: 0, total_debits: 0 } } })
            }

            if (url === '/invoices') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/cash-flow') {
                return Promise.resolve({ data: [] })
            }

            if (url === '/dre') {
                return Promise.resolve({ data: { revenue: 0, costs: 0, expenses: 0, total_costs: 0, gross_profit: 0, net_balance: 0, period: { from: '2026-03-01', to: '2026-03-31', os_number: null } } })
            }

            if (url === '/cash-flow/dre-comparativo') {
                return Promise.resolve({ data: { current: { revenue: 0, total_costs: 0, gross_profit: 0 }, previous: { revenue: 0, total_costs: 0, gross_profit: 0 }, variation: { revenue: 0, total_costs: 0, gross_profit: 0 } } })
            }

            if (url === '/financial/supplier-advances') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/financial/supplier-contracts') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/financial/lookups/supplier-contract-payment-frequencies') {
                return Promise.resolve({ data: { data: [{ id: 1, name: 'Mensal', slug: 'monthly' }] } })
            }

            if (url === '/debt-renegotiations') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/financial/batch-payment-approval') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            if (url === '/financial/checks') {
                return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('so carrega lookups de transferencia ao abrir modal de criacao', async () => {
        const user = userEvent.setup({ delay: null })

        render(<FundTransfersPage />)

        await screen.findByRole('heading', { name: /Transfer.ncias p\/ T.cnicos/i })
        expect(mockApiGet).not.toHaveBeenCalledWith('/bank-accounts', expect.anything())
        expect(mockApiGet).not.toHaveBeenCalledWith('/technicians/options', expect.anything())

        await user.click(screen.getByRole('button', { name: /Nova Transfer.ncia/i }))

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/bank-accounts', { params: { is_active: true } })
            expect(mockApiGet).toHaveBeenCalledWith('/technicians/options')
        })
    })

    it('nao consulta /users na pagina de despesas sem permissao de iam.user.view', async () => {
        render(<ExpensesPage />)

        await screen.findByText('Despesas')

        expect(mockApiGet).not.toHaveBeenCalledWith('/users', expect.anything())
    })

    it('nao consulta categorias de contas a pagar sem permissao de visualizar categorias', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.create',
        ].includes(permission))

        render(<AccountPayableCategoriesPage />)

        await screen.findByText('Voce pode operar categorias, mas nao possui permissao para listar as categorias existentes.')
        expect(mockApiGet).not.toHaveBeenCalledWith('/account-payable-categories', expect.anything())
    })

    it('expõe nome acessível nos seletores de cor da categoria', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.view',
            'finance.payable.create',
        ].includes(permission))

        const user = userEvent.setup()
        render(<AccountPayableCategoriesPage />)

        const createButtons = await screen.findAllByRole('button', { name: /Nova Categoria/i })
        await user.click(createButtons[createButtons.length - 1] as HTMLButtonElement)

        expect(await screen.findByRole('button', { name: /Selecionar cor #3b82f6/i })).toBeInTheDocument()
    })

    it('nao consulta formas de pagamento sem permissao de visualizar metodos', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.create',
        ].includes(permission))

        render(<PaymentMethodsPage />)

        await screen.findByText('Voce pode operar formas de pagamento, mas nao possui permissao para listar os metodos configurados.')
        expect(mockApiGet).not.toHaveBeenCalledWith('/payment-methods', expect.anything())
    })

    it('permite abrir conta a pagar sem listar registros quando o perfil so pode criar', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.create',
        ].includes(permission))

        const user = userEvent.setup()

        render(<AccountsPayablePage />)

        await screen.findByText('Contas a Pagar')
        expect(mockApiGet).not.toHaveBeenCalledWith('/accounts-payable', expect.anything())

        await user.click(screen.getAllByRole('button', { name: /Nova Conta/i })[0])

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/suppliers', { params: { limit: 200 } })
        })
    })

    it('permite abrir conta a receber sem listar registros quando o perfil so pode criar', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.receivable.create',
        ].includes(permission))

        const user = userEvent.setup()

        render(<AccountsReceivablePage />)

        await screen.findByText('Contas a Receber')
        expect(mockApiGet).not.toHaveBeenCalledWith('/accounts-receivable', expect.anything())

        await user.click(screen.getAllByRole('button', { name: /Novo T.tulo/i })[0])

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/customers', { params: { limit: 100 } })
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/work-orders', { params: { limit: 50 } })
        })
    })

    it('permite abrir criacao de fatura sem listar historico quando o perfil so pode criar', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.receivable.create',
        ].includes(permission))

        const user = userEvent.setup()

        render(<InvoicesPage />)

        await screen.findByText('Faturamento / NF')
        expect(mockApiGet).not.toHaveBeenCalledWith('/invoices', expect.anything())

        await user.click(screen.getAllByRole('button', { name: /Nova Fatura/i })[0])

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/customers', { params: { limit: 100 } })
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/work-orders', { params: { limit: 50 } })
        })
    })

    it('carrega lookup bancario sem tentar listar conciliacoes quando o perfil so pode operar conciliacao', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.receivable.create',
        ].includes(permission))

        render(<BankReconciliationPage />)

        await screen.findByText('Conciliação Bancária')

        expect(mockApiGet).not.toHaveBeenCalledWith('/bank-reconciliation/summary', expect.anything())

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/bank-accounts')
        })
    })

    it('aceita perfil de contas a pagar para visualizar conciliacao e regras', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.view',
        ].includes(permission))

        render(<BankReconciliationPage />)

        await screen.findByRole('heading', { name: /Concilia.*Banc/i })

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/bank-reconciliation/summary')
            expect(mockApiGet).toHaveBeenCalledWith('/bank-reconciliation/statements', { params: { page: 1 } })
        })
    })

    it('aceita perfil de contas a pagar para listar regras de conciliacao', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.view',
        ].includes(permission))

        render(<ReconciliationRulesPage />)

        await screen.findByText('Regras de Conciliacao Automatica')

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/reconciliation-rules', { params: { search: undefined } })
        })
    })

    it('oculta criacao e exclusao de regras quando o perfil so pode visualizar conciliacao', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.receivable.view',
        ].includes(permission))

        render(<ReconciliationRulesPage />)

        await screen.findByText('Regras de Conciliacao Automatica')
        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/reconciliation-rules', { params: { search: undefined } })
        })

        expect(screen.queryByRole('button', { name: /Nova Regra/i })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /Criar Primeira Regra/i })).not.toBeInTheDocument()
    })

    it('nao consulta reembolsos sem permissao de visualizar despesas', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'expenses.expense.approve',
        ].includes(permission))

        render(<ExpenseReimbursementsPage />)

        await screen.findByText('A listagem de reembolsos exige permissao de visualizacao de despesas.')
        expect(mockApiGet).not.toHaveBeenCalledWith('/financial/expense-reimbursements', expect.anything())
    })

    it('nao consulta cheques sem permissao de visualizar contas a pagar', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.create',
        ].includes(permission))

        render(<FinancialChecksPage />)

        await screen.findByText('Voce pode operar cheques, mas nao possui permissao para listar o historico.')
        expect(mockApiGet).not.toHaveBeenCalledWith('/financial/checks', expect.anything())
    })

    it('abre detalhes de contas a receber com payload envelopado', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.receivable.view',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/accounts-receivable') {
                return Promise.resolve({
                    data: {
                        data: [{
                            id: 10,
                            description: 'Recebimento de contrato',
                            amount: '500.00',
                            amount_paid: '100.00',
                            due_date: '2026-03-20',
                            status: 'pending',
                            customer: { id: 9, name: 'Cliente 1' },
                        }],
                        current_page: 1,
                        last_page: 1,
                        total: 1,
                    },
                })
            }

            if (url === '/accounts-receivable-summary') {
                return Promise.resolve({ data: { pending: 500, overdue: 0, billed_this_month: 500, paid_this_month: 100, total_open: 400 } })
            }

            if (url === '/accounts-receivable/10') {
                return Promise.resolve({
                    data: {
                        data: {
                            id: 10,
                            description: 'Recebimento de contrato',
                            amount: '500.00',
                            amount_paid: '100.00',
                            due_date: '2026-03-20',
                            status: 'pending',
                            customer: { id: 9, name: 'Cliente 1' },
                            payments: [],
                        },
                    },
                })
            }

            if (url === '/financial/lookups/payment-methods') {
                return Promise.resolve({ data: { data: [{ id: 1, code: 'pix', name: 'PIX', is_active: true }] } })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        const user = userEvent.setup()
        render(<AccountsReceivablePage />)

        await screen.findByText('Recebimento de contrato')
        await user.click(screen.getByRole('button', { name: /Ver detalhes/i }))

        expect(await screen.findByRole('heading', { name: 'Detalhes do Título' })).toBeInTheDocument()
        expect(screen.getAllByText('Recebimento de contrato').length).toBeGreaterThan(0)
        expect(mockApiGet).toHaveBeenCalledWith('/accounts-receivable/10')
    })

    it('abre detalhes de contas a pagar com payload envelopado', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.view',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/accounts-payable') {
                return Promise.resolve({
                    data: {
                        data: [{
                            id: 20,
                            supplier_id: 5,
                            category_id: 2,
                            description: 'Fatura do fornecedor',
                            amount: '700.00',
                            amount_paid: '0.00',
                            due_date: '2026-03-25',
                            status: 'pending',
                            supplier_relation: { id: 5, name: 'Fornecedor 1' },
                            category_relation: { id: 2, name: 'Servicos' },
                        }],
                        current_page: 1,
                        last_page: 1,
                        total: 1,
                    },
                })
            }

            if (url === '/accounts-payable-summary') {
                return Promise.resolve({ data: { pending: 700, overdue: 0, recorded_this_month: 700, paid_this_month: 0, total_open: 700 } })
            }

            if (url === '/account-payable-categories') {
                return Promise.resolve({ data: { data: [{ id: 2, name: 'Servicos' }] } })
            }

            if (url === '/accounts-payable/20') {
                return Promise.resolve({
                    data: {
                        data: {
                            id: 20,
                            supplier_id: 5,
                            category_id: 2,
                            description: 'Fatura do fornecedor',
                            amount: '700.00',
                            amount_paid: '0.00',
                            due_date: '2026-03-25',
                            status: 'pending',
                            supplier_relation: { id: 5, name: 'Fornecedor 1' },
                            category_relation: { id: 2, name: 'Servicos' },
                            payments: [],
                        },
                    },
                })
            }

            if (url === '/financial/lookups/payment-methods') {
                return Promise.resolve({ data: { data: [{ id: 1, code: 'pix', name: 'PIX', is_active: true }] } })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        const user = userEvent.setup()
        render(<AccountsPayablePage />)

        await screen.findByText('Fatura do fornecedor')
        await user.click(screen.getByRole('button', { name: /Ver detalhes/i }))

        expect(await screen.findByRole('heading', { name: 'Detalhes da Conta' })).toBeInTheDocument()
        expect(screen.getAllByText('Fatura do fornecedor').length).toBeGreaterThan(0)
        expect(mockApiGet).toHaveBeenCalledWith('/accounts-payable/20')
    })

    it('nao consulta fluxo de caixa sem permissao de visualizar cash flow', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.dre.view',
        ].includes(permission))

        render(<CashFlowPage />)

        await screen.findByText('Fluxo de Caixa e DRE')
        expect(mockApiGet).not.toHaveBeenCalledWith('/cash-flow', expect.anything())
        expect(mockApiGet).toHaveBeenCalledWith('/dre', { params: {} })
    })

    it('renderiza fluxo de caixa e DRE com payload envelopado', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.cashflow.view',
            'finance.dre.view',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/cash-flow') {
                return Promise.resolve({
                    data: {
                        data: [{
                            month: '2026-03',
                            label: 'Mar/2026',
                            receivables_total: 1200,
                            receivables_paid: 900,
                            payables_total: 500,
                            payables_paid: 300,
                            expenses_total: 100,
                            balance: 600,
                            cash_balance: 500,
                        }],
                    },
                })
            }

            if (url === '/dre') {
                return Promise.resolve({
                    data: {
                        data: {
                            revenue: 900,
                            costs: 300,
                            expenses: 100,
                            total_costs: 400,
                            gross_profit: 500,
                            net_balance: 500,
                            period: { from: '2026-03-01', to: '2026-03-31', os_number: null },
                        },
                    },
                })
            }

            if (url === '/cash-flow/dre-comparativo') {
                return Promise.resolve({
                    data: {
                        data: {
                            current: { revenue: 900, total_costs: 400, gross_profit: 500 },
                            previous: { revenue: 800, total_costs: 350, gross_profit: 450 },
                            variation: { revenue: 12.5, total_costs: 14.3, gross_profit: 11.1 },
                        },
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        render(<CashFlowPage />)

        expect(await screen.findByText('Mar/2026')).toBeInTheDocument()
        expect(screen.getAllByText((_, element) => element?.textContent?.includes('900,00') ?? false).length).toBeGreaterThan(0)
    })

    it('exporta fluxo de caixa em CSV com BOM, cabecalho, colunas e escaping deterministicos', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.cashflow.view',
            'finance.dre.view',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/cash-flow') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                month: '2026-03',
                                label: 'Mar; "Especial"/2026',
                                receivables_total: 1200.5,
                                receivables_paid: 900,
                                payables_total: 500,
                                payables_paid: 300,
                                expenses_total: 100,
                                balance: 600,
                                cash_balance: 500,
                            },
                            {
                                month: '2026-04',
                                label: 'Abr/2026',
                                receivables_total: 2000,
                                receivables_paid: 1000,
                                payables_total: 700,
                                payables_paid: 400,
                                expenses_total: 50,
                                balance: 850,
                            },
                        ],
                    },
                })
            }

            if (url === '/dre') {
                return Promise.resolve({
                    data: {
                        data: {
                            revenue: 900,
                            costs: 300,
                            expenses: 100,
                            total_costs: 400,
                            gross_profit: 500,
                            net_balance: 500,
                            period: { from: '2026-03-01', to: '2026-03-31', os_number: null },
                        },
                    },
                })
            }

            if (url === '/cash-flow/dre-comparativo') {
                return Promise.resolve({
                    data: {
                        data: {
                            current: { revenue: 900, total_costs: 400, gross_profit: 500 },
                            previous: { revenue: 800, total_costs: 350, gross_profit: 450 },
                            variation: { revenue: 12.5, total_costs: 14.3, gross_profit: 11.1 },
                        },
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        const user = userEvent.setup()
        const originalCreateObjectURL = URL.createObjectURL
        const originalRevokeObjectURL = URL.revokeObjectURL
        const OriginalBlob = globalThis.Blob
        const blobParts: BlobPart[][] = []
        class CapturingBlob extends OriginalBlob {
            constructor(parts?: BlobPart[], options?: BlobPropertyBag) {
                blobParts.push(parts ?? [])
                super(parts, options)
            }
        }
        let clickedLinkSnapshot: Pick<HTMLAnchorElement, 'download' | 'href'> | null = null
        const clickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
            clickedLinkSnapshot = {
                download: this.download,
                href: this.href,
            }
        })
        let exportedBlob: Blob | null = null
        const createObjectURL = vi.fn((blob: Blob) => {
            exportedBlob = blob
            return 'blob:cash-flow-csv'
        })
        const revokeObjectURL = vi.fn()

        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: createObjectURL,
        })
        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: revokeObjectURL,
        })
        vi.stubGlobal('Blob', CapturingBlob)

        try {
            render(<CashFlowPage />)

            await screen.findByText('Mar; "Especial"/2026')
            await user.click(screen.getByTestId('cash-flow-export-csv'))

            expect(createObjectURL).toHaveBeenCalledTimes(1)
            expect(exportedBlob).toBeInstanceOf(Blob)
            if (!exportedBlob) {
                throw new Error('CSV export did not create a Blob')
            }

            expect(exportedBlob).toBeInstanceOf(OriginalBlob)
            expect(blobParts[0]?.[0]).toBe([
                '\uFEFF"Mes";"A receber";"Recebido";"A pagar";"Pago";"Despesas";"Saldo caixa"',
                '"Mar; ""Especial""/2026";"1200.5";"900";"500";"300";"100";"500"',
                '"Abr/2026";"2000";"1000";"700";"400";"50";"850"',
            ].join('\n'))

            expect(clickedLinkSnapshot?.download).toMatch(/^fluxo_caixa_\d{4}-\d{2}-\d{2}\.csv$/)
            expect(clickedLinkSnapshot?.href).toBe('blob:cash-flow-csv')
            expect(clickSpy).toHaveBeenCalledTimes(1)
            expect(revokeObjectURL).toHaveBeenCalledWith('blob:cash-flow-csv')
        } finally {
            clickSpy.mockRestore()
            vi.stubGlobal('Blob', OriginalBlob)
            if (originalCreateObjectURL) {
                Object.defineProperty(URL, 'createObjectURL', {
                    configurable: true,
                    value: originalCreateObjectURL,
                })
            } else {
                Reflect.deleteProperty(URL, 'createObjectURL')
            }
            if (originalRevokeObjectURL) {
                Object.defineProperty(URL, 'revokeObjectURL', {
                    configurable: true,
                    value: originalRevokeObjectURL,
                })
            } else {
                Reflect.deleteProperty(URL, 'revokeObjectURL')
            }
        }
    })

    it('permite criar adiantamento sem listar historico quando o perfil so pode criar', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.create',
        ].includes(permission))

        const user = userEvent.setup()

        render(<SupplierAdvancesPage />)

        await screen.findByText('Adiantamentos a Fornecedores')
        expect(mockApiGet).not.toHaveBeenCalledWith('/financial/supplier-advances', expect.anything())

        await user.click(screen.getByRole('button', { name: /Novo Adiantamento/i }))

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/suppliers', { params: { limit: 100 } })
        })
    })

    it('permite criar contrato sem listar historico quando o perfil so pode operar payable', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.create',
        ].includes(permission))

        const user = userEvent.setup()

        render(<SupplierContractsPage />)

        await screen.findByText('Contratos de Fornecedor')
        expect(mockApiGet).not.toHaveBeenCalledWith('/financial/supplier-contracts', expect.anything())

        await user.click(screen.getByRole('button', { name: /Novo Contrato/i }))

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/suppliers', { params: { limit: 100 } })
            expect(mockApiGet).toHaveBeenCalledWith('/financial/lookups/supplier-contract-payment-frequencies')
        })
    })

    it('usa endpoints modernos de renegociacao e nao abre formulario sem view', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.receivable.create',
        ].includes(permission))

        render(<DebtRenegotiationPage />)

        await screen.findByText('Renegociacao de Dividas')
        expect(mockApiGet).not.toHaveBeenCalledWith('/debt-renegotiations', expect.anything())
        expect(screen.getByRole('button', { name: /Nova Renegociacao/i })).toBeDisabled()
    })

    it('nao consulta aprovacao em lote sem permissao de visualizar contas a pagar', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.settle',
        ].includes(permission))

        render(<BatchPaymentApprovalPage />)

        await screen.findByText('A listagem e a seleção em lote exigem permissão de visualização de contas a pagar.')
        expect(mockApiGet).not.toHaveBeenCalledWith('/financial/batch-payment-approval', expect.anything())
    })
    it('abre detalhes de fatura com payload envelopado', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.receivable.view',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/invoices') {
                return Promise.resolve({
                    data: {
                        data: [{
                            id: 31,
                            invoice_number: 'NF-00031',
                            nf_number: '123',
                            customer: { id: 9, name: 'Cliente 1' },
                            work_order: { id: 11, number: 'OS-11', os_number: 'OS-11', business_number: 'OS-11' },
                            status: 'draft',
                            total: '150.00',
                            issued_at: null,
                            due_date: '2026-03-31',
                            observations: 'Observacao teste',
                            items: [],
                            created_at: '2026-03-12T10:00:00Z',
                        }],
                        current_page: 1,
                        last_page: 1,
                        total: 1,
                    },
                })
            }

            if (url === '/invoices/31') {
                return Promise.resolve({
                    data: {
                        data: {
                            id: 31,
                            invoice_number: 'NF-00031',
                            nf_number: '123',
                            customer: { id: 9, name: 'Cliente 1' },
                            work_order: { id: 11, number: 'OS-11', os_number: 'OS-11', business_number: 'OS-11' },
                            status: 'draft',
                            total: '150.00',
                            issued_at: null,
                            due_date: '2026-03-31',
                            observations: 'Observacao teste',
                            items: [],
                            created_at: '2026-03-12T10:00:00Z',
                        },
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        const user = userEvent.setup()
        render(<InvoicesPage />)

        await screen.findByText('NF-00031')
        await user.click(screen.getByRole('button', { name: /Ver detalhes/i }))

        expect(await screen.findByRole('heading', { name: /Fatura NF-00031/i })).toBeInTheDocument()
        expect(screen.getByText('Observacao teste')).toBeInTheDocument()
    })

    it('renderiza resumo de pagamentos com payload envelopado', async () => {
        mockHasPermission.mockImplementation((permission: string) => [
            'finance.payable.view',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/payments') {
                return Promise.resolve({
                    data: {
                        data: [{
                            id: 1,
                            payable_type: 'App\\\\Models\\\\AccountPayable',
                            payable_id: 20,
                            amount: '120.00',
                            payment_method: 'pix',
                            payment_date: '2026-03-12',
                            notes: 'Pagamento teste',
                            created_at: '2026-03-12T10:00:00Z',
                        }],
                        current_page: 1,
                        last_page: 1,
                        total: 1,
                    },
                })
            }

            if (url === '/payments-summary') {
                return Promise.resolve({
                    data: {
                        data: {
                            total_received: 0,
                            total_paid: 120,
                            net: -120,
                            count: 1,
                            total: 120,
                            by_method: [{ payment_method: 'pix', total: 120, count: 1 }],
                        },
                    },
                })
            }

            if (url === '/payment-methods') {
                return Promise.resolve({ data: { data: [{ id: 1, code: 'pix', name: 'PIX' }] } })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        render(<PaymentsPage />)

        await screen.findByRole('heading', { name: 'Pagamentos' })
        expect((await screen.findAllByText(/R\$\s*120,00/)).length).toBeGreaterThan(0)
        expect(screen.getByText('Pagamento')).toBeInTheDocument()
    })
})
