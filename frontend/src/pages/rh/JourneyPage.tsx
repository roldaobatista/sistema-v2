import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Clock, Calendar, TrendingUp, Moon, Users, Calculator,
    ChevronLeft, ChevronRight, AlertTriangle, ShieldAlert, Coffee,
    Timer, CheckCircle2, Download
} from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { cn } from '@/lib/utils'
import { safeArray } from '@/lib/safe-array'
import { useAuthStore } from '@/stores/auth-store'
import { toast } from 'sonner'

interface JourneyEntry {
    id: number
    user_id: number
    user?: { name: string }
    date: string
    scheduled_hours: string
    worked_hours: string
    overtime_hours_50: string
    overtime_hours_100: string
    night_hours: string
    absence_hours: string
    hour_bank_balance: string
    overtime_limit_exceeded?: boolean
    tolerance_applied?: boolean
    break_compliance?: 'compliant' | 'short_break' | 'missing_break' | null
    inter_shift_hours?: number | null
    is_holiday: boolean
    is_dsr: boolean
    status: 'calculated' | 'adjusted' | 'locked'
}

interface MonthSummary {
    total_worked: string
    total_overtime_50: string
    total_overtime_100: string
    total_night: string
    total_absence: string
    hour_bank_balance: string
}

interface JourneyResponse {
    data?: JourneyEntry[]
    summary?: MonthSummary | null
}

interface HourBankBalance {
    balance?: string
}

const statusColors: Record<string, string> = {
    calculated: 'bg-blue-100 text-blue-700',
    adjusted: 'bg-amber-100 text-amber-700',
    locked: 'bg-surface-100 text-surface-600',
}

const fmt = (val: string | number) => parseFloat(String(val || '0')).toFixed(1)

export default function JourneyPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const canManage = hasRole('super_admin') || hasPermission('hr.journey.manage')
    const [selectedUser, setSelectedUser] = useState<number | null>(null)
    const [yearMonth, setYearMonth] = useState(() => {
        const now = new Date()
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
    })

    // Users list
    const { data: usersRes, error: usersError } = useQuery({
        queryKey: ['technicians-options'],
        queryFn: () => api.get('/technicians/options').then(response => safeArray<{ id: number; name: string }>(unwrapData(response))),
    })
    const users: { id: number; name: string }[] = usersRes ?? []

    // Journey entries for selected user & month
    const { data: journeyRes, isLoading, error: journeyError } = useQuery({
        queryKey: ['journey-entries', selectedUser, yearMonth],
        queryFn: () => api.get('/hr/journey-entries', {
            params: { user_id: selectedUser, year_month: yearMonth },
        }).then(response => unwrapData<JourneyResponse>(response)),
        enabled: !!selectedUser,
    })

    const entries = safeArray<JourneyEntry>(journeyRes?.data)
    const summary: MonthSummary | null = journeyRes?.summary ?? null

    // Calculate month
    const calculateMut = useMutation({
        mutationFn: () => api.post('/hr/journey/calculate', { year_month: yearMonth, user_id: selectedUser }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['journey-entries'] })
            toast.success('Jornada calculada com sucesso')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao calcular')),
    })

    // Hour bank
    const { data: hourBankRes, error: hourBankError } = useQuery({
        queryKey: ['hour-bank', selectedUser],
        queryFn: () => api.get('/hr/hour-bank/balance', { params: { user_id: selectedUser } }).then(response => unwrapData<HourBankBalance>(response)),
        enabled: !!selectedUser,
    })
    const pageError = usersError ?? journeyError ?? hourBankError
    const pageErrorMessage = pageError
        ? getApiErrorMessage(pageError, 'Nao foi possivel carregar os dados de jornada agora.')
        : null

    const navigateMonth = (dir: -1 | 1) => {
        const [y, m] = yearMonth.split('-').map(Number)
        const d = new Date(y, m - 1 + dir, 1)
        setYearMonth(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
    }

    const monthLabel = () => {
        const [y, m] = yearMonth.split('-').map(Number)
        return new Date(y, m - 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
    }

    return (
        <div className="space-y-5">
            <PageHeader title="Jornada & Banco de Horas" subtitle="Controle de horas trabalhadas, extras e noturnas" />

            {summary && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {[
                        { label: 'Trabalhadas', value: `${fmt(summary.total_worked)}h`, icon: Clock, color: 'text-brand-600 bg-brand-50' },
                        { label: 'HE 50%', value: `${fmt(summary.total_overtime_50)}h`, icon: TrendingUp, color: 'text-amber-600 bg-amber-50' },
                        { label: 'HE 100%', value: `${fmt(summary.total_overtime_100)}h`, icon: TrendingUp, color: 'text-red-600 bg-red-50' },
                        { label: 'Noturnas', value: `${fmt(summary.total_night)}h`, icon: Moon, color: 'text-emerald-600 bg-emerald-50' },
                        { label: 'Faltas', value: `${fmt(summary.total_absence)}h`, icon: AlertTriangle, color: 'text-red-600 bg-red-50' },
                        { label: 'Banco', value: `${fmt(summary.hour_bank_balance)}h`, icon: Calculator, color: parseFloat(summary.hour_bank_balance) >= 0 ? 'text-emerald-600 bg-emerald-50' : 'text-red-600 bg-red-50' },
                    ].map(s => (
                        <div key={s.label} className="rounded-xl border border-default bg-surface-0 p-3.5 shadow-card">
                            <div className="flex items-center gap-2.5">
                                <div className={`rounded-lg p-2 ${s.color}`}><s.icon className="h-4 w-4" /></div>
                                <div>
                                    <p className="text-xs text-surface-500">{s.label}</p>
                                    <p className="text-sm font-bold tabular-nums text-surface-900">{s.value}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <select
                        aria-label="Selecionar técnico"
                        value={selectedUser ?? ''}
                        onChange={e => setSelectedUser(e.target.value ? Number(e.target.value) : null)}
                        className="rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                    >
                        <option value="">— Selecionar técnico —</option>
                        {(users || []).map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                    </select>

                    <div className="flex items-center gap-1 rounded-lg border border-default bg-surface-50 px-1 py-0.5">
                        <button
                            type="button"
                            title="Mês anterior"
                            aria-label="Mês anterior"
                            onClick={() => navigateMonth(-1)}
                            className="rounded p-1.5 hover:bg-surface-100"
                        >
                            <ChevronLeft className="h-4 w-4 text-surface-500" />
                        </button>
                        <span className="min-w-[140px] text-center text-sm font-medium text-surface-700 capitalize">
                            {monthLabel()}
                        </span>
                        <button
                            type="button"
                            title="Próximo mês"
                            aria-label="Próximo mês"
                            onClick={() => navigateMonth(1)}
                            className="rounded p-1.5 hover:bg-surface-100"
                        >
                            <ChevronRight className="h-4 w-4 text-surface-500" />
                        </button>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {canManage && selectedUser && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => calculateMut.mutate()}
                            loading={calculateMut.isPending}
                            icon={<Calculator className="h-4 w-4" />}
                        >
                            Recalcular Mês
                        </Button>
                    )}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            const [y, m] = yearMonth.split('-').map(Number)
                            const startDate = `${y}-${String(m).padStart(2, '0')}-01`
                            const endDate = new Date(y, m, 0).toISOString().split('T')[0]
                            window.open(`/api/v1/hr/fiscal/afd?start_date=${startDate}&end_date=${endDate}`, '_blank')
                        }}
                        icon={<Download className="h-4 w-4" />}
                    >
                        Exportar AFD
                    </Button>
                </div>
            </div>

            <div className="overflow-auto rounded-xl border border-default bg-surface-0 shadow-card">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-subtle bg-surface-50">
                            <th className="px-4 py-2.5 text-left font-semibold text-surface-600">Data</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Previstas</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Trabalhadas</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">HE 50%</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">HE 100%</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Noturnas</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Faltas</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Banco</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Compliance</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-subtle">
                        {pageErrorMessage && (
                            <tr>
                                <td colSpan={10} className="px-4 py-8 text-center text-sm text-red-600">
                                    {pageErrorMessage}
                                </td>
                            </tr>
                        )}
                        {!pageErrorMessage && !selectedUser && (
                            <tr>
                                <td colSpan={10} className="px-4 py-12 text-center">
                                    <Users className="mx-auto h-8 w-8 text-surface-300" />
                                    <p className="mt-2 text-sm text-surface-400">Selecione um técnico para ver a jornada</p>
                                </td>
                            </tr>
                        )}
                        {!pageErrorMessage && selectedUser && isLoading && (
                            <tr><td colSpan={10} className="px-4 py-8 text-center text-surface-400">Carregando...</td></tr>
                        )}
                        {!pageErrorMessage && selectedUser && !isLoading && entries.length === 0 && (
                            <tr>
                                <td colSpan={10} className="px-4 py-12 text-center">
                                    <Calendar className="mx-auto h-8 w-8 text-surface-300" />
                                    <p className="mt-2 text-sm text-surface-400">Nenhuma jornada calculada para este mês</p>
                                    {canManage && (
                                        <Button variant="outline" size="sm" className="mt-3" onClick={() => calculateMut.mutate()}>
                                            Calcular Agora
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        )}
                        {!pageErrorMessage && entries.map(e => {
                            const date = new Date(e.date + 'T00:00:00')
                            const isWeekend = date.getDay() === 0 || date.getDay() === 6
                            const _hasOvertime = parseFloat(e.overtime_hours_50) > 0 || parseFloat(e.overtime_hours_100) > 0

                            return (
                                <tr key={e.id} className={cn(
                                    'transition-colors hover:bg-surface-50/50',
                                    e.is_holiday && 'bg-red-50/40',
                                    isWeekend && !e.is_holiday && 'bg-surface-50/50',
                                )}>
                                    <td className="px-4 py-2.5">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-surface-900">
                                                {date.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })}
                                            </span>
                                            {e.is_holiday && <span className="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-600">Feriado</span>}
                                            {e.is_dsr && <span className="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-600">DSR</span>}
                                        </div>
                                    </td>
                                    <td className="px-4 py-2.5 text-center text-surface-500 tabular-nums">{fmt(e.scheduled_hours)}</td>
                                    <td className="px-4 py-2.5 text-center font-medium text-surface-900 tabular-nums">{fmt(e.worked_hours)}</td>
                                    <td className={cn('px-4 py-2.5 text-center tabular-nums', parseFloat(e.overtime_hours_50) > 0 ? 'font-bold text-amber-600' : 'text-surface-400')}>
                                        {fmt(e.overtime_hours_50)}
                                    </td>
                                    <td className={cn('px-4 py-2.5 text-center tabular-nums', parseFloat(e.overtime_hours_100) > 0 ? 'font-bold text-red-600' : 'text-surface-400')}>
                                        {fmt(e.overtime_hours_100)}
                                    </td>
                                    <td className={cn('px-4 py-2.5 text-center tabular-nums', parseFloat(e.night_hours) > 0 ? 'text-emerald-600' : 'text-surface-400')}>
                                        {fmt(e.night_hours)}
                                    </td>
                                    <td className={cn('px-4 py-2.5 text-center tabular-nums', parseFloat(e.absence_hours) > 0 ? 'text-red-600' : 'text-surface-400')}>
                                        {fmt(e.absence_hours)}
                                    </td>
                                    <td className={cn('px-4 py-2.5 text-center font-mono text-xs tabular-nums',
                                        parseFloat(e.hour_bank_balance) > 0 ? 'text-emerald-600' : parseFloat(e.hour_bank_balance) < 0 ? 'text-red-600' : 'text-surface-400'
                                    )}>
                                        {parseFloat(e.hour_bank_balance) > 0 ? '+' : ''}{fmt(e.hour_bank_balance)}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <div className="flex flex-wrap items-center justify-center gap-1">
                                            {e.overtime_limit_exceeded && (
                                                <span title="Art. 59 CLT: HE excede 2h/dia" className="inline-flex items-center gap-0.5 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">
                                                    <ShieldAlert className="h-3 w-3" />HE
                                                </span>
                                            )}
                                            {e.tolerance_applied && (
                                                <span title="Art. 58 §1: Tolerância aplicada" className="inline-flex items-center gap-0.5 rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">
                                                    <Timer className="h-3 w-3" />Tol
                                                </span>
                                            )}
                                            {e.break_compliance === 'short_break' && (
                                                <span title="Art. 71: Intervalo curto" className="inline-flex items-center gap-0.5 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">
                                                    <Coffee className="h-3 w-3" />Int
                                                </span>
                                            )}
                                            {e.break_compliance === 'missing_break' && (
                                                <span title="Art. 71: Sem intervalo" className="inline-flex items-center gap-0.5 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">
                                                    <Coffee className="h-3 w-3" />S/Int
                                                </span>
                                            )}
                                            {e.inter_shift_hours != null && e.inter_shift_hours < 11 && (
                                                <span title={`Art. 66: Descanso ${e.inter_shift_hours}h (<11h)`} className="inline-flex items-center gap-0.5 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">
                                                    <AlertTriangle className="h-3 w-3" />{e.inter_shift_hours}h
                                                </span>
                                            )}
                                            {!e.overtime_limit_exceeded && !e.tolerance_applied && e.break_compliance === 'compliant' && (e.inter_shift_hours == null || e.inter_shift_hours >= 11) && parseFloat(e.worked_hours) > 0 && (
                                                <CheckCircle2 className="h-4 w-4 text-emerald-500" title="Conforme" />
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-2.5 text-center">
                                        <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', statusColors[e.status])}>
                                            {e.status === 'calculated' ? 'Calc' : e.status === 'adjusted' ? 'Ajust' : 'Lock'}
                                        </span>
                                    </td>
                                </tr>
                            )
                        })}
                    </tbody>
                </table>
            </div>

            {hourBankRes && selectedUser && (
                <div className="rounded-xl border border-default bg-gradient-to-br from-surface-0 to-surface-50 p-5 shadow-card">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-semibold text-surface-700">Saldo do Banco de Horas</p>
                            <p className="text-xs text-surface-500">Acumulado até o mês atual</p>
                        </div>
                        <div className={cn(
                            'text-2xl font-bold tabular-nums',
                            parseFloat(hourBankRes.balance ?? '0') >= 0 ? 'text-emerald-600' : 'text-red-600'
                        )}>
                            {parseFloat(hourBankRes.balance ?? '0') > 0 ? '+' : ''}{fmt(hourBankRes.balance ?? '0')}h
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
