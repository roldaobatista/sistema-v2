import React, { useState, useEffect, type ElementType } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Users, Search, Phone, Mail, ArrowRight, RefreshCw, UserPlus, AlertTriangle, AlertOctagon, Clock, CheckCircle, Loader2, Download, LinkIcon, MessageCircle, FileText } from 'lucide-react'
import { useInmetroAutoSync } from '@/hooks/useInmetroAutoSync'
import {
    useInmetroLeads,
    useEnrichOwner,
    useConvertToCustomer,
    useEnrichBatch,
    useDeleteOwner,
    useConversionStats,
    useCrossReference,
    useCrossReferenceStats,
    useExportLeadsPdf,
    getInmetroStatsPayload,
    type InmetroOwner
} from '@/hooks/useInmetro'
import { useAuthStore } from '@/stores/auth-store'
import { Modal } from '@/components/ui/modal'
import { toast } from 'sonner'
import api, { getApiErrorMessage } from '@/lib/api'
import { InmetroOwnerEditModal } from './InmetroOwnerEditModal'
import { InmetroStatusUpdateModal } from './InmetroStatusUpdateModal'

type LeadsPagination = {
    data: InmetroOwner[]
    total?: number
    from?: number
    to?: number
    last_page?: number
}

type ConversionStats = {
    total_leads?: number
    converted?: number
    conversion_rate?: number
    avg_days_to_convert?: number | null
}

type CrossReferenceStats = {
    linked?: number
    total_owners?: number
    link_percentage?: number
}

type EditableOwner = {
    id: number
    name?: string
    trade_name?: string
    phone?: string
    phone2?: string
    email?: string
    notes?: string
}

const priorityConfig: Record<string, { label: string; color: string; icon: ElementType; pulse?: boolean }> = {
    critical: { label: 'CRÍTICO', color: 'bg-red-600 text-white border-red-700', icon: AlertOctagon, pulse: true },
    urgent: { label: 'Urgente', color: 'bg-red-100 text-red-700 border-red-200', icon: AlertTriangle },
    high: { label: 'Alta', color: 'bg-amber-100 text-amber-700 border-amber-200', icon: Clock },
    normal: { label: 'Normal', color: 'bg-blue-100 text-blue-700 border-blue-200', icon: Clock },
    low: { label: 'Baixa', color: 'bg-surface-100 text-surface-600 border-surface-200', icon: CheckCircle },
}

const statusConfig: Record<string, { label: string; color: string }> = {
    new: { label: 'Novo', color: 'bg-blue-500' },
    contacted: { label: 'Contactado', color: 'bg-amber-500' },
    negotiating: { label: 'Negociando', color: 'bg-teal-500' },
    converted: { label: 'Convertido', color: 'bg-green-500' },
    lost: { label: 'Perdido', color: 'bg-surface-400' },
}

function unwrapLeadsPagination(payload: LeadsPagination | { data?: LeadsPagination } | null | undefined): LeadsPagination | null {
    if (!payload) return null
    if (Array.isArray(payload.data)) return payload
    return payload.data ?? null
}

function unwrapConversionStats(payload: ConversionStats | { data?: ConversionStats } | null | undefined): ConversionStats | null {
    if (!payload) return null
    if ('converted' in payload || 'total_leads' in payload || 'conversion_rate' in payload) return payload
    return payload.data ?? null
}

function unwrapCrossReferenceStats(payload: CrossReferenceStats | { data?: CrossReferenceStats } | null | undefined): CrossReferenceStats | null {
    if (!payload) return null
    if ('linked' in payload || 'total_owners' in payload || 'link_percentage' in payload) return payload
    return payload.data ?? null
}

function toEditableOwner(owner: InmetroOwner | null): EditableOwner | null {
    if (!owner) return null

    return {
        id: owner.id,
        name: owner.name ?? undefined,
        trade_name: owner.trade_name ?? undefined,
        phone: owner.phone ?? undefined,
        phone2: owner.phone2 ?? undefined,
        email: owner.email ?? undefined,
        notes: owner.notes ?? undefined,
    }
}

export function InmetroLeadsPage() {
    const { hasPermission } = useAuthStore()
    const canEnrich = hasPermission('inmetro.intelligence.enrich')
    const canConvert = hasPermission('inmetro.intelligence.convert')

    const [searchParams] = useSearchParams()
    const [searchInput, setSearchInput] = useState('')
    const [debouncedSearch, setDebouncedSearch] = useState('')

    const [filters, setFilters] = useState({
        priority: '', city: '', type: '',
        lead_status: searchParams.get('lead_status') || '',
        search: '', per_page: 25, page: 1,
    })
    const [selectedIds, setSelectedIds] = useState<number[]>([])
    const [convertTargetId, setConvertTargetId] = useState<number | null>(null)
    const [ownerToEdit, setOwnerToEdit] = useState<InmetroOwner | null>(null)
    const [ownerToDelete, setOwnerToDelete] = useState<number | null>(null)
    const [statusUpdateTarget, setStatusUpdateTarget] = useState<{ id: number; status: string } | null>(null)

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(searchInput), 300)
        return () => clearTimeout(timer)
    }, [searchInput])

    useEffect(() => {
        if (debouncedSearch !== filters.search) {
            setFilters(prev => ({ ...prev, search: debouncedSearch, page: 1 }))
        }
    }, [debouncedSearch])

    const { data, isLoading } = useInmetroLeads(filters)
    const enrichMutation = useEnrichOwner()
    const enrichBatchMutation = useEnrichBatch()
    const convertMutation = useConvertToCustomer()
    const deleteOwnerMutation = useDeleteOwner()
    const crossRefMutation = useCrossReference()
    const exportPdf = useExportLeadsPdf()

    const pagination = unwrapLeadsPagination(data as LeadsPagination | { data?: LeadsPagination } | null | undefined)
    const leads: InmetroOwner[] = pagination?.data ?? []
    const { data: convStatsData } = useConversionStats()
    const { data: crossRefStatsData } = useCrossReferenceStats()
    const { isSyncing, triggerSync } = useInmetroAutoSync()
    const convStats = unwrapConversionStats(convStatsData as ConversionStats | { data?: ConversionStats } | null | undefined)
    const crossRefStats = unwrapCrossReferenceStats(crossRefStatsData as CrossReferenceStats | { data?: CrossReferenceStats } | null | undefined)
    const editableOwner = toEditableOwner(ownerToEdit)

    const handleEnrich = (ownerId: number) => {
        enrichMutation.mutate(ownerId, {
            onSuccess: () => toast.success('Contato enriquecido com sucesso'),
            onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao enriquecer contato')),
        })
    }

    const handleBatchEnrich = () => {
        if (selectedIds.length === 0) return toast.warning('Selecione leads para enriquecer')
        enrichBatchMutation.mutate(selectedIds, {
            onSuccess: (res: { data?: { stats?: { enriched?: number; failed?: number; skipped?: number } } }) => {
                const stats = getInmetroStatsPayload(res.data)
                toast.success(`${stats?.enriched ?? 0} enriquecidos, ${stats?.failed ?? 0} falhas, ${stats?.skipped ?? 0} ignorados`)
                setSelectedIds([])
            },
            onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro no enriquecimento em lote')),
        })
    }

    const confirmConvert = () => {
        if (!convertTargetId) return
        convertMutation.mutate(convertTargetId, {
            onSuccess: () => {
                toast.success('Lead convertido em cliente CRM!')
                setConvertTargetId(null)
            },
            onError: (err: unknown) => {
                toast.error(getApiErrorMessage(err, 'Erro na conversão'))
                setConvertTargetId(null)
            },
        })
    }

    const handleStatusChange = (ownerId: number, leadStatus: string) => {
        setStatusUpdateTarget({ id: ownerId, status: leadStatus })
    }

    const toggleSelect = (id: number) => {
        setSelectedIds(prev => prev.includes(id) ? (prev || []).filter(x => x !== id) : [...prev, id])
    }

    const toggleAll = () => {
        if (selectedIds.length === leads.length) {
            setSelectedIds([])
        } else {
            setSelectedIds((leads || []).map((l) => l.id))
        }
    }

    return (
        <>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-surface-900">
                            Leads INMETRO
                            {pagination?.total != null && (
                                <span className="ml-2 text-sm font-normal text-surface-500">({pagination.total} registros)</span>
                            )}
                        </h1>
                        <p className="text-sm text-surface-500">Proprietários com equipamentos próximos do vencimento</p>
                    </div>
                    {selectedIds.length > 0 && canEnrich && (
                        <button
                            onClick={handleBatchEnrich}
                            disabled={enrichBatchMutation.isPending}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 transition-colors disabled:opacity-50"
                        >
                            <RefreshCw className={`h-4 w-4 ${enrichBatchMutation.isPending ? 'animate-spin' : ''}`} />
                            Enriquecer {selectedIds.length} selecionados
                        </button>
                    )}
                    {canConvert && (
                        <button
                            onClick={async () => {
                                try {
                                    const res = await api.get('/inmetro/export/leads', { params: filters, responseType: 'blob' })
                                    const url = window.URL.createObjectURL(new Blob([res.data]))
                                    const link = document.createElement('a')
                                    link.href = url
                                    link.setAttribute('download', `leads-inmetro-${new Date().toISOString().slice(0, 10)}.csv`)
                                    document.body.appendChild(link)
                                    link.click()
                                    link.remove()
                                    window.URL.revokeObjectURL(url)
                                    toast.success('CSV exportado com sucesso')
                                } catch (err: unknown) { toast.error(getApiErrorMessage(err, 'Erro ao exportar CSV')) }
                            }}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm font-medium text-surface-700 hover:bg-surface-50 transition-colors"
                        >
                            <Download className="h-4 w-4" />
                            Exportar CSV
                        </button>
                    )}
                    {canConvert && (
                        <button
                            onClick={() => crossRefMutation.mutate()}
                            disabled={crossRefMutation.isPending}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-green-300 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 hover:bg-green-100 transition-colors disabled:opacity-50"
                        >
                            {crossRefMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <LinkIcon className="h-4 w-4" />}
                            Cross-Reference CRM
                        </button>
                    )}
                    <button
                        onClick={triggerSync}
                        disabled={isSyncing}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm font-medium text-surface-700 hover:bg-surface-50 transition-colors disabled:opacity-50"
                    >
                        {isSyncing ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                        Atualizar dados
                    </button>
                    <button
                        onClick={() => exportPdf.mutate()}
                        disabled={exportPdf.isPending}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm font-medium text-surface-700 hover:bg-surface-50 transition-colors disabled:opacity-50"
                    >
                        {exportPdf.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileText className="h-4 w-4" />}
                        Relatório PDF
                    </button>
                </div>

                {/* Sync Banner */}
                {isSyncing && (
                    <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 flex items-center gap-3 animate-pulse">
                        <Loader2 className="h-5 w-5 text-blue-600 animate-spin shrink-0" />
                        <div>
                            <p className="text-sm font-medium text-blue-800">Buscando dados do INMETRO...</p>
                            <p className="text-xs text-blue-600">Importando proprietários e instrumentos do portal RBMLQ.</p>
                        </div>
                    </div>
                )}

                {/* KPI Cards */}
                {convStats && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div className="rounded-xl border border-default bg-surface-0 p-4">
                            <p className="text-xs text-surface-500 font-medium">Total Leads</p>
                            <p className="text-2xl font-bold text-surface-900 mt-1">{convStats.total_leads}</p>
                        </div>
                        <div className="rounded-xl border border-default bg-surface-0 p-4">
                            <p className="text-xs text-surface-500 font-medium">Convertidos</p>
                            <p className="text-2xl font-bold text-green-600 mt-1">{convStats.converted}</p>
                        </div>
                        <div className="rounded-xl border border-default bg-surface-0 p-4">
                            <p className="text-xs text-surface-500 font-medium">Taxa de Conversão</p>
                            <p className="text-2xl font-bold text-brand-600 mt-1">{convStats.conversion_rate}%</p>
                        </div>
                        <div className="rounded-xl border border-default bg-surface-0 p-4">
                            <p className="text-xs text-surface-500 font-medium">Tempo Médio (dias)</p>
                            <p className="text-2xl font-bold text-amber-600 mt-1">{convStats.avg_days_to_convert ?? '—'}</p>
                        </div>
                        {crossRefStats && (
                            <div className="rounded-xl border border-green-200 bg-green-50 p-4">
                                <p className="text-xs text-green-700 font-medium">Vinculados ao CRM</p>
                                <p className="text-2xl font-bold text-green-600 mt-1">{crossRefStats.linked}</p>
                                <p className="text-xs text-green-500">{crossRefStats.link_percentage}% de {crossRefStats.total_owners}</p>
                            </div>
                        )}
                    </div>
                )}

                {/* Filters */}
                <div className="flex flex-wrap gap-3">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
                        <input
                            type="text"
                            placeholder="Buscar por nome ou documento..."
                            value={searchInput}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearchInput(e.target.value)}
                            className="pl-9 rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm w-64"
                        />
                    </div>
                    <select
                        value={filters.priority}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setFilters({ ...filters, priority: e.target.value, page: 1 })}
                        className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm"
                        aria-label="Filtrar por prioridade"
                    >
                        <option value="">Todas prioridades</option>
                        <option value="urgent">🔴 Urgente</option>
                        <option value="high">🟡 Alta</option>
                        <option value="normal">🔵 Normal</option>
                        <option value="low">⚪ Baixa</option>
                    </select>
                    <select
                        value={filters.lead_status}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setFilters({ ...filters, lead_status: e.target.value, page: 1 })}
                        className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm"
                        aria-label="Filtrar por status"
                    >
                        <option value="">Todos status</option>
                        {Object.entries(statusConfig).map(([key, cfg]) => (
                            <option key={key} value={key}>{cfg.label}</option>
                        ))}
                    </select>
                    <input
                        type="text"
                        placeholder="Filtrar por cidade..."
                        value={filters.city}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFilters({ ...filters, city: e.target.value, page: 1 })}
                        className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm w-48"
                    />
                    <select
                        value={filters.type}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setFilters({ ...filters, type: e.target.value, page: 1 })}
                        className="rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm"
                        aria-label="Filtrar por tipo"
                    >
                        <option value="">Todos tipos</option>
                        <option value="PJ">🏢 PJ</option>
                        <option value="PF">👤 PF</option>
                    </select>
                </div>

                {/* Loading skeleton */}
                {isLoading ? (
                    <div className="space-y-3 animate-pulse">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="h-16 bg-surface-100 rounded-xl" />
                        ))}
                    </div>
                ) : leads.length === 0 && !isSyncing ? (
                    <div className="text-center py-16">
                        <Users className="h-12 w-12 text-surface-300 mx-auto mb-3" />
                        <p className="text-surface-500 text-sm">Nenhum lead encontrado.</p>
                        <button
                            onClick={triggerSync}
                            className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors"
                        >
                            <RefreshCw className="h-4 w-4" /> Buscar dados do INMETRO
                        </button>
                    </div>
                ) : leads.length === 0 && isSyncing ? null : (
                    <>
                        {/* Table */}
                        <div className="overflow-x-auto rounded-xl border border-default bg-surface-0">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-default bg-surface-50">
                                        <th className="px-3 py-2.5 text-left">
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.length === leads.length && leads.length > 0}
                                                onChange={toggleAll}
                                                className="rounded border-surface-300"
                                                aria-label="Selecionar todos"
                                            />
                                        </th>
                                        <th className="px-3 py-2.5 text-left font-medium text-surface-600">Prioridade</th>
                                        <th className="px-3 py-2.5 text-left font-medium text-surface-600">Proprietário</th>
                                        <th className="px-3 py-2.5 text-left font-medium text-surface-600">Documento</th>
                                        <th className="px-3 py-2.5 text-left font-medium text-surface-600">Cidade(s)</th>
                                        <th className="px-3 py-2.5 text-left font-medium text-surface-600">Equip.</th>
                                        <th className="px-3 py-2.5 text-left font-medium text-surface-600">Contato</th>
                                        <th className="px-3 py-2.5 text-left font-medium text-surface-600">Status</th>
                                        <th className="px-3 py-2.5 text-left font-medium text-surface-600">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(leads || []).map((lead) => {
                                        const pri = priorityConfig[lead.priority] || priorityConfig.normal
                                        const PriorityIcon = pri.icon
                                        const cities = (lead.locations || []).map(l => l.address_city).join(', ') || '—'
                                        return (
                                            <tr key={lead.id} className="border-b border-subtle hover:bg-surface-25 transition-colors">
                                                <td className="px-3 py-2.5">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedIds.includes(lead.id)}
                                                        onChange={() => toggleSelect(lead.id)}
                                                        className="rounded border-surface-300"
                                                        aria-label={`Selecionar ${lead.name}`}
                                                    />
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    <span className={`inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full border ${pri.color}`}>
                                                        <PriorityIcon className="h-3 w-3" /> {pri.label}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    <Link to={`/inmetro/owners/${lead.id}`} className="font-medium text-brand-700 hover:text-brand-800 hover:underline">
                                                        {lead.name}
                                                    </Link>
                                                    {lead.trade_name && <p className="text-xs text-surface-500">{lead.trade_name}</p>}
                                                    {lead.converted_to_customer_id && (
                                                        <Link to={`/cadastros/clientes/${lead.converted_to_customer_id}`} className="inline-flex items-center gap-1 mt-0.5 text-xs text-green-600 hover:text-green-700">
                                                            <CheckCircle className="h-3 w-3" /> Convertido
                                                        </Link>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2.5 text-surface-600 font-mono text-xs">{lead.document}</td>
                                                <td className="px-3 py-2.5 text-surface-600 text-xs max-w-32 truncate">{cities}</td>
                                                <td className="px-3 py-2.5 text-center font-bold text-surface-700">{lead.instruments_count ?? 0}</td>
                                                <td className="px-3 py-2.5">
                                                    <div className="flex items-center gap-1">
                                                        {lead.phone && (
                                                            <>
                                                                <span title={lead.phone}><Phone className="h-3.5 w-3.5 text-green-500" /></span>
                                                                <a
                                                                    href={`https://wa.me/55${lead.phone.replace(/\D/g, '')}?text=${encodeURIComponent(`Olá ${lead.name}, somos da Solution e identificamos que seu instrumento necessita de verificação metrológica. Podemos ajudar?`)}`}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="p-0.5 rounded hover:bg-green-100 text-green-600 transition-colors"
                                                                    title="Contato via WhatsApp"
                                                                >
                                                                    <MessageCircle className="h-3.5 w-3.5" />
                                                                </a>
                                                            </>
                                                        )}
                                                        {!lead.phone && lead.email && <span title={lead.email}><Mail className="h-3.5 w-3.5 text-blue-500" /></span>}
                                                        {!lead.phone && !lead.email && <span className="text-xs text-surface-400">—</span>}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    <select
                                                        value={lead.lead_status}
                                                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => handleStatusChange(lead.id, e.target.value)}
                                                        className="text-xs rounded border border-default bg-surface-0 px-1.5 py-0.5"
                                                    >
                                                        {Object.entries(statusConfig).map(([key, cfg]) => (
                                                            <option key={key} value={key}>{cfg.label}</option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    <div className="flex items-center gap-1">
                                                        {canEnrich && (
                                                            <button
                                                                onClick={() => handleEnrich(lead.id)}
                                                                disabled={enrichMutation.isPending}
                                                                className="p-1 rounded hover:bg-surface-100 text-surface-500 hover:text-brand-600 transition-colors"
                                                                title="Enriquecer contato"
                                                            >
                                                                <RefreshCw className="h-3.5 w-3.5" />
                                                            </button>
                                                        )}
                                                        {canConvert && !lead.converted_to_customer_id && (
                                                            <button
                                                                onClick={() => setConvertTargetId(lead.id)}
                                                                disabled={convertMutation.isPending}
                                                                className="p-1 rounded hover:bg-surface-100 text-surface-500 hover:text-green-600 transition-colors"
                                                                title="Converter em cliente CRM"
                                                            >
                                                                <UserPlus className="h-3.5 w-3.5" />
                                                            </button>
                                                        )}
                                                        <button
                                                            onClick={() => setOwnerToEdit(lead)}
                                                            className="p-1 rounded hover:bg-surface-100 text-surface-500 hover:text-brand-600 transition-colors"
                                                            title="Editar"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" /><path d="m15 5 4 4" /></svg>
                                                        </button>
                                                        <button
                                                            onClick={() => setOwnerToDelete(lead.id)}
                                                            className="p-1 rounded hover:bg-surface-100 text-surface-500 hover:text-red-600 transition-colors"
                                                            title="Excluir"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18" /><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" /><line x1="10" x2="10" y1="11" y2="17" /><line x1="14" x2="14" y1="11" y2="17" /></svg>
                                                        </button>
                                                        <Link
                                                            to={`/inmetro/owners/${lead.id}`}
                                                            className="p-1 rounded hover:bg-surface-100 text-surface-500 hover:text-brand-600 transition-colors"
                                                            title="Ver detalhes"
                                                        >
                                                            <ArrowRight className="h-3.5 w-3.5" />
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {pagination && (pagination.last_page ?? 0) > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-surface-500">
                                    {pagination.from}–{pagination.to} de {pagination.total}
                                </p>
                                <div className="flex gap-1">
                                    {Array.from({ length: Math.min(pagination.last_page, 10) }, (_, i) => i + 1).map(page => (
                                        <button
                                            key={page}
                                            onClick={() => setFilters({ ...filters, page })}
                                            className={`px-2.5 py-1 text-xs rounded-md transition-colors ${filters.page === page
                                                ? 'bg-brand-600 text-white'
                                                : 'bg-surface-100 text-surface-600 hover:bg-surface-200'
                                                }`}
                                        >
                                            {page}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Status Update Modal */}
            <InmetroStatusUpdateModal
                open={!!statusUpdateTarget}
                onOpenChange={(open: boolean) => !open && setStatusUpdateTarget(null)}
                ownerId={statusUpdateTarget?.id ?? null}
                newStatus={statusUpdateTarget?.status ?? null}
                newStatusLabel={statusUpdateTarget ? statusConfig[statusUpdateTarget.status]?.label : ''}
                onSuccess={() => setStatusUpdateTarget(null)}
            />

            {/* Convert Confirmation Modal */}
            <Modal
                open={convertTargetId !== null}
                onOpenChange={(open: boolean) => !open && setConvertTargetId(null)}
                title="Converter Lead em Cliente"
                description="Deseja realmente converter este lead INMETRO em um cliente CRM?"
                size="sm"
            >
                <div className="flex items-center justify-end gap-3 pt-2">
                    <button
                        onClick={() => setConvertTargetId(null)}
                        className="rounded-lg border border-default px-4 py-2 text-sm font-medium text-surface-700 hover:bg-surface-50 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={confirmConvert}
                        disabled={convertMutation.isPending}
                        className="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors disabled:opacity-50"
                    >
                        {convertMutation.isPending ? 'Convertendo...' : 'Confirmar Conversão'}
                    </button>
                </div>
            </Modal>

            {/* Edit Owner Modal */}
            <InmetroOwnerEditModal
                open={!!ownerToEdit}
                onOpenChange={(open: boolean) => !open && setOwnerToEdit(null)}
                owner={editableOwner}
            />

            {/* Delete Confirmation */}
            <Modal
                open={!!ownerToDelete}
                onOpenChange={(open: boolean) => !open && setOwnerToDelete(null)}
                title="Excluir Lead"
                description="Tem certeza que deseja excluir este lead? Esta ação não pode ser desfeita e removerá todos os instrumentos e localizações associados."
                size="sm"
            >
                <div className="flex items-center justify-end gap-3 pt-2">
                    <button
                        onClick={() => setOwnerToDelete(null)}
                        className="rounded-lg border border-default px-4 py-2 text-sm font-medium text-surface-700 hover:bg-surface-50 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={() => {
                            if (ownerToDelete) {
                                deleteOwnerMutation.mutate(ownerToDelete, {
                                    onSuccess: () => {
                                        toast.success('Lead excluído com sucesso')
                                        setOwnerToDelete(null)
                                    },
                                    onError: (err: unknown) => {
                                        toast.error(getApiErrorMessage(err, 'Erro ao excluir lead'))
                                    },
                                })
                            }
                        }}
                        disabled={deleteOwnerMutation.isPending}
                        className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 transition-colors disabled:opacity-50"
                    >
                        {deleteOwnerMutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
                        Excluir
                    </button>
                </div>
            </Modal>
        </>
    )
}

export default InmetroLeadsPage
