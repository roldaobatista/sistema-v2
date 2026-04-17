import { useState, useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
    Scale, Plus, Trash2, CheckCircle2, XCircle, Save, ChevronDown, ChevronUp,
    Loader2, ArrowLeft, Gauge, FlaskConical, FileText, WifiOff,
} from 'lucide-react'
import { z } from 'zod'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { cn, getApiErrorMessage } from '@/lib/utils'
import api from '@/lib/api'
import { toast } from 'sonner'
import { useOfflineMutation } from '@/hooks/useOfflineMutation'

interface Equipment {
    id: number
    code?: string
    name?: string
    serial_number?: string
    brand?: string
    model?: string
}

interface EquipmentListEntry extends Partial<Equipment> {
    equipment?: Equipment | null
}

interface WorkOrderResponse {
    equipment?: Equipment | null
    equipmentsList?: EquipmentListEntry[] | null
}

const DEFAULT_TOLERANCE = 0.5

const nullifyEmptyString = z.preprocess((val) => (val === '' || val === null || val === undefined || Number.isNaN(val) ? null : val), z.coerce.number().nullable())
const requireNumber = z.preprocess((val) => (val === '' || val === null || val === undefined || Number.isNaN(val) ? undefined : val), z.coerce.number({ required_error: 'Obrigatório', invalid_type_error: 'Obrigatório' }))

const readingSchema = z.object({
    nominal: requireNumber,
    indication: requireNumber,
    unit: z.string().min(1, 'Obrigatório'),
})

const excentricitySchema = z.object({
    load: nullifyEmptyString,
    center: nullifyEmptyString,
    front: nullifyEmptyString,
    back: nullifyEmptyString,
    left: nullifyEmptyString,
    right: nullifyEmptyString,
})

const calibrationSchema = z.object({
    tolerance: z.coerce.number({ required_error: 'Obrigatório', invalid_type_error: 'Inválido' }).min(0, 'Deve ser positivo'),
    readings: z.array(readingSchema).min(1, 'Adicione pelo menos uma leitura'),
    excentricity: excentricitySchema.optional(),
})

type CalibrationFormValues = z.infer<typeof calibrationSchema>

function collectWorkOrderEquipments(workOrder: WorkOrderResponse | null | undefined): Equipment[] {
    const unique = new Map<number, Equipment>()

    const append = (candidate: Equipment | null | undefined) => {
        if (!candidate?.id || unique.has(candidate.id)) {
            return
        }
        unique.set(candidate.id, candidate)
    }

    append(workOrder?.equipment)
    for (const entry of workOrder?.equipmentsList ?? []) {
        append(entry.equipment ?? entry)
    }
    return Array.from(unique.values())
}

export default function TechCalibrationReadingsPage() {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const [equipments, setEquipments] = useState<Equipment[]>([])
    const [selectedEquipment, setSelectedEquipment] = useState<Equipment | null>(null)
    const [calibrationId, setCalibrationId] = useState<number | null>(null)
    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState<string | null>(null)
    const [excentricityExpanded, setExcentricityExpanded] = useState(false)
    const [readingsSaved, setReadingsSaved] = useState(false)
    const [generatingCert, setGeneratingCert] = useState(false)

    const offlineMut = useOfflineMutation<unknown, { mutations: { type: string; data: unknown }[] }>({
        url: '/tech/sync/batch',
        invalidateKeys: [['tech-wo-detail', id!]],
        onSuccess: (_data, wasOffline) => {
            if (wasOffline) {
                setReadingsSaved(true)
                toast.info('Leituras registradas offline. Certificado poderá ser gerado após sincronização.')
            } else {
                setReadingsSaved(true)
            }
        },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao salvar leituras')),
        offlineToast: 'Leituras salvas offline. Serão sincronizadas quando houver conexão.',
        successToast: 'Leituras salvas com sucesso',
    })

    const equipmentList = equipments.length > 0
        ? equipments
        : selectedEquipment
            ? [selectedEquipment]
            : []

    const {
        register,
        control,
        handleSubmit,
        watch,
        reset,
        formState: { errors, isValid }
    } = useForm<CalibrationFormValues>({
        resolver: zodResolver(calibrationSchema),
        defaultValues: {
            tolerance: DEFAULT_TOLERANCE,
            readings: [{ nominal: undefined, indication: undefined, unit: 'kg' }],
            excentricity: { load: null, center: null, front: null, back: null, left: null, right: null }
        },
        mode: 'onChange'
    })

    const { fields, append, remove } = useFieldArray({
        control,
        name: 'readings'
    })

    const watchReadings = watch('readings')
    const watchExcentricity = watch('excentricity')
    const watchTolerance = watch('tolerance') || 0

    useEffect(() => {
        if (!id) return
        async function fetchWorkOrder() {
            try {
                setLoading(true)
                setError(null)
                const { data } = await api.get(`/work-orders/${id}`)
                const wo = (data.data || data) as WorkOrderResponse
                const list = collectWorkOrderEquipments(wo)
                if (list.length > 0) setEquipments(list)
                else setError('Nenhum equipamento vinculado a esta OS')
            } catch (err: unknown) {
                setError(getApiErrorMessage(err, 'Erro ao carregar OS'))
                toast.error('Erro ao carregar dados da OS')
            } finally {
                setLoading(false)
            }
        }
        fetchWorkOrder()
    }, [id])

    const ensureCalibration = async (equipmentId: number): Promise<number | null> => {
        try {
            const { data } = await api.get(`/equipments/${equipmentId}/calibrations`)
            const calibs = data.calibrations || []
            const forWo = calibs.find((c: { work_order_id?: number }) => c.work_order_id === Number(id))
            const latest = forWo || calibs[0]
            if (latest) return latest.id
            const today = new Date().toISOString().slice(0, 10)
            const { data: created } = await api.post(`/equipments/${equipmentId}/calibrations`, {
                calibration_date: today,
                calibration_type: 'interna',
                result: 'approved_with_restriction',
                work_order_id: Number(id),
            })
            return created?.calibration?.id ?? null
        } catch {
            toast.error('Erro ao obter/criar calibração')
            return null
        }
    }

    const handleSelectEquipment = async (eq: Equipment) => {
        setSelectedEquipment(eq)
        const calId = await ensureCalibration(eq.id)
        setCalibrationId(calId)
        reset({
            tolerance: DEFAULT_TOLERANCE,
            readings: [{ nominal: undefined, indication: undefined, unit: 'kg' }],
            excentricity: { load: null, center: null, front: null, back: null, left: null, right: null }
        })
        setReadingsSaved(false)
    }

    const calcError = (nominal?: number | null, indication?: number | null) => {
        if (nominal == null || indication == null || Number.isNaN(nominal) || Number.isNaN(indication)) return null
        return indication - nominal
    }

    const calcMaxExcentricityDiff = () => {
        if (!watchExcentricity) return null
        const vals = [
            watchExcentricity.center,
            watchExcentricity.front,
            watchExcentricity.back,
            watchExcentricity.left,
            watchExcentricity.right,
        ].filter((v) => v != null && !Number.isNaN(v)) as number[]
        if (vals.length < 2) return null
        return Math.max(...vals) - Math.min(...vals)
    }

    const maxReadingError = (watchReadings || []).reduce((max, r) => {
        const err = calcError(r.nominal, r.indication)
        if (err === null) return max
        return Math.max(max, Math.abs(err))
    }, 0)

    const maxExcentricity = calcMaxExcentricityDiff()
    const passed = maxReadingError <= watchTolerance && (maxExcentricity === null || maxExcentricity <= watchTolerance)

    const onSubmit = async (data: CalibrationFormValues) => {
        if (!calibrationId || !selectedEquipment) return

        // Offline: queue the calibration reading for later sync
        if (!offlineMut.isOnline) {
            const resultValue = passed ? 'approved' : 'rejected'
            offlineMut.mutate({
                mutations: [{
                    type: 'calibration_reading',
                    data: {
                        calibration_id: calibrationId,
                        equipment_id: selectedEquipment.id,
                        work_order_id: Number(id),
                        tolerance: data.tolerance,
                        result: resultValue,
                        readings: (data.readings || []).map((r) => ({
                            reference_value: r.nominal,
                            indication_increasing: r.indication,
                            indication_decreasing: null,
                            k_factor: 2.0,
                            repetition: 1,
                            unit: r.unit,
                        })),
                        excentricity: data.excentricity,
                    },
                }],
            })
            return
        }

        setSaving(true)
        try {
            // 1. Save readings
            await api.post(`/calibration/${calibrationId}/readings`, {
                readings: (data.readings || []).map((r) => ({
                    reference_value: r.nominal,
                    indication_increasing: r.indication,
                    indication_decreasing: null, // Default: leitura decrescente nao coletada neste fluxo simplificado
                    k_factor: 2.0, // Default ISO: fator de abrangencia k=2 (95.45% confianca)
                    repetition: 1, // Default: primeira repeticao (fluxo simplificado coleta 1 repeticao)
                    unit: r.unit,
                })),
            })

            // 2. Save eccentricity (if provided) — sequential to ensure atomicity
            const ex = data.excentricity
            const hasExcentricity = ex && [ex.center, ex.front, ex.back, ex.left, ex.right].some((v) => v != null)

            if (hasExcentricity && ex?.load != null) {
                const load = ex.load
                const positions = [
                    { key: 'center' as const },
                    { key: 'front' as const },
                    { key: 'back' as const },
                    { key: 'left' as const },
                    { key: 'right' as const },
                ]
                try {
                    await api.post(`/calibration/${calibrationId}/excentricity`, {
                        tests: positions
                            .filter((p) => ex[p.key] != null)
                            .map((p) => ({
                                position: p.key,
                                load_applied: load,
                                indication: ex[p.key],
                            })),
                    })
                } catch (exErr: unknown) {
                    // Readings saved but eccentricity failed — notify user clearly
                    toast.error(
                        getApiErrorMessage(exErr, 'Leituras salvas, mas erro ao salvar excentricidade. Tente salvar novamente.'),
                    )
                    setReadingsSaved(true)
                    setSaving(false)
                    return
                }
            }

            // 3. Send calculated result to backend via wizard endpoint
            const resultValue = passed ? 'approved' : 'rejected'
            try {
                await api.put(`/calibration/${calibrationId}/wizard`, {
                    result: resultValue,
                })
            } catch {
                // Non-critical: readings/eccentricity already saved, result update is best-effort
            }

            toast.success('Leituras salvas com sucesso')
            setReadingsSaved(true)
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao salvar leituras'))
        } finally {
            setSaving(false)
        }
    }

    const handleGenerateCertificate = async () => {
        if (!calibrationId || !selectedEquipment) return
        setGeneratingCert(true)
        try {
            await api.post(`/calibration/${calibrationId}/generate-certificate`)
            const { data } = await api.get(`/equipments/${selectedEquipment.id}/calibrations/${calibrationId}/pdf`, { responseType: 'blob' })
            const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
            window.open(url, '_blank')
            toast.success('Certificado gerado. Abra a nova aba para visualizar ou imprimir.')
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao gerar certificado'))
        } finally {
            setGeneratingCert(false)
        }
    }

    const inputClass =
        'w-full px-3 py-2.5 rounded-lg bg-surface-100 border-0 text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none'

    return (
        <div className="flex flex-col h-full">
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => navigate(`/tech/os/${id}`)}
                        className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                        aria-label="Voltar"
                    >
                        <ArrowLeft className="w-5 h-5 text-surface-600" />
                    </button>
                    <h1 className="text-lg font-bold text-foreground">
                        Leituras de Calibração
                    </h1>
                </div>
                {!offlineMut.isOnline && (
                    <div className="mt-2 flex items-center gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                        <WifiOff className="w-3.5 h-3.5 flex-shrink-0" />
                        <span>Modo offline — leituras serão sincronizadas quando houver conexão</span>
                    </div>
                )}
                {offlineMut.isOfflineQueued && (
                    <div className="mt-2 flex items-center gap-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-1.5">
                        <WifiOff className="w-3.5 h-3.5 flex-shrink-0" />
                        <span>Leituras registradas offline — pendente de sincronização</span>
                    </div>
                )}
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                {loading ? (
                    <div className="flex flex-col items-center justify-center py-20 gap-3">
                        <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                        <p className="text-sm text-surface-500">Carregando equipamentos...</p>
                    </div>
                ) : error ? (
                    <div className="bg-card rounded-xl p-4">
                        <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
                    </div>
                ) : (
                    <>
                        <div>
                            <h2 className="text-sm font-medium text-surface-700 mb-2 flex items-center gap-2">
                                <Gauge className="w-4 h-4" />
                                Selecione o equipamento
                            </h2>
                            <div className="grid gap-2">
                                {(equipmentList || []).map((eq) => (
                                    <button
                                        key={eq.id}
                                        type="button"
                                        onClick={() => handleSelectEquipment(eq)}
                                        className={cn(
                                            'bg-card rounded-xl p-4 text-left transition-colors',
                                            selectedEquipment?.id === eq.id
                                                ? 'ring-2 ring-brand-500'
                                                : 'hover:bg-surface-50 dark:hover:bg-surface-700/80'
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            <Scale className="w-5 h-5 text-brand-500" />
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    {eq.code || eq.name || `Equipamento #${eq.id}`}
                                                </p>
                                                {(eq.serial_number || eq.model) && (
                                                    <p className="text-xs text-surface-500">
                                                        {[eq.serial_number, eq.model].filter(Boolean).join(' • ')}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {selectedEquipment && calibrationId && (
                            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                                <div className="bg-card rounded-xl p-4">
                                    <div className="flex items-center justify-between mb-3">
                                        <h3 className="text-sm font-medium text-surface-700 flex items-center gap-2">
                                            <FlaskConical className="w-4 h-4" />
                                            Pontos de leitura
                                        </h3>
                                        <button
                                            type="button"
                                            onClick={() => append({ nominal: undefined as unknown as number, indication: undefined as unknown as number, unit: 'kg' })}
                                            className="flex items-center gap-1 text-sm text-brand-600"
                                        >
                                            <Plus className="w-4 h-4" /> Adicionar Leitura
                                        </button>
                                    </div>
                                    <div className="space-y-3">
                                        {fields.map((field, i) => {
                                            const r = watchReadings?.[i]
                                            const err = calcError(r?.nominal, r?.indication)
                                            return (
                                                <div
                                                    key={field.id}
                                                    className="flex flex-wrap items-end gap-2 p-3 rounded-lg bg-surface-50"
                                                >
                                                    <div className="flex-1 min-w-[80px]">
                                                        <label className="text-xs text-surface-500 block mb-1">
                                                            Nominal
                                                        </label>
                                                        <input
                                                            type="number"
                                                            step="0.0001"
                                                            {...register(`readings.${i}.nominal`)}
                                                            className={cn(inputClass, errors.readings?.[i]?.nominal && 'ring-2 ring-red-500/50')}
                                                            placeholder="0"
                                                        />
                                                    </div>
                                                    <div className="flex-1 min-w-[80px]">
                                                        <label className="text-xs text-surface-500 block mb-1">
                                                            Indicação
                                                        </label>
                                                        <input
                                                            type="number"
                                                            step="0.0001"
                                                            {...register(`readings.${i}.indication`)}
                                                            className={cn(inputClass, errors.readings?.[i]?.indication && 'ring-2 ring-red-500/50')}
                                                            placeholder="0"
                                                        />
                                                    </div>
                                                    <div className="w-12">
                                                        <label className="text-xs text-surface-500 block mb-1">
                                                            Erro
                                                        </label>
                                                        <p className="text-sm font-mono text-surface-700 py-2">
                                                            {err !== null ? err.toFixed(4) : '—'}
                                                        </p>
                                                    </div>
                                                    <div className="w-14">
                                                        <label className="text-xs text-surface-500 block mb-1" htmlFor={`reading-unit-${i}`}>
                                                            Unid.
                                                        </label>
                                                        <input
                                                            id={`reading-unit-${i}`}
                                                            type="text"
                                                            {...register(`readings.${i}.unit`)}
                                                            className={cn(inputClass, errors.readings?.[i]?.unit && 'ring-2 ring-red-500/50')}
                                                            aria-label="Unidade da leitura"
                                                        />
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => remove(i)}
                                                        disabled={fields.length <= 1}
                                                        className="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg disabled:opacity-40"
                                                        aria-label="Remover leitura"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            )
                                        })}
                                        {errors.readings?.root && (
                                            <p className="text-xs text-red-500 mt-1">{errors.readings.root.message}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="bg-card rounded-xl p-4">
                                    <button
                                        type="button"
                                        onClick={() => setExcentricityExpanded((x) => !x)}
                                        className="w-full flex items-center justify-between text-left"
                                    >
                                        <span className="text-sm font-medium text-surface-700">
                                            Ensaio de Excentricidade
                                        </span>
                                        {excentricityExpanded ? (
                                            <ChevronUp className="w-5 h-5 text-surface-400" />
                                        ) : (
                                            <ChevronDown className="w-5 h-5 text-surface-400" />
                                        )}
                                    </button>
                                    {excentricityExpanded && (
                                        <div className="mt-3 space-y-3">
                                            <div>
                                                <label className="text-xs text-surface-500 block mb-1">
                                                    Carga aplicada (kg)
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    {...register('excentricity.load')}
                                                    className={cn(inputClass, errors.excentricity?.load && 'ring-2 ring-red-500/50')}
                                                    placeholder="0"
                                                />
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                {[
                                                    { key: 'center' as const, label: 'Centro' },
                                                    { key: 'front' as const, label: 'Frente' },
                                                    { key: 'back' as const, label: 'Traseira' },
                                                    { key: 'left' as const, label: 'Esquerda' },
                                                    { key: 'right' as const, label: 'Direita' },
                                                ].map(({ key, label }) => (
                                                <div key={key}>
                                                    <label className="text-xs text-surface-500 block mb-1">
                                                        {label}
                                                    </label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        {...register(`excentricity.${key}`)}
                                                        className={cn(inputClass, errors.excentricity?.[key] && 'ring-2 ring-red-500/50')}
                                                        placeholder="0"
                                                    />
                                                </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {maxExcentricity !== null && (
                                        <p className="mt-2 text-xs text-surface-500">
                                            Diferença máxima: {maxExcentricity.toFixed(4)}
                                        </p>
                                    )}
                                </div>

                                <div className="flex gap-2 items-center">
                                    <label className="text-sm text-surface-600" htmlFor="tolerance-input">
                                        Tolerância:
                                    </label>
                                    <div className="relative">
                                        <input
                                            id="tolerance-input"
                                            type="number"
                                            step="0.01"
                                            {...register('tolerance')}
                                            className={cn(inputClass, 'w-24', errors.tolerance && 'ring-2 ring-red-500/50')}
                                            aria-label="Tolerância em kg"
                                        />
                                        {errors.tolerance && <p className="text-xs text-red-500 mt-1 absolute -bottom-5">{errors.tolerance.message}</p>}
                                    </div>
                                </div>

                                <div
                                    className={cn(
                                        'rounded-xl p-4 flex items-center gap-3',
                                        passed
                                            ? 'bg-emerald-50'
                                            : 'bg-red-50'
                                    )}
                                >
                                    {passed ? (
                                        <>
                                            <CheckCircle2 className="w-8 h-8 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                                            <div>
                                                <p className="font-medium text-emerald-800 dark:text-emerald-300">
                                                    APROVADO
                                                </p>
                                                <p className="text-sm text-emerald-600 dark:text-emerald-400">
                                                    Dentro da tolerância
                                                </p>
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <XCircle className="w-8 h-8 text-red-600 dark:text-red-400 flex-shrink-0" />
                                            <div>
                                                <p className="font-medium text-red-800 dark:text-red-300">
                                                    REPROVADO
                                                </p>
                                                <p className="text-sm text-red-600 dark:text-red-400">
                                                    Erro acima da tolerância
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={saving || offlineMut.isPending || !isValid}
                                    className="w-full flex items-center justify-center gap-2 py-3 bg-brand-600 text-white rounded-xl font-medium disabled:opacity-50"
                                >
                                    {(saving || offlineMut.isPending) ? (
                                        <>
                                            <Loader2 className="w-5 h-5 animate-spin" />
                                            Salvando...
                                        </>
                                    ) : !offlineMut.isOnline ? (
                                        <>
                                            <WifiOff className="w-4 h-4" />
                                            <Save className="w-5 h-5" />
                                            Salvar Offline
                                        </>
                                    ) : (
                                        <>
                                            <Save className="w-5 h-5" />
                                            Salvar Leituras
                                        </>
                                    )}
                                </button>
                                {readingsSaved && (
                                    <button
                                        type="button"
                                        onClick={handleGenerateCertificate}
                                        disabled={generatingCert}
                                        className="w-full flex items-center justify-center gap-2 py-3 bg-emerald-600 text-white rounded-xl font-medium disabled:opacity-50 mt-2"
                                    >
                                        {generatingCert ? (
                                            <>
                                                <Loader2 className="w-5 h-5 animate-spin" />
                                                Gerando...
                                            </>
                                        ) : (
                                            <>
                                                <FileText className="w-5 h-5" />
                                                Gerar certificado
                                            </>
                                        )}
                                    </button>
                                )}
                            </form>
                        )}
                    </>
                )}
            </div>
        </div>
    )
}
