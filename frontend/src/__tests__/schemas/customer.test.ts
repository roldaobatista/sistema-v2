import { describe, it, expect } from 'vitest'
import { customerSchema } from '@/schemas/customer'

describe('customerSchema', () => {
    it('rejeita nome vazio', () => {
        const result = customerSchema.safeParse({ name: '', type: 'PJ' })
        expect(result.success).toBe(false)
    })

    it('aceita dados válidos mínimos', () => {
        const result = customerSchema.safeParse({ name: 'Cliente Teste', type: 'PJ' })
        expect(result.success).toBe(true)
    })

    it('rejeita type inválido', () => {
        const result = customerSchema.safeParse({ name: 'Test', type: 'XX' })
        expect(result.success).toBe(false)
    })

    it('aceita PF e PJ', () => {
        expect(customerSchema.safeParse({ name: 'A', type: 'PF' }).success).toBe(true)
        expect(customerSchema.safeParse({ name: 'B', type: 'PJ' }).success).toBe(true)
    })

    it('rejeita contato sem nome quando informado', () => {
        const result = customerSchema.safeParse({
            name: 'Cliente Teste',
            type: 'PJ',
            contacts: [{ name: '', email: 'contato@empresa.com' }],
        })

        expect(result.success).toBe(false)
    })

    it('rejeita contrato com data final anterior a inicial', () => {
        const result = customerSchema.safeParse({
            name: 'Cliente Contrato',
            type: 'PJ',
            contract_start: '2026-03-10',
            contract_end: '2026-03-09',
        })

        expect(result.success).toBe(false)
    })

    it('rejeita documento com tamanho invalido', () => {
        const result = customerSchema.safeParse({
            name: 'Cliente Documento',
            type: 'PJ',
            document: '123456789012',
        })

        expect(result.success).toBe(false)
    })
})
