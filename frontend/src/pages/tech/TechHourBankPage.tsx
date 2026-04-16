import { useQuery } from '@tanstack/react-query'
import { Calculator, TrendingUp, TrendingDown, Loader2 } from 'lucide-react'
import api, { unwrapData } from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import { cn } from '@/lib/utils'

interface HourBankBalance {
  balance?: string
}

interface HourBankTransaction {
  id: number
  type: string
  hours: string
  balance_after: string
  reference_date: string
  notes: string | null
}

export default function TechHourBankPage() {
  const { user } = useAuthStore()

  const { data: balanceData, isLoading: balLoading } = useQuery({
    queryKey: ['tech-hour-bank-balance'],
    queryFn: () =>
      api.get('/hr/hour-bank/balance', { params: { user_id: user?.id } })
        .then((r) => unwrapData<HourBankBalance>(r)),
    enabled: !!user?.id,
  })

  const { data: txData, isLoading: txLoading } = useQuery({
    queryKey: ['tech-hour-bank-transactions'],
    queryFn: () =>
      api.get('/hr/hour-bank/transactions', { params: { user_id: user?.id, per_page: 30 } })
        .then((r) => r.data),
    enabled: !!user?.id,
  })

  const balance = parseFloat(balanceData?.balance ?? '0')
  const transactions: HourBankTransaction[] = txData?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Meu Banco de Horas"
        subtitle="Saldo acumulado e histórico de movimentações"
        icon={Calculator}
      />

      {balLoading ? (
        <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground" />
      ) : (
        <div className="rounded-xl border bg-gradient-to-br from-white to-slate-50 p-6 text-center">
          <p className="text-sm text-muted-foreground">Saldo Atual</p>
          <p className={cn(
            'text-4xl font-bold tabular-nums',
            balance >= 0 ? 'text-green-600' : 'text-red-600',
          )}>
            {balance > 0 ? '+' : ''}{balance.toFixed(1)}h
          </p>
        </div>
      )}

      <h3 className="font-semibold">Movimentações Recentes</h3>

      {txLoading ? (
        <Loader2 className="mx-auto h-5 w-5 animate-spin text-muted-foreground" />
      ) : transactions.length === 0 ? (
        <div className="rounded-lg border border-dashed p-6 text-center text-muted-foreground">
          Nenhuma movimentação registrada.
        </div>
      ) : (
        <div className="space-y-1">
          {transactions.map((tx) => {
            const hours = parseFloat(tx.hours)
            const isCredit = hours > 0
            return (
              <div key={tx.id} className="flex items-center justify-between rounded border px-3 py-2 text-sm">
                <div className="flex items-center gap-2">
                  {isCredit
                    ? <TrendingUp className="h-4 w-4 text-green-500" aria-hidden="true" />
                    : <TrendingDown className="h-4 w-4 text-red-500" aria-hidden="true" />
                  }
                  <div>
                    <span className="font-medium capitalize">{tx.type}</span>
                    {tx.notes && <span className="ml-2 text-muted-foreground">{tx.notes}</span>}
                  </div>
                </div>
                <div className="flex items-center gap-4">
                  <span className="text-xs text-muted-foreground">
                    {new Date(tx.reference_date + 'T12:00:00').toLocaleDateString('pt-BR')}
                  </span>
                  <span className={cn('font-mono font-medium', isCredit ? 'text-green-600' : 'text-red-600')}>
                    {isCredit ? '+' : ''}{hours.toFixed(1)}h
                  </span>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
