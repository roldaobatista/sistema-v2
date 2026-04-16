import { z } from 'zod'

// Commission Rule form validation
export const commissionRuleSchema = z.object({
    name: z.string().min(3, 'Nome deve ter pelo menos 3 caracteres'),
    calculation_type: z.string().min(1, 'Tipo de calculo e obrigatorio'),
    value: z.coerce.number().positive('Valor deve ser maior que 0'),
    applies_to_role: z.enum(['tecnico', 'vendedor', 'motorista']).default('tecnico'),
    user_id: z.preprocess(
        (value) => (value === '' || value == null ? null : value),
        z.union([z.coerce.number().int().positive(), z.null()]).optional().nullable()
    ),
    priority: z.coerce.number().int().min(0).default(0),
    applies_to: z.enum(['all', 'products', 'services']).default('all'),
    applies_when: z.enum(['os_completed', 'installment_paid', 'os_invoiced']).default('os_completed'),
    source_filter: z.string().max(100, 'Filtro de origem deve ter no maximo 100 caracteres').optional().nullable(),
    active: z.boolean().default(true),
})

export type CommissionRuleFormData = z.infer<typeof commissionRuleSchema>

// Commission Goal form validation
export const commissionGoalSchema = z.object({
    user_id: z.coerce.number().positive('Selecione um usuario'),
    period: z.string().min(7, 'Periodo e obrigatorio').regex(/^\d{4}-\d{2}$/, 'Formato: AAAA-MM'),
    target_amount: z.coerce.number().positive('Meta deve ser maior que 0'),
    type: z.enum(['revenue', 'os_count', 'new_clients'], { message: 'Selecione o tipo de meta' }),
    bonus_percentage: z.coerce.number().min(0).max(100).optional().nullable(),
    bonus_amount: z.coerce.number().min(0).optional().nullable(),
    notes: z.string().optional().nullable(),
})

export type CommissionGoalFormData = z.infer<typeof commissionGoalSchema>

// Commission Campaign form validation
export const commissionCampaignSchema = z.object({
    name: z.string().min(3, 'Nome deve ter pelo menos 3 caracteres'),
    multiplier: z.coerce.number().min(1.01, 'Multiplicador deve ser maior que 1').max(5, 'Multiplicador maximo e 5'),
    starts_at: z.string().min(1, 'Data de inicio e obrigatoria'),
    ends_at: z.string().min(1, 'Data de fim e obrigatoria'),
    applies_to_role: z.string().optional().nullable(),
}).refine(
    data => new Date(data.ends_at) >= new Date(data.starts_at),
    { message: 'Data de fim deve ser posterior ao inicio', path: ['ends_at'] }
)

export type CommissionCampaignFormData = z.infer<typeof commissionCampaignSchema>

// Commission Dispute form validation
export const commissionDisputeSchema = z.object({
    commission_event_id: z.coerce.number().positive('Selecione um evento'),
    reason: z.string().min(10, 'Motivo deve ter pelo menos 10 caracteres'),
})

export type CommissionDisputeFormData = z.infer<typeof commissionDisputeSchema>

// Dispute resolution form validation
export const disputeResolutionSchema = z.object({
    status: z.enum(['accepted', 'rejected']),
    resolution_notes: z.string().min(5, 'Notas devem ter pelo menos 5 caracteres'),
    new_amount: z.coerce.number().min(0).optional().nullable(),
})

export type DisputeResolutionFormData = z.infer<typeof disputeResolutionSchema>

// Settlement Action Schemas
export const closeSettlementSchema = z.object({
    user_id: z.string().min(1, 'Selecione um usuário'),
    period: z.string().min(7, 'Período é obrigatório'),
})
export type CloseSettlementFormData = z.infer<typeof closeSettlementSchema>

export const paySettlementSchema = z.object({
    payment_notes: z.string().optional().nullable(),
})
export type PaySettlementFormData = z.infer<typeof paySettlementSchema>

export const rejectSettlementSchema = z.object({
    rejection_reason: z.string().min(5, 'O motivo deve ter pelo menos 5 caracteres'),
})
export type RejectSettlementFormData = z.infer<typeof rejectSettlementSchema>

export const batchGenerateSchema = z.object({
    user_id: z.string().optional(),
    date_from: z.string().min(1, 'Data de início é obrigatória'),
    date_to: z.string().min(1, 'Data de fim é obrigatória'),
})
export type BatchGenerateFormData = z.infer<typeof batchGenerateSchema>

// Helper to extract field errors from ZodError
export function getFieldErrors(error: z.ZodError): Record<string, string> {
    const errors: Record<string, string> = {}
    for (const issue of error.issues) {
        const path = issue.path.join('.')
        if (!errors[path]) errors[path] = issue.message
    }
    return errors
}
