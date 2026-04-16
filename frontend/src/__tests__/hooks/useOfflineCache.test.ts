import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, waitFor, act } from '@testing-library/react'
import { useOfflineCache } from '@/hooks/useOfflineCache'

describe('useOfflineCache', () => {
    let mockStorage: Record<string, string>

    beforeEach(() => {
        mockStorage = {}
        vi.spyOn(localStorage, 'getItem').mockImplementation((key) => mockStorage[key] ?? null)
        vi.spyOn(localStorage, 'setItem').mockImplementation((key, value) => {
            mockStorage[key] = value
        })
        vi.spyOn(localStorage, 'removeItem').mockImplementation((key) => {
            delete mockStorage[key]
        })
        vi.spyOn(Date, 'now').mockReturnValue(1000000)
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('should return null data initially when cache is empty', () => {
        const fetchFn = vi.fn().mockResolvedValue({ items: [] })
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'test' }))
        // Initially null from cache, loading from fetch
        expect(result.current.data === null || result.current.loading).toBe(true)
    })

    it('should return cached data if cache is valid', () => {
        mockStorage['cache:test'] = JSON.stringify({ data: { items: [1, 2] }, timestamp: 999000 })
        const fetchFn = vi.fn().mockResolvedValue({ items: [1, 2, 3] })
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'test' }))
        expect(result.current.data).toEqual({ items: [1, 2] })
    })

    it('should return null when cached data is expired', () => {
        // Default maxAge = 30 min = 1800000ms. Date.now() = 1000000, so timestamp must be < -800000 to be expired.
        // Use a Date.now() large enough to make timestamp=0 expired.
        vi.spyOn(Date, 'now').mockReturnValue(2000000)
        mockStorage['cache:test'] = JSON.stringify({ data: { items: [1] }, timestamp: 0 })
        const fetchFn = vi.fn().mockResolvedValue({ items: [] })
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'test' }))
        expect(result.current.data).toBeNull()
    })

    it('should call fetchFn on mount and update data', async () => {
        const fetchFn = vi.fn().mockResolvedValue({ result: 'fresh' })
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'fetch-test' }))

        await waitFor(() => {
            expect(result.current.data).toEqual({ result: 'fresh' })
        })

        expect(fetchFn).toHaveBeenCalledTimes(1)
    })

    it('should save fetched data to localStorage', async () => {
        const fetchFn = vi.fn().mockResolvedValue({ saved: true })
        renderHook(() => useOfflineCache(fetchFn, { key: 'save-test' }))

        await waitFor(() => {
            expect(mockStorage['cache:save-test']).toBeDefined()
        })

        const parsed = JSON.parse(mockStorage['cache:save-test'])
        expect(parsed.data).toEqual({ saved: true })
        expect(parsed.timestamp).toBe(1000000)
    })

    it('should set loading to false after fetch completes', async () => {
        const fetchFn = vi.fn().mockResolvedValue('done')
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'loading-test' }))

        await waitFor(() => {
            expect(result.current.loading).toBe(false)
        })
    })

    it('should set error on fetch failure', async () => {
        const fetchFn = vi.fn().mockRejectedValue(new Error('Network error'))
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'error-test' }))

        await waitFor(() => {
            expect(result.current.error).toBe('Network error')
        })
    })

    it('should set generic error message for non-Error rejections', async () => {
        const fetchFn = vi.fn().mockRejectedValue('some string error')
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'generic-error' }))

        await waitFor(() => {
            expect(result.current.error).toBe('Erro ao carregar dados')
        })
    })

    it('should keep cached data when fetch fails', async () => {
        mockStorage['cache:keep'] = JSON.stringify({ data: { old: true }, timestamp: 999999 })
        const fetchFn = vi.fn().mockRejectedValue(new Error('fail'))
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'keep' }))

        await waitFor(() => {
            expect(result.current.error).toBe('fail')
        })

        expect(result.current.data).toEqual({ old: true })
    })

    it('should refresh data when refresh is called', async () => {
        let callCount = 0
        const fetchFn = vi.fn().mockImplementation(() => {
            callCount++
            return Promise.resolve({ count: callCount })
        })

        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'refresh-test' }))

        await waitFor(() => {
            expect(result.current.data).toEqual({ count: 1 })
        })

        await act(async () => {
            await result.current.refresh()
        })

        expect(result.current.data).toEqual({ count: 2 })
        expect(fetchFn).toHaveBeenCalledTimes(2)
    })

    it('should respect custom maxAge', () => {
        // Set maxAge to 1000ms, timestamp 500ms ago -> valid
        mockStorage['cache:custom'] = JSON.stringify({ data: 'valid', timestamp: 999500 })
        const fetchFn = vi.fn().mockResolvedValue('new')
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'custom', maxAge: 1000 }))
        expect(result.current.data).toBe('valid')
    })

    it('should indicate isCached when serving stale data during fetch', () => {
        mockStorage['cache:stale'] = JSON.stringify({ data: 'cached', timestamp: 999999 })
        const fetchFn = vi.fn().mockImplementation(() => new Promise(() => {})) // never resolves
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'stale' }))
        // data exists and loading is true internally
        expect(result.current.data).toBe('cached')
        expect(result.current.isCached).toBe(true)
    })

    it('should handle malformed cache data gracefully', () => {
        mockStorage['cache:bad'] = 'not-json'
        const fetchFn = vi.fn().mockResolvedValue('ok')
        const { result } = renderHook(() => useOfflineCache(fetchFn, { key: 'bad' }))
        expect(result.current.data).toBeNull()
    })
})
