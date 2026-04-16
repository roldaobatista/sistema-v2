export interface TravelRequest {
  id: number
  user_id: number
  user?: { id: number; name: string }
  approved_by: number | null
  status: 'pending' | 'approved' | 'in_progress' | 'completed' | 'cancelled'
  destination: string
  purpose: string
  departure_date: string
  return_date: string
  departure_time: string | null
  return_time: string | null
  estimated_days: number
  daily_allowance_amount: number | null
  total_advance_requested: number | null
  requires_vehicle: boolean
  fleet_vehicle_id: number | null
  requires_overnight: boolean
  rest_days_after: number
  overtime_authorized: boolean
  work_orders: number[] | null
  itinerary: Record<string, unknown>[] | null
  meal_policy: Record<string, unknown> | null
  overnight_stays?: OvernightStay[]
  advances?: TravelAdvance[]
  expense_report?: TravelExpenseReport | null
  created_at: string
  updated_at: string
}

export interface OvernightStay {
  id: number
  travel_request_id: number
  stay_date: string
  hotel_name: string | null
  city: string
  state: string | null
  cost: number | null
  receipt_path: string | null
  status: string
}

export interface TravelAdvance {
  id: number
  travel_request_id: number
  amount: number
  status: 'pending' | 'approved' | 'paid' | 'accounted'
  paid_at: string | null
  notes: string | null
}

export interface TravelExpenseReport {
  id: number
  travel_request_id: number
  total_expenses: number
  total_advances: number
  balance: number
  status: 'draft' | 'submitted' | 'approved' | 'rejected'
  items?: TravelExpenseItem[]
}

export interface TravelExpenseItem {
  id: number
  type: 'alimentacao' | 'transporte' | 'hospedagem' | 'pedagio' | 'combustivel' | 'outros'
  description: string
  amount: number
  expense_date: string
  receipt_path: string | null
  is_within_policy: boolean
}

export const TRAVEL_STATUS_LABELS: Record<string, string> = {
  pending: 'Pendente',
  approved: 'Aprovada',
  in_progress: 'Em Andamento',
  completed: 'Concluída',
  cancelled: 'Cancelada',
}

export const TRAVEL_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  in_progress: 'bg-blue-100 text-blue-800',
  completed: 'bg-slate-100 text-slate-800',
  cancelled: 'bg-red-100 text-red-800',
}

export const EXPENSE_TYPE_LABELS: Record<string, string> = {
  alimentacao: 'Alimentação',
  transporte: 'Transporte',
  hospedagem: 'Hospedagem',
  pedagio: 'Pedágio',
  combustivel: 'Combustível',
  outros: 'Outros',
}
