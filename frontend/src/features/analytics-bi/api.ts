import api from '@/lib/api'
import type {
    AnalyticsDatasetItem,
    DataExportJobItem,
    EmbeddedDashboardItem,
    PaginatedResult,
    PaginationMeta,
} from './types'

type RawPaginatedResponse<T> = {
    data?: {
        data?: T[]
        meta?: Partial<PaginationMeta>
    }
}

function normalizePaginatedResponse<T>(payload: RawPaginatedResponse<T>): PaginatedResult<T> {
    return {
        data: payload.data?.data ?? [],
        meta: {
            current_page: payload.data?.meta?.current_page ?? 1,
            per_page: payload.data?.meta?.per_page ?? 15,
            total: payload.data?.meta?.total ?? 0,
            last_page: payload.data?.meta?.last_page,
            from: payload.data?.meta?.from,
            to: payload.data?.meta?.to,
        },
    }
}

export async function fetchAnalyticsDatasets(): Promise<PaginatedResult<AnalyticsDatasetItem>> {
    const response = await api.get<RawPaginatedResponse<AnalyticsDatasetItem>>('/analytics/datasets')
    return normalizePaginatedResponse(response.data)
}

export async function fetchDataExportJobs(): Promise<PaginatedResult<DataExportJobItem>> {
    const response = await api.get<RawPaginatedResponse<DataExportJobItem>>('/analytics/export-jobs')
    return normalizePaginatedResponse(response.data)
}

export async function fetchEmbeddedDashboards(): Promise<PaginatedResult<EmbeddedDashboardItem>> {
    const response = await api.get<RawPaginatedResponse<EmbeddedDashboardItem>>('/analytics/dashboards')
    return normalizePaginatedResponse(response.data)
}
