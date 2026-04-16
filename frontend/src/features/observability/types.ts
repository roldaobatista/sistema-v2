export interface ObservabilitySummary {
    status: string
    active_alerts: number
    tracked_endpoints: number
}

export interface ObservabilityCheck {
    ok: boolean
    error?: string
    version?: string | null
    pending_jobs?: number
    failed_jobs?: number
    used_percent?: number
    free_gb?: number
    host?: string
    port?: number
}

export interface ObservabilityHealth {
    status: string
    timestamp: string
    checks: Record<string, ObservabilityCheck>
}

export interface ObservabilityMetric {
    path: string
    method: string
    count: number
    p50_ms: number
    p95_ms: number
    p99_ms: number
    error_rate: number
    last_seen_at?: string | null
}

export interface ObservabilityAlert {
    level: string
    type: string
    message: string
    value?: number
    path?: string
}

export interface ObservabilityHistoryItem {
    id: number
    status: string
    alerts_count: number
    captured_at: string | null
}

export interface ObservabilityLinks {
    horizon: string
    pulse: string
    jaeger: string
}

export interface ObservabilityDashboard {
    summary: ObservabilitySummary
    health: ObservabilityHealth
    metrics: ObservabilityMetric[]
    alerts: ObservabilityAlert[]
    history: ObservabilityHistoryItem[]
    links: ObservabilityLinks
}
