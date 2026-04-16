import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Clock, CheckCircle2, XCircle, Search, ChevronLeft, ChevronRight
} from 'lucide-react'
import api, { getApiErrorMessage } from '@/lib/api'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/ui/modal'
import { PageHeader } from '@/components/ui/pageheader'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'

const rejectSchema = z.object({
    reason: z.string().min(3, 'O motivo deve ter pelo menos 3 caracteres')
})
type RejectFormData = z.infer<typeof rejectSchema>

interface Adjustment {
    id: number
    requester?: { name: string }
    approver?: { name: string }
    entry?: {
        id: number
        clock_in: string
        clock_out: string | null
    }
    original_clock_in: string
    original_clock_out: string | null
    adjusted_clock_in: string
    adjusted_clock_out: string | null
    reason: string
    status: 'pending' | 'approved' | 'rejected'
    created_at: string
}

const statusColors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
}
const statusLabels: Record<string, string> = {
    pending: 'Pendente',
    approved: 'Aprovado',
    rejected: 'Rejeitado',
}

export default function ClockAdjustmentsPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const canApprove = hasRole('super_admin') || hasPermission('hr.adjustment.approve')
    const [page, setPage] = useState(1)
    const [statusFilter, setStatusFilter] = useState<string>('pending')
    const [search, setSearch] = useState('')
    const [rejectTarget, setRejectTarget] = useState<Adjustment | null>(null)

    const { register, handleSubmit, reset, formState: { errors } } = useForm<RejectFormData>({
        resolver: zodResolver(rejectSchema)
    })

    const { data: adjustmentsRes, isLoading } = useQuery({
        queryKey: ['clock-adjustments', page, statusFilter, search],
        queryFn: () => api.get('/hr/adjustments', {
            params: { page, per_page: 20, status: statusFilter || undefined, search: search || undefined },
        }).then(r => r.data),
    })

    const adjustments: Adjustment[] = adjustmentsRes?.data?.data ?? adjustmentsRes?.data ?? []
    const lastPage = adjustmentsRes?.meta?.last_page ?? 1

    const approveMut = useMutation({
        mutationFn: (id: number) => api.post(`/hr/adjustments/${id}/approve`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['clock-adjustments'] })
            toast.success('Ajuste aprovado')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao aprovar')),
    })

    const rejectMut = useMutation({
        mutationFn: ({ id, reason }: { id: number; reason: string }) =>
            api.post(`/hr/adjustments/${id}/reject`, { rejection_reason: reason }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['clock-adjustments'] })
            setRejectTarget(null)
            reset()
            toast.success('Ajuste rejeitado')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao rejeitar')),
    })

    const fmtDateTime = (d: string | null) =>
        d ? new Date(d).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'

    return (
        <div className="space-y-5">
            <PageHeader title="Ajustes de Ponto" subtitle="Solicitações de ajuste pendentes de aprovação" />

            <div className="flex flex-wrap items-center gap-3">
                <div className="relative flex-1 max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input
                        type="text"
                        placeholder="Buscar por técnico..."
                        value={search}
                        onChange={e => { setSearch(e.target.value); setPage(1) }}
                        className="w-full rounded-lg border border-default bg-surface-50 py-2.5 pl-10 pr-4 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                    />
                </div>
                <div className="flex gap-1 rounded-lg border border-default bg-surface-50 p-0.5">
                    {['pending', 'approved', 'rejected', ''].map(s => (
                        <button
                            key={s}
                            onClick={() => { setStatusFilter(s); setPage(1) }}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-xs font-medium transition-all',
                                statusFilter === s
                                    ? 'bg-surface-0 text-brand-700 shadow-sm'
                                    : 'text-surface-500 hover:text-surface-700'
                            )}
                        >
                            {s === '' ? 'Todos' : statusLabels[s]}
                        </button>
                    ))}
                </div>
            </div>

            <div className="overflow-auto rounded-xl border border-default bg-surface-0 shadow-card">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-subtle bg-surface-50">
                            <th className="px-4 py-2.5 text-left font-semibold text-surface-600">Técnico</th>
                            <th className="px-4 py-2.5 text-left font-semibold text-surface-600">Original</th>
                            <th className="px-4 py-2.5 text-left font-semibold text-surface-600">Ajustado</th>
                            <th className="px-4 py-2.5 text-left font-semibold text-surface-600">Motivo</th>
                            <th className="px-4 py-2.5 text-center font-semibold text-surface-600">Status</th>
                            {canApprove && <th className="px-4 py-2.5 text-right font-semibold text-surface-600">Ações</th>}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-subtle">
                        {isLoading && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-surface-400">Carregando...</td></tr>
                        )}
                        {!isLoading && adjustments.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-12 text-center">
                                    <Clock className="mx-auto h-8 w-8 text-surface-300" />
                                    <p className="mt-2 text-sm text-surface-400">Nenhum ajuste encontrado</p>
                                </td>
                            </tr>
                        )}
                        {(adjustments || []).map(a => (
                            <tr key={a.id} className="transition-colors hover:bg-surface-50/50">
                                <td className="px-4 py-3 font-medium text-surface-900">
                                    {a.requester?.name ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-xs text-surface-500">
                                    <div>{fmtDateTime(a.original_clock_in)}</div>
                                    <div>{fmtDateTime(a.original_clock_out)}</div>
                                </td>
                                <td className="px-4 py-3 text-xs text-brand-600 font-medium">
                                    <div>{fmtDateTime(a.adjusted_clock_in)}</div>
                                    <div>{fmtDateTime(a.adjusted_clock_out)}</div>
                                </td>
                                <td className="px-4 py-3 text-sm text-surface-600 max-w-xs truncate">
                                    {a.reason}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[a.status])}>
                                        {statusLabels[a.status]}
                                    </span>
                                </td>
                                {canApprove && (
                                    <td className="px-4 py-3 text-right">
                                        {a.status === 'pending' && (
                                            <div className="flex items-center justify-end gap-1.5">
                                                <button
                                                    onClick={() => approveMut.mutate(a.id)}
                                                    disabled={approveMut.isPending}
                                                    className="rounded-lg p-1.5 text-emerald-500 hover:bg-emerald-50"
                                                    title="Aprovar"
                                                    aria-label="Aprovar ajuste"
                                                >
                                                    <CheckCircle2 className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => { setRejectTarget(a); reset() }}
                                                    className="rounded-lg p-1.5 text-red-500 hover:bg-red-50"
                                                    title="Rejeitar"
                                                    aria-label="Rejeitar ajuste"
                                                >
                                                    <XCircle className="h-4 w-4" />
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {lastPage > 1 && (
                <div className="flex items-center justify-between">
                    <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}
                        aria-label="Página Anterior" icon={<ChevronLeft className="h-4 w-4" />}>Anterior</Button>
                    <span className="text-xs text-surface-500">Página {page} de {lastPage}</span>
                    <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => setPage(p => p + 1)}>
                        Próxima <ChevronRight className="ml-1 h-4 w-4" aria-label="Próxima página" />
                    </Button>
                </div>
            )}

            <Modal open={!!rejectTarget} onOpenChange={(val) => { if(!val) setRejectTarget(null) }} title="Rejeitar Ajuste" size="sm">
                <form onSubmit={handleSubmit((data) => rejectTarget && rejectMut.mutate({ id: rejectTarget.id, reason: data.reason }))} className="space-y-4">
                    <p className="text-sm text-surface-600">
                        Insira o motivo da rejeição para <strong>{rejectTarget?.requester?.name}</strong>:
                    </p>
                    <div>
                        <textarea
                            {...register('reason')}
                            rows={3}
                            className={cn(
                                "w-full rounded-lg border bg-surface-50 px-3 py-2.5 text-sm focus:bg-surface-0 focus:outline-none focus:ring-2",
                                errors.reason ? "border-red-500 focus:border-red-500 focus:ring-red-500/15" : "border-default focus:border-brand-400 focus:ring-brand-500/15"
                            )}
                            placeholder="Motivo da rejeição..."
                        />
                        {errors.reason && <p className="mt-1 text-xs text-red-500">{errors.reason.message}</p>}
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setRejectTarget(null)}>Cancelar</Button>
                        <Button
                            type="submit"
                            className="bg-red-600 hover:bg-red-700"
                            loading={rejectMut.isPending}
                        >
                            Rejeitar
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    )
}
