import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
    BarChart3, Trophy, Clock, Target, TrendingUp, CheckCircle2,
    ArrowLeft, Wrench, Star, Award, Flame, DollarSign, Calendar, Navigation,
    ClipboardList, Wallet, Shield, Timer, LogIn, Coins, Activity,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import api from '@/lib/api'
import { DashboardSkeleton } from '@/components/tech/TechSkeleton'
import { useAuthStore } from '@/stores/auth-store'

interface ProductivityData {
    completed_this_month?: number
    average_time_hours?: number
    nps_score?: number
    pending_count?: number
    in_progress_count?: number
    completion_rate?: number
    hours_worked_month?: number
    streak_days?: number
    weekly_completed?: Array<{ week: string; count: number }>
    completed_today?: number
    pending_today?: number
    in_progress_today?: number
    average_time_hours_week?: number
}

interface RankingEntry {
    id?: number
    position?: number
}

interface RankingData {
    position: number | null
    total_technicians: number
}

interface ClockStatusData {
    clocked_in?: boolean
    clock_in_at?: string
    on_break?: boolean
    total_hours_today?: number
}

interface CommissionSummaryData {
    total?: number
    paid?: number
    pending?: number
    total_this_month?: number
}

interface ExpenseItem {
    id: number
    amount: number
    created_at: string
}

interface CalibrationExpiring {
    id: number
    name?: string
    next_calibration_date?: string
}

function CardSkeleton({ className }: { className?: string }) {
    return (
        <div className={cn('bg-card rounded-xl p-4 animate-pulse', className)}>
            <div className="h-4 w-24 bg-surface-200 rounded mb-2" />
            <div className="h-6 w-16 bg-surface-200 rounded" />
        </div>
    )
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
}

export default function TechDashboardPage() {
    const navigate = useNavigate()
    const { user } = useAuthStore()

    // --- Core productivity data ---
    const { data: productivityData, isLoading: loadingProductivity } = useQuery({
        queryKey: ['tech-dashboard-productivity'],
        queryFn: async () => {
            const res = await api.get('/reports/productivity', { params: { my: '1' } }).catch(() => ({ data: {} }))
            return (res.data || {}) as ProductivityData
        },
        staleTime: 60_000,
    })

    // --- Ranking data ---
    const { data: rankingData, isLoading: loadingRanking } = useQuery({
        queryKey: ['tech-dashboard-ranking'],
        queryFn: async () => {
            const res = await api.get('/commission-dashboard/ranking').catch(() => ({ data: { data: [] } }))
            const rankingPayload = res.data?.data ?? res.data ?? []
            const rankingList = Array.isArray(rankingPayload) ? rankingPayload as RankingEntry[] : []
            const currentEntry = rankingList.find((entry) => entry.id === user?.id)
            return {
                position: currentEntry?.position ?? null,
                total_technicians: rankingList.length,
            } as RankingData
        },
        staleTime: 60_000,
    })

    // --- Clock status (Horas Hoje + Status do Ponto) ---
    const { data: clockStatus, isLoading: loadingClock } = useQuery({
        queryKey: ['tech-dashboard-clock-status'],
        queryFn: async () => {
            const res = await api.get('/hr/advanced/clock/status').catch(() => ({ data: {} }))
            return (res.data?.data ?? res.data ?? {}) as ClockStatusData
        },
        staleTime: 30_000,
    })

    // --- Commission summary ---
    const { data: commissionData, isLoading: loadingCommission } = useQuery({
        queryKey: ['tech-dashboard-commission'],
        queryFn: async () => {
            const res = await api.get('/my/commission-summary').catch(() => ({ data: {} }))
            return (res.data?.data ?? res.data ?? {}) as CommissionSummaryData
        },
        staleTime: 60_000,
    })

    // --- Expenses this month ---
    const { data: expensesData, isLoading: loadingExpenses } = useQuery({
        queryKey: ['tech-dashboard-expenses'],
        queryFn: async () => {
            const now = new Date()
            const from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
            const to = now.toISOString().slice(0, 10)
            const res = await api.get('/technician-cash/my-expenses', { params: { from, to } }).catch(() => ({ data: { data: [] } }))
            const items: ExpenseItem[] = res.data?.data ?? res.data ?? []
            const total = Array.isArray(items) ? items.reduce((sum, e) => sum + (Number(e.amount) || 0), 0) : 0
            const weekStart = new Date(now)
            weekStart.setDate(weekStart.getDate() - weekStart.getDay())
            const weekTotal = Array.isArray(items)
                ? items.filter(e => new Date(e.created_at.replace(' ', 'T')) >= weekStart).reduce((sum, e) => sum + (Number(e.amount) || 0), 0)
                : 0
            return { total_month: total, total_week: weekTotal, count: Array.isArray(items) ? items.length : 0 }
        },
        staleTime: 60_000,
    })

    // --- Calibrations expiring in 30 days ---
    const { data: calibrationsExpiring, isLoading: loadingCalibrations } = useQuery({
        queryKey: ['tech-dashboard-calibrations-expiring'],
        queryFn: async () => {
            const res = await api.get('/tool-calibrations/expiring').catch(() => ({ data: { data: [] } }))
            const items: CalibrationExpiring[] = res.data?.data ?? res.data ?? []
            return { count: Array.isArray(items) ? items.length : 0 }
        },
        staleTime: 60_000,
    })

    const loading = loadingProductivity && loadingRanking

    const weeklyData = useMemo(() => {
        if (!productivityData?.weekly_completed || productivityData.weekly_completed.length === 0) {
            return Array.from({ length: 4 }, (_, i) => ({ week: `Semana ${i + 1}`, count: 0 }))
        }
        return (productivityData.weekly_completed || []).slice(-4)
    }, [productivityData?.weekly_completed])

    const maxCount = Math.max(...(weeklyData || []).map(w => w.count), 1)

    const badges = useMemo(() => {
        const completed = productivityData?.completed_this_month ?? 0
        const nps = productivityData?.nps_score ?? 0
        const rate = productivityData?.completion_rate ?? 0

        return [
            { id: 'first10', emoji: '🎯', label: '10 OS no mês', earned: completed >= 10 },
            { id: 'first25', emoji: '💪', label: '25 OS no mês', earned: completed >= 25 },
            { id: 'first50', emoji: '🏆', label: '50 OS no mês', earned: completed >= 50 },
            { id: 'nps9', emoji: 'â­', label: 'NPS acima de 9', earned: nps >= 9 },
            { id: 'perfect', emoji: '💯', label: '100% conclusão', earned: rate >= 100 },
            { id: 'top3', emoji: '🥇', label: 'Top 3 ranking', earned: (rankingData?.position ?? 999) <= 3 },
        ]
    }, [productivityData, rankingData])

    // Hours today from clock status
    const hoursToday = useMemo(() => {
        if (clockStatus?.total_hours_today != null) {
            return clockStatus.total_hours_today
        }
        if (clockStatus?.clocked_in && clockStatus?.clock_in_at) {
            const start = new Date(clockStatus.clock_in_at.replace(' ', 'T')).getTime()
            // eslint-disable-next-line react-hooks/purity
            const now = Date.now()
            return (now - start) / (1000 * 60 * 60)
        }
        return 0
    }, [clockStatus])

    // Commission total for the month
    const commissionTotal = commissionData?.total_this_month ?? commissionData?.total ?? 0

    if (loading) {
        return (
            <div className="flex flex-col h-full">
                <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => navigate(-1)}
                            className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5 text-surface-600" />
                        </button>
                        <h1 className="text-lg font-bold text-foreground">
                            Dashboard
                        </h1>
                    </div>
                </div>
                <div className="flex-1 overflow-y-auto px-4 py-4">
                    <DashboardSkeleton />
                </div>
            </div>
        )
    }

    return (
        <div className="flex flex-col h-full">
            {/* Header */}
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => navigate(-1)}
                        className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5 text-surface-600" />
                    </button>
                    <h1 className="text-lg font-bold text-foreground">
                        Dashboard
                    </h1>
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                {/* Summary Cards Row */}
                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-card rounded-xl p-3">
                        <div className="flex items-center gap-2 mb-1">
                            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                            <span className="text-xs text-surface-500">OS Concluídas</span>
                        </div>
                        <p className="text-lg font-bold text-foreground">
                            {productivityData?.completed_this_month ?? 0}
                        </p>
                        <p className="text-[10px] text-surface-400">este mês</p>
                    </div>

                    <div className="bg-card rounded-xl p-3">
                        <div className="flex items-center gap-2 mb-1">
                            <Clock className="w-4 h-4 text-blue-500" />
                            <span className="text-xs text-surface-500">Tempo Médio</span>
                        </div>
                        <p className="text-lg font-bold text-foreground">
                            {productivityData?.average_time_hours ? `${productivityData.average_time_hours.toFixed(1)}h` : 'N/A'}
                        </p>
                        <p className="text-[10px] text-surface-400">por OS</p>
                    </div>

                    <div className="bg-card rounded-xl p-3">
                        <div className="flex items-center gap-2 mb-1">
                            <Star className="w-4 h-4 text-amber-500" />
                            <span className="text-xs text-surface-500">NPS Pessoal</span>
                        </div>
                        <p className="text-lg font-bold text-foreground">
                            {productivityData?.nps_score ? productivityData.nps_score.toFixed(1) : 'N/A'}
                        </p>
                        <p className="text-[10px] text-surface-400">avaliação</p>
                    </div>
                </div>

                {/* NEW: OS do Dia + Status do Ponto */}
                <div className="grid grid-cols-2 gap-3">
                    {/* OS do Dia */}
                    {loadingProductivity ? <CardSkeleton /> : (
                        <div className="bg-card rounded-xl p-4">
                            <div className="flex items-center gap-2 mb-2">
                                <ClipboardList className="w-4 h-4 text-emerald-500" />
                                <span className="text-xs font-semibold text-surface-500">OS do Dia</span>
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <span className="text-[11px] text-surface-500">Pendentes</span>
                                    <span className="text-sm font-bold text-amber-600">
                                        {productivityData?.pending_today ?? productivityData?.pending_count ?? 0}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-[11px] text-surface-500">Em andamento</span>
                                    <span className="text-sm font-bold text-blue-600">
                                        {productivityData?.in_progress_today ?? productivityData?.in_progress_count ?? 0}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-[11px] text-surface-500">Concluídas</span>
                                    <span className="text-sm font-bold text-emerald-600">
                                        {productivityData?.completed_today ?? 0}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Status do Ponto */}
                    {loadingClock ? <CardSkeleton /> : (
                        <div className={cn(
                            'rounded-xl p-4',
                            clockStatus?.clocked_in
                                ? 'bg-emerald-50 dark:bg-emerald-900/20'
                                : 'bg-card'
                        )}>
                            <div className="flex items-center gap-2 mb-2">
                                <LogIn className="w-4 h-4 text-emerald-600" />
                                <span className="text-xs font-semibold text-surface-500">Status do Ponto</span>
                            </div>
                            <p className={cn(
                                'text-lg font-bold',
                                clockStatus?.clocked_in ? 'text-emerald-700 dark:text-emerald-400' : 'text-surface-400'
                            )}>
                                {clockStatus?.clocked_in
                                    ? (clockStatus?.on_break ? 'Em Intervalo' : 'Entrada')
                                    : 'Saída'}
                            </p>
                            {clockStatus?.clock_in_at && (
                                <p className="text-[10px] text-surface-400 mt-0.5">
                                    Desde {new Date(clockStatus.clock_in_at.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {/* NEW: Horas Hoje + Comissões */}
                <div className="grid grid-cols-2 gap-3">
                    {/* Horas Hoje */}
                    {loadingClock ? <CardSkeleton /> : (
                        <div className="bg-card rounded-xl p-4">
                            <div className="flex items-center gap-2 mb-2">
                                <Timer className="w-4 h-4 text-sky-500" />
                                <span className="text-xs font-semibold text-surface-500">Horas Hoje</span>
                            </div>
                            <p className="text-xl font-bold text-foreground">
                                {hoursToday > 0 ? `${hoursToday.toFixed(1)}h` : '0h'}
                            </p>
                            <p className="text-[10px] text-surface-400">
                                {clockStatus?.clocked_in ? 'em andamento' : 'encerrado'}
                            </p>
                        </div>
                    )}

                    {/* Comissões */}
                    {loadingCommission ? <CardSkeleton /> : (
                        <div className="bg-card rounded-xl p-4">
                            <div className="flex items-center gap-2 mb-2">
                                <Coins className="w-4 h-4 text-emerald-500" />
                                <span className="text-xs font-semibold text-surface-500">Comissões</span>
                            </div>
                            <p className="text-xl font-bold text-foreground">
                                {formatCurrency(commissionTotal)}
                            </p>
                            <p className="text-[10px] text-surface-400">este mês</p>
                        </div>
                    )}
                </div>

                {/* NEW: Despesas + Próximas Calibrações */}
                <div className="grid grid-cols-2 gap-3">
                    {/* Despesas */}
                    {loadingExpenses ? <CardSkeleton /> : (
                        <div className="bg-card rounded-xl p-4">
                            <div className="flex items-center gap-2 mb-2">
                                <Wallet className="w-4 h-4 text-red-500" />
                                <span className="text-xs font-semibold text-surface-500">Despesas</span>
                            </div>
                            <p className="text-lg font-bold text-foreground">
                                {formatCurrency(expensesData?.total_month ?? 0)}
                            </p>
                            <p className="text-[10px] text-surface-400">
                                Semana: {formatCurrency(expensesData?.total_week ?? 0)}
                            </p>
                        </div>
                    )}

                    {/* Próximas Calibrações */}
                    {loadingCalibrations ? <CardSkeleton /> : (
                        <div className={cn(
                            'rounded-xl p-4',
                            (calibrationsExpiring?.count ?? 0) > 0
                                ? 'bg-orange-50 dark:bg-orange-900/20'
                                : 'bg-card'
                        )}>
                            <div className="flex items-center gap-2 mb-2">
                                <Shield className="w-4 h-4 text-orange-500" />
                                <span className="text-xs font-semibold text-surface-500">Calibrações</span>
                            </div>
                            <p className={cn(
                                'text-xl font-bold',
                                (calibrationsExpiring?.count ?? 0) > 0
                                    ? 'text-orange-600 dark:text-orange-400'
                                    : 'text-foreground'
                            )}>
                                {calibrationsExpiring?.count ?? 0}
                            </p>
                            <p className="text-[10px] text-surface-400">vencendo em 30 dias</p>
                        </div>
                    )}
                </div>

                {/* NEW: Produtividade (average completion time this week) */}
                <div className="grid grid-cols-2 gap-3">
                    <div className="bg-card rounded-xl p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <Activity className="w-4 h-4 text-cyan-500" />
                            <span className="text-xs font-semibold text-surface-500">Produtividade</span>
                        </div>
                        <p className="text-xl font-bold text-foreground">
                            {productivityData?.average_time_hours_week
                                ? `${productivityData.average_time_hours_week.toFixed(1)}h`
                                : productivityData?.average_time_hours
                                    ? `${productivityData.average_time_hours.toFixed(1)}h`
                                    : 'N/A'}
                        </p>
                        <p className="text-[10px] text-surface-400">tempo médio/OS esta semana</p>
                    </div>
                    <div className="bg-card rounded-xl p-4 flex flex-col justify-center items-center">
                        <p className="text-2xl font-bold text-foreground">
                            {expensesData?.count ?? 0}
                        </p>
                        <p className="text-[10px] text-surface-400">despesas registradas no mês</p>
                    </div>
                </div>

                {/* Ranking Card */}
                <div className="bg-gradient-to-r from-brand-600 to-brand-700 rounded-xl p-4 text-white">
                    <div className="flex items-center gap-3 mb-2">
                        <Trophy className="w-6 h-6" />
                        <div className="flex-1">
                            <h3 className="text-sm font-semibold">Ranking de Técnicos</h3>
                            <p className="text-xs text-brand-100">
                                {rankingData?.position && rankingData?.total_technicians
                                    ? `${rankingData.position}º de ${rankingData.total_technicians} técnicos`
                                    : 'Posição não disponível'}
                            </p>
                        </div>
                    </div>
                    {rankingData?.position && rankingData.position <= 3 && (
                        <div className="mt-2 text-xs text-brand-100">
                            🏆 Parabéns! Você está entre os melhores!
                        </div>
                    )}
                </div>

                {/* Weekly Chart */}
                <div className="bg-card rounded-xl p-4">
                    <div className="flex items-center gap-2 mb-4">
                        <BarChart3 className="w-5 h-5 text-brand-600" />
                        <h3 className="text-sm font-semibold text-foreground">
                            OS Concluídas por Semana
                        </h3>
                    </div>
                    <div className="space-y-3">
                        {(weeklyData || []).map((week, idx) => (
                            <div key={idx} className="space-y-1">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-surface-600">{week.week}</span>
                                    <span className="font-semibold text-foreground">{week.count}</span>
                                </div>
                                <div className="h-3 bg-surface-100 rounded-full overflow-hidden">
                                    <div
                                        className="h-full bg-brand-600 rounded-full transition-all"
                                        style={{ width: `${(week.count / maxCount) * 100}%` }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-2 gap-3">
                    <div className="bg-card rounded-xl p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <Clock className="w-4 h-4 text-amber-500" />
                            <span className="text-xs text-surface-500">OS Pendentes</span>
                        </div>
                        <p className="text-xl font-bold text-foreground">
                            {productivityData?.pending_count ?? 0}
                        </p>
                    </div>

                    <div className="bg-card rounded-xl p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <Wrench className="w-4 h-4 text-blue-500" />
                            <span className="text-xs text-surface-500">OS em Andamento</span>
                        </div>
                        <p className="text-xl font-bold text-foreground">
                            {productivityData?.in_progress_count ?? 0}
                        </p>
                    </div>

                    <div className="bg-card rounded-xl p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <Target className="w-4 h-4 text-emerald-500" />
                            <span className="text-xs text-surface-500">Taxa de Conclusão</span>
                        </div>
                        <p className="text-xl font-bold text-foreground">
                            {productivityData?.completion_rate ? `${productivityData.completion_rate.toFixed(0)}%` : 'N/A'}
                        </p>
                    </div>

                    <div className="bg-card rounded-xl p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <TrendingUp className="w-4 h-4 text-teal-500" />
                            <span className="text-xs text-surface-500">Horas Trabalhadas</span>
                        </div>
                        <p className="text-xl font-bold text-foreground">
                            {productivityData?.hours_worked_month ? `${productivityData.hours_worked_month.toFixed(1)}h` : 'N/A'}
                        </p>
                        <p className="text-[10px] text-surface-400">este mês</p>
                    </div>
                </div>

                {/* Badges & Conquistas */}
                <div className="bg-card rounded-xl p-4">
                    <div className="flex items-center gap-2 mb-4">
                        <Award className="w-5 h-5 text-amber-500" />
                        <h3 className="text-sm font-semibold text-foreground">
                            Conquistas
                        </h3>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        {(badges || []).map((badge) => (
                            <div
                                key={badge.id}
                                className={cn(
                                    'flex flex-col items-center gap-1.5 p-3 rounded-xl text-center',
                                    badge.earned
                                        ? 'bg-amber-50'
                                        : 'bg-surface-50 opacity-40'
                                )}
                            >
                                <span className="text-2xl">{badge.emoji}</span>
                                <span className={cn(
                                    'text-[10px] font-medium leading-tight',
                                    badge.earned ? 'text-amber-700' : 'text-surface-400'
                                )}>
                                    {badge.label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Streak */}
                <div className="bg-gradient-to-r from-orange-500 to-amber-500 rounded-xl p-4 text-white">
                    <div className="flex items-center gap-3">
                        <div className="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center">
                            <Flame className="w-7 h-7" />
                        </div>
                        <div>
                            <p className="text-2xl font-bold">{productivityData?.streak_days ?? 0} dias</p>
                            <p className="text-xs text-orange-100">Sequência sem SLA estourado</p>
                        </div>
                    </div>
                </div>

                {/* Atalhos Rápidos */}
                <div className="bg-card rounded-xl p-4">
                    <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide mb-3">Atalhos Rápidos</h3>
                    <div className="grid grid-cols-2 gap-2">
                        {[
                            { label: 'Minhas Comissões', path: '/tech/comissoes', icon: DollarSign, color: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' },
                            { label: 'Metas', path: '/tech/metas', icon: Target, color: 'text-blue-600 bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400' },
                            { label: 'Resumo do Dia', path: '/tech/resumo-diario', icon: Calendar, color: 'text-teal-600 bg-teal-100 dark:bg-teal-900/30 dark:text-teal-400' },
                            { label: 'Rota do Dia', path: '/tech/rota', icon: Navigation, color: 'text-sky-600 bg-sky-100 dark:bg-sky-900/30 dark:text-sky-400' },
                        ].map(link => (
                            <button
                                key={link.path}
                                onClick={() => navigate(link.path)}
                                className="flex items-center gap-2.5 p-3 rounded-xl bg-surface-50 active:scale-[0.98] transition-transform"
                            >
                                <div className={cn('w-8 h-8 rounded-lg flex items-center justify-center', link.color)}>
                                    <link.icon className="w-4 h-4" />
                                </div>
                                <span className="text-xs font-medium text-surface-700">{link.label}</span>
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    )
}
