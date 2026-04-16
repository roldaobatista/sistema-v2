import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { FileText, Download, Eye, AlertCircle, RefreshCw } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { toast } from 'sonner'
import type { PayslipRecord } from '@/types/hr'

function formatCurrency(value: number | string): string {
  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatMonth(ym: string): string {
  const [y, m] = ym.split('-')
  const months = ['Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']
  return `${months[parseInt(m, 10) - 1]} ${y}`
}

export default function PayslipPage() {
  const [selectedId, setSelectedId] = useState<number | null>(null)

  const { data: payslips, isLoading, error } = useQuery({
    queryKey: ['my-payslips'],
    queryFn: async () => {
      const res = await api.get('/hr/my-payslips', { params: { per_page: 50 } })
      return unwrapData<PayslipRecord[]>(res)
    },
  })

  const { data: detail } = useQuery({
    queryKey: ['payslip-detail', selectedId],
    queryFn: async () => {
      if (!selectedId) return null
      const res = await api.get(`/hr/payslips/${selectedId}`)
      return unwrapData<PayslipRecord>(res)
    },
    enabled: !!selectedId,
  })

  if (error) {
    return (
      <div className="flex items-center gap-2 text-red-500 p-8">
        <AlertCircle className="h-5 w-5" />
        <span>{getApiErrorMessage(error, 'Erro ao carregar holerites.')}</span>
      </div>
    )
  }

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
          <FileText className="h-6 w-6" /> Meus Holerites
        </h1>
        <p className="text-muted-foreground text-sm mt-1">
          Visualize e baixe seus holerites mensais.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Historico de Holerites</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center justify-center py-12 text-muted-foreground">
              <RefreshCw className="h-5 w-5 animate-spin mr-2" /> Carregando...
            </div>
          ) : !payslips || payslips.length === 0 ? (
            <p className="text-center text-muted-foreground py-8">Nenhum holerite disponivel.</p>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {payslips.map((ps) => (
                <Card key={ps.id} className="hover:shadow-md transition-shadow cursor-pointer" onClick={() => setSelectedId(ps.id)}>
                  <CardContent className="pt-4">
                    <div className="flex items-start justify-between">
                      <div>
                        <p className="font-semibold">{formatMonth(ps.reference_month)}</p>
                        <p className="text-sm text-muted-foreground mt-1">
                          Liquido: {formatCurrency(ps.payroll_line?.net_salary ?? 0)}
                        </p>
                      </div>
                      <div className="flex flex-col items-end gap-1">
                        {ps.viewed_at ? (
                          <Badge variant="outline" className="text-xs">Visualizado</Badge>
                        ) : (
                          <Badge variant="default" className="text-xs">Novo</Badge>
                        )}
                        <Button size="sm" variant="ghost" title="Ver detalhes">
                          <Eye className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Payslip Detail Dialog */}
      <Dialog open={!!selectedId} onOpenChange={(open) => { if (!open) setSelectedId(null) }}>
        <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Holerite - {detail ? formatMonth(detail.reference_month) : '...'}
            </DialogTitle>
          </DialogHeader>
          {!detail ? (
            <div className="flex items-center justify-center py-8 text-muted-foreground">
              <RefreshCw className="h-4 w-4 animate-spin mr-2" /> Carregando...
            </div>
          ) : (
            <PayslipDetail payslip={detail} />
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

function PayslipDetail({ payslip }: { payslip: PayslipRecord }) {
  const line = payslip.payroll_line
  if (!line) {
    return <p className="text-muted-foreground py-4">Dados do holerite indisponiveis.</p>
  }

  const earnings: Array<{ label: string; value: number }> = [
    { label: 'Salario Base', value: Number(line.base_salary) },
    { label: 'HE 50%', value: Number(line.overtime_50_value) },
    { label: 'HE 100%', value: Number(line.overtime_100_value) },
    { label: 'Adicional Noturno', value: Number(line.night_shift_value) },
    { label: 'DSR', value: Number(line.dsr_value) },
    { label: 'Comissoes', value: Number(line.commission_value) },
    { label: 'Bonus', value: Number(line.bonus_value) },
    { label: 'Outros Proventos', value: Number(line.other_earnings) },
    { label: 'Ferias', value: Number(line.vacation_value) },
    { label: '1/3 Ferias', value: Number(line.vacation_bonus) },
    { label: '13o Salario', value: Number(line.thirteenth_value) },
  ].filter((e) => e.value > 0)

  const deductions: Array<{ label: string; value: number }> = [
    { label: 'INSS', value: Number(line.inss_employee) },
    { label: 'IRRF', value: Number(line.irrf) },
    { label: 'Vale Transporte', value: Number(line.transportation_discount) },
    { label: 'Vale Refeicao', value: Number(line.meal_discount) },
    { label: 'Plano de Saude', value: Number(line.health_insurance_discount) },
    { label: 'Adiantamento', value: Number(line.advance_discount) },
    { label: 'Faltas', value: Number(line.absence_value) },
    { label: 'Outros Descontos', value: Number(line.other_deductions) },
  ].filter((d) => d.value > 0)

  return (
    <div className="space-y-6">
      {/* Summary */}
      <div className="grid grid-cols-3 gap-4 p-4 bg-muted/30 rounded-lg">
        <div className="text-center">
          <p className="text-xs text-muted-foreground">Bruto</p>
          <p className="text-lg font-bold">{formatCurrency(line.gross_salary)}</p>
        </div>
        <div className="text-center">
          <p className="text-xs text-muted-foreground">Descontos</p>
          <p className="text-lg font-bold text-red-600">
            {formatCurrency(
              Number(line.inss_employee) + Number(line.irrf) + Number(line.transportation_discount)
              + Number(line.meal_discount) + Number(line.health_insurance_discount)
              + Number(line.other_deductions) + Number(line.advance_discount) + Number(line.absence_value)
            )}
          </p>
        </div>
        <div className="text-center">
          <p className="text-xs text-muted-foreground">Liquido</p>
          <p className="text-lg font-bold text-green-600">{formatCurrency(line.net_salary)}</p>
        </div>
      </div>

      {/* Work info */}
      <div className="grid grid-cols-3 gap-3 text-sm">
        <div>
          <span className="text-muted-foreground">Dias Trabalhados:</span>{' '}
          <span className="font-medium">{line.worked_days}</span>
        </div>
        <div>
          <span className="text-muted-foreground">Faltas:</span>{' '}
          <span className="font-medium">{line.absence_days}</span>
        </div>
        <div>
          <span className="text-muted-foreground">FGTS:</span>{' '}
          <span className="font-medium">{formatCurrency(line.fgts_value)}</span>
        </div>
      </div>

      {/* Earnings */}
      <div>
        <h3 className="font-semibold text-sm mb-2 text-green-700">Proventos</h3>
        <table className="w-full text-sm">
          <tbody>
            {earnings.map((e) => (
              <tr key={e.label} className="border-b last:border-0">
                <td className="py-1.5">{e.label}</td>
                <td className="py-1.5 text-right font-medium">{formatCurrency(e.value)}</td>
              </tr>
            ))}
            <tr className="font-bold border-t-2">
              <td className="py-2">Total Proventos</td>
              <td className="py-2 text-right">{formatCurrency(line.gross_salary)}</td>
            </tr>
          </tbody>
        </table>
      </div>

      {/* Deductions */}
      <div>
        <h3 className="font-semibold text-sm mb-2 text-red-700">Descontos</h3>
        <table className="w-full text-sm">
          <tbody>
            {deductions.map((d) => (
              <tr key={d.label} className="border-b last:border-0">
                <td className="py-1.5">{d.label}</td>
                <td className="py-1.5 text-right font-medium text-red-600">{formatCurrency(d.value)}</td>
              </tr>
            ))}
            <tr className="font-bold border-t-2">
              <td className="py-2">Total Descontos</td>
              <td className="py-2 text-right text-red-600">
                {formatCurrency(deductions.reduce((acc, d) => acc + d.value, 0))}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      {/* Signature */}
      {payslip.digital_signature_hash && (
        <div className="text-xs text-muted-foreground border-t pt-3">
          <p>Assinatura digital: {payslip.digital_signature_hash.substring(0, 16)}...</p>
        </div>
      )}

      {/* Download PDF */}
      <div className="flex justify-end">
        <Button
          variant="outline"
          onClick={async () => {
            try {
              const res = await api.get(`/hr/payslips/${payslip.id}/download`, { responseType: 'blob' })
              const url = window.URL.createObjectURL(new Blob([res.data]))
              const link = document.createElement('a')
              link.href = url
              link.setAttribute('download', `holerite-${payslip.reference_month}.pdf`)
              document.body.appendChild(link)
              link.click()
              link.remove()
              window.URL.revokeObjectURL(url)
            } catch {
              toast.error('Erro ao baixar holerite.')
            }
          }}
        >
          <Download className="h-4 w-4 mr-2" /> Baixar PDF
        </Button>
      </div>
    </div>
  )
}
