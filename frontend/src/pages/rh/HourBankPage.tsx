import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
    Calculator, TrendingUp, AlertTriangle,
    ArrowUpRight, ArrowDownRight, Timer, Users
} from 'lucide-react'
import api, { unwrapData } from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { cn } from '@/lib/utils'
import { safeArray } from '@/lib/safe-array'
import { useAuthStore } from '@/stores/auth-store'

interface HourBankTransaction {
    id: number
    type: 'accrual' | 'usage' | 'expiry' | 'payout'
    hours: string
    balance_before: string
    balance_after: string
    reference_date: string
    expired_at: string | null
    notes: string | null
    created_at: string
}

interface HourBankSummary {
    user_id: number
    period: { from: string; to: string }
    total_worked: number
    total_expected: number
    balance_hours: number
    balance_type: 'credit' | 'debit'
    details: Array<{
        date: string
        worked_hours: number
        expected_hours: number
        balance: number
    }>
}

const typeLabels: Record<string, { label: string; color: string; icon: typeof TrendingUp }> = {
    accrual: { label: 'Acumulado', color: 'text-emerald-700 bg-emerald-100', icon: ArrowUpRight },
    usage: { label: 'Compensado', color: 'text-blue-700 bg-blue-100', icon: ArrowDownRight },
    expiry: { label: 'Expirado', color: 'text-red-700 bg-red-100', icon: Timer },
    payout: { label: 'Pago (HE)', color: 'text-amber-700 bg-amber-100', icon: Calculator },
}

const fmt = (val: string | number) => parseFloat(String(val || '0')).toFixed(1)

export default function HourBankPage() {
    const { hasPermission, hasRole } = useAuthStore()
    const [selectedUser, setSelectedUser] = useState<number | null>(null)

    const { data: usersRes } = useQuery({
        queryKey: ['technicians-options'],
        queryFn: () => api.get('/technicians/options').then(r => safeArray<{ id: number; name: string }>(unwrapData(r))),
    })
    const users: { id: number; name: string }[] = usersRes ?? []

    const { data: summaryRes, isLoading: loadingSummary } = useQuery({
        queryKey: ['hour-bank-summary', selectedUser],
        queryFn: () => api.get('/hr/people/hour-bank-summary', { params: { user_id: selectedUser } }).then(r => unwrapData<HourBankSummary>(r)),
        enabled: !!selectedUser,
    })

    const { data: balanceRes } = useQuery({
        queryKey: ['hour-bank-balance', selectedUser],
        queryFn: () => api.get('/hr/hour-bank/balance', { params: { user_id: selectedUser } }).then(r => unwrapData<{ balance: string }>(r)),
        enabled: !!selectedUser,
    })

    const balance = parseFloat(balanceRes?.balance ?? '0')

    return (
        <div className="space-y-5">
            <PageHeader title="Banco de Horas" subtitle="Saldo, transacoes e expiracao por colaborador" />

            <div className="flex items-center gap-3">
                <select
                    aria-label="Selecionar colaborador"
                    value={selectedUser ?? ''}
                    onChange={e => setSelectedUser(e.target.value ? Number(e.target.value) : null)}
                    className="rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                >
                    <option value="">— Selecionar colaborador —</option>
                    {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                </select>
            </div>

            {!selectedUser && (
                <div className="rounded-xl border border-default bg-surface-0 p-12 text-center shadow-card">
                    <Users className="mx-auto h-8 w-8 text-surface-300" />
                    <p className="mt-2 text-sm text-surface-400">Selecione um colaborador para ver o banco de horas</p>
                </div>
            )}

            {selectedUser && (
                <>
                    {/* Balance cards */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <p className="text-xs text-surface-500">Saldo Atual</p>
                            <p className={cn('mt-1 text-3xl font-bold tabular-nums', balance >= 0 ? 'text-emerald-600' : 'text-red-600')}>
                                {balance > 0 ? '+' : ''}{fmt(balance)}h
                            </p>
                            <p className="mt-1 text-xs text-surface-400">
                                {balance >= 0 ? 'Credito — horas a compensar' : 'Debito — horas devidas'}
                            </p>
                        </div>
                        <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <p className="text-xs text-surface-500">Trabalhadas no Mes</p>
                            <p className="mt-1 text-3xl font-bold tabular-nums text-brand-600">
                                {fmt(summaryRes?.total_worked ?? 0)}h
                            </p>
                            <p className="mt-1 text-xs text-surface-400">
                                Previstas: {fmt(summaryRes?.total_expected ?? 0)}h
                            </p>
                        </div>
                        <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-amber-500" />
                                <p className="text-xs text-surface-500">Atencao Expiracao</p>
                            </div>
                            <p className="mt-1 text-sm text-surface-600">
                                Horas positivas expiram conforme o tipo de acordo (individual: 6 meses, coletivo: 12 meses).
                            </p>
                        </div>
                    </div>

                    {/* Daily breakdown */}
                    {summaryRes && summaryRes.details && summaryRes.details.length > 0 && (
                        <div className="overflow-auto rounded-xl border border-default bg-surface-0 shadow-card">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-subtle bg-surface-50">
                                        <th className="px-4 py-2.5 text-left font-semibold text-surface-600">Data</th>
                                        <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Trabalhadas</th>
                                        <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Previstas</th>
                                        <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Saldo Dia</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-subtle">
                                    {summaryRes.details.filter(d => d.expected_hours > 0 || d.worked_hours > 0).map(d => (
                                        <tr key={d.date} className="hover:bg-surface-50/50">
                                            <td className="px-4 py-2 font-medium text-surface-900">
                                                {new Date(d.date + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })}
                                            </td>
                                            <td className="px-4 py-2 text-center tabular-nums">{fmt(d.worked_hours)}</td>
                                            <td className="px-4 py-2 text-center tabular-nums text-surface-500">{fmt(d.expected_hours)}</td>
                                            <td className={cn('px-4 py-2 text-center font-medium tabular-nums',
                                                d.balance > 0 ? 'text-emerald-600' : d.balance < 0 ? 'text-red-600' : 'text-surface-400'
                                            )}>
                                                {d.balance > 0 ? '+' : ''}{fmt(d.balance)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {loadingSummary && (
                        <div className="rounded-xl border border-default bg-surface-0 p-8 text-center shadow-card">
                            <p className="text-sm text-surface-400">Carregando...</p>
                        </div>
                    )}
                </>
            )}
        </div>
    )
}
