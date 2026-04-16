import type { CrmActivity } from '@/lib/crm-api'

export interface Customer {
  id: number
  tenant_id?: number
  type: 'PF' | 'PJ'
  name: string
  trade_name: string | null
  document: string | null
  email: string | null
  phone: string | null
  phone2?: string | null
  address_zip?: string | null
  address_street?: string | null
  address_number?: string | null
  address_complement?: string | null
  address_neighborhood?: string | null
  address_city?: string | null
  address_state?: string | null
  latitude?: number | string | null
  longitude?: number | string | null
  google_maps_link?: string | null
  notes?: string | null
  is_active?: boolean
  // Enrichment fields (CNPJ lookup)
  state_registration?: string | null
  municipal_registration?: string | null
  cnae_code?: string | null
  cnae_description?: string | null
  legal_nature?: string | null
  capital?: number | string | null
  simples_nacional?: boolean | null
  mei?: boolean | null
  company_status?: string | null
  opened_at?: string | null
  is_rural_producer?: boolean | null
  partners?: CustomerPartner[] | null
  secondary_activities?: CnaeEntry[] | null
  enrichment_data?: Record<string, unknown> | null
  enriched_at?: string | null
  // CRM fields
  source?: string | null
  segment?: string | null
  company_size?: string | null
  annual_revenue_estimate?: number | string | null
  contract_type?: string | null
  contract_start?: string | null
  contract_end?: string | null
  health_score?: number | null
  last_contact_at?: string | null
  next_follow_up_at?: string | null
  assigned_seller_id?: number | null
  tags?: string[] | null
  rating?: string | null
  // Computed
  nearest_calibration_at?: string | null
  created_at?: string
  updated_at?: string
}

export interface CustomerContact {
  id?: number
  name: string
  role?: string | null
  phone?: string | null
  email?: string | null
  is_primary?: boolean
}

export interface CustomerWithContacts extends Customer {
  contacts?: CustomerContact[]
  assigned_seller?: {
    id: number
    name: string
  } | null
  documents_count?: number | null
}

export interface Customer360DataSummary {
  name: string
  trade_name?: string
  is_active: boolean
  rating?: string
  contract_type?: string
  contract_start?: string
  contract_end?: string
  phone?: string
  phone2?: string
  email?: string
  document?: string
  address_city?: string
  address_state?: string
  address_street?: string
  address_number?: string
  address_neighborhood?: string
  latitude?: number
  longitude?: number
  health_score?: number
  segment?: string
  assigned_seller?: { name: string }
  created_at: string
  last_contact_at?: string
  notes?: string
  contacts?: CustomerContact[]
}

export interface Customer360WorkOrderItem {
  id: number
  number: string
  created_at: string
  status: string
  total: number | string
}

export interface Customer360ServiceCallItem {
  id: number
  call_number?: string
  protocol?: string
  observations?: string
  subject?: string
  priority: string
  status: string
}

export interface Customer360EquipmentItem {
  id: number
  code: string
  brand: string
  model: string
  category?: string
  calibration_status: string
  next_calibration_at?: string
  tracking_url: string
}

export interface Customer360QuoteItem {
  id: number
  quote_number: string
  created_at: string
  status: string
  total: number | string
}

export interface Customer360ReceivableItem {
  id: number
  due_date: string
  description: string
  work_order?: { number: string }
  status: string
  amount: number | string
}

export interface Customer360FiscalNoteItem {
  id: number
  number: string
  created_at: string
  total_value: number | string
  file_url?: string
}

export interface Customer360Metrics {
  churn_risk: string
  last_contact_days: number
  ltv: number
  conversion_rate: number
  forecast: { name: string; count: number }[]
  trend: { date: string; error: number; uncertainty: number }[]
  main_equipment_name: string | null
  radar: { subject: string; value: number }[]
  benchmarking?: { name: string; value: string | number }[]
}

export interface Customer360DocumentItem {
  id: number
  equipment_id?: number | null
  file_name?: string | null
  file_path?: string | null
  created_at: string
  equipment?: {
    id: number
    code?: string | null
    brand?: string | null
    model?: string | null
  } | null
}

export interface Customer360DealItem {
  id: number
  title: string
  value: number | string
  status: 'open' | 'won' | 'lost'
  stage?: { id: number; name: string } | null
  pipeline?: { id: number; name: string } | null
  expected_close_date?: string | null
  created_at?: string
}

export interface Customer360Data {
  customer: Customer360DataSummary
  health_breakdown?: Record<string, { score: number; max: number; label: string }>
  equipments: Customer360EquipmentItem[]
  deals: Customer360DealItem[]
  timeline: CrmActivity[]
  work_orders: Customer360WorkOrderItem[]
  service_calls: Customer360ServiceCallItem[]
  quotes: Customer360QuoteItem[]
  receivables: Customer360ReceivableItem[]
  pending_receivables: number
  documents: Customer360DocumentItem[]
  fiscal_notes: Customer360FiscalNoteItem[]
  metrics: Customer360Metrics
}

export interface CustomerPartner {
  name: string | null
  role: string | null
  document: string | null
  entry_date?: string | null
  share_percentage?: number | null
}

export interface CnaeEntry {
  code: string
  description: string | null
}

export interface CustomerFormData {
  type: 'PF' | 'PJ'
  name: string
  trade_name: string
  document: string
  email: string
  phone: string
  phone2: string
  notes: string
  is_active: boolean
  address_zip: string
  address_street: string
  address_number: string
  address_complement: string
  address_neighborhood: string
  address_city: string
  address_state: string
  latitude?: string
  longitude?: string
  google_maps_link?: string
  state_registration?: string
  municipal_registration?: string
  cnae_code?: string
  cnae_description?: string
  legal_nature?: string
  capital?: string
  simples_nacional?: boolean | null
  mei?: boolean | null
  company_status?: string
  opened_at?: string
  is_rural_producer?: boolean
  partners?: CustomerPartner[]
  secondary_activities?: CnaeEntry[]
  source?: string
  segment?: string
  company_size?: string
  rating?: string
  assigned_seller_id?: string
  annual_revenue_estimate?: string
  contract_type?: string
  contract_start?: string
  contract_end?: string
  contacts?: CustomerContact[]
}

/** Resposta de dependências ao tentar excluir cliente */
export interface DeleteDependencies {
  work_orders?: number
  active_work_orders?: number
  receivables?: number
  quotes?: number
  deals?: number
  service_calls?: number
  equipments?: number
}

export interface CustomerListParams {
  search?: string
  type?: 'PF' | 'PJ'
  is_active?: boolean
  segment?: string
  rating?: string
  source?: string
  assigned_seller_id?: string | number
  sort?: 'name' | 'created_at' | 'health_score' | 'last_contact_at' | 'rating'
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface CustomerListResponse {
  data: CustomerWithContacts[]
  meta?: {
    total?: number
    last_page?: number
  }
  total?: number
  last_page?: number
}

export interface CustomerOptions {
  sources?: Record<string, string>
  segments?: Record<string, string>
  company_sizes?: Record<string, string>
  ratings?: Record<string, string>
  contract_types?: Record<string, string>
}

export interface CustomerDocument {
  id: number
  title: string
  type: string
  file_path: string
  file_name: string
  file_size: number
  expiry_date: string | null
  notes: string | null
  created_at: string
  uploader?: {
    id: number
    name: string
  } | null
}

export interface CustomerDuplicateRecord {
  id: number
  name: string
  document: string
  email: string
  created_at: string
}

export interface CustomerDuplicateGroup {
  key: string
  count: number
  customers: CustomerDuplicateRecord[]
}
