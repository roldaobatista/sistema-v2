export type RefreshStrategy = 'manual' | 'hourly' | 'daily' | 'weekly'
export type ExportJobStatus = 'pending' | 'running' | 'completed' | 'failed' | 'cancelled'
export type DashboardProvider = 'metabase' | 'power_bi' | 'custom_url'

export interface PaginationMeta {
    current_page: number
    per_page: number
    total: number
    last_page?: number
    from?: number | null
    to?: number | null
}

export interface PaginatedResult<T> {
    data: T[]
    meta: PaginationMeta
}

export interface AnalyticsDatasetItem {
    id: number
    name: string
    description?: string | null
    refresh_strategy: RefreshStrategy
    is_active: boolean
    cache_ttl_minutes?: number
    source_modules?: string[]
    last_refreshed_at?: string | null
}

export interface DataExportJobItem {
    id: number
    name: string
    status: ExportJobStatus
    output_format: 'csv' | 'xlsx' | 'json'
    rows_exported?: number | null
    completed_at?: string | null
    output_path?: string | null
}

export interface EmbeddedDashboardItem {
    id: number
    name: string
    provider: DashboardProvider
    embed_url: string
    is_active: boolean
    display_order?: number
}
