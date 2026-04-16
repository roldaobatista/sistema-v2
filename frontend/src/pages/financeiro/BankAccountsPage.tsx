import React, { useState } from 'react'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Building2, Plus, Pencil, Trash2, Search, CheckCircle, XCircle,
} from 'lucide-react'
import api from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { formatCurrency } from '@/lib/utils'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import { Modal } from '@/components/ui/modal'
import { FormField } from '@/components/ui/form-field'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { queryKeys } from '@/lib/query-keys'
import type { BankAccount } from '@/types/financial'
import { handleFormError } from '@/lib/form-utils'
import { optionalString, requiredString } from '@/schemas/common'
import { z } from 'zod'

const ACCOUNT_TYPE_LABELS: Record<string, string> = {
    corrente: 'Conta Corrente',
    poupanca: 'Poupança',
    pagamento: 'Conta Pagamento',
}

const bankAccountSchema = z.object({
    name: requiredString('Nome é obrigatório'),
    bank_name: requiredString('Banco é obrigatório'),
    agency: optionalString,
    account_number: optionalString,
    account_type: z.string().default('corrente'),
    pix_key: optionalString,
    balance: z.string().default('0'),
    is_active: z.boolean().default(true),
})

type BankAccountFormData = z.infer<typeof bankAccountSchema>

const defaultValues: BankAccountFormData = {
    name: '', bank_name: '', agency: '', account_number: '',
    account_type: 'corrente', pix_key: '', balance: '0', is_active: true,
}

export function BankAccountsPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const isSuperAdmin = hasRole('super_admin')
    const canCreate = isSuperAdmin || hasPermission('financial.bank_account.create')
    const canUpdate = isSuperAdmin || hasPermission('financial.bank_account.update')
    const canDelete = isSuperAdmin || hasPermission('financial.bank_account.delete')

    const [search, setSearch] = useState('')
    const [showModal, setShowModal] = useState(false)
    const [editing, setEditing] = useState<BankAccount | null>(null)
    const [deleteTarget, setDeleteTarget] = useState<BankAccount | null>(null)

    const { register, handleSubmit: formSubmit, reset, control, setError, formState: { errors } } = useForm<BankAccountFormData>({
        resolver: zodResolver(bankAccountSchema) as Resolver<BankAccountFormData>,
        defaultValues,
    })

    const { data: accountsRes, isLoading } = useQuery({
        queryKey: queryKeys.financial.bankAccounts.list({ search }),
        queryFn: () => financialApi.bankAccounts.list({ search: search || undefined }),
    })
    const { data: accountTypeItems = [] } = useQuery({
        queryKey: ['lookups', 'bank-account-types'],
        queryFn: async () => {
            const { data } = await api.get('/lookups/bank-account-types')
            const payload = data?.data ?? data
            return Array.isArray(payload) ? payload : []
        },
        staleTime: 5 * 60_000,
    })

    const rawAccounts = accountsRes?.data
    const accounts: BankAccount[] = Array.isArray(rawAccounts) ? rawAccounts : (rawAccounts as { data?: BankAccount[] })?.data ?? []
    const accountTypeLabelByValue = Object.fromEntries(
        (accountTypeItems || []).flatMap((item) => {
            const entries: Array<[string, string]> = []
            if (item.slug) entries.push([item.slug, item.name])
            entries.push([item.name, item.name])
            return entries
        })
    )

    const storeMut = useMutation({
        mutationFn: (data: BankAccountFormData) => financialApi.bankAccounts.create(data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.bankAccounts.all })
            broadcastQueryInvalidation(['bank-accounts'], 'Contas Bancárias')
            setShowModal(false)
            toast.success('Conta bancária criada com sucesso')
        },
        onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao criar conta'),
    })

    const updateMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: BankAccountFormData }) => financialApi.bankAccounts.update(id, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.bankAccounts.all })
            broadcastQueryInvalidation(['bank-accounts'], 'Contas Bancárias')
            setShowModal(false)
            setEditing(null)
            toast.success('Conta bancária atualizada com sucesso')
        },
        onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao atualizar conta'),
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => financialApi.bankAccounts.destroy(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.bankAccounts.all })
            broadcastQueryInvalidation(['bank-accounts'], 'Contas Bancárias')
            setDeleteTarget(null)
            toast.success('Conta bancária excluída')
        },
        onError: (err: { response?: { data?: { message?: string } } }) => {
            toast.error(err?.response?.data?.message ?? 'Erro ao excluir conta')
        },
    })

    const openCreate = () => {
        setEditing(null)
        reset(defaultValues)
        setShowModal(true)
    }

    const openEdit = (acc: BankAccount) => {
        setEditing(acc)
        reset({
            name: acc.name,
            bank_name: acc.bank_name ?? '',
            agency: acc.agency ?? '',
            account_number: acc.account_number ?? '',
            account_type: acc.account_type ?? 'corrente',
            pix_key: acc.pix_key ?? '',
            balance: acc.balance != null ? String(acc.balance) : '0',
            is_active: acc.is_active ?? true,
        })
        setShowModal(true)
    }

    const onSubmit = (data: BankAccountFormData) => {
        if (editing) {
            updateMut.mutate({ id: editing.id, data })
        } else {
            storeMut.mutate(data)
        }
    }

    const isSaving = storeMut.isPending || updateMut.isPending

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Contas Bancárias</h1>
                    <p className="text-sm text-surface-500">
                        {accounts.length} {accounts.length === 1 ? 'conta cadastrada' : 'contas cadastradas'}
                    </p>
                </div>
                {canCreate && (
                    <Button onClick={openCreate} icon={<Plus className="h-4 w-4" />}>Nova Conta</Button>
                )}
            </div>

            <div className="relative max-w-sm">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                <input
                    type="text"
                    placeholder="Buscar por nome, banco ou conta..."
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    className="w-full rounded-lg border border-default bg-surface-50 py-2.5 pl-9 pr-3 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                />
            </div>

            <div className="rounded-xl border border-default bg-surface-0 shadow-card overflow-hidden">
                {isLoading ? (
                    <div className="py-12 text-center text-sm text-surface-400">Carregando...</div>
                ) : accounts.length === 0 ? (
                    <div className="py-12 text-center">
                        <Building2 className="mx-auto h-10 w-10 text-surface-300" />
                        <p className="mt-2 text-sm text-surface-400">Nenhuma conta bancária encontrada</p>
                        {canCreate && (
                            <Button variant="outline" size="sm" className="mt-3" onClick={openCreate}>
                                Cadastrar primeira conta
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-subtle bg-surface-50/50">
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Nome</th>
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Banco</th>
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Agência / Conta</th>
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Tipo</th>
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">PIX</th>
                                    <th className="px-4 py-3 text-right font-medium text-surface-600">Saldo</th>
                                    <th className="px-4 py-3 text-center font-medium text-surface-600">Status</th>
                                    <th className="px-4 py-3 text-right font-medium text-surface-600">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {(accounts || []).map(acc => (
                                    <tr key={acc.id} className="hover:bg-surface-50/50 transition-colors">
                                        <td className="px-4 py-3 font-medium text-surface-800">{acc.name}</td>
                                        <td className="px-4 py-3 text-surface-600">{acc.bank_name}</td>
                                        <td className="px-4 py-3 text-surface-600 tabular-nums">
                                            {acc.agency && <span>AG {acc.agency}</span>}
                                            {acc.agency && acc.account_number && <span> / </span>}
                                            {acc.account_number && <span>CC {acc.account_number}</span>}
                                            {!acc.agency && !acc.account_number && <span className="text-surface-300">—</span>}
                                        </td>
                                        <td className="px-4 py-3 text-surface-600">{acc.account_type != null ? (accountTypeLabelByValue[acc.account_type] ?? ACCOUNT_TYPE_LABELS[acc.account_type] ?? acc.account_type) : '—'}</td>
                                        <td className="px-4 py-3 text-surface-600 text-xs font-mono">{acc.pix_key || <span className="text-surface-300">—</span>}</td>
                                        <td className="px-4 py-3 text-right tabular-nums font-semibold text-surface-800">{formatCurrency(Number(acc.balance))}</td>
                                        <td className="px-4 py-3 text-center">
                                            {acc.is_active ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                    <CheckCircle className="h-3 w-3" /> Ativa
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                                                    <XCircle className="h-3 w-3" /> Inativa
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                {canUpdate && (
                                                    <button onClick={() => openEdit(acc)} className="rounded-md p-1.5 text-surface-400 hover:bg-surface-100 hover:text-brand-600 transition-colors"
                                                        title="Editar" aria-label={`Editar conta ${acc.name}`} type="button">
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {canDelete && (
                                                    <button onClick={() => setDeleteTarget(acc)} className="rounded-md p-1.5 text-surface-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                                                        title="Excluir" aria-label={`Excluir conta ${acc.name}`} type="button">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Modal open={showModal} onOpenChange={setShowModal} title={editing ? 'Editar Conta Bancária' : 'Nova Conta Bancária'} size="md">
                <form onSubmit={formSubmit(onSubmit)} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField label="Nome *" error={errors.name?.message} required>
                            <Input {...register('name')} placeholder="Ex: Bradesco AG 1234" />
                        </FormField>
                        <FormField label="Banco *" error={errors.bank_name?.message} required>
                            <Input {...register('bank_name')} placeholder="Ex: Bradesco" />
                        </FormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField label="Agência" error={errors.agency?.message}>
                            <Input {...register('agency')} />
                        </FormField>
                        <FormField label="Número da Conta" error={errors.account_number?.message}>
                            <Input {...register('account_number')} />
                        </FormField>
                        <Controller control={control} name="account_type" render={({ field }) => (
                            <LookupCombobox
                                lookupType="bank-account-types"
                                label="Tipo *"
                                value={field.value}
                                onChange={field.onChange}
                                placeholder="Selecionar tipo"
                                allowCreate={false}
                            />
                        )} />
                    </div>
                    <Controller control={control} name="is_active" render={({ field }) => (
                        <div className="flex items-center gap-2">
                            <input type="checkbox" id="is_active" checked={field.value} onChange={e => field.onChange(e.target.checked)}
                                className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500" />
                            <label htmlFor="is_active" className="text-sm text-surface-700">Conta ativa</label>
                        </div>
                    )} />
                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" type="button" onClick={() => setShowModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={isSaving}>{editing ? 'Salvar Alterações' : 'Criar Conta'}</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)} title="Excluir Conta Bancária" size="sm">
                <p className="text-sm text-surface-600">
                    Tem certeza que deseja excluir a conta <strong>{deleteTarget?.name}</strong>?
                    Esta ação não pode ser desfeita.
                </p>
                <div className="flex justify-end gap-2 pt-4">
                    <Button variant="outline" type="button" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
                    <Button className="bg-red-600 hover:bg-red-700" loading={deleteMut.isPending}
                        onClick={() => deleteTarget && deleteMut.mutate(deleteTarget.id)}>
                        Excluir
                    </Button>
                </div>
            </Modal>
        </div>
    )
}
