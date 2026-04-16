import api from '@/lib/api'
import type {
    DisposeAssetPayload,
    FixedAsset,
    FixedAssetFilters,
    FixedAssetInventory,
    FixedAssetInventoryPayload,
    FixedAssetMovement,
    FixedAssetMovementPayload,
    FixedAssetPayload,
    FixedAssetsDashboard,
    FixedAssetDepreciationLog,
    PaginatedEnvelope,
    RunDepreciationPayload,
} from '@/types/fixed-assets'

function unwrapData<T>(payload: unknown): T {
    if (payload && typeof payload === 'object' && 'data' in payload) {
        return (payload as { data: T }).data
    }

    return payload as T
}

export const fixedAssetsApi = {
    async list(filters: FixedAssetFilters = {}): Promise<PaginatedEnvelope<FixedAsset>> {
        const response = await api.get('/fixed-assets', { params: filters })
        const data = response.data as FixedAsset[] & { meta?: PaginatedEnvelope<FixedAsset>['meta'] }

        return {
            data,
            meta: data.meta,
        }
    },

    async create(payload: FixedAssetPayload): Promise<FixedAsset> {
        const response = await api.post('/fixed-assets', payload)
        return unwrapData<FixedAsset>(response.data)
    },

    async update(assetId: number, payload: Partial<FixedAssetPayload> & { status?: string }): Promise<FixedAsset> {
        const response = await api.put(`/fixed-assets/${assetId}`, payload)
        return unwrapData<FixedAsset>(response.data)
    },

    async dashboard(): Promise<FixedAssetsDashboard> {
        const response = await api.get('/fixed-assets/dashboard')
        return unwrapData<FixedAssetsDashboard>(response.data)
    },

    async suspend(assetId: number): Promise<FixedAsset> {
        const response = await api.post(`/fixed-assets/${assetId}/suspend`)
        return unwrapData<FixedAsset>(response.data)
    },

    async reactivate(assetId: number): Promise<FixedAsset> {
        const response = await api.post(`/fixed-assets/${assetId}/reactivate`)
        return unwrapData<FixedAsset>(response.data)
    },

    async dispose(assetId: number, payload: DisposeAssetPayload): Promise<FixedAsset> {
        const response = await api.post(`/fixed-assets/${assetId}/dispose`, payload)
        return unwrapData<FixedAsset>(response.data)
    },

    async runDepreciation(payload: RunDepreciationPayload): Promise<{
        reference_month: string
        processed_assets: number
        skipped_assets: number
    }> {
        const response = await api.post('/fixed-assets/run-depreciation', payload)
        return unwrapData(response.data)
    },

    async depreciationLogs(assetId: number, perPage = 15): Promise<PaginatedEnvelope<FixedAssetDepreciationLog>> {
        const response = await api.get(`/fixed-assets/${assetId}/depreciation-logs`, {
            params: { per_page: perPage },
        })
        const data = response.data as FixedAssetDepreciationLog[] & { meta?: PaginatedEnvelope<FixedAssetDepreciationLog>['meta'] }

        return {
            data,
            meta: data.meta,
        }
    },

    async listMovements(assetId?: number, perPage = 15): Promise<PaginatedEnvelope<FixedAssetMovement>> {
        const response = await api.get('/fixed-assets/movements', {
            params: { asset_record_id: assetId, per_page: perPage },
        })
        const data = response.data as FixedAssetMovement[] & { meta?: PaginatedEnvelope<FixedAssetMovement>['meta'] }

        return {
            data,
            meta: data.meta,
        }
    },

    async createMovement(assetId: number, payload: FixedAssetMovementPayload): Promise<FixedAssetMovement> {
        const response = await api.post(`/fixed-assets/${assetId}/movements`, payload)
        return unwrapData<FixedAssetMovement>(response.data)
    },

    async listInventories(assetId?: number, perPage = 15): Promise<PaginatedEnvelope<FixedAssetInventory>> {
        const response = await api.get('/fixed-assets/inventories', {
            params: { asset_record_id: assetId, per_page: perPage },
        })
        const data = response.data as FixedAssetInventory[] & { meta?: PaginatedEnvelope<FixedAssetInventory>['meta'] }

        return {
            data,
            meta: data.meta,
        }
    },

    async createInventory(assetId: number, payload: FixedAssetInventoryPayload): Promise<FixedAssetInventory> {
        const response = await api.post(`/fixed-assets/${assetId}/inventories`, payload)
        return unwrapData<FixedAssetInventory>(response.data)
    },
}
