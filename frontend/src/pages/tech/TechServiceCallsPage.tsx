import { useState, useMemo, useCallback, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    Phone, MapPin, Clock, ChevronRight, Search, AlertCircle,
    CheckCircle2, Loader2, ArrowLeft, ArrowRightCircle, Navigation,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import api, { getApiErrorMessage } from '@/lib/api'
import { canAcceptServiceCall, unwrapServiceCallPayload } from '@/lib/service-call-normalizers'
import { toast } from 'sonner'
import { useOfflineCache } from '@/hooks/useOfflineCache'

interface ServiceCall {
    id: number
    call_number: string
    customer?: { id: number; name?: string; phone?: string } | null
    observations?: string | null
    address?: string | null
    city?: string | null
    state?: string | null
    status: string
    priority: string
    created_at: string
    sla_breached?: boolean
    sla_remaining_minutes?: number | null
    latitude?: number
    longitude?: number
}

const STATUS_MAP: Record<string, { label: string; color: string }> = {
    pending_scheduling: { label: 'Pendente Agendamento', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    scheduled: { label: 'Agendado', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30' },
    rescheduled: { label: 'Reagendado', color: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
    awaiting_confirmation: { label: 'Aguard. Confirmacao', color: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400' },
    in_progress: { label: 'Em Andamento', color: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400' },
    converted_to_os: { label: 'Convertido em OS', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30' },
    cancelled: { label: 'Cancelado', color: 'bg-red-100 text-red-700 dark:bg-red-900/30' },
}

const PRIORITY_COLORS: Record<string, string> = {
    low: 'border-l-surface-300',
    normal: 'border-l-blue-400',
    high: 'border-l-amber-500',
    urgent: 'border-l-red-500',
}

const PRIORITY_LABELS: Record<string, string> = {
    low: 'Baixa',
    normal: 'Normal',
    high: 'Alta',
    urgent: 'Urgente',
}

export default function TechServiceCallsPage() {
    const navigate = useNavigate()
    const fetchCalls = useCallback(async () => {
        const { data } = await api.get('/service-calls', {
            params: { my: '1', per_page: 50 },
        })

        return (data.data || []).map((call: ServiceCall) => ({
            ...call,
            customer: call.customer ?? null,
        })) as ServiceCall[]
    }, [])

    const { data: callsData, loading, error, refresh } = useOfflineCache(fetchCalls, { key: 'tech-service-calls' })
    const calls = callsData ?? []
    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState<string>('all')
    const [expandedId, setExpandedId] = useState<number | null>(null)
    const [updatingStatus, setUpdatingStatus] = useState<number | null>(null)

    useEffect(() => {
        if (error) toast.error(error)
    }, [error])

    const filteredCalls = useMemo(() => {
        return calls.filter((call) => {
            const matchesSearch = !search || [
                call.call_number,
                call.customer?.name,
                call.observations,
            ].some((field) => field?.toLowerCase().includes(search.toLowerCase()))

            const matchesStatus = statusFilter === 'all' || call.status === statusFilter

            return matchesSearch && matchesStatus
        })
    }, [calls, search, statusFilter])

    const handleAccept = async (id: number) => {
        try {
            setUpdatingStatus(id)
            await api.put(`/service-calls/${id}/status`, { status: 'scheduled' })
            toast.success('Chamado aceito e agendado')
            refresh()
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao aceitar chamado'))
        } finally {
            setUpdatingStatus(null)
        }
    }

    const handleConvertToOS = async (id: number) => {
        try {
            setUpdatingStatus(id)
            const response = await api.post(`/service-calls/${id}/convert-to-os`)
            const workOrder = unwrapServiceCallPayload<{ id?: number }>(response)
            toast.success('Chamado convertido em OS com sucesso')
            navigate(workOrder?.id ? `/tech/os/${workOrder.id}` : '/tech/os')
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao converter chamado'))
        } finally {
            setUpdatingStatus(null)
        }
    }

    const handleNavigate = (call: ServiceCall) => {
        if (call.latitude && call.longitude) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${call.latitude},${call.longitude}`
            window.open(url, '_blank')
        } else if (call.address || call.city) {
            const address = [call.address, call.city, call.state].filter(Boolean).join(', ')
            const url = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`
            window.open(url, '_blank')
        } else {
            toast.error('Endereco nao disponivel')
        }
    }

    const statusFilters = [
        { key: 'all', label: 'Todos' },
        { key: 'pending_scheduling', label: 'Pendentes' },
        { key: 'scheduled', label: 'Agendados' },
        { key: 'rescheduled', label: 'Reagendados' },
        { key: 'awaiting_confirmation', label: 'Aguardando' },
        { key: 'in_progress', label: 'Em andamento' },
        { key: 'converted_to_os', label: 'Convertidos' },
    ]

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-border bg-card px-4 pb-4 pt-3">
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => navigate(-1)}
                        className="rounded-lg p-1.5 transition-colors hover:bg-surface-100 dark:hover:bg-surface-800"
                    >
                        <ArrowLeft className="h-5 w-5 text-surface-600" />
                    </button>
                    <h1 className="text-lg font-bold text-foreground">
                        Chamados Tecnicos
                    </h1>
                </div>
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar chamados..."
                        className="w-full rounded-xl border-0 bg-surface-100 py-2.5 pl-9 pr-4 text-sm placeholder:text-surface-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                    />
                </div>

                <div className="no-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    {statusFilters.map((filter) => (
                        <button
                            key={filter.key}
                            onClick={() => setStatusFilter(filter.key)}
                            className={cn(
                                'whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition-colors',
                                statusFilter === filter.key
                                    ? 'bg-brand-600 text-white'
                                    : 'bg-surface-100 text-surface-600'
                            )}
                        >
                            {filter.label}
                        </button>
                    ))}
                </div>

                {loading ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20">
                        <Loader2 className="h-8 w-8 animate-spin text-brand-500" />
                        <p className="text-sm text-surface-500">Carregando chamados...</p>
                    </div>
                ) : filteredCalls.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20">
                        <AlertCircle className="h-12 w-12 text-surface-300" />
                        <p className="text-sm text-surface-500">
                            {search ? 'Nenhum chamado encontrado' : 'Nenhum chamado atribuido'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {filteredCalls.map((call) => {
                            const status = STATUS_MAP[call.status] || STATUS_MAP.pending_scheduling
                            const priorityKey = call.priority || 'normal'
                            const isExpanded = expandedId === call.id

                            return (
                                <div
                                    key={call.id}
                                    className={cn(
                                        'rounded-xl border-l-4 bg-card p-4 shadow-sm',
                                        PRIORITY_COLORS[priorityKey] || PRIORITY_COLORS.normal
                                    )}
                                >
                                    <button
                                        onClick={() => setExpandedId(isExpanded ? null : call.id)}
                                        className="w-full text-left"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                                    <span className="text-sm font-semibold text-foreground">
                                                        {call.call_number}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                            status.color
                                                        )}
                                                    >
                                                        {status.label}
                                                    </span>
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-medium text-surface-600">
                                                        {PRIORITY_LABELS[priorityKey]}
                                                    </span>
                                                </div>
                                                <p className="truncate text-xs text-surface-500">
                                                    {call.customer?.name || 'Cliente nao informado'}
                                                </p>
                                                {call.observations && (
                                                    <p className="mt-0.5 line-clamp-1 text-xs text-surface-400">
                                                        {call.observations}
                                                    </p>
                                                )}
                                                <div className="mt-2 flex items-center gap-3 text-[11px] text-surface-400">
                                                    <span className="flex items-center gap-1">
                                                        <Clock className="h-3 w-3" />
                                                        {new Date(call.created_at).toLocaleDateString('pt-BR')}
                                                    </span>
                                                    {call.sla_breached && (
                                                        <span className="flex items-center gap-1 text-red-600 dark:text-red-400">
                                                            <AlertCircle className="h-3 w-3" />
                                                            SLA Estourado
                                                        </span>
                                                    )}
                                                    {!call.sla_breached && call.sla_remaining_minutes != null && call.sla_remaining_minutes < 120 && (
                                                        <span className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                            <AlertCircle className="h-3 w-3" />
                                                            SLA: {Math.floor(call.sla_remaining_minutes / 60)}h{call.sla_remaining_minutes % 60}min
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <ChevronRight
                                                className={cn(
                                                    'mt-1 h-5 w-5 flex-shrink-0 text-surface-300 transition-transform',
                                                    isExpanded && 'rotate-90'
                                                )}
                                            />
                                        </div>
                                    </button>

                                    {isExpanded && (
                                        <div className="mt-4 space-y-3 border-t border-border pt-4">
                                            {call.observations && (
                                                <div>
                                                    <p className="mb-1 text-xs font-medium text-surface-600">
                                                        Observacoes
                                                    </p>
                                                    <p className="text-sm text-foreground">
                                                        {call.observations}
                                                    </p>
                                                </div>
                                            )}
                                            {call.customer?.phone && (
                                                <div className="flex items-center gap-2">
                                                    <Phone className="h-4 w-4 text-surface-400" />
                                                    <a
                                                        href={`tel:${call.customer.phone}`}
                                                        className="text-sm text-brand-600"
                                                    >
                                                        {call.customer.phone}
                                                    </a>
                                                </div>
                                            )}
                                            {(call.address || call.city) && (
                                                <div className="flex items-start gap-2">
                                                    <MapPin className="mt-0.5 h-4 w-4 text-surface-400" />
                                                    <p className="flex-1 text-sm text-foreground">
                                                        {[call.address, call.city, call.state].filter(Boolean).join(', ')}
                                                    </p>
                                                </div>
                                            )}

                                            <div className="flex gap-2 pt-2">
                                                {canAcceptServiceCall(call.status) && (
                                                    <button
                                                        onClick={() => handleAccept(call.id)}
                                                        disabled={updatingStatus === call.id}
                                                        className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                                                    >
                                                        {updatingStatus === call.id ? (
                                                            <>
                                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                                Aceitando...
                                                            </>
                                                        ) : (
                                                            <>
                                                                <CheckCircle2 className="h-4 w-4" />
                                                                Aceitar
                                                            </>
                                                        )}
                                                    </button>
                                                )}
                                                {['scheduled', 'rescheduled', 'awaiting_confirmation', 'in_progress'].includes(call.status) && (
                                                    <button
                                                        onClick={() => handleConvertToOS(call.id)}
                                                        disabled={updatingStatus === call.id}
                                                        className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                                                    >
                                                        {updatingStatus === call.id ? (
                                                            <>
                                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                                Convertendo...
                                                            </>
                                                        ) : (
                                                            <>
                                                                <ArrowRightCircle className="h-4 w-4" />
                                                                Converter em OS
                                                            </>
                                                        )}
                                                    </button>
                                                )}
                                                {(call.latitude || call.address) && (
                                                    <button
                                                        onClick={() => handleNavigate(call)}
                                                        className="flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white"
                                                    >
                                                        <Navigation className="h-4 w-4" />
                                                        Navegar
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )
                        })}
                    </div>
                )}
            </div>
        </div>
    )
}
