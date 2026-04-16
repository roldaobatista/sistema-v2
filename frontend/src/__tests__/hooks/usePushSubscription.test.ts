import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
    getApiErrorMessage: vi.fn((error: { response?: { data?: { message?: string } } } | undefined, fallback: string) =>
        error?.response?.data?.message ?? fallback
    ),
}))

vi.mock('sonner', () => ({
    toast: {
        error: vi.fn(),
    },
}))

vi.mock('@/lib/sentry', () => ({
    captureError: vi.fn(),
}))

import api from '@/lib/api'
import { toast } from 'sonner'
import { usePushSubscription } from '@/hooks/usePushSubscription'

const mockGet = vi.mocked(api.get)
const mockPost = vi.mocked(api.post)
const mockDelete = vi.mocked(api.delete)

const mockSubscription = {
    endpoint: 'https://fcm.googleapis.com/fcm/send/test',
    toJSON: () => ({
        endpoint: 'https://fcm.googleapis.com/fcm/send/test',
        keys: {
            p256dh: 'test-p256dh',
            auth: 'test-auth',
        },
    }),
    unsubscribe: vi.fn().mockResolvedValue(true),
}

const mockRegistration = {
    pushManager: {
        getSubscription: vi.fn(),
        subscribe: vi.fn(),
    },
}

describe('usePushSubscription', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        vi.stubGlobal('PushManager', class PushManager {})
        vi.stubGlobal('Notification', {
            permission: 'default',
            requestPermission: vi.fn().mockResolvedValue('granted'),
        })

        Object.defineProperty(globalThis.Notification, 'permission', {
            value: 'default',
            writable: true,
            configurable: true,
        })

        mockRegistration.pushManager.getSubscription.mockReset()
        mockRegistration.pushManager.subscribe.mockReset()
        mockRegistration.pushManager.getSubscription.mockResolvedValue(null)
        mockRegistration.pushManager.subscribe.mockResolvedValue(mockSubscription)
        mockSubscription.unsubscribe.mockClear()

        Object.defineProperty(navigator, 'serviceWorker', {
            value: { ready: Promise.resolve(mockRegistration) },
            writable: true,
            configurable: true,
        })
    })

    it('usa os endpoints reais do Laravel ao inscrever e so marca sucesso apos aceite do backend', async () => {
        mockGet.mockResolvedValue({ data: { data: { publicKey: 'dGVzdC1wdXNoLWtleQ' } } })
        mockPost.mockResolvedValue({ status: 201, data: { data: { id: 5 } } })

        const { result } = renderHook(() => usePushSubscription())

        await waitFor(() => {
            expect(result.current.loading).toBe(false)
            expect(result.current.isSupported).toBe(true)
        })

        let subscribed = false
        await act(async () => {
            subscribed = await result.current.subscribe()
        })

        expect(subscribed).toBe(true)
        expect(mockGet).toHaveBeenCalledWith('/push/vapid-key')
        expect(mockPost).toHaveBeenCalledWith('/push/subscribe', {
            endpoint: 'https://fcm.googleapis.com/fcm/send/test',
            keys: {
                p256dh: 'test-p256dh',
                auth: 'test-auth',
            },
        })

        await waitFor(() => {
            expect(result.current.isSubscribed).toBe(true)
            expect(result.current.permission).toBe('granted')
        })
    })

    it('faz rollback da inscricao local quando o backend rejeita o subscribe', async () => {
        mockGet.mockResolvedValue({ data: { data: { publicKey: 'dGVzdC1wdXNoLWtleQ' } } })
        mockPost.mockRejectedValue({
            response: {
                data: {
                    message: 'Falha ao registrar push',
                },
            },
        })

        const { result } = renderHook(() => usePushSubscription())

        await waitFor(() => {
            expect(result.current.loading).toBe(false)
        })

        let subscribed = true
        await act(async () => {
            subscribed = await result.current.subscribe()
        })

        expect(subscribed).toBe(false)
        expect(mockSubscription.unsubscribe).toHaveBeenCalledTimes(1)
        expect(toast.error).toHaveBeenCalledWith('Falha ao registrar push')

        await waitFor(() => {
            expect(result.current.isSubscribed).toBe(false)
        })
    })

    it('usa o endpoint real de unsubscribe e so confirma sucesso apos o backend aceitar', async () => {
        Object.defineProperty(globalThis.Notification, 'permission', {
            value: 'granted',
            writable: true,
            configurable: true,
        })
        mockRegistration.pushManager.getSubscription
            .mockResolvedValueOnce(mockSubscription)
            .mockResolvedValueOnce(mockSubscription)
        mockDelete.mockResolvedValue({ status: 200, data: { message: 'Inscrição removida com sucesso' } })

        const { result } = renderHook(() => usePushSubscription())

        await waitFor(() => {
            expect(result.current.isSubscribed).toBe(true)
        })

        let unsubscribed = false
        await act(async () => {
            unsubscribed = await result.current.unsubscribe()
        })

        expect(unsubscribed).toBe(true)
        expect(mockDelete).toHaveBeenCalledWith('/push/unsubscribe', {
            data: { endpoint: 'https://fcm.googleapis.com/fcm/send/test' },
        })
        expect(mockSubscription.unsubscribe).toHaveBeenCalledTimes(1)

        await waitFor(() => {
            expect(result.current.isSubscribed).toBe(false)
        })
    })
})
