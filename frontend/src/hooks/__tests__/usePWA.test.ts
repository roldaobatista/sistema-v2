import { renderHook, act } from '@testing-library/react'
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { usePWA } from '../usePWA'

describe('usePWA', () => {
    let originalMatchMedia: (query: string) => MediaQueryList;

    beforeEach(() => {
        originalMatchMedia = window.matchMedia;
        window.matchMedia = vi.fn().mockImplementation((query) => ({
            matches: false,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        }))

        Object.defineProperty(navigator, 'onLine', {
            writable: true,
            value: true
        })
    })

    afterEach(() => {
        window.matchMedia = originalMatchMedia
        vi.restoreAllMocks()
    })

    it('should initialize with network status', () => {
        const { result } = renderHook(() => usePWA())
        expect(result.current.isOnline).toBe(true)
    })

    it('should handle offline and online events', () => {
        const { result } = renderHook(() => usePWA())

        act(() => {
            window.dispatchEvent(new Event('offline'))
        })
        expect(result.current.isOnline).toBe(false)

        act(() => {
            window.dispatchEvent(new Event('online'))
        })
        expect(result.current.isOnline).toBe(true)
    })

    it('should detect when app is installable via beforeinstallprompt', () => {
        const { result } = renderHook(() => usePWA())

        expect(result.current.isInstallable).toBe(false)

        act(() => {
            const event = new Event('beforeinstallprompt') as any;
            event.prompt = vi.fn()
            event.userChoice = Promise.resolve({ outcome: 'accepted' })
            event.preventDefault = vi.fn()
            window.dispatchEvent(event)
        })

        expect(result.current.isInstallable).toBe(true)
    })

    it('should handle appinstalled event', () => {
        const { result } = renderHook(() => usePWA())

        act(() => {
            window.dispatchEvent(new Event('appinstalled'))
        })

        expect(result.current.isInstalled).toBe(true)
        expect(result.current.isInstallable).toBe(false)
    })
})
