export interface QuoteItem {
    id: number;
    tenant_id: number;
    quote_equipment_id: number;
    type: 'product' | 'service';
    product_id: number | null;
    service_id: number | null;
    custom_description: string | null;
    quantity: number;
    original_price: number;
    cost_price: number;
    unit_price: number;
    discount_percentage: number;
    subtotal: number;
    sort_order: number;
    internal_note: string | null;
    created_at: string;
    updated_at: string;
    // Appended
    description: string | null;
    // Relations
    product?: { id: number; name: string };
    service?: { id: number; name: string };
}

export interface QuoteEquipment {
    id: number;
    tenant_id: number;
    quote_id: number;
    equipment_id: number | null;
    description: string | null;
    sort_order: number;
    created_at: string;
    updated_at: string;
    // Relations
    equipment?: { id: number; name?: string; model?: string; brand?: string; serial_number?: string; tag?: string };
    items?: QuoteItem[];
    photos?: QuotePhoto[];
}

export interface QuotePhoto {
    id: number;
    tenant_id: number;
    quote_equipment_id: number | null;
    quote_item_id: number | null;
    path: string;
    caption: string | null;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

export type QuoteStatus =
  | 'draft'
  | 'pending_internal_approval'
  | 'internally_approved'
  | 'sent'
  | 'approved'
  | 'rejected'
  | 'expired'
  | 'in_execution'
  | 'installation_testing'
  | 'renegotiation'
  | 'invoiced'

export interface Quote {
    id: number;
    tenant_id: number;
    quote_number: string;
    revision: number;
    customer_id: number;
    seller_id: number;
    created_by: number | null;
    status: QuoteStatus;
    status_label?: string | null;
    status_color?: string | null;
    source: string | null;
    valid_until: string | null;
    discount_percentage: number;
    discount_amount: number;
    displacement_value: number;
    subtotal: number;
    total: number;
    observations: string | null;
    internal_notes: string | null;
    general_conditions: string | null;
    payment_terms: string | null;
    payment_terms_detail: string | null;
    payment_method_label?: string | null;
    payment_condition_summary?: string | null;
    payment_detail_text?: string | null;
    payment_schedule?: {
        title: string;
        days: number;
        due_date: string | null;
        text: string;
    }[];
    template_id: number | null;
    opportunity_id: number | null;
    currency: string;
    validity_days: number | null;
    custom_fields: Record<string, unknown> | null;
    is_template: boolean;
    internal_approved_by: number | null;
    internal_approved_at: string | null;
    level2_approved_by: number | null;
    level2_approved_at: string | null;
    sent_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    last_followup_at: string | null;
    followup_count: number;
    client_viewed_at: string | null;
    client_view_count: number;
    is_installation_testing: boolean;
    // Public approval fields
    magic_token?: string | null;
    client_ip_approval?: string | null;
    term_accepted_at?: string | null;
    approval_channel?: string | null;
    approval_notes?: string | null;
    approved_by_name?: string | null;
    // Computed
    approval_url?: string;
    pdf_url?: string;
    approval_token?: string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    // Relations
    customer?: { id: number; name: string; document?: string; email?: string; phone?: string; contacts?: unknown[] };
    seller?: { id: number; name: string };
    creator?: { id: number; name: string } | null;
    internal_approver?: { id: number; name: string } | null;
    level2_approver?: { id: number; name: string } | null;
    template?: { id: number; name?: string } | null;
    equipments?: QuoteEquipment[];
    tags?: { id: number; name: string; color: string | null }[];
    emails?: QuoteEmailLog[];
    work_orders?: { id: number; quote_id: number; number: string; os_number: string | null; status: string; created_at: string }[];
    service_calls?: { id: number; quote_id: number; call_number: string; status: string; created_at: string }[];
    account_receivables?: { id: number; quote_id: number; amount: number; amount_paid: number; due_date: string; status: string; description: string }[];
}

export interface QuoteSummary {
    draft: number;
    pending_internal_approval: number;
    internally_approved: number;
    sent: number;
    approved: number;
    rejected: number;
    expired: number;
    in_execution: number;
    installation_testing: number;
    renegotiation: number;
    invoiced: number;
    total_month: number;
    conversion_rate: number;
}

export interface QuoteTemplate {
    id: number;
    tenant_id: number;
    name: string;
    warranty_terms: string | null;
    payment_terms_text: string | null;
    general_conditions: string | null;
    delivery_terms: string | null;
    is_default: boolean;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface QuoteTimelineEntry {
    id: number;
    action: string;
    action_label?: string;
    description: string;
    user_id?: number;
    user_name?: string | null;
    created_at: string;
    old_values?: Record<string, unknown> | null;
    new_values?: Record<string, unknown> | null;
}

export interface QuoteInstallment {
    installments: number;
    value: number;
}

export interface QuoteEmailLog {
    id: number;
    recipient_email: string;
    recipient_name?: string | null;
    subject: string;
    status: string;
    message_body?: string | null;
    pdf_attached: boolean;
    queued_at?: string | null;
    sent_at?: string | null;
    failed_at?: string | null;
    error_message?: string | null;
    created_at?: string;
    updated_at?: string;
}

/** Step do wizard de criação de orçamento */
export type QuoteCreateStep = 'customer' | 'equipments' | 'review'

/** Bloco equipamento + itens no formulário de criação */
export interface QuoteEquipmentBlockForm {
    equipment_id: number
    equipmentName: string
    description: string
    items: QuoteItemRowForm[]
}

/** Linha de item (produto/serviço) no formulário de orçamento */
export interface QuoteItemRowForm {
    type: 'product' | 'service'
    product_id?: number
    service_id?: number
    name: string
    quantity: number
    original_price: number
    unit_price: number
    discount_percentage: number
    discount_mode: 'percent' | 'value'
    discount_value: number
}

/** Opção de produto em combos de orçamento */
export interface QuoteProductOption {
    id: number
    name?: string
    sell_price?: number
}

/** Opção de serviço em combos de orçamento */
export interface QuoteServiceOption {
    id: number
    name?: string
    default_price?: number
}

/** Item da proposta pública (visualização do cliente) */
export interface PublicQuoteItem {
    id: number
    description: string
    quantity: number
    unit_price: number
    subtotal: number
}

/** Payload da proposta pública (visualização do cliente) */
export interface PublicQuotePayload {
    id: number
    quote_number: string
    reference: string
    total: number
    valid_until: string | null
    items: PublicQuoteItem[]
    customer_name: string
    company_name: string
    pdf_url?: string
    payment_terms?: string | null
    general_conditions?: string | null
}

/** Item no formulário de edição de orçamento */
export interface QuoteItemForm {
    type: 'product' | 'service'
    product_id?: number | null
    service_id?: number | null
    custom_description: string
    quantity: number
    original_price: number
    unit_price: number
    discount_percentage: number
    discount_mode: 'percent' | 'value'
    discount_value: number
}

/** Canal de aprovação do orçamento */
export type ApprovalChannel = 'whatsapp' | 'email' | 'phone' | 'in_person' | 'portal' | 'integration' | 'other'

/** Resumo avançado de orçamentos (dashboard) */
export interface AdvancedQuoteSummary {
    total_quotes: number
    total_approved: number
    conversion_rate: number
    avg_ticket: number
    avg_conversion_days: number
    top_sellers: {
        seller_id: number
        total_approved: number
        total_value: number
        seller?: { id: number; name: string }
    }[]
    monthly_trend: {
        month: string
        total: number
        approved: number
    }[]
}

/** Payload para transição de status no Kanban */
export type TransitionPayload = Record<string, string | number | boolean>

/** Definição de transição de status */
export interface TransitionDef {
    target: string
    endpoint: string
    method?: 'post'
    payload?: TransitionPayload
}
