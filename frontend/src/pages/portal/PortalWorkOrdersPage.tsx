import { useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { FileText, Search, Clock, CheckCircle, AlertCircle, Wrench, Package, RefreshCw } from 'lucide-react'
import initEcho from '@/lib/echo'
import type { LucideIcon } from 'lucide-react'
import api from '@/lib/api'
import { cn, formatCurrency } from '@/lib/utils'
import { WORK_ORDER_STATUS } from '@/lib/constants'

interface PortalWorkOrderAssignee {
    name?: string | null
}

interface PortalWorkOrder {
    id: number
    number?: string | null
    description?: string | null
    status: string
    created_at: string
    total?: number | string | null
    assignee?: PortalWorkOrderAssignee | null
}

const statusConfig: Record<string, { label: string; color: string; bg: string; icon: LucideIcon }> = {
    open: { label: 'Aberta', color: 'text-sky-600', bg: 'bg-sky-100', icon: Clock },
    in_progress: { label: 'Em Andamento', color: 'text-amber-600', bg: 'bg-amber-100', icon: Wrench },
    waiting_parts: { label: 'Aguard. Peças', color: 'text-orange-600', bg: 'bg-orange-100', icon: Package },
    waiting_approval: { label: 'Aguard. Aprovação', color: 'text-cyan-600', bg: 'bg-cyan-100', icon: AlertCircle },
    completed: { label: 'Concluída', color: 'text-emerald-600', bg: 'bg-emerald-100', icon: CheckCircle },
    delivered: { label: 'Entregue', color: 'text-teal-600', bg: 'bg-teal-100', icon: CheckCircle },
    invoiced: { label: 'Faturada', color: 'text-emerald-600', bg: 'bg-emerald-100', icon: FileText },
    cancelled: { label: 'Cancelada', color: 'text-red-600', bg: 'bg-red-100', icon: AlertCircle },
}

const trackingSteps = [WORK_ORDER_STATUS.OPEN, WORK_ORDER_STATUS.IN_PROGRESS, WORK_ORDER_STATUS.COMPLETED]
const fmtDate = (date: string) => new Date(date).toLocaleDateString('pt-BR')

export function PortalWorkOrdersPage() {
    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState('')
    const queryClient = useQueryClient()

    const { data, isLoading, isError, refetch } = useQuery({
        queryKey: ['portal-work-orders'],
        queryFn: () => api.get('/portal/work-orders').then((response) => response.data),
    })

    // WebSocket: atualização em tempo real quando status de OS muda
    useEffect(() => {
        let channel: ReturnType<Awaited<ReturnType<typeof initEcho>>['channel']> | null = null
        initEcho().then((echo) => {
            if (!echo) return
            channel = echo.channel('work-orders')
                .listen('.WorkOrderStatusChanged', () => {
                    queryClient.invalidateQueries({ queryKey: ['portal-work-orders'] })
                })
        })
        return () => { channel?.stopListening?.('.WorkOrderStatusChanged') }
    }, [queryClient])

    const all: PortalWorkOrder[] = data?.data ?? []

    const filtered = useMemo(() => {
        let list = all

        if (statusFilter) {
            list = list.filter((workOrder) => workOrder.status === statusFilter)
        }

        if (search) {
            const query = search.toLowerCase()
            list = list.filter((workOrder) =>
                (workOrder.number ?? '').toLowerCase().includes(query)
                || (workOrder.description ?? '').toLowerCase().includes(query)
            )
        }

        return list
    }, [all, search, statusFilter])

    const counts = useMemo(() => {
        const values: Record<string, number> = {}
        Object.keys(statusConfig).forEach((key) => {
            values[key] = 0
        })

        all.forEach((workOrder) => {
            if (values[workOrder.status] !== undefined) {
                values[workOrder.status] += 1
            }
        })

        return values
    }, [all])

    const getStepIndex = (status: string) => {
        if (status === WORK_ORDER_STATUS.DELIVERED || status === WORK_ORDER_STATUS.INVOICED) {
            return 2
        }
        if (status === WORK_ORDER_STATUS.COMPLETED) {
            return 2
        }
        if (
            status === WORK_ORDER_STATUS.IN_PROGRESS
            || status === WORK_ORDER_STATUS.WAITING_PARTS
            || status === WORK_ORDER_STATUS.WAITING_APPROVAL
        ) {
            return 1
        }

        return 0
    }

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Minhas Ordens de Serviço</h1>
                <p className="mt-0.5 text-sm text-surface-500">Acompanhe o progresso dos seus serviços</p>
            </div>

            <div className="flex flex-wrap gap-2">
                <button
                    onClick={() => setStatusFilter('')}
                    className={cn(
                        'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                        !statusFilter ? 'border-brand-600 bg-brand-600 text-white' : 'border-default bg-surface-0 text-surface-600 hover:bg-surface-50',
                    )}
                >
                    Todas ({all.length})
                </button>
                {Object.entries(statusConfig).map(([key, config]) => (
                    counts[key] > 0 && (
                        <button
                            key={key}
                            onClick={() => setStatusFilter(statusFilter === key ? '' : key)}
                            className={cn(
                                'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                statusFilter === key ? `${config.bg} ${config.color} border-current` : 'border-default bg-surface-0 text-surface-600 hover:bg-surface-50',
                            )}
                        >
                            {config.label} ({counts[key]})
                        </button>
                    )
                ))}
            </div>

            <div className="relative max-w-sm">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                <input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    aria-label="Buscar ordens de serviço"
                    placeholder="Buscar OS..."
                    className="w-full rounded-lg border border-default bg-surface-50 py-2.5 pl-10 pr-4 text-sm focus:border-brand-500 focus:outline-none"
                />
            </div>

            {isLoading ? (
                <div className="space-y-3">
                    {[1, 2, 3].map((item) => (
                        <div key={item} className="animate-pulse rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="mb-3 flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="h-9 w-9 rounded-lg bg-surface-200" />
                                    <div className="space-y-1">
                                        <div className="h-4 w-16 rounded bg-surface-200" />
                                        <div className="h-3 w-20 rounded bg-surface-100" />
                                    </div>
                                </div>
                                <div className="h-6 w-24 rounded-full bg-surface-200" />
                            </div>
                            <div className="mb-3 h-4 w-3/4 rounded bg-surface-100" />
                            <div className="flex gap-6">
                                <div className="h-3 w-24 rounded bg-surface-100" />
                                <div className="h-3 w-20 rounded bg-surface-100" />
                            </div>
                        </div>
                    ))}
                </div>
            ) : isError ? (
                <div className="py-12 text-center">
                    <RefreshCw className="mx-auto h-10 w-10 text-red-300" />
                    <p className="mt-2 text-sm text-surface-400">Erro ao carregar ordens de serviço</p>
                    <button onClick={() => refetch()} className="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Tentar novamente
                    </button>
                </div>
            ) : filtered.length === 0 ? (
                <div className="py-12 text-center">
                    <FileText className="mx-auto h-10 w-10 text-surface-300" />
                    <p className="mt-2 text-sm text-surface-400">Nenhuma OS encontrada</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {filtered.map((workOrder) => {
                        const config = statusConfig[workOrder.status] ?? statusConfig.open
                        const stepIndex = getStepIndex(workOrder.status)
                        const StatusIcon = config.icon

                        return (
                            <div key={workOrder.id} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card transition-all hover:shadow-elevated">
                                <div className="mb-3 flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className={cn('rounded-lg p-2', config.bg)}>
                                            <StatusIcon className={cn('h-4 w-4', config.color)} />
                                        </div>
                                        <div>
                                            <p className="text-sm font-bold text-brand-600">{workOrder.number ?? `#${workOrder.id}`}</p>
                                            <p className="text-xs text-surface-400">{fmtDate(workOrder.created_at)}</p>
                                        </div>
                                    </div>
                                    <span className={cn('rounded-full px-2.5 py-1 text-xs font-semibold', config.bg, config.color)}>
                                        {config.label}
                                    </span>
                                </div>

                                {workOrder.description && (
                                    <p className="mb-3 line-clamp-2 text-sm text-surface-700">{workOrder.description}</p>
                                )}

                                <div className="mb-4 flex flex-wrap gap-4 text-xs text-surface-500">
                                    {workOrder.assignee?.name && <span>Técnico: <strong className="text-surface-700">{workOrder.assignee.name}</strong></span>}
                                    {workOrder.total && parseFloat(String(workOrder.total)) > 0 && (
                                        <span>Valor: <strong className="text-surface-700">{formatCurrency(workOrder.total)}</strong></span>
                                    )}
                                </div>

                                {workOrder.status !== 'cancelled' && (
                                    <div>
                                        <div className="flex items-center gap-1">
                                            {trackingSteps.map((step, index) => (
                                                <div key={step} className="flex flex-1 items-center">
                                                    <div
                                                        className={cn(
                                                            'h-2.5 w-2.5 flex-shrink-0 rounded-full border-2 transition-colors',
                                                            index <= stepIndex ? 'border-brand-500 bg-brand-500' : 'border-default bg-surface-0',
                                                        )}
                                                    />
                                                    {index < trackingSteps.length - 1 && (
                                                        <div className={cn('mx-1 h-0.5 flex-1', index < stepIndex ? 'bg-brand-500' : 'bg-surface-200')} />
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-1.5 flex justify-between text-xs text-surface-400">
                                            <span>Aberta</span>
                                            <span>Em Andamento</span>
                                            <span>Concluída</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )
                    })}
                </div>
            )}
        </div>
    )
}
