import { z } from 'zod'
import { addressSchema, contactSchema, optionalString, requiredString } from '@/schemas/common'

export const supplierSchema = z.object({
  type: z.enum(['PF', 'PJ']),
  name: requiredString('Nome é obrigatório'),
  document: optionalString,
  trade_name: optionalString,
  notes: optionalString,
  is_active: z.boolean().default(true),
}).merge(addressSchema).merge(contactSchema)

export type SupplierFormData = z.infer<typeof supplierSchema>
