import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CalendarDays, RefreshCw, Loader2, ChevronLeft, ChevronRight } from 'lucide-react'
import { journeyApi } from '@/lib/journey-api'
import { JourneyTimeline } from '@/components/journey/JourneyTimeline'
import { JourneyDaySummary } from '@/components/journey/JourneyDaySummary'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { toast } from 'sonner'
import type { JourneyDay, JourneyDayFilters } from '@/types/journey'

export default function JourneyDayPage() {
  const qc = useQueryClient()
  const [filters, setFilters] = useState<JourneyDayFilters>({
    per_page: 10,
  })
  const [selectedDayId, setSelectedDayId] = useState<number | null>(null)
  const [page, setPage] = useState(1)

  const { data: listData, isLoading: listLoading } = useQuery({
    queryKey: ['journey-days', filters, page],
    queryFn: () =>
      journeyApi.listDays({ ...filters, per_page: filters.per_page }),
  })

  const { data: dayDetail, isLoading: detailLoading } = useQuery({
    queryKey: ['journey-day', selectedDayId],
    queryFn: () => journeyApi.showDay(selectedDayId!),
    enabled: !!selectedDayId,
  })

  const reclassifyMut = useMutation({
    mutationFn: (id: number) => journeyApi.reclassifyDay(id),
    onSuccess: () => {
      toast.success('Jornada reclassificada com sucesso')
      qc.invalidateQueries({ queryKey: ['journey-days'] })
      if (selectedDayId) {
        qc.invalidateQueries({ queryKey: ['journey-day', selectedDayId] })
      }
    },
    onError: () => toast.error('Erro ao reclassificar jornada'),
  })

  const days = listData?.data ?? []
  const totalPages = listData?.last_page ?? 1

  return (
    <div className="space-y-6">
      <PageHeader
        title="Motor de Jornada"
        subtitle="Timeline de classificação do tempo dos técnicos"
        icon={CalendarDays}
      />

      {/* Filtros */}
      <div className="flex flex-wrap gap-3">
        <input
          type="date"
          className="rounded-md border px-3 py-1.5 text-sm"
          aria-label="Data início"
          value={filters.date_from ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value || undefined }))}
        />
        <input
          type="date"
          className="rounded-md border px-3 py-1.5 text-sm"
          aria-label="Data fim"
          value={filters.date_to ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value || undefined }))}
        />
        <select
          className="rounded-md border px-3 py-1.5 text-sm"
          aria-label="Status aprovação"
          value={filters.approval_status ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              approval_status: (e.target.value || undefined) as JourneyDayFilters['approval_status'],
            }))
          }
        >
          <option value="">Todos os status</option>
          <option value="pending">Pendente</option>
          <option value="approved">Aprovado</option>
          <option value="rejected">Rejeitado</option>
        </select>
      </div>

      {/* Lista de dias */}
      {listLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : days.length === 0 ? (
        <div className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
          Nenhuma jornada encontrada para os filtros selecionados.
        </div>
      ) : (
        <div className="space-y-3">
          {days.map((day: JourneyDay) => (
            <div key={day.id}>
              <button
                type="button"
                className="w-full text-left"
                onClick={() => setSelectedDayId(selectedDayId === day.id ? null : day.id)}
                aria-label={`Ver detalhes da jornada de ${day.reference_date}`}
              >
                <JourneyDaySummary day={day} />
              </button>

              {selectedDayId === day.id && (
                <div className="mt-2 ml-4 space-y-3">
                  <div className="flex items-center gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => reclassifyMut.mutate(day.id)}
                      disabled={reclassifyMut.isPending}
                      aria-label="Reclassificar jornada"
                    >
                      <RefreshCw className="mr-1 h-3.5 w-3.5" />
                      Reclassificar
                    </Button>
                  </div>

                  {detailLoading ? (
                    <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
                  ) : dayDetail ? (
                    <JourneyTimeline blocks={dayDetail.blocks} />
                  ) : null}
                </div>
              )}
            </div>
          ))}

          {/* Paginação */}
          {totalPages > 1 && (
            <div className="flex items-center justify-center gap-2 pt-4">
              <Button
                size="sm"
                variant="outline"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
                aria-label="Página anterior"
              >
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <span className="text-sm text-muted-foreground">
                {page} / {totalPages}
              </span>
              <Button
                size="sm"
                variant="outline"
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                disabled={page >= totalPages}
                aria-label="Próxima página"
              >
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
