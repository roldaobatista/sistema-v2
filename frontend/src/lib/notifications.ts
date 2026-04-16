type NotificationEnvelope<T> = {
    success?: boolean
    data?: T
}

type NotificationListItem = {
    id: number | string
    read?: boolean
    read_at?: string | null
}

type NotificationListPayload<T> = {
    notifications?: T[]
    unread_count?: number
}

function hasEnvelopeData<T>(payload: unknown): payload is NotificationEnvelope<T> {
    return !!payload && typeof payload === 'object' && 'data' in payload
}

export function extractNotificationList<T>(payload: NotificationEnvelope<NotificationListPayload<T>> | NotificationListPayload<T> | T[] | null | undefined): T[] {
    if (Array.isArray(payload)) {
        return payload
    }

    const data = hasEnvelopeData<NotificationListPayload<T>>(payload)
        ? payload.data
        : payload

    return Array.isArray(data?.notifications) ? data.notifications : []
}

export function extractUnreadCount(
    payload: NotificationEnvelope<{ unread_count?: number }> | { unread_count?: number } | null | undefined
): number {
    const data = hasEnvelopeData<{ unread_count?: number }>(payload)
        ? payload.data
        : payload

    return typeof data?.unread_count === 'number' ? data.unread_count : 0
}

export function countUnreadNotifications<T extends NotificationListItem>(notifications: T[]): number {
    return notifications.filter(item => !item.read && !item.read_at).length
}
