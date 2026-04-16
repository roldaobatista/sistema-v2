/**
 * @deprecated Use usePushSubscription instead — this hook is kept for backward compatibility.
 * usePushSubscription has better error handling (rollback on subscribe failure) and sendTest support.
 */
import { usePushSubscription } from './usePushSubscription'

type PushPermission = 'default' | 'granted' | 'denied' | 'unsupported'

interface UsePushNotificationsReturn {
    permission: PushPermission
    isSubscribed: boolean
    isLoading: boolean
    subscribe: () => Promise<void>
    unsubscribe: () => Promise<void>
    sendTest: () => Promise<void>
}

export function usePushNotifications(): UsePushNotificationsReturn {
    const { permission, isSubscribed, loading, subscribe, unsubscribe, sendTest } = usePushSubscription()

    return {
        permission: permission as PushPermission,
        isSubscribed,
        isLoading: loading,
        subscribe: async () => { await subscribe() },
        unsubscribe: async () => { await unsubscribe() },
        sendTest: async () => { await sendTest() },
    }
}
