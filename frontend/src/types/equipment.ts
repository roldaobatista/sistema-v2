export interface EquipmentCustomerRef {
  id: number
  name: string
  document?: string | null
  phone?: string | null
}

export interface EquipmentResponsibleRef {
  id: number
  name: string
}

export interface EquipmentModelRef {
  id: number
  name: string
  brand: string | null
  category: string | null
  products?: { id: number; name: string; code: string | null }[]
}

export interface EquipmentCalibration {
  id: number
  calibration_date: string
  next_due_date: string | null
  calibration_type: string
  result: string
  laboratory: string | null
  certificate_number: string | null
  uncertainty: string | null
  error_found: string | null
  performer?: { id: number; name: string }
  standard_weights?: {
    id: number
    code: string
    nominal_value: string
    unit: string
    precision_class?: string | null
    certificate_status?: string
  }[]
  notes?: string | null
}

export interface EquipmentMaintenance {
  id: number
  equipment_id: number
  type: string
  description: string
  parts_replaced?: string | null
  cost?: string | null
  downtime_hours?: string | null
  next_maintenance_at?: string | null
  performer?: { id: number; name: string }
  work_order?: { id: number; number: string } | null
  created_at: string
}

export interface EquipmentDocument {
  id: number
  name: string
  file_path: string
  type: string
  created_at: string
  uploaded_by?: number
  expires_at?: string | null
}

export interface EquipmentWorkOrderRef {
  id: number
  number?: string | null
  os_number?: string | null
  business_number?: string | null
  status?: string | null
  description?: string | null
  completed_at?: string | null
  created_at?: string | null
}

export interface Equipment {
  id: number
  tenant_id?: number
  customer_id?: number | null
  code: string
  type: string | null
  category?: string | null
  brand: string | null
  manufacturer?: string | null
  model: string | null
  serial_number: string | null
  capacity: number | string | null
  capacity_unit: string | null
  equipment_model_id?: number | null
  resolution: number | string | null
  precision_class: string | null
  status: string
  location: string | null
  tag?: string | null
  is_critical?: boolean
  is_active?: boolean
  inmetro_number?: string | null
  next_calibration_at: string | null
  last_calibration_at: string | null
  calibration_interval_months?: number | null
  calibration_status?: string | null
  certificate_number?: string | null
  notes?: string | null
  purchase_value?: number | string | null
  customer?: EquipmentCustomerRef
  responsible?: EquipmentResponsibleRef
  equipment_model?: EquipmentModelRef | null
  calibrations?: EquipmentCalibration[]
  maintenances?: EquipmentMaintenance[]
  documents?: EquipmentDocument[]
  work_orders?: EquipmentWorkOrderRef[]
  tracking_url?: string
  created_at?: string
  updated_at?: string
}

export interface EquipmentAlert {
  id: number
  code: string
  brand: string | null
  model: string | null
  serial_number: string | null
  customer: string | null
  next_calibration_at: string | null
  days_remaining: number | null
  status: string
}

export interface EquipmentDashboardData {
  total: number
  overdue: number
  due_7_days: number
  due_30_days: number
  critical_count: number
  by_category: Record<string, number>
  by_status: Record<string, number>
}

export interface EquipmentQrResult {
  qr_token: string
  public_url: string
}

export interface EquipmentModel {
  id: number
  tenant_id?: number
  name: string
  brand?: string | null
  category?: string | null
  products_count?: number
  products?: { id: number; name: string; code: string | null }[]
  created_at?: string
  updated_at?: string
}

export interface StandardWeight {
  id: number
  tenant_id?: number
  code: string
  nominal_value: string
  unit: string
  serial_number: string | null
  manufacturer: string | null
  precision_class: string | null
  material: string | null
  shape: string | null
  certificate_number: string | null
  certificate_date: string | null
  certificate_expiry: string | null
  certificate_file?: string | null
  laboratory: string | null
  status: string
  status_label?: string | null
  display_name?: string | null
  notes: string | null
  laboratory_accreditation?: string | null
  traceability_chain?: string | null
  wear_rate_percentage?: number | null
  expected_failure_date?: string | null
  created_at?: string
  updated_at?: string
}

export interface StandardWeightConstants {
  statuses: Record<string, string | { label: string; color: string }>
  precision_classes: Record<string, string>
  units: string[]
  shapes: Record<string, string>
}

export interface StandardWeightExpiringSummary {
  expiring: StandardWeight[]
  expired: StandardWeight[]
  expiring_count: number
  expired_count: number
}

export interface WeightAssignmentRecord {
  id: number
  standard_weight_id: number
  assigned_to_user_id: number | null
  assignment_type: string
  assigned_at: string
  returned_at: string | null
  notes: string | null
  user_name?: string | null
  weight_code?: string | null
  weight_nominal_value?: string | number | null
  weight_unit?: string | null
}

export interface EquipmentTechnicianOption {
  id: number
  name: string
  email?: string | null
}
