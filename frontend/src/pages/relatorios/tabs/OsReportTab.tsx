import {
    BarChart, Bar, XAxis, YAxis, Tooltip,
    ResponsiveContainer, CartesianGrid,
} from 'recharts'
import { ClipboardList, CheckCircle2, Clock, DollarSign } from 'lucide-react'
import { formatCurrency } from '@/lib/utils'
import { KpiCardSpark } from '@/components/charts/KpiCardSpark'
import { ChartCard } from '@/components/charts/ChartCard'
import { DonutChart } from '@/components/charts/DonutChart'
import { TrendAreaChart } from '@/components/charts/TrendAreaChart'

const statusLabels: Record<string, string> = {
    open: 'Aberta', in_progress: 'Em Andamento', waiting_parts: 'Aguardando Peças',
    waiting_approval: 'Aguard. Aprovação', completed: 'Concluída', delivered: 'Entregue',
    cancelled: 'Cancelada',
}
const statusColors: Record<string, string> = {
    open: '#059669', in_progress: '#f59e0b', waiting_parts: '#06b6d4',
    waiting_approval: '#0d9488', completed: '#22c55e', delivered: '#14b8a6',
    cancelled: '#ef4444',
}
const priorityLabels: Record<string, string> = { low: 'Baixa', normal: 'Normal', high: 'Alta', urgent: 'Urgente' }
const priorityColors: Record<string, string> = { low: '#06b6d4', normal: '#059669', high: '#f59e0b', urgent: '#ef4444' }

interface StatusRow { status: string; count: number; total?: number }
interface PriorityRow { priority: string; count: number }
interface MonthlyRow { period: string; count: number; total: number }
interface PriorityChartItem { name: string; count: number; fill: string }

interface OsReportData {
    by_status?: StatusRow[]
    by_priority?: PriorityRow[]
    monthly?: MonthlyRow[]
    avg_completion_hours?: number
}

interface Props { data: OsReportData }

export function OsReportTab({ data }: Props) {
    const statusData = (data.by_status ?? []).map((s: StatusRow) => ({
        name: statusLabels[s.status] ?? s.status,
        value: Number(s.count),
        color: statusColors[s.status],
    }))
    const totalCount = statusData.reduce((s: number, d: { value: number }) => s + d.value, 0)
    const totalValue = (data.by_status ?? []).reduce((s: number, d: StatusRow) => s + Number(d.total ?? 0), 0)
    const completedCount = (data.by_status ?? []).find((s: StatusRow) => s.status === 'completed')?.count ?? 0

    const priorityData = (data.by_priority ?? []).map((p: PriorityRow) => ({
        name: priorityLabels[p.priority] ?? p.priority,
        count: Number(p.count),
        fill: priorityColors[p.priority] ?? '#64748b',
    }))

    const monthlyData = (data.monthly ?? []).map((m: MonthlyRow) => ({
        period: m.period,
        count: Number(m.count),
        total: Number(m.total),
    }))

    const sparkMonthly = (monthlyData || []).map((m: { count: number }) => m.count)

    return (
        <div className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCardSpark
                    label="Total OS"
                    value={totalCount}
                    icon={<ClipboardList className="h-4 w-4" />}
                    sparkData={sparkMonthly}
                    sparkColor="#059669"
                />
                <KpiCardSpark
                    label="Concluídas"
                    value={completedCount}
                    icon={<CheckCircle2 className="h-4 w-4" />}
                    sparkColor="#22c55e"
                />
                <KpiCardSpark
                    label="Ticket Médio"
                    value={totalCount > 0 ? formatCurrency(totalValue / totalCount) : 'R$ 0'}
                    icon={<DollarSign className="h-4 w-4" />}
                    sparkColor="#f59e0b"
                />
                <KpiCardSpark
                    label="Tempo Médio"
                    value={`${data.avg_completion_hours ?? 0}h`}
                    icon={<Clock className="h-4 w-4" />}
                    sparkColor="#06b6d4"
                />
            </div>

            {monthlyData.length > 0 && (
                <ChartCard title="Evolução Mensal" icon={<ClipboardList className="h-4 w-4" />}>
                    <TrendAreaChart
                        data={monthlyData}
                        xKey="period"
                        series={[
                            { key: 'count', label: 'Quantidade', color: '#059669' },
                            { key: 'total', label: 'Valor (R$)', color: '#22c55e' },
                        ]}
                        formatValue={formatCurrency}
                        yTickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : String(v)}
                        height="100%"
                    />
                </ChartCard>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <ChartCard title="Distribuição por Status" height={260}>
                    <DonutChart
                        data={statusData}
                        centerValue={totalCount}
                        centerLabel="Total"
                        height={220}
                        formatValue={(v) => String(v)}
                    />
                </ChartCard>

                <ChartCard title="Por Prioridade" height={260}>
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={priorityData} layout="vertical" margin={{ left: 10, right: 20 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-surface-200" />
                            <XAxis type="number" tick={{ fontSize: 11 }} />
                            <YAxis type="category" dataKey="name" width={70} tick={{ fontSize: 12 }} />
                            <Tooltip />
                            <Bar dataKey="count" name="Quantidade" radius={[0, 4, 4, 0]} animationDuration={800}>
                                {(priorityData || []).map((entry: PriorityChartItem) => (
                                    <rect key={entry.name} fill={entry.fill} />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </ChartCard>
            </div>
        </div>
    )
}
