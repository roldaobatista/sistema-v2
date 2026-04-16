import { useEffect, useState, useCallback, useMemo, type MouseEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    ClipboardList, MapPin, Clock, ChevronRight, Search,
    AlertCircle, CheckCircle2, WifiOff,
    Navigation2, Phone, Timer, Pin, Truck,
    Pause, Play, Undo2,
} from 'lucide-react'
import { toast } from 'sonner'
import { useForm } from 'react-hook-form'
import { useOfflineStore } from '@/hooks/useOfflineStore'
import { cn } from '@/lib/utils'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import type { OfflineWorkOrder } from '@/lib/offlineDb'
import { ListSkeleton } from '@/components/tech/TechSkeleton'
import { usePullToRefresh } from '@/hooks/usePullToRefresh'
import { PullToRefreshIndicator } from '@/components/tech/PullToRefreshIndicator'

function getSlaInfo(slaDueAt: string | null | undefined, status: string): { label: string; color: string } | null {
    if (!slaDueAt || ['completed', 'cancelled', 'delivered', 'invoiced'].includes(status)) return null

    const now = new Date()
    const due = new Date(slaDueAt)
    const diffMs = due.getTime() - now.getTime()
    const diffHours = diffMs / (1000 * 60 * 60)

    if (diffHours < 0) return { label: 'SLA Estourado', color: 'bg-red-500 text-white' }
    if (diffHours < 2) return { label: `${Math.ceil(diffHours * 60)}min`, color: 'bg-red-100 text-red-700 dark:bg-red-900/30' }
    if (diffHours < 24) return { label: `${Math.ceil(diffHours)}h`, color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30' }

    return { label: `${Math.ceil(diffHours / 24)}d`, color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30' }
}

const STATUS_MAP: Record<string, { label: string; color: string; icon: typeof Clock }> = {
    open: { label: 'Aberta', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30', icon: Clock },
    awaiting_dispatch: { label: 'Aguard. Despacho', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30', icon: Clock },
    in_displacement: { label: 'Em Deslocamento', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', icon: Truck },
    displacement_paused: { label: 'Desloc. Pausado', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30', icon: Pause },
    at_client: { label: 'No Cliente', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30', icon: MapPin },
    in_service: { label: 'Em Servico', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', icon: Play },
    service_paused: { label: 'Servico Pausado', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30', icon: Pause },
    awaiting_return: { label: 'Servico Concluido', color: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30', icon: CheckCircle2 },
    in_return: { label: 'Em Retorno', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', icon: Undo2 },
    completed: { label: 'Concluida', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30', icon: CheckCircle2 },
    return_paused: { label: 'Retorno Pausado', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30', icon: Pause },
    delivered: { label: 'Entregue', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30', icon: CheckCircle2 },
    invoiced: { label: 'Faturada', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30', icon: ClipboardList },
    cancelled: { label: 'Cancelada', color: 'bg-red-100 text-red-700 dark:bg-red-900/30', icon: AlertCircle },
}

const PRIORITY_COLORS: Record<string, string> = {
    low: 'border-l-surface-300',
    normal: 'border-l-blue-400',
    high: 'border-l-amber-500',
    urgent: 'border-l-red-500',
}

interface TechSyncPayload {
    work_orders?: OfflineWorkOrder[]
}

interface FilterFormValues {
    search: string
    statusFilter: string
}

export default function TechWorkOrdersPage() {
    const navigate = useNavigate()
    const { items: offlineOrders, putMany, isLoading: offlineLoading } = useOfflineStore('work-orders')
    const [pinnedIds, setPinnedIds] = useState<Set<number>>(() => {
        try {
            const saved = localStorage.getItem('tech-pinned-os')
            return new Set(saved ? JSON.parse(saved) : [])
        } catch {
            return new Set()
        }
    })

    const { register, watch, setValue } = useForm<FilterFormValues>({
        defaultValues: {
            search: '',
            statusFilter: 'active'
        }
    })

    const search = watch('search')
    const statusFilter = watch('statusFilter')

    const [isOnline, setIsOnline] = useState(() => navigator.onLine)
    const [isFetching, setIsFetching] = useState(false)

    const syncWorkOrders = useCallback(async () => {
        const response = await api.get<{ data?: TechSyncPayload } | TechSyncPayload>('/tech/sync?since=1970-01-01T00:00:00Z')
        const payload = unwrapData<TechSyncPayload>(response) ?? {}
        const workOrders = payload.work_orders ?? []

        if (workOrders.length > 0) {
            await putMany(workOrders)
        }
    }, [putMany])

    useEffect(() => {
        const handleOnline = () => setIsOnline(true)
        const handleOffline = () => setIsOnline(false)

        window.addEventListener('online', handleOnline)
        window.addEventListener('offline', handleOffline)

        return () => {
            window.removeEventListener('online', handleOnline)
            window.removeEventListener('offline', handleOffline)
        }
    }, [])

    const handleRefresh = useCallback(async () => {
        if (!navigator.onLine) return

        setIsFetching(true)
        try {
            await syncWorkOrders()
        } catch (err: unknown) {
            const apiError = getApiErrorMessage(err, 'Nao foi possivel atualizar a lista. Verifique a conexao.')
            const msg = err instanceof Error ? err.message : String(err)
            toast.error(`${apiError} (${msg} @ ${api.defaults.baseURL})`)
        } finally {
            setIsFetching(false)
        }
    }, [syncWorkOrders])

    const { containerRef, isRefreshing, pullDistance } = usePullToRefresh({ onRefresh: handleRefresh })

    useEffect(() => {
        if (!isOnline) return

        async function fetchAndCache() {
            setIsFetching(true)
            try {
                await syncWorkOrders()
            } catch (err: unknown) {
                const apiError = getApiErrorMessage(err, 'Nao foi possivel atualizar. Exibindo dados em cache.')
                const msg = err instanceof Error ? err.message : String(err)
                toast.error(`${apiError} (${msg})`)
            } finally {
                setIsFetching(false)
            }
        }

        void fetchAndCache()
    }, [isOnline, syncWorkOrders])

    const filtered = (offlineOrders || []).filter((wo) => {
        const matchesSearch = !search || [
            wo.number,
            wo.os_number,
            wo.customer_name,
            wo.description,
        ].some((field) => field?.toLowerCase().includes(search.toLowerCase()))

        const matchesStatus = statusFilter === 'all'
            || (statusFilter === 'active' && !['completed', 'delivered', 'invoiced', 'cancelled'].includes(wo.status))
            || wo.status === statusFilter

        return matchesSearch && matchesStatus
    })

    const sorted = useMemo(() => {
        return [...filtered].sort((a, b) => {
            const aPinned = pinnedIds.has(a.id) ? 1 : 0
            const bPinned = pinnedIds.has(b.id) ? 1 : 0
            return bPinned - aPinned
        })
    }, [filtered, pinnedIds])

    const togglePin = (id: number, event: MouseEvent) => {
        event.stopPropagation()
        setPinnedIds((prev) => {
            const next = new Set(prev)
            if (next.has(id)) next.delete(id)
            else next.add(id)
            localStorage.setItem('tech-pinned-os', JSON.stringify([...next]))
            return next
        })
    }

    const statusFilters = [
        { key: 'active', label: 'Ativas' },
        { key: 'open', label: 'Abertas' },
        { key: 'in_service', label: 'Em Servico' },
        { key: 'awaiting_return', label: 'Retorno' },
        { key: 'completed', label: 'Concluidas' },
        { key: 'all', label: 'Todas' },
    ]

    const loading = offlineLoading || isFetching

    return (
        <div className="flex flex-col h-full">
            <div className="sticky top-0 z-10 bg-card px-4 pt-4 pb-2 space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <h1 className="text-lg font-bold text-foreground">Ordens de Servico</h1>
                        {!isOnline && (
                            <span className="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                <WifiOff className="w-3 h-3" />
                                offline
                            </span>
                        )}
                    </div>
                    <span className="text-[11px] font-medium text-surface-400">
                        Fluxo de execucao pela OS
                    </span>
                </div>

                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400" />
                    <input
                        {...register('search')}
                        placeholder="Buscar OS, cliente..."
                        className="w-full pl-9 pr-4 py-2.5 rounded-xl bg-surface-100 border-0 text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30 focus:outline-none"
                    />
                </div>

                <div className="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 no-scrollbar">
                    {(statusFilters || []).map((filter) => (
                        <button
                            key={filter.key}
                            onClick={() => setValue('statusFilter', filter.key)}
                            className={cn(
                                'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors',
                                statusFilter === filter.key
                                    ? 'bg-brand-600 text-white'
                                    : 'bg-surface-100 text-surface-600'
                            )}
                        >
                            {filter.label}
                        </button>
                    ))}
                </div>
            </div>

            <div ref={containerRef} className="flex-1 overflow-y-auto px-4 pb-4 space-y-2">
                <PullToRefreshIndicator pullDistance={pullDistance} isRefreshing={isRefreshing} />
                {loading && sorted.length === 0 ? (
                    <ListSkeleton count={4} />
                ) : sorted.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 gap-3">
                        <ClipboardList className="w-12 h-12 text-surface-300" />
                        <p className="text-sm text-surface-500">
                            {search ? 'Nenhuma OS encontrada' : 'Nenhuma OS atribuida'}
                        </p>
                    </div>
                ) : (
                    (sorted || []).map((wo) => {
                        const normalizedStatus = wo.status === 'pending'
                            ? 'open'
                            : wo.status === 'in_progress'
                                ? 'in_service'
                                : wo.status
                        const status = STATUS_MAP[normalizedStatus] || STATUS_MAP.open
                        const StatusIcon = status.icon
                        const priorityKey = wo.priority ?? 'normal'
                        const slaInfo = getSlaInfo(wo.sla_due_at, normalizedStatus)
                        const customerPhone = wo.customerPhone ?? wo.customer_phone

                        return (
                            <div
                                key={wo.id}
                                onClick={() => navigate(`/tech/os/${wo.id}`)}
                                className={cn(
                                    'w-full text-left bg-card rounded-xl p-4 border-l-4 shadow-sm cursor-pointer',
                                    'active:scale-[0.98] transition-transform relative',
                                    PRIORITY_COLORS[priorityKey] || PRIORITY_COLORS.normal,
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1 flex-wrap">
                                            <span className="font-semibold text-sm text-foreground">
                                                {wo.os_number || wo.number}
                                            </span>
                                            <span className={cn(
                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium',
                                                status.color,
                                            )}>
                                                <StatusIcon className="w-3 h-3" />
                                                {status.label}
                                            </span>
                                            {slaInfo && (
                                                <span className={cn(
                                                    'inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-medium',
                                                    slaInfo.color,
                                                )}>
                                                    <Timer className="w-2.5 h-2.5" />
                                                    {slaInfo.label}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-surface-500 truncate">
                                            {wo.customer_name || 'Cliente nao informado'}
                                        </p>
                                        {wo.description && (
                                            <p className="text-xs text-surface-400 line-clamp-1 mt-0.5">
                                                {wo.description}
                                            </p>
                                        )}
                                        <div className="flex items-center gap-3 mt-2 text-[11px] text-surface-400">
                                            {wo.scheduled_date && (
                                                <span className="flex items-center gap-1">
                                                    <Clock className="w-3 h-3" />
                                                    {new Date(wo.scheduled_date).toLocaleDateString('pt-BR')}
                                                </span>
                                            )}
                                            {wo.city && (
                                                <span className="flex items-center gap-1">
                                                    <MapPin className="w-3 h-3" />
                                                    {wo.city}
                                                </span>
                                            )}
                                        </div>
                                        {!['completed', 'cancelled', 'delivered', 'invoiced'].includes(normalizedStatus) && (
                                            <div className="flex items-center gap-2 mt-2.5 pt-2.5 border-t border-surface-100">
                                                {wo.customer_address && (
                                                    <button
                                                        onClick={(event) => {
                                                            event.stopPropagation()
                                                            const address = encodeURIComponent(`${wo.customer_address || ''} ${wo.city || ''}`)
                                                            window.open(`https://www.google.com/maps/dir/?api=1&destination=${address}`, '_blank')
                                                        }}
                                                        className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-600 dark:text-emerald-400 text-[11px] font-medium active:scale-95 transition-all"
                                                    >
                                                        <Navigation2 className="w-3.5 h-3.5" />
                                                        Navegar
                                                    </button>
                                                )}
                                                {customerPhone && (
                                                    <a
                                                        href={`tel:${customerPhone}`}
                                                        onClick={(event) => event.stopPropagation()}
                                                        className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-blue-50 text-blue-600 dark:text-blue-400 text-[11px] font-medium active:scale-95 transition-all"
                                                    >
                                                        <Phone className="w-3.5 h-3.5" />
                                                        Ligar
                                                    </a>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1 shrink-0 relative z-10">
                                        <button
                                            onClick={(event) => togglePin(wo.id, event)}
                                            className="p-1 rounded-lg"
                                            title={pinnedIds.has(wo.id) ? "Desafixar" : "Fixar (Ir para o topo)"}
                                        >
                                            <Pin className={cn('w-3.5 h-3.5', pinnedIds.has(wo.id) ? 'text-brand-600 fill-brand-600' : 'text-surface-300')} />
                                        </button>
                                        <ChevronRight className="w-5 h-5 text-surface-300 mt-1" />
                                    </div>
                                </div>
                            </div>
                        )
                    })
                )}
            </div>
        </div>
    )
}
