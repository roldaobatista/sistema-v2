import { describe, it, expect } from 'vitest'
import {
    DEAL_STATUS,
    QUOTE_STATUS,
    WORK_ORDER_STATUS,
    SERVICE_CALL_STATUS,
    FINANCIAL_STATUS,
    COMMISSION_STATUS,
    EXPENSE_STATUS,
    EQUIPMENT_STATUS,
    CENTRAL_ITEM_STATUS,
    IMPORT_ROW_STATUS,
    BANK_ENTRY_STATUS,
    MESSAGE_STATUS,
} from '@/lib/constants'

describe('Status Constants', () => {
    it('DEAL_STATUS should have correct values', () => {
        expect(DEAL_STATUS.OPEN).toBe('open')
        expect(DEAL_STATUS.WON).toBe('won')
        expect(DEAL_STATUS.LOST).toBe('lost')
        expect(Object.keys(DEAL_STATUS)).toHaveLength(3)
    })

    it('QUOTE_STATUS should have all expected statuses', () => {
        const expected = ['DRAFT', 'PENDING_INTERNAL', 'INTERNALLY_APPROVED', 'SENT', 'APPROVED', 'REJECTED', 'EXPIRED', 'IN_EXECUTION', 'INSTALLATION_TESTING', 'RENEGOTIATION', 'INVOICED']
        expect(Object.keys(QUOTE_STATUS)).toEqual(expected)
        expect(QUOTE_STATUS.DRAFT).toBe('draft')
        expect(QUOTE_STATUS.PENDING_INTERNAL).toBe('pending_internal_approval')
        expect(QUOTE_STATUS.INTERNALLY_APPROVED).toBe('internally_approved')
        expect(QUOTE_STATUS.INVOICED).toBe('invoiced')
    })

    it('WORK_ORDER_STATUS should have all workflow states', () => {
        const keys = Object.keys(WORK_ORDER_STATUS)
        expect(keys).toContain('OPEN')
        expect(keys).toContain('AWAITING_DISPATCH')
        expect(keys).toContain('IN_DISPLACEMENT')
        expect(keys).toContain('DISPLACEMENT_PAUSED')
        expect(keys).toContain('AT_CLIENT')
        expect(keys).toContain('IN_SERVICE')
        expect(keys).toContain('SERVICE_PAUSED')
        expect(keys).toContain('AWAITING_RETURN')
        expect(keys).toContain('IN_RETURN')
        expect(keys).toContain('RETURN_PAUSED')
        expect(keys).toContain('IN_PROGRESS')
        expect(keys).toContain('WAITING_PARTS')
        expect(keys).toContain('WAITING_APPROVAL')
        expect(keys).toContain('COMPLETED')
        expect(keys).toContain('DELIVERED')
        expect(keys).toContain('INVOICED')
        expect(keys).toContain('CANCELLED')
        expect(keys).toHaveLength(17)
    })

    it('SERVICE_CALL_STATUS should have correct values', () => {
        expect(SERVICE_CALL_STATUS.PENDING_SCHEDULING).toBe('pending_scheduling')
        expect(SERVICE_CALL_STATUS.SCHEDULED).toBe('scheduled')
        expect(SERVICE_CALL_STATUS.RESCHEDULED).toBe('rescheduled')
        expect(SERVICE_CALL_STATUS.AWAITING_CONFIRMATION).toBe('awaiting_confirmation')
        expect(SERVICE_CALL_STATUS.CONVERTED_TO_OS).toBe('converted_to_os')
        expect(SERVICE_CALL_STATUS.CANCELLED).toBe('cancelled')
        expect(SERVICE_CALL_STATUS.OPEN).toBe('pending_scheduling')
        expect(Object.keys(SERVICE_CALL_STATUS)).toHaveLength(7)
    })

    it('FINANCIAL_STATUS should include all payment states', () => {
        expect(FINANCIAL_STATUS.PENDING).toBe('pending')
        expect(FINANCIAL_STATUS.PARTIAL).toBe('partial')
        expect(FINANCIAL_STATUS.PAID).toBe('paid')
        expect(FINANCIAL_STATUS.OVERDUE).toBe('overdue')
        expect(FINANCIAL_STATUS.CANCELLED).toBe('cancelled')
        expect(FINANCIAL_STATUS.RENEGOTIATED).toBe('renegotiated')
    })

    it('COMMISSION_STATUS should have all commission lifecycle states', () => {
        expect(Object.keys(COMMISSION_STATUS)).toHaveLength(8)
        expect(COMMISSION_STATUS.PENDING).toBe('pending')
        expect(COMMISSION_STATUS.REVERSED).toBe('reversed')
    })

    it('EXPENSE_STATUS should have correct values', () => {
        expect(Object.keys(EXPENSE_STATUS)).toHaveLength(5)
        expect(EXPENSE_STATUS.REVIEWED).toBe('reviewed')
        expect(EXPENSE_STATUS.REIMBURSED).toBe('reimbursed')
    })

    it('EQUIPMENT_STATUS should use English lowercase values', () => {
        expect(EQUIPMENT_STATUS.ACTIVE).toBe('active')
        expect(EQUIPMENT_STATUS.IN_CALIBRATION).toBe('in_calibration')
        expect(EQUIPMENT_STATUS.DISCARDED).toBe('discarded')
    })

    it('CENTRAL_ITEM_STATUS should use English lowercase values', () => {
        expect(CENTRAL_ITEM_STATUS.OPEN).toBe('open')
        expect(CENTRAL_ITEM_STATUS.COMPLETED).toBe('completed')
    })

    it('IMPORT_ROW_STATUS should have validation states', () => {
        expect(IMPORT_ROW_STATUS.VALID).toBe('valid')
        expect(IMPORT_ROW_STATUS.WARNING).toBe('warning')
        expect(IMPORT_ROW_STATUS.ERROR).toBe('error')
    })

    it('BANK_ENTRY_STATUS should have reconciliation states', () => {
        expect(Object.keys(BANK_ENTRY_STATUS)).toHaveLength(3)
        expect(BANK_ENTRY_STATUS.MATCHED).toBe('matched')
    })

    it('MESSAGE_STATUS should have delivery lifecycle states', () => {
        expect(Object.keys(MESSAGE_STATUS)).toHaveLength(5)
        expect(MESSAGE_STATUS.DELIVERED).toBe('delivered')
        expect(MESSAGE_STATUS.FAILED).toBe('failed')
    })
})
