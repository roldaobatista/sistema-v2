import { useCallback, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Plus, Receipt, Loader2, Pencil, Trash2, WifiOff } from 'lucide-react'
import { cn, formatCurrency, getApiErrorMessage } from '@/lib/utils'
import { toast } from 'sonner'
import api, { buildStorageUrl, unwrapData } from '@/lib/api'
import { useExpenseCategories } from '@/hooks/useExpenseCategories'
import { useOfflineMutation } from '@/hooks/useOfflineMutation'
import { useSyncStatus } from '@/hooks/useSyncStatus'
import ExpenseForm from '@/components/expenses/ExpenseForm'
import type { ExpenseFormSubmitData } from '@/components/expenses/ExpenseForm'
import type { ExpenseItem, ExpenseStatus } from '@/types/expense'
import { EXPENSE_STATUS_MAP } from '@/types/expense'
import { useAuthStore } from '@/stores/auth-store'

interface ExpenseInitialData {
    categoryId: number | null
    description: string
    amount: string
    date: string
    photoPreview: string | null
    paymentMethod: 'cash' | 'corporate_card'
}

export default function TechExpensePage() {
    const { id: workOrderId } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const { categories } = useExpenseCategories()
    const { hasPermission, hasRole } = useAuthStore()
    const [showForm, setShowForm] = useState(false)
    const [editingId, setEditingId] = useState<number | null>(null)
    const [initialData, setInitialData] = useState<ExpenseInitialData | undefined>()

    const { isOnline } = useSyncStatus()

    const canCreateExpense = hasRole('super_admin') || hasPermission('technicians.cashbox.expense.create') || hasPermission('technicians.cashbox.manage')
    const canUpdateExpense = hasRole('super_admin') || hasPermission('technicians.cashbox.expense.update') || hasPermission('technicians.cashbox.manage')
    const canDeleteExpense = hasRole('super_admin') || hasPermission('technicians.cashbox.expense.delete') || hasPermission('technicians.cashbox.manage')

    const queryParams = useMemo(() => ({
        work_order_id: workOrderId ?? '',
        per_page: '100',
    }), [workOrderId])

    const expensesQuery = useQuery({
        queryKey: ['tech-expenses', 'work-order', queryParams],
        queryFn: async () => unwrapData<ExpenseItem[]>(await api.get('/technician-cash/my-expenses', { params: queryParams })),
        enabled: !!workOrderId,
    })

    const saveExpenseMutation = useMutation({
        mutationFn: async (data: ExpenseFormSubmitData) => {
            if (!workOrderId) {
                throw new Error('OS nao informada')
            }

            const formData = new FormData()
            formData.append('work_order_id', workOrderId)
            formData.append('expense_category_id', String(data.expense_category_id))
            formData.append('description', data.description || data.categoryName)
            formData.append('amount', data.amount)
            formData.append('expense_date', data.expense_date)
            formData.append('payment_method', data.payment_method)
            if (data.notes) formData.append('notes', data.notes)
            if (data.photo) formData.append('receipt', data.photo)

            if (editingId) {
                return api.put(`/technician-cash/my-expenses/${editingId}`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                })
            }

            return api.post('/technician-cash/my-expenses', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
        },
        onSuccess: async (response) => {
            const savedExpense = unwrapData<ExpenseItem>(response)
            toast.success(editingId ? 'Despesa atualizada com sucesso.' : 'Despesa registrada com sucesso.')
            if (savedExpense._budget_warning) {
                toast.warning(savedExpense._budget_warning)
            }
            if (savedExpense._warning) {
                toast.warning(savedExpense._warning)
            }
            resetForm()
            await queryClient.invalidateQueries({ queryKey: ['tech-expenses'] })
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao salvar despesa'))
        },
    })

    const deleteExpenseMutation = useMutation({
        mutationFn: async (expenseId: number) => {
            await api.delete(`/technician-cash/my-expenses/${expenseId}`)
        },
        onSuccess: async () => {
            toast.success('Despesa removida.')
            await queryClient.invalidateQueries({ queryKey: ['tech-expenses'] })
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Nao foi possivel remover a despesa'))
        },
    })

    const offlineCreateExpense = useOfflineMutation<unknown, Record<string, unknown>>({
        url: '/tech/sync/batch',
        method: 'POST',
        invalidateKeys: [['tech-expenses']],
        onSuccess: (_data, wasOffline) => {
            if (!wasOffline) {
                toast.success('Despesa registrada com sucesso.')
            }
            resetForm()
        },
        onError: (error) => {
            toast.error(getApiErrorMessage(error, 'Erro ao salvar despesa offline'))
        },
        offlineToast: 'Despesa salva offline. Sera sincronizada quando houver conexao.',
        successToast: 'Despesa registrada com sucesso.',
    })

    const handleSaveExpense = useCallback(async (data: ExpenseFormSubmitData) => {
        // If editing or online, use the regular mutation (supports FormData/file uploads)
        if (editingId || isOnline) {
            await saveExpenseMutation.mutateAsync(data)
            return
        }

        // Offline create: serialize plain data for the sync queue
        const expensePayload: Record<string, unknown> = {
            work_order_id: workOrderId,
            expense_category_id: data.expense_category_id,
            description: data.description || data.categoryName,
            amount: data.amount,
            expense_date: data.expense_date,
            payment_method: data.payment_method,
        }
        if (data.notes) expensePayload.notes = data.notes
        // Note: photo/receipt upload is not supported offline — will be synced without receipt
        if (data.photo) {
            toast.warning('Comprovante nao pode ser enviado offline. Anexe apos sincronizar.')
        }

        await offlineCreateExpense.mutate({ mutations: [{ type: 'expense', data: expensePayload }] })
    }, [editingId, isOnline, saveExpenseMutation, workOrderId, offlineCreateExpense])

    const resetForm = useCallback(() => {
        setEditingId(null)
        setInitialData(undefined)
        setShowForm(false)
    }, [])

    const handleEdit = useCallback((expense: ExpenseItem) => {
        if (!canUpdateExpense) {
            toast.error('Voce nao tem permissao para editar despesas.')
            return
        }

        if (!['pending', 'rejected'].includes(expense.status)) {
            toast.error('Somente despesas pendentes ou rejeitadas podem ser editadas.')
            return
        }

        setEditingId(expense.id)
        setInitialData({
            categoryId: expense.expense_category_id ?? expense.category?.id ?? null,
            description: expense.description ?? '',
            amount: String(expense.amount ?? ''),
            date: expense.expense_date?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
            photoPreview: buildStorageUrl(expense.receipt_path) ?? expense.receipt_path ?? null,
            paymentMethod: (expense.payment_method as 'cash' | 'corporate_card') ?? 'corporate_card',
        })
        setShowForm(true)
    }, [canUpdateExpense])

    const handleRemove = useCallback(async (expenseId: number) => {
        if (!canDeleteExpense) {
            toast.error('Voce nao tem permissao para remover despesas.')
            return
        }

        if (!window.confirm('Deseja remover esta despesa?')) {
            return
        }

        await deleteExpenseMutation.mutateAsync(expenseId)
    }, [canDeleteExpense, deleteExpenseMutation])

    const expenses = expensesQuery.data ?? []
    const total = expenses.reduce((sum, expense) => sum + (Number(expense.amount) || 0), 0)

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-border bg-card px-4 pb-4 pt-3">
                <button onClick={() => navigate(`/tech/os/${workOrderId}`)} className="mb-2 flex items-center gap-1 text-sm text-brand-600">
                    <ArrowLeft className="h-4 w-4" /> Voltar
                </button>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <h1 className="text-lg font-bold text-foreground">Despesas</h1>
                        {!isOnline && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                <WifiOff className="h-3 w-3" /> Offline
                            </span>
                        )}
                        {offlineCreateExpense.isOfflineQueued && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                Pendente de sync
                            </span>
                        )}
                    </div>
                    <button
                        onClick={() => {
                            if (!canCreateExpense) return
                            resetForm()
                            setShowForm(true)
                        }}
                        disabled={!canCreateExpense}
                        className="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
                    >
                        <Plus className="h-3.5 w-3.5" /> Nova
                    </button>
                </div>
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                {showForm && (
                    <ExpenseForm
                        categories={categories}
                        onSubmit={(data) => handleSaveExpense(data)}
                        editingId={editingId}
                        initialData={initialData}
                        variant="inline"
                        onClose={resetForm}
                    />
                )}

                {expenses.length > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-surface-400">
                                Registradas ({expenses.length})
                            </h3>
                            <span className="text-sm font-bold text-foreground">{formatCurrency(total)}</span>
                        </div>

                        {expenses.map((expense) => {
                            const categoryColor = expense.category?.color ?? '#f59e0b'
                            const statusInfo = EXPENSE_STATUS_MAP[expense.status as ExpenseStatus] ?? EXPENSE_STATUS_MAP.pending
                            const receiptUrl = buildStorageUrl(expense.receipt_path)
                            const canEdit = canUpdateExpense && (expense.status === 'pending' || expense.status === 'rejected')
                            const canRemove = canDeleteExpense && (expense.status === 'pending' || expense.status === 'rejected')

                            return (
                                <div
                                    key={expense.id}
                                    className={cn(
                                        'flex items-center gap-3 rounded-xl bg-card p-3',
                                        canEdit && 'cursor-pointer active:bg-surface-50 dark:active:bg-surface-800'
                                    )}
                                    onClick={() => canEdit && handleEdit(expense)}
                                >
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
                                        <p className="text-sm font-medium text-foreground">{expense.description}</p>
                                        {expense.category?.name && (
                                            <p className="truncate text-xs text-surface-500">{expense.category.name}</p>
                                        )}
                                        <span className={cn('mt-0.5 inline-block rounded-full px-1.5 py-0.5 text-[10px] font-medium', statusInfo.cls)}>
                                            {statusInfo.label}
                                        </span>
                                        {expense.status === 'rejected' && expense.rejection_reason && (
                                            <p className="mt-0.5 truncate text-[10px] text-red-500">
                                                Motivo: {expense.rejection_reason}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex-shrink-0 text-right">
                                        <p className="text-sm font-bold text-foreground">{formatCurrency(Number(expense.amount))}</p>
                                    </div>
                                    {(canEdit || canRemove) && (
                                        <div className="flex flex-col gap-1">
                                            {canEdit && (
                                                <button
                                                    onClick={(event) => { event.stopPropagation(); handleEdit(expense) }}
                                                    aria-label="Editar despesa"
                                                    className="flex h-7 w-7 items-center justify-center rounded-full text-brand-500 hover:bg-brand-50 dark:hover:bg-brand-900/20"
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </button>
                                            )}
                                            {canRemove && (
                                                <button
                                                    onClick={(event) => { event.stopPropagation(); void handleRemove(expense.id) }}
                                                    aria-label="Remover despesa"
                                                    className="flex h-7 w-7 items-center justify-center rounded-full text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )
                        })}
                    </div>
                )}

                {expensesQuery.isLoading && (
                    <div className="flex flex-col items-center justify-center gap-3 py-20">
                        <Loader2 className="h-8 w-8 animate-spin text-brand-500" />
                        <p className="text-sm text-surface-500">Carregando despesas...</p>
                    </div>
                )}

                {expensesQuery.isError && (
                    <div className="flex flex-col items-center justify-center gap-3 py-20 text-center">
                        <Receipt className="h-12 w-12 text-surface-300" />
                        <p className="text-sm text-surface-500">Nao foi possivel carregar as despesas desta OS.</p>
                        <button
                            onClick={() => void queryClient.invalidateQueries({ queryKey: ['tech-expenses'] })}
                            className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white"
                        >
                            Tentar novamente
                        </button>
                    </div>
                )}

                {!expensesQuery.isLoading && !showForm && expenses.length === 0 && !expensesQuery.isError && (
                    <div className="flex flex-col items-center justify-center gap-3 py-20">
                        <Receipt className="h-12 w-12 text-surface-300" />
                        <p className="text-sm text-surface-500">Nenhuma despesa registrada.</p>
                        <button
                            onClick={() => setShowForm(true)}
                            disabled={!canCreateExpense}
                            className="text-sm font-medium text-brand-600 disabled:opacity-50"
                        >
                            Adicionar despesa
                        </button>
                    </div>
                )}
            </div>
        </div>
    )
}
