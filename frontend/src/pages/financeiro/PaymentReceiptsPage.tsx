import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Download, Loader2, Receipt } from 'lucide-react'
import { toast } from 'sonner'
import { getApiErrorMessage, unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import type { PaginatedResponse } from '@/types/api'
import type { PaymentReceiptRecord } from '@/types/financial'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'

const fmt = (value: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
const fmtDate = (date: string | null) => date ? new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR') : ''

export function PaymentReceiptsPage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['payment-receipts', from, to],
    queryFn: async () => {
      const response = await financialApi.paymentReceipts.list({ from: from || undefined, to: to || undefined, per_page: 50 })
      return unwrapData<PaginatedResponse<PaymentReceiptRecord>>(response)
    },
  })

  const receipts = data?.data ?? []

  const downloadPdf = async (id: number) => {
    try {
      const response = await financialApi.paymentReceipts.downloadPdf(id)
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.download = `recibo-${id}.pdf`
      link.click()
      window.URL.revokeObjectURL(url)
    } catch (error) {
      toast.error(getApiErrorMessage(error, 'Erro ao baixar recibo'))
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Recibos de Pagamento" description="Consulte e baixe recibos dos pagamentos recebidos" />

      <div className="flex flex-wrap items-end gap-3">
        <div>
          <label htmlFor="payment-receipts-from" className="text-xs font-medium text-muted-foreground">De</label>
          <Input id="payment-receipts-from" type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="w-40" />
        </div>
        <div>
          <label htmlFor="payment-receipts-to" className="text-xs font-medium text-muted-foreground">Ate</label>
          <Input id="payment-receipts-to" type="date" value={to} onChange={(event) => setTo(event.target.value)} className="w-40" />
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-6 w-6 animate-spin text-muted-foreground" /></div>
      ) : isError ? (
        <Card>
          <CardContent className="py-12 text-center text-sm text-muted-foreground">
            Nao foi possivel carregar os recibos no momento.
          </CardContent>
        </Card>
      ) : receipts.length === 0 ? (
        <p className="py-12 text-center text-sm text-muted-foreground">Nenhum recibo encontrado para o periodo selecionado.</p>
      ) : (
        <div className="space-y-3">
          {receipts.map((receipt) => (
            <Card key={receipt.id}>
              <CardContent className="flex items-center justify-between p-4">
                <div className="flex items-center gap-3">
                  <Receipt className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="font-medium">{fmt(Number(receipt.amount))}</p>
                    <p className="text-sm text-muted-foreground">{receipt.payment_method || 'Nao informado'} · {fmtDate(receipt.payment_date)}</p>
                    {receipt.receiver?.name ? <p className="text-xs text-muted-foreground">Recebido por: {receipt.receiver.name}</p> : null}
                    {receipt.payable?.description ? <p className="text-xs text-muted-foreground">{receipt.payable.description}</p> : null}
                  </div>
                </div>
                <Button variant="outline" size="sm" onClick={() => downloadPdf(receipt.id)}>
                  <Download className="mr-1 h-4 w-4" /> PDF
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

export default PaymentReceiptsPage
