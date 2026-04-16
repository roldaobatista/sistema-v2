import { DollarSign, TrendingUp, TrendingDown, AlertTriangle } from 'lucide-react'
import { formatCurrency } from '@/lib/utils'
import { KpiCardSpark } from '@/components/charts/KpiCardSpark'
import { ChartCard } from '@/components/charts/ChartCard'
import { DonutChart } from '@/components/charts/DonutChart'
import { TrendAreaChart } from '@/components/charts/TrendAreaChart'

interface ReceivableData { total?: number; total_paid?: number; overdue?: number }
interface PayableData { total?: number }
interface ExpenseRow { category?: string; total?: number }
interface MonthlyFlowRow { period: string; income?: number; expense?: number; balance?: number }
interface MonthlyFlowItem { period: string; receita: number; despesa: number; saldo: number }

interface FinancialReportData {
    receivable?: ReceivableData
    payable?: PayableData
    expenses_by_category?: ExpenseRow[]
    monthly_flow?: MonthlyFlowRow[]
}

interface Props { data: FinancialReportData }

export function FinancialReportTab({ data }: Props) {
    const ar: ReceivableData = data.receivable ?? {}
    const ap: PayableData = data.payable ?? {}

    const expenseData = (data.expenses_by_category ?? []).map((e: ExpenseRow) => ({
        name: e.category ?? 'Sem categoria',
        value: Number(e.total ?? 0),
    }))

    const monthlyFlow: MonthlyFlowItem[] = (data.monthly_flow ?? []).map((m: MonthlyFlowRow) => ({
        period: m.period,
        receita: Number(m.income ?? 0),
        despesa: Number(m.expense ?? 0),
        saldo: Number(m.balance ?? 0),
    }))
    const monthlyFlowChartData: Array<Record<string, string | number>> = monthlyFlow.map((item) => ({
        period: item.period,
        receita: item.receita,
        despesa: item.despesa,
        saldo: item.saldo,
    }))

    const sparkReceita = (monthlyFlow || []).map((m: MonthlyFlowItem) => m.receita)
    const sparkDespesa = (monthlyFlow || []).map((m: MonthlyFlowItem) => m.despesa)

    return (
        <div className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCardSpark
                    label="Receita"
                    value={formatCurrency(Number(ar.total ?? 0))}
                    icon={<TrendingUp className="h-4 w-4" />}
                    sparkData={sparkReceita}
                    sparkColor="#22c55e"
                    valueClassName="text-emerald-600"
                />
                <KpiCardSpark
                    label="Recebido"
                    value={formatCurrency(Number(ar.total_paid ?? 0))}
                    icon={<DollarSign className="h-4 w-4" />}
                    sparkColor="#059669"
                />
                <KpiCardSpark
                    label="Despesas (AP)"
                    value={formatCurrency(Number(ap.total ?? 0))}
                    icon={<TrendingDown className="h-4 w-4" />}
                    sparkData={sparkDespesa}
                    sparkColor="#ef4444"
                    valueClassName="text-red-600"
                />
                <KpiCardSpark
                    label="Inadimplência"
                    value={formatCurrency(Number(ar.overdue ?? 0))}
                    icon={<AlertTriangle className="h-4 w-4" />}
                    sparkColor="#f59e0b"
                    valueClassName={Number(ar.overdue ?? 0) > 0 ? 'text-amber-600' : undefined}
                />
            </div>

            {monthlyFlow.length > 0 && (
                <ChartCard title="Receita x Despesa (Mensal)" icon={<DollarSign className="h-4 w-4" />}>
                    <TrendAreaChart
                        data={monthlyFlowChartData}
                        xKey="period"
                        series={[
                            { key: 'receita', label: 'Receita', color: '#22c55e' },
                            { key: 'despesa', label: 'Despesa', color: '#ef4444' },
                            { key: 'saldo', label: 'Saldo', color: '#059669', dashed: true },
                        ]}
                        formatValue={formatCurrency}
                        yTickFormatter={(v) => `${(v / 1000).toFixed(0)}k`}
                        height="100%"
                    />
                </ChartCard>
            )}

            {expenseData.length > 0 && (
                <ChartCard title="Despesas por Categoria" height={280}>
                    <DonutChart
                        data={expenseData}
                        centerLabel="Total"
                        centerValue={formatCurrency(expenseData.reduce((s: number, d: { value: number }) => s + d.value, 0))}
                        height={240}
                        formatValue={formatCurrency}
                    />
                </ChartCard>
            )}
        </div>
    )
}
