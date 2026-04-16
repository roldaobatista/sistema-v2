import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plane, Plus, Eye, CheckCircle2, XCircle, Loader2, MapPin, Calendar } from 'lucide-react'
import { travelApi } from '@/lib/travel-api'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import type { TravelRequest } from '@/types/travel'
import { TRAVEL_STATUS_LABELS, TRAVEL_STATUS_COLORS } from '@/types/travel'

export default function TravelRequestsPage() {
  const qc = useQueryClient()
  const [selectedId, setSelectedId] = useState<number | null>(null)

  const { data: listData, isLoading } = useQuery({
    queryKey: ['travel-requests'],
    queryFn: () => travelApi.list({ per_page: 25 }),
  })

  const { data: detail, isLoading: detailLoading } = useQuery({
    queryKey: ['travel-request', selectedId],
    queryFn: () => travelApi.show(selectedId!),
    enabled: !!selectedId,
  })

  const approveMut = useMutation({
    mutationFn: (id: number) => travelApi.approve(id),
    onSuccess: () => {
      toast.success('Viagem aprovada')
      qc.invalidateQueries({ queryKey: ['travel-requests'] })
    },
    onError: () => toast.error('Erro ao aprovar'),
  })

  const cancelMut = useMutation({
    mutationFn: (id: number) => travelApi.cancel(id),
    onSuccess: () => {
      toast.success('Viagem cancelada')
      qc.invalidateQueries({ queryKey: ['travel-requests'] })
    },
    onError: () => toast.error('Erro ao cancelar'),
  })

  const requests = listData?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="Solicitações de Viagem" subtitle="Gerenciar viagens e deslocamentos de técnicos" icon={Plane} />

      <div className="flex justify-end">
        <Button size="sm" aria-label="Nova solicitação de viagem">
          <Plus className="mr-1 h-4 w-4" />
          Nova Viagem
        </Button>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : requests.length === 0 ? (
        <div className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
          Nenhuma solicitação de viagem encontrada.
        </div>
      ) : (
        <div className="space-y-3">
          {requests.map((tr: TravelRequest) => (
            <div key={tr.id} className="rounded-lg border">
              <div className="flex items-center justify-between p-4">
                <div className="flex items-center gap-4">
                  <div>
                    <div className="flex items-center gap-2">
                      <MapPin className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                      <span className="font-medium">{tr.destination}</span>
                      <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', TRAVEL_STATUS_COLORS[tr.status])}>
                        {TRAVEL_STATUS_LABELS[tr.status] ?? tr.status}
                      </span>
                    </div>
                    <div className="mt-1 flex items-center gap-3 text-sm text-muted-foreground">
                      <span>{tr.user?.name}</span>
                      <span className="flex items-center gap-1">
                        <Calendar className="h-3.5 w-3.5" aria-hidden="true" />
                        {new Date(tr.departure_date + 'T12:00:00').toLocaleDateString('pt-BR')} — {new Date(tr.return_date + 'T12:00:00').toLocaleDateString('pt-BR')}
                      </span>
                      <span>{tr.estimated_days} dia(s)</span>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">{tr.purpose}</p>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <Button size="icon" variant="ghost" className="h-8 w-8" onClick={() => setSelectedId(selectedId === tr.id ? null : tr.id)} aria-label="Ver detalhes">
                    <Eye className="h-4 w-4" />
                  </Button>
                  {tr.status === 'pending' && (
                    <>
                      <Button size="sm" onClick={() => approveMut.mutate(tr.id)} disabled={approveMut.isPending} aria-label="Aprovar viagem">
                        <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                        Aprovar
                      </Button>
                      <Button size="sm" variant="outline" onClick={() => cancelMut.mutate(tr.id)} disabled={cancelMut.isPending} aria-label="Cancelar viagem">
                        <XCircle className="mr-1 h-3.5 w-3.5" />
                        Cancelar
                      </Button>
                    </>
                  )}
                </div>
              </div>

              {selectedId === tr.id && detail && !detailLoading && (
                <div className="border-t p-4 space-y-4">
                  {detail.overnight_stays && detail.overnight_stays.length > 0 && (
                    <div>
                      <h4 className="text-sm font-semibold mb-2">Pernoites</h4>
                      <div className="space-y-1">
                        {detail.overnight_stays.map((stay) => (
                          <div key={stay.id} className="flex items-center justify-between text-sm rounded border px-3 py-1.5">
                            <span>{new Date(stay.stay_date + 'T12:00:00').toLocaleDateString('pt-BR')} — {stay.city}{stay.state ? `/${stay.state}` : ''}</span>
                            <span>{stay.hotel_name}</span>
                            <span className="font-medium">R$ {Number(stay.cost ?? 0).toFixed(2)}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {detail.advances && detail.advances.length > 0 && (
                    <div>
                      <h4 className="text-sm font-semibold mb-2">Adiantamentos</h4>
                      <div className="space-y-1">
                        {detail.advances.map((adv) => (
                          <div key={adv.id} className="flex items-center justify-between text-sm rounded border px-3 py-1.5">
                            <span className="capitalize">{adv.status}</span>
                            <span className="font-medium">R$ {Number(adv.amount).toFixed(2)}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {detail.expense_report && (
                    <div>
                      <h4 className="text-sm font-semibold mb-2">Prestação de Contas</h4>
                      <div className="grid grid-cols-3 gap-3 text-sm">
                        <div className="rounded border p-2 text-center">
                          <div className="text-xs text-muted-foreground">Despesas</div>
                          <div className="font-bold">R$ {Number(detail.expense_report.total_expenses).toFixed(2)}</div>
                        </div>
                        <div className="rounded border p-2 text-center">
                          <div className="text-xs text-muted-foreground">Adiantamentos</div>
                          <div className="font-bold">R$ {Number(detail.expense_report.total_advances).toFixed(2)}</div>
                        </div>
                        <div className="rounded border p-2 text-center">
                          <div className="text-xs text-muted-foreground">Saldo</div>
                          <div className={cn('font-bold', Number(detail.expense_report.balance) >= 0 ? 'text-green-600' : 'text-red-600')}>
                            R$ {Number(detail.expense_report.balance).toFixed(2)}
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
