import { useState, useCallback, useEffect } from 'react'
import { generateUlid } from '@/lib/offlineDb'
import api, { unwrapData } from '@/lib/api'

export interface ChatMessage {
    id: number | string
    work_order_id: number
    user_id: number
    user?: {
        name: string
        avatar_url?: string
    }
    message: string
    type: 'text' | 'file' | 'system'
    file_path?: string
    synced?: boolean
    created_at: string
}

const STORE_KEY = 'chat-messages'

export function useChatStoreForward(workOrderId: number) {
    const [messages, setMessages] = useState<ChatMessage[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [pendingCount, setPendingCount] = useState(0)

    const refresh = useCallback(async (silent = false) => {
        if (!silent) setIsLoading(true)
        try {
            // Load local pending ones
            const stored = localStorage.getItem(`${STORE_KEY}-${workOrderId}-pending`)
            let pending: ChatMessage[] = stored ? JSON.parse(stored) : []

            // Try fetch from server
            let serverMessages: ChatMessage[] = []
            try {
                const res = await api.get(`/work-orders/${workOrderId}/chats`)
                serverMessages = unwrapData<ChatMessage[]>(res) ?? []
                // Cache server messages
                localStorage.setItem(`${STORE_KEY}-${workOrderId}-cache`, JSON.stringify(serverMessages))

                // Clear pending that have been synced to the server (match by message body and recent created_at)
                const originalPendingCount = pending.length
                pending = pending.filter(pMessage => {
                     return !serverMessages.some(s => s.message === pMessage.message && Math.abs(new Date(s.created_at).getTime() - new Date(pMessage.created_at).getTime()) < 60000 * 60 * 24)
                });

                if (pending.length !== originalPendingCount) {
                    localStorage.setItem(`${STORE_KEY}-${workOrderId}-pending`, JSON.stringify(pending))
                }

                const all = [...serverMessages, ...pending].sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
                setMessages(all)
                setPendingCount(pending.length)
            } catch (err) {
                // Offline fallback
                const cached = localStorage.getItem(`${STORE_KEY}-${workOrderId}-cache`)
                const cachedMessages: ChatMessage[] = cached ? JSON.parse(cached) : []
                const all = [...cachedMessages, ...pending].sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
                setMessages(all)
                setPendingCount(pending.length)
            }
        } catch {
            setMessages([])
        } finally {
            if (!silent) setIsLoading(false)
        }
    }, [workOrderId])

    useEffect(() => {
        refresh()
        const interval = setInterval(() => {
            refresh(true)
        }, 10000)
        return () => clearInterval(interval)
    }, [refresh])

    const sendMessage = useCallback(async (
        messageText: string,
        userId: number,
        userName: string,
        type: ChatMessage['type'] = 'text'
    ) => {
        const msg: ChatMessage = {
            id: generateUlid(),
            work_order_id: workOrderId,
            user_id: userId,
            user: { name: userName },
            message: messageText,
            type,
            synced: false,
            created_at: new Date().toISOString(),
        }

        try {
            const res = await api.post(`/work-orders/${workOrderId}/chats`, { message: messageText, type })
            const created = unwrapData<ChatMessage>(res)
            if (created) {
                setMessages(prev => {
                    const next = [...prev, created]
                    return next.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
                })
            }
        } catch {
            // Failed to send online, queue for sync
            const stored = localStorage.getItem(`${STORE_KEY}-${workOrderId}-pending`)
            const pending: ChatMessage[] = stored ? JSON.parse(stored) : []
            pending.push(msg)
            localStorage.setItem(`${STORE_KEY}-${workOrderId}-pending`, JSON.stringify(pending))

            setMessages(prev => [...prev, msg].sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()))
            setPendingCount(pending.length)

            try {
                const { enqueueMutation } = await import('@/lib/offlineDb')
                await enqueueMutation('POST', `/work-orders/${workOrderId}/chats`, {
                    message: messageText,
                    type: type,
                })
            } catch {
                // Ignore enqueue error
            }
        }
    }, [workOrderId])

    return {
        messages,
        isLoading,
        pendingCount,
        sendMessage,
        refresh,
    }
}
