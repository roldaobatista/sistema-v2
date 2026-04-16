import { describe, it, expect } from 'vitest'
import { supplierSchema } from '@/schemas/supplier'

describe('supplierSchema', () => {
    it('rejeita nome vazio', () => {
        const result = supplierSchema.safeParse({ name: '', type: 'PJ' })
        expect(result.success).toBe(false)
    })

    it('aceita dados válidos mínimos', () => {
        const result = supplierSchema.safeParse({ name: 'Test', type: 'PJ' })
        expect(result.success).toBe(true)
    })

    it('rejeita email inválido', () => {
        const result = supplierSchema.safeParse({ name: 'Test', type: 'PJ', email: 'bad' })
        expect(result.success).toBe(false)
    })

    it('aceita email vazio', () => {
        const result = supplierSchema.safeParse({ name: 'Test', type: 'PJ', email: '' })
        expect(result.success).toBe(true)
    })

    it('rejeita type inválido', () => {
        const result = supplierSchema.safeParse({ name: 'Test', type: 'XX' })
        expect(result.success).toBe(false)
    })

    it('aceita PF e PJ', () => {
        expect(supplierSchema.safeParse({ name: 'A', type: 'PF' }).success).toBe(true)
        expect(supplierSchema.safeParse({ name: 'B', type: 'PJ' }).success).toBe(true)
    })
})
