import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Play, Square, Clock, Wrench, Truck, Coffee, Settings } from 'lucide-react'
import { workOrderApi } from '@/lib/work-order-api'
import { cn, getApiErrorMessage } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'

const activityTypes = [
    { key: 'work', label: 'Trabalho', icon: Wrench, color: 'emerald' },
    { key: 'travel', label: 'Deslocamento', icon: Truck, color: 'sky' },
    { key: 'setup', label: 'Preparacao', icon: Settings, color: 'amber' },
    { key: 'pause', label: 'Pausa', icon: Coffee, color: 'surface' },
] as const

interface TimeLog {
    id: number
    user_id: number
    started_at: string
    ended_at: string | null
    duration_seconds: number | null
    activity_type: string
    description: string | null
    user?: { id: number; name: string }
}

interface ExecutionTimerProps {
    workOrderId: number
    status: string
}

function formatDuration(seconds: number): string {
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = seconds % 60
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

export default function ExecutionTimer({ workOrderId, status }: ExecutionTimerProps) {
    const qc = useQueryClient()
    const [elapsed, setElapsed] = useState(0)
    const [selectedType, setSelectedType] = useState<string>('work')
    const currentUserId = useAuthStore((state) => state.user?.id ?? 0)

    const { data: logsRes } = useQuery({
        queryKey: ['work-order-time-logs', workOrderId],
        queryFn: () => workOrderApi.timeLogs(workOrderId),
    })
    const logs: TimeLog[] = logsRes?.data?.data ?? []
    const ownLogs = logs.filter((log) => log.user_id === currentUserId)

    const activeLog = ownLogs.find((log) => !log.ended_at)

    useEffect(() => {
        if (!activeLog) {
            setElapsed(0)
            return
        }

        const startTime = new Date(activeLog.started_at).getTime()
        const tick = () => setElapsed(Math.floor((Date.now() - startTime) / 1000))
        tick()
        const interval = setInterval(tick, 1000)
        return () => clearInterval(interval)
    }, [activeLog])

    const startMut = useMutation({
        mutationFn: () => workOrderApi.startTimeLog({
            work_order_id: workOrderId,
            activity_type: selectedType,
        }),
        onSuccess: () => {
            toast.success('Timer iniciado')
            qc.invalidateQueries({ queryKey: ['work-order-time-logs', workOrderId] })
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao iniciar timer')),
    })

    const stopMut = useMutation({
        mutationFn: (logId: number) => workOrderApi.stopTimeLog(logId),
        onSuccess: () => {
            toast.success('Timer parado')
            qc.invalidateQueries({ queryKey: ['work-order-time-logs', workOrderId] })
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao parar timer')),
    })

    const totalSeconds = ownLogs.reduce((sum, log) => sum + (log.duration_seconds ?? 0), 0) + (activeLog ? elapsed : 0)

    const breakdown = activityTypes
        .map((activityType) => {
            const seconds = ownLogs
                .filter((log) => log.activity_type === activityType.key && log.duration_seconds)
                .reduce((sum, log) => sum + (log.duration_seconds ?? 0), 0) + (activeLog?.activity_type === activityType.key ? elapsed : 0)

            return { ...activityType, seconds }
        })
        .filter((item) => item.seconds > 0)

    const canStart = [
        'in_displacement',
        'displacement_paused',
        'at_client',
        'in_service',
        'service_paused',
        'waiting_parts',
        'waiting_approval',
        'awaiting_return',
        'in_return',
        'return_paused',
        'in_progress',
    ].includes(status)

    return (
        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
            <h3 className="text-sm font-semibold text-surface-900 mb-3 flex items-center gap-2">
                <Clock className="h-4 w-4 text-brand-500" />
                Timer de Execucao
            </h3>

            <div className="text-center mb-4">
                <p className={cn(
                    'text-3xl font-mono font-bold tabular-nums transition-colors',
                    activeLog ? 'text-emerald-600' : 'text-surface-400'
                )}>
                    {formatDuration(activeLog ? elapsed : 0)}
                </p>
                {activeLog && (
                    <p className="text-xs text-surface-500 mt-1">
                        {activityTypes.find((item) => item.key === activeLog.activity_type)?.label ?? activeLog.activity_type}
                    </p>
                )}
            </div>

            {!activeLog && canStart && (
                <div className="flex gap-1.5 mb-3 justify-center flex-wrap">
                    {activityTypes.map((activityType) => {
                        const Icon = activityType.icon
                        return (
                            <button
                                key={activityType.key}
                                onClick={() => setSelectedType(activityType.key)}
                                className={cn(
                                    'flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all border',
                                    selectedType === activityType.key
                                        ? 'border-brand-300 bg-brand-50 text-brand-700'
                                        : 'border-transparent bg-surface-100 text-surface-500 hover:bg-surface-200'
                                )}
                            >
                                <Icon className="h-3 w-3" />
                                {activityType.label}
                            </button>
                        )
                    })}
                </div>
            )}

            <div className="flex gap-2 justify-center">
                {activeLog ? (
                    <Button
                        variant="danger"
                        size="sm"
                        onClick={() => stopMut.mutate(activeLog.id)}
                        loading={stopMut.isPending}
                        icon={<Square className="h-3.5 w-3.5" />}
                    >
                        Parar
                    </Button>
                ) : canStart ? (
                    <Button
                        size="sm"
                        onClick={() => startMut.mutate()}
                        loading={startMut.isPending}
                        icon={<Play className="h-3.5 w-3.5" />}
                    >
                        Iniciar
                    </Button>
                ) : null}
            </div>

            {totalSeconds > 0 && (
                <div className="mt-4 pt-3 border-t border-subtle">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-medium text-surface-600">Tempo Total</span>
                        <span className="text-sm font-bold text-surface-900">{formatDuration(totalSeconds)}</span>
                    </div>
                    {breakdown.length > 0 && (
                        <div className="space-y-1">
                            {breakdown.map((item) => {
                                const Icon = item.icon
                                return (
                                    <div key={item.key} className="flex items-center justify-between text-xs">
                                        <span className="flex items-center gap-1 text-surface-500">
                                            <Icon className="h-3 w-3" /> {item.label}
                                        </span>
                                        <span className="font-mono text-surface-700">{formatDuration(item.seconds)}</span>
                                    </div>
                                )
                            })}
                        </div>
                    )}
                </div>
            )}

            {ownLogs.filter((log) => log.ended_at).length > 0 && (
                <div className="mt-3 pt-3 border-t border-subtle">
                    <p className="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-2">Registros</p>
                    <div className="space-y-1.5 max-h-32 overflow-y-auto">
                        {ownLogs.filter((log) => log.ended_at).slice(0, 5).map((log) => (
                            <div key={log.id} className="flex items-center justify-between text-[11px] text-surface-500">
                                <span>
                                    {activityTypes.find((item) => item.key === log.activity_type)?.label ?? log.activity_type}
                                    {log.user && <span className="ml-1 text-surface-400">- {log.user.name}</span>}
                                </span>
                                <span className="font-mono">{formatDuration(log.duration_seconds ?? 0)}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    )
}
