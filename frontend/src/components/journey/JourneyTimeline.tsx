import { CLASSIFICATION_COLORS, type JourneyBlock, type TimeClassification } from '@/types/journey'
import { cn } from '@/lib/utils'

interface JourneyTimelineProps {
  blocks: JourneyBlock[]
  className?: string
}

function formatMinutes(minutes: number | null): string {
  if (!minutes) return '0min'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h === 0) return `${m}min`
  return m > 0 ? `${h}h${m}min` : `${h}h`
}

function formatTime(iso: string | null): string {
  if (!iso) return '--:--'
  return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

export function JourneyTimeline({ blocks, className }: JourneyTimelineProps) {
  if (blocks.length === 0) {
    return (
      <div className={cn('rounded-lg border border-dashed p-8 text-center text-muted-foreground', className)}>
        Nenhum bloco de jornada registrado para este dia.
      </div>
    )
  }

  return (
    <div className={cn('space-y-1', className)}>
      {blocks.map((block) => {
        const color = CLASSIFICATION_COLORS[block.classification as TimeClassification] ?? '#94a3b8'
        return (
          <div
            key={block.id}
            className="flex items-center gap-3 rounded-md border px-3 py-2 text-sm"
            style={{ borderLeftColor: color, borderLeftWidth: 4 }}
          >
            <div
              className="h-3 w-3 shrink-0 rounded-full"
              style={{ backgroundColor: color }}
              aria-hidden="true"
            />
            <div className="min-w-[90px] font-medium">
              {formatTime(block.started_at)} - {formatTime(block.ended_at)}
            </div>
            <div className="flex-1">
              <span className="font-medium">{block.classification_label}</span>
              {block.work_order_id && (
                <span className="ml-2 text-xs text-muted-foreground">OS #{block.work_order_id}</span>
              )}
              {block.is_manually_adjusted && (
                <span className="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">
                  Ajuste manual
                </span>
              )}
            </div>
            <div className="text-right text-muted-foreground">
              {formatMinutes(block.duration_minutes)}
            </div>
          </div>
        )
      })}
    </div>
  )
}
