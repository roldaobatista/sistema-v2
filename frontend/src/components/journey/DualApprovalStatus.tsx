import { cn } from '@/lib/utils'
import { CheckCircle2, Clock, XCircle, Lock } from 'lucide-react'
import type { JourneyDay } from '@/types/journey'

interface DualApprovalStatusProps {
  day: JourneyDay
  className?: string
}

const statusConfig = {
  pending: { icon: Clock, label: 'Pendente', color: 'text-yellow-600 bg-yellow-50 border-yellow-200' },
  approved: { icon: CheckCircle2, label: 'Aprovado', color: 'text-green-600 bg-green-50 border-green-200' },
  rejected: { icon: XCircle, label: 'Rejeitado', color: 'text-red-600 bg-red-50 border-red-200' },
}

export function DualApprovalStatus({ day, className }: DualApprovalStatusProps) {
  const opStatus = statusConfig[day.operational_approval_status as keyof typeof statusConfig] ?? statusConfig.pending
  const hrStatus = statusConfig[day.hr_approval_status as keyof typeof statusConfig] ?? statusConfig.pending
  const OpIcon = opStatus.icon
  const HrIcon = hrStatus.icon

  return (
    <div className={cn('flex items-center gap-4', className)}>
      <div className={cn('flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm', opStatus.color)}>
        <OpIcon className="h-4 w-4" aria-hidden="true" />
        <span className="font-medium">Operacional: {opStatus.label}</span>
      </div>

      <div className="text-muted-foreground">→</div>

      <div className={cn('flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm', hrStatus.color)}>
        <HrIcon className="h-4 w-4" aria-hidden="true" />
        <span className="font-medium">RH: {hrStatus.label}</span>
      </div>

      {day.is_closed && (
        <div className="flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-600">
          <Lock className="h-4 w-4" aria-hidden="true" />
          <span className="font-medium">Fechado</span>
        </div>
      )}
    </div>
  )
}
