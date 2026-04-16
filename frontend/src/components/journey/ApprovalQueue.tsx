import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, XCircle, Loader2 } from 'lucide-react'
import { journeyApprovalApi } from '@/lib/journey-approval-api'
import { JourneyDaySummary } from './JourneyDaySummary'
import { JourneyTimeline } from './JourneyTimeline'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'
import type { JourneyDay } from '@/types/journey'
import { getApiErrorMessage } from '@/lib/api'

interface ApprovalQueueProps {
  level: 'operational' | 'hr'
}

export function ApprovalQueue({ level }: ApprovalQueueProps) {
  const qc = useQueryClient()
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [rejectingId, setRejectingId] = useState<number | null>(null)
  const [rejectReason, setRejectReason] = useState('')

  const levelLabel = level === 'operational' ? 'Operacional' : 'RH'

  const { data, isLoading } = useQuery({
    queryKey: ['journey-approvals', level],
    queryFn: () => journeyApprovalApi.listPending(level),
  })

  const approveMut = useMutation({
    mutationFn: (id: number) => journeyApprovalApi.approve(id, level),
    onSuccess: () => {
      toast.success(`Aprovação ${levelLabel} realizada`)
      qc.invalidateQueries({ queryKey: ['journey-approvals'] })
    },
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao aprovar')),
  })

  const rejectMut = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      journeyApprovalApi.reject(id, level, reason),
    onSuccess: () => {
      toast.success(`Jornada rejeitada (${levelLabel})`)
      setRejectingId(null)
      setRejectReason('')
      qc.invalidateQueries({ queryKey: ['journey-approvals'] })
    },
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao rejeitar')),
  })

  const days = data?.data ?? []

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (days.length === 0) {
    return (
      <div className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
        Nenhuma jornada pendente de aprovação {levelLabel}.
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {days.map((day: JourneyDay) => (
        <div key={day.id} className="rounded-lg border">
          <button
            type="button"
            className="w-full text-left"
            onClick={() => setExpandedId(expandedId === day.id ? null : day.id)}
            aria-label={`Expandir jornada de ${day.reference_date}`}
          >
            <JourneyDaySummary day={day} />
          </button>

          {expandedId === day.id && (
            <div className="border-t p-4 space-y-3">
              <JourneyTimeline blocks={day.blocks ?? []} />

              <div className="flex items-center gap-2 pt-2">
                <Button
                  size="sm"
                  onClick={() => approveMut.mutate(day.id)}
                  disabled={approveMut.isPending}
                  aria-label="Aprovar jornada"
                >
                  <CheckCircle2 className="mr-1 h-4 w-4" />
                  Aprovar
                </Button>

                {rejectingId === day.id ? (
                  <div className="flex items-center gap-2">
                    <input
                      type="text"
                      className="rounded-md border px-2 py-1 text-sm"
                      placeholder="Motivo da rejeição..."
                      value={rejectReason}
                      onChange={(e) => setRejectReason(e.target.value)}
                      aria-label="Motivo da rejeição"
                    />
                    <Button
                      size="sm"
                      variant="destructive"
                      onClick={() => rejectMut.mutate({ id: day.id, reason: rejectReason })}
                      disabled={rejectMut.isPending || !rejectReason.trim()}
                      aria-label="Confirmar rejeição"
                    >
                      Confirmar
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => { setRejectingId(null); setRejectReason('') }}
                      aria-label="Cancelar rejeição"
                    >
                      Cancelar
                    </Button>
                  </div>
                ) : (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setRejectingId(day.id)}
                    aria-label="Rejeitar jornada"
                  >
                    <XCircle className="mr-1 h-4 w-4" />
                    Rejeitar
                  </Button>
                )}
              </div>
            </div>
          )}
        </div>
      ))}
    </div>
  )
}
