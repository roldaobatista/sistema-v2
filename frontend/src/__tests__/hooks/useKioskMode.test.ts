import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useKioskMode } from '@/hooks/useKioskMode'

describe('useKioskMode', () => {
    let mockRequestFullscreen: ReturnType<typeof vi.fn>
    let mockExitFullscreen: ReturnType<typeof vi.fn>

    beforeEach(() => {
        mockRequestFullscreen = vi.fn().mockResolvedValue(undefined)
        mockExitFullscreen = vi.fn().mockResolvedValue(undefined)

        Object.defineProperty(document.documentElement, 'requestFullscreen', {
            value: mockRequestFullscreen,
            writable: true,
            configurable: true,
        })

        Object.defineProperty(document, 'exitFullscreen', {
            value: mockExitFullscreen,
            writable: true,
            configurable: true,
        })

        Object.defineProperty(document, 'fullscreenElement', {
            value: null,
            writable: true,
            configurable: true,
        })
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('should detect fullscreen support', () => {
        const { result } = renderHook(() => useKioskMode())
        expect(result.current.isSupported).toBe(true)
    })

    it('should start with isActive false', () => {
        const { result } = renderHook(() => useKioskMode())
        expect(result.current.isActive).toBe(false)
    })

    it('should enter kiosk mode (request fullscreen)', async () => {
        const { result } = renderHook(() => useKioskMode())

        await act(async () => {
            const success = await result.current.enterKiosk()
            expect(success).toBe(true)
        })

        expect(mockRequestFullscreen).toHaveBeenCalledWith({ navigationUI: 'hide' })
    })

    it('should exit kiosk mode', async () => {
        Object.defineProperty(document, 'fullscreenElement', {
            value: document.documentElement,
            writable: true,
            configurable: true,
        })

        const { result } = renderHook(() => useKioskMode())

        await act(async () => {
            await result.current.exitKiosk()
        })

        expect(mockExitFullscreen).toHaveBeenCalled()
    })

    it('should track fullscreen state via fullscreenchange event', () => {
        const { result } = renderHook(() => useKioskMode())

        expect(result.current.isActive).toBe(false)

        act(() => {
            Object.defineProperty(document, 'fullscreenElement', {
                value: document.documentElement,
                writable: true,
                configurable: true,
            })
            document.dispatchEvent(new Event('fullscreenchange'))
        })

        expect(result.current.isActive).toBe(true)

        act(() => {
            Object.defineProperty(document, 'fullscreenElement', {
                value: null,
                writable: true,
                configurable: true,
            })
            document.dispatchEvent(new Event('fullscreenchange'))
        })

        expect(result.current.isActive).toBe(false)
    })

    it('should toggle between enter and exit kiosk', async () => {
        const { result } = renderHook(() => useKioskMode())

        // Toggle on
        await act(async () => {
            await result.current.toggle()
        })
        expect(mockRequestFullscreen).toHaveBeenCalled()

        // Simulate fullscreen becoming active
        act(() => {
            Object.defineProperty(document, 'fullscreenElement', {
                value: document.documentElement,
                writable: true,
                configurable: true,
            })
            document.dispatchEvent(new Event('fullscreenchange'))
        })

        // Toggle off
        await act(async () => {
            await result.current.toggle()
        })
        expect(mockExitFullscreen).toHaveBeenCalled()
    })

    it('should return false from enterKiosk when not supported', async () => {
        Object.defineProperty(document.documentElement, 'requestFullscreen', {
            value: undefined,
            writable: true,
            configurable: true,
        })

        const { result } = renderHook(() => useKioskMode())

        await act(async () => {
            const success = await result.current.enterKiosk()
            expect(success).toBe(false)
        })
    })
})
