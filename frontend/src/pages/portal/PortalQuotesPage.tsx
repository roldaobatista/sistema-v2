import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { DollarSign, Check, X, FileText, Clock, CheckCircle, XCircle, RefreshCw } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { cn, formatCurrency } from '@/lib/utils'
import { QUOTE_STATUS } from '@/lib/constants'
import { isPortalQuoteActionable } from '@/features/quotes/portal'

const fmtDate = (d: string) => new Date(d).toLocaleDateString('pt-BR')

interface PortalQuoteItem {
    product?: { name?: string }
    service?: { name?: string }
    custom_description?: string | null
    subtotal?: number | null
}

interface PortalQuoteEquipment {
    items?: PortalQuoteItem[]
}

interface PortalQuote {
    id: number
    quote_number?: string | null
    created_at: string
    status: string
    total_amount?: number | null
    total?: number | null
    equipments?: PortalQuoteEquipment[]
}

const statusCfg: Record<string, { label: string; color: string; bg: string; icon: React.ComponentType<{ className?: string }> }> = {
    [QUOTE_STATUS.DRAFT]: { label: 'Rascunho', color: 'text-slate-600', bg: 'bg-slate-100', icon: FileText },
    [QUOTE_STATUS.PENDING_INTERNAL]: { label: 'Aprov. Interna', color: 'text-amber-700', bg: 'bg-amber-100', icon: Clock },
    [QUOTE_STATUS.INTERNALLY_APPROVED]: { label: 'Aprovado Internamente', color: 'text-teal-700', bg: 'bg-teal-100', icon: CheckCircle },
    [QUOTE_STATUS.SENT]: { label: 'Enviado', color: 'text-sky-600', bg: 'bg-sky-100', icon: FileText },
    [QUOTE_STATUS.APPROVED]: { label: 'Aprovado', color: 'text-emerald-600', bg: 'bg-emerald-100', icon: CheckCircle },
    [QUOTE_STATUS.IN_EXECUTION]: { label: 'Em Execucao', color: 'text-emerald-700', bg: 'bg-emerald-100', icon: FileText },
    [QUOTE_STATUS.INSTALLATION_TESTING]: { label: 'Instalacao p/ Teste', color: 'text-orange-700', bg: 'bg-orange-100', icon: Clock },
    [QUOTE_STATUS.RENEGOTIATION]: { label: 'Em Renegociacao', color: 'text-rose-700', bg: 'bg-rose-100', icon: Clock },
    [QUOTE_STATUS.REJECTED]: { label: 'Rejeitado', color: 'text-red-600', bg: 'bg-red-100', icon: XCircle },
    [QUOTE_STATUS.EXPIRED]: { label: 'Expirado', color: 'text-amber-700', bg: 'bg-amber-100', icon: Clock },
    [QUOTE_STATUS.INVOICED]: { label: 'Faturado', color: 'text-emerald-700', bg: 'bg-emerald-100', icon: DollarSign },
}

function resolveStatusConfig(status: string) {
    return statusCfg[status] ?? { label: status, color: 'text-slate-600', bg: 'bg-slate-100', icon: FileText }
}

function requireArray<T>(value: unknown, fallbackMessage: string): T[] {
    if (Array.isArray(value)) {
        return value as T[]
    }

    throw new Error(fallbackMessage)
}

function normalizeRejectComments(value: string): string | undefined {
    const normalized = value.trim()
    return normalized === '' ? undefined : normalized
}

export function PortalQuotesPage() {
    const qc = useQueryClient()
    const [rejectingId, setRejectingId] = useState<number | null>(null)
    const [rejectReason, setRejectReason] = useState('')

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['portal-quotes'],
        queryFn: async () => requireArray<PortalQuote>(
            unwrapData<PortalQuote[]>(await api.get('/portal/quotes')),
            'Erro ao carregar orcamentos',
        ),
    })

    const approveMut = useMutation({
        mutationFn: (id: number) => api.post(`/portal/quotes/${id}/status`, { action: 'approve' }),
        onSuccess: () => {
            toast.success('Operação realizada com sucesso')
            qc.invalidateQueries({ queryKey: ['portal-quotes'] })
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Nao foi possivel atualizar o orcamento'))
        },
    })

    const rejectMut = useMutation({
        mutationFn: ({ id, comments }: { id: number; comments?: string }) =>
            api.post(`/portal/quotes/${id}/status`, { action: 'reject', comments }),
        onSuccess: () => {
            toast.success('Operação realizada com sucesso')
            qc.invalidateQueries({ queryKey: ['portal-quotes'] })
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Nao foi possivel atualizar o orcamento'))
        },
    })

    useEffect(() => {
        if (isError) {
            toast.error(getApiErrorMessage(error, 'Erro ao carregar orcamentos'))
        }
    }, [error, isError])

    const quotes: PortalQuote[] = data ?? []
    const pendingCount = quotes.filter(q => isPortalQuoteActionable(q.status)).length

    const content = (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Orçamentos</h1>
                    <p className="mt-0.5 text-sm text-surface-500">
                        {pendingCount > 0 ? `${pendingCount} orçamento(s) aguardando aprovação` : 'Todos os orçamentos'}
                    </p>
                </div>
            </div>

            {isLoading ? (
                <div className="text-center text-surface-400 py-12">Carregando...</div>
            ) : isError ? (
                <div className="py-12 text-center">
                    <RefreshCw className="mx-auto h-10 w-10 text-red-300" />
                    <p className="mt-2 text-sm text-surface-400">Erro ao carregar orçamentos</p>
                    <button onClick={() => refetch()} className="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Tentar novamente
                    </button>
                </div>
            ) : quotes.length === 0 ? (
                <div className="text-center py-12">
                    <FileText className="mx-auto h-10 w-10 text-surface-300" />
                    <p className="mt-2 text-sm text-surface-400">Nenhum orçamento encontrado</p>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {(quotes || []).map((q) => {
                        const cfg = resolveStatusConfig(q.status)
                        const StatusIcon = cfg.icon
                        const isPending = isPortalQuoteActionable(q.status)
                        const isAnyMutating = approveMut.isPending || rejectMut.isPending
                        return (
                            <div key={q.id} className={cn(
                                'rounded-xl border bg-surface-0 shadow-card overflow-hidden transition-all',
                                isPending ? 'border-brand-200' : 'border-surface-200'
                            )}>
                                <div className="p-5">
                                    <div className="flex items-start justify-between mb-3">
                                        <div className="flex items-center gap-3">
                                            <div className={cn('rounded-lg p-2', cfg.bg)}>
                                                <StatusIcon className={cn('h-4 w-4', cfg.color)} />
                                            </div>
                                            <div>
                                                <p className="text-sm font-bold text-surface-900">Orçamento #{q.quote_number ?? q.id}</p>
                                                <p className="text-xs text-surface-400">{fmtDate(q.created_at)}</p>
                                            </div>
                                        </div>
                                        <span className={cn('text-xs font-semibold px-2.5 py-1 rounded-full', cfg.bg, cfg.color)}>
                                            {cfg.label}
                                        </span>
                                    </div>

                                    {(() => {
                                        const allItems = q.equipments?.flatMap((e) => e.items ?? []) ?? []
                                        return allItems.length > 0 && (
                                            <div className="mb-3 space-y-1">
                                                {(allItems || []).slice(0, 3).map((item, i: number) => (
                                                    <div key={i} className="flex items-center justify-between text-xs">
                                                        <span className="text-surface-600 truncate max-w-[60%]">{item.product?.name || item.service?.name || item.custom_description}</span>
                                                        <span className="text-surface-500 font-medium">{formatCurrency(item.subtotal ?? 0)}</span>
                                                    </div>
                                                ))}
                                                {allItems.length > 3 && (
                                                    <p className="text-xs text-surface-400 italic">+{allItems.length - 3} item(ns)</p>
                                                )}
                                            </div>
                                        )
                                    })()}

                                    <div className="border-t border-subtle pt-3 flex items-center justify-between">
                                        <span className="text-xs text-surface-500">Total</span>
                                        <span className="text-sm font-semibold tabular-nums text-surface-900">{formatCurrency(q.total_amount ?? q.total ?? 0)}</span>
                                    </div>
                                </div>

                                {isPending && (
                                    <div className="border-t border-subtle bg-surface-50 px-5 py-3 flex gap-2 justify-end">
                                        <button
                                            onClick={() => { setRejectingId(q.id); setRejectReason('') }}
                                            disabled={isAnyMutating}
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50"
                                        >
                                            <X className="h-3.5 w-3.5" /> Rejeitar
                                        </button>
                                        <button
                                            onClick={() => approveMut.mutate(q.id)}
                                            disabled={isAnyMutating}
                                            className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-xs font-medium bg-brand-600 text-white hover:bg-brand-700 transition-colors disabled:opacity-50"
                                        >
                                            <Check className="h-3.5 w-3.5" /> Aprovar
                                        </button>
                                    </div>
                                )}
                            </div>
                        )
                    })}
                </div>
            )}
        </div>
    )

    return (
        <>
            {content}

            {rejectingId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
                    <div className="bg-surface-0 rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                        <h3 className="text-lg font-semibold text-surface-900 mb-3">Rejeitar Orçamento</h3>
                        <textarea
                            value={rejectReason}
                            onChange={e => setRejectReason(e.target.value)}
                            placeholder="Motivo da rejeição (opcional)..."
                            rows={3}
                            className="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 mb-4"
                        />
                        <div className="flex justify-end gap-2">
                            <button
                                onClick={() => setRejectingId(null)}
                                className="px-4 py-2 text-sm rounded-lg text-surface-600 hover:bg-surface-100 transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={() => {
                                    rejectMut.mutate({ id: rejectingId, comments: normalizeRejectComments(rejectReason) })
                                    setRejectingId(null)
                                }}
                                disabled={rejectMut.isPending}
                                className="px-4 py-2 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors disabled:opacity-50"
                            >
                                Confirmar Rejeição
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    )
}
