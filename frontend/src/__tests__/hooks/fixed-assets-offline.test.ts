import { beforeEach, describe, expect, it, vi } from 'vitest'

const offlinePostMock = vi.fn()
const saveOfflineMock = vi.fn()
const listOfflineMock = vi.fn()
const markInventorySyncMock = vi.fn()

vi.mock('@/lib/syncEngine', () => ({
    offlinePost: offlinePostMock,
}))

vi.mock('@/lib/offlineDb', () => ({
    saveOfflineFixedAssetInventory: saveOfflineMock,
    listOfflineFixedAssetInventories: listOfflineMock,
    markOfflineFixedAssetInventorySync: markInventorySyncMock,
}))

describe('fixed-assets-offline', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('queues inventory count with local reference', async () => {
        saveOfflineMock.mockResolvedValue('offline-123')
        offlinePostMock.mockResolvedValue(true)

        const mod = await import('@/lib/fixed-assets-offline')
        const result = await mod.queueFixedAssetInventoryCount(10, {
            inventory_date: '2026-03-27',
            counted_location: 'Campo',
            counted_status: 'active',
            condition_ok: true,
            notes: 'Conferido',
        })

        expect(offlinePostMock).toHaveBeenCalledWith('/fixed-assets/10/inventories', expect.objectContaining({
            offline_reference: 'offline-123',
            synced_from_pwa: true,
        }))
        expect(markInventorySyncMock).not.toHaveBeenCalled()
        expect(result).toEqual({ queuedOffline: true, offlineId: 'offline-123' })
    })

    it('marks local inventory as synced when online post succeeds immediately', async () => {
        saveOfflineMock.mockResolvedValue('offline-456')
        offlinePostMock.mockResolvedValue(false)

        const mod = await import('@/lib/fixed-assets-offline')
        const result = await mod.queueFixedAssetInventoryCount(33, {
            inventory_date: '2026-03-27',
            counted_location: 'Matriz',
            counted_status: 'active',
            condition_ok: true,
            notes: 'Sincronizado na hora',
        })

        expect(markInventorySyncMock).toHaveBeenCalledWith('offline-456', {
            synced: true,
            sync_error: null,
        })
        expect(result).toEqual({ queuedOffline: false, offlineId: 'offline-456' })
    })

    it('counts pending offline inventories', async () => {
        listOfflineMock.mockResolvedValue([
            { id: '1', synced: false },
            { id: '2', synced: true },
            { id: '3', synced: false },
        ])

        const mod = await import('@/lib/fixed-assets-offline')
        await expect(mod.getOfflineFixedAssetInventoryQueueCount()).resolves.toBe(2)
    })
})
