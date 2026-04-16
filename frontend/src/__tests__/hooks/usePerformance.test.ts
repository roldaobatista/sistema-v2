import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'
import { toast } from 'sonner'
import type { ContinuousFeedback, PerformanceReview } from '@/types/hr'

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
    },
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

import { usePerformance, useReview } from '@/hooks/usePerformance'
import api from '@/lib/api'

const mockGet = vi.mocked(api.get)
const mockPost = vi.mocked(api.post)
const mockPut = vi.mocked(api.put)

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })
    return ({ children }: { children: React.ReactNode }) =>
        React.createElement(QueryClientProvider, { client: queryClient }, children)
}

describe('usePerformance', () => {
    beforeEach(() => {
        mockGet.mockReset()
        mockPost.mockReset()
        mockPut.mockReset()
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('should fetch reviews on mount', async () => {
        mockGet.mockResolvedValueOnce({ data: { data: [{ id: 1, title: 'Review 1' }] } }) // reviews
        mockGet.mockResolvedValueOnce({ data: { data: [{ id: 1, content: 'Good', type: 'praise', created_at: '2026-03-20T00:00:00Z' }] } }) // feedback

        const { result } = renderHook(() => usePerformance(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.loadingReviews).toBe(false)
        })

        expect(mockGet).toHaveBeenCalledWith('/hr/performance-reviews')
        expect(result.current.reviews).toEqual([{ id: 1, title: 'Review 1' }])
    })

    it('should fetch feedback list on mount', async () => {
        mockGet.mockResolvedValueOnce({ data: { data: [] } })
        mockGet.mockResolvedValueOnce({ data: { data: [{ id: 1, content: 'Nice work', type: 'suggestion', created_at: '2026-03-20T00:00:00Z' }] } })

        const { result } = renderHook(() => usePerformance(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.loadingFeedback).toBe(false)
        })

        expect(mockGet).toHaveBeenCalledWith('/hr/continuous-feedback')
        expect(result.current.feedbackList).toEqual([
            {
                id: 1,
                content: 'Nice work',
                type: 'suggestion',
                created_at: '2026-03-20T00:00:00Z',
            },
        ])
    })

    it('should expose createReview mutation', async () => {
        mockGet.mockResolvedValue({ data: { data: [] } })
        mockPost.mockResolvedValue({ data: { id: 1 } })

        const { result } = renderHook(() => usePerformance(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.loadingReviews).toBe(false)
        })

        const draftReview: Partial<PerformanceReview> = {
            title: 'Avaliação Q1',
            cycle: '2026-Q1',
            type: '180',
            user_id: 1,
            reviewer_id: 2,
        }

        await act(async () => {
            result.current.createReview.mutate(draftReview)
        })

        await waitFor(() => {
            expect(mockPost).toHaveBeenCalledWith('/hr/performance-reviews', expect.objectContaining({
                title: 'Avaliação Q1',
                cycle: '2026-Q1',
                type: '180',
                user_id: 1,
                reviewer_id: 2,
                year: 2026,
            }))
        })
    })

    it('should expose sendFeedback mutation', async () => {
        mockGet.mockResolvedValue({ data: { data: [] } })
        mockPost.mockResolvedValue({ data: { id: 1 } })

        const { result } = renderHook(() => usePerformance(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.loadingFeedback).toBe(false)
        })

        const feedbackPayload: Partial<ContinuousFeedback> = {
            to_user_id: 3,
            type: 'guidance',
            visibility: 'public',
            message: 'Great job',
        }

        await act(async () => {
            result.current.sendFeedback.mutate(feedbackPayload)
        })

        await waitFor(() => {
            expect(mockPost).toHaveBeenCalledWith('/hr/continuous-feedback', {
                to_user_id: 3,
                type: 'suggestion',
                visibility: 'public',
                content: 'Great job',
            })
        })
    })

    it('should use api error payload when createReview fails', async () => {
        mockGet.mockResolvedValue({ data: { data: [] } })
        mockPost.mockRejectedValue({
            isAxiosError: true,
            response: {
                data: {
                    error: 'Colaborador invalido',
                },
            },
        })

        const { result } = renderHook(() => usePerformance(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.loadingReviews).toBe(false)
        })

        await act(async () => {
            result.current.createReview.mutate({ user_id: 999 })
        })

        await waitFor(() => {
            expect(toast.error).toHaveBeenCalledWith('Colaborador invalido')
        })
    })
})

describe('useReview', () => {
    beforeEach(() => {
        mockGet.mockReset()
    })

    it('should fetch a single review by id', async () => {
        mockGet.mockResolvedValue({ data: { data: { id: 5, title: 'Annual Review' } } })

        const { result } = renderHook(() => useReview(5), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(mockGet).toHaveBeenCalledWith('/hr/performance-reviews/5')
        expect(result.current.data).toEqual({ id: 5, title: 'Annual Review' })
    })

    it('should not fetch when id is 0', () => {
        const { result } = renderHook(() => useReview(0), { wrapper: createWrapper() })
        expect(result.current.isLoading).toBe(false)
    })
})
