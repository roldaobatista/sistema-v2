import { useQuery } from '@tanstack/react-query'
import { Shield, AlertTriangle, CheckCircle, Clock, TrendingUp } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import api from '@/lib/api'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'

interface SlaOverview {
    total_com_sla?: number
    em_risco?: number
    response?: {
        taxa?: number
        cumprido?: number
        estourado?: number
    }
    resolution?: {
        taxa?: number
        cumprido?: number
        estourado?: number
    }
}

interface SlaPolicyMetric {
    id: number
    name: string
    priority: string
    total: number
    breached: number
    compliance_rate: number
}

interface SlaBreachedWorkOrder {
    id: number
    number: string
    os_number?: string | null
    business_number?: string | null
    customer?: { name?: string | null } | null
    assignee?: { name?: string | null } | null
    sla_policy?: { name?: string | null } | null
    sla_response_breached?: boolean
    sla_resolution_breached?: boolean
}

const priorityConfig: Record<string, { label: string; icon: string; color: string }> = {
    low: { label: 'Baixa', icon: 'L', color: 'text-surface-600' },
    medium: { label: 'Media', icon: 'M', color: 'text-amber-600' },
    high: { label: 'Alta', icon: 'A', color: 'text-orange-600' },
    critical: { label: 'Critica', icon: 'C', color: 'text-red-600' },
}

const woIdentifier = (workOrder?: { number: string; os_number?: string | null; business_number?: string | null } | null) =>
    workOrder?.business_number ?? workOrder?.os_number ?? workOrder?.number ?? '-'

export function SlaDashboardPage() {
    const navigate = useNavigate()
    const { hasPermission } = useAuthStore()
    const canView = hasPermission('os.work_order.view')

    const { data: overview, isLoading } = useQuery({
        queryKey: ['sla-dashboard-overview'],
        queryFn: () => api.get('/sla-dashboard/overview'),
        enabled: canView,
    })
    const { data: byPolicy } = useQuery({
        queryKey: ['sla-dashboard-by-policy'],
        queryFn: () => api.get('/sla-dashboard/by-policy'),
        enabled: canView,
    })
    const { data: breached } = useQuery({
        queryKey: ['sla-dashboard-breached'],
        queryFn: () => api.get('/sla-dashboard/breached'),
        enabled: canView,
    })

    const ov: SlaOverview | undefined = overview?.data
    const policies: SlaPolicyMetric[] = byPolicy?.data ?? []
    const breachedOrders: SlaBreachedWorkOrder[] = breached?.data?.data ?? []

    if (!canView) {
        return (
            <div className="space-y-5">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Dashboard SLA</h1>
                </div>
                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Voce nao possui permissao para visualizar o dashboard de SLA.
                </div>
            </div>
        )
    }

    if (isLoading) {
        return <p className="py-12 text-center text-surface-400">Carregando...</p>
    }

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Dashboard SLA</h1>
                <p className="mt-0.5 text-sm text-surface-500">Acompanhamento de cumprimento de SLA em tempo real</p>
            </div>

            <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                <KpiCard icon={<Shield className="h-5 w-5 text-brand-500" />} label="Total com SLA" value={ov?.total_com_sla ?? 0} />
                <KpiCard icon={<CheckCircle className="h-5 w-5 text-emerald-500" />} label="Resposta OK" value={`${ov?.response?.taxa ?? 0}%`} sub={`${ov?.response?.cumprido ?? 0} de ${(ov?.response?.cumprido ?? 0) + (ov?.response?.estourado ?? 0)}`} />
                <KpiCard icon={<AlertTriangle className="h-5 w-5 text-red-500" />} label="Resposta Estourada" value={ov?.response?.estourado ?? 0} danger />
                <KpiCard icon={<TrendingUp className="h-5 w-5 text-emerald-500" />} label="Resolucao OK" value={`${ov?.resolution?.taxa ?? 0}%`} sub={`${ov?.resolution?.cumprido ?? 0} resolvidas`} />
                <KpiCard icon={<Clock className="h-5 w-5 text-amber-500" />} label="Em Risco" value={ov?.em_risco ?? 0} warning />
            </div>

            <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                <div className="border-b border-subtle px-5 py-3">
                    <h2 className="text-sm font-bold text-surface-900">Compliance por Politica</h2>
                </div>
                <div className="divide-y divide-subtle">
                    {(policies || []).map((policy) => {
                        const priority = priorityConfig[policy.priority] ?? priorityConfig.medium
                        return (
                            <div key={policy.id} className="flex items-center justify-between px-5 py-3">
                                <div className="flex items-center gap-3">
                                    <span className="text-lg">{priority.icon}</span>
                                    <div>
                                        <p className="text-sm font-medium text-surface-900">{policy.name}</p>
                                        <p className="text-xs text-surface-500">{policy.total} OS • {policy.breached} estouradas</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="h-2 w-32 overflow-hidden rounded-full bg-surface-100">
                                        <div
                                            className={cn(
                                                'h-full rounded-full transition-all',
                                                policy.compliance_rate >= 90 ? 'bg-emerald-500' : policy.compliance_rate >= 70 ? 'bg-amber-500' : 'bg-red-500'
                                            )}
                                            style={{ width: `${policy.compliance_rate}%` }}
                                        />
                                    </div>
                                    <span className={cn('w-14 text-right text-sm font-bold', policy.compliance_rate >= 90 ? 'text-emerald-600' : policy.compliance_rate >= 70 ? 'text-amber-600' : 'text-red-600')}>
                                        {policy.compliance_rate}%
                                    </span>
                                </div>
                            </div>
                        )
                    })}
                    {policies.length === 0 ? (
                        <p className="px-5 py-6 text-center text-sm text-surface-400">Nenhuma politica com OS associadas</p>
                    ) : null}
                </div>
            </div>

            <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                <div className="border-b border-subtle px-5 py-3">
                    <h2 className="text-sm font-bold text-surface-900">OS com SLA Estourado</h2>
                </div>
                {breachedOrders.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-surface-400">Nenhuma OS com SLA estourado.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-surface-50 text-surface-500">
                                    <th className="px-4 py-2 text-left font-medium">OS</th>
                                    <th className="px-4 py-2 text-left font-medium">Cliente</th>
                                    <th className="px-4 py-2 text-left font-medium">Tecnico</th>
                                    <th className="px-4 py-2 text-left font-medium">SLA</th>
                                    <th className="px-4 py-2 text-left font-medium">Breach</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {(breachedOrders || []).map((workOrder) => (
                                    <tr
                                        key={workOrder.id}
                                        className="hover:bg-red-50/50 cursor-pointer"
                                        onClick={() => navigate(`/os/${workOrder.id}`)}
                                    >
                                        <td className="px-4 py-2 font-mono text-brand-600">{woIdentifier(workOrder)}</td>
                                        <td className="px-4 py-2">{workOrder.customer?.name ?? '-'}</td>
                                        <td className="px-4 py-2">{workOrder.assignee?.name ?? '-'}</td>
                                        <td className="px-4 py-2">{workOrder.sla_policy?.name ?? '-'}</td>
                                        <td className="px-4 py-2">
                                            {workOrder.sla_response_breached ? <span className="mr-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">Resposta</span> : null}
                                            {workOrder.sla_resolution_breached ? <span className="inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700">Resolucao</span> : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    )
}

function KpiCard({ icon, label, value, sub, danger, warning }: { icon: React.ReactNode; label: string; value: string | number; sub?: string; danger?: boolean; warning?: boolean }) {
    return (
        <div className={cn('rounded-xl border bg-surface-0 p-4 shadow-card', danger && 'border-red-200', warning && 'border-amber-200')}>
            <div className="mb-2 flex items-center gap-2">
                {icon}
                <span className="text-xs text-surface-500">{label}</span>
            </div>
            <p className={cn('text-2xl font-bold', danger ? 'text-red-600' : warning ? 'text-amber-600' : 'text-surface-900')}>{value}</p>
            {sub ? <p className="mt-0.5 text-xs text-surface-400">{sub}</p> : null}
        </div>
    )
}
