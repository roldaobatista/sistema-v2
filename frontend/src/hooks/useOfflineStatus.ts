import { useState, useEffect, useCallback } from 'react'
import { getQueueCount } from '@/lib/offline/indexedDB'

interface OfflineStatus {
  isOnline: boolean
  pendingCount: number
  failedCount: number
  conflictCount: number
  refresh: () => void
}

export function useOfflineStatus(): OfflineStatus {
  const [isOnline, setIsOnline] = useState(
    typeof navigator !== 'undefined' ? navigator.onLine : true,
  )
  const [counts, setCounts] = useState({ pending: 0, failed: 0, conflict: 0 })

  const refresh = useCallback(async () => {
    try {
      const queueCounts = await getQueueCount()
      setCounts(queueCounts)
    } catch {
      // IndexedDB may not be available in SSR or test envs
    }
  }, [])

  useEffect(() => {
    const handleOnline = () => {
      setIsOnline(true)
      refresh()
    }
    const handleOffline = () => setIsOnline(false)

    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)

    // Initial count
    refresh()

    // Poll every 30s for count changes
    const interval = setInterval(refresh, 30_000)

    return () => {
      window.removeEventListener('online', handleOnline)
      window.removeEventListener('offline', handleOffline)
      clearInterval(interval)
    }
  }, [refresh])

  return {
    isOnline,
    pendingCount: counts.pending,
    failedCount: counts.failed,
    conflictCount: counts.conflict,
    refresh,
  }
}
