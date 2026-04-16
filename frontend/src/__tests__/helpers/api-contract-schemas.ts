/**
 * API Contract Schemas — defines the expected shape of API responses.
 * Used by integration tests to validate that mocked data matches the
 * real API contract. If the backend changes its response format,
 * updating these schemas will cause all dependent tests to fail,
 * preventing false-positive mocks.
 */

// ── GENERIC RESPONSE WRAPPERS ──

export interface PaginatedResponse<T> {
    data: T[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
    links: {
        first: string
        last: string
        prev: string | null
        next: string | null
    }
}

export interface SingleResponse<T> {
    data: T
    message?: string
}

// ── AUTH ──

export interface LoginResponse {
    token: string
    user: {
        id: number
        name: string
        email: string
        tenant_id: number
        tenant: { id: number; name: string } | null
        permissions: string[]
        roles: string[]
    }
}

export interface MeResponse {
    user: {
        id: number
        name: string
        email: string
        phone: string | null
        tenant: { id: number; name: string } | null
        permissions: string[]
        roles: string[]
        last_login_at: string | null
    }
}

// ── CUSTOMER ──

export interface Customer {
    id: number
    tenant_id: number
    name: string
    type: 'PF' | 'PJ'
    document: string | null
    email: string | null
    phone: string | null
    created_at: string
    updated_at: string
}

// ── WORK ORDER ──

export interface WorkOrder {
    id: number
    tenant_id: number
    customer_id: number
    business_number: string
    description: string | null
    status: 'open' | 'awaiting_dispatch' | 'in_displacement' | 'displacement_paused' | 'at_client' | 'in_service' | 'service_paused' | 'awaiting_return' | 'in_return' | 'return_paused' | 'in_progress' | 'waiting_parts' | 'waiting_approval' | 'completed' | 'delivered' | 'invoiced' | 'cancelled'
    priority: 'low' | 'normal' | 'high' | 'urgent'
    total: number
    scheduled_date: string | null
    customer?: Customer
    created_at: string
    updated_at: string
}

// ── INVOICE ──

export interface Invoice {
    id: number
    tenant_id: number
    work_order_id: number
    customer_id: number
    invoice_number: string
    status: 'draft' | 'issued' | 'sent' | 'cancelled'
    total: number
    issued_at: string | null
    due_date: string | null
    created_at: string
}

// ── ACCOUNT RECEIVABLE ──

export interface AccountReceivable {
    id: number
    tenant_id: number
    customer_id: number
    description: string
    amount: number
    amount_paid: number
    status: 'pending' | 'partial' | 'paid' | 'overdue' | 'cancelled'
    due_date: string
    created_at: string
}

// ── QUOTE ──

export interface Quote {
    id: number
    tenant_id: number
    customer_id: number
    quote_number: string
    status: 'draft' | 'pending_internal_approval' | 'internally_approved' | 'sent' | 'approved' | 'rejected' | 'expired' | 'in_execution' | 'installation_testing' | 'renegotiation' | 'invoiced'
    total: number
    valid_until: string | null
    created_at: string
}

// ── PRODUCT ──

export interface Product {
    id: number
    tenant_id: number
    name: string
    code: string | null
    cost_price: number | string
    sell_price: number | string
    stock_qty: number | string
    stock_min: number | string
    is_active: boolean
    created_at: string
}

// ── VALIDATION HELPERS ──

export function validateShape<T>(data: unknown, requiredKeys: (keyof T)[]): boolean {
    if (!data || typeof data !== 'object') return false
    return requiredKeys.every(key => key in (data as Record<string, any>))
}

export function validateCustomer(data: any): data is Customer {
    return validateShape<Customer>(data, ['id', 'name', 'type', 'tenant_id'])
}

export function validateWorkOrder(data: any): data is WorkOrder {
    return validateShape<WorkOrder>(data, ['id', 'customer_id', 'status', 'tenant_id'])
}

export function validateInvoice(data: any): data is Invoice {
    return validateShape<Invoice>(data, ['id', 'invoice_number', 'status', 'total'])
}

export function validateLoginResponse(data: any): data is LoginResponse {
    return validateShape<LoginResponse>(data, ['token', 'user'])
}
