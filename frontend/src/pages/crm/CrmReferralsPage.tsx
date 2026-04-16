import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Users, Gift, TrendingUp, Plus, UserPlus, Award, Star, Pencil, Trash2 } from 'lucide-react'
import { crmFeaturesApi } from '@/lib/crm-features-api'
import type { CrmReferral, CrmReferralOptions, CrmReferralStats } from '@/lib/crm-features-api'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { Modal } from '@/components/ui/modal'
import { TableSkeleton } from '@/components/ui/tableskeleton'
import { EmptyState } from '@/components/ui/emptystate'
import { toast } from 'sonner'

const fmtBRL = (v: number | string) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

const STATUS_MAP: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'danger' | 'secondary' }> = {
    pending: { label: 'Pendente', variant: 'warning' },
    contacted: { label: 'Contactado', variant: 'info' },
    converted: { label: 'Convertido', variant: 'success' },
    lost: { label: 'Perdido', variant: 'danger' },
}

const getErrorMessage = (err: unknown, fallback: string): string => {
    const maybe = err as { response?: { data?: { message?: string } } }
    return maybe.response?.data?.message ?? fallback
}

export function CrmReferralsPage() {
    const qc = useQueryClient()
    const [showCreate, setShowCreate] = useState(false)
    const [editing, setEditing] = useState<CrmReferral | null>(null)
    const [deleteTarget, setDeleteTarget] = useState<CrmReferral | null>(null)
    const [filterStatus, setFilterStatus] = useState('')
    const [createRewardValue, setCreateRewardValue] = useState(0)
    const [editRewardValue, setEditRewardValue] = useState(0)

    const { data: referrals = [], isLoading } = useQuery<CrmReferral[]>({
        queryKey: ['crm-referrals', filterStatus],
        queryFn: () => crmFeaturesApi.getReferrals(filterStatus ? { status: filterStatus, per_page: 100 } : { per_page: 100 }),
    })

    const { data: stats = {
        total: 0,
        pending: 0,
        converted: 0,
        conversion_rate: 0,
        total_rewards: 0,
        total_reward_value: 0,
        top_referrers: [],
    } } = useQuery<CrmReferralStats>({
        queryKey: ['crm-referral-stats'],
        queryFn: () => crmFeaturesApi.getReferralStats(),
    })

    const { data: options = { customers: [], deals: [] } } = useQuery<CrmReferralOptions>({
        queryKey: ['crm-referral-options'],
        queryFn: () => crmFeaturesApi.getReferralOptions(),
    })
    const referrerOptions = options.customers
    const dealOptions = options.deals

    const createMut = useMutation({
        mutationFn: (data: Partial<CrmReferral>) => crmFeaturesApi.createReferral(data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['crm-referrals'] })
            qc.invalidateQueries({ queryKey: ['crm-referral-stats'] })
            setShowCreate(false)
            setCreateRewardValue(0)
            toast.success('Indicacao registrada com sucesso')
        },
        onError: (err: unknown) => toast.error(getErrorMessage(err, 'Erro ao registrar indicacao')),
    })

    const updateMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<CrmReferral> }) => crmFeaturesApi.updateReferral(id, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['crm-referrals'] })
            qc.invalidateQueries({ queryKey: ['crm-referral-stats'] })
            setEditing(null)
            setEditRewardValue(0)
            toast.success('Indicacao atualizada com sucesso')
        },
        onError: (err: unknown) => toast.error(getErrorMessage(err, 'Erro ao atualizar indicacao')),
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => crmFeaturesApi.deleteReferral(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['crm-referrals'] })
            qc.invalidateQueries({ queryKey: ['crm-referral-stats'] })
            setDeleteTarget(null)
            toast.success('Indicacao removida com sucesso')
        },
        onError: (err: unknown) => toast.error(getErrorMessage(err, 'Erro ao remover indicacao')),
    })

    const handleCreate = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        const fd = new FormData(e.currentTarget)
        const referrerId = Number(fd.get('referrer_customer_id') || 0)
        if (!referrerId) {
            toast.error('Selecione o cliente indicador')
            return
        }

        createMut.mutate({
            referrer_customer_id: referrerId,
            deal_id: Number(fd.get('deal_id')) || null,
            referred_name: fd.get('referred_name') as string,
            referred_email: (fd.get('referred_email') as string) || null,
            referred_phone: (fd.get('referred_phone') as string) || null,
            reward_type: (fd.get('reward_type') as string) || null,
            reward_value: createRewardValue > 0 ? createRewardValue : null,
            notes: (fd.get('notes') as string) || null,
        })
    }

    const handleUpdate = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        if (!editing?.id) return
        const fd = new FormData(e.currentTarget)

        updateMut.mutate({
            id: editing.id,
            data: {
                status: (fd.get('status') as string) || editing.status,
                deal_id: Number(fd.get('deal_id')) || null,
                reward_type: (fd.get('reward_type') as string) || null,
                reward_value: editRewardValue > 0 ? editRewardValue : null,
                reward_given: fd.get('reward_given') === 'on',
                notes: (fd.get('notes') as string) || null,
            },
        })
    }

    const totalRewards = Number(stats.total_rewards ?? stats.total_reward_value ?? 0)
    const topReferrers = useMemo(
        () =>
            (stats.top_referrers ?? []).map((item) => ({
                name: item.name ?? `#${item.id ?? '-'}`,
                count: Number(item.count ?? 0),
            })),
        [stats.top_referrers]
    )

    return (
        <div className='space-y-6'>
            <PageHeader
                title='Programa de Indicacoes'
                subtitle='Gerencie indicacoes de clientes e acompanhe conversoes.'
                count={referrals.length}
                icon={Users}
            >
                <Button
                    size='sm'
                    onClick={() => {
                        setCreateRewardValue(0)
                        setShowCreate(true)
                    }}
                    icon={<Plus className='h-4 w-4' />}
                >
                    Nova Indicacao
                </Button>
            </PageHeader>

            <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <div className='flex items-center gap-3'>
                        <div className='h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600'>
                            <UserPlus className='h-5 w-5' />
                        </div>
                        <div>
                            <p className='text-sm font-medium text-surface-500'>Total de Indicacoes</p>
                            <h3 className='text-2xl font-bold text-surface-900'>{stats.total ?? referrals.length}</h3>
                        </div>
                    </div>
                </div>
                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <div className='flex items-center gap-3'>
                        <div className='h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600'>
                            <TrendingUp className='h-5 w-5' />
                        </div>
                        <div>
                            <p className='text-sm font-medium text-surface-500'>Convertidas</p>
                            <h3 className='text-2xl font-bold text-surface-900'>{stats.converted ?? 0}</h3>
                        </div>
                    </div>
                </div>
                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <div className='flex items-center gap-3'>
                        <div className='h-10 w-10 rounded-full bg-cyan-100 flex items-center justify-center text-cyan-600'>
                            <Award className='h-5 w-5' />
                        </div>
                        <div>
                            <p className='text-sm font-medium text-surface-500'>Taxa de Conversao</p>
                            <h3 className='text-2xl font-bold text-surface-900'>{stats.conversion_rate ?? 0}%</h3>
                        </div>
                    </div>
                </div>
                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card'>
                    <div className='flex items-center gap-3'>
                        <div className='h-10 w-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600'>
                            <Gift className='h-5 w-5' />
                        </div>
                        <div>
                            <p className='text-sm font-medium text-surface-500'>Recompensas Pagas</p>
                            <h3 className='text-2xl font-bold text-surface-900'>{fmtBRL(totalRewards)}</h3>
                        </div>
                    </div>
                </div>
            </div>

            {topReferrers.length > 0 && (
                <div className='rounded-xl border border-default bg-surface-0 p-6 shadow-card'>
                    <h3 className='font-semibold text-surface-900 mb-4 flex items-center gap-2'>
                        <Star className='h-4 w-4 text-amber-500' /> Top Indicadores
                    </h3>
                    <div className='space-y-2'>
                        {topReferrers.slice(0, 5).map((r, idx) => (
                            <div key={`${r.name}-${idx}`} className='flex items-center gap-3 text-sm'>
                                <span className='w-6 text-center font-bold text-surface-400'>{idx + 1}</span>
                                <span className='flex-1 font-medium text-surface-900 truncate'>{r.name}</span>
                                <Badge variant='brand'>{r.count} indicacoes</Badge>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className='bg-surface-0 border border-default rounded-xl overflow-hidden shadow-card'>
                <div className='p-4 border-b border-default flex flex-wrap items-center justify-between gap-3'>
                    <h2 className='font-semibold text-surface-900'>Indicacoes</h2>
                    <div className='flex items-center gap-2'>
                        <label className='text-xs font-medium text-surface-500'>Status:</label>
                        <select
                            value={filterStatus}
                            onChange={(e) => setFilterStatus(e.target.value)}
                            className='h-8 rounded-lg border-default text-xs px-2 w-32'
                        >
                            <option value=''>Todos</option>
                            {Object.entries(STATUS_MAP).map(([k, v]) => (
                                <option key={k} value={k}>{v.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {isLoading ? (
                    <TableSkeleton rows={6} cols={7} />
                ) : referrals.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title='Nenhuma indicacao encontrada'
                        message='Registre a primeira indicacao para comecar a acompanhar o programa.'
                        action={{ label: 'Nova Indicacao', onClick: () => setShowCreate(true), icon: <Plus className='h-4 w-4' /> }}
                    />
                ) : (
                    <div className='overflow-x-auto'>
                        <table className='w-full text-sm'>
                            <thead className='bg-surface-50 text-surface-500 border-b border-default'>
                                <tr>
                                    <th className='px-4 py-3 text-left font-medium'>Indicador</th>
                                    <th className='px-4 py-3 text-left font-medium'>Indicado</th>
                                    <th className='px-4 py-3 text-left font-medium'>Contato</th>
                                    <th className='px-4 py-3 text-center font-medium'>Status</th>
                                    <th className='px-4 py-3 text-left font-medium'>Recompensa</th>
                                    <th className='px-4 py-3 text-left font-medium'>Negocio</th>
                                    <th className='px-4 py-3 text-right font-medium'>Acoes</th>
                                </tr>
                            </thead>
                            <tbody className='divide-y divide-subtle'>
                                {referrals.map((r) => {
                                    const s = STATUS_MAP[r.status] ?? STATUS_MAP.pending
                                    return (
                                        <tr key={r.id} className='hover:bg-surface-50 transition-colors'>
                                            <td className='px-4 py-3 font-medium text-surface-900'>
                                                {r.referrer?.name ?? `#${r.referrer_customer_id}`}
                                            </td>
                                            <td className='px-4 py-3 text-surface-700'>
                                                {r.referred?.name ?? r.referred_name}
                                            </td>
                                            <td className='px-4 py-3 text-surface-500 text-xs'>
                                                {r.referred_email && <span className='block'>{r.referred_email}</span>}
                                                {r.referred_phone && <span className='block'>{r.referred_phone}</span>}
                                                {!r.referred_email && !r.referred_phone && '-'}
                                            </td>
                                            <td className='px-4 py-3 text-center'>
                                                <Badge variant={s.variant}>{s.label}</Badge>
                                            </td>
                                            <td className='px-4 py-3 text-surface-600'>
                                                {r.reward_type ? (
                                                    <span className='flex items-center gap-1'>
                                                        <Gift className='h-3 w-3' />
                                                        {r.reward_value ? fmtBRL(r.reward_value) : r.reward_type}
                                                        {r.reward_given && <Badge variant='success' size='xs'>Pago</Badge>}
                                                    </span>
                                                ) : '-'}
                                            </td>
                                            <td className='px-4 py-3 text-surface-600'>
                                                {r.deal ? (
                                                    <span className='text-xs'>
                                                        {r.deal.title}
                                                        <span className='block text-emerald-600 font-medium'>{fmtBRL(r.deal.value)}</span>
                                                    </span>
                                                ) : '-'}
                                            </td>
                                            <td className='px-4 py-3'>
                                                <div className='flex justify-end gap-1'>
                                                    <Button
                                                        type='button'
                                                        size='icon'
                                                        variant='ghost'
                                                        className='h-7 w-7'
                                                        onClick={() => {
                                                            setEditRewardValue(Number(r.reward_value ?? 0))
                                                            setEditing(r)
                                                        }}
                                                        aria-label='Editar indicacao'
                                                    >
                                                        <Pencil className='h-3.5 w-3.5' />
                                                    </Button>
                                                    <Button
                                                        type='button'
                                                        size='icon'
                                                        variant='ghost'
                                                        className='h-7 w-7 text-red-600 hover:text-red-700'
                                                        onClick={() => setDeleteTarget(r)}
                                                        aria-label='Excluir indicacao'
                                                    >
                                                        <Trash2 className='h-3.5 w-3.5' />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Modal open={showCreate} onOpenChange={setShowCreate} title='Nova Indicacao'>
                <form onSubmit={handleCreate} className='space-y-4'>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Cliente Indicador *</label>
                        <select
                            name='referrer_customer_id'
                            required
                            className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500'
                            defaultValue=''
                        >
                            <option value='' disabled>Selecione um cliente</option>
                            {referrerOptions.map((customer) => (
                                <option key={customer.id} value={customer.id}>
                                    {customer.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Input label='Nome do Indicado *' name='referred_name' required placeholder='Nome completo' />
                    <div className='grid grid-cols-2 gap-4'>
                        <Input label='E-mail' name='referred_email' type='email' placeholder='email@exemplo.com' />
                        <Input label='Telefone' name='referred_phone' placeholder='(11) 99999-0000' />
                    </div>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Negocio Relacionado</label>
                        <select name='deal_id' className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500'>
                            <option value=''>Nao vincular agora</option>
                            {dealOptions.map((deal) => (
                                <option key={deal.id} value={deal.id}>
                                    {deal.title} ({fmtBRL(deal.value ?? 0)})
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className='grid grid-cols-2 gap-4'>
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Tipo de Recompensa</label>
                            <select name='reward_type' className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500'>
                                <option value=''>Nenhuma</option>
                                <option value='discount'>Desconto</option>
                                <option value='credit'>Credito</option>
                                <option value='gift'>Brinde</option>
                            </select>
                        </div>
                        <CurrencyInput label='Valor da Recompensa' value={createRewardValue} onChange={setCreateRewardValue} />
                    </div>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Observacoes</label>
                        <textarea
                            name='notes'
                            rows={2}
                            className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 px-3 py-2'
                            placeholder='Informacoes adicionais sobre a indicacao'
                        />
                    </div>
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => setShowCreate(false)}>Cancelar</Button>
                        <Button type='submit' loading={createMut.isPending}>Registrar Indicacao</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!editing} onOpenChange={() => setEditing(null)} title='Atualizar Indicacao'>
                {editing && (
                    <form onSubmit={handleUpdate} className='space-y-4'>
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Status *</label>
                            <select
                                name='status'
                                defaultValue={editing.status}
                                className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500'
                            >
                                {Object.entries(STATUS_MAP).map(([status, cfg]) => (
                                    <option key={status} value={status}>{cfg.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Negocio Relacionado</label>
                            <select
                                name='deal_id'
                                defaultValue={editing.deal?.id ?? ''}
                                className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500'
                            >
                                <option value=''>Nao vinculado</option>
                                {dealOptions.map((deal) => (
                                    <option key={deal.id} value={deal.id}>
                                        {deal.title} ({fmtBRL(deal.value ?? 0)})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className='grid grid-cols-2 gap-4'>
                            <div>
                                <label className='text-xs font-medium text-surface-700 mb-1 block'>Tipo de Recompensa</label>
                                <select
                                    name='reward_type'
                                    defaultValue={editing.reward_type ?? ''}
                                    className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500'
                                >
                                    <option value=''>Nenhuma</option>
                                    <option value='discount'>Desconto</option>
                                    <option value='credit'>Credito</option>
                                    <option value='gift'>Brinde</option>
                                </select>
                            </div>
                            <CurrencyInput
                                label='Valor da Recompensa'
                                value={editRewardValue}
                                onChange={setEditRewardValue}
                            />
                        </div>
                        <label className='flex items-center gap-2 text-sm text-surface-700 cursor-pointer'>
                            <input
                                type='checkbox'
                                name='reward_given'
                                defaultChecked={editing.reward_given}
                                className='rounded border-default'
                            />
                            Recompensa ja foi paga
                        </label>
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Observacoes</label>
                            <textarea
                                name='notes'
                                rows={2}
                                defaultValue={editing.notes ?? ''}
                                className='w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 px-3 py-2'
                                placeholder='Informacoes adicionais'
                            />
                        </div>
                        <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                            <Button variant='outline' type='button' onClick={() => setEditing(null)}>Cancelar</Button>
                            <Button type='submit' loading={updateMut.isPending}>Salvar Alteracoes</Button>
                        </div>
                    </form>
                )}
            </Modal>

            <Modal open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)} title='Excluir Indicacao'>
                {deleteTarget && (
                    <div className='space-y-4'>
                        <p className='text-sm text-surface-600'>
                            Confirmar exclusao da indicacao de <strong>{deleteTarget.referred_name}</strong>?
                        </p>
                        <div className='flex justify-end gap-2 border-t border-surface-100 pt-4'>
                            <Button variant='outline' onClick={() => setDeleteTarget(null)}>Cancelar</Button>
                            <Button
                                className='bg-red-600 hover:bg-red-700 text-white'
                                loading={deleteMut.isPending}
                                onClick={() => deleteMut.mutate(deleteTarget.id)}
                            >
                                Excluir
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    )
}
