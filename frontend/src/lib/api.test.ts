import { describe, expect, it } from 'vitest'
import { normalizeResponseData, unwrapData } from './api'
import { safeArray } from './safe-array'

describe('api helpers', () => {
    it('unwrapData suporta payload direto e envelopado', () => {
        expect(unwrapData<number[]>({ data: [1, 2] })).toEqual([1, 2])
        expect(unwrapData<number[]>({ data: { data: [3, 4] } })).toEqual([3, 4])
    })

    it('normalizeResponseData preserva metadados de paginacao no array retornado', () => {
        const normalized = normalizeResponseData({
            data: [{ id: 1 }, { id: 2 }],
            current_page: 2,
            last_page: 4,
            total: 20,
        }) as unknown as Array<{ id: number }> & { current_page?: number; last_page?: number; total?: number }

        expect(Array.isArray(normalized)).toBe(true)
        expect(normalized).toHaveLength(2)
        expect(normalized.current_page).toBe(2)
        expect(normalized.last_page).toBe(4)
        expect(normalized.total).toBe(20)
    })

    it('safeArray funciona com unwrapData em payload envelopado e paginado', () => {
        expect(safeArray<{ id: number }>(unwrapData({
            data: {
                data: [{ id: 10 }, { id: 20 }],
                current_page: 1,
                total: 2,
            },
        }))).toEqual([{ id: 10 }, { id: 20 }])
    })
})
