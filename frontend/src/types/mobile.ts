export interface MobilePreferences {
    offline_mode: boolean
    theme: 'light' | 'dark' | 'system'
    notifications_enabled: boolean
    auto_sync: boolean
    sync_interval_minutes: number
}

export interface InteractiveNotification {
    id: string
    type: string
    title: string
    body: string
    actions: Array<{ label: string; value: string }>
    created_at: string
    responded_at?: string | null
}

export interface PrintJob {
    id: number
    type: string
    status: 'pending' | 'printing' | 'completed' | 'failed'
    payload: Record<string, unknown>
    created_at: string
}

export interface ThermalReading {
    work_order_id: number
    equipment_id: number
    temperature: number
    unit: 'celsius' | 'fahrenheit'
    photo_path?: string
    notes?: string
}

export interface OfflineMapRegion {
    id: number
    name: string
    bounds: { north: number; south: number; east: number; west: number }
    zoom_levels: [number, number]
}

export interface VoiceReport {
    id: number
    work_order_id: number
    audio_path: string
    duration_seconds: number
    transcription?: string | null
    created_at: string
}

export interface PhotoAnnotation {
    id: number
    work_order_id: number
    photo_path: string
    annotations: Array<{ x: number; y: number; text: string }>
    created_at: string
}
