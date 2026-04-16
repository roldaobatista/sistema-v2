import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fixedAssetsApi } from '@/lib/fixed-assets-api'
import type {
    DisposeAssetPayload,
    FixedAssetFilters,
    FixedAssetInventoryPayload,
    FixedAssetMovementPayload,
    FixedAssetPayload,
    RunDepreciationPayload,
} from '@/types/fixed-assets'

const fixedAssetQueryKeys = {
    all: ['fixed-assets'] as const,
    list: (filters: FixedAssetFilters) => ['fixed-assets', 'list', filters] as const,
    dashboard: ['fixed-assets', 'dashboard'] as const,
    depreciationLogs: (assetId: number, perPage: number) => ['fixed-assets', 'depreciation-logs', assetId, perPage] as const,
    movements: (assetId: number | null, perPage: number) => ['fixed-assets', 'movements', assetId, perPage] as const,
    inventories: (assetId: number | null, perPage: number) => ['fixed-assets', 'inventories', assetId, perPage] as const,
}

export function useFixedAssets(filters: FixedAssetFilters) {
    return useQuery({
        queryKey: fixedAssetQueryKeys.list(filters),
        queryFn: () => fixedAssetsApi.list(filters),
    })
}

export function useFixedAssetsDashboard() {
    return useQuery({
        queryKey: fixedAssetQueryKeys.dashboard,
        queryFn: () => fixedAssetsApi.dashboard(),
    })
}

export function useDepreciationLogs(assetId: number | null, perPage = 15) {
    return useQuery({
        queryKey: fixedAssetQueryKeys.depreciationLogs(assetId ?? 0, perPage),
        queryFn: () => fixedAssetsApi.depreciationLogs(assetId ?? 0, perPage),
        enabled: assetId !== null,
    })
}

export function useFixedAssetMovements(assetId: number | null, perPage = 15) {
    return useQuery({
        queryKey: fixedAssetQueryKeys.movements(assetId, perPage),
        queryFn: () => fixedAssetsApi.listMovements(assetId ?? undefined, perPage),
    })
}

export function useFixedAssetInventories(assetId: number | null, perPage = 15) {
    return useQuery({
        queryKey: fixedAssetQueryKeys.inventories(assetId, perPage),
        queryFn: () => fixedAssetsApi.listInventories(assetId ?? undefined, perPage),
    })
}

function useInvalidateFixedAssets() {
    const queryClient = useQueryClient()

    return () => {
        void queryClient.invalidateQueries({ queryKey: fixedAssetQueryKeys.all })
    }
}

export function useCreateFixedAsset() {
    const invalidate = useInvalidateFixedAssets()

    return useMutation({
        mutationFn: (payload: FixedAssetPayload) => fixedAssetsApi.create(payload),
        onSuccess: invalidate,
    })
}

export function useUpdateFixedAsset() {
    const invalidate = useInvalidateFixedAssets()

    return useMutation({
        mutationFn: ({ assetId, payload }: { assetId: number; payload: Partial<FixedAssetPayload> & { status?: string } }) =>
            fixedAssetsApi.update(assetId, payload),
        onSuccess: invalidate,
    })
}

export function useSuspendAsset() {
    const invalidate = useInvalidateFixedAssets()

    return useMutation({
        mutationFn: (assetId: number) => fixedAssetsApi.suspend(assetId),
        onSuccess: invalidate,
    })
}

export function useReactivateAsset() {
    const invalidate = useInvalidateFixedAssets()

    return useMutation({
        mutationFn: (assetId: number) => fixedAssetsApi.reactivate(assetId),
        onSuccess: invalidate,
    })
}

export function useDisposeAsset() {
    const invalidate = useInvalidateFixedAssets()

    return useMutation({
        mutationFn: ({ assetId, payload }: { assetId: number; payload: DisposeAssetPayload }) =>
            fixedAssetsApi.dispose(assetId, payload),
        onSuccess: invalidate,
    })
}

export function useRunMonthlyDepreciation() {
    const invalidate = useInvalidateFixedAssets()

    return useMutation({
        mutationFn: (payload: RunDepreciationPayload) => fixedAssetsApi.runDepreciation(payload),
        onSuccess: invalidate,
    })
}

export function useCreateFixedAssetMovement() {
    const invalidate = useInvalidateFixedAssets()

    return useMutation({
        mutationFn: ({ assetId, payload }: { assetId: number; payload: FixedAssetMovementPayload }) =>
            fixedAssetsApi.createMovement(assetId, payload),
        onSuccess: invalidate,
    })
}

export function useCreateFixedAssetInventory() {
    const invalidate = useInvalidateFixedAssets()

    return useMutation({
        mutationFn: ({ assetId, payload }: { assetId: number; payload: FixedAssetInventoryPayload }) =>
            fixedAssetsApi.createInventory(assetId, payload),
        onSuccess: invalidate,
    })
}
