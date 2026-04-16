import { z } from 'zod'

export const addressSchema = z.object({
  address_zip: z.string().optional().default(''),
  address_street: z.string().optional().default(''),
  address_number: z.string().optional().default(''),
  address_complement: z.string().optional().default(''),
  address_neighborhood: z.string().optional().default(''),
  address_city: z.string().optional().default(''),
  address_state: z.string().optional().default(''),
})

export const contactSchema = z.object({
  phone: z.string().optional().default(''),
  phone2: z.string().optional().default(''),
  email: z.string().email('E-mail inválido').optional().or(z.literal('')),
})

export const optionalString = z.string().optional().default('')
export const requiredString = (msg = 'Campo obrigatório') => z.string().min(1, msg)
export const optionalEmail = z.string().email('E-mail inválido').optional().or(z.literal(''))
export const optionalDecimal = z.coerce.number().nonnegative().optional().default(0)
export const requiredPositive = (msg = 'Valor deve ser positivo') => z.coerce.number().positive(msg)
