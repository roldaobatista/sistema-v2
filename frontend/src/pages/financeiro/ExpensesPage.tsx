import { useState, useEffect, useRef } from 'react'
import { toast } from 'sonner'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import type { AxiosError } from 'axios'
import { handleFormError } from '@/lib/form-utils'
import { requiredString, optionalString } from '@/schemas/common'
import {
    Receipt, Plus, Search, CheckCircle, XCircle, ClipboardCheck,
    Clock, Eye, Trash2, RefreshCw, Pencil, RotateCcw, Settings, Download, DollarSign, Copy, History,
} from 'lucide-react'
import api from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { queryKeys } from '@/lib/query-keys'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { EXPENSE_STATUS } from '@/lib/constants'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/ui/iconbutton'
import { Badge, type BadgeProps } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { Modal } from '@/components/ui/modal'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import { ExpenseAnalyticsPanel } from '@/components/expenses/ExpenseAnalyticsPanel'
import { ExpenseHistoryModal } from '@/components/expenses/ExpenseHistoryModal'
import { ExpenseCategoryManager } from '@/components/expenses/ExpenseCategoryManager'
import { ColorDot } from '@/components/ui/color-dot'
import { formatCurrency } from '@/lib/utils'

const fileSchema = z.custom<File>(
    (value) => typeof File !== 'undefined' && value instanceof File,
    { message: 'Arquivo inválido' },
)

const expenseSchema = z.object({
    expense_category_id: optionalString,
    work_order_id: optionalString,
    chart_of_account_id: optionalString,
    description: requiredString('Descrição é obrigatória'),
    amount: z.coerce.number({ required_error: 'Valor é obrigatório' }).min(0.01, 'Valor inválido'),
    expense_date: requiredString('Data é obrigatória'),
    payment_method: optionalString,
    notes: optionalString,
    affects_technician_cash: z.boolean(),
    affects_net_value: z.boolean(),
    receipt: fileSchema.nullable().optional(),
    km_quantity: optionalString,
    km_rate: optionalString,
    km_billed_to_client: z.boolean(),
})

type ExpenseFormData = z.infer<typeof expenseSchema>

const statusConfig: Record<string, { label: string; variant: BadgeProps['variant'] }> = {
    pending: { label: 'Pendente', variant: 'warning' },
    reviewed: { label: 'Conferido', variant: 'info' },
    approved: { label: 'Aprovado', variant: 'success' },
    rejected: { label: 'Rejeitado', variant: 'danger' },
    reimbursed: { label: 'Reembolsado', variant: 'success' },
}

const isEditableExpenseStatus = (status: string): status is 'pending' | 'reviewed' | 'rejected' =>
    status === EXPENSE_STATUS.PENDING || status === EXPENSE_STATUS.REVIEWED || status === EXPENSE_STATUS.REJECTED

const fallbackPaymentMethods: Record<string, string> = {
    dinheiro: 'Dinheiro', pix: 'PIX', cartao_credito: 'Cartão Crédito',
    cartao_debito: 'Cartão Débito', boleto: 'Boleto', transferencia: 'Transferência',
    corporate_card: 'Cartão Corporativo',
}

interface Exp {
    id: number; description: string; amount: string
    expense_date: string; status: string; payment_method: string | null
    notes: string | null; receipt_path: string | null
    chart_of_account_id?: number | null
    chart_of_account?: { id: number; code: string; name: string; type: string } | null
    rejection_reason?: string | null
    affects_technician_cash?: boolean
    affects_net_value?: boolean
    km_quantity?: string | null
    km_rate?: string | null
    km_billed_to_client?: boolean
    category: { id: number; name: string; color: string } | null
    creator: { id: number; name: string }
    work_order: { id: number; number: string; os_number?: string | null; business_number?: string | null } | null
    approver?: { id: number; name: string } | null
    reviewer?: { id: number; name: string } | null
    _warning?: string
    _budget_warning?: string
}

interface StatusHistoryEntry {
    id: number; from_status: string | null; to_status: string
    reason: string | null; changed_by: string; changed_at: string
}

interface PaymentMethodOption {
    code: string
    name: string
}

interface ApiError {
    response?: {
        status?: number
        data?: { message?: string; errors?: Record<string, string[]> }
    }
}

const fmtDate = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('pt-BR')
const woIdentifier = (wo?: { number: string; os_number?: string | null; business_number?: string | null } | null) =>
    wo?.business_number ?? wo?.os_number ?? wo?.number ?? '—'

export function ExpensesPage() {
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canViewChart = hasPermission('finance.chart.view')
    const canViewPaymentMethods = hasPermission('finance.payable.view') || hasPermission('finance.receivable.view')
    const canViewUsers = hasPermission('iam.user.view')
    const [searchParams, setSearchParams] = useSearchParams()

    const canCreate = hasPermission('expenses.expense.create')
    const canUpdate = hasPermission('expenses.expense.update')
    const canApprove = hasPermission('expenses.expense.approve')
    const canReview = hasPermission('expenses.expense.review')
    const canDelete = hasPermission('expenses.expense.delete')

    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState('')
    const [catFilter, setCatFilter] = useState('')
    const [page, setPage] = useState(1)
    const [showForm, setShowForm] = useState(false)
    const [showDetail, setShowDetail] = useState<Exp | null>(null)
    const [showCatManager, setShowCatManager] = useState(false)
    const [editingId, setEditingId] = useState<number | null>(null)
    const [rejectTarget, setRejectTarget] = useState<number | null>(null)
    const [rejectReason, setRejectReason] = useState('')
    const [deleteTarget, setDeleteTarget] = useState<number | null>(null)
    const [dateFrom, setDateFrom] = useState('')
    const [dateTo, setDateTo] = useState('')
    const [debouncedSearch, setDebouncedSearch] = useState('')

    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
    const searchTimer = useRef<ReturnType<typeof setTimeout>>(undefined)
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
    const [showBatchReject, setShowBatchReject] = useState(false)
    const [batchRejectReason, setBatchRejectReason] = useState('')
    const [creatorFilter, setCreatorFilter] = useState('')
    const [woFilter, setWoFilter] = useState(() => searchParams.get('work_order_id')?.trim() ?? '')
    const [showAnalytics, setShowAnalytics] = useState(false)
    const [showHistory, setShowHistory] = useState<number | null>(null)
    const initializedFromQueryRef = useRef(false)
    const emptyForm: ExpenseFormData = {
        expense_category_id: '', work_order_id: '',
        chart_of_account_id: '', description: '', amount: 0, expense_date: '', payment_method: '', notes: '',
        affects_technician_cash: false, affects_net_value: true, receipt: null,
        km_quantity: '', km_rate: '', km_billed_to_client: false,
    }

    const form = useForm<ExpenseFormData>({
        resolver: zodResolver(expenseSchema),
        defaultValues: emptyForm,
    })

    const queryWorkOrderId = searchParams.get('work_order_id')?.trim() ?? ''
    const queryOpenNew = searchParams.get('new') === '1'

    useEffect(() => {
        clearTimeout(searchTimer.current)
        searchTimer.current = setTimeout(() => setDebouncedSearch(search), 300)
        return () => clearTimeout(searchTimer.current)
    }, [search])

    useEffect(() => {
        if (initializedFromQueryRef.current) return
        initializedFromQueryRef.current = true

        if (queryOpenNew && canCreate) {
            setEditingId(null)
            form.reset({
                ...emptyForm,
                work_order_id: queryWorkOrderId || '',
                expense_date: new Date().toISOString().slice(0, 10),
            })
            setShowForm(true)
        }

        if (queryOpenNew) {
            const next = new URLSearchParams(searchParams)
            next.delete('new')
            setSearchParams(next, { replace: true })
        }
    }, [canCreate, queryOpenNew, queryWorkOrderId, searchParams, setSearchParams])

    // Reset page when filters change
    useEffect(() => { setPage(1) }, [debouncedSearch, statusFilter, catFilter, dateFrom, dateTo, creatorFilter, woFilter])

    const { data: res, isLoading, isError, refetch } = useQuery({
        queryKey: queryKeys.financial.expenses.list({ search: debouncedSearch, status: statusFilter, category: catFilter, date_from: dateFrom, date_to: dateTo, creator: creatorFilter, work_order: woFilter, page }),
        queryFn: () => financialApi.expenses.list({
            search: debouncedSearch || undefined,
            status: statusFilter || undefined,
            expense_category_id: catFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            created_by: creatorFilter || undefined,
            work_order_id: woFilter || undefined,
            per_page: 50,
            page,
        }),
    })
    const records: Exp[] = res?.data?.data ?? []
    const pagination = res?.data ? { current: res.data.current_page, last: res.data.last_page, total: res.data.total } : null

    const { data: summaryRes } = useQuery({
        queryKey: queryKeys.financial.expenses.summary,
        queryFn: () => financialApi.expenses.summary(),
    })
    const summary = summaryRes?.data ?? {}

    const { data: catsRes } = useQuery({
        queryKey: queryKeys.financial.expenses.categories,
        queryFn: () => financialApi.expenses.categories(),
    })
    const categories = catsRes?.data?.data ?? catsRes?.data ?? []

    const { data: chartRes } = useQuery({
        queryKey: queryKeys.financial.chartOfAccounts({ is_active: 1, type: 'expense' }),
        queryFn: () => financialApi.chartOfAccounts.list({ is_active: 1, type: 'expense' }),
        enabled: canViewChart && showForm,
    })
    const chartAccounts: { id: number; code: string; name: string }[] = chartRes?.data?.data ?? []

    const { data: paymentMethodsRes } = useQuery({
        queryKey: queryKeys.financial.paymentMethods,
        queryFn: () => financialApi.paymentMethods.list(),
        enabled: canViewPaymentMethods,
    })

    const apiPaymentMethods: Array<{ code?: string; name?: string; is_active?: boolean }> = Array.isArray(paymentMethodsRes?.data)
        ? paymentMethodsRes.data
        : []

    const activeApiPaymentMethods: PaymentMethodOption[] = (apiPaymentMethods || [])
        .filter(item => item?.code && item?.name && item?.is_active !== false)
        .map(item => ({
            code: String(item.code),
            name: String(item.name),
        }))

    const paymentMethodOptions: PaymentMethodOption[] = activeApiPaymentMethods.length > 0
        ? activeApiPaymentMethods
        : Object.entries(fallbackPaymentMethods).map(([code, name]) => ({ code, name }))

    const paymentMethodLabelMap = new Map<string, string>(
        paymentMethodOptions.map(option => [option.code, option.name])
    )

    const getPaymentMethodLabel = (code: string | null | undefined): string =>
        code ? (paymentMethodLabelMap.get(code) ?? fallbackPaymentMethods[code] ?? code) : 'Nao definido'

    const { data: analyticsRes } = useQuery({
        queryKey: queryKeys.financial.expenses.analytics,
        queryFn: () => financialApi.expenses.analytics(),
        enabled: showAnalytics,
    })
    const analytics = analyticsRes?.data ?? null

    const { data: wosRes } = useQuery({
        queryKey: [...queryKeys.workOrders.list({}), 'expense-form'],
        queryFn: () => api.get('/financial/lookups/work-orders', { params: { limit: 50 } }),
        enabled: showForm && (canCreate || canUpdate),
    })

    const { data: usersRes } = useQuery({
        queryKey: queryKeys.users.list({ per_page: 200, active: 1 }),
        queryFn: () => api.get('/users', { params: { per_page: 200, active: 1 } }),
        enabled: canViewUsers,
    })
    const allUsers: { id: number; name: string }[] = usersRes?.data?.data ?? usersRes?.data ?? []

    const saveMut = useMutation({
        mutationFn: (data: ExpenseFormData) => {
            const formData = new FormData()
            if (data.expense_category_id) formData.append('expense_category_id', String(data.expense_category_id))
            if (data.work_order_id) formData.append('work_order_id', String(data.work_order_id))
            if (data.chart_of_account_id) formData.append('chart_of_account_id', String(data.chart_of_account_id))
            formData.append('description', data.description)
            formData.append('amount', String(data.amount))
            formData.append('expense_date', data.expense_date)
            if (data.payment_method) formData.append('payment_method', data.payment_method)
            if (data.notes) formData.append('notes', data.notes)
            formData.append('affects_technician_cash', data.affects_technician_cash ? '1' : '0')
            formData.append('affects_net_value', data.affects_net_value ? '1' : '0')
            if (data.km_quantity) formData.append('km_quantity', String(data.km_quantity))
            if (data.km_rate) formData.append('km_rate', String(data.km_rate))
            formData.append('km_billed_to_client', data.km_billed_to_client ? '1' : '0')
            if (data.receipt) formData.append('receipt', data.receipt as File)

            if (editingId) {
                formData.append('_method', 'PUT')
                return financialApi.expenses.update(editingId, formData)
            }
            return financialApi.expenses.create(formData)
        },
        onSuccess: (res: { data?: { _warning?: string; _budget_warning?: string } }) => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.all })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.summary })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.analytics })
            broadcastQueryInvalidation(['expenses', 'expense-summary', 'expense-analytics'], 'Despesa')
            setShowForm(false)
            setEditingId(null)
            form.reset(emptyForm)
            const msg = editingId ? 'Despesa atualizada com sucesso' : 'Despesa criada com sucesso'
            const warning = res?.data?._warning
            const budgetWarning = res?.data?._budget_warning
            toast.success(warning ? `${msg}. ⚠️ ${warning}` : budgetWarning ? `${msg}. ⚠️ ${budgetWarning}` : msg)
        },
        onError: (err: unknown) => {
            const apiErr = err as AxiosError<{ message: string; errors?: Record<string, string[]> }>
            if (apiErr?.response?.status === 403) {
                toast.error('Você não tem permissão para esta ação')
            } else {
                handleFormError(apiErr, form.setError, 'Erro ao salvar despesa')
            }
        },
    })

    const statusMut = useMutation({
        mutationFn: ({ id, status, rejection_reason }: { id: number; status: string; rejection_reason?: string }) =>
            financialApi.expenses.updateStatus(id, { status, rejection_reason }),
        onSuccess: (_d, vars) => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.all })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.summary })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.analytics })
            broadcastQueryInvalidation(['expenses', 'expense-summary', 'expense-analytics'], 'Despesa')
            setRejectTarget(null)
            toast.success(`Despesa ${statusConfig[vars.status]?.label?.toLowerCase() ?? vars.status} com sucesso`)
        },
        onError: (err: ApiError) => {
            setRejectTarget(null)
            if (err?.response?.status === 403) {
                toast.error(err?.response?.data?.message ?? 'Você não tem permissão para esta ação')
            } else {
                toast.error(err?.response?.data?.message ?? 'Erro ao atualizar status')
            }
        },
    })

    const delMut = useMutation({
        mutationFn: (id: number) => financialApi.expenses.destroy(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.all })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.summary })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.analytics })
            broadcastQueryInvalidation(['expenses', 'expense-summary', 'expense-analytics'], 'Despesa')
            setDeleteTarget(null)
            toast.success('Despesa excluída com sucesso')
        },
        onError: (err: ApiError) => {
            setDeleteTarget(null)
            if (err?.response?.status === 403) {
                toast.error('Você não tem permissão para excluir')
            } else {
                toast.error(err?.response?.data?.message ?? 'Erro ao excluir despesa')
            }
        },
    })

    const batchMut = useMutation({
        mutationFn: (data: { expense_ids: number[]; status: string; rejection_reason?: string }) =>
            financialApi.expenses.batchStatus(data),
        onSuccess: (res: { data?: { message?: string } }) => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.all })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.summary })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.analytics })
            broadcastQueryInvalidation(['expenses', 'expense-summary', 'expense-analytics'], 'Despesa')
            setSelectedIds(new Set())
            setShowBatchReject(false)
            toast.success(res?.data?.message ?? 'Lote processado com sucesso')
        },
        onError: (err: ApiError) => {
            if (err?.response?.status === 422) {
                toast.error(err?.response?.data?.message ?? 'Erro de validação no lote')
            } else if (err?.response?.status === 403) {
                toast.error('Você não tem permissão para esta ação')
            } else {
                toast.error(err?.response?.data?.message ?? 'Erro ao processar lote')
            }
        },
    })

    const dupMut = useMutation({
        mutationFn: (id: number) => financialApi.expenses.duplicate(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.all })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.summary })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.analytics })
            broadcastQueryInvalidation(['expenses', 'expense-summary', 'expense-analytics'], 'Despesa')
            toast.success('Despesa duplicada como pendente')
        },
        onError: (err: ApiError) => {
            toast.error(err?.response?.data?.message ?? 'Erro ao duplicar despesa')
        },
    })

    const reviewMut = useMutation({
        mutationFn: (id: number) => financialApi.expenses.review(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.all })
            qc.invalidateQueries({ queryKey: queryKeys.financial.expenses.summary })
            broadcastQueryInvalidation(['expenses', 'expense-summary'], 'Despesa')
            toast.success('Despesa conferida com sucesso')
        },
        onError: (err: ApiError) => {
            if (err?.response?.status === 403) {
                toast.error(err?.response?.data?.message ?? 'Você não tem permissão para conferir')
            } else {
                toast.error(err?.response?.data?.message ?? 'Erro ao conferir despesa')
            }
        },
    })

    const { data: historyRes } = useQuery({
        queryKey: ['expense-history', showHistory],
        queryFn: () => financialApi.expenses.history(showHistory!),
        enabled: showHistory !== null,
    })
    const historyEntries: StatusHistoryEntry[] = historyRes?.data?.data ?? historyRes?.data ?? []

    const handleExport = async () => {
        try {
            const params = new URLSearchParams()
            if (statusFilter) params.set('status', statusFilter)
            if (catFilter) params.set('expense_category_id', catFilter)
            if (dateFrom) params.set('date_from', dateFrom)
            if (dateTo) params.set('date_to', dateTo)
            if (creatorFilter) params.set('created_by', creatorFilter)
            if (woFilter) params.set('work_order_id', woFilter)
            const response = await api.get(`/expenses-export?${params.toString()}`, { responseType: 'blob' })
            const url = window.URL.createObjectURL(new Blob([response.data]))
            const a = document.createElement('a')
            a.href = url
            a.download = `despesas_${new Date().toISOString().slice(0, 10)}.csv`
            a.click()
            window.URL.revokeObjectURL(url)
            toast.success('Exportação concluída')
        } catch {
            toast.error('Erro ao exportar despesas')
        }
    }

    const toggleSelect = (id: number) => {
        setSelectedIds(prev => {
            const next = new Set(prev)
            if (next.has(id)) next.delete(id); else next.add(id)
            return next
        })
    }

    const toggleSelectAll = () => {
        const eligibleIds = (records || []).filter(r => r.status === EXPENSE_STATUS.PENDING || r.status === EXPENSE_STATUS.REVIEWED).map(r => r.id)
        if (eligibleIds.length > 0 && eligibleIds.every(id => selectedIds.has(id))) {
            setSelectedIds(new Set())
        } else {
            setSelectedIds(new Set(eligibleIds))
        }
    }

    const eligibleRecords = (records || []).filter(r => r.status === EXPENSE_STATUS.PENDING || r.status === EXPENSE_STATUS.REVIEWED)
    const allEligibleSelected = eligibleRecords.length > 0 && eligibleRecords.every(r => selectedIds.has(r.id))

    const filterUsers = allUsers.length > 0 ? allUsers : Array.from(new Map((records || []).map(r => [r.creator.id, r.creator])).values())



    const loadDetail = async (exp: Exp) => {
        try {
            const { data } = await financialApi.expenses.detail(exp.id)
            setShowDetail(data)
        } catch (err: unknown) {
            const apiErr = err as ApiError
            if (apiErr?.response?.status === 403) {
                toast.error('Você não tem permissão para ver esta despesa')
            } else {
                toast.error(apiErr?.response?.data?.message ?? 'Erro ao carregar detalhes da despesa')
            }
        }
    }

    const openEdit = (exp: Exp) => {
        if (!isEditableExpenseStatus(exp.status)) return
        setEditingId(exp.id)
        form.reset({
            expense_category_id: String(exp.category?.id ?? ''),
            work_order_id: String(exp.work_order?.id ?? ''),
            chart_of_account_id: String(exp.chart_of_account?.id ?? ''),
            description: exp.description,
            amount: Number(exp.amount),
            expense_date: exp.expense_date,
            payment_method: String(exp.payment_method ?? ''),
            notes: exp.notes ?? '',
            affects_technician_cash: !!exp.affects_technician_cash,
            affects_net_value: exp.affects_net_value !== false,
            receipt: null,
            km_quantity: String(exp.km_quantity ?? ''),
            km_rate: String(exp.km_rate ?? ''),
            km_billed_to_client: !!exp.km_billed_to_client,
        })
        setShowForm(true)
    }

    const openCreateForm = () => {
        setEditingId(null)
        form.reset({
            ...emptyForm,
            work_order_id: woFilter || '',
            expense_date: new Date().toISOString().slice(0, 10),
        })
        setShowForm(true)
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Despesas"
                subtitle="Controle de despesas e aprovações"
                count={pagination?.total}
                actions={[
                    { label: 'Exportar CSV', onClick: handleExport, icon: <Download className="h-4 w-4" />, variant: 'outline' as const },
                    ...(canCreate ? [{ label: 'Categorias', onClick: () => setShowCatManager(true), icon: <Settings className="h-4 w-4" />, variant: 'outline' as const }] : []),
                    ...(canCreate ? [{ label: 'Nova Despesa', onClick: openCreateForm, icon: <Plus className="h-4 w-4" /> }] : []),
                ]}
            />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <div className="flex items-center gap-2 text-amber-600"><Clock className="h-4 w-4" /><span className="text-xs font-medium">Pendente Aprovação</span></div>
                    <p className="mt-1 text-xl font-bold text-surface-900">{formatCurrency(summary.pending ?? 0)}</p>
                    <p className="mt-0.5 text-xs text-surface-400">{summary.pending_count ?? 0} despesa(s)</p>
                </div>
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <div className="flex items-center gap-2 text-sky-600"><CheckCircle className="h-4 w-4" /><span className="text-xs font-medium">Aprovado</span></div>
                    <p className="mt-1 text-xl font-bold text-sky-600">{formatCurrency(summary.approved ?? 0)}</p>
                </div>
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <div className="flex items-center gap-2 text-emerald-600"><DollarSign className="h-4 w-4" /><span className="text-xs font-medium">Reembolsado</span></div>
                    <p className="mt-1 text-xl font-bold text-emerald-600">{formatCurrency(summary.reimbursed ?? 0)}</p>
                </div>
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <div className="flex items-center gap-2 text-surface-600"><Receipt className="h-4 w-4" /><span className="text-xs font-medium">Total do Mês</span></div>
                    <p className="mt-1 text-xl font-bold text-surface-900">{formatCurrency(summary.month_total ?? 0)}</p>
                    <p className="mt-0.5 text-xs text-surface-400">{summary.total_count ?? 0} total</p>
                </div>
            </div>

            <ExpenseAnalyticsPanel
                show={showAnalytics}
                onToggle={() => setShowAnalytics(p => !p)}
                analytics={analytics}
                fmtBRL={(v: string | number) => formatCurrency(Number(v))}
            />

            <div className="flex flex-wrap gap-3">
                <div className="relative flex-1 max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input value={search} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value)} placeholder="Buscar descrição"
                        className="w-full rounded-lg border border-default bg-surface-50 py-2.5 pl-10 pr-4 text-sm focus:border-brand-500 focus:outline-none" aria-label="Buscar por descrição" />
                </div>
                <select value={statusFilter} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setStatusFilter(e.target.value)}
                    aria-label="Filtrar por status"
                    className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <option value="">Todos os status</option>
                    {Object.entries(statusConfig).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                </select>
                <select value={catFilter} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setCatFilter(e.target.value)}
                    aria-label="Filtrar por categoria"
                    className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <option value="">Todas categorias</option>
                    {(categories || []).map((c: { id: number; name: string }) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                {filterUsers.length > 1 && (
                    <select value={creatorFilter} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setCreatorFilter(e.target.value)}
                        aria-label="Filtrar por responsável"
                        className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option value="">Todos responsáveis</option>
                        {(filterUsers || []).map((c: { id: number; name: string }) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                )}
            </div>
            <div className="flex flex-wrap gap-3">
                <Input type="date" value={dateFrom} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDateFrom(e.target.value)} className="w-40" placeholder="De" />
                <Input type="date" value={dateTo} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDateTo(e.target.value)} className="w-40" placeholder="Até" />
                {woFilter && (
                    <Button variant="ghost" size="sm" onClick={() => setWoFilter('')} className="text-xs text-surface-500">
                        Limpar filtro OS
                    </Button>
                )}
            </div>

            {selectedIds.size > 0 && canApprove && (
                <div className="flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50/50 px-4 py-2.5">
                    <span className="text-sm font-medium text-brand-700">{selectedIds.size} selecionada(s)</span>
                    <Button size="sm" variant="outline" icon={<CheckCircle className="h-4 w-4" />}
                        loading={batchMut.isPending}
                        onClick={() => batchMut.mutate({ expense_ids: Array.from(selectedIds), status: EXPENSE_STATUS.APPROVED })}>
                        Aprovar Selecionadas
                    </Button>
                    <Button size="sm" variant="outline" className="border-red-300 text-red-600 hover:bg-red-50" icon={<XCircle className="h-4 w-4" />}
                        onClick={() => { setShowBatchReject(true); setBatchRejectReason('') }}>
                        Rejeitar Selecionadas
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => setSelectedIds(new Set())}>
                        Limpar seleção
                    </Button>
                </div>
            )}

            {isError && (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Erro ao carregar despesas. <button onClick={() => refetch()} className="underline ml-1">Tentar novamente</button>
                </div>
            )}

            <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-subtle bg-surface-50">
                            {canApprove && (
                                <th className="w-10 px-3 py-2.5">
                                    <input type="checkbox" checked={allEligibleSelected && eligibleRecords.length > 0} onChange={toggleSelectAll}
                                        className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500"
                                        title="Selecionar pendentes e conferidas" />
                                </th>
                            )}
                            <th className="px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Descrição</th>
                            <th className="hidden px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600 sm:table-cell">Categoria</th>
                            <th className="hidden px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600 md:table-cell">Responsável</th>
                            <th className="hidden px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600 md:table-cell">Data</th>
                            <th className="px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Status</th>
                            <th className="px-3.5 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Valor</th>
                            <th className="px-3.5 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-subtle">
                        {isLoading ? (
                            Array.from({ length: 5 }).map((_, i) => (
                                <tr key={`skel-${i}`}>
                                    {canApprove && <td className="px-3 py-3"><div className="h-4 w-4 animate-pulse rounded bg-surface-200" /></td>}
                                    <td className="px-4 py-3"><div className="h-4 w-32 animate-pulse rounded bg-surface-200" /></td>
                                    <td className="hidden px-4 py-3 sm:table-cell"><div className="h-4 w-20 animate-pulse rounded bg-surface-200" /></td>
                                    <td className="hidden px-4 py-3 md:table-cell"><div className="h-4 w-24 animate-pulse rounded bg-surface-200" /></td>
                                    <td className="hidden px-4 py-3 md:table-cell"><div className="h-4 w-20 animate-pulse rounded bg-surface-200" /></td>
                                    <td className="px-4 py-3"><div className="h-5 w-16 animate-pulse rounded-full bg-surface-200" /></td>
                                    <td className="px-4 py-3"><div className="ml-auto h-4 w-20 animate-pulse rounded bg-surface-200" /></td>
                                    <td className="px-4 py-3"><div className="ml-auto h-4 w-16 animate-pulse rounded bg-surface-200" /></td>
                                </tr>
                            ))
                        ) : records.length === 0 ? (
                            <tr><td colSpan={canApprove ? 8 : 7} className="px-4 py-16 text-center">
                                <Receipt className="mx-auto h-10 w-10 text-surface-300" />
                                <p className="mt-3 text-sm font-medium text-surface-600">Nenhuma despesa encontrada</p>
                                <p className="mt-1 text-xs text-surface-400">Crie uma nova despesa para começar</p>
                                {canCreate && (
                                    <Button className="mt-4" size="sm" icon={<Plus className="h-4 w-4" />}
                                        onClick={openCreateForm}>
                                        Nova Despesa
                                    </Button>
                                )}
                            </td></tr>
                        ) : (records || []).map(r => (
                            <tr key={r.id} className={`hover:bg-surface-50 transition-colors duration-100 ${selectedIds.has(r.id) ? 'bg-brand-50/30' : ''}`}>
                                {canApprove && (
                                    <td className="px-3 py-3">
                                        {(r.status === EXPENSE_STATUS.PENDING || r.status === EXPENSE_STATUS.REVIEWED) ? (
                                            <input type="checkbox" checked={selectedIds.has(r.id)} onChange={() => toggleSelect(r.id)}
                                                aria-label={`Selecionar despesa ${r.description}`}
                                                className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500" />
                                        ) : <div className="h-4 w-4" />}
                                    </td>
                                )}
                                <td className="px-4 py-3">
                                    <p className="text-sm font-medium text-surface-900">{r.description}</p>
                                    {r.work_order && <p className="text-xs text-brand-500">{woIdentifier(r.work_order)}</p>}
                                </td>
                                <td className="hidden px-4 py-3 sm:table-cell">
                                    {r.category ? (
                                        <span className="inline-flex items-center gap-1.5 text-xs font-medium">
                                            <ColorDot color={r.category.color} size="md" />
                                            {r.category.name}
                                        </span>
                                    ) : '—'}
                                </td>
                                <td className="hidden px-4 py-3 text-sm text-surface-600 md:table-cell">{r.creator.name}</td>
                                <td className="hidden px-4 py-3 text-sm text-surface-500 md:table-cell">{fmtDate(r.expense_date)}</td>
                                <td className="px-4 py-3"><Badge variant={statusConfig[r.status]?.variant}>{statusConfig[r.status]?.label}</Badge></td>
                                <td className="px-3.5 py-2.5 text-right text-sm font-semibold text-surface-900">{formatCurrency(Number(r.amount))}</td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-end gap-1">
                                        <IconButton label="Ver detalhes" icon={<Eye className="h-4 w-4" />} onClick={() => loadDetail(r)} />
                                        {canUpdate && isEditableExpenseStatus(r.status) && (
                                            <IconButton label="Editar" icon={<Pencil className="h-4 w-4" />} onClick={() => openEdit(r)} className="hover:text-brand-600" />
                                        )}
                                        {canReview && r.status === EXPENSE_STATUS.PENDING && (
                                            <IconButton label="Conferir" icon={<ClipboardCheck className="h-4 w-4" />} onClick={() => reviewMut.mutate(r.id)} className="hover:text-blue-600" />
                                        )}
                                        {canApprove && (r.status === EXPENSE_STATUS.PENDING || r.status === EXPENSE_STATUS.REVIEWED) && (
                                            <>
                                                <IconButton label="Aprovar" icon={<CheckCircle className="h-4 w-4" />} onClick={() => statusMut.mutate({ id: r.id, status: EXPENSE_STATUS.APPROVED })} className="hover:text-sky-600" />
                                                <IconButton label="Rejeitar" icon={<XCircle className="h-4 w-4" />} onClick={() => {
                                                    setRejectTarget(r.id)
                                                    setRejectReason('')
                                                }} className="hover:text-red-600" />
                                            </>
                                        )}
                                        {canApprove && r.status === EXPENSE_STATUS.APPROVED && (
                                            <IconButton label="Marcar como reembolsado" icon={<RefreshCw className="h-4 w-4" />} onClick={() => statusMut.mutate({ id: r.id, status: EXPENSE_STATUS.REIMBURSED })} className="hover:text-emerald-600" />
                                        )}
                                        {r.status === EXPENSE_STATUS.REJECTED && (canUpdate || canApprove) && (
                                            <IconButton label="Resubmeter como pendente" icon={<RotateCcw className="h-4 w-4" />} onClick={() => statusMut.mutate({ id: r.id, status: EXPENSE_STATUS.PENDING })} className="hover:text-amber-600" />
                                        )}
                                        {canCreate && (
                                            <IconButton label="Duplicar" icon={<Copy className="h-4 w-4" />} onClick={() => dupMut.mutate(r.id)} />
                                        )}
                                        <IconButton label="Histórico" icon={<History className="h-4 w-4" />} onClick={() => setShowHistory(r.id)} />
                                        {canDelete && isEditableExpenseStatus(r.status) && (
                                            <IconButton label="Excluir" icon={<Trash2 className="h-4 w-4" />} onClick={() => setDeleteTarget(r.id)} className="hover:text-red-600" />
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {pagination && pagination.last > 1 && (
                <div className="flex items-center justify-between px-4 py-3">
                    <span className="text-xs text-surface-500">{pagination.total} despesa(s) — Página {pagination.current} de {pagination.last}</span>
                    <div className="flex gap-1">
                        <Button variant="outline" size="sm" disabled={pagination.current <= 1} onClick={() => setPage(p => Math.max(1, p - 1))}>Anterior</Button>
                        <Button variant="outline" size="sm" disabled={pagination.current >= pagination.last} onClick={() => setPage(p => p + 1)}>Próxima</Button>
                    </div>
                </div>
            )}

            <Modal open={showForm} onOpenChange={(v) => { setShowForm(v); if (!v) { setEditingId(null); } }} title={editingId ? 'Editar Despesa' : 'Nova Despesa'} size="lg">
                <form onSubmit={form.handleSubmit(data => saveMut.mutate(data))} className="space-y-4">
                    <Controller control={form.control} name="description" render={({ field, fieldState }) => (
                        <div>
                            <Input label="Descrição *" {...field} value={field.value || ''} error={fieldState.error?.message} required />
                        </div>
                    )} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Controller control={form.control} name="expense_category_id" render={({ field, fieldState }) => (
                            <div>
                                <label htmlFor="expense_category_id" className="mb-1.5 block text-sm font-medium text-surface-700">Categoria</label>
                                <select id="expense_category_id" {...field} value={field.value || ''}
                                    className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:bg-surface-0 focus:outline-none focus:ring-2 ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-default focus:border-brand-400 focus:ring-brand-500/15'}`}>
                                    <option value="">Sem categoria</option>
                                    {(categories || []).map((c: { id: number; name: string }) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />

                        <Controller control={form.control} name="work_order_id" render={({ field, fieldState }) => (
                            <div>
                                <label htmlFor="work_order_id" className="mb-1.5 block text-sm font-medium text-surface-700">Vinculada à OS</label>
                                <select id="work_order_id" {...field} value={field.value || ''}
                                    className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:bg-surface-0 focus:outline-none focus:ring-2 ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-default focus:border-brand-400 focus:ring-brand-500/15'}`}>
                                    <option value="">Nenhuma</option>
                                    {(wosRes?.data?.data ?? []).map((wo: { id: number; number: string; os_number?: string | null; business_number?: string | null; customer?: { name: string } | null }) => <option key={wo.id} value={wo.id}>{wo.business_number ?? wo.os_number ?? wo.number} — {wo.customer?.name}</option>)}
                                </select>
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />
                    </div>
                    {canViewChart && (
                        <Controller control={form.control} name="chart_of_account_id" render={({ field, fieldState }) => (
                            <div>
                                <label htmlFor="chart_of_account_id" className="mb-1.5 block text-sm font-medium text-surface-700">Plano de Contas</label>
                                <select id="chart_of_account_id" {...field} value={field.value || ''}
                                    className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:bg-surface-0 focus:outline-none focus:ring-2 ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-default focus:border-brand-400 focus:ring-brand-500/15'}`}>
                                    <option value="">Nao classificado</option>
                                    {(chartAccounts || []).map(account => <option key={account.id} value={account.id}>{account.code} - {account.name}</option>)}
                                </select>
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />
                    )}
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Controller control={form.control} name="amount" render={({ field, fieldState }) => (
                            <div>
                                <CurrencyInput label="Valor (R$) *" value={field.value || 0} onChange={field.onChange} error={fieldState.error?.message} required />
                            </div>
                        )} />

                        <Controller control={form.control} name="expense_date" render={({ field, fieldState }) => (
                            <div>
                                <Input label="Data *" type="date" {...field} value={field.value || ''} error={fieldState.error?.message} required />
                            </div>
                        )} />

                        <Controller control={form.control} name="payment_method" render={({ field, fieldState }) => (
                            <div>
                                <label htmlFor="payment_method" className="mb-1.5 block text-sm font-medium text-surface-700">Forma Pgto</label>
                                <select id="payment_method" {...field} value={field.value || ''}
                                    className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:bg-surface-0 focus:outline-none focus:ring-2 ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-default focus:border-brand-400 focus:ring-brand-500/15'}`}>
                                    <option value="">Não definido</option>
                                    {paymentMethodOptions.map(method => <option key={method.code} value={method.code}>{method.name}</option>)}
                                </select>
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />
                    </div>

                    <Controller control={form.control} name="notes" render={({ field, fieldState }) => (
                        <div>
                            <label htmlFor="expense_notes" className="mb-1.5 block text-sm font-medium text-surface-700">Observações</label>
                            <textarea id="expense_notes" {...field} value={field.value || ''} rows={2}
                                className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:bg-surface-0 focus:outline-none focus:ring-2 ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-default focus:border-brand-400 focus:ring-brand-500/15'}`} />
                            {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                        </div>
                    )} />

                    <div className="flex flex-col gap-2 sm:flex-row sm:gap-6">
                        <Controller control={form.control} name="affects_technician_cash" render={({ field }) => (
                            <div className="flex items-center gap-2">
                                <input type="checkbox" id="affects_cash" checked={field.value} onChange={field.onChange}
                                    className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500" />
                                <label htmlFor="affects_cash" className="text-sm font-medium text-surface-700">Impacta caixa do técnico</label>
                            </div>
                        )} />

                        <Controller control={form.control} name="affects_net_value" render={({ field }) => (
                            <div className="flex items-center gap-2">
                                <input type="checkbox" id="affects_net" checked={field.value} onChange={field.onChange}
                                    className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500" />
                                <label htmlFor="affects_net" className="text-sm font-medium text-surface-700">Deduz do valor líquido (comissões)</label>
                            </div>
                        )} />
                    </div>
                    {/* Km Tracking */}
                    <div className="rounded-lg border border-default p-3 space-y-3 bg-surface-50/50">
                        <p className="text-xs font-semibold text-surface-600 uppercase tracking-wider">Km Rodados</p>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Controller control={form.control} name="km_quantity" render={({ field, fieldState }) => (
                                <div>
                                    <Input label="Quantidade (km)" type="number" step="0.1" {...field} value={field.value || ''}
                                        error={fieldState.error?.message}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                            const km = e.target.value
                                            field.onChange(km)
                                            const rate = form.getValues('km_rate')
                                            if (km && rate && !isNaN(Number(km)) && !isNaN(Number(rate))) {
                                                form.setValue('amount', Number((Number(km) * Number(rate)).toFixed(2)))
                                            }
                                        }} />
                                </div>
                            )} />

                            <Controller control={form.control} name="km_rate" render={({ field, fieldState }) => (
                                <div>
                                    <CurrencyInput label="Valor por km (R$)" value={parseFloat(String(field.value)) || 0}
                                        error={fieldState.error?.message}
                                        onChange={(val) => {
                                            field.onChange(val ? String(val) : '')
                                            const km = form.getValues('km_quantity')
                                            if (val && km && !isNaN(Number(km))) {
                                                form.setValue('amount', Number((Number(km) * val).toFixed(2)))
                                            }
                                        }} />
                                </div>
                            )} />

                            <Controller control={form.control} name="km_billed_to_client" render={({ field }) => (
                                <div className="flex items-end pb-1">
                                    <div className="flex items-center gap-2">
                                        <input type="checkbox" id="km_billed" checked={field.value} onChange={field.onChange}
                                            className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500" />
                                        <label htmlFor="km_billed" className="text-sm font-medium text-surface-700">Cobrar do cliente</label>
                                    </div>
                                </div>
                            )} />
                        </div>
                    </div>

                    <Controller control={form.control} name="receipt" render={({ field, fieldState }) => (
                        <div>
                            <label htmlFor="expense_receipt" className="mb-1.5 block text-sm font-medium text-surface-700">Comprovante</label>
                            <input id="expense_receipt" type="file" accept="image/*,.pdf" onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                if (e.target.files?.[0]) field.onChange(e.target.files[0])
                            }} className="w-full text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                            {editingId && !field.value && (
                                <p className="mt-1 text-xs text-surface-500">Deixe vazio para manter o comprovante atual (se houver).</p>
                            )}
                            {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                        </div>
                    )} />

                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" type="button" onClick={() => { setShowForm(false); setEditingId(null); }}>Cancelar</Button>
                        <Button type="submit" loading={saveMut.isPending} disabled={saveMut.isPending}>{editingId ? 'Salvar' : 'Criar'}</Button>
                    </div>
                </form>
            </Modal>

            <ExpenseCategoryManager
                open={showCatManager}
                onClose={() => setShowCatManager(false)}
                categories={categories}
            />

            <Modal open={!!showDetail} onOpenChange={() => setShowDetail(null)} title="Detalhes da Despesa" size="lg">
                {showDetail && (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div><span className="text-xs text-surface-500">Descrição</span><p className="text-sm font-medium">{showDetail.description}</p></div>
                            <div>
                                <span className="text-xs text-surface-500">Categoria</span>
                                {showDetail.category ? (
                                    <p className="flex items-center gap-1.5 text-sm font-medium">
                                        <ColorDot color={showDetail.category.color} size="md" />
                                        {showDetail.category.name}
                                    </p>
                                ) : <p className="text-sm text-surface-400">Sem categoria</p>}
                            </div>
                            <div>
                                <span className="text-xs text-surface-500">Plano de Contas</span>
                                <p className="text-sm font-medium">{showDetail.chart_of_account ? `${showDetail.chart_of_account.code} - ${showDetail.chart_of_account.name}` : '—'}</p>
                            </div>
                            <div><span className="text-xs text-surface-500">Valor</span><p className="text-sm font-semibold tabular-nums">{formatCurrency(Number(showDetail.amount))}</p></div>
                            <div><span className="text-xs text-surface-500">Status</span><Badge variant={statusConfig[showDetail.status]?.variant}>{statusConfig[showDetail.status]?.label}</Badge></div>
                            <div><span className="text-xs text-surface-500">Responsável</span><p className="text-sm">{showDetail.creator.name}</p></div>
                            <div><span className="text-xs text-surface-500">Data</span><p className="text-sm">{fmtDate(showDetail.expense_date)}</p></div>
                            {showDetail.payment_method && <div><span className="text-xs text-surface-500">Forma de Pagamento</span><p className="text-sm">{getPaymentMethodLabel(showDetail.payment_method)}</p></div>}
                            {showDetail.approver && <div><span className="text-xs text-surface-500">Aprovado por</span><p className="text-sm">{showDetail.approver.name}</p></div>}
                            {showDetail.work_order && <div><span className="text-xs text-surface-500">OS</span><p className="text-sm text-brand-600 font-medium">{woIdentifier(showDetail.work_order)}</p></div>}
                            {showDetail.reviewer && <div><span className="text-xs text-surface-500">Conferido por</span><p className="text-sm">{showDetail.reviewer.name}</p></div>}
                            {showDetail.affects_technician_cash && <div><span className="text-xs text-surface-500">Caixa do Técnico</span><Badge variant="info">Impacta caixa</Badge></div>}
                            {showDetail.affects_net_value && <div><span className="text-xs text-surface-500">Valor Líquido</span><Badge variant="warning">Deduz do líquido</Badge></div>}
                            {showDetail.km_quantity && Number(showDetail.km_quantity) > 0 && (
                                <div><span className="text-xs text-surface-500">Km Rodados</span><p className="text-sm font-medium">{Number(showDetail.km_quantity).toLocaleString('pt-BR', { minimumFractionDigits: 1 })} km</p></div>
                            )}
                            {showDetail.km_rate && Number(showDetail.km_rate) > 0 && (
                                <div><span className="text-xs text-surface-500">Valor por Km</span><p className="text-sm font-medium">{formatCurrency(Number(showDetail.km_rate))}</p></div>
                            )}
                            {showDetail.km_billed_to_client && <div><span className="text-xs text-surface-500">Km cobrado do cliente</span><Badge variant="info">Sim</Badge></div>}
                            {showDetail.receipt_path && <div><span className="text-xs text-surface-500">Comprovante</span><p className="text-sm text-brand-600 underline"><a href={`${api.defaults.baseURL?.replace('/api', '')}${showDetail.receipt_path}`} target="_blank" rel="noreferrer">Ver comprovante</a></p></div>}
                            {showDetail.rejection_reason && <div className="col-span-2"><span className="text-xs text-surface-500">Motivo da rejeição</span><p className="text-sm text-red-600">{showDetail.rejection_reason}</p></div>}
                            {showDetail.notes && <div className="col-span-2"><span className="text-xs text-surface-500">Obs</span><p className="text-sm text-surface-600">{showDetail.notes}</p></div>}
                        </div>
                    </div>
                )}
            </Modal>

            <Modal open={rejectTarget !== null} onOpenChange={() => setRejectTarget(null)} title="Rejeitar Despesa">
                <form onSubmit={e => { e.preventDefault(); if (!rejectReason.trim()) return; statusMut.mutate({ id: rejectTarget!, status: EXPENSE_STATUS.REJECTED, rejection_reason: rejectReason.trim() }) }} className="space-y-4">
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Motivo da rejeição *</label>
                        <textarea value={rejectReason} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setRejectReason(e.target.value)} rows={3} required
                            className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                            placeholder="Informe o motivo da rejeição..." />
                    </div>
                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" type="button" onClick={() => setRejectTarget(null)}>Cancelar</Button>
                        <Button type="submit" className="bg-red-600 hover:bg-red-700" loading={statusMut.isPending} disabled={statusMut.isPending}>Rejeitar</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={deleteTarget !== null} onOpenChange={() => setDeleteTarget(null)} title="Confirmar Exclusão">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Tem certeza que deseja excluir esta despesa? Esta ação não pode ser desfeita.</p>
                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
                        <Button className="bg-red-600 hover:bg-red-700" loading={delMut.isPending} disabled={delMut.isPending} onClick={() => { delMut.mutate(deleteTarget!) }}>Excluir</Button>
                    </div>
                </div>
            </Modal>

            <Modal open={showBatchReject} onOpenChange={setShowBatchReject} title="Rejeitar em Lote">
                <form onSubmit={e => { e.preventDefault(); if (!batchRejectReason.trim()) return; batchMut.mutate({ expense_ids: Array.from(selectedIds), status: EXPENSE_STATUS.REJECTED, rejection_reason: batchRejectReason.trim() }) }} className="space-y-4">
                    <p className="text-sm text-surface-600">{selectedIds.size} despesa(s) selecionada(s) serão rejeitadas.</p>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Motivo da rejeição *</label>
                        <textarea value={batchRejectReason} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setBatchRejectReason(e.target.value)} rows={3} required
                            className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                            placeholder="Informe o motivo da rejeição..." />
                    </div>
                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" type="button" onClick={() => setShowBatchReject(false)}>Cancelar</Button>
                        <Button type="submit" className="bg-red-600 hover:bg-red-700" loading={batchMut.isPending} disabled={batchMut.isPending}>Rejeitar Selecionadas</Button>
                    </div>
                </form>
            </Modal>

            <ExpenseHistoryModal
                open={showHistory !== null}
                onClose={() => setShowHistory(null)}
                entries={historyEntries}
                statusConfig={statusConfig as Record<string, { label: string; variant: string }>}
            />
        </div>
    )
}
