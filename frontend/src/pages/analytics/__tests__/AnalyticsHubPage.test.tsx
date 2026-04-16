import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { render } from '@/__tests__/test-utils'
import api from '@/lib/api'
import { AnalyticsHubPage } from '../AnalyticsHubPage'

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

    return {
        ...actual,
        default: {
            ...actual.default,
            get: vi.fn(),
            post: vi.fn(),
        },
    }
})

describe('AnalyticsHubPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        vi.mocked(api.get).mockImplementation((url) => {
            if (url === '/analytics/datasets') {
                return Promise.resolve({
                    data: {
                        data: [
                            { id: 1, name: 'OS por tecnico', refresh_strategy: 'daily', is_active: true },
                        ],
                        meta: { current_page: 1, per_page: 15, total: 1 },
                    },
                } as never)
            }

            if (url === '/analytics/export-jobs') {
                return Promise.resolve({
                    data: {
                        data: [
                            { id: 9, name: 'Exportacao mensal', status: 'completed', output_format: 'json' },
                        ],
                        meta: { current_page: 1, per_page: 15, total: 1 },
                    },
                } as never)
            }

            if (url === '/analytics/dashboards') {
                return Promise.resolve({
                    data: {
                        data: [
                            { id: 7, name: 'Financeiro Q1', provider: 'metabase', embed_url: 'https://example.com/embed', is_active: true },
                        ],
                        meta: { current_page: 1, per_page: 15, total: 1 },
                    },
                } as never)
            }

            return Promise.resolve({
                data: {
                    data: [],
                },
            } as never)
        })
    })

    it('renderiza as novas abas de BI e carrega dados principais', async () => {
        render(<AnalyticsHubPage />)

        await waitFor(() => {
            expect(screen.getByText('Analytics Hub')).toBeInTheDocument()
            expect(screen.getByRole('tab', { name: /datasets/i })).toBeInTheDocument()
            expect(screen.getByRole('tab', { name: /exportações/i })).toBeInTheDocument()
            expect(screen.getByRole('tab', { name: /dashboards/i })).toBeInTheDocument()
        })
    })
})
