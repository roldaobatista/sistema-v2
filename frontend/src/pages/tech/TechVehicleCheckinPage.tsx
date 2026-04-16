import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    Car, Fuel, Gauge, CheckCircle2, AlertTriangle, Plus, Clock, Loader2,
    ArrowLeft, Shield, CircleDot, Droplets,
} from 'lucide-react'
import { cn, formatCurrency } from '@/lib/utils'
import api from '@/lib/api'
import { offlinePost } from '@/lib/syncEngine'
import { toast } from 'sonner'
import { CurrencyInputInline } from '@/components/common/CurrencyInput'
import { useAuthStore } from '@/stores/auth-store'
import { z } from 'zod'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

// Types
type TabId = 'checkin' | 'fuel' | 'incidents'

interface Vehicle {
    id: number
    plate: string
    model?: string
    brand?: string
    year?: number
    odometer_km?: number
    assignedUser_id?: number
}

interface Inspection {
    id: number
    fleet_vehicle_id: number
    inspection_date: string
    odometer_km: number
    checklist_data?: Record<string, string | boolean>
    status: string
}

interface FuelLog {
    id: number
    date: string
    odometer_km: number
    liters: number
    total_value: number
    gas_station?: string
    fuel_type?: string
}

interface Accident {
    id: number
    occurrence_date: string
    description: string
    status?: string
}

const FUEL_LEVELS = ['Vazio', '1/4', '1/2', '3/4', 'Cheio'] as const
const FUEL_TYPES = ['Gasolina', 'Etanol', 'Diesel', 'GNV'] as const
const INCIDENT_TYPES = ['Acidente', 'Avaria Mecânica', 'Multa', 'Pneu Furado', 'Outro'] as const

// Zod Schemas
const checkinSchema = z.object({
    odometer_km: z.coerce.number().min(1, 'Informe o km atual válido'),
    fuel_level: z.string().optional(),
    observations: z.string().optional(),
    pneus_ok: z.boolean().default(true),
    luzes_ok: z.boolean().default(true),
    freios_ok: z.boolean().default(true),
    limpeza_ok: z.boolean().default(true),
    documentos_ok: z.boolean().default(true),
})
type CheckinData = z.infer<typeof checkinSchema>

const checkoutSchema = z.object({
    odometer_km: z.coerce.number().min(1, 'Informe o km final válido'),
    condition_ok: z.boolean().default(true),
})
type CheckoutData = z.infer<typeof checkoutSchema>

const fuelSchema = z.object({
    odometer_km: z.coerce.number().min(1, 'Informe o km'),
    liters: z.coerce.number().min(0.01, 'Informe os litros'),
    total_value: z.coerce.number().min(0.01, 'Informe o valor total'),
    gas_station: z.string().optional(),
    fuel_type: z.string().min(1, 'Selecione o tipo'),
})
type FuelData = z.infer<typeof fuelSchema>

const incidentSchema = z.object({
    type: z.string().min(1, 'Selecione o tipo'),
    description: z.string().min(3, 'Informe os detalhes'),
})
type IncidentData = z.infer<typeof incidentSchema>


export default function TechVehicleCheckinPage() {
    const navigate = useNavigate()
    const { user } = useAuthStore()
    const [tab, setTab] = useState<TabId>('checkin')
    const [vehicle, setVehicle] = useState<Vehicle | null>(null)
    const [loading, setLoading] = useState(true)
    const [inspections, setInspections] = useState<Inspection[]>([])
    const [fuelLogs, setFuelLogs] = useState<FuelLog[]>([])
    const [accidents, setAccidents] = useState<Accident[]>([])

    const [showFuelForm, setShowFuelForm] = useState(false)
    const [showIncidentForm, setShowIncidentForm] = useState(false)

    // Form Hooks
    const checkinForm = useForm<CheckinData>({
        resolver: zodResolver(checkinSchema),
        defaultValues: {
            odometer_km: undefined,
            fuel_level: FUEL_LEVELS[2],
            observations: '',
            pneus_ok: true,
            luzes_ok: true,
            freios_ok: true,
            limpeza_ok: true,
            documentos_ok: true,
        }
    })

    const checkoutForm = useForm<CheckoutData>({
        resolver: zodResolver(checkoutSchema),
        defaultValues: {
            odometer_km: undefined,
            condition_ok: true,
        }
    })

    const fuelForm = useForm<FuelData>({
        resolver: zodResolver(fuelSchema),
        defaultValues: {
            odometer_km: undefined,
            liters: undefined,
            total_value: undefined,
            gas_station: '',
            fuel_type: FUEL_TYPES[0],
        }
    })

    const incidentForm = useForm<IncidentData>({
        resolver: zodResolver(incidentSchema),
        defaultValues: {
            type: INCIDENT_TYPES[0],
            description: '',
        }
    })

    const sortedInspections = [...inspections].sort(
        (a, b) => new Date(b.inspection_date).getTime() - new Date(a.inspection_date).getTime()
    )
    const mostRecent = sortedInspections[0]
    const activeCheckin =
        mostRecent && (mostRecent.checklist_data as Record<string, string>)?.['check_type'] === 'check_in'
            ? mostRecent
            : null

    useEffect(() => {
        loadVehicle()
    }, [])

    useEffect(() => {
        if (vehicle?.id) {
            loadInspections()
            loadFuelLogs()
            loadAccidents()
        }
    }, [vehicle?.id])

    async function loadVehicle() {
        try {
            setLoading(true)
            const res = await api.get<{ data?: Vehicle[] }>('/fleet/vehicles', { params: { per_page: 100 } })
            const list = res.data?.data ?? (Array.isArray(res.data) ? res.data : [])
            const arr = Array.isArray(list) ? list : (list as { data?: Vehicle[] })?.data ?? []
            const myVehicle = arr.find((v: Vehicle) => v.assignedUser_id === user?.id) ?? arr[0] ?? null
            setVehicle(myVehicle)
        } catch (e: unknown) {
            toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erro ao carregar veículo')
        } finally {
            setLoading(false)
        }
    }

    async function loadInspections() {
        if (!vehicle?.id) return
        try {
            const res = await api.get<{ data?: Inspection[] }>(`/fleet/vehicles/${vehicle.id}/inspections`, {
                params: { per_page: 50 },
            })
            const list = (res.data as { data?: Inspection[] })?.data ?? []
            setInspections(list)
        } catch {
            setInspections([])
        }
    }

    async function loadFuelLogs() {
        if (!vehicle?.id) return
        try {
            const res = await api.get<{ data?: FuelLog[] }>('/fleet/fuel-logs', {
                params: { fleet_vehicle_id: vehicle.id, per_page: 20 },
            })
            const list = (res.data as { data?: FuelLog[] })?.data ?? []
            setFuelLogs(list)
        } catch {
            setFuelLogs([])
        }
    }

    async function loadAccidents() {
        if (!vehicle?.id) return
        try {
            const res = await api.get<{ data?: Accident[] }>('/fleet/accidents', {
                params: { fleet_vehicle_id: vehicle.id, per_page: 20 },
            })
            const list = (res.data as { data?: Accident[] })?.data ?? []
            setAccidents(list)
        } catch {
            setAccidents([])
        }
    }

    const onCheckinSubmit = async (data: CheckinData) => {
        if (!vehicle?.id) return
        try {
            const checklist: Record<string, string | boolean> = {
                check_type: 'check_in',
                fuel_level: data.fuel_level || '',
                pneus_ok: data.pneus_ok,
                luzes_ok: data.luzes_ok,
                freios_ok: data.freios_ok,
                limpeza_ok: data.limpeza_ok,
                documentos_ok: data.documentos_ok,
                observations: data.observations || '',
            }
            const wasQueued = await offlinePost(`/fleet/vehicles/${vehicle.id}/inspections`, {
                inspection_date: new Date().toISOString().slice(0, 10),
                odometer_km: data.odometer_km,
                checklist_data: checklist,
                status: 'ok',
                observations: data.observations || '',
            })
            if (wasQueued) {
                toast.success('Check-in salvo offline. Será sincronizado quando houver conexão.')
            } else {
                toast.success('Check-in realizado')
            }
            checkinForm.reset()
            loadInspections()
        } catch (e: unknown) {
            toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erro ao fazer check-in')
        }
    }

    const onCheckoutSubmit = async (data: CheckoutData) => {
        if (!vehicle?.id) return
        try {
            const checklist: Record<string, string | boolean> = {
                check_type: 'check_out',
                condition_ok: data.condition_ok,
            }
            const wasQueued = await offlinePost(`/fleet/vehicles/${vehicle.id}/inspections`, {
                inspection_date: new Date().toISOString().slice(0, 10),
                odometer_km: data.odometer_km,
                checklist_data: checklist,
                status: data.condition_ok ? 'ok' : 'issues_found',
                observations: '',
            })
            if (wasQueued) {
                toast.success('Check-out salvo offline. Será sincronizado quando houver conexão.')
            } else {
                toast.success('Check-out realizado')
            }
            checkoutForm.reset()
            loadInspections()
        } catch (e: unknown) {
            toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erro ao fazer check-out')
        }
    }

    const onFuelSubmit = async (data: FuelData) => {
        if (!vehicle?.id) return
        try {
            const wasQueued = await offlinePost('/fleet/fuel-logs', {
                fleet_vehicle_id: vehicle.id,
                date: new Date().toISOString().slice(0, 10),
                odometer_km: data.odometer_km,
                liters: data.liters,
                price_per_liter: data.liters > 0 ? data.total_value / data.liters : 0,
                total_value: data.total_value,
                fuel_type: data.fuel_type,
                gas_station: data.gas_station || undefined,
            })
            if (wasQueued) {
                toast.success('Abastecimento salvo offline. Será sincronizado quando houver conexão.')
            } else {
                toast.success('Abastecimento registrado')
            }
            setShowFuelForm(false)
            fuelForm.reset()
            loadFuelLogs()
        } catch (e: unknown) {
            toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erro ao registrar abastecimento')
        }
    }

    const onIncidentSubmit = async (data: IncidentData) => {
        if (!vehicle?.id) return
        try {
            const fullDesc = `[${data.type}] ${data.description}`
            const wasQueued = await offlinePost('/fleet/accidents', {
                fleet_vehicle_id: vehicle.id,
                occurrence_date: new Date().toISOString().slice(0, 10),
                description: fullDesc,
                status: 'investigating',
            })
            if (wasQueued) {
                toast.success('Ocorrência salva offline. Será sincronizada quando houver conexão.')
            } else {
                toast.success('Ocorrência registrada')
            }
            setShowIncidentForm(false)
            incidentForm.reset()
            loadAccidents()
        } catch (e: unknown) {
            toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erro ao registrar ocorrência')
        }
    }

    const formatDate = (d: string) => {
        try {
            return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
        } catch {
            return d
        }
    }

    const formatTime = (d: string) => {
        try {
            return new Date(d).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
        } catch {
            return ''
        }
    }

    if (loading) {
        return (
            <div className="flex flex-col h-full">
                <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                    <div className="flex items-center gap-3">
                        <button
                            title="Voltar"
                            onClick={() => navigate('/tech')}
                            className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5 text-surface-600" />
                        </button>
                        <h1 className="text-lg font-bold text-foreground">Meu Veículo</h1>
                    </div>
                </div>
                <div className="flex-1 overflow-y-auto flex items-center justify-center">
                    <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                </div>
            </div>
        )
    }

    if (!vehicle) {
        return (
            <div className="flex flex-col h-full">
                <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                    <div className="flex items-center gap-3">
                        <button
                            title="Voltar"
                            onClick={() => navigate('/tech')}
                            className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5 text-surface-600" />
                        </button>
                        <h1 className="text-lg font-bold text-foreground">Meu Veículo</h1>
                    </div>
                </div>
                <div className="flex-1 overflow-y-auto px-4 py-6 flex flex-col items-center justify-center gap-4">
                    <Car className="w-12 h-12 text-surface-400" />
                    <p className="text-sm text-surface-600 text-center">
                        Nenhum veículo atribuído a você.
                    </p>
                    <button
                        title="Voltar"
                        onClick={() => navigate('/tech')}
                        className="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium"
                    >
                        Voltar
                    </button>
                </div>
            </div>
        )
    }

    const tabs: { id: TabId; label: string }[] = [
        { id: 'checkin', label: 'Check-in/out' },
        { id: 'fuel', label: 'Abastecimentos' },
        { id: 'incidents', label: 'Ocorrências' },
    ]

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
                    <h1 className="text-lg font-bold text-foreground">Meu Veículo</h1>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto w-full max-w-lg mx-auto pb-8">
                <div className="p-4 space-y-4">
                    {/* Vehicle Header Info */}
                    <div className="bg-card rounded-xl p-4 border border-border">
                        <div className="flex items-center gap-3">
                            <div className="p-2 rounded-lg bg-brand-100 dark:bg-brand-900/30">
                                <Car className="w-5 h-5 text-brand-600 dark:text-brand-400" />
                            </div>
                            <div>
                                <p className="font-semibold text-foreground">
                                    {vehicle.plate}
                                </p>
                                <p className="text-sm text-surface-600">
                                    {[vehicle.brand, vehicle.model, vehicle.year].filter(Boolean).join(' · ')}
                                </p>
                                {vehicle.odometer_km != null && (
                                    <p className="text-xs text-surface-500 flex items-center gap-1 mt-0.5">
                                        <Gauge className="w-3.5 h-3.5" />
                                        {vehicle.odometer_km.toLocaleString('pt-BR')} km
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Tabs */}
                    <div className="flex gap-1 p-1 rounded-lg bg-surface-100 dark:bg-surface-800/50">
                        {tabs.map((t) => (
                            <button
                                key={t.id}
                                title={t.label}
                                onClick={() => setTab(t.id)}
                                className={cn(
                                    'flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors',
                                    tab === t.id
                                        ? 'bg-card text-brand-600 dark:text-brand-400 shadow-sm'
                                        : 'text-surface-600 hover:text-surface-900 dark:hover:text-surface-300'
                                )}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>

                    {/* Checkin / Checkout Tab */}
                    {tab === 'checkin' && (
                        <div className="space-y-4">
                            <div className="bg-card rounded-xl p-5 border border-border shadow-sm">
                                <div className="flex items-center gap-2 mb-5 pb-4 border-b border-border">
                                    {activeCheckin ? (
                                        <>
                                            <CheckCircle2 className="w-5 h-5 text-emerald-500" />
                                            <span className="text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                                Check-in ativo desde {formatTime(activeCheckin.inspection_date)}
                                            </span>
                                        </>
                                    ) : (
                                        <>
                                            <CircleDot className="w-5 h-5 text-amber-500" />
                                            <span className="text-sm font-medium text-amber-700 dark:text-amber-400">
                                                Necessário Check-in para Iniciar o Dia
                                            </span>
                                        </>
                                    )}
                                </div>

                                {!activeCheckin && (
                                    <form onSubmit={checkinForm.handleSubmit(onCheckinSubmit)} className="space-y-4">
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 mb-1">
                                                    Km atual <span className="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="number"
                                                    {...checkinForm.register('odometer_km')}
                                                    placeholder="Ex: 45000"
                                                    className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 focus:outline-none"
                                                />
                                                {checkinForm.formState.errors.odometer_km && (
                                                    <p className="text-xs text-red-500 mt-1">{checkinForm.formState.errors.odometer_km.message}</p>
                                                )}
                                            </div>

                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 mb-1">
                                                    Nível combustível
                                                </label>
                                                <select
                                                    {...checkinForm.register('fuel_level')}
                                                    className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 focus:outline-none"
                                                >
                                                    {FUEL_LEVELS.map((f) => (
                                                        <option key={f} value={f}>{f}</option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 mb-2">
                                                    Itens de Verificação Rápida
                                                </label>
                                                <div className="flex flex-wrap gap-2">
                                                    {[
                                                        { key: 'pneus_ok', label: 'Pneus OK' },
                                                        { key: 'luzes_ok', label: 'Luzes OK' },
                                                        { key: 'freios_ok', label: 'Freios OK' },
                                                        { key: 'limpeza_ok', label: 'Limpeza OK' },
                                                        { key: 'documentos_ok', label: 'Docs OK' },
                                                    ].map(({ key, label }) => {
                                                        const val = checkinForm.watch(key as keyof CheckinData)
                                                        return (
                                                            <button
                                                                key={key}
                                                                type="button"
                                                                title={label}
                                                                onClick={() => checkinForm.setValue(key as keyof CheckinData, !val)}
                                                                className={cn(
                                                                    'px-3 py-2 rounded-lg text-xs font-medium flex items-center gap-1.5 transition-colors',
                                                                    val
                                                                        ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 border border-emerald-200'
                                                                        : 'bg-surface-100 border border-surface-200 text-surface-600'
                                                                )}
                                                            >
                                                                <Shield className="w-3.5 h-3.5" />
                                                                {label}
                                                            </button>
                                                        )
                                                    })}
                                                </div>
                                            </div>

                                            <div>
                                                <label className="block text-sm font-medium text-surface-700 mb-1">
                                                    Observações (Avarias, riscos, etc)
                                                </label>
                                                <textarea
                                                    {...checkinForm.register('observations')}
                                                    rows={2}
                                                    placeholder="Opcional"
                                                    className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 focus:outline-none resize-none"
                                                />
                                            </div>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={checkinForm.formState.isSubmitting}
                                            className="w-full py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium text-sm flex items-center justify-center gap-2 transition-colors mt-2"
                                            title="Fazer Check-in"
                                        >
                                            {checkinForm.formState.isSubmitting ? (
                                                <Loader2 className="w-4 h-4 animate-spin" />
                                            ) : (
                                                <CheckCircle2 className="w-4 h-4" />
                                            )}
                                            Fazer Check-in
                                        </button>
                                    </form>
                                )}

                                {activeCheckin && (
                                    <form onSubmit={checkoutForm.handleSubmit(onCheckoutSubmit)} className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-surface-700 mb-1">
                                                Km final <span className="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                {...checkoutForm.register('odometer_km')}
                                                placeholder="Ex: 45120"
                                                className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 focus:outline-none"
                                            />
                                            {checkoutForm.formState.errors.odometer_km && (
                                                <p className="text-xs text-red-500 mt-1">{checkoutForm.formState.errors.odometer_km.message}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="flex items-center gap-2 cursor-pointer p-3 rounded-lg border border-surface-200 bg-surface-50">
                                                <input
                                                    type="checkbox"
                                                    {...checkoutForm.register('condition_ok')}
                                                    className="rounded border-surface-300 text-brand-600 focus:ring-brand-500 w-4 h-4"
                                                />
                                                <span className="text-sm font-medium text-surface-700">
                                                    O veículo foi devolvido nas mesmas condições
                                                </span>
                                            </label>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={checkoutForm.formState.isSubmitting}
                                            className="w-full py-2.5 mt-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm flex items-center justify-center gap-2 transition-colors"
                                            title="Fazer Check-out"
                                        >
                                            {checkoutForm.formState.isSubmitting ? (
                                                <Loader2 className="w-4 h-4 animate-spin" />
                                            ) : (
                                                <Clock className="w-4 h-4" />
                                            )}
                                            Fazer Check-out
                                        </button>
                                    </form>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Fuel Tab */}
                    {tab === 'fuel' && (
                        <div className="space-y-4">
                            {!showFuelForm ? (
                                <button
                                    title="Novo Abastecimento"
                                    onClick={() => setShowFuelForm(true)}
                                    className="w-full py-3 rounded-xl border-2 border-dashed border-surface-300 hover:border-brand-400 hover:bg-brand-50/50 text-surface-600 hover:text-brand-600 dark:hover:bg-brand-900/10 transition-colors text-sm font-medium flex items-center justify-center gap-2"
                                >
                                    <Plus className="w-4 h-4" />
                                    Registrar Novo Abastecimento
                                </button>
                            ) : (
                                <div className="bg-card rounded-xl p-5 border border-border shadow-sm">
                                    <div className="flex justify-between items-center mb-4">
                                        <h3 className="font-semibold text-foreground">Novo Abastecimento</h3>
                                        <button
                                            title="Cancelar"
                                            type="button"
                                            onClick={() => setShowFuelForm(false)}
                                            className="text-surface-400 hover:text-surface-600 p-1"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                    <form onSubmit={fuelForm.handleSubmit(onFuelSubmit)} className="space-y-3">
                                        <div className="grid grid-cols-2 gap-3">
                                            <div>
                                                <label className="block text-xs font-medium text-surface-500 mb-1">Km Atual *</label>
                                                <input
                                                    type="number"
                                                    {...fuelForm.register('odometer_km')}
                                                    placeholder="Km"
                                                    className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 outline-none"
                                                />
                                                {fuelForm.formState.errors.odometer_km && (
                                                    <p className="text-xs text-red-500 mt-1">{fuelForm.formState.errors.odometer_km.message}</p>
                                                )}
                                            </div>
                                            <div>
                                                <label className="block text-xs font-medium text-surface-500 mb-1">Litros *</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    {...fuelForm.register('liters')}
                                                    placeholder="Litros"
                                                    className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 outline-none"
                                                />
                                                {fuelForm.formState.errors.liters && (
                                                    <p className="text-xs text-red-500 mt-1">{fuelForm.formState.errors.liters.message}</p>
                                                )}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-medium text-surface-500 mb-1">Valor Total (R$) *</label>
                                            <Controller
                                                control={fuelForm.control}
                                                name="total_value"
                                                render={({ field }) => (
                                                    <CurrencyInputInline
                                                        value={field.value || 0}
                                                        onChange={(val) => field.onChange(val)}
                                                        placeholder="Valor total"
                                                        className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 outline-none"
                                                    />
                                                )}
                                            />
                                            {fuelForm.formState.errors.total_value && (
                                                <p className="text-xs text-red-500 mt-1">{fuelForm.formState.errors.total_value.message}</p>
                                            )}
                                        </div>

                                        <div className="grid grid-cols-2 gap-3">
                                            <div>
                                                <label className="block text-xs font-medium text-surface-500 mb-1">Combustível</label>
                                                <select
                                                    {...fuelForm.register('fuel_type')}
                                                    className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 outline-none"
                                                >
                                                    {FUEL_TYPES.map((f) => (
                                                        <option key={f} value={f}>{f}</option>
                                                    ))}
                                                </select>
                                                {fuelForm.formState.errors.fuel_type && (
                                                    <p className="text-xs text-red-500 mt-1">{fuelForm.formState.errors.fuel_type.message}</p>
                                                )}
                                            </div>
                                            <div>
                                                <label className="block text-xs font-medium text-surface-500 mb-1">Opcional</label>
                                                <input
                                                    type="text"
                                                    {...fuelForm.register('gas_station')}
                                                    placeholder="Posto (Nome)"
                                                    className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 outline-none"
                                                />
                                            </div>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={fuelForm.formState.isSubmitting}
                                            className="w-full py-2.5 mt-2 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium text-sm flex items-center justify-center gap-2 transition-colors"
                                            title="Registrar Abastecimento"
                                        >
                                            {fuelForm.formState.isSubmitting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Droplets className="w-4 h-4" />}
                                            Registrar
                                        </button>
                                    </form>
                                </div>
                            )}

                            <div className="space-y-3 mt-2">
                                <h4 className="text-sm font-medium text-surface-500 pl-1 uppercase tracking-wider">Histórico Recente</h4>
                                {fuelLogs.length === 0 ? (
                                    <p className="text-sm text-surface-500 text-center py-6 bg-surface-50 rounded-xl border border-dashed border-surface-200">
                                        Nenhum abastecimento registrado
                                    </p>
                                ) : (
                                    fuelLogs.map((log) => (
                                        <div
                                            key={log.id}
                                            className="bg-card rounded-xl p-4 border border-border shadow-sm flex flex-col gap-2"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                                                        <Fuel className="w-4 h-4" />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-semibold text-foreground">
                                                            {formatDate(log.date)}
                                                        </p>
                                                        <p className="text-xs text-surface-500 mt-0.5">
                                                            {log.liters.toLocaleString('pt-BR')}L ({log.fuel_type ?? 'N/A'})
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-medium text-foreground">
                                                        {formatCurrency(log.total_value)}
                                                    </p>
                                                    <p className="text-xs text-surface-400 mt-0.5 whitespace-nowrap">
                                                        {log.odometer_km?.toLocaleString('pt-BR')} km
                                                    </p>
                                                </div>
                                            </div>
                                            {log.gas_station && <p className="text-xs text-surface-500 pl-11">{log.gas_station}</p>}
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    )}

                    {/* Incidents Tab */}
                    {tab === 'incidents' && (
                        <div className="space-y-4">
                            {!showIncidentForm ? (
                                <button
                                    title="Reportar Ocorrência"
                                    onClick={() => setShowIncidentForm(true)}
                                    className="w-full py-3 rounded-xl border-2 border-dashed border-surface-300 hover:border-amber-400 hover:bg-amber-50/50 text-surface-600 hover:text-amber-700 dark:hover:bg-amber-900/10 transition-colors text-sm font-medium flex items-center justify-center gap-2"
                                >
                                    <Plus className="w-4 h-4" />
                                    Reportar Nova Ocorrência
                                </button>
                            ) : (
                                <div className="bg-card rounded-xl p-5 border border-border shadow-sm">
                                    <div className="flex justify-between items-center mb-4">
                                        <h3 className="font-semibold text-foreground">Nova Ocorrência</h3>
                                        <button
                                            title="Cancelar"
                                            type="button"
                                            onClick={() => setShowIncidentForm(false)}
                                            className="text-surface-400 hover:text-surface-600 p-1"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                    <form onSubmit={incidentForm.handleSubmit(onIncidentSubmit)} className="space-y-3">
                                        <div>
                                            <label className="block text-xs font-medium text-surface-500 mb-1">Tipo de Evento</label>
                                            <select
                                                {...incidentForm.register('type')}
                                                className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none"
                                            >
                                                {INCIDENT_TYPES.map((t) => (
                                                    <option key={t} value={t}>{t}</option>
                                                ))}
                                            </select>
                                            {incidentForm.formState.errors.type && (
                                                <p className="text-xs text-red-500 mt-1">{incidentForm.formState.errors.type.message}</p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-surface-500 mb-1">Descrição</label>
                                            <textarea
                                                {...incidentForm.register('description')}
                                                rows={3}
                                                placeholder="Descreva o que aconteceu em detalhes..."
                                                className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-sm focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none resize-none"
                                            />
                                            {incidentForm.formState.errors.description && (
                                                <p className="text-xs text-red-500 mt-1">{incidentForm.formState.errors.description.message}</p>
                                            )}
                                        </div>
                                        <button
                                            type="submit"
                                            disabled={incidentForm.formState.isSubmitting}
                                            className="w-full py-2.5 mt-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm flex items-center justify-center gap-2 transition-colors"
                                            title="Salvar Ocorrência"
                                        >
                                            {incidentForm.formState.isSubmitting ? <Loader2 className="w-4 h-4 animate-spin" /> : <AlertTriangle className="w-4 h-4" />}
                                            Registrar Ocorrência
                                        </button>
                                    </form>
                                </div>
                            )}

                            <div className="space-y-3 mt-2">
                                <h4 className="text-sm font-medium text-surface-500 pl-1 uppercase tracking-wider">Histórico de Ocorrências</h4>
                                {accidents.length === 0 ? (
                                    <p className="text-sm text-surface-500 text-center py-6 bg-surface-50 rounded-xl border border-dashed border-surface-200">
                                        Nenhuma ocorrência relatada
                                    </p>
                                ) : (
                                    accidents.map((a) => {
                                        const typeMatch = a.description.match(/^\[([^\]]+)\]/)
                                        const type = typeMatch ? typeMatch[1] : 'Outro'
                                        const desc = typeMatch ? a.description.replace(/^\[[^\]]+\]\s*/, '') : a.description
                                        return (
                                            <div
                                                key={a.id}
                                                className="bg-card rounded-xl p-4 border border-border shadow-sm flex flex-col gap-2 relative overflow-hidden"
                                            >
                                                <div className="absolute top-0 left-0 w-1 h-full bg-amber-500" />
                                                <div className="flex items-start justify-between gap-2 pl-1">
                                                    <span className="px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                                        {type}
                                                    </span>
                                                    <span className="text-xs font-medium text-surface-500">{formatDate(a.occurrence_date)}</span>
                                                </div>
                                                <p className="text-sm text-surface-700 dark:text-surface-300 mt-1 pl-1 leading-relaxed">{desc}</p>
                                            </div>
                                        )
                                    })
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    )
}
