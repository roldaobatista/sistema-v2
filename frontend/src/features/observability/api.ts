import api, { unwrapData } from '@/lib/api'
import type { ObservabilityDashboard } from './types'

export async function getObservabilityDashboard(): Promise<ObservabilityDashboard> {
    const response = await api.get('/observability/dashboard')
    return unwrapData<ObservabilityDashboard>(response)
}
