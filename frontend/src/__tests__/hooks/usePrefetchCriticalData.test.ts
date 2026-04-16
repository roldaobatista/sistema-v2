import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook } from '@testing-library/react'

const mockCurrentMode = { value: 'gestao' as string }
const mockToken = { value: 'test-token-abcdefgh' as string | null }
const mockIsOnline = { value: true }
const mockSwRegistration = { value: {} as any }
const mockPostMessage = vi.fn()
const mockIsApiHealthy = vi.fn().mockReturnValue(true)

vi.mock('@/hooks/useAppMode', () => ({
    useAppMode: () => ({ currentMode: mockCurrentMode.value }),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({ token: mockToken.value }),
}))

vi.mock('@/hooks/usePWA', () => ({
    usePWA: () => ({
        isOnline: mockIsOnline.value,
        swRegistration: mockSwRegistration.value,
    }),
}))

vi.mock('@/lib/api-health', () => ({
    isApiHealthy: () => mockIsApiHealthy(),
}))

import { usePrefetchCriticalData } from '@/hooks/usePrefetchCriticalData'

describe('usePrefetchCriticalData', () => {
    beforeEach(() => {
        mockCurrentMode.value = 'gestao'
        mockToken.value = 'test-token-abcdefgh'
        mockIsOnline.value = true
        mockSwRegistration.value = {}
        mockIsApiHealthy.mockReturnValue(true)
        mockPostMessage.mockClear()

        Object.defineProperty(navigator, 'serviceWorker', {
            value: {
                controller: { postMessage: mockPostMessage },
            },
            writable: true,
            configurable: true,
        })
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('should send CACHE_API_DATA message to service worker on mount', () => {
        renderHook(() => usePrefetchCriticalData())
        expect(mockPostMessage).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'CACHE_API_DATA',
                urls: expect.any(Array),
                headers: expect.objectContaining({
                    Authorization: expect.stringContaining('Bearer'),
                }),
            }),
        )
    })

    it('should not prefetch when offline', () => {
        mockIsOnline.value = false
        renderHook(() => usePrefetchCriticalData())
        expect(mockPostMessage).not.toHaveBeenCalled()
    })

    it('should not prefetch when no token', () => {
        mockToken.value = null
        renderHook(() => usePrefetchCriticalData())
        expect(mockPostMessage).not.toHaveBeenCalled()
    })

    it('should not prefetch when no service worker registration', () => {
        mockSwRegistration.value = null
        renderHook(() => usePrefetchCriticalData())
        expect(mockPostMessage).not.toHaveBeenCalled()
    })

    it('should not prefetch when API is not healthy', () => {
        mockIsApiHealthy.mockReturnValue(false)
        renderHook(() => usePrefetchCriticalData())
        expect(mockPostMessage).not.toHaveBeenCalled()
    })

    it('should include mode-specific URLs for tecnico mode', () => {
        mockCurrentMode.value = 'tecnico'
        renderHook(() => usePrefetchCriticalData())

        const message = mockPostMessage.mock.calls[0][0]
        const urls = message.urls as string[]
        expect(urls.some((u: string) => u.includes('work-orders'))).toBe(true)
        expect(urls.some((u: string) => u.includes('equipments'))).toBe(true)
    })

    it('should not re-prefetch with same cache key', () => {
        const { rerender } = renderHook(() => usePrefetchCriticalData())
        expect(mockPostMessage).toHaveBeenCalledTimes(1)

        rerender()
        // Should not call again with same mode + token
        expect(mockPostMessage).toHaveBeenCalledTimes(1)
    })
})
