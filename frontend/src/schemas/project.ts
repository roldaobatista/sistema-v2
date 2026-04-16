import { z } from 'zod'

const optionalPositiveInt = z.preprocess(
    value => value === '' || value === null || value === undefined ? undefined : value,
    z.coerce.number().int().positive().optional()
)

const optionalPositiveNumber = z.preprocess(
    value => value === '' || value === null || value === undefined ? undefined : value,
    z.coerce.number().positive().optional()
)

const optionalNonNegativeNumber = z.preprocess(
    value => value === '' || value === null || value === undefined ? undefined : value,
    z.coerce.number().min(0).optional()
)

export const projectFormSchema = z.object({
    customer_id: z.coerce.number().int().positive('Informe o cliente'),
    name: z.string().trim().min(1, 'Nome é obrigatório'),
    description: z.string().trim().optional().or(z.literal('')),
    status: z.enum(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
    priority: z.enum(['low', 'medium', 'high', 'critical']),
    start_date: z.string().optional().or(z.literal('')),
    end_date: z.string().optional().or(z.literal('')),
    budget: optionalNonNegativeNumber,
    billing_type: z.enum(['milestone', 'hourly', 'fixed_price']),
    hourly_rate: optionalNonNegativeNumber,
    crm_deal_id: optionalPositiveInt,
    manager_id: optionalPositiveInt,
    tags: z.string().trim().optional().or(z.literal('')),
}).superRefine((value, ctx) => {
    if (value.start_date && value.end_date && value.end_date < value.start_date) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Data final deve ser maior ou igual à inicial',
            path: ['end_date'],
        })
    }
})

export const projectMilestoneFormSchema = z.object({
    name: z.string().trim().min(1, 'Nome do marco é obrigatório'),
    planned_start: z.string().optional().or(z.literal('')),
    planned_end: z.string().optional().or(z.literal('')),
    billing_value: optionalNonNegativeNumber,
    weight: optionalPositiveNumber,
    order: z.coerce.number().int().min(1, 'Ordem deve ser maior que zero'),
    dependencies: z.string().trim().optional().or(z.literal('')),
    deliverables: z.string().trim().optional().or(z.literal('')),
}).superRefine((value, ctx) => {
    if (value.planned_start && value.planned_end && value.planned_end < value.planned_start) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Data final deve ser maior ou igual à inicial',
            path: ['planned_end'],
        })
    }
})

export const projectResourceFormSchema = z.object({
    user_id: z.coerce.number().int().positive('Informe o usuário'),
    role: z.string().trim().min(1, 'Função é obrigatória'),
    allocation_percent: z.coerce.number().min(1, 'Alocação mínima é 1%').max(100, 'Alocação máxima é 100%'),
    start_date: z.string().min(1, 'Data inicial é obrigatória'),
    end_date: z.string().min(1, 'Data final é obrigatória'),
    hourly_rate: optionalNonNegativeNumber,
    total_hours_planned: optionalNonNegativeNumber,
}).superRefine((value, ctx) => {
    if (value.end_date < value.start_date) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Data final deve ser maior ou igual à inicial',
            path: ['end_date'],
        })
    }
})

export const projectTimeEntryFormSchema = z.object({
    project_resource_id: z.coerce.number().int().positive('Informe o recurso'),
    milestone_id: optionalPositiveInt,
    work_order_id: optionalPositiveInt,
    date: z.string().min(1, 'Data é obrigatória'),
    hours: z.coerce.number().min(0.25, 'Mínimo de 0,25 hora').max(24, 'Máximo de 24 horas'),
    description: z.string().trim().optional().or(z.literal('')),
    billable: z.boolean().default(true),
})

export type ProjectFormValues = z.infer<typeof projectFormSchema>
export type ProjectMilestoneFormValues = z.infer<typeof projectMilestoneFormSchema>
export type ProjectResourceFormValues = z.infer<typeof projectResourceFormSchema>
export type ProjectTimeEntryFormValues = z.infer<typeof projectTimeEntryFormSchema>
