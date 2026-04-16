import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query'
import {
  broadcastClearAuthenticatedCache,
  broadcastInvalidateAll,
  initCrossTabSync,
  type AuthenticatedCacheScope,
} from '@/lib/cross-tab-sync'
import { captureError } from '@/lib/sentry'

interface ClearAuthenticatedQueryCacheOptions {
  broadcast?: boolean
  scope?: AuthenticatedCacheScope
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
      refetchOnWindowFocus: true,
    },
  },
  queryCache: new QueryCache({
    onError: (error, query) => {
      captureError(error, {
        source: 'react-query.query',
        queryKey: JSON.stringify(query.queryKey),
      })
    },
  }),
  mutationCache: new MutationCache({
    onError: (error, _variables, _context, mutation) => {
      captureError(error, {
        source: 'react-query.mutation',
        mutationKey: JSON.stringify(mutation.options.mutationKey ?? []),
      })
    },
    onSuccess: () => {
      broadcastInvalidateAll()
    },
  }),
})

export function clearAuthenticatedQueryCache(options: ClearAuthenticatedQueryCacheOptions = {}) {
  queryClient.clear()
  if (options.broadcast) {
    broadcastClearAuthenticatedCache(options.scope ?? 'all')
  }
}

initCrossTabSync(queryClient)
