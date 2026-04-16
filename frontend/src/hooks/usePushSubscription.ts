import { useState, useEffect, useCallback } from 'react'
import api, { getApiErrorMessage } from '@/lib/api'
import { captureError } from '@/lib/sentry'
import { toast } from 'sonner'

interface PushSubscriptionState {
    isSubscribed: boolean
    isSupported: boolean
    permission: NotificationPermission | 'unsupported'
    loading: boolean
}

function unwrapPayload<T>(response: { data?: { data?: T } | T }): T | undefined {
    const payload = response?.data

    if (payload != null && typeof payload === 'object' && 'data' in payload) {
        return (payload as { data?: T }).data
    }

    return payload as T | undefined
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const rawData = window.atob(base64)
    const outputArray = new Uint8Array(rawData.length)
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i)
    }
    return outputArray
}

export function usePushSubscription() {
    const [state, setState] = useState<PushSubscriptionState>({
        isSubscribed: false,
        isSupported: false,
        permission: 'unsupported',
        loading: true,
    })

    useEffect(() => {
        const checkSupport = async () => {
            const supported = 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window

            if (!supported) {
                setState({ isSubscribed: false, isSupported: false, permission: 'unsupported', loading: false })
                return
            }

            const permission = Notification.permission
            let isSubscribed = false

            try {
                const reg = await navigator.serviceWorker.ready
                const sub = await reg.pushManager.getSubscription()
                isSubscribed = !!sub
            } catch {
                // ignore
            }

            setState({ isSubscribed, isSupported: true, permission, loading: false })
        }

        checkSupport()
    }, [])

    const subscribe = useCallback(async (): Promise<boolean> => {
        setState(prev => ({ ...prev, loading: true }))

        try {
            const permission = await Notification.requestPermission()
            if (permission !== 'granted') {
                setState(prev => ({ ...prev, permission, loading: false }))
                return false
            }

            const reg = await navigator.serviceWorker.ready
            const vapidKeyResponse = await api.get('/push/vapid-key')
            const vapidKey = unwrapPayload<{ publicKey: string }>(vapidKeyResponse)?.publicKey

            const options: PushSubscriptionOptionsInit = {
                userVisibleOnly: true,
                ...(vapidKey ? { applicationServerKey: urlBase64ToUint8Array(vapidKey) as BufferSource } : {}),
            }

            const subscription = await reg.pushManager.subscribe(options)
            try {
                const json = subscription.toJSON()
                await api.post('/push/subscribe', {
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: json.keys?.p256dh,
                        auth: json.keys?.auth,
                    },
                })
            } catch (error) {
                await subscription.unsubscribe()
                throw error
            }

            setState({ isSubscribed: true, isSupported: true, permission: 'granted', loading: false })
            return true
        } catch (err) {
            captureError(err, { action: 'push-subscribe' })
            setState(prev => ({ ...prev, loading: false }))
            toast.error(getApiErrorMessage(err, 'Erro ao ativar notificações push'))
            return false
        }
    }, [])

    const unsubscribe = useCallback(async (): Promise<boolean> => {
        setState(prev => ({ ...prev, loading: true }))

        try {
            const reg = await navigator.serviceWorker.ready
            const sub = await reg.pushManager.getSubscription()
            if (sub) {
                await api.delete('/push/unsubscribe', {
                    data: { endpoint: sub.endpoint },
                })
                await sub.unsubscribe()
            }

            setState(prev => ({ ...prev, isSubscribed: false, loading: false }))
            return true
        } catch (err) {
            captureError(err, { action: 'push-unsubscribe' })
            setState(prev => ({ ...prev, loading: false }))
            toast.error(getApiErrorMessage(err, 'Erro ao desativar notificações push'))
            return false
        }
    }, [])

    const sendTest = useCallback(async (): Promise<boolean> => {
        try {
            await api.post('/push/test')
            return true
        } catch (err) {
            captureError(err, { action: 'push-test' })
            toast.error(getApiErrorMessage(err, 'Erro ao enviar notificação de teste'))
            return false
        }
    }, [])

    return { ...state, subscribe, unsubscribe, sendTest }
}
