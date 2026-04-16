import { z } from 'zod'

export const workOrderPriorities = ['low', 'normal', 'high', 'urgent'] as const
export const workOrderInitialStatuses = [
  'open',
  'awaiting_dispatch',
  'in_displacement',
  'in_service',
  'completed',
  'delivered',
  'invoiced',
] as const

const optionalPositiveInt = z
  .union([z.number().int().positive(), z.literal(''), z.null()])
  .transform((v) => (v === '' || v === null ? undefined : v))
  .optional()

const optionalString = z
  .union([z.string(), z.null()])
  .transform((v) => (v === null || v === '' ? undefined : v))
  .optional()

const optionalNumericString = z
  .union([z.string(), z.null()])
  .transform((v) => (v === null || v === '' ? undefined : v === undefined ? undefined : v))
  .optional()

const optionalDecisionRule = z
  .union([z.enum(['simple', 'guard_band', 'shared_risk']), z.literal(''), z.null()])
  .transform((v) => (v === '' || v === null ? undefined : v))
  .optional()

const optionalGuardBandMode = z
  .union([z.enum(['k_times_u', 'percent_limit', 'fixed_abs']), z.literal(''), z.null()])
  .transform((v) => (v === '' || v === null ? undefined : v))
  .optional()

export const workOrderCreateSchema = z.object({
  customer_id: z
    .union([z.number().int().positive(), z.string().min(1)])
    .transform((v) => Number(v))
    .pipe(z.number().int().positive({ message: 'Cliente é obrigatório' })),
  description: z
    .string()
    .min(3, 'Descrição deve ter no mínimo 3 caracteres')
    .max(5000, 'Descrição deve ter no máximo 5000 caracteres'),
  priority: z.enum(workOrderPriorities).default('normal'),
  initial_status: z.enum(workOrderInitialStatuses).default('open'),
  service_type: optionalString,
  lead_source: optionalString,
  assigned_to: optionalPositiveInt,
  seller_id: optionalPositiveInt,
  driver_id: optionalPositiveInt,
  equipment_id: optionalPositiveInt,
  branch_id: optionalPositiveInt,
  checklist_id: optionalPositiveInt,
  quote_id: optionalPositiveInt,
  service_call_id: optionalPositiveInt,
  is_warranty: z.boolean().default(false),
  discount: optionalNumericString,
  discount_percentage: optionalNumericString,
  displacement_value: optionalNumericString,
  internal_notes: z
    .string()
    .max(5000, 'Notas internas deve ter no máximo 5000 caracteres')
    .optional()
    .or(z.literal('')),
  manual_justification: z
    .string()
    .max(5000, 'Justificativa deve ter no máximo 5000 caracteres')
    .optional()
    .or(z.literal('')),
  os_number: optionalString,
  origin_type: optionalString,
  address: optionalString,
  city: optionalString,
  state: optionalString,
  zip_code: optionalString,
  contact_phone: optionalString,
  scheduled_date: optionalString,
  delivery_forecast: optionalString,
  agreed_payment_method: optionalString,
  agreed_payment_notes: optionalString,
  received_at: optionalString,
  started_at: optionalString,
  completed_at: optionalString,
  delivered_at: optionalString,
  tags: z.array(z.string()).optional(),
  // Análise Crítica (ISO 17025 / Calibração)
  service_modality: optionalString,
  requires_adjustment: z.boolean().default(false),
  requires_maintenance: z.boolean().default(false),
  client_wants_conformity_declaration: z.boolean().default(false),
  decision_rule_agreed: optionalDecisionRule,
  // Parâmetros condicionais ILAC G8:09/2019
  decision_guard_band_mode: optionalGuardBandMode,
  decision_guard_band_value: z
    .union([z.number(), z.null()])
    .optional(),
  decision_producer_risk_alpha: z
    .union([z.number().min(0.0001).max(0.5), z.null()])
    .optional(),
  decision_consumer_risk_beta: z
    .union([z.number().min(0.0001).max(0.5), z.null()])
    .optional(),
  subject_to_legal_metrology: z.boolean().default(false),
  needs_ipem_interaction: z.boolean().default(false),
  site_conditions: z
    .string()
    .max(5000, 'Condições do local deve ter no máximo 5000 caracteres')
    .optional()
    .or(z.literal('')),
  calibration_scope_notes: z
    .string()
    .max(5000, 'Observações do escopo deve ter no máximo 5000 caracteres')
    .optional()
    .or(z.literal('')),
  will_emit_complementary_report: z.boolean().default(false),
})
  .refine(
    (d) =>
      d.decision_rule_agreed !== 'guard_band' ||
      (d.decision_guard_band_mode != null && d.decision_guard_band_value != null),
    {
      message: 'Modo e valor da banda de guarda são obrigatórios quando a regra é guard_band',
      path: ['decision_guard_band_value'],
    },
  )
  .refine(
    (d) =>
      d.decision_rule_agreed !== 'shared_risk' ||
      (d.decision_producer_risk_alpha != null && d.decision_consumer_risk_beta != null),
    {
      message: 'Riscos α e β são obrigatórios quando a regra é shared_risk',
      path: ['decision_producer_risk_alpha'],
    },
  )

export type WorkOrderCreateInput = z.input<typeof workOrderCreateSchema>
export type WorkOrderCreateData = z.output<typeof workOrderCreateSchema>
