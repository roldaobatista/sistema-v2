import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useLowDataMode } from '@/hooks/useLowDataMode'

describe('useLowDataMode', () => {
    let mockStorage: Record<string, string>

    beforeEach(() => {
        mockStorage = {}
        vi.spyOn(localStorage, 'getItem').mockImplementation((key) => mockStorage[key] ?? null)
        vi.spyOn(localStorage, 'setItem').mockImplementation((key, value) => {
            mockStorage[key] = value
        })

        // Remove connection API by default
        Object.defineProperty(navigator, 'connection', {
            value: undefined,
            writable: true,
            configurable: true,
        })
        ;(navigator as any).mozConnection = undefined
        ;(navigator as any).webkitConnection = undefined
    })

    afterEach(() => {
        vi.restoreAllMocks()
        document.documentElement.style.removeProperty('--animation-duration')
        document.documentElement.classList.remove('reduce-motion')
    })

    it('should start disabled by default', () => {
        const { result } = renderHook(() => useLowDataMode())
        expect(result.current.isEnabled).toBe(false)
    })

    it('should start enabled if stored in localStorage', () => {
        mockStorage['low-data-mode'] = 'true'
        const { result } = renderHook(() => useLowDataMode())
        expect(result.current.isEnabled).toBe(true)
    })

    it('should set low maxImageSize when enabled', () => {
        mockStorage['low-data-mode'] = 'true'
        const { result } = renderHook(() => useLowDataMode())
        expect(result.current.maxImageSize).toBe(100)
    })

    it('should set high maxImageSize when disabled', () => {
        const { result } = renderHook(() => useLowDataMode())
        expect(result.current.maxImageSize).toBe(500)
    })

    it('should toggle enabled state', () => {
        const { result } = renderHook(() => useLowDataMode())

        act(() => {
            result.current.toggle()
        })

        expect(result.current.isEnabled).toBe(true)
        expect(result.current.lazyLoadImages).toBe(true)
        expect(result.current.disableAnimations).toBe(true)
        expect(result.current.compressUploads).toBe(true)
        expect(mockStorage['low-data-mode']).toBe('true')
    })

    it('should toggle back to disabled', () => {
        mockStorage['low-data-mode'] = 'true'
        const { result } = renderHook(() => useLowDataMode())

        act(() => {
            result.current.toggle()
        })

        expect(result.current.isEnabled).toBe(false)
        expect(result.current.lazyLoadImages).toBe(false)
        expect(result.current.disableAnimations).toBe(false)
    })

    it('should apply reduce-motion class when animations are disabled', () => {
        mockStorage['low-data-mode'] = 'true'
        renderHook(() => useLowDataMode())
        expect(document.documentElement.classList.contains('reduce-motion')).toBe(true)
    })

    it('should remove reduce-motion class when animations are enabled', () => {
        document.documentElement.classList.add('reduce-motion')
        renderHook(() => useLowDataMode())
        expect(document.documentElement.classList.contains('reduce-motion')).toBe(false)
    })

    it('should auto-enable on slow connection', () => {
        const connectionListeners: Array<() => void> = []
        Object.defineProperty(navigator, 'connection', {
            value: {
                effectiveType: '2g',
                downlink: 0.5,
                addEventListener: (type: string, fn: () => void) => {
                    connectionListeners.push(fn)
                },
                removeEventListener: vi.fn(),
            },
            writable: true,
            configurable: true,
        })

        const { result } = renderHook(() => useLowDataMode())
        expect(result.current.isEnabled).toBe(true)
        expect(result.current.isSlowConnection).toBe(true)
    })

    it('should expose processImage function', () => {
        const { result } = renderHook(() => useLowDataMode())
        expect(typeof result.current.processImage).toBe('function')
    })
})
