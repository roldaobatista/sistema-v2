import { z } from 'zod'
import { addressSchema, contactSchema, optionalString, requiredString } from '@/schemas/common'

const boundedOptionalString = (max: number) => z.string().max(max).optional().default('')

const customerContactSchema = z.object({
  id: z.number().optional(),
  name: z.string().trim().min(1, 'Nome do contato e obrigatorio').max(255),
  role: z.string().max(100).default(''),
  phone: z.string().max(20).default(''),
  email: z.string().email('E-mail do contato invalido').max(255).optional().or(z.literal('')),
  is_primary: z.boolean().default(false),
})

const partnerSchema = z.object({
  name: z.string().nullable().default(null),
  role: z.string().nullable().default(null),
  document: z.string().nullable().default(null),
  entry_date: z.string().nullable().optional(),
  share_percentage: z.number().nullable().optional(),
})

const cnaeEntrySchema = z.object({
  code: z.string(),
  description: z.string().nullable().default(null),
})

export const customerSchema = z.object({
  type: z.enum(['PF', 'PJ']),
  name: requiredString('Nome e obrigatorio').max(255),
  trade_name: boundedOptionalString(255),
  document: boundedOptionalString(20),
  notes: optionalString,
  is_active: z.boolean().default(true),
  latitude: optionalString,
  longitude: optionalString,
  google_maps_link: boundedOptionalString(500),
  state_registration: boundedOptionalString(30),
  municipal_registration: boundedOptionalString(30),
  cnae_code: boundedOptionalString(10),
  cnae_description: boundedOptionalString(255),
  legal_nature: boundedOptionalString(255),
  capital: optionalString,
  simples_nacional: z.boolean().nullable().default(null),
  mei: z.boolean().nullable().default(null),
  company_status: boundedOptionalString(50),
  opened_at: optionalString,
  is_rural_producer: z.boolean().default(false),
  partners: z.array(partnerSchema).default([]),
  secondary_activities: z.array(cnaeEntrySchema).default([]),
  source: optionalString,
  segment: optionalString,
  company_size: optionalString,
  rating: optionalString,
  assigned_seller_id: optionalString,
  annual_revenue_estimate: optionalString,
  contract_type: optionalString,
  contract_start: optionalString,
  contract_end: optionalString,
  contacts: z.array(customerContactSchema).default([]),
}).merge(addressSchema).merge(contactSchema).superRefine((data, ctx) => {
  const documentDigits = data.document.replace(/\D/g, '')
  if (documentDigits.length > 0 && documentDigits.length !== 11 && documentDigits.length !== 14) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['document'],
      message: 'Documento deve ter 11 ou 14 digitos',
    })
  }

  if (data.address_state && data.address_state.length > 2) {
    ctx.addIssue({
      code: z.ZodIssueCode.too_big,
      path: ['address_state'],
      maximum: 2,
      inclusive: true,
      origin: 'string',
      message: 'UF deve ter no maximo 2 caracteres',
    })
  }

  if (data.contract_start && data.contract_end && data.contract_end < data.contract_start) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['contract_end'],
      message: 'A data final do contrato deve ser posterior ou igual a inicial',
    })
  }
})

export type CustomerFormData = z.infer<typeof customerSchema>
