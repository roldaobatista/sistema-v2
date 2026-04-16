// ─── Status Constants ─────────────────────────────────────
// Centralized status values to eliminate hardcoded strings.
// Must stay in sync with backend model constants.

export const DEAL_STATUS = {
    OPEN: 'open',
    WON: 'won',
    LOST: 'lost',
} as const;

export const QUOTE_STATUS = {
    DRAFT: 'draft',
    PENDING_INTERNAL: 'pending_internal_approval',
    INTERNALLY_APPROVED: 'internally_approved',
    SENT: 'sent',
    APPROVED: 'approved',
    REJECTED: 'rejected',
    EXPIRED: 'expired',
    IN_EXECUTION: 'in_execution',
    INSTALLATION_TESTING: 'installation_testing',
    RENEGOTIATION: 'renegotiation',
    INVOICED: 'invoiced',
} as const;

export const WORK_ORDER_STATUS = {
    OPEN: 'open',
    AWAITING_DISPATCH: 'awaiting_dispatch',
    IN_DISPLACEMENT: 'in_displacement',
    DISPLACEMENT_PAUSED: 'displacement_paused',
    AT_CLIENT: 'at_client',
    IN_SERVICE: 'in_service',
    SERVICE_PAUSED: 'service_paused',
    AWAITING_RETURN: 'awaiting_return',
    IN_RETURN: 'in_return',
    RETURN_PAUSED: 'return_paused',
    IN_PROGRESS: 'in_progress',
    WAITING_PARTS: 'waiting_parts',
    WAITING_APPROVAL: 'waiting_approval',
    COMPLETED: 'completed',
    DELIVERED: 'delivered',
    INVOICED: 'invoiced',
    CANCELLED: 'cancelled',
} as const;

export const SERVICE_CALL_STATUS = {
    PENDING_SCHEDULING: 'pending_scheduling',
    SCHEDULED: 'scheduled',
    RESCHEDULED: 'rescheduled',
    AWAITING_CONFIRMATION: 'awaiting_confirmation',
    CONVERTED_TO_OS: 'converted_to_os',
    CANCELLED: 'cancelled',
    /** @deprecated alias for backward compat */
    OPEN: 'pending_scheduling',
} as const;

export const FINANCIAL_STATUS = {
    PENDING: 'pending',
    PARTIAL: 'partial',
    PAID: 'paid',
    OVERDUE: 'overdue',
    CANCELLED: 'cancelled',
    RENEGOTIATED: 'renegotiated',
} as const;

export const COMMISSION_STATUS = {
    PENDING: 'pending',
    APPROVED: 'approved',
    PAID: 'paid',
    REVERSED: 'reversed',
    REJECTED: 'rejected',
    OPEN: 'open',
    ACCEPTED: 'accepted',
    CLOSED: 'closed',
} as const;

export const EXPENSE_STATUS = {
    PENDING: 'pending',
    REVIEWED: 'reviewed',
    APPROVED: 'approved',
    REJECTED: 'rejected',
    REIMBURSED: 'reimbursed',
} as const;

export const EQUIPMENT_STATUS = {
    ACTIVE: 'active',
    IN_CALIBRATION: 'in_calibration',
    IN_MAINTENANCE: 'in_maintenance',
    OUT_OF_SERVICE: 'out_of_service',
    DISCARDED: 'discarded',
} as const;

export const CENTRAL_ITEM_STATUS = {
    OPEN: 'open',
    IN_PROGRESS: 'in_progress',
    COMPLETED: 'completed',
    CANCELLED: 'cancelled',
} as const;

export const IMPORT_ROW_STATUS = {
    VALID: 'valid',
    WARNING: 'warning',
    ERROR: 'error',
} as const;

export const BANK_ENTRY_STATUS = {
    PENDING: 'pending',
    MATCHED: 'matched',
    IGNORED: 'ignored',
} as const;

export const MESSAGE_STATUS = {
    PENDING: 'pending',
    SENT: 'sent',
    DELIVERED: 'delivered',
    READ: 'read',
    FAILED: 'failed',
} as const;

export const STORAGE_KEYS = {
    BIOMETRIC_CREDENTIAL: 'biometric_credential_id',
} as const;
