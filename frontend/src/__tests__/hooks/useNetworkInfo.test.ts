import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useNetworkInfo } from '@/hooks/useNetworkInfo'

describe('useNetworkInfo', () => {
    let onlineListeners: Array<() => void>
    let offlineListeners: Array<() => void>
    let connectionListeners: Array<() => void>
    let mockConnection: any

    beforeEach(() => {
        onlineListeners = []
        offlineListeners = []
        connectionListeners = []

        mockConnection = {
            effectiveType: '4g',
            downlink: 10,
            rtt: 50,
            saveData: false,
            addEventListener: (type: string, fn: () => void) => {
                if (type === 'change') connectionListeners.push(fn)
            },
            removeEventListener: (type: string, fn: () => void) => {
                connectionListeners = connectionListeners.filter((l) => l !== fn)
            },
        }

        Object.defineProperty(navigator, 'connection', {
            value: mockConnection,
            writable: true,
            configurable: true,
        })

        Object.defineProperty(navigator, 'onLine', {
            value: true,
            writable: true,
            configurable: true,
        })

        const originalAddEventListener = window.addEventListener.bind(window)
        const originalRemoveEventListener = window.removeEventListener.bind(window)

        vi.spyOn(window, 'addEventListener').mockImplementation((type: string, fn: any) => {
            if (type === 'online') onlineListeners.push(fn)
            else if (type === 'offline') offlineListeners.push(fn)
            else originalAddEventListener(type, fn)
        })

        vi.spyOn(window, 'removeEventListener').mockImplementation((type: string, fn: any) => {
            if (type === 'online') onlineListeners = onlineListeners.filter((l) => l !== fn)
            else if (type === 'offline') offlineListeners = offlineListeners.filter((l) => l !== fn)
            else originalRemoveEventListener(type, fn)
        })
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('should return initial online state', () => {
        const { result } = renderHook(() => useNetworkInfo())
        expect(result.current.isOnline).toBe(true)
    })

    it('should return connection effective type', () => {
        const { result } = renderHook(() => useNetworkInfo())
        expect(result.current.effectiveType).toBe('4g')
    })

    it('should return downlink and rtt info', () => {
        const { result } = renderHook(() => useNetworkInfo())
        expect(result.current.downlink).toBe(10)
        expect(result.current.rtt).toBe(50)
    })

    it('should update when going offline', () => {
        const { result } = renderHook(() => useNetworkInfo())

        act(() => {
            Object.defineProperty(navigator, 'onLine', { value: false, writable: true, configurable: true })
            offlineListeners.forEach((fn) => fn())
        })

        expect(result.current.isOnline).toBe(false)
    })

    it('should update when going online', () => {
        Object.defineProperty(navigator, 'onLine', { value: false, writable: true, configurable: true })
        const { result } = renderHook(() => useNetworkInfo())

        act(() => {
            Object.defineProperty(navigator, 'onLine', { value: true, writable: true, configurable: true })
            onlineListeners.forEach((fn) => fn())
        })

        expect(result.current.isOnline).toBe(true)
    })

    it('should return unknown effectiveType when connection API is absent', () => {
        Object.defineProperty(navigator, 'connection', {
            value: undefined,
            writable: true,
            configurable: true,
        })
        ;(navigator as any).mozConnection = undefined
        ;(navigator as any).webkitConnection = undefined

        const { result } = renderHook(() => useNetworkInfo())
        expect(result.current.effectiveType).toBe('unknown')
        expect(result.current.supported).toBe(false)
    })

    it('should report saveData flag', () => {
        mockConnection.saveData = true
        const { result } = renderHook(() => useNetworkInfo())
        expect(result.current.saveData).toBe(true)
    })

    it('should clean up event listeners on unmount', () => {
        const { unmount } = renderHook(() => useNetworkInfo())

        const onlineCount = onlineListeners.length
        const offlineCount = offlineListeners.length

        unmount()

        expect(onlineListeners.length).toBeLessThan(onlineCount)
        expect(offlineListeners.length).toBeLessThan(offlineCount)
    })
})
