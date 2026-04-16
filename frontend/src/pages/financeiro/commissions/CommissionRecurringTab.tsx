import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pause, Play, Plus, Repeat, Trash2 } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/ui/modal'
import { Badge } from '@/components/ui/badge'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { cn } from '@/lib/utils'
import type { CommissionRule, RecurringCommission, UserOption } from './types'

const commissionRecurringSchema = z.object({
    user_id: z.coerce.number().positive('Selecione um usuario'),
    recurring_contract_id: z.coerce.number().positive('Selecione um contrato'),
    commission_rule_id: z.coerce.number().positive('Selecione uma regra'),
})

type RecurringFormData = z.infer<typeof commissionRecurringSchema>

type ContractOption = {
    id: number
    name: string
    is_active?: boolean
}

function recurringStatusLabel(status: RecurringCommission['status']) {
    if (status === 'active') return 'Ativa'
    if (status === 'paused') return 'Pausada'
    return 'Encerrada'
}

function recurringStatusVariant(status: RecurringCommission['status']) {
    if (status === 'active') return 'success' as const
    if (status === 'paused') return 'secondary' as const
    return 'danger' as const
}

export function CommissionRecurringTab() {
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canCreate = hasPermission('commissions.recurring.create')
    const canUpdate = hasPermission('commissions.recurring.update')
    const canDelete = hasPermission('commissions.recurring.delete')

    const [page, setPage] = useState(1)
    const [showProcessConfirm, setShowProcessConfirm] = useState(false)
    const [showCreateModal, setShowCreateModal] = useState(false)
    const [deleteId, setDeleteId] = useState<number | null>(null)

    const form = useForm<RecurringFormData>({
        resolver: zodResolver(commissionRecurringSchema),
        defaultValues: {
            user_id: undefined,
            recurring_contract_id: undefined,
            commission_rule_id: undefined,
        }
    })

    const recurringParams: Record<string, string | number> = { page, per_page: 20 }
    const { data: recurringRes, isLoading } = useQuery({
        queryKey: ['recurring-commissions', recurringParams],
        queryFn: () => financialApi.commissions.recurring.list(recurringParams),
    })
    const recurringPayload = recurringRes?.data ?? recurringRes
    const items: RecurringCommission[] = recurringPayload?.data ?? (Array.isArray(recurringPayload) ? recurringPayload : [])
    const recurringLastPage = recurringPayload?.meta?.last_page ?? recurringPayload?.last_page ?? 1

    const { data: users = [] } = useQuery({
        queryKey: ['commission-users-select'],
        queryFn: async () => unwrapData<UserOption[]>(await financialApi.commissions.users()) ?? [],
        enabled: canCreate,
    })

    const { data: rules = [] } = useQuery({
        queryKey: ['commission-rules-select'],
        queryFn: async () => unwrapData<CommissionRule[]>(await financialApi.commissions.rules()) ?? [],
        enabled: canCreate,
    })

    const { data: contracts = [] } = useQuery({
        queryKey: ['recurring-contracts-select'],
        queryFn: async () => unwrapData<ContractOption[]>(await api.get('/recurring-contracts', { params: { per_page: 100 } })) ?? [],
        enabled: canCreate,
    })

    const invalidateAll = () => {
        qc.invalidateQueries({ queryKey: ['recurring-commissions'] })
        qc.invalidateQueries({ queryKey: ['commission-events'] })
    }

    const createMut = useMutation({
        mutationFn: (payload: RecurringFormData) => financialApi.commissions.recurring.store(payload as unknown as Record<string, unknown>),
        onSuccess: () => {
            invalidateAll()
            setShowCreateModal(false)
            form.reset()
            toast.success('Comissao recorrente criada')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao criar comissao recorrente'))
        },
    })

    const statusMut = useMutation({
        mutationFn: ({ id, status }: { id: number; status: 'active' | 'paused' | 'terminated' }) =>
            financialApi.commissions.recurring.updateStatus(id, { status }),
        onSuccess: () => {
            invalidateAll()
            toast.success('Status atualizado')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao atualizar status'))
        },
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => financialApi.commissions.recurring.destroy(id),
        onSuccess: () => {
            invalidateAll()
            toast.success('Comissao recorrente excluida')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao excluir comissao recorrente'))
        },
    })

    const processMut = useMutation({
        mutationFn: () => financialApi.commissions.recurring.processMonthly(),
        onSuccess: async (response) => {
            invalidateAll()
            const payload = unwrapData<{ generated?: number }>(response) ?? {}
            toast.success(`${payload.generated ?? 0} recorrencias processadas`)
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao processar recorrencias'))
        },
    })

    const { errors } = form.formState

    return (
        <div className='space-y-4'>
            <div className='flex justify-between items-center bg-surface-0 p-4 rounded-xl border border-default shadow-card'>
                <div>
                    <h2 className='font-semibold text-surface-900'>Comissoes recorrentes</h2>
                    <p className='text-xs text-surface-500'>Vincule contratos recorrentes a regras e beneficarios.</p>
                </div>
                <div className='flex gap-2'>
                    {canCreate && <Button variant='outline' onClick={() => { form.reset(); setShowCreateModal(true) }} icon={<Plus className='h-4 w-4' />}>Nova recorrencia</Button>}
                    {canCreate && <Button onClick={() => setShowProcessConfirm(true)} loading={processMut.isPending} icon={<Play className='h-4 w-4' />}>Processar mes</Button>}
                </div>
            </div>

            <div className='bg-surface-0 border border-default rounded-xl overflow-hidden shadow-card'>
                <div className='overflow-x-auto'>
                    <table className='w-full text-sm'>
                        <thead className='bg-surface-50 text-surface-500 border-b border-default'>
                            <tr>
                                <th className='px-4 py-3 text-left font-medium'>Usuario</th>
                                <th className='px-4 py-3 text-left font-medium'>Regra</th>
                                <th className='px-4 py-3 text-left font-medium'>Contrato</th>
                                <th className='px-4 py-3 text-left font-medium'>Ultima geracao</th>
                                <th className='px-4 py-3 text-center font-medium'>Status</th>
                                <th className='px-4 py-3 text-right font-medium'>Acoes</th>
                            </tr>
                        </thead>
                        <tbody className='divide-y divide-subtle'>
                            {isLoading ? (
                                <tr><td colSpan={6} className='p-8 text-center text-surface-500'>Carregando...</td></tr>
                            ) : items.length === 0 ? (
                                <tr><td colSpan={6} className='p-12 text-center'><Repeat className='h-8 w-8 mx-auto text-surface-300 mb-2' /><p className='text-surface-500'>Nenhuma comissao recorrente cadastrada.</p></td></tr>
                            ) : items.map((item) => (
                                <tr key={item.id} className='hover:bg-surface-50 transition-colors'>
                                    <td className='px-4 py-3 font-medium text-surface-900'>{item.user_name}</td>
                                    <td className='px-4 py-3 text-surface-600'>{item.rule_name} ({item.rule_value})</td>
                                    <td className='px-4 py-3 text-surface-600'>{item.contract_name ?? `#${item.recurring_contract_id}`}</td>
                                    <td className='px-4 py-3 text-surface-500'>{item.last_generated_at ?? '-'}</td>
                                    <td className='px-4 py-3 text-center'><Badge variant={recurringStatusVariant(item.status)}>{recurringStatusLabel(item.status)}</Badge></td>
                                    <td className='px-4 py-3 text-right'>
                                        <div className='flex justify-end gap-1 flex-wrap'>
                                            {item.status === 'active' && canUpdate && <Button size='sm' variant='outline' className='h-7 text-xs px-2' onClick={() => statusMut.mutate({ id: item.id, status: 'paused' })} icon={<Pause className='h-3 w-3' />}>Pausar</Button>}
                                            {item.status === 'paused' && canUpdate && <Button size='sm' variant='outline' className='h-7 text-xs px-2' onClick={() => statusMut.mutate({ id: item.id, status: 'active' })} icon={<Play className='h-3 w-3' />}>Ativar</Button>}
                                            {item.status !== 'terminated' && canUpdate && <Button size='sm' variant='outline' className='h-7 text-xs px-2 text-red-600 border-red-200 hover:bg-red-50' onClick={() => statusMut.mutate({ id: item.id, status: 'terminated' })}>Encerrar</Button>}
                                            {canDelete && <Button size='sm' variant='outline' className='h-7 text-xs px-2 text-red-600 border-red-200 hover:bg-red-50' onClick={() => setDeleteId(item.id)} icon={<Trash2 className='h-3 w-3' />}>Excluir</Button>}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {recurringLastPage > 1 && (
                    <div className='flex items-center justify-between px-4 py-3 border-t border-default'>
                        <span className='text-xs text-surface-500'>Página {page} de {recurringLastPage}</span>
                        <div className='flex gap-1'>
                            <Button variant='outline' size='sm' className='h-7 text-xs' disabled={page <= 1} onClick={() => setPage(p => p - 1)}>Anterior</Button>
                            <Button variant='outline' size='sm' className='h-7 text-xs' disabled={page >= recurringLastPage} onClick={() => setPage(p => p + 1)}>Próxima</Button>
                        </div>
                    </div>
                )}
            </div>

            <Modal open={showCreateModal} onOpenChange={setShowCreateModal} title='Nova recorrencia'>
                <form onSubmit={form.handleSubmit((d) => createMut.mutate(d))} className='space-y-4'>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Usuario</label>
                        <select {...form.register('user_id')} className={cn('w-full rounded-lg border-default text-sm h-9 px-2', errors.user_id && 'border-red-500')}>
                            <option value=''>Selecione...</option>
                            {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                        {errors.user_id && <p className='text-[10px] text-red-500 mt-1'>{errors.user_id.message}</p>}
                    </div>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Contrato recorrente</label>
                        <select {...form.register('recurring_contract_id')} className={cn('w-full rounded-lg border-default text-sm h-9 px-2', errors.recurring_contract_id && 'border-red-500')}>
                            <option value=''>Selecione...</option>
                            {contracts.map((contract) => <option key={contract.id} value={contract.id}>{contract.name}</option>)}
                        </select>
                        {errors.recurring_contract_id && <p className='text-[10px] text-red-500 mt-1'>{errors.recurring_contract_id.message}</p>}
                    </div>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Regra de comissao</label>
                        <select {...form.register('commission_rule_id')} className={cn('w-full rounded-lg border-default text-sm h-9 px-2', errors.commission_rule_id && 'border-red-500')}>
                            <option value=''>Selecione...</option>
                            {rules.filter((rule) => rule.active).map((rule) => <option key={rule.id} value={rule.id}>{rule.name}</option>)}
                        </select>
                        {errors.commission_rule_id && <p className='text-[10px] text-red-500 mt-1'>{errors.commission_rule_id.message}</p>}
                    </div>
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => { setShowCreateModal(false); form.reset() }}>Cancelar</Button>
                        <Button type='submit' loading={createMut.isPending}>Criar recorrencia</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={showProcessConfirm} onOpenChange={setShowProcessConfirm} title='Processar geracao mensal'>
                <p className='text-sm text-surface-600 py-2'>Deseja processar a geracao mensal das comissoes recorrentes? Eventos serao criados apenas para contratos ativos ainda nao gerados neste mes.</p>
                <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                    <Button variant='outline' onClick={() => setShowProcessConfirm(false)}>Cancelar</Button>
                    <Button className='bg-emerald-600 hover:bg-emerald-700 text-white' loading={processMut.isPending} onClick={() => {
                        processMut.mutate()
                        setShowProcessConfirm(false)
                    }}>
                        Processar
                    </Button>
                </div>
            </Modal>

            <Modal open={Boolean(deleteId)} onOpenChange={() => setDeleteId(null)} title='Excluir recorrencia'>
                <p className='text-sm text-surface-600 py-2'>Deseja excluir esta recorrencia? Esta acao remove o vinculo, nao apaga eventos ja gerados.</p>
                <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                    <Button variant='outline' onClick={() => setDeleteId(null)}>Cancelar</Button>
                    <Button className='bg-red-600 hover:bg-red-700 text-white' loading={deleteMut.isPending} onClick={() => {
                        if (deleteId) deleteMut.mutate(deleteId)
                        setDeleteId(null)
                    }}>
                        Excluir
                    </Button>
                </div>
            </Modal>
        </div>
    )
}
