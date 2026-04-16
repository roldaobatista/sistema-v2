import api from './api'

export interface PayrollMonthSummary {
  user_id: number
  user_name: string
  year_month: string
  working_days: number
  total_worked_hours: number
  total_overtime_hours: number
  total_travel_hours: number
  total_break_hours: number
  total_oncall_hours: number
  total_overnight_hours: number
  all_days_closed: boolean
  all_days_approved: boolean
}

export interface BlockingDay {
  journey_day_id: number
  user_id: number
  user_name: string
  reference_date: string
  operational_status: string
  hr_status: string
}

export interface ESocialGenerateResult {
  [eventType: string]: {
    generated: number
    event_ids: number[]
  }
}

export const payrollJourneyApi = {
  monthSummary: (yearMonth: string) =>
    api.get<{ data: PayrollMonthSummary[] }>('/journey/payroll/month-summary', { params: { year_month: yearMonth } })
      .then((r) => r.data.data),

  blockingDays: (yearMonth: string) =>
    api.get<{ data: BlockingDay[] }>('/journey/payroll/blocking-days', { params: { year_month: yearMonth } })
      .then((r) => r.data.data),

  generateESocial: (yearMonth: string, eventTypes: string[]) =>
    api.post<{ data: ESocialGenerateResult }>('/journey/esocial/generate', { year_month: yearMonth, event_types: eventTypes })
      .then((r) => r.data.data),
}
