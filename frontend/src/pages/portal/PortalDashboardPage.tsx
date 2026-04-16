import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useNavigate } from 'react-router-dom'
import {
    FileText,
    DollarSign,
    Clock,
    CheckCircle,
    AlertCircle,
    ArrowRight,
    Package,
    TrendingUp,
    RefreshCw,
} from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { cn, formatCurrency } from '@/lib/utils'
import { WORK_ORDER_STATUS, QUOTE_STATUS } from '@/lib/constants'

const statusConfig: Record<string, { label: string; color: string; bg: string }> = {
    open: { label: 'Aberta', color: 'text-sky-600', bg: 'bg-sky-100' },
    in_progress: { label: 'Em Andamento', color: 'text-amber-600', bg: 'bg-amber-100' },
    waiting_parts: { label: 'Aguardando', color: 'text-orange-600', bg: 'bg-orange-100' },
    completed: { label: 'Concluida', color: 'text-emerald-600', bg: 'bg-emerald-100' },
    cancelled: { label: 'Cancelada', color: 'text-red-600', bg: 'bg-red-100' },
}

interface PortalDashboardWorkOrder {
    id: number
    number: string
    description?: string | null
    status: string
}

interface PortalDashboardQuote {
    id: number
    status: string
}

interface PortalDashboardFinancial {
    amount?: number | string | null
}

const trackingSteps = ['open', 'in_progress', 'completed'] as const

function requireArray<T>(value: unknown, fallbackMessage: string): T[] {
    if (Array.isArray(value)) {
        return value as T[]
    }

    throw new Error(fallbackMessage)
}

export function PortalDashboardPage() {
    const navigate = useNavigate()

    const {
        data: workOrders = [],
        isLoading: loadingWorkOrders,
        isError: hasWorkOrdersError,
        error: workOrdersError,
        refetch: refetchWorkOrders,
    } = useQuery<PortalDashboardWorkOrder[]>({
        queryKey: ['portal-dashboard-os'],
        queryFn: async () => requireArray<PortalDashboardWorkOrder>(
            unwrapData<PortalDashboardWorkOrder[]>(await api.get('/portal/work-orders')),
            'Erro ao carregar ordens de servico',
        ),
    })

    const {
        data: quotes = [],
        isLoading: loadingQuotes,
        isError: hasQuotesError,
        error: quotesError,
        refetch: refetchQuotes,
    } = useQuery<PortalDashboardQuote[]>({
        queryKey: ['portal-dashboard-quotes'],
        queryFn: async () => requireArray<PortalDashboardQuote>(
            unwrapData<PortalDashboardQuote[]>(await api.get('/portal/quotes')),
            'Erro ao carregar orcamentos',
        ),
    })

    const {
        data: financials = [],
        isLoading: loadingFinancials,
        isError: hasFinancialsError,
        error: financialsError,
        refetch: refetchFinancials,
    } = useQuery<PortalDashboardFinancial[]>({
        queryKey: ['portal-dashboard-financials'],
        queryFn: async () => requireArray<PortalDashboardFinancial>(
            unwrapData<PortalDashboardFinancial[]>(await api.get('/portal/financials')),
            'Erro ao carregar financeiro',
        ),
    })

    useEffect(() => {
        if (hasWorkOrdersError) {
            toast.error(getApiErrorMessage(workOrdersError, 'Erro ao carregar ordens de servico'))
        }
        if (hasQuotesError) {
            toast.error(getApiErrorMessage(quotesError, 'Erro ao carregar orcamentos'))
        }
        if (hasFinancialsError) {
            toast.error(getApiErrorMessage(financialsError, 'Erro ao carregar financeiro'))
        }
    }, [financialsError, hasFinancialsError, hasQuotesError, hasWorkOrdersError, quotesError, workOrdersError])

    const isLoading = loadingWorkOrders || loadingQuotes || loadingFinancials
    const hasError = hasWorkOrdersError || hasQuotesError || hasFinancialsError
    const openOS = workOrders.filter((workOrder) => workOrder.status !== WORK_ORDER_STATUS.COMPLETED && workOrder.status !== WORK_ORDER_STATUS.CANCELLED).length
    const completedOS = workOrders.filter((workOrder) => workOrder.status === WORK_ORDER_STATUS.COMPLETED).length
    const pendingQuotes = quotes.filter((quote) => quote.status === QUOTE_STATUS.SENT).length
    const totalPending = financials.reduce((acc, financial) => acc + Number.parseFloat(String(financial.amount ?? 0)), 0)
    const recentOS = workOrders.slice(0, 5)

    const cards = [
        { label: 'OS Abertas', value: openOS, icon: FileText, color: 'text-brand-600 bg-brand-50', link: '/portal/os' },
        { label: 'OS Concluidas', value: completedOS, icon: CheckCircle, color: 'text-emerald-600 bg-emerald-50', link: '/portal/os' },
        { label: 'Orcamentos', value: pendingQuotes, icon: Package, color: 'text-amber-600 bg-amber-50', link: '/portal/orcamentos' },
        { label: 'Faturas', value: formatCurrency(totalPending), icon: DollarSign, color: 'text-red-600 bg-red-50', link: '/portal/financeiro' },
    ]

    const handleRetry = () => {
        void Promise.all([
            refetchWorkOrders(),
            refetchQuotes(),
            refetchFinancials(),
        ])
    }

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Portal do Cliente</h1>
                <p className="mt-0.5 text-sm text-surface-500">Acompanhe suas ordens de servico, orcamentos e faturas.</p>
            </div>

            {isLoading ? (
                <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card text-sm text-surface-500">
                    Carregando dados do portal...
                </div>
            ) : hasError ? (
                <div className="rounded-xl border border-default bg-surface-0 p-8 text-center shadow-card">
                    <RefreshCw className="mx-auto h-10 w-10 text-red-300" />
                    <p className="mt-2 text-sm text-surface-400">Erro ao carregar dados do portal</p>
                    <button type="button" onClick={handleRetry} className="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Tentar novamente
                    </button>
                </div>
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {cards.map((card) => (
                            <button
                                key={card.label}
                                type="button"
                                onClick={() => navigate(card.link)}
                                className="rounded-xl border border-default bg-surface-0 p-5 shadow-card text-left transition-all hover:shadow-elevated hover:-translate-y-0.5 group"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-surface-500 uppercase tracking-wider">{card.label}</p>
                                        <p className="mt-2 text-lg font-semibold text-surface-900 tracking-tight">{card.value}</p>
                                    </div>
                                    <div className={cn('rounded-lg p-2.5', card.color)}>
                                        <card.icon className="h-5 w-5" />
                                    </div>
                                </div>
                                <div className="mt-3 flex items-center text-xs text-brand-600 font-medium group-hover:underline">
                                    Ver detalhes <ArrowRight className="h-3 w-3 ml-1" />
                                </div>
                            </button>
                        ))}
                    </div>

                    <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                        <div className="flex items-center justify-between border-b border-subtle px-5 py-3">
                            <h2 className="text-sm font-semibold text-surface-900">Ultimas Ordens de Servico</h2>
                            <button type="button" onClick={() => navigate('/portal/os')} className="text-xs text-brand-600 font-medium hover:underline">
                                Ver todas
                            </button>
                        </div>

                        {recentOS.length === 0 ? (
                            <div className="py-12 text-center">
                                <AlertCircle className="mx-auto h-8 w-8 text-surface-300" />
                                <p className="mt-2 text-sm text-surface-400">Nenhuma OS encontrada</p>
                            </div>
                        ) : (
                            <div className="divide-y divide-subtle">
                                {recentOS.map((workOrder) => {
                                    const currentStep = workOrder.status === WORK_ORDER_STATUS.COMPLETED
                                        ? 'completed'
                                        : workOrder.status === WORK_ORDER_STATUS.IN_PROGRESS || workOrder.status === WORK_ORDER_STATUS.WAITING_PARTS
                                            ? 'in_progress'
                                            : 'open'
                                    const currentIdx = trackingSteps.indexOf(currentStep)

                                    return (
                                        <div key={workOrder.id} className="px-5 py-4 hover:bg-surface-50 transition-colors">
                                            <div className="flex items-center justify-between mb-3">
                                                <div className="flex items-center gap-3">
                                                    <span className="text-sm font-bold text-brand-600">{workOrder.number}</span>
                                                    <span className="text-sm text-surface-700 truncate max-w-xs">
                                                        {workOrder.description || 'Sem descricao'}
                                                    </span>
                                                </div>
                                                <span
                                                    className={cn(
                                                        'text-xs font-semibold px-2.5 py-1 rounded-full',
                                                        statusConfig[workOrder.status]?.bg ?? 'bg-surface-100',
                                                        statusConfig[workOrder.status]?.color ?? 'text-surface-600',
                                                    )}
                                                >
                                                    {statusConfig[workOrder.status]?.label ?? workOrder.status}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-1">
                                                {trackingSteps.map((step, index) => (
                                                    <div key={step} className="flex items-center flex-1">
                                                        <div
                                                            className={cn(
                                                                'h-2 w-2 rounded-full flex-shrink-0 transition-colors',
                                                                index <= currentIdx ? 'bg-brand-500' : 'bg-surface-300',
                                                            )}
                                                        />
                                                        <div
                                                            className={cn(
                                                                'flex-1 h-0.5 mx-1',
                                                                index < currentIdx ? 'bg-brand-500' : 'bg-surface-200',
                                                                index === trackingSteps.length - 1 && 'hidden',
                                                            )}
                                                        />
                                                    </div>
                                                ))}
                                            </div>

                                            <div className="flex items-center justify-between mt-2 text-xs text-surface-400">
                                                <span>Aberta</span>
                                                <span>Em Andamento</span>
                                                <span>Concluida</span>
                                            </div>
                                        </div>
                                    )
                                })}
                            </div>
                        )}
                    </div>

                    <div className="grid gap-3 sm:grid-cols-3">
                        <button type="button" onClick={() => navigate('/portal/chamados/novo')} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card text-left hover:shadow-elevated transition-all group">
                            <Clock className="h-6 w-6 text-sky-500 mb-2" />
                            <p className="font-semibold text-surface-900 text-sm">Abrir Chamado</p>
                            <p className="text-xs text-surface-400 mt-0.5">Solicite assistencia tecnica</p>
                        </button>
                        <button type="button" onClick={() => navigate('/portal/orcamentos')} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card text-left hover:shadow-elevated transition-all group">
                            <TrendingUp className="h-6 w-6 text-amber-500 mb-2" />
                            <p className="font-semibold text-surface-900 text-sm">Orcamentos</p>
                            <p className="text-xs text-surface-400 mt-0.5">Veja propostas e aprove</p>
                        </button>
                        <button type="button" onClick={() => navigate('/portal/financeiro')} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card text-left hover:shadow-elevated transition-all group">
                            <DollarSign className="h-6 w-6 text-emerald-500 mb-2" />
                            <p className="font-semibold text-surface-900 text-sm">Financeiro</p>
                            <p className="text-xs text-surface-400 mt-0.5">Faturas e pagamentos</p>
                        </button>
                    </div>
                </>
            )}
        </div>
    )
}
