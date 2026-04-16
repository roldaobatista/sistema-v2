import { lazy, Suspense, useState, type ReactNode } from 'react'
import {
    BarChart3,
    LayoutDashboard,
    BrainCircuit,
    Database,
    Download,
    MonitorPlay,
    RefreshCw,
    CheckCircle2,
    AlertTriangle,
    Clock3,
} from 'lucide-react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PdfExportButton } from '@/components/analytics/PdfExportButton'
import {
    useAnalyticsDatasets,
    useDataExportJobs,
    useEmbeddedDashboards,
} from '@/features/analytics-bi/hooks'
import type {
    AnalyticsDatasetItem,
    DataExportJobItem,
    EmbeddedDashboardItem,
} from '@/features/analytics-bi/types'

const AnalyticsOverview = lazy(async () => {
    const module = await import('./AnalyticsOverview')
    return { default: module.AnalyticsOverview }
})

const PredictiveAnalytics = lazy(async () => {
    const module = await import('./PredictiveAnalytics')
    return { default: module.PredictiveAnalytics }
})

function getDefaultDateRange() {
    const now = new Date()
    const from = new Date(now.getFullYear(), now.getMonth(), 1)
    const to = new Date(now.getFullYear(), now.getMonth() + 1, 0)
    return {
        from: from.toISOString().split('T')[0],
        to: to.toISOString().split('T')[0],
    }
}

export function AnalyticsHubPage() {
    const defaults = getDefaultDateRange()
    const [from, setFrom] = useState(defaults.from)
    const [to, setTo] = useState(defaults.to)
    const [currentTab, setCurrentTab] = useState('overview')

    return (
        <div className="space-y-6" id="analytics-hub-container">
            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between no-print">
                <div>
                    <h1 className="text-2xl font-bold text-surface-900 flex items-center gap-2">
                        <BarChart3 className="h-7 w-7 text-brand-500" />
                        Analytics Hub
                    </h1>
                    <p className="text-sm text-surface-500 mt-1">Visão executiva consolidada e inteligência preditiva</p>
                </div>

                <div className="flex flex-wrap items-center gap-2 animate-in fade-in slide-in-from-right-4 duration-300">
                    <PdfExportButton elementId="analytics-hub-container" fileName={`analytics-${currentTab}`} />

                    {currentTab === 'overview' && (
                        <div className="flex items-center gap-2 ml-2 pl-2 border-l border-default">
                            <input
                                type="date"
                                aria-label="Data inicial"
                                value={from}
                                onChange={e => setFrom(e.target.value)}
                                className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm text-surface-700 focus:ring-2 focus:ring-brand-500"
                            />
                            <span className="text-surface-400 text-sm">até</span>
                            <input
                                type="date"
                                aria-label="Data final"
                                value={to}
                                onChange={e => setTo(e.target.value)}
                                className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm text-surface-700 focus:ring-2 focus:ring-brand-500"
                            />
                        </div>
                    )}
                </div>
            </div>

            <Tabs defaultValue="overview" onValueChange={setCurrentTab} className="space-y-6">
                <TabsList className="flex h-auto flex-wrap justify-start gap-2 bg-transparent p-0">
                    <TabsTrigger value="overview" className="gap-2">
                        <LayoutDashboard className="h-4 w-4" />
                        Visão Geral
                    </TabsTrigger>
                    <TabsTrigger value="predictive" className="gap-2">
                        <BrainCircuit className="h-4 w-4" />
                        Inteligência Artificial
                    </TabsTrigger>
                    <TabsTrigger value="datasets" className="gap-2">
                        <Database className="h-4 w-4" />
                        Datasets
                    </TabsTrigger>
                    <TabsTrigger value="exports" className="gap-2">
                        <Download className="h-4 w-4" />
                        Exportações
                    </TabsTrigger>
                    <TabsTrigger value="dashboards" className="gap-2">
                        <MonitorPlay className="h-4 w-4" />
                        Dashboards
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="overview">
                    {currentTab === 'overview' && (
                        <Suspense fallback={<PanelLoading />}>
                            <AnalyticsOverview from={from} to={to} />
                        </Suspense>
                    )}
                </TabsContent>

                <TabsContent value="predictive">
                    {currentTab === 'predictive' && (
                        <Suspense fallback={<PanelLoading />}>
                            <PredictiveAnalytics />
                        </Suspense>
                    )}
                </TabsContent>

                <TabsContent value="datasets">
                    {currentTab === 'datasets' && <AnalyticsDatasetsPanel />}
                </TabsContent>

                <TabsContent value="exports">
                    {currentTab === 'exports' && <AnalyticsExportJobsPanel />}
                </TabsContent>

                <TabsContent value="dashboards">
                    {currentTab === 'dashboards' && <AnalyticsEmbeddedDashboardsPanel />}
                </TabsContent>
            </Tabs>
        </div>
    )
}

export default AnalyticsHubPage

function AnalyticsDatasetsPanel() {
    const { data, isLoading } = useAnalyticsDatasets()

    return (
        <section className="space-y-4">
            <PanelHeader
                title="Datasets analíticos"
                description="Bases versionadas para preview, exportação e refresh em cache por tenant."
            />
            {isLoading ? <PanelLoading /> : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {data?.data.length ? data.data.map(dataset => (
                        <DatasetCard key={dataset.id} dataset={dataset} />
                    )) : <EmptyPanel message="Nenhum dataset analítico configurado até agora." />}
                </div>
            )}
        </section>
    )
}

function AnalyticsExportJobsPanel() {
    const { data, isLoading } = useDataExportJobs()

    return (
        <section className="space-y-4">
            <PanelHeader
                title="Exportações agendadas e sob demanda"
                description="Acompanhe fila de export, status final e formato de saída para cada dataset."
            />
            {isLoading ? <PanelLoading /> : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {data?.data.length ? data.data.map(job => (
                        <ExportJobCard key={job.id} job={job} />
                    )) : <EmptyPanel message="Nenhuma exportação foi registrada ainda." />}
                </div>
            )}
        </section>
    )
}

function AnalyticsEmbeddedDashboardsPanel() {
    const { data, isLoading } = useEmbeddedDashboards()

    return (
        <section className="space-y-4">
            <PanelHeader
                title="Dashboards embedados"
                description="Consolidação de painéis externos para uso dentro do hub analítico."
            />
            {isLoading ? <PanelLoading /> : (
                <div className="grid gap-4 xl:grid-cols-2">
                    {data?.data.length ? data.data.map(dashboard => (
                        <DashboardCard key={dashboard.id} dashboard={dashboard} />
                    )) : <EmptyPanel message="Nenhum dashboard embedado foi cadastrado ainda." />}
                </div>
            )}
        </section>
    )
}

function PanelHeader({ title, description }: { title: string; description: string }) {
    return (
        <div className="rounded-2xl border border-default bg-gradient-to-r from-surface-0 via-surface-0 to-brand-50/50 p-5">
            <h2 className="text-lg font-semibold text-surface-900">{title}</h2>
            <p className="mt-1 text-sm text-surface-500">{description}</p>
        </div>
    )
}

function PanelLoading() {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div key={index} className="h-40 animate-pulse rounded-2xl border border-default bg-surface-50" />
            ))}
        </div>
    )
}

function EmptyPanel({ message }: { message: string }) {
    return (
        <div className="rounded-2xl border border-dashed border-default bg-surface-50 p-8 text-center text-sm text-surface-500">
            {message}
        </div>
    )
}

function DatasetCard({ dataset }: { dataset: AnalyticsDatasetItem }) {
    return (
        <article className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-base font-semibold text-surface-900">{dataset.name}</h3>
                    <p className="mt-1 text-sm text-surface-500">{dataset.description || 'Dataset analítico pronto para preview e exportação.'}</p>
                </div>
                <StatusPill tone={dataset.is_active ? 'success' : 'muted'}>
                    {dataset.is_active ? 'Ativo' : 'Inativo'}
                </StatusPill>
            </div>
            <div className="mt-4 flex flex-wrap gap-2 text-xs text-surface-500">
                <MetaPill icon={<RefreshCw className="h-3.5 w-3.5" />} label={`Refresh ${dataset.refresh_strategy}`} />
                <MetaPill icon={<Clock3 className="h-3.5 w-3.5" />} label={`TTL ${dataset.cache_ttl_minutes ?? 0} min`} />
            </div>
        </article>
    )
}

function ExportJobCard({ job }: { job: DataExportJobItem }) {
    const tone = job.status === 'completed'
        ? 'success'
        : job.status === 'failed' || job.status === 'cancelled'
            ? 'danger'
            : 'warning'

    return (
        <article className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-base font-semibold text-surface-900">{job.name}</h3>
                    <p className="mt-1 text-sm text-surface-500">Formato {job.output_format.toUpperCase()} para consumo operacional e auditoria.</p>
                </div>
                <StatusPill tone={tone}>{formatJobStatus(job.status)}</StatusPill>
            </div>
            <div className="mt-4 flex flex-wrap gap-2 text-xs text-surface-500">
                <MetaPill icon={<Download className="h-3.5 w-3.5" />} label={`Linhas ${job.rows_exported ?? 0}`} />
                <MetaPill icon={<Clock3 className="h-3.5 w-3.5" />} label={job.completed_at ? 'Concluído' : 'Em processamento'} />
            </div>
        </article>
    )
}

function DashboardCard({ dashboard }: { dashboard: EmbeddedDashboardItem }) {
    return (
        <article className="overflow-hidden rounded-2xl border border-default bg-surface-0 shadow-card">
            <div className="flex items-start justify-between gap-3 border-b border-default p-5">
                <div>
                    <h3 className="text-base font-semibold text-surface-900">{dashboard.name}</h3>
                    <p className="mt-1 text-sm text-surface-500">Provider {formatProvider(dashboard.provider)} com embed seguro no hub.</p>
                </div>
                <StatusPill tone={dashboard.is_active ? 'success' : 'muted'}>
                    {dashboard.is_active ? 'Ativo' : 'Inativo'}
                </StatusPill>
            </div>
            <div className="p-5">
                <a
                    href={dashboard.embed_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-2 text-sm font-medium text-brand-700 hover:text-brand-800"
                >
                    <MonitorPlay className="h-4 w-4" />
                    Abrir dashboard
                </a>
            </div>
        </article>
    )
}

function MetaPill({ icon, label }: { icon: ReactNode; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-surface-100 px-3 py-1">
            {icon}
            {label}
        </span>
    )
}

function StatusPill({ children, tone }: { children: ReactNode; tone: 'success' | 'warning' | 'danger' | 'muted' }) {
    const className = {
        success: 'bg-emerald-50 text-emerald-700',
        warning: 'bg-amber-50 text-amber-700',
        danger: 'bg-red-50 text-red-700',
        muted: 'bg-surface-100 text-surface-600',
    }[tone]

    const icon = {
        success: <CheckCircle2 className="h-3.5 w-3.5" />,
        warning: <Clock3 className="h-3.5 w-3.5" />,
        danger: <AlertTriangle className="h-3.5 w-3.5" />,
        muted: <Clock3 className="h-3.5 w-3.5" />,
    }[tone]

    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ${className}`}>
            {icon}
            {children}
        </span>
    )
}

function formatJobStatus(status: DataExportJobItem['status']) {
    const labels: Record<DataExportJobItem['status'], string> = {
        pending: 'Pendente',
        running: 'Rodando',
        completed: 'Concluído',
        failed: 'Falhou',
        cancelled: 'Cancelado',
    }

    return labels[status]
}

function formatProvider(provider: EmbeddedDashboardItem['provider']) {
    const labels: Record<EmbeddedDashboardItem['provider'], string> = {
        metabase: 'Metabase',
        power_bi: 'Power BI',
        custom_url: 'Custom URL',
    }

    return labels[provider]
}
