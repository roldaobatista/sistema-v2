import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Edit, Plus, Trash2, Wallet } from 'lucide-react'
import { getApiErrorMessage, unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { Badge } from '@/components/ui/badge'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { CommissionRule, UserOption } from './types'
import { fmtBRL, getCommissionRoleLabel } from './utils'
import { commissionRuleSchema, type CommissionRuleFormData } from './schemas'

export function CommissionRulesTab() {
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canCreate = hasPermission('commissions.rule.create')
    const canUpdate = hasPermission('commissions.rule.update')
    const canDelete = hasPermission('commissions.rule.delete')

    const [showModal, setShowModal] = useState(false)
    const [deleteRuleId, setDeleteRuleId] = useState<number | null>(null)
    const [editing, setEditing] = useState<CommissionRule | null>(null)

    const form = useForm<CommissionRuleFormData>({
        resolver: zodResolver(commissionRuleSchema),
        defaultValues: {
            calculation_type: 'percent_gross',
            applies_to_role: 'tecnico',
            priority: 0,
            applies_to: 'all',
            applies_when: 'os_completed',
            active: true,
            source_filter: '',
        }
    })

    const getAppliesWhenBadge = (val: string) => {
        switch (val) {
            case 'os_completed': return <Badge variant='outline' className='bg-blue-50 text-blue-700 border-blue-200'>Ao Concluir OS</Badge>
            case 'installment_paid': return <Badge variant='outline' className='bg-green-50 text-green-700 border-green-200'>Pgto de Parcela</Badge>
            case 'os_invoiced': return <Badge variant='outline' className='bg-amber-50 text-amber-700 border-amber-200'>Ao Faturar OS</Badge>
            default: return <Badge variant='outline'>{val?.replace(/_/g, ' ')}</Badge>
        }
    }

    const getRoleBadge = (val: string) => {
        const label = getCommissionRoleLabel(val)
        switch (val) {
            case 'tecnico': return <Badge variant='outline' className='bg-emerald-50 text-emerald-700 border-emerald-200 uppercase text-[10px]'>{label}</Badge>
            case 'vendedor': return <Badge variant='outline' className='bg-emerald-50 text-emerald-700 border-emerald-200 uppercase text-[10px]'>{label}</Badge>
            case 'motorista': return <Badge variant='outline' className='bg-slate-50 text-slate-700 border-slate-200 uppercase text-[10px]'>{label}</Badge>
            default: return <Badge variant='secondary' className='uppercase text-[10px]'>{label}</Badge>
        }
    }

    const { data: rules = [], isLoading } = useQuery({
        queryKey: ['commission-rules'],
        queryFn: async () => unwrapData<CommissionRule[]>(await financialApi.commissions.rules()) ?? []
    })

    const saveMut = useMutation({
        mutationFn: (data: CommissionRuleFormData) => {
            const payload = { ...data, source_filter: data.source_filter || null }
            return editing
                ? financialApi.commissions.updateRule(editing.id, payload)
                : financialApi.commissions.storeRule(payload)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-rules'] })
            broadcastQueryInvalidation(['commission-rules'], 'Regra de Comissao')
            setShowModal(false)
            setEditing(null)
            form.reset()
            toast.success('Regra salva com sucesso')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao salvar regra'))
        },
    })

    const delMut = useMutation({
        mutationFn: (id: number) => financialApi.commissions.destroyRule(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-rules'] })
            broadcastQueryInvalidation(['commission-rules'], 'Regra de Comissao')
            toast.success('Regra excluida')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao excluir regra'))
        },
    })

    const { data: users = [] } = useQuery({
        queryKey: ['commission-users-select'],
        queryFn: async () => unwrapData<UserOption[]>(await financialApi.commissions.users()) ?? []
    })

    const { data: calcTypes = {} } = useQuery({
        queryKey: ['commission-calculation-types'],
        queryFn: async () => unwrapData<Record<string, string>>(await financialApi.commissions.calculationTypes()) ?? {}
    })

    const openCreate = () => {
        setEditing(null)
        form.reset({
            name: '',
            calculation_type: 'percent_gross',
            value: undefined,
            applies_to_role: 'tecnico',
            user_id: undefined,
            priority: 0,
            applies_to: 'all',
            applies_when: 'os_completed',
            source_filter: '',
            active: true,
        })
        setShowModal(true)
    }

    const openEdit = (rule: CommissionRule) => {
        setEditing(rule)
        form.reset({
            name: rule.name,
            calculation_type: rule.calculation_type,
            value: Number(rule.value),
            applies_to_role: rule.applies_to_role as 'tecnico' | 'vendedor' | 'motorista',
            user_id: rule.user_id,
            priority: rule.priority,
            applies_to: rule.applies_to as 'all' | 'products' | 'services',
            applies_when: rule.applies_when as 'os_completed' | 'installment_paid' | 'os_invoiced',
            source_filter: rule.source_filter ?? '',
            active: rule.active ?? true,
        })
        setShowModal(true)
    }

    const { errors } = form.formState

    return (
        <div className='space-y-4'>
            <div className='flex justify-between items-center bg-surface-0 p-4 rounded-xl border border-default shadow-card'>
                <div>
                    <h2 className='font-semibold text-surface-900'>Regras de Comissao</h2>
                    <p className='text-xs text-surface-500'>Defina como as comissoes sao calculadas.</p>
                </div>
                {canCreate && <Button onClick={openCreate} icon={<Plus className='h-4 w-4' />}>Nova Regra</Button>}
            </div>

            <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>
                {isLoading ? <p className='text-center col-span-full text-surface-500'>Carregando...</p> : rules.length === 0 ? <div className='text-center col-span-full py-8'><Wallet className='h-8 w-8 mx-auto text-surface-300 mb-2' /><p className='text-surface-500'>Nenhuma regra cadastrada.</p></div> : rules.map((rule) => (
                    <div key={rule.id} className='bg-surface-0 border border-default p-4 rounded-xl shadow-card transition-shadow relative group'>
                        <div className='absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1'>
                            {canUpdate && <Button size='icon' variant='ghost' className='h-7 w-7' onClick={() => openEdit(rule)} aria-label='Editar regra'><Edit className='h-3.5 w-3.5' /></Button>}
                            {canDelete && <Button size='icon' variant='ghost' className='h-7 w-7 text-red-600 hover:text-red-700 hover:bg-red-50' onClick={() => setDeleteRuleId(rule.id)} aria-label='Excluir regra'><Trash2 className='h-3.5 w-3.5' /></Button>}
                        </div>

                        <div className='flex justify-between items-start mb-2 pr-12'>
                            {getRoleBadge(rule.applies_to_role)}
                        </div>

                        <h3 className='font-bold text-base text-surface-900 mb-1 truncate' title={rule.name}>{rule.name}</h3>

                        <div className='flex items-baseline gap-1 mb-3'>
                            <span className='text-lg font-bold text-brand-600'>
                                {rule.calculation_type?.includes('fixed') ? fmtBRL(rule.value) : `${rule.value}%`}
                            </span>
                            <span className='text-xs text-surface-500'>{rule.calculation_type?.replace(/_/g, ' ')}</span>
                        </div>

                        <div className='pt-3 border-t border-surface-100 text-xs text-surface-500 grid grid-cols-2 gap-2'>
                            <div><span className='block text-xs uppercase text-surface-400 font-semibold'>Prioridade</span>{rule.priority}</div>
                            <div><span className='block text-xs uppercase text-surface-400 font-semibold'>Aplica-se</span>{rule.applies_to}</div>
                            <div className='col-span-2 flex flex-col items-start gap-1'>
                                <span className='block text-xs uppercase text-surface-400 font-semibold'>Quando</span>
                                {getAppliesWhenBadge(rule.applies_when)}
                            </div>
                            <div className='col-span-2'>
                                <span className='block text-xs uppercase text-surface-400 font-semibold'>Beneficiario</span>
                                {rule.user?.name ?? 'Todos do cargo'}
                            </div>
                            {rule.source_filter && (
                                <div className='col-span-2'>
                                    <span className='block text-xs uppercase text-surface-400 font-semibold'>Filtro de Origem</span>
                                    {rule.source_filter}
                                </div>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            <Modal open={showModal} onOpenChange={setShowModal} title={editing ? 'Editar Regra' : 'Nova Regra'}>
                <form onSubmit={form.handleSubmit((d) => saveMut.mutate(d))} className='space-y-4'>
                    <div>
                        <Input label='Nome da Regra' {...form.register('name')} placeholder='Ex: Comissao Vendas Padrao' className={errors.name ? 'border-red-500' : ''} />
                        {errors.name && <p className='text-[10px] text-red-500 mt-1'>{errors.name.message}</p>}
                    </div>

                    <div className='grid grid-cols-2 gap-4'>
                        <div>
                            <label htmlFor='commission-rule-applies-to-role' className='text-xs font-medium text-surface-700 mb-1 block'>Papel (Cargo)</label>
                            <select id='commission-rule-applies-to-role' {...form.register('applies_to_role')} className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2'>
                                <option value='tecnico'>Tecnico</option>
                                <option value='vendedor'>Vendedor</option>
                                <option value='motorista'>Motorista</option>
                            </select>
                            {errors.applies_to_role && <p className='text-[10px] text-red-500 mt-1'>{errors.applies_to_role.message}</p>}
                        </div>
                        <div>
                            <label htmlFor='commission-rule-user-id' className='text-xs font-medium text-surface-700 mb-1 block'>Usuario Especifico (Opcional)</label>
                            <select id='commission-rule-user-id' {...form.register('user_id')} className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2'>
                                <option value=''>Todos do cargo</option>
                                {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </select>
                            {errors.user_id && <p className='text-[10px] text-red-500 mt-1'>{errors.user_id.message}</p>}
                        </div>
                    </div>

                    <div className='grid grid-cols-2 gap-4'>
                        <div>
                            <label htmlFor='commission-rule-calculation-type' className='text-xs font-medium text-surface-700 mb-1 block'>Tipo de Calculo</label>
                            <select id='commission-rule-calculation-type' {...form.register('calculation_type')} className={`w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2 ${errors.calculation_type ? 'border-red-500' : ''}`}>
                                <option value=''>Selecione...</option>
                                {Object.keys(calcTypes).length > 0 ? (
                                    Object.entries(calcTypes).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))
                                ) : (
                                    <>
                                        <option value='percent_gross'>% Bruto</option>
                                        <option value='percent_net'>% Liquido</option>
                                        <option value='fixed_per_os'>Valor Fixo por OS</option>
                                        <option value='fixed_per_item'>Valor Fixo por Item</option>
                                    </>
                                )}
                            </select>
                            {errors.calculation_type && <p className='text-[10px] text-red-500 mt-1'>{errors.calculation_type.message}</p>}
                        </div>
                        <div>
                            <Input label='Valor / Percentual' type='number' step='0.01' {...form.register('value')} className={errors.value ? 'border-red-500' : ''} />
                            {errors.value && <p className='text-[10px] text-red-500 mt-1'>{errors.value.message}</p>}
                        </div>
                    </div>

                    <div>
                        <Input label='Prioridade (Maior = Executa Primeiro)' type='number' {...form.register('priority')} className={errors.priority ? 'border-red-500' : ''} />
                        {errors.priority && <p className='text-[10px] text-red-500 mt-1'>{errors.priority.message}</p>}
                    </div>

                    <div className='grid grid-cols-2 gap-4'>
                        <div>
                            <label htmlFor='commission-rule-applies-when' className='text-xs font-medium text-surface-700 mb-1 block'>Quando Disparar</label>
                            <select id='commission-rule-applies-when' {...form.register('applies_when')} className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2'>
                                <option value='os_completed'>Ao Concluir OS</option>
                                <option value='installment_paid'>Ao Receber Pagamento</option>
                                <option value='os_invoiced'>Ao Faturar OS</option>
                            </select>
                            {errors.applies_when && <p className='text-[10px] text-red-500 mt-1'>{errors.applies_when.message}</p>}
                        </div>
                        <div>
                            <label htmlFor='commission-rule-applies-to' className='text-xs font-medium text-surface-700 mb-1 block'>Aplica-se a</label>
                            <select id='commission-rule-applies-to' {...form.register('applies_to')} className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2'>
                                <option value='all'>Todos os Itens</option>
                                <option value='products'>Somente Produtos</option>
                                <option value='services'>Somente Servicos</option>
                            </select>
                            {errors.applies_to && <p className='text-[10px] text-red-500 mt-1'>{errors.applies_to.message}</p>}
                        </div>
                    </div>

                    <div>
                        <Input label='Filtro de Origem (Opcional)' {...form.register('source_filter')} placeholder='Ex: site, indicacao, telemarketing' className={errors.source_filter ? 'border-red-500' : ''} />
                        <p className='text-[10px] text-surface-400 mt-1'>Aplica regra somente a OS originadas desta fonte</p>
                        {errors.source_filter && <p className='text-[10px] text-red-500 mt-1'>{errors.source_filter.message}</p>}
                    </div>

                    {editing && (
                        <div className='flex items-center gap-2'>
                            <label htmlFor='commission-rule-active' className='text-xs font-medium text-surface-700'>Status</label>
                            <select id='commission-rule-active' {...form.register('active')} className='rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2'>
                                <option value='true'>Ativa</option>
                                <option value='false'>Inativa</option>
                            </select>
                        </div>
                    )}

                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => setShowModal(false)}>Cancelar</Button>
                        <Button type='submit' loading={saveMut.isPending}>Salvar Regra</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!deleteRuleId} onOpenChange={() => setDeleteRuleId(null)} title='Excluir Regra'>
                <p className='text-sm text-surface-600 py-2'>Deseja excluir esta regra de comissao? Esta acao nao pode ser desfeita.</p>
                <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                    <Button variant='outline' onClick={() => setDeleteRuleId(null)}>Cancelar</Button>
                    <Button className='bg-red-600 hover:bg-red-700 text-white' loading={delMut.isPending}
                        onClick={() => { if (deleteRuleId) delMut.mutate(deleteRuleId); setDeleteRuleId(null) }}>Excluir</Button>
                </div>
            </Modal>
        </div>
    )
}
