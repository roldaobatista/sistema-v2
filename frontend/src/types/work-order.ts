export type WorkOrderStatus =
  | 'open'
  | 'awaiting_dispatch'
  | 'in_displacement'
  | 'displacement_paused'
  | 'at_client'
  | 'in_service'
  | 'service_paused'
  | 'awaiting_return'
  | 'in_return'
  | 'return_paused'
  | 'in_progress'
  | 'waiting_parts'
  | 'waiting_approval'
  | 'completed'
  | 'delivered'
  | 'invoiced'
  | 'cancelled'

export type WorkOrderPriority = 'low' | 'normal' | 'high' | 'urgent'

export interface WorkOrderCustomerRef {
  id: number
  name: string
  phone?: string | null
  document?: string | null
  email?: string | null
  latitude?: number | string | null
  longitude?: number | string | null
  address_city?: string | null
  address_state?: string | null
  google_maps_link?: string | null
  contacts?: { name?: string; email?: string; phone?: string; role?: string }[]
}

export interface WorkOrderAssigneeRef {
  id: number
  name: string
}

export interface WorkOrderEquipmentRef {
  id: number
  type: string
  brand?: string | null
  model?: string | null
  serial_number?: string | null
}

export type WorkOrderOriginType = 'quote' | 'service_call' | 'recurring_contract' | 'manual'

export type WorkOrderLeadSource = 'prospeccao' | 'retorno' | 'contato_direto' | 'indicacao'

export type WorkOrderServiceType =
  | 'diagnostico'
  | 'manutencao_corretiva'
  | 'preventiva'
  | 'calibracao'
  | 'instalacao'
  | 'retorno'
  | 'garantia'

export type WorkOrderServiceModality =
  | 'calibration'
  | 'inspection'
  | 'maintenance'
  | 'adjustment'
  | 'diagnostic'

export type DecisionRuleAgreed = 'simple' | 'guard_band' | 'shared_risk'

export type GuardBandMode = 'k_times_u' | 'percent_limit' | 'fixed_abs'

/**
 * Parâmetros condicionais da regra de decisão na análise crítica (ISO 17025 §7.8.6).
 * Coletados ANTES da execução e gravados na OS como acordo com o cliente (ILAC G8 §3).
 */
export interface DecisionRuleCriticalAnalysisFields {
  decision_guard_band_mode?: GuardBandMode | null
  decision_guard_band_value?: number | null
  decision_producer_risk_alpha?: number | null
  decision_consumer_risk_beta?: number | null
}

export type WorkOrderAgreedPaymentMethod =
  | 'pix'
  | 'boleto'
  | 'cartao_credito'
  | 'cartao_debito'
  | 'transferencia'
  | 'dinheiro'
  | 'pending_after_invoice'

export interface WorkOrderPhotoChecklistItem {
  id: string
  text: string
  checked: boolean
  photo_url?: string
}

export interface WorkOrderPhotoChecklist {
  items?: WorkOrderPhotoChecklistItem[]
  before?: Array<{ url: string; caption?: string; created_at?: string }>
  during?: Array<{ url: string; caption?: string; created_at?: string }>
  after?: Array<{ url: string; caption?: string; created_at?: string }>
}

export interface DisplacementStop {
  id?: number
  lat: number
  lng: number
  timestamp: string
  label?: string
  type?: 'pause' | 'fuel' | 'custom'
  started_at?: string
  ended_at?: string | null
  notes?: string | null
}

export interface FiscalNote {
  id: number
  type: 'nfe' | 'nfse'
  number?: string | null
  status: string
  total_amount?: number | string | null
  issued_at?: string | null
  pdf_url?: string | null
  xml_url?: string | null
  protocol?: string | null
}

export interface CostEstimateBreakdown {
  items_subtotal: number | string
  items_discount: number | string
  global_discount: number | string
  items_cost: number | string
  displacement_value: number | string
  commission_estimate: number | string
  total_cost: number | string
  revenue: number | string
  grand_total: number | string
  profit: number | string
  margin_pct: number
}

export interface ApprovalEntry {
  id: number
  approver_id: number
  approver?: { id: number; name: string }
  status: 'pending' | 'approved' | 'rejected'
  requested_at?: string
  responded_at?: string | null
  notes?: string | null
  requested_by?: { id: number; name: string }
}

export interface ChatMessage {
  id: number
  work_order_id: number
  user_id?: number
  message: string
  attachment_url?: string | null
  attachment_name?: string | null
  created_at: string
  user?: { id: number; name: string }
  is_read?: boolean
}

export interface TimeLogEntry {
  id: number
  work_order_id: number
  user_id: number
  description?: string | null
  started_at: string
  ended_at?: string | null
  duration_minutes?: number | null
  user?: { id: number; name: string }
}

export interface SatisfactionRating {
  id: number
  work_order_id: number
  score: number
  comment?: string | null
  created_at?: string
  respondent_name?: string | null
  respondent_email?: string | null
}

export interface WorkOrderCreatePayload {
  customer_id: number
  description: string
  priority?: WorkOrderPriority
  status?: WorkOrderStatus
  service_type?: WorkOrderServiceType | string
  assigned_to?: number | null
  seller_id?: number | null
  driver_id?: number | null
  branch_id?: number | null
  checklist_id?: number | null
  equipment_id?: number | null
  scheduled_date?: string | null
  delivery_forecast?: string | null
  is_warranty?: boolean
  address?: string | null
  city?: string | null
  state?: string | null
  zip_code?: string | null
  contact_phone?: string | null
  internal_notes?: string | null
  technical_report?: string | null
  origin_type?: WorkOrderOriginType
  quote_id?: number | null
  service_call_id?: number | null
  recurring_contract_id?: number | null
  manual_justification?: string | null
  lead_source?: string | null
  displacement_value?: string | number | null
  discount_percentage?: string | number | null
  discount_amount?: string | number | null
  agreed_payment_method?: string | null
  agreed_payment_notes?: string | null
  tags?: string[]
  technician_ids?: number[]
  items?: ItemFormPayload[]
  sla_policy_id?: number | null
  os_number?: string | null
  // Análise Crítica (ISO 17025 / Calibração)
  service_modality?: string | null
  requires_adjustment?: boolean
  requires_maintenance?: boolean
  client_wants_conformity_declaration?: boolean
  decision_rule_agreed?: string | null
  subject_to_legal_metrology?: boolean
  needs_ipem_interaction?: boolean
  site_conditions?: string | null
  calibration_scope_notes?: string | null
  applicable_procedure?: string | null
  will_emit_complementary_report?: boolean
  client_accepted_at?: string | null
  client_accepted_by?: string | null
}

export interface RequestApprovalPayload {
  approver_ids: number[]
  notes?: string
}

export interface RespondApprovalPayload {
  notes?: string
}

export interface CheckinPayload {
  latitude?: number
  longitude?: number
  notes?: string
}

export interface CheckoutPayload {
  latitude?: number
  longitude?: number
  notes?: string
}

export interface SubmitRatingPayload {
  score: number
  comment?: string
  respondent_name?: string
  respondent_email?: string
}

export interface WorkOrderTemplatePayload {
  name: string
  description?: string
  priority?: WorkOrderPriority
  service_type?: string
  checklist_id?: number | null
  items?: ItemFormPayload[]
  tags?: string[]
}

export interface WorkOrder {
  id: number
  tenant_id?: number
  number: string
  os_number?: string | null
  business_number?: string | null
  customer_id?: number
  customer?: WorkOrderCustomerRef
  equipment_id?: number | null
  equipment?: WorkOrderEquipmentRef | null
  assigned_to?: number | null
  assignee?: WorkOrderAssigneeRef | null
  created_by?: number | null
  seller_id?: number | null
  seller?: { id: number; name: string } | null
  driver_id?: number | null
  branch_id?: number | null
  branch?: { id: number; name: string } | null
  status: WorkOrderStatus
  priority: WorkOrderPriority
  description: string
  total: string | number
  address?: string | null
  city?: string | null
  state?: string | null
  zip_code?: string | null
  contact_phone?: string | null
  scheduled_date?: string | null
  created_at: string
  updated_at?: string
  deleted_at?: string | null
  checklist_id?: number | null
  technical_report?: string | null
  internal_notes?: string | null
  displacement_value?: string | number | null
  is_warranty?: boolean
  is_master?: boolean
  parent_id?: number | null
  parent?: { id: number; business_number?: string } | null
  children?: WorkOrder[]
  quote_id?: number | null
  quote?: { id: number; number?: string; status?: string } | null
  service_call_id?: number | null
  service_call?: { id: number; number?: string; status?: string } | null
  recurring_contract_id?: number | null
  origin_type?: WorkOrderOriginType | string | null
  lead_source?: WorkOrderLeadSource | string | null
  status_history?: StatusHistoryEntry[]
  checkin_at?: string | null
  checkout_at?: string | null
  creator?: { id: number; name: string; email?: string } | null
  sla_due_at?: string | null
  sla_responded_at?: string | null
  sla_policy_id?: number | null
  items?: WorkOrderItem[]
  attachments?: WorkOrderAttachment[]
  discount?: string | number | null
  discount_percentage?: string | number | null
  discount_amount?: string | number | null
  // Displacement tracking
  displacement_started_at?: string | null
  displacement_arrived_at?: string | null
  displacement_duration_minutes?: number | null
  displacement_stops?: DisplacementStop[]
  // Service tracking
  service_started_at?: string | null
  wait_time_minutes?: number | null
  service_duration_minutes?: number | null
  // Return tracking
  return_started_at?: string | null
  return_destination?: string | null
  return_arrived_at?: string | null
  return_duration_minutes?: number | null
  total_duration_minutes?: number | null
  // Arrival GPS
  arrival_latitude?: number | string | null
  arrival_longitude?: number | string | null
  // Start/End GPS
  start_latitude?: number | null
  start_longitude?: number | null
  end_latitude?: number | null
  end_longitude?: number | null
  // Checkin/Checkout GPS
  checkin_lat?: number | null
  checkin_lng?: number | null
  checkout_lat?: number | null
  checkout_lng?: number | null
  auto_km_calculated?: number | string | null
  // Date milestones
  received_at?: string | null
  started_at?: string | null
  completed_at?: string | null
  delivered_at?: string | null
  cancelled_at?: string | null
  cancellation_reason?: string | null
  cancellation_category?: string | null
  // Service type / justification
  service_type?: WorkOrderServiceType | string | null
  manual_justification?: string | null
  // Payment agreement
  agreed_payment_method?: WorkOrderAgreedPaymentMethod | string | null
  agreed_payment_notes?: string | null
  // Pause tracking
  is_paused?: boolean
  paused_at?: string | null
  pause_reason?: string | null
  // SLA
  sla_response_breached?: boolean
  sla_resolution_breached?: boolean
  sla_deadline?: string | null
  sla_hours?: number | null
  // Operational
  eta_minutes?: number | null
  difficulty_level?: string | null
  total_cost?: number | string | null
  profit_margin?: number | string | null
  reschedule_count?: number | null
  visit_number?: number | null
  reopen_count?: number | null
  auto_assigned?: boolean
  auto_assignment_rule_id?: number | null
  project_id?: number | null
  fleet_vehicle_id?: number | null
  cost_center_id?: number | null
  rating_token?: string | null
  // Appended / computed
  waze_link?: string | null
  google_maps_link?: string | null
  warranty_until?: string | null
  is_under_warranty?: boolean
  estimated_profit?: {
    revenue: string
    costs: string
    profit: string
    margin_pct: number
    breakdown: { items_cost: string; displacement: string; commission: string }
  }
  // Relations
  equipments_list?: WorkOrderEquipmentRef[]
  calibrations?: WorkOrderCalibrationRef[]
  technicians?: WorkOrderAssigneeRef[]
  fiscal_notes?: FiscalNote[]
  invoices?: { id: number; invoice_number: string; total: number | string; status: string }[]
  chats?: ChatMessage[]
  satisfaction_survey?: SatisfactionRating | null
  checklist_responses?: ChecklistResponse[]
  // Dispatch
  dispatch_authorized_by?: number | null
  dispatch_authorized_at?: string | null
  dispatch_authorizer?: { id?: number; name?: string } | null
  driver?: { id?: number; name?: string } | null
  allowed_transitions?: string[]
  delivery_forecast?: string | null
  photo_checklist?: WorkOrderPhotoChecklist | null
  tags?: string[]
  warranty_terms?: string | null
  // Análise Crítica (ISO 17025 / Calibração)
  service_modality?: WorkOrderServiceModality | string | null
  requires_adjustment?: boolean
  requires_maintenance?: boolean
  client_wants_conformity_declaration?: boolean
  decision_rule_agreed?: DecisionRuleAgreed | string | null
  subject_to_legal_metrology?: boolean
  needs_ipem_interaction?: boolean
  site_conditions?: string | null
  calibration_scope_notes?: string | null
  applicable_procedure?: string | null
  will_emit_complementary_report?: boolean
  client_accepted_at?: string | null
  client_accepted_by?: string | null
  maintenance_reports?: MaintenanceReportRef[]
  // Signature
  signature_path?: string | null
  signature_signer?: string | null
  signature_at?: string | null
  signature_ip?: string | null
}

export interface WorkOrderItem {
  id?: number
  work_order_id?: number
  type: 'product' | 'service'
  reference_id: number | string | null
  description: string
  quantity: number | string
  unit_price: number | string
  cost_price?: number | string | null
  discount?: number | string
  total?: string | number
  warehouse_id?: number | string | null
  product?: { id: number; name: string; code?: string | null } | null
  service?: { id: number; name: string } | null
}

export interface ItemFormPayload {
  type: 'product' | 'service'
  reference_id: string | number | null
  description: string
  quantity: string
  unit_price: string
  discount: string
  warehouse_id?: string | number | null
}

export interface EditFormPayload {
  description: string
  priority: string
  technical_report: string
  internal_notes: string
  displacement_value: string
  is_warranty: boolean
  assigned_to?: number | string | null
  seller_id?: number | string | null
  driver_id?: number | string | null
  technician_ids?: number[]
  lead_source?: string
  scheduled_date?: string
  service_type?: string
  address?: string
  city?: string
  state?: string
  zip_code?: string
  contact_phone?: string
  delivery_forecast?: string
  checklist_id?: number | string | null
  branch_id?: number | string | null
  tags?: string[]
  agreed_payment_method?: string
  agreed_payment_notes?: string
  os_number?: string
  sla_policy_id?: number | string | null
  // Análise Crítica (ISO 17025 / Calibração)
  service_modality?: string | null
  requires_adjustment?: boolean
  requires_maintenance?: boolean
  client_wants_conformity_declaration?: boolean
  decision_rule_agreed?: string | null
  subject_to_legal_metrology?: boolean
  needs_ipem_interaction?: boolean
  site_conditions?: string | null
  calibration_scope_notes?: string | null
  applicable_procedure?: string | null
  will_emit_complementary_report?: boolean
  client_accepted_at?: string | null
  client_accepted_by?: string | null
}

export interface ChecklistResponsePayload {
  checklist_item_id: number
  value: string
  notes: string
}

export interface ChecklistTemplateItem {
  id: number
  order_index: number
  description: string
  type: string
  is_required: boolean
}

export interface ChecklistResponse {
  checklist_item_id: number
  value: string
  notes: string
}

export interface ProductOrService {
  id: number
  name: string
  sell_price?: string
  default_price?: string
}

export interface WorkOrderAttachment {
  id: number
  file_name: string
  file_path: string
  file_type?: string | null
  file_size: number
  description?: string | null
  category?: string | null
  uploader?: { name: string }
  created_at?: string | null
}

export interface WorkOrderCalibrationRef {
  id: number
  certificate_number?: string
  calibration_date?: string
  result: string
  equipment_id: number
}

export interface StatusHistoryEntry {
  id: number
  to_status: string
  notes?: string
  created_at: string
  user?: { name: string }
}

export interface PartsKit {
  id: number
  name: string
  items_count?: number
  items?: { id: number; name: string; quantity: number; unit_price?: string | number }[]
}

// ─── Maintenance Report (Portaria Inmetro 457/2021) ────────────
export type MaintenanceConditionBefore = 'defective' | 'degraded' | 'functional' | 'unknown'
export type MaintenanceConditionAfter = 'functional' | 'limited' | 'requires_calibration' | 'not_repaired'
export type SealStatus = 'intact' | 'broken' | 'replaced' | 'not_applicable'

export interface ReplacedPart {
  name: string
  part_number?: string | null
  origin?: string | null
  quantity?: number
}

export interface MaintenanceReportRef {
  id: number
  work_order_id: number
  equipment_id: number
  defect_found: string
  condition_before: MaintenanceConditionBefore
  condition_after: MaintenanceConditionAfter
  requires_calibration_after: boolean
  performer?: { id: number; name: string }
  created_at?: string
}

export interface MaintenanceReport extends MaintenanceReportRef {
  probable_cause?: string | null
  corrective_action?: string | null
  parts_replaced?: ReplacedPart[] | null
  seal_status?: SealStatus | null
  new_seal_number?: string | null
  requires_ipem_verification?: boolean
  notes?: string | null
  photo_evidence?: string[] | null
  started_at?: string | null
  completed_at?: string | null
  approver?: { id: number; name: string } | null
  work_order?: { id: number; number: string } | null
  equipment?: { id: number; brand: string; model: string; serial_number: string } | null
}

// ─── Certificate Emission Checklist (ISO 17025) ────────────────
export interface CertificateEmissionChecklist {
  id: number
  equipment_calibration_id: number
  verified_by: number
  equipment_identified: boolean
  scope_defined: boolean
  critical_analysis_done: boolean
  procedure_defined: boolean
  standards_traceable: boolean
  raw_data_recorded: boolean
  uncertainty_calculated: boolean
  adjustment_documented: boolean
  no_undue_interval: boolean
  conformity_declaration_valid: boolean
  accreditation_mark_correct: boolean
  observations?: string | null
  approved: boolean
  verified_at?: string | null
  verifier?: { id: number; name: string }
}
