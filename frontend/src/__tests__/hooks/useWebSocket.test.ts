import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'

// Mock WebSocket
class MockWebSocket {
    static CONNECTING = 0
    static OPEN = 1
    static CLOSING = 2
    static CLOSED = 3

    url: string
    readyState = MockWebSocket.CONNECTING
    onopen: ((ev: any) => void) | null = null
    onclose: ((ev: any) => void) | null = null
    onmessage: ((ev: any) => void) | null = null
    onerror: ((ev: any) => void) | null = null
    send = vi.fn()
    close = vi.fn().mockImplementation(() => {
        this.readyState = MockWebSocket.CLOSED
        if (this.onclose) this.onclose({})
    })

    constructor(url: string) {
        this.url = url
        MockWebSocket.instances.push(this)
    }

    simulateOpen() {
        this.readyState = MockWebSocket.OPEN
        if (this.onopen) this.onopen({})
    }

    simulateMessage(data: any) {
        if (this.onmessage) this.onmessage({ data: JSON.stringify(data) })
    }

    simulateError() {
        if (this.onerror) this.onerror({})
    }

    simulateClose() {
        this.readyState = MockWebSocket.CLOSED
        if (this.onclose) this.onclose({})
    }

    static instances: MockWebSocket[] = []
    static clear() {
        MockWebSocket.instances = []
    }
}

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })
    return ({ children }: { children: React.ReactNode }) =>
        React.createElement(QueryClientProvider, { client: queryClient }, children)
}

describe('useWebSocket', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        vi.stubGlobal('WebSocket', MockWebSocket)
        MockWebSocket.clear()
    })

    afterEach(() => {
        vi.useRealTimers()
        vi.unstubAllGlobals()
    })

    // Lazy import to pick up the mocked WebSocket
    async function getHook() {
        const mod = await import('@/hooks/useWebSocket')
        return mod.useWebSocket
    }

    it('should not connect when enabled is false', async () => {
        const useWebSocket = await getHook()
        renderHook(() => useWebSocket({ url: 'ws://localhost:6001', enabled: false }), {
            wrapper: createWrapper(),
        })
        expect(MockWebSocket.instances).toHaveLength(0)
    })

    it('should not connect when url is undefined', async () => {
        const useWebSocket = await getHook()
        renderHook(() => useWebSocket({ enabled: true }), { wrapper: createWrapper() })
        expect(MockWebSocket.instances).toHaveLength(0)
    })

    it('should connect when enabled and url are provided', async () => {
        const useWebSocket = await getHook()
        renderHook(() => useWebSocket({ url: 'ws://localhost:6001', enabled: true }), {
            wrapper: createWrapper(),
        })
        expect(MockWebSocket.instances).toHaveLength(1)
        expect(MockWebSocket.instances[0].url).toBe('ws://localhost:6001')
    })

    it('should set isConnected to true on open', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        expect(result.current.isConnected).toBe(false)

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        expect(result.current.isConnected).toBe(true)
    })

    it('should subscribe to tenant channel on open', async () => {
        const useWebSocket = await getHook()
        renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', tenantId: 5, enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        expect(MockWebSocket.instances[0].send).toHaveBeenCalledWith(
            JSON.stringify({ event: 'subscribe', channel: 'private-tenant.5.notifications' }),
        )
    })

    it('should subscribe to user channel on open', async () => {
        const useWebSocket = await getHook()
        renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', userId: 42, enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        expect(MockWebSocket.instances[0].send).toHaveBeenCalledWith(
            JSON.stringify({ event: 'subscribe', channel: 'private-user.42.notifications' }),
        )
    })

    it('should subscribe to both tenant and user channels', async () => {
        const useWebSocket = await getHook()
        renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', tenantId: 1, userId: 10, enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        expect(MockWebSocket.instances[0].send).toHaveBeenCalledTimes(2)
    })

    it('should set lastMessage when a message is received', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        act(() => {
            MockWebSocket.instances[0].simulateMessage({ event: 'test', data: { id: 1 } })
        })

        expect(result.current.lastMessage).toEqual({ event: 'test', data: { id: 1 } })
    })

    it('should ignore non-JSON messages without crashing', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        act(() => {
            if (MockWebSocket.instances[0].onmessage) {
                MockWebSocket.instances[0].onmessage({ data: 'not-json-ping' })
            }
        })

        expect(result.current.lastMessage).toBeNull()
    })

    it('should send data when connection is open', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        // Clear subscription calls
        MockWebSocket.instances[0].send.mockClear()

        act(() => {
            result.current.send({ event: 'ping' })
        })

        expect(MockWebSocket.instances[0].send).toHaveBeenCalledWith(JSON.stringify({ event: 'ping' }))
    })

    it('should not send data when connection is not open', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        // Connection not opened yet (readyState = CONNECTING)
        MockWebSocket.instances[0].send.mockClear()

        act(() => {
            result.current.send({ event: 'ping' })
        })

        expect(MockWebSocket.instances[0].send).not.toHaveBeenCalled()
    })

    it('should set isConnected to false on close', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })
        expect(result.current.isConnected).toBe(true)

        act(() => {
            MockWebSocket.instances[0].simulateClose()
        })
        expect(result.current.isConnected).toBe(false)
    })

    it('should attempt reconnection on close with exponential backoff', async () => {
        const useWebSocket = await getHook()
        renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        const firstWs = MockWebSocket.instances[0]
        act(() => {
            firstWs.simulateOpen()
        })

        // Close triggers reconnect after baseDelay(2000) * 2^0 = 2000ms
        act(() => {
            firstWs.simulateClose()
        })

        expect(MockWebSocket.instances).toHaveLength(1)

        act(() => {
            vi.advanceTimersByTime(2000)
        })

        expect(MockWebSocket.instances).toHaveLength(2)
    })

    it('should use increasing delays for each reconnection attempt', async () => {
        const useWebSocket = await getHook()
        renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        // First connection
        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        // Close #1 -> reconnect after 2000ms (2000*2^0)
        act(() => {
            MockWebSocket.instances[0].simulateClose()
        })
        act(() => {
            vi.advanceTimersByTime(2000)
        })
        expect(MockWebSocket.instances).toHaveLength(2)

        // Close #2 -> reconnect after 4000ms (2000*2^1)
        act(() => {
            MockWebSocket.instances[1].simulateClose()
        })
        act(() => {
            vi.advanceTimersByTime(3999)
        })
        expect(MockWebSocket.instances).toHaveLength(2)
        act(() => {
            vi.advanceTimersByTime(1)
        })
        expect(MockWebSocket.instances).toHaveLength(3)
    })

    it('should stop reconnecting after maxReconnectAttempts (5)', async () => {
        const useWebSocket = await getHook()
        renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        // Close 5 times to exhaust attempts
        for (let i = 0; i < 5; i++) {
            act(() => {
                MockWebSocket.instances[MockWebSocket.instances.length - 1].simulateClose()
            })
            act(() => {
                vi.advanceTimersByTime(30000) // enough for any backoff
            })
        }

        const countAfterExhaustion = MockWebSocket.instances.length

        // Close once more -- should NOT reconnect
        act(() => {
            MockWebSocket.instances[MockWebSocket.instances.length - 1].simulateClose()
        })
        act(() => {
            vi.advanceTimersByTime(60000)
        })

        expect(MockWebSocket.instances).toHaveLength(countAfterExhaustion)
    })

    it('should reset reconnect attempts on successful connection', async () => {
        const useWebSocket = await getHook()
        renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        // Close twice
        act(() => { MockWebSocket.instances[0].simulateClose() })
        act(() => { vi.advanceTimersByTime(2000) })
        act(() => { MockWebSocket.instances[1].simulateClose() })
        act(() => { vi.advanceTimersByTime(4000) })

        // Third connection succeeds - this should reset counter
        act(() => { MockWebSocket.instances[2].simulateOpen() })

        // Now close again - delay should be back to 2000ms (attempt 0)
        act(() => { MockWebSocket.instances[2].simulateClose() })
        const countBefore = MockWebSocket.instances.length
        act(() => { vi.advanceTimersByTime(2000) })
        expect(MockWebSocket.instances).toHaveLength(countBefore + 1)
    })

    it('should close WebSocket on error', async () => {
        const useWebSocket = await getHook()
        renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateError()
        })

        expect(MockWebSocket.instances[0].close).toHaveBeenCalled()
    })

    it('should disconnect and clean up on manual disconnect', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        act(() => {
            result.current.disconnect()
        })

        expect(result.current.isConnected).toBe(false)
        expect(MockWebSocket.instances[0].close).toHaveBeenCalled()
    })

    it('should clean up on unmount', async () => {
        const useWebSocket = await getHook()
        const { unmount } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        act(() => {
            MockWebSocket.instances[0].simulateOpen()
        })

        unmount()

        expect(MockWebSocket.instances[0].close).toHaveBeenCalled()
    })

    it('should expose a reconnect function', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )

        expect(typeof result.current.reconnect).toBe('function')
    })

    it('should return null as initial lastMessage', async () => {
        const useWebSocket = await getHook()
        const { result } = renderHook(
            () => useWebSocket({ url: 'ws://localhost:6001', enabled: true }),
            { wrapper: createWrapper() },
        )
        expect(result.current.lastMessage).toBeNull()
    })
})
