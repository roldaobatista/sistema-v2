import { useCallback } from 'react'
import { addToSyncQueue, generateUUID } from '@/lib/offline/indexedDB'
import { syncOfflineQueue } from '@/lib/offline/syncEngine'
import { useOfflineStatus } from './useOfflineStatus'
import api from '@/lib/api'

interface UseOfflineQueueOptions {
  onQueued?: () => void
  onSynced?: () => void
}

export function useOfflineQueue(options: UseOfflineQueueOptions = {}) {
  const { isOnline, pendingCount, refresh } = useOfflineStatus()

  /**
   * Submit a request — if online, send immediately.
   * If offline, queue for later sync.
   */
  const submit = useCallback(
    async (
      url: string,
      method: string,
      data: Record<string, unknown>,
      eventType?: string,
    ) => {
      const uuid = generateUUID()
      const localTimestamp = new Date().toISOString()

      // Inject UUID and local timestamp into data
      const enrichedData = {
        ...data,
        _offline_uuid: uuid,
        _local_timestamp: localTimestamp,
      }

      if (isOnline) {
        try {
          const response = await api.request({ url, method, data: enrichedData })
          options.onSynced?.()
          return { synced: true, response, uuid }
        } catch {
          // If request fails while online, queue it
          await addToSyncQueue({
            uuid,
            url,
            method,
            data: enrichedData,
            headers: {},
            localTimestamp,
            eventType,
          })
          options.onQueued?.()
          refresh()
          return { synced: false, queued: true, uuid }
        }
      }

      // Offline: queue immediately
      await addToSyncQueue({
        uuid,
        url,
        method,
        data: enrichedData,
        headers: {},
        localTimestamp,
        eventType,
      })
      options.onQueued?.()
      refresh()
      return { synced: false, queued: true, uuid }
    },
    [isOnline, options, refresh],
  )

  const forceSync = useCallback(async () => {
    await syncOfflineQueue()
    refresh()
  }, [refresh])

  return {
    submit,
    forceSync,
    isOnline,
    pendingCount,
  }
}
