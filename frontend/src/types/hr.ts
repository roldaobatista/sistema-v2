export interface Department {
    id: number
    name: string
    manager_id?: number
    parent_id?: number
    cost_center?: string
    manager?: { id: number; name: string; avatar?: string }
    parent?: Department
    children?: Department[]
    _count?: { users: number }
}

export interface Position {
    id: number
    name: string
    department_id: number
    level: 'junior' | 'pleno' | 'senior' | 'lead' | 'manager' | 'specialist'
    description?: string
    department?: Department
    _count?: { users: number }
}

export interface Skill {
    id: number
    name: string
    category: string
    description?: string
}

export interface UserSkill {
    id: number
    user_id: number
    skill_id: number
    current_level: number // 1-5
    assessed_at?: string
    skill?: Skill
    user?: { id: number; name: string; avatar?: string }
}

export interface PerformanceReview {
    id: number
    title: string
    user_id: number
    reviewer_id: number
    cycle: string
    status: 'draft' | 'scheduled' | 'in_progress' | 'completed' | 'canceled'
    deadline?: string
    completed_at?: string
    overall_rating?: number
    user?: { id: number; name: string; avatar?: string; position?: { name: string } }
    reviewer?: { id: number; name: string; avatar?: string }
    type: '180' | '360' | 'leader' | 'peer'
    nine_box_potential?: 'low' | 'medium' | 'high'
    nine_box_performance?: 'low' | 'medium' | 'high'
    created_at?: string
    ratings?: Record<string, number>
    feedback_text?: string
    action_plan?: string
    overall_score?: number
    potential_score?: number
    score?: number
}

export interface ContinuousFeedback {
    id: number
    from_user_id: number
    to_user_id: number
    message?: string
    content?: string
    type: 'praise' | 'guidance' | 'correction' | 'suggestion' | 'concern'
    visibility: 'public' | 'private' | 'manager_only'
    created_at: string
    fromUser?: { id: number; name: string; avatar?: string }
    toUser?: { id: number; name: string; avatar?: string }
}

// Time Clock
export interface TimeClockEntry {
  id: number;
  user_id: number;
  user?: { id: number; name: string; email: string };
  clock_in: string;
  clock_out: string | null;
  latitude_in: number | null;
  longitude_in: number | null;
  latitude_out: number | null;
  longitude_out: number | null;
  type: 'regular' | 'overtime' | 'travel';
  notes: string | null;
  selfie_path: string | null;
  liveness_score: number | null;
  liveness_passed: boolean;
  geofence_location_id: number | null;
  geofence_location?: GeofenceLocation;
  geofence_distance_meters: number | null;
  device_info: Record<string, unknown> | null;
  ip_address: string | null;
  clock_method: 'selfie' | 'qrcode' | 'manual' | 'auto_os';
  approval_status: 'auto_approved' | 'pending' | 'approved' | 'rejected';
  approved_by: number | null;
  rejection_reason: string | null;
  work_order_id: number | null;
  break_start: string | null;
  break_end: string | null;
  break_latitude: number | null;
  break_longitude: number | null;
  record_hash: string | null;
  nsr: number | null;
  duration_minutes: number | null;
  employee_confirmation_hash: string | null;
  confirmed_at: string | null;
  confirmation_method: 'selfie' | 'pin' | 'biometric' | 'manual' | null;
  created_at: string;
  updated_at: string;
}

export interface TimeClockAdjustment {
  id: number;
  time_clock_entry_id: number;
  entry?: TimeClockEntry;
  requested_by: number;
  requester?: { id: number; name: string };
  approved_by: number | null;
  approver?: { id: number; name: string };
  original_clock_in: string;
  original_clock_out: string | null;
  adjusted_clock_in: string;
  adjusted_clock_out: string | null;
  reason: string;
  status: 'pending' | 'approved' | 'rejected';
  rejection_reason: string | null;
  decided_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface GeofenceLocation {
  id: number;
  name: string;
  latitude: number;
  longitude: number;
  radius_meters: number;
  is_active: boolean;
  linked_entity_type: string | null;
  linked_entity_id: number | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface JourneyRule {
  id: number;
  name: string;
  daily_hours: number;
  weekly_hours: number;
  overtime_weekday_pct: number;
  overtime_weekend_pct: number;
  overtime_holiday_pct: number;
  night_shift_pct: number;
  night_start: string;
  night_end: string;
  uses_hour_bank: boolean;
  hour_bank_expiry_months: number;
  agreement_type: 'individual' | 'collective' | 'monthly';
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

export interface JourneyEntry {
  id: number;
  user_id: number;
  user?: { id: number; name: string };
  date: string;
  journey_rule_id: number | null;
  scheduled_hours: number;
  worked_hours: number;
  overtime_hours_50: number;
  overtime_hours_100: number;
  night_hours: number;
  absence_hours: number;
  hour_bank_balance: number;
  overtime_limit_exceeded: boolean;
  tolerance_applied: boolean;
  break_compliance: 'compliant' | 'short_break' | 'missing_break' | null;
  inter_shift_hours: number | null;
  is_holiday: boolean;
  is_dsr: boolean;
  status: 'calculated' | 'adjusted' | 'locked';
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface HourBankTransaction {
  id: number;
  user_id: number;
  journey_entry_id: number | null;
  type: 'accrual' | 'usage' | 'expiry' | 'payout';
  hours: number;
  balance_before: number;
  balance_after: number;
  reference_date: string;
  expired_at: string | null;
  payout_payroll_id: number | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface FiscalIntegrityResponse {
  total_entries: number;
  valid_entries: number;
  invalid_entries: number;
  chain_intact: boolean;
  violations: Array<{
    id: number;
    nsr: number;
    date: string;
    user_id: number;
  }>;
}

export interface Holiday {
  id: number;
  name: string;
  date: string;
  is_national: boolean;
  is_recurring: boolean;
  created_at: string;
  updated_at: string;
}

export interface LeaveRequest {
  id: number;
  user_id: number;
  user?: { id: number; name: string };
  type: 'vacation' | 'medical' | 'personal' | 'maternity' | 'paternity' | 'bereavement' | 'other';
  start_date: string;
  end_date: string;
  days_count: number;
  reason: string | null;
  document_path: string | null;
  status: 'pending' | 'approved' | 'rejected';
  approved_by: number | null;
  approver?: { id: number; name: string };
  approved_at: string | null;
  rejection_reason: string | null;
  created_at: string;
  updated_at: string;
}

export interface VacationBalance {
  id: number;
  user_id: number;
  user?: { id: number; name: string };
  acquisition_start: string;
  acquisition_end: string;
  total_days: number;
  taken_days: number;
  sold_days: number;
  remaining_days: number;
  deadline: string;
  status: 'accruing' | 'available' | 'partially_taken' | 'taken' | 'expired';
  created_at: string;
  updated_at: string;
}

export interface EmployeeDocument {
  id: number;
  user_id: number;
  user?: { id: number; name: string };
  category: 'aso' | 'nr' | 'contract' | 'license' | 'certification' | 'id_doc' | 'other';
  name: string;
  file_path: string;
  expiry_date: string | null;
  issued_date: string | null;
  issuer: string | null;
  is_mandatory: boolean;
  status: 'valid' | 'expiring' | 'expired' | 'pending';
  notes: string | null;
  uploaded_by: number | null;
  created_at: string;
  updated_at: string;
}

export interface EmployeeBenefit {
  id: number;
  user_id: number;
  user?: { id: number; name: string };
  type: string;
  provider: string | null;
  value: number;
  employee_contribution: number;
  start_date: string | null;
  end_date: string | null;
  is_active: boolean;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface OnboardingTemplate {
  id: number;
  name: string;
  type: string;
  default_tasks: string[];
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface OnboardingChecklist {
  id: number;
  user_id: number;
  user?: { id: number; name: string };
  onboarding_template_id: number;
  template?: OnboardingTemplate;
  started_at: string | null;
  completed_at: string | null;
  status: 'in_progress' | 'completed' | 'cancelled';
  progress: number;
  items?: OnboardingChecklistItem[];
  created_at: string;
  updated_at: string;
}

export interface OnboardingChecklistItem {
  id: number;
  onboarding_checklist_id: number;
  title: string;
  description: string | null;
  responsible_id: number | null;
  responsible?: { id: number; name: string };
  is_completed: boolean;
  completed_at: string | null;
  completed_by: number | null;
  order: number;
}

export interface Payroll {
  id: number
  tenant_id: number
  reference_month: string
  type: 'regular' | 'thirteenth_first' | 'thirteenth_second' | 'vacation' | 'rescission' | 'advance'
  status: 'draft' | 'calculated' | 'approved' | 'paid' | 'cancelled'
  total_gross: number
  total_deductions: number
  total_net: number
  total_fgts: number
  total_inss_employer: number
  employee_count: number
  calculated_by: number | null
  approved_by: number | null
  calculated_at: string | null
  approved_at: string | null
  paid_at: string | null
  notes: string | null
  calculated_by_user?: { id: number; name: string }
  approved_by_user?: { id: number; name: string }
  lines?: PayrollLine[]
  created_at: string
  updated_at: string
}

export interface PayrollLine {
  id: number
  payroll_id: number
  user_id: number
  tenant_id: number
  user?: { id: number; name: string; email?: string }
  gross_salary: number
  net_salary: number
  base_salary: number
  overtime_50_hours: number
  overtime_50_value: number
  overtime_100_hours: number
  overtime_100_value: number
  night_hours: number
  night_shift_value: number
  dsr_value: number
  commission_value: number
  bonus_value: number
  other_earnings: number
  inss_employee: number
  irrf: number
  transportation_discount: number
  meal_discount: number
  health_insurance_discount: number
  other_deductions: number
  advance_discount: number
  fgts_value: number
  inss_employer_value: number
  worked_days: number
  absence_days: number
  absence_value: number
  vacation_days: number
  vacation_value: number
  vacation_bonus: number
  thirteenth_value: number
  thirteenth_months: number
  vt_deduction: number
  vr_deduction: number
  hour_bank_payout_hours: number
  hour_bank_payout_value: number
  advance_deduction: number
  status: 'calculated' | 'reviewed' | 'approved'
  notes: string | null
  payslip?: PayslipRecord
  created_at: string
  updated_at: string
}

export interface PayslipRecord {
  id: number
  payroll_line_id: number
  user_id: number
  tenant_id: number
  reference_month: string
  file_path: string | null
  sent_at: string | null
  viewed_at: string | null
  digital_signature_hash: string | null
  payroll_line?: PayrollLine
  created_at: string
  updated_at: string
}

export interface Rescission {
  id: number;
  user_id: number;
  user?: { id: number; name: string; email?: string; cpf?: string; admission_date?: string; salary?: number };
  type: 'sem_justa_causa' | 'justa_causa' | 'pedido_demissao' | 'acordo_mutuo' | 'termino_contrato';
  notice_date: string | null;
  termination_date: string;
  last_work_day: string | null;
  notice_type: 'worked' | 'indemnified' | 'waived' | null;
  notice_days: number;
  notice_value: number;
  salary_balance_days: number;
  salary_balance_value: number;
  vacation_proportional_days: number;
  vacation_proportional_value: number;
  vacation_bonus_value: number;
  vacation_overdue_days: number;
  vacation_overdue_value: number;
  vacation_overdue_bonus_value: number;
  thirteenth_proportional_months: number;
  thirteenth_proportional_value: number;
  fgts_balance: number;
  fgts_penalty_value: number;
  fgts_penalty_rate: number;
  other_earnings: number;
  other_deductions: number;
  advance_deductions: number;
  hour_bank_payout: number;
  inss_deduction: number;
  irrf_deduction: number;
  total_gross: number;
  total_deductions: number;
  total_net: number;
  status: 'draft' | 'calculated' | 'approved' | 'paid' | 'cancelled';
  calculated_by: number | null;
  approved_by: number | null;
  calculated_at: string | null;
  approved_at: string | null;
  paid_at: string | null;
  trct_file_path: string | null;
  notes: string | null;
  calculated_by_user?: { id: number; name: string };
  approved_by_user?: { id: number; name: string };
  created_at: string;
  updated_at: string;
}

export interface ClockComprovante {
  employee_name: string;
  pis: string | null;
  cpf: string | null;
  date: string;
  type: string;
  time: string;
  location: string;
  nsr: number | null;
  hash: string | null;
  clock_method: string;
}

export interface EspelhoPonto {
  employee: {
    id: number;
    name: string;
    pis: string | null;
    cpf: string | null;
    admission_date: string | null;
    work_shift: string | null;
    cbo_code: string | null;
  };
  period: {
    year: number;
    month: number;
    month_name: string;
    start_date: string;
    end_date: string;
  };
  days: Array<{
    date: string;
    day_of_week: string;
    total_hours: number;
    total_break_minutes: number;
    entries: Array<{
      id: number;
      clock_in: string | null;
      clock_out: string | null;
      break_start: string | null;
      break_end: string | null;
      clock_method: string;
      approval_status: string;
      worked_minutes?: number | null;
      break_minutes?: number;
    }>;
  }>;
  summary: {
    total_work_days: number;
    total_hours: number;
    total_minutes: number;
    total_break_minutes: number;
    average_hours_per_day: number;
  };
  confirmation?: {
    confirmed_at: string;
    content_hash: string;
  } | null;
}

// ── eSocial ──

export interface ESocialEvent {
  id: number
  event_type: string
  related_type: string | null
  related_id: number | null
  status: 'pending' | 'generating' | 'sent' | 'accepted' | 'rejected' | 'cancelled'
  xml_content: string | null
  protocol_number: string | null
  receipt_number: string | null
  response_xml: string | null
  error_message: string | null
  batch_id: string | null
  environment: 'production' | 'restricted'
  version: string
  sent_at: string | null
  response_at: string | null
  created_at: string
  updated_at: string
}

export interface ESocialCertificate {
  id: number
  serial_number: string | null
  issuer: string | null
  valid_from: string | null
  valid_until: string | null
  is_active: boolean
  is_expired: boolean
  created_at: string
  updated_at: string
}

export interface ESocialDashboard {
  counts: {
    pending: number
    sent: number
    accepted: number
    rejected: number
    total: number
  }
  by_type: Record<string, number>
  recent_events: ESocialEvent[]
  certificate: {
    id: number
    serial_number: string | null
    issuer: string | null
    valid_until: string | null
    is_expired: boolean
    is_active: boolean
  } | null
}

// ── Dashboard HR Widgets ──

export interface HrDashboardWidgets {
  employees_clocked_in: number
  total_active_employees: number
  pending_adjustments: number
  pending_leaves: number
  expiring_documents_30d: number
  expiring_vacations_60d: number
  hour_bank_positive_hours: number
  hour_bank_negative_hours: number
  current_payroll: {
    id: number
    reference_month: string
    type: string
    status: string
  } | null
  birthdays_this_month: Array<{
    id: number
    name: string
    birth_date: string
  }>
}

export interface HrTeamMember {
  id: number
  name: string
  has_clocked_in_today: boolean
  is_on_break: boolean
  is_on_leave: boolean
  clock_in_time: string | null
}

// ── Relatórios HR ──

export interface PayrollCostReport {
  reference_month: string
  total_gross: number
  total_net: number
  total_deductions: number
  total_fgts: number
  total_inss_employer: number
  total_cost: number
  employee_count: number
  types: string[]
}

export interface OvertimeTrendReport {
  monthly_trend: Array<{
    month: string
    total_ot50_hours: number
    total_ot100_hours: number
    total_overtime_hours: number
    employees_with_overtime: number
  }>
  top_overtime_employees: Array<{
    user_id: number
    name: string
    total_hours: number
  }>
}

export interface TaxObligationsReport {
  reference_month: string
  payroll_status: string
  employee_count: number
  total_gross: number
  total_net: number
  inss_employee_total: number
  inss_employer_total: number
  irrf_total: number
  fgts_total: number
  total_labor_cost: number
  year_accumulated: {
    total_gross: number
    total_inss_employer: number
    total_fgts: number
    total_irrf: number
    total_labor_cost: number
  }
}

export interface EmployeeDependent {
  id: number;
  tenant_id: number;
  user_id: number;
  name: string;
  cpf: string | null;
  birth_date: string;
  relationship: 'filho' | 'conjuge' | 'pais' | 'other';
  is_irrf_dependent: boolean;
  is_benefit_dependent: boolean;
  start_date: string | null;
  end_date: string | null;
  created_at: string;
  updated_at: string;
}

export interface TimeClockAuditLog {
  id: number;
  tenant_id: number;
  time_clock_entry_id: number | null;
  time_clock_adjustment_id: number | null;
  action: string;
  performed_by: number;
  performer?: { id: number; name: string };
  ip_address: string | null;
  user_agent: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

export interface ESocialRubric {
  id: number;
  tenant_id: number;
  code: string;
  description: string;
  nature: 'provento' | 'desconto' | 'informativa';
  type: string;
  incidence_inss: boolean;
  incidence_irrf: boolean;
  incidence_fgts: boolean;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface TaxTableData {
  inss: Array<{ id: number; year: number; min_salary: number; max_salary: number; rate: number; deduction: number }>;
  irrf: Array<{ id: number; year: number; min_salary: number; max_salary: number; rate: number; deduction: number }>;
  minimum_wage: { id: number; year: number; month: number; value: number } | null;
}

export interface HashChainVerificationResult {
  is_valid: boolean;
  total_records: number;
  valid_count: number;
  invalid_count: number;
  broken_chain_at: number | null;
  nsr_gaps: number[];
  details: Array<{ nsr: number; id: number; hash_valid: boolean; chain_valid: boolean }>;
  verified_at: string;
}
