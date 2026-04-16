import { useState, useRef, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth-store'
import { toast } from 'sonner'
import api, { getApiErrorMessage } from '@/lib/api'
import { quoteApi } from '@/lib/quote-api'
import { queryKeys } from '@/lib/query-keys'
import { useAuvoExport } from '@/hooks/useAuvoExport'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { QUOTE_STATUS } from '@/lib/constants'
import { QUOTE_STATUS_CONFIG, isMutableQuoteStatus } from '@/features/quotes/constants'
import type { Quote, QuoteSummary } from '@/types/quote'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import {
    Plus, Search, Send, CheckCircle, Copy, ArrowRightLeft,
    Trash2, FileText, RefreshCw, Download, UploadCloud,
    MessageCircle, Tag, BarChart3, Filter, X
} from 'lucide-react'
import { formatCurrency } from '@/lib/utils'

const STATUSFilterS = [
    { value: '', label: 'Todos' },
    { value: QUOTE_STATUS.DRAFT, label: 'Rascunho' },
    { value: QUOTE_STATUS.PENDING_INTERNAL, label: 'Aguard. Aprov. Interna' },
    { value: QUOTE_STATUS.INTERNALLY_APPROVED, label: 'Aprovado Internamente' },
    { value: QUOTE_STATUS.SENT, label: 'Enviado' },
    { value: QUOTE_STATUS.APPROVED, label: 'Aprovado' },
    { value: QUOTE_STATUS.REJECTED, label: 'Rejeitado' },
    { value: QUOTE_STATUS.EXPIRED, label: 'Expirado' },
    { value: QUOTE_STATUS.IN_EXECUTION, label: 'Em Execução' },
    { value: QUOTE_STATUS.INSTALLATION_TESTING, label: 'Instalação p/ Teste' },
    { value: QUOTE_STATUS.RENEGOTIATION, label: 'Em Renegociação' },
    { value: QUOTE_STATUS.INVOICED, label: 'Faturado' },
]

export function QuotesListPage() {
    const navigate = useNavigate()
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()
    const { exportQuote } = useAuvoExport()

    const [search, setSearch] = useState('')
    const [debouncedSearch, setDebouncedSearch] = useState('')
    const [status, setStatus] = useState('')
    const [sellerId, setSellerId] = useState('')
    const [tagFilter, setTagFilter] = useState('')
    const [dateFrom, setDateFrom] = useState('')
    const [dateTo, setDateTo] = useState('')
    const [source, setSource] = useState('')
    const [totalMin, setTotalMin] = useState('')
    const [totalMax, setTotalMax] = useState('')
    const [showAdvancedFilters, setShowAdvancedFilters] = useState(false)
    const [page, setPage] = useState(1)
    const [deleteTarget, setDeleteTarget] = useState<Quote | null>(null)
    const [selectedIds, setSelectedIds] = useState<number[]>([])
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined)

    const canCreate = hasPermission('quotes.quote.create')
    const canUpdate = hasPermission('quotes.quote.update')
    const canDelete = hasPermission('quotes.quote.delete')
    const canSend = hasPermission('quotes.quote.send')
    const canApprove = hasPermission('quotes.quote.approve')
    const canConvert = hasPermission('quotes.quote.convert')
    const canInternalApprove = hasPermission('quotes.quote.internal_approve')

    useEffect(() => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current)
        }
        debounceRef.current = setTimeout(() => {
            setDebouncedSearch(search)
            setPage(1)
        }, 300)
        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current)
            }
        }
    }, [search])

    const { data: summary } = useQuery<QuoteSummary>({
        queryKey: queryKeys.quotes.summary,
        queryFn: () => quoteApi.summary(),
    })

    const { data: usersRes } = useQuery({
        queryKey: queryKeys.users.list({ per_page: 200 }),
        queryFn: () => api.get('/users', { params: { per_page: 200 } }).then(r => r.data),
    })
    const users = usersRes?.data ?? (Array.isArray(usersRes) ? usersRes : [])

    const { data: tagsData } = useQuery<{ id: number; name: string; color: string }[]>({
        queryKey: queryKeys.quotes.tags,
        queryFn: () => quoteApi.tags(),
    })
    const tags = tagsData ?? []

    const hasActiveFilters = !!(debouncedSearch || status || sellerId || tagFilter || dateFrom || dateTo || source || totalMin || totalMax)

    const clearAllFilters = () => {
        setSearch(''); setDebouncedSearch(''); setStatus(''); setSellerId(''); setTagFilter('')
        setDateFrom(''); setDateTo(''); setSource(''); setTotalMin(''); setTotalMax(''); setPage(1)
    }

    const { data: listData, isLoading, isError, refetch } = useQuery({
        queryKey: queryKeys.quotes.list({ search: debouncedSearch, status, seller_id: sellerId, tag_id: tagFilter, date_from: dateFrom, date_to: dateTo, source, total_min: totalMin, total_max: totalMax, page }),
        queryFn: () => quoteApi.list({
            search: debouncedSearch || undefined,
            status: status || undefined,
            seller_id: sellerId || undefined,
            tag_id: tagFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            source: source || undefined,
            total_min: totalMin || undefined,
            total_max: totalMax || undefined,
            page,
        }).then((r: import('axios').AxiosResponse<{ data?: Quote[]; last_page?: number; total?: number }>) => r.data),
    })
    const quotes: Quote[] = listData?.data ?? []
    const pagination = listData

    const invalidateAll = () => {
        qc.invalidateQueries({ queryKey: queryKeys.quotes.all })
        qc.invalidateQueries({ queryKey: queryKeys.quotes.summary })
        qc.invalidateQueries({ queryKey: queryKeys.quotes.advancedSummary })
        broadcastQueryInvalidation([...queryKeys.quotes.all, ...queryKeys.quotes.summary, ...queryKeys.quotes.advancedSummary, 'dashboard'], 'Orçamento')
    }

    const bulkActionMut = useMutation({
        mutationFn: ({ action, ids }: { action: 'delete' | 'approve' | 'send' | 'export', ids: number[] }) => quoteApi.bulkAction({ action, ids }),
        onSuccess: (data) => {
            const result = data?.data;
            const successCount = result?.success ?? 0;
            const failCount = result?.failed ?? 0;

            if (failCount === 0) {
                toast.success(`Ação concluída com sucesso (${successCount} itens)!`)
            } else {
                toast.warning(`Ação concluída parcialmente: ${successCount} sucesso(s), ${failCount} falha(s).`)
            }
            setSelectedIds([])
            invalidateAll()
        },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao executar ação em massa')),
    })

    const requestInternalApprovalMut = useMutation({
        mutationFn: (id: number) => quoteApi.requestInternalApproval(id),
        onSuccess: () => { toast.success('Solicitação de aprovação interna enviada!'); invalidateAll() },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao solicitar aprovação')),
    })

    const internalApproveMut = useMutation({
        mutationFn: (id: number) => quoteApi.internalApprove(id),
        onSuccess: () => { toast.success('Orçamento aprovado internamente!'); invalidateAll() },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao aprovar internamente')),
    })

    const sendMut = useMutation({
        mutationFn: (id: number) => quoteApi.send(id),
        onSuccess: () => { toast.success('Orçamento enviado com sucesso!'); invalidateAll() },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao enviar orçamento')),
    })

    const approveMut = useMutation({
        mutationFn: (id: number) => quoteApi.approve(id),
        onSuccess: () => { toast.success('Orçamento aprovado!'); invalidateAll() },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao aprovar orçamento')),
    })

    const convertMut = useMutation({
        mutationFn: (id: number) => quoteApi.convertToOs(id, false),
        onSuccess: () => { toast.success('OS criada a partir do orçamento!'); invalidateAll() },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao converter em OS')),
    })

    const duplicateMut = useMutation({
        mutationFn: (id: number) => quoteApi.duplicate(id),
        onSuccess: () => { toast.success('Orçamento duplicado!'); invalidateAll() },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao duplicar orçamento')),
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => quoteApi.destroy(id),
        onSuccess: () => { toast.success('Orçamento excluído!'); setDeleteTarget(null); invalidateAll() },
        onError: (err) => { toast.error(getApiErrorMessage(err, 'Erro ao excluir orçamento')); setDeleteTarget(null) },
    })

    const reopenMut = useMutation({
        mutationFn: (id: number) => quoteApi.reopen(id),
        onSuccess: () => { toast.success('Orçamento reaberto!'); invalidateAll() },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao reabrir orçamento')),
    })

    const handleExportCsv = async () => {
        try {
            const res = await quoteApi.export({ status: status || undefined })
            const url = URL.createObjectURL(new Blob([res.data]))
            const a = document.createElement('a'); a.href = url; a.download = `orçamentos_${new Date().toISOString().slice(0, 10)}.csv`; a.click()
            URL.revokeObjectURL(url)
            toast.success('Exportação concluída!')
        } catch (err) {
            toast.error(getApiErrorMessage(err, 'Erro ao exportar orçamentos'))
        }
    }

    const summaryCards = summary ? [
        { label: 'Rascunho', value: summary.draft, color: 'text-content-secondary' },
        { label: 'Aguard. Aprov. Interna', value: summary.pending_internal_approval ?? 0, color: 'text-amber-600' },
        { label: 'Aprov. Internamente', value: summary.internally_approved ?? 0, color: 'text-teal-600' },
        { label: 'Enviados', value: summary.sent, color: 'text-blue-600' },
        { label: 'Aprovados', value: summary.approved, color: 'text-green-600' },
        { label: 'Rejeitados', value: summary.rejected ?? 0, color: 'text-red-600' },
        { label: 'Faturados', value: summary.invoiced, color: 'text-emerald-600' },
        { label: 'Total do Mês', value: formatCurrency(summary.total_month ?? 0), color: 'text-emerald-600', isCurrency: true },
    ] : []

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-content-primary">Orçamentos</h1>
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" icon={<BarChart3 className="h-4 w-4" />} onClick={() => navigate('/orcamentos/dashboard')}>Dashboard</Button>
                    {hasPermission('quotes.quote.view') && (
                        <Button variant="outline" size="sm" icon={<Download className="h-4 w-4" />} onClick={handleExportCsv}>Exportar CSV</Button>
                    )}
                    {canCreate && (
                        <Button icon={<Plus className="h-4 w-4" />} onClick={() => navigate('/orcamentos/novo')}>Novo Orçamento</Button>
                    )}
                </div>
            </div>

            {summaryCards.length > 0 && (
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8 gap-4">
                    {(summaryCards || []).map((c) => (
                        <Card key={c.label} className="p-4 text-center">
                            <p className="text-xs text-content-secondary mb-1">{c.label}</p>
                            <p className={`text-xl font-bold ${c.color}`}>{c.value}</p>
                        </Card>
                    ))}
                </div>
            )}

            <div className="flex flex-col gap-3">
                <div className="flex flex-col sm:flex-row gap-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-content-tertiary" />
                        <Input
                            placeholder="Buscar por número ou cliente..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-10"
                        />
                    </div>
                    <div className="flex gap-2 flex-wrap">
                        {(STATUSFilterS || []).map((f) => (
                            <button
                                key={f.value}
                                onClick={() => { setStatus(f.value); setPage(1) }}
                                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${status === f.value
                                    ? 'bg-brand-600 text-white'
                                    : 'bg-surface-100 text-content-secondary hover:bg-surface-200'
                                    }`}
                            >
                                {f.label}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <label className="flex items-center gap-2">
                        <span className="text-content-secondary">Vendedor</span>
                        <select
                            value={sellerId}
                            onChange={(e) => { setSellerId(e.target.value); setPage(1) }}
                            className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 min-w-[140px]"
                        >
                            <option value="">Todos</option>
                            {Array.isArray(users) && (users || []).map((u: { id: number; name: string }) => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="flex items-center gap-2">
                        <span className="text-content-secondary">Tag</span>
                        <select
                            value={tagFilter}
                            onChange={(e) => { setTagFilter(e.target.value); setPage(1) }}
                            className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 min-w-[120px]"
                        >
                            <option value="">Todas</option>
                            {(tags || []).map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="flex items-center gap-2">
                        <span className="text-content-secondary">De</span>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => { setDateFrom(e.target.value); setPage(1) }}
                            className="rounded-lg border border-default bg-surface-0 px-3 py-1.5"
                        />
                    </label>
                    <label className="flex items-center gap-2">
                        <span className="text-content-secondary">Até</span>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => { setDateTo(e.target.value); setPage(1) }}
                            className="rounded-lg border border-default bg-surface-0 px-3 py-1.5"
                        />
                    </label>
                    <button
                        type="button"
                        onClick={() => setShowAdvancedFilters(!showAdvancedFilters)}
                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${showAdvancedFilters ? 'bg-brand-100 text-brand-700' : 'bg-surface-100 text-content-secondary hover:bg-surface-200'}`}
                        aria-label="Mostrar filtros avançados"
                        aria-expanded={showAdvancedFilters}
                    >
                        <Filter className="h-3.5 w-3.5" /> Mais filtros
                    </button>
                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={clearAllFilters}
                            className="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 transition-colors"
                            aria-label="Limpar todos os filtros"
                        >
                            <X className="h-3.5 w-3.5" /> Limpar
                        </button>
                    )}
                </div>

                {showAdvancedFilters && (
                    <div className="flex flex-wrap items-center gap-3 text-sm rounded-lg border border-default bg-surface-50 p-3 animate-in fade-in slide-in-from-top-1">
                        <div className="flex items-center gap-2">
                            <span className="text-content-secondary">Origem</span>
                            <div className="min-w-[160px]">
                                <LookupCombobox
                                    lookupType="quote-sources"
                                    valueField="slug"
                                    value={source}
                                    onChange={(v) => { setSource(v ?? ''); setPage(1) }}
                                    placeholder="Todos"
                                    className="w-full bg-surface-0"
                                />
                            </div>
                        </div>
                        <label className="flex items-center gap-2">
                            <span className="text-content-secondary">Valor mín.</span>
                            <input
                                type="number"
                                value={totalMin}
                                onChange={(e) => { setTotalMin(e.target.value); setPage(1) }}
                                placeholder="0,00"
                                min="0"
                                step="0.01"
                                className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 w-[110px]"
                                aria-label="Valor mínimo do orçamento"
                            />
                        </label>
                        <label className="flex items-center gap-2">
                            <span className="text-content-secondary">Valor máx.</span>
                            <input
                                type="number"
                                value={totalMax}
                                onChange={(e) => { setTotalMax(e.target.value); setPage(1) }}
                                placeholder="0,00"
                                min="0"
                                step="0.01"
                                className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 w-[110px]"
                                aria-label="Valor máximo do orçamento"
                            />
                        </label>
                    </div>
                )}
            </div>

            {isError && (
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Erro ao carregar orçamentos. <button onClick={() => refetch()} className="underline ml-1" aria-label="Tentar carregar orçamentos novamente">Tentar novamente</button>
                </div>
            )}

            {selectedIds.length > 0 && (
                <div className="bg-brand-50 border border-brand-200 rounded-lg p-3 flex items-center justify-between shadow-sm animate-in fade-in slide-in-from-top-2">
                    <span className="text-sm font-medium text-brand-700">{selectedIds.length} selecionado(s)</span>
                    <div className="flex items-center gap-2">
                        {canApprove && (
                            <Button variant="outline" size="sm" className="bg-white text-green-700 border-green-200 hover:bg-green-50" onClick={() => bulkActionMut.mutate({ action: 'approve', ids: selectedIds })} disabled={bulkActionMut.isPending}>
                                <CheckCircle className="h-4 w-4 mr-1" /> Aprovar
                            </Button>
                        )}
                        {canSend && (
                            <Button variant="outline" size="sm" className="bg-white text-blue-700 border-blue-200 hover:bg-blue-50" onClick={() => bulkActionMut.mutate({ action: 'send', ids: selectedIds })} disabled={bulkActionMut.isPending}>
                                <Send className="h-4 w-4 mr-1" /> Enviar
                            </Button>
                        )}
                        {canDelete && (
                            <Button variant="outline" size="sm" className="bg-white text-red-700 border-red-200 hover:bg-red-50 hover:text-red-800" onClick={() => {
                                if (confirm(`Excluir ${selectedIds.length} orçamento(s) selecionado(s)? Esta ação não pode ser desfeita e apenas orçamentos em status permitido serão excluídos.`)) {
                                    bulkActionMut.mutate({ action: 'delete', ids: selectedIds })
                                }
                            }} disabled={bulkActionMut.isPending}>
                                <Trash2 className="h-4 w-4 mr-1" /> Excluir
                            </Button>
                        )}
                        <Button variant="ghost" size="sm" className="text-brand-700 hover:bg-brand-100" onClick={() => setSelectedIds([])} disabled={bulkActionMut.isPending}>
                            Cancelar
                        </Button>
                    </div>
                </div>
            )}

            <div className="bg-surface-0 rounded-xl border border-default shadow-card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-subtle">
                        <thead className="bg-surface-50">
                            <tr>
                                <th className="px-4 py-3 text-left w-10">
                                    <input
                                        type="checkbox"
                                        className="rounded border-default text-brand-600 focus:ring-brand-500 cursor-pointer"
                                        checked={quotes.length > 0 && selectedIds.length === quotes.length}
                                        onChange={(e) => {
                                            if (e.target.checked) setSelectedIds(quotes.map(q => q.id))
                                            else setSelectedIds([])
                                        }}
                                        title="Selecionar todos desta página"
                                    />
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-content-secondary uppercase">Número</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-content-secondary uppercase">Cliente</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-content-secondary uppercase">Origem</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-content-secondary uppercase">Vendedor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-content-secondary uppercase">Status</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold text-content-secondary uppercase">Total</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-content-secondary uppercase">Validade</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold text-content-secondary uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-subtle">
                            {isLoading ? (
                                Array.from({ length: 5 }).map((_, i) => (
                                    <tr key={i}>
                                        {Array.from({ length: 9 }).map((_, j) => (
                                            <td key={j} className="px-4 py-3"><div className="h-4 bg-surface-100 rounded animate-pulse" /></td>
                                        ))}
                                    </tr>
                                ))
                            ) : quotes.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-16 text-center">
                                        <FileText className="h-12 w-12 text-content-tertiary mx-auto mb-3" />
                                        <p className="text-content-secondary font-medium">Nenhum orçamento encontrado</p>
                                        <p className="text-sm text-content-tertiary mt-1">
                                            {debouncedSearch || status || sellerId || dateFrom || dateTo ? 'Tente alterar os filtros' : 'Crie seu primeiro orçamento'}
                                        </p>
                                        {canCreate && !debouncedSearch && !status && !sellerId && !dateFrom && !dateTo && (
                                            <Button variant="outline" size="sm" className="mt-4" onClick={() => navigate('/orcamentos/novo')}>
                                                <Plus className="h-4 w-4 mr-1" /> Novo Orçamento
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ) : (
                                (quotes || []).map((q) => {
                                    const cfg = QUOTE_STATUS_CONFIG[q.status] ?? { label: q.status, variant: 'default', icon: FileText }
                                    const isDraft = q.status === QUOTE_STATUS.DRAFT
                                    const isInternallyApproved = q.status === QUOTE_STATUS.INTERNALLY_APPROVED
                                    const isSent = q.status === QUOTE_STATUS.SENT
                                    const isApproved = q.status === QUOTE_STATUS.APPROVED
                                    const isConvertible = isApproved || isInternallyApproved
                                    const isRejected = q.status === QUOTE_STATUS.REJECTED
                                    const isExpired = q.status === QUOTE_STATUS.EXPIRED
                                    const isPendingInternal = q.status === QUOTE_STATUS.PENDING_INTERNAL
                                    const isMutable = isMutableQuoteStatus(q.status)

                                    return (
                                        <tr key={q.id} className="hover:bg-surface-50 transition-colors duration-100 cursor-pointer" onClick={() => navigate(`/orcamentos/${q.id}`)}>
                                            <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Selecionar orçamento ${q.quote_number}`}
                                                    className="rounded border-default text-brand-600 focus:ring-brand-500 cursor-pointer"
                                                    checked={selectedIds.includes(q.id)}
                                                    onChange={(e) => {
                                                        if (e.target.checked) setSelectedIds([...selectedIds, q.id])
                                                        else setSelectedIds(selectedIds.filter(id => id !== q.id))
                                                    }}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-medium text-brand-600">{q.quote_number}</span>
                                                {q.revision > 1 && <span className="text-xs text-content-tertiary ml-1">rev.{q.revision}</span>}
                                            </td>
                                            <td className="px-4 py-3 text-content-primary">{q.customer?.name ?? '—'}</td>
                                            <td className="px-4 py-3 text-content-secondary text-sm">{q.source ?? '—'}</td>
                                            <td className="px-4 py-3 text-content-secondary text-sm">{q.seller?.name ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                {q.tags && q.tags.length > 0 && (
                                                    <div className="flex gap-1 mt-1 flex-wrap">
                                                        {(q.tags || []).map((t: { id: number; name: string; color: string | null }) => (
                                                            <span key={t.id} className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium" style={{ backgroundColor: `${t.color ?? ''}20`, color: t.color ?? '' }}>
                                                                <Tag className="h-2.5 w-2.5" />{t.name}
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium">{formatCurrency(q.total)}</td>
                                            <td className="px-4 py-3 text-content-secondary text-sm">
                                                {q.valid_until ? (() => {
                                                    const daysLeft = Math.ceil((new Date(q.valid_until).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
                                                    const dateStr = new Date(q.valid_until).toLocaleDateString('pt-BR')
                                                    if (daysLeft < 0 && ['sent', 'pending_internal_approval', 'internally_approved'].includes(q.status)) {
                                                        return <span className="text-red-600 font-medium" title="Expirado">{dateStr}</span>
                                                    }
                                                    if (daysLeft >= 0 && daysLeft <= 3 && q.status === 'sent') {
                                                        return <span className="text-orange-600 font-medium" title={`Expira em ${daysLeft} dia(s)`}>{dateStr}</span>
                                                    }
                                                    return dateStr
                                                })() : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right" onClick={(e) => e.stopPropagation()}>
                                                <div className="flex gap-1 justify-end">
                                                    {canSend && isDraft && (
                                                        <button title="Solicitar Aprovação Interna" aria-label="Solicitar aprovação interna do orçamento" onClick={() => requestInternalApprovalMut.mutate(q.id)} className="p-1.5 rounded hover:bg-surface-100 text-amber-600" disabled={requestInternalApprovalMut.isPending}>
                                                            <Send className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {(canInternalApprove && (isDraft || q.status === QUOTE_STATUS.PENDING_INTERNAL)) && (
                                                        <button type="button" aria-label="Aprovar orçamento internamente" title="Aprovar internamente" onClick={() => internalApproveMut.mutate(q.id)} className="p-1.5 rounded hover:bg-surface-100 text-teal-600" disabled={internalApproveMut.isPending}>
                                                            <CheckCircle className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {canSend && isInternallyApproved && (
                                                        <button type="button" aria-label="Enviar orçamento ao cliente" title="Enviar ao Cliente" onClick={() => sendMut.mutate(q.id)} className="p-1.5 rounded hover:bg-surface-100 text-blue-600" disabled={sendMut.isPending}>
                                                            <Send className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {hasPermission('auvo.export.execute') && (
                                                        <button type="button" aria-label="Exportar orçamento para Auvo" title="Exportar para Auvo" onClick={() => exportQuote.mutate(q.id)} className="p-1.5 rounded hover:bg-surface-100 text-cyan-600" disabled={exportQuote.isPending}>
                                                            <UploadCloud className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {canApprove && isSent && (
                                                        <button type="button" aria-label="Aprovar orçamento" title="Aprovar" onClick={() => approveMut.mutate(q.id)} className="p-1.5 rounded hover:bg-surface-100 text-green-600" disabled={approveMut.isPending}>
                                                            <CheckCircle className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {canConvert && isConvertible && (
                                                        <button type="button" aria-label="Converter orçamento em ordem de serviço" title="Converter em OS" onClick={() => convertMut.mutate(q.id)} className="p-1.5 rounded hover:bg-surface-100 text-emerald-600" disabled={convertMut.isPending}>
                                                            <ArrowRightLeft className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {canUpdate && (isRejected || isExpired) && (
                                                        <button type="button" aria-label="Reabrir orçamento" title="Reabrir" onClick={() => reopenMut.mutate(q.id)} className="p-1.5 rounded hover:bg-surface-100 text-amber-600" disabled={reopenMut.isPending}>
                                                            <RefreshCw className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {canCreate && (
                                                        <button type="button" aria-label="Duplicar orçamento" title="Duplicar" onClick={() => duplicateMut.mutate(q.id)} className="p-1.5 rounded hover:bg-surface-100 text-content-secondary" disabled={duplicateMut.isPending}>
                                                            <Copy className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {canSend && isSent && (
                                                        <button type="button" aria-label="Gerar link de WhatsApp do orçamento" title="Enviar WhatsApp" onClick={async () => {
                                                            try {
                                                                const res = await quoteApi.getWhatsAppUrl(q.id)
                                                                const url = res?.data?.data?.url ?? res?.data?.url
                                                                if (url) {
                                                                    window.open(url, '_blank')
                                                                } else {
                                                                    toast.error('Link de WhatsApp não disponível')
                                                                }
                                                            } catch (err) { toast.error(getApiErrorMessage(err, 'Erro ao gerar link WhatsApp')) }
                                                        }} className="p-1.5 rounded hover:bg-green-50 text-green-600">
                                                            <MessageCircle className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {canDelete && isMutable && (
                                                        <button type="button" aria-label="Excluir orçamento" title="Excluir" onClick={() => setDeleteTarget(q)} className="p-1.5 rounded hover:bg-red-50 text-red-600" disabled={deleteMut.isPending}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    )
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {pagination && (pagination.last_page ?? 1) > 1 && (
                    <div className="flex items-center justify-between px-4 py-3 border-t border-default">
                        <span className="text-sm text-content-secondary">
                            Mostrando {(pagination as { from?: number; to?: number }).from ?? (page - 1) * 20 + 1}–{(pagination as { from?: number; to?: number }).to ?? Math.min(page * 20, pagination.total ?? 0)} de {pagination.total ?? 0}
                        </span>
                        <div className="flex gap-1">
                            {Array.from({ length: pagination.last_page ?? 1 }, (_: unknown, i: number) => i + 1).slice(
                                Math.max(0, page - 3), Math.min(pagination.last_page ?? 1, page + 2)
                            ).map((p: number) => (
                                <button
                                    key={p}
                                    onClick={() => setPage(p)}
                                    className={`px-3 py-1 rounded text-sm ${page === p ? 'bg-brand-600 text-white' : 'hover:bg-surface-100 text-content-secondary'}`}
                                >
                                    {p}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setDeleteTarget(null)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-sm mx-4 shadow-elevated" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">Excluir Orçamento</h3>
                        <p className="text-content-secondary text-sm mb-6">
                            Tem certeza que deseja excluir o orçamento <strong>{deleteTarget.quote_number}</strong>? Esta ação não pode ser desfeita.
                        </p>
                        <div className="flex gap-3 justify-end">
                            <Button variant="outline" size="sm" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
                            <Button
                                variant="danger"
                                size="sm"
                                onClick={() => deleteMut.mutate(deleteTarget.id)}
                                disabled={deleteMut.isPending}
                            >
                                {deleteMut.isPending ? 'Excluindo...' : 'Excluir'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
