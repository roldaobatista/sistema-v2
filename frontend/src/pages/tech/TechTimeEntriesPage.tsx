import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    ArrowLeft, Clock, Play, Square, Loader2, Timer, CheckCircle2, History, MapPin
} from 'lucide-react'
import { cn, getApiErrorMessage } from '@/lib/utils'
import api from '@/lib/api'
import { toast } from 'sonner'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

interface TimeClockEntry {
    id: number
    user_id: number
    clock_in: string
    clock_out: string | null
    type: string
    latitude_in?: number
    longitude_in?: number
    latitude_out?: number
    longitude_out?: number
    notes?: string
}

const clockInSchema = z.object({
    type: z.enum(['regular', 'overtime', 'travel'], { required_error: 'Selecione o tipo de apontamento' }),
})
type ClockInData = z.infer<typeof clockInSchema>

const clockOutSchema = z.object({
    notes: z.string().optional(),
})
type ClockOutData = z.infer<typeof clockOutSchema>

export default function TechTimeEntriesPage() {
    const navigate = useNavigate()
    const [entries, setEntries] = useState<TimeClockEntry[]>([])
    const [loading, setLoading] = useState(true)
    const [clocking, setClocking] = useState(false)
    const [elapsed, setElapsed] = useState('00:00:00')
    const timerRef = useRef<ReturnType<typeof setInterval>>(undefined)

    // Zod Forms
    const inForm = useForm<ClockInData>({
        resolver: zodResolver(clockInSchema),
        defaultValues: { type: 'regular' }
    })

    const outForm = useForm<ClockOutData>({
        resolver: zodResolver(clockOutSchema),
        defaultValues: { notes: '' }
    })

    const activeEntry = entries.find(e => !e.clock_out)
    const isClockedIn = !!activeEntry

    useEffect(() => {
        fetchData()
    }, [])

    useEffect(() => {
        if (activeEntry) {
            const updateElapsed = () => {
                const diff = Date.now() - new Date(activeEntry.clock_in).getTime()
                const h = Math.floor(diff / 3600000)
                const m = Math.floor((diff % 3600000) / 60000)
                const s = Math.floor((diff % 60000) / 1000)
                setElapsed(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`)
            }
            updateElapsed()
            timerRef.current = setInterval(updateElapsed, 1000)
            return () => clearInterval(timerRef.current)
        } else {
            setElapsed('00:00:00')
        }
    }, [activeEntry])

    async function fetchData() {
        setLoading(true)
        try {
            const res = await api.get('/hr/clock/my', { params: { per_page: 50 } })
            const data = (res.data as { data?: { data?: TimeClockEntry[] } })?.data?.data ??
                         (res.data as { data?: TimeClockEntry[] })?.data ?? []
            setEntries(data)
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao carregar histórico do ponto'))
        } finally {
            setLoading(false)
        }
    }

    const getPosition = (): Promise<{ latitude: number, longitude: number } | null> => {
        return new Promise((resolve) => {
            if (!navigator.geolocation) return resolve(null)
            navigator.geolocation.getCurrentPosition(
                (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
                () => resolve(null),
                { timeout: 8000 }
            )
        })
    }

    const onClockInSubmit = async (data: ClockInData) => {
        setClocking(true)
        try {
            const pos = await getPosition()
            await api.post('/hr/clock/in', {
                type: data.type,
                latitude: pos?.latitude,
                longitude: pos?.longitude,
            })
            toast.success('Ponto registrado: Entrada')
            inForm.reset()
            fetchData()
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao registrar entrada'))
        } finally {
            setClocking(false)
        }
    }

    const onClockOutSubmit = async (data: ClockOutData) => {
        if (!activeEntry) return
        setClocking(true)
        try {
            const pos = await getPosition()
            await api.post('/hr/clock/out', {
                latitude: pos?.latitude,
                longitude: pos?.longitude,
                notes: data.notes || undefined,
            })
            toast.success('Ponto registrado: Saída')
            outForm.reset()
            fetchData()
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao registrar saída'))
        } finally {
            setClocking(false)
        }
    }

    const formatDuration = (start: string, end: string | null) => {
        if (!end) return 'Em andamento'
        const diff = new Date(end).getTime() - new Date(start).getTime()
        const h = Math.floor(diff / 3600000)
        const m = Math.floor((diff % 3600000) / 60000)
        return `${h}h${String(m).padStart(2, '0')}`
    }

    const getTypeLabel = (type: string) => {
        switch (type) {
            case 'regular': return 'Regular'
            case 'overtime': return 'Hora Extra'
            case 'travel': return 'Deslocamento'
            default: return type
        }
    }

    const sortedEntries = [...entries].sort(
        (a, b) => new Date(b.clock_in).getTime() - new Date(a.clock_in).getTime()
    )

    // Agrupamento simples por dia
    const groupedByDay: Record<string, TimeClockEntry[]> = {}
    sortedEntries.forEach((entry) => {
        const day = new Date(entry.clock_in).toLocaleDateString('pt-BR')
        if (!groupedByDay[day]) groupedByDay[day] = []
        groupedByDay[day].push(entry)
    })

    return (
        <div className="flex flex-col h-full overflow-hidden">
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border shrink-0">
                <div className="flex items-center gap-3">
                    <button
                        title="Voltar"
                        onClick={() => navigate('/tech')}
                        className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5 text-surface-600" />
                    </button>
                    <h1 className="text-lg font-bold text-foreground">Controle de Ponto</h1>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto w-full max-w-lg mx-auto pb-8">
                <div className="p-4 space-y-6">

                    {/* Status & Clock Action Card */}
                    <div className={cn(
                        'rounded-2xl p-6 shadow-sm border border-border/50 transition-all duration-300',
                        isClockedIn
                            ? 'bg-gradient-to-br from-brand-600 to-brand-800 text-white'
                            : 'bg-card'
                    )}>
                        <div className="text-center mb-6">
                            <div className="flex items-center justify-center gap-2 mb-3">
                                <Timer className={cn('w-5 h-5', isClockedIn ? 'text-white/80' : 'text-surface-400')} />
                                <span className={cn('text-sm font-medium uppercase tracking-wider', isClockedIn ? 'text-white/90' : 'text-surface-500')}>
                                    {isClockedIn ? 'Em Expediente' : 'Fora de Expediente'}
                                </span>
                            </div>

                            <p className={cn('text-5xl font-bold font-mono tracking-tight', isClockedIn ? 'text-white drop-shadow-sm' : 'text-surface-300')}>
                                {elapsed}
                            </p>

                            {activeEntry && (
                                <p className="text-sm mt-3 text-white/80 flex items-center justify-center gap-1.5 font-medium">
                                    <Clock className="w-3.5 h-3.5" />
                                    Iniciado às {new Date(activeEntry.clock_in).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                </p>
                            )}
                        </div>

                        {!isClockedIn ? (
                            <form onSubmit={inForm.handleSubmit(onClockInSubmit)} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-surface-500 uppercase tracking-wider mb-1.5">
                                        Tipo de Apontamento
                                    </label>
                                    <select
                                        {...inForm.register('type')}
                                        className="w-full px-4 py-3 rounded-xl bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 outline-none transition-shadow"
                                    >
                                        <option value="regular">Regular (Entrada / Retorno)</option>
                                        <option value="overtime">Hora Extra</option>
                                        <option value="travel">Deslocamento / Viagem</option>
                                    </select>
                                    {inForm.formState.errors.type && (
                                        <p className="text-xs text-red-500 mt-1">{inForm.formState.errors.type.message}</p>
                                    )}
                                </div>
                                <button
                                    type="submit"
                                    disabled={clocking || loading}
                                    title="Registrar Entrada"
                                    className="w-full flex items-center justify-center gap-2 py-3.5 rounded-xl bg-brand-600 hover:bg-brand-700 active:scale-[0.98] text-white font-semibold transition-all shadow-sm"
                                >
                                    {clocking ? <Loader2 className="w-5 h-5 animate-spin" /> : <Play className="w-5 h-5 fill-current" />}
                                    Registrar Entrada
                                </button>
                                <p className="flex justify-center gap-1.5 items-center text-xs text-surface-400 mt-3">
                                    <MapPin className="w-3.5 h-3.5" /> Localização será registrada
                                </p>
                            </form>
                        ) : (
                            <form onSubmit={outForm.handleSubmit(onClockOutSubmit)} className="space-y-4">
                                <div className="space-y-2">
                                    <div className="flex gap-2">
                                        {['Pausa para Almoço', 'Fim de Expediente'].map(note => (
                                            <button
                                                key={note}
                                                type="button"
                                                onClick={() => outForm.setValue('notes', note, { shouldValidate: true })}
                                                className={cn(
                                                    'flex-1 py-2 px-3 rounded-lg text-xs font-medium border transition-colors',
                                                    outForm.watch('notes') === note
                                                        ? 'bg-white text-brand-700 border-white'
                                                        : 'bg-white/10 border-white/20 text-white hover:bg-white/20'
                                                )}
                                            >
                                                {note}
                                            </button>
                                        ))}
                                    </div>
                                    <input
                                        type="text"
                                        {...outForm.register('notes')}
                                        placeholder="Ou digite outra observação da saída..."
                                        className="w-full px-4 py-3 rounded-xl bg-white/10 border border-white/20 text-sm text-white placeholder:text-white/50 focus:ring-2 focus:ring-white/30 outline-none backdrop-blur-sm"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={clocking}
                                    title="Registrar Saída"
                                    className="w-full flex items-center justify-center gap-2 py-3.5 rounded-xl bg-white text-brand-700 hover:bg-surface-50 active:scale-[0.98] font-semibold transition-all shadow-sm mt-2"
                                >
                                    {clocking ? <Loader2 className="w-5 h-5 animate-spin" /> : <Square className="w-5 h-5 fill-current" />}
                                    Registrar Saída
                                </button>
                                <p className="flex justify-center gap-1.5 items-center text-xs text-white/60 mt-3">
                                    <MapPin className="w-3.5 h-3.5" /> Localização será atualizada
                                </p>
                            </form>
                        )}
                    </div>

                    {/* Entries list */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold text-surface-500 uppercase tracking-wider flex items-center gap-2">
                            <History className="w-4 h-4" /> Histórico de Ponto
                        </h3>

                        {loading && entries.length === 0 ? (
                            <div className="flex justify-center py-8">
                                <Loader2 className="w-6 h-6 animate-spin text-brand-500" />
                            </div>
                        ) : entries.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-10 bg-surface-50 rounded-2xl border border-dashed border-surface-200">
                                <Clock className="w-10 h-10 text-surface-300 mb-3" />
                                <p className="text-sm text-surface-500">Nenhum ponto registrado</p>
                            </div>
                        ) : (
                            <div className="space-y-6">
                                {Object.keys(groupedByDay).map(day => (
                                    <div key={day} className="space-y-3">
                                        <h4 className="text-xs font-bold text-surface-400 pl-1">{day}</h4>
                                        <div className="space-y-2">
                                            {groupedByDay[day].map(entry => (
                                                <div key={entry.id} className="bg-card rounded-xl p-4 border border-border shadow-sm flex items-center gap-4">
                                                    <div className={cn(
                                                        'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0',
                                                        entry.clock_out ? 'bg-surface-100 text-surface-500' : 'bg-emerald-100 text-emerald-600'
                                                    )}>
                                                        {entry.clock_out ? <CheckCircle2 className="w-5 h-5" /> : <Loader2 className="w-5 h-5 animate-spin" />}
                                                    </div>

                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 mb-0.5">
                                                            <span className="text-sm font-bold text-foreground">
                                                                {new Date(entry.clock_in).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                                                {entry.clock_out && ` - ${new Date(entry.clock_out).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`}
                                                            </span>
                                                            <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-surface-100 text-surface-600 uppercase">
                                                                {getTypeLabel(entry.type)}
                                                            </span>
                                                        </div>
                                                        <p className="text-xs text-surface-500">
                                                            Duração: <strong className="text-surface-700">{formatDuration(entry.clock_in, entry.clock_out)}</strong>
                                                        </p>
                                                        {entry.notes && (
                                                            <p className="text-xs text-surface-400 truncate mt-1">Obs: {entry.notes}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                </div>
            </div>
        </div>
    )
}
