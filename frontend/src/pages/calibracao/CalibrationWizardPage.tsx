import { useState, useEffect, useCallback, useRef } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useMutation, useQueryClient, useQuery } from '@tanstack/react-query'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { safeArray } from '@/lib/safe-array'
import { getCalibrationReadingsPath } from '@/lib/calibration-utils'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { toast } from 'sonner'
import {
    ArrowLeft, ArrowRight, Check, ChevronRight, Wand2, FileCheck,
    RefreshCw, Save, AlertTriangle, CheckCircle, XCircle,
    Thermometer, Droplets, Wind, Scale, Target, RotateCcw, TrendingUp,
    Info, Plus, Trash2, ToggleLeft, ToggleRight, Printer, ClipboardCheck,
    HelpCircle, MapPin, Link2,
} from 'lucide-react'
import {
    useCalibrationCalculations,
    calculateReadingError,
    isConforming,
    calculateRepeatability,
} from '@/hooks/useCalibrationCalculations'
import {
    useCalibrationPrefill,
    useCalibrationSuggestedPoints,
    saveRepeatabilityTest,
    validateIso17025,
} from '@/hooks/useCalibrationPrefill'
import {
    CalibrationGuide,
    EnvironmentAlert,
    WeightClassAlert,
    Iso17025Progress,
} from '@/components/calibration/CalibrationGuide'
import type {
    CalibrationWizardFormData,
    ReadingRow,
    EccentricityRow,
    EccentricityPayload,
    GravityRef,
    ValidationResult,
    AvailableWeight,
    FilteredWeight,
} from '@/types/calibration'
import CertificateEmissionChecklistForm from '@/components/calibracao/CertificateEmissionChecklistForm'
import DecisionResultBanner from '@/components/calibracao/DecisionResultBanner'
import { equipmentApi } from '@/lib/equipment-api'
import type { DecisionResult } from '@/types/calibration'

// â”€â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
type WizardState = CalibrationWizardFormData

const PRECISION_CLASSES = ['I', 'II', 'III', 'IIII']

const STEP_LABELS = [
    { key: 'identification', label: 'Equipamento', icon: Scale },
    { key: 'environment', label: 'Condições', icon: Thermometer },
    { key: 'standards', label: 'Padrões', icon: Target },
    { key: 'readings', label: 'Leituras', icon: ChevronRight },
    { key: 'linearity', label: 'Linearidade', icon: TrendingUp },
    { key: 'eccentricity', label: 'Excentricidade', icon: RotateCcw },
    { key: 'repeatability', label: 'Repetibilidade', icon: RefreshCw },
    { key: 'verification', label: 'Verificação', icon: ClipboardCheck },
]

function emptyState(): WizardState {
    return {
        equipmentId: null,
        calibrationId: null,
        precisionClass: 'III',
        eValue: '',
        maxCapacity: '',
        capacityUnit: 'kg',
        verificationType: 'subsequent',
        temperature: '',
        humidity: '',
        pressure: '',
        calibrationLocation: '',
        calibrationLocationType: 'laboratory',
        calibrationMethod: 'Comparação direta com massas padrão',
        receivedDate: new Date().toISOString().split('T')[0],
        issuedDate: new Date().toISOString().split('T')[0],
        calibrationDate: new Date().toISOString().split('T')[0],
        gravityState: '',
        gravityCity: '',
        gravityAcceleration: '',
        laboratoryAddress: '',
        decisionRule: 'simple',
        scopeDeclaration: '',
        standardUsed: '',
        weightIds: [],
        readings: [emptyReading()],
        linearityTests: [],
        eccentricityTests: defaultEccentricityRows(),
        repeatabilityLoad: '',
        repeatabilityMeasurements: ['', '', '', '', '', '', '', '', '', ''],
        workOrderId: null,
    }
}

function emptyReading(): ReadingRow {
    return { referenceValue: '', indicationIncreasing: '', indicationDecreasing: '', kFactor: '2.00', unit: 'kg' }
}

function defaultEccentricityRows(): EccentricityRow[] {
    return ['Centro', 'Frente', 'Trás', 'Esquerda', 'Direita'].map((pos) => ({
        position: pos, loadApplied: '', indication: '',
    }))
}

function HelpTip({ text }: { text: string }) {
    return (
        <TooltipProvider delayDuration={200}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <HelpCircle className="h-3.5 w-3.5 text-muted-foreground cursor-help inline-block ml-1" />
                </TooltipTrigger>
                <TooltipContent side="top" className="max-w-xs text-xs">
                    <p>{text}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    )
}

// â”€â”€â”€ CalibrationWizardPage â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
export default function CalibrationWizardPage() {
    const navigate = useNavigate()
    const { equipmentId: paramEquipId, calibrationId: paramCalId } = useParams()
    const [searchParams] = useSearchParams()
    const qc = useQueryClient()

    const [step, setStep] = useState(0)
    const [state, setState] = useState<WizardState>(emptyState)
    const [isAdvancedMode, setIsAdvancedMode] = useState(false)
    const [validationResult, setValidationResult] = useState<ValidationResult | null>(null)
    const [isPrefillApplied, setIsPrefillApplied] = useState(false)
    const [isChecklistApproved, setIsChecklistApproved] = useState(false)
    const prefillAppliedRef = useRef(false)
    const pointsAppliedRef = useRef(false)
    const draftCreatingRef = useRef(false)

    const equipmentIdNum = paramEquipId ? parseInt(paramEquipId) : null
    const osIdFromQuery = searchParams.get('os') ? parseInt(searchParams.get('os')!) : null

    // â”€â”€â”€ Queries â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const { data: equipment } = useQuery({
        queryKey: ['equipment-detail', equipmentIdNum],
        queryFn: () => api.get(`/equipments/${equipmentIdNum}`).then((r) => unwrapData(r)),
        enabled: !!equipmentIdNum && equipmentIdNum > 0,
    })

    const { data: prefillResponse } = useCalibrationPrefill(equipmentIdNum)
    const { data: suggestedPoints } = useCalibrationSuggestedPoints(equipmentIdNum)

    const { data: gravityRefs } = useQuery<GravityRef[]>({
        queryKey: ['gravity-refs'],
        queryFn: () => api.get('/calibration/gravity').then((r) => safeArray<GravityRef>(r.data?.references ?? r.data)),
        staleTime: 60 * 60 * 1000,
    })

    const { data: availableWeights } = useQuery({
        queryKey: ['standard-weights-available'],
        queryFn: () => api.get('/standard-weights?status=active&per_page=200').then((r) => safeArray<AvailableWeight>(r.data)),
        staleTime: 5 * 60 * 1000,
    })

    const { data: workOrder } = useQuery({
        queryKey: ['work-order-detail', osIdFromQuery],
        queryFn: () => api.get(`/work-orders/${osIdFromQuery}`).then((r) => unwrapData(r)),
        enabled: !!osIdFromQuery,
    })

    const calculations = useCalibrationCalculations({
        precisionClass: state.precisionClass,
        eValue: parseFloat(state.eValue) || 0,
        maxCapacity: parseFloat(state.maxCapacity) || 0,
        verificationType: state.verificationType,
    })

    // â”€â”€â”€ Auto-create draft calibration â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const createDraftMutation = useMutation({
        mutationFn: (eqId: number) =>
            api.post(`/calibration/equipment/${eqId}/draft`, {
                work_order_id: osIdFromQuery || undefined,
            }).then((r) => unwrapData<{ calibration: { id: number; work_order_id: number | null } }>(r)),
        onSuccess: (data) => {
            const cal = data.calibration
            setState((prev) => ({ ...prev, calibrationId: cal.id, workOrderId: cal.work_order_id }))
            navigate(`/calibracao/wizard/${equipmentIdNum}/${cal.id}${osIdFromQuery ? `?os=${osIdFromQuery}` : ''}`, { replace: true })
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao iniciar calibracao')),
    })

    useEffect(() => {
        if (!equipmentIdNum || equipmentIdNum <= 0 || draftCreatingRef.current) return
        if (paramCalId) {
            setState((prev) => ({ ...prev, calibrationId: parseInt(paramCalId) }))
            return
        }
        if (!equipment) return
        draftCreatingRef.current = true
        createDraftMutation.mutate(equipmentIdNum)
    }, [equipmentIdNum, paramCalId, equipment])

    // â”€â”€â”€ Initialize from equipment â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    useEffect(() => {
        if (!equipment) return
        setState((prev) => ({
            ...prev,
            equipmentId: equipment.id,
            precisionClass: equipment.precision_class || 'III',
            eValue: String(equipment.resolution ?? ''),
            maxCapacity: String(equipment.capacity ?? ''),
            capacityUnit: equipment.capacity_unit || 'kg',
            workOrderId: osIdFromQuery ?? prev.workOrderId,
        }))
    }, [equipment, osIdFromQuery])

    // â”€â”€â”€ Auto-apply prefill â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    useEffect(() => {
        if (prefillAppliedRef.current || !prefillResponse?.prefilled || !prefillResponse.data) return
        prefillAppliedRef.current = true
        const d = prefillResponse.data
        setState((prev) => ({
            ...prev,
            temperature: d.temperature != null ? String(d.temperature) : prev.temperature,
            humidity: d.humidity != null ? String(d.humidity) : prev.humidity,
            pressure: d.pressure != null ? String(d.pressure) : prev.pressure,
            calibrationLocation: d.calibration_location ?? prev.calibrationLocation,
            calibrationLocationType: (d.calibration_location_type as WizardState['calibrationLocationType']) ?? prev.calibrationLocationType,
            calibrationMethod: d.calibration_method ?? prev.calibrationMethod,
            standardUsed: d.standard_used ?? prev.standardUsed,
            verificationType: (d.verification_type as WizardState['verificationType']) ?? prev.verificationType,
            eValue: d.verification_division_e != null ? String(d.verification_division_e) : prev.eValue,
            weightIds: d.weight_ids ?? prev.weightIds,
            gravityAcceleration: d.gravity_acceleration != null ? String(d.gravity_acceleration) : prev.gravityAcceleration,
            laboratoryAddress: d.laboratory_address ?? prev.laboratoryAddress,
            decisionRule: (d.decision_rule as WizardState['decisionRule']) ?? prev.decisionRule,
            readings: d.reading_structure?.length
                ? (d.reading_structure || []).map((r) => ({
                    referenceValue: String(r.reference_value),
                    indicationIncreasing: '',
                    indicationDecreasing: '',
                    kFactor: '2.00',
                    unit: r.unit || 'kg',
                }))
                : prev.readings,
        }))
        setIsPrefillApplied(true)
        toast.success('Dados pré-preenchidos automaticamente da calibração anterior')
    }, [prefillResponse])

    // â”€â”€â”€ Auto-apply suggested points (if no prefill readings) â”€â”€
    useEffect(() => {
        if (pointsAppliedRef.current || !suggestedPoints?.points?.length) return
        if (prefillResponse?.prefilled && prefillResponse.data?.reading_structure?.length) return
        pointsAppliedRef.current = true
        setState((prev) => ({
            ...prev,
            readings: (suggestedPoints.points || []).map((p) => ({
                referenceValue: String(p.load),
                indicationIncreasing: '',
                indicationDecreasing: '',
                kFactor: '2.00',
                unit: prev.capacityUnit,
            })),
            eccentricityTests: (prev.eccentricityTests || []).map((t) => ({
                ...t,
                loadApplied: String(suggestedPoints.eccentricity_load),
            })),
            repeatabilityLoad: String(suggestedPoints.repeatability_load),
        }))
        toast.info('Pontos de medição sugeridos aplicados automaticamente')
    }, [suggestedPoints, prefillResponse])

    // â”€â”€â”€ Gravity states/cities â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const gravityStates = [...new Set((gravityRefs ?? []).map((g) => g.state))].sort()
    const gravityCities = (gravityRefs ?? []).filter((g) => g.state === state.gravityState)

    const handleGravityStateChange = (st: string) => {
        const cities = (gravityRefs ?? []).filter((g) => g.state === st)
        const firstCity = cities[0]
        setState((prev) => ({
            ...prev,
            gravityState: st,
            gravityCity: firstCity?.city ?? '',
            gravityAcceleration: firstCity ? String(firstCity.gravity) : prev.gravityAcceleration,
        }))
    }

    const handleGravityCityChange = (city: string) => {
        const ref = (gravityRefs ?? []).find((g) => g.state === state.gravityState && g.city === city)
        setState((prev) => ({
            ...prev,
            gravityCity: city,
            gravityAcceleration: ref ? String(ref.gravity) : prev.gravityAcceleration,
        }))
    }

    // â”€â”€â”€ Filtered weights for balance class â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const minWeightClass = calculations.procedureConfig?.minWeightClass
    const WEIGHT_CLASS_RANK: Record<string, number> = {
        E1: 1, E2: 2, F1: 3, F2: 4, M1: 5, 'M1-2': 6, M2: 7, 'M2-3': 8, M3: 9,
    }
    const minRank = minWeightClass ? (WEIGHT_CLASS_RANK[minWeightClass] ?? 99) : 99
    const filteredWeights = (availableWeights ?? []).map((w: AvailableWeight): FilteredWeight => ({
        ...w,
        adequate: (WEIGHT_CLASS_RANK[w.precision_class] ?? 99) <= minRank,
        expired: !!w.certificate_expiry && new Date(w.certificate_expiry) < new Date(),
    })).sort((a: FilteredWeight, b: FilteredWeight) => {
        if (a.adequate && !b.adequate) return -1
        if (!a.adequate && b.adequate) return 1
        return (WEIGHT_CLASS_RANK[a.precision_class] ?? 99) - (WEIGHT_CLASS_RANK[b.precision_class] ?? 99)
    })

    // â”€â”€â”€ Mutations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const saveReadingsMutation = useMutation({
        mutationFn: (data: { readings: ReadingRow[] }) =>
            api.post(`/calibration/${state.calibrationId}/readings`, data).then((r) => unwrapData(r)),
        onSuccess: () => toast.success('Leituras salvas'),
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar leituras')),
    })

    const saveEccentricityMutation = useMutation({
        mutationFn: (data: { tests: EccentricityPayload[] }) =>
            api.post(`/calibration/${state.calibrationId}/excentricity`, data).then((r) => unwrapData(r)),
        onSuccess: () => toast.success('Ensaio de excentricidade salvo'),
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar excentricidade')),
    })

    const generateCertMutation = useMutation({
        mutationFn: () =>
            api.post(`/calibration/${state.calibrationId}/generate-certificate`).then((r) => unwrapData<{ certificate_number?: string }>(r)),
        onSuccess: (data) => {
            toast.success(`Certificado ${data.certificate_number} gerado!`)
            qc.invalidateQueries({ queryKey: ['calibrations'] })
            if (state.calibrationId) {
                navigate(getCalibrationReadingsPath(state.calibrationId))
            }
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao gerar certificado')),
    })

    // â”€â”€â”€ ISO 17025 §7.8.6 — Decision rule evaluation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const [decisionResult, setDecisionResult] = useState<DecisionResult | null>(null)
    const [decisionGuardBandMode, setDecisionGuardBandMode] =
        useState<'k_times_u' | 'percent_limit' | 'fixed_abs'>('k_times_u')
    const [decisionGuardBandValue, setDecisionGuardBandValue] = useState<string>('1.0')
    const [decisionAlpha, setDecisionAlpha] = useState<string>('0.05')
    const [decisionBeta, setDecisionBeta] = useState<string>('0.05')

    const evaluateDecisionMutation = useMutation({
        mutationFn: () => {
            if (!state.calibrationId) throw new Error('Calibração não inicializada')
            const payload: Parameters<typeof equipmentApi.evaluateDecision>[1] = {
                rule: state.decisionRule,
                coverage_factor_k: 2.0,
                confidence_level: 95.45,
            }
            if (state.decisionRule === 'guard_band') {
                payload.guard_band_mode = decisionGuardBandMode
                payload.guard_band_value = parseFloat(decisionGuardBandValue) || 0
            } else if (state.decisionRule === 'shared_risk') {
                payload.producer_risk_alpha = parseFloat(decisionAlpha) || 0.05
                payload.consumer_risk_beta = parseFloat(decisionBeta) || 0.05
            }
            return equipmentApi.evaluateDecision(state.calibrationId, payload)
        },
        onSuccess: (data) => {
            setDecisionResult(data.decision as DecisionResult)
            toast.success(`Avaliação concluída: ${data.decision.result?.toUpperCase()}`)
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao avaliar conformidade')),
    })

    const updateWizardMutation = useMutation({
        mutationFn: (data: Record<string, unknown>) =>
            api.put(`/calibration/${state.calibrationId}/wizard`, data).then((r) => unwrapData(r)),
    })

    const syncWeightsMutation = useMutation({
        mutationFn: (weightIds: number[]) =>
            api.post(`/calibration/${state.calibrationId}/weights`, { weight_ids: weightIds }).then((r) => unwrapData(r)),
    })

    // â”€â”€â”€ Save current step to server â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const saveStepToServer = useCallback((currentStep: number) => {
        if (!state.calibrationId) return

        if (currentStep === 0 || currentStep === 1) {
            updateWizardMutation.mutate({
                temperature: state.temperature ? parseFloat(state.temperature) : null,
                humidity: state.humidity ? parseFloat(state.humidity) : null,
                pressure: state.pressure ? parseFloat(state.pressure) : null,
                calibration_location: state.calibrationLocation || null,
                calibration_location_type: state.calibrationLocationType,
                calibration_method: state.calibrationMethod || null,
                calibration_date: state.calibrationDate || null,
                received_date: state.receivedDate || null,
                issued_date: state.issuedDate || null,
                verification_type: state.verificationType,
                verification_division_e: state.eValue ? parseFloat(state.eValue) : null,
                gravity_acceleration: state.gravityAcceleration ? parseFloat(state.gravityAcceleration) : null,
                laboratory_address: state.laboratoryAddress || null,
                decision_rule: state.decisionRule,
                scope_declaration: state.scopeDeclaration || null,
                work_order_id: state.workOrderId,
            })
        }
        if (currentStep === 2 && state.weightIds.length) {
            syncWeightsMutation.mutate(state.weightIds)
        }
        if (currentStep === 3) {
            const validReadings = (state.readings || []).filter((r) => r.referenceValue)
            if (validReadings.length) saveReadingsMutation.mutate({ readings: validReadings })
        }
        if (currentStep === 4) {
            const validTests = (state.eccentricityTests || []).filter((t) => t.loadApplied && t.indication)
            if (validTests.length) {
                saveEccentricityMutation.mutate({
                    tests: (validTests || []).map((t) => ({
                        position: t.position,
                        load_applied: parseFloat(t.loadApplied),
                        indication: parseFloat(t.indication),
                    })),
                })
            }
        }
    }, [state, updateWizardMutation, syncWeightsMutation, saveReadingsMutation, saveEccentricityMutation])

    // â”€â”€â”€ Step-level validation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const getStepErrors = useCallback((s: number): string[] => {
        const errors: string[] = []
        if (s === 0) {
            if (!state.eValue || parseFloat(state.eValue) <= 0) errors.push('Divisão de verificação (e) é obrigatória')
            if (!state.maxCapacity || parseFloat(state.maxCapacity) <= 0) errors.push('Capacidade máxima é obrigatória')
        }
        if (s === 1) {
            if (!state.temperature) errors.push('Temperatura é obrigatória')
            if (!state.humidity) errors.push('Umidade é obrigatória')
            if (!state.pressure) errors.push('Pressão atmosférica é obrigatória (ISO 17025 §7.7.1)')
            else if (parseFloat(state.pressure) < 800 || parseFloat(state.pressure) > 1100) errors.push('Pressão deve estar entre 800 e 1100 hPa')
            if (!state.calibrationDate) errors.push('Data da calibração é obrigatória')
        }
        if (s === 3) {
            const filled = (state.readings || []).filter((r) => r.referenceValue && r.indicationIncreasing)
            if (filled.length < 3) errors.push('Mínimo de 3 leituras com indicação preenchida')
        }
        if (s === 5) {
            const valid = (state.repeatabilityMeasurements || []).filter((v) => v && !isNaN(parseFloat(v)))
            if (valid.length < 6) errors.push('Mínimo de 6 medições de repetibilidade')
        }
        return errors
    }, [state])

    // â”€â”€â”€ Navigation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const canNext = step < STEP_LABELS.length - 1
    const canPrev = step > 0

    const goNext = useCallback(() => {
        const errors = getStepErrors(step)
        if (errors.length > 0) {
            (errors || []).forEach((e) => toast.warning(e))
            return
        }
        saveStepToServer(step)
        if (canNext) setStep((s) => s + 1)
    }, [step, canNext, getStepErrors, saveStepToServer])

    const goPrev = useCallback(() => {
        if (canPrev) setStep((s) => s - 1)
    }, [canPrev])

    // â”€â”€â”€ Final: validate + generate â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const handleFinish = useCallback(async () => {
        if (!state.calibrationId) {
            toast.error('Nenhuma calibração selecionada')
            return
        }

        saveStepToServer(1)

        const validMeasurements = (state.repeatabilityMeasurements || []).map((v) => parseFloat(v)).filter((v) => !isNaN(v))
        if (validMeasurements.length >= 2) {
            try {
                await saveRepeatabilityTest(state.calibrationId, parseFloat(state.repeatabilityLoad) || 0, validMeasurements, state.capacityUnit)
            } catch (err: unknown) { toast.error(getApiErrorMessage(err, 'Erro ao salvar repetibilidade')) }
        }

        try {
            const result = await validateIso17025(state.calibrationId)
            setValidationResult(result)
            if (!result.complete) {
                toast.warning(`Certificado incompleto: ${result.missing_fields?.length ?? 0} campos faltando`)
                return
            }
        } catch { /* continue */ }

        generateCertMutation.mutate()
    }, [state, generateCertMutation, saveStepToServer])

    const handleSaveDraft = useCallback(async () => {
        if (!state.calibrationId) { toast.error('Nenhuma calibração selecionada'); return }
        saveStepToServer(0)
        saveStepToServer(1)
        saveStepToServer(2)
        saveStepToServer(3)
        saveStepToServer(4)
        const validMeasurements = (state.repeatabilityMeasurements || []).map((v) => parseFloat(v)).filter((v) => !isNaN(v))
        if (validMeasurements.length >= 2) {
            try { await saveRepeatabilityTest(state.calibrationId, parseFloat(state.repeatabilityLoad) || 0, validMeasurements, state.capacityUnit) } catch { /* silent */ }
        }
        toast.success('Rascunho salvo com sucesso')
    }, [state, saveStepToServer])

    // â”€â”€â”€ Render helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const update = (field: keyof WizardState, value: WizardState[keyof WizardState]) => setState((prev) => ({ ...prev, [field]: value }))
    const updateReading = (index: number, field: keyof ReadingRow, value: string) =>
        setState((prev) => ({ ...prev, readings: (prev.readings || []).map((r, i) => (i === index ? { ...r, [field]: value } : r)) }))
    const updateEccentricity = (index: number, field: keyof EccentricityRow, value: string) =>
        setState((prev) => ({ ...prev, eccentricityTests: (prev.eccentricityTests || []).map((t, i) => i === index ? { ...t, [field]: value } : t) }))
    const updateRepMeasurement = (index: number, value: string) =>
        setState((prev) => ({ ...prev, repeatabilityMeasurements: (prev.repeatabilityMeasurements || []).map((m, i) => i === index ? value : m) }))

    const toggleWeight = (id: number) => {
        setState((prev) => ({
            ...prev,
            weightIds: prev.weightIds.includes(id) ? (prev.weightIds || []).filter((w) => w !== id) : [...prev.weightIds, id],
        }))
    }

    // â”€â”€â”€ Step 1: Identification â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const renderStep1 = () => (
        <div className="space-y-6">
            <CalibrationGuide step="identification" compact={isAdvancedMode} />

            {workOrder && (
                <div className="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-teal-50 dark:bg-teal-950/30 border border-teal-200 dark:border-teal-800">
                    <Link2 className="h-4 w-4 text-teal-600 shrink-0" />
                    <span className="text-sm text-teal-700 dark:text-teal-300">
                        Vinculada à <strong>OS #{workOrder.os_number || workOrder.number}</strong> - {workOrder.customer?.name}
                    </span>
                </div>
            )}

            {workOrder?.requires_maintenance && (
                <div className="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
                    <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
                    <span className="text-sm text-amber-700 dark:text-amber-300">
                        <strong>Atenção:</strong> Esta OS requer manutenção. Verifique se o relatório de manutenção foi preenchido antes de prosseguir com a calibração.
                    </span>
                </div>
            )}

            {workOrder?.subject_to_legal_metrology && (
                <div className="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800">
                    <AlertTriangle className="h-4 w-4 text-blue-600 shrink-0" />
                    <span className="text-sm text-blue-700 dark:text-blue-300">
                        Instrumento sujeito à <strong>metrologia legal</strong>.
                        {workOrder.needs_ipem_interaction && ' Necessita interação com IPEM.'}
                    </span>
                </div>
            )}

            {calculations.procedureConfig && (
                <div className="flex flex-wrap gap-2 text-xs">
                    <span className="px-2 py-1 rounded bg-green-100 text-green-800 font-medium">
                        {calculations.procedureConfig.classLabel}
                    </span>
                    <span className="px-2 py-1 rounded bg-blue-100 text-blue-800">
                        Peso mín: classe {calculations.procedureConfig.minWeightClass}
                    </span>
                    <span className="px-2 py-1 rounded bg-teal-100 text-teal-800">
                        Decimais: {calculations.procedureConfig.decimalPlaces}
                    </span>
                    <span className="px-2 py-1 rounded bg-amber-100 text-amber-800">
                        Mín. {calculations.procedureConfig.minLinearityPoints} pontos
                    </span>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {equipment && (
                    <Card className="col-span-full bg-muted/30">
                        <CardContent className="pt-4">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div><span className="text-muted-foreground">Marca:</span><p className="font-medium">{equipment.brand || '—'}</p></div>
                                <div><span className="text-muted-foreground">Modelo:</span><p className="font-medium">{equipment.model || '—'}</p></div>
                                <div><span className="text-muted-foreground">Nº Série:</span><p className="font-medium">{equipment.serial_number || '—'}</p></div>
                                <div><span className="text-muted-foreground">Código:</span><p className="font-medium">{equipment.code || '—'}</p></div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="space-y-2">
                    <Label htmlFor="precisionClass">
                        Classe de Exatidão
                        <HelpTip text="Classificação da balança conforme Portaria INMETRO 157/2022. Classe I: especial (analítica), II: fina (semi-analítica), III: média (plataforma), IIII: ordinária (rodoviária)." />
                    </Label>
                    <select id="precisionClass" value={state.precisionClass} onChange={(e) => update('precisionClass', e.target.value)} className="w-full border rounded-md px-3 py-2 text-sm bg-background" aria-label="Classe de Exatidão">
                        {(PRECISION_CLASSES || []).map((c) => <option key={c} value={c}>Classe {c}</option>)}
                    </select>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="eValue">
                        Divisão de Verificação (e)
                        <HelpTip text="Valor 'e' é a divisão de verificação usada para calcular o EMA. Geralmente igual à resolução (d). Exemplo: se a balança mostra 0.001 kg, então e = 0.001." />
                    </Label>
                    <Input id="eValue" type="number" step="0.0001" value={state.eValue} onChange={(e) => update('eValue', e.target.value)} placeholder="Ex: 0.001" />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="maxCapacity">Capacidade Máxima (Max)</Label>
                    <div className="flex gap-2">
                        <Input id="maxCapacity" type="number" step="0.01" value={state.maxCapacity} onChange={(e) => update('maxCapacity', e.target.value)} placeholder="Ex: 150" className="flex-1" />
                        <select value={state.capacityUnit} onChange={(e) => update('capacityUnit', e.target.value)} className="border rounded-md px-3 py-2 text-sm bg-background w-20" aria-label="Unidade">
                            <option value="kg">kg</option><option value="g">g</option><option value="mg">mg</option><option value="t">t</option>
                        </select>
                    </div>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="verificationType">
                        Tipo de Verificação
                        <HelpTip text="Inicial: balança nova ou após reparo. Subsequente: recalibração periódica. Em Uso: fiscalização pelo IPEM (EMA dobrado)." />
                    </Label>
                    <select id="verificationType" value={state.verificationType} onChange={(e) => update('verificationType', e.target.value as WizardState['verificationType'])} className="w-full border rounded-md px-3 py-2 text-sm bg-background" aria-label="Tipo de Verificação">
                        <option value="initial">Verificação Inicial</option>
                        <option value="subsequent">Verificação Subsequente</option>
                        <option value="in_use">Verificação em Uso (Fiscalização)</option>
                    </select>
                </div>
            </div>

            {isPrefillApplied && (
                <div className="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 text-sm">
                    <ClipboardCheck className="h-4 w-4 text-blue-600 shrink-0" />
                    <span className="text-blue-700 dark:text-blue-300 flex-1"><strong>Memória ativa</strong> — Dados pré-preenchidos da calibração anterior</span>
                </div>
            )}
        </div>
    )

    // â”€â”€â”€ Step 2: Environmental Conditions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const renderStep2 = () => (
        <div className="space-y-6">
            <CalibrationGuide step="environment" compact={isAdvancedMode} />
            <EnvironmentAlert temperature={state.temperature ? parseFloat(state.temperature) : undefined} humidity={state.humidity ? parseFloat(state.humidity) : undefined} />

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="temperature" className="flex items-center gap-2"><Thermometer className="h-4 w-4 text-orange-500" /> Temperatura (°C)</Label>
                    <Input id="temperature" type="number" step="0.1" value={state.temperature} onChange={(e) => update('temperature', e.target.value)} placeholder="23.0" />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="humidity" className="flex items-center gap-2"><Droplets className="h-4 w-4 text-blue-500" /> Umidade (%RH)</Label>
                    <Input id="humidity" type="number" step="0.1" value={state.humidity} onChange={(e) => update('humidity', e.target.value)} placeholder="50.0" />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="pressure" className="flex items-center gap-2"><Wind className="h-4 w-4 text-gray-500" /> Pressão (hPa) <span className="text-red-500">*</span></Label>
                    <Input id="pressure" type="number" step="0.1" value={state.pressure} onChange={(e) => update('pressure', e.target.value)} placeholder="1013.25" />
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="space-y-2">
                    <Label>
                        <MapPin className="h-4 w-4 text-green-600 inline mr-1" />
                        Gravidade Local (m/s²)
                        <HelpTip text="Aceleração da gravidade no local da calibração. Obrigatório para ISO 17025. Selecione o estado/cidade ou digite manualmente." />
                    </Label>
                    <div className="grid grid-cols-2 gap-2">
                        <select value={state.gravityState} onChange={(e) => handleGravityStateChange(e.target.value)} className="border rounded-md px-3 py-2 text-sm bg-background" aria-label="Estado">
                            <option value="">Estado...</option>
                            {(gravityStates || []).map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                        <select value={state.gravityCity} onChange={(e) => handleGravityCityChange(e.target.value)} className="border rounded-md px-3 py-2 text-sm bg-background" disabled={!state.gravityState} aria-label="Cidade">
                            <option value="">Cidade...</option>
                            {(gravityCities || []).map((g) => <option key={g.city} value={g.city}>{g.city}</option>)}
                        </select>
                    </div>
                    <Input type="number" step="0.0001" value={state.gravityAcceleration} onChange={(e) => update('gravityAcceleration', e.target.value)} placeholder="9.7862" />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="calibrationLocation">Local da Calibração</Label>
                    <Input id="calibrationLocation" value={state.calibrationLocation} onChange={(e) => update('calibrationLocation', e.target.value)} placeholder="Endereço do laboratório" />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="locationType">Tipo de Local</Label>
                    <select id="locationType" value={state.calibrationLocationType} onChange={(e) => update('calibrationLocationType', e.target.value)} className="w-full border rounded-md px-3 py-2 text-sm bg-background" aria-label="Tipo de Local">
                        <option value="laboratory">Laboratório</option>
                        <option value="field">Campo</option>
                        <option value="client_site">Nas dependências do cliente</option>
                    </select>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="laboratoryAddress">
                        Endereço do Laboratório (ISO 17025 §7.8.2.1b)
                        <HelpTip text="Endereço completo do laboratório que realiza a calibração. Obrigatório no certificado conforme ISO 17025." />
                    </Label>
                    <Input id="laboratoryAddress" value={state.laboratoryAddress} onChange={(e) => update('laboratoryAddress', e.target.value)} placeholder="Rua, nº, bairro, cidade - UF" />
                </div>
                <div className="space-y-2">
                    <Label>
                        Regra de Decisão (ISO 17025 §7.8.6)
                        <HelpTip text="Fonte: acordo definido na OS (ILAC G8 §3). Para sobrescrever com justificativa, use 'Avaliar Conformidade' no final do wizard." />
                    </Label>
                    <div
                        className="w-full rounded-md border border-dashed bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
                        aria-label="Regra de Decisão (acordada na OS)"
                    >
                        {state.decisionRule === 'simple' && 'Simples — acordada na OS'}
                        {state.decisionRule === 'guard_band' && 'Banda de Guarda — acordada na OS'}
                        {state.decisionRule === 'shared_risk' && 'Risco Compartilhado — acordada na OS'}
                        {!state.decisionRule && 'Não definida na OS'}
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="calibrationDate">Data da Calibração</Label>
                    <Input id="calibrationDate" type="date" value={state.calibrationDate} onChange={(e) => update('calibrationDate', e.target.value)} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="receivedDate">Data de Recebimento</Label>
                    <Input id="receivedDate" type="date" value={state.receivedDate} onChange={(e) => update('receivedDate', e.target.value)} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="issuedDate">Data de Emissão</Label>
                    <Input id="issuedDate" type="date" value={state.issuedDate} onChange={(e) => update('issuedDate', e.target.value)} />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="calibrationMethod">Método de Calibração</Label>
                <Input id="calibrationMethod" value={state.calibrationMethod} onChange={(e) => update('calibrationMethod', e.target.value)} placeholder="Comparação direta com massas padrão" />
            </div>
        </div>
    )

    // â”€â”€â”€ Step 3: Standard Weights â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const renderStep3 = () => (
        <div className="space-y-6">
            <CalibrationGuide step="standards" compact={isAdvancedMode} />

            <WeightClassAlert
                balanceClass={state.precisionClass}
                weights={(filteredWeights || []).filter((w: FilteredWeight) => state.weightIds.includes(w.id)).map((w: FilteredWeight) => ({
                    code: w.code, precision_class: w.precision_class, certificate_expiry: w.certificate_expiry,
                }))}
            />

            {filteredWeights.length > 0 ? (
                <div className="space-y-3">
                    <Label>Selecione os Pesos Padrão
                        <HelpTip text={`Para balança classe ${state.precisionClass}, os pesos devem ser classe ${minWeightClass} ou superior (OIML R111-1). Pesos inadequados aparecem em cinza.`} />
                    </Label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto">
                        {(filteredWeights || []).map((w: FilteredWeight) => {
                            const selected = state.weightIds.includes(w.id)
                            return (
                                <button
                                    key={w.id} type="button" onClick={() => !w.expired && toggleWeight(w.id)}
                                    className={`text-left p-3 rounded-lg border text-sm transition-all ${
                                        w.expired ? 'opacity-40 cursor-not-allowed border-red-300 bg-red-50 dark:bg-red-950/20' :
                                        selected ? 'border-green-500 bg-green-50 dark:bg-green-950/30 ring-1 ring-green-500' :
                                        !w.adequate ? 'opacity-60 border-dashed border-amber-300' :
                                        'border-border hover:border-primary/50 hover:bg-muted/50'
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium">{w.code}</span>
                                        {selected && <CheckCircle className="h-4 w-4 text-green-600" />}
                                        {w.expired && <span className="text-[10px] text-red-600 font-medium">VENCIDO</span>}
                                        {!w.adequate && !w.expired && <span className="text-[10px] text-amber-600">CLASSE BAIXA</span>}
                                    </div>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {w.nominal_value} {w.unit} · Classe {w.precision_class}
                                        {w.certificate_number && ` · Cert. ${w.certificate_number}`}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-1 mt-1">
                                        {w.laboratory_accreditation ? (
                                            <span className="inline-flex items-center gap-0.5 rounded-full bg-blue-100 dark:bg-blue-900/30 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 dark:text-blue-300">
                                                <CheckCircle className="h-2.5 w-2.5" /> {w.laboratory_accreditation}
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300">
                                                <AlertTriangle className="h-2.5 w-2.5" /> Sem acreditacao
                                            </span>
                                        )}
                                        {w.certificate_expiry && new Date(w.certificate_expiry) < new Date() && (
                                            <span className="inline-flex items-center gap-0.5 rounded-full bg-red-100 dark:bg-red-900/30 px-1.5 py-0.5 text-[10px] font-medium text-red-700 dark:text-red-300">
                                                <XCircle className="h-2.5 w-2.5" /> Cert. vencido
                                            </span>
                                        )}
                                        {w.certificate_expiry && new Date(w.certificate_expiry) >= new Date() && (() => {
                                            const daysLeft = Math.ceil((new Date(w.certificate_expiry).getTime() - Date.now()) / 86400000)
                                            if (daysLeft <= 30) return (
                                                <span className="inline-flex items-center gap-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300">
                                                    <AlertTriangle className="h-2.5 w-2.5" /> Vence em {daysLeft}d
                                                </span>
                                            )
                                            return null
                                        })()}
                                    </div>
                                </button>
                            )
                        })}
                    </div>
                </div>
            ) : (
                <Card className="bg-amber-50 dark:bg-amber-950/20 border-amber-200">
                    <CardContent className="pt-4 flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-amber-600" />
                        <p className="text-sm">Nenhum peso padrão cadastrado. Cadastre pesos em Equipamentos → Pesos Padrão.</p>
                    </CardContent>
                </Card>
            )}

            <div className="space-y-2">
                <Label htmlFor="standardUsed">Descrição dos Padrões (para o certificado)</Label>
                <textarea id="standardUsed" className="w-full min-h-[80px] border rounded-md px-3 py-2 text-sm bg-background resize-y" value={state.standardUsed} onChange={(e) => update('standardUsed', e.target.value)} placeholder="Ex: Jogo de massas padrão classe F1, certificado RBC nº XXX-XXXX, validade XX/XX/XXXX" />
            </div>
        </div>
    )

    // â”€â”€â”€ Step 4: Readings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const renderStep4 = () => {
        const eVal = parseFloat(state.eValue) || 0
        return (
            <div className="space-y-4">
                <CalibrationGuide step="readings" compact={isAdvancedMode} />
                <div className="flex items-center gap-2 justify-between flex-wrap">
                    <div className="flex gap-2">
                        <Button size="sm" variant="outline" onClick={() => {
                            if (!suggestedPoints?.points?.length) return
                            setState((prev) => ({
                                ...prev,
                                readings: (suggestedPoints.points || []).map((p) => ({
                                    referenceValue: String(p.load), indicationIncreasing: '', indicationDecreasing: '', kFactor: '2.00', unit: prev.capacityUnit,
                                })),
                            }))
                            toast.info('Pontos sugeridos aplicados')
                        }} disabled={!suggestedPoints}>
                            <Wand2 className="h-4 w-4 mr-1" /> Sugerir Pontos
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => setState((prev) => ({ ...prev, readings: [...prev.readings, emptyReading()] }))}>
                            <Plus className="h-4 w-4 mr-1" /> Adicionar
                        </Button>
                    </div>
                    {eVal > 0 && (
                        <p className="text-xs text-muted-foreground">
                            e = {eVal} {state.capacityUnit} | Classe {state.precisionClass} | {state.verificationType === 'in_use' ? 'Em Uso (2×)' : state.verificationType === 'initial' ? 'Inicial' : 'Subsequente'}
                        </p>
                    )}
                </div>

                <div className="overflow-x-auto -mx-4 px-4 md:mx-0 md:px-0">
                    <table className="w-full text-sm min-w-[700px]">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="p-2 w-10">#</th>
                                <th className="p-2">Ref. ({state.capacityUnit})</th>
                                <th className="p-2">Indicação ↑</th>
                                <th className="p-2">Indicação ↓</th>
                                <th className="p-2">k</th>
                                <th className="p-2">Erro</th>
                                <th className="p-2">EMA<HelpTip text="Erro Máximo Admissível: limite calculado automaticamente pela Portaria INMETRO 157/2022 para a classe e carga." /></th>
                                <th className="p-2">Status</th>
                                <th className="p-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {(state.readings || []).map((r, i) => {
                                const ref = parseFloat(r.referenceValue) || 0
                                const ind = parseFloat(r.indicationIncreasing) || 0
                                const error = r.indicationIncreasing ? calculateReadingError(ind, ref) : null
                                const ema = eVal > 0 && ref > 0 ? calculations.calculateEmaForLoad(ref) : null
                                const conforming = error !== null && ema !== null ? isConforming(error, ema) : null

                                return (
                                    <tr key={i} className="border-b hover:bg-muted/50">
                                        <td className="p-2 text-muted-foreground">{i + 1}</td>
                                        <td className="p-2"><Input type="number" step="0.0001" value={r.referenceValue} onChange={(e) => updateReading(i, 'referenceValue', e.target.value)} className="w-24 md:w-28" placeholder="0.0000" aria-label={`Ref ${i + 1}`} /></td>
                                        <td className="p-2"><Input type="number" step="0.0001" value={r.indicationIncreasing} onChange={(e) => updateReading(i, 'indicationIncreasing', e.target.value)} className="w-24 md:w-28" placeholder="0.0000" aria-label={`Ind↑ ${i + 1}`} /></td>
                                        <td className="p-2"><Input type="number" step="0.0001" value={r.indicationDecreasing} onChange={(e) => updateReading(i, 'indicationDecreasing', e.target.value)} className="w-24 md:w-28" placeholder="0.0000" aria-label={`Ind↓ ${i + 1}`} /></td>
                                        <td className="p-2"><Input type="number" step="0.01" value={r.kFactor} onChange={(e) => updateReading(i, 'kFactor', e.target.value)} className="w-16" aria-label={`k ${i + 1}`} /></td>
                                        <td className="p-2 font-mono text-right">{error !== null ? error.toFixed(4) : '—'}</td>
                                        <td className="p-2 font-mono text-right text-muted-foreground">{ema !== null ? `±${ema.toFixed(4)}` : '—'}</td>
                                        <td className="p-2 text-center">{conforming === null ? <span className="text-muted-foreground">—</span> : conforming ? <CheckCircle className="h-4 w-4 text-green-600 inline-block" /> : <XCircle className="h-4 w-4 text-red-600 inline-block" />}</td>
                                        <td className="p-2"><Button size="icon" variant="ghost" onClick={() => setState((prev) => ({ ...prev, readings: (prev.readings || []).filter((_, j) => j !== i) }))} disabled={state.readings.length <= 1} aria-label={`Remover ${i + 1}`}><Trash2 className="h-4 w-4 text-destructive" /></Button></td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        )
    }

    // â”€â”€â”€ Step 5: Linearity â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const renderStepLinearity = () => {
        const linearityPoints = state.linearityTests ?? []
        const eVal = parseFloat(state.eValue) || 0
        const pClass = state.precisionClass || 'III'
        const maxCap = parseFloat(state.maxCapacity) || 150

        const handleAddLinearityPoint = () => {
            const nextRef = linearityPoints.length === 0
                ? maxCap * 0.25
                : Math.min(maxCap, (linearityPoints[linearityPoints.length - 1]?.reference_value ?? 0) + maxCap * 0.25)
            setState(prev => ({
                ...prev,
                linearityTests: [
                    ...(prev.linearityTests ?? []),
                    { reference_value: parseFloat(nextRef.toFixed(4)), indication_increasing: '', indication_decreasing: '', unit: prev.capacityUnit || 'kg' },
                ],
            }))
        }

        const handleSuggestLinearityPoints = () => {
            const points = [0.10, 0.25, 0.50, 0.75, 1.0].map(pct => ({
                reference_value: parseFloat((maxCap * pct).toFixed(4)),
                indication_increasing: '',
                indication_decreasing: '',
                unit: state.capacityUnit || 'kg',
            }))
            setState(prev => ({ ...prev, linearityTests: points }))
        }

        const updateLinearityPoint = (idx: number, field: string, value: string) => {
            setState(prev => {
                const tests = [...(prev.linearityTests ?? [])]
                tests[idx] = { ...tests[idx], [field]: value }
                return { ...prev, linearityTests: tests }
            })
        }

        const removeLinearityPoint = (idx: number) => {
            setState(prev => ({
                ...prev,
                linearityTests: (prev.linearityTests ?? []).filter((_, i) => i !== idx),
            }))
        }

        return (
            <div className="space-y-4">
                <div className="flex items-center gap-2 mb-3">
                    <Button type="button" variant="outline" size="sm" onClick={handleSuggestLinearityPoints}>
                        <Wand2 className="h-4 w-4 mr-1" /> Sugerir 5 pontos
                    </Button>
                    <Button type="button" variant="outline" size="sm" onClick={handleAddLinearityPoint}>
                        <Plus className="h-4 w-4 mr-1" /> Adicionar ponto
                    </Button>
                </div>

                {linearityPoints.length === 0 ? (
                    <div className="text-center py-8 text-muted-foreground">
                        <TrendingUp className="h-8 w-8 mx-auto mb-2 opacity-50" />
                        <p>Nenhum ponto de linearidade. Clique em "Sugerir 5 pontos" para iniciar.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="py-2 px-2 w-8">#</th>
                                    <th className="py-2 px-2">Referência ({state.capacityUnit || 'kg'})</th>
                                    <th className="py-2 px-2">Indicação ↑</th>
                                    <th className="py-2 px-2">Indicação ↓</th>
                                    <th className="py-2 px-2 text-center">Erro ↑</th>
                                    <th className="py-2 px-2 text-center">Erro ↓</th>
                                    <th className="py-2 px-2 text-center">Histerese</th>
                                    <th className="py-2 px-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {linearityPoints.map((pt, idx) => {
                                    const ref = parseFloat(String(pt.reference_value)) || 0
                                    const indInc = parseFloat(String(pt.indication_increasing)) || 0
                                    const indDec = parseFloat(String(pt.indication_decreasing)) || 0
                                    const errInc = pt.indication_increasing ? (indInc - ref) : null
                                    const errDec = pt.indication_decreasing ? (indDec - ref) : null
                                    const hyst = (pt.indication_increasing && pt.indication_decreasing) ? Math.abs(indInc - indDec) : null

                                    return (
                                        <tr key={idx} className="border-b">
                                            <td className="py-1 px-2 text-muted-foreground">{idx + 1}</td>
                                            <td className="py-1 px-2">
                                                <Input
                                                    type="number"
                                                    step="0.0001"
                                                    value={pt.reference_value}
                                                    onChange={e => updateLinearityPoint(idx, 'reference_value', e.target.value)}
                                                    className="h-8 w-28"
                                                />
                                            </td>
                                            <td className="py-1 px-2">
                                                <Input
                                                    type="number"
                                                    step="0.0001"
                                                    value={pt.indication_increasing}
                                                    onChange={e => updateLinearityPoint(idx, 'indication_increasing', e.target.value)}
                                                    className="h-8 w-28"
                                                    placeholder="—"
                                                />
                                            </td>
                                            <td className="py-1 px-2">
                                                <Input
                                                    type="number"
                                                    step="0.0001"
                                                    value={pt.indication_decreasing}
                                                    onChange={e => updateLinearityPoint(idx, 'indication_decreasing', e.target.value)}
                                                    className="h-8 w-28"
                                                    placeholder="—"
                                                />
                                            </td>
                                            <td className="py-1 px-2 text-center font-mono text-xs">
                                                {errInc !== null ? (
                                                    <span className={Math.abs(errInc) > eVal * 1.5 ? 'text-red-600' : 'text-green-600'}>
                                                        {errInc.toFixed(4)}
                                                    </span>
                                                ) : '—'}
                                            </td>
                                            <td className="py-1 px-2 text-center font-mono text-xs">
                                                {errDec !== null ? (
                                                    <span className={Math.abs(errDec) > eVal * 1.5 ? 'text-red-600' : 'text-green-600'}>
                                                        {errDec.toFixed(4)}
                                                    </span>
                                                ) : '—'}
                                            </td>
                                            <td className="py-1 px-2 text-center font-mono text-xs">
                                                {hyst !== null ? hyst.toFixed(4) : '—'}
                                            </td>
                                            <td className="py-1 px-2">
                                                <Button type="button" variant="ghost" size="sm" onClick={() => removeLinearityPoint(idx)}>
                                                    <Trash2 className="h-3 w-3 text-red-500" />
                                                </Button>
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        )
    }

    // â”€â”€â”€ Step 6: Eccentricity â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const renderStep5 = () => {
        const centerInd = parseFloat(state.eccentricityTests[0]?.indication) || 0
        const gridPositions = [
            { label: 'Frente', row: 0, col: 1, idx: 1 },
            { label: 'Esquerda', row: 1, col: 0, idx: 3 },
            { label: 'Centro', row: 1, col: 1, idx: 0 },
            { label: 'Direita', row: 1, col: 2, idx: 4 },
            { label: 'Trás', row: 2, col: 1, idx: 2 },
        ]

        return (
            <div className="space-y-4">
                <CalibrationGuide step="eccentricity" compact={isAdvancedMode} />
                {suggestedPoints && (
                    <p className="text-xs text-muted-foreground flex items-center gap-1">
                        <Info className="h-3.5 w-3.5" />
                        Carga sugerida: <strong>{suggestedPoints.eccentricity_load} {state.capacityUnit}</strong> (≈1/3 da capacidade)
                    </p>
                )}

                <div className="flex flex-col md:flex-row gap-6">
                    <div className="flex-shrink-0">
                        <p className="text-xs font-medium text-muted-foreground mb-2 uppercase tracking-wider">Diagrama do Prato</p>
                        <div className="grid grid-cols-3 gap-1 w-48 h-48 bg-muted/30 border-2 border-dashed border-muted-foreground/20 rounded-lg p-1">
                            {[0, 1, 2].map((row) =>
                                [0, 1, 2].map((col) => {
                                    const pos = gridPositions.find((p) => p.row === row && p.col === col)
                                    if (!pos) return <div key={`${row}-${col}`} className="rounded" />
                                    const test = state.eccentricityTests[pos.idx]
                                    const hasValue = !!test?.indication
                                    const ind = parseFloat(test?.indication) || 0
                                    const err = hasValue && pos.idx !== 0 ? Math.abs(ind - centerInd) : 0
                                    const eVal = parseFloat(state.eValue) || 1
                                    const isOk = hasValue && (pos.idx === 0 || err <= eVal)
                                    return (
                                        <button key={`${row}-${col}`} type="button" onClick={() => document.getElementById(`ecc-ind-${pos.idx}`)?.focus()}
                                            className={`rounded flex flex-col items-center justify-center text-[10px] font-medium transition-all cursor-pointer border ${hasValue ? (isOk ? 'bg-green-100 border-green-400 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 border-red-400 text-red-800 dark:bg-red-900/40 dark:text-red-300') : 'bg-background border-border hover:bg-muted/50'}`}>
                                            <span>{pos.label}</span>
                                            {hasValue && <span className="font-mono text-[9px]">{ind.toFixed(2)}</span>}
                                        </button>
                                    )
                                })
                            )}
                        </div>
                    </div>

                    <div className="flex-1 overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="border-b text-left"><th className="p-2">Posição</th><th className="p-2">Carga ({state.capacityUnit})</th><th className="p-2">Indicação ({state.capacityUnit})</th><th className="p-2">Erro vs Centro</th></tr></thead>
                            <tbody>
                                {(state.eccentricityTests || []).map((t, i) => {
                                    const ind = parseFloat(t.indication) || 0
                                    const errVsCenter = t.indication && i > 0 ? (ind - centerInd).toFixed(4) : i === 0 ? 'REF' : '—'
                                    return (
                                        <tr key={i} className="border-b hover:bg-muted/50">
                                            <td className="p-2 font-medium">{t.position}</td>
                                            <td className="p-2"><Input type="number" step="0.0001" value={t.loadApplied} onChange={(e) => updateEccentricity(i, 'loadApplied', e.target.value)} className="w-28" placeholder="0.0000" aria-label={`Carga ${t.position}`} /></td>
                                            <td className="p-2"><Input id={`ecc-ind-${i}`} type="number" step="0.0001" value={t.indication} onChange={(e) => updateEccentricity(i, 'indication', e.target.value)} className="w-28" placeholder="0.0000" aria-label={`Indicação ${t.position}`} /></td>
                                            <td className="p-2 font-mono text-right"><span className={typeof errVsCenter === 'string' && errVsCenter !== '—' && errVsCenter !== 'REF' ? (Math.abs(parseFloat(errVsCenter)) > 0 ? 'text-yellow-600' : 'text-green-600') : 'text-muted-foreground'}>{errVsCenter}</span></td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        )
    }

    // â”€â”€â”€ Step 6: Repeatability â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const renderStep6 = () => {
        const validMeasurements = (state.repeatabilityMeasurements || []).map((v) => parseFloat(v)).filter((v) => !isNaN(v))
        const stats = validMeasurements.length >= 2 ? calculateRepeatability(validMeasurements) : null

        return (
            <div className="space-y-6">
                <CalibrationGuide step="repeatability" compact={isAdvancedMode} />
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="repLoad">Carga Aplicada ({state.capacityUnit})</Label>
                        <Input id="repLoad" type="number" step="0.0001" value={state.repeatabilityLoad} onChange={(e) => update('repeatabilityLoad', e.target.value)} placeholder={suggestedPoints ? String(suggestedPoints.repeatability_load) : '0'} />
                        <p className="text-xs text-muted-foreground">Recomendado: ≈50% da capacidade máxima</p>
                    </div>
                </div>

                <div className="space-y-3">
                    <Label>Medições (mínimo 6, ideal 10)</Label>
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-2">
                        {(state.repeatabilityMeasurements || []).map((m, i) => (
                            <div key={i} className="relative">
                                <Input type="number" step="0.0001" value={m} onChange={(e) => updateRepMeasurement(i, e.target.value)} placeholder={`M${i + 1}`} aria-label={`Medição ${i + 1}`} />
                                <span className="absolute top-0.5 right-2 text-xs text-muted-foreground">{i + 1}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {stats && (
                    <Card className="bg-muted/30">
                        <CardHeader className="pb-2"><CardTitle className="text-base">Resultados Calculados</CardTitle></CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div><span className="text-muted-foreground">n medições:</span><p className="font-mono font-medium">{validMeasurements.length}</p></div>
                                <div><span className="text-muted-foreground">Média:</span><p className="font-mono font-medium">{stats.mean.toFixed(4)}</p></div>
                                <div><span className="text-muted-foreground">Desvio Padrão (s):<HelpTip text="Medida da dispersão dos resultados. Quanto menor, mais repetível é a balança." /></span><p className="font-mono font-medium">{stats.stdDev.toFixed(6)}</p></div>
                                <div><span className="text-muted-foreground">Incerteza Tipo A (u_A):<HelpTip text="Incerteza padrão obtida por análise estatística. Calculada como s/√n." /></span><p className="font-mono font-medium">{stats.uncertaintyTypeA.toFixed(6)}</p></div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {validationResult && (
                    <Card className={validationResult.complete ? 'border-green-200 bg-green-50 dark:bg-green-950/20' : 'border-yellow-200 bg-yellow-50 dark:bg-yellow-950/20'}>
                        <CardContent className="pt-4">
                            <div className="flex items-start gap-3">
                                {validationResult.complete ? <CheckCircle className="h-5 w-5 text-green-600 shrink-0" /> : <AlertTriangle className="h-5 w-5 text-yellow-600 shrink-0" />}
                                <div>
                                    <p className="font-medium">Validação ISO 17025: {validationResult.score}/{validationResult.total_fields} campos</p>
                                    {!validationResult.complete && (validationResult.missing_fields?.length ?? 0) > 0 && (
                                        <ul className="mt-2 text-sm text-muted-foreground list-disc list-inside">
                                            {(validationResult.missing_fields || []).map((f: string) => <li key={f}>{f}</li>)}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        )
    }

    // â”€â”€â”€ Step 7: Verification Checklist â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const renderStep7 = () => (
        <div className="space-y-4">
            {state.calibrationId ? (
                <>
                    {/* Avaliação ISO 17025 §7.8.6 — ILAC G8:09/2019 */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <ClipboardCheck className="h-4 w-4" />
                                Avaliação de Conformidade (ISO 17025 §7.8.6)
                            </CardTitle>
                            <CardDescription>
                                Regra acordada: <strong>{state.decisionRule}</strong>. Os parâmetros abaixo são usados pelo motor para
                                calcular o resultado segundo ILAC G8:09/2019 / ILAC P14:09/2020 / JCGM 106:2012.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {state.decisionRule === 'guard_band' && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="gbMode">Modo da banda de guarda</Label>
                                        <select
                                            id="gbMode"
                                            value={decisionGuardBandMode}
                                            onChange={(e) =>
                                                setDecisionGuardBandMode(e.target.value as 'k_times_u' | 'percent_limit' | 'fixed_abs')
                                            }
                                            className="w-full border rounded-md px-3 py-2 text-sm bg-background"
                                            aria-label="Modo da banda de guarda"
                                        >
                                            <option value="k_times_u">k × U (múltiplo da incerteza)</option>
                                            <option value="percent_limit">% do limite (EMA)</option>
                                            <option value="fixed_abs">Valor absoluto fixo</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label htmlFor="gbValue">Valor</Label>
                                        <Input
                                            id="gbValue"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            value={decisionGuardBandValue}
                                            onChange={(e) => setDecisionGuardBandValue(e.target.value)}
                                            placeholder="Ex: 1.0"
                                        />
                                    </div>
                                </div>
                            )}
                            {state.decisionRule === 'shared_risk' && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="riskAlpha">Risco do produtor Î±</Label>
                                        <Input
                                            id="riskAlpha"
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            max="0.5"
                                            value={decisionAlpha}
                                            onChange={(e) => setDecisionAlpha(e.target.value)}
                                            placeholder="0.05"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="riskBeta">Risco do consumidor Î²</Label>
                                        <Input
                                            id="riskBeta"
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            max="0.5"
                                            value={decisionBeta}
                                            onChange={(e) => setDecisionBeta(e.target.value)}
                                            placeholder="0.05"
                                        />
                                    </div>
                                </div>
                            )}
                            <Button
                                onClick={() => evaluateDecisionMutation.mutate()}
                                disabled={evaluateDecisionMutation.isPending || !state.decisionRule}
                            >
                                <FileCheck className="h-4 w-4 mr-1" />
                                {evaluateDecisionMutation.isPending ? 'Avaliando…' : 'Avaliar Conformidade'}
                            </Button>
                            <DecisionResultBanner decision={decisionResult} />
                        </CardContent>
                    </Card>

                    <CertificateEmissionChecklistForm
                        calibrationId={state.calibrationId}
                        onApproved={() => setIsChecklistApproved(true)}
                    />
                </>
            ) : (
                <div className="text-center text-muted-foreground py-8">
                    Nenhuma calibração selecionada. Volte à primeira etapa.
                </div>
            )}
        </div>
    )

    const stepRenderers = [renderStep1, renderStep2, renderStep3, renderStep4, renderStepLinearity, renderStep5, renderStep6, renderStep7]

    // â”€â”€â”€ ISO 17025 Progress (live) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const liveFilledCount = [
        !!state.calibrationLocation,
        !!state.temperature,
        !!state.humidity,
        !!state.calibrationDate,
        !!state.calibrationMethod,
        !!state.eValue,
        !!state.maxCapacity,
        state.readings.some((r) => r.referenceValue && r.indicationIncreasing),
        (state.repeatabilityMeasurements || []).filter((v) => v && !isNaN(parseFloat(v))).length >= 6,
        state.eccentricityTests.some((t) => t.indication),
        state.weightIds.length > 0 || !!state.standardUsed,
        !!state.gravityAcceleration,
        !!state.laboratoryAddress,
        !!state.receivedDate,
        !!state.issuedDate,
        !!state.decisionRule,
    ].filter(Boolean).length

    // â”€â”€â”€ Main Layout â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if (createDraftMutation.isPending) {
        return (
            <div className="flex items-center justify-center h-64 gap-3">
                <RefreshCw className="h-5 w-5 animate-spin text-primary" />
                <span className="text-muted-foreground">Iniciando calibração...</span>
            </div>
        )
    }

    return (
        <div className="space-y-4 md:space-y-6">
            <PageHeader
                title="Wizard de Calibração"
                subtitle={equipment ? `${equipment.brand || ''} ${equipment.model || ''} — S/N: ${equipment.serial_number || ''}` : 'Certificado de calibração guiado ISO 17025'}
            >
                <Button size="sm" variant="ghost" onClick={() => setIsAdvancedMode(!isAdvancedMode)} className="gap-1">
                    {isAdvancedMode ? <ToggleRight className="h-4 w-4" /> : <ToggleLeft className="h-4 w-4" />}
                    {isAdvancedMode ? 'Modo Wizard' : 'Modo Avançado'}
                </Button>
            </PageHeader>

            {/* Live ISO 17025 Progress */}
            <Iso17025Progress filledCount={liveFilledCount} totalCount={16} missingFields={[]} />

            {/* Step Indicator */}
            {!isAdvancedMode && (
                <nav className="flex items-center gap-1 md:gap-2 overflow-x-auto pb-2" aria-label="Progresso do wizard">
                    {(STEP_LABELS || []).map((s, i) => {
                        const Icon = s.icon
                        const isActive = i === step
                        const isDone = i < step
                        return (
                            <button key={s.key} onClick={() => setStep(i)} className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap ${isActive ? 'bg-primary text-primary-foreground shadow-sm' : ''} ${isDone ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : ''} ${!isActive && !isDone ? 'text-muted-foreground hover:bg-muted' : ''}`}>
                                {isDone ? <Check className="h-4 w-4" /> : <Icon className="h-4 w-4" />}
                                <span className="hidden md:inline">{s.label}</span>
                                <span className="md:hidden">{i + 1}</span>
                            </button>
                        )
                    })}
                </nav>
            )}

            {/* Content */}
            {isAdvancedMode ? (
                <div className="space-y-6">
                    {(STEP_LABELS || []).map((s, i) => (
                        <Card key={s.key}>
                            <CardHeader><CardTitle className="text-base flex items-center gap-2">{(() => { const Icon = s.icon; return <Icon className="h-4 w-4" /> })()}Etapa {i + 1}: {s.label}</CardTitle></CardHeader>
                            <CardContent>{stepRenderers[i]()}</CardContent>
                        </Card>
                    ))}
                    <div className="flex flex-col sm:flex-row justify-end gap-3">
                        <Button variant="outline" onClick={() => navigate(-1)}>Cancelar</Button>
                        <Button variant="secondary" onClick={handleSaveDraft}><Save className="h-4 w-4 mr-1" /> Salvar Rascunho</Button>
                        <Button onClick={handleFinish} disabled={generateCertMutation.isPending || !isChecklistApproved}><FileCheck className="h-4 w-4 mr-1" />{generateCertMutation.isPending ? 'Gerando...' : 'Gerar Certificado'}</Button>
                    </div>
                </div>
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {(() => { const Icon = STEP_LABELS[step].icon; return <Icon className="h-5 w-5" /> })()}
                            Etapa {step + 1}: {STEP_LABELS[step].label}
                        </CardTitle>
                        <CardDescription>
                            {step === 0 && 'Confirme os dados metrológicos do equipamento.'}
                            {step === 1 && 'Registre as condições ambientais e dados do laboratório.'}
                            {step === 2 && 'Selecione os pesos padrão utilizados.'}
                            {step === 3 && 'Insira as leituras de indicação (carga crescente e decrescente).'}
                            {step === 4 && 'Registre os pontos de linearidade (carga crescente e decrescente com histerese).'}
                            {step === 5 && 'Registre o ensaio de excentricidade (5 posições no prato).'}
                            {step === 6 && 'Registre as medições de repetibilidade (mínimo 6 repetições).'}
                            {step === 7 && 'Verifique todos os itens antes de emitir o certificado.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {stepRenderers[step]()}

                        <div className="flex justify-between items-center mt-8 pt-6 border-t gap-2">
                            <Button variant="outline" onClick={goPrev} disabled={!canPrev} className="shrink-0">
                                <ArrowLeft className="h-4 w-4 mr-1" /> Anterior
                            </Button>
                            <span className="text-sm text-muted-foreground hidden sm:block">{step + 1} de {STEP_LABELS.length}</span>
                            {canNext ? (
                                <Button onClick={goNext} className="shrink-0">Próximo <ArrowRight className="h-4 w-4 ml-1" /></Button>
                            ) : (
                                <div className="flex flex-wrap gap-2 justify-end">
                                    <Button variant="secondary" size="sm" onClick={handleSaveDraft}><Save className="h-4 w-4 mr-1" /> Rascunho</Button>
                                    <Button onClick={handleFinish} disabled={generateCertMutation.isPending || !isChecklistApproved}><FileCheck className="h-4 w-4 mr-1" />{generateCertMutation.isPending ? 'Gerando...' : 'Gerar Certificado'}</Button>
                                    <Button variant="outline" size="sm" onClick={handleFinish} disabled={generateCertMutation.isPending || !isChecklistApproved}><Printer className="h-4 w-4 mr-1" /> Imprimir</Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    )
}
