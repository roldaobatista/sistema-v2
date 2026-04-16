import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { crmFeaturesApi, type CrmPipelineVelocity } from '@/lib/crm-features-api'
import { crmApi, type CrmPipeline } from '@/lib/crm-api'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { PageHeader } from '@/components/ui/pageheader'
import { Gauge, TrendingUp, Clock, DollarSign, BarChart3, Loader2, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'

const fmtBRL = (v: number | string) =>
    Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

const EMPTY_VELOCITY: CrmPipelineVelocity = {
    avg_cycle_days: 0,
    avg_deal_value: 0,
    velocity_number: 0,
    win_rate: 0,
    total_deals: 0,
    stages: [],
}

export function CrmVelocityPage() {
    const [months, setMonths] = useState('6')
    const [pipelineId, setPipelineId] = useState('all')

    const { data: pipelines = [] } = useQuery({
        queryKey: ['crm', 'pipelines'],
        queryFn: () => crmApi.getPipelines(),
    })

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['crm-velocity', months, pipelineId],
        queryFn: () =>
            crmFeaturesApi.getPipelineVelocity({
                months: Number(months),
                pipeline_id: pipelineId !== 'all' ? Number(pipelineId) : undefined,
            }),
    })

    const velocity = data ?? EMPTY_VELOCITY

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-primary-500" />
            </div>
        )
    }

    if (isError) {
        return (
            <div className="flex flex-col items-center justify-center py-20 gap-4">
                <AlertCircle className="h-10 w-10 text-red-500" />
                <p className="text-surface-600">{(error as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erro ao carregar dados de velocidade.'}</p>
                <Button variant="outline" onClick={() => refetch()}>Tentar novamente</Button>
            </div>
        )
    }

    const metrics = [
        {
            label: 'Ciclo Medio',
            value: `${velocity.avg_cycle_days.toFixed(1)} dias`,
            icon: Clock,
            color: 'text-blue-600 bg-blue-50',
        },
        {
            label: 'Valor Medio',
            value: fmtBRL(velocity.avg_deal_value),
            icon: DollarSign,
            color: 'text-green-600 bg-green-50',
        },
        {
            label: 'Velocidade',
            value: fmtBRL(velocity.velocity_number),
            icon: Gauge,
            color: 'text-teal-600 bg-teal-50',
            description: 'Receita potencial/dia',
        },
        {
            label: 'Taxa de Conversao',
            value: `${(velocity.win_rate ?? 0).toFixed(1)}%`,
            icon: TrendingUp,
            color: 'text-orange-600 bg-orange-50',
        },
    ]

    const stageMaxValue = Math.max(...velocity.stages.map((stage) => stage.total_value), 1)

    return (
        <div className="space-y-6">
            <PageHeader
                title="Velocidade do Pipeline"
                subtitle="Analise de velocidade e ciclo de vendas por etapa do funil."
                icon={Gauge}
            />

            <div className="flex flex-wrap items-center gap-3">
                <Select value={pipelineId} onValueChange={setPipelineId}>
                    <SelectTrigger className="w-48">
                        <SelectValue placeholder="Pipeline" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos os Pipelines</SelectItem>
                        {pipelines.map((pipeline: CrmPipeline) => (
                            <SelectItem key={pipeline.id} value={String(pipeline.id)}>
                                {pipeline.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={months} onValueChange={setMonths}>
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="Periodo" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="3">Ultimos 3 meses</SelectItem>
                        <SelectItem value="6">Ultimos 6 meses</SelectItem>
                        <SelectItem value="12">Ultimos 12 meses</SelectItem>
                    </SelectContent>
                </Select>

                <Badge variant="secondary" className="ml-auto">
                    {velocity.total_deals} negocios analisados
                </Badge>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {metrics.map((metric) => (
                    <Card key={metric.label}>
                        <CardContent className="pt-6">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm text-surface-500">{metric.label}</p>
                                    <p className="mt-1 text-2xl font-bold">{metric.value}</p>
                                    {metric.description && (
                                        <p className="mt-1 text-xs text-surface-400">{metric.description}</p>
                                    )}
                                </div>
                                <div className={`rounded-lg p-2.5 ${metric.color}`}>
                                    <metric.icon className="h-5 w-5" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <BarChart3 className="h-5 w-5" />
                        Analise por Etapa
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {velocity.stages.length === 0 ? (
                        <div className="flex flex-col items-center py-10 text-surface-400">
                            <BarChart3 className="mb-2 h-10 w-10" />
                            <p>Nenhuma etapa encontrada para o periodo selecionado.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-surface-500">
                                        <th className="pb-3 pr-4 font-medium">Etapa</th>
                                        <th className="pb-3 pr-4 text-right font-medium">Negocios Ativos</th>
                                        <th className="pb-3 pr-4 text-right font-medium">Valor Total</th>
                                        <th className="pb-3 pr-4 text-right font-medium">Media de Dias</th>
                                        <th className="min-w-[200px] pb-3 font-medium">Volume</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {velocity.stages.map((stage) => (
                                        <tr key={stage.name} className="border-b last:border-0">
                                            <td className="py-3 pr-4 font-medium">{stage.name}</td>
                                            <td className="py-3 pr-4 text-right tabular-nums">{stage.deals_count}</td>
                                            <td className="py-3 pr-4 text-right tabular-nums">{fmtBRL(stage.total_value)}</td>
                                            <td className="py-3 pr-4 text-right tabular-nums">
                                                <Badge variant={stage.avg_days_in_stage > 30 ? 'destructive' : 'secondary'}>
                                                    {stage.avg_days_in_stage.toFixed(1)}d
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-surface-100">
                                                        <div
                                                            className="h-full rounded-full bg-primary-500 transition-all"
                                                            style={{ width: `${(stage.total_value / stageMaxValue) * 100}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Formula de Velocidade</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap items-center justify-center gap-3 text-sm">
                        <div className="rounded-lg bg-blue-50 px-4 py-2 text-center">
                            <p className="text-xs text-surface-500">Negocios</p>
                            <p className="text-lg font-bold text-blue-700">{velocity.total_deals}</p>
                        </div>
                        <span className="text-xl text-surface-400">x</span>
                        <div className="rounded-lg bg-green-50 px-4 py-2 text-center">
                            <p className="text-xs text-surface-500">Valor Medio</p>
                            <p className="text-lg font-bold text-green-700">{fmtBRL(velocity.avg_deal_value)}</p>
                        </div>
                        <span className="text-xl text-surface-400">x</span>
                        <div className="rounded-lg bg-orange-50 px-4 py-2 text-center">
                            <p className="text-xs text-surface-500">Win Rate</p>
                            <p className="text-lg font-bold text-orange-700">{(velocity.win_rate ?? 0).toFixed(1)}%</p>
                        </div>
                        <span className="text-xl text-surface-400">/</span>
                        <div className="rounded-lg bg-red-50 px-4 py-2 text-center">
                            <p className="text-xs text-surface-500">Ciclo (dias)</p>
                            <p className="text-lg font-bold text-red-700">{velocity.avg_cycle_days.toFixed(1)}</p>
                        </div>
                        <span className="text-xl text-surface-400">=</span>
                        <div className="rounded-lg border-2 border-teal-200 bg-teal-50 px-4 py-2 text-center">
                            <p className="text-xs text-surface-500">Velocidade</p>
                            <p className="text-lg font-bold text-teal-700">{fmtBRL(velocity.velocity_number)}/dia</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    )
}
