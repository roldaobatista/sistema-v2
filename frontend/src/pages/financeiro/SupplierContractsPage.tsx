import { useMemo, useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import type { AxiosError } from 'axios'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, FileText, RotateCcw, Pencil, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { handleFormError } from '@/lib/form-utils'
import { requiredString, optionalString } from '@/schemas/common'
import api from '@/lib/api'
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
const fmtDate = (d: string) => new Date(`${d}T00:00:00`).toLocaleDateString('pt-BR')
const parseDateInput = (d: string | null | undefined) => (d ? d.slice(0, 10) : '')

const statusConfig: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'danger' | 'default' }> = {
    active: { label: 'Ativo', variant: 'success' },
    expired: { label: 'Expirado', variant: 'danger' },
    cancelled: { label: 'Cancelado', variant: 'default' },
}

interface Contract {
    id: number
    supplier_id: number
    description: string
    start_date: string
    end_date: string
    value: string
    payment_frequency: string
    auto_renew: boolean
    status: string
    notes?: string | null
    supplier?: { id: number; name: string }
}

const contractSchema = z.object({
    supplier_id: requiredString('Fornecedor é obrigatório'),
    description: requiredString('Descrição é obrigatória'),
    start_date: requiredString('Data de início é obrigatória'),
    end_date: requiredString('Data de término é obrigatória'),
    value: requiredString('Valor é obrigatório').min(1, 'Valor obrigatório'),
    payment_frequency: requiredString('Frequência de pagamento é obrigatória'),
    auto_renew: z.boolean().default(false),
    notes: optionalString,
})

type ContractFormData = z.infer<typeof contractSchema>
const emptyForm: ContractFormData = { supplier_id: '', description: '', start_date: '', end_date: '', value: '0', payment_frequency: 'monthly', auto_renew: false, notes: '' }

interface LookupItem {
    id: number
    name: string
    slug?: string
}

const FALLBACK_FREQUENCY_OPTIONS: LookupItem[] = [
    { id: 1, name: 'Mensal', slug: 'monthly' },
    { id: 2, name: 'Trimestral', slug: 'quarterly' },
    { id: 3, name: 'Anual', slug: 'annual' },
    { id: 4, name: 'Unico', slug: 'one_time' },
]

const unwrapNestedData = (payload: unknown): unknown => {
    if (payload && typeof payload === 'object' && 'data' in payload) {
        const nested = (payload as { data?: unknown }).data
        if (nested !== undefined) {
            return unwrapNestedData(nested)
        }
    }

    return payload
}

const normalizeLookupList = (payload: unknown): LookupItem[] => {
    if (Array.isArray(payload)) return payload as LookupItem[]
    if (payload && typeof payload === 'object' && Array.isArray((payload as { data?: unknown }).data)) {
        return (payload as { data: LookupItem[] }).data
    }
    return []
}

const normalizeContractsPayload = (payload: unknown): { data: Contract[]; current_page: number; last_page: number; total: number } => {
    if (Array.isArray(payload)) {
        return {
            data: payload as Contract[],
            current_page: 1,
            last_page: 1,
            total: payload.length,
        }
    }

    if (!payload || typeof payload !== 'object') {
        return { data: [], current_page: 1, last_page: 1, total: 0 }
    }

    const directData = (payload as { data?: unknown }).data
    if (Array.isArray(directData)) {
        return {
            data: directData as Contract[],
            current_page: Number((payload as { current_page?: number }).current_page ?? 1),
            last_page: Number((payload as { last_page?: number }).last_page ?? 1),
            total: Number((payload as { total?: number }).total ?? directData.length),
        }
    }

    if (directData && typeof directData === 'object') {
        return normalizeContractsPayload(directData)
    }

    return { data: [], current_page: 1, last_page: 1, total: 0 }
}

export function SupplierContractsPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const isSuperAdmin = hasRole('super_admin')
    const canView = isSuperAdmin || hasPermission('finance.payable.view') || hasPermission('financeiro.view')
    const canCreate = isSuperAdmin || hasPermission('finance.payable.create') || hasPermission('financeiro.payment.create')
    const canUpdate = isSuperAdmin || hasPermission('finance.payable.update') || hasPermission('financeiro.payment.create')
    const canDelete = isSuperAdmin || hasPermission('finance.payable.delete') || hasPermission('financeiro.payment.create')
    const [statusFilter, setStatusFilter] = useState('')
    const [page, setPage] = useState(1)
    const [showForm, setShowForm] = useState(false)
    const [editingId, setEditingId] = useState<number | null>(null)
    const [deleteTarget, setDeleteTarget] = useState<Contract | null>(null)

    const form = useForm<ContractFormData>({
        resolver: zodResolver(contractSchema),
        defaultValues: emptyForm,
    })

    const { data: res, isLoading, isError, refetch } = useQuery({
        queryKey: ['supplier-contracts', statusFilter, page],
        queryFn: () => financialApi.supplierContracts.list({ status: statusFilter || undefined, page }),
        enabled: canView,
    })
    const contractsPayload = normalizeContractsPayload(res?.data)
    const records: Contract[] = contractsPayload.data
    const pagination = { currentPage: contractsPayload.current_page, lastPage: contractsPayload.last_page, total: contractsPayload.total }

    const { data: suppRes } = useQuery({
        queryKey: ['suppliers-select'],
        queryFn: () => api.get('/financial/lookups/suppliers', { params: { limit: 100 } }),
        enabled: showForm && (canCreate || canUpdate),
    })
    const suppliers = normalizeLookupList(unwrapNestedData(suppRes?.data)) as Array<{ id: number; name: string }>

    const { data: paymentFrequenciesLookup } = useQuery({
        queryKey: ['lookups', 'supplier-contract-payment-frequencies'],
        queryFn: () => api.get('/financial/lookups/supplier-contract-payment-frequencies').then((response) => normalizeLookupList(unwrapNestedData(response.data))),
        enabled: showForm && (canCreate || canUpdate),
    })

    const frequencyOptions = useMemo<LookupItem[]>(
        () => (paymentFrequenciesLookup && paymentFrequenciesLookup.length > 0 ? paymentFrequenciesLookup : FALLBACK_FREQUENCY_OPTIONS),
        [paymentFrequenciesLookup],
    )

    const frequencyLabelMap = useMemo<Record<string, string>>(() => {
        const map: Record<string, string> = {
            monthly: 'Mensal',
            quarterly: 'Trimestral',
            annual: 'Anual',
            one_time: 'Unico',
        }
        for (const item of frequencyOptions) {
            if (item.slug) map[item.slug] = item.name
        }
        return map
    }, [frequencyOptions])

    const closeForm = () => {
        setShowForm(false)
        setEditingId(null)
        form.reset(emptyForm)
    }

    const saveMut = useMutation({
        mutationFn: (data: ContractFormData) => {
            const payload = {
                ...data,
                supplier_id: Number(data.supplier_id),
                notes: data.notes?.trim() || null,
            }
            if (editingId) {
                return financialApi.supplierContracts.update(editingId, payload)
            }
            return financialApi.supplierContracts.store(payload)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['supplier-contracts'] })
            closeForm()
            toast.success(editingId ? 'Contrato atualizado com sucesso' : 'Contrato criado com sucesso')
        },
        onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, form.setError, editingId ? 'Erro ao atualizar contrato' : 'Erro ao criar contrato'),
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => financialApi.supplierContracts.destroy(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['supplier-contracts'] })
            setDeleteTarget(null)
            toast.success('Contrato excluido com sucesso')
        },
        onError: (error: unknown) => {
            const payload = (error as { response?: { data?: { message?: string } } })?.response?.data
            toast.error(payload?.message ?? 'Erro ao excluir contrato')
        },
    })

    const openCreate = () => {
        setEditingId(null)
        form.reset(emptyForm)
        setShowForm(true)
    }

    const openEdit = (record: Contract) => {
        setEditingId(record.id)
        form.reset({
            supplier_id: String(record.supplier_id),
            description: record.description ?? '',
            start_date: parseDateInput(record.start_date),
            end_date: parseDateInput(record.end_date),
            value: String(record.value ?? '0'),
            payment_frequency: record.payment_frequency || 'monthly',
            auto_renew: !!record.auto_renew,
            notes: record.notes ?? '',
        })
        setShowForm(true)
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Contratos de Fornecedor"
                subtitle="Contratos recorrentes com fornecedores"
                count={canView ? pagination.total : 0}
                actions={canCreate ? [{ label: 'Novo Contrato', onClick: openCreate, icon: <Plus className="h-4 w-4" /> }] : []}
            />

            <div className="flex gap-3">
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} aria-label="Status" className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <option value="">Todos os status</option>
                    {Object.entries(statusConfig).map(([key, value]) => <option key={key} value={key}>{value.label}</option>)}
                </select>
            </div>

            {!canView && (canCreate || canUpdate || canDelete) ? (
                <div className="rounded-xl border border-dashed border-default bg-surface-0 p-4 text-sm text-surface-600 shadow-card">
                    Voce pode operar contratos, mas nao possui permissao para listar o historico.
                </div>
            ) : null}

            {canView ? (
                <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-subtle bg-surface-50">
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Descricao</th>
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Fornecedor</th>
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Vigencia</th>
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Frequencia</th>
                                <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Status</th>
                                <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Valor</th>
                                <th className="px-4 py-2.5 text-center text-xs font-semibold uppercase text-surface-600">Renova</th>
                                <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-subtle">
                            {isLoading ? (
                                <tr><td colSpan={8} className="px-4 py-12 text-center text-sm text-surface-500">Carregando...</td></tr>
                            ) : isError ? (
                                <tr><td colSpan={8} className="px-4 py-12 text-center text-sm text-red-600">Erro ao carregar. <button className="underline" onClick={() => refetch()}>Tentar novamente</button></td></tr>
                            ) : records.length === 0 ? (
                                <tr><td colSpan={8} className="px-4 py-2"><EmptyState icon={<FileText className="h-5 w-5 text-surface-300" />} message="Nenhum contrato encontrado" compact /></td></tr>
                            ) : records.map((record) => (
                                <tr key={record.id} className="hover:bg-surface-50 transition-colors">
                                    <td className="px-4 py-3 text-sm font-medium text-surface-900">{record.description}</td>
                                    <td className="px-4 py-3 text-sm text-surface-600">{record.supplier?.name ?? '-'}</td>
                                    <td className="px-4 py-3 text-sm text-surface-500">{fmtDate(parseDateInput(record.start_date))} - {fmtDate(parseDateInput(record.end_date))}</td>
                                    <td className="px-4 py-3 text-sm text-surface-600">{frequencyLabelMap[record.payment_frequency] ?? record.payment_frequency}</td>
                                    <td className="px-4 py-3"><Badge variant={statusConfig[record.status]?.variant}>{statusConfig[record.status]?.label ?? record.status}</Badge></td>
                                    <td className="px-4 py-3 text-right text-sm font-semibold text-surface-900">{fmtBRL(record.value)}</td>
                                    <td className="px-4 py-3 text-center">{record.auto_renew ? <RotateCcw className="mx-auto h-4 w-4 text-emerald-500" /> : <span className="text-surface-300">-</span>}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-2">
                                            {canUpdate ? (
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(record)} aria-label={`Editar contrato ${record.description}`}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                            {canDelete ? (
                                                <Button variant="ghost" size="icon" onClick={() => setDeleteTarget(record)} aria-label={`Excluir contrato ${record.description}`}>
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </td>
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

            <Modal open={showForm} onOpenChange={(open) => { if (!open) closeForm(); else setShowForm(true) }} title={editingId ? 'Editar Contrato de Fornecedor' : 'Novo Contrato de Fornecedor'} size="lg">
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
                        <Controller control={form.control} name="start_date" render={({ field, fieldState }) => (
                            <Input label="Inicio *" type="date" {...field} error={fieldState.error?.message} required />
                        )} />
                        <Controller control={form.control} name="end_date" render={({ field, fieldState }) => (
                            <Input label="Fim *" type="date" {...field} error={fieldState.error?.message} required />
                        )} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Controller control={form.control} name="value" render={({ field, fieldState }) => (
                            <CurrencyInput label="Valor (R$) *" value={Number(field.value) || 0} onChange={(val) => field.onChange(String(val))} error={fieldState.error?.message} required />
                        )} />

                        <Controller control={form.control} name="payment_frequency" render={({ field, fieldState }) => (
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-surface-700">Frequencia *</label>
                                <select {...field} aria-label="Frequencia" className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/15 ${fieldState.error ? 'border-red-500 focus:border-red-500' : 'border-default focus:border-brand-400'}`}>
                                    {frequencyOptions.map((freq) => (
                                        <option key={freq.id} value={freq.slug || freq.name}>{freq.name}</option>
                                    ))}
                                </select>
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />
                    </div>

                    <Controller control={form.control} name="auto_renew" render={({ field }) => (
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={field.value} onChange={(e) => field.onChange(e.target.checked)} className="rounded border-default" />
                            Renovacao automatica
                        </label>
                    )} />

                    <Controller control={form.control} name="notes" render={({ field, fieldState }) => (
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Observacoes</label>
                            <textarea {...field} rows={2} aria-label="Observações" className={`w-full rounded-lg border bg-surface-50 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/15 ${fieldState.error ? 'border-red-500 focus:border-red-500' : 'border-default focus:border-brand-400'}`} />
                            {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                        </div>
                    )} />

                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" type="button" onClick={closeForm}>Cancelar</Button>
                        <Button type="submit" loading={saveMut.isPending}>{editingId ? 'Atualizar Contrato' : 'Criar Contrato'}</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!deleteTarget} onOpenChange={(open) => { if (!open) setDeleteTarget(null) }} title="Excluir contrato" size="sm">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">
                        Confirma exclusao do contrato <strong>{deleteTarget?.description}</strong>?
                    </p>
                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
                        <Button variant="danger" loading={deleteMut.isPending} onClick={() => { if (deleteTarget) deleteMut.mutate(deleteTarget.id) }}>
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
