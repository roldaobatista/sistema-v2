import { describe, expect, it } from 'vitest'

import { normalizeResponseData } from '@/lib/api'

describe('normalizeResponseData', () => {
    it('preserva itens e metadados em respostas paginadas', () => {
        const response = normalizeResponseData({
            data: [{ id: 1 }, { id: 2 }],
            current_page: 2,
            last_page: 5,
            per_page: 20,
            total: 94,
            from: 21,
            to: 40,
        })

        expect(Array.isArray(response)).toBe(true)
        expect(response).toHaveLength(2)
        expect(response.data).toBe(response)
        expect(response.current_page).toBe(2)
        expect(response.last_page).toBe(5)
        expect(response.per_page).toBe(20)
        expect(response.total).toBe(94)
        expect(response.from).toBe(21)
        expect(response.to).toBe(40)
    })

    it('preserva campos extras em respostas paginadas', () => {
        const response = normalizeResponseData({
            data: [{ id: 1 }],
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: 1,
            from: 1,
            to: 1,
            stats: { total_forgotten: 3 },
            meta: { current_page: 1, total: 1 },
        })

        expect(response.stats).toEqual({ total_forgotten: 3 })
        expect(response.meta).toEqual({ current_page: 1, total: 1 })
    })

    it('mantem payloads nao paginados inalterados', () => {
        const payload = { data: { id: 1, name: 'Teste' } }

        expect(normalizeResponseData(payload)).toBe(payload)
    })
})
