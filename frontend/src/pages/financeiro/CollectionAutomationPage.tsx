import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { getApiErrorMessage, unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { AlertTriangle, CalendarClock, CheckCircle2, MessageSquare, Phone, Scale, Zap } from 'lucide-react'

interface CollectionCustomer {
    id: number
    name: string
    email?: string | null
    phone?: string | null
}

interface CollectionRuleItem {
    id: number
    customer?: CollectionCustomer | null
    amount: number | string
    due_date: string
    days_overdue: number
    collection_stage: string
    suggested_action: string
}

interface CollectionSummary {
    total_overdue: number
    total_amount: number
    by_stage?: Record<string, { count: number; total: number }>
}

interface CollectionPayload {
    data: CollectionRuleItem[]
    summary: CollectionSummary
}

type StageVisual = {
    label: string
    icon: typeof Zap
    tone: string
}

const stageVisuals: Record<string, StageVisual> = {
    reminder: { label: 'Lembrete', icon: MessageSquare, tone: 'bg-sky-100 text-sky-800' },
    first_contact: { label: 'Primeiro contato', icon: Phone, tone: 'bg-amber-100 text-amber-800' },
    formal_notice: { label: 'Notificação formal', icon: AlertTriangle, tone: 'bg-orange-100 text-orange-800' },
    negotiation: { label: 'Negociação', icon: CalendarClock, tone: 'bg-cyan-100 text-cyan-800' },
    restriction: { label: 'Restrição', icon: Zap, tone: 'bg-red-100 text-red-800' },
    legal: { label: 'Jurídico', icon: Scale, tone: 'bg-red-200 text-red-900' },
}

interface ApiError {
    response?: { data?: { message?: string } }
}

const formatCurrency = (value: number | string) =>
    Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

const formatDate = (value: string) => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR')

export default function CollectionAutomationPage() {
    const { data, isLoading, isError, error, refetch } = useQuery<CollectionPayload>({
        queryKey: ['financial', 'collection-rules'],
        queryFn: async () => unwrapData<CollectionPayload>(await financialApi.collectionAutomation.rules()),
    })

    const items = data?.data ?? []
    const summary = data?.summary ?? { total_overdue: 0, total_amount: 0, by_stage: {} }
    const groupedStages = useMemo(() => {
        return Object.entries(summary.by_stage ?? {}).map(([stage, stageSummary]) => {
            const visual = stageVisuals[stage] ?? stageVisuals.reminder
            return {
                stage,
                visual,
                count: stageSummary.count,
                total: stageSummary.total,
            }
        })
    }, [summary.by_stage])

    return (
        <div className="space-y-6">
            <PageHeader
                title="Automação de Cobrança"
                subtitle="Régua operacional dos recebíveis vencidos com próxima ação recomendada"
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div className="rounded-xl border bg-card p-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <AlertTriangle className="h-4 w-4 text-red-500" /> Títulos vencidos
                    </div>
                    <div className="mt-1 text-2xl font-bold text-red-600">{summary.total_overdue}</div>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <div className="text-sm text-muted-foreground">Valor Total Vencido</div>
                    <div className="mt-1 text-2xl font-bold">
                        {formatCurrency(summary.total_amount)}
                    </div>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <CheckCircle2 className="h-4 w-4 text-emerald-500" /> Estágios ativos
                    </div>
                    <div className="mt-1 text-2xl font-bold text-emerald-600">{groupedStages.length}</div>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Phone className="h-4 w-4 text-blue-500" /> Contatos prioritários
                    </div>
                    <div className="mt-1 text-2xl font-bold text-blue-600">
                        {(summary.by_stage?.formal_notice?.count ?? 0) + (summary.by_stage?.negotiation?.count ?? 0)}
                    </div>
                </div>
            </div>

            <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800">
                <div className="flex items-center gap-2 text-sm font-medium text-blue-700 dark:text-blue-400">
                    <Zap className="h-4 w-4" /> Régua operacional ativa
                </div>
                <p className="mt-1 text-xs text-blue-600 dark:text-blue-300">
                    Esta tela usa o contrato real do backend para classificar atraso, estágio de cobrança e ação sugerida.
                    O acompanhamento operacional fica coerente com os títulos em aberto e com o status financeiro.
                </p>
            </div>

            {isLoading ? (
                <div className="flex justify-center py-12 text-muted-foreground">Carregando...</div>
            ) : isError ? (
                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {getApiErrorMessage(error as ApiError, 'Erro ao carregar a régua de cobrança.')}
                    <button className="ml-2 underline" onClick={() => refetch()}>Tentar novamente</button>
                </div>
            ) : items.length === 0 ? (
                <EmptyState
                    icon={Zap}
                    title="Nenhum título vencido"
                    description="Não há recebíveis vencidos para tratar na régua de cobrança."
                />
            ) : (
                <div className="space-y-4">
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {groupedStages.map(({ stage, visual, count, total }) => {
                            const Icon = visual.icon
                            return (
                                <div key={stage} className="rounded-xl border bg-card p-4">
                                    <div className={`inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium ${visual.tone}`}>
                                        <Icon className="h-3.5 w-3.5" />
                                        {visual.label}
                                    </div>
                                    <div className="mt-3 text-sm text-muted-foreground">{count} título(s)</div>
                                    <div className="mt-1 text-xl font-semibold">{formatCurrency(total)}</div>
                                </div>
                            )
                        })}
                    </div>

                    <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr>
                                <th className="p-3 text-left font-medium">Cliente</th>
                                <th className="p-3 text-left font-medium">Vencimento</th>
                                <th className="p-3 text-right font-medium">Valor</th>
                                <th className="p-3 text-center font-medium">Atraso</th>
                                <th className="p-3 text-center font-medium">Estágio</th>
                                <th className="p-3 text-left font-medium">Ação sugerida</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {items.map((item) => {
                                const visual = stageVisuals[item.collection_stage] ?? stageVisuals.reminder
                                const Icon = visual.icon
                                return (
                                    <tr key={item.id}>
                                        <td className="p-3 font-medium">{item.customer?.name ?? `Título #${item.id}`}</td>
                                        <td className="p-3 text-xs text-muted-foreground">{formatDate(item.due_date)}</td>
                                        <td className="p-3 text-right text-xs">{formatCurrency(item.amount)}</td>
                                        <td className="p-3 text-center text-xs font-medium text-amber-700">{item.days_overdue} dia(s)</td>
                                        <td className="p-3 text-center">
                                            <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${visual.tone}`}>
                                                <Icon className="h-3 w-3" />
                                                {visual.label}
                                            </span>
                                        </td>
                                        <td className="p-3 text-xs text-muted-foreground">{item.suggested_action}</td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                    </div>
                </div>
            )}
        </div>
    )
}
