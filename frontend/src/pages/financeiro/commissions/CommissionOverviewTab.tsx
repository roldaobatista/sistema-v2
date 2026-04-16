import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { TrendingUp, CheckCircle, AlertCircle, Target, Wallet } from 'lucide-react'
import {
    BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid,
    PieChart, Pie, Cell, Legend
} from 'recharts'
import { financialApi } from '@/lib/financial-api'
import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input'
import type { OverviewData, EvolutionData, RankingEntry, ByRuleEntry, ByRoleEntry } from './types'
import { fmtBRL, getCommissionRoleLabel } from './utils'

/** Type-safe API response unwrap: handles {data:{data:T}}, {data:T}, T */
function safeUnwrap<T>(res: unknown, fallback: T): T {
    const r = res as Record<string, unknown> | undefined
    const d = r?.data as Record<string, unknown> | T | undefined
    if (d && typeof d === 'object' && 'data' in d) return (d as Record<string, unknown>).data as T
    if (d !== undefined && d !== null) return d as T
    return (r as T) ?? fallback
}

const CHART_COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#0d9488', '#ec4899', '#06b6d4', '#84cc16']

interface CommissionOverviewProps {
    onNavigateTab?: (tab: string, filter?: Record<string, string>) => void
}

export function CommissionOverviewTab({ onNavigateTab }: CommissionOverviewProps) {
    const { data: overviewRes } = useQuery({ queryKey: ['commission-overview'], queryFn: () => financialApi.commissions.dashboard.overview() })
    const overview: OverviewData = safeUnwrap<OverviewData>(overviewRes, {} as OverviewData)

    const [rankPeriod, setRankPeriod] = useState(() => new Date().toISOString().slice(0, 7))
    const { data: rankRes } = useQuery({ queryKey: ['commission-ranking', rankPeriod], queryFn: () => financialApi.commissions.dashboard.ranking({ period: rankPeriod }) })
    const ranking: RankingEntry[] = safeUnwrap<RankingEntry[]>(rankRes, [])

    const { data: evoRes } = useQuery({ queryKey: ['commission-evolution'], queryFn: () => financialApi.commissions.dashboard.evolution({ months: 6 }) })
    const evolution: EvolutionData[] = safeUnwrap<EvolutionData[]>(evoRes, [])

    const { data: byRuleRes } = useQuery({ queryKey: ['commission-by-rule'], queryFn: () => financialApi.commissions.dashboard.byRule() })
    const byRuleData: ByRuleEntry[] = safeUnwrap<ByRuleEntry[]>(byRuleRes, [])

    const { data: byRoleRes } = useQuery({ queryKey: ['commission-by-role'], queryFn: () => financialApi.commissions.dashboard.byRole() })
    const byRoleData: ByRoleEntry[] = safeUnwrap<ByRoleEntry[]>(byRoleRes, [])

    const pieByRule = (byRuleData || []).map((r, i) => ({
        name: r.calculation_type?.replace(/_/g, ' ') ?? 'N/A',
        value: Number(r.total),
        count: r.count,
        fill: CHART_COLORS[i % CHART_COLORS.length]
    }))

    const pieByRole = (byRoleData || []).map((r, i) => ({
        name: getCommissionRoleLabel(r.role),
        value: Number(r.total),
        count: r.count,
        fill: CHART_COLORS[(i + 3) % CHART_COLORS.length]
    }))

    const customTooltip = ({
        active,
        payload,
    }: {
        active?: boolean
        payload?: Array<{ name?: string; value?: number | string; payload?: { label?: string; period?: string } }>
    }) => {
        if (!active || !payload?.length) return null
        const d = payload[0]
        return (
            <div className='bg-surface-0 border border-default rounded-lg shadow-lg px-3 py-2 text-xs'>
                <p className='font-semibold text-surface-900'>{d.name ?? d.payload?.label ?? d.payload?.period}</p>
                <p className='text-emerald-600 font-medium'>{fmtBRL(Number(d.value ?? 0))}</p>
            </div>
        )
    }

    const pieLabelRenderer = ({ name, percent }: { name?: string; percent?: number }) =>
        `${name ?? 'N/A'} (${((percent ?? 0) * 100).toFixed(0)}%)`

    return (
        <div className='space-y-6'>
            {/* KPI Cards — Clickable */}
            <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
                <button type='button' className='rounded-xl border border-default bg-surface-0 p-6 shadow-card text-left transition-shadow hover:shadow-md'
                    onClick={() => onNavigateTab?.('settlements')}>
                    <div className='flex items-center gap-4'>
                        <div className='h-12 w-12 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600'>
                            <TrendingUp className='h-6 w-6' />
                        </div>
                        <div>
                            <p className='text-sm font-medium text-surface-500'>Pago (Mês)</p>
                            <h3 className='text-2xl font-bold text-surface-900'>{fmtBRL(overview.paid_this_month ?? 0)}</h3>
                            {overview.variation_pct !== null && overview.variation_pct !== undefined && (
                                <span className={cn('text-xs font-medium', overview.variation_pct >= 0 ? 'text-emerald-600' : 'text-red-600')}>
                                    {overview.variation_pct >= 0 ? '+' : ''}{overview.variation_pct}% vs mês anterior
                                </span>
                            )}
                        </div>
                    </div>
                </button>
                <button type='button' className='rounded-xl border border-default bg-surface-0 p-6 shadow-card text-left transition-shadow hover:shadow-md'
                    onClick={() => onNavigateTab?.('events', { status: 'pending' })}>
                    <div className='flex items-center gap-4'>
                        <div className='h-12 w-12 rounded-full bg-amber-100 flex items-center justify-center text-amber-600'>
                            <AlertCircle className='h-6 w-6' />
                        </div>
                        <div>
                            <p className='text-sm font-medium text-surface-500'>Pendente</p>
                            <h3 className='text-2xl font-bold text-surface-900'>{fmtBRL(overview.pending ?? 0)}</h3>
                        </div>
                    </div>
                </button>
                <button type='button' className='rounded-xl border border-default bg-surface-0 p-6 shadow-card text-left transition-shadow hover:shadow-md'
                    onClick={() => onNavigateTab?.('events', { status: 'approved' })}>
                    <div className='flex items-center gap-4'>
                        <div className='h-12 w-12 rounded-full bg-sky-100 flex items-center justify-center text-sky-600'>
                            <CheckCircle className='h-6 w-6' />
                        </div>
                        <div>
                            <p className='text-sm font-medium text-surface-500'>Aprovado</p>
                            <h3 className='text-2xl font-bold text-surface-900'>{fmtBRL(overview.approved ?? 0)}</h3>
                        </div>
                    </div>
                </button>
                <button type='button' className='rounded-xl border border-default bg-surface-0 p-6 shadow-card text-left transition-shadow hover:shadow-md'
                    onClick={() => onNavigateTab?.('events')}>
                    <div className='flex items-center gap-4'>
                        <div className='h-12 w-12 rounded-full bg-cyan-100 flex items-center justify-center text-cyan-600'>
                            <Target className='h-6 w-6' />
                        </div>
                        <div>
                            <p className='text-sm font-medium text-surface-500'>Eventos no Mês</p>
                            <h3 className='text-2xl font-bold text-surface-900'>{overview.events_count ?? 0}</h3>
                        </div>
                    </div>
                </button>
            </div>

            {/* Charts Row */}
            <div className='grid gap-4 lg:grid-cols-2'>
                {/* Evolution Chart — Recharts BarChart */}
                <div className='rounded-xl border border-default bg-surface-0 p-6 shadow-card'>
                    <h3 className='font-semibold text-surface-900 mb-4'>Evolução Mensal</h3>
                    {evolution.length === 0 ? (
                        <div className='text-center py-8'><TrendingUp className='h-8 w-8 mx-auto text-surface-300 mb-2' /><p className='text-surface-500'>Sem dados de evolução.</p></div>
                    ) : (
                        <ResponsiveContainer width='100%' height={220}>
                            <BarChart data={evolution} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                                <CartesianGrid strokeDasharray='3 3' stroke='var(--color-surface-100, #f1f5f9)' />
                                <XAxis dataKey={d => d.label ?? d.period} tick={{ fontSize: 11, fill: 'var(--color-surface-500, #64748b)' }} />
                                <YAxis tickFormatter={(v: number) => fmtBRL(v)} tick={{ fontSize: 10, fill: 'var(--color-surface-500, #64748b)' }} width={80} />
                                <Tooltip content={customTooltip} />
                                <Bar dataKey='total' fill='#3b82f6' radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                {/* Ranking */}
                <div className='rounded-xl border border-default bg-surface-0 p-6 shadow-card'>
                    <div className='flex justify-between items-center mb-4'>
                        <h3 className='font-semibold text-surface-900'>Ranking — Top 10</h3>
                        <Input type='month' value={rankPeriod} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setRankPeriod(e.target.value)} className='h-8 text-xs w-36' />
                    </div>
                    {ranking.length === 0 ? (
                        <div className='text-center py-8'><Target className='h-8 w-8 mx-auto text-surface-300 mb-2' /><p className='text-surface-500'>Sem dados de ranking.</p></div>
                    ) : (
                        <div className='space-y-2 max-h-48 overflow-y-auto'>
                            {(ranking || []).map(r => (
                                <div key={r.id} className='flex items-center gap-3 text-sm'>
                                    <span className='w-6 text-center font-bold text-surface-400'>{r.medal ?? r.position}</span>
                                    <span className='flex-1 font-medium text-surface-900 truncate'>{r.name}</span>
                                    <span className='text-xs text-surface-500'>{r.events_count} ev.</span>
                                    <span className='font-semibold text-emerald-600 tabular-nums'>{fmtBRL(r.total)}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Distribution Row — Recharts PieCharts */}
            <div className='grid gap-4 lg:grid-cols-2'>
                {/* By Rule — PieChart */}
                <div className='rounded-xl border border-default bg-surface-0 p-6 shadow-card'>
                    <h3 className='font-semibold text-surface-900 mb-4'>Distribuição por Tipo de Cálculo</h3>
                    {pieByRule.length === 0 ? (
                        <div className='text-center py-8'><Wallet className='h-8 w-8 mx-auto text-surface-300 mb-2' /><p className='text-surface-500'>Sem dados.</p></div>
                    ) : (
                        <ResponsiveContainer width='100%' height={220}>
                            <PieChart>
                                <Pie data={pieByRule} dataKey='value' nameKey='name' cx='50%' cy='50%' outerRadius={80} label={pieLabelRenderer} labelLine={false}>
                                    {(pieByRule || []).map((entry, idx) => (
                                        <Cell key={idx} fill={entry.fill} />
                                    ))}
                                </Pie>
                                <Tooltip content={customTooltip} />
                                <Legend verticalAlign='bottom' height={36} wrapperStyle={{ fontSize: 11 }} />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </div>

                {/* By Role — PieChart */}
                <div className='rounded-xl border border-default bg-surface-0 p-6 shadow-card'>
                    <h3 className='font-semibold text-surface-900 mb-4'>Distribuição por Papel</h3>
                    {pieByRole.length === 0 ? (
                        <div className='text-center py-8'><Wallet className='h-8 w-8 mx-auto text-surface-300 mb-2' /><p className='text-surface-500'>Sem dados.</p></div>
                    ) : (
                        <ResponsiveContainer width='100%' height={220}>
                            <PieChart>
                                <Pie data={pieByRole} dataKey='value' nameKey='name' cx='50%' cy='50%' outerRadius={80} label={pieLabelRenderer} labelLine={false}>
                                    {(pieByRole || []).map((entry, idx) => (
                                        <Cell key={idx} fill={entry.fill} />
                                    ))}
                                </Pie>
                                <Tooltip content={customTooltip} />
                                <Legend verticalAlign='bottom' height={36} wrapperStyle={{ fontSize: 11 }} />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </div>
            </div>
        </div>
    )
}
