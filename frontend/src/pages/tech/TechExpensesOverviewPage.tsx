import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Receipt, Loader2, Plus, AlertTriangle } from 'lucide-react'
import { cn, formatCurrency, getApiErrorMessage } from '@/lib/utils'
import api, { buildStorageUrl, unwrapData } from '@/lib/api'
import { toast } from 'sonner'
import { useExpenseCategories } from '@/hooks/useExpenseCategories'
import { usePullToRefresh } from '@/hooks/usePullToRefresh'
import ExpenseForm from '@/components/expenses/ExpenseForm'
import type { ExpenseFormSubmitData } from '@/components/expenses/ExpenseForm'
import type { ExpenseItem, ExpenseStatus } from '@/types/expense'
import { EXPENSE_STATUS_MAP } from '@/types/expense'
import { useAuthStore } from '@/stores/auth-store'

export default function TechExpensesOverviewPage() {
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const { categories } = useExpenseCategories()
    const { hasPermission, hasRole } = useAuthStore()
    const [period, setPeriod] = useState<'week' | 'month' | 'all' | 'custom'>('month')
    const [dateFrom, setDateFrom] = useState('')
    const [dateTo, setDateTo] = useState('')
    const [categoryFilter, setCategoryFilter] = useState('')
    const [showCreateForm, setShowCreateForm] = useState(false)

    const canCreateExpense = hasRole('super_admin') || hasPermission('technicians.cashbox.expense.create') || hasPermission('technicians.cashbox.manage')

    const queryParams = useMemo(() => {
        const params: Record<string, string> = { per_page: '200' }

        if (period === 'week') {
            const date = new Date()
            date.setDate(date.getDate() - 7)
            params.date_from = date.toISOString().slice(0, 10)
        } else if (period === 'month') {
            const date = new Date()
            date.setDate(1)
            params.date_from = date.toISOString().slice(0, 10)
        } else if (period === 'custom') {
            if (dateFrom) params.date_from = dateFrom
            if (dateTo) params.date_to = dateTo
        }

        return params
    }, [period, dateFrom, dateTo])

    const expensesQuery = useQuery({
        queryKey: ['tech-expenses', queryParams],
        queryFn: async () => unwrapData<ExpenseItem[]>(await api.get('/technician-cash/my-expenses', { params: queryParams })),
    })

    const createExpenseMutation = useMutation({
        mutationFn: async (data: ExpenseFormSubmitData) => {
            const formData = new FormData()
            formData.append('expense_category_id', String(data.expense_category_id))
            formData.append('description', data.description || data.categoryName)
            formData.append('amount', data.amount)
            formData.append('expense_date', data.expense_date)
            formData.append('payment_method', data.payment_method)
            if (data.notes) formData.append('notes', data.notes)
            if (data.photo) formData.append('receipt', data.photo)

            return api.post('/technician-cash/my-expenses', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
        },
        onSuccess: async (response) => {
            const created = unwrapData<ExpenseItem>(response)
            setShowCreateForm(false)
            toast.success('Despesa registrada com sucesso.')
            if (created._budget_warning) {
                toast.warning(created._budget_warning)
            }
            if (created._warning) {
                toast.warning(created._warning)
            }
            await queryClient.invalidateQueries({ queryKey: ['tech-expenses'] })
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao salvar despesa'))
        },
    })

    const refreshExpenses = async () => {
        await queryClient.invalidateQueries({ queryKey: ['tech-expenses'] })
    }

    const { containerRef, isRefreshing, pullDistance } = usePullToRefresh({
        onRefresh: refreshExpenses,
    })

    const expenses = expensesQuery.data ?? []
    const filteredExpenses = categoryFilter
        ? expenses.filter((expense) => (expense.category?.name ?? 'Outros') === categoryFilter)
        : expenses

    const totalAmount = filteredExpenses.reduce((sum, expense) => sum + (Number(expense.amount) || 0), 0)
    const pendingAmount = filteredExpenses
        .filter((expense) => expense.status === 'pending' || expense.status === 'reviewed')
        .reduce((sum, expense) => sum + (Number(expense.amount) || 0), 0)
    const approvedAmount = filteredExpenses
        .filter((expense) => expense.status === 'approved')
        .reduce((sum, expense) => sum + (Number(expense.amount) || 0), 0)
    const reimbursedAmount = filteredExpenses
        .filter((expense) => expense.status === 'reimbursed')
        .reduce((sum, expense) => sum + (Number(expense.amount) || 0), 0)

    const rejectedCount = expenses.filter((expense) => expense.status === 'rejected').length
    const uniqueCategories = [...new Set(expenses.map((expense) => expense.category?.name ?? 'Outros'))].sort()

    return (
        <div className="relative flex h-full flex-col">
            <div className="border-b border-border bg-card px-4 pb-4 pt-3">
                <button onClick={() => navigate('/tech')} className="mb-2 flex items-center gap-1 text-sm text-brand-600">
                    <ArrowLeft className="h-4 w-4" /> Voltar
                </button>
                <h1 className="text-lg font-bold text-foreground">Minhas Despesas</h1>
            </div>

            {(pullDistance > 0 || isRefreshing) && (
                <div className="flex items-center justify-center py-2">
                    <Loader2 className={cn('h-5 w-5 text-brand-500', isRefreshing && 'animate-spin')} />
                    <span className="ml-2 text-xs text-surface-500">
                        {isRefreshing ? 'Atualizando...' : 'Solte para atualizar'}
                    </span>
                </div>
            )}

            <div ref={containerRef} className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                {rejectedCount > 0 && (
                    <div className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 dark:border-red-800 dark:bg-red-900/20">
                        <AlertTriangle className="h-4 w-4 flex-shrink-0 text-red-500" />
                        <p className="text-xs font-medium text-red-700 dark:text-red-400">
                            {rejectedCount} despesa(s) rejeitada(s). Edite na OS e reenvie para aprovacao.
                        </p>
                    </div>
                )}

                <div className="grid grid-cols-4 gap-2">
                    <div className="rounded-xl bg-card p-2 text-center flex flex-col justify-center">
                        <p className="text-[9px] font-medium uppercase text-surface-500">Total</p>
                        <p className="mt-0.5 text-xs font-bold text-foreground line-clamp-1">{formatCurrency(totalAmount)}</p>
                    </div>
                    <div className="rounded-xl bg-card p-2 text-center flex flex-col justify-center">
                        <p className="text-[9px] font-medium uppercase text-amber-600">Pendente</p>
                        <p className="mt-0.5 text-xs font-bold text-amber-700 line-clamp-1">{formatCurrency(pendingAmount)}</p>
                    </div>
                    <div className="rounded-xl bg-card p-2 text-center flex flex-col justify-center">
                        <p className="text-[9px] font-medium uppercase text-emerald-600">Aprovado</p>
                        <p className="mt-0.5 text-xs font-bold text-emerald-700 line-clamp-1">{formatCurrency(approvedAmount)}</p>
                    </div>
                    <div className="rounded-xl bg-card p-2 text-center flex flex-col justify-center">
                        <p className="text-[9px] font-medium uppercase text-blue-600">Reembolso</p>
                        <p className="mt-0.5 text-xs font-bold text-blue-700 line-clamp-1">{formatCurrency(reimbursedAmount)}</p>
                    </div>
                </div>

                <div className="flex flex-col gap-2">
                    <div className="flex gap-2">
                        {([['week', 'Semana'], ['month', 'Mes'], ['all', 'Tudo'], ['custom', 'Data']] as const).map(([key, label]) => (
                            <button
                                key={key}
                                onClick={() => setPeriod(key)}
                                className={cn(
                                    'flex-1 rounded-lg py-2 text-xs font-medium transition-colors',
                                    period === key ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                                )}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    {period === 'custom' && (
                        <div className="flex items-center gap-2 rounded-lg bg-surface-50 p-2">
                            <input
                                type="date"
                                aria-label="Data inicio"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full rounded-md border border-default bg-surface-0 px-2 py-1.5 text-xs focus:border-brand-500 focus:outline-none"
                            />
                            <span className="text-xs text-surface-400">até</span>
                            <input
                                type="date"
                                aria-label="Data fim"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full rounded-md border border-default bg-surface-0 px-2 py-1.5 text-xs focus:border-brand-500 focus:outline-none"
                            />
                        </div>
                    )}
                </div>

                {uniqueCategories.length > 0 && (
                    <div className="no-scrollbar flex gap-2 overflow-x-auto pb-1">
                        <button
                            onClick={() => setCategoryFilter('')}
                            className={cn(
                                'whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium',
                                !categoryFilter ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                            )}
                        >
                            Todas
                        </button>
                        {uniqueCategories.map((category) => (
                            <button
                                key={category}
                                onClick={() => setCategoryFilter(category)}
                                className={cn(
                                    'whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium',
                                    categoryFilter === category ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                                )}
                            >
                                {category}
                            </button>
                        ))}
                    </div>
                )}

                {expensesQuery.isLoading ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20">
                        <Loader2 className="h-8 w-8 animate-spin text-brand-500" />
                        <p className="text-sm text-surface-500">Carregando despesas...</p>
                    </div>
                ) : expensesQuery.isError ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20 text-center">
                        <Receipt className="h-12 w-12 text-surface-300" />
                        <p className="text-sm text-surface-500">Nao foi possivel carregar suas despesas.</p>
                        <button
                            onClick={() => void refreshExpenses()}
                            className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white"
                        >
                            Tentar novamente
                        </button>
                    </div>
                ) : filteredExpenses.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20">
                        <Receipt className="h-12 w-12 text-surface-300" />
                        <p className="text-sm text-surface-500">Nenhuma despesa encontrada.</p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {filteredExpenses.map((expense) => {
                            const categoryName = expense.category?.name ?? 'Outros'
                            const categoryColor = expense.category?.color ?? '#9ca3af'
                            const statusInfo = EXPENSE_STATUS_MAP[expense.status as ExpenseStatus] ?? EXPENSE_STATUS_MAP.pending
                            const workOrderLabel = expense.work_order?.os_number ?? expense.work_order?.number
                            const receiptUrl = buildStorageUrl(expense.receipt_path)

                            return (
                                <button
                                    key={expense.id}
                                    type="button"
                                    onClick={() => {
                                        if (expense.work_order_id) {
                                            navigate(`/tech/os/${expense.work_order_id}/expenses`)
                                        }
                                    }}
                                    className={cn(
                                        'w-full rounded-xl bg-card p-3 text-left transition-transform',
                                        expense.work_order_id && 'active:scale-[0.98] cursor-pointer',
                                    )}
                                >
                                    <div className="flex items-center gap-3">
                                        {receiptUrl ? (
                                            <img
                                                src={receiptUrl}
                                                alt="Comprovante"
                                                className="h-9 w-9 flex-shrink-0 rounded-lg object-cover"
                                            />
                                        ) : (
                                            <div
                                                className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg"
                                                style={{ backgroundColor: `${categoryColor}20`, color: categoryColor }}
                                            >
                                                <Receipt className="h-4 w-4" />
                                            </div>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm font-medium text-foreground">{expense.description}</p>
                                                <span className={cn('rounded-full px-1.5 py-0.5 text-[10px] font-medium', statusInfo.cls)}>
                                                    {statusInfo.label}
                                                </span>
                                            </div>
                                            <p className="truncate text-xs text-surface-500">{categoryName}</p>
                                            <div className="mt-0.5 flex items-center gap-2 text-[10px] text-surface-400">
                                                {workOrderLabel && <span>OS: {workOrderLabel}</span>}
                                                <span>{new Date(`${expense.expense_date}T00:00:00`).toLocaleDateString('pt-BR')}</span>
                                            </div>
                                            {expense.status === 'rejected' && expense.rejection_reason && (
                                                <p className="mt-0.5 truncate text-[10px] text-red-500">
                                                    Motivo: {expense.rejection_reason}
                                                </p>
                                            )}
                                        </div>
                                        <p className="flex-shrink-0 text-sm font-bold text-foreground">
                                            {formatCurrency(Number(expense.amount))}
                                        </p>
                                    </div>
                                </button>
                            )
                        })}
                    </div>
                )}

                <div className="h-16" />
            </div>

            {!showCreateForm && canCreateExpense && (
                <button
                    onClick={() => setShowCreateForm(true)}
                    aria-label="Nova despesa avulsa"
                    className="absolute bottom-6 right-6 z-10 flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg transition-transform active:scale-95"
                >
                    <Plus className="h-6 w-6" />
                </button>
            )}

            {showCreateForm && canCreateExpense && (
                <ExpenseForm
                    categories={categories}
                    onSubmit={(data) => createExpenseMutation.mutateAsync(data).then(() => undefined)}
                    variant="sheet"
                    onClose={() => setShowCreateForm(false)}
                    showDateField
                />
            )}
        </div>
    )
}
