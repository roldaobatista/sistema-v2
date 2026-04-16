import { useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import type { AxiosError } from 'axios'
import { handleFormError } from '@/lib/form-utils'
import { requiredString, optionalString } from '@/schemas/common'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, FileText} from 'lucide-react'
import { toast } from 'sonner'
import { getApiErrorMessage } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { Modal } from '@/components/ui/modal'
import { useAuthStore } from '@/stores/auth-store'

const fmtBRL = (val: string | number) => Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDate = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('pt-BR')

const statusConfig: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'danger' | 'default' }> = {
    pending: { label: 'Pendente', variant: 'warning' },
    deposited: { label: 'Depositado', variant: 'info' },
    compensated: { label: 'Compensado', variant: 'success' },
    returned: { label: 'Devolvido', variant: 'danger' },
    custody: { label: 'Em Custódia', variant: 'default' },
}

const typeLabels: Record<string, string> = { received: 'Recebido', issued: 'Emitido' }

interface Check {
    id: number; type: string; number: string; bank: string
    amount: string; due_date: string; issuer: string; status: string; notes?: string
}

interface CheckForm {
    type: string; number: string; bank: string; amount: string
    due_date: string; issuer: string; status: string; notes: string
}

interface ApiError {
    response?: { data?: { message?: string; errors?: Record<string, string[]> } }
}

const checkSchema = z.object({
    type: z.enum(['received', 'issued']),
    number: requiredString('Número é obrigatório'),
    bank: requiredString('Banco é obrigatório'),
    issuer: requiredString('Emitente é obrigatório'),
    amount: z.coerce.number({ required_error: 'Valor é obrigatório' }).min(0.01, 'Valor inválido'),
    due_date: requiredString('Vencimento é obrigatório'),
    status: optionalString,
    notes: optionalString,
})

type CheckFormData = z.infer<typeof checkSchema>

export function FinancialChecksPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const isSuperAdmin = hasRole('super_admin')
    const canView = isSuperAdmin || hasPermission('finance.payable.view') || hasPermission('financeiro.view')
    const canCreate = isSuperAdmin || hasPermission('finance.payable.create') || hasPermission('financeiro.payment.create')
    const canUpdate = isSuperAdmin || hasPermission('finance.payable.update') || hasPermission('financeiro.payment.create')
    const emptyForm: CheckFormData = { type: 'received', number: '', bank: '', amount: 0, due_date: '', issuer: '', status: 'pending', notes: '' }

    const [statusFilter, setStatusFilter] = useState('')
    const [typeFilter, setTypeFilter] = useState('')
    const [page, setPage] = useState(1)
    const [showForm, setShowForm] = useState(false)
    const [statusTarget, setStatusTarget] = useState<Check | null>(null)
    const [newStatus, setNewStatus] = useState('')

    const form = useForm<CheckFormData>({
        resolver: zodResolver(checkSchema),
        defaultValues: emptyForm,
    })

    const { data: res, isLoading, isError, refetch } = useQuery({
        queryKey: ['financial-checks', statusFilter, typeFilter, page],
        queryFn: () => financialApi.checks.list({ status: statusFilter || undefined, type: typeFilter || undefined, page }),
        enabled: canView,
    })
    const records: Check[] = res?.data?.data ?? []
    const pagination = { currentPage: res?.data?.current_page ?? 1, lastPage: res?.data?.last_page ?? 1, total: res?.data?.total ?? 0 }

    const saveMut = useMutation({
        mutationFn: (data: CheckFormData) => financialApi.checks.store({ ...data, amount: String(data.amount), notes: data.notes?.trim() || null }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['financial-checks'] })
            setShowForm(false); form.reset(emptyForm)
            toast.success('Cheque registrado com sucesso')
        },
        onError: (err: unknown) => {
            handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, form.setError, 'Erro ao registrar cheque')
        },
    })

    const statusMut = useMutation({
        mutationFn: ({ id, status }: { id: number; status: string }) => financialApi.checks.updateStatus(id, { status }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['financial-checks'] })
            setStatusTarget(null); setNewStatus('')
            toast.success('Status atualizado com sucesso')
        },
        onError: (error: ApiError) => {
            toast.error(getApiErrorMessage(error, 'Erro ao atualizar status'))
        },
    })

    return (
        <div className="space-y-5">
            <PageHeader
                title="Cheques"
                subtitle="Gestão de cheques recebidos e emitidos"
                count={canView ? pagination.total : 0}
                actions={canCreate ? [{ label: 'Novo Cheque', onClick: () => { form.reset(emptyForm); setShowForm(true) }, icon: <Plus className="h-4 w-4" /> }] : []}
            />

            <div className="flex gap-3">
                <select value={typeFilter} onChange={e => setTypeFilter(e.target.value)} aria-label="Tipo" className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <option value="">Todos os tipos</option>
                    <option value="received">Recebido</option>
                    <option value="issued">Emitido</option>
                </select>
                <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} aria-label="Status" className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <option value="">Todos os status</option>
                    {Object.entries(statusConfig).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                </select>
            </div>

            {!canView && (canCreate || canUpdate) ? (
                <div className="rounded-xl border border-dashed border-default bg-surface-0 p-4 text-sm text-surface-600 shadow-card">
                    Voce pode operar cheques, mas nao possui permissao para listar o historico.
                </div>
            ) : null}

            {canView ? (
                <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-subtle bg-surface-50">
                            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Nº</th>
                            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Tipo</th>
                            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Banco</th>
                            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Emitente</th>
                            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Vencimento</th>
                            <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Status</th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Valor</th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-subtle">
                        {isLoading ? (
                            <tr><td colSpan={8} className="px-4 py-12 text-center text-sm text-surface-500">Carregando...</td></tr>
                        ) : isError ? (
                            <tr><td colSpan={8} className="px-4 py-12 text-center text-sm text-red-600">Erro ao carregar. <button className="underline" onClick={() => refetch()}>Tentar novamente</button></td></tr>
                        ) : records.length === 0 ? (
                            <tr><td colSpan={8} className="px-4 py-2"><EmptyState icon={<FileText className="h-5 w-5 text-surface-300" />} message="Nenhum cheque encontrado" compact /></td></tr>
                        ) : (records || []).map(r => (
                            <tr key={r.id} className="hover:bg-surface-50 transition-colors">
                                <td className="px-4 py-3 text-sm font-medium text-surface-900">{r.number}</td>
                                <td className="px-4 py-3"><Badge variant={r.type === 'received' ? 'info' : 'warning'}>{typeLabels[r.type] ?? r.type}</Badge></td>
                                <td className="px-4 py-3 text-sm text-surface-600">{r.bank}</td>
                                <td className="px-4 py-3 text-sm text-surface-600">{r.issuer}</td>
                                <td className="px-4 py-3 text-sm text-surface-500">{fmtDate(r.due_date)}</td>
                                <td className="px-4 py-3"><Badge variant={statusConfig[r.status]?.variant}>{statusConfig[r.status]?.label ?? r.status}</Badge></td>
                                <td className="px-4 py-3 text-right text-sm font-semibold text-surface-900">{fmtBRL(r.amount)}</td>
                                <td className="px-4 py-3 text-right">
                                    {canUpdate ? <Button size="sm" variant="outline" onClick={() => { setStatusTarget(r); setNewStatus(r.status) }}>Alterar Status</Button> : null}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                </div>
            ) : null}

            {canView && pagination.lastPage > 1 && (
                <div className="flex items-center justify-between rounded-xl border border-default bg-surface-0 px-4 py-3 shadow-card">
                    <span className="text-sm text-surface-500">{pagination.total} registro(s)</span>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" disabled={pagination.currentPage <= 1} onClick={() => setPage(p => p - 1)}>Anterior</Button>
                        <span className="text-sm text-surface-700">Página {pagination.currentPage} de {pagination.lastPage}</span>
                        <Button variant="outline" size="sm" disabled={pagination.currentPage >= pagination.lastPage} onClick={() => setPage(p => p + 1)}>Próxima</Button>
                    </div>
                </div>
            )}

            {/* CREATE FORM */}
            <Modal open={showForm} onOpenChange={setShowForm} title="Registrar Cheque" size="lg">
                <form onSubmit={form.handleSubmit(data => saveMut.mutate(data))} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Controller control={form.control} name="type" render={({ field, fieldState }) => (
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-surface-700">Tipo *</label>
                                <select {...field} aria-label="Tipo de cheque" className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-default focus:border-brand-400 focus:ring-brand-500/15'}`}>
                                    <option value="received">Recebido</option>
                                    <option value="issued">Emitido</option>
                                </select>
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />

                        <Controller control={form.control} name="number" render={({ field, fieldState }) => (
                            <Input label="Número *" {...field} value={field.value || ''} error={fieldState.error?.message} required />
                        )} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Controller control={form.control} name="bank" render={({ field, fieldState }) => (
                            <Input label="Banco *" {...field} value={field.value || ''} error={fieldState.error?.message} required />
                        )} />

                        <Controller control={form.control} name="issuer" render={({ field, fieldState }) => (
                            <Input label="Emitente *" {...field} value={field.value || ''} error={fieldState.error?.message} required />
                        )} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Controller control={form.control} name="amount" render={({ field, fieldState }) => (
                            <CurrencyInput label="Valor (R$) *" value={field.value || 0} onChange={field.onChange} error={fieldState.error?.message} required />
                        )} />

                        <Controller control={form.control} name="due_date" render={({ field, fieldState }) => (
                            <Input label="Vencimento *" type="date" {...field} value={field.value || ''} error={fieldState.error?.message} required />
                        )} />
                    </div>
                    <Controller control={form.control} name="notes" render={({ field, fieldState }) => (
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Observações</label>
                            <textarea {...field} value={field.value || ''} rows={2} aria-label="Observações" className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-default focus:border-brand-400 focus:ring-brand-500/15'}`} />
                            {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                        </div>
                    )} />
                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" type="button" onClick={() => setShowForm(false)}>Cancelar</Button>
                        <Button type="submit" loading={saveMut.isPending}>Registrar</Button>
                    </div>
                </form>
            </Modal>

            {/* STATUS CHANGE */}
            <Modal open={!!statusTarget} onOpenChange={() => setStatusTarget(null)} title="Alterar Status do Cheque">
                <div className="space-y-4">
                    {statusTarget && (
                        <div className="rounded-lg bg-surface-50 p-3 text-sm">
                            <p className="font-medium">Cheque Nº {statusTarget.number}</p>
                            <p className="text-surface-500">{statusTarget.bank} — {statusTarget.issuer} — {fmtBRL(statusTarget.amount)}</p>
                        </div>
                    )}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Novo Status</label>
                        <select value={newStatus} onChange={e => setNewStatus(e.target.value)} aria-label="Novo status" className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                            {Object.entries(statusConfig).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                        </select>
                    </div>
                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" onClick={() => setStatusTarget(null)}>Cancelar</Button>
                        <Button loading={statusMut.isPending} onClick={() => { if (statusTarget) statusMut.mutate({ id: statusTarget.id, status: newStatus }) }}>Atualizar Status</Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
