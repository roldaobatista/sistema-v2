import React, { useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import type { Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useDebounce } from '@/hooks/useDebounce'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Search, Plus, Pencil, Trash2, Truck, AlertTriangle, Loader2 } from 'lucide-react'
import { useViaCep } from '@/hooks/useViaCep'
import { useCnpjLookup } from '@/hooks/useCnpjLookup'
import { useIbgeStates, useIbgeCities } from '@/hooks/useIbge'
import { toast } from 'sonner'
import api, { getApiErrorMessage } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/ui/iconbutton'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { FormField } from '@/components/ui/form-field'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { useAuthStore } from '@/stores/auth-store'
import { handleFormError } from '@/lib/form-utils'
import { maskPhone } from '@/lib/form-masks'
import { extractDeleteConflict } from '@/types/api'
import { supplierSchema, type SupplierFormData } from '@/schemas/supplier'

interface Supplier {
    id: number; type: 'PF' | 'PJ'; name: string; document: string | null
    trade_name: string | null; email: string | null; phone: string | null
    phone2: string | null; is_active: boolean; notes: string | null
    address_zip: string | null; address_street: string | null
    address_number: string | null; address_complement: string | null
    address_neighborhood: string | null; address_city: string | null
    address_state: string | null
}

const defaultValues: SupplierFormData = {
    type: 'PJ', name: '', document: '', trade_name: '',
    email: '', phone: '', phone2: '',
    address_zip: '', address_street: '', address_number: '',
    address_complement: '', address_neighborhood: '',
    address_city: '', address_state: '',
    notes: '', is_active: true,
}

export function SuppliersPage() {
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('cadastros.supplier.create')
  const canUpdate = hasPermission('cadastros.supplier.update')
  const canDelete = hasPermission('cadastros.supplier.delete')

    const qc = useQueryClient()
    const [search, setSearch] = useState('')
    const debouncedSearch = useDebounce(search, 400)
    const [showForm, setShowForm] = useState(false)
    const [editing, setEditing] = useState<Supplier | null>(null)
    const [showDetail, setShowDetail] = useState<Supplier | null>(null)
    const [showConfirmDelete, setShowConfirmDelete] = useState<Supplier | null>(null)
    const [deleteDependencies, setDeleteDependencies] = useState<Record<string, number> | null>(null)
    const [deleteMessage, setDeleteMessage] = useState<string | null>(null)

    const viaCep = useViaCep()
    const cnpjLookup = useCnpjLookup()
    const { data: ibgeStates = [] } = useIbgeStates()

    const { register, handleSubmit, reset, setValue, getValues, watch, control, setError, formState: { errors } } = useForm<SupplierFormData>({
        resolver: zodResolver(supplierSchema) as Resolver<SupplierFormData>,
        defaultValues,
    })
    const addressState = watch('address_state')
    const { data: ibgeCities = [] } = useIbgeCities(addressState)

    async function handleCepBlur() {
        const zip = getValues('address_zip')
        const result = await viaCep.lookup(zip)
        if (result) {
            setValue('address_street', result.street || getValues('address_street'))
            setValue('address_neighborhood', result.neighborhood || getValues('address_neighborhood'))
            setValue('address_city', result.city || getValues('address_city'))
            setValue('address_state', result.state || getValues('address_state'))
        }
    }

    async function onCnpjLookup() {
        const doc = getValues('document')
        const result = await cnpjLookup.lookup(doc)
        if (result) {
            if (!getValues('name')) setValue('name', result.name ?? '')
            if (!getValues('trade_name')) setValue('trade_name', result.trade_name ?? '')
            if (!getValues('email')) setValue('email', result.email ?? '')
            if (!getValues('phone')) setValue('phone', result.phone ? maskPhone(result.phone) : '')
            if (!getValues('address_zip')) setValue('address_zip', result.address_zip ?? '')
            if (!getValues('address_street')) setValue('address_street', result.address_street ?? '')
            if (!getValues('address_number')) setValue('address_number', result.address_number ?? '')
            if (!getValues('address_complement')) setValue('address_complement', result.address_complement ?? '')
            if (!getValues('address_neighborhood')) setValue('address_neighborhood', result.address_neighborhood ?? '')
            if (!getValues('address_city')) setValue('address_city', result.address_city ?? '')
            if (!getValues('address_state')) setValue('address_state', result.address_state ?? '')
        }
    }

    const { data: res, isLoading } = useQuery({
        queryKey: ['suppliers', debouncedSearch],
        queryFn: () => api.get('/suppliers', { params: { search: debouncedSearch, per_page: 50 } }),
    })
    const suppliers: Supplier[] = res?.data?.data ?? []

    const saveMut = useMutation({
        mutationFn: (data: SupplierFormData) =>
            editing ? api.put(`/suppliers/${editing.id}`, data) : api.post('/suppliers', data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['suppliers'] })
            setShowForm(false)
            toast.success(editing ? 'Fornecedor atualizado com sucesso!' : 'Fornecedor criado com sucesso!')
        },
        onError: (err: unknown) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao salvar fornecedor.'),
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => api.delete(`/suppliers/${id}`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['suppliers'] })
            setShowConfirmDelete(null)
            toast.success('Fornecedor excluído com sucesso!')
        },
        onError: (err: unknown) => {
            const deleteConflict = extractDeleteConflict(err)

            if (deleteConflict) {
                setDeleteDependencies(deleteConflict.dependencies)
                setDeleteMessage(deleteConflict.message)
                return
            }

            toast.error(getApiErrorMessage(err, 'Erro ao excluir fornecedor.'))
            setShowConfirmDelete(null)
        },
    })

    const openCreate = () => {
        setEditing(null)
        reset(defaultValues)
        setShowForm(true)
    }
    const openEdit = (s: Supplier) => {
        setEditing(s)
        reset({
            type: s.type ?? 'PJ', name: s.name, document: s.document ?? '',
            trade_name: s.trade_name ?? '', email: s.email ?? '',
            phone: s.phone ? maskPhone(s.phone) : '', phone2: s.phone2 ? maskPhone(s.phone2) : '',
            address_zip: s.address_zip ?? '', address_street: s.address_street ?? '',
            address_number: s.address_number ?? '', address_complement: s.address_complement ?? '',
            address_neighborhood: s.address_neighborhood ?? '',
            address_city: s.address_city ?? '', address_state: s.address_state ?? '',
            notes: s.notes ?? '', is_active: s.is_active,
        })
        setShowForm(true)
    }

    if (isLoading) return (
        <div className="space-y-5 animate-fade-in">
            <div className="flex items-center justify-between">
                <div>
                    <div className="skeleton h-7 w-32" />
                    <div className="skeleton mt-2 h-4 w-48" />
                </div>
                <div className="skeleton h-9 w-28" />
            </div>
            <div className="skeleton h-10 w-full max-w-md" />
            <div className="rounded-xl border border-default bg-surface-0 h-96"></div>
        </div>
    )

    return (
        <div className="space-y-5 animate-fade-in">
            <PageHeader
                title="Fornecedores"
                subtitle="Cadastro de fornecedores e parceiros"
                count={suppliers.length}
                actions={canCreate ? [{ label: 'Novo Fornecedor', onClick: openCreate, icon: <Plus className="h-4 w-4" /> }] : []}
            />

            <div className="max-w-sm">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input type="text" value={search} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value)}
                        placeholder="Buscar por nome, CNPJ ou telefone..."
                        className="w-full rounded-lg border border-default bg-surface-50 py-2.5 pl-10 pr-4 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-subtle bg-surface-50">
                            <th className="px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500">Fornecedor</th>
                            <th className="hidden px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500 md:table-cell">Documento</th>
                            <th className="hidden px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500 lg:table-cell">Contato</th>
                            <th className="hidden px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500 lg:table-cell">Cidade/UF</th>
                            <th className="px-3.5 py-2.5 text-center text-xs font-medium uppercase tracking-wider text-surface-500">Status</th>
                            <th className="px-3.5 py-2.5 text-right text-xs font-medium uppercase tracking-wider text-surface-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-subtle">
                        {suppliers.length === 0 ? (
                            <tr><td colSpan={6} className="px-4 py-2">
                                <EmptyState
                                    icon={<Truck className="h-5 w-5 text-surface-300" />}
                                    message="Nenhum fornecedor encontrado"
                                    action={{ label: 'Novo Fornecedor', onClick: openCreate, icon: <Plus className="h-4 w-4" /> }}
                                    compact
                                />
                            </td></tr>
                        ) : (suppliers || []).map(s => (
                            <tr key={s.id} className="hover:bg-surface-50 transition-colors duration-100 cursor-pointer" onClick={() => setShowDetail(s)}>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50">
                                            <Truck className="h-4 w-4 text-emerald-600" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-surface-900">{s.name}</p>
                                            {s.trade_name && <p className="text-xs text-surface-400">{s.trade_name}</p>}
                                        </div>
                                    </div>
                                </td>
                                <td className="hidden px-4 py-3 text-sm text-surface-600 md:table-cell">
                                    {s.document || <span className="text-surface-400">—</span>}
                                </td>
                                <td className="hidden px-4 py-3 lg:table-cell">
                                    <div className="text-sm text-surface-600">{s.email || '—'}</div>
                                    <div className="text-xs text-surface-400">{s.phone ? maskPhone(s.phone) : ''}</div>
                                </td>
                                <td className="hidden px-4 py-3 text-sm text-surface-600 lg:table-cell">
                                    {s.address_city ? `${s.address_city}/${s.address_state}` : '—'}
                                </td>
                                <td className="px-3.5 py-2.5 text-center">
                                    <Badge variant={s.is_active ? 'success' : 'danger'}>
                                        {s.is_active ? 'Ativo' : 'Inativo'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3" onClick={e => e.stopPropagation()}>
                                    <div className="flex items-center justify-end gap-1">
                                        {canUpdate && (
                                            <IconButton label="Editar" icon={<Pencil className="h-4 w-4" />} onClick={() => openEdit(s)} className="hover:text-brand-600" />
                                        )}
                                        {canDelete && (
                                            <IconButton label="Excluir" icon={<Trash2 className="h-4 w-4" />} onClick={() => {
                                                setShowConfirmDelete(s)
                                                setDeleteDependencies(null)
                                                setDeleteMessage(null)
                                            }} className="hover:text-red-600" />
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Form Modal */}
            <Modal open={showForm} onOpenChange={setShowForm} title={editing ? 'Editar Fornecedor' : 'Novo Fornecedor'} size="lg">
                <form onSubmit={handleSubmit((data: SupplierFormData) => saveMut.mutate(data))} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField label="Tipo" error={errors.type?.message}>
                            <select {...register('type')} aria-label="Tipo de pessoa"
                                className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                <option value="PJ">Pessoa Jurídica</option>
                                <option value="PF">Pessoa Física</option>
                            </select>
                        </FormField>
                        <div className="relative">
                            <FormField label={watch('type') === 'PJ' ? 'CNPJ' : 'CPF'} error={errors.document?.message}>
                                <Input {...register('document')} />
                            </FormField>
                            {watch('type') === 'PJ' && getValues('document').replace(/\D/g, '').length === 14 && (
                                <button type="button" onClick={onCnpjLookup} disabled={cnpjLookup.loading}
                                    className="absolute right-2 top-8 rounded p-1 text-surface-400 hover:text-brand-600 transition-colors"
                                    title="Buscar dados do CNPJ">
                                    {cnpjLookup.loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
                                </button>
                            )}
                        </div>
                        <FormField label={watch('type') === 'PJ' ? 'Razão Social' : 'Nome'} error={errors.name?.message} required>
                            <Input {...register('name')} placeholder="Nome do fornecedor" />
                        </FormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField label="Nome Fantasia" error={errors.trade_name?.message}>
                            <Input {...register('trade_name')} />
                        </FormField>
                        <FormField label="Email" error={errors.email?.message}>
                            <Input {...register('email')} type="email" placeholder="email@exemplo.com" />
                        </FormField>
                        <FormField label="Telefone" error={errors.phone?.message}>
                            <Input
                                {...register('phone')}
                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setValue('phone', maskPhone(e.target.value), { shouldDirty: true })}
                                maxLength={15}
                                inputMode="tel"
                                placeholder="(00) 00000-0000"
                            />
                        </FormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-4">
                        <div className="relative">
                            <FormField label="CEP" error={errors.address_zip?.message}>
                                <Input {...register('address_zip')} onBlur={handleCepBlur} />
                            </FormField>
                            {viaCep.loading && <Loader2 className="absolute right-2 top-8 h-4 w-4 animate-spin text-brand-500" />}
                        </div>
                        <div className="sm:col-span-2">
                            <FormField label="Rua" error={errors.address_street?.message}>
                                <Input {...register('address_street')} />
                            </FormField>
                        </div>
                        <FormField label="Número" error={errors.address_number?.message}>
                            <Input {...register('address_number')} />
                        </FormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-4">
                        <FormField label="Complemento" error={errors.address_complement?.message}>
                            <Input {...register('address_complement')} />
                        </FormField>
                        <FormField label="Bairro" error={errors.address_neighborhood?.message}>
                            <Input {...register('address_neighborhood')} />
                        </FormField>
                        <Controller
                            control={control}
                            name="address_city"
                            render={({ field }) => (
                                <FormField label="Cidade" error={errors.address_city?.message}>
                                    <select id="supplier_address_city" {...field} disabled={!addressState}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="">{addressState ? 'Selecione' : 'Selecione o UF primeiro'}</option>
                                        {(ibgeCities || []).map(c => <option key={c.id} value={c.name}>{c.name}</option>)}
                                    </select>
                                </FormField>
                            )}
                        />
                        <Controller
                            control={control}
                            name="address_state"
                            render={({ field }) => (
                                <FormField label="UF" error={errors.address_state?.message}>
                                    <select id="supplier_address_state" value={field.value} onChange={e => { field.onChange(e.target.value); setValue('address_city', '') }}
                                        aria-label="UF"
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="">Selecione</option>
                                        {(ibgeStates || []).map(s => <option key={s.abbr} value={s.abbr}>{s.abbr} — {s.name}</option>)}
                                    </select>
                                </FormField>
                            )}
                        />
                    </div>
                    <FormField label="Observações" error={errors.notes?.message}>
                        <textarea id="supplier_notes" {...register('notes')} rows={2}
                            className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                    </FormField>
                    <Controller
                        control={control}
                        name="is_active"
                        render={({ field }) => (
                            <div className="flex items-center gap-2">
                                <input type="checkbox" id="sup-active" checked={field.value} onChange={e => field.onChange(e.target.checked)}
                                    className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500" />
                                <label htmlFor="sup-active" className="text-sm text-surface-700">Ativo</label>
                            </div>
                        )}
                    />
                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" type="button" onClick={() => setShowForm(false)}>Cancelar</Button>
                        <Button type="submit" loading={saveMut.isPending}>{editing ? 'Salvar' : 'Criar'}</Button>
                    </div>
                </form>
            </Modal>

            {/* Detail Modal */}
            <Modal open={!!showDetail} onOpenChange={() => setShowDetail(null)} title="Detalhes do Fornecedor" size="md">
                {showDetail && (
                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div><span className="text-xs text-surface-500">Tipo</span><p className="text-sm font-medium">{showDetail.type === 'PJ' ? 'Pessoa Jurídica' : 'Pessoa Física'}</p></div>
                            <div><span className="text-xs text-surface-500">Documento</span><p className="text-sm font-medium font-mono">{showDetail.document || '—'}</p></div>
                            <div><span className="text-xs text-surface-500">{showDetail.type === 'PJ' ? 'Razão Social' : 'Nome'}</span><p className="text-sm font-medium">{showDetail.name}</p></div>
                            <div><span className="text-xs text-surface-500">Nome Fantasia</span><p className="text-sm font-medium">{showDetail.trade_name ?? '—'}</p></div>
                            <div><span className="text-xs text-surface-500">E-mail</span><p className="text-sm font-medium">{showDetail.email ?? '—'}</p></div>
                            <div><span className="text-xs text-surface-500">Telefone</span><p className="text-sm font-medium">{showDetail.phone ?? '—'}</p></div>
                        </div>
                        {(showDetail.address_street || showDetail.address_city) && (
                            <div className="border-t border-subtle pt-3">
                                <span className="text-xs text-surface-500">Endereço</span>
                                <p className="text-sm font-medium">
                                    {[showDetail.address_street, showDetail.address_number, showDetail.address_complement].filter(Boolean).join(', ')}
                                </p>
                                <p className="text-sm text-surface-600">
                                    {[showDetail.address_neighborhood, showDetail.address_city, showDetail.address_state].filter(Boolean).join(' — ')} {showDetail.address_zip && `• CEP ${showDetail.address_zip}`}
                                </p>
                            </div>
                        )}
                        {showDetail.notes && (
                            <div className="border-t border-subtle pt-3">
                                <span className="text-xs text-surface-500">Observações</span>
                                <p className="text-sm whitespace-pre-wrap">{showDetail.notes}</p>
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            {/* Confirm Delete Modal */}
            <Modal open={!!showConfirmDelete} onOpenChange={() => setShowConfirmDelete(null)} size="sm" title="Excluir Fornecedor">
                <div className="space-y-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 flex-shrink-0">
                            <AlertTriangle className="h-5 w-5 text-red-600" />
                        </div>
                        <div>
                            <h3 className="font-medium text-surface-900">Tem certeza?</h3>
                            <p className="text-sm text-surface-500">
                                Deseja realmente excluir <strong>{showConfirmDelete?.name}</strong>?
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
                            <Button className="bg-red-600 hover:bg-red-700 text-white" loading={deleteMut.isPending}
                                onClick={() => {
                                    if (showConfirmDelete) {
                                        deleteMut.mutate(showConfirmDelete.id)
                                    }
                                }}>
                                Excluir Fornecedor
                            </Button>
                        )}
                    </div>
                </div>
            </Modal>
        </div>
    )
}
