export interface FiscalNote {
  id: number
  type: 'nfe' | 'nfse'
  number: string | null
  series: string | null
  access_key: string | null
  reference: string | null
  status: 'pending' | 'processing' | 'authorized' | 'cancelled' | 'rejected'
  provider: string
  total_amount: string
  contingency_mode: boolean
  verification_code: string | null
  issued_at: string | null
  cancelled_at: string | null
  error_message: string | null
  pdf_url: string | null
  pdf_path: string | null
  xml_url: string | null
  xml_path: string | null
  nature_of_operation?: string | null
  cfop?: string | null
  cancel_reason?: string | null
  environment?: string | null
  protocol_number?: string | null
  customer?: { id: number; name: string; email?: string }
  work_order?: { id: number; number: string } | null
  quote?: { id: number } | null
  creator?: { id: number; name: string }
  created_at: string
}

export interface FiscalConfig {
  provider?: string
  environment?: string
  fiscal_regime?: string
  cnae?: string
  inscricao_municipal?: string
  inscricao_estadual?: string
  ambiente?: string
  serie_nfe?: number
  serie_nfse?: number
  auto_send_email?: boolean
  [key: string]: unknown
}

export interface CertificateInfo {
  uploaded?: boolean
  expires_at?: string
  subject?: string
  issuer?: string
  days_until_expiry?: number
  [key: string]: unknown
}
