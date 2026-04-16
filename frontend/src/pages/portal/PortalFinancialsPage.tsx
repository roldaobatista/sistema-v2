import { useEffect, useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { DollarSign, Clock, CheckCircle, AlertTriangle, Receipt, RefreshCw } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { cn, formatCurrency } from '@/lib/utils'
import { FINANCIAL_STATUS } from '@/lib/constants'

interface PortalFinancial {
    id: number
    description?: string | null
    amount: number | string
    due_date: string
    status: string
}

const fmtDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString('pt-BR')

const statusCfg: Record<string, { label: string; color: string; bg: string; icon: LucideIcon }> = {
    [FINANCIAL_STATUS.PENDING]: { label: 'Pendente', color: 'text-amber-600', bg: 'bg-amber-100', icon: Clock },
    [FINANCIAL_STATUS.PARTIAL]: { label: 'Parcial', color: 'text-sky-600', bg: 'bg-sky-100', icon: DollarSign },
    [FINANCIAL_STATUS.PAID]: { label: 'Pago', color: 'text-emerald-600', bg: 'bg-emerald-100', icon: CheckCircle },
    [FINANCIAL_STATUS.OVERDUE]: { label: 'Atrasado', color: 'text-red-600', bg: 'bg-red-100', icon: AlertTriangle },
}

function requireArray<T>(value: unknown, fallbackMessage: string): T[] {
    if (Array.isArray(value)) {
        return value as T[]
    }

    throw new Error(fallbackMessage)
}

export function PortalFinancialsPage() {
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['portal-financials'],
        queryFn: async () => requireArray<PortalFinancial>(
            unwrapData<PortalFinancial[]>(await api.get('/portal/financials')),
            'Erro ao carregar financeiro',
        ),
    })

    useEffect(() => {
        if (isError) {
            toast.error(getApiErrorMessage(error, 'Erro ao carregar financeiro'))
        }
    }, [error, isError])

    const financials: PortalFinancial[] = data ?? []

    const summary = useMemo(() => {
        let pending = 0
        let paid = 0
        let overdue = 0

        financials.forEach((financial) => {
            const amount = parseFloat(String(financial.amount ?? 0))
            if (financial.status === FINANCIAL_STATUS.PAID) {
                paid += amount
                return
            }

            const isOverdue = new Date(financial.due_date) < new Date()
            if (isOverdue) {
                overdue += amount
            } else {
                pending += amount
            }
        })

        return { pending, paid, overdue, total: pending + paid + overdue }
    }, [financials])

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Financeiro</h1>
                <p className="mt-0.5 text-sm text-surface-500">Suas faturas e pagamentos</p>
            </div>

            {isLoading ? (
                <div className="py-12 text-center text-surface-400">Carregando...</div>
            ) : isError ? (
                <div className="py-12 text-center">
                    <RefreshCw className="mx-auto h-10 w-10 text-red-300" />
                    <p className="mt-2 text-sm text-surface-400">Erro ao carregar financeiro</p>
                    <button onClick={() => refetch()} className="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Tentar novamente
                    </button>
                </div>
            ) : (
                <>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                            <div className="flex items-center gap-2 text-amber-600">
                                <Clock className="h-4 w-4" />
                                <span className="text-xs font-medium">Pendente</span>
                            </div>
                            <p className="mt-1 text-xl font-bold text-surface-900">{formatCurrency(summary.pending)}</p>
                        </div>
                        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                            <div className="flex items-center gap-2 text-red-600">
                                <AlertTriangle className="h-4 w-4" />
                                <span className="text-xs font-medium">Atrasado</span>
                            </div>
                            <p className="mt-1 text-xl font-bold text-red-600">{formatCurrency(summary.overdue)}</p>
                        </div>
                        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                            <div className="flex items-center gap-2 text-emerald-600">
                                <CheckCircle className="h-4 w-4" />
                                <span className="text-xs font-medium">Pago</span>
                            </div>
                            <p className="mt-1 text-xl font-bold text-emerald-600">{formatCurrency(summary.paid)}</p>
                        </div>
                    </div>

                    {financials.length === 0 ? (
                        <div className="py-12 text-center">
                            <Receipt className="mx-auto h-10 w-10 text-surface-300" />
                            <p className="mt-2 text-sm text-surface-400">Nenhuma fatura encontrada</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {financials.map((item) => {
                                const isOverdue = item.status !== FINANCIAL_STATUS.PAID && new Date(item.due_date) < new Date()
                                const effectiveStatus = isOverdue ? FINANCIAL_STATUS.OVERDUE : item.status
                                const cfg = statusCfg[effectiveStatus] ?? statusCfg.pending
                                const StatusIcon = cfg.icon

                                return (
                                    <div
                                        key={item.id}
                                        className={cn(
                                            'flex items-center gap-4 rounded-xl border bg-surface-0 p-4 shadow-card transition-all',
                                            isOverdue ? 'border-red-200' : 'border-surface-200',
                                        )}
                                    >
                                        <div className={cn('flex-shrink-0 rounded-lg p-2.5', cfg.bg)}>
                                            <StatusIcon className={cn('h-4 w-4', cfg.color)} />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-surface-900">{item.description || `Fatura #${item.id}`}</p>
                                            <p className="mt-0.5 text-xs text-surface-400">Vencimento: {fmtDate(item.due_date)}</p>
                                        </div>
                                        <div className="flex-shrink-0 text-right">
                                            <p className="text-sm font-bold text-surface-900">{formatCurrency(item.amount)}</p>
                                            <span className={cn('rounded-full px-2 py-0.5 text-xs font-semibold', cfg.bg, cfg.color)}>
                                                {cfg.label}
                                            </span>
                                        </div>
                                    </div>
                                )
                            })}
                        </div>
                    )}

                    {!isLoading && !isError && !!data && financials.length === 0 && (
                        <button onClick={() => refetch()} className="text-sm font-medium text-brand-600 hover:text-brand-700">
                            Atualizar
                        </button>
                    )}
                </>
            )}
        </div>
    )
}
