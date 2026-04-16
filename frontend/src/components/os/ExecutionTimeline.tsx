import { useQuery } from '@tanstack/react-query'
import {
    Navigation, MapPin, Play, Pause, CheckCircle2, Clock,
    ArrowRight, AlertCircle, Undo2, Home, XCircle, LogIn, LogOut,
} from 'lucide-react'
import { unwrapData } from '@/lib/api'
import { workOrderApi } from '@/lib/work-order-api'
import { Button } from '@/components/ui/button'
import { cn, getApiErrorMessage } from '@/lib/utils'
import { safeArray } from '@/lib/safe-array'

interface TimelineEvent {
    id: number
    event_type: string
    event_label: string
    user: { id: number; name: string } | null
    latitude: number | null
    longitude: number | null
    metadata: Record<string, unknown> | null
    created_at: string
}

type TimelineResponse = TimelineEvent[] | { data?: TimelineEvent[] }

const EVENT_ICONS: Record<string, { icon: typeof Navigation; color: string }> = {
    displacement_started: { icon: Navigation, color: 'text-blue-500 bg-blue-100' },
    displacement_paused: { icon: Pause, color: 'text-amber-500 bg-amber-100' },
    displacement_resumed: { icon: Play, color: 'text-blue-500 bg-blue-100' },
    arrived_at_client: { icon: MapPin, color: 'text-emerald-500 bg-emerald-100' },
    service_started: { icon: Play, color: 'text-emerald-500 bg-emerald-100' },
    service_paused: { icon: Pause, color: 'text-amber-500 bg-amber-100' },
    service_resumed: { icon: ArrowRight, color: 'text-emerald-500 bg-emerald-100' },
    service_completed: { icon: CheckCircle2, color: 'text-emerald-600 bg-emerald-100' },
    return_started: { icon: Undo2, color: 'text-blue-500 bg-blue-100' },
    return_paused: { icon: Pause, color: 'text-amber-500 bg-amber-100' },
    return_resumed: { icon: Play, color: 'text-blue-500 bg-blue-100' },
    return_arrived: { icon: Home, color: 'text-emerald-600 bg-emerald-100' },
    closed_no_return: { icon: XCircle, color: 'text-surface-600 bg-surface-100' },
    checkin_registered: { icon: LogIn, color: 'text-emerald-600 bg-emerald-100' },
    checkout_registered: { icon: LogOut, color: 'text-rose-600 bg-rose-100' },
    status_changed: { icon: AlertCircle, color: 'text-surface-500 bg-surface-100' },
}

interface ExecutionTimelineProps {
    workOrderId: number
    className?: string
}

export function ExecutionTimeline({ workOrderId, className }: ExecutionTimelineProps) {
    const { data: events = [], isLoading, isError, error, refetch, isFetching } = useQuery<TimelineEvent[]>({
        queryKey: ['wo-execution-timeline', workOrderId],
        queryFn: async () => {
            const response = await workOrderApi.executionTimeline(workOrderId)
            return safeArray<TimelineEvent>(unwrapData<TimelineResponse>(response))
        },
    })

    if (isLoading) {
        return (
            <div className={cn('space-y-3 animate-pulse', className)}>
                {[1, 2, 3].map((i) => (
                    <div key={i} className="flex gap-3 items-start">
                        <div className="h-8 w-8 rounded-full bg-surface-200" />
                        <div className="flex-1 space-y-1">
                            <div className="h-4 w-40 rounded bg-surface-200" />
                            <div className="h-3 w-24 rounded bg-surface-100" />
                        </div>
                    </div>
                ))}
            </div>
        )
    }

    if (events.length === 0) {
        if (isError) {
            return (
                <div className={cn('flex flex-col items-center py-8 text-center text-surface-500', className)}>
                    <AlertCircle className="mb-2 h-8 w-8 text-red-400" />
                    <p className="text-sm">{getApiErrorMessage(error, 'Nao foi possivel carregar a timeline de execucao.')}</p>
                    <Button className="mt-3" variant="outline" onClick={() => refetch()} loading={isFetching}>
                        Tentar novamente
                    </Button>
                </div>
            )
        }

        return (
            <div className={cn('flex flex-col items-center py-8 text-surface-400', className)}>
                <Clock className="h-8 w-8 mb-2 opacity-40" />
                <p className="text-sm">Nenhum evento de execucao registrado</p>
            </div>
        )
    }

    return (
        <div className={cn('space-y-0', className)}>
            {events.map((event, idx) => {
                const config = EVENT_ICONS[event.event_type] || EVENT_ICONS.status_changed
                const Icon = config.icon
                const isLast = idx === events.length - 1
                const reason = event.metadata?.reason as string | undefined
                const waitTime = event.metadata?.wait_time_minutes as number | undefined
                const fromStatusLabel = event.metadata?.from_label as string | undefined
                const toStatusLabel = event.metadata?.to_label as string | undefined
                const statusNotes = event.metadata?.notes as string | undefined

                return (
                    <div key={event.id} className="flex gap-3 relative">
                        <div className="flex flex-col items-center">
                            <div className={cn('h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0', config.color)}>
                                <Icon className="h-4 w-4" />
                            </div>
                            {!isLast && <div className="w-px flex-1 bg-surface-200 min-h-[24px]" />}
                        </div>
                        <div className="pb-4 min-w-0 flex-1">
                            <p className="text-sm font-medium text-surface-900">{event.event_label}</p>
                            <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-surface-500 mt-0.5">
                                <span>{new Date(event.created_at).toLocaleString('pt-BR')}</span>
                                {event.user && <span>{event.user.name}</span>}
                                {event.latitude != null && event.longitude != null && (
                                    <a
                                        href={`https://www.google.com/maps?q=${event.latitude},${event.longitude}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-blue-500 hover:underline"
                                    >
                                        GPS
                                    </a>
                                )}
                            </div>
                            {reason && (
                                <p className="text-xs text-surface-500 mt-1 italic">Motivo: {reason}</p>
                            )}
                            {event.event_type === 'status_changed' && (fromStatusLabel || toStatusLabel) && (
                                <p className="text-xs text-surface-500 mt-1">
                                    {fromStatusLabel ? `${fromStatusLabel} -> ${toStatusLabel ?? fromStatusLabel}` : `Status: ${toStatusLabel}`}
                                </p>
                            )}
                            {statusNotes && (
                                <p className="text-xs text-surface-500 mt-1 italic">Obs: {statusNotes}</p>
                            )}
                            {waitTime != null && (
                                <p className="text-xs text-amber-600 mt-1">Tempo de espera no cliente: {waitTime}min</p>
                            )}
                            {event.event_type === 'service_completed' && event.metadata && (
                                <div className="mt-2 text-xs text-surface-600 bg-surface-50 rounded-lg p-2 space-y-1">
                                    <p>Deslocamento (ida): {(event.metadata.displacement_minutes ?? event.metadata.displacement_ida_minutes ?? 0) as number}min</p>
                                    <p>Espera: {event.metadata.wait_time_minutes as number ?? 0}min</p>
                                    <p>Servico liquido: {event.metadata.service_duration_minutes as number ?? 0}min</p>
                                    {(event.metadata.service_pause_minutes as number ?? 0) > 0 && (
                                        <p>Pausas de servico: {event.metadata.service_pause_minutes as number}min</p>
                                    )}
                                </div>
                            )}
                            {(event.event_type === 'return_arrived' || event.event_type === 'closed_no_return') && event.metadata && (
                                <div className="mt-2 text-xs text-surface-600 bg-emerald-50 rounded-lg p-2 space-y-1">
                                    <p className="font-semibold text-emerald-700">Resumo final da OS</p>
                                    <p>Deslocamento (ida): {event.metadata.displacement_ida_minutes as number ?? 0}min</p>
                                    <p>Espera no cliente: {event.metadata.wait_time_minutes as number ?? 0}min</p>
                                    <p>Servico: {event.metadata.service_duration_minutes as number ?? 0}min</p>
                                    <p>Retorno (volta): {event.metadata.return_duration_minutes as number ?? 0}min</p>
                                    <p className="font-medium border-t border-emerald-200 pt-1 mt-1">Total: {event.metadata.total_duration_minutes as number ?? 0}min</p>
                                    {event.event_type === 'closed_no_return' && event.metadata.reason != null && (
                                        <p className="italic text-surface-500">Motivo: {String(event.metadata.reason)}</p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                )
            })}
        </div>
    )
}
