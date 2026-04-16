import type { ReactNode } from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { CommissionOverviewTab } from '@/pages/financeiro/commissions/CommissionOverviewTab'
import { CrmReportTab } from '@/pages/relatorios/tabs/CrmReportTab'
import { FinancialReportTab } from '@/pages/relatorios/tabs/FinancialReportTab'

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
    }
})

vi.mock('recharts', () => ({
    ResponsiveContainer: ({ children }: { children: ReactNode }) => <div data-testid="responsive">{children}</div>,
    BarChart: ({ children }: { children: ReactNode }) => <div data-testid="bar-chart">{children}</div>,
    PieChart: ({ children }: { children: ReactNode }) => <div data-testid="pie-chart">{children}</div>,
    CartesianGrid: () => <div data-testid="cartesian-grid" />,
    XAxis: () => <div data-testid="x-axis" />,
    YAxis: () => <div data-testid="y-axis" />,
    Tooltip: () => <div data-testid="tooltip" />,
    Legend: () => <div data-testid="legend" />,
    Bar: () => <div data-testid="bar" />,
    Pie: ({ data }: { data?: unknown[] }) => <div data-testid="pie" data-count={data?.length ?? 0} />,
    Cell: ({ fill }: { fill: string }) => <div data-testid="cell" data-fill={fill} />,
}))

vi.mock('@/components/charts/KpiCardSpark', () => ({
    KpiCardSpark: ({ label, value }: { label: string; value: string | number }) => (
        <div>{`${label}:${String(value)}`}</div>
    ),
}))

vi.mock('@/components/charts/ChartCard', () => ({
    ChartCard: ({ title, children }: { title: string; children: ReactNode }) => (
        <section>
            <h3>{title}</h3>
            {children}
        </section>
    ),
}))

vi.mock('@/components/charts/DonutChart', () => ({
    DonutChart: ({
        data,
        centerLabel,
        centerValue,
    }: {
        data: Array<{ name: string; value: number }>
        centerLabel?: string
        centerValue?: string | number
    }) => <div>{`donut:${data.length}:${centerLabel ?? ''}:${String(centerValue ?? '')}`}</div>,
}))

vi.mock('@/components/charts/FunnelChart', () => ({
    FunnelChart: ({ data }: { data: Array<{ name: string; value: number }> }) => <div>{`funnel:${data.length}`}</div>,
}))

vi.mock('@/components/charts/TrendAreaChart', () => ({
    TrendAreaChart: ({
        data,
        series,
    }: {
        data: Array<Record<string, string | number>>
        series: Array<{ key: string; label: string }>
    }) => <div>{`trend:${data.length}:${series.map((item) => item.key).join(',')}`}</div>,
}))

const escapeRegExp = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
const currencyTextMatcher = (value: number) => new RegExp(escapeRegExp(value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })).replace(/\s+/g, '\\s*'))

describe('CommissionOverviewTab', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockApiGet.mockImplementation((url: string) => {
            switch (url) {
                case '/commission-dashboard/overview':
                    return Promise.resolve({
                        data: {
                            data: {
                                paid_this_month: 1500,
                                pending: 300,
                                approved: 450,
                                events_count: 12,
                                variation_pct: 10,
                            },
                        },
                    })
                case '/commission-dashboard/ranking':
                    return Promise.resolve({
                        data: {
                            data: [
                                { id: 1, position: 1, medal: '🥇', name: 'Ana', events_count: 4, total: 800 },
                            ],
                        },
                    })
                case '/commission-dashboard/evolution':
                    return Promise.resolve({
                        data: {
                            data: [
                                { period: '2026-01', label: 'Jan', total: 400 },
                                { period: '2026-02', label: 'Fev', total: 700 },
                            ],
                        },
                    })
                case '/commission-dashboard/by-rule':
                    return Promise.resolve({
                        data: {
                            data: [
                                { calculation_type: 'fixed_value', total: 1000, count: 2 },
                            ],
                        },
                    })
                case '/commission-dashboard/by-role':
                    return Promise.resolve({
                        data: {
                            data: [
                                { role: 'seller', total: 900, count: 2 },
                            ],
                        },
                    })
                default:
                    return Promise.resolve({ data: { data: [] } })
            }
        })
    })

    it('renderiza os cards e o ranking usando payload envelopado', async () => {
        const onNavigateTab = vi.fn()

        render(<CommissionOverviewTab onNavigateTab={onNavigateTab} />)

        expect(await screen.findByText('Pago (Mês)')).toBeInTheDocument()
        await waitFor(() => {
            expect(screen.getByText(currencyTextMatcher(1500))).toBeInTheDocument()
        })
        expect(screen.getByText('Pendente')).toBeInTheDocument()
        expect(screen.getByText('Aprovado')).toBeInTheDocument()
        expect(screen.getByText('Eventos no Mês')).toBeInTheDocument()
        expect(screen.getByText('Ana')).toBeInTheDocument()
    })
})

describe('CrmReportTab', () => {
    it('normaliza os dados do relatório CRM para os cards e gráficos', () => {
        render(
            <CrmReportTab
                data={{
                    total_deals: 12,
                    won_deals: 4,
                    conversion_rate: 33.3,
                    revenue: 12500,
                    avg_deal_value: 3125,
                    health_distribution: [
                        { range: 'Saudavel', count: 8 },
                        { range: 'Critico', count: 4 },
                    ],
                    deals_by_status: [
                        { status: 'lead', count: 5 },
                        { status: 'proposal', count: 3 },
                        { status: 'won', count: 4 },
                    ],
                    deals_by_seller: [
                        { owner_name: 'Carla', count: 3, value: 7200 },
                        { owner_name: 'Bruno', count: 2, value: 5300 },
                    ],
                }}
            />
        )

        expect(screen.getByText('Deals:12')).toBeInTheDocument()
        expect(screen.getByText(new RegExp(`Receita Won:${currencyTextMatcher(12500).source}`))).toBeInTheDocument()
        expect(screen.getByText('Conversão:33.3%')).toBeInTheDocument()
        expect(screen.getByText(new RegExp(`Ticket Médio:${currencyTextMatcher(3125).source}`))).toBeInTheDocument()
        expect(screen.getByText('funnel:3')).toBeInTheDocument()
        expect(screen.getByText('donut:2:Clientes:12')).toBeInTheDocument()
        expect(screen.getByText('Deals por Vendedor')).toBeInTheDocument()
    })
})

describe('FinancialReportTab', () => {
    it('converte o fluxo mensal para o contrato do gráfico sem cast inseguro', () => {
        render(
            <FinancialReportTab
                data={{
                    receivable: { total: 15000, total_paid: 9000, overdue: 1200 },
                    payable: { total: 3500 },
                    expenses_by_category: [
                        { category: 'Operacional', total: 2200 },
                        { category: 'Fiscal', total: 1300 },
                    ],
                    monthly_flow: [
                        { period: 'Jan', income: 5000, expense: 1200, balance: 3800 },
                        { period: 'Fev', income: 7000, expense: 1800, balance: 5200 },
                    ],
                }}
            />
        )

        expect(screen.getByText(new RegExp(`Receita:${currencyTextMatcher(15000).source}`))).toBeInTheDocument()
        expect(screen.getByText(new RegExp(`Recebido:${currencyTextMatcher(9000).source}`))).toBeInTheDocument()
        expect(screen.getByText(new RegExp(`Despesas \\(AP\\):${currencyTextMatcher(3500).source}`))).toBeInTheDocument()
        expect(screen.getByText(new RegExp(`Inadimplência:${currencyTextMatcher(1200).source}`))).toBeInTheDocument()
        expect(screen.getByText('trend:2:receita,despesa,saldo')).toBeInTheDocument()
        expect(screen.getByText(new RegExp(`donut:2:Total:${currencyTextMatcher(3500).source}`))).toBeInTheDocument()
    })
})
