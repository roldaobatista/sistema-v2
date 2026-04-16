import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechCashPage from '@/pages/tech/TechCashPage'
import TechExpensesOverviewPage from '@/pages/tech/TechExpensesOverviewPage'
import TechExpensePage from '@/pages/tech/TechExpensePage'

const {
    mockNavigate,
    mockApiGet,
    mockApiPost,
    mockApiPut,
    mockApiDelete,
    toastError,
    toastSuccess,
    toastWarning,
    confirmSpy,
    hasPermissionMock,
    hasRoleMock,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockApiPut: vi.fn(),
    mockApiDelete: vi.fn(),
    toastError: vi.fn(),
    toastSuccess: vi.fn(),
    toastWarning: vi.fn(),
    confirmSpy: vi.fn(),
    hasPermissionMock: vi.fn(),
    hasRoleMock: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => ({ id: '15' }),
    }
})

vi.mock('@/hooks/usePullToRefresh', () => ({
    usePullToRefresh: () => ({
        containerRef: { current: null },
        isRefreshing: false,
        pullDistance: 0,
    }),
}))

vi.mock('@/hooks/useExpenseCategories', () => ({
    useExpenseCategories: () => ({
        categories: [
            { id: 1, name: 'Combustivel', color: '#f59e0b' },
        ],
        isLoading: false,
        error: null,
    }),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: hasPermissionMock,
        hasRole: hasRoleMock,
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
        },
    }
})

vi.mock('sonner', () => ({
    toast: {
        error: toastError,
        success: toastSuccess,
        warning: toastWarning,
    },
}))

describe('Auditoria pages caixa e despesas tecnico', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        vi.stubGlobal('confirm', confirmSpy)
        confirmSpy.mockReturnValue(true)
        hasRoleMock.mockReturnValue(false)
        hasPermissionMock.mockImplementation((permission: string) => [
            'technicians.cashbox.view',
            'technicians.cashbox.expense.create',
            'technicians.cashbox.expense.update',
            'technicians.cashbox.expense.delete',
            'technicians.cashbox.request_funds',
        ].includes(permission))

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/technician-cash/my-fund') {
                return Promise.resolve({ data: { data: { id: 1, balance: '120.00', card_balance: '30.00' } } })
            }

            if (url === '/technician-cash/my-transactions') {
                return Promise.resolve({ data: { data: [{ id: 1, type: 'credit', amount: '50.00', description: 'Reforco', transaction_date: '2026-03-12' }] } })
            }

            if (url === '/technician-cash/my-requests') {
                return Promise.resolve({ data: { data: [{ id: 9, amount: '75.00', reason: 'Troco', status: 'pending', created_at: '2026-03-12T10:00:00Z', approved_at: null }] } })
            }

            if (url === '/technician-cash/my-expenses') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 7,
                                description: 'Combustivel',
                                amount: '89.90',
                                expense_date: '2026-03-12',
                                status: 'rejected',
                                rejection_reason: 'Comprovante ilegivel',
                                receipt_path: '/storage/tenants/1/receipts/combustivel.jpg',
                                expense_category_id: 1,
                                category: { id: 1, name: 'Combustivel', color: '#f59e0b' },
                                work_order: { id: 15, number: 'OS-000015', os_number: 'OS-000015' },
                            },
                        ],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        mockApiPost.mockResolvedValue({ data: { data: { id: 1 } } })
        mockApiPut.mockResolvedValue({ data: { data: { id: 7, status: 'pending' } } })
        mockApiDelete.mockResolvedValue({ data: null })
    })

    it('desembrulha corretamente o envelope do caixa tecnico mobile', async () => {
        render(<TechCashPage />)

        expect(await screen.findByText('Meu Caixa')).toBeInTheDocument()
        expect(await screen.findByText('R$ 120,00')).toBeInTheDocument()
        expect(screen.getByText('Cartao corporativo: R$ 30,00')).toBeInTheDocument()
        expect(screen.getByText('Reforco')).toBeInTheDocument()
    })

    it('carrega despesas do endpoint self-service e mostra motivo da rejeicao', async () => {
        render(<TechExpensesOverviewPage />)

        expect(await screen.findByText('Minhas Despesas')).toBeInTheDocument()
        expect(await screen.findAllByText('Combustivel')).toHaveLength(3)
        expect(screen.getByText('OS: OS-000015')).toBeInTheDocument()
        expect(screen.getByText(/Motivo: Comprovante ilegivel/)).toBeInTheDocument()

        expect(mockApiGet).toHaveBeenCalledWith('/technician-cash/my-expenses', expect.objectContaining({
            params: expect.objectContaining({ per_page: '200' }),
        }))
    })

    it('remove despesa da os usando endpoint self-service do tecnico', async () => {
        const user = userEvent.setup()
        render(<TechExpensePage />)

        const removeButton = await screen.findByLabelText('Remover despesa')
        await user.click(removeButton)

        await waitFor(() => {
            expect(mockApiDelete).toHaveBeenCalledWith('/technician-cash/my-expenses/7')
        })
    })

    it('atualiza despesa da os com verbo put no endpoint self-service do tecnico', async () => {
        const user = userEvent.setup()
        render(<TechExpensePage />)

        const editButton = await screen.findByLabelText('Editar despesa')
        await user.click(editButton)

        const submitButton = await screen.findByRole('button', { name: 'Atualizar' })
        await user.click(submitButton)

        await waitFor(() => {
            expect(mockApiPut).toHaveBeenCalledWith(
                '/technician-cash/my-expenses/7',
                expect.any(FormData),
                expect.objectContaining({
                    headers: { 'Content-Type': 'multipart/form-data' },
                }),
            )
        })

        const submittedFormData = mockApiPut.mock.calls[0]?.[1] as FormData
        expect(submittedFormData.get('affects_net_value')).toBeNull()
        expect(submittedFormData.get('affects_technician_cash')).toBeNull()
    })

    it('mantem o saldo visivel quando apenas a lista de movimentacoes falha', async () => {
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/technician-cash/my-fund') {
                return Promise.resolve({ data: { data: { id: 1, balance: '120.00', card_balance: '0.00' } } })
            }

            if (url === '/technician-cash/my-transactions') {
                return Promise.reject({ response: { status: 500, data: { message: 'Falha nas movimentacoes' } } })
            }

            if (url === '/technician-cash/my-requests') {
                return Promise.resolve({ data: { data: [] } })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        render(<TechCashPage />)

        expect(await screen.findByText('Meu Caixa')).toBeInTheDocument()
        expect(screen.getByText('R$ 120,00')).toBeInTheDocument()
        await waitFor(() => {
            expect(toastError).toHaveBeenCalled()
        })
        expect(screen.getByText('Falha ao carregar movimentacoes.')).toBeInTheDocument()
    })

    it('resolve imagem de comprovante com caminho legado /storage', async () => {
        render(<TechExpensesOverviewPage />)

        const image = await screen.findByAltText('Comprovante')
        expect((image as HTMLImageElement).src).toMatch(/\/storage\/tenants\/1\/receipts\/combustivel\.jpg$/)
    })

    it('nao libera criacao de despesa para quem so pode visualizar o caixa', async () => {
        hasPermissionMock.mockImplementation((permission: string) => permission === 'technicians.cashbox.view')

        render(<TechExpensesOverviewPage />)

        expect(await screen.findByText('Minhas Despesas')).toBeInTheDocument()
        expect(screen.queryByLabelText('Nova despesa avulsa')).not.toBeInTheDocument()
    })

    it('nao libera solicitacao de fundos para quem so pode visualizar o caixa', async () => {
        hasPermissionMock.mockImplementation((permission: string) => permission === 'technicians.cashbox.view')

        render(<TechCashPage />)

        const button = await screen.findByRole('button', { name: /Solicitar Fundos/i })
        expect(button).toBeDisabled()
    })
})
