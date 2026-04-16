import {
    BarChart, Bar, XAxis, YAxis, Tooltip,
    ResponsiveContainer, CartesianGrid, Legend,
} from 'recharts'
import { FileText, CheckCircle2, Percent, Users, XCircle, Clock, DollarSign } from 'lucide-react'
import { formatCurrency } from '@/lib/utils'
import { KpiCardSpark } from '@/components/charts/KpiCardSpark'
import { ChartCard } from '@/components/charts/ChartCard'
import { FunnelChart } from '@/components/charts/FunnelChart'

interface QuotesReportStatusItem {
    status: string
    count: number
    total?: number
}

interface QuotesReportSellerItem {
    id: number
    name: string
    count: number
    total: number
}

interface QuotesReportPayload {
    total?: number
    approved?: number
    conversion_rate?: number
    total_value?: number
    by_status?: QuotesReportStatusItem[]
    by_seller?: QuotesReportSellerItem[]
}

interface SellerChartItem {
    name: string
    count: number
    total: number
}

interface Props {
    data: QuotesReportPayload
}

function formatSellerTooltip(value: number | string | undefined, name: string | undefined): [string | number, string] {
    const safeName = name ?? ''

    if (safeName === 'Valor') {
        return [formatCurrency(Number(value ?? 0)), safeName]
    }

    return [value ?? 0, safeName]
}

export function QuotesReportTab({ data }: Props) {
    const total = data.total ?? 0
    const approved = data.approved ?? 0
    const conversionRate = data.conversion_rate ?? 0
    const byStatus = data.by_status ?? []
    const bySeller: SellerChartItem[] = (data.by_seller ?? [])
        .map((seller) => ({
            name: seller.name,
            count: Number(seller.count),
            total: Number(seller.total ?? 0),
        }))
        .sort((a, b) => b.count - a.count)

    const getStatusCount = (status: string) => byStatus.find((item) => item.status === status)?.count ?? 0
    const getStatusTotal = (status: string) => byStatus.find((item) => item.status === status)?.total ?? 0
    const rejected = getStatusCount('rejected')
    const expired = getStatusCount('expired')

    // Ticket médio: valor total dos aprovados / quantidade de aprovados
    const approvedTotal = getStatusTotal('approved') + getStatusTotal('invoiced')
    const approvedCount = getStatusCount('approved') + getStatusCount('invoiced')
    const averageTicket = approvedCount > 0 ? approvedTotal / approvedCount : 0

    const funnelData = [
        { name: 'Criados', value: total },
        { name: 'Enviados', value: getStatusCount('sent') + getStatusCount('approved') + getStatusCount('invoiced') },
        { name: 'Aprovados', value: getStatusCount('approved') + getStatusCount('invoiced') },
        { name: 'Faturados', value: getStatusCount('invoiced') },
        { name: 'Rejeitados', value: rejected },
        { name: 'Expirados', value: expired },
    ].filter((item) => item.value > 0)

    return (
        <div className="space-y-5">
            {/* KPIs principais */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCardSpark
                    label="Total"
                    value={total}
                    icon={<FileText className="h-4 w-4" />}
                    sparkColor="#059669"
                />
                <KpiCardSpark
                    label="Aprovados"
                    value={approved}
                    icon={<CheckCircle2 className="h-4 w-4" />}
                    sparkColor="#22c55e"
                    valueClassName="text-emerald-600"
                />
                <KpiCardSpark
                    label="Conversao"
                    value={`${conversionRate}%`}
                    icon={<Percent className="h-4 w-4" />}
                    sparkColor={conversionRate >= 50 ? '#22c55e' : '#f59e0b'}
                />
                <KpiCardSpark
                    label="Ticket Medio"
                    value={formatCurrency(averageTicket)}
                    icon={<DollarSign className="h-4 w-4" />}
                    sparkColor="#0d9488"
                />
            </div>

            {/* KPIs secundários */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCardSpark
                    label="Rejeitados"
                    value={rejected}
                    icon={<XCircle className="h-4 w-4" />}
                    sparkColor="#ef4444"
                    valueClassName="text-red-600"
                />
                <KpiCardSpark
                    label="Expirados"
                    value={expired}
                    icon={<Clock className="h-4 w-4" />}
                    sparkColor="#f59e0b"
                    valueClassName="text-amber-600"
                />
                <KpiCardSpark
                    label="Vendedores"
                    value={bySeller.length}
                    icon={<Users className="h-4 w-4" />}
                    sparkColor="#06b6d4"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {funnelData.length > 0 && (
                    <ChartCard title="Funil de Conversao" icon={<FileText className="h-4 w-4" />}>
                        <FunnelChart data={funnelData} height="100%" />
                    </ChartCard>
                )}

                {bySeller.length > 0 && (
                    <ChartCard title="Por Vendedor" icon={<Users className="h-4 w-4" />}>
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={bySeller} layout="vertical" margin={{ left: 10, right: 30 }}>
                                <CartesianGrid strokeDasharray="3 3" className="stroke-surface-200" />
                                <XAxis type="number" tick={{ fontSize: 11 }} />
                                <YAxis type="category" dataKey="name" width={100} tick={{ fontSize: 12 }} />
                                <Tooltip formatter={formatSellerTooltip} />
                                <Legend />
                                <Bar dataKey="count" name="Quantidade" fill="#059669" radius={[0, 4, 4, 0]} animationDuration={800} />
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>
                )}
            </div>
        </div>
    )
}
