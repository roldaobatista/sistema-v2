import { renderHook, act } from '@testing-library/react'
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { useSyncStatus } from '../useSyncStatus'
import { syncEngine } from '@/lib/syncEngine'
import { getMutationQueueCount, getSyncErrorCount } from '@/lib/offlineDb'

vi.mock('@/lib/offlineDb', () => ({
    getMutationQueueCount: vi.fn(),
    getSyncErrorCount: vi.fn(),
}))

vi.mock('@/hooks/usePWA', () => ({
    usePWA: vi.fn(() => ({ isOnline: true }))
}))

vi.mock('@/lib/syncEngine', () => ({
    syncEngine: {
        fullSync: vi.fn(),
        onSyncComplete: vi.fn(() => vi.fn()) // returns unsubscribe fn
    }
}))

describe('useSyncStatus', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        vi.mocked(getMutationQueueCount).mockResolvedValue(0)
        vi.mocked(getSyncErrorCount).mockResolvedValue(0)
        vi.mocked(syncEngine.fullSync).mockResolvedValue({
            success: true,
            timestamp: '2026-03-23T10:00:00Z',
            errors: []
        })
    })

    it('should initialize and fetch pending counts', async () => {
        vi.mocked(getMutationQueueCount).mockResolvedValue(3)
        vi.mocked(getSyncErrorCount).mockResolvedValue(1)

        const { result } = renderHook(() => useSyncStatus())

        // Espera a promise do useEffect de Refresh rodar
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 0))
        })

        expect(result.current.pendingCount).toBe(3)
        expect(result.current.syncErrorCount).toBe(1)
        expect(getMutationQueueCount).toHaveBeenCalled()
    })

    it('should trigger syncNow and update state', async () => {
        const { result } = renderHook(() => useSyncStatus())

        await act(async () => {
            await result.current.syncNow()
        })

        expect(syncEngine.fullSync).toHaveBeenCalled()
        expect(result.current.lastResult?.success).toBe(true)
    })
})
