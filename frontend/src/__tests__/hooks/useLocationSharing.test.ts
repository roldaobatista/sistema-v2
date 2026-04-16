import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'

vi.mock('@/lib/api', () => ({
    default: {
        post: vi.fn().mockResolvedValue({ data: {} }),
    },
}))

import { useLocationSharing } from '@/hooks/useLocationSharing'
import api from '@/lib/api'

describe('useLocationSharing', () => {
    let mockGetCurrentPosition: ReturnType<typeof vi.fn>
    let mockStorage: Record<string, string>
    const mockPost = vi.mocked(api.post)

    beforeEach(() => {
        vi.useFakeTimers()
        mockPost.mockClear()

        mockStorage = {}
        vi.spyOn(localStorage, 'getItem').mockImplementation((key) => mockStorage[key] ?? null)
        vi.spyOn(localStorage, 'setItem').mockImplementation((key, value) => {
            mockStorage[key] = value
        })
        vi.spyOn(localStorage, 'removeItem').mockImplementation((key) => {
            delete mockStorage[key]
        })

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

    it('should start with sharing disabled', () => {
        const { result } = renderHook(() => useLocationSharing())
        expect(result.current.isSharing).toBe(false)
        expect(result.current.lastPosition).toBeNull()
    })

    it('should start sharing and get current position', () => {
        const { result } = renderHook(() => useLocationSharing())

        act(() => {
            result.current.startSharing()
        })

        expect(result.current.isSharing).toBe(true)
        expect(mockGetCurrentPosition).toHaveBeenCalled()
    })

    it('should send location to API on position success', () => {
        const { result } = renderHook(() => useLocationSharing())

        act(() => {
            result.current.startSharing()
        })

        const successCb = mockGetCurrentPosition.mock.calls[0][0]
        act(() => {
            successCb({ coords: { latitude: -23.55, longitude: -46.63 } })
        })

        expect(mockPost).toHaveBeenCalledWith('/user/location', {
            latitude: -23.55,
            longitude: -46.63,
        })
    })

    it('should stop sharing and clear interval', () => {
        const { result } = renderHook(() => useLocationSharing())

        act(() => {
            result.current.startSharing()
        })

        mockGetCurrentPosition.mockClear()

        act(() => {
            result.current.stopSharing()
        })

        expect(result.current.isSharing).toBe(false)

        vi.advanceTimersByTime(5 * 60 * 1000)
        expect(mockGetCurrentPosition).not.toHaveBeenCalled()
    })

    it('should persist sharing state to localStorage', () => {
        const { result } = renderHook(() => useLocationSharing())

        act(() => {
            result.current.startSharing()
        })

        expect(mockStorage['location-sharing-active']).toBe('true')

        act(() => {
            result.current.stopSharing()
        })

        expect(mockStorage['location-sharing-active']).toBeUndefined()
    })

    it('should set error when geolocation is not supported', () => {
        Object.defineProperty(navigator, 'geolocation', {
            value: undefined,
            writable: true,
            configurable: true,
        })

        const { result } = renderHook(() => useLocationSharing())

        act(() => {
            result.current.startSharing()
        })

        expect(result.current.error).toBe('GPS não suportado')
    })

    it('should resume sharing if it was active when hook mounts', () => {
        mockStorage['location-sharing-active'] = 'true'

        renderHook(() => useLocationSharing())

        expect(mockGetCurrentPosition).toHaveBeenCalled()
    })

    it('should toggle sharing state', () => {
        const { result } = renderHook(() => useLocationSharing())

        act(() => {
            result.current.toggle()
        })

        expect(result.current.isSharing).toBe(true)

        act(() => {
            result.current.toggle()
        })

        expect(result.current.isSharing).toBe(false)
    })

    it('should send location periodically (every 5 minutes)', () => {
        const { result } = renderHook(() => useLocationSharing())

        act(() => {
            result.current.startSharing()
        })

        mockGetCurrentPosition.mockClear()

        act(() => {
            vi.advanceTimersByTime(5 * 60 * 1000)
        })

        expect(mockGetCurrentPosition).toHaveBeenCalledTimes(1)
    })
})
