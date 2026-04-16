import { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    Clock, MapPin, Play, Pause, Square, Coffee, Sun, Calendar, CheckCircle2,
    Loader2, ArrowLeft, Timer, Edit,
} from 'lucide-react'
import { cn, getApiErrorMessage } from '@/lib/utils'
import api, { unwrapData } from '@/lib/api'
import { toast } from 'sonner'
import { Modal } from '@/components/ui/modal'
import { Button } from '@/components/ui/button'
import { safeArray } from '@/lib/safe-array'
import { ComprovanteModal, ComprovanteData } from '@/components/hr/ComprovanteModal'

interface ClockStatus {
    clocked_in: boolean
    clock_in_at?: string
    on_break?: boolean
    break_started_at?: string
    location?: string
    city?: string
}

interface ClockEntry {
    id?: number
    type: 'entrada' | 'saida_almoco' | 'volta_almoco' | 'saida'
    time: string
    location?: string
}

interface MonthSummary {
    total_hours?: number
    days_worked?: number
    average_hours_per_day?: number
}

const ENTRY_LABELS: Record<string, string> = {
    entrada: 'Entrada',
    saida_almoco: 'Saída Almoço',
    volta_almoco: 'Volta Almoço',
    saida: 'Saída',
}

function formatTime(dateStr: string) {
    return new Date(dateStr).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    })
}

function formatElapsed(ms: number) {
    const h = Math.floor(ms / 3600000)
    const m = Math.floor((ms % 3600000) / 60000)
    return `${h}h ${m}m`
}

export default function TechTimeClockPage() {
    const navigate = useNavigate()
    const [status, setStatus] = useState<ClockStatus | null>(null)
    const [todayEntries, setTodayEntries] = useState<ClockEntry[]>([])
    const [monthSummary, setMonthSummary] = useState<MonthSummary | null>(null)
    const [loading, setLoading] = useState(true)
    const [actionLoading, setActionLoading] = useState<string | null>(null)
    const [location, setLocation] = useState<string | null>(null)
    const [elapsed, setElapsed] = useState(0)
    const [showAdjustModal, setShowAdjustModal] = useState(false)
    const [comprovanteData, setComprovanteData] = useState<ComprovanteData | null>(null)
    const [showComprovante, setShowComprovante] = useState(false)

    const fetchStatus = useCallback(async () => {
        try {
            const response = await api.get('/hr/advanced/clock/status')
            const s = unwrapData<Record<string, unknown>>(response)
            setStatus({
                clocked_in: Boolean(s.clocked_in ?? s.isClocked_in ?? false),
                clock_in_at: typeof (s.clock_in_at ?? s.clocked_in_at) === 'string' ? String(s.clock_in_at ?? s.clocked_in_at) : undefined,
                on_break: Boolean(s.on_break ?? s.is_on_break ?? false),
                break_started_at: typeof s.break_started_at === 'string' ? s.break_started_at : undefined,
                location: typeof (s.location ?? s.address) === 'string' ? String(s.location ?? s.address) : undefined,
                city: typeof s.city === 'string' ? s.city : undefined,
            })
        } catch {
            setStatus({ clocked_in: false })
        }
    }, [])

    const fetchTodayEntries = useCallback(async () => {
        try {
            const today = new Date().toISOString().slice(0, 10)
            const response = await api.get('/hr/clock/my', {
                params: { month: today.slice(0, 7), date: today },
            })
            const payload = unwrapData<{ entries?: ClockEntry[] } | ClockEntry[]>(response)
            setTodayEntries(safeArray<ClockEntry>(Array.isArray(payload) ? payload : payload?.entries ?? []))
        } catch {
            setTodayEntries([])
        }
    }, [])

    const fetchMonthSummary = useCallback(async () => {
        try {
            const month = new Date().toISOString().slice(0, 7)
            const response = await api.get('/hr/clock/my', { params: { month } })
            const payload = unwrapData<Record<string, unknown>>(response)
            const summaryPayload = payload.summary
            const s = summaryPayload && typeof summaryPayload === 'object'
                ? (summaryPayload as Record<string, unknown>)
                : payload
            setMonthSummary({
                total_hours: Number(s?.total_hours ?? s?.hours_worked ?? 0),
                days_worked: Number(s?.days_worked ?? s?.days ?? 0),
                average_hours_per_day: Number(s?.average_hours_per_day ?? s?.avg_hours ?? 0),
            })
        } catch {
            setMonthSummary(null)
        }
    }, [])

    useEffect(() => {
        async function load() {
            setLoading(true)
            await Promise.all([fetchStatus(), fetchTodayEntries(), fetchMonthSummary()])
            setLoading(false)
        }
        load()
    }, [fetchStatus, fetchTodayEntries, fetchMonthSummary])

    useEffect(() => {
        if (!navigator.geolocation) return
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const { latitude, longitude } = pos.coords
                setLocation(`${latitude.toFixed(4)}, ${longitude.toFixed(4)}`)
            },
            () => setLocation(null)
        )
    }, [])

    useEffect(() => {
        if (!status?.clocked_in || !status.clock_in_at) {
            setElapsed(0)
            return
        }
        const update = () => {
            const start = new Date(status.clock_in_at!).getTime()
            setElapsed(Date.now() - start)
        }
        update()
        const id = setInterval(update, 60000)
        return () => clearInterval(id)
    }, [status?.clocked_in, status?.clock_in_at])

    const getCoords = (): Promise<{ latitude: number; longitude: number; accuracy: number; altitude: number | null; speed: number | null }> =>
        new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                (p) => resolve({
                    latitude: p.coords.latitude,
                    longitude: p.coords.longitude,
                    accuracy: p.coords.accuracy,
                    altitude: p.coords.altitude,
                    speed: p.coords.speed
                }),
                reject
            )
        })

    const handleClockIn = async () => {
        setActionLoading('clock_in')
        try {
            const coords = await getCoords().catch(() => ({ latitude: 0, longitude: 0, accuracy: 0, altitude: null, speed: null }))
            const res = await api.post('/hr/advanced/clock-in', coords)
            toast.success('Entrada registrada')
            const payload = unwrapData<{ comprovante?: ComprovanteData }>(res)
            if (payload?.comprovante) {
                setComprovanteData(payload.comprovante)
                setShowComprovante(true)
            }
            await fetchStatus()
            await fetchTodayEntries()
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao registrar entrada'))
        } finally {
            setActionLoading(null)
        }
    }

    const handleClockOut = async () => {
        setActionLoading('clock_out')
        try {
            const coords = await getCoords().catch(() => ({ latitude: 0, longitude: 0, accuracy: 0, altitude: null, speed: null }))
            const res = await api.post('/hr/advanced/clock-out', coords)
            toast.success('Saída registrada')
            const payload = unwrapData<{ comprovante?: ComprovanteData }>(res)
            if (payload?.comprovante) {
                setComprovanteData(payload.comprovante)
                setShowComprovante(true)
            }
            await fetchStatus()
            await fetchTodayEntries()
            await fetchMonthSummary()
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao registrar saída'))
        } finally {
            setActionLoading(null)
        }
    }

    const handleBreakStart = async () => {
        setActionLoading('break_start')
        try {
            const coords = await getCoords().catch(() => ({ latitude: 0, longitude: 0, accuracy: 0, altitude: null, speed: null }))
            const res = await api.post('/hr/advanced/break-start', coords)
            toast.success('Saída para almoço registrada')
            const payload = unwrapData<{ comprovante?: ComprovanteData }>(res)
            if (payload?.comprovante) {
                setComprovanteData(payload.comprovante)
                setShowComprovante(true)
            }
            await fetchStatus()
            await fetchTodayEntries()
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao registrar saída almoço'))
        } finally {
            setActionLoading(null)
        }
    }

    const handleBreakEnd = async () => {
        setActionLoading('break_end')
        try {
            const coords = await getCoords().catch(() => ({ latitude: 0, longitude: 0, accuracy: 0, altitude: null, speed: null }))
            const res = await api.post('/hr/advanced/break-end', coords)
            toast.success('Volta do almoço registrada')
            const payload = unwrapData<{ comprovante?: ComprovanteData }>(res)
            if (payload?.comprovante) {
                setComprovanteData(payload.comprovante)
                setShowComprovante(true)
            }
            await fetchStatus()
            await fetchTodayEntries()
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao registrar volta almoço'))
        } finally {
            setActionLoading(null)
        }
    }

    const handleRequestAdjust = () => setShowAdjustModal(true)

    const locationDisplay = status?.city ?? status?.location ?? location ?? 'Localização não disponível'

    if (loading) {
        return (
            <div className="flex flex-col h-full">
                <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => navigate('/tech')}
                            className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                            aria-label="Voltar"
                        >
                            <ArrowLeft className="w-5 h-5 text-surface-600" />
                        </button>
                        <h1 className="text-lg font-bold text-foreground">
                            Ponto Eletrônico
                        </h1>
                    </div>
                </div>
                <div className="flex-1 overflow-y-auto flex items-center justify-center">
                    <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                </div>
            </div>
        )
    }

    return (
        <div className="flex flex-col h-full">
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => navigate('/tech')}
                        className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                        aria-label="Voltar"
                    >
                        <ArrowLeft className="w-5 h-5 text-surface-600" />
                    </button>
                    <h1 className="text-lg font-bold text-foreground">
                        Ponto Eletrônico
                    </h1>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                <div
                    className={cn(
                        'rounded-xl p-5',
                        status?.clocked_in && !status?.on_break
                            ? 'bg-emerald-50 border border-emerald-200'
                            : 'bg-card'
                    )}
                >
                    {status?.clocked_in && !status?.on_break ? (
                        <>
                            <div className="flex items-center gap-2 text-emerald-700 mb-1">
                                <CheckCircle2 className="w-5 h-5" />
                                <span className="font-semibold">Em serviço</span>
                            </div>
                            <p className="text-sm text-emerald-600 dark:text-emerald-500">
                                Desde {status.clock_in_at ? formatTime(status.clock_in_at) : '—'}
                            </p>
                            <div className="flex items-center gap-2 mt-3">
                                <Timer className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                <span className="text-2xl font-bold text-emerald-800 dark:text-emerald-300">
                                    {formatElapsed(elapsed)}
                                </span>
                            </div>
                        </>
                    ) : (
                        <div className="flex items-center gap-2 text-surface-600">
                            <Pause className="w-5 h-5" />
                            <span className="font-medium">Fora de serviço</span>
                        </div>
                    )}
                    <div className="flex items-center gap-1.5 mt-3 text-xs text-surface-500">
                        <MapPin className="w-3.5 h-3.5" />
                        {locationDisplay}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-2">
                    {!status?.clocked_in && (
                        <button
                            onClick={handleClockIn}
                            disabled={!!actionLoading}
                            className="flex items-center justify-center gap-2 py-3 bg-emerald-600 text-white rounded-xl font-medium disabled:opacity-50"
                        >
                            {actionLoading === 'clock_in' ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <Play className="w-5 h-5" />
                            )}
                            Entrada
                        </button>
                    )}
                    {status?.clocked_in && !status?.on_break && (
                        <button
                            onClick={handleBreakStart}
                            disabled={!!actionLoading}
                            className="flex items-center justify-center gap-2 py-3 bg-amber-500 text-white rounded-xl font-medium disabled:opacity-50"
                        >
                            {actionLoading === 'break_start' ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <Coffee className="w-5 h-5" />
                            )}
                            Saída Almoço
                        </button>
                    )}
                    {status?.on_break && (
                        <button
                            onClick={handleBreakEnd}
                            disabled={!!actionLoading}
                            className="flex items-center justify-center gap-2 py-3 bg-blue-600 text-white rounded-xl font-medium disabled:opacity-50"
                        >
                            {actionLoading === 'break_end' ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <Sun className="w-5 h-5" />
                            )}
                            Volta Almoço
                        </button>
                    )}
                    {status?.clocked_in && (
                        <button
                            onClick={handleClockOut}
                            disabled={!!actionLoading}
                            className="flex items-center justify-center gap-2 py-3 bg-red-600 text-white rounded-xl font-medium disabled:opacity-50"
                        >
                            {actionLoading === 'clock_out' ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <Square className="w-5 h-5" />
                            )}
                            Saída
                        </button>
                    )}
                </div>

                <div className="bg-card rounded-xl p-4">
                    <h3 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-3">
                        <Clock className="w-4 h-4" />
                        Registros de hoje
                    </h3>
                    {todayEntries.length === 0 ? (
                        <p className="text-sm text-surface-500">Nenhum registro hoje</p>
                    ) : (
                        <div className="space-y-2">
                            {(todayEntries || []).map((e, i) => (
                                <div
                                    key={i}
                                    className="flex items-center justify-between py-2 border-b border-surface-100 last:border-0"
                                >
                                    <span className="text-sm text-surface-700">
                                        {ENTRY_LABELS[e.type] ?? e.type}
                                    </span>
                                    <div className="text-right">
                                        <span className="text-sm font-medium text-foreground">
                                            {formatTime(e.time)}
                                        </span>
                                        {e.location && (
                                            <p className="text-xs text-surface-500">({e.location})</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="bg-card rounded-xl p-4">
                    <h3 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-3">
                        <Calendar className="w-4 h-4" />
                        Resumo do mês
                    </h3>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <p className="text-xs text-surface-500">Horas trabalhadas</p>
                            <p className="text-lg font-bold text-foreground">
                                {monthSummary?.total_hours?.toFixed(1) ?? '0'}h
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-surface-500">Dias trabalhados</p>
                            <p className="text-lg font-bold text-foreground">
                                {monthSummary?.days_worked ?? 0}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-surface-500">Média/dia</p>
                            <p className="text-lg font-bold text-foreground">
                                {monthSummary?.average_hours_per_day?.toFixed(1) ?? '0'}h
                            </p>
                        </div>
                    </div>
                </div>

                <button
                    onClick={handleRequestAdjust}
                    className="w-full flex items-center justify-center gap-2 py-2.5 border border-surface-200 rounded-xl text-sm font-medium text-surface-700 hover:bg-surface-50 dark:hover:bg-surface-700 transition-colors"
                >
                    <Edit className="w-4 h-4" />
                    Solicitar Ajuste
                </button>
            </div>

            <Modal
                open={showAdjustModal}
                onOpenChange={setShowAdjustModal}
                title="Solicitar ajuste de ponto"
                footer={
                    <div className="flex justify-end">
                        <Button variant="outline" onClick={() => setShowAdjustModal(false)}>Fechar</Button>
                    </div>
                }
            >
                <p className="text-sm text-surface-600">
                    Para solicitar correção ou ajuste de ponto, entre em contato com o setor de RH (recursos humanos) da empresa.
                </p>
            </Modal>

            <ComprovanteModal
                open={showComprovante}
                onOpenChange={setShowComprovante}
                data={comprovanteData}
            />
        </div>
    )
}
