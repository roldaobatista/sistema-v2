import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { FileText, AlertTriangle, CheckCircle2, Loader2, Send } from 'lucide-react'
import { payrollJourneyApi, type PayrollMonthSummary, type BlockingDay } from '@/lib/payroll-journey-api'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'

export default function PayrollJourneyPage() {
  const [yearMonth, setYearMonth] = useState(() => {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
  })

  const { data: summary, isLoading: sumLoading } = useQuery({
    queryKey: ['payroll-journey-summary', yearMonth],
    queryFn: () => payrollJourneyApi.monthSummary(yearMonth),
  })

  const { data: blocking, isLoading: blkLoading } = useQuery({
    queryKey: ['payroll-journey-blocking', yearMonth],
    queryFn: () => payrollJourneyApi.blockingDays(yearMonth),
  })

  const generateMut = useMutation({
    mutationFn: () => payrollJourneyApi.generateESocial(yearMonth, ['S-1200', 'S-2230']),
    onSuccess: (data) => {
      const total = Object.values(data).reduce((s, v) => s + v.generated, 0)
      toast.success(`${total} evento(s) eSocial gerado(s)`)
    },
    onError: () => toast.error('Erro ao gerar eventos eSocial'),
  })

  const blockingDays: BlockingDay[] = blocking ?? []
  const summaryData: PayrollMonthSummary[] = summary ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="Integração Jornada → Folha" subtitle="Exportar horas para folha e gerar eventos eSocial" icon={FileText} />

      <div className="flex items-center gap-3">
        <input
          type="month"
          className="rounded-md border px-3 py-1.5 text-sm"
          value={yearMonth}
          onChange={(e) => setYearMonth(e.target.value)}
          aria-label="Competência"
        />
        <Button
          size="sm"
          onClick={() => generateMut.mutate()}
          disabled={generateMut.isPending || blockingDays.length > 0}
          aria-label="Gerar eventos eSocial"
        >
          <Send className="mr-1 h-4 w-4" />
          Gerar eSocial
        </Button>
      </div>

      {blockingDays.length > 0 && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-4">
          <div className="flex items-center gap-2 text-sm font-semibold text-red-800">
            <AlertTriangle className="h-4 w-4" />
            {blockingDays.length} dia(s) não fechado(s) — bloqueando geração de folha
          </div>
          <div className="mt-2 space-y-1">
            {blockingDays.map((d) => (
              <div key={d.journey_day_id} className="flex items-center justify-between text-sm text-red-700">
                <span>{d.user_name} — {new Date(d.reference_date + 'T12:00:00').toLocaleDateString('pt-BR')}</span>
                <span>Op: {d.operational_status} | RH: {d.hr_status}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {sumLoading ? (
        <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground" />
      ) : summaryData.length === 0 ? (
        <div className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
          Nenhuma jornada fechada para esta competência.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-muted-foreground">
                <th className="px-3 py-2">Técnico</th>
                <th className="px-3 py-2 text-center">Dias</th>
                <th className="px-3 py-2 text-center">Trabalhado</th>
                <th className="px-3 py-2 text-center">HE</th>
                <th className="px-3 py-2 text-center">Desloc.</th>
                <th className="px-3 py-2 text-center">Sobreaviso</th>
                <th className="px-3 py-2 text-center">Pernoite</th>
                <th className="px-3 py-2 text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              {summaryData.map((row) => (
                <tr key={row.user_id} className="border-b hover:bg-muted/50">
                  <td className="px-3 py-2 font-medium">{row.user_name}</td>
                  <td className="px-3 py-2 text-center">{row.working_days}</td>
                  <td className="px-3 py-2 text-center">{row.total_worked_hours}h</td>
                  <td className={cn('px-3 py-2 text-center', row.total_overtime_hours > 0 && 'font-bold text-red-600')}>
                    {row.total_overtime_hours}h
                  </td>
                  <td className="px-3 py-2 text-center">{row.total_travel_hours}h</td>
                  <td className="px-3 py-2 text-center">{row.total_oncall_hours}h</td>
                  <td className="px-3 py-2 text-center">{row.total_overnight_hours}h</td>
                  <td className="px-3 py-2 text-center">
                    {row.all_days_approved
                      ? <CheckCircle2 className="inline h-4 w-4 text-green-500" aria-label="Aprovado" />
                      : <AlertTriangle className="inline h-4 w-4 text-amber-500" aria-label="Pendente" />
                    }
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
