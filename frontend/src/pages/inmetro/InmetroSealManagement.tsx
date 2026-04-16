import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Search, Plus, UserPlus, FileText } from 'lucide-react'
import api from '@/lib/api'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'

interface InmetroSeal {
    id: number
    type: string
    number: string
    status: string
    assigned_toUser: { id: number; name: string } | null
    work_order: { id: number; number: string } | null
    equipment: { id: number; brand: string; model: string } | null
    photo_path: string | null
    used_at: string | null
    created_at: string
}

interface _LookupItem {
    id: number
    name: string
    slug?: string
}

interface ApiError {
    response?: {
        data?: {
            message?: string
        }
    }
}

const STATUS_CONFIG: Record<string, { label: string; color: string; badgeVariant: 'emerald' | 'blue' | 'zinc' | 'red' | 'amber' }> = {
    available: { label: 'Disponível', color: 'text-emerald-600 bg-emerald-50', badgeVariant: 'emerald' },
    assigned: { label: 'Com Técnico', color: 'text-blue-600 bg-blue-50', badgeVariant: 'blue' },
    used: { label: 'Utilizado', color: 'text-zinc-600 bg-zinc-50', badgeVariant: 'zinc' },
    damaged: { label: 'Danificado', color: 'text-red-600 bg-red-50', badgeVariant: 'red' },
    lost: { label: 'Extraviado', color: 'text-amber-600 bg-amber-50', badgeVariant: 'amber' },
}

export default function InmetroSealManagement() {
    const { user } = useAuthStore()
    const hasPermission = (p: string) => user?.all_permissions?.includes(p) ?? false
    const canView = hasPermission('inmetro.intelligence.view')
    const canManage = hasPermission('inmetro.intelligence.convert')
    const qc = useQueryClient()
    const navigate = useNavigate()
    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState('')
    const [typeFilter, setTypeFilter] = useState('')
    const [techFilter, _setTechFilter] = useState('')
    const [page, _setPage] = useState(1)

    const [showBatchModal, setShowBatchModal] = useState(false)
    const [showAssignModal, setShowAssignModal] = useState(false)
    const [selectedSeals, setSelectedSeals] = useState<number[]>([])

    const { data: res, isLoading } = useQuery({
        queryKey: ['inmetro-seals', search, statusFilter, typeFilter, techFilter, page],
        queryFn: () => api.get('/repair-seals', {
            params: {
                search,
                status: statusFilter || undefined,
                type: typeFilter || undefined,
                technician_id: techFilter || undefined,
                page,
                per_page: 50
            }
        }),
        enabled: canView,
    })

    const { data: techsRes } = useQuery({
        queryKey: ['technicians-options'],
        queryFn: () => api.get('/technicians/options'),
        enabled: canManage,
    })
    const { data: sealTypeItems = [] } = useQuery({
        queryKey: ['lookups', 'inmetro-seal-types'],
        queryFn: async () => {
            const { data } = await api.get('/lookups/inmetro-seal-types')
            const payload = data?.data ?? data
            return Array.isArray(payload) ? payload : []
        },
        staleTime: 5 * 60_000,
        enabled: canView || canManage,
    })
    const { data: sealStatusItems = [] } = useQuery({
        queryKey: ['lookups', 'inmetro-seal-statuses'],
        queryFn: async () => {
            const { data } = await api.get('/lookups/inmetro-seal-statuses')
            const payload = data?.data ?? data
            return Array.isArray(payload) ? payload : []
        },
        staleTime: 5 * 60_000,
        enabled: canView || canManage,
    })
    const technicians = techsRes?.data?.data ?? techsRes?.data ?? []

    const seals: InmetroSeal[] = res?.data?.data ?? []
    const _pagination = { last_page: res?.data?.last_page ?? 1, current_page: res?.data?.current_page ?? 1 }
    const sealTypeOptions = useMemo(() => {
        const fallback = [
            { value: 'seal_reparo', label: 'Selo Reparo' },
            { value: 'seal', label: 'Lacre' },
        ]
        const options = [...fallback]
        sealTypeItems.forEach((item) => {
            const value = item.slug ?? item.name
            if (!options.some((option) => option.value === value)) {
                options.push({ value, label: item.name })
            }
        })
        return options
    }, [sealTypeItems])
    const sealStatusOptions = useMemo(() => {
        const fallback = [
            { value: 'available', label: 'Disponível' },
            { value: 'assigned', label: 'Com Técnico' },
            { value: 'used', label: 'Utilizado' },
            { value: 'damaged', label: 'Danificado' },
            { value: 'lost', label: 'Extraviado' },
        ]
        const options = [...fallback]
        sealStatusItems.forEach((item) => {
            const value = item.slug ?? item.name
            if (!options.some((option) => option.value === value)) {
                options.push({ value, label: item.name })
            }
        })
        return options
    }, [sealStatusItems])
    const sealTypeLabelByValue = useMemo(() => {
        const labels: Record<string, string> = {
            seal_reparo: 'Selo Reparo',
            seal: 'Lacre',
        }
        sealTypeItems.forEach((item) => {
            if (item.slug) {
                labels[item.slug] = item.name
            }
            labels[item.name] = item.name
        })
        return labels
    }, [sealTypeItems])

    const batchMut = useMutation({
        mutationFn: (payload: Record<string, unknown>) => api.post('/repair-seal-batches', payload),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['inmetro-seals'] })
            setShowBatchModal(false)
            toast.success('Lote cadastrado com sucesso!')
        },
        onError: (error: unknown) => {
            const apiError = error as ApiError
            toast.error(apiError?.response?.data?.message ?? 'Erro ao cadastrar lote')
        }
    })

    const assignMut = useMutation({
        mutationFn: (payload: { technician_id: number; seal_ids: number[] }) => api.post('/repair-seals/assign', payload),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['inmetro-seals'] })
            setShowAssignModal(false)
            setSelectedSeals([])
            toast.success('Itens atribuídos com sucesso!')
        },
        onError: (error: unknown) => {
            const apiError = error as ApiError
            toast.error(apiError?.response?.data?.message ?? 'Erro ao atribuir itens')
        }
    })

    const toggleSeal = (id: number) => {
        setSelectedSeals(prev => prev.includes(id) ? (prev || []).filter(x => x !== id) : [...prev, id])
    }

    if (!canView) {
        return (
            <div className="rounded-xl border border-default bg-surface-50 p-6 text-sm text-surface-600">
                Você não possui permissão para visualizar os selos INMETRO.
            </div>
        )
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Gestão de Selos e Lacres"
                subtitle="Controle unitário de insumos INMETRO"
                actions={[
                    {
                        label: 'Relatórios & Auditoria',
                        icon: <FileText className="h-4 w-4" />,
                        onClick: () => navigate('/inmetro/relatorio-selos'),
                        permission: canView,
                        variant: 'outline'
                    },
                    {
                        label: 'Atribuir a Técnico',
                        icon: <UserPlus className="h-4 w-4" />,
                        onClick: () => setShowAssignModal(true),
                        permission: canManage,
                        disabled: selectedSeals.length === 0 || !canManage,
                        variant: 'outline'
                    },
                    {
                        label: 'Entrada em Lote',
                        icon: <Plus className="h-4 w-4" />,
                        onClick: () => setShowBatchModal(true),
                        permission: canManage,
                    },
                ]}
            />

            <div className="flex flex-wrap gap-3">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input
                        type="text" value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar número..."
                        className="w-full rounded-lg border border-default bg-surface-50 py-2.5 pl-10 pr-4 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                    />
                </div>
                <select
                    value={typeFilter}
                    onChange={(e) => setTypeFilter(e.target.value)}
                    title="Filtrar por tipo"
                    className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                >
                    <option value="">Todos os tipos</option>
                    {sealTypeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    title="Filtrar por status"
                    className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                >
                    <option value="">Todos os status</option>
                    {sealStatusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
            </div>

            <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                <table className="w-full text-left">
                    <thead>
                        <tr className="border-b border-subtle bg-surface-50">
                            <th className="w-10 px-4 py-3"></th>
                            <th className="px-4 py-3 text-xs font-medium uppercase tracking-wider text-surface-500">Número</th>
                            <th className="px-4 py-3 text-xs font-medium uppercase tracking-wider text-surface-500">Tipo</th>
                            <th className="px-4 py-3 text-xs font-medium uppercase tracking-wider text-surface-500">Status</th>
                            <th className="px-4 py-3 text-xs font-medium uppercase tracking-wider text-surface-500">Responsável</th>
                            <th className="px-4 py-3 text-xs font-medium uppercase tracking-wider text-surface-500">Vínculo</th>
                            <th className="px-4 py-3 text-xs font-medium uppercase tracking-wider text-surface-500">Data</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-subtle">
                        {isLoading ? (
                            <tr><td colSpan={7} className="py-10 text-center text-sm text-surface-500">Carregando...</td></tr>
                        ) : seals.length === 0 ? (
                            <tr><td colSpan={7} className="py-10 text-center text-sm text-surface-500">Nenhum registro encontrado.</td></tr>
                        ) : (seals || []).map(s => {
                            const st = STATUS_CONFIG[s.status] || STATUS_CONFIG.available
                            return (
                                <tr key={s.id} className={cn("hover:bg-surface-50 transition-colors", selectedSeals.includes(s.id) && "bg-brand-50/30")}>
                                    <td className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={selectedSeals.includes(s.id)}
                                            onChange={() => toggleSeal(s.id)}
                                            disabled={s.status !== 'available'}
                                            title={`Selecionar selo ${s.number}`}
                                            className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500"
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-sm font-medium text-surface-900">{s.number}</td>
                                    <td className="px-4 py-3 text-sm text-surface-600">
                                        {sealTypeLabelByValue[s.type] ?? s.type}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant={st.badgeVariant}>{st.label}</Badge>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-surface-600">
                                        {s.assigned_toUser?.name || '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-surface-600">
                                        {s.work_order ? `OS #${s.work_order.number}` : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-surface-500">
                                        {s.used_at ? new Date(s.used_at).toLocaleDateString('pt-BR') : '—'}
                                    </td>
                                </tr>
                            )
                        })}
                    </tbody>
                </table>
            </div>

            <Modal open={showBatchModal} onOpenChange={setShowBatchModal} title="Entrada em Lote" size="md">
                <form onSubmit={(e) => {
                    e.preventDefault();
                    const fd = new FormData(e.currentTarget);
                    batchMut.mutate(Object.fromEntries(fd) as Record<string, unknown>);
                }} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Tipo</label>
                            <select name="type" title="Selecionar tipo de selo" className="w-full rounded-lg border border-default p-2 text-sm" required>
                                {sealTypeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                        </div>
                        <Input label="Código do Lote" name="batch_code" placeholder="Ex: LOTE-2026-001" required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Número Inicial" name="range_start" required />
                        <Input label="Número Final" name="range_end" required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Prefixo (opcional)</label>
                            <Input name="prefix" placeholder="Ex: RS" />
                        </div>
                        <Input label="Recebido em" name="received_at" type="date" required />
                    </div>
                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <Button variant="outline" type="button" onClick={() => setShowBatchModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={batchMut.isPending}>Salvar Lote</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={showAssignModal} onOpenChange={setShowAssignModal} title="Atribuir a Técnico" size="sm">
                <form onSubmit={(e) => {
                    e.preventDefault();
                    const fd = new FormData(e.currentTarget);
                    assignMut.mutate({
                        technician_id: Number(fd.get('technician_id')),
                        seal_ids: selectedSeals
                    });
                }} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium mb-1">Técnico Responsável</label>
                        <select name="technician_id" title="Selecionar técnico" className="w-full rounded-lg border border-default p-2 text-sm" required>
                            <option value="">Selecione...</option>
                            {(technicians || []).map((t: { id: number; name: string }) => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </select>
                    </div>
                    <p className="text-xs text-surface-500">Você está atribuindo {selectedSeals.length} itens.</p>
                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <Button variant="outline" type="button" onClick={() => setShowAssignModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={assignMut.isPending}>Atribuir Agora</Button>
                    </div>
                </form>
            </Modal>
        </div>
    )
}
