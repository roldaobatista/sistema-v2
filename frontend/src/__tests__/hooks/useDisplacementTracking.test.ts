import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook } from '@testing-library/react'

vi.mock('@/lib/api', () => ({
    default: {
        post: vi.fn().mockResolvedValue({ data: {} }),
    },
}))

import { useDisplacementTracking } from '@/hooks/useDisplacementTracking'
import api from '@/lib/api'

describe('useDisplacementTracking', () => {
    let mockGetCurrentPosition: ReturnType<typeof vi.fn>
    const mockPost = vi.mocked(api.post)

    beforeEach(() => {
        vi.useFakeTimers()
        mockPost.mockClear()

        mockGetCurrentPosition = vi.fn()
        Object.defineProperty(navigator, 'geolocation', {
            value: { getCurrentPosition: mockGetCurrentPosition },
            writable: true,
            configurable: true,
        })
    })

    afterEach(() => {
        vi.useRealTimers()
        vi.restoreAllMocks()
    })

    it('should not send location when workOrderId is undefined', () => {
        renderHook(() => useDisplacementTracking(undefined, true))
        expect(mockGetCurrentPosition).not.toHaveBeenCalled()
    })

    it('should not send location when isActive is false', () => {
        renderHook(() => useDisplacementTracking(1, false))
        expect(mockGetCurrentPosition).not.toHaveBeenCalled()
    })

    it('should send location immediately when active with workOrderId', () => {
        renderHook(() => useDisplacementTracking(1, true))
        expect(mockGetCurrentPosition).toHaveBeenCalledTimes(1)
    })

    it('should call geolocation with high accuracy', () => {
        renderHook(() => useDisplacementTracking(1, true))
        const options = mockGetCurrentPosition.mock.calls[0][2]
        expect(options.enableHighAccuracy).toBe(true)
    })

    it('should send location to API when position is obtained', () => {
        renderHook(() => useDisplacementTracking(1, true))

        // Simulate successful position callback
        const successCb = mockGetCurrentPosition.mock.calls[0][0]
        successCb({
            coords: { latitude: -23.5505, longitude: -46.6333 },
        })

        expect(mockPost).toHaveBeenCalledWith('/work-orders/1/displacement/location', {
            latitude: -23.5505,
            longitude: -46.6333,
        })
    })

    it('should set up interval to send location every 10 minutes', () => {
        renderHook(() => useDisplacementTracking(1, true))

        mockGetCurrentPosition.mockClear()

        vi.advanceTimersByTime(10 * 60 * 1000) // 10 minutes
        expect(mockGetCurrentPosition).toHaveBeenCalledTimes(1)

        vi.advanceTimersByTime(10 * 60 * 1000) // another 10 min
        expect(mockGetCurrentPosition).toHaveBeenCalledTimes(2)
    })

    it('should clear interval when isActive becomes false', () => {
        const { rerender } = renderHook(
            ({ id, active }) => useDisplacementTracking(id, active),
            { initialProps: { id: 1, active: true } },
        )

        mockGetCurrentPosition.mockClear()

        rerender({ id: 1, active: false })

        vi.advanceTimersByTime(10 * 60 * 1000)
        expect(mockGetCurrentPosition).not.toHaveBeenCalled()
    })

    it('should clear interval on unmount', () => {
        const { unmount } = renderHook(() => useDisplacementTracking(1, true))

        mockGetCurrentPosition.mockClear()
        unmount()

        vi.advanceTimersByTime(10 * 60 * 1000)
        expect(mockGetCurrentPosition).not.toHaveBeenCalled()
    })

    it('should not crash when geolocation is not available', () => {
        Object.defineProperty(navigator, 'geolocation', {
            value: undefined,
            writable: true,
            configurable: true,
        })

        expect(() => {
            renderHook(() => useDisplacementTracking(1, true))
        }).not.toThrow()
    })

    it('should silently handle geolocation error callback', () => {
        renderHook(() => useDisplacementTracking(1, true))
        const errorCb = mockGetCurrentPosition.mock.calls[0][1]
        expect(() => errorCb(new Error('Permission denied'))).not.toThrow()
    })
})
