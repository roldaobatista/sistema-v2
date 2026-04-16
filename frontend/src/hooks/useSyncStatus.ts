import { useState, useEffect, useCallback, useRef } from 'react'
import { syncEngine, type SyncResult } from '@/lib/syncEngine'
import { getMutationQueueCount, getSyncErrorCount } from '@/lib/offlineDb'
import { usePWA } from '@/hooks/usePWA'

export function useSyncStatus() {
    const { isOnline } = usePWA()
    const [pendingCount, setPendingCount] = useState(0)
    const [syncErrorCount, setSyncErrorCount] = useState(0)
    const [lastSyncAt, setLastSyncAt] = useState<string | null>(null)
    const [isSyncing, setIsSyncing] = useState(false)
    const [lastResult, setLastResult] = useState<SyncResult | null>(null)
    const autoSyncTimerRef = useRef<number | null>(null)
    const isSyncingRef = useRef(false)
    const isOnlineRef = useRef(isOnline)

    // Keep refs in sync with state
    useEffect(() => { isOnlineRef.current = isOnline }, [isOnline])

    // Refresh pending count
    const refreshPendingCount = useCallback(async () => {
        try {
            const [count, errorCount] = await Promise.all([
                getMutationQueueCount(),
                getSyncErrorCount()
            ])
            setPendingCount(count)
            setSyncErrorCount(errorCount)
        } catch {
            // IndexedDB may be unavailable in tests, SSR, or restricted browsers.
            setPendingCount(0)
            setSyncErrorCount(0)
        }
    }, [])

    // Manual sync trigger — uses refs to avoid dependency on isSyncing/isOnline
    // which would cause effect cascades
    const syncNow = useCallback(async () => {
        if (isSyncingRef.current || !isOnlineRef.current) return null
        isSyncingRef.current = true
        setIsSyncing(true)
        try {
            const result = await syncEngine.fullSync()
            setLastResult(result)
            setLastSyncAt(result.timestamp)
            await refreshPendingCount()
            return result
        } finally {
            isSyncingRef.current = false
            setIsSyncing(false)
        }
    }, [refreshPendingCount])

    // Listen for sync events from SW
    useEffect(() => {
        const handleMessage = (event: MessageEvent) => {
            if (event.data?.type === 'SYNC_COMPLETE') {
                refreshPendingCount()
                setLastSyncAt(new Date().toISOString())
            }
        }

        // Listen for localStorage requests from SW
        const handleSwMessage = (event: MessageEvent) => {
            if (event.data?.type === 'GET_LOCAL_STORAGE') {
                const value = localStorage.getItem(event.data.key)
                let parsed = null
                try { parsed = value ? JSON.parse(value) : null } catch { parsed = null }
                event.ports[0]?.postMessage(parsed)
            }
        }

        navigator.serviceWorker?.addEventListener('message', handleMessage)
        navigator.serviceWorker?.addEventListener('message', handleSwMessage)

        return () => {
            navigator.serviceWorker?.removeEventListener('message', handleMessage)
            navigator.serviceWorker?.removeEventListener('message', handleSwMessage)
        }
    }, [refreshPendingCount])

    // Auto-sync when coming back online (only reacts to isOnline changes)
    useEffect(() => {
        if (isOnline) {
            // Small delay to let network stabilize
            const timer = setTimeout(() => {
                getMutationQueueCount().then(count => {
                    if (count > 0) syncNow()
                }).catch(() => {
                    // IndexedDB access failed — skip auto-sync
                })
            }, 1000)
            return () => clearTimeout(timer)
        }
    }, [isOnline, syncNow])

    // Periodic sync (every 5 minutes when online)
    useEffect(() => {
        if (isOnline) {
            autoSyncTimerRef.current = window.setInterval(() => {
                syncNow()
            }, 5 * 60 * 1000) // 5 minutes
        }

        return () => {
            if (autoSyncTimerRef.current) {
                clearInterval(autoSyncTimerRef.current)
                autoSyncTimerRef.current = null
            }
        }
    }, [isOnline, syncNow])

    // Initial refresh
    useEffect(() => {
        refreshPendingCount()
    }, [refreshPendingCount])

    // Listen for sync engine events
    useEffect(() => {
        return syncEngine.onSyncComplete((result) => {
            setLastResult(result)
            setLastSyncAt(result.timestamp)
            refreshPendingCount()
        })
    }, [refreshPendingCount])

    return {
        pendingCount,
        syncErrorCount,
        lastSyncAt,
        isSyncing,
        lastResult,
        isOnline,
        syncNow,
        refreshPendingCount,
    }
}
