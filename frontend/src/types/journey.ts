export interface JourneyBlock {
  id: number
  journey_day_id: number
  user_id: number
  classification: string
  classification_label: string
  is_work_time: boolean
  is_paid_time: boolean
  started_at: string
  ended_at: string | null
  duration_minutes: number | null
  work_order_id: number | null
  time_clock_entry_id: number | null
  fleet_trip_id: number | null
  schedule_id: number | null
  source: string
  is_auto_classified: boolean
  is_manually_adjusted: boolean
  adjusted_by: number | null
  adjustment_reason: string | null
  metadata: Record<string, unknown> | null
  created_at: string
}

export interface JourneyDay {
  id: number
  user_id: number
  user?: { id: number; name: string }
  reference_date: string
  regime_type: string
  total_minutes_worked: number
  total_minutes_overtime: number
  total_minutes_travel: number
  total_minutes_wait: number
  total_minutes_break: number
  total_minutes_overnight: number
  total_minutes_oncall: number
  operational_approval_status: string
  operational_approver_id: number | null
  operational_approved_at: string | null
  hr_approval_status: string
  hr_approver_id: number | null
  hr_approved_at: string | null
  is_closed: boolean
  is_pending_approval: boolean
  is_fully_approved: boolean
  notes: string | null
  blocks: JourneyBlock[]
  created_at: string
  updated_at: string
}

export interface JourneyPolicy {
  id: number
  name: string
  regime_type: string
  daily_hours_limit: number
  weekly_hours_limit: number
  monthly_hours_limit: number | null
  break_minutes: number
  displacement_counts_as_work: boolean
  wait_time_counts_as_work: boolean
  travel_meal_counts_as_break: boolean
  auto_suggest_clock_on_displacement: boolean
  pre_assigned_break: boolean
  overnight_min_hours: number
  oncall_multiplier_percent: number
  overtime_50_percent_limit: number | null
  overtime_100_percent_limit: number | null
  saturday_is_overtime: boolean
  sunday_is_overtime: boolean
  custom_rules: Record<string, unknown> | null
  is_default: boolean
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface JourneyDayFilters {
  user_id?: number
  date_from?: string
  date_to?: string
  approval_status?: 'pending' | 'approved' | 'rejected'
  is_closed?: boolean
  per_page?: number
}

export type TimeClassification =
  | 'jornada_normal'
  | 'hora_extra'
  | 'intervalo'
  | 'deslocamento_cliente'
  | 'deslocamento_entre'
  | 'espera_local'
  | 'execucao_servico'
  | 'almoco_viagem'
  | 'pernoite'
  | 'sobreaviso'
  | 'plantao'
  | 'tempo_improdutivo'
  | 'ausencia'
  | 'atestado'
  | 'folga'
  | 'compensacao'
  | 'adicional_noturno'
  | 'dsr'

export const CLASSIFICATION_COLORS: Record<TimeClassification, string> = {
  jornada_normal: '#22c55e',
  hora_extra: '#ef4444',
  intervalo: '#94a3b8',
  deslocamento_cliente: '#3b82f6',
  deslocamento_entre: '#60a5fa',
  espera_local: '#f59e0b',
  execucao_servico: '#0d9488',
  almoco_viagem: '#a1a1aa',
  pernoite: '#1e293b',
  sobreaviso: '#f97316',
  plantao: '#e11d48',
  tempo_improdutivo: '#d4d4d8',
  ausencia: '#fca5a5',
  atestado: '#fbbf24',
  folga: '#a3e635',
  compensacao: '#67e8f9',
  adicional_noturno: '#059669',
  dsr: '#2dd4bf',
}
