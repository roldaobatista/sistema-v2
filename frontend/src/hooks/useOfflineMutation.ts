import { useState, useCallback, useRef } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { offlinePost, offlinePut } from '@/lib/syncEngine'
import { useSyncStatus } from '@/hooks/useSyncStatus'

type HttpMethod = 'POST' | 'PUT'

interface UseOfflineMutationOptions<TData, TVariables> {
    /** URL da API (ex: '/tech/sync/batch') */
    url: string | ((variables: TVariables) => string)
    /** Método HTTP (default: POST) */
    method?: HttpMethod
    /** Query keys para invalidar após sucesso online */
    invalidateKeys?: string[][]
    /** Callback após sucesso (online ou queued) */
    onSuccess?: (data: TData | null, wasOffline: boolean) => void
    /** Callback após erro (apenas online — offline nunca falha) */
    onError?: (error: unknown) => void
    /** Mensagem toast quando salvo offline */
    offlineToast?: string
    /** Mensagem toast quando salvo online */
    successToast?: string
}

interface UseOfflineMutationReturn<TVariables> {
    mutate: (variables: TVariables) => Promise<void>
    isPending: boolean
    isOfflineQueued: boolean
    isOnline: boolean
}

export function useOfflineMutation<TData = unknown, TVariables = unknown>(
    options: UseOfflineMutationOptions<TData, TVariables>
): UseOfflineMutationReturn<TVariables> {
    const { method = 'POST', invalidateKeys, onSuccess, onError, offlineToast, successToast } = options
    const [isPending, setIsPending] = useState(false)
    const [isOfflineQueued, setIsOfflineQueued] = useState(false)
    const qc = useQueryClient()
    const { isOnline, refreshPendingCount } = useSyncStatus()
    const isPendingRef = useRef(false)

    const mutate = useCallback(async (variables: TVariables) => {
        if (isPendingRef.current) return
        isPendingRef.current = true
        setIsPending(true)
        setIsOfflineQueued(false)

        const url = typeof options.url === 'function' ? options.url(variables) : options.url

        try {
            const fn = method === 'PUT' ? offlinePut : offlinePost
            const wasQueued = await fn(url, variables)

            if (wasQueued) {
                setIsOfflineQueued(true)
                toast.info(offlineToast || 'Salvo offline. Será sincronizado quando houver conexão.')
                await refreshPendingCount()
                onSuccess?.(null, true)
            } else {
                if (successToast) toast.success(successToast)
                if (invalidateKeys) {
                    for (const key of invalidateKeys) {
                        qc.invalidateQueries({ queryKey: key })
                    }
                }
                onSuccess?.(null, false)
            }
        } catch (error) {
            onError?.(error)
        } finally {
            isPendingRef.current = false
            setIsPending(false)
        }
    }, [options.url, method, invalidateKeys, onSuccess, onError, offlineToast, successToast, qc, refreshPendingCount])

    return { mutate, isPending, isOfflineQueued, isOnline }
}
