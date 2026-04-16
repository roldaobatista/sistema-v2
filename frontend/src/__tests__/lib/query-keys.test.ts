import { describe, it, expect } from 'vitest'
import { queryKeys } from '@/lib/query-keys'

describe('queryKeys', () => {
    describe('customers', () => {
        it('customers.all retorna array readonly', () => {
            expect(queryKeys.customers.all).toEqual(['customers'])
        })

        it('customers.list inclui params', () => {
            const key = queryKeys.customers.list({ search: 'test', page: 1 })
            expect(key).toEqual(['customers', { search: 'test', page: 1 }])
        })

        it('customers.detail inclui id', () => {
            expect(queryKeys.customers.detail(42)).toEqual(['customers', 42])
        })

        it('customers.contacts inclui id', () => {
            expect(queryKeys.customers.contacts(10)).toEqual(['customers', 10, 'contacts'])
        })
    })

    describe('suppliers', () => {
        it('suppliers.all retorna array', () => {
            expect(queryKeys.suppliers.all).toEqual(['suppliers'])
        })

        it('suppliers.list inclui params', () => {
            const key = queryKeys.suppliers.list({ page: 2 })
            expect(key).toEqual(['suppliers', { page: 2 }])
        })
    })

    describe('workOrders', () => {
        it('workOrders.detail inclui id', () => {
            expect(queryKeys.workOrders.detail(1)).toEqual(['work-orders', 1])
        })
    })

    describe('quotes', () => {
        it('quotes.list inclui params', () => {
            expect(queryKeys.quotes.list({})).toEqual(['quotes', {}])
        })
    })

    describe('users', () => {
        it('users.detail inclui id', () => {
            expect(queryKeys.users.detail(5)).toEqual(['users', 5])
        })
    })

    describe('agenda', () => {
        it('central.items.detail inclui id', () => {
            expect(queryKeys.central.items.detail(3)).toEqual(['central-items', 3])
        })
    })
})
