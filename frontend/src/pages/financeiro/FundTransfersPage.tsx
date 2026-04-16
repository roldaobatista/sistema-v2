import React, { useState } from 'react'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    ArrowRightLeft, Plus, Ban, Search, Wallet, TrendingUp, Users,
} from 'lucide-react'
import api from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { queryKeys } from '@/lib/query-keys'
import { formatCurrency } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { Modal } from '@/components/ui/modal'
import { FormField } from '@/components/ui/form-field'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import { handleFormError } from '@/lib/form-utils'
import { requiredString } from '@/schemas/common'
import { z } from 'zod'
import type { FundTransferBankAccountOption, TechnicianOption, FundTransferRecord, FundTransferSummary } from '@/types/financial'

const STATUS_LABELS: Record<string, { label: string; color: string }> = {
    completed: { label: 'Concluída', color: 'bg-emerald-50 text-emerald-700' },
    cancelled: { label: 'Cancelada', color: 'bg-red-50 text-red-700' },
}

const PAYMENT_METHODS: Record<string, string> = {
    pix: 'PIX', ted: 'TED', doc: 'DOC', dinheiro: 'Dinheiro', transferencia: 'Transferência',
}

const fundTransferSchema = z.object({
    bank_account_id: z.string().min(1, 'Selecione a conta bancária'),
    to_user_id: z.string().min(1, 'Selecione o técnico'),
    amount: z.string().min(1, 'Informe o valor'),
    transfer_date: requiredString('Data é obrigatória'),
    payment_method: z.string().default('pix'),
    description: requiredString('Descrição é obrigatória'),
})

type FundTransferFormData = z.infer<typeof fundTransferSchema>

const defaultValues: FundTransferFormData = {
    bank_account_id: '', to_user_id: '', amount: '', transfer_date: new Date().toISOString().split('T')[0], payment_method: 'pix', description: '',
}

export function FundTransfersPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const isSuperAdmin = hasRole('super_admin')
    const canCreate = isSuperAdmin || hasPermission('financial.fund_transfer.create')
    const canCancel = isSuperAdmin || hasPermission('financial.fund_transfer.cancel')

    const [showModal, setShowModal] = useState(false)
    const [cancelTarget, setCancelTarget] = useState<FundTransferRecord | null>(null)
    const canLoadTransferLookups = canCreate && showModal

    const { register, handleSubmit, reset, control, setError, formState: { errors } } = useForm<FundTransferFormData>({
        resolver: zodResolver(fundTransferSchema) as Resolver<FundTransferFormData>,
        defaultValues,
    })
    const [page, setPage] = useState(1)
    const [filters, setFilters] = useState({
        search: '', to_user_id: '', bank_account_id: '', status: '', date_from: '', date_to: '',
    })

    const { data: transfersRes, isLoading } = useQuery({
        queryKey: queryKeys.financial.fundTransfers.list({ page, ...filters }),
        queryFn: () => financialApi.fundTransfers.list({
            page,
            ...(filters.search ? { search: filters.search } : {}),
            ...(filters.to_user_id ? { to_user_id: filters.to_user_id } : {}),
            ...(filters.bank_account_id ? { bank_account_id: filters.bank_account_id } : {}),
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.date_from ? { date_from: filters.date_from } : {}),
            ...(filters.date_to ? { date_to: filters.date_to } : {}),
        }),
    })

    const { data: summaryRes } = useQuery({
        queryKey: queryKeys.financial.fundTransfers.summary,
        queryFn: () => financialApi.fundTransfers.summary(),
    })

    const { data: accountsRes } = useQuery({
        queryKey: [...queryKeys.financial.bankAccounts.list({ is_active: true }), 'active'],
        queryFn: () => financialApi.bankAccounts.list({ is_active: true }),
        enabled: canLoadTransferLookups,
    })

    const { data: techsRes } = useQuery({
        queryKey: ['technicians-options'],
        queryFn: () => api.get('/technicians/options'),
        enabled: canLoadTransferLookups,
    })

    const transfers: FundTransferRecord[] = transfersRes?.data?.data ?? []
    const pagination = transfersRes?.data
    const summary: FundTransferSummary | undefined = summaryRes?.data
    const accountsRaw = accountsRes?.data
    const bankAccounts: FundTransferBankAccountOption[] = Array.isArray(accountsRaw)
        ? accountsRaw.map((a: { id: number; name: string; bank_name?: string }) => ({ id: a.id, name: a.name, bank_name: a.bank_name ?? '' }))
        : (accountsRaw as { data?: { id: number; name: string; bank_name?: string }[] })?.data?.map((a) => ({ id: a.id, name: a.name, bank_name: a.bank_name ?? '' })) ?? []
    const technicians: TechnicianOption[] = techsRes?.data?.data ?? techsRes?.data ?? []

    const createMut = useMutation({
        mutationFn: (data: FundTransferFormData) => financialApi.fundTransfers.create(data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['fund-transfers'] })
            qc.invalidateQueries({ queryKey: queryKeys.financial.fundTransfers.summary })
            qc.invalidateQueries({ queryKey: ['tech-cash-funds'] })
            qc.invalidateQueries({ queryKey: ['tech-cash-summary'] })
            qc.invalidateQueries({ queryKey: ['accounts-payable'] })
            setShowModal(false)
            toast.success('Transferência realizada com sucesso')
        },
        onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao criar transferência'),
    })

    const cancelMut = useMutation({
        mutationFn: (id: number) => financialApi.fundTransfers.cancel(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['fund-transfers'] })
            qc.invalidateQueries({ queryKey: queryKeys.financial.fundTransfers.summary })
            qc.invalidateQueries({ queryKey: ['tech-cash-funds'] })
            qc.invalidateQueries({ queryKey: ['tech-cash-summary'] })
            qc.invalidateQueries({ queryKey: ['accounts-payable'] })
            setCancelTarget(null)
            toast.success('Transferência cancelada com sucesso')
        },
        onError: (err: { response?: { data?: { message?: string } } }) => {
            toast.error(err?.response?.data?.message ?? 'Erro ao cancelar transferência')
        },
    })

    const openCreate = () => {
        reset({ ...defaultValues, transfer_date: new Date().toISOString().split('T')[0] })
        setShowModal(true)
    }

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Transferências p/ Técnicos</h1>
                    <p className="text-sm text-surface-500">Transferências de verba empresa → caixa do técnico</p>
                </div>
                {canCreate && (
                    <Button onClick={openCreate} icon={<Plus className="h-4 w-4" />}>Nova Transferência</Button>
                )}
            </div>

            {summary && (
                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        { label: 'Total do Mês', value: formatCurrency(summary.month_total), icon: ArrowRightLeft, color: 'text-brand-600 bg-brand-50' },
                        { label: 'Total Geral', value: formatCurrency(summary.total_all), icon: TrendingUp, color: 'text-emerald-600 bg-emerald-50' },
                        { label: 'Técnicos (Mês)', value: summary.by_technician?.length ?? 0, icon: Users, color: 'text-sky-600 bg-sky-50' },
                    ].map(s => (
                        <div key={s.label} className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                            <div className="flex items-center gap-3">
                                <div className={`rounded-lg p-2.5 ${s.color}`}><s.icon className="h-5 w-5" /></div>
                                <div>
                                    <p className="text-xs text-surface-500">{s.label}</p>
                                    <p className="text-sm font-semibold tabular-nums text-surface-900">{s.value}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap gap-3">
                <div className="relative flex-1 min-w-[200px] max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input type="text" placeholder="Buscar..."
                        value={filters.search}
                        onChange={e => { setFilters(p => ({ ...p, search: e.target.value })); setPage(1); }}
                        className="w-full rounded-lg border border-default bg-surface-50 py-2 pl-9 pr-3 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                </div>
                <select value={filters.status} aria-label="Filtrar por status"
                    onChange={e => { setFilters(p => ({ ...p, status: e.target.value })); setPage(1); }}
                    className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                    <option value="">Todos os status</option>
                    <option value="completed">Concluída</option>
                    <option value="cancelled">Cancelada</option>
                </select>
                <input type="date" value={filters.date_from} aria-label="Data inicial"
                    onChange={e => { setFilters(p => ({ ...p, date_from: e.target.value })); setPage(1); }}
                    className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none" />
                <input type="date" value={filters.date_to} aria-label="Data final"
                    onChange={e => { setFilters(p => ({ ...p, date_to: e.target.value })); setPage(1); }}
                    className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none" />
            </div>

            <div className="rounded-xl border border-default bg-surface-0 shadow-card overflow-hidden">
                {isLoading ? (
                    <div className="py-12 text-center text-sm text-surface-400">Carregando...</div>
                ) : transfers.length === 0 ? (
                    <div className="py-12 text-center">
                        <Wallet className="mx-auto h-10 w-10 text-surface-300" />
                        <p className="mt-2 text-sm text-surface-400">Nenhuma transferência encontrada</p>
                        {canCreate && (
                            <Button variant="outline" size="sm" className="mt-3" onClick={openCreate}>
                                Realizar primeira transferência
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-subtle bg-surface-50/50">
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Data</th>
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Técnico</th>
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Conta Origem</th>
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Método</th>
                                    <th className="px-4 py-3 text-left font-medium text-surface-600">Descrição</th>
                                    <th className="px-4 py-3 text-right font-medium text-surface-600">Valor</th>
                                    <th className="px-4 py-3 text-center font-medium text-surface-600">Status</th>
                                    <th className="px-4 py-3 text-right font-medium text-surface-600">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {(transfers || []).map(tx => {
                                    const st = STATUS_LABELS[tx.status]
                                    return (
                                        <tr key={tx.id} className="hover:bg-surface-50/50 transition-colors">
                                            <td className="px-4 py-3 text-surface-700 tabular-nums">
                                                {new Date(tx.transfer_date).toLocaleDateString('pt-BR')}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-surface-800">
                                                {tx.technician?.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-surface-600">
                                                {tx.bank_account ? `${tx.bank_account.name}` : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-surface-600">
                                                {PAYMENT_METHODS[tx.payment_method] ?? tx.payment_method}
                                            </td>
                                            <td className="px-4 py-3 text-surface-600 max-w-[200px] truncate" title={tx.description}>
                                                {tx.description}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums font-semibold text-surface-800">
                                                {formatCurrency(Number(tx.amount))}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${st?.color ?? ''}`}>
                                                    {st?.label ?? tx.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {canCancel && tx.status === 'completed' && (
                                                    <button onClick={() => setCancelTarget(tx)}
                                                        className="rounded-md p-1.5 text-surface-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                                                        title="Cancelar transferência">
                                                        <Ban className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination && pagination.last_page > 1 && (
                    <div className="border-t border-subtle px-5 py-3 flex items-center justify-between">
                        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>
                            Anterior
                        </Button>
                        <span className="text-xs text-surface-500">
                            Página {pagination.current_page} de {pagination.last_page}
                        </span>
                        <Button variant="outline" size="sm" disabled={page >= pagination.last_page} onClick={() => setPage(p => p + 1)}>
                            Próxima
                        </Button>
                    </div>
                )}
            </div>

            <Modal open={showModal} onOpenChange={setShowModal} title="Nova Transferência" size="md">
                <form onSubmit={handleSubmit((data: FundTransferFormData) => createMut.mutate(data))} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField label="Conta Bancária *" error={errors.bank_account_id?.message} required>
                            <select {...register('bank_account_id')} aria-label="Conta bancária" className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                <option value="">— Selecionar —</option>
                                {(bankAccounts || []).map(ba => (
                                    <option key={ba.id} value={ba.id}>{ba.name} ({ba.bank_name})</option>
                                ))}
                            </select>
                        </FormField>
                        <FormField label="Técnico Destino *" error={errors.to_user_id?.message} required>
                            <select {...register('to_user_id')} aria-label="Técnico destino" className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                <option value="">— Selecionar —</option>
                                {(technicians || []).map(t => (
                                    <option key={t.id} value={t.id}>{t.name}</option>
                                ))}
                            </select>
                        </FormField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Controller control={control} name="amount" render={({ field }) => (
                            <FormField label="Valor (R$) *" error={errors.amount?.message} required>
                                <CurrencyInput label="" value={parseFloat(field.value) || 0} onChange={v => field.onChange(String(v))} />
                            </FormField>
                        )} />
                        <FormField label="Data *" error={errors.transfer_date?.message} required>
                            <Input {...register('transfer_date')} type="date" />
                        </FormField>
                        <Controller control={control} name="payment_method" render={({ field }) => (
                            <LookupCombobox lookupType="payment-methods" endpoint="/financial/lookups/payment-methods" valueField="code" label="Método *" value={field.value} onChange={field.onChange} placeholder="Selecionar método" className="w-full" />
                        )} />
                    </div>
                    <FormField label="Descrição *" error={errors.description?.message} required>
                        <Input {...register('description')} placeholder="Ex: Verba operacional janeiro" />
                    </FormField>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" type="button" onClick={() => setShowModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={createMut.isPending}>Confirmar Transferência</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!cancelTarget} onOpenChange={() => setCancelTarget(null)} title="Cancelar Transferência" size="sm">
                <p className="text-sm text-surface-600">
                    Tem certeza que deseja cancelar esta transferência de{' '}
                    <strong>{cancelTarget && formatCurrency(Number(cancelTarget.amount))}</strong> para{' '}
                    <strong>{cancelTarget?.technician?.name}</strong>?
                </p>
                <p className="mt-2 text-xs text-surface-400">
                    O saldo será revertido do caixa do técnico e a conta a pagar será cancelada.
                </p>
                <div className="flex justify-end gap-2 pt-4">
                    <Button variant="outline" type="button" onClick={() => setCancelTarget(null)}>Voltar</Button>
                    <Button className="bg-red-600 hover:bg-red-700" loading={cancelMut.isPending}
                        onClick={() => cancelTarget && cancelMut.mutate(cancelTarget.id)}>
                        Cancelar Transferência
                    </Button>
                </div>
            </Modal>
        </div>
    )
}
