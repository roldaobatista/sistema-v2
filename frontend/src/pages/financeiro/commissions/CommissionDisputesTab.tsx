import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, AlertCircle } from 'lucide-react'
import { getApiErrorMessage } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { Badge } from '@/components/ui/badge'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { cn } from '@/lib/utils'
import { commissionDisputeSchema, disputeResolutionSchema, type CommissionDisputeFormData, type DisputeResolutionFormData } from './schemas'
import type { CommissionDispute, CommissionEvent } from './types'
import {
    fmtBRL,
    fmtDate,
    getCommissionDisputeStatusLabel,
    getCommissionDisputeStatusVariant,
    normalizeCommissionDisputeStatus,
} from './utils'

/** Type-safe API response unwrap */
function safeUnwrap<T>(res: unknown, fallback: T): T {
    const r = res as Record<string, unknown> | undefined
    const d = r?.data as Record<string, unknown> | T | undefined
    if (d && typeof d === 'object' && 'data' in d) return (d as Record<string, unknown>).data as T
    if (d !== undefined && d !== null) return d as T
    return (r as T) ?? fallback
}

export function CommissionDisputesTab() {
    const qc = useQueryClient()
    const { hasPermission, user } = useAuthStore()
    const canCreate = hasPermission('commissions.dispute.create')
    const canResolve = hasPermission('commissions.dispute.resolve')

    const [page, setPage] = useState(1)
    const [filterStatus, setFilterStatus] = useState('')
    const [showModal, setShowModal] = useState(false)
    const [resolveModal, setResolveModal] = useState<CommissionDispute | null>(null)
    const [resolveStatus, setResolveStatus] = useState<'accepted' | 'rejected'>('accepted')

    const createForm = useForm<CommissionDisputeFormData>({
        resolver: zodResolver(commissionDisputeSchema),
        defaultValues: {
            commission_event_id: undefined,
            reason: '',
        }
    })

    const resolveForm = useForm<DisputeResolutionFormData>({
        resolver: zodResolver(disputeResolutionSchema),
        defaultValues: {
            status: 'accepted',
            resolution_notes: '',
            new_amount: undefined,
        }
    })

    const disputeParams: Record<string, string | number> = { page, per_page: 20 }
    if (filterStatus) disputeParams.status = filterStatus

    const { data: disputesRes, isLoading } = useQuery({ queryKey: ['commission-disputes', disputeParams], queryFn: () => financialApi.commissions.disputes.list(disputeParams) })
    const disputesPayload = safeUnwrap<Record<string, unknown>>(disputesRes, {})
    const disputes: CommissionDispute[] = (disputesPayload?.data as CommissionDispute[] | undefined) ?? (Array.isArray(disputesPayload) ? disputesPayload as unknown as CommissionDispute[] : [])
    const disputesLastPage = (disputesPayload?.meta as Record<string, unknown> | undefined)?.last_page as number ?? (disputesPayload?.last_page as number | undefined) ?? 1

    const { data: eventsPayload } = useQuery({ queryKey: ['commission-events'], queryFn: () => financialApi.commissions.events() })
    const events: CommissionEvent[] = safeUnwrap<CommissionEvent[]>(eventsPayload, [])

    const disputableEvents = events
        .filter((event) => event.status === 'pending' || event.status === 'approved')
        .filter((event) => canResolve || event.user_id === user?.id)

    const storeMut = useMutation({
        mutationFn: (data: CommissionDisputeFormData) => financialApi.commissions.disputes.store(data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-disputes'] });
            setShowModal(false);
            createForm.reset()
            toast.success('Contestacao registrada')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao registrar contestacao'))
    })

    const resolveMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: DisputeResolutionFormData }) => financialApi.commissions.disputes.resolve(id, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['commission-disputes'] })
            qc.invalidateQueries({ queryKey: ['commission-events'] })
            setResolveModal(null);
            resolveForm.reset()
            toast.success('Contestacao resolvida')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao resolver'))
    })

    const openResolve = (dispute: CommissionDispute, status: 'accepted' | 'rejected') => {
        setResolveModal(dispute)
        setResolveStatus(status)
        resolveForm.reset({
            status,
            resolution_notes: '',
            new_amount: undefined,
        })
    }

    const { errors: createErrors } = createForm.formState
    const { errors: resolveErrors } = resolveForm.formState
    const currentResolutionNotes = resolveForm.watch('resolution_notes')

    return (
        <div className='space-y-4'>
            <div className='flex justify-between items-center bg-surface-0 p-4 rounded-xl border border-default shadow-card'>
                <div><h2 className='font-semibold text-surface-900'>Contestacoes</h2><p className='text-xs text-surface-500'>Abra e resolva disputas de comissoes.</p></div>
                {canCreate && <Button onClick={() => { createForm.reset(); setShowModal(true) }} icon={<Plus className='h-4 w-4' />}>Nova Contestacao</Button>}
            </div>
            <div className='bg-surface-0 border border-default rounded-xl overflow-hidden shadow-card'>
                <div className='p-4 border-b border-default flex flex-wrap gap-2'>
                    <select title='Filtrar por status' value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1) }} className='h-8 rounded-lg border-default text-xs px-2 w-32'>
                        <option value=''>Todos status</option>
                        <option value='open'>Aberta</option>
                        <option value='accepted'>Aceita</option>
                        <option value='rejected'>Rejeitada</option>
                    </select>
                </div>
                <div className='overflow-x-auto'>
                    <table className='w-full text-sm'>
                        <thead className='bg-surface-50 text-surface-500 border-b border-default'>
                            <tr><th className='px-4 py-3 text-left font-medium'>Data</th><th className='px-4 py-3 text-left font-medium'>Usuario</th><th className='px-4 py-3 text-left font-medium'>Motivo</th><th className='px-4 py-3 text-right font-medium'>Valor</th><th className='px-4 py-3 text-center font-medium'>Status</th><th className='px-4 py-3 text-right font-medium'>Acoes</th></tr>
                        </thead>
                        <tbody className='divide-y divide-subtle'>
                            {isLoading ? <tr><td colSpan={6} className='p-8 text-center text-surface-500'>Carregando...</td></tr>
                                : disputes.length === 0 ? <tr><td colSpan={6} className='p-12 text-center'><AlertCircle className='h-8 w-8 mx-auto text-surface-300 mb-2' /><p className='text-surface-500'>Nenhuma contestacao registrada.</p></td></tr>
                                    : (disputes || []).map(d => (
                                        <tr key={d.id} className='hover:bg-surface-50 transition-colors'>
                                            <td className='px-4 py-3 text-surface-600'>{fmtDate(d.created_at)}</td>
                                            <td className='px-4 py-3 font-medium text-surface-900'>{d.user_name ?? d.user?.name}</td>
                                            <td className='px-4 py-3 text-surface-600 max-w-xs truncate' title={d.reason}>{d.reason}</td>
                                            <td className='px-4 py-3 text-right font-semibold text-emerald-600'>{fmtBRL(d.commission_amount ?? d.commission_event?.commission_amount ?? 0)}</td>
                                            <td className='px-4 py-3 text-center'><Badge variant={getCommissionDisputeStatusVariant(d.status)}>{getCommissionDisputeStatusLabel(d.status)}</Badge></td>
                                            <td className='px-4 py-3 text-right'>
                                                {normalizeCommissionDisputeStatus(d.status) === 'open' && canResolve && (
                                                    <div className='flex justify-end gap-1'>
                                                        <Button size='sm' className='bg-emerald-600 hover:bg-emerald-700 text-white h-7 text-xs px-2' onClick={() => openResolve(d, 'accepted')}>Aceitar</Button>
                                                        <Button size='sm' variant='outline' className='text-red-600 border-red-200 hover:bg-red-50 h-7 text-xs px-2' onClick={() => openResolve(d, 'rejected')}>Rejeitar</Button>
                                                    </div>
                                                )}
                                                {normalizeCommissionDisputeStatus(d.status) !== 'open' && d.resolution_notes && (
                                                    <span className='text-xs text-surface-500' title={d.resolution_notes}>
                                                        {(d.resolution_notes || '').slice(0, 25)}{(d.resolution_notes || '').length > 25 ? '...' : ''}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                        </tbody>
                    </table>
                </div>
                {disputesLastPage > 1 && (
                    <div className='flex items-center justify-between px-4 py-3 border-t border-default'>
                        <span className='text-xs text-surface-500'>Página {page} de {disputesLastPage}</span>
                        <div className='flex gap-1'>
                            <Button variant='outline' size='sm' className='h-7 text-xs' disabled={page <= 1} onClick={() => setPage(p => p - 1)}>Anterior</Button>
                            <Button variant='outline' size='sm' className='h-7 text-xs' disabled={page >= disputesLastPage} onClick={() => setPage(p => p + 1)}>Próxima</Button>
                        </div>
                    </div>
                )}
            </div>

            {/* New Dispute Modal */}
            <Modal open={showModal} onOpenChange={setShowModal} title='Nova Contestacao'>
                <form onSubmit={createForm.handleSubmit((d) => storeMut.mutate(d))} className='space-y-4'>
                    <div><label className='text-xs font-medium text-surface-700 mb-1 block'>Evento</label>
                        <select {...createForm.register('commission_event_id')} title='Selecionar evento de comissao' className={cn('w-full rounded-lg border-default text-sm h-9 px-2', createErrors.commission_event_id && 'border-red-500')}>
                            <option value=''>Selecione...</option>
                            {disputableEvents.map(event => <option key={event.id} value={event.id}>#{event.id} - {event.user?.name} - {fmtBRL(event.commission_amount)}</option>)}
                        </select>
                        {createErrors.commission_event_id && <p className='text-[10px] text-red-500 mt-1'>{createErrors.commission_event_id.message}</p>}
                    </div>
                    <div>
                        <Input label='Motivo (min 10 caracteres)' {...createForm.register('reason')} className={createErrors.reason ? 'border-red-500' : ''} />
                        {createErrors.reason && <p className='text-[10px] text-red-500 mt-1'>{createErrors.reason.message}</p>}
                    </div>
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => { setShowModal(false); createForm.reset() }}>Cancelar</Button>
                        <Button type='submit' loading={storeMut.isPending}>Registrar</Button>
                    </div>
                </form>
            </Modal>

            {/* Resolve Dispute Modal */}
            <Modal open={!!resolveModal} onOpenChange={() => { setResolveModal(null); resolveForm.reset() }}
                title={resolveStatus === 'accepted' ? 'Aceitar Contestacao' : 'Rejeitar Contestacao'}>
                <form onSubmit={resolveForm.handleSubmit((d) => {
                    if (!resolveModal) return
                    resolveMut.mutate({ id: resolveModal.id, data: d })
                })} className='space-y-4'>
                    {resolveModal && (
                        <div className='bg-surface-50 rounded-lg p-3 text-sm'>
                            <p><strong>Usuario:</strong> {resolveModal.user_name ?? resolveModal.user?.name}</p>
                            <p><strong>Motivo:</strong> {resolveModal.reason}</p>
                            <p><strong>Valor Atual:</strong> {fmtBRL(resolveModal.commission_amount ?? resolveModal.commission_event?.commission_amount ?? 0)}</p>
                        </div>
                    )}
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Notas de Resolucao (min 5 caracteres)</label>
                        <textarea {...resolveForm.register('resolution_notes')} className={cn('w-full rounded-lg border-default text-sm p-3 min-h-[80px]', resolveErrors.resolution_notes && 'border-red-500')} placeholder='Descreva a justificativa da decisao...' />
                        {resolveErrors.resolution_notes && <p className='text-[10px] text-red-500 mt-1'>{resolveErrors.resolution_notes.message}</p>}
                    </div>
                    {resolveStatus === 'accepted' && (
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Novo Valor (opcional - deixe vazio para estornar)</label>
                            <Input type='number' step='0.01' min='0' {...resolveForm.register('new_amount')} placeholder='Ex: 150.00' className={resolveErrors.new_amount ? 'border-red-500' : ''} />
                            {resolveErrors.new_amount && <p className='text-[10px] text-red-500 mt-1'>{resolveErrors.new_amount.message}</p>}
                            <p className='text-xs text-surface-400 mt-1'>Se preenchido, o valor da comissao sera ajustado. Se vazio, o evento sera estornado.</p>
                        </div>
                    )}
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => { setResolveModal(null); resolveForm.reset() }}>Cancelar</Button>
                        <Button
                            type='submit'
                            className={resolveStatus === 'accepted' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white'}
                            loading={resolveMut.isPending}
                            disabled={!currentResolutionNotes || currentResolutionNotes.length < 5}
                        >{resolveStatus === 'accepted' ? 'Confirmar Aceitacao' : 'Confirmar Rejeicao'}</Button>
                    </div>
                </form>
            </Modal>
        </div>
    )
}
