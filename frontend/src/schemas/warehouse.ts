import { z } from 'zod'
import { requiredString } from '@/schemas/common'

export const warehouseSchema = z.object({
  name: requiredString('Nome é obrigatório'),
  code: requiredString('Código é obrigatório'),
  type: z.enum(['fixed', 'vehicle', 'technician']),
  user_id: z.string().optional().default(''),
  vehicle_id: z.string().optional().default(''),
  is_active: z.boolean().default(true),
})

export type WarehouseFormData = z.infer<typeof warehouseSchema>
