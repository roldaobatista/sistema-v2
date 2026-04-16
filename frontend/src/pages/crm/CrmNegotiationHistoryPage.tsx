import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
    Briefcase,
    Cpu,
    FileText,
    Loader2,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
    Search,
    UserCircle,
    Users,
    Wrench,
} from 'lucide-react'

import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import {
    getNegotiationHistory,
    type NegotiationHistoryTimelineItem,
} from '@/lib/crm-field-api'
import { safeArray } from '@/lib/safe-array'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/pageheader'

const fmtDate = (d: string) => new Date(d).toLocaleDateString('pt-BR')
const fmtMoney = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

const entryIcons: Record<string, React.ElementType> = {
    quote: FileText,
    work_order: Wrench,
    deal: Briefcase,
    ligacao: Phone,
    email: Mail,
    reuniao: Users,
    visita: MapPin,
    whatsapp: MessageCircle,
    nota: FileText,
    tarefa: Briefcase,
    system: Cpu,
}

const entryLabels: Record<string, string> = {
    quote: 'Orcamento',
    work_order: 'Ordem de Servico',
    deal: 'Negociacao',
    ligacao: 'Ligacao',
    email: 'E-mail',
    reuniao: 'Reuniao',
    visita: 'Visita',
    whatsapp: 'WhatsApp',
    nota: 'Nota',
    tarefa: 'Tarefa',
    system: 'Sistema',
}

function getEntryKind(item: NegotiationHistoryTimelineItem): string {
    return item.entry_type === 'activity' ? item.type : (item.entry_type ?? item.type)
}

function getEntryTitle(item: NegotiationHistoryTimelineItem): string {
    const entryKind = getEntryKind(item)

    if (item.entry_type === 'activity') {
        return item.title
    }

    const suffix = String(item.quote_number ?? item.os_number ?? item.business_number ?? item.title ?? '').trim()
    const baseLabel = entryLabels[entryKind] ?? 'Registro'

    return suffix ? `${baseLabel} ${suffix}` : baseLabel
}

function getEntryAmount(item: NegotiationHistoryTimelineItem): number | null {
    if (item.entry_type === 'activity') {
        return null
    }

    const rawValue = item.total ?? item.value
    return typeof rawValue === 'number' ? rawValue : Number(rawValue ?? 0)
}

function getEntryStatus(item: NegotiationHistoryTimelineItem): string | null {
    if (item.entry_type === 'activity') {
        return item.outcome ?? item.channel ?? (item.is_automated ? 'automatico' : null)
    }

    return item.status ?? null
}

export function CrmNegotiationHistoryPage() {
    const [customerId, setCustomerId] = useState<number | null>(null)
    const [search, setSearch] = useState('')

    const searchQ = useQuery({
        queryKey: ['customers-neg-search', search],
        queryFn: () => api.get('/customers', { params: { search, per_page: 8, is_active: true } }).then(r => safeArray<{ id: number; name: string }>(unwrapData(r))),
        enabled: search.length >= 2,
    })

    const {
        data,
        isLoading,
        isError,
        error,
    } = useQuery({
        queryKey: ['negotiation-history', customerId],
        queryFn: () => getNegotiationHistory(customerId!),
        enabled: !!customerId,
    })

    const selectedCustomer = customerId != null
        ? (searchQ.data ?? []).find(customer => customer.id === customerId)?.name ?? search
        : null

    return (
        <div className="space-y-6">
            <PageHeader
                title="Historico de Negociacao"
                description="Timeline unificada de orcamentos, ordens de servico, deals e atividades CRM por cliente."
            />

            <div className="max-w-xl space-y-2">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Buscar cliente para historico de negociacao"
                        className="pl-9"
                        placeholder="Buscar cliente..."
                        value={search}
                        onChange={event => setSearch(event.target.value)}
                    />
                </div>

                {(searchQ.data ?? []).length > 0 && search.length >= 2 && (
                    <div className="max-h-48 overflow-auto rounded-md border bg-background">
                        {(searchQ.data ?? []).map(customer => (
                            <button
                                key={customer.id}
                                className={`w-full px-3 py-2 text-left text-sm hover:bg-accent ${customer.id === customerId ? 'bg-accent' : ''}`}
                                onClick={() => {
                                    setCustomerId(customer.id)
                                    setSearch(customer.name)
                                }}
                                type="button"
                            >
                                {customer.name}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {!customerId && (
                <Card>
                    <CardContent className="py-10 text-center text-sm text-muted-foreground">
                        Selecione um cliente para carregar o historico comercial completo.
                    </CardContent>
                </Card>
            )}

            {customerId && isLoading && (
                <div className="flex justify-center py-12">
                    <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                </div>
            )}

            {customerId && isError && (
                <Card>
                    <CardContent className="py-6 text-sm text-destructive">
                        {getApiErrorMessage(error, 'Nao foi possivel carregar o historico de negociacao.')}
                    </CardContent>
                </Card>
            )}

            {data && (
                <>
                    <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                        <Card>
                            <CardContent className="py-3 text-center">
                                <p className="text-lg font-bold">{fmtMoney(data.totals.total_quoted)}</p>
                                <p className="text-xs text-muted-foreground">Total orcado</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="py-3 text-center">
                                <p className="text-lg font-bold">{fmtMoney(data.totals.total_os)}</p>
                                <p className="text-xs text-muted-foreground">Total OS</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="py-3 text-center">
                                <p className="text-lg font-bold">{fmtMoney(data.totals.total_deals_won)}</p>
                                <p className="text-xs text-muted-foreground">Deals ganhos</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="py-3 text-center">
                                <p className="text-lg font-bold">{fmtMoney(data.totals.avg_discount)}</p>
                                <p className="text-xs text-muted-foreground">Desconto medio</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="py-3 text-center">
                                <p className="text-lg font-bold">{data.totals.activities_count}</p>
                                <p className="text-xs text-muted-foreground">Atividades CRM</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="py-3 text-center">
                                <p className="text-lg font-bold">{data.totals.messages_count}</p>
                                <p className="text-xs text-muted-foreground">Mensagens</p>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-2">
                        {data.timeline.length === 0 ? (
                            <Card>
                                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                    Nenhum evento comercial encontrado para {selectedCustomer ?? 'este cliente'}.
                                </CardContent>
                            </Card>
                        ) : (
                            data.timeline.map(item => {
                                const entryKind = getEntryKind(item)
                                const Icon = entryIcons[entryKind] ?? FileText
                                const amount = getEntryAmount(item)
                                const status = getEntryStatus(item)

                                return (
                                    <Card key={`${item.entry_type ?? item.type}-${item.id}`} className="transition-shadow hover:shadow-sm">
                                        <CardContent className="py-3">
                                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                                <div className="flex items-start gap-3">
                                                    <div className="rounded-full bg-muted p-2">
                                                        <Icon className="h-4 w-4 text-muted-foreground" />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <p className="font-medium">{getEntryTitle(item)}</p>
                                                            <Badge variant="secondary">
                                                                {entryLabels[entryKind] ?? 'Registro'}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            {fmtDate(item.created_at)}
                                                        </p>
                                                        {item.entry_type === 'activity' && (
                                                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                                {item.user?.name && <span>{item.user.name}</span>}
                                                                {item.contact?.name && (
                                                                    <span className="inline-flex items-center gap-1">
                                                                        <UserCircle className="h-3 w-3" />
                                                                        {item.contact.name}
                                                                    </span>
                                                                )}
                                                                {item.deal?.title && <span>Deal: {item.deal.title}</span>}
                                                            </div>
                                                        )}
                                                        {item.entry_type === 'activity' && item.description && (
                                                            <p className="text-sm text-muted-foreground">{item.description}</p>
                                                        )}
                                                        {item.entry_type !== 'activity' && item.lost_reason && (
                                                            <p className="text-sm text-muted-foreground">Motivo da perda: {item.lost_reason}</p>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="flex flex-wrap items-center gap-2 md:justify-end">
                                                    {amount != null && (
                                                        <span className="font-medium">{fmtMoney(amount)}</span>
                                                    )}
                                                    {status && (
                                                        <Badge variant="outline">{status}</Badge>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )
                            })
                        )}
                    </div>
                </>
            )}
        </div>
    )
}
