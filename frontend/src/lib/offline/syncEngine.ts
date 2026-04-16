import api from '@/lib/api'
import { captureError } from '@/lib/sentry'
import { getPendingRequests, deleteRequest, updateRequestStatus } from './indexedDB'
import { toast } from 'sonner'

let isSyncing = false

function getHttpStatus(error: unknown): number | undefined {
    if (typeof error !== 'object' || error === null || !('response' in error)) {
        return undefined
    }

    const response = (error as { response?: { status?: unknown } }).response
    return typeof response?.status === 'number' ? response.status : undefined
}

/**
 * Syncs the pending offline queue with the server.
 * Should be called when the browser goes online or the app starts.
 */
export async function syncOfflineQueue() {
    if (isSyncing) return

    const pending = await getPendingRequests()
    if (pending.length === 0) return

    isSyncing = true
    const total = pending.length
    let synced = 0
    let failed = 0

    toast.info(`Sincronizando ${total} ${total === 1 ? 'pendência' : 'pendências'} offline...`, {
        duration: 5000,
    })

    // Process requests in order
    for (const request of pending) {
        try {
            await updateRequestStatus(request.id!, 'syncing', request.attempts + 1)

            // Re-send using the existing api instance
            await api.request({
                url: request.url,
                method: request.method,
                data: request.data,
                headers: {
                    ...request.headers,
                    'X-Offline-Sync': 'true', // Marker for the server if needed
                },
            })

            await deleteRequest(request.id!)
            synced++
        } catch (error: unknown) {
            const status = getHttpStatus(error)

            captureError(error, {
                context: 'offline.syncOfflineQueue',
                requestId: request.id,
                method: request.method,
                url: request.url,
                status,
            })

            failed++

            // If it's a permanent error (e.g. 422), we might want to discard or mark as failed
            // For now, we just keep it pending for the next attempt unless it's a 4xx that won't recover
            if (status !== undefined && status >= 400 && status < 500 && status !== 429) {
                await updateRequestStatus(request.id!, 'failed')
                // Optionally move to a "trash" store or notify the user
            } else {
                await updateRequestStatus(request.id!, 'pending')
                // Stop the sync process if it's a network error to avoid multiple failures
                break
            }
        }
    }

    isSyncing = false

    if (synced > 0) {
        toast.success(`${synced} ${synced === 1 ? 'operação foi' : 'operações foram'} sincronizadas!`)

        // Notify Service Worker if needed (as per main.tsx listeners)
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'SYNC_COMPLETE',
                synced,
                failed,
                remaining: total - synced - failed
            })
        }
    }

    if (failed > 0) {
        toast.error(`${failed} ${failed === 1 ? 'operação falhou' : 'operações falharam'} na sincronização.`)
    }
}

/**
 * Initializes listeners for the sync engine.
 */
export function initSyncEngine() {
    if (typeof window === 'undefined') return

    window.addEventListener('online', () => {
        syncOfflineQueue()
    })

    // Initial check on load
    if (navigator.onLine) {
        syncOfflineQueue()
    }
}
