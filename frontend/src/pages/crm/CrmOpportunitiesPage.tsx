import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { ArrowRight, FileText, Loader2, Plus, UserX, Wrench } from 'lucide-react'

import { NewDealModal } from '@/components/crm/NewDealModal'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { EmptyState } from '@/components/ui/emptystate'
import { PageHeader } from '@/components/ui/pageheader'
import { getApiErrorMessage } from '@/lib/api'
import { crmApi } from '@/lib/crm-api'
import { getLatentOpportunities, type LatentOpportunity } from '@/lib/crm-field-api'

const typeConfig: Record<LatentOpportunity['type'], { label: string; icon: React.ElementType; color: string; source: string }> = {
    calibration_expiring: { label: 'Calibracao vencendo', icon: Wrench, color: 'text-orange-600', source: 'calibracao_vencendo' },
    inactive_customer: { label: 'Cliente inativo', icon: UserX, color: 'text-red-600', source: 'retorno' },
    contract_renewal: { label: 'Renovacao de contrato', icon: FileText, color: 'text-blue-600', source: 'contrato_renovacao' },
}

export function CrmOpportunitiesPage() {
    const navigate = useNavigate()
    const [createDealFor, setCreateDealFor] = useState<{ customerId: number; title: string; source: string } | null>(null)

    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['latent-opportunities'],
        queryFn: getLatentOpportunities,
    })
    const { data: pipelines = [] } = useQuery({
        queryKey: ['crm', 'pipelines'],
        queryFn: () => crmApi.getPipelines(),
        enabled: createDealFor !== null,
    })

    const opportunities = data?.opportunities ?? []
    const summary = data?.summary
    const defaultPipeline = pipelines.find(pipeline => pipeline.is_default) ?? pipelines[0]
    const firstStage = defaultPipeline?.stages?.[0]

    return (
        <div className="space-y-6">
            <PageHeader title="Oportunidades Latentes" description="Sinais comerciais que ainda nao viraram acao no pipeline." />

            {isLoading ? (
                <div className="flex justify-center py-12">
                    <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                </div>
            ) : isError ? (
                <Card>
                    <CardContent className="py-6 text-sm text-destructive">
                        {getApiErrorMessage(error, 'Nao foi possivel carregar as oportunidades latentes.')}
                    </CardContent>
                </Card>
            ) : (
                <>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Card>
                            <CardContent className="py-4 text-center">
                                <p className="text-2xl font-bold">{summary?.total ?? 0}</p>
                                <p className="text-xs text-muted-foreground">Total</p>
                            </CardContent>
                        </Card>
                        <Card className="bg-orange-50">
                            <CardContent className="py-4 text-center">
                                <Wrench className="mx-auto mb-1 h-5 w-5 text-orange-600" />
                                <p className="text-2xl font-bold">{summary?.calibration_expiring ?? 0}</p>
                                <p className="text-xs text-muted-foreground">Calibracoes</p>
                            </CardContent>
                        </Card>
                        <Card className="bg-red-50">
                            <CardContent className="py-4 text-center">
                                <UserX className="mx-auto mb-1 h-5 w-5 text-red-600" />
                                <p className="text-2xl font-bold">{summary?.inactive_customers ?? 0}</p>
                                <p className="text-xs text-muted-foreground">Clientes inativos</p>
                            </CardContent>
                        </Card>
                        <Card className="bg-blue-50">
                            <CardContent className="py-4 text-center">
                                <FileText className="mx-auto mb-1 h-5 w-5 text-blue-600" />
                                <p className="text-2xl font-bold">{summary?.contract_renewals ?? 0}</p>
                                <p className="text-xs text-muted-foreground">Contratos</p>
                            </CardContent>
                        </Card>
                    </div>

                    {opportunities.length === 0 ? (
                        <EmptyState
                            icon={FileText}
                            title="Nenhuma oportunidade latente"
                            message="Nao ha sinais comerciais pendentes para transformar em deal neste momento."
                        />
                    ) : (
                        <div className="space-y-2">
                            {opportunities.map((opportunity, index) => {
                                const config = typeConfig[opportunity.type] ?? typeConfig.calibration_expiring
                                const Icon = config.icon
                                const customerId = opportunity.customer?.id ?? 0
                                const suggestedTitle = `${config.label} - ${opportunity.customer?.name ?? 'Cliente'}`
                                return (
                                    <Card key={`${opportunity.type}-${customerId}-${index}`} className="transition-shadow hover:shadow-sm">
                                        <CardContent className="py-3">
                                            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                                                <div className="flex items-center gap-3">
                                                    <Icon className={`h-5 w-5 ${config.color}`} />
                                                    <div>
                                                        <p className="font-medium">{opportunity.customer?.name ?? 'Cliente nao identificado'}</p>
                                                        <p className="text-sm text-muted-foreground">{opportunity.detail}</p>
                                                    </div>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant="outline">{config.label}</Badge>
                                                    <Badge variant={opportunity.priority === 'high' ? 'destructive' : 'secondary'}>
                                                        {opportunity.priority === 'high' ? 'Alta' : 'Media'}
                                                    </Badge>
                                                    {customerId > 0 && (
                                                        <Button
                                                            size="sm"
                                                            variant="default"
                                                            onClick={() => setCreateDealFor({ customerId, title: suggestedTitle, source: config.source })}
                                                        >
                                                            <Plus className="mr-1 h-3.5 w-3.5" /> Criar deal
                                                        </Button>
                                                    )}
                                                    {customerId > 0 && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            aria-label={`Abrir Customer 360 de ${opportunity.customer?.name ?? 'cliente'}`}
                                                            onClick={() => navigate(`/crm/clientes/${customerId}`)}
                                                            title="Ver Customer 360"
                                                        >
                                                            <ArrowRight className="h-3.5 w-3.5" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )
                            })}
                        </div>
                    )}
                </>
            )}

            {defaultPipeline && firstStage && createDealFor && (
                <NewDealModal
                    open={Boolean(createDealFor)}
                    onClose={() => setCreateDealFor(null)}
                    pipelineId={defaultPipeline.id}
                    stageId={firstStage.id}
                    initialCustomerId={createDealFor.customerId}
                    initialTitle={createDealFor.title}
                    initialSource={createDealFor.source}
                />
            )}
        </div>
    )
}
