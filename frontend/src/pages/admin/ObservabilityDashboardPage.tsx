import { useDeferredValue, useState } from 'react'
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    CheckCircle2,
    Clock3,
    Gauge,
    HardDrive,
    Loader2,
    Radar,
    RefreshCcw,
    Server,
    ShieldAlert,
} from 'lucide-react'
import { useObservabilityDashboardQuery } from '@/features/observability/hooks'

function statusTone(status: string): string {
    if (status === 'healthy') return 'bg-emerald-100 text-emerald-700'
    if (status === 'critical') return 'bg-red-100 text-red-700'
    return 'bg-amber-100 text-amber-700'
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('pt-BR').format(value)
}

function formatDateTime(value?: string | null): string {
    if (!value) return 'Sem registro'

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value))
}

export function ObservabilityDashboardPage() {
    const { data, isLoading, isError, refetch, isFetching } = useObservabilityDashboardQuery()
    const [endpointFilter, setEndpointFilter] = useState('')
    const deferredEndpointFilter = useDeferredValue(endpointFilter.trim().toLowerCase())

    const metrics = (data?.metrics ?? []).filter(metric => {
        if (!deferredEndpointFilter) return true

        return `${metric.method} ${metric.path}`.toLowerCase().includes(deferredEndpointFilter)
    })

    return (
        <div className="space-y-5">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight text-surface-900">Observabilidade e Monitoramento</h1>
                    <p className="mt-1 text-sm text-surface-500">
                        Saúde da plataforma, métricas de API, alertas operacionais e atalhos para ferramentas externas.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${statusTone(data?.summary.status ?? 'degraded')}`}>
                        <Radar className="h-3.5 w-3.5" />
                        {(data?.summary.status ?? 'degraded').toUpperCase()}
                    </span>
                    <button
                        type="button"
                        onClick={() => { void refetch() }}
                        aria-label="Atualizar dashboard de observabilidade"
                        className="inline-flex items-center gap-2 rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-50"
                    >
                        <RefreshCcw className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} />
                        Atualizar
                    </button>
                </div>
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center rounded-2xl border border-default bg-surface-0 py-20">
                    <Loader2 className="h-8 w-8 animate-spin text-surface-400" />
                </div>
            ) : null}

            {isError ? (
                <div className="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    Não foi possível carregar o dashboard de observabilidade no momento.
                </div>
            ) : null}

            {!isLoading && !isError && data ? (
                <>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-surface-500">Status Geral</span>
                                <Activity className="h-5 w-5 text-blue-600" />
                            </div>
                            <p className="mt-4 text-2xl font-semibold tracking-tight text-surface-900">{data.summary.status}</p>
                            <p className="mt-1 text-xs text-surface-500">Atualizado em {formatDateTime(data.health.timestamp)}</p>
                        </div>

                        <div className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-surface-500">Alertas Ativos</span>
                                <ShieldAlert className="h-5 w-5 text-red-600" />
                            </div>
                            <p className="mt-4 text-2xl font-semibold tracking-tight text-surface-900">{formatNumber(data.summary.active_alerts)}</p>
                            <p className="mt-1 text-xs text-surface-500">Thresholds de fila, disco e latência</p>
                        </div>

                        <div className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-surface-500">Endpoints Monitorados</span>
                                <Gauge className="h-5 w-5 text-amber-600" />
                            </div>
                            <p className="mt-4 text-2xl font-semibold tracking-tight text-surface-900">{formatNumber(data.summary.tracked_endpoints)}</p>
                            <p className="mt-1 text-xs text-surface-500">Métricas p50, p95 e p99 por rota</p>
                        </div>

                        <div className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-surface-500">Checks Saudáveis</span>
                                <Server className="h-5 w-5 text-emerald-600" />
                            </div>
                            <p className="mt-4 text-2xl font-semibold tracking-tight text-surface-900">
                                {Object.values(data.health.checks).filter(check => check.ok).length}/{Object.keys(data.health.checks).length}
                            </p>
                            <p className="mt-1 text-xs text-surface-500">MySQL, Redis, fila, disco, Reverb e collector</p>
                        </div>
                    </div>

                    <div className="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
                        <section className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="mb-4 flex items-center gap-2">
                                <Server className="h-5 w-5 text-surface-500" />
                                <h2 className="text-base font-semibold text-surface-900">Saúde dos Serviços</h2>
                            </div>

                            <div className="grid gap-3 md:grid-cols-2">
                                {Object.entries(data.health.checks).map(([key, check]) => (
                                    <div key={key} className="rounded-xl border border-default bg-surface-50 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold capitalize text-surface-900">{key}</p>
                                                <p className="mt-1 text-xs text-surface-500">
                                                    {check.error
                                                        ? check.error
                                                        : `Última leitura: ${formatDateTime(data.health.timestamp)}`}
                                                </p>
                                            </div>
                                            <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${check.ok ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                                                {check.ok ? <CheckCircle2 className="h-3.5 w-3.5" /> : <AlertTriangle className="h-3.5 w-3.5" />}
                                                {check.ok ? 'OK' : 'Falha'}
                                            </span>
                                        </div>

                                        <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-surface-600">
                                            {typeof check.pending_jobs === 'number' ? <span>Fila: {formatNumber(check.pending_jobs)}</span> : null}
                                            {typeof check.failed_jobs === 'number' ? <span>Falhos: {formatNumber(check.failed_jobs)}</span> : null}
                                            {typeof check.used_percent === 'number' ? <span>Disco: {check.used_percent}%</span> : null}
                                            {typeof check.free_gb === 'number' ? <span>Livre: {check.free_gb} GB</span> : null}
                                            {check.version ? <span>Versão: {check.version}</span> : null}
                                            {check.host ? <span>{check.host}:{check.port ?? '-'}</span> : null}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="mb-4 flex items-center gap-2">
                                <ShieldAlert className="h-5 w-5 text-surface-500" />
                                <h2 className="text-base font-semibold text-surface-900">Alertas Operacionais</h2>
                            </div>

                            <div className="space-y-3">
                                {data.alerts.length === 0 ? (
                                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                                        Nenhum alerta ativo no momento.
                                    </div>
                                ) : (
                                    data.alerts.map((alert, index) => (
                                        <div key={`${alert.type}-${index}`} className="rounded-xl border border-red-200 bg-red-50 p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-sm font-semibold uppercase tracking-wide text-red-700">{alert.type}</span>
                                                <span className="text-xs font-semibold text-red-600">{alert.level}</span>
                                            </div>
                                            <p className="mt-2 text-sm text-red-700">{alert.message}</p>
                                            {typeof alert.value === 'number' ? (
                                                <p className="mt-2 text-xs text-red-600">Valor atual: {formatNumber(alert.value)}</p>
                                            ) : null}
                                            {alert.path ? (
                                                <p className="mt-1 text-xs font-mono text-red-600">{alert.path}</p>
                                            ) : null}
                                        </div>
                                    ))
                                )}
                            </div>

                            <div className="mt-5 grid gap-3">
                                <a href={data.links.horizon} target="_blank" rel="noreferrer" className="inline-flex items-center justify-between rounded-xl border border-default bg-surface-50 px-4 py-3 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100">
                                    Horizon
                                    <ArrowUpRight className="h-4 w-4" />
                                </a>
                                <a href={data.links.pulse} target="_blank" rel="noreferrer" className="inline-flex items-center justify-between rounded-xl border border-default bg-surface-50 px-4 py-3 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100">
                                    Pulse
                                    <ArrowUpRight className="h-4 w-4" />
                                </a>
                                <a href={data.links.jaeger} target="_blank" rel="noreferrer" className="inline-flex items-center justify-between rounded-xl border border-default bg-surface-50 px-4 py-3 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100">
                                    Jaeger
                                    <ArrowUpRight className="h-4 w-4" />
                                </a>
                            </div>
                        </section>
                    </div>

                    <div className="grid gap-5 xl:grid-cols-[1.7fr_1fr]">
                        <section className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex items-center gap-2">
                                    <Gauge className="h-5 w-5 text-surface-500" />
                                    <h2 className="text-base font-semibold text-surface-900">Métricas por Endpoint</h2>
                                </div>

                                <input
                                    type="search"
                                    value={endpointFilter}
                                    onChange={(event) => setEndpointFilter(event.target.value)}
                                    placeholder="Filtrar por método ou rota"
                                    aria-label="Filtrar métricas por endpoint"
                                    className="w-full rounded-lg border border-default px-3 py-2 text-sm text-surface-700 focus:border-brand-500 focus:outline-none lg:max-w-xs"
                                />
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-subtle">
                                    <thead className="bg-surface-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-surface-500">Endpoint</th>
                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-surface-500">Volume</th>
                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-surface-500">p50</th>
                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-surface-500">p95</th>
                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-surface-500">p99</th>
                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-surface-500">Erro</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-subtle">
                                        {metrics.map(metric => (
                                            <tr key={`${metric.method}-${metric.path}`} className="hover:bg-surface-50">
                                                <td className="px-3 py-3">
                                                    <div className="flex flex-col">
                                                        <span className="text-xs font-semibold uppercase text-surface-500">{metric.method}</span>
                                                        <span className="font-mono text-sm text-surface-800">{metric.path}</span>
                                                        <span className="mt-1 text-xs text-surface-400">Último hit: {formatDateTime(metric.last_seen_at)}</span>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3 text-sm text-surface-700">{formatNumber(metric.count)}</td>
                                                <td className="px-3 py-3 text-sm text-surface-700">{metric.p50_ms} ms</td>
                                                <td className={`px-3 py-3 text-sm font-medium ${metric.p95_ms > 2000 ? 'text-red-600' : 'text-surface-700'}`}>{metric.p95_ms} ms</td>
                                                <td className={`px-3 py-3 text-sm font-medium ${metric.p99_ms > 2000 ? 'text-red-600' : 'text-surface-700'}`}>{metric.p99_ms} ms</td>
                                                <td className="px-3 py-3 text-sm text-surface-700">{metric.error_rate}%</td>
                                            </tr>
                                        ))}
                                        {metrics.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="px-3 py-12 text-center text-sm text-surface-400">
                                                    Nenhum endpoint encontrado para o filtro informado.
                                                </td>
                                            </tr>
                                        ) : null}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section className="rounded-2xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="mb-4 flex items-center gap-2">
                                <Clock3 className="h-5 w-5 text-surface-500" />
                                <h2 className="text-base font-semibold text-surface-900">Histórico Recente</h2>
                            </div>

                            <div className="space-y-3">
                                {data.history.map(item => (
                                    <div key={item.id} className="rounded-xl border border-default bg-surface-50 p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${statusTone(item.status)}`}>
                                                {item.status}
                                            </span>
                                            <span className="text-xs text-surface-500">#{item.id}</span>
                                        </div>
                                        <p className="mt-3 text-sm text-surface-700">{formatDateTime(item.captured_at)}</p>
                                        <div className="mt-2 flex items-center gap-2 text-xs text-surface-500">
                                            <HardDrive className="h-3.5 w-3.5" />
                                            {formatNumber(item.alerts_count)} alerta(s) no snapshot
                                        </div>
                                    </div>
                                ))}
                                {data.history.length === 0 ? (
                                    <div className="rounded-xl border border-default bg-surface-50 p-4 text-sm text-surface-500">
                                        Ainda não há snapshots persistidos.
                                    </div>
                                ) : null}
                            </div>
                        </section>
                    </div>
                </>
            ) : null}
        </div>
    )
}
