import { useEffect, useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
    ArrowLeft, Wallet, ArrowUpCircle, ArrowDownCircle, Loader2,
    Send, Clock, CheckCircle2, XCircle, DollarSign,
} from 'lucide-react'
import { cn, formatCurrency, getApiErrorMessage } from '@/lib/utils'
import api, { unwrapData } from '@/lib/api'
import { toast } from 'sonner'
import { CurrencyInputInline } from '@/components/common/CurrencyInput'
import { usePullToRefresh } from '@/hooks/usePullToRefresh'
import { useAuthStore } from '@/stores/auth-store'

interface CashFund {
    id: number
    balance: number | string
    card_balance?: number | string
}

interface CashTransaction {
    id: number
    type: 'credit' | 'debit'
    payment_method?: 'cash' | 'corporate_card'
    amount: number | string
    description: string | null
    transaction_date: string
    balance_after?: number | string
}

interface FundRequest {
    id: number
    amount: number | string
    reason: string | null
    status: 'pending' | 'approved' | 'rejected'
    created_at: string
    approved_at: string | null
}

export default function TechCashPage() {
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const [showRequestForm, setShowRequestForm] = useState(false)
    const [requestAmount, setRequestAmount] = useState('')
    const [requestReason, setRequestReason] = useState('')
    const [requestPaymentMethod, setRequestPaymentMethod] = useState<'cash' | 'corporate_card'>('cash')
    const [activeTab, setActiveTab] = useState<'transactions' | 'requests'>('transactions')

    const canRequestFunds = hasRole('super_admin') || hasPermission('technicians.cashbox.request_funds') || hasPermission('technicians.cashbox.manage')

    const fundQuery = useQuery({
        queryKey: ['tech-cash', 'fund'],
        queryFn: async () => unwrapData<CashFund>(await api.get('/technician-cash/my-fund')),
    })

    const transactionsQuery = useQuery({
        queryKey: ['tech-cash', 'transactions'],
        queryFn: async () => unwrapData<CashTransaction[]>(await api.get('/technician-cash/my-transactions', { params: { per_page: 50 } })),
    })

    const requestsQuery = useQuery({
        queryKey: ['tech-cash', 'requests'],
        queryFn: async () => unwrapData<FundRequest[]>(await api.get('/technician-cash/my-requests')),
    })

    const requestFundsMutation = useMutation({
        mutationFn: async () => {
            await api.post('/technician-cash/request-funds', {
                amount: Number(requestAmount),
                reason: requestReason.trim() || null,
                payment_method: requestPaymentMethod,
            })
        },
        onSuccess: async () => {
            toast.success('Solicitacao de fundos enviada.')
            setShowRequestForm(false)
            setRequestAmount('')
            setRequestReason('')
            await queryClient.invalidateQueries({ queryKey: ['tech-cash'] })
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao solicitar fundos'))
        },
    })

    const refreshData = async () => {
        await Promise.all([
            queryClient.invalidateQueries({ queryKey: ['tech-cash', 'fund'] }),
            queryClient.invalidateQueries({ queryKey: ['tech-cash', 'transactions'] }),
            queryClient.invalidateQueries({ queryKey: ['tech-cash', 'requests'] }),
        ])
    }

    const { containerRef, isRefreshing, pullDistance } = usePullToRefresh({
        onRefresh: refreshData,
    })

    const fund = fundQuery.data ?? null
    const transactions = transactionsQuery.data ?? []
    const requests = requestsQuery.data ?? []

    const recentResolvedRequests = useMemo(() => {
        const threeDaysAgo = new Date()
        threeDaysAgo.setDate(threeDaysAgo.getDate() - 3)
        return requests
            .filter(req => req.status !== 'pending')
            .filter(req => {
                const updated = req.approved_at ? new Date(req.approved_at) : new Date(req.created_at)
                return updated >= threeDaysAgo
            })
            .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
    }, [requests])

    useEffect(() => {
        if (transactionsQuery.isError) {
            toast.error('Nao foi possivel carregar as movimentacoes do caixa.')
        }
    }, [transactionsQuery.isError])

    useEffect(() => {
        if (requestsQuery.isError) {
            toast.error('Nao foi possivel carregar as solicitacoes de fundos.')
        }
    }, [requestsQuery.isError])

    const txTypeConfig: Record<CashTransaction['type'], { label: string; icon: typeof ArrowUpCircle; color: string }> = {
        credit: { label: 'Credito', icon: ArrowUpCircle, color: 'text-emerald-600 dark:text-emerald-400' },
        debit: { label: 'Debito', icon: ArrowDownCircle, color: 'text-red-600 dark:text-red-400' },
    }

    const reqStatusConfig: Record<FundRequest['status'], { label: string; icon: typeof Clock; color: string }> = {
        pending: { label: 'Pendente', icon: Clock, color: 'text-amber-600 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400' },
        approved: { label: 'Aprovada', icon: CheckCircle2, color: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' },
        rejected: { label: 'Rejeitada', icon: XCircle, color: 'text-red-600 bg-red-100 dark:bg-red-900/30 dark:text-red-400' },
    }

    if (fundQuery.isLoading) {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-3">
                <Loader2 className="h-8 w-8 animate-spin text-brand-500" />
                <p className="text-sm text-surface-500">Carregando caixa...</p>
            </div>
        )
    }

    if (fundQuery.isError) {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-3 px-6 text-center">
                <Wallet className="h-10 w-10 text-surface-300" />
                <p className="text-sm text-surface-500">Nao foi possivel carregar o caixa tecnico.</p>
                <button
                    onClick={() => void refreshData()}
                    className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white"
                >
                    Tentar novamente
                </button>
            </div>
        )
    }

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-border bg-card px-4 pb-4 pt-3">
                <button onClick={() => navigate('/tech')} className="mb-2 flex items-center gap-1 text-sm text-brand-600">
                    <ArrowLeft className="h-4 w-4" /> Voltar
                </button>
                <h1 className="text-lg font-bold text-foreground">Meu Caixa</h1>
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
                {recentResolvedRequests.length > 0 && (
                    <div className={cn(
                        "rounded-xl p-3 flex items-start gap-3 border",
                        recentResolvedRequests[0].status === 'approved'
                            ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800/50 dark:text-emerald-400'
                            : 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800/50 dark:text-red-400'
                    )}>
                        {recentResolvedRequests[0].status === 'approved' ? (
                            <CheckCircle2 className="w-5 h-5 mt-0.5 shrink-0" />
                        ) : (
                            <XCircle className="w-5 h-5 mt-0.5 shrink-0" />
                        )}
                        <div>
                            <p className="text-sm font-semibold">
                                Solicitação {recentResolvedRequests[0].status === 'approved' ? 'Aprovada' : 'Rejeitada'}
                            </p>
                            <p className="text-xs mt-0.5 opacity-90 leading-relaxed">
                                Seu pedido de fundos de <strong>{formatCurrency(Number(recentResolvedRequests[0].amount))}</strong> foi {recentResolvedRequests[0].status === 'approved' ? 'aprovado' : 'rejeitado'}.
                            </p>
                        </div>
                    </div>
                )}

                <div className="rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 p-5 text-white">
                    <div className="mb-1 flex items-center gap-2">
                        <Wallet className="h-5 w-5 opacity-80" />
                        <span className="text-sm font-medium opacity-80">Saldo Atual</span>
                    </div>
                    <p className="mt-1 text-3xl font-bold">{formatCurrency(Number(fund?.balance ?? 0))}</p>
                    {Number(fund?.card_balance ?? 0) > 0 && (
                        <p className="mt-2 text-xs opacity-80">
                            Cartao corporativo: {formatCurrency(Number(fund?.card_balance ?? 0))}
                        </p>
                    )}
                    <button
                        onClick={() => { if (canRequestFunds) setShowRequestForm(true) }}
                        disabled={!canRequestFunds}
                        className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-white/20 py-2.5 text-sm font-medium backdrop-blur-sm transition-colors active:bg-white/30 disabled:opacity-50"
                    >
                        <Send className="h-4 w-4" /> Solicitar Fundos
                    </button>
                </div>

                {showRequestForm && canRequestFunds && (
                    <div className="space-y-3 rounded-xl bg-card p-4">
                        <h3 className="text-sm font-semibold text-foreground">Solicitar Fundos</h3>
                        <div>
                            <label htmlFor="tech-cash-request-amount" className="mb-1 block text-xs font-medium text-surface-500">Valor (R$) *</label>
                            <CurrencyInputInline
                                id="tech-cash-request-amount"
                                value={Number(requestAmount) || 0}
                                onChange={(value) => setRequestAmount(String(value))}
                                className="w-full rounded-lg bg-surface-100 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                            />
                        </div>
                        <div>
                            <label htmlFor="tech-cash-request-reason" className="mb-1 block text-xs font-medium text-surface-500">Motivo</label>
                            <textarea
                                id="tech-cash-request-reason"
                                value={requestReason}
                                onChange={(event) => setRequestReason(event.target.value)}
                                placeholder="Ex: preciso de verba para despesas em campo."
                                rows={2}
                                className="w-full resize-none rounded-lg bg-surface-100 px-3 py-2.5 text-sm placeholder:text-surface-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                            />
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={() => setShowRequestForm(false)}
                                className="flex-1 rounded-xl bg-surface-100 py-2.5 text-sm font-medium text-surface-600"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={() => requestFundsMutation.mutate()}
                                disabled={requestFundsMutation.isPending || Number(requestAmount) <= 0}
                                className={cn(
                                    'flex flex-1 items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-semibold text-white transition-colors',
                                    Number(requestAmount) > 0 ? 'bg-brand-600 active:bg-brand-700' : 'bg-surface-300'
                                )}
                            >
                                {requestFundsMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                Enviar
                            </button>
                        </div>
                    </div>
                )}

                <button
                    onClick={() => navigate('/tech/comissoes')}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-surface-100 py-2.5 text-xs font-medium text-surface-600 transition-all active:scale-[0.98]"
                >
                    <DollarSign className="h-4 w-4" /> Ver Minhas Comissoes
                </button>

                <div className="flex gap-2">
                    <button
                        onClick={() => setActiveTab('transactions')}
                        className={cn(
                            'flex-1 rounded-lg py-2 text-xs font-medium transition-colors',
                            activeTab === 'transactions' ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                        )}
                    >
                        Movimentacoes
                    </button>
                    <button
                        onClick={() => setActiveTab('requests')}
                        className={cn(
                            'relative flex-1 rounded-lg py-2 text-xs font-medium transition-colors',
                            activeTab === 'requests' ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                        )}
                    >
                        Solicitacoes
                        {requests.filter((item) => item.status === 'pending').length > 0 && (
                            <span className="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">
                                {requests.filter((item) => item.status === 'pending').length}
                            </span>
                        )}
                    </button>
                </div>

                {activeTab === 'transactions' && (
                    <div className="space-y-2">
                        {transactionsQuery.isError ? (
                            <div className="flex flex-col items-center justify-center gap-3 py-12">
                                <Wallet className="h-10 w-10 text-surface-300" />
                                <p className="text-sm text-surface-500">Falha ao carregar movimentacoes.</p>
                                <button onClick={() => void queryClient.invalidateQueries({ queryKey: ['tech-cash', 'transactions'] })} className="text-sm font-medium text-brand-600">
                                    Tentar novamente
                                </button>
                            </div>
                        ) : transactions.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 py-12">
                                <DollarSign className="h-10 w-10 text-surface-300" />
                                <p className="text-sm text-surface-500">Nenhuma movimentacao.</p>
                            </div>
                        ) : (
                            transactions.map((transaction) => {
                                const config = txTypeConfig[transaction.type]
                                const Icon = config.icon
                                const isPositive = transaction.type === 'credit'

                                return (
                                    <div key={transaction.id} className="flex items-center gap-3 rounded-xl bg-card p-3">
                                        <div className={cn(
                                            'flex h-9 w-9 items-center justify-center rounded-lg',
                                            isPositive ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-red-100 dark:bg-red-900/30'
                                        )}>
                                            <Icon className={cn('h-4 w-4', config.color)} />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-foreground">{config.label}</p>
                                            {transaction.description && (
                                                <p className="truncate text-xs text-surface-500">{transaction.description}</p>
                                            )}
                                            <p className="mt-0.5 text-[10px] text-surface-400">
                                                {new Date(transaction.transaction_date).toLocaleDateString('pt-BR')}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className={cn(
                                                'text-sm font-bold',
                                                isPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                                            )}>
                                                {isPositive ? '+' : '-'}{formatCurrency(Math.abs(Number(transaction.amount)))}
                                            </p>
                                            {transaction.balance_after !== undefined && (
                                                <p className="mt-0.5 text-[10px] text-surface-500 font-medium">
                                                    Saldo: {formatCurrency(Number(transaction.balance_after))}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )
                            })
                        )}
                    </div>
                )}

                {activeTab === 'requests' && (
                    <div className="space-y-2">
                        {requestsQuery.isError ? (
                            <div className="flex flex-col items-center justify-center gap-3 py-12">
                                <Send className="h-10 w-10 text-surface-300" />
                                <p className="text-sm text-surface-500">Falha ao carregar solicitacoes.</p>
                                <button onClick={() => void queryClient.invalidateQueries({ queryKey: ['tech-cash', 'requests'] })} className="text-sm font-medium text-brand-600">
                                    Tentar novamente
                                </button>
                            </div>
                        ) : requests.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 py-12">
                                <Send className="h-10 w-10 text-surface-300" />
                                <p className="text-sm text-surface-500">Nenhuma solicitacao.</p>
                            </div>
                        ) : (
                            requests.map((request) => {
                                const config = reqStatusConfig[request.status]
                                const Icon = config.icon

                                return (
                                    <div key={request.id} className="rounded-xl bg-card p-3">
                                        <div className="flex items-center gap-3">
                                            <div className={cn('flex h-9 w-9 items-center justify-center rounded-lg', config.color)}>
                                                <Icon className="h-4 w-4" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-bold text-foreground">{formatCurrency(Number(request.amount))}</p>
                                                {request.reason && <p className="truncate text-xs text-surface-500">{request.reason}</p>}
                                                <p className="mt-0.5 text-[10px] text-surface-400">
                                                    {new Date(request.created_at).toLocaleDateString('pt-BR')}
                                                </p>
                                            </div>
                                            <span className={cn('rounded-full px-2 py-0.5 text-[10px] font-medium', config.color)}>
                                                {config.label}
                                            </span>
                                        </div>
                                    </div>
                                )
                            })
                        )}
                    </div>
                )}
            </div>
        </div>
    )
}
