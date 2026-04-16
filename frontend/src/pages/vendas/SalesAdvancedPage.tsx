import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { Card, CardContent } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { Loader2, Users, Target, PercentCircle, AlertTriangle } from 'lucide-react'

const fmt = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

export function SalesAdvancedPage() {
  const [tab, setTab] = useState('followup')

  const { data: followUps, isLoading: fuLoading } = useQuery({
    queryKey: ['sales-followup'],
    queryFn: () => api.get('/sales/follow-up-queue').then(r => r.data),
    enabled: tab === 'followup',
  })

  const { data: lossReasons, isLoading: lrLoading } = useQuery({
    queryKey: ['sales-loss-reasons'],
    queryFn: () => api.get('/sales/loss-reasons').then(r => r.data),
    enabled: tab === 'loss',
  })

  const { data: segmentation, isLoading: segLoading } = useQuery({
    queryKey: ['sales-segmentation'],
    queryFn: () => api.get('/sales/client-segmentation').then(r => r.data),
    enabled: tab === 'segmentation',
  })

  const { data: discountRequests, isLoading: drLoading } = useQuery({
    queryKey: ['sales-discounts'],
    queryFn: () => api.get('/sales/discount-requests').then(r => r.data),
    enabled: tab === 'discounts',
  })

  interface SalesItem {
    id?: number
    customer_name?: string
    customer?: { name?: string }
    name?: string
    reason?: string
    quote_number?: string
    number?: string
    value?: number
    total?: number
    count?: number
    days_pending?: number
    status?: string
    segment?: string
  }

  const renderItems = (items: SalesItem[] | { data?: SalesItem[] | { data?: SalesItem[] } } | undefined, loading: boolean, empty: string) => {
    if (loading) {
      return <div className="flex justify-center py-8"><Loader2 className="w-5 h-5 animate-spin text-muted-foreground" /></div>
    }

    const wrapped = items as { data?: { data?: SalesItem[] } | SalesItem[] } | SalesItem[] | undefined
    const list: SalesItem[] = Array.isArray(wrapped)
      ? wrapped
      : Array.isArray((wrapped?.data as { data?: SalesItem[] })?.data)
        ? (wrapped?.data as { data: SalesItem[] }).data
        : Array.isArray(wrapped?.data)
          ? wrapped.data as SalesItem[]
          : []

    if (!list.length) {
      return <p className="text-center py-8 text-sm text-muted-foreground">{empty}</p>
    }

    return (
      <div className="space-y-3">
        {(list || []).map((item: SalesItem, i: number) => {
          const customerName = item.customer_name ?? item.customer?.name ?? item.name ?? item.reason ?? `#${item.id}`
          const quoteNumber = item.quote_number ?? item.number
          const amount = item.value ?? item.total

          return (
            <Card key={item.id ?? i}>
              <CardContent className="flex items-center justify-between p-4">
                <div>
                  <p className="font-medium">{customerName}</p>
                  {quoteNumber && <p className="text-sm text-muted-foreground">Orçamento: {quoteNumber}</p>}
                  {amount !== undefined && amount !== null && <p className="text-sm text-muted-foreground">{fmt(Number(amount))}</p>}
                  {item.count !== undefined && <p className="text-sm text-muted-foreground">Ocorrências: {item.count}</p>}
                  {item.days_pending !== undefined && <p className="text-xs text-muted-foreground">{item.days_pending} dias pendente</p>}
                </div>
                {item.status && <Badge>{item.status}</Badge>}
                {item.segment && <Badge variant="secondary">{item.segment}</Badge>}
              </CardContent>
            </Card>
          )
        })}
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Vendas Avançado" description="Follow-ups, análise de perdas, segmentação e descontos" />

      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="followup"><Target className="mr-1 h-4 w-4" /> Follow-Up</TabsTrigger>
          <TabsTrigger value="loss"><AlertTriangle className="mr-1 h-4 w-4" /> Perdas</TabsTrigger>
          <TabsTrigger value="segmentation"><Users className="mr-1 h-4 w-4" /> Segmentação</TabsTrigger>
          <TabsTrigger value="discounts"><PercentCircle className="mr-1 h-4 w-4" /> Descontos</TabsTrigger>
        </TabsList>

        <TabsContent value="followup">
          {renderItems(followUps, fuLoading, 'Nenhum follow-up pendente')}
        </TabsContent>
        <TabsContent value="loss">
          {renderItems(lossReasons, lrLoading, 'Nenhum motivo de perda registrado')}
        </TabsContent>
        <TabsContent value="segmentation">
          {renderItems(segmentation, segLoading, 'Nenhum dado de segmentação disponível')}
        </TabsContent>
        <TabsContent value="discounts">
          {renderItems(discountRequests, drLoading, 'Nenhuma solicitação de desconto pendente')}
        </TabsContent>
      </Tabs>
    </div>
  )
}

export default SalesAdvancedPage
