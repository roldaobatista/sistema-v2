import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'
import { useCrossTabSync } from '@/hooks/useCrossTabSync'

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })
    return ({ children }: { children: React.ReactNode }) =>
        React.createElement(QueryClientProvider, { client: queryClient }, children)
}

describe('useCrossTabSync', () => {
    let mockChannel: {
        postMessage: ReturnType<typeof vi.fn>
        close: ReturnType<typeof vi.fn>
        onmessage: ((ev: MessageEvent) => void) | null
    }

    beforeEach(() => {
        mockChannel = {
            postMessage: vi.fn(),
            close: vi.fn(),
            onmessage: null,
        }

        // Use a class so `new BroadcastChannel(...)` works properly
        const MockBroadcastChannel = vi.fn(function (this: any) {
            return mockChannel
        }) as any
        vi.stubGlobal('BroadcastChannel', MockBroadcastChannel)
    })

    afterEach(() => {
        vi.restoreAllMocks()
        vi.unstubAllGlobals()
    })

    it('should create a BroadcastChannel with correct name', () => {
        renderHook(() => useCrossTabSync(), { wrapper: createWrapper() })
        expect(globalThis.BroadcastChannel).toHaveBeenCalledWith('kalibrium-sync')
    })

    it('should set up onmessage handler', () => {
        renderHook(() => useCrossTabSync(), { wrapper: createWrapper() })
        expect(mockChannel.onmessage).toBeInstanceOf(Function)
    })

    it('should broadcast invalidation messages', () => {
        const { result } = renderHook(() => useCrossTabSync(), { wrapper: createWrapper() })

        act(() => {
            result.current.broadcast(['orders', 'customers'])
        })

        expect(mockChannel.postMessage).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'invalidate',
                queryKeys: ['orders', 'customers'],
                timestamp: expect.any(Number),
            }),
        )
    })

    it('should invalidate queries when receiving a message from another tab', () => {
        const queryClient = new QueryClient({
            defaultOptions: { queries: { retry: false, gcTime: 0 } },
        })
        const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries')

        const wrapper = ({ children }: { children: React.ReactNode }) =>
            React.createElement(QueryClientProvider, { client: queryClient }, children)

        renderHook(() => useCrossTabSync(), { wrapper })

        act(() => {
            mockChannel.onmessage!({
                data: {
                    type: 'invalidate',
                    queryKeys: ['notifications', 'orders'],
                    timestamp: Date.now(),
                },
            } as any)
        })

        expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['notifications'] })
        expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['orders'] })
    })

    it('should ignore messages with wrong type', () => {
        const queryClient = new QueryClient({
            defaultOptions: { queries: { retry: false, gcTime: 0 } },
        })
        const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries')

        const wrapper = ({ children }: { children: React.ReactNode }) =>
            React.createElement(QueryClientProvider, { client: queryClient }, children)

        renderHook(() => useCrossTabSync(), { wrapper })

        act(() => {
            mockChannel.onmessage!({
                data: { type: 'other', queryKeys: ['test'] },
            } as any)
        })

        expect(invalidateSpy).not.toHaveBeenCalled()
    })

    it('should ignore messages without queryKeys array', () => {
        const queryClient = new QueryClient({
            defaultOptions: { queries: { retry: false, gcTime: 0 } },
        })
        const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries')

        const wrapper = ({ children }: { children: React.ReactNode }) =>
            React.createElement(QueryClientProvider, { client: queryClient }, children)

        renderHook(() => useCrossTabSync(), { wrapper })

        act(() => {
            mockChannel.onmessage!({
                data: { type: 'invalidate', queryKeys: 'not-array' },
            } as any)
        })

        expect(invalidateSpy).not.toHaveBeenCalled()
    })

    it('should close BroadcastChannel on unmount', () => {
        const { unmount } = renderHook(() => useCrossTabSync(), { wrapper: createWrapper() })
        unmount()
        expect(mockChannel.close).toHaveBeenCalled()
    })

    it('should not crash when BroadcastChannel is not available', () => {
        vi.unstubAllGlobals()
        delete (globalThis as any).BroadcastChannel

        expect(() => {
            const { result } = renderHook(() => useCrossTabSync(), { wrapper: createWrapper() })
            act(() => {
                result.current.broadcast(['test'])
            })
        }).not.toThrow()
    })
})
