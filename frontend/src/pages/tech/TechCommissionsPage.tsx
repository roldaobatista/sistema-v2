import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, CheckCircle2, Clock, DollarSign, Download, Loader2, Plus } from 'lucide-react'
import { cn, formatCurrency } from '@/lib/utils'
import { getApiErrorMessage } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import type { CommissionEvent, CommissionSettlement, CommissionDispute, ApiError, TechCommissionSummary } from '@/pages/financeiro/commissions/types'
import { getCommissionDisputeStatusLabel, normalizeCommissionDisputeStatus } from '@/pages/financeiro/commissions/utils'

/** Type-safe API response unwrap: handles {data:{data:T}}, {data:T}, T */
function safeUnwrap<T>(res: unknown, fallback: T): T {
    const r = res as Record<string, unknown> | undefined
    const d = r?.data as Record<string, unknown> | T | undefined
    if (d && typeof d === 'object' && 'data' in d) return (d as Record<string, unknown>).data as T
    if (d !== undefined && d !== null) return d as T
    return (r as T) ?? fallback
}

const STATUS_BADGES: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30',
    approved: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-900/30',
    open: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30',
    accepted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30',
    resolved: 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-300',
}

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pendente',
    approved: 'Aprovado',
    paid: 'Pago',
    reversed: 'Estornado',
    cancelled: 'Cancelado',
    rejected: 'Rejeitado',
    open: 'Aberta',
    accepted: 'Aceita',
    resolved: 'Resolvida (legado)',
    closed: 'Fechado',
}

function readCache<T>(key: string, fallback: T): T {
    try {
        const raw = localStorage.getItem(key)
        if (!raw) return fallback
        const parsed = JSON.parse(raw) as { data?: T; timestamp?: number }
        if (!parsed.timestamp || Date.now() - parsed.timestamp > 30 * 60 * 1000) {
            return fallback
        }
        return parsed.data ?? fallback
    } catch {
        return fallback
    }
}

export default function TechCommissionsPage() {
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const user = useAuthStore(state => state.user)
    const [periodFilter, setPeriodFilter] = useState<'current' | 'previous' | 'all'>('current')
    const [activeTab, setActiveTab] = useState<'events' | 'settlements' | 'disputes'>('events')
    const [showDisputeForm, setShowDisputeForm] = useState(false)
    const [disputeReason, setDisputeReason] = useState('')
    const [disputeEventId, setDisputeEventId] = useState<number | null>(null)
    const [downloadingPeriod, setDownloadingPeriod] = useState<string | null>(null)

    const handleDownloadStatement = async (period: string) => {
        try {
            setDownloadingPeriod(period)
            const response = await financialApi.commissions.my.statementDownload({ period })

            const url = window.URL.createObjectURL(new Blob([response as BlobPart]))
            const link = document.createElement('a')
            link.href = url
            link.setAttribute('download', `holerite-comissao-${period}.pdf`)
            document.body.appendChild(link)
            link.click()
            link.parentNode?.removeChild(link)
        } catch (err: unknown) {
            toast.error('Erro ao baixar extrato em PDF')
        } finally {
            setDownloadingPeriod(null)
        }
    }

    const period = useMemo(() => {
        const now = new Date()
        if (periodFilter === 'current') return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
        if (periodFilter === 'previous') {
            const prev = new Date(now.getFullYear(), now.getMonth() - 1)
            return `${prev.getFullYear()}-${String(prev.getMonth() + 1).padStart(2, '0')}`
        }
        return ''
    }, [periodFilter])

    const cachePrefix = user?.id ?? 'guest'
    const cacheKey = (suffix: string) => `cache:commission-${suffix}-${cachePrefix}-${period || 'all'}`
    const disputesCacheKey = `cache:commission-disputes-${cachePrefix}`
    const summaryParams = period ? { period } : periodFilter === 'all' ? { all: 1 } : {}
    const summaryLabel = periodFilter === 'all' ? 'Total acumulado' : periodFilter === 'previous' ? 'Total do mes anterior' : 'Total do mes'
    const pendingLabel = periodFilter === 'all' ? 'Pendente total' : periodFilter === 'previous' ? 'Pendente no mes' : 'Pendente'
    const paidLabel = periodFilter === 'all' ? 'Pago total' : periodFilter === 'previous' ? 'Pago no mes' : 'Pago'

    const summaryQuery = useQuery<TechCommissionSummary>({
        queryKey: ['tech-commission-summary', periodFilter, period],
        queryFn: async () => {
            const r = await financialApi.commissions.my.summary(summaryParams)
            return safeUnwrap<TechCommissionSummary>(r, {} as TechCommissionSummary)
        },
        enabled: !!user,
        placeholderData: () => readCache<TechCommissionSummary>(cacheKey('summary'), {} as TechCommissionSummary),
    })

    const eventsQuery = useQuery<CommissionEvent[]>({
        queryKey: ['tech-commission-events', period],
        queryFn: async () => {
            const r = await financialApi.commissions.my.events(period ? { period } : {})
            return safeUnwrap<CommissionEvent[]>(r, [])
        },
        enabled: !!user,
        placeholderData: () => readCache<CommissionEvent[]>(cacheKey('events'), []),
    })

    const settlementsQuery = useQuery<CommissionSettlement[]>({
        queryKey: ['tech-commission-settlements', period],
        queryFn: async () => {
            const r = await financialApi.commissions.my.settlements(period ? { period } : {})
            return safeUnwrap<CommissionSettlement[]>(r, [])
        },
        enabled: !!user,
        placeholderData: () => readCache<CommissionSettlement[]>(cacheKey('settlements'), []),
    })

    const disputesQuery = useQuery<CommissionDispute[]>({
        queryKey: ['tech-commission-disputes'],
        queryFn: async () => {
            const r = await financialApi.commissions.my.disputes()
            return safeUnwrap<CommissionDispute[]>(r, [])
        },
        enabled: !!user,
        placeholderData: () => readCache<CommissionDispute[]>(disputesCacheKey, []),
    })

    const summary = summaryQuery.data ?? {}
    const events = eventsQuery.data ?? []
    const settlements = settlementsQuery.data ?? []
    const disputes = disputesQuery.data ?? []
    const loading = !user || summaryQuery.isLoading || eventsQuery.isLoading || settlementsQuery.isLoading || disputesQuery.isLoading

    useEffect(() => {
        localStorage.setItem(cacheKey('summary'), JSON.stringify({ data: summary, timestamp: Date.now() }))
    }, [summary, period])

    useEffect(() => {
        localStorage.setItem(cacheKey('events'), JSON.stringify({ data: events, timestamp: Date.now() }))
    }, [events, period])

    useEffect(() => {
        localStorage.setItem(cacheKey('settlements'), JSON.stringify({ data: settlements, timestamp: Date.now() }))
    }, [settlements, period])

    useEffect(() => {
        localStorage.setItem(disputesCacheKey, JSON.stringify({ data: disputes, timestamp: Date.now() }))
    }, [disputes, disputesCacheKey])

    useEffect(() => {
        const error = summaryQuery.error ?? eventsQuery.error ?? settlementsQuery.error ?? disputesQuery.error
        if (summaryQuery.isError || eventsQuery.isError || settlementsQuery.isError || disputesQuery.isError) {
            toast.error(getApiErrorMessage(error, 'Erro ao carregar comissoes'))
        }
    }, [
        disputesQuery.error,
        disputesQuery.isError,
        eventsQuery.error,
        eventsQuery.isError,
        settlementsQuery.error,
        settlementsQuery.isError,
        summaryQuery.error,
        summaryQuery.isError,
    ])

    const createDisputeMutation = useMutation({
        mutationFn: (payload: { commission_event_id: number; reason: string }) => financialApi.commissions.disputes.store(payload),
        onSuccess: async () => {
            toast.success('Contestacao registrada')
            setShowDisputeForm(false)
            setDisputeReason('')
            setDisputeEventId(null)
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['tech-commission-disputes'] }),
                queryClient.invalidateQueries({ queryKey: ['tech-commission-events'] }),
            ])
        },
        onError: (err: ApiError) => toast.error(getApiErrorMessage(err, 'Erro ao registrar contestacao')),
    })

    const handleCreateDispute = () => {
        if (!disputeEventId || !disputeReason.trim()) {
            toast.error('Selecione um evento e informe o motivo')
            return
        }
        if (disputeReason.trim().length < 10) {
            toast.error('O motivo deve ter pelo menos 10 caracteres')
            return
        }

        createDisputeMutation.mutate({
            commission_event_id: disputeEventId,
            reason: disputeReason.trim(),
        })
    }

    const disputableEvents = events.filter((event) => event.status === 'pending' || event.status === 'approved')

    return (
        <div className='flex flex-col h-full'>
            <div className='bg-card px-4 pt-3 pb-4 border-b border-border'>
                <button
                    onClick={() => navigate('/tech')}
                    className='flex items-center gap-1 text-sm text-brand-600 mb-2'
                >
                    <ArrowLeft className='w-4 h-4' /> Voltar
                </button>
                <h1 className='text-lg font-bold text-foreground'>
                    Comissoes
                </h1>
            </div>

            <div className='flex-1 overflow-y-auto px-4 py-4 space-y-4'>
                {loading ? (
                    <div className='flex justify-center py-12'>
                        <Loader2 className='w-8 h-8 animate-spin text-brand-500' />
                    </div>
                ) : (
                    <>
                        <div className='grid grid-cols-3 gap-3'>
                            <div className='bg-card rounded-xl p-3'>
                                <div className='flex items-center gap-1 mb-1'>
                                    <DollarSign className='w-4 h-4 text-brand-600' />
                                    <span className='text-xs text-surface-500'>{summaryLabel}</span>
                                </div>
                                <p className='text-lg font-bold text-foreground'>
                                    {formatCurrency(summary.total_month ?? 0)}
                                </p>
                            </div>
                            <div className='bg-card rounded-xl p-3'>
                                <div className='flex items-center gap-1 mb-1'>
                                    <Clock className='w-4 h-4 text-amber-500' />
                                    <span className='text-xs text-surface-500'>{pendingLabel}</span>
                                </div>
                                <p className='text-lg font-bold text-amber-600 dark:text-amber-400'>
                                    {formatCurrency(summary.pending ?? 0)}
                                </p>
                            </div>
                            <div className='bg-card rounded-xl p-3'>
                                <div className='flex items-center gap-1 mb-1'>
                                    <CheckCircle2 className='w-4 h-4 text-emerald-500' />
                                    <span className='text-xs text-surface-500'>{paidLabel}</span>
                                </div>
                                <p className='text-lg font-bold text-emerald-600 dark:text-emerald-400'>
                                    {formatCurrency(summary.paid ?? 0)}
                                </p>
                            </div>
                        </div>

                        {summary.goal && (
                            <div className='bg-card rounded-xl p-4 shadow-sm border border-border'>
                                <div className='flex justify-between items-center mb-2'>
                                    <p className='text-sm font-medium text-foreground'>
                                        Meta de {summary.goal.type === 'revenue' ? 'Faturamento' : summary.goal.type === 'os_count' ? 'OS' : 'Vendas'}
                                    </p>
                                    <span className={cn(
                                        'text-xs font-semibold',
                                        summary.goal.achievement_pct >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-brand-600 dark:text-brand-400'
                                    )}>
                                        {summary.goal.achievement_pct >= 100 && <CheckCircle2 className='w-3 h-3 inline mr-1' />}
                                        {Number(summary.goal.achievement_pct).toFixed(1)}%
                                    </span>
                                </div>
                                <div className='h-2 w-full bg-surface-200 dark:bg-surface-800 rounded-full overflow-hidden'>
                                    <div
                                        className={cn(
                                            'h-full rounded-full transition-all',
                                            summary.goal.achievement_pct >= 100 ? 'bg-emerald-500' : 'bg-brand-500'
                                        )}
                                        style={{ width: `${Math.min(100, summary.goal.achievement_pct)}%` }}
                                    />
                                </div>
                                <div className='flex justify-between items-center mt-2 text-xs text-surface-500'>
                                    <span>
                                        Alcançado: {summary.goal.type === 'revenue' ? formatCurrency(summary.goal.achieved_amount) : summary.goal.achieved_amount}
                                    </span>
                                    <span>
                                        Objetivo: {summary.goal.type === 'revenue' ? formatCurrency(summary.goal.target_amount) : summary.goal.target_amount}
                                    </span>
                                </div>
                            </div>
                        )}

                        {settlements.length > 0 && (() => {
                            const totalEarned = settlements.reduce((sum, settlement) => sum + Number(settlement.total_amount || 0), 0)
                            const totalPaid = settlements.reduce((sum, settlement) => sum + Number(settlement.paid_amount || 0), 0)
                            const totalBalance = totalEarned - totalPaid
                            return totalBalance > 0 ? (
                                <div className='bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-3'>
                                    <p className='text-xs text-amber-700 dark:text-amber-400 font-medium'>Saldo acumulado a receber</p>
                                    <p className='text-xl font-bold text-amber-700 dark:text-amber-300 mt-1'>{formatCurrency(totalBalance)}</p>
                                    <p className='text-[10px] text-amber-600/70 dark:text-amber-500/70 mt-1'>Calculado: {formatCurrency(totalEarned)} - Recebido: {formatCurrency(totalPaid)}</p>
                                </div>
                            ) : null
                        })()}

                        <div className='flex gap-2'>
                            {(['current', 'previous', 'all'] as const).map((filter) => (
                                <button
                                    key={filter}
                                    onClick={() => setPeriodFilter(filter)}
                                    className={cn(
                                        'flex-1 px-3 py-2 rounded-lg text-sm font-medium',
                                        periodFilter === filter ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                                    )}
                                >
                                    {filter === 'current' ? 'Mes Atual' : filter === 'previous' ? 'Mes Anterior' : 'Tudo'}
                                </button>
                            ))}
                        </div>

                        <div className='flex gap-2 border-b border-border pb-2'>
                            {(['events', 'settlements', 'disputes'] as const).map((tab) => (
                                <button
                                    key={tab}
                                    onClick={() => setActiveTab(tab)}
                                    className={cn(
                                        'flex-1 px-3 py-2 rounded-lg text-sm font-medium',
                                        activeTab === tab ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                                    )}
                                >
                                    {tab === 'events' ? 'Eventos' : tab === 'settlements' ? 'Fechamentos' : 'Disputas'}
                                </button>
                            ))}
                        </div>

                        {activeTab === 'events' && (
                            <div className='space-y-3'>
                                {events.length === 0 ? (
                                    <p className='text-sm text-surface-500 text-center py-6'>Nenhum evento</p>
                                ) : (
                                    events.map((event) => (
                                        <div key={event.id} className='bg-card rounded-xl p-3'>
                                            <div className='flex justify-between items-start gap-2'>
                                                <div>
                                                    <p className='text-sm font-medium text-foreground'>
                                                        {event.notes || event.rule?.name || 'Comissao'}
                                                    </p>
                                                    <p className='text-xs text-surface-500'>
                                                        OS {(event.work_order?.os_number || event.work_order?.number) ?? '-'}
                                                    </p>
                                                </div>
                                                <div className='text-right'>
                                                    <p className='text-sm font-medium text-foreground'>
                                                        {formatCurrency(Number(event.commission_amount || 0))}
                                                    </p>
                                                    <span
                                                        className={cn(
                                                            'inline-block px-2 py-0.5 rounded text-[10px] font-medium mt-1',
                                                            STATUS_BADGES[event.status] ?? 'bg-surface-100 text-surface-600'
                                                        )}
                                                    >
                                                        {STATUS_LABELS[event.status] ?? event.status}
                                                    </span>
                                                </div>
                                            </div>
                                            <p className='text-[10px] text-surface-400 mt-2'>
                                                {new Date(event.created_at).toLocaleDateString('pt-BR')}
                                            </p>
                                        </div>
                                    ))
                                )}
                            </div>
                        )}

                        {activeTab === 'settlements' && (
                            <div className='space-y-3'>
                                {settlements.length === 0 ? (
                                    <p className='text-sm text-surface-500 text-center py-6'>Nenhum fechamento</p>
                                ) : (
                                    settlements.map((settlement) => {
                                        const earned = Number(settlement.total_amount || 0)
                                        const paid = Number(settlement.paid_amount || 0)
                                        const balance = earned - paid
                                        return (
                                            <div key={settlement.id} className='bg-card rounded-xl p-3'>
                                                <div className='flex justify-between items-start'>
                                                    <div>
                                                        <p className='text-sm font-medium text-foreground'>
                                                            {settlement.period.replace(/-/, '/')}
                                                        </p>
                                                        <p className='text-xs text-surface-500'>
                                                            Calculado: {formatCurrency(earned)}
                                                        </p>
                                                        {settlement.status === 'paid' && (
                                                            <>
                                                                <p className='text-xs text-emerald-600 dark:text-emerald-400'>
                                                                    Recebido: {formatCurrency(paid)}
                                                                </p>
                                                                {balance > 0.01 && (
                                                                    <p className='text-xs text-amber-600 dark:text-amber-400 font-medium'>
                                                                        Saldo: {formatCurrency(balance)}
                                                                    </p>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                    <div className='text-right'>
                                                        <span
                                                            className={cn(
                                                                'inline-block px-2 py-0.5 rounded text-[10px] font-medium',
                                                                STATUS_BADGES[settlement.status] ?? 'bg-surface-100 text-surface-600'
                                                            )}
                                                        >
                                                            {STATUS_LABELS[settlement.status] ?? settlement.status}
                                                        </span>
                                                        {settlement.paid_at && (
                                                            <p className='text-[10px] text-surface-400 mt-1'>
                                                                Pago em {new Date(settlement.paid_at).toLocaleDateString('pt-BR')}
                                                            </p>
                                                        )}
                                                        {settlement.payment_notes && (
                                                            <p className='text-[10px] text-surface-400 mt-1'>
                                                                {settlement.payment_notes.length > 40 ? `${settlement.payment_notes.slice(0, 40)}...` : settlement.payment_notes}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className='mt-3 pt-3 border-t border-border'>
                                                    <button
                                                        onClick={() => handleDownloadStatement(settlement.period)}
                                                        disabled={downloadingPeriod === settlement.period}
                                                        className='flex items-center justify-center gap-2 w-full py-2 bg-surface-50 hover:bg-surface-100 dark:bg-surface-900/50 dark:hover:bg-surface-800 text-brand-600 dark:text-brand-400 rounded-lg text-sm font-medium transition-colors border border-surface-200 dark:border-surface-800'
                                                    >
                                                        {downloadingPeriod === settlement.period ? <Loader2 className='w-4 h-4 animate-spin' /> : <Download className='w-4 h-4' />}
                                                        Baixar PDF do Extrato
                                                    </button>
                                                </div>
                                            </div>
                                        )
                                    })
                                )}
                            </div>
                        )}

                        {activeTab === 'disputes' && (
                            <div className='space-y-3'>
                                {showDisputeForm && (
                                    <div className='bg-card rounded-xl p-4 space-y-3'>
                                        <h3 className='text-sm font-semibold text-foreground'>
                                            Nova contestacao
                                        </h3>
                                        <select
                                            value={disputeEventId ?? ''}
                                            onChange={(event) => setDisputeEventId(Number(event.target.value) || null)}
                                            aria-label='Selecionar evento para contestacao'
                                            className='w-full px-3 py-2 rounded-lg bg-surface-100 border-0 text-sm'
                                        >
                                            <option value=''>Selecione o evento</option>
                                            {disputableEvents.map((event) => (
                                                <option key={event.id} value={event.id}>
                                                    OS {(event.work_order?.os_number || event.work_order?.number) ?? event.id} - {formatCurrency(Number(event.commission_amount || 0))}
                                                </option>
                                            ))}
                                        </select>
                                        <textarea
                                            value={disputeReason}
                                            onChange={(event) => setDisputeReason(event.target.value)}
                                            placeholder='Motivo da contestacao (min. 10 caracteres)'
                                            rows={3}
                                            aria-label='Motivo da contestacao'
                                            className='w-full px-3 py-2 rounded-lg bg-surface-100 border-0 text-sm resize-none'
                                        />
                                        <div className='flex gap-2'>
                                            <button
                                                onClick={() => {
                                                    setShowDisputeForm(false)
                                                    setDisputeReason('')
                                                    setDisputeEventId(null)
                                                }}
                                                className='flex-1 px-3 py-2 rounded-lg bg-surface-200 text-sm'
                                            >
                                                Cancelar
                                            </button>
                                            <button
                                                onClick={handleCreateDispute}
                                                disabled={createDisputeMutation.isPending}
                                                className='flex-1 px-3 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium disabled:opacity-50 flex items-center justify-center gap-2'
                                            >
                                                {createDisputeMutation.isPending ? <Loader2 className='w-4 h-4 animate-spin' /> : null}
                                                Enviar
                                            </button>
                                        </div>
                                    </div>
                                )}

                                {!showDisputeForm && (
                                    <button
                                        onClick={() => setShowDisputeForm(true)}
                                        className='w-full flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-dashed border-surface-300 dark:border-surface-600 text-surface-600 hover:border-brand-500 hover:text-brand-600'
                                    >
                                        <Plus className='w-5 h-5' /> Nova contestacao
                                    </button>
                                )}

                                {disputes.length === 0 ? (
                                    <p className='text-sm text-surface-500 text-center py-6'>Nenhuma contestacao</p>
                                ) : (
                                    disputes.map((dispute) => (
                                        <div key={dispute.id} className='bg-card rounded-xl p-3'>
                                            <p className='text-sm text-foreground line-clamp-2'>
                                                {dispute.reason}
                                            </p>
                                            <div className='flex justify-between items-center mt-2'>
                                                <span className='text-xs text-surface-500'>
                                                    {formatCurrency(Number(dispute.commission_event?.commission_amount || 0))}
                                                </span>
                                                <p className='text-[10px] text-surface-400'>
                                                    {new Date(dispute.created_at).toLocaleDateString('pt-BR')}
                                                </p>
                                            </div>
                                            <span
                                                className={cn(
                                                    'inline-block px-2 py-0.5 rounded text-[10px] font-medium mt-2',
                                                    STATUS_BADGES[normalizeCommissionDisputeStatus(dispute.status)] ?? 'bg-surface-100 text-surface-600'
                                                )}
                                            >
                                                {getCommissionDisputeStatusLabel(dispute.status)}
                                            </span>
                                        </div>
                                    ))
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    )
}
