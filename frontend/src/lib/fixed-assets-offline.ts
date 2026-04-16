import {
    listOfflineFixedAssetInventories,
    markOfflineFixedAssetInventorySync,
    saveOfflineFixedAssetInventory,
} from '@/lib/offlineDb'
import { offlinePost } from '@/lib/syncEngine'
import type { FixedAssetInventoryPayload } from '@/types/fixed-assets'

export async function queueFixedAssetInventoryCount(assetId: number, payload: FixedAssetInventoryPayload): Promise<{ queuedOffline: boolean; offlineId: string | null }> {
    const offlineId = await saveOfflineFixedAssetInventory({
        asset_id: assetId,
        inventory_date: payload.inventory_date,
        counted_location: payload.counted_location ?? null,
        counted_status: payload.counted_status ?? null,
        condition_ok: payload.condition_ok ?? true,
        notes: payload.notes ?? null,
    })

    const queuedOffline = await offlinePost(`/fixed-assets/${assetId}/inventories`, {
        ...payload,
        offline_reference: offlineId,
        synced_from_pwa: true,
    })

    if (!queuedOffline) {
        await markOfflineFixedAssetInventorySync(offlineId, {
            synced: true,
            sync_error: null,
        })
    }

    return { queuedOffline, offlineId }
}

export async function getOfflineFixedAssetInventoryQueueCount(): Promise<number> {
    const records = await listOfflineFixedAssetInventories()
    return records.filter((record) => !record.synced).length
}
