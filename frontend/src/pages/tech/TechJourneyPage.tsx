import { useQuery } from '@tanstack/react-query'
import { CalendarDays, Loader2 } from 'lucide-react'
import { journeyApi } from '@/lib/journey-api'
import { JourneyTimeline } from '@/components/journey/JourneyTimeline'
import { JourneyDaySummary } from '@/components/journey/JourneyDaySummary'
import { DualApprovalStatus } from '@/components/journey/DualApprovalStatus'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import type { JourneyDay } from '@/types/journey'
import { useState } from 'react'

export default function TechJourneyPage() {
  const { user } = useAuthStore()
  const [selectedId, setSelectedId] = useState<number | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['tech-journey-days', user?.id],
    queryFn: () =>
      journeyApi.listDays({ user_id: user?.id, per_page: 14 }),
    enabled: !!user?.id,
  })

  const { data: detail } = useQuery({
    queryKey: ['tech-journey-day', selectedId],
    queryFn: () => journeyApi.showDay(selectedId!),
    enabled: !!selectedId,
  })

  const days = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Minha Jornada"
        subtitle="Visualize a classificação do seu tempo de trabalho"
        icon={CalendarDays}
      />

      {isLoading ? (
        <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground" />
      ) : days.length === 0 ? (
        <div className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
          Nenhuma jornada registrada nos últimos dias.
        </div>
      ) : (
        <div className="space-y-3">
          {days.map((day: JourneyDay) => (
            <div key={day.id}>
              <button
                type="button"
                className="w-full text-left"
                onClick={() => setSelectedId(selectedId === day.id ? null : day.id)}
                aria-label={`Ver detalhes de ${day.reference_date}`}
              >
                <JourneyDaySummary day={day} />
              </button>

              {selectedId === day.id && detail && (
                <div className="mt-2 ml-4 space-y-3">
                  <DualApprovalStatus day={detail} />
                  <JourneyTimeline blocks={detail.blocks ?? []} />
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
