export interface StandardWeightRef {
  id: number
  code: string
  nominal_value: string
  unit: string
  precision_class: string
  certificate_status?: string
  laboratory_accreditation?: string | null
  traceability_chain?: string | null
}

export interface Calibration {
  id: number
  calibration_date: string
  next_due_date?: string | null
  calibration_type?: string
  result: string
  laboratory?: string | null
  certificate_number?: string | null
  uncertainty?: string | null
  error_found?: string | null
  performer?: { id: number; name: string }
  standard_weights?: StandardWeightRef[]
  notes?: string | null
}

export interface CalibrationItem {
  id: number
  certificate_number: string | null
  calibration_date: string
  result: string
  equipment?: {
    id: number
    code: string
    brand: string
    model: string
    serial_number: string
    precision_class: string
    customer?: { id: number; name: string }
  }
  performer?: { id: number; name: string }
}

export interface CertificateTemplate {
  id: number
  name: string
  content?: string | null
  is_active?: boolean
}

// ─── CalibrationReading (matches backend fillable) ────────────────
export interface CalibrationReading {
  id?: number
  tenant_id?: number
  equipment_calibration_id?: number
  reference_value: string
  indication_increasing: string
  indication_decreasing: string
  error?: string | null
  expanded_uncertainty?: string | null
  k_factor: string
  correction?: string | null
  reading_order?: number
  repetition?: number
  unit: string
}

// ─── ExcentricityTest (matches backend fillable) ──────────────────
export interface ExcentricityTest {
  id?: number
  tenant_id?: number
  equipment_calibration_id?: number
  position: string
  load_applied: string | number
  indication: string | number
  error?: string | number | null
  max_permissible_error?: string | number | null
  conforms?: boolean | null
  position_order?: number
}

// ─── RepeatabilityTest (matches backend fillable) ─────────────────
export interface RepeatabilityTest {
  id?: number
  tenant_id?: number
  equipment_calibration_id?: number
  load_value: string | number
  unit: string
  measurement_1?: string | number | null
  measurement_2?: string | number | null
  measurement_3?: string | number | null
  measurement_4?: string | number | null
  measurement_5?: string | number | null
  measurement_6?: string | number | null
  measurement_7?: string | number | null
  measurement_8?: string | number | null
  measurement_9?: string | number | null
  measurement_10?: string | number | null
  mean?: string | number | null
  std_deviation?: string | number | null
  uncertainty_type_a?: string | number | null
}

// ─── CertificateEmissionChecklist (matches backend fillable) ─────
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
  verified_at: string
  verifier?: { id: number; name: string }
}

// ─── Wizard form types (used in CalibrationWizardPage) ────────────
export interface ReadingRow {
  referenceValue: string
  indicationIncreasing: string
  indicationDecreasing: string
  kFactor: string
  unit: string
}

export interface EccentricityRow {
  position: string
  loadApplied: string
  indication: string
}

export interface EccentricityPayload {
  position: string
  load_applied: number
  indication: number
}

export interface GravityRef {
  state: string
  city: string
  gravity: number
}

export interface ValidationResult {
  complete: boolean
  score: number
  total_fields: number
  missing_fields?: string[]
}

export interface AvailableWeight {
  id: number
  code: string
  precision_class: string
  certificate_expiry?: string
  nominal_value: string | number
  unit: string
  certificate_number?: string
  laboratory?: string | null
  laboratory_accreditation?: string | null
  traceability_chain?: string | null
}

export interface FilteredWeight extends AvailableWeight {
  adequate: boolean
  expired: boolean
}

export interface CalibrationWizardFormData {
  equipmentId: number | null
  calibrationId: number | null
  precisionClass: string
  eValue: string
  maxCapacity: string
  capacityUnit: string
  verificationType: 'initial' | 'subsequent' | 'in_use'

  temperature: string
  humidity: string
  pressure: string
  calibrationLocation: string
  calibrationLocationType: 'laboratory' | 'field' | 'client_site'
  calibrationMethod: string
  receivedDate: string
  issuedDate: string
  calibrationDate: string
  gravityState: string
  gravityCity: string
  gravityAcceleration: string
  laboratoryAddress: string
  decisionRule: 'simple' | 'guard_band' | 'shared_risk'
  scopeDeclaration: string

  standardUsed: string
  weightIds: number[]

  readings: ReadingRow[]

  linearityTests?: LinearityTestRow[]

  eccentricityTests: EccentricityRow[]

  repeatabilityLoad: string
  repeatabilityMeasurements: string[]

  workOrderId: number | null
}

export interface LinearityTestRow {
  reference_value: number | string
  indication_increasing: number | string
  indication_decreasing: number | string
  unit?: string
}

/**
 * Resultado avaliado pela ConformityAssessmentService (ISO 17025 §7.8.6,
 * ILAC G8:09/2019, ILAC P14:09/2020, JCGM 106:2012).
 */
export interface DecisionResult {
  rule: 'simple' | 'guard_band' | 'shared_risk' | null
  result: 'accept' | 'warn' | 'reject' | null
  coverage_factor_k: number | null
  confidence_level: number | null
  guard_band_mode?: string | null
  guard_band_value?: number | null
  guard_band_applied?: number | null
  producer_risk_alpha?: number | null
  consumer_risk_beta?: number | null
  z_value?: number | null
  false_accept_probability?: number | null
  calculated_at?: string | null
  calculated_by?: { id: number; name: string } | null
  notes?: string | null
}
