import { cn } from '@/lib/utils'
import { TrendingUp, TrendingDown, DollarSign } from 'lucide-react'

interface ProfitData {
    revenue: number
    costs: number
    profit: number
    margin_pct: number
    breakdown: {
        items_cost: number
        displacement: number
        commission: number
    }
}

interface OsProfitBarProps {
    data: ProfitData | null
    compact?: boolean
}

function fmtMoney(v: number) {
    return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export default function OsProfitBar({ data, compact = false }: OsProfitBarProps) {
    if (!data) return null

    const isProfit = data.profit >= 0
    const barWidth = Math.min(Math.abs(data.margin_pct), 100)

    if (compact) {
        return (
            <div className="flex items-center gap-2 text-xs">
                <DollarSign className="h-3.5 w-3.5 text-surface-400" />
                <span className={cn('font-semibold', isProfit ? 'text-emerald-600' : 'text-red-600')}>
                    {fmtMoney(data.profit)}
                </span>
                <span className="text-surface-400">({data.margin_pct}%)</span>
            </div>
        )
    }

    return (
        <div className="rounded-xl border border-default bg-surface-0 p-3 space-y-2">
            <div className="flex items-center justify-between">
                <h4 className="text-xs font-semibold text-surface-600 flex items-center gap-1.5">
                    <DollarSign className="h-3.5 w-3.5" /> DRE da OS
                </h4>
                <div className={cn(
                    'flex items-center gap-1 text-xs font-bold',
                    isProfit ? 'text-emerald-600' : 'text-red-600'
                )}>
                    {isProfit ? <TrendingUp className="h-3.5 w-3.5" /> : <TrendingDown className="h-3.5 w-3.5" />}
                    {fmtMoney(data.profit)} ({data.margin_pct}%)
                </div>
            </div>

            {/* Profit bar */}
            <div className="h-2 bg-surface-100 rounded-full overflow-hidden">
                <div
                    className={cn('h-full rounded-full transition-all', isProfit ? 'bg-emerald-500' : 'bg-red-500')}
                    style={{ width: `${barWidth}%` }}
                />
            </div>

            {/* Breakdown */}
            <div className="grid grid-cols-4 gap-2 text-[10px]">
                <div>
                    <span className="text-surface-400 block">Receita</span>
                    <span className="font-semibold text-surface-700">{fmtMoney(data.revenue)}</span>
                </div>
                <div>
                    <span className="text-surface-400 block">Peças/Serv</span>
                    <span className="font-semibold text-red-500">-{fmtMoney(data.breakdown.items_cost)}</span>
                </div>
                <div>
                    <span className="text-surface-400 block">Deslocam.</span>
                    <span className="font-semibold text-red-500">-{fmtMoney(data.breakdown.displacement)}</span>
                </div>
                <div>
                    <span className="text-surface-400 block">Comissão</span>
                    <span className="font-semibold text-red-500">-{fmtMoney(data.breakdown.commission)}</span>
                </div>
            </div>
        </div>
    )
}
