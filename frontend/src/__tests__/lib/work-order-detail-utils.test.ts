import { describe, expect, it } from 'vitest'
import {
    extractWorkOrderQrProduct,
    isPrivilegedFieldRole,
    isTechnicianLinkedToWorkOrder,
} from '@/lib/work-order-detail-utils'

describe('work-order-detail-utils', () => {
    it('identifica roles privilegiadas de campo', () => {
        expect(isPrivilegedFieldRole(['tecnico', 'admin'])).toBe(true)
        expect(isPrivilegedFieldRole(['tecnico'])).toBe(false)
    })

    it('considera vinculo por responsavel, technician_ids ou relacao technicians', () => {
        expect(isTechnicianLinkedToWorkOrder({ assigned_to: 10 }, 10)).toBe(true)
        expect(isTechnicianLinkedToWorkOrder({ technician_ids: [11, 12] }, 12)).toBe(true)
        expect(isTechnicianLinkedToWorkOrder({ technicians: [{ id: 13 }] }, 13)).toBe(true)
        expect(isTechnicianLinkedToWorkOrder({ technicians: [{ id: 99 }] }, 10)).toBe(false)
    })

    it('libera o fluxo de execucao para roles privilegiadas mesmo sem vinculo direto', () => {
        expect(isTechnicianLinkedToWorkOrder({ assigned_to: null, technician_ids: [] }, 77, true)).toBe(true)
    })

    it('normaliza produto de QR com envelope padrao da API', () => {
        expect(
            extractWorkOrderQrProduct({
                data: {
                    data: {
                        id: 5,
                        name: 'Valvula',
                        sell_price: '19.90',
                    },
                },
            }),
        ).toEqual({
            id: 5,
            name: 'Valvula',
            sell_price: '19.90',
        })
    })

    it('aceita payload legado com sale_price sem envelope', () => {
        expect(
            extractWorkOrderQrProduct({
                data: {
                    id: 8,
                    name: 'Sensor',
                    sale_price: 42.5,
                },
            }),
        ).toEqual({
            id: 8,
            name: 'Sensor',
            sell_price: 42.5,
        })
    })

    it('retorna null quando o payload nao representa um produto valido', () => {
        expect(extractWorkOrderQrProduct({ data: { data: [] } })).toBeNull()
        expect(extractWorkOrderQrProduct({ data: { data: { id: null } } })).toBeNull()
    })
})
