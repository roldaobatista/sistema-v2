import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

describe('offline sync engine', () => {
    beforeEach(() => {
        vi.resetModules()
    })

    afterEach(() => {
        vi.restoreAllMocks()
        vi.unstubAllGlobals()
    })

    it('reports failed queued requests through telemetry instead of production console logging', async () => {
        const apiError = { response: { status: 500 }, message: 'Network unavailable' }
        const captureError = vi.fn()
        const deleteRequest = vi.fn()
        const updateRequestStatus = vi.fn().mockResolvedValue(undefined)
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})

        vi.doMock('@/lib/api', () => ({
            default: {
                request: vi.fn().mockRejectedValue(apiError),
            },
        }))

        vi.doMock('@/lib/sentry', () => ({
            captureError,
        }))

        vi.doMock('@/lib/offline/indexedDB', () => ({
            getPendingRequests: vi.fn().mockResolvedValue([
                {
                    id: 123,
                    uuid: 'offline-request-123',
                    url: '/work-orders/1',
                    method: 'POST',
                    data: { status: 'started' },
                    headers: {},
                    timestamp: 1,
                    localTimestamp: '2026-04-13T00:00:00.000Z',
                    status: 'pending',
                    attempts: 0,
                },
            ]),
            deleteRequest,
            updateRequestStatus,
        }))

        vi.doMock('sonner', () => ({
            toast: {
                info: vi.fn(),
                success: vi.fn(),
                error: vi.fn(),
            },
        }))

        const { syncOfflineQueue } = await import('@/lib/offline/syncEngine')

        await syncOfflineQueue()

        expect(captureError).toHaveBeenCalledWith(
            apiError,
            expect.objectContaining({
                context: 'offline.syncOfflineQueue',
                requestId: 123,
                method: 'POST',
                url: '/work-orders/1',
            }),
        )
        expect(consoleError).not.toHaveBeenCalled()
        expect(updateRequestStatus).toHaveBeenCalledWith(123, 'syncing', 1)
        expect(updateRequestStatus).toHaveBeenCalledWith(123, 'pending')
        expect(deleteRequest).not.toHaveBeenCalled()
    })
})
