import { useState, useEffect, useRef, useCallback } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
    FileText, Download, Printer, CheckCircle2, Loader2, ArrowLeft,
    Award, Send, Eye, WifiOff,
} from 'lucide-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { cn, getApiErrorMessage } from '@/lib/utils'
import api, { getApiOrigin } from '@/lib/api'
import { toast } from 'sonner'
import { usePWA } from '@/hooks/usePWA'

const emailSchema = z.object({
    email: z.string().email('Informe um e-mail válido')
})
type EmailFormValues = z.infer<typeof emailSchema>

const TECH_CERTIFICATE_QUERY_KEYS = {
    workOrder: (workOrderId: number) => ['tech-certificate', 'work-order', workOrderId] as const,
    templates: ['tech-certificate', 'templates'] as const,
    calibrations: (equipmentId: number) => ['tech-certificate', 'equipment-calibrations', equipmentId] as const,
}

interface Equipment {
    id: number
    code?: string
    name?: string
    serial_number?: string
}

interface Calibration {
    id: number
    calibration_date: string
    result?: string
    certificate_number?: string
    certificate_pdf_path?: string
    readings?: unknown[]
}

interface CertificateTemplate {
    id: number
    name: string
    is_default?: boolean
}

interface EquipmentListEntry extends Partial<Equipment> {
    equipment?: Equipment | null
}

interface WorkOrderResponse {
    equipment?: Equipment | null
    equipmentsList?: EquipmentListEntry[] | null
}

interface CalibrationRecord extends Calibration {
    work_order_id?: number
}

type ApiEnvelope<T> = T[] | { data?: T[] }
type WorkOrderEnvelope = WorkOrderResponse | { data?: WorkOrderResponse }
type CalibrationPayload = { calibrations?: ApiEnvelope<CalibrationRecord> }

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

function normalizeArrayPayload<T>(payload: ApiEnvelope<T> | null | undefined): T[] {
    if (Array.isArray(payload)) {
        return payload
    }

    return Array.isArray(payload?.data) ? payload.data : []
}

function normalizeWorkOrderPayload(payload: WorkOrderEnvelope | null | undefined): WorkOrderResponse | null {
    if (!payload) {
        return null
    }

    return 'data' in payload && payload.data ? payload.data : payload
}

export default function TechCertificatePage() {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const [selectedEquipment, setSelectedEquipment] = useState<Equipment | null>(null)
    const [selectedTemplateId, setSelectedTemplateId] = useState<number | null>(null)
    const [generating, setGenerating] = useState(false)
    const [certificate, setCertificate] = useState<{
        certificate_number: string
        path?: string
        url?: string
    } | null>(null)
    const [generatingAndPrint, setGeneratingAndPrint] = useState(false)

    const { isOnline } = usePWA()
    const objectUrlsRef = useRef<string[]>([])
    const workOrderId = id ? Number(id) : 0

    const createTrackedObjectUrl = useCallback((blob: Blob): string => {
        const url = URL.createObjectURL(blob)
        objectUrlsRef.current.push(url)
        return url
    }, [])

    useEffect(() => {
        return () => {
            objectUrlsRef.current.forEach(url => URL.revokeObjectURL(url))
            objectUrlsRef.current = []
        }
    }, [])

    const {
        register: registerEmail,
        handleSubmit: handleEmailSubmit,
        formState: { errors: emailErrors, isSubmitting: isSendingEmail },
        reset: resetEmail
    } = useForm<EmailFormValues>({
        resolver: zodResolver(emailSchema)
    })

    const workOrderQuery = useQuery({
        queryKey: TECH_CERTIFICATE_QUERY_KEYS.workOrder(workOrderId),
        queryFn: async () => {
            const { data } = await api.get<WorkOrderEnvelope>(`/work-orders/${workOrderId}`)
            return collectWorkOrderEquipments(normalizeWorkOrderPayload(data))
        },
        enabled: workOrderId > 0,
        retry: false,
    })

    const templatesQuery = useQuery({
        queryKey: TECH_CERTIFICATE_QUERY_KEYS.templates,
        queryFn: async () => {
            const { data } = await api.get<ApiEnvelope<CertificateTemplate>>('/certificate-templates')
            return normalizeArrayPayload<CertificateTemplate>(data)
        },
        retry: false,
    })

    const calibrationsQuery = useQuery({
        queryKey: TECH_CERTIFICATE_QUERY_KEYS.calibrations(selectedEquipment?.id ?? 0),
        queryFn: async () => {
            const { data } = await api.get<CalibrationPayload>(`/equipments/${selectedEquipment!.id}/calibrations`)
            return normalizeArrayPayload<CalibrationRecord>(data.calibrations)
        },
        enabled: Boolean(selectedEquipment?.id),
        retry: false,
    })

    useEffect(() => {
        setSelectedEquipment(null)
        setCertificate(null)
    }, [workOrderId])

    useEffect(() => {
        if (workOrderQuery.isError) {
            toast.error(getApiErrorMessage(workOrderQuery.error, 'Erro ao carregar OS'))
        }
    }, [workOrderQuery.error, workOrderQuery.isError])

    useEffect(() => {
        if (templatesQuery.isError) {
            toast.error(getApiErrorMessage(templatesQuery.error, 'Erro ao carregar templates'))
        }
    }, [templatesQuery.error, templatesQuery.isError])

    useEffect(() => {
        const list = workOrderQuery.data ?? []
        if (list.length !== 1) {
            return
        }

        setSelectedEquipment((current) => current?.id === list[0].id ? current : list[0])
    }, [workOrderQuery.data])

    useEffect(() => {
        const list = templatesQuery.data ?? []
        if (list.length === 0) {
            setSelectedTemplateId(null)
            return
        }

        setSelectedTemplateId((current) => {
            if (current !== null && list.some((template) => template.id === current)) {
                return current
            }

            return list.find((template) => template.is_default)?.id ?? list[0].id
        })
    }, [templatesQuery.data])

    const equipments = workOrderQuery.data ?? []
    const calibrations = calibrationsQuery.data ?? []
    const templates = templatesQuery.data ?? []
    const loading = workOrderQuery.isLoading
    const latestCalibration =
        calibrations.find((c: CalibrationRecord) => c.work_order_id === workOrderId) ??
        calibrations[0] ??
        null

    const handleSelectEquipment = (eq: Equipment) => {
        setSelectedEquipment(eq)
        setCertificate(null)
    }

    const handleGenerate = async () => {
        if (!latestCalibration) {
            toast.error('Nenhuma calibração encontrada para este equipamento')
            return
        }
        setGenerating(true)
        setCertificate(null)
        try {
            const { data } = await api.post(
                `/calibration/${latestCalibration.id}/generate-certificate`,
                selectedTemplateId ? { template_id: selectedTemplateId } : {}
            )
            setCertificate({
                certificate_number: data.certificate_number || 'Gerado',
                path: data.path,
                url: data.url,
            })
            toast.success('Certificado gerado com sucesso')
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao gerar certificado'))
        } finally {
            setGenerating(false)
        }
    }

    function triggerFileDownload(url: string, filename: string) {
        const a = document.createElement('a')
        a.href = url
        a.download = filename
        a.target = '_blank'
        a.rel = 'noopener noreferrer'
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
    }

    const handleViewPdf = async () => {
        if (certificate?.url) {
            triggerFileDownload(certificate.url, `Certificado_${certificate.certificate_number}.pdf`)
        } else if (certificate?.path) {
            const base = getApiOrigin()
            const fullUrl = `${base}/storage/${certificate.path}`

            const toastId = toast.loading('Preparando visualização do documento...')
            try {
                const { data } = await api.get(fullUrl, { responseType: 'blob' })
                const url = createTrackedObjectUrl(new Blob([data], { type: 'application/pdf' }))
                toast.dismiss(toastId)
                triggerFileDownload(url, `Certificado_${certificate.certificate_number}.pdf`)
            } catch {
                toast.dismiss(toastId)
                window.open(fullUrl, '_blank', 'noopener,noreferrer')
            }
        } else {
            toast.error('URL do PDF não disponível')
        }
    }

    const handleGenerateAndPrint = async () => {
        if (!latestCalibration || !selectedEquipment) return
        setGeneratingAndPrint(true)
        setCertificate(null)
        try {
            await api.post(
                `/calibration/${latestCalibration.id}/generate-certificate`,
                selectedTemplateId ? { template_id: selectedTemplateId } : {}
            )
            const { data } = await api.get(
                `/equipments/${selectedEquipment.id}/calibrations/${latestCalibration.id}/pdf`,
                { responseType: 'blob' }
            )
            const url = createTrackedObjectUrl(new Blob([data], { type: 'application/pdf' }))

            triggerFileDownload(url, `Certificado_${latestCalibration.certificate_number || 'Novo'}.pdf`)

            setCertificate({
                certificate_number: latestCalibration.certificate_number ?? 'Gerado',
                path: undefined,
                url,
            })
            toast.success('Certificado pronto para impressão via navegador.')
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao gerar certificado'))
        } finally {
            setGeneratingAndPrint(false)
        }
    }

    const handleSendEmail = async (data: EmailFormValues) => {
        try {
            await api.post(`/calibration/${latestCalibration?.id}/send-certificate-email`, {
                email: data.email,
            })
            toast.success('E-mail enviado com sucesso')
            resetEmail()
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao enviar e-mail'))
        }
    }

    const handlePrint = () => {
        navigate(`/tech/os/${id}/print`)
    }

    const equipmentList = equipments.length > 0 ? equipments : selectedEquipment ? [selectedEquipment] : []

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
                        Certificado de Calibração
                    </h1>
                </div>
                {!isOnline && (
                    <div className="mt-2 flex items-center gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                        <WifiOff className="w-3.5 h-3.5 flex-shrink-0" />
                        <span>Não disponível offline — conecte-se para gerar certificados e enviar e-mails</span>
                    </div>
                )}
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                {loading ? (
                    <div className="flex flex-col items-center justify-center py-20 gap-3">
                        <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                        <p className="text-sm text-surface-500">Carregando...</p>
                    </div>
                ) : (
                    <>
                        <div>
                            <h2 className="text-sm font-medium text-surface-700 mb-2 flex items-center gap-2">
                                <Award className="w-4 h-4" />
                                Selecione o equipamento
                            </h2>
                            <div className="grid gap-2">
                                {(equipmentList || []).map((eq) => (
                                    <button
                                        key={eq.id}
                                        onClick={() => handleSelectEquipment(eq)}
                                        className={cn(
                                            'bg-card rounded-xl p-4 text-left transition-colors',
                                            selectedEquipment?.id === eq.id
                                                ? 'ring-2 ring-brand-500'
                                                : 'hover:bg-surface-50 dark:hover:bg-surface-700/80'
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            <FileText className="w-5 h-5 text-brand-500" />
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    {eq.code || eq.name || `Equipamento #${eq.id}`}
                                                </p>
                                                {eq.serial_number && (
                                                    <p className="text-xs text-surface-500">
                                                        S/N: {eq.serial_number}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {selectedEquipment && (
                            <>
                                <div className="bg-card rounded-xl p-4">
                                    <h3 className="text-sm font-medium text-surface-700 mb-2">
                                        Resumo da Calibração
                                    </h3>
                                    {latestCalibration ? (
                                        <div className="space-y-1 text-sm">
                                            <p>
                                                <span className="text-surface-500">Data:</span>{' '}
                                                {new Date(latestCalibration.calibration_date).toLocaleDateString(
                                                    'pt-BR'
                                                )}
                                            </p>
                                            <p>
                                                <span className="text-surface-500">Resultado:</span>{' '}
                                                <span
                                                    className={cn(
                                                        latestCalibration.result === 'approved'
                                                            ? 'text-emerald-600'
                                                            : latestCalibration.result === 'rejected'
                                                              ? 'text-red-600'
                                                              : 'text-amber-600'
                                                    )}
                                                >
                                                    {latestCalibration.result === 'approved'
                                                        ? 'Aprovado'
                                                        : latestCalibration.result === 'rejected'
                                                          ? 'Reprovado'
                                                          : 'Aprovado com Ressalva'}
                                                </span>
                                            </p>
                                            {latestCalibration.certificate_number && (
                                                <p>
                                                    <span className="text-surface-500">Certificado:</span>{' '}
                                                    {latestCalibration.certificate_number}
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-surface-500">
                                            Nenhuma calibração registrada para este equipamento
                                        </p>
                                    )}
                                </div>

                                {templates.length > 0 && (
                                    <div className="bg-card rounded-xl p-4">
                                        <h3 className="text-sm font-medium text-surface-700 mb-2">
                                            Modelo de Certificado
                                        </h3>
                                        <div className="space-y-2">
                                            {(templates || []).map((t) => (
                                                <button
                                                    key={t.id}
                                                    onClick={() => setSelectedTemplateId(t.id)}
                                                    className={cn(
                                                        'w-full flex items-center gap-2 p-3 rounded-lg text-left transition-colors',
                                                        selectedTemplateId === t.id
                                                            ? 'bg-brand-100 ring-1 ring-brand-500'
                                                            : 'bg-surface-50 hover:bg-surface-100 dark:hover:bg-surface-700'
                                                    )}
                                                >
                                                    <FileText className="w-4 h-4 text-surface-500" />
                                                    <span className="text-sm font-medium">
                                                        {t.name}
                                                        {t.is_default && (
                                                            <span className="ml-1 text-xs text-surface-400">
                                                                (padrão)
                                                            </span>
                                                        )}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <button
                                        onClick={handleGenerate}
                                        disabled={generating || generatingAndPrint || !latestCalibration || !isOnline}
                                        className="flex items-center justify-center gap-2 py-3 bg-brand-600 text-white rounded-xl font-medium disabled:opacity-50"
                                    >
                                        {generating ? (
                                            <>
                                                <Loader2 className="w-5 h-5 animate-spin" />
                                                Gerando...
                                            </>
                                        ) : (
                                            <>
                                                <Download className="w-5 h-5" />
                                                Gerar Certificado
                                            </>
                                        )}
                                    </button>
                                    <button
                                        onClick={handleGenerateAndPrint}
                                        disabled={generating || generatingAndPrint || !latestCalibration || !isOnline}
                                        className="flex items-center justify-center gap-2 py-3 bg-emerald-600 text-white rounded-xl font-medium disabled:opacity-50"
                                    >
                                        {generatingAndPrint ? (
                                            <>
                                                <Loader2 className="w-5 h-5 animate-spin" />
                                                Gerando...
                                            </>
                                        ) : (
                                            <>
                                                <Printer className="w-5 h-5" />
                                                Gerar e imprimir
                                            </>
                                        )}
                                    </button>
                                </div>

                                {certificate && (
                                    <div className="bg-emerald-50 rounded-xl p-4 space-y-3">
                                        <div className="flex items-center gap-3">
                                            <CheckCircle2 className="w-8 h-8 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                                            <div>
                                                <p className="font-medium text-emerald-800 dark:text-emerald-300">
                                                    Certificado gerado
                                                </p>
                                                <p className="text-sm text-emerald-600 dark:text-emerald-400">
                                                    Nº {certificate.certificate_number}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <button
                                                onClick={handleViewPdf}
                                                className="flex items-center gap-2 px-3 py-2 bg-card rounded-lg text-sm font-medium hover:bg-surface-50 dark:hover:bg-surface-700"
                                            >
                                                <Eye className="w-4 h-4" />
                                                Visualizar PDF
                                            </button>
                                            <button
                                                onClick={handlePrint}
                                                className="flex items-center gap-2 px-3 py-2 bg-card rounded-lg text-sm font-medium hover:bg-surface-50 dark:hover:bg-surface-700"
                                            >
                                                <Printer className="w-4 h-4" />
                                                Imprimir
                                            </button>
                                        </div>
                                        <div className="pt-2 border-t border-emerald-200">
                                            <p className="text-xs font-medium text-surface-600 mb-2">
                                                Enviar por e-mail
                                            </p>
                                            <div className="flex gap-2">
                                                <div className="flex-1">
                                                    <input
                                                        {...registerEmail('email')}
                                                        placeholder="E-mail do destinatário"
                                                        aria-label="E-mail do destinatário"
                                                        className="w-full px-3 py-2.5 rounded-lg bg-card border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none"
                                                    />
                                                    {emailErrors.email && <p className="text-xs text-red-500 mt-1">{emailErrors.email.message}</p>}
                                                </div>
                                                <button
                                                    onClick={handleEmailSubmit(handleSendEmail)}
                                                    disabled={isSendingEmail || !isOnline}
                                                    className="flex items-start justify-center h-fit gap-2 px-4 py-2.5 bg-brand-600 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                                                    title={!isOnline ? 'Não disponível offline' : undefined}
                                                >
                                                    {isSendingEmail ? (
                                                        <Loader2 className="w-4 h-4 animate-spin" />
                                                    ) : (
                                                        <Send className="w-4 h-4 mt-0.5" />
                                                    )}
                                                    Enviar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </>
                )}
            </div>
        </div>
    )
}
