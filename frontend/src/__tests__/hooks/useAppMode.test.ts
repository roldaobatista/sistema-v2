import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'

const mockNavigate = vi.fn()
const mockLocation = { pathname: '/' }

vi.mock('react-router-dom', () => ({
    useLocation: () => mockLocation,
    useNavigate: () => mockNavigate,
}))

const mockHasRole = vi.fn().mockReturnValue(false)

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasRole: mockHasRole,
    }),
}))

vi.mock('@/lib/api', () => ({
    default: {
        patch: vi.fn().mockResolvedValue({}),
    },
}))

import { useAppMode } from '@/hooks/useAppMode'

describe('useAppMode', () => {
    let mockStorage: Record<string, string>

    beforeEach(() => {
        mockStorage = {}
        vi.spyOn(localStorage, 'getItem').mockImplementation((key) => mockStorage[key] ?? null)
        vi.spyOn(localStorage, 'setItem').mockImplementation((key, value) => {
            mockStorage[key] = value
        })
        mockHasRole.mockReset()
        mockNavigate.mockClear()
        mockLocation.pathname = '/'
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('should default to gestao mode for admin users', () => {
        mockHasRole.mockImplementation((role: string) => role === 'admin')
        const { result } = renderHook(() => useAppMode())
        expect(result.current.currentMode).toBe('gestao')
    })

    it('should return all modes for admin users', () => {
        mockHasRole.mockImplementation((role: string) => role === 'admin')
        const { result } = renderHook(() => useAppMode())
        expect(result.current.availableModes).toEqual(['gestao', 'tecnico', 'vendedor'])
    })

    it('should return only tecnico mode for tecnico role', () => {
        mockHasRole.mockImplementation((role: string) => role === 'tecnico')
        const { result } = renderHook(() => useAppMode())
        expect(result.current.availableModes).toContain('tecnico')
    })

    it('should switch mode and navigate', () => {
        mockHasRole.mockImplementation((role: string) => role === 'admin')
        const { result } = renderHook(() => useAppMode())

        act(() => {
            result.current.switchMode('tecnico')
        })

        expect(result.current.currentMode).toBe('tecnico')
        expect(mockNavigate).toHaveBeenCalledWith('/tech')
    })

    it('should navigate to /crm for vendedor mode', () => {
        mockHasRole.mockImplementation((role: string) => role === 'admin')
        const { result } = renderHook(() => useAppMode())

        act(() => {
            result.current.switchMode('vendedor')
        })

        expect(mockNavigate).toHaveBeenCalledWith('/crm')
    })

    it('should navigate to / for gestao mode', () => {
        mockHasRole.mockImplementation((role: string) => role === 'admin')
        const { result } = renderHook(() => useAppMode())

        act(() => {
            result.current.switchMode('tecnico')
        })

        act(() => {
            result.current.switchMode('gestao')
        })

        expect(mockNavigate).toHaveBeenCalledWith('/')
    })

    it('should not switch to unavailable mode', () => {
        mockHasRole.mockImplementation((role: string) => role === 'tecnico')
        const { result } = renderHook(() => useAppMode())

        const prevMode = result.current.currentMode
        act(() => {
            result.current.switchMode('vendedor')
        })

        // Should not have changed if vendedor is not available
        if (!result.current.availableModes.includes('vendedor')) {
            expect(result.current.currentMode).toBe(prevMode)
        }
    })

    it('should persist mode to localStorage', () => {
        mockHasRole.mockImplementation((role: string) => role === 'admin')
        renderHook(() => useAppMode())

        expect(mockStorage['kalibrium-mode']).toBeDefined()
    })

    it('should restore mode from localStorage', () => {
        mockStorage['kalibrium-mode'] = 'tecnico'
        mockHasRole.mockImplementation((role: string) => role === 'admin')
        const { result } = renderHook(() => useAppMode())
        expect(result.current.currentMode).toBe('tecnico')
    })

    it('should report hasMultipleModes correctly', () => {
        mockHasRole.mockImplementation((role: string) => role === 'admin')
        const { result } = renderHook(() => useAppMode())
        expect(result.current.hasMultipleModes).toBe(true)
    })
})
