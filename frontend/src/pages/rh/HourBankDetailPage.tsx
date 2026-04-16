import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Clock, TrendingUp, TrendingDown, Info, Calendar } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { safeArray } from '@/lib/safe-array'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'

type HourBankBalance = {
    user_id: number
    balance: number | string
}

type JourneyEntrySummary = {
    id: number
    date: string
    scheduled_hours: number | string
    worked_hours: number | string
}

type JourneyEntriesPayload = {
    data?: JourneyEntrySummary[]
}

function toHourNumber(value: number | string | null | undefined): number {
    const numericValue = Number(value ?? 0)
    return Number.isFinite(numericValue) ? numericValue : 0
}

function formatHours(hours: number | string) {
    const totalMinutes = Math.round(toHourNumber(hours) * 60)
    const isNegative = totalMinutes < 0
    const absMin = Math.abs(totalMinutes)
    const h = Math.floor(absMin / 60)
    const m = absMin % 60
    const formatted = `${h}h${m.toString().padStart(2, '0')}`
    return isNegative ? `-${formatted}` : formatted
}

export default function HourBankDetailPage() {
    const { user } = useAuthStore()
    const [currentDate, setCurrentDate] = useState(() => new Date())
    const yearMonth = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`

    // Fetch the hour bank balance
    const { data: balanceData, isLoading: loadingBalance, error: balanceError } = useQuery({
        queryKey: ['hour-bank-balance', user?.id],
        queryFn: () => api.get('/hr/hour-bank/balance', { params: { user_id: user?.id } }).then(unwrapData<HourBankBalance>),
        enabled: !!user?.id,
    })

    // Fetch the month journey to show related history
    const { data: journeyData, isLoading: loadingJourney, error: journeyError } = useQuery({
        queryKey: ['journey-entries', user?.id, yearMonth],
        queryFn: () => api.get('/hr/journey-entries', {
            params: { user_id: user?.id, year_month: yearMonth },
        }).then(unwrapData<JourneyEntriesPayload>),
        enabled: !!user?.id,
    })

    const queryError = balanceError ?? journeyError
    const hasQueryError = !!queryError
    const queryErrorMessage = hasQueryError
        ? getApiErrorMessage(queryError, 'Nao foi possivel carregar o banco de horas agora. Tente novamente em instantes.')
        : null

    const entries = safeArray<JourneyEntrySummary>(journeyData?.data)
    const balanceHours = toHourNumber(balanceData?.balance)
    const isPositive = balanceHours > 0
    const isNegative = balanceHours < 0

    const handlePrevMonth = () => {
        setCurrentDate(prev => {
            const d = new Date(prev)
            d.setMonth(d.getMonth() - 1)
            return d
        })
    }

    const handleNextMonth = () => {
        setCurrentDate(prev => {
            const d = new Date(prev)
            d.setMonth(d.getMonth() + 1)
            return d
        })
    }

    return (
        <div className="space-y-6 max-w-4xl mx-auto">
            <PageHeader
                title="Banco de Horas"
                subtitle="Acompanhe o saldo e o extrato de suas horas compensáveis"
            />

            {/* Saldo Atual */}
            <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <div className="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-2">
                            <Clock className="w-4 h-4" />
                            Saldo Atual (Geral)
                        </h3>
                        {loadingBalance ? (
                            <div className="h-10 w-32 bg-slate-100 animate-pulse rounded" />
                        ) : (
                            <div className="flex items-baseline gap-3">
                                <span className={cn(
                                    "text-4xl font-black",
                                    isPositive ? "text-emerald-600" : isNegative ? "text-red-600" : "text-slate-700"
                                )}>
                                    {formatHours(balanceHours)}
                                </span>
                                {isPositive && <TrendingUp className="w-5 h-5 text-emerald-500" />}
                                {isNegative && <TrendingDown className="w-5 h-5 text-red-500" />}
                            </div>
                        )}
                        <p className="text-sm text-slate-400 mt-2">
                            Saldo total acumulado até o momento.
                        </p>
                    </div>

                    <div className="flex-1 bg-slate-50 p-4 rounded-xl border border-slate-100 flex gap-3 items-start">
                        <Info className="w-5 h-5 text-brand-500 shrink-0 mt-0.5" />
                        <div className="text-sm text-slate-600">
                            <strong>Como funciona:</strong> O banco de horas permite a compensação de jornadas excedentes ou faltantes.
                            Horas positivas podem ser utilizadas como folga, enquanto horas negativas devem ser compensadas em dias úteis, de acordo com as regras de jornada estabelecidas.
                        </div>
                    </div>
                </div>
            </div>

            {/* Navegação de Mês */}
            <div className="flex items-center justify-between mt-8 mb-4">
                <h2 className="text-lg font-bold text-slate-800">Extrato Mensal</h2>
                <div className="flex items-center gap-2 bg-white px-1 max-w-fit rounded-lg shadow-sm border border-slate-200">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handlePrevMonth}
                        className="text-slate-500"
                        aria-label="Mês anterior"
                    >
                        &larr;
                    </Button>
                    <div className="flex items-center gap-2 px-4 py-1.5 font-medium text-slate-700 min-w-[140px] justify-center">
                        <Calendar className="w-4 h-4 text-slate-400" />
                        {currentDate.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase())}
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleNextMonth}
                        className="text-slate-500"
                        aria-label="Próximo mês"
                        disabled={yearMonth === `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`}
                    >
                        &rarr;
                    </Button>
                </div>
            </div>

            {/* Tabela de Extrato */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <table className="w-full text-sm text-left">
                    <thead className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs">
                        <tr>
                            <th className="px-6 py-4">Data</th>
                            <th className="px-6 py-4">Horas Trabalhadas</th>
                            <th className="px-6 py-4">Jornada Esperada</th>
                            <th className="px-6 py-4 text-right">Saldo do Dia</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {queryErrorMessage ? (
                            <tr>
                                <td colSpan={4} className="px-6 py-12 text-center text-red-600">{queryErrorMessage}</td>
                            </tr>
                        ) : loadingJourney ? (
                            <tr>
                                <td colSpan={4} className="px-6 py-12 text-center text-slate-500">Carregando extrato do mês...</td>
                            </tr>
                        ) : entries.length === 0 ? (
                            <tr>
                                <td colSpan={4} className="px-6 py-12 text-center text-slate-500">Nenhum registro encontrado para este mês.</td>
                            </tr>
                        ) : (
                            entries.map((entry) => {
                                const expected = toHourNumber(entry.scheduled_hours)
                                const worked = toHourNumber(entry.worked_hours)
                                const diff = worked - expected

                                return (
                                    <tr key={entry.id} className="hover:bg-slate-50/50">
                                        <td className="px-6 py-3 font-medium text-slate-700">
                                            {new Date(entry.date + 'T00:00:00').toLocaleDateString('pt-BR')}
                                        </td>
                                        <td className="px-6 py-3 text-slate-600">
                                            {formatHours(worked)} {worked > 0 ? '' : <span className="text-slate-400 italic text-xs ml-2">(Sem registro)</span>}
                                        </td>
                                        <td className="px-6 py-3 text-slate-600">
                                            {formatHours(expected)}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {diff === 0 ? (
                                                <span className="text-slate-400">0h00</span>
                                            ) : (
                                                <span className={cn(
                                                    "font-bold px-2.5 py-1 rounded-full text-xs",
                                                    diff > 0 ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"
                                                )}>
                                                    {diff > 0 ? '+' : ''}{formatHours(diff)}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                )
                            })
                        )}
                    </tbody>
                </table>
            </div>

        </div>
    )
}
