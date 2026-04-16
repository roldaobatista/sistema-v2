import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'

// Mock the ui-store
const mockStoreState = {
    theme: 'system' as 'light' | 'dark' | 'system',
    setTheme: vi.fn(),
}

const subscribers: Array<() => void> = []

vi.mock('@/stores/ui-store', () => ({
    useUIStore: Object.assign(
        vi.fn(),
        {
            getState: () => mockStoreState,
            subscribe: (fn: () => void) => {
                subscribers.push(fn)
                return () => {
                    const idx = subscribers.indexOf(fn)
                    if (idx >= 0) subscribers.splice(idx, 1)
                }
            },
        },
    ),
}))

import { useDarkMode } from '@/hooks/useDarkMode'

describe('useDarkMode', () => {
    let matchMediaListeners: Array<(e: { matches: boolean }) => void>
    let mockMatchMedia: ReturnType<typeof vi.fn>

    beforeEach(() => {
        matchMediaListeners = []
        mockMatchMedia = vi.fn().mockImplementation(() => ({
            matches: false,
            addEventListener: (type: string, fn: any) => {
                matchMediaListeners.push(fn)
            },
            removeEventListener: (type: string, fn: any) => {
                const idx = matchMediaListeners.indexOf(fn)
                if (idx >= 0) matchMediaListeners.splice(idx, 1)
            },
        }))
        window.matchMedia = mockMatchMedia

        // Reset store state
        mockStoreState.theme = 'system'
        mockStoreState.setTheme.mockClear()

        // Set up DOM elements for applyTheme
        document.documentElement.classList.remove('dark', 'light')
    })

    afterEach(() => {
        vi.restoreAllMocks()
        subscribers.length = 0
    })

    it('should default to system theme', () => {
        const { result } = renderHook(() => useDarkMode())
        expect(result.current.theme).toBe('system')
    })

    it('should respect stored dark theme from ui-store', () => {
        mockStoreState.theme = 'dark'
        const { result } = renderHook(() => useDarkMode())
        expect(result.current.theme).toBe('dark')
        expect(result.current.isDark).toBe(true)
    })

    it('should respect stored light theme from ui-store', () => {
        mockStoreState.theme = 'light'
        const { result } = renderHook(() => useDarkMode())
        expect(result.current.theme).toBe('light')
        expect(result.current.isDark).toBe(false)
    })

    it('should toggle from light to dark', () => {
        mockStoreState.theme = 'light'
        const { result } = renderHook(() => useDarkMode())

        act(() => {
            result.current.toggle()
        })

        expect(result.current.isDark).toBe(true)
        expect(result.current.theme).toBe('dark')
    })

    it('should toggle from dark to light', () => {
        mockStoreState.theme = 'dark'
        const { result } = renderHook(() => useDarkMode())

        act(() => {
            result.current.toggle()
        })

        expect(result.current.isDark).toBe(false)
        expect(result.current.theme).toBe('light')
    })

    it('should apply dark class to documentElement', () => {
        mockStoreState.theme = 'dark'
        renderHook(() => useDarkMode())
        expect(document.documentElement.classList.contains('dark')).toBe(true)
    })

    it('should apply light class to documentElement', () => {
        mockStoreState.theme = 'light'
        renderHook(() => useDarkMode())
        expect(document.documentElement.classList.contains('light')).toBe(true)
    })

    it('should set theme explicitly', () => {
        const { result } = renderHook(() => useDarkMode())

        act(() => {
            result.current.setTheme('dark')
        })

        expect(result.current.theme).toBe('dark')
        expect(result.current.isDark).toBe(true)
        expect(mockStoreState.setTheme).toHaveBeenCalledWith('dark')
    })

    it('should respond to system preference change when in system mode', () => {
        mockStoreState.theme = 'system'
        const { result } = renderHook(() => useDarkMode())

        // Initially not dark (matchMedia returns false)
        expect(result.current.isDark).toBe(false)

        // Simulate OS switching to dark mode
        act(() => {
            matchMediaListeners.forEach((fn) => fn({ matches: true }))
        })

        expect(result.current.isDark).toBe(true)
    })

    it('should not respond to system preference change when not in system mode', () => {
        mockStoreState.theme = 'light'
        const { result } = renderHook(() => useDarkMode())

        act(() => {
            matchMediaListeners.forEach((fn) => fn({ matches: true }))
        })

        // Should stay light even though system says dark
        expect(result.current.isDark).toBe(false)
    })
})
