/**
 * Zod schemas for Work Order editing/creation forms.
 * Used with React Hook Form for front-end validation.
 */
import { z } from 'zod'

export const editWorkOrderSchema = z.object({
  description: z.string().max(5000, 'Descrição muito longa').optional(),
  internal_notes: z.string().max(5000, 'Observações internas muito longas').optional(),
  priority: z.enum(['low', 'normal', 'high', 'urgent', '']).optional(),
  service_type: z.string().max(100).optional(),
  scheduled_date: z.string().optional(),
  delivery_forecast: z.string().optional(),
  assigned_to: z.coerce.number().positive('Selecione um técnico').optional().or(z.literal('')),
  seller_id: z.coerce.number().positive().optional().or(z.literal('')),
  driver_id: z.coerce.number().positive().optional().or(z.literal('')),
  branch_id: z.coerce.number().positive().optional().or(z.literal('')),
  checklist_id: z.coerce.number().positive().optional().or(z.literal('')),
  contact_phone: z.string().max(20).optional(),
  address: z.string().max(500).optional(),
  city: z.string().max(100).optional(),
  state: z.string().max(2).optional(),
  zip_code: z.string().max(10).optional(),
  arrival_latitude: z.coerce.number().optional().or(z.literal('')),
  arrival_longitude: z.coerce.number().optional().or(z.literal('')),
  displacement_value: z.coerce.number().min(0).optional(),
  discount: z.coerce.number().min(0).optional(),
  discount_percentage: z.coerce.number().min(0).max(100).optional(),
  agreed_payment_method: z.string().optional(),
  agreed_payment_notes: z.string().max(500).optional(),
  technician_ids: z.array(z.number()).optional(),
  equipment_ids: z.array(z.number()).optional(),
})

export type EditWorkOrderInput = z.infer<typeof editWorkOrderSchema>

export const itemFormSchema = z.object({
  type: z.enum(['product', 'service']),
  reference_id: z.coerce.number().positive('Selecione um produto ou serviço').optional().or(z.literal('')),
  description: z.string().min(1, 'Descrição é obrigatória').max(500),
  quantity: z.coerce.number().positive('Quantidade deve ser maior que zero'),
  unit_price: z.coerce.number().min(0, 'Preço não pode ser negativo'),
  discount: z.coerce.number().min(0, 'Desconto não pode ser negativo').optional(),
  warehouse_id: z.coerce.number().positive().optional().or(z.literal('')),
})

export type ItemFormInput = z.infer<typeof itemFormSchema>
