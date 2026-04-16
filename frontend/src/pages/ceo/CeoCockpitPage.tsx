import { useQuery } from '@tanstack/react-query'
import {
    DollarSign, TrendingUp, Users, Wrench,
    AlertTriangle, CheckCircle2, Clock, BarChart3,
} from 'lucide-react'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

interface KPI {
    label: string
    value: string | number
    change?: number
    icon: React.ElementType
    color: string
}

function fmtMoney(v: number) {
    return (v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export default function CeoCockpitPage() {
    const { data: dashboard, isLoading } = useQuery({
        queryKey: ['ceo-cockpit'],
        queryFn: () => api.get('/dashboard/executive-summary').then(r => r.data),
        refetchInterval: 60_000, // Auto-refresh every 60s
    })

    const kpis: KPI[] = [
        {
            label: 'Faturamento Mensal',
            value: fmtMoney(dashboard?.monthly_revenue ?? 0),
            change: dashboard?.revenue_change_pct,
            icon: DollarSign,
            color: 'text-emerald-600 bg-emerald-50',
        },
        {
            label: 'OS Abertas',
            value: dashboard?.open_work_orders ?? 0,
            icon: Wrench,
            color: 'text-blue-600 bg-blue-50',
        },
        {
            label: 'Clientes Ativos',
            value: dashboard?.active_customers ?? 0,
            icon: Users,
            color: 'text-brand-600 bg-brand-50',
        },
        {
            label: 'Inadimplência',
            value: fmtMoney(dashboard?.overdue_amount ?? 0),
            icon: AlertTriangle,
            color: 'text-red-600 bg-red-50',
        },
        {
            label: 'Taxa de Conversão',
            value: `${dashboard?.conversion_rate ?? 0}%`,
            icon: TrendingUp,
            color: 'text-amber-600 bg-amber-50',
        },
        {
            label: 'Tempo Médio OS',
            value: `${dashboard?.avg_os_hours ?? 0}h`,
            icon: Clock,
            color: 'text-surface-600 bg-surface-100',
        },
    ]

    if (isLoading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="h-8 w-8 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" />
            </div>
        )
    }

    return (
        <div className="min-h-screen bg-surface-50 p-4 space-y-4 max-w-lg mx-auto">
            {/* Header */}
            <div className="text-center py-3">
                <h1 className="text-lg font-bold text-surface-900">Cockpit CEO</h1>
                <p className="text-xs text-surface-400">Visão executiva em tempo real</p>
            </div>

            {/* KPI Cards - Full Width, Touch Friendly */}
            <div className="space-y-2">
                {(kpis || []).map(kpi => (
                    <div
                        key={kpi.label}
                        className="flex items-center gap-3 p-4 rounded-xl border border-default bg-surface-0 active:scale-[0.98] transition-transform"
                    >
                        <div className={cn('p-2.5 rounded-xl', kpi.color)}>
                            <kpi.icon className="h-5 w-5" />
                        </div>
                        <div className="flex-1">
                            <p className="text-[11px] text-surface-400 font-medium uppercase tracking-wide">{kpi.label}</p>
                            <p className="text-xl font-bold text-surface-900">{kpi.value}</p>
                        </div>
                        {kpi.change !== undefined && (
                            <span className={cn(
                                'text-xs font-semibold px-2 py-0.5 rounded-full',
                                kpi.change >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                            )}>
                                {kpi.change >= 0 ? '+' : ''}{kpi.change}%
                            </span>
                        )}
                    </div>
                ))}
            </div>

            {/* Quick Actions - Swipe-Friendly */}
            <div className="space-y-2">
                <h2 className="text-xs font-semibold text-surface-500 px-1 flex items-center gap-1.5">
                    <BarChart3 className="h-3.5 w-3.5" /> Ações Rápidas
                </h2>
                {(dashboard?.pending_approvals ?? []).slice(0, 5).map((item: { id: number; type: string; description: string; amount?: number }) => (
                    <div
                        key={`${item.type}-${item.id}`}
                        className="flex items-center justify-between p-3 rounded-xl border border-default bg-surface-0"
                    >
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-surface-800 truncate">{item.description}</p>
                            <p className="text-[10px] text-surface-400">{item.type}</p>
                        </div>
                        {item.amount && (
                            <span className="text-xs font-semibold text-surface-600">{fmtMoney(item.amount)}</span>
                        )}
                        <button className="ml-2 px-3 py-1.5 rounded-lg bg-emerald-500 text-white text-xs font-semibold active:bg-emerald-600">
                            <CheckCircle2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                ))}
                {(dashboard?.pending_approvals ?? []).length === 0 && (
                    <div className="text-center py-6 text-surface-400">
                        <CheckCircle2 className="h-6 w-6 mx-auto mb-1 opacity-40" />
                        <p className="text-xs">Nenhuma aprovação pendente</p>
                    </div>
                )}
            </div>
        </div>
    )
}
