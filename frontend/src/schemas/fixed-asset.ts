import { z } from 'zod'

const categories = ['machinery', 'vehicle', 'equipment', 'furniture', 'it', 'tooling', 'other'] as const
const depreciationMethods = ['linear', 'accelerated', 'units_produced'] as const
const ciapCreditTypes = ['icms_full', 'icms_48', 'none'] as const
const disposalReasons = ['sale', 'loss', 'scrap', 'donation', 'theft'] as const
const optionalPositiveInt = z.preprocess(
    value => value === '' || value === null || value === undefined ? undefined : value,
    z.coerce.number().int().positive().optional()
)

export const fixedAssetFormSchema = z.object({
    name: z.string().trim().min(1, 'Nome é obrigatório'),
    description: z.string().trim().optional().or(z.literal('')),
    category: z.enum(categories),
    acquisition_date: z.string().min(1, 'Data de aquisição é obrigatória'),
    acquisition_value: z.coerce.number().min(0.01, 'Valor de aquisição deve ser maior que zero'),
    residual_value: z.coerce.number().min(0, 'Valor residual não pode ser negativo'),
    useful_life_months: z.coerce.number().int().min(1, 'Vida útil deve ser maior que zero'),
    depreciation_method: z.enum(depreciationMethods),
    location: z.string().trim().optional().or(z.literal('')),
    responsible_user_id: optionalPositiveInt,
    supplier_id: optionalPositiveInt,
    fleet_vehicle_id: optionalPositiveInt,
    nf_number: z.string().trim().optional().or(z.literal('')),
    nf_serie: z.string().trim().optional().or(z.literal('')),
    ciap_credit_type: z.enum(ciapCreditTypes).default('none'),
}).superRefine((value, ctx) => {
    if (value.residual_value > value.acquisition_value) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Valor residual não pode ser maior que o valor de aquisição',
            path: ['residual_value'],
        })
    }

    if (value.ciap_credit_type === 'icms_48' && value.useful_life_months < 48) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'CIAP 48 exige vida útil mínima de 48 meses',
            path: ['useful_life_months'],
        })
    }
})

export const runDepreciationSchema = z.object({
    reference_month: z.string().regex(/^\d{4}-\d{2}$/, 'Informe o mês no formato AAAA-MM'),
})

export const disposeAssetSchema = z.object({
    disposal_date: z.string().min(1, 'Data da baixa é obrigatória'),
    reason: z.enum(disposalReasons),
    disposal_value: z.coerce.number().min(0, 'Valor da baixa não pode ser negativo').optional(),
    notes: z.string().trim().optional().or(z.literal('')),
    approved_by: z.coerce.number().int().positive('Informe o aprovador'),
})

export const fixedAssetMovementSchema = z.object({
    movement_type: z.enum(['transfer', 'assignment', 'maintenance', 'inventory_adjustment']),
    to_location: z.string().trim().optional().or(z.literal('')),
    to_responsible_user_id: optionalPositiveInt,
    moved_at: z.string().min(1, 'Data da movimentação é obrigatória'),
    notes: z.string().trim().optional().or(z.literal('')),
})

export const fixedAssetInventorySchema = z.object({
    inventory_date: z.string().min(1, 'Data do inventário é obrigatória'),
    counted_location: z.string().trim().optional().or(z.literal('')),
    counted_status: z.enum(['active', 'suspended', 'disposed', 'fully_depreciated']).optional(),
    condition_ok: z.boolean().default(true),
    notes: z.string().trim().optional().or(z.literal('')),
})

export type FixedAssetFormValues = z.infer<typeof fixedAssetFormSchema>
export type RunDepreciationValues = z.infer<typeof runDepreciationSchema>
export type DisposeAssetValues = z.infer<typeof disposeAssetSchema>
export type FixedAssetMovementValues = z.infer<typeof fixedAssetMovementSchema>
export type FixedAssetInventoryValues = z.infer<typeof fixedAssetInventorySchema>
