import React, { useState, useEffect } from 'react'
import { toast } from 'sonner'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import {
    Search, Plus, Scale, AlertTriangle, Clock, Eye, ChevronLeft, ChevronRight, Shield, Download, Trash2
} from 'lucide-react'
import { equipmentApi } from '@/lib/equipment-api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { getApiErrorMessage } from '@/lib/api'
import { extractDeleteConflict } from '@/types/api'
import { cn } from '@/lib/utils'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { useAuthStore } from '@/stores/auth-store'
import { useDebounce } from '@/hooks/useDebounce'
import { queryKeys } from '@/lib/query-keys'
import type { Equipment } from '@/types/equipment'
import type { EquipmentDashboardData } from '@/types/equipment'

const statusColors: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700',
    in_calibration: 'bg-blue-100 text-blue-700',
    in_maintenance: 'bg-amber-100 text-amber-700',
    out_of_service: 'bg-surface-200 text-surface-600',
    discarded: 'bg-red-100 text-red-700',
}

const statusLabels: Record<string, string> = {
    active: 'Ativo',
    in_calibration: 'Em Calibração',
    in_maintenance: 'Em Manutenção',
    out_of_service: 'Fora de Uso',
    discarded: 'Descartado',
}

function calibrationBadge(nextDate: string | null) {
    if (!nextDate) return { className: 'bg-surface-100 text-surface-500', label: 'Sem data' }
    const d = new Date(nextDate)
    const now = new Date()
    const diffDays = Math.ceil((d.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
    if (diffDays < 0) return { className: 'bg-red-100 text-red-700', label: `Vencida (${Math.abs(diffDays)}d)` }
    if (diffDays <= 30) return { className: 'bg-amber-100 text-amber-700', label: `${diffDays}d restantes` }
    return { className: 'bg-emerald-100 text-emerald-700', label: `${diffDays}d` }
}

export default function EquipmentListPage() {
    const navigate = useNavigate()
    const { hasPermission } = useAuthStore()
    const canCreate = hasPermission('equipments.equipment.create')
    const canDelete = hasPermission('equipments.equipment.delete')
    const canExport = hasPermission('equipments.equipment.view')
    const qc = useQueryClient()

    const [search, setSearch] = useState('')
    const debouncedSearch = useDebounce(search, 300)
    const [filterCategory, setFilterCategory] = useState('')
    const [filterStatus, setFilterStatus] = useState('')
    const [filterOverdue, setFilterOverdue] = useState(false)
    const [page, setPage] = useState(1)

    const [showConfirmDelete, setShowConfirmDelete] = useState<Equipment | null>(null)
    const [deleteDependencies, setDeleteDependencies] = useState<Record<string, number> | null>(null)
    const [deleteMessage, setDeleteMessage] = useState<string | null>(null)

    const { data: dashboard } = useQuery<EquipmentDashboardData>({
        queryKey: queryKeys.equipment.dashboard,
        queryFn: async () => {
            const d = await equipmentApi.dashboard()
            return d ?? { total: 0, overdue: 0, due_7_days: 0, due_30_days: 0, critical_count: 0, by_category: {}, by_status: {} }
        },
    })

    const { data: constants } = useQuery({
        queryKey: queryKeys.equipment.constants,
        queryFn: () => equipmentApi.constants(),
    })

    const { data: pageData, isLoading, isError, error } = useQuery({
        queryKey: queryKeys.equipment.list({ search: debouncedSearch, category: filterCategory, status: filterStatus, overdue: filterOverdue, page }),
        queryFn: () => equipmentApi.list({
            search: debouncedSearch || undefined,
            category: filterCategory || undefined,
            status: filterStatus || undefined,
            overdue: filterOverdue || undefined,
            page,
            per_page: 25,
        }),
    })

    useEffect(() => {
        if (isError && error) {
            toast.error(getApiErrorMessage(error, 'Erro ao carregar equipamentos'))
        }
    }, [isError, error])

    const deleteMutation = useMutation({
        mutationFn: (id: number) => equipmentApi.destroy(id),
        onSuccess: () => {
            toast.success('Equipamento excluído com sucesso')
            qc.invalidateQueries({ queryKey: queryKeys.equipment.all })
            qc.invalidateQueries({ queryKey: queryKeys.equipment.dashboard })
            broadcastQueryInvalidation(['equipments'], 'Equipamento')
            setShowConfirmDelete(null)
        },
        onError: (err: unknown) => {
            const deleteConflict = extractDeleteConflict(err)

            if (deleteConflict) {
                setDeleteDependencies(deleteConflict.dependencies)
                setDeleteMessage(deleteConflict.message)
                return
            }

            toast.error(getApiErrorMessage(err, 'Erro ao excluir equipamento'))
            setShowConfirmDelete(null)
        },
    })

    const equipments: Equipment[] = pageData?.data ?? []
    const lastPage = pageData?.meta?.last_page ?? pageData?.last_page ?? 1
    const categories = constants?.categories ?? {}

    return (
        <div className="space-y-5">
            <PageHeader
                title="Equipamentos"
                subtitle="Gestão de balanças, instrumentos e metrologia"
                actions={[
                    ...(canExport ? [{
                        label: 'Exportar CSV',
                        icon: <Download size={16} />,
                        variant: 'outline' as const,
                        onClick: async () => {
                            try {
                                const res = await equipmentApi.export()
                                const url = URL.createObjectURL(res.data)
                                const a = document.createElement('a')
                                a.href = url
                                a.download = `equipamentos_${new Date().toISOString().slice(0, 10)}.csv`
                                a.click()
                                URL.revokeObjectURL(url)
                            } catch (err) {
                                toast.error(getApiErrorMessage(err, 'Erro ao exportar CSV'))
                            }
                        },
                    }] : []),
                    ...(canCreate ? [{
                        label: 'Novo Equipamento',
                        icon: <Plus size={16} />,
                        href: '/equipamentos/novo',
                    }] : []),
                ]}
            />

            {dashboard && (
                <div className="grid grid-cols-5 gap-3">
                    <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-brand-50 p-2"><Scale size={20} className="text-brand-600" /></div>
                            <div>
                                <p className="text-lg font-semibold text-surface-900 tracking-tight">{dashboard.total}</p>
                                <p className="text-xs text-surface-500">Total Ativos</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-red-100 p-2"><AlertTriangle size={20} className="text-red-600" /></div>
                            <div>
                                <p className="text-2xl font-bold text-red-700">{dashboard.overdue}</p>
                                <p className="text-xs text-red-600">Vencidos</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-amber-100 p-2"><Clock size={20} className="text-amber-600" /></div>
                            <div>
                                <p className="text-2xl font-bold text-amber-700">{dashboard.due_7_days}</p>
                                <p className="text-xs text-amber-600">Vence em 7d</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-blue-200/50 bg-blue-50 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-blue-100 p-2"><Clock size={20} className="text-blue-600" /></div>
                            <div>
                                <p className="text-2xl font-bold text-blue-700">{dashboard.due_30_days}</p>
                                <p className="text-xs text-blue-600">Vence em 30d</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-surface-100 p-2"><Shield size={20} className="text-surface-600" /></div>
                            <div>
                                <p className="text-lg font-semibold text-surface-900 tracking-tight">{dashboard.critical_count}</p>
                                <p className="text-xs text-surface-500">Críticos</p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {dashboard && Object.keys(dashboard.by_category ?? {}).length > 0 && (() => {
                const cats = Object.entries(dashboard.by_category ?? {}) as [string, number][]
                const total = cats.reduce((s, [, v]) => s + (v as number), 0)
                const catColors: Record<string, string> = {
                    rodoviaria: 'bg-brand-500', industrial: 'bg-emerald-500',
                    laboratorio: 'bg-blue-500', comercial: 'bg-amber-500',
                    especial: 'bg-rose-500', outro: 'bg-surface-400',
                }
                return (
                    <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <h3 className="text-sm font-semibold text-surface-900 mb-3">Distribuição por Categoria</h3>
                        <div className="flex h-6 overflow-hidden rounded-full">
                            {(cats || []).map(([key, count]: [string, number]) => (
                                <div key={key} className={cn('transition-all', catColors[key] ?? 'bg-surface-300')}
                                    style={{ width: `${total > 0 ? (count / total) * 100 : 0}%` }}
                                    title={`${(categories as Record<string, string>)[key] || key}: ${count}`}
                                />
                            ))}
                        </div>
                        <div className="mt-2 flex flex-wrap gap-3">
                            {(cats || []).map(([key, count]: [string, number]) => (
                                <div key={key} className="flex items-center gap-1.5">
                                    <span className={cn('h-2.5 w-2.5 rounded-full', catColors[key] ?? 'bg-surface-300')} />
                                    <span className="text-xs text-surface-600">{(categories as Record<string, string>)[key] || key} <strong>{count}</strong> ({total > 0 ? Math.round((count / total) * 100) : 0}%)</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )
            })()}

            <div className="flex flex-wrap items-center gap-3">
                <div className="relative flex-1">
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-surface-400" />
                    <input
                        type="text"
                        placeholder="Buscar por código, série, marca, modelo, tag..."
                        value={search}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => { setSearch(e.target.value); setPage(1); }}
                        className="w-full rounded-lg border border-default bg-surface-0 py-2.5 pl-10 pr-4 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                    />
                </div>
                <select
                    value={filterCategory}
                    onChange={(e: React.ChangeEvent<HTMLSelectElement>) => { setFilterCategory(e.target.value); setPage(1) }}
                    aria-label="Filtrar por categoria"
                    className="rounded-lg border border-default bg-surface-0 px-3 py-2.5 text-sm"
                >
                    <option value="">Todas categorias</option>
                    {Object.entries(categories).map(([k, v]) => (
                        <option key={k} value={k}>{v as string}</option>
                    ))}
                </select>
                <select
                    value={filterStatus}
                    onChange={(e: React.ChangeEvent<HTMLSelectElement>) => { setFilterStatus(e.target.value); setPage(1) }}
                    aria-label="Filtrar por status"
                    className="rounded-lg border border-default bg-surface-0 px-3 py-2.5 text-sm"
                >
                    <option value="">Todos status</option>
                    {Object.entries(statusLabels).map(([k, v]) => (
                        <option key={k} value={k}>{v}</option>
                    ))}
                </select>
                <button
                    onClick={() => { setFilterOverdue(!filterOverdue); setPage(1) }}
                    className={cn(
                        'flex items-center gap-1 rounded-lg border px-3 py-2.5 text-sm font-medium transition-all',
                        filterOverdue
                            ? 'border-red-300 bg-red-50 text-red-700'
                            : 'border-default bg-surface-0 text-surface-600 hover:bg-surface-50'
                    )}
                >
                    <AlertTriangle size={14} />
                    Vencidos
                </button>
            </div>

            <div className="overflow-auto rounded-xl border border-default bg-surface-0 shadow-card">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-subtle bg-surface-50">
                            <th className="px-3.5 py-2.5 text-left font-semibold text-surface-600">Código</th>
                            <th className="px-3.5 py-2.5 text-left font-semibold text-surface-600">Equipamento</th>
                            <th className="px-3.5 py-2.5 text-left font-semibold text-surface-600">Série</th>
                            <th className="px-3.5 py-2.5 text-left font-semibold text-surface-600">Cliente</th>
                            <th className="px-3.5 py-2.5 text-left font-semibold text-surface-600">Categoria</th>
                            <th className="px-3.5 py-2.5 text-left font-semibold text-surface-600">Status</th>
                            <th className="px-3.5 py-2.5 text-left font-semibold text-surface-600">Calibração</th>
                            <th className="px-3.5 py-2.5 text-left font-semibold text-surface-600">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-subtle">
                        {isLoading && (
                            <tr><td colSpan={8} className="px-4 py-2">
                                <div className="space-y-3 py-4">
                                    {[1,2,3,4,5].map(i => <div key={i} className="skeleton h-10 w-full rounded" />)}
                                </div>
                            </td></tr>
                        )}
                        {!isLoading && equipments.length === 0 && (
                            <tr><td colSpan={8} className="px-4 py-2">
                                <EmptyState
                                    icon={<Scale className="h-5 w-5 text-surface-300" />}
                                    message="Nenhum equipamento encontrado"
                                    action={canCreate ? { label: 'Novo Equipamento', onClick: () => navigate('/equipamentos/novo'), icon: <Plus className="h-4 w-4" /> } : undefined}
                                    compact
                                />
                            </td></tr>
                        )}
                        {(equipments || []).map(eq => {
                            const calBadge = calibrationBadge(eq.next_calibration_at)
                            return (
                                <tr key={eq.id} className="transition-colors hover:bg-surface-50/50">
                                    <td className="px-4 py-3">
                                        <span className="font-mono text-xs font-medium text-brand-600">{eq.code}</span>
                                        {eq.is_critical && (
                                            <span className="ml-1 inline-block h-2 w-2 rounded-full bg-red-500" title="Crítico" />
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-surface-900">
                                            {eq.brand} {eq.model}
                                        </div>
                                        <div className="text-xs text-surface-500">{eq.type}</div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-surface-600">
                                        {eq.serial_number || '—'}
                                    </td>
                                    <td className="max-w-[150px] truncate px-4 py-3 text-surface-700">
                                        {eq.customer?.name || '—'}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-surface-600">
                                        {(eq.category && (categories as Record<string, string>)[eq.category]) || eq.category || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[eq.status] || 'bg-surface-100')}>
                                            {statusLabels[eq.status] || eq.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium', calBadge.className)}>
                                            {calBadge.label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1">
                                            <Link
                                                to={`/equipamentos/${eq.id}`}
                                                className="inline-flex items-center gap-1 rounded-lg bg-surface-100 px-2.5 py-1.5 text-xs font-medium text-surface-700 hover:bg-surface-200"
                                            >
                                                <Eye size={12} />
                                                Ver
                                            </Link>
                                            {canDelete && (
                                                <button
                                                    onClick={() => {
                                                        setShowConfirmDelete(eq)
                                                        setDeleteDependencies(null)
                                                        setDeleteMessage(null)
                                                    }}
                                                    aria-label={`Excluir equipamento ${eq.code}`}
                                                    title="Excluir equipamento"
                                                    className="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100"
                                                >
                                                    <Trash2 size={12} />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            )
                        })}
                    </tbody>
                </table>
            </div>

            {lastPage > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button
                        onClick={() => setPage(p => Math.max(1, p - 1))}
                        disabled={page === 1}
                        aria-label="Página anterior"
                        className="rounded-lg border border-default p-2 hover:bg-surface-50 disabled:opacity-50"
                    >
                        <ChevronLeft size={16} />
                    </button>
                    <span className="text-sm text-surface-600">Página {page} de {lastPage}</span>
                    <button
                        onClick={() => setPage(p => Math.min(lastPage, p + 1))}
                        disabled={page === lastPage}
                        aria-label="Próxima página"
                        className="rounded-lg border border-default p-2 hover:bg-surface-50 disabled:opacity-50"
                    >
                        <ChevronRight size={16} />
                    </button>
                </div>
            )}

            <Modal open={!!showConfirmDelete} onOpenChange={() => setShowConfirmDelete(null)} size="sm" title="Excluir Equipamento">
                <div className="space-y-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 flex-shrink-0">
                            <AlertTriangle className="h-5 w-5 text-red-600" />
                        </div>
                        <div>
                            <h3 className="font-medium text-surface-900">Tem certeza?</h3>
                            <p className="text-sm text-surface-500">
                                Deseja realmente excluir o equipamento <strong>{showConfirmDelete?.code}</strong>?
                            </p>
                        </div>
                    </div>

                    {deleteMessage && (
                        <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700 border border-red-100">
                            <p className="font-medium mb-1">Não é possível excluir:</p>
                            <p>{deleteMessage}</p>
                        </div>
                    )}

                    {deleteDependencies && (
                        <div className="space-y-2">
                            <p className="text-xs font-medium text-surface-600 uppercase tracking-wide">Vínculos encontrados:</p>
                            <div className="grid grid-cols-2 gap-2">
                                {Object.entries(deleteDependencies).map(([key, count]) => (
                                    <div key={key} className="flex items-center justify-between rounded bg-surface-50 px-3 py-2 text-sm border border-default">
                                        <span className="text-surface-600 capitalize">{key.replace(/_/g, ' ')}</span>
                                        <Badge variant="neutral">{String(count)}</Badge>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" onClick={() => setShowConfirmDelete(null)}>Cancelar</Button>
                        {deleteDependencies ? (
                            <Button variant="ghost" disabled className="text-surface-400 cursor-not-allowed">
                                Resolva as pendências acima
                            </Button>
                        ) : (
                            <Button className="bg-red-600 hover:bg-red-700 text-white" loading={deleteMutation.isPending}
                                onClick={() => {
                                    if (showConfirmDelete) {
                                        deleteMutation.mutate(showConfirmDelete.id)
                                    }
                                }}>
                                Excluir Equipamento
                            </Button>
                        )}
                    </div>
                </div>
            </Modal>
        </div>
    )
}
