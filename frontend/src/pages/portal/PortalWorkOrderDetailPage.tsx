import { useParams, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Clock, FileText, Wrench, User, Paperclip, CheckCircle } from 'lucide-react'
import api, { unwrapData } from '@/lib/api'
import { workOrderStatus } from '@/lib/status-config'
import { cn } from '@/lib/utils'
import { StatusTimeline } from '@/components/os/StatusTimeline'

interface PortalWorkOrderItem {
    id: number
    type: string
    description: string
    quantity: number
    unit_price: number
    total: number
}

interface PortalWorkOrderAttachment {
    id: number
    file_name: string
    file_path: string
    file_type: string
    created_at: string
}

interface PortalWorkOrderData {
    id: number
    business_number?: string
    os_number?: string
    number?: number
    status: string
    priority: string
    description?: string
    service_type?: string
    total?: number
    created_at?: string
    completed_at?: string
    scheduled_date?: string
    customer?: { id: number; name: string }
    assignee?: { id: number; name: string }
    technicians?: { id: number; name: string }[]
    driver?: { id: number; name: string }
    equipment?: { id: number; brand?: string; model?: string; serial_number?: string }
    equipments_list?: { id: number; brand?: string; model?: string; serial_number?: string; tag?: string }[]
    items?: PortalWorkOrderItem[]
    status_history?: { id: number; from_status: string; to_status: string; notes?: string; created_at: string; user?: { id: number; name: string } }[]
    attachments?: PortalWorkOrderAttachment[]
    timeline_events?: { event_type: string; metadata?: Record<string, unknown>; created_at: string }[]
    technical_report?: string
}

const statusLabels: Record<string, string> = {}
const statusColors: Record<string, string> = {}
Object.entries(workOrderStatus).forEach(([key, cfg]) => {
    statusLabels[key] = cfg.label
    statusColors[key] = cfg.badgeClass || 'bg-surface-100 text-surface-700'
})

const priorityLabels: Record<string, string> = { low: 'Baixa', normal: 'Normal', high: 'Alta', urgent: 'Urgente' }

export function PortalWorkOrderDetailPage() {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()

    const { data, isLoading, isError } = useQuery({
        queryKey: ['portal-work-order', id],
        queryFn: () => api.get(`/portal/work-orders/${id}`).then(r => unwrapData<PortalWorkOrderData>(r)),
        enabled: !!id,
    })

    if (isLoading) {
        return (
            <div className="flex flex-col h-full bg-surface-50">
                <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                    <button onClick={() => navigate('/portal/os')} className="flex items-center gap-1 text-sm text-brand-600 mb-2">
                        <ArrowLeft className="w-4 h-4" /> Voltar
                    </button>
                    <div className="h-6 w-48 bg-surface-200 rounded animate-pulse" />
                </div>
                <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    {[1, 2, 3].map(i => <div key={i} className="h-24 bg-surface-200 rounded-xl animate-pulse" />)}
                </div>
            </div>
        )
    }

    if (isError || !data) {
        return (
            <div className="flex flex-col h-full bg-surface-50">
                <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                    <button onClick={() => navigate('/portal/os')} className="flex items-center gap-1 text-sm text-brand-600 mb-2">
                        <ArrowLeft className="w-4 h-4" /> Voltar
                    </button>
                    <h1 className="text-lg font-bold text-foreground">Erro</h1>
                </div>
                <div className="flex-1 flex items-center justify-center">
                    <p className="text-sm text-surface-500">Não foi possível carregar esta ordem de serviço.</p>
                </div>
            </div>
        )
    }

    const wo = data
    const osNumber = wo.business_number ?? wo.os_number ?? (wo.number ? `#${wo.number}` : `#${wo.id}`)
    const items = wo.items ?? []
    const attachments = wo.attachments ?? []
    const statusHistory = wo.status_history ?? []

    return (
        <div className="flex flex-col h-full bg-surface-50">
            {/* Header */}
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                <button onClick={() => navigate('/portal/os')} className="flex items-center gap-1 text-sm text-brand-600 mb-2" aria-label="Voltar para lista">
                    <ArrowLeft className="w-4 h-4" /> Voltar
                </button>
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-foreground">OS {osNumber}</h1>
                    <span className={cn('inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[wo.status] || 'bg-surface-100 text-surface-700')}>
                        {statusLabels[wo.status] || wo.status}
                    </span>
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                {/* Info */}
                <div className="bg-card rounded-xl p-4 space-y-2">
                    <h2 className="text-xs font-semibold text-surface-500 uppercase tracking-wide">Informações</h2>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span className="text-surface-400 text-xs">Prioridade</span>
                            <p className="font-medium text-surface-800">{priorityLabels[wo.priority] || wo.priority}</p>
                        </div>
                        {wo.service_type && (
                            <div>
                                <span className="text-surface-400 text-xs">Tipo</span>
                                <p className="font-medium text-surface-800">{wo.service_type}</p>
                            </div>
                        )}
                        {wo.created_at && (
                            <div>
                                <span className="text-surface-400 text-xs">Criada em</span>
                                <p className="font-medium text-surface-800">{new Date(wo.created_at).toLocaleDateString('pt-BR')}</p>
                            </div>
                        )}
                        {wo.scheduled_date && (
                            <div>
                                <span className="text-surface-400 text-xs">Agendada</span>
                                <p className="font-medium text-surface-800">{new Date(wo.scheduled_date).toLocaleDateString('pt-BR')}</p>
                            </div>
                        )}
                        {wo.total != null && (
                            <div>
                                <span className="text-surface-400 text-xs">Total</span>
                                <p className="font-bold text-teal-600">R$ {Number(wo.total).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Descrição */}
                {wo.description && (
                    <div className="bg-card rounded-xl p-4 space-y-2">
                        <h2 className="text-xs font-semibold text-surface-500 uppercase tracking-wide flex items-center gap-1">
                            <FileText className="w-3.5 h-3.5" /> Descrição
                        </h2>
                        <p className="text-sm text-surface-700 whitespace-pre-wrap">{wo.description}</p>
                    </div>
                )}

                {/* Técnicos */}
                {(wo.assignee || (wo.technicians && wo.technicians.length > 0)) && (
                    <div className="bg-card rounded-xl p-4 space-y-2">
                        <h2 className="text-xs font-semibold text-surface-500 uppercase tracking-wide flex items-center gap-1">
                            <User className="w-3.5 h-3.5" /> Equipe Técnica
                        </h2>
                        <div className="flex flex-wrap gap-2">
                            {wo.assignee && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700">
                                    <Wrench className="w-3 h-3" /> {wo.assignee.name}
                                </span>
                            )}
                            {wo.technicians?.filter(t => t.id !== wo.assignee?.id).map(t => (
                                <span key={t.id} className="inline-flex rounded-full bg-surface-100 px-2.5 py-0.5 text-xs font-medium text-surface-700">
                                    {t.name}
                                </span>
                            ))}
                        </div>
                    </div>
                )}

                {/* Equipamentos */}
                {wo.equipments_list && wo.equipments_list.length > 0 && (
                    <div className="bg-card rounded-xl p-4 space-y-2">
                        <h2 className="text-xs font-semibold text-surface-500 uppercase tracking-wide flex items-center gap-1">
                            <Wrench className="w-3.5 h-3.5" /> Equipamentos
                        </h2>
                        <div className="space-y-1">
                            {wo.equipments_list.map(eq => (
                                <p key={eq.id} className="text-sm text-surface-700">
                                    {[eq.brand, eq.model].filter(Boolean).join(' ')} {eq.serial_number ? `(S/N: ${eq.serial_number})` : ''} {eq.tag ? `[${eq.tag}]` : ''}
                                </p>
                            ))}
                        </div>
                    </div>
                )}

                {/* Itens */}
                {items.length > 0 && (
                    <div className="bg-card rounded-xl p-4 space-y-2">
                        <h2 className="text-xs font-semibold text-surface-500 uppercase tracking-wide">Itens / Serviços</h2>
                        <div className="divide-y divide-subtle">
                            {items.map(item => (
                                <div key={item.id} className="flex justify-between py-2 text-sm">
                                    <div className="flex-1">
                                        <p className="text-surface-800">{item.description}</p>
                                        <p className="text-xs text-surface-400">{item.type === 'product' ? 'Produto' : 'Serviço'} • Qtd: {item.quantity}</p>
                                    </div>
                                    <span className="font-medium text-surface-800 ml-2">R$ {Number(item.total).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Laudo */}
                {wo.technical_report && (
                    <div className="bg-card rounded-xl p-4 space-y-2">
                        <h2 className="text-xs font-semibold text-surface-500 uppercase tracking-wide flex items-center gap-1">
                            <CheckCircle className="w-3.5 h-3.5" /> Laudo Técnico
                        </h2>
                        <p className="text-sm text-surface-700 whitespace-pre-wrap">{wo.technical_report}</p>
                    </div>
                )}

                {/* Timeline */}
                {statusHistory.length > 0 && (
                    <div className="bg-card rounded-xl p-4 space-y-2">
                        <h2 className="text-xs font-semibold text-surface-500 uppercase tracking-wide flex items-center gap-1">
                            <Clock className="w-3.5 h-3.5" /> Histórico de Status
                        </h2>
                        <StatusTimeline currentStatus={wo.status} statusHistory={statusHistory} />
                    </div>
                )}

                {/* Anexos */}
                {attachments.length > 0 && (
                    <div className="bg-card rounded-xl p-4 space-y-2">
                        <h2 className="text-xs font-semibold text-surface-500 uppercase tracking-wide flex items-center gap-1">
                            <Paperclip className="w-3.5 h-3.5" /> Anexos ({attachments.length})
                        </h2>
                        <div className="space-y-1">
                            {attachments.map(att => (
                                <a key={att.id} href={att.file_path} target="_blank" rel="noopener noreferrer"
                                    className="block text-sm text-brand-600 hover:underline truncate">
                                    {att.file_name}
                                </a>
                            ))}
                        </div>
                    </div>
                )}

                <div className="h-4" />
            </div>
        </div>
    )
}

export default PortalWorkOrderDetailPage
