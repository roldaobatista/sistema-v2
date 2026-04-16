import { useQuery } from '@tanstack/react-query'
import {
    fetchAnalyticsDatasets,
    fetchDataExportJobs,
    fetchEmbeddedDashboards,
} from './api'

export function useAnalyticsDatasets() {
    return useQuery({
        queryKey: ['analytics-bi', 'datasets'],
        queryFn: fetchAnalyticsDatasets,
    })
}

export function useDataExportJobs() {
    return useQuery({
        queryKey: ['analytics-bi', 'export-jobs'],
        queryFn: fetchDataExportJobs,
    })
}

export function useEmbeddedDashboards() {
    return useQuery({
        queryKey: ['analytics-bi', 'dashboards'],
        queryFn: fetchEmbeddedDashboards,
    })
}
