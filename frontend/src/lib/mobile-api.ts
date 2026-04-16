import api from './api'
import type {
    MobilePreferences,
    InteractiveNotification,
    PrintJob,
    ThermalReading,
    OfflineMapRegion,
} from '@/types/mobile'

export const mobileApi = {
    preferences: {
        get: () => api.get<{ data: MobilePreferences }>('/mobile/preferences'),
        update: (data: Partial<MobilePreferences>) => api.put('/mobile/preferences', data),
    },
    syncQueue: {
        list: () => api.get('/mobile/sync-queue'),
        add: (data: Record<string, unknown>) => api.post('/mobile/sync-queue', data),
    },
    notifications: {
        interactive: () => api.get<{ data: InteractiveNotification[] }>('/mobile/notifications'),
        respond: (id: string, data: { action: string }) =>
            api.post(`/mobile/notifications/${id}/respond`, data),
    },
    signature: {
        store: (data: FormData) =>
            api.post('/mobile/signatures', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    },
    barcode: {
        lookup: (code: string) => api.get('/mobile/barcode-lookup', { params: { code } }),
    },
    voiceReport: {
        store: (data: FormData) =>
            api.post('/mobile/voice-reports', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    },
    photoAnnotation: {
        store: (data: FormData) =>
            api.post('/mobile/photo-annotations', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    },
    thermalReadings: {
        store: (data: ThermalReading) => api.post('/mobile/thermal-readings', data),
    },
    kioskConfig: {
        get: () => api.get('/mobile/kiosk-config'),
        update: (data: Record<string, unknown>) => api.put('/mobile/kiosk-config', data),
    },
    offlineMapRegions: {
        list: () => api.get<{ data: OfflineMapRegion[] }>('/mobile/offline-map-regions'),
    },
    printJobs: {
        list: () => api.get<{ data: PrintJob[] }>('/mobile/print-jobs'),
        create: (data: Record<string, unknown>) => api.post('/mobile/print-jobs', data),
    },
    biometric: {
        config: () => api.get('/mobile/biometric-config'),
        update: (data: Record<string, unknown>) => api.put('/mobile/biometric-config', data),
    },
}
