import { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
    ArrowLeft, MapPin, Phone, Clock, User, AlertTriangle,
    ClipboardList, Camera, Receipt, PenTool, PlayCircle,
    CheckCircle2, Loader2, ChevronRight, WifiOff, ShieldCheck,
    Navigation, Send, Mic, Printer, ImagePlus,
    FlaskConical, Award, Flag, FileCheck, X, Star, MessageCircle,
    Car, Coffee, MapPinned, QrCode, Package, ChevronDown, ChevronUp,
    Wrench, DollarSign, Layers
} from 'lucide-react'
import { useOfflineStore } from '@/hooks/useOfflineStore'
import { useDisplacementTracking } from '@/hooks/useDisplacementTracking'
import { useTechTimerStore } from '@/stores/tech-timer-store'
import { useAuthStore } from '@/stores/auth-store'
import { cn, formatCurrency, getApiErrorMessage } from '@/lib/utils'
import { offlinePost } from '@/lib/syncEngine'
import { CurrencyInputInline } from '@/components/common/CurrencyInput'
import api, { unwrapData } from '@/lib/api'
import { useToast } from '@/components/ui/use-toast'
import SLACountdown from '@/components/common/SLACountdown'
import TechChatDrawer from '@/components/tech/TechChatDrawer'
import { QrScannerModal } from '@/components/qr/QrScannerModal'
import { parseLabelQrPayload } from '@/lib/labelQr'
import { workOrderStatus } from '@/lib/status-config'
import { extractWorkOrderQrProduct, isPrivilegedFieldRole, isTechnicianLinkedToWorkOrder } from '@/lib/work-order-detail-utils'
import type { OfflineWorkOrder } from '@/lib/offlineDb'

type PrimaryAction = 'start_service' | 'resume_service' | 'finalize'
type PauseFlowMode = 'displacement' | 'return' | null
type ReturnDestination = 'base' | 'hotel' | 'next_client' | 'other'

const STATUS_MAP: Record<string, { label: string; color: string; next?: PrimaryAction; nextLabel?: string }> = {
    pending: { label: 'Pendente', color: 'bg-amber-500' },
    open: { label: 'Aberta', color: 'bg-amber-500' },
    awaiting_dispatch: { label: 'Aguard. Despacho', color: 'bg-amber-500' },
    in_displacement: { label: 'Em Deslocamento', color: 'bg-blue-500' },
    displacement_paused: { label: 'Desloc. Pausado', color: 'bg-amber-500' },
    at_client: { label: 'No Cliente', color: 'bg-emerald-500', next: 'start_service', nextLabel: 'Iniciar servico' },
    in_progress: { label: 'Em Andamento', color: 'bg-blue-500', next: 'finalize', nextLabel: 'Finalizar atendimento' },
    in_service: { label: 'Em Servico', color: 'bg-blue-500', next: 'finalize', nextLabel: 'Finalizar atendimento' },
    service_paused: { label: 'Servico Pausado', color: 'bg-amber-500', next: 'resume_service', nextLabel: 'Retomar servico' },
    processing: { label: 'Em Processamento', color: 'bg-blue-500' },
    authorized: { label: 'Autorizada', color: 'bg-teal-500' },
    waiting_parts: { label: 'Aguard. Peças', color: 'bg-amber-500' },
    waiting_approval: { label: 'Aguard. Aprovação', color: 'bg-amber-500' },
    awaiting_return: { label: 'Servico Concluido', color: 'bg-teal-500' },
    in_return: { label: 'Em Retorno', color: 'bg-blue-500' },
    return_paused: { label: 'Retorno Pausado', color: 'bg-amber-500' },
    completed: { label: 'Concluida', color: 'bg-emerald-500' },
    delivered: { label: 'Entregue', color: 'bg-emerald-500' },
    invoiced: { label: 'Faturada', color: 'bg-emerald-500' },
    rejected: { label: 'Rejeitada', color: 'bg-red-500' },
    cancelled: { label: 'Cancelada', color: 'bg-red-500' },
}

const ACTION_CARDS = [
    { key: 'checklist', label: 'Checklists', icon: ClipboardList, color: 'text-cyan-600 bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-400' },
    { key: 'photos', label: 'Fotos', icon: Camera, color: 'text-sky-600 bg-sky-100 dark:bg-sky-900/30 dark:text-sky-400' },
    { key: 'add-part-qr', label: 'Adicionar peça (QR)', icon: QrCode, color: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' },
    { key: 'seals', label: 'Selos', icon: ShieldCheck, color: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' },
    { key: 'calibration', label: 'Leituras Calibração', icon: FlaskConical, color: 'text-teal-600 bg-teal-100 dark:bg-teal-900/30 dark:text-teal-400' },
    { key: 'certificado', label: 'Certificado', icon: Award, color: 'text-orange-600 bg-orange-100 dark:bg-orange-900/30 dark:text-orange-400' },
    { key: 'expenses', label: 'Despesas', icon: Receipt, color: 'text-amber-600 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400' },
    { key: 'signature', label: 'Assinatura', icon: PenTool, color: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' },
    { key: 'chat', label: 'Chat Interno', icon: Send, color: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' },
    { key: 'voice-report', label: 'Relatório Voz', icon: Mic, color: 'text-rose-600 bg-rose-100 dark:bg-rose-900/30 dark:text-rose-400' },
    { key: 'annotate', label: 'Anotar Foto', icon: ImagePlus, color: 'text-cyan-600 bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-400' },
    { key: 'print', label: 'Impressão BT', icon: Printer, color: 'text-surface-600 bg-surface-100' },
    { key: 'ocorrencia', label: 'Ocorrência', icon: Flag, color: 'text-amber-600 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400' },
    { key: 'contrato', label: 'Contrato', icon: FileCheck, color: 'text-blue-600 bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400' },
    { key: 'nps', label: 'Avaliação NPS', icon: Star, color: 'text-yellow-600 bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-400' },
];

const READ_ONLY_ACTION_CARD_KEYS = new Set([
    'chat',
])

const STOP_TYPE_LABELS: Record<string, string> = {
    lunch: 'Almoco',
    hotel: 'Hotel',
    br_stop: 'Parada BR',
    other: 'Outro',
}

const RETURN_DESTINATIONS: Array<{ value: ReturnDestination; label: string }> = [
    { value: 'base', label: 'Base' },
    { value: 'hotel', label: 'Hotel' },
    { value: 'next_client', label: 'Proximo cliente' },
    { value: 'other', label: 'Outro' },
]

function getReasonFromStopType(type: string, notes: string): string {
    const normalized = notes.trim()
    if (normalized) return normalized

    return STOP_TYPE_LABELS[type] ?? 'Pausa operacional'
}

function calculateMinutes(startAt?: string | null, endAt?: string | null): number | null {
    if (!startAt || !endAt) return null

    return Math.max(0, Math.round((new Date(endAt).getTime() - new Date(startAt).getTime()) / 60000))
}

function calculateStopMinutes(stops: OfflineWorkOrder['displacement_stops']): number {
    return (stops ?? []).reduce((total, stop) => {
        if (!stop.started_at || !stop.ended_at) return total

        return total + Math.max(0, Math.round((new Date(stop.ended_at).getTime() - new Date(stop.started_at).getTime()) / 60000))
    }, 0)
}

export default function TechWorkOrderDetailPage() {
    const { id } = useParams<{ id: string }>()
    const timer = useTechTimerStore()
    const { user, hasPermission, hasRole } = useAuthStore()
    const navigate = useNavigate()
    const { toast } = useToast()
    const { getById, put } = useOfflineStore('work-orders')
    const [wo, setWo] = useState<OfflineWorkOrder | null>(null)
    const [loading, setLoading] = useState(true)
    const [transitioning, setTransitioning] = useState(false)
    const [updatingLocation, setUpdatingLocation] = useState(false)
    const [isChatOpen, setIsChatOpen] = useState(false)
    const [showCompletionWizard, setShowCompletionWizard] = useState(false)
    const [completionSteps, _setCompletionSteps] = useState({
        photos: false,
        checklist: false,
        signature: false,
        nps: false,
    })
    const [quickNote, setQuickNote] = useState('')
    const [sendingNote, setSendingNote] = useState(false)
    const [notes, setNotes] = useState<{ content?: string; message?: string; body?: string; created_at?: string }[]>([])
    const [displacementLoading, setDisplacementLoading] = useState(false)
    const [pauseFlowMode, setPauseFlowMode] = useState<PauseFlowMode>(null)
    const [stopNotes, setStopNotes] = useState('')
    const [showReturnStartModal, setShowReturnStartModal] = useState(false)
    const [showAddPartQrModal, setShowAddPartQrModal] = useState(false)
    const [addPartProduct, setAddPartProduct] = useState<{ id: number; name: string; sell_price?: string | number | null } | null>(null)
    const [addPartQty, setAddPartQty] = useState('1')
    const [addPartUnitPrice, setAddPartUnitPrice] = useState('')
    const [addPartSubmitting, setAddPartSubmitting] = useState(false)
    const [showAddPartQrScanner, setShowAddPartQrScanner] = useState(false)
    const [technicalReport, setTechnicalReport] = useState('')
    const [resolutionNotes, setResolutionNotes] = useState('')
    const [showStatusHistory, setShowStatusHistory] = useState(false)
    const [showEquipmentList, setShowEquipmentList] = useState(false)
    const [showItemsList, setShowItemsList] = useState(false)

    // Initialize report from existing data if possible when opening wizard
    useEffect(() => {
        if (showCompletionWizard && wo) {
            setTechnicalReport(wo.technical_report || '')
        }
    }, [showCompletionWizard, wo?.id])

    const displacementActive = wo?.status === 'in_displacement'
    const openStop = wo?.displacement_stops?.find((s) => !s.ended_at)
    useDisplacementTracking(wo?.id, !!displacementActive && !openStop)

    const normalizedStatus = wo?.status === 'pending'
        ? 'open'
        : wo?.status === 'in_progress'
            ? 'in_service'
            : wo?.status

    const fieldRoles = Array.isArray(user?.roles)
        ? user.roles
        : (Array.isArray(user?.all_roles) ? user.all_roles : [])
    const isAdminFieldRole = isPrivilegedFieldRole(fieldRoles) || hasRole('super_admin')
    const canViewWorkOrders = hasPermission('os.work_order.view')
    const canChangeWorkOrderStatus = hasPermission('os.work_order.change_status')
    const canUpdateWorkOrder = hasPermission('os.work_order.update')
    const technicianLinkedToWorkOrder = !wo || !user
        ? false
        : isTechnicianLinkedToWorkOrder(wo, user.id, isAdminFieldRole)
            || (wo.assigned_to == null && (wo.technician_ids ?? []).length === 0)
    const canExecuteFlow = canViewWorkOrders && canChangeWorkOrderStatus && technicianLinkedToWorkOrder
    const canEditWorkOrderData = canUpdateWorkOrder && technicianLinkedToWorkOrder
    const showExecutionBlocked = !!wo && !canExecuteFlow
    const executionBlockedMessage = !canViewWorkOrders
        ? 'Seu perfil não pode visualizar esta OS.'
        : !canChangeWorkOrderStatus
            ? 'Seu perfil não tem permissão para executar transições da OS em campo.'
            : 'Esta OS não está vinculada ao seu usuário técnico.'
    const availableActionCards = ACTION_CARDS.filter((card) => canEditWorkOrderData || READ_ONLY_ACTION_CARD_KEYS.has(card.key))

    const ensureExecutionAllowed = useCallback(() => {
        if (canExecuteFlow) {
            return true
        }

        toast({
            title: 'Ação não permitida',
            description: executionBlockedMessage,
            variant: 'destructive',
        })
        return false
    }, [canExecuteFlow, executionBlockedMessage, toast])

    const ensureWorkOrderUpdateAllowed = useCallback(() => {
        if (canEditWorkOrderData) {
            return true
        }

        toast({
            title: 'Ação não permitida',
            description: technicianLinkedToWorkOrder
                ? 'Seu perfil não tem permissão para alterar dados desta OS.'
                : 'Esta OS não está vinculada ao seu usuário técnico.',
            variant: 'destructive',
        })
        return false
    }, [canEditWorkOrderData, technicianLinkedToWorkOrder, toast])


    useEffect(() => {
        if (!id) return
        let cancelled = false
        getById(Number(id)).then((data) => {
            if (!cancelled) {
                setWo(data ?? null)
                setLoading(false)
            }
        })
        return () => { cancelled = true }
    }, [id])

    useEffect(() => {
        if (!id) return
        let cancelled = false
        api.get(`/work-orders/${id}/chats`).then(res => {
            if (!cancelled) setNotes(unwrapData<typeof notes>(res) ?? [])
        }).catch((err: unknown) => {
            if (cancelled) return
            setNotes([])
            toast({
                title: 'Nao foi possivel carregar as notas',
                description: getApiErrorMessage(err, 'Falha ao carregar o historico interno desta OS.'),
                variant: 'destructive',
            })
        })
        return () => { cancelled = true }
    }, [id, toast])

    const handleSendNote = async () => {
        if (!quickNote.trim() || !wo) return
        if (!ensureWorkOrderUpdateAllowed()) {
            return
        }
        setSendingNote(true)
        try {
            await api.post(`/work-orders/${wo.id}/chats`, { message: quickNote.trim() })
            setNotes(prev => [...prev, { content: quickNote.trim(), created_at: new Date().toISOString() }])
            setQuickNote('')
            toast({ title: 'Nota adicionada' })
        } catch (err: unknown) {
            toast({ title: 'Erro', description: getApiErrorMessage(err, 'Falha ao enviar nota'), variant: 'destructive' })
        } finally {
            setSendingNote(false)
        }
    }

    const persistWorkOrder = useCallback(async (changes: Partial<OfflineWorkOrder>) => {
        if (!wo) return null

        const updated = {
            ...wo,
            ...changes,
            updated_at: new Date().toISOString(),
        }

        await put(updated as OfflineWorkOrder)
        setWo(updated as OfflineWorkOrder)

        return updated as OfflineWorkOrder
    }, [put, wo])

    const getCurrentPosition = useCallback((): Promise<{ latitude: number; longitude: number } | null> => {
        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve(null)
                return
            }

            navigator.geolocation.getCurrentPosition(
                (position) => resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                }),
                () => resolve(null),
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            )
        })
    }, [])

    const closeOpenStopLocally = useCallback((workOrder: OfflineWorkOrder, endedAt: string) => {
        const stops = [...(workOrder.displacement_stops ?? [])]
        const openIndex = stops.findIndex((stop) => !stop.ended_at)

        if (openIndex >= 0) {
            stops[openIndex] = { ...stops[openIndex], ended_at: endedAt }
        }

        return stops
    }, [])

    const handleUpdateLocation = async () => {
        if (!wo?.customer_id) return
        setUpdatingLocation(true)

        if (!navigator.geolocation) {
            toast({
                title: "Erro",
                description: "Geolocalização não suportada pelo navegador.",
                variant: "destructive"
            })
            setUpdatingLocation(false)
            return
        }

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                try {
                    await api.post(`/technicians/customers/${wo.customer_id}/geolocation`, {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    })

                    toast({
                        title: "Sucesso",
                        description: "Localização do cliente atualizada!",
                        className: "bg-green-600 text-white border-none"
                    })
                } catch (err: unknown) {
                    toast({
                        title: "Erro",
                        description: getApiErrorMessage(err, "Falha ao enviar localização. Verifique sua conexão."),
                        variant: "destructive"
                    })
                } finally {
                    setUpdatingLocation(false)
                }
            },
            (error) => {
                let msg = "Não foi possível obter sua localização."
                if (error.code === 1) msg = "Permissão de localização negada."
                if (error.code === 2) msg = "Sinal de GPS indisponível."
                if (error.code === 3) msg = "Tempo limite excedido ao buscar GPS."

                toast({
                    title: "Erro de GPS",
                    description: msg,
                    variant: "destructive"
                })
                setUpdatingLocation(false)
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        )
    }

    const handleAddPartQrScanned = async (raw: string) => {
        const productId = parseLabelQrPayload(raw)
        if (!productId || !wo) return
        try {
            const res = await api.get(`/products/${productId}`)
            const product = extractWorkOrderQrProduct(res)
            if (!product?.id) {
                toast({ title: 'Produto não encontrado', variant: 'destructive' })
                return
            }
            setAddPartProduct(product)
            setAddPartUnitPrice(String(product.sell_price ?? 0))
            setAddPartQty('1')
        } catch (err: unknown) {
            toast({ title: 'Erro ao buscar produto', description: getApiErrorMessage(err, 'Falha ao consultar a peca pelo QR.'), variant: 'destructive' })
        }
    }

    const handleAddPartSubmit = async () => {
        if (!wo || !addPartProduct) return
        if (!ensureWorkOrderUpdateAllowed()) return
        const qty = parseFloat(addPartQty)
        const price = parseFloat(addPartUnitPrice) || 0
        if (!Number.isFinite(qty) || qty <= 0) {
            toast({ title: 'Informe uma quantidade válida', variant: 'destructive' })
            return
        }
        setAddPartSubmitting(true)
        try {
            await api.post(`/work-orders/${wo.id}/items`, {
                type: 'product',
                reference_id: addPartProduct.id,
                description: addPartProduct.name,
                quantity: qty,
                unit_price: price,
            })
            toast({ title: 'Peça adicionada à OS' })
            setShowAddPartQrModal(false)
            setAddPartProduct(null)
            setAddPartQty('1')
            setAddPartUnitPrice('')
            getById(wo.id).then((data) => data && setWo(data))
        } catch (err: unknown) {
            toast({ title: getApiErrorMessage(err, 'Erro ao adicionar item'), variant: 'destructive' })
        } finally {
            setAddPartSubmitting(false)
        }
    }

    const handleCompleteOS = useCallback(async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return
        if (!['in_service', 'service_paused'].includes(wo.status)) return

        setTransitioning(true)
        try {
            const now = new Date().toISOString()

            await offlinePost(`/work-orders/${wo.id}/execution/finalize`, {
                recorded_at: new Date().toISOString(),
                technical_report: technicalReport.trim() || undefined,
                resolution_notes: resolutionNotes.trim() || undefined,
            })
            await persistWorkOrder({
                status: 'awaiting_return',
                service_duration_minutes: calculateMinutes(wo.service_started_at, now) ?? wo.service_duration_minutes,
                technical_report: technicalReport.trim() || wo.technical_report,
            })

            if (timer.workOrderId === wo.id) {
                timer.stop()
            }

            setShowCompletionWizard(false)
            toast({ title: 'Servico finalizado', description: 'OS aguardando retorno.' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao finalizar atendimento'),
                variant: 'destructive',
            })
        } finally {
            setTransitioning(false)
        }
    }, [ensureExecutionAllowed, persistWorkOrder, timer, wo])

    const handleStartDisplacement = async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        setDisplacementLoading(true)
        try {
            const position = await getCurrentPosition()

            await offlinePost(`/work-orders/${wo.id}/execution/start-displacement`, { recorded_at: new Date().toISOString(),
                ...(position ?? {}),
            })

            await persistWorkOrder({
                status: 'in_displacement',
                displacement_started_at: new Date().toISOString(),
                displacement_status: 'in_progress',
                displacement_stops: wo.displacement_stops ?? [],
            })

            toast({ title: 'Deslocamento iniciado' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao iniciar deslocamento'),
                variant: 'destructive',
            })
        } finally {
            setDisplacementLoading(false)
        }
    }

    const handleArriveDisplacement = async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        setDisplacementLoading(true)
        try {
            const position = await getCurrentPosition()
            const arrivedAt = new Date().toISOString()
            const closedStops = closeOpenStopLocally(wo, arrivedAt)
            const grossMinutes = calculateMinutes(wo.displacement_started_at, arrivedAt) ?? wo.displacement_duration_minutes ?? null
            const stopMinutes = calculateStopMinutes(closedStops)

            await offlinePost(`/work-orders/${wo.id}/execution/arrive`, { recorded_at: new Date().toISOString(),
                ...(position ?? {}),
            })

            await persistWorkOrder({
                status: 'at_client',
                displacement_arrived_at: arrivedAt,
                displacement_status: 'arrived',
                displacement_stops: closedStops,
                displacement_duration_minutes: grossMinutes == null ? null : Math.max(0, grossMinutes - stopMinutes),
            })

            toast({ title: 'Chegada registrada' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao registrar chegada'),
                variant: 'destructive',
            })
        } finally {
            setDisplacementLoading(false)
        }
    }

    const handlePauseFlow = async (type: string) => {
        if (!wo || !pauseFlowMode) return
        if (!ensureExecutionAllowed()) return

        setDisplacementLoading(true)
        try {
            const position = await getCurrentPosition()
            const reason = getReasonFromStopType(type, stopNotes)
            const optimisticStop = {
                id: `temp-${Date.now()}`,
                type,
                started_at: new Date().toISOString(),
                ended_at: null,
            }

            if (pauseFlowMode === 'displacement') {
                await offlinePost(`/work-orders/${wo.id}/execution/pause-displacement`, { recorded_at: new Date().toISOString(),
                    reason,
                    stop_type: type,
                    ...(position ?? {}),
                })

                await persistWorkOrder({
                    status: 'displacement_paused',
                    displacement_status: 'paused',
                    displacement_stops: [...(wo.displacement_stops ?? []), optimisticStop],
                })
            } else {
                await offlinePost(`/work-orders/${wo.id}/execution/pause-return`, { recorded_at: new Date().toISOString(),
                    reason,
                    stop_type: type,
                    ...(position ?? {}),
                })

                await persistWorkOrder({
                    status: 'return_paused',
                    displacement_stops: [...(wo.displacement_stops ?? []), optimisticStop],
                })
            }

            setPauseFlowMode(null)
            setStopNotes('')
            toast({ title: 'Pausa registrada' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao registrar a pausa'),
                variant: 'destructive',
            })
        } finally {
            setDisplacementLoading(false)
        }
    }

    const handleResumeFlow = async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        setDisplacementLoading(true)
        try {
            const endedAt = new Date().toISOString()

            if (wo.status === 'displacement_paused') {
                await offlinePost(`/work-orders/${wo.id}/execution/resume-displacement`, { recorded_at: new Date().toISOString() })
                await persistWorkOrder({
                    status: 'in_displacement',
                    displacement_status: 'in_progress',
                    displacement_stops: closeOpenStopLocally(wo, endedAt),
                })
                toast({ title: 'Deslocamento retomado' })
                return
            }

            if (wo.status === 'return_paused') {
                await offlinePost(`/work-orders/${wo.id}/execution/resume-return`, { recorded_at: new Date().toISOString() })
                await persistWorkOrder({
                    status: 'in_return',
                    displacement_stops: closeOpenStopLocally(wo, endedAt),
                })
                toast({ title: 'Retorno retomado' })
            }
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao retomar o fluxo'),
                variant: 'destructive',
            })
        } finally {
            setDisplacementLoading(false)
        }
    }

    const handleStartService = useCallback(async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        setTransitioning(true)
        try {
            const now = new Date().toISOString()

            await offlinePost(`/work-orders/${wo.id}/execution/start-service`, { recorded_at: new Date().toISOString() })
            await persistWorkOrder({
                status: 'in_service',
                service_started_at: now,
                wait_time_minutes: calculateMinutes(wo.displacement_arrived_at, now) ?? wo.wait_time_minutes,
            })

            toast({ title: 'Servico iniciado' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao iniciar o servico'),
                variant: 'destructive',
            })
        } finally {
            setTransitioning(false)
        }
    }, [ensureExecutionAllowed, persistWorkOrder, wo])

    const handlePauseService = useCallback(async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        const reason = window.prompt('Informe o motivo da pausa do servico:')?.trim()
        if (reason == null) return
        if (!reason) {
            toast({ title: 'Informe o motivo da pausa', variant: 'destructive' })
            return
        }

        setTransitioning(true)
        try {
            await offlinePost(`/work-orders/${wo.id}/execution/pause-service`, { recorded_at: new Date().toISOString(), reason })
            await persistWorkOrder({ status: 'service_paused' })

            if (timer.workOrderId === wo.id && timer.isRunning) {
                timer.pause()
            }

            toast({ title: 'Servico pausado' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao pausar o servico'),
                variant: 'destructive',
            })
        } finally {
            setTransitioning(false)
        }
    }, [ensureExecutionAllowed, persistWorkOrder, timer, wo])

    const handleResumeService = useCallback(async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        setTransitioning(true)
        try {
            await offlinePost(`/work-orders/${wo.id}/execution/resume-service`, { recorded_at: new Date().toISOString() })
            await persistWorkOrder({ status: 'in_service' })
            toast({ title: 'Servico retomado' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao retomar o servico'),
                variant: 'destructive',
            })
        } finally {
            setTransitioning(false)
        }
    }, [ensureExecutionAllowed, persistWorkOrder, wo])

    const handleStartReturn = async (destination: ReturnDestination) => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        setDisplacementLoading(true)
        try {
            const position = await getCurrentPosition()

            await offlinePost(`/work-orders/${wo.id}/execution/start-return`, { recorded_at: new Date().toISOString(),
                destination,
                ...(position ?? {}),
            })

            await persistWorkOrder({
                status: 'in_return',
                return_started_at: new Date().toISOString(),
                return_destination: destination,
            })

            setShowReturnStartModal(false)
            toast({ title: 'Retorno iniciado' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao iniciar o retorno'),
                variant: 'destructive',
            })
        } finally {
            setDisplacementLoading(false)
        }
    }

    const handleCloseWithoutReturn = async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        const reason = window.prompt('Motivo opcional para encerrar sem retorno:') ?? ''

        setDisplacementLoading(true)
        try {
            const now = new Date().toISOString()

            await offlinePost(`/work-orders/${wo.id}/execution/close-without-return`, { recorded_at: new Date().toISOString(),
                ...(reason.trim() ? { reason: reason.trim() } : {}),
            })

            await persistWorkOrder({
                status: 'completed',
                completed_at: now,
                total_duration_minutes: calculateMinutes(wo.displacement_started_at, now) ?? wo.total_duration_minutes,
            })

            toast({ title: 'OS encerrada sem retorno' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao encerrar sem retorno'),
                variant: 'destructive',
            })
        } finally {
            setDisplacementLoading(false)
        }
    }

    const handleArriveReturn = async () => {
        if (!wo) return
        if (!ensureExecutionAllowed()) return

        setDisplacementLoading(true)
        try {
            const position = await getCurrentPosition()
            const now = new Date().toISOString()

            await offlinePost(`/work-orders/${wo.id}/execution/arrive-return`, { recorded_at: new Date().toISOString(),
                ...(position ?? {}),
            })

            await persistWorkOrder({
                status: 'completed',
                completed_at: now,
                return_arrived_at: now,
                displacement_stops: closeOpenStopLocally(wo, now),
                total_duration_minutes: calculateMinutes(wo.displacement_started_at, now) ?? wo.total_duration_minutes,
            })

            toast({ title: 'Retorno concluido' })
        } catch (err: unknown) {
            toast({
                title: 'Erro',
                description: getApiErrorMessage(err, 'Falha ao concluir o retorno'),
                variant: 'destructive',
            })
        } finally {
            setDisplacementLoading(false)
        }
    }

    const handleStatusTransition = useCallback(async () => {
        if (!wo) return

        const nextAction = STATUS_MAP[wo.status]?.next
        if (!nextAction) return

        if (nextAction === 'finalize') {
            setShowCompletionWizard(true)
            return
        }

        if (nextAction === 'start_service') {
            await handleStartService()
            return
        }

        if (nextAction === 'resume_service') {
            await handleResumeService()
        }
    }, [handleResumeService, handleStartService, wo])

    if (loading) {
        return (
            <div className="flex items-center justify-center h-full">
                <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
            </div>
        )
    }

    if (!wo) {
        return (
            <div className="flex flex-col items-center justify-center h-full gap-3 p-6">
                <AlertTriangle className="w-12 h-12 text-amber-400" />
                <p className="text-sm text-surface-500">OS não encontrada localmente</p>
                <button onClick={() => navigate('/tech')} className="text-sm text-brand-600 font-medium">
                    Voltar
                </button>
            </div>
        )
    }

    const currentStatus = STATUS_MAP[normalizedStatus ?? 'open'] || STATUS_MAP.open
    const customerPhone = wo.customerPhone ?? wo.customer_phone
    const showDisplacementStart = ['open', 'awaiting_dispatch'].includes(normalizedStatus ?? 'open') && !wo.displacement_started_at
    const showTimer = ['in_service', 'service_paused'].includes(normalizedStatus ?? '')
    const showReturnFlow = ['awaiting_return', 'in_return', 'return_paused', 'completed'].includes(normalizedStatus ?? '') || !!wo.return_started_at

    return (
        <div className="relative flex flex-col h-full">
            {/* Header */}
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                <button onClick={() => navigate('/tech')} className="flex items-center gap-1 text-sm text-brand-600 mb-3">
                    <ArrowLeft className="w-4 h-4" /> Voltar
                </button>

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-foreground">
                            {wo.os_number || wo.number}
                        </h1>
                        <span className={cn(
                            'inline-flex items-center gap-1 mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium text-white',
                            currentStatus.color,
                        )}>
                            {currentStatus.label}
                        </span>
                        <div className="mt-2">
                            <SLACountdown dueAt={wo.sla_due_at ?? null} status={wo.status} />
                        </div>
                    </div>
                    {!navigator.onLine && (
                        <WifiOff className="w-4 h-4 text-amber-500" />
                    )}
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                {/* Customer info */}
                <div className="bg-card rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                    <div className="p-4 space-y-4">
                        {showExecutionBlocked && (
                            <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-800">
                                {executionBlockedMessage}
                            </div>
                        )}

                        <div className="flex items-center justify-between">
                            <h3 className="text-[10px] font-bold text-surface-400 uppercase tracking-[0.1em]">Informações do Cliente</h3>
                            <div className="flex items-center gap-1">
                                {customerPhone && (
                                    <>
                                        <a
                                            href={`tel:${customerPhone}`}
                                            title="Ligar para o cliente"
                                            className="p-2 rounded-full bg-emerald-50 text-emerald-600 dark:text-emerald-400 active:scale-95 transition-all"
                                        >
                                            <Phone className="w-4 h-4" />
                                        </a>
                                        <a
                                            href={`https://wa.me/${customerPhone.replace(/\D/g, '')}?text=${encodeURIComponent(`Olá! Informo que a OS ${wo.os_number || wo.number} está com status: ${currentStatus.label}. Equipe Kalibrium.`)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="p-2 rounded-full bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 active:scale-95 transition-all"
                                            title="Enviar WhatsApp"
                                        >
                                            <MessageCircle className="w-4 h-4" />
                                        </a>
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="flex items-start gap-4">
                            <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 flex items-center justify-center flex-shrink-0 shadow-lg shadow-brand-500/20">
                                <User className="w-6 h-6 text-white" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="font-bold text-base text-foreground truncate">
                                    {wo.customer_name || 'Não informado'}
                                </p>
                                {wo.customer_address && (
                                    <p className="text-xs text-surface-500 mt-0.5 line-clamp-2 leading-relaxed">
                                        {wo.customer_address}
                                        {wo.city && `, ${wo.city}`}
                                        {wo.state && ` - ${wo.state}`}
                                    </p>
                                )}
                                {wo.contact_phone && !customerPhone && (
                                    <p className="text-xs text-surface-500 mt-0.5">
                                        <Phone className="w-3 h-3 inline mr-1" />{wo.contact_phone}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Botões de Ação Ergonômicos */}
                        <div className="grid grid-cols-2 gap-2 pt-2">
                            {wo.google_maps_link && (
                                <a
                                    href={wo.google_maps_link}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center justify-center gap-2 py-3 rounded-xl bg-surface-50 border border-border text-[11px] font-bold text-surface-700 active:scale-95 transition-all shadow-sm"
                                >
                                    <MapPin className="w-3.5 h-3.5 text-red-500" /> Google Maps
                                </a>
                            )}
                            {wo.waze_link && (
                                <a
                                    href={wo.waze_link}
                                    className="flex items-center justify-center gap-2 py-3 rounded-xl bg-surface-50 border border-border text-[11px] font-bold text-surface-700 active:scale-95 transition-all shadow-sm"
                                >
                                    <Navigation className="w-3.5 h-3.5 text-[#33ccff]" /> Waze
                                </a>
                            )}
                        </div>

                        {/* Deslocamento */}
                        <div className="space-y-2 pt-2 border-t border-surface-100">
                            {showDisplacementStart ? (
                                <button
                                    onClick={handleStartDisplacement}
                                    disabled={displacementLoading || !canExecuteFlow}
                                    className="flex items-center justify-center gap-2 py-3 px-4 w-full rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold shadow-md active:scale-[0.98] transition-all disabled:opacity-50"
                                >
                                    {displacementLoading ? (
                                        <Loader2 className="w-3.5 h-3.5 animate-spin" />
                                    ) : (
                                        <Car className="w-3.5 h-3.5" />
                                    )}
                                    Iniciar deslocamento
                                </button>
                            ) : wo.status === 'in_displacement' ? (
                                <>
                                    <div className="flex items-center gap-2 py-2 px-3 rounded-lg bg-blue-50 border border-blue-200 dark:border-blue-800">
                                        <MapPinned className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                        <span className="text-xs font-medium text-blue-800 dark:text-blue-200">Em deslocamento</span>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <button
                                            onClick={() => setPauseFlowMode('displacement')}
                                            disabled={displacementLoading || !canExecuteFlow}
                                            className="flex items-center justify-center gap-2 py-3 rounded-xl bg-surface-100 text-surface-700 text-[11px] font-bold active:scale-[0.98] disabled:opacity-50"
                                        >
                                            <Coffee className="w-3.5 h-3.5" /> Registrar pausa
                                        </button>
                                        <button
                                            onClick={handleArriveDisplacement}
                                            disabled={displacementLoading || !canExecuteFlow}
                                            className="flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold active:scale-[0.98] disabled:opacity-50"
                                        >
                                            {displacementLoading ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <MapPin className="w-3.5 h-3.5" />}
                                            Cheguei ao cliente
                                        </button>
                                    </div>
                                </>
                            ) : wo.status === 'displacement_paused' ? (
                                <>
                                    <div className="py-2 px-3 rounded-lg bg-amber-50 border border-amber-200 dark:border-amber-800 space-y-1">
                                        <p className="text-xs font-medium text-amber-800 dark:text-amber-200">Deslocamento pausado</p>
                                        <p className="text-[11px] text-amber-700 dark:text-amber-300">Motivo registrado no fluxo da OS.</p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <button
                                            onClick={handleResumeFlow}
                                            disabled={displacementLoading || !canExecuteFlow}
                                            className="flex items-center justify-center gap-2 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold active:scale-[0.98] disabled:opacity-50"
                                        >
                                            {displacementLoading ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <PlayCircle className="w-3.5 h-3.5" />}
                                            Retomar
                                        </button>
                                        <button
                                            onClick={handleArriveDisplacement}
                                            disabled={displacementLoading || !canExecuteFlow}
                                            className="flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold active:scale-[0.98] disabled:opacity-50"
                                        >
                                            <MapPin className="w-3.5 h-3.5" />
                                            Cheguei ao cliente
                                        </button>
                                    </div>
                                </>
                            ) : wo.displacement_arrived_at ? (
                                <div className="py-2 px-3 rounded-lg bg-emerald-50 border border-emerald-200 dark:border-emerald-800 space-y-1">
                                    <p className="text-xs font-medium text-emerald-800 dark:text-emerald-200">
                                        Chegou às {wo.displacement_arrived_at ? new Date(wo.displacement_arrived_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '-'}
                                    </p>
                                    {wo.displacement_duration_minutes != null && (
                                        <p className="text-[11px] text-emerald-600 dark:text-emerald-400">
                                            Tempo em deslocamento: {wo.displacement_duration_minutes} min
                                        </p>
                                    )}
                                </div>
                            ) : null}
                        </div>

                        <button
                            onClick={handleUpdateLocation}
                            disabled={updatingLocation || !canEditWorkOrderData}
                            className="flex items-center gap-2 py-3 px-4 w-full rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-[11px] font-bold shadow-md shadow-brand-500/10 active:scale-[0.98] transition-all disabled:opacity-50"
                        >
                            {updatingLocation ? (
                                <Loader2 className="w-3.5 h-3.5 animate-spin" />
                            ) : (
                                <MapPin className="w-3.5 h-3.5" />
                            )}
                            {updatingLocation ? 'Sincronizando GPS...' : 'Confirmar Chegada ao Local (GPS)'}
                        </button>
                    </div>
                </div>

                {/* Schedule */}
                {wo.scheduled_date && (
                    <div className="flex items-center gap-2 bg-card rounded-xl p-4">
                        <Clock className="w-5 h-5 text-surface-400" />
                        <div>
                            <p className="text-xs text-surface-400">Agendamento</p>
                            <p className="text-sm font-medium text-foreground">
                                {new Date(wo.scheduled_date).toLocaleString('pt-BR', {
                                    day: '2-digit', month: '2-digit', year: 'numeric',
                                    hour: '2-digit', minute: '2-digit',
                                })}
                            </p>
                        </div>
                    </div>
                )}

                {/* Timer */}
                {showTimer && (
                    <div className="bg-card rounded-xl p-4 space-y-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Clock className="w-5 h-5 text-brand-500" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">Cronômetro</p>
                                    <p className="text-xs text-surface-500">Registre o tempo nesta OS</p>
                                </div>
                            </div>
                            {timer.workOrderId === wo.id ? (
                                <button
                                    onClick={() => timer.isRunning ? timer.pause() : timer.resume()}
                                    className={cn(
                                        'px-3 py-1.5 rounded-lg text-xs font-medium',
                                        timer.isRunning
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30'
                                            : 'bg-brand-100 text-brand-700'
                                    )}
                                >
                                    {timer.isRunning ? 'Pausar' : 'Continuar'}
                                </button>
                            ) : (
                                <button
                                    onClick={() => timer.start(wo.id, wo.os_number || wo.number || String(wo.id))}
                                    className="px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-medium active:bg-brand-700"
                                >
                                    Iniciar Timer
                                </button>
                            )}
                        </div>
                        <div className="flex gap-2">
                            {normalizedStatus === 'in_service' && (
                                <button
                                    onClick={handlePauseService}
                                    disabled={transitioning || !canExecuteFlow}
                                    className="flex-1 py-2.5 rounded-xl bg-amber-100 text-amber-700 text-xs font-semibold active:scale-[0.98]"
                                >
                                    Pausar servico
                                </button>
                            )}
                            {wo.status === 'service_paused' && (
                                <button
                                    onClick={handleResumeService}
                                    disabled={transitioning || !canExecuteFlow}
                                    className="flex-1 py-2.5 rounded-xl bg-blue-600 text-white text-xs font-semibold active:scale-[0.98]"
                                >
                                    Retomar servico
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {showReturnFlow && (
                    <div className="bg-card rounded-xl p-4 space-y-3">
                        <div>
                            <p className="text-sm font-medium text-foreground">Fluxo de retorno</p>
                            <p className="text-xs text-surface-500">
                                {wo.return_destination
                                    ? `Destino: ${RETURN_DESTINATIONS.find((option) => option.value === wo.return_destination)?.label ?? wo.return_destination}`
                                    : 'Defina se a equipe volta para a base ou segue para o proximo atendimento.'}
                            </p>
                        </div>

                        {wo.status === 'awaiting_return' && (
                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    onClick={() => setShowReturnStartModal(true)}
                                    disabled={displacementLoading || !canExecuteFlow}
                                    className="py-3 rounded-xl bg-blue-600 text-white text-[11px] font-bold active:scale-[0.98]"
                                >
                                    Iniciar retorno
                                </button>
                                <button
                                    onClick={handleCloseWithoutReturn}
                                    disabled={displacementLoading || !canExecuteFlow}
                                    className="py-3 rounded-xl bg-surface-100 text-surface-700 text-[11px] font-bold active:scale-[0.98]"
                                >
                                    Encerrar sem retorno
                                </button>
                            </div>
                        )}

                        {wo.status === 'in_return' && (
                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    onClick={() => setPauseFlowMode('return')}
                                    disabled={displacementLoading || !canExecuteFlow}
                                    className="py-3 rounded-xl bg-surface-100 text-surface-700 text-[11px] font-bold active:scale-[0.98]"
                                >
                                    Pausar retorno
                                </button>
                                <button
                                    onClick={handleArriveReturn}
                                    disabled={displacementLoading || !canExecuteFlow}
                                    className="py-3 rounded-xl bg-emerald-600 text-white text-[11px] font-bold active:scale-[0.98]"
                                >
                                    Concluir retorno
                                </button>
                            </div>
                        )}

                        {wo.status === 'return_paused' && (
                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    onClick={handleResumeFlow}
                                    disabled={displacementLoading || !canExecuteFlow}
                                    className="py-3 rounded-xl bg-blue-600 text-white text-[11px] font-bold active:scale-[0.98]"
                                >
                                    Retomar retorno
                                </button>
                                <button
                                    onClick={handleArriveReturn}
                                    disabled={displacementLoading || !canExecuteFlow}
                                    className="py-3 rounded-xl bg-emerald-600 text-white text-[11px] font-bold active:scale-[0.98]"
                                >
                                    Concluir retorno
                                </button>
                            </div>
                        )}

                        {wo.status === 'completed' && (
                            <div className="py-2 px-3 rounded-lg bg-emerald-50 border border-emerald-200 dark:border-emerald-800 space-y-1">
                                {wo.return_started_at && (
                                    <p className="text-[11px] text-emerald-700 dark:text-emerald-300">
                                        Retorno iniciado em {new Date(wo.return_started_at).toLocaleString('pt-BR')}
                                    </p>
                                )}
                                {wo.return_arrived_at && (
                                    <p className="text-[11px] text-emerald-700 dark:text-emerald-300">
                                        Retorno concluído em {new Date(wo.return_arrived_at).toLocaleString('pt-BR')}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* Priority */}
                {wo.priority && (
                    <div className="bg-card rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide mb-2">Prioridade</h3>
                        <span className={cn(
                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                            {
                                'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': wo.priority === 'urgent',
                                'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400': wo.priority === 'high',
                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': wo.priority === 'medium',
                                'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': wo.priority === 'low',
                                'bg-surface-100 text-surface-800 dark:bg-surface-800 dark:text-surface-300': !['urgent', 'high', 'medium', 'low'].includes(wo.priority)
                            }
                        )}>
                            {wo.priority === 'urgent' ? 'Urgente' : wo.priority === 'high' ? 'Alta' : wo.priority === 'medium' ? 'Média' : wo.priority === 'low' ? 'Baixa' : wo.priority}
                        </span>
                    </div>
                )}

                {/* Service Type & Warranty */}
                {(wo.service_type_name || wo.is_warranty) && (
                    <div className="bg-card rounded-xl p-4 flex items-center gap-3 flex-wrap">
                        {wo.service_type_name && (
                            <div className="flex items-center gap-2">
                                <Wrench className="w-4 h-4 text-surface-400" />
                                <span className="text-sm text-foreground font-medium">{wo.service_type_name}</span>
                            </div>
                        )}
                        {wo.is_warranty && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <ShieldCheck className="w-3 h-3" /> Garantia
                            </span>
                        )}
                    </div>
                )}

                {/* Items & Total Value */}
                {((wo.items && wo.items.length > 0) || (parseFloat(String(wo.total_amount ?? 0)) > 0)) && (
                    <div className="bg-card rounded-xl overflow-hidden">
                        <button
                            onClick={() => setShowItemsList(!showItemsList)}
                            className="w-full flex items-center justify-between p-4"
                        >
                            <div className="flex items-center gap-2">
                                <DollarSign className="w-4 h-4 text-surface-400" />
                                <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide">
                                    Itens & Valor
                                </h3>
                                {wo.items && wo.items.length > 0 && (
                                    <span className="px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400 text-[10px] font-bold">
                                        {wo.items.length}
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                {parseFloat(String(wo.total_amount ?? 0)) > 0 && (
                                    <span className="text-sm font-semibold text-emerald-600">
                                        {formatCurrency(parseFloat(String(wo.total_amount ?? 0)))}
                                    </span>
                                )}
                                {showItemsList ? <ChevronUp className="w-4 h-4 text-surface-400" /> : <ChevronDown className="w-4 h-4 text-surface-400" />}
                            </div>
                        </button>
                        {showItemsList && wo.items && wo.items.length > 0 && (
                            <div className="px-4 pb-4 space-y-2">
                                {wo.items.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between py-2 border-t border-surface-100">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm text-foreground truncate">{item.description}</p>
                                            <p className="text-[11px] text-surface-500">
                                                {item.type === 'product' ? 'Produto' : 'Serviço'} &middot; Qtd: {item.quantity}
                                            </p>
                                        </div>
                                        <span className="text-sm font-medium text-foreground ml-2 flex-shrink-0">
                                            {formatCurrency(parseFloat(String(item.line_total ?? 0)))}
                                        </span>
                                    </div>
                                ))}
                                {parseFloat(String(wo.displacement_value ?? 0)) > 0 && (
                                    <div className="flex items-center justify-between pt-2 border-t border-surface-100">
                                        <span className="text-sm text-surface-500">Deslocamento</span>
                                        <span className="text-sm font-medium text-emerald-600">
                                            + {formatCurrency(parseFloat(String(wo.displacement_value ?? 0)))}
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* Equipment List */}
                {wo.equipment_refs && wo.equipment_refs.length > 0 && (
                    <div className="bg-card rounded-xl overflow-hidden">
                        <button
                            onClick={() => setShowEquipmentList(!showEquipmentList)}
                            className="w-full flex items-center justify-between p-4"
                        >
                            <div className="flex items-center gap-2">
                                <Layers className="w-4 h-4 text-surface-400" />
                                <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide">Equipamentos</h3>
                                <span className="px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400 text-[10px] font-bold">
                                    {wo.equipment_refs.length}
                                </span>
                            </div>
                            {showEquipmentList ? <ChevronUp className="w-4 h-4 text-surface-400" /> : <ChevronDown className="w-4 h-4 text-surface-400" />}
                        </button>
                        {showEquipmentList && (
                            <div className="px-4 pb-4 space-y-2">
                                {wo.equipment_refs.map((eq) => (
                                    <div key={eq.id} className="py-2 border-t border-surface-100">
                                        <p className="text-sm text-foreground font-medium">
                                            {[eq.brand, eq.model].filter(Boolean).join(' ') || `Equipamento #${eq.id}`}
                                        </p>
                                        <div className="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                                            {eq.serial_number && (
                                                <span className="text-[11px] text-surface-500">S/N: {eq.serial_number}</span>
                                            )}
                                            {eq.tag && (
                                                <span className="text-[11px] text-surface-500">Tag: {eq.tag}</span>
                                            )}
                                            {eq.type && (
                                                <span className="text-[11px] text-surface-500">Tipo: {eq.type}</span>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Description */}
                {wo.description && (
                    <div className="bg-card rounded-xl p-4">
                        <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide mb-2">Descrição</h3>
                        <p className="text-sm text-surface-700 leading-relaxed">
                            {wo.description}
                        </p>
                    </div>
                )}

                {/* Quick Note */}
                <div className="bg-card rounded-xl p-4">
                    <div className="flex items-center justify-between mb-2">
                        <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide">Nota Rápida</h3>
                    </div>
                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={quickNote}
                            onChange={(e) => setQuickNote(e.target.value)}
                            placeholder="Adicionar observação..."
                            className="flex-1 px-3 py-2 rounded-lg bg-surface-100 border-0 text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30 focus:outline-none"
                            onKeyDown={(e) => e.key === 'Enter' && quickNote.trim() && canEditWorkOrderData && void handleSendNote()}
                        />
                        <button
                            onClick={handleSendNote}
                            disabled={!quickNote.trim() || sendingNote || !canEditWorkOrderData}
                            className={cn(
                                'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                quickNote.trim()
                                    ? 'bg-brand-600 text-white active:bg-brand-700'
                                    : 'bg-surface-200 text-surface-400'
                            )}
                        >
                            {sendingNote ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
                        </button>
                    </div>
                    {notes.length > 0 && (
                        <div className="mt-3 space-y-2 max-h-32 overflow-y-auto">
                            {(notes || []).slice(-3).map((note, i) => (
                                <div key={i} className="flex gap-2 text-xs">
                                    <span className="text-surface-400 flex-shrink-0">
                                        {new Date(note.created_at || Date.now()).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                    </span>
                                    <span className="text-surface-600">{note.content || note.message || note.body}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Status History */}
                {wo.status_history && wo.status_history.length > 0 && (
                    <div className="bg-card rounded-xl overflow-hidden">
                        <button
                            onClick={() => setShowStatusHistory(!showStatusHistory)}
                            className="w-full flex items-center justify-between p-4"
                        >
                            <div className="flex items-center gap-2">
                                <Clock className="w-4 h-4 text-brand-500" />
                                <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide">Histórico de Status</h3>
                                <span className="px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400 text-[10px] font-bold">
                                    {wo.status_history.length}
                                </span>
                            </div>
                            {showStatusHistory ? <ChevronUp className="w-4 h-4 text-surface-400" /> : <ChevronDown className="w-4 h-4 text-surface-400" />}
                        </button>
                        {showStatusHistory && (
                            <div className="px-4 pb-4 space-y-3">
                                {wo.status_history.map((h) => {
                                    const cfg = workOrderStatus[h.to_status]
                                    const statusLabel = cfg?.label ?? h.to_status
                                    const statusColor = STATUS_MAP[h.to_status]?.color ?? 'bg-surface-400'
                                    return (
                                        <div key={h.id} className="flex items-start gap-3">
                                            <div className="flex-shrink-0 mt-1">
                                                <div className={cn('w-2.5 h-2.5 rounded-full', statusColor)} />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-foreground">{statusLabel}</p>
                                                {h.notes && (
                                                    <p className="text-[11px] text-surface-500 mt-0.5 line-clamp-2">{h.notes}</p>
                                                )}
                                                <p className="text-[10px] text-surface-400 mt-0.5">
                                                    {new Date(h.created_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                                                    {h.user_name && ` · ${h.user_name}`}
                                                </p>
                                            </div>
                                        </div>
                                    )
                                })}
                            </div>
                        )}
                    </div>
                )}

                {/* Action cards */}
                <div className="grid grid-cols-2 gap-3 pb-8">
                    {(availableActionCards || []).map((card) => (
                        <button
                            key={card.key}
                            onClick={() => {
                                if (card.key === 'add-part-qr') {
                                    setShowAddPartQrModal(true)
                                    setAddPartProduct(null)
                                } else if (card.key === 'chat') {
                                    setIsChatOpen(true)
                                } else {
                                    navigate(`/tech/os/${wo.id}/${card.key}`)
                                }
                            }}
                            className="flex flex-col items-start gap-4 bg-card rounded-2xl p-5 border border-surface-100 shadow-sm active:scale-[0.96] active:bg-surface-50 dark:active:bg-surface-700 transition-all group"
                        >
                            <div className="relative">
                                <div className={cn('w-12 h-12 rounded-xl flex items-center justify-center transition-transform group-active:scale-90', card.color)}>
                                    <card.icon className="w-6 h-6" />
                                </div>
                                {card.key === 'chat' && (wo.comments_count ?? 0) > 0 && (
                                    <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1">
                                        {wo.comments_count}
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center justify-between w-full">
                                <p className="text-sm font-bold text-foreground">{card.label}</p>
                                <ChevronRight className="w-4 h-4 text-surface-300 group-hover:translate-x-1 transition-transform" />
                            </div>
                        </button>
                    ))}
                </div>
            </div>

            {/* Bottom action */}
            {currentStatus.next && (
                <div className="p-4 bg-card border-t border-border safe-area-bottom">
                    <button
                        onClick={handleStatusTransition}
                        disabled={transitioning}
                        className={cn(
                            'w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-colors',
                            currentStatus.next === 'finalize'
                                ? 'bg-emerald-600 active:bg-emerald-700'
                                : 'bg-brand-600 active:bg-brand-700',
                            transitioning && 'opacity-70',
                        )}
                    >
                        {transitioning ? (
                            <Loader2 className="w-4 h-4 animate-spin" />
                        ) : currentStatus.next === 'finalize' ? (
                            <CheckCircle2 className="w-4 h-4" />
                        ) : (
                            <PlayCircle className="w-4 h-4" />
                        )}
                        {currentStatus.nextLabel}
                    </button>
                </div>
            )}

            {showCompletionWizard && (
                <div className="absolute inset-0 z-50 flex flex-col bg-surface-50">
                    <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-bold text-foreground">Finalizar OS</h2>
                            <button onClick={() => setShowCompletionWizard(false)} aria-label="Fechar" className="p-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800">
                                <X className="w-5 h-5 text-surface-500" />
                            </button>
                        </div>
                        <p className="text-xs text-surface-500 mt-1">Verifique os itens antes de concluir</p>
                    </div>

                    <div className="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                        {[
                            { key: 'photos', label: 'Fotos do Serviço', desc: 'Registre fotos do antes/depois', icon: Camera, path: `/tech/os/${wo.id}/photos`, required: false },
                            ...(Array.isArray(wo.equipment_ids) && wo.equipment_ids.length ? [{ key: 'calibration_wizard', label: 'Wizard Calibração', desc: 'Certificado de calibração ISO 17025', icon: FlaskConical, path: `/calibracao/wizard/${wo.equipment_ids[0]}?os=${wo.id}`, required: false }] : []),
                            { key: 'checklist', label: 'Checklists', desc: 'Preencha os checklists obrigatórios', icon: ClipboardList, path: `/tech/os/${wo.id}/checklist`, required: true },
                            { key: 'signature', label: 'Assinatura do Cliente', desc: 'Colete a assinatura', icon: PenTool, path: `/tech/os/${wo.id}/signature`, required: true },
                            { key: 'nps', label: 'Avaliação NPS', desc: 'Colete a avaliação do cliente', icon: Star, path: `/tech/os/${wo.id}/nps`, required: false },
                        ].map((step) => {
                            const StepIcon = step.icon
                            return (
                                <button
                                    key={step.key}
                                    onClick={() => navigate(step.path)}
                                    className="w-full flex items-center gap-3 bg-card rounded-xl p-4 active:scale-[0.98] transition-transform"
                                >
                                    <div className={cn(
                                        'w-10 h-10 rounded-xl flex items-center justify-center',
                                        completionSteps[step.key as keyof typeof completionSteps]
                                            ? 'bg-emerald-100 dark:bg-emerald-900/30'
                                            : 'bg-surface-100'
                                    )}>
                                        {completionSteps[step.key as keyof typeof completionSteps] ? (
                                            <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                        ) : (
                                            <StepIcon className="w-5 h-5 text-surface-600" />
                                        )}
                                    </div>
                                    <div className="flex-1 text-left">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-medium text-foreground">{step.label}</p>
                                            {step.required && (
                                                <span className="text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 font-medium">Recomendado</span>
                                            )}
                                        </div>
                                        <p className="text-xs text-surface-500">{step.desc}</p>
                                    </div>
                                    <ChevronRight className="w-4 h-4 text-surface-300" />
                                </button>
                            )
                        })}

                        {/* Technical Report & Resolution Notes Fields */}
                        <div className="bg-card rounded-xl p-4 border border-surface-200 mt-4 space-y-4">
                            <div>
                                <label htmlFor="technical_report" className="block text-sm font-semibold text-foreground mb-1">
                                    Parecer Técnico <span className="text-red-500">*</span>
                                </label>
                                <p className="text-xs text-surface-500 mb-2">Relato detalhado do diagnóstico e serviço executado.</p>
                                <textarea
                                    id="technical_report"
                                    value={technicalReport}
                                    onChange={(e) => setTechnicalReport(e.target.value)}
                                    className="w-full min-h-[100px] px-3 py-2 rounded-xl bg-surface-50 border border-default text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30 resize-none"
                                    placeholder="Ex: Foi constatado curto-circuito na placa mãe..."
                                    required
                                />
                            </div>

                            <div>
                                <label htmlFor="resolution_notes" className="block text-sm font-semibold text-foreground mb-1">
                                    Notas Internas (Resolução)
                                </label>
                                <p className="text-xs text-surface-500 mb-2">Anotações visíveis apenas para a equipe interna.</p>
                                <textarea
                                    id="resolution_notes"
                                    value={resolutionNotes}
                                    onChange={(e) => setResolutionNotes(e.target.value)}
                                    className="w-full min-h-[80px] px-3 py-2 rounded-xl bg-surface-50 border border-default text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30 resize-none"
                                    placeholder="Notas de resolução..."
                                />
                            </div>
                        </div>
                    </div>

                    <div className="p-4 bg-card border-t border-border safe-area-bottom">
                        <button
                            onClick={handleCompleteOS}
                            disabled={transitioning}
                            className="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-600 text-white text-sm font-semibold active:bg-emerald-700 transition-colors disabled:opacity-70"
                        >
                            {transitioning ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
                            Concluir OS Agora
                        </button>
                        <p className="text-[10px] text-surface-400 text-center mt-2">
                            Você pode concluir mesmo sem completar todos os itens
                        </p>
                    </div>
                </div>
            )}

            {pauseFlowMode && (
                <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm bg-card rounded-t-2xl sm:rounded-2xl p-4 shadow-xl">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-bold text-foreground">
                                {pauseFlowMode === 'displacement' ? 'Pausar deslocamento' : 'Pausar retorno'}
                            </h3>
                            <button onClick={() => { setPauseFlowMode(null); setStopNotes('') }} aria-label="Fechar" className="p-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800">
                                <X className="w-5 h-5 text-surface-500" />
                            </button>
                        </div>
                        <div className="space-y-2">
                            {[
                                { type: 'lunch', label: 'Almoço', icon: Coffee },
                                { type: 'hotel', label: 'Hotel', icon: Car },
                                { type: 'br_stop', label: 'Parada BR', icon: MapPin },
                                { type: 'other', label: 'Outro', icon: Flag },
                            ].map(({ type, label, icon: Icon }) => (
                                <button
                                    key={type}
                                    onClick={() => handlePauseFlow(type)}
                                    className="w-full flex items-center gap-3 py-3 px-4 rounded-xl bg-surface-50 hover:bg-surface-100 dark:hover:bg-surface-700 text-foreground text-sm font-medium active:scale-[0.98]"
                                >
                                    <Icon className="w-5 h-5 text-surface-500" />
                                    {label}
                                </button>
                            ))}
                        </div>
                        <input
                            type="text"
                            value={stopNotes}
                            onChange={(e) => setStopNotes(e.target.value)}
                            placeholder="Observação (opcional)"
                            className="mt-3 w-full px-3 py-2 rounded-lg bg-surface-100 border-0 text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30"
                        />
                    </div>
                </div>
            )}

            {showReturnStartModal && (
                <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm bg-card rounded-t-2xl sm:rounded-2xl p-4 shadow-xl">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-bold text-foreground">Destino do retorno</h3>
                            <button onClick={() => setShowReturnStartModal(false)} aria-label="Fechar" className="p-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800">
                                <X className="w-5 h-5 text-surface-500" />
                            </button>
                        </div>
                        <div className="space-y-2">
                            {RETURN_DESTINATIONS.map((option) => (
                                <button
                                    key={option.value}
                                    onClick={() => handleStartReturn(option.value)}
                                    className="w-full py-3 px-4 rounded-xl bg-surface-50 hover:bg-surface-100 dark:hover:bg-surface-700 text-left text-sm font-medium text-foreground active:scale-[0.98]"
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {showAddPartQrModal && wo && (
                <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm bg-card rounded-t-2xl sm:rounded-2xl p-4 shadow-xl">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-bold text-foreground">
                                {addPartProduct ? 'Confirmar peça' : 'Adicionar peça (QR)'}
                            </h3>
                            <button
                                onClick={() => {
                                    setShowAddPartQrModal(false)
                                    setAddPartProduct(null)
                                    setShowAddPartQrScanner(false)
                                }}
                                aria-label="Fechar" className="p-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800"
                            >
                                <X className="w-5 h-5 text-surface-500" />
                            </button>
                        </div>
                        {!addPartProduct ? (
                            <div className="space-y-3">
                                <p className="text-sm text-surface-600">Escaneie a etiqueta da peça para adicionar à OS.</p>
                                <button
                                    type="button"
                                    onClick={() => setShowAddPartQrScanner(true)}
                                    className="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-brand-600 text-white text-sm font-semibold active:bg-brand-700"
                                >
                                    <QrCode className="w-5 h-5" />
                                    Escanear etiqueta
                                </button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <div className="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-800">
                                    <Package className="w-8 h-8 text-surface-400" />
                                    <div className="min-w-0">
                                        <p className="font-medium text-foreground truncate">{addPartProduct.name}</p>
                                        <p className="text-xs text-surface-500">Produto #{addPartProduct.id}</p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-medium text-surface-500 mb-1">Quantidade</label>
                                        <input
                                            type="number"
                                            min="0.01"
                                            step="any"
                                            value={addPartQty}
                                            onChange={(e) => setAddPartQty(e.target.value)}
                                            className="w-full px-3 py-2 rounded-lg bg-surface-50 border border-default text-sm"
                                            aria-label="Quantidade"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-surface-500 mb-1">Preço unit.</label>
                                        <CurrencyInputInline
                                            value={parseFloat(addPartUnitPrice) || 0}
                                            onChange={(val) => setAddPartUnitPrice(String(val))}
                                            className="w-full px-3 py-2 rounded-lg bg-surface-50 border border-default text-sm"
                                            aria-label="Preço unitário"
                                        />
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setAddPartProduct(null)}
                                        className="flex-1 py-2.5 rounded-xl border border-default text-sm font-medium text-surface-700 hover:bg-surface-50"
                                    >
                                        Outra peça
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleAddPartSubmit}
                                        disabled={addPartSubmitting}
                                        className="flex-1 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold active:bg-brand-700 disabled:opacity-70 flex items-center justify-center gap-2"
                                    >
                                        {addPartSubmitting ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                                        Adicionar
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            <QrScannerModal
                open={showAddPartQrScanner}
                onClose={() => setShowAddPartQrScanner(false)}
                onScan={(raw) => {
                    handleAddPartQrScanned(raw)
                    setShowAddPartQrScanner(false)
                }}
                title="Escanear etiqueta da peça"
            />

            {wo && (
                <TechChatDrawer
                    workOrderId={wo.id}
                    isOpen={isChatOpen}
                    onClose={() => setIsChatOpen(false)}
                />
            )}
        </div>
    )
}
