import type { QueryClient } from '@tanstack/react-query'

const CHANNEL_NAME = 'kalibrium-sync'

interface SyncMessage {
    type: 'clear-authenticated-cache' | 'invalidate-all'
    timestamp: number
    scope?: AuthenticatedCacheScope
}

let channel: BroadcastChannel | null = null
export type AuthenticatedCacheScope = 'admin' | 'portal' | 'all'

const authenticatedCacheClearListeners = new Set<(scope: AuthenticatedCacheScope) => void>()

function notifyAuthenticatedCacheClearListeners(scope: AuthenticatedCacheScope) {
    for (const listener of authenticatedCacheClearListeners) {
        listener(scope)
    }
}

function getQueryScope(queryKey: readonly unknown[]): AuthenticatedCacheScope {
    const rootKey = queryKey[0]

    if (typeof rootKey === 'string' && rootKey.startsWith('portal-')) {
        return 'portal'
    }

    return 'admin'
}

function clearQueriesForScope(queryClient: QueryClient, scope: AuthenticatedCacheScope) {
    if (scope === 'all') {
        queryClient.clear()
        return
    }

    queryClient.removeQueries({
        predicate: query => getQueryScope(query.queryKey) === scope,
    })
}

function getChannel(): BroadcastChannel | null {
    if (typeof BroadcastChannel === 'undefined') return null
    if (!channel) {
        channel = new BroadcastChannel(CHANNEL_NAME)
    }
    return channel
}

/**
 * Broadcast a global invalidation to all other open tabs.
 * Called automatically after every successful mutation via MutationCache.
 */
export function broadcastInvalidateAll() {
    const ch = getChannel()
    if (!ch) return

    const message: SyncMessage = {
        type: 'invalidate-all',
        timestamp: Date.now(),
    }
    ch.postMessage(message)
}

export function broadcastClearAuthenticatedCache(scope: AuthenticatedCacheScope = 'all') {
    const ch = getChannel()
    if (!ch) return

    const message: SyncMessage = {
        type: 'clear-authenticated-cache',
        timestamp: Date.now(),
        scope,
    }
    ch.postMessage(message)
}

export function subscribeAuthenticatedCacheClear(listener: (scope: AuthenticatedCacheScope) => void) {
    authenticatedCacheClearListeners.add(listener)

    return () => {
        authenticatedCacheClearListeners.delete(listener)
    }
}

/**
 * Initialize the global cross-tab sync listener.
 * Listens for invalidation messages from other tabs and
 * refetches all active queries in this tab.
 */
export function initCrossTabSync(queryClient: QueryClient) {
    const ch = getChannel()
    if (!ch) return

    ch.onmessage = (event: MessageEvent<SyncMessage>) => {
        const data = event.data
        if (data?.type === 'clear-authenticated-cache') {
            const scope = data.scope ?? 'all'
            clearQueriesForScope(queryClient, scope)
            notifyAuthenticatedCacheClearListeners(scope)
            return
        }

        if (data?.type !== 'invalidate-all') return

        // Invalidate ALL queries — TanStack only refetches active (mounted) ones
        queryClient.invalidateQueries()
    }
}

/**
 * Cleanup the BroadcastChannel. Call on app unmount.
 */
export function cleanupCrossTabSync() {
    if (channel) {
        channel.close()
        channel = null
    }
}

/**
 * @deprecated No longer needed — cross-tab sync is now handled globally
 * via MutationCache.onSuccess in App.tsx. This export is kept for
 * backward compatibility with existing imports.
 */
export function broadcastQueryInvalidation(_queryKeys: string[], _source?: string) {
    // No-op: handled globally by MutationCache
}
