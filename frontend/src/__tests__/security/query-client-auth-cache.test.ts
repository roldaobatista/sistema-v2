import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient } from '@tanstack/react-query'

type MockBroadcastChannel = {
    postMessage: ReturnType<typeof vi.fn>
    close: ReturnType<typeof vi.fn>
    onmessage: ((event: MessageEvent) => void) | null
}

describe('query-client auth cache broadcast', () => {
    let mockChannel: MockBroadcastChannel

    beforeEach(() => {
        vi.resetModules()
        mockChannel = {
            postMessage: vi.fn(),
            close: vi.fn(),
            onmessage: null,
        }

        const MockBroadcastChannelConstructor = vi.fn(function BroadcastChannelMock() {
            return mockChannel
        })

        vi.stubGlobal('BroadcastChannel', MockBroadcastChannelConstructor as unknown as typeof BroadcastChannel)
    })

    afterEach(async () => {
        const { cleanupCrossTabSync } = await import('@/lib/cross-tab-sync')
        cleanupCrossTabSync()
        vi.unstubAllGlobals()
        vi.restoreAllMocks()
    })

    it('limpa cache autenticado localmente sem notificar outras abas por padrao', async () => {
        const { clearAuthenticatedQueryCache, queryClient } = await import('@/lib/query-client')
        const clearSpy = vi.spyOn(queryClient, 'clear')

        clearAuthenticatedQueryCache()

        expect(clearSpy).toHaveBeenCalledTimes(1)
        expect(mockChannel.postMessage).not.toHaveBeenCalledWith(expect.objectContaining({
            type: 'clear-authenticated-cache',
        }))
    })

    it('notifica outras abas somente quando a invalidacao de sessao e explicita', async () => {
        const { clearAuthenticatedQueryCache } = await import('@/lib/query-client')

        clearAuthenticatedQueryCache({ broadcast: true, scope: 'admin' })

        expect(mockChannel.postMessage).toHaveBeenCalledWith(expect.objectContaining({
            type: 'clear-authenticated-cache',
            scope: 'admin',
        }))
    })

    it('preserva cache admin ao receber broadcast de limpeza do portal', async () => {
        const { initCrossTabSync } = await import('@/lib/cross-tab-sync')
        const queryClient = new QueryClient()
        queryClient.setQueryData(['work-orders', 10], { id: 10 })
        queryClient.setQueryData(['portal-work-orders'], [{ id: 20 }])

        initCrossTabSync(queryClient)
        mockChannel.onmessage?.({
            data: {
                type: 'clear-authenticated-cache',
                scope: 'portal',
                timestamp: Date.now(),
            },
        } as MessageEvent)

        expect(queryClient.getQueryData(['work-orders', 10])).toEqual({ id: 10 })
        expect(queryClient.getQueryData(['portal-work-orders'])).toBeUndefined()
    })

    it('preserva cache portal ao receber broadcast de limpeza admin', async () => {
        const { initCrossTabSync } = await import('@/lib/cross-tab-sync')
        const queryClient = new QueryClient()
        queryClient.setQueryData(['customers'], [{ id: 30 }])
        queryClient.setQueryData(['portal-dashboard-os'], [{ id: 40 }])

        initCrossTabSync(queryClient)
        mockChannel.onmessage?.({
            data: {
                type: 'clear-authenticated-cache',
                scope: 'admin',
                timestamp: Date.now(),
            },
        } as MessageEvent)

        expect(queryClient.getQueryData(['customers'])).toBeUndefined()
        expect(queryClient.getQueryData(['portal-dashboard-os'])).toEqual([{ id: 40 }])
    })
})
