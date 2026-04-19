import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
    AlertTriangle,
    ArrowLeft,
    Calendar,
    CheckCircle2,
    ChevronRight,
    Download,
    FileText,
    History,
    Loader2,
    Scale,
    TrendingUp,
    Wrench,
    XCircle,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { toast } from 'sonner'

interface Calibration {
    id: number
    calibration_date: string
    result: string
    certificate_number?: string | null
    certificate_pdf_path?: string | null
    certificate_url?: string | null
    performer?: { name: string } | null
    readings_count?: number | null
}

interface Maintenance {
    id: number
    type: string
    description: string
    created_at: string
    performer?: { name: string } | null
    work_order?: { id: number; number?: string | null } | null
}

interface WorkOrder {
    id: number
    os_number?: string | null
    number?: string | null
    status: string
    description?: string | null
    created_at?: string | null
}

interface Equipment {
    id: number
    code: string
    type?: string | null
    model?: string | null
    brand?: string | null
    serial_number?: string | null
    tag?: string | null
    customer?: { id: number; name: string } | null
    precision_class?: string | null
    next_calibration_at?: string | null
    maintenances?: Maintenance[]
    work_orders?: WorkOrder[]
}

const statusBadge: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30',
    in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30',
    cancelled: 'bg-surface-200 text-surface-600',
}

type TabKey = 'calibracoes' | 'manutencoes' | 'certificados'

function formatDate(value?: string | null): string {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleDateString('pt-BR')
}

export default function TechEquipmentHistoryPage() {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const [equipment, setEquipment] = useState<Equipment | null>(null)
    const [calibrations, setCalibrations] = useState<Calibration[]>([])
    const [loading, setLoading] = useState(true)
    const [tab, setTab] = useState<TabKey>('calibracoes')

    useEffect(() => {
        if (!id) {
            return
        }

        void loadData(id)
    }, [id])

    async function loadData(equipmentId: string) {
        setLoading(true)
        try {
            const [equipmentResponse, calibrationsResponse] = await Promise.all([
                api.get(`/equipments/${equipmentId}`),
                api.get(`/equipments/${equipmentId}/calibrations`),
            ])

            setEquipment(unwrapData<Equipment>(equipmentResponse))
            setCalibrations(unwrapData<{ calibrations: Calibration[] }>(calibrationsResponse).calibrations ?? [])
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Erro ao carregar dados do equipamento.'))
        } finally {
            setLoading(false)
        }
    }

    async function openCertificatePdf(calibrationId: number) {
        if (!id) {
            return
        }

        try {
            const { data } = await api.get(`/equipments/${id}/calibrations/${calibrationId}/pdf`, {
                responseType: 'blob',
            })
            const url = URL.createObjectURL(data)
            window.open(url, '_blank', 'noopener,noreferrer')
            setTimeout(() => URL.revokeObjectURL(url), 60_000)
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Erro ao abrir certificado.'))
        }
    }

    const recentCalibrations = calibrations.slice(0, 3)
    const allPassed = recentCalibrations.length >= 3 && recentCalibrations.every((calibration) =>
        ['approved', 'approved_with_restriction'].includes(calibration.result)
    )
    const trendLabel = allPassed
        ? 'Tendencia estavel'
        : recentCalibrations.some((calibration) => ['rejected'].includes(calibration.result))
            ? 'Atencao'
            : null
    const trendColor = allPassed ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'
    const maintenances = equipment?.maintenances ?? []
    const relatedWorkOrders = equipment?.work_orders ?? []
    const certificates = calibrations
        .filter((calibration) => calibration.certificate_number || calibration.certificate_pdf_path || calibration.certificate_url)
        .map((calibration) => ({
            id: calibration.id,
            number: calibration.certificate_number,
            date: calibration.calibration_date,
            hasPdf: Boolean(calibration.certificate_pdf_path || calibration.certificate_url),
        }))

    if (loading) {
        return (
            <div className="flex h-full flex-col">
                <Header navigate={navigate} title="Historico do Equipamento" />
                <div className="flex flex-1 items-center justify-center">
                    <Loader2 className="h-8 w-8 animate-spin text-brand-500" />
                </div>
            </div>
        )
    }

    if (!equipment) {
        return (
            <div className="flex h-full flex-col">
                <Header navigate={navigate} title="Equipamento nao encontrado" />
                <div className="flex flex-1 items-center justify-center p-4">
                    <p className="text-sm text-surface-500">Equipamento nao encontrado.</p>
                </div>
            </div>
        )
    }

    return (
        <div className="flex h-full flex-col">
            <Header navigate={navigate} title="Historico do Equipamento" />

            <div className="p-4">
                <div className="space-y-2 rounded-xl bg-card p-4">
                    <span className="text-sm font-semibold text-foreground">
                        {equipment.brand} {equipment.model}
                    </span>
                    <p className="text-xs text-surface-400">{equipment.code}</p>
                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-surface-500">
                        {equipment.type && <span>Tipo: {equipment.type}</span>}
                        {equipment.serial_number && <span>S/N: {equipment.serial_number}</span>}
                        {equipment.customer?.name && <span>Cliente: {equipment.customer.name}</span>}
                        {equipment.precision_class && <span>Classe: {equipment.precision_class}</span>}
                    </div>
                </div>
            </div>

            <div className="flex border-b border-border">
                <TabButton active={tab === 'calibracoes'} onClick={() => setTab('calibracoes')} icon={Scale} label="Calibracoes" />
                <TabButton active={tab === 'manutencoes'} onClick={() => setTab('manutencoes')} icon={Wrench} label="Manutencoes" />
                <TabButton active={tab === 'certificados'} onClick={() => setTab('certificados')} icon={FileText} label="Certificados" />
            </div>

            <div className="flex-1 overflow-y-auto p-4">
                {tab === 'calibracoes' && (
                    <div className="space-y-4">
                        {trendLabel && (
                            <div className={cn('flex items-center gap-2 text-sm font-medium', trendColor)}>
                                {allPassed ? <TrendingUp className="h-4 w-4" /> : <AlertTriangle className="h-4 w-4" />}
                                {trendLabel}
                            </div>
                        )}

                        {equipment.next_calibration_at && (
                            <div className="flex items-center gap-2 text-sm text-surface-600">
                                <Calendar className="h-4 w-4" />
                                Proxima calibracao: {formatDate(equipment.next_calibration_at)}
                            </div>
                        )}

                        <div className="space-y-2">
                            {calibrations.map((calibration) => {
                                const approved = ['approved', 'approved_with_restriction'].includes(calibration.result)

                                return (
                                    <button
                                        key={calibration.id}
                                        type="button"
                                        onClick={() => navigate(`/tech/equipamentos/${id}/calibracoes/${calibration.id}`)}
                                        className="flex w-full items-center justify-between rounded-xl bg-card p-4 text-left active:scale-[0.98] transition-transform"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-foreground">
                                                {formatDate(calibration.calibration_date)}
                                            </p>
                                            <p className="text-xs text-surface-500">
                                                {calibration.performer?.name || 'Tecnico'} • {calibration.readings_count ?? '—'} leituras
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={cn(
                                                    'flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                    approved ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30' : 'bg-red-100 text-red-700 dark:bg-red-900/30',
                                                )}
                                            >
                                                {approved ? <CheckCircle2 className="h-3 w-3" /> : <XCircle className="h-3 w-3" />}
                                                {approved ? 'Aprovado' : 'Reprovado'}
                                            </span>
                                            <ChevronRight className="h-4 w-4 text-surface-400" />
                                        </div>
                                    </button>
                                )
                            })}
                        </div>

                        {calibrations.length === 0 && (
                            <EmptySection icon={Scale} message="Nenhuma calibracao registrada" />
                        )}
                    </div>
                )}

                {tab === 'manutencoes' && (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            {maintenances.map((maintenance) => (
                                <div key={maintenance.id} className="rounded-xl bg-card p-4">
                                    <p className="text-sm font-medium text-foreground">{maintenance.type || 'Manutencao'}</p>
                                    <p className="mt-0.5 text-xs text-surface-500">
                                        {formatDate(maintenance.created_at)} • {maintenance.description || 'Sem descricao'}
                                    </p>
                                    <p className="mt-0.5 text-xs text-surface-400">
                                        {maintenance.performer?.name || 'Tecnico'}
                                        {maintenance.work_order?.id ? ` • OS ${maintenance.work_order.number || maintenance.work_order.id}` : ''}
                                    </p>
                                </div>
                            ))}
                        </div>

                        {maintenances.length === 0 && (
                            <EmptySection icon={Wrench} message="Nenhuma manutencao registrada" />
                        )}

                        {relatedWorkOrders.length > 0 && (
                            <div className="pt-2">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-surface-400">
                                    OS relacionadas
                                </p>
                                <div className="space-y-2">
                                    {relatedWorkOrders.map((workOrder) => (
                                        <button
                                            key={workOrder.id}
                                            type="button"
                                            onClick={() => navigate(`/tech/os/${workOrder.id}`)}
                                            className="flex w-full items-center justify-between rounded-xl bg-card p-4 text-left active:scale-[0.98]"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-foreground">
                                                    OS {workOrder.os_number || workOrder.number || workOrder.id}
                                                </p>
                                                <p className="mt-0.5 text-xs text-surface-500">
                                                    {formatDate(workOrder.created_at)} • {workOrder.description?.slice(0, 50) || '—'}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className={cn('rounded-full px-2 py-0.5 text-[10px] font-medium', statusBadge[workOrder.status] || 'bg-surface-200 text-surface-600')}>
                                                    {workOrder.status}
                                                </span>
                                                <ChevronRight className="h-4 w-4 text-surface-400" />
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {tab === 'certificados' && (
                    <div className="space-y-2">
                        {certificates.map((certificate) => (
                            <div key={certificate.id} className="flex items-center justify-between rounded-xl bg-card p-4">
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        {certificate.number || `Certificado #${certificate.id}`}
                                    </p>
                                    <p className="text-xs text-surface-500">{formatDate(certificate.date)}</p>
                                </div>
                                {certificate.hasPdf && (
                                    <button
                                        type="button"
                                        onClick={() => openCertificatePdf(certificate.id)}
                                        className="flex items-center gap-2 rounded-lg bg-brand-100 px-3 py-2 text-sm font-medium text-brand-700"
                                    >
                                        <Download className="h-4 w-4" />
                                        Baixar
                                    </button>
                                )}
                            </div>
                        ))}

                        {certificates.length === 0 && (
                            <EmptySection icon={FileText} message="Nenhum certificado disponivel" />
                        )}
                    </div>
                )}
            </div>
        </div>
    )
}

function Header({ navigate, title }: { navigate: ReturnType<typeof useNavigate>; title: string }) {
    return (
        <header className="flex items-center gap-3 border-b border-border bg-card px-4 py-3">
            <button type="button" onClick={() => navigate(-1)} className="p-1">
                <ArrowLeft className="h-5 w-5 text-surface-600" />
            </button>
            <History className="h-5 w-5 text-brand-600" />
            <h1 className="text-lg font-bold text-foreground">{title}</h1>
        </header>
    )
}

function TabButton({
    active,
    onClick,
    icon: Icon,
    label,
}: {
    active: boolean
    onClick: () => void
    icon: typeof Scale
    label: string
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex flex-1 items-center justify-center gap-1 py-3 text-sm font-medium',
                active ? 'border-b-2 border-brand-500 text-brand-600' : 'text-surface-500',
            )}
        >
            <Icon className="h-4 w-4" />
            {label}
        </button>
    )
}

function EmptySection({ icon: Icon, message }: { icon: typeof Scale; message: string }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-16">
            <Icon className="h-12 w-12 text-surface-300" />
            <p className="text-sm text-surface-500">{message}</p>
        </div>
    )
}
