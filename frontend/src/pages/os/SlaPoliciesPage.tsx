import { useState } from 'react'
import { toast } from 'sonner'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Edit2, Trash2, Shield, Clock, X, AlertTriangle } from 'lucide-react'
import api from '@/lib/api'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'

interface SlaPolicy {
    id: number
    name: string
    description: string | null
    priority: string
    response_time_hours: number
    resolution_time_hours: number
    is_active: boolean
}

const priorityConfig: Record<string, { label: string; color: string; icon: string }> = {
    low: { label: 'Baixa', color: 'text-surface-600 bg-surface-100', icon: 'L' },
    medium: { label: 'Media', color: 'text-amber-700 bg-amber-50', icon: 'M' },
    high: { label: 'Alta', color: 'text-orange-700 bg-orange-50', icon: 'A' },
    critical: { label: 'Critica', color: 'text-red-700 bg-red-50', icon: 'C' },
}

export function SlaPoliciesPage() {
    const { hasPermission } = useAuthStore()
    const canView = hasPermission('os.work_order.view')
    const canCreate = hasPermission('os.work_order.create')
    const canUpdate = hasPermission('os.work_order.update')
    const canDelete = hasPermission('os.work_order.delete')

    const qc = useQueryClient()
    const [modal, setModal] = useState<{ mode: 'create' | 'edit'; policy?: SlaPolicy } | null>(null)

    const { data: res, isLoading, isError } = useQuery({
        queryKey: ['sla-policies'],
        queryFn: () => api.get('/sla-policies'),
        enabled: canView,
    })
    const policies: SlaPolicy[] = res?.data?.data ?? []

    const saveMut = useMutation({
        mutationFn: (data: Record<string, unknown>) =>
            data.id ? api.put(`/sla-policies/${data.id}`, data) : api.post('/sla-policies', data),
        onSuccess: () => {
            toast.success('Operacao realizada com sucesso')
            qc.invalidateQueries({ queryKey: ['sla-policies'] })
            setModal(null)
        },
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => api.delete(`/sla-policies/${id}`),
        onSuccess: () => {
            toast.success('Operacao realizada com sucesso')
            qc.invalidateQueries({ queryKey: ['sla-policies'] })
        },
    })

    const fmtHours = (hours: number) => hours >= 24 ? `${Math.floor(hours / 24)}d ${hours % 24}h` : `${hours}h`

    if (!canView) {
        return (
            <div className="space-y-5">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Politicas de SLA</h1>
                </div>
                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Voce nao possui permissao para visualizar politicas de SLA.
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Politicas de SLA</h1>
                    <p className="mt-0.5 text-sm text-surface-500">Defina tempos de resposta e resolucao por prioridade</p>
                </div>
                {canCreate ? (
                    <button
                        onClick={() => setModal({ mode: 'create' })}
                        className="flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md transition-colors hover:bg-brand-600"
                    >
                        <Plus className="h-4 w-4" /> Nova Politica
                    </button>
                ) : null}
            </div>

            {isLoading && <p className="py-8 text-center text-sm text-surface-400">Carregando...</p>}

            {!isLoading && isError && (
                <div className="rounded-xl border border-default bg-surface-0 p-12 text-center">
                    <AlertTriangle className="mx-auto h-12 w-12 text-red-300" />
                    <p className="mt-3 text-sm font-medium text-red-600">Erro ao carregar politicas de SLA</p>
                    <p className="mt-1 text-xs text-surface-400">Tente novamente mais tarde</p>
                </div>
            )}

            {!isLoading && !isError && policies.length === 0 && (
                <div className="rounded-xl border border-dashed border-default bg-surface-50 p-12 text-center">
                    <Shield className="mx-auto h-12 w-12 text-surface-300" />
                    <p className="mt-3 text-sm text-surface-500">Nenhuma politica de SLA cadastrada</p>
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                {(policies || []).map((policy) => {
                    const priority = priorityConfig[policy.priority] ?? priorityConfig.medium

                    return (
                        <div
                            key={policy.id}
                            className={cn(
                                'group rounded-xl border bg-surface-0 p-5 shadow-card transition-all hover:shadow-lg',
                                !policy.is_active && 'opacity-60'
                            )}
                        >
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-2">
                                    <span className="text-lg">{priority.icon}</span>
                                    <div>
                                        <h3 className="text-sm font-bold text-surface-900">{policy.name}</h3>
                                        <span className={cn('mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium', priority.color)}>
                                            {priority.label}
                                        </span>
                                    </div>
                                </div>
                                <div className="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                    {canUpdate ? (
                                        <button
                                            onClick={() => setModal({ mode: 'edit', policy })}
                                            className="rounded-lg p-1.5 hover:bg-surface-100"
                                            aria-label={`Editar politica ${policy.name}`}
                                        >
                                            <Edit2 className="h-3.5 w-3.5 text-surface-500" />
                                        </button>
                                    ) : null}
                                    {canDelete ? (
                                        <button
                                            onClick={() => {
                                                if (confirm('Excluir esta politica?')) {
                                                    deleteMut.mutate(policy.id)
                                                }
                                            }}
                                            className="rounded-lg p-1.5 hover:bg-red-50"
                                            aria-label={`Excluir politica ${policy.name}`}
                                        >
                                            <Trash2 className="h-3.5 w-3.5 text-red-500" />
                                        </button>
                                    ) : null}
                                </div>
                            </div>

                            {policy.description ? (
                                <p className="mt-2 line-clamp-2 text-xs text-surface-500">{policy.description}</p>
                            ) : null}

                            <div className="mt-4 grid grid-cols-2 gap-3">
                                <div className="rounded-lg bg-blue-50 p-3 text-center">
                                    <Clock className="mx-auto mb-1 h-4 w-4 text-blue-500" />
                                    <p className="text-xs font-medium text-blue-600">Resposta</p>
                                    <p className="text-sm font-semibold tabular-nums text-blue-700">{fmtHours(policy.response_time_hours)}</p>
                                </div>
                                <div className="rounded-lg bg-emerald-50 p-3 text-center">
                                    <AlertTriangle className="mx-auto mb-1 h-4 w-4 text-emerald-500" />
                                    <p className="text-xs font-medium text-emerald-600">Resolucao</p>
                                    <p className="text-sm font-semibold tabular-nums text-emerald-700">{fmtHours(policy.resolution_time_hours)}</p>
                                </div>
                            </div>

                            {!policy.is_active ? (
                                <p className="mt-3 text-center text-xs text-surface-400">Inativa</p>
                            ) : null}
                        </div>
                    )
                })}
            </div>

            {modal ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" onClick={() => setModal(null)}>
                    <div className="w-full max-w-md rounded-2xl bg-surface-0 p-6 shadow-2xl" onClick={(event) => event.stopPropagation()}>
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-semibold tabular-nums text-surface-900">
                                {modal.mode === 'edit' ? 'Editar Politica' : 'Nova Politica SLA'}
                            </h3>
                            <button onClick={() => setModal(null)}>
                                <X className="h-5 w-5 text-surface-400" />
                            </button>
                        </div>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault()
                                const formData = new FormData(event.currentTarget)
                                saveMut.mutate({
                                    id: modal.policy?.id,
                                    name: formData.get('name'),
                                    description: formData.get('description') || null,
                                    priority: formData.get('priority'),
                                    response_time_hours: Number(formData.get('response_time_hours')),
                                    resolution_time_hours: Number(formData.get('resolution_time_hours')),
                                    is_active: formData.get('is_active') === 'on',
                                })
                            }}
                            className="mt-4 space-y-3"
                        >
                            <div>
                                <label className="text-xs font-medium text-surface-700">Nome</label>
                                <input name="name" required defaultValue={modal.policy?.name} className="mt-1 block w-full rounded-lg border border-surface-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label className="text-xs font-medium text-surface-700">Descricao</label>
                                <textarea name="description" rows={2} defaultValue={modal.policy?.description ?? ''} className="mt-1 block w-full rounded-lg border border-surface-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label className="text-xs font-medium text-surface-700">Prioridade</label>
                                <select name="priority" required defaultValue={modal.policy?.priority ?? 'medium'} className="mt-1 block w-full rounded-lg border border-surface-300 px-3 py-2 text-sm" aria-label="Prioridade da politica SLA">
                                    {Object.entries(priorityConfig).map(([key, value]) => (
                                        <option key={key} value={key}>{value.icon} {value.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="text-xs font-medium text-surface-700">Resposta (horas)</label>
                                    <input name="response_time_hours" type="number" min={1} required defaultValue={modal.policy?.response_time_hours ?? 4} className="mt-1 block w-full rounded-lg border border-surface-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-surface-700">Resolucao (horas)</label>
                                    <input name="resolution_time_hours" type="number" min={1} required defaultValue={modal.policy?.resolution_time_hours ?? 24} className="mt-1 block w-full rounded-lg border border-surface-300 px-3 py-2 text-sm" />
                                </div>
                            </div>
                            <label className="flex items-center gap-2 text-sm text-surface-700">
                                <input name="is_active" type="checkbox" defaultChecked={modal.policy?.is_active ?? true} className="rounded" />
                                Ativa
                            </label>
                            <div className="flex gap-2 pt-2">
                                <button type="button" onClick={() => setModal(null)} className="flex-1 rounded-xl border border-surface-300 px-4 py-2 text-sm font-medium">Cancelar</button>
                                <button type="submit" disabled={saveMut.isPending} className="flex-1 rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-50">
                                    {saveMut.isPending ? 'Salvando...' : 'Salvar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </div>
    )
}
