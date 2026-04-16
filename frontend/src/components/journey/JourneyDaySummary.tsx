import type { JourneyDay } from '@/types/journey'
import { cn } from '@/lib/utils'
import { Clock, Timer, Car, Coffee, Moon, Phone, TrendingUp } from 'lucide-react'

interface JourneyDaySummaryProps {
  day: JourneyDay
  className?: string
}

function formatMinutes(minutes: number): string {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h === 0) return `${m}min`
  return m > 0 ? `${h}h${m}min` : `${h}h`
}

const statusLabels: Record<string, string> = {
  pending: 'Pendente',
  approved: 'Aprovado',
  rejected: 'Rejeitado',
}

const statusColors: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
}

export function JourneyDaySummary({ day, className }: JourneyDaySummaryProps) {
  const items = [
    { icon: Clock, label: 'Trabalhado', value: day.total_minutes_worked, color: 'text-green-600' },
    { icon: TrendingUp, label: 'Hora Extra', value: day.total_minutes_overtime, color: 'text-red-600' },
    { icon: Car, label: 'Deslocamento', value: day.total_minutes_travel, color: 'text-blue-600' },
    { icon: Timer, label: 'Espera', value: day.total_minutes_wait, color: 'text-amber-600' },
    { icon: Coffee, label: 'Intervalo', value: day.total_minutes_break, color: 'text-gray-600' },
    { icon: Moon, label: 'Pernoite', value: day.total_minutes_overnight, color: 'text-slate-600' },
    { icon: Phone, label: 'Sobreaviso', value: day.total_minutes_oncall, color: 'text-orange-600' },
  ]

  return (
    <div className={cn('rounded-lg border p-4', className)}>
      <div className="mb-3 flex items-center justify-between">
        <h3 className="font-semibold">
          {new Date(day.reference_date + 'T12:00:00').toLocaleDateString('pt-BR', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
          })}
        </h3>
        <div className="flex gap-2">
          <span
            className={cn(
              'rounded-full px-2 py-0.5 text-xs font-medium',
              statusColors[day.operational_approval_status],
            )}
          >
            Op: {statusLabels[day.operational_approval_status] ?? day.operational_approval_status}
          </span>
          <span
            className={cn(
              'rounded-full px-2 py-0.5 text-xs font-medium',
              statusColors[day.hr_approval_status],
            )}
          >
            RH: {statusLabels[day.hr_approval_status] ?? day.hr_approval_status}
          </span>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
        {items.map(({ icon: Icon, label, value, color }) => (
          <div key={label} className="flex items-center gap-1.5 text-sm">
            <Icon className={cn('h-4 w-4', color)} aria-hidden="true" />
            <div>
              <div className="text-xs text-muted-foreground">{label}</div>
              <div className="font-medium">{formatMinutes(value)}</div>
            </div>
          </div>
        ))}
      </div>

      {day.notes && (
        <p className="mt-2 text-sm text-muted-foreground">{day.notes}</p>
      )}
    </div>
  )
}
