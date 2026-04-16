import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { crmFeaturesApi, CrmInteractiveProposal } from '@/lib/crm-features-api'
import { getApiErrorMessage } from '@/lib/api'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { PageHeader } from '@/components/ui/pageheader'
import { toast } from 'sonner'
import {
    FileText, Plus, Search, Copy, Eye, Clock, Loader2, AlertCircle,
    CheckCircle, ExternalLink, Send,
} from 'lucide-react'

const fmtBRL = (value: number | string) =>
    Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

const fmtDate = (date: string | null) =>
    date ? new Date(date).toLocaleDateString('pt-BR') : '-'

const fmtTime = (seconds: number) => {
    if (seconds < 60) return `${seconds}s`
    if (seconds < 3600) return `${Math.floor(seconds / 60)}min`
    return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}min`
}

const STATUS_MAP: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    draft: { label: 'Rascunho', variant: 'secondary' },
    sent: { label: 'Enviada', variant: 'default' },
    viewed: { label: 'Visualizada', variant: 'outline' },
    accepted: { label: 'Aceita', variant: 'default' },
    rejected: { label: 'Rejeitada', variant: 'destructive' },
    expired: { label: 'Expirada', variant: 'secondary' },
}

export function CrmProposalsPage() {
    const queryClient = useQueryClient()
    const [search, setSearch] = useState('')
    const [createOpen, setCreateOpen] = useState(false)
    const [quoteId, setQuoteId] = useState('')
    const [dealId, setDealId] = useState('')
    const [expiresAt, setExpiresAt] = useState('')

    const { data: proposals = [], isLoading, isError, error, refetch } = useQuery<CrmInteractiveProposal[]>({
        queryKey: ['crm-proposals'],
        queryFn: crmFeaturesApi.getProposals,
    })

    const createMutation = useMutation({
        mutationFn: (data: { quote_id: number; deal_id?: number; expires_at?: string }) => crmFeaturesApi.createProposal(data),
        onSuccess: () => {
            toast.success('Proposta criada com sucesso!')
            queryClient.invalidateQueries({ queryKey: ['crm-proposals'] })
            setCreateOpen(false)
            resetForm()
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao criar proposta. Tente novamente.'))
        },
    })

    function resetForm() {
        setQuoteId('')
        setDealId('')
        setExpiresAt('')
    }

    function handleCreate() {
        if (!quoteId || Number.isNaN(Number(quoteId))) {
            toast.error('Informe um ID de orcamento valido.')
            return
        }

        createMutation.mutate({
            quote_id: Number(quoteId),
            deal_id: dealId ? Number(dealId) : undefined,
            expires_at: expiresAt || undefined,
        })
    }

    function copyLink(token: string) {
        const url = `${window.location.origin}/propostas/${token}`
        navigator.clipboard.writeText(url).then(
            () => toast.success('Link copiado para a area de transferencia!'),
            () => toast.error('Erro ao copiar link.'),
        )
    }

    const filtered = proposals.filter((proposal) => {
        if (!search) return true

        const query = search.toLowerCase()
        return (
            proposal.quote?.quote_number?.toLowerCase().includes(query) ||
            proposal.deal?.title?.toLowerCase().includes(query) ||
            proposal.token?.toLowerCase().includes(query) ||
            STATUS_MAP[proposal.status]?.label.toLowerCase().includes(query)
        )
    })

    const totalViews = proposals.reduce((sum, proposal) => sum + (proposal.view_count ?? 0), 0)
    const acceptedCount = proposals.filter((proposal) => proposal.status === 'accepted').length
    const avgTimeSpent = proposals.length > 0
        ? proposals.reduce((sum, proposal) => sum + (proposal.time_spent_seconds ?? 0), 0) / proposals.length
        : 0

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-primary-500" />
            </div>
        )
    }

    if (isError) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-20">
                <AlertCircle className="h-10 w-10 text-red-500" />
                <p className="text-surface-600">{getApiErrorMessage(error, 'Erro ao carregar propostas.')}</p>
                <Button variant="outline" onClick={() => refetch()}>Tentar novamente</Button>
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Propostas Interativas"
                subtitle="Gerencie propostas com rastreamento de visualizacao e interacao."
                icon={FileText}
            >
                <Dialog open={createOpen} onOpenChange={(open) => { setCreateOpen(open); if (!open) resetForm() }}>
                    <DialogTrigger asChild>
                        <Button variant="primary" size="sm" icon={<Plus className="h-4 w-4" />}>
                            Nova Proposta
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Criar Proposta a partir de Orcamento</DialogTitle>
                        </DialogHeader>
                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-surface-700">
                                    ID do Orcamento *
                                </label>
                                <Input
                                    type="number"
                                    placeholder="Ex: 123"
                                    value={quoteId}
                                    onChange={(event) => setQuoteId(event.target.value)}
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-surface-700">
                                    ID do Negocio (opcional)
                                </label>
                                <Input
                                    type="number"
                                    placeholder="Ex: 456"
                                    value={dealId}
                                    onChange={(event) => setDealId(event.target.value)}
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-surface-700">
                                    Data de Expiracao (opcional)
                                </label>
                                <Input
                                    type="date"
                                    value={expiresAt}
                                    onChange={(event) => setExpiresAt(event.target.value)}
                                />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <Button variant="outline" onClick={() => setCreateOpen(false)}>
                                    Cancelar
                                </Button>
                                <Button
                                    variant="primary"
                                    onClick={handleCreate}
                                    disabled={createMutation.isPending}
                                    icon={createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                >
                                    {createMutation.isPending ? 'Criando...' : 'Criar Proposta'}
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </PageHeader>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-sm text-surface-500">Total de Propostas</p>
                                <p className="mt-1 text-2xl font-bold">{proposals.length}</p>
                            </div>
                            <div className="rounded-lg bg-blue-50 p-2.5 text-blue-600">
                                <FileText className="h-5 w-5" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-sm text-surface-500">Visualizacoes Totais</p>
                                <p className="mt-1 text-2xl font-bold">{totalViews}</p>
                                <p className="mt-1 text-xs text-surface-400">
                                    Tempo medio: {fmtTime(Math.round(avgTimeSpent))}
                                </p>
                            </div>
                            <div className="rounded-lg bg-green-50 p-2.5 text-green-600">
                                <Eye className="h-5 w-5" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-sm text-surface-500">Aceitas</p>
                                <p className="mt-1 text-2xl font-bold">{acceptedCount}</p>
                                <p className="mt-1 text-xs text-surface-400">
                                    Taxa: {proposals.length ? ((acceptedCount / proposals.length) * 100).toFixed(1) : 0}%
                                </p>
                            </div>
                            <div className="rounded-lg bg-teal-50 p-2.5 text-teal-600">
                                <CheckCircle className="h-5 w-5" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Propostas
                        </CardTitle>
                        <div className="relative w-full sm:w-72">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                            <Input
                                placeholder="Buscar proposta..."
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                className="pl-9"
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {filtered.length === 0 ? (
                        <div className="flex flex-col items-center py-10 text-surface-400">
                            <FileText className="mb-2 h-10 w-10" />
                            <p>{search ? 'Nenhuma proposta encontrada para a busca.' : 'Nenhuma proposta cadastrada. Crie a primeira!'}</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-surface-500">
                                        <th className="pb-3 pr-4 font-medium">Orcamento</th>
                                        <th className="pb-3 pr-4 font-medium">Negocio</th>
                                        <th className="pb-3 pr-4 font-medium">Status</th>
                                        <th className="pb-3 pr-4 text-right font-medium">Valor</th>
                                        <th className="pb-3 pr-4 text-center font-medium">Visualizacoes</th>
                                        <th className="pb-3 pr-4 text-center font-medium">Tempo</th>
                                        <th className="pb-3 pr-4 font-medium">Expira em</th>
                                        <th className="pb-3 text-right font-medium">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((proposal) => {
                                        const status = STATUS_MAP[proposal.status] ?? { label: proposal.status, variant: 'secondary' as const }
                                        return (
                                            <tr key={proposal.id} className="border-b last:border-0 hover:bg-surface-50">
                                                <td className="py-3 pr-4">
                                                    <span className="font-medium">
                                                        {proposal.quote?.quote_number ?? `#${proposal.quote_id}`}
                                                    </span>
                                                </td>
                                                <td className="py-3 pr-4 text-surface-600">
                                                    {proposal.deal?.title ?? '-'}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <Badge variant={status.variant}>{status.label}</Badge>
                                                </td>
                                                <td className="py-3 pr-4 text-right tabular-nums">
                                                    {proposal.quote?.total != null ? fmtBRL(proposal.quote.total) : '-'}
                                                </td>
                                                <td className="py-3 pr-4 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Eye className="h-3.5 w-3.5 text-surface-400" />
                                                        <span className="tabular-nums">{proposal.view_count}</span>
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Clock className="h-3.5 w-3.5 text-surface-400" />
                                                        <span className="tabular-nums">{fmtTime(proposal.time_spent_seconds)}</span>
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 text-surface-600">
                                                    {fmtDate(proposal.expires_at)}
                                                </td>
                                                <td className="py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => copyLink(proposal.token)}
                                                            title="Copiar link"
                                                        >
                                                            <Copy className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => window.open(`/propostas/${proposal.token}`, '_blank')}
                                                            title="Abrir proposta"
                                                        >
                                                            <ExternalLink className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
