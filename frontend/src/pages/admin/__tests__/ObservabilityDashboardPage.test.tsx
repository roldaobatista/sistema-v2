import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { render } from '@/__tests__/test-utils'
import api from '@/lib/api'
import { ObservabilityDashboardPage } from '../ObservabilityDashboardPage'

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

    return {
        ...actual,
        default: {
            ...actual.default,
            get: vi.fn(),
        },
    }
})

describe('ObservabilityDashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        vi.mocked(api.get).mockResolvedValue({
            data: {
                data: {
                    summary: {
                        status: 'critical',
                        active_alerts: 2,
                        tracked_endpoints: 3,
                    },
                    health: {
                        status: 'degraded',
                        timestamp: '2026-03-27T12:00:00Z',
                        checks: {
                            mysql: { ok: true, version: '8.0.36' },
                            redis: { ok: true },
                            queue: { ok: false, pending_jobs: 1500, failed_jobs: 12, error: 'Unavailable' },
                            disk: { ok: true, used_percent: 74.2, free_gb: 128.4 },
                            reverb: { ok: true, host: '127.0.0.1', port: 8080 },
                        },
                    },
                    metrics: [
                        { path: '/api/v1/orders', method: 'GET', count: 320, p50_ms: 40, p95_ms: 1800, p99_ms: 2400, error_rate: 1.2, last_seen_at: '2026-03-27T12:03:00Z' },
                        { path: '/api/v1/observability/dashboard', method: 'GET', count: 28, p50_ms: 55, p95_ms: 90, p99_ms: 120, error_rate: 0, last_seen_at: '2026-03-27T12:02:00Z' },
                    ],
                    alerts: [
                        { level: 'critical', type: 'queue', message: 'Fila default acima do threshold de 1000 jobs.', value: 1500 },
                        { level: 'critical', type: 'latency', message: 'Latencia acima de 2000ms detectada.', value: 2400, path: '/api/v1/orders' },
                    ],
                    history: [
                        { id: 10, status: 'critical', alerts_count: 2, captured_at: '2026-03-27T12:00:00Z' },
                        { id: 9, status: 'healthy', alerts_count: 0, captured_at: '2026-03-27T11:55:00Z' },
                    ],
                    links: {
                        horizon: '/horizon',
                        pulse: '/pulse',
                        jaeger: 'http://localhost:16686',
                    },
                },
            },
        } as never)
    })

    it('renderiza cards, checks e links operacionais', async () => {
        render(<ObservabilityDashboardPage />)

        await waitFor(() => {
            expect(screen.getByText('Observabilidade e Monitoramento')).toBeInTheDocument()
            expect(screen.getByText('Alertas Ativos')).toBeInTheDocument()
            expect(screen.getByText('Saúde dos Serviços')).toBeInTheDocument()
            expect(screen.getByText('Horizon')).toBeInTheDocument()
            expect(screen.getByText('Pulse')).toBeInTheDocument()
            expect(screen.getByText('Jaeger')).toBeInTheDocument()
        })

        expect(screen.getAllByText(/critical/i).length).toBeGreaterThan(0)
        expect(screen.getByText('mysql')).toBeInTheDocument()
        expect(screen.getAllByText('queue').length).toBeGreaterThan(0)
    })

    it('filtra métricas por endpoint', async () => {
        render(<ObservabilityDashboardPage />)

        const input = await screen.findByRole('searchbox', { name: /filtrar métricas por endpoint/i })
        await userEvent.type(input, 'orders')

        await waitFor(() => {
            expect(screen.getAllByText('/api/v1/orders').length).toBeGreaterThan(0)
            expect(screen.queryByText('/api/v1/observability/dashboard')).not.toBeInTheDocument()
        })
    })
})
