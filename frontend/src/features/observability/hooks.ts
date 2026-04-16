import { useQuery } from '@tanstack/react-query'
import { getObservabilityDashboard } from './api'

export function useObservabilityDashboardQuery() {
    return useQuery({
        queryKey: ['observability-dashboard'],
        queryFn: getObservabilityDashboard,
        refetchInterval: 60000,
    })
}
