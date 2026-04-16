import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowDownRight, ArrowUpRight, Award, BarChart3, PieChart, TrendingUp, Trophy, Users } from 'lucide-react'
import { unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { cn, formatCurrency, getApiErrorMessage } from '@/lib/utils'
import { PageHeader } from '@/components/ui/pageheader'
import type { ByRoleEntry, ByRuleEntry, EvolutionData, OverviewData, RankingEntry } from './commissions/types'
import { getCommissionRoleLabel, normalizeCommissionRole } from './commissions/utils'

const calcTypeLabels: Record<string, string> = {
    percent_gross: '% Bruto',
    percent_net: '% Liquido',
    fixed_per_os: 'Fixo por OS',
    percent_services_only: '% Servicos',
    percent_products_only: '% Produtos',
    percent_profit: '% Lucro',
    percent_gross_minus_displacement: '% Bruto sem deslocamento',
    percent_gross_minus_expenses: '% Bruto sem despesas',
    tiered_gross: 'Escalonado',
    custom_formula: 'Formula',
}

const roleColor: Record<string, string> = {
    tecnico: '#3B82F6',
    vendedor: '#10B981',
    motorista: '#F59E0B',
}

export function CommissionDashboardPage() {
    const [months, setMonths] = useState(6)
    const [period, setPeriod] = useState(() => new Date().toISOString().slice(0, 7))

    const overviewQuery = useQuery({
        queryKey: ['commission-overview', period],
        queryFn: async () => unwrapData<OverviewData>(await financialApi.commissions.dashboard.overview({ period })) ?? { paid_this_month: 0, pending: 0, approved: 0, variation_pct: null },
    })

    const rankingQuery = useQuery({
        queryKey: ['commission-ranking', period],
        queryFn: async () => unwrapData<RankingEntry[]>(await financialApi.commissions.dashboard.ranking({ period })) ?? [],
    })

    const evolutionQuery = useQuery({
        queryKey: ['commission-evolution', months],
        queryFn: async () => unwrapData<EvolutionData[]>(await financialApi.commissions.dashboard.evolution({ months })) ?? [],
    })

    const byRuleQuery = useQuery({
        queryKey: ['commission-by-rule'],
        queryFn: async () => unwrapData<ByRuleEntry[]>(await financialApi.commissions.dashboard.byRule()) ?? [],
    })

    const byRoleQuery = useQuery({
        queryKey: ['commission-by-role'],
        queryFn: async () => unwrapData<ByRoleEntry[]>(await financialApi.commissions.dashboard.byRole()) ?? [],
    })

    const isLoading = overviewQuery.isLoading || rankingQuery.isLoading || evolutionQuery.isLoading || byRuleQuery.isLoading || byRoleQuery.isLoading
    const error = overviewQuery.error ?? rankingQuery.error ?? evolutionQuery.error ?? byRuleQuery.error ?? byRoleQuery.error

    const overview = overviewQuery.data ?? { paid_this_month: 0, pending: 0, approved: 0, variation_pct: null }
    const ranking = rankingQuery.data ?? []
    const evolution = evolutionQuery.data ?? []
    const byRule = byRuleQuery.data ?? []
    const byRole = byRoleQuery.data ?? []

    const refetchAll = () => {
        overviewQuery.refetch()
        rankingQuery.refetch()
        evolutionQuery.refetch()
        byRuleQuery.refetch()
        byRoleQuery.refetch()
    }

    const maxEvolution = Math.max(...evolution.map((entry) => Number(entry.total ?? 0)), 1)
    const maxByRule = Math.max(...byRule.map((entry) => Number(entry.total ?? 0)), 1)
    const maxByRole = Math.max(...byRole.map((entry) => Number(entry.total ?? 0)), 1)

    if (isLoading) {
        return (
            <div className='space-y-5'>
                <PageHeader title='Dashboard de comissoes' subtitle='Visao analitica e KPIs de comissoes' />
                <div className='grid gap-3 sm:grid-cols-2 xl:grid-cols-4'>
                    {[1, 2, 3, 4].map(i => (
                        <div key={i} className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                            <div className='flex items-center gap-2 mb-3'>
                                <div className='h-5 w-5 rounded-md bg-surface-200 animate-pulse' />
                                <div className='h-4 w-24 rounded-md bg-surface-200 animate-pulse' />
                            </div>
                            <div className='h-8 w-32 rounded-md bg-surface-200 animate-pulse' />
                            <div className='mt-2 h-3 w-40 rounded-md bg-surface-200 animate-pulse' />
                        </div>
                    ))}
                </div>
                <div className='grid gap-4 lg:grid-cols-3'>
                    <div className='lg:col-span-2 rounded-xl border border-default bg-surface-0 p-5 shadow-card h-[280px] flex items-end justify-between gap-2 overflow-hidden'>
                        {[...Array(12)].map((_, i) => <div key={i} className='w-full bg-surface-200 animate-pulse rounded-t-md opacity-70' style={{ height: `${((i * 17) % 60) + 20}%` }} />)}
                    </div>
                    <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card h-[280px] space-y-4'>
                        <div className='h-5 w-40 rounded-md bg-surface-200 animate-pulse mb-6' />
                        {[1, 2, 3, 4].map(i => (
                            <div key={i} className='flex items-center gap-3'>
                                <div className='h-6 w-6 rounded-md bg-surface-200 animate-pulse' />
                                <div className='flex-1 space-y-2'>
                                    <div className='h-4 w-full rounded-md bg-surface-200 animate-pulse' />
                                    <div className='h-3 w-16 rounded-md bg-surface-200 animate-pulse' />
                                </div>
                                <div className='h-5 w-16 rounded-md bg-surface-200 animate-pulse' />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        )
    }

    return (
        <div className='space-y-5'>
            <div className='flex flex-wrap items-end justify-between gap-3'>
                <PageHeader title='Dashboard de comissoes' subtitle='Visao analitica e KPIs de comissoes' />
                <div className='flex gap-2 items-center'>
                    <label className='text-xs text-surface-500'>Periodo</label>
                    <input type='month' value={period} onChange={e => setPeriod(e.target.value)} className='h-8 rounded-lg border-default text-xs px-2' />
                </div>
            </div>

            {error && (
                <div className='rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700'>
                    {getApiErrorMessage(error, 'Erro ao carregar dados de comissoes.')}
                    <button onClick={refetchAll} className='underline ml-1'>Tentar novamente</button>
                </div>
            )}

            <div className='grid gap-3 sm:grid-cols-2 xl:grid-cols-4'>
                <KpiCard icon={<BarChart3 className='h-5 w-5' />} label='Pendente' value={formatCurrency(overview.pending ?? 0)} hint={`${overview.total_events ?? 0} eventos totais`} tone='amber' />
                <KpiCard icon={<Award className='h-5 w-5' />} label='Aprovado' value={formatCurrency(overview.approved ?? 0)} hint={`${overview.total_rules ?? 0} regras ativas`} tone='sky' />
                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <div className='flex items-center gap-2 text-emerald-600'><TrendingUp className='h-5 w-5' /><span className='text-xs font-semibold uppercase tracking-wider'>Pago no mes</span></div>
                    <p className='mt-3 text-2xl font-bold text-emerald-600'>{formatCurrency(overview.paid_this_month ?? 0)}</p>
                    <div className='mt-1 flex items-center gap-1 text-xs'>
                        {overview.variation_pct != null ? (
                            <>
                                {overview.variation_pct >= 0
                                    ? <><ArrowUpRight className='h-3.5 w-3.5 text-emerald-500' /><span className='text-emerald-600'>+{overview.variation_pct}%</span></>
                                    : <><ArrowDownRight className='h-3.5 w-3.5 text-red-500' /><span className='text-red-600'>{overview.variation_pct}%</span></>
                                }
                                <span className='text-surface-400'>vs. mes anterior</span>
                            </>
                        ) : (
                            <span className='text-surface-400'>Sem dados do mes anterior</span>
                        )}
                    </div>
                </div>
                <KpiCard icon={<PieChart className='h-5 w-5' />} label='Mes anterior' value={formatCurrency(overview.paid_last_month ?? 0)} tone='surface' />
            </div>

            <div className='grid gap-4 lg:grid-cols-3'>
                <div className='lg:col-span-2 rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <div className='flex items-center justify-between mb-4'>
                        <h2 className='text-sm font-semibold text-surface-900'>Evolucao mensal</h2>
                        <select aria-label='Filtrar periodo de meses' title='Filtrar periodo de meses' value={months} onChange={(event) => setMonths(Number(event.target.value))} className='rounded-lg border border-surface-300 px-2 py-1 text-xs'>
                            <option value={3}>3 meses</option>
                            <option value={6}>6 meses</option>
                            <option value={12}>12 meses</option>
                        </select>
                    </div>
                    <div className='flex items-end gap-2' style={{ height: 200 }}>
                        {evolution.map((entry, index) => {
                            const height = maxEvolution > 0 ? (Number(entry.total) / maxEvolution) * 180 : 0
                            return (
                                <div key={`${entry.period}-${index}`} className='flex flex-1 flex-col items-center gap-1' title={formatCurrency(entry.total)}>
                                    <span className='text-[10px] text-surface-500 font-medium'>{formatCurrency(entry.total).replace('R$\u00a0', '')}</span>
                                    <div className='w-full rounded-t-md bg-brand-500 transition-all hover:bg-brand-600' style={{ height: Math.max(height, 4) }} />
                                    <span className='text-[10px] text-surface-500'>{entry.label ?? entry.period}</span>
                                </div>
                            )
                        })}
                        {evolution.length === 0 && <p className='w-full text-center text-sm text-surface-400 self-center'>Sem movimentações processadas no período.</p>}
                    </div>
                </div>

                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <h2 className='mb-4 text-sm font-semibold text-surface-900 flex items-center gap-2'><Trophy className='h-4 w-4 text-amber-500' /> Ranking do mes</h2>
                    <div className='space-y-2.5'>
                        {ranking.map((entry) => (
                            <div key={entry.id} className='flex items-center gap-3'>
                                <span className='w-6 text-center text-lg'>{entry.medal || entry.position}</span>
                                <div className='flex-1 min-w-0'>
                                    <p className='text-[13px] font-medium text-surface-900 truncate'>{entry.name}</p>
                                    <p className='text-xs text-surface-500'>{entry.events_count} eventos</p>
                                </div>
                                <span className='text-sm font-bold text-surface-900'>{formatCurrency(entry.total)}</span>
                            </div>
                        ))}
                        {ranking.length === 0 && <p className='text-sm text-surface-400 text-center py-4'>Sem comissões fechadas.</p>}
                    </div>
                </div>
            </div>

            <div className='grid gap-4 lg:grid-cols-2'>
                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <h2 className='mb-4 text-sm font-semibold text-surface-900'>Por tipo de calculo</h2>
                    <div className='space-y-3'>
                        {byRule.map((entry, index) => {
                            const pct = maxByRule > 0 ? (Number(entry.total) / maxByRule) * 100 : 0
                            const colors = ['bg-brand-500', 'bg-sky-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500']
                            return (
                                <div key={`${entry.calculation_type}-${index}`}>
                                    <div className='flex justify-between text-xs mb-1'>
                                        <span className='font-medium text-surface-700'>{calcTypeLabels[entry.calculation_type] ?? entry.calculation_type}</span>
                                        <span className='text-surface-500'>{formatCurrency(entry.total)} ({entry.count})</span>
                                    </div>
                                    <div className='h-2 rounded-full bg-surface-100'>
                                        <div className={cn('h-full rounded-full transition-all', colors[index % colors.length])} style={{ width: `${Math.min(pct, 100)}%` }} />
                                    </div>
                                </div>
                            )
                        })}
                        {byRule.length === 0 && <p className='text-sm text-surface-400 text-center py-4'>Nenhuma regra gerou vales.</p>}
                    </div>
                </div>

                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <h2 className='mb-4 text-sm font-semibold text-surface-900 flex items-center gap-2'><Users className='h-4 w-4 text-brand-500' /> Por papel</h2>
                    <div className='flex items-center justify-center' style={{ height: 200 }}>
                        {byRole.length > 0 ? (
                            <div className='flex items-end gap-6'>
                                {byRole.map((entry, index) => {
                                    const height = (Number(entry.total) / maxByRole) * 160
                                    const canonicalRole = normalizeCommissionRole(entry.role) ?? entry.role
                                    const color = roleColor[canonicalRole] ?? '#6B7280'
                                    return (
                                        <div key={`${entry.role}-${index}`} className='flex flex-col items-center gap-2'>
                                            <span className='text-xs font-bold text-surface-900'>{formatCurrency(entry.total)}</span>
                                            <div className='w-14 rounded-t-lg transition-all' style={{ height: Math.max(height, 8), backgroundColor: color }} />
                                            <div className='text-center'>
                                                <p className='text-xs font-semibold' style={{ color }}>{getCommissionRoleLabel(entry.role)}</p>
                                                <p className='text-[10px] text-surface-500'>{entry.count} eventos</p>
                                            </div>
                                        </div>
                                    )
                                })}
                            </div>
                        ) : <p className='text-sm text-surface-400'>Nenhum dado financeiro processado para as categorias.</p>}
                    </div>
                </div>
            </div>
        </div>
    )
}

function KpiCard({
    icon,
    label,
    value,
    hint,
    tone,
}: {
    icon: React.ReactNode
    label: string
    value: string
    hint?: string
    tone: 'amber' | 'sky' | 'surface'
}) {
    const colorClass = {
        amber: 'text-amber-600',
        sky: 'text-sky-600',
        surface: 'text-surface-500',
    }[tone]

    return (
        <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
            <div className={`flex items-center gap-2 ${colorClass}`}>
                {icon}
                <span className='text-xs font-semibold uppercase tracking-wider'>{label}</span>
            </div>
            <p className={`mt-3 text-2xl font-bold ${tone === 'surface' ? 'text-surface-700' : colorClass}`}>{value}</p>
            {hint && <p className='mt-1 text-xs text-surface-500'>{hint}</p>}
        </div>
    )
}
