import { useState, useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { BarChart3, Clock, CheckCircle, AlertTriangle, Wrench, TrendingUp, Users, DollarSign, Shield, Calendar, Timer, Activity } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import api, { unwrapData } from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { workOrderStatus } from '@/lib/status-config'
import { cn } from '@/lib/utils'
import { safeArray } from '@/lib/safe-array'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useAuthStore } from '@/stores/auth-store'
import type { WorkOrder } from '@/types/work-order'
import { ResponsiveContainer, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip } from 'recharts'

function KpiCard({ icon, label, value, sub, color }: {
    icon: React.ReactNode; label: string; value: string | number; sub?: string; color?: string
}) {
    return (
        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
            <div className="flex items-center gap-2 mb-2">
                {icon}
                <span className="text-xs text-surface-500">{label}</span>
            </div>
            <p className={cn('text-2xl font-bold', color || 'text-surface-900')}>{value}</p>
            {sub && <p className="text-xs text-surface-400 mt-0.5">{sub}</p>}
        </div>
    )
}

const statusLabels = Object.fromEntries(
    Object.entries(workOrderStatus).map(([status, config]) => [status, config.label])
) as Record<string, string>

const statusColors: Record<string, string> = {
    open: 'bg-blue-100 text-blue-700',
    awaiting_dispatch: 'bg-amber-100 text-amber-700',
    in_displacement: 'bg-cyan-100 text-cyan-700',
    displacement_paused: 'bg-amber-100 text-amber-700',
    at_client: 'bg-emerald-100 text-emerald-700',
    in_service: 'bg-amber-100 text-amber-700',
    service_paused: 'bg-amber-100 text-amber-700',
    awaiting_return: 'bg-teal-100 text-teal-700',
    in_return: 'bg-cyan-100 text-cyan-700',
    return_paused: 'bg-amber-100 text-amber-700',
    in_progress: 'bg-amber-100 text-amber-700',
    completed: 'bg-emerald-100 text-emerald-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    invoiced: 'bg-brand-100 text-brand-700',
    cancelled: 'bg-red-100 text-red-700',
    waiting_parts: 'bg-orange-100 text-orange-700',
    waiting_approval: 'bg-brand-100 text-brand-700',
}

interface DashboardTopCustomer {
    name: string
    total_os: number
    revenue: number | string
}

interface DashboardDailyTrend {
    date: string
    count: number
    revenue: number
}

interface WorkOrderDashboardStats {
    total_orders?: number
    month_revenue?: number | string
    avg_completion_hours?: number | string | null
    sla_compliance?: number | string
    overdue_orders?: number
    status_counts?: Record<string, number>
    service_type_counts?: Record<string, number>
    top_customers?: DashboardTopCustomer[]
    daily_trend?: DashboardDailyTrend[]
}

const statusBarColors: Record<string, string> = {
    open: 'bg-blue-500',
    awaiting_dispatch: 'bg-amber-500',
    in_displacement: 'bg-cyan-500',
    at_client: 'bg-emerald-500',
    in_service: 'bg-amber-400',
    in_progress: 'bg-amber-500',
    completed: 'bg-emerald-500',
    delivered: 'bg-emerald-400',
    invoiced: 'bg-brand-500',
    cancelled: 'bg-red-500',
    waiting_parts: 'bg-orange-500',
    waiting_approval: 'bg-brand-400',
}

function StatusBarChart({ statusCounts }: { statusCounts: Record<string, number> }) {
    const entries = Object.entries(statusCounts)
        .filter(([, count]) => (count as number) > 0)
        .sort(([, a], [, b]) => (b as number) - (a as number))
    const maxCount = Math.max(...entries.map(([, c]) => c as number), 1)

    return (
        <div className="space-y-2 p-4">
            {entries.map(([status, count]) => (
                <div key={status} className="flex items-center gap-3">
                    <span className="text-xs text-surface-600 w-28 truncate text-right">{statusLabels[status] || status}</span>
                    <div className="flex-1 h-5 bg-surface-100 rounded-full overflow-hidden">
                        <div
                            className={cn('h-full rounded-full transition-all', statusBarColors[status] || 'bg-surface-400')}
                            style={{ width: `${((count as number) / maxCount) * 100}%` }}
                        />
                    </div>
                    <span className="text-xs font-bold text-surface-700 w-8">{count as number}</span>
                </div>
            ))}
            {entries.length === 0 && <p className="text-sm text-surface-400 text-center py-4">Sem dados</p>}
        </div>
    )
}

function TrendChart({ data }: { data: DashboardDailyTrend[] }) {
    if (!data || data.length === 0) {
        return <p className="px-5 py-6 text-sm text-surface-400 text-center">Sem dados para o período</p>
    }

    const chartData = data.map(d => ({
        date: new Date(d.date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }),
        'OS Criadas': d.count,
        'Receita': d.revenue,
    }))

    return (
        <div className="p-4" style={{ height: 260 }}>
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={chartData} margin={{ top: 5, right: 10, left: 0, bottom: 5 }}>
                    <defs>
                        <linearGradient id="colorCount" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#059669" stopOpacity={0.3} />
                            <stop offset="95%" stopColor="#059669" stopOpacity={0} />
                        </linearGradient>
                        <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#14b8a6" stopOpacity={0.3} />
                            <stop offset="95%" stopColor="#14b8a6" stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                    <XAxis dataKey="date" tick={{ fontSize: 10 }} stroke="#94a3b8" />
                    <YAxis yAxisId="left" tick={{ fontSize: 10 }} stroke="#94a3b8" allowDecimals={false} />
                    <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 10 }} stroke="#94a3b8" tickFormatter={(v: number) => `R$${(v / 1000).toFixed(0)}k`} />
                    <Tooltip
                        contentStyle={{ borderRadius: 8, fontSize: 12, border: '1px solid #e2e8f0' }}
                        formatter={(value: number, name: string) => {
                            if (name === 'Receita') return [`R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`, name]
                            return [value, name]
                        }}
                    />
                    <Area yAxisId="left" type="monotone" dataKey="OS Criadas" stroke="#059669" fill="url(#colorCount)" strokeWidth={2} />
                    <Area yAxisId="right" type="monotone" dataKey="Receita" stroke="#14b8a6" fill="url(#colorRevenue)" strokeWidth={2} />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    )
}

export function WorkOrderDashboardPage() {
    const navigate = useNavigate()
    const { hasPermission } = useAuthStore()
    const canView = hasPermission('os.work_order.view')

    const defaultFrom = useMemo(() => {
        const d = new Date(); d.setDate(d.getDate() - 30)
        return d.toISOString().slice(0, 10)
    }, [])
    const defaultTo = useMemo(() => new Date().toISOString().slice(0, 10), [])

    const [dateFrom, setDateFrom] = useState(defaultFrom)
    const [dateTo, setDateTo] = useState(defaultTo)

    const { data: statsRes, isLoading, isError, refetch, isFetching } = useQuery({
        queryKey: ['work-orders-dashboard-stats', dateFrom, dateTo],
        queryFn: () => api.get(`/work-orders-dashboard-stats?from=${dateFrom}&to=${dateTo}`).then((r) => unwrapData<WorkOrderDashboardStats>(r)),
        enabled: canView,
    })

    const { data: recentRes } = useQuery({
        queryKey: ['work-orders-recent-dashboard'],
        queryFn: () => api.get('/work-orders?per_page=10').then((r) => safeArray<WorkOrder>(unwrapData(r))),
        enabled: canView,
    })

    const stats = statsRes ?? {}
    const statusCounts = stats.status_counts ?? {}
    const serviceTypeCounts = stats.service_type_counts ?? {}
    const topCustomers = stats.top_customers ?? []
    const dailyTrend = stats.daily_trend ?? []
    const recent = recentRes ?? []

    const terminalStatuses = new Set(['completed', 'delivered', 'invoiced', 'cancelled'])
    const activeCount = Object.entries(statusCounts)
        .filter(([status, count]) => !terminalStatuses.has(status) && Number(count) > 0)
        .reduce((total, [, count]) => total + Number(count), 0)
    const completedCount = (statusCounts.completed || 0) + (statusCounts.delivered || 0) + (statusCounts.invoiced || 0)

    if (!canView) {
        return (
            <div className="space-y-4">
                <PageHeader
                    title="Dashboard de Ordens de Servico"
                    subtitle="Visao geral e metricas operacionais"
                    backTo="/os"
                />
                <div className="rounded-xl border border-default bg-surface-0 p-6 text-sm text-surface-600 shadow-card">
                    Voce nao possui permissao para visualizar o dashboard de ordens de servico.
                </div>
            </div>
        )
    }

    if (isLoading) {
        return (
            <div className="space-y-6 animate-pulse">
                <div className="h-8 bg-surface-200 rounded w-64" />
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {[1, 2, 3, 4].map((i) => <div key={i} className="h-24 bg-surface-200 rounded-xl" />)}
                </div>
            </div>
        )
    }

    if (isError) {
        return (
            <div className="space-y-4">
                <PageHeader
                    title="Dashboard de Ordens de Servico"
                    subtitle="Visao geral e metricas operacionais"
                    backTo="/os"
                />
                <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                    <p className="text-sm text-surface-600">Erro ao carregar o dashboard de ordens de servico.</p>
                    <Button className="mt-3" variant="outline" onClick={() => void refetch()} disabled={isFetching}>
                        Tentar novamente
                    </Button>
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight flex items-center gap-2">
                        <BarChart3 className="w-5 h-5 text-brand-500" />
                        Dashboard de Ordens de Servico
                    </h1>
                    <p className="mt-0.5 text-sm text-surface-500">
                        Visao geral e metricas operacionais
                    </p>
                </div>
                <div className="flex items-center gap-2 text-sm">
                    <Calendar className="h-4 w-4 text-surface-400" />
                    <Input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} className="w-36 h-8 text-xs" aria-label="Data inicial" />
                    <span className="text-surface-400">até</span>
                    <Input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} className="w-36 h-8 text-xs" aria-label="Data final" />
                </div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <KpiCard icon={<Wrench className="h-5 w-5 text-brand-500" />} label="Total de OS" value={stats.total_orders ?? 0} />
                <KpiCard
                    icon={<Clock className="h-5 w-5 text-amber-500" />}
                    label="Ativas"
                    value={activeCount}
                    color="text-amber-600"
                    sub="Abertas e em andamento"
                />
                <KpiCard
                    icon={<CheckCircle className="h-5 w-5 text-emerald-500" />}
                    label="Concluidas"
                    value={completedCount}
                    color="text-emerald-600"
                />
                <KpiCard
                    icon={<DollarSign className="h-5 w-5 text-teal-500" />}
                    label="Receita do Mes"
                    value={`R$ ${Number(stats.month_revenue || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`}
                    color="text-teal-600"
                />
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <KpiCard
                    icon={<TrendingUp className="h-5 w-5 text-blue-500" />}
                    label="Tempo Medio (h)"
                    value={stats.avg_completion_hours ?? '-'}
                    color="text-blue-600"
                    sub="Media de conclusao"
                />
                <KpiCard
                    icon={<Shield className="h-5 w-5 text-emerald-500" />}
                    label="SLA Compliance"
                    value={`${stats.sla_compliance ?? 100}%`}
                    color={Number(stats.sla_compliance ?? 100) >= 90 ? 'text-emerald-600' : 'text-amber-600'}
                />
                <KpiCard
                    icon={<Timer className="h-5 w-5 text-red-500" />}
                    label="O.S. Atrasadas"
                    value={stats.overdue_orders || 0}
                    color={Number(stats.overdue_orders || 0) > 0 ? 'text-red-600' : 'text-surface-900'}
                    sub="SLA vencido"
                />
                <KpiCard
                    icon={<AlertTriangle className="h-5 w-5 text-red-500" />}
                    label="Canceladas"
                    value={statusCounts.cancelled || 0}
                    color="text-red-600"
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                    <div className="border-b border-subtle px-5 py-3">
                        <h2 className="text-sm font-bold text-surface-900">Distribuicao por Status</h2>
                    </div>
                    <StatusBarChart statusCounts={statusCounts} />
                </div>

                <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                    <div className="border-b border-subtle px-5 py-3">
                        <h2 className="text-sm font-bold text-surface-900 flex items-center gap-2">
                            <TrendingUp className="w-4 h-4" /> Tendência Diária
                        </h2>
                    </div>
                    <TrendChart data={dailyTrend} />
                </div>

                <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                    <div className="border-b border-subtle px-5 py-3">
                        <h2 className="text-sm font-bold text-surface-900 flex items-center gap-2">
                            <Users className="w-4 h-4" /> Top 5 Clientes
                        </h2>
                    </div>
                    {topCustomers.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-surface-400 text-center">Nenhum cliente com OS</p>
                    ) : (
                        <div className="divide-y divide-subtle">
                            {topCustomers.map((customer, index) => (
                                <div key={`${customer.name}-${index}`} className="flex items-center justify-between px-5 py-3">
                                    <div>
                                        <p className="text-sm font-medium text-surface-900">{customer.name}</p>
                                        <p className="text-xs text-surface-500">{customer.total_os} OS</p>
                                    </div>
                                    <span className="text-sm font-bold text-teal-600">
                                        R$ {Number(customer.revenue || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-default bg-surface-0 shadow-card lg:col-span-2">
                    <div className="border-b border-subtle px-5 py-3">
                        <h2 className="text-sm font-bold text-surface-900 flex items-center gap-2">
                            <Activity className="w-4 h-4" /> Tipos de Serviço mais executados
                        </h2>
                    </div>
                    {Object.keys(serviceTypeCounts).length === 0 ? (
                        <p className="px-5 py-6 text-sm text-surface-400 text-center">Nenhum serviço registrado</p>
                    ) : (
                        <div className="p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                            {Object.entries(serviceTypeCounts)
                                .sort(([, a], [, b]) => Number(b) - Number(a))
                                .map(([type, count]) => (
                                    <div key={type} className="flex justify-between items-center p-3 border border-subtle rounded-lg bg-surface-50">
                                        <span className="text-sm font-medium text-surface-700">{type === 'preventive' ? 'Preventiva' : type === 'corrective' ? 'Corretiva' : type === 'installation' ? 'Instalação' : type}</span>
                                        <span className="text-sm font-bold text-brand-600">{Number(count)}</span>
                                    </div>
                                ))}
                        </div>
                    )}
                </div>
            </div>

            <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                <div className="border-b border-subtle px-5 py-3">
                    <h2 className="text-sm font-bold text-surface-900">Ultimas OS Criadas</h2>
                </div>
                {recent.length === 0 ? (
                    <p className="px-5 py-8 text-sm text-surface-400 text-center">Nenhuma OS encontrada</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-surface-50 text-surface-500">
                                    <th className="px-4 py-2 text-left font-medium">Numero</th>
                                    <th className="px-4 py-2 text-left font-medium">Cliente</th>
                                    <th className="px-4 py-2 text-left font-medium">Status</th>
                                    <th className="px-4 py-2 text-left font-medium">Criada em</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {recent.map((wo) => (
                                    <tr
                                        key={wo.id}
                                        className="hover:bg-surface-50/50 cursor-pointer"
                                        onClick={() => navigate(`/os/${wo.id}`)}
                                    >
                                        <td className="px-4 py-2 font-mono text-brand-600">
                                            {wo.business_number ?? wo.os_number ?? wo.number ?? `#${wo.id}`}
                                        </td>
                                        <td className="px-4 py-2">{wo.customer?.name ?? '-'}</td>
                                        <td className="px-4 py-2">
                                            <span className={cn('inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusColors[wo.status] || 'bg-surface-100 text-surface-700')}>
                                                {statusLabels[wo.status] || wo.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 text-surface-500">
                                            {wo.created_at ? new Date(wo.created_at).toLocaleDateString('pt-BR') : '-'}
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

export default WorkOrderDashboardPage
