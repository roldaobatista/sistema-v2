import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

import {
    useFuelingLogs,
    useFuelingLog,
    useCreateFuelingLog,
    useUpdateFuelingLog,
    useApproveFuelingLog,
    useResubmitFuelingLog,
    useDeleteFuelingLog,
    FUEL_TYPES,
} from '@/hooks/useFuelingLogs'
import api from '@/lib/api'
import { toast } from 'sonner'

const mockGet = vi.mocked(api.get)
const mockPost = vi.mocked(api.post)
const mockPut = vi.mocked(api.put)
const mockDelete = vi.mocked(api.delete)

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })
    return ({ children }: { children: React.ReactNode }) =>
        React.createElement(QueryClientProvider, { client: queryClient }, children)
}

describe('useFuelingLogs', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should fetch fueling logs without filters', async () => {
        mockGet.mockResolvedValue({
            data: { data: [{ id: 1, vehicle_plate: 'ABC-1234' }] },
        })

        const { result } = renderHook(() => useFuelingLogs(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(mockGet).toHaveBeenCalledWith(expect.stringContaining('/fueling-logs'))
    })

    it('should pass filters as query params', async () => {
        mockGet.mockResolvedValue({ data: { data: [] } })

        renderHook(() => useFuelingLogs({ status: 'pending', user_id: 5 }), {
            wrapper: createWrapper(),
        })

        await waitFor(() => {
            expect(mockGet).toHaveBeenCalledWith(
                expect.stringMatching(/fueling-logs\?.*status=pending/),
            )
        })
    })

    it('should not include undefined filters in params', async () => {
        mockGet.mockResolvedValue({ data: { data: [] } })

        renderHook(() => useFuelingLogs({ status: 'approved', search: undefined }), {
            wrapper: createWrapper(),
        })

        await waitFor(() => {
            const url = mockGet.mock.calls[0][0] as string
            expect(url).not.toContain('search')
        })
    })
})

describe('useFuelingLog', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should fetch a single fueling log', async () => {
        mockGet.mockResolvedValue({
            data: { id: 1, vehicle_plate: 'XYZ-9876', liters: 50 },
        })

        const { result } = renderHook(() => useFuelingLog(1), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(mockGet).toHaveBeenCalledWith('/fueling-logs/1')
    })

    it('should not fetch when id is null', () => {
        const { result } = renderHook(() => useFuelingLog(null), { wrapper: createWrapper() })
        expect(result.current.isLoading).toBe(false)
        expect(mockGet).not.toHaveBeenCalled()
    })
})

describe('useCreateFuelingLog', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should create a fueling log', async () => {
        mockPost.mockResolvedValue({ data: { id: 1 } })

        const { result } = renderHook(() => useCreateFuelingLog(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate({
                vehicle_plate: 'ABC-1234',
                odometer_km: 50000,
                fuel_type: 'diesel',
                liters: 60,
                price_per_liter: 5.89,
                total_amount: 353.4,
                date: '2026-03-15',
            })
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPost).toHaveBeenCalledWith('/fueling-logs', expect.objectContaining({
            vehicle_plate: 'ABC-1234',
            liters: 60,
        }))
        expect(toast.success).toHaveBeenCalledWith('Abastecimento registrado')
    })
})

describe('useUpdateFuelingLog', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should update a fueling log', async () => {
        mockPut.mockResolvedValue({ data: { id: 1 } })

        const { result } = renderHook(() => useUpdateFuelingLog(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate({
                id: 1,
                vehicle_plate: 'ABC-1234',
                odometer_km: 51000,
                fuel_type: 'diesel',
                liters: 65,
                price_per_liter: 5.99,
                total_amount: 389.35,
                date: '2026-03-15',
            })
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPut).toHaveBeenCalledWith('/fueling-logs/1', expect.objectContaining({
            liters: 65,
        }))
        expect(toast.success).toHaveBeenCalledWith('Abastecimento atualizado')
    })
})

describe('useApproveFuelingLog', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should approve a fueling log', async () => {
        mockPost.mockResolvedValue({ data: {} })

        const { result } = renderHook(() => useApproveFuelingLog(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate({ id: 1, action: 'approve' })
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPost).toHaveBeenCalledWith('/fueling-logs/1/approve', {
            action: 'approve',
            rejection_reason: undefined,
        })
        expect(toast.success).toHaveBeenCalledWith('Abastecimento aprovado')
    })

    it('should reject a fueling log with reason', async () => {
        mockPost.mockResolvedValue({ data: {} })

        const { result } = renderHook(() => useApproveFuelingLog(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate({ id: 2, action: 'reject', rejection_reason: 'Invalid receipt' })
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(toast.success).toHaveBeenCalledWith('Abastecimento rejeitado')
    })
})

describe('useResubmitFuelingLog', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should resubmit a fueling log', async () => {
        mockPost.mockResolvedValue({ data: {} })

        const { result } = renderHook(() => useResubmitFuelingLog(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate(1)
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPost).toHaveBeenCalledWith('/fueling-logs/1/resubmit')
        expect(toast.success).toHaveBeenCalledWith('Abastecimento resubmetido como pendente')
    })
})

describe('useDeleteFuelingLog', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should delete a fueling log', async () => {
        mockDelete.mockResolvedValue({ data: {} })

        const { result } = renderHook(() => useDeleteFuelingLog(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate(1)
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockDelete).toHaveBeenCalledWith('/fueling-logs/1')
        expect(toast.success).toHaveBeenCalledWith('Registro excluído')
    })
})

describe('FUEL_TYPES', () => {
    it('should export fuel types constant', () => {
        expect(FUEL_TYPES).toHaveLength(4)
        expect(FUEL_TYPES.map((ft) => ft.value)).toEqual([
            'diesel',
            'diesel_s10',
            'gasolina',
            'etanol',
        ])
    })
})
