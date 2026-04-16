import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
    },
}))

import { useExpenseCategories } from '@/hooks/useExpenseCategories'
import api from '@/lib/api'

const mockGet = vi.mocked(api.get)

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })
    return ({ children }: { children: React.ReactNode }) =>
        React.createElement(QueryClientProvider, { client: queryClient }, children)
}

describe('useExpenseCategories', () => {
    beforeEach(() => {
        mockGet.mockReset()
    })

    it('should fetch expense categories', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: [
                    { id: 1, name: 'Transporte', parent_id: null },
                    { id: 2, name: 'Alimentacao', parent_id: null },
                ],
            },
        })

        const { result } = renderHook(() => useExpenseCategories(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(mockGet).toHaveBeenCalledWith('/expense-categories')
        expect(result.current.categories).toHaveLength(2)
    })

    it('should return empty array when no data', async () => {
        mockGet.mockResolvedValue({ data: null })

        const { result } = renderHook(() => useExpenseCategories(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(result.current.categories).toEqual([])
    })

    it('should handle nested data structure (res.data.data)', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: [{ id: 1, name: 'Combustivel' }],
            },
        })

        const { result } = renderHook(() => useExpenseCategories(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(result.current.categories).toEqual([{ id: 1, name: 'Combustivel' }])
    })

    it('should handle flat data structure (res.data is array)', async () => {
        mockGet.mockResolvedValue({
            data: [{ id: 1, name: 'Material' }],
        })

        const { result } = renderHook(() => useExpenseCategories(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(result.current.categories).toEqual([{ id: 1, name: 'Material' }])
    })

    it('should expose error when fetch fails', async () => {
        mockGet.mockRejectedValue(new Error('Server error'))

        const { result } = renderHook(() => useExpenseCategories(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(result.current.error).toBeTruthy()
    })

    it('should default categories to empty array during loading', () => {
        mockGet.mockImplementation(() => new Promise(() => {})) // never resolves
        const { result } = renderHook(() => useExpenseCategories(), { wrapper: createWrapper() })
        expect(result.current.categories).toEqual([])
    })
})
