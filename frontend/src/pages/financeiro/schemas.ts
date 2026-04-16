import { z } from 'zod'
import { requiredString, optionalString } from '@/schemas/common'

export const accountPayableSchema = z.object({
  supplier_id: z.string().optional(),
  category_id: z.string().min(1, 'Categoria é obrigatória'),
  chart_of_account_id: z.string().optional(),
  work_order_id: optionalString,
  description: z.string().min(1, 'Descrição é obrigatória'),
  amount: z.string().refine((val) => parseFloat(val) > 0, 'Valor deve ser superior a zero'),
  due_date: z.string().min(1, 'Data de vencimento é obrigatória'),
  payment_method: z.string().optional(),
  notes: z.string().optional(),
  penalty_amount: z.string().optional(),
  interest_amount: z.string().optional(),
  discount_amount: z.string().optional(),
  cost_center_id: z.string().optional(),
})

export type AccountPayableFormData = z.infer<typeof accountPayableSchema>

export const payAccountPayableSchema = z.object({
  amount: z.string().refine((val) => parseFloat(val) > 0, 'Valor deve ser superior a zero'),
  payment_method: z.string().min(1, 'Forma de pagamento é obrigatória'),
  payment_date: z.string().min(1, 'Data do pagamento é obrigatória'),
  notes: z.string().optional(),
})

export type PayAccountPayableFormData = z.infer<typeof payAccountPayableSchema>

export const accountReceivableSchema = z.object({
  customer_id: z.string().min(1, 'Cliente é obrigatório'),
  work_order_id: z.string().optional(),
  chart_of_account_id: z.string().optional(),
  description: z.string().min(1, 'Descrição é obrigatória'),
  amount: z.string().refine((val) => parseFloat(val) > 0, 'Valor deve ser superior a zero'),
  due_date: z.string().min(1, 'Data de vencimento é obrigatória'),
  payment_method: z.string().optional(),
  notes: z.string().optional(),
  penalty_amount: z.string().optional(),
  interest_amount: z.string().optional(),
  discount_amount: z.string().optional(),
  cost_center_id: z.string().optional(),
})

export type AccountReceivableFormData = z.infer<typeof accountReceivableSchema>

export const payAccountReceivableSchema = z.object({
  amount: z.string().refine((val) => parseFloat(val) > 0, 'Valor deve ser superior a zero'),
  payment_method: z.string().min(1, 'Forma de pagamento é obrigatória'),
  payment_date: z.string().min(1, 'Data de recebimento é obrigatória'),
  notes: z.string().optional(),
})

export type PayAccountReceivableFormData = z.infer<typeof payAccountReceivableSchema>

export const genOsReceivableSchema = z.object({
  work_order_id: z.string().min(1, 'OS é obrigatória'),
  due_date: z.string().min(1, 'Data de vencimento é obrigatória'),
  payment_method: z.string().optional(),
})

export type GenOsReceivableFormData = z.infer<typeof genOsReceivableSchema>

export const invoiceSchema = z.object({
  customer_id: requiredString('Cliente é obrigatório'),
  work_order_id: optionalString,
  nf_number: optionalString,
  due_date: optionalString,
  observations: optionalString,
})

export type InvoiceFormData = z.infer<typeof invoiceSchema>

export const supplierAdvanceSchema = z.object({
  supplier_id: requiredString('Fornecedor é obrigatório'),
  description: requiredString('Descrição é obrigatória'),
  amount: requiredString('Valor é obrigatório').min(1, 'Valor obrigatório'),
  due_date: requiredString('Vencimento é obrigatório'),
  notes: optionalString,
})

export type SupplierAdvanceFormData = z.infer<typeof supplierAdvanceSchema>

export const reconciliationRuleSchema = z.object({
  name: requiredString('Nome da regra é obrigatório'),
  match_field: requiredString('Campo de match é obrigatório'),
  match_operator: requiredString('Operador é obrigatório'),
  match_value: optionalString,
  match_amount_min: optionalString,
  match_amount_max: optionalString,
  action: requiredString('Ação é obrigatória'),
  category: optionalString,
  priority: requiredString('Prioridade é obrigatória'),
  is_active: z.boolean().default(true),
})

export type ReconciliationRuleForm = z.infer<typeof reconciliationRuleSchema>

export const fuelingLogSchema = z.object({
  vehicle_plate: requiredString('Placa do veículo é obrigatória'),
  odometer_km: z.coerce.number().min(0, 'Odômetro inválido'),
  fuel_type: requiredString('Tipo de combustível é obrigatório'),
  liters: z.coerce.number().min(0.01, 'Litros inválido'),
  price_per_liter: z.coerce.number().min(0.01, 'Preço inválido'),
  total_amount: z.coerce.number().min(0, 'Total inválido'),
  date: requiredString('Data é obrigatória'),
  gas_station: optionalString,
  notes: optionalString,
  work_order_id: z.number().nullable().optional(),
})

export type FuelingLogFormData = z.infer<typeof fuelingLogSchema>

export const debtRenegotiationSchema = z.object({
  description: requiredString('Descrição é obrigatória'),
  installments: z.coerce.number().min(1, 'Mínimo de 1 parcela').max(48, 'Máximo de 48 parcelas'),
  discount_percentage: z.coerce.number().min(0, 'Minimo é 0').max(100, 'Maximo é 100').default(0),
  interest_rate: z.coerce.number().min(0, 'Juros não pode ser negativo').default(0),
  new_due_date: requiredString('Data do primeiro vencimento é obrigatória'),
  notes: optionalString,
})

export type DebtRenegotiationFormData = z.infer<typeof debtRenegotiationSchema>

export const taxCalculatorSchema = z.object({
  gross_amount: z.coerce.number().min(0.01, 'O valor bruto é obrigatório'),
  service_type: requiredString('Tipo de serviço é obrigatório').default('calibracao'),
  tax_regime: requiredString('Regime tributário é obrigatório').default('simples_nacional'),
})

export type TaxCalculatorFormData = z.infer<typeof taxCalculatorSchema>
