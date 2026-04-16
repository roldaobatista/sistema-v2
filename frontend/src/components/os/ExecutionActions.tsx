import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Navigation, MapPin, Play, Pause, Coffee, CheckCircle2,
    Loader2, AlertTriangle, Undo2, Home, SkipForward,
} from 'lucide-react'
import { workOrderApi } from '@/lib/work-order-api'
import { queryKeys } from '@/lib/query-keys'
import { toast } from 'sonner'
import { cn, getApiErrorMessage } from '@/lib/utils'

interface ExecutionActionsProps {
    workOrderId: number
    status: string
    onStatusChange?: () => void
    className?: string
    canExecute?: boolean
    blockedMessage?: string
}

const PAUSE_REASONS = [
    { value: 'lunch', label: 'Almoço' },
    { value: 'fueling', label: 'Abastecimento' },
    { value: 'technical_stop', label: 'Parada Técnica' },
    { value: 'br_stop', label: 'Parada na BR' },
    { value: 'hotel', label: 'Hotel/Pernoite' },
    { value: 'other', label: 'Outro' },
]

const SERVICEPause_REASONS = [
    { value: 'Aguardando peça', label: 'Aguardando Peça' },
    { value: 'Aguardando operador', label: 'Aguardando Operador' },
    { value: 'Almoço', label: 'Almoço' },
    { value: 'Chuva', label: 'Chuva' },
    { value: 'Outro', label: 'Outro' },
]

const RETURN_DESTINATIONS = [
    { value: 'base', label: 'Base da Empresa' },
    { value: 'hotel', label: 'Hotel / Pernoite' },
    { value: 'next_client', label: 'Próximo Cliente' },
    { value: 'other', label: 'Outro' },
]

function getGps(): Promise<{ latitude: number; longitude: number } | null> {
    return new Promise((resolve) => {
        if (!navigator.geolocation) {
            resolve(null)
            return
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        )
    })
}

export function ExecutionActions({
    workOrderId,
    status,
    onStatusChange,
    className,
    canExecute = true,
    blockedMessage = 'Voce nao pode executar o fluxo desta OS.',
}: ExecutionActionsProps) {
    const queryClient = useQueryClient()
    const [showPauseModal, setShowPauseModal] = useState(false)
    const [showServicePauseModal, setShowServicePauseModal] = useState(false)
    const [pauseReason, setPauseReason] = useState('')
    const [pauseStopType, setPauseStopType] = useState('other')
    const [servicePauseReason, setServicePauseReason] = useState('')
    const [showFinalizeModal, setShowFinalizeModal] = useState(false)
    const [technicalReport, setTechnicalReport] = useState('')
    const [showReturnModal, setShowReturnModal] = useState(false)
    const [returnDestination, setReturnDestination] = useState('base')
    const [showReturnPauseModal, setShowReturnPauseModal] = useState(false)
    const [returnPauseReason, setReturnPauseReason] = useState('')
    const [returnPauseStopType, setReturnPauseStopType] = useState('other')
    const [showCloseNoReturnModal, setShowCloseNoReturnModal] = useState(false)
    const [closeNoReturnReason, setCloseNoReturnReason] = useState('')

    const invalidate = () => {
        queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
        queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.all })
        queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.dashboardStats })
        queryClient.invalidateQueries({ queryKey: ['wo-execution-timeline', workOrderId] })
        onStatusChange?.()
    }

    const executionMutation = useMutation({
        mutationFn: async ({ action, data }: { action: string; data?: Record<string, unknown> }) => {
            const gps = await getGps()
            const payload = { ...data, ...(gps ?? {}) }
            const res = await workOrderApi.executionAction(workOrderId, action, payload)
            return res.data
        },
        onSuccess: (data) => {
            toast.success(data?.message || 'Ação executada')
            invalidate()
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao executar ação'))
        },
    })

    const isLoading = executionMutation.isPending

    const actionButton = (
        label: string,
        action: string,
        icon: React.ReactNode,
        variant: 'primary' | 'warning' | 'success' | 'danger' = 'primary',
        data?: Record<string, unknown>,
        onClick?: () => void
    ) => {
        const colors = {
            primary: 'bg-blue-600 hover:bg-blue-700 text-white',
            warning: 'bg-amber-500 hover:bg-amber-600 text-white',
            success: 'bg-emerald-600 hover:bg-emerald-700 text-white',
            danger: 'bg-red-500 hover:bg-red-600 text-white',
        }

        return (
            <button
                key={action}
                disabled={isLoading}
                onClick={() => onClick ? onClick() : executionMutation.mutate({ action, data })}
                className={cn(
                    'flex items-center gap-2 px-4 py-3 rounded-xl font-medium text-sm transition-all shadow-sm',
                    'disabled:opacity-50 disabled:cursor-not-allowed',
                    colors[variant],
                )}
            >
                {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : icon}
                {label}
            </button>
        )
    }

    const normalizedStatus = status === 'in_progress' ? 'in_service' : status

    if (!canExecute) {
        return (
            <div className={cn('space-y-3', className)}>
                <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-surface-500">
                    <AlertTriangle className="h-3.5 w-3.5" />
                    Execucao em Campo
                </div>
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {blockedMessage}
                </div>
            </div>
        )
    }

    const renderActions = () => {
        switch (normalizedStatus) {
            case 'open':
            case 'awaiting_dispatch':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Iniciar Deslocamento', 'start-displacement', <Navigation className="h-4 w-4" />)}
                    </div>
                )

            case 'in_displacement':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Pausar Deslocamento', 'pause-displacement', <Coffee className="h-4 w-4" />, 'warning', undefined, () => setShowPauseModal(true))}
                        {actionButton('Cheguei no Cliente', 'arrive', <MapPin className="h-4 w-4" />, 'success')}
                    </div>
                )

            case 'displacement_paused':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Retomar Deslocamento', 'resume-displacement', <Play className="h-4 w-4" />)}
                    </div>
                )

            case 'at_client':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Iniciar Serviço', 'start-service', <Play className="h-4 w-4" />, 'success')}
                    </div>
                )

            case 'in_service':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Pausar Serviço', 'pause-service', <Pause className="h-4 w-4" />, 'warning', undefined, () => setShowServicePauseModal(true))}
                        {actionButton('Finalizar Serviço', 'finalize', <CheckCircle2 className="h-4 w-4" />, 'success', undefined, () => setShowFinalizeModal(true))}
                    </div>
                )

            case 'service_paused':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Retomar Serviço', 'resume-service', <Play className="h-4 w-4" />)}
                    </div>
                )

            case 'awaiting_return':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Iniciar Retorno', 'start-return', <Undo2 className="h-4 w-4" />, 'primary', undefined, () => setShowReturnModal(true))}
                        {actionButton('Encerrar OS (sem retorno)', 'close-without-return', <SkipForward className="h-4 w-4" />, 'warning', undefined, () => setShowCloseNoReturnModal(true))}
                    </div>
                )

            case 'in_return':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Pausar Retorno', 'pause-return', <Coffee className="h-4 w-4" />, 'warning', undefined, () => setShowReturnPauseModal(true))}
                        {actionButton('Cheguei no Destino', 'arrive-return', <Home className="h-4 w-4" />, 'success')}
                    </div>
                )

            case 'return_paused':
                return (
                    <div className="flex flex-wrap gap-3">
                        {actionButton('Retomar Retorno', 'resume-return', <Play className="h-4 w-4" />)}
                    </div>
                )

            default:
                return null
        }
    }

    const actions = renderActions()
    if (!actions) return null

    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-surface-500">
                <AlertTriangle className="h-3.5 w-3.5" />
                Execução em Campo
            </div>
            {actions}

            {showPauseModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-surface-800 rounded-xl p-6 max-w-sm w-full space-y-4 shadow-xl">
                        <h3 className="font-semibold text-surface-900 dark:text-white">Pausar Deslocamento</h3>
                        <div>
                            <label className="block text-xs text-surface-500 mb-1">Tipo de parada</label>
                            <select
                                aria-label="Tipo de parada"
                                value={pauseStopType}
                                onChange={(e) => setPauseStopType(e.target.value)}
                                className="w-full rounded-lg border border-default px-3 py-2 text-sm bg-surface-0"
                            >
                                {PAUSE_REASONS.map((r) => (
                                    <option key={r.value} value={r.value}>{r.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs text-surface-500 mb-1">Motivo</label>
                            <input
                                type="text"
                                value={pauseReason}
                                onChange={(e) => setPauseReason(e.target.value)}
                                placeholder="Descreva o motivo..."
                                className="w-full rounded-lg border border-default px-3 py-2 text-sm bg-surface-0"
                            />
                        </div>
                        <div className="flex gap-2 justify-end">
                            <button onClick={() => setShowPauseModal(false)} className="px-3 py-2 text-sm rounded-lg border border-default">
                                Cancelar
                            </button>
                            <button
                                disabled={!pauseReason.trim() || isLoading}
                                onClick={() => {
                                    executionMutation.mutate({
                                        action: 'pause-displacement',
                                        data: { reason: pauseReason, stop_type: pauseStopType },
                                    })
                                    setShowPauseModal(false)
                                    setPauseReason('')
                                }}
                                className="px-3 py-2 text-sm rounded-lg bg-amber-500 text-white font-medium disabled:opacity-50"
                            >
                                Confirmar Pausa
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showServicePauseModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-surface-800 rounded-xl p-6 max-w-sm w-full space-y-4 shadow-xl">
                        <h3 className="font-semibold text-surface-900 dark:text-white">Pausar Serviço</h3>
                        <div>
                            <label className="block text-xs text-surface-500 mb-1">Motivo</label>
                            <select
                                aria-label="Motivo da pausa"
                                value={servicePauseReason}
                                onChange={(e) => setServicePauseReason(e.target.value)}
                                className="w-full rounded-lg border border-default px-3 py-2 text-sm bg-surface-0"
                            >
                                <option value="">Selecione...</option>
                                {SERVICEPause_REASONS.map((r) => (
                                    <option key={r.value} value={r.value}>{r.label}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex gap-2 justify-end">
                            <button onClick={() => setShowServicePauseModal(false)} className="px-3 py-2 text-sm rounded-lg border border-default">
                                Cancelar
                            </button>
                            <button
                                disabled={!servicePauseReason || isLoading}
                                onClick={() => {
                                    executionMutation.mutate({
                                        action: 'pause-service',
                                        data: { reason: servicePauseReason },
                                    })
                                    setShowServicePauseModal(false)
                                    setServicePauseReason('')
                                }}
                                className="px-3 py-2 text-sm rounded-lg bg-amber-500 text-white font-medium disabled:opacity-50"
                            >
                                Confirmar Pausa
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showFinalizeModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-surface-800 rounded-xl p-6 max-w-md w-full space-y-4 shadow-xl">
                        <h3 className="font-semibold text-surface-900 dark:text-white">Finalizar Serviço</h3>
                        <p className="text-xs text-surface-500">Após finalizar, você poderá iniciar o retorno ou encerrar a OS.</p>
                        <div>
                            <label className="block text-xs text-surface-500 mb-1">Relatório Técnico (opcional)</label>
                            <textarea
                                value={technicalReport}
                                onChange={(e) => setTechnicalReport(e.target.value)}
                                rows={4}
                                placeholder="Descreva o que foi feito..."
                                className="w-full rounded-lg border border-default px-3 py-2 text-sm bg-surface-0 resize-none"
                            />
                        </div>
                        <div className="flex gap-2 justify-end">
                            <button onClick={() => setShowFinalizeModal(false)} className="px-3 py-2 text-sm rounded-lg border border-default">
                                Cancelar
                            </button>
                            <button
                                disabled={isLoading}
                                onClick={() => {
                                    executionMutation.mutate({
                                        action: 'finalize',
                                        data: { technical_report: technicalReport || undefined },
                                    })
                                    setShowFinalizeModal(false)
                                    setTechnicalReport('')
                                }}
                                className="px-3 py-2 text-sm rounded-lg bg-emerald-600 text-white font-medium disabled:opacity-50"
                            >
                                Finalizar Serviço
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showReturnModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-surface-800 rounded-xl p-6 max-w-sm w-full space-y-4 shadow-xl">
                        <h3 className="font-semibold text-surface-900 dark:text-white">Iniciar Retorno</h3>
                        <div>
                            <label className="block text-xs text-surface-500 mb-1">Destino do retorno</label>
                            <select
                                aria-label="Destino do retorno"
                                value={returnDestination}
                                onChange={(e) => setReturnDestination(e.target.value)}
                                className="w-full rounded-lg border border-default px-3 py-2 text-sm bg-surface-0"
                            >
                                {RETURN_DESTINATIONS.map((d) => (
                                    <option key={d.value} value={d.value}>{d.label}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex gap-2 justify-end">
                            <button onClick={() => setShowReturnModal(false)} className="px-3 py-2 text-sm rounded-lg border border-default">
                                Cancelar
                            </button>
                            <button
                                disabled={isLoading}
                                onClick={() => {
                                    executionMutation.mutate({
                                        action: 'start-return',
                                        data: { destination: returnDestination },
                                    })
                                    setShowReturnModal(false)
                                }}
                                className="px-3 py-2 text-sm rounded-lg bg-blue-600 text-white font-medium disabled:opacity-50"
                            >
                                Iniciar Retorno
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showReturnPauseModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-surface-800 rounded-xl p-6 max-w-sm w-full space-y-4 shadow-xl">
                        <h3 className="font-semibold text-surface-900 dark:text-white">Pausar Retorno</h3>
                        <div>
                            <label className="block text-xs text-surface-500 mb-1">Tipo de parada</label>
                            <select
                                aria-label="Tipo de parada do retorno"
                                value={returnPauseStopType}
                                onChange={(e) => setReturnPauseStopType(e.target.value)}
                                className="w-full rounded-lg border border-default px-3 py-2 text-sm bg-surface-0"
                            >
                                {PAUSE_REASONS.map((r) => (
                                    <option key={r.value} value={r.value}>{r.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs text-surface-500 mb-1">Motivo</label>
                            <input
                                type="text"
                                value={returnPauseReason}
                                onChange={(e) => setReturnPauseReason(e.target.value)}
                                placeholder="Descreva o motivo..."
                                className="w-full rounded-lg border border-default px-3 py-2 text-sm bg-surface-0"
                            />
                        </div>
                        <div className="flex gap-2 justify-end">
                            <button onClick={() => setShowReturnPauseModal(false)} className="px-3 py-2 text-sm rounded-lg border border-default">
                                Cancelar
                            </button>
                            <button
                                disabled={!returnPauseReason.trim() || isLoading}
                                onClick={() => {
                                    executionMutation.mutate({
                                        action: 'pause-return',
                                        data: { reason: returnPauseReason, stop_type: returnPauseStopType },
                                    })
                                    setShowReturnPauseModal(false)
                                    setReturnPauseReason('')
                                }}
                                className="px-3 py-2 text-sm rounded-lg bg-amber-500 text-white font-medium disabled:opacity-50"
                            >
                                Confirmar Pausa
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showCloseNoReturnModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-surface-800 rounded-xl p-6 max-w-sm w-full space-y-4 shadow-xl">
                        <h3 className="font-semibold text-surface-900 dark:text-white">Encerrar OS sem Retorno</h3>
                        <p className="text-xs text-surface-500">A OS será finalizada sem registrar o deslocamento de volta.</p>
                        <div>
                            <label className="block text-xs text-surface-500 mb-1">Motivo (opcional)</label>
                            <input
                                type="text"
                                value={closeNoReturnReason}
                                onChange={(e) => setCloseNoReturnReason(e.target.value)}
                                placeholder="Ex: indo para próximo atendimento..."
                                className="w-full rounded-lg border border-default px-3 py-2 text-sm bg-surface-0"
                            />
                        </div>
                        <div className="flex gap-2 justify-end">
                            <button onClick={() => setShowCloseNoReturnModal(false)} className="px-3 py-2 text-sm rounded-lg border border-default">
                                Cancelar
                            </button>
                            <button
                                disabled={isLoading}
                                onClick={() => {
                                    executionMutation.mutate({
                                        action: 'close-without-return',
                                        data: { reason: closeNoReturnReason || undefined },
                                    })
                                    setShowCloseNoReturnModal(false)
                                    setCloseNoReturnReason('')
                                }}
                                className="px-3 py-2 text-sm rounded-lg bg-amber-500 text-white font-medium disabled:opacity-50"
                            >
                                Encerrar OS
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
