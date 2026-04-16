import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
    Wallet, TrendingUp, TrendingDown, ArrowUpCircle, ArrowDownCircle,
    User, Plus, Minus,
} from 'lucide-react'
import api, { unwrapData } from '@/lib/api'
import { formatCurrency } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { Modal } from '@/components/ui/modal'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'

interface Technician {
    id: number
    name: string
}

interface ExpenseSummary {
    id: number
    description: string
}

interface WorkOrder {
    id: number
    number: string
    os_number?: string | null
}

interface Fund {
    id: number
    user_id: number
    technician?: Technician | null
    balance: string
    card_balance?: string
    status?: 'active' | 'suspended' | 'closed' | null
    credit_limit?: string | null
}

interface Transaction {
    id: number
    type: 'credit' | 'debit'
    payment_method?: 'cash' | 'corporate_card'
    amount: string
    balance_after: string
    description: string
    transaction_date: string
    work_order?: WorkOrder | null
    expense?: ExpenseSummary | null
    creator?: { name: string } | null
}

interface CashSummary {
    total_balance: string
    total_card_balance: string
    month_credits: string
    month_debits: string
    funds_count: number
}

interface CashDetail {
    fund: Fund
    transactions: {
        data: Transaction[]
        current_page: number
        last_page: number
    }
}

interface TechnicianOption {
    user_id: number
    name: string
}

interface FundRequest {
    id: number
    technician?: { name: string } | null
    amount: string
    reason: string
    payment_method?: 'cash' | 'corporate_card' | null
    created_at: string
}

interface CashMutationError {
    response?: {
        data?: {
            message?: string
        }
    }
}

interface CashFormState {
    user_id: string
    amount: string
    description: string
    payment_method: 'cash' | 'corporate_card'
    work_order_id?: string
    bank_account_id?: string
}

interface CreditPayload {
    user_id: number
    amount: number
    description: string
    payment_method: 'cash' | 'corporate_card'
    work_order_id?: number
    bank_account_id: number
}

interface DebitPayload {
    user_id: number
    amount: number
    description: string
    payment_method: 'cash' | 'corporate_card'
    work_order_id?: number
}

const workOrderLabel = (workOrder?: WorkOrder | null) =>
    workOrder?.os_number ?? workOrder?.number ?? '-'

export function TechnicianCashPage() {
    const queryClient = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const canManageCash = hasRole('super_admin') || hasPermission('technicians.cashbox.manage')
    const [selectedTech, setSelectedTech] = useState<number | null>(null)
    const [showCreditModal, setShowCreditModal] = useState(false)
    const [showDebitModal, setShowDebitModal] = useState(false)
    const [showApproveModal, setShowApproveModal] = useState(false)
    const [approveData, setApproveData] = useState<{ id: number; bank_account_id: string; payment_method: 'cash' | 'corporate_card' } | null>(null)
    const [page, setPage] = useState(1)
    const [filters, setFilters] = useState({ date_from: '', date_to: '' })
    const [txForm, setTxForm] = useState<CashFormState>({
        user_id: '',
        amount: '',
        description: '',
        payment_method: 'cash',
        bank_account_id: '',
        work_order_id: '',
    })

    const summaryQuery = useQuery({
        queryKey: ['tech-cash-summary'],
        queryFn: async () => unwrapData<CashSummary>(await api.get('/technician-cash-summary')),
    })

    const fundsQuery = useQuery({
        queryKey: ['tech-cash-funds'],
        queryFn: async () => unwrapData<Fund[]>(await api.get('/technician-cash', { params: { per_page: 100 } })),
    })

    const detailQuery = useQuery({
        queryKey: ['tech-cash-detail', selectedTech, page, filters],
        queryFn: async () => unwrapData<CashDetail>(await api.get(`/technician-cash/${selectedTech}`, {
            params: { page, ...filters },
        })),
        enabled: selectedTech !== null,
    })

    const techniciansQuery = useQuery({
        queryKey: ['technicians-cash-options'],
        queryFn: async () => unwrapData<Technician[]>(await api.get('/technicians/options')),
    })

    const requestsQuery = useQuery({
        queryKey: ['tech-fund-requests'],
        queryFn: async () => unwrapData<FundRequest[]>(await api.get('/technician-fund-requests', { params: { status: 'pending' } })),
        enabled: canManageCash,
    })

    const invalidateCashQueries = async () => {
        await Promise.all([
            queryClient.invalidateQueries({ queryKey: ['tech-cash-funds'] }),
            queryClient.invalidateQueries({ queryKey: ['tech-cash-summary'] }),
            queryClient.invalidateQueries({ queryKey: ['tech-cash-detail'] }),
            queryClient.invalidateQueries({ queryKey: ['tech-fund-requests'] }),
        ])
    }

    const creditMutation = useMutation({
        mutationFn: (payload: CreditPayload) => api.post('/technician-cash/credit', payload),
        onSuccess: async () => {
            await invalidateCashQueries()
            setShowCreditModal(false)
            toast.success('Crédito adicionado com sucesso.')
        },
        onError: (error: CashMutationError) => {
            toast.error(error?.response?.data?.message ?? 'Erro ao adicionar crédito')
        },
    })

    const debitMutation = useMutation({
        mutationFn: (payload: DebitPayload) => api.post('/technician-cash/debit', payload),
        onSuccess: async () => {
            await invalidateCashQueries()
            setShowDebitModal(false)
            toast.success('Débito registrado com sucesso.')
        },
        onError: (error: CashMutationError) => {
            toast.error(error?.response?.data?.message ?? 'Erro ao lançar débito')
        },
    })

    const updateRequestStatusMutation = useMutation({
        mutationFn: (data: { id: number, status: 'approved' | 'rejected', payment_method?: string, bank_account_id?: number }) =>
            api.put(`/technician-fund-requests/${data.id}/status`, {
                status: data.status,
                payment_method: data.payment_method,
                bank_account_id: data.bank_account_id
            }),
        onSuccess: async () => {
            toast.success('Status da solicitação atualizado com sucesso')
            setShowApproveModal(false)
            setApproveData(null)
            await invalidateCashQueries()
        },
        onError: (error: CashMutationError) => {
            toast.error(error?.response?.data?.message ?? 'Erro ao atualizar solicitação')
        }
    })

    const technicianOptions: TechnicianOption[] = useMemo(
        () => (techniciansQuery.data ?? []).map((technician) => ({
            user_id: technician.id,
            name: technician.name,
        })),
        [techniciansQuery.data]
    )

    const openCreditModal = (userId?: number) => {
        if (!canManageCash) return
        setTxForm({
            user_id: userId ? String(userId) : '',
            amount: '',
            description: '',
            payment_method: 'cash',
            bank_account_id: '',
            work_order_id: '',
        })
        setShowCreditModal(true)
    }

    const openDebitModal = (userId?: number) => {
        if (!canManageCash) return
        setTxForm({
            user_id: userId ? String(userId) : '',
            amount: '',
            description: '',
            payment_method: 'cash',
            bank_account_id: '',
            work_order_id: '',
        })
        setShowDebitModal(true)
    }

    const buildCreditPayload = (): CreditPayload => ({
        user_id: Number(txForm.user_id),
        amount: Number(txForm.amount),
        description: txForm.description.trim(),
        payment_method: txForm.payment_method,
        work_order_id: txForm.work_order_id ? Number(txForm.work_order_id) : undefined,
        bank_account_id: Number(txForm.bank_account_id),
    })

    const buildDebitPayload = (): DebitPayload => ({
        user_id: Number(txForm.user_id),
        amount: Number(txForm.amount),
        description: txForm.description.trim(),
        payment_method: txForm.payment_method,
        work_order_id: txForm.work_order_id ? Number(txForm.work_order_id) : undefined,
    })

    const summary = summaryQuery.data
    const funds = fundsQuery.data ?? []
    const detail = detailQuery.data
    const transactions = detail?.transactions?.data ?? []

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="tracking-tight text-lg font-semibold text-surface-900">Caixa do Técnico</h1>
                    <p className="text-sm text-surface-500">Controle de verba rotativa por técnico</p>
                </div>
                {canManageCash && (
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => openCreditModal()} icon={<Plus className="h-4 w-4" />}>Crédito</Button>
                        <Button variant="outline" onClick={() => openDebitModal()} icon={<Minus className="h-4 w-4" />}>Débito</Button>
                    </div>
                )}
            </div>

            <Tabs defaultValue="extract" className="space-y-5">
                {canManageCash && (
                    <TabsList>
                        <TabsTrigger value="extract">Saldos e Extratos</TabsTrigger>
                        <TabsTrigger value="requests">
                            Solicitações de Verba
                            {((requestsQuery.data?.length) ?? 0) > 0 && (
                                <span className="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-600">
                                    {requestsQuery.data?.length}
                                </span>
                            )}
                        </TabsTrigger>
                    </TabsList>
                )}

                <TabsContent value="extract" className="space-y-5">

            {summary && (
                <div className="grid gap-4 sm:grid-cols-4">
                    <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg p-2.5 text-brand-600 bg-brand-50"><Wallet className="h-5 w-5" /></div>
                            <div>
                                <p className="text-xs text-surface-500">Saldo Total Dinheiro</p>
                                <p className="text-sm font-semibold text-surface-900 tabular-nums">{formatCurrency(summary.total_balance)}</p>
                                {Number(summary.total_card_balance) !== 0 && (
                                    <p className="mt-0.5 text-[0.65rem] font-bold text-teal-600 uppercase tabular-nums">Cartão: {formatCurrency(summary.total_card_balance)}</p>
                                )}
                            </div>
                        </div>
                    </div>
                    {[
                        { label: 'Créditos (Mês)', value: formatCurrency(summary.month_credits), icon: TrendingUp, color: 'text-emerald-600 bg-emerald-50' },
                        { label: 'Débitos (Mês)', value: formatCurrency(summary.month_debits), icon: TrendingDown, color: 'text-red-600 bg-red-50' },
                        { label: 'Técnicos', value: summary.funds_count, icon: User, color: 'text-sky-600 bg-sky-50' },
                    ].map((item) => (
                        <div key={item.label} className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                            <div className="flex items-center gap-3">
                                <div className={`rounded-lg p-2.5 ${item.color}`}><item.icon className="h-5 w-5" /></div>
                                <div>
                                    <p className="text-xs text-surface-500">{item.label}</p>
                                    <p className="text-sm font-semibold text-surface-900 tabular-nums">{item.value}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                    <div className="border-b border-subtle px-5 py-3">
                        <h3 className="text-sm font-semibold text-surface-900">Saldos por Técnico</h3>
                    </div>
                    <div className="divide-y divide-subtle">
                        {fundsQuery.isLoading ? (
                            <p className="py-8 text-center text-sm text-surface-400">Carregando...</p>
                        ) : funds.length === 0 ? (
                            <p className="py-8 text-center text-sm text-surface-400">Nenhum fundo cadastrado</p>
                        ) : (
                            funds.map((fund) => (
                                <button
                                    key={fund.id}
                                    onClick={() => { setSelectedTech(fund.user_id); setPage(1) }}
                                    className={`flex w-full items-center justify-between px-5 py-3 text-left transition-colors hover:bg-surface-50 ${selectedTech === fund.user_id ? 'bg-brand-50/50' : ''}`}
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-surface-100">
                                            <User className="h-4 w-4 text-surface-500" />
                                        </div>
                                        <span className="text-sm font-medium text-surface-800">{fund.technician?.name ?? 'Sem nome'}</span>
                                    </div>
                                    <div className="shrink-0 text-right">
                                        <p className={`text-sm font-bold ${Number(fund.balance) >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                            {formatCurrency(fund.balance)}
                                        </p>
                                        {fund.card_balance && Number(fund.card_balance) !== 0 && (
                                            <p className="text-xs text-surface-400">Cartão: {formatCurrency(fund.card_balance)}</p>
                                        )}
                                        {fund.credit_limit && (
                                            <p className="text-[0.6rem] text-surface-400">Limite: {formatCurrency(fund.credit_limit)}</p>
                                        )}
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </div>

                <div className="flex flex-col rounded-xl border border-default bg-surface-0 shadow-card lg:col-span-2">
                    <div className="flex items-center justify-between border-b border-subtle px-5 py-3">
                        <h3 className="text-sm font-semibold text-surface-900">
                            {selectedTech ? `Extrato - ${detail?.fund?.technician?.name ?? ''}` : 'Selecione um técnico'}
                        </h3>
                        {selectedTech && detail?.fund && (
                            <div className="flex gap-2">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="date"
                                        aria-label="Filtrar data inicial"
                                        className="h-7 rounded-md border border-default bg-surface-50 px-2 text-xs text-surface-700 focus:border-brand-500 focus:outline-none"
                                        value={filters.date_from}
                                        onChange={(event) => { setFilters((current) => ({ ...current, date_from: event.target.value })); setPage(1) }}
                                    />
                                    <span className="text-xs text-surface-400">até</span>
                                    <input
                                        type="date"
                                        aria-label="Filtrar data final"
                                        className="h-7 rounded-md border border-default bg-surface-50 px-2 text-xs text-surface-700 focus:border-brand-500 focus:outline-none"
                                        value={filters.date_to}
                                        onChange={(event) => { setFilters((current) => ({ ...current, date_to: event.target.value })); setPage(1) }}
                                    />
                                </div>
                                {canManageCash && (
                                    <>
                                        <div className="mx-1 h-4 w-px bg-default" />
                                        <div className="flex gap-1.5">
                                            <Button variant="ghost" size="sm" onClick={() => openCreditModal(selectedTech)} icon={<ArrowUpCircle className="h-3.5 w-3.5 text-emerald-600" />}>Crédito</Button>
                                            <Button variant="ghost" size="sm" onClick={() => openDebitModal(selectedTech)} icon={<ArrowDownCircle className="h-3.5 w-3.5 text-red-600" />}>Débito</Button>
                                        </div>
                                    </>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="flex-1 divide-y divide-subtle">
                        {!selectedTech ? (
                            <div className="py-16 text-center">
                                <Wallet className="mx-auto h-10 w-10 text-surface-300" />
                                <p className="mt-2 text-sm text-surface-400">Clique em um técnico para ver o extrato</p>
                            </div>
                        ) : detailQuery.isLoading ? (
                            <p className="py-8 text-center text-sm text-surface-400">Carregando...</p>
                        ) : detailQuery.isError ? (
                            <div className="py-8 text-center">
                                <p className="text-sm text-surface-400">Não foi possível carregar o extrato.</p>
                                <Button variant="outline" size="sm" className="mt-3" onClick={() => void queryClient.invalidateQueries({ queryKey: ['tech-cash-detail'] })}>
                                    Tentar novamente
                                </Button>
                            </div>
                        ) : transactions.length === 0 ? (
                            <p className="py-8 text-center text-sm text-surface-400">Nenhuma movimentação no período</p>
                        ) : (
                            transactions.map((transaction) => (
                                <div key={transaction.id} className="flex items-center gap-3 px-5 py-3">
                                    <div className={`rounded-full p-1.5 ${transaction.type === 'credit' ? 'bg-emerald-50' : 'bg-red-50'}`}>
                                        {transaction.type === 'credit'
                                            ? <ArrowUpCircle className="h-4 w-4 text-emerald-600" />
                                            : <ArrowDownCircle className="h-4 w-4 text-red-600" />}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-surface-800">{transaction.description}</p>
                                        <div className="flex items-center gap-2 text-xs text-surface-400">
                                            <span>{new Date(transaction.transaction_date).toLocaleDateString('pt-BR')}</span>
                                            {transaction.payment_method === 'corporate_card' && (
                                                <span className="rounded bg-teal-50 px-1 py-0.5 text-xs font-bold text-teal-600">CARTÃO</span>
                                            )}
                                            {transaction.work_order && <span>· OS {workOrderLabel(transaction.work_order)}</span>}
                                            {transaction.expense && <span>· Desp. {transaction.expense.description}</span>}
                                            {transaction.creator && <span>· {transaction.creator.name}</span>}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className={`text-sm font-bold ${transaction.type === 'credit' ? 'text-emerald-600' : 'text-red-600'}`}>
                                            {transaction.type === 'credit' ? '+' : '-'}{formatCurrency(transaction.amount)}
                                        </p>
                                        <p className="text-xs text-surface-400">Saldo: {formatCurrency(transaction.balance_after)}</p>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {selectedTech && detail?.transactions && detail.transactions.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-subtle px-5 py-3">
                            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>
                                Anterior
                            </Button>
                            <span className="text-xs text-surface-500">
                                Página {detail.transactions.current_page} de {detail.transactions.last_page}
                            </span>
                            <Button variant="outline" size="sm" disabled={page >= detail.transactions.last_page} onClick={() => setPage((current) => current + 1)}>
                                Próxima
                            </Button>
                        </div>
                    )}
                </div>
            </div>
            </TabsContent>

            {canManageCash && (
                <TabsContent value="requests" className="space-y-5">
                    <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                        <div className="border-b border-subtle px-5 py-3">
                            <h3 className="text-sm font-semibold text-surface-900">Solicitações Pendentes</h3>
                        </div>
                        <div className="divide-y divide-subtle">
                            {requestsQuery.isLoading ? (
                                <p className="py-8 text-center text-sm text-surface-400">Carregando...</p>
                            ) : !requestsQuery.data?.length ? (
                                <p className="py-8 text-center text-sm text-surface-400">Nenhuma solicitação pendente</p>
                            ) : (
                                requestsQuery.data.map((req) => (
                                    <div key={req.id} className="flex items-center justify-between px-5 py-4">
                                        <div>
                                            <p className="text-sm font-medium text-surface-900">{req.technician?.name}</p>
                                            <p className="mt-1 text-xs text-surface-500">Motivo: {req.reason}</p>
                                            <p className="mt-1 text-xs text-surface-400">{new Date(req.created_at).toLocaleString('pt-BR')}</p>
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <span className="text-lg font-bold text-surface-900">{formatCurrency(req.amount)}</span>
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                                    onClick={() => {
                                                        if (confirm('Rejeitar esta solicitação?')) {
                                                            updateRequestStatusMutation.mutate({ id: req.id, status: 'rejected' })
                                                        }
                                                    }}
                                                    loading={updateRequestStatusMutation.isPending}
                                                >
                                                    Rejeitar
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    className="bg-emerald-600 text-white hover:bg-emerald-700"
                                                    onClick={() => {
                                                        setApproveData({
                                                            id: req.id,
                                                            bank_account_id: '',
                                                            payment_method: req.payment_method ?? 'cash',
                                                        })
                                                        setShowApproveModal(true)
                                                    }}
                                                    loading={updateRequestStatusMutation.isPending && approveData?.id === req.id}
                                                >
                                                    Aprovar
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </TabsContent>
            )}
            </Tabs>

            <Modal open={showCreditModal && canManageCash} onOpenChange={setShowCreditModal} title="Adicionar Crédito" size="sm">
                <form onSubmit={(event) => { event.preventDefault(); creditMutation.mutate(buildCreditPayload()) }} className="space-y-4">
                    {!txForm.user_id && (
                        <div>
                            <label htmlFor="tech-cash-credit-user" className="mb-1.5 block text-sm font-medium text-surface-700">Técnico</label>
                            <select
                                id="tech-cash-credit-user"
                                value={txForm.user_id}
                                onChange={(event) => setTxForm((current) => ({ ...current, user_id: event.target.value }))}
                                required
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                            >
                                <option value="">- Selecionar -</option>
                                {technicianOptions.map((technician) => <option key={technician.user_id} value={technician.user_id}>{technician.name}</option>)}
                            </select>
                        </div>
                    )}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Meio de Pagamento</label>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setTxForm((current) => ({ ...current, payment_method: 'cash' }))}
                                className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${txForm.payment_method === 'cash' ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-default bg-surface-50 text-surface-500'}`}
                            >
                                Dinheiro
                            </button>
                            <button
                                type="button"
                                onClick={() => setTxForm((current) => ({ ...current, payment_method: 'corporate_card' }))}
                                className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${txForm.payment_method === 'corporate_card' ? 'border-teal-400 bg-teal-50 text-teal-700' : 'border-default bg-surface-50 text-surface-500'}`}
                            >
                                Cartão Corporativo
                            </button>
                        </div>
                    </div>
                    <div>
                        <LookupCombobox
                            lookupType="bank-accounts"
                            endpoint="/financial/lookups/bank-accounts"
                            label="Conta Bancária de Origem *"
                            value={txForm.bank_account_id || ''}
                            onChange={(val) => setTxForm((current) => ({ ...current, bank_account_id: val }))}
                            placeholder="Buscar conta..."
                            allowCreate={false}
                        />
                    </div>
                    <div>
                        <LookupCombobox
                            lookupType="work-orders"
                            endpoint="/financial/lookups/work-orders"
                            label="Vincular OS (Opcional)"
                            value={txForm.work_order_id || ''}
                            onChange={(val) => setTxForm((current) => ({ ...current, work_order_id: val }))}
                            placeholder="Buscar por número da OS..."
                            allowCreate={false}
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Valor (R$)</label>
                        <CurrencyInput value={Number(txForm.amount) || 0} onChange={(value) => setTxForm((current) => ({ ...current, amount: String(value) }))} />
                    </div>
                    <Input label="Descrição" value={txForm.description} required onChange={(event) => setTxForm((current) => ({ ...current, description: event.target.value }))} />
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" type="button" onClick={() => setShowCreditModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={creditMutation.isPending} disabled={!txForm.user_id || !txForm.bank_account_id || Number(txForm.amount) <= 0 || txForm.description.trim() === ''}>
                            Confirmar Crédito
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={showDebitModal && canManageCash} onOpenChange={setShowDebitModal} title="Lançar Débito" size="sm">
                <form onSubmit={(event) => { event.preventDefault(); debitMutation.mutate(buildDebitPayload()) }} className="space-y-4">
                    {!txForm.user_id && (
                        <div>
                            <label htmlFor="tech-cash-debit-user" className="mb-1.5 block text-sm font-medium text-surface-700">Técnico</label>
                            <select
                                id="tech-cash-debit-user"
                                value={txForm.user_id}
                                onChange={(event) => setTxForm((current) => ({ ...current, user_id: event.target.value }))}
                                required
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                            >
                                <option value="">- Selecionar -</option>
                                {technicianOptions.map((technician) => <option key={technician.user_id} value={technician.user_id}>{technician.name}</option>)}
                            </select>
                        </div>
                    )}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Meio de Pagamento</label>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setTxForm((current) => ({ ...current, payment_method: 'cash' }))}
                                className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${txForm.payment_method === 'cash' ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-default bg-surface-50 text-surface-500'}`}
                            >
                                Dinheiro
                            </button>
                            <button
                                type="button"
                                onClick={() => setTxForm((current) => ({ ...current, payment_method: 'corporate_card' }))}
                                className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${txForm.payment_method === 'corporate_card' ? 'border-teal-400 bg-teal-50 text-teal-700' : 'border-default bg-surface-50 text-surface-500'}`}
                            >
                                Cartão Corporativo
                            </button>
                        </div>
                    </div>
                    <div>
                        <LookupCombobox
                            lookupType="work-orders"
                            endpoint="/financial/lookups/work-orders"
                            label="Vincular OS (Opcional)"
                            value={txForm.work_order_id || ''}
                            onChange={(val) => setTxForm((current) => ({ ...current, work_order_id: val }))}
                            placeholder="Buscar por número da OS..."
                            allowCreate={false}
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Valor (R$)</label>
                        <CurrencyInput value={Number(txForm.amount) || 0} onChange={(value) => setTxForm((current) => ({ ...current, amount: String(value) }))} />
                    </div>
                    <Input label="Descrição" value={txForm.description} required onChange={(event) => setTxForm((current) => ({ ...current, description: event.target.value }))} />
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" type="button" onClick={() => setShowDebitModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={debitMutation.isPending} className="bg-red-600 hover:bg-red-700" disabled={!txForm.user_id || Number(txForm.amount) <= 0 || txForm.description.trim() === ''}>
                            Confirmar Débito
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={showApproveModal && !!approveData} onOpenChange={setShowApproveModal} title="Aprovar Solicitação de Verba" size="sm">
                <form onSubmit={(event) => {
                    event.preventDefault()
                    if (approveData?.bank_account_id && approveData.payment_method) {
                        updateRequestStatusMutation.mutate({
                            id: approveData.id,
                            status: 'approved',
                            bank_account_id: Number(approveData.bank_account_id),
                            payment_method: approveData.payment_method,
                        })
                    }
                }} className="space-y-4">
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Meio de Pagamento</label>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setApproveData(curr => curr ? { ...curr, payment_method: 'cash' } : null)}
                                className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${approveData?.payment_method === 'cash' ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-default bg-surface-50 text-surface-500'}`}
                            >
                                Dinheiro
                            </button>
                            <button
                                type="button"
                                onClick={() => setApproveData(curr => curr ? { ...curr, payment_method: 'corporate_card' } : null)}
                                className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${approveData?.payment_method === 'corporate_card' ? 'border-teal-400 bg-teal-50 text-teal-700' : 'border-default bg-surface-50 text-surface-500'}`}
                            >
                                Cartão Corporativo
                            </button>
                        </div>
                    </div>
                    <div>
                        <LookupCombobox
                            lookupType="bank-accounts"
                            endpoint="/financial/lookups/bank-accounts"
                            label="Conta Bancária de Origem *"
                            value={approveData?.bank_account_id ?? ''}
                            onChange={(val) => setApproveData(curr => curr ? { ...curr, bank_account_id: val } : null)}
                            placeholder="Buscar conta..."
                            allowCreate={false}
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" type="button" onClick={() => setShowApproveModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={updateRequestStatusMutation.isPending} className="bg-emerald-600 hover:bg-emerald-700" disabled={!approveData?.bank_account_id}>
                            Confirmar Aprovação
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    )
}
