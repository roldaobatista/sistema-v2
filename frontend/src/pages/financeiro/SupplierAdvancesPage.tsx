import { useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Truck } from 'lucide-react'
import { toast } from 'sonner'
import { handleFormError } from '@/lib/form-utils'
import api from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { supplierAdvanceSchema, type SupplierAdvanceFormData as AdvanceFormData } from './schemas'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { Modal } from '@/components/ui/modal'
import { useAuthStore } from '@/stores/auth-store'

const fmtBRL = (val: string | number) => Number(val).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDate = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString('pt-BR')

const statusConfig: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'danger' | 'default' }> = {
    pending: { label: 'Pendente', variant: 'warning' },
    partial: { label: 'Parcial', variant: 'info' },
    paid: { label: 'Pago', variant: 'success' },
    overdue: { label: 'Vencido', variant: 'danger' },
    cancelled: { label: 'Cancelado', variant: 'default' },
}

interface Advance {
    id: number
    description: string
    amount: string
    amount_paid: string
    due_date: string
    status: string
    supplier?: { id: number; name: string }
}

const emptyForm: AdvanceFormData = { supplier_id: '', description: '', amount: '0', due_date: '', notes: '' }

export function SupplierAdvancesPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const isSuperAdmin = hasRole('super_admin')
    const canView = isSuperAdmin || hasPermission('finance.payable.view') || hasPermission('financeiro.view')
    const canCreate = isSuperAdmin || hasPermission('finance.payable.create') || hasPermission('financeiro.payment.create')
    const [page, setPage] = useState(1)
    const [showForm, setShowForm] = useState(false)

    const form = useForm<AdvanceFormData>({
        resolver: zodResolver(supplierAdvanceSchema),
        defaultValues: emptyForm,
    })

    const { data: res, isLoading, isError, refetch } = useQuery({
        queryKey: ['supplier-advances', page],
        queryFn: () => financialApi.supplierAdvances.list({ page }),
        enabled: canView,
    })
    const records: Advance[] = res?.data?.data ?? []
    const pagination = { currentPage: res?.data?.current_page ?? 1, lastPage: res?.data?.last_page ?? 1, total: res?.data?.total ?? 0 }

    const { data: suppRes } = useQuery({
        queryKey: ['suppliers-select'],
        queryFn: () => api.get('/financial/lookups/suppliers', { params: { limit: 100 } }),
        enabled: showForm && canCreate,
    })
    const suppliers: { id: number; name: string }[] = suppRes?.data?.data ?? []

    const saveMut = useMutation({
        mutationFn: (data: AdvanceFormData) => financialApi.supplierAdvances.store({ ...data, supplier_id: Number(data.supplier_id), notes: data.notes?.trim() || null }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['supplier-advances'] })
            setShowForm(false)
            form.reset(emptyForm)
            toast.success('Adiantamento registrado com sucesso')
        },
        onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, form.setError, 'Erro ao registrar adiantamento'),
    })

    return (
        <div className="space-y-5">
            <PageHeader
                title="Adiantamentos a Fornecedores"
                subtitle="Gestão de adiantamentos"
                count={canView ? pagination.total : 0}
                actions={canCreate ? [{ label: 'Novo Adiantamento', onClick: () => { form.reset(emptyForm); setShowForm(true) }, icon: <Plus className="h-4 w-4" /> }] : []}
            />

            {!canView && canCreate ? (
                <div className="rounded-xl border border-dashed border-default bg-surface-0 p-4 text-sm text-surface-600 shadow-card">
                    Voce pode registrar adiantamentos, mas nao possui permissao para listar o historico.
                </div>
            ) : null}

            {canView ? (
                <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-subtle bg-surface-50">
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Descricao</th>
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Fornecedor</th>
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Vencimento</th>
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Status</th>
                                <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Valor</th>
                                <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Pago</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-subtle">
                            {isLoading ? (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-sm text-surface-500">Carregando...</td></tr>
                            ) : isError ? (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-sm text-red-600">Erro ao carregar. <button className="underline" onClick={() => refetch()}>Tentar novamente</button></td></tr>
                            ) : records.length === 0 ? (
                                <tr><td colSpan={6} className="px-4 py-2"><EmptyState icon={<Truck className="h-5 w-5 text-surface-300" />} message="Nenhum adiantamento encontrado" compact /></td></tr>
                            ) : records.map((record) => (
                                <tr key={record.id} className="hover:bg-surface-50 transition-colors">
                                    <td className="px-4 py-3 text-sm font-medium text-surface-900">{record.description}</td>
                                    <td className="px-4 py-3 text-sm text-surface-600">{record.supplier?.name ?? '-'}</td>
                                    <td className="px-4 py-3 text-sm text-surface-500">{fmtDate(record.due_date)}</td>
                                    <td className="px-4 py-3"><Badge variant={statusConfig[record.status]?.variant}>{statusConfig[record.status]?.label ?? record.status}</Badge></td>
                                    <td className="px-4 py-3 text-right text-sm font-semibold text-surface-900">{fmtBRL(record.amount)}</td>
                                    <td className="px-4 py-3 text-right text-sm text-surface-600">{fmtBRL(record.amount_paid)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : null}

            {canView && pagination.lastPage > 1 ? (
                <div className="flex items-center justify-between rounded-xl border border-default bg-surface-0 px-4 py-3 shadow-card">
                    <span className="text-sm text-surface-500">{pagination.total} registro(s)</span>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" disabled={pagination.currentPage <= 1} onClick={() => setPage((p) => p - 1)}>Anterior</Button>
                        <span className="text-sm text-surface-700">Pagina {pagination.currentPage} de {pagination.lastPage}</span>
                        <Button variant="outline" size="sm" disabled={pagination.currentPage >= pagination.lastPage} onClick={() => setPage((p) => p + 1)}>Proxima</Button>
                    </div>
                </div>
            ) : null}

            <Modal open={showForm} onOpenChange={setShowForm} title="Novo Adiantamento a Fornecedor" size="lg">
                <form onSubmit={form.handleSubmit((data) => saveMut.mutate(data))} className="space-y-4">
                    <Controller control={form.control} name="supplier_id" render={({ field, fieldState }) => (
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Fornecedor *</label>
                            <select {...field} aria-label="Fornecedor" className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/15 ${fieldState.error ? 'border-red-500 focus:border-red-500' : 'border-default focus:border-brand-400'}`}>
                                <option value="">Selecionar</option>
                                {suppliers.map((supplier) => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}
                            </select>
                            {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                        </div>
                    )} />

                    <Controller control={form.control} name="description" render={({ field, fieldState }) => (
                        <Input label="Descricao *" {...field} error={fieldState.error?.message} required />
                    )} />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Controller control={form.control} name="amount" render={({ field, fieldState }) => (
                            <CurrencyInput label="Valor (R$) *" value={Number(field.value) || 0} onChange={(val) => field.onChange(String(val))} error={fieldState.error?.message} required />
                        )} />

                        <Controller control={form.control} name="due_date" render={({ field, fieldState }) => (
                            <Input label="Vencimento *" type="date" {...field} error={fieldState.error?.message} required />
                        )} />
                    </div>

                    <Controller control={form.control} name="notes" render={({ field, fieldState }) => (
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Observacoes</label>
                            <textarea {...field} rows={2} aria-label="Observações" className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/15 ${fieldState.error ? 'border-red-500 focus:border-red-500' : 'border-default focus:border-brand-400'}`} />
                            {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                        </div>
                    )} />

                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" type="button" onClick={() => setShowForm(false)}>Cancelar</Button>
                        <Button type="submit" loading={saveMut.isPending}>Registrar</Button>
                    </div>
                </form>
            </Modal>
        </div>
    )
}
