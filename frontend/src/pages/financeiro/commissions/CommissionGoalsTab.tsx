import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Edit, Plus, RefreshCw, Target, Trash2 } from 'lucide-react'
import { getApiErrorMessage, unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { cn, formatCurrency } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { Badge } from '@/components/ui/badge'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { commissionGoalSchema, type CommissionGoalFormData } from './schemas'
import type { CommissionGoal, UserOption } from './types'

function formatGoalMetric(type: CommissionGoal['type'], value: number) {
    if (type === 'revenue') {
        return formatCurrency(value)
    }
    return Number(value).toLocaleString('pt-BR')
}

function goalTypeLabel(type: CommissionGoal['type']) {
    if (type === 'os_count') return 'Qtd. OS'
    if (type === 'new_clients') return 'Novos clientes'
    return 'Faturamento'
}

export function CommissionGoalsTab() {
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canCreate = hasPermission('commissions.goal.create')
    const canUpdate = hasPermission('commissions.goal.update')
    const canDelete = hasPermission('commissions.goal.delete')
    const canRefresh = hasPermission('commissions.goal.update')

    const [showModal, setShowModal] = useState(false)
    const [deleteGoalId, setDeleteGoalId] = useState<number | null>(null)
    const [editingGoal, setEditingGoal] = useState<CommissionGoal | null>(null)

    const form = useForm<CommissionGoalFormData>({
        resolver: zodResolver(commissionGoalSchema),
        defaultValues: {
            period: new Date().toISOString().slice(0, 7),
            type: 'revenue',
        }
    })

    const { data: goals = [], isLoading } = useQuery({
        queryKey: ['commission-goals'],
        queryFn: async () => unwrapData<CommissionGoal[]>(await financialApi.commissions.goals.list()) ?? [],
    })

    const { data: users = [] } = useQuery({
        queryKey: ['commission-users-select'],
        queryFn: async () => unwrapData<UserOption[]>(await financialApi.commissions.users()) ?? [],
    })

    const storeMut = useMutation({
        mutationFn: (payload: CommissionGoalFormData) => {
            const data = {
                ...payload,
                bonus_percentage: payload.bonus_percentage || null,
                bonus_amount: payload.bonus_amount || null,
                notes: payload.notes || null,
            };
            return editingGoal
                ? financialApi.commissions.goals.update(editingGoal.id, data)
                : financialApi.commissions.goals.store(data)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-goals'] })
            setShowModal(false)
            setEditingGoal(null)
            form.reset()
            toast.success(editingGoal ? 'Meta atualizada' : 'Meta criada')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, editingGoal ? 'Erro ao atualizar meta' : 'Erro ao criar meta'))
        },
    })

    const refreshMut = useMutation({
        mutationFn: (id: number) => financialApi.commissions.goals.refresh(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-goals'] })
            toast.success('Meta atualizada')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao atualizar meta'))
        },
    })

    const delMut = useMutation({
        mutationFn: (id: number) => financialApi.commissions.goals.destroy(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-goals'] })
            toast.success('Meta excluida')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao excluir meta'))
        },
    })

    const openCreate = () => {
        setEditingGoal(null)
        form.reset({
            period: new Date().toISOString().slice(0, 7),
            type: 'revenue',
            user_id: undefined,
            target_amount: undefined,
            bonus_percentage: null,
            bonus_amount: null,
            notes: '',
        })
        setShowModal(true)
    }

    const openEdit = (goal: CommissionGoal) => {
        setEditingGoal(goal)
        form.reset({
            user_id: goal.user_id,
            period: goal.period,
            target_amount: Number(goal.target_amount),
            type: goal.type,
            bonus_percentage: goal.bonus_percentage != null ? Number(goal.bonus_percentage) : null,
            bonus_amount: goal.bonus_amount != null ? Number(goal.bonus_amount) : null,
            notes: goal.notes ?? '',
        })
        setShowModal(true)
    }

    const onSubmit = (data: CommissionGoalFormData) => {
        storeMut.mutate(data)
    }

    const { errors } = form.formState

    return (
        <div className='space-y-4'>
            <div className='flex justify-between items-center bg-surface-0 p-4 rounded-xl border border-default shadow-card'>
                <div>
                    <h2 className='font-semibold text-surface-900'>Metas de comissao</h2>
                    <p className='text-xs text-surface-500'>Controle metas mensais por faturamento, OS ou novos clientes.</p>
                </div>
                {canCreate && <Button onClick={openCreate} icon={<Plus className='h-4 w-4' />}>Nova meta</Button>}
            </div>

            <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>
                {isLoading ? (
                    <p className='text-center col-span-full text-surface-500'>Carregando...</p>
                ) : goals.length === 0 ? (
                    <div className='text-center col-span-full py-8'>
                        <Target className='h-8 w-8 mx-auto text-surface-300 mb-2' />
                        <p className='text-surface-500'>Nenhuma meta cadastrada.</p>
                    </div>
                ) : goals.map((goal) => {
                    const achievement = goal.achievement_pct ?? 0

                    return (
                        <div key={goal.id} className='bg-surface-0 border border-default p-4 rounded-xl shadow-card relative group'>
                            <div className='absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1'>
                                {canRefresh && <Button size='icon' variant='ghost' className='h-7 w-7' onClick={() => refreshMut.mutate(goal.id)} aria-label='Atualizar meta'><RefreshCw className='h-3.5 w-3.5' /></Button>}
                                {canUpdate && <Button size='icon' variant='ghost' className='h-7 w-7' onClick={() => openEdit(goal)} aria-label='Editar meta'><Edit className='h-3.5 w-3.5' /></Button>}
                                {canDelete && <Button size='icon' variant='ghost' className='h-7 w-7 text-red-600' onClick={() => setDeleteGoalId(goal.id)} aria-label='Excluir meta'><Trash2 className='h-3.5 w-3.5' /></Button>}
                            </div>
                            <div className='flex justify-between items-start'>
                                <p className='text-sm font-bold text-surface-900'>{goal.user_name}</p>
                                <Badge variant='outline' className='text-xs'>{goalTypeLabel(goal.type)}</Badge>
                            </div>
                            <p className='text-xs text-surface-500 mb-3'>{goal.period}</p>
                            <div className='flex justify-between text-xs mb-1'>
                                <span>Alcancado: {formatGoalMetric(goal.type, goal.achieved_amount)}</span>
                                <span>Meta: {formatGoalMetric(goal.type, goal.target_amount)}</span>
                            </div>
                            <div className='h-2 rounded-full bg-surface-100'>
                                <div
                                    className={cn('h-full rounded-full transition-all', achievement >= 100 ? 'bg-emerald-500' : achievement >= 50 ? 'bg-amber-500' : 'bg-red-400')}
                                    title={`${achievement}% atingido`}
                                    style={{ width: `${Math.min(achievement, 100)}%` }}
                                />
                            </div>
                            <p className='text-xs text-surface-500 mt-1 text-right'>{achievement}%</p>
                        </div>
                    )
                })}
            </div>

            <Modal open={showModal} onOpenChange={setShowModal} title={editingGoal ? 'Editar meta' : 'Nova meta'}>
                <form onSubmit={form.handleSubmit(onSubmit)} className='space-y-4'>
                    <div className='grid grid-cols-2 gap-4'>
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Usuario</label>
                            <select
                                {...form.register('user_id')}
                                aria-label='Selecionar usuario'
                                disabled={Boolean(editingGoal)}
                                className={cn('w-full rounded-lg border-default text-sm h-9 px-2 disabled:bg-surface-100', errors.user_id && 'border-red-500')}
                            >
                                <option value=''>Selecione...</option>
                                {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                            </select>
                            {errors.user_id && <p className='text-[10px] text-red-500 mt-1'>{errors.user_id.message}</p>}
                        </div>
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Tipo de meta</label>
                            <select
                                {...form.register('type')}
                                aria-label='Tipo de meta'
                                className={cn('w-full rounded-lg border-default text-sm h-9 px-2', errors.type && 'border-red-500')}
                            >
                                <option value='revenue'>Faturamento</option>
                                <option value='os_count'>Quantidade de OS</option>
                                <option value='new_clients'>Novos clientes</option>
                            </select>
                            {errors.type && <p className='text-[10px] text-red-500 mt-1'>{errors.type.message}</p>}
                        </div>
                    </div>
                    <div className='grid grid-cols-2 gap-4'>
                        <div>
                            <Input label='Periodo' type='month' {...form.register('period')} className={errors.period ? 'border-red-500' : ''} />
                            {errors.period && <p className='text-[10px] text-red-500 mt-1'>{errors.period.message}</p>}
                        </div>
                        <div>
                            <Input
                                label={form.watch('type') === 'revenue' ? 'Meta' : 'Meta em quantidade'}
                                type='number'
                                step='0.01'
                                {...form.register('target_amount')}
                                className={errors.target_amount ? 'border-red-500' : ''}
                            />
                            {errors.target_amount && <p className='text-[10px] text-red-500 mt-1'>{errors.target_amount.message}</p>}
                        </div>
                    </div>
                    <div className='grid grid-cols-2 gap-4'>
                        <div>
                            <Input label='Bonus percentual' type='number' step='0.01' min='0' max='100' {...form.register('bonus_percentage')} className={errors.bonus_percentage ? 'border-red-500' : ''} />
                            {errors.bonus_percentage && <p className='text-[10px] text-red-500 mt-1'>{errors.bonus_percentage.message}</p>}
                        </div>
                        <div>
                            <Input label='Bonus fixo' type='number' step='0.01' min='0' {...form.register('bonus_amount')} className={errors.bonus_amount ? 'border-red-500' : ''} />
                            {errors.bonus_amount && <p className='text-[10px] text-red-500 mt-1'>{errors.bonus_amount.message}</p>}
                        </div>
                    </div>
                    <div>
                        <Input label='Observacoes' {...form.register('notes')} placeholder='Observacoes sobre a meta' className={errors.notes ? 'border-red-500' : ''} />
                        {errors.notes && <p className='text-[10px] text-red-500 mt-1'>{errors.notes.message}</p>}
                    </div>
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => {
                            setShowModal(false)
                            setEditingGoal(null)
                            form.reset()
                        }}>
                            Cancelar
                        </Button>
                        <Button type='submit' loading={storeMut.isPending}>{editingGoal ? 'Salvar meta' : 'Criar meta'}</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={Boolean(deleteGoalId)} onOpenChange={() => setDeleteGoalId(null)} title='Excluir meta'>
                <p className='text-sm text-surface-600 py-2'>Deseja excluir esta meta? Esta acao nao pode ser desfeita.</p>
                <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                    <Button variant='outline' onClick={() => setDeleteGoalId(null)}>Cancelar</Button>
                    <Button
                        className='bg-red-600 hover:bg-red-700 text-white'
                        loading={delMut.isPending}
                        onClick={() => {
                            if (deleteGoalId) delMut.mutate(deleteGoalId)
                            setDeleteGoalId(null)
                        }}
                    >
                        Excluir
                    </Button>
                </div>
            </Modal>
        </div>
    )
}
