import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { AnalyticsOverview } from '@/pages/analytics/AnalyticsOverview'
import AutomationPage from '@/pages/automacao/AutomationPage'

const { mockApiGet } = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
  const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

  return {
    ...actual,
    default: {
      get: mockApiGet,
      post: vi.fn(),
      put: vi.fn(),
      patch: vi.fn(),
      delete: vi.fn(),
    },
    getApiErrorMessage: (_error: unknown, fallback: string) => fallback,
  }
})

vi.mock('@/components/charts', () => ({
  ChartCard: ({ title, children }: { title: string; children: ReactNode }) => (
    <section>
      <h3>{title}</h3>
      {children}
    </section>
  ),
  ChartCardSkeleton: () => <div>loading chart</div>,
  KpiCardSpark: ({ label, value }: { label: string; value: string | number }) => (
    <div>{`${label}:${String(value)}`}</div>
  ),
  TrendAreaChart: ({ data }: { data: Array<{ month: string }> }) => <div>{`trend:${data.length}`}</div>,
  DonutChart: ({ data }: { data: Array<{ name: string; value: number }> }) => <div>{`donut:${data.length}`}</div>,
}))

vi.mock('@/components/ui/pageheader', () => ({
  PageHeader: ({ title, subtitle }: { title: string; subtitle?: string }) => (
    <div>
      <h1>{title}</h1>
      {subtitle ? <p>{subtitle}</p> : null}
    </div>
  ),
}))

describe('contratos envelopados em analytics e automacao', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockApiGet.mockImplementation((url: string) => {
      if (url === '/analytics/executive-summary') {
        return Promise.resolve({
          data: {
            data: {
              operational: { total_os: 8, completion_rate: 62.5, total_service_calls: 3, sc_completed: 2 },
              financial: { total_received: 4500, total_overdue: 500, total_expenses: 1200 },
              commercial: { total_quotes: 5, conversion_rate: 40, new_customers: 2, quotes_value: 15000, total_active_customers: 12 },
              assets: {},
            },
          },
        })
      }

      if (url === '/analytics/trends') {
        return Promise.resolve({
          data: {
            data: {
              monthly: [{ month: 'Jan' }, { month: 'Fev' }],
              os_by_status: [
                { status: 'pending', total: 3 },
                { status: 'completed', total: 5 },
              ],
            },
          },
        })
      }

      if (url === '/automation/rules') {
        return Promise.resolve({
          data: {
            data: {
              data: [
                {
                  id: 1,
                  name: 'Regra de follow-up',
                  trigger_event: 'os.created',
                  action_type: 'send_notification',
                  is_active: true,
                  conditions: null,
                  execution_count: 7,
                  action_config: {},
                },
              ],
              total: 1,
              current_page: 1,
              last_page: 1,
            },
          },
        })
      }

      if (url === '/automation/webhooks') {
        return Promise.resolve({
          data: {
            data: {
              data: [
                {
                  id: 9,
                  name: 'Webhook ERP',
                  url: 'https://example.test/hook',
                  events: ['quote.approved'],
                  is_active: true,
                  last_triggered_at: null,
                },
              ],
              total: 1,
              current_page: 1,
              last_page: 1,
            },
          },
        })
      }

      if (url === '/automation/reports') {
        return Promise.resolve({
          data: {
            data: {
              data: [
                {
                  id: 3,
                  name: 'Relatório diário',
                  report_type: 'work-orders',
                  frequency: 'daily',
                  recipients: ['ops@kalibrium.test'],
                  is_active: true,
                  last_sent_at: null,
                },
              ],
              total: 1,
              current_page: 1,
              last_page: 1,
            },
          },
        })
      }

      if (url.startsWith('/lookups/')) {
        return Promise.resolve({ data: { data: [] } })
      }

      return Promise.resolve({ data: { data: [] } })
    })
  })

  it('AnalyticsOverview consome envelopes aninhados sem zerar os KPIs', async () => {
    render(<AnalyticsOverview from="2026-03-01" to="2026-03-31" />)

    expect(await screen.findByText('Ordens de Serviço:8')).toBeInTheDocument()
    expect(screen.getByText('Clientes Ativos')).toBeInTheDocument()
    expect(screen.getByText('donut:2')).toBeInTheDocument()
    expect(screen.getAllByText('trend:2').length).toBeGreaterThanOrEqual(3)
  })

  it('AutomationPage renderiza regras, webhooks e relatorios com payload envelopado', async () => {
    const user = userEvent.setup()

    render(<AutomationPage />)

    await user.click(screen.getByRole('button', { name: 'Minhas Regras' }))
    expect(await screen.findByText('Regra de follow-up')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Webhooks' }))
    expect(await screen.findByText('Webhook ERP')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Relatórios Agendados' }))
    expect(await screen.findByText('Relatório diário')).toBeInTheDocument()

    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledWith('/automation/reports', expect.any(Object))
    })
  })
})
