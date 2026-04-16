import { describe, it, expect } from 'vitest'
import {
    addressSchema,
    contactSchema,
    optionalString,
    requiredString,
    optionalEmail,
    optionalDecimal,
    requiredPositive,
} from '@/schemas/common'

describe('common schemas', () => {
    describe('addressSchema', () => {
        it('aceita objeto vazio com defaults', () => {
            const result = addressSchema.safeParse({})
            expect(result.success).toBe(true)
            if (result.success) {
                expect(result.data.address_zip).toBe('')
                expect(result.data.address_city).toBe('')
            }
        })

        it('aceita campos preenchidos', () => {
            const result = addressSchema.safeParse({
                address_zip: '01310-100',
                address_street: 'Av Paulista',
                address_city: 'São Paulo',
                address_state: 'SP',
            })
            expect(result.success).toBe(true)
            if (result.success) {
                expect(result.data.address_zip).toBe('01310-100')
                expect(result.data.address_state).toBe('SP')
            }
        })
    })

    describe('contactSchema', () => {
        it('rejeita email inválido', () => {
            const result = contactSchema.safeParse({ email: 'invalid' })
            expect(result.success).toBe(false)
        })

        it('aceita email vazio', () => {
            const result = contactSchema.safeParse({ email: '' })
            expect(result.success).toBe(true)
        })

        it('aceita email válido', () => {
            const result = contactSchema.safeParse({ email: 'a@b.com' })
            expect(result.success).toBe(true)
        })
    })

    describe('requiredString', () => {
        it('rejeita string vazia', () => {
            const schema = requiredString('Nome obrigatório')
            expect(schema.safeParse('').success).toBe(false)
        })

        it('aceita string não vazia', () => {
            const schema = requiredString()
            expect(schema.safeParse('test').success).toBe(true)
        })
    })

    describe('optionalString', () => {
        it('default vazio', () => {
            const result = optionalString.safeParse(undefined)
            expect(result.success).toBe(true)
            if (result.success) expect(result.data).toBe('')
        })
    })

    describe('optionalEmail', () => {
        it('rejeita email inválido', () => {
            expect(optionalEmail.safeParse('x').success).toBe(false)
        })

        it('aceita email válido ou vazio', () => {
            expect(optionalEmail.safeParse('').success).toBe(true)
            expect(optionalEmail.safeParse('a@b.com').success).toBe(true)
        })
    })

    describe('optionalDecimal', () => {
        it('aceita número e default 0', () => {
            const r = optionalDecimal.safeParse(undefined)
            expect(r.success).toBe(true)
            if (r.success) expect(r.data).toBe(0)
        })
    })

    describe('requiredPositive', () => {
        it('rejeita zero ou negativo', () => {
            const schema = requiredPositive()
            expect(schema.safeParse(0).success).toBe(false)
            expect(schema.safeParse(-1).success).toBe(false)
        })

        it('aceita positivo', () => {
            const schema = requiredPositive()
            expect(schema.safeParse(1).success).toBe(true)
        })
    })
})
