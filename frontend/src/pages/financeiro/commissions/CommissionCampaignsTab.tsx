import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Edit, Megaphone, Plus, Trash2 } from 'lucide-react'
import { getApiErrorMessage, unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { Badge } from '@/components/ui/badge'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { commissionCampaignSchema, type CommissionCampaignFormData } from './schemas'
import type { CommissionCampaign } from './types'
import { fmtDate, getCommissionRoleLabel } from './utils'

export function CommissionCampaignsTab() {
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canCreate = hasPermission('commissions.campaign.create')
    const canUpdate = hasPermission('commissions.campaign.update')
    const canDelete = hasPermission('commissions.campaign.delete')

    const [showModal, setShowModal] = useState(false)
    const [deleteCampId, setDeleteCampId] = useState<number | null>(null)
    const [editingCampaign, setEditingCampaign] = useState<CommissionCampaign | null>(null)

    const form = useForm<CommissionCampaignFormData>({
        resolver: zodResolver(commissionCampaignSchema),
        defaultValues: {
            name: '',
            multiplier: 1.50,
            starts_at: '',
            ends_at: '',
            applies_to_role: '',
        }
    })

    const { data: campaigns = [], isLoading } = useQuery({
        queryKey: ['commission-campaigns'],
        queryFn: async () => unwrapData<CommissionCampaign[]>(await financialApi.commissions.campaigns.list()) ?? [],
    })

    const storeMut = useMutation({
        mutationFn: (payload: CommissionCampaignFormData) => {
            const data = {
                ...payload,
                applies_to_role: payload.applies_to_role || null,
            }
            return editingCampaign
                ? financialApi.commissions.campaigns.update(editingCampaign.id, data)
                : financialApi.commissions.campaigns.store(data)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-campaigns'] })
            setShowModal(false)
            setEditingCampaign(null)
            form.reset()
            toast.success(editingCampaign ? 'Campanha atualizada' : 'Campanha criada')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao salvar campanha'))
        },
    })

    const delMut = useMutation({
        mutationFn: (id: number) => financialApi.commissions.campaigns.destroy(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-campaigns'] })
            toast.success('Campanha excluida')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao excluir campanha'))
        },
    })

    const openCreate = () => {
        setEditingCampaign(null)
        form.reset({
            name: '',
            multiplier: 1.50,
            starts_at: '',
            ends_at: '',
            applies_to_role: '',
        })
        setShowModal(true)
    }

    const openEdit = (campaign: CommissionCampaign) => {
        setEditingCampaign(campaign)
        form.reset({
            name: campaign.name,
            multiplier: Number(campaign.multiplier),
            starts_at: campaign.starts_at?.slice(0, 10) ?? '',
            ends_at: campaign.ends_at?.slice(0, 10) ?? '',
            applies_to_role: campaign.applies_to_role ?? '',
        })
        setShowModal(true)
    }

    const { errors } = form.formState

    return (
        <div className='space-y-4'>
            <div className='flex justify-between items-center bg-surface-0 p-4 rounded-xl border border-default shadow-card'>
                <div>
                    <h2 className='font-semibold text-surface-900'>Campanhas de comissao</h2>
                    <p className='text-xs text-surface-500'>Aceleradores temporarios aplicados sobre regras elegiveis.</p>
                </div>
                {canCreate && <Button onClick={openCreate} icon={<Plus className='h-4 w-4' />}>Nova campanha</Button>}
            </div>

            <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-3'>
                {isLoading ? (
                    <p className='text-center col-span-full text-surface-500'>Carregando...</p>
                ) : campaigns.length === 0 ? (
                    <div className='text-center col-span-full py-8'>
                        <Megaphone className='h-8 w-8 mx-auto text-surface-300 mb-2' />
                        <p className='text-surface-500'>Nenhuma campanha cadastrada.</p>
                    </div>
                ) : campaigns.map((campaign) => (
                    <div key={campaign.id} className='bg-surface-0 border border-default p-4 rounded-xl shadow-card relative group'>
                        <div className='absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1'>
                            {canUpdate && (
                                <Button size='icon' variant='ghost' className='h-7 w-7' onClick={() => openEdit(campaign)} aria-label='Editar campanha'>
                                    <Edit className='h-3.5 w-3.5' />
                                </Button>
                            )}
                            {canDelete && (
                                <Button size='icon' variant='ghost' className='h-7 w-7 text-red-600' onClick={() => setDeleteCampId(campaign.id)} aria-label='Excluir campanha'>
                                    <Trash2 className='h-3.5 w-3.5' />
                                </Button>
                            )}
                        </div>
                        <h3 className='font-bold text-base text-surface-900 mb-1'>{campaign.name}</h3>
                        <span className='text-lg font-bold text-brand-600'>x{campaign.multiplier}</span>
                        <div className='pt-3 mt-3 border-t border-surface-100 text-xs text-surface-500 grid grid-cols-2 gap-2'>
                            <div><span className='block text-xs uppercase text-surface-400 font-semibold'>Inicio</span>{fmtDate(campaign.starts_at)}</div>
                            <div><span className='block text-xs uppercase text-surface-400 font-semibold'>Fim</span>{fmtDate(campaign.ends_at)}</div>
                            {campaign.applies_to_role && <div className='col-span-2'><span className='block text-xs uppercase text-surface-400 font-semibold'>Papel</span>{getCommissionRoleLabel(campaign.applies_to_role)}</div>}
                            {campaign.applies_to_calculation_type && <div className='col-span-2'><span className='block text-xs uppercase text-surface-400 font-semibold'>Calculo</span>{campaign.applies_to_calculation_type}</div>}
                        </div>
                        {(() => {
                            const now = new Date().toISOString().slice(0, 10)
                            const isExpired = campaign.ends_at && campaign.ends_at.slice(0, 10) < now
                            const isFuture = campaign.starts_at && campaign.starts_at.slice(0, 10) > now
                            const label = !campaign.active ? 'Inativa' : isExpired ? 'Expirada' : isFuture ? 'Futura' : 'Ativa'
                            const variant = !campaign.active || isExpired ? 'secondary' : isFuture ? 'info' : 'success'
                            return <Badge variant={variant} className='mt-2'>{label}</Badge>
                        })()}
                    </div>
                ))}
            </div>

            <Modal open={showModal} onOpenChange={setShowModal} title={editingCampaign ? 'Editar campanha' : 'Nova campanha'}>
                <form onSubmit={form.handleSubmit((d) => storeMut.mutate(d))} className='space-y-4'>
                    <div>
                        <Input label='Nome' {...form.register('name')} placeholder='Nome da campanha' className={errors.name ? 'border-red-500' : ''} />
                        {errors.name && <p className='text-[10px] text-red-500 mt-1'>{errors.name.message}</p>}
                    </div>

                    <div>
                        <Input label='Multiplicador' type='number' step='0.01' min='1.01' max='5' {...form.register('multiplier')} className={errors.multiplier ? 'border-red-500' : ''} />
                        {errors.multiplier && <p className='text-[10px] text-red-500 mt-1'>{errors.multiplier.message}</p>}
                    </div>

                    <div className='grid grid-cols-2 gap-4'>
                        <div>
                            <Input label='Inicio' type='date' {...form.register('starts_at')} className={errors.starts_at ? 'border-red-500' : ''} />
                            {errors.starts_at && <p className='text-[10px] text-red-500 mt-1'>{errors.starts_at.message}</p>}
                        </div>
                        <div>
                            <Input label='Fim' type='date' {...form.register('ends_at')} className={errors.ends_at ? 'border-red-500' : ''} />
                            {errors.ends_at && <p className='text-[10px] text-red-500 mt-1'>{errors.ends_at.message}</p>}
                        </div>
                    </div>

                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Papel</label>
                        <select {...form.register('applies_to_role')} className={cn('w-full rounded-lg border-default text-sm h-9 px-2', errors.applies_to_role && 'border-red-500')}>
                            <option value=''>Todos</option>
                            <option value='tecnico'>Tecnico</option>
                            <option value='vendedor'>Vendedor</option>
                            <option value='motorista'>Motorista</option>
                        </select>
                        {errors.applies_to_role && <p className='text-[10px] text-red-500 mt-1'>{errors.applies_to_role.message}</p>}
                    </div>

                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => { setShowModal(false); setEditingCampaign(null); form.reset() }}>Cancelar</Button>
                        <Button type='submit' loading={storeMut.isPending}>{editingCampaign ? 'Salvar' : 'Criar campanha'}</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={Boolean(deleteCampId)} onOpenChange={(open) => !open && setDeleteCampId(null)} title='Excluir campanha'>
                <p className='text-sm text-surface-600 py-2'>Deseja excluir esta campanha? Esta acao nao pode ser desfeita.</p>
                <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                    <Button variant='outline' onClick={() => setDeleteCampId(null)}>Cancelar</Button>
                    <Button className='bg-red-600 hover:bg-red-700 text-white' loading={delMut.isPending} onClick={() => { if (deleteCampId) delMut.mutate(deleteCampId); setDeleteCampId(null) }}>Excluir</Button>
                </div>
            </Modal>
        </div>
    )
}
