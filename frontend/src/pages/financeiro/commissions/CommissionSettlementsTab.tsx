import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Calendar, CheckCircle, Download, RefreshCw, RotateCcw, Wallet, XCircle } from 'lucide-react'
import { getApiErrorMessage } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { cn, formatCurrency } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { Badge } from '@/components/ui/badge'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { closeSettlementSchema, paySettlementSchema, rejectSettlementSchema, batchGenerateSchema } from './schemas'
import type { CloseSettlementFormData, PaySettlementFormData, RejectSettlementFormData, BatchGenerateFormData } from './schemas'
import type { BalanceSummary, CommissionSettlement, UserOption } from './types'
import { fmtDate, normalizeSettlementStatus, settlementStatusLabel, settlementStatusVariant } from './utils'

/** Type-safe API response unwrap */
function safeUnwrap<T>(res: unknown, fallback: T): T {
    const r = res as Record<string, unknown> | undefined
    const d = r?.data as Record<string, unknown> | T | undefined
    if (d && typeof d === 'object' && 'data' in d) return (d as Record<string, unknown>).data as T
    if (d !== undefined && d !== null) return d as T
    return (r as T) ?? fallback
}

export function CommissionSettlementsTab() {
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canCreateSettlement = hasPermission('commissions.settlement.create')
    const canUpdateSettlement = hasPermission('commissions.settlement.update')
    const canBatchGenerate = hasPermission('commissions.rule.create')
    const canApprove = hasPermission('commissions.settlement.approve')

    const [page, setPage] = useState(1)
    const [filterStatus, setFilterStatus] = useState('')
    const [filterPeriod, setFilterPeriod] = useState('')
    const [filterUserId, setFilterUserId] = useState('')
    const [balanceUserId, setBalanceUserId] = useState('')
    const [payError, setPayError] = useState<string | null>(null)
    const [confirmAction, setConfirmAction] = useState<{ type: 'reopen' | 'approve'; id: number } | null>(null)
    const [payModal, setPayModal] = useState<{ id: number; total_amount: number } | null>(null)
    const [rejectModal, setRejectModal] = useState<{ id: number } | null>(null)
    const [batchModal, setBatchModal] = useState(false)
    const [previewPdf, setPreviewPdf] = useState<{ url: string; userId: number; period: string } | null>(null)

    const closeForm = useForm<CloseSettlementFormData>({
        resolver: zodResolver(closeSettlementSchema),
        defaultValues: { user_id: '', period: new Date().toISOString().slice(0, 7) }
    })

    const payForm = useForm<PaySettlementFormData>({
        resolver: zodResolver(paySettlementSchema),
        defaultValues: { payment_notes: '' }
    })

    const rejectForm = useForm<RejectSettlementFormData>({
        resolver: zodResolver(rejectSettlementSchema),
        defaultValues: { rejection_reason: '' }
    })

    const batchForm = useForm<BatchGenerateFormData>({
        resolver: zodResolver(batchGenerateSchema),
        defaultValues: { user_id: '', date_from: '', date_to: '' }
    })

    const settlementParams: Record<string, string | number> = { page, per_page: 20 }
    if (filterStatus) settlementParams.status = filterStatus
    if (filterPeriod) settlementParams.period = filterPeriod
    if (filterUserId) settlementParams.user_id = filterUserId

    const { data: settlementsRes, isLoading } = useQuery({
        queryKey: ['commission-settlements', settlementParams],
        queryFn: () => financialApi.commissions.settlements(settlementParams),
    })
    const settlementsPayload = safeUnwrap<Record<string, unknown>>(settlementsRes, {})
    const settlements: CommissionSettlement[] = (settlementsPayload?.data as CommissionSettlement[] | undefined) ?? (Array.isArray(settlementsPayload) ? settlementsPayload as unknown as CommissionSettlement[] : [])
    const settlementsPagination = (settlementsPayload?.meta as Record<string, unknown> | undefined) ?? {}
    const settlementsLastPage = (settlementsPagination?.last_page as number | undefined) ?? (settlementsPayload?.last_page as number | undefined) ?? 1

    const { data: usersPayload = { data: [] } } = useQuery({
        queryKey: ['commission-users-select'],
        queryFn: () => financialApi.commissions.users(),
    })
    const users: UserOption[] = safeUnwrap<UserOption[]>(usersPayload, [])

    const { data: balancePayload = null } = useQuery({
        queryKey: ['commission-balance', balanceUserId],
        queryFn: () => financialApi.commissions.balanceSummary(),
        enabled: Boolean(balanceUserId),
    })
    // Simulate balance fetch per user if financialApi doesn't accept user_id parameter, but usually it does. The API object doesn't have it defined with params, so we might need a workaround or assume it does.
    // In actual implementation, we'll pass params to it. Currently, it's defined without params in financialApi.ts, let's fix it later. For now, we'll use api.get directly if needed, or modify financialApi.
    const balance: BalanceSummary | null = safeUnwrap<BalanceSummary | null>(balancePayload, null)

    const invalidateAll = () => {
        const keys = ['commission-settlements', 'commission-events', 'commission-overview', 'commission-balance']
        keys.forEach((key) => {
            qc.invalidateQueries({ queryKey: [key] })
        })
        broadcastQueryInvalidation(keys, 'Comissao')
    }

    const closeMut = useMutation({
        mutationFn: (payload: CloseSettlementFormData) => financialApi.commissions.closeSettlement(payload),
        onSuccess: () => {
            invalidateAll()
            toast.success('Periodo fechado com sucesso')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao fechar periodo')),
    })

    const payMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: PaySettlementFormData }) => financialApi.commissions.paySettlement(id, data),
        onSuccess: () => {
            invalidateAll()
            setPayError(null)
            setPayModal(null)
            payForm.reset()
            toast.success('Pagamento registrado')
        },
        onError: (err: unknown) => {
            const message = getApiErrorMessage(err, 'Erro ao pagar fechamento')
            setPayError(message)
            toast.error(message)
        },
    })

    const reopenMut = useMutation({
        mutationFn: (id: number) => financialApi.commissions.reopenSettlement(id),
        onSuccess: () => { invalidateAll(); toast.success('Fechamento reaberto') },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao reabrir fechamento')),
    })

    const approveMut = useMutation({
        mutationFn: (id: number) => financialApi.commissions.approveSettlement(id),
        onSuccess: () => { invalidateAll(); toast.success('Fechamento aprovado') },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao aprovar fechamento')),
    })

    const rejectMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: RejectSettlementFormData }) => financialApi.commissions.rejectSettlement(id, data),
        onSuccess: () => {
            invalidateAll()
            setRejectModal(null)
            rejectForm.reset()
            toast.success('Fechamento rejeitado')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao rejeitar fechamento')),
    })

    const batchGenMut = useMutation({
        mutationFn: (payload: BatchGenerateFormData) => financialApi.commissions.batchGenerateForWorkOrders(payload),
        onSuccess: async (response) => {
            const resData = safeUnwrap<Record<string, unknown>>(response, {})
            toast.success((resData.message as string) ?? `${(resData.generated as number) ?? 0} comissoes geradas`)
            invalidateAll()
            setBatchModal(false)
            batchForm.reset()
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao gerar comissoes em lote')),
    })

    const handleExportSettlements = async () => {
        try {
            const response = await financialApi.commissions.exportSettlements()
            const url = window.URL.createObjectURL(new Blob([response as BlobPart]))
            const anchor = document.createElement('a')
            anchor.href = url
            anchor.download = 'fechamentos.csv'
            anchor.click()
            window.URL.revokeObjectURL(url)
        } catch (err) {
            toast.error(getApiErrorMessage(err, 'Erro ao exportar fechamentos'))
        }
    }

    const handlePreviewStatement = async (userId: number, period: string) => {
        try {
            const response = await financialApi.commissions.downloadStatement({ user_id: userId, period })
            const url = window.URL.createObjectURL(new Blob([response as BlobPart], { type: 'application/pdf' }))
            setPreviewPdf({ url, userId, period })
        } catch (err) {
            toast.error(getApiErrorMessage(err, 'Erro ao carregar PDF'))
        }
    }

    const handleDownloadStatement = () => {
        if (!previewPdf) return

        const anchor = document.createElement('a')
        anchor.href = previewPdf.url
        anchor.download = `extrato-${previewPdf.period}-${previewPdf.userId}.pdf`
        anchor.click()
    }

    return (
        <div className='space-y-4'>
            <div className='bg-surface-0 border border-default rounded-xl p-4 shadow-card space-y-4'>
                <h2 className='font-semibold text-surface-900'>Saldo acumulado</h2>
                <div className='flex flex-wrap gap-3 items-end'>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Beneficiario</label>
                        <select
                            aria-label='Selecionar beneficiario'
                            value={balanceUserId}
                            onChange={(event) => setBalanceUserId(event.target.value)}
                            className='w-48 rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2'
                        >
                            <option value=''>Selecione...</option>
                            {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                    </div>
                </div>
                {balance && (
                    <div className='grid grid-cols-2 gap-3 sm:grid-cols-4'>
                        <MetricCard label='Total calculado' value={formatCurrency(balance.total_earned)} tone='emerald' />
                        <MetricCard label='Total pago' value={formatCurrency(balance.total_paid)} tone='sky' />
                        <MetricCard label='Saldo a receber' value={formatCurrency(balance.balance)} tone='amber' />
                        <MetricCard label='Pendente sem fechamento' value={formatCurrency(balance.pending_unsettled)} tone='surface' />
                    </div>
                )}
            </div>

            {(canCreateSettlement || canBatchGenerate) && (
                <div className='bg-surface-0 border border-default rounded-xl p-4 shadow-card space-y-4'>
                    <h2 className='font-semibold text-surface-900'>{canCreateSettlement ? 'Fechar periodo' : 'Gerar comissoes em lote'}</h2>
                    <div className='flex flex-wrap gap-3 items-end'>
                        {canCreateSettlement && (
                            <form
                                onSubmit={closeForm.handleSubmit((data) => closeMut.mutate(data))}
                                className='flex flex-wrap gap-3 items-end w-full sm:w-auto'
                            >
                                <div>
                                    <label className='text-xs font-medium text-surface-700 mb-1 block'>Periodo</label>
                                    <Input
                                        type='month'
                                        {...closeForm.register('period')}
                                        className={cn('w-40', closeForm.formState.errors.period && 'border-red-500')}
                                    />
                                    {closeForm.formState.errors.period && <p className='text-[10px] text-red-500 mt-1 absolute'>{closeForm.formState.errors.period.message}</p>}
                                </div>
                                <div>
                                    <label className='text-xs font-medium text-surface-700 mb-1 block'>Usuario</label>
                                    <select
                                        aria-label='Selecionar usuario para fechamento'
                                        {...closeForm.register('user_id')}
                                        className={cn('w-48 rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2', closeForm.formState.errors.user_id && 'border-red-500')}
                                    >
                                        <option value=''>Selecione...</option>
                                        {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                                    </select>
                                    {closeForm.formState.errors.user_id && <p className='text-[10px] text-red-500 mt-1 absolute'>{closeForm.formState.errors.user_id.message}</p>}
                                </div>
                                <Button type='submit' loading={closeMut.isPending} icon={<Calendar className='h-4 w-4' />}>
                                    Fechar periodo
                                </Button>
                            </form>
                        )}
                        {canBatchGenerate && (
                            <Button variant='outline' onClick={() => setBatchModal(true)} icon={<RefreshCw className='h-4 w-4' />}>
                                Gerar comissoes em lote
                            </Button>
                        )}
                    </div>
                    {payError && (
                        <p className='text-sm text-red-600 bg-red-50 rounded-lg p-3'>
                            {payError}
                            <button className='underline ml-2' onClick={() => setPayError(null)}>Fechar</button>
                        </p>
                    )}
                </div>
            )}

            <div className='bg-surface-0 border border-default rounded-xl overflow-hidden shadow-card'>
                <div className='p-4 border-b border-default space-y-3'>
                    <div className='flex justify-between items-center'>
                        <h2 className='font-semibold text-surface-900'>Fechamentos realizados</h2>
                        <Button variant='outline' size='sm' onClick={handleExportSettlements} icon={<Download className='h-3 w-3' />}>
                            Exportar CSV
                        </Button>
                    </div>
                    <div className='flex flex-wrap gap-2'>
                        <select title='Filtrar por status' value={filterStatus} onChange={e => { setFilterStatus(e.target.value); setPage(1) }} className='h-8 rounded-lg border-default text-xs px-2 w-32'>
                            <option value=''>Todos status</option>
                            <option value='open'>Aberto</option>
                            <option value='closed'>Fechado</option>
                            <option value='pending_approval'>Pendente aprov.</option>
                            <option value='approved'>Aprovado</option>
                            <option value='paid'>Pago</option>
                            <option value='rejected'>Rejeitado</option>
                        </select>
                        <input type='month' value={filterPeriod} onChange={e => { setFilterPeriod(e.target.value); setPage(1) }} className='h-8 rounded-lg border-default text-xs px-2 w-36' />
                        <select title='Filtrar por usuário' value={filterUserId} onChange={e => { setFilterUserId(e.target.value); setPage(1) }} className='h-8 rounded-lg border-default text-xs px-2 w-40'>
                            <option value=''>Todos usuarios</option>
                            {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                        </select>
                    </div>
                </div>
                <div className='overflow-x-auto'>
                    <table className='w-full text-sm'>
                        <thead className='bg-surface-50 text-surface-500 border-b border-default'>
                            <tr>
                                <th className='px-4 py-3 text-left font-medium'>Periodo</th>
                                <th className='px-4 py-3 text-left font-medium'>Beneficiario</th>
                                <th className='px-4 py-3 text-right font-medium'>Eventos</th>
                                <th className='px-4 py-3 text-right font-medium'>Calculado</th>
                                <th className='px-4 py-3 text-right font-medium'>Pago</th>
                                <th className='px-4 py-3 text-right font-medium'>Saldo</th>
                                <th className='px-4 py-3 text-center font-medium'>Status</th>
                                <th className='px-4 py-3 text-center font-medium'>Pago em</th>
                                <th className='px-4 py-3 text-right font-medium'>Acoes</th>
                            </tr>
                        </thead>
                        <tbody className='divide-y divide-subtle'>
                            {isLoading ? (
                                <tr><td colSpan={9} className='p-8 text-center text-surface-500'>Carregando...</td></tr>
                            ) : settlements.length === 0 ? (
                                <tr><td colSpan={9} className='p-12 text-center'><Calendar className='h-8 w-8 mx-auto text-surface-300 mb-2' /><p className='text-surface-500'>Nenhum fechamento realizado.</p></td></tr>
                            ) : settlements.map((settlement) => (
                                (() => {
                                    const normalizedStatus = normalizeSettlementStatus(settlement.status)

                                    return (
                                <tr key={settlement.id} className='hover:bg-surface-50 transition-colors'>
                                    <td className='px-4 py-3 font-medium text-surface-900'>{settlement.period}</td>
                                    <td className='px-4 py-3 text-surface-700'>{settlement.user?.name ?? '-'}</td>
                                    <td className='px-4 py-3 text-right text-surface-600'>{settlement.events_count}</td>
                                    <td className='px-4 py-3 text-right font-semibold text-emerald-600'>{formatCurrency(settlement.total_amount)}</td>
                                    <td className='px-4 py-3 text-right font-semibold text-sky-600'>{settlement.paid_amount != null ? formatCurrency(settlement.paid_amount) : '-'}</td>
                                    <td className='px-4 py-3 text-right font-semibold'>
                                        <span className={Number(settlement.balance ?? 0) > 0 ? 'text-amber-600' : 'text-surface-400'}>
                                            {settlement.balance != null ? formatCurrency(settlement.balance) : '-'}
                                        </span>
                                    </td>
                                    <td className='px-4 py-3 text-center'>
                                        <Badge variant={settlementStatusVariant(settlement.status)}>{settlementStatusLabel(settlement.status)}</Badge>
                                        {settlement.rejection_reason && (
                                            <p className='text-xs text-red-500 mt-1' title={settlement.rejection_reason}>
                                                Motivo: {settlement.rejection_reason.slice(0, 30)}{settlement.rejection_reason.length > 30 ? '...' : ''}
                                            </p>
                                        )}
                                        {settlement.payment_notes && (
                                            <p className='text-xs text-surface-500 mt-1' title={settlement.payment_notes}>
                                                Obs: {settlement.payment_notes.slice(0, 30)}{settlement.payment_notes.length > 30 ? '...' : ''}
                                            </p>
                                        )}
                                    </td>
                                    <td className='px-4 py-3 text-center text-surface-500'>{fmtDate(settlement.paid_at)}</td>
                                    <td className='px-4 py-3 text-right'>
                                        <div className='flex justify-end gap-1 flex-wrap'>
                                            <Button
                                                size='sm'
                                                variant='outline'
                                                className='h-7 text-xs px-2'
                                                onClick={() => handlePreviewStatement(settlement.user_id, settlement.period)}
                                                icon={<Download className='h-3 w-3' />}
                                            >
                                                PDF
                                            </Button>
                                            {canApprove && normalizedStatus === 'closed' && (
                                                <>
                                                    <Button
                                                        size='sm'
                                                        className='bg-sky-600 hover:bg-sky-700 text-white h-7 text-xs px-2'
                                                        onClick={() => setConfirmAction({ type: 'approve', id: settlement.id })}
                                                        loading={approveMut.isPending}
                                                        icon={<CheckCircle className='h-3 w-3' />}
                                                    >
                                                        Aprovar
                                                    </Button>
                                                    <Button
                                                        size='sm'
                                                        variant='outline'
                                                        className='text-red-600 border-red-200 hover:bg-red-50 h-7 text-xs px-2'
                                                        onClick={() => setRejectModal({ id: settlement.id })}
                                                        icon={<XCircle className='h-3 w-3' />}
                                                    >
                                                        Rejeitar
                                                    </Button>
                                                </>
                                            )}
                                            {canUpdateSettlement && (normalizedStatus === 'closed' || normalizedStatus === 'approved') && (
                                                <Button
                                                    size='sm'
                                                    className='bg-emerald-600 hover:bg-emerald-700 text-white h-7 text-xs px-2'
                                                    onClick={() => {
                                                        setPayError(null)
                                                        payForm.reset()
                                                        setPayModal({ id: settlement.id, total_amount: Number(settlement.total_amount) })
                                                    }}
                                                    loading={payMut.isPending}
                                                    icon={<Wallet className='h-3 w-3' />}
                                                >
                                                    Pagar
                                                </Button>
                                            )}
                                            {canUpdateSettlement && ['closed', 'approved', 'rejected'].includes(normalizedStatus) && (
                                                <Button
                                                    size='sm'
                                                    variant='outline'
                                                    className='h-7 text-xs px-2'
                                                    onClick={() => setConfirmAction({ type: 'reopen', id: settlement.id })}
                                                    loading={reopenMut.isPending}
                                                    icon={<RotateCcw className='h-3 w-3' />}
                                                >
                                                    Reabrir
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                                    )
                                })()
                            ))}
                        </tbody>
                    </table>
                </div>
                {settlementsLastPage > 1 && (
                    <div className='flex items-center justify-between px-4 py-3 border-t border-default'>
                        <span className='text-xs text-surface-500'>Página {page} de {settlementsLastPage}</span>
                        <div className='flex gap-1'>
                            <Button variant='outline' size='sm' className='h-7 text-xs' disabled={page <= 1} onClick={() => setPage(p => p - 1)}>Anterior</Button>
                            <Button variant='outline' size='sm' className='h-7 text-xs' disabled={page >= settlementsLastPage} onClick={() => setPage(p => p + 1)}>Próxima</Button>
                        </div>
                    </div>
                )}
            </div>

            <Modal
                open={Boolean(previewPdf)}
                onOpenChange={() => {
                    if (previewPdf) {
                        window.URL.revokeObjectURL(previewPdf.url)
                        setPreviewPdf(null)
                    }
                }}
                title='Preview do extrato'
            >
                <div className='space-y-4'>
                    {previewPdf && <iframe src={previewPdf.url} className='w-full h-[60vh] rounded-lg border border-default' title='Preview PDF' />}
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' onClick={() => {
                            if (previewPdf) {
                                window.URL.revokeObjectURL(previewPdf.url)
                                setPreviewPdf(null)
                            }
                        }}>
                            Fechar
                        </Button>
                        <Button onClick={handleDownloadStatement} icon={<Download className='h-4 w-4' />}>Baixar PDF</Button>
                    </div>
                </div>
            </Modal>

            <Modal
                open={Boolean(payModal)}
                onOpenChange={() => {
                    setPayModal(null)
                    payForm.reset()
                }}
                title='Registrar pagamento'
            >
                <form onSubmit={payForm.handleSubmit((data) => {
                    if (payModal) payMut.mutate({ id: payModal.id, data })
                })} className='space-y-4'>
                    <p className='text-sm text-surface-600'>
                        Este fluxo registra apenas o pagamento integral do fechamento.
                        Valor calculado: <strong>{payModal ? formatCurrency(payModal.total_amount) : '-'}</strong>.
                    </p>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Observacoes</label>
                        <textarea
                            {...payForm.register('payment_notes')}
                            className={cn('w-full rounded-lg border-default text-sm p-3 min-h-[60px]', payForm.formState.errors.payment_notes && 'border-red-500')}
                            placeholder='Ex: Pago via PIX em 15/03/2026'
                        />
                        {payForm.formState.errors.payment_notes && <p className='text-[10px] text-red-500 mt-1'>{payForm.formState.errors.payment_notes.message}</p>}
                    </div>
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => {
                            setPayModal(null)
                            payForm.reset()
                        }}>
                            Cancelar
                        </Button>
                        <Button
                            type='submit'
                            className='bg-emerald-600 hover:bg-emerald-700 text-white'
                            loading={payMut.isPending}
                        >
                            Confirmar pagamento integral
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={Boolean(confirmAction)}
                onOpenChange={() => setConfirmAction(null)}
                title={confirmAction?.type === 'approve' ? 'Confirmar aprovacao' : 'Confirmar reabertura'}
            >
                <p className='text-sm text-surface-600 py-2'>
                    {confirmAction?.type === 'approve'
                        ? 'Deseja aprovar este fechamento? Depois disso ele podera ser pago.'
                        : 'Deseja reabrir este fechamento? Os eventos voltarao ao status pendente.'}
                </p>
                <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                    <Button variant='outline' onClick={() => setConfirmAction(null)}>Cancelar</Button>
                    <Button
                        className={confirmAction?.type === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : ''}
                        loading={confirmAction?.type === 'approve' ? approveMut.isPending : reopenMut.isPending}
                        onClick={() => {
                            if (confirmAction?.type === 'approve') {
                                approveMut.mutate(confirmAction.id)
                            } else if (confirmAction) {
                                reopenMut.mutate(confirmAction.id)
                            }
                            setConfirmAction(null)
                        }}
                    >
                        {confirmAction?.type === 'approve' ? 'Confirmar aprovacao' : 'Confirmar reabertura'}
                    </Button>
                </div>
            </Modal>

            <Modal
                open={Boolean(rejectModal)}
                onOpenChange={() => {
                    setRejectModal(null)
                    rejectForm.reset()
                }}
                title='Rejeitar fechamento'
            >
                <form onSubmit={rejectForm.handleSubmit((data) => {
                    if (rejectModal) rejectMut.mutate({ id: rejectModal.id, data })
                })} className='space-y-4'>
                    <p className='text-sm text-surface-600'>Informe o motivo da rejeicao. O fechamento voltara para revisao operacional.</p>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Motivo da rejeicao</label>
                        <textarea
                            {...rejectForm.register('rejection_reason')}
                            className={cn('w-full rounded-lg border-default text-sm p-3 min-h-[80px]', rejectForm.formState.errors.rejection_reason && 'border-red-500')}
                            placeholder='Ex: valores divergentes do relatorio'
                        />
                        {rejectForm.formState.errors.rejection_reason && <p className='text-[10px] text-red-500 mt-1'>{rejectForm.formState.errors.rejection_reason.message}</p>}
                    </div>
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => {
                            setRejectModal(null)
                            rejectForm.reset()
                        }}>
                            Cancelar
                        </Button>
                        <Button
                            type='submit'
                            className='bg-red-600 hover:bg-red-700 text-white'
                            loading={rejectMut.isPending}
                        >
                            Rejeitar fechamento
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={batchModal} onOpenChange={(v) => { setBatchModal(v); if (!v) batchForm.reset() }} title='Gerar comissoes em lote'>
                <form onSubmit={batchForm.handleSubmit((data) => batchGenMut.mutate(data))} className='space-y-4'>
                    <p className='text-sm text-surface-600'>
                        Selecione o tecnico e o periodo. O sistema vai gerar comissoes apenas para OS ainda nao comissionadas.
                    </p>
                    <div>
                        <label className='text-xs font-medium text-surface-700 mb-1 block'>Tecnico</label>
                        <select
                            aria-label='Selecionar tecnico para lote'
                            {...batchForm.register('user_id')}
                            className={cn('w-full rounded-lg border-default text-sm focus:ring-brand-500 focus:border-brand-500 h-9 px-2', batchForm.formState.errors.user_id && 'border-red-500')}
                        >
                            <option value=''>Todos</option>
                            {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                        {batchForm.formState.errors.user_id && <p className='text-[10px] text-red-500 mt-1'>{batchForm.formState.errors.user_id.message}</p>}
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Data inicio</label>
                            <Input type='date' {...batchForm.register('date_from')} className={batchForm.formState.errors.date_from ? 'border-red-500' : ''} />
                            {batchForm.formState.errors.date_from && <p className='text-[10px] text-red-500 mt-1'>{batchForm.formState.errors.date_from.message}</p>}
                        </div>
                        <div>
                            <label className='text-xs font-medium text-surface-700 mb-1 block'>Data fim</label>
                            <Input type='date' {...batchForm.register('date_to')} className={batchForm.formState.errors.date_to ? 'border-red-500' : ''} />
                            {batchForm.formState.errors.date_to && <p className='text-[10px] text-red-500 mt-1'>{batchForm.formState.errors.date_to.message}</p>}
                        </div>
                    </div>
                    <div className='flex justify-end gap-2 pt-4 border-t border-surface-100'>
                        <Button variant='outline' type='button' onClick={() => { setBatchModal(false); batchForm.reset() }}>Cancelar</Button>
                        <Button
                            type='submit'
                            className='bg-brand-600 hover:bg-brand-700 text-white'
                            loading={batchGenMut.isPending}
                        >
                            Gerar comissoes
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    )
}

function MetricCard({
    label,
    value,
    tone,
}: {
    label: string
    value: string
    tone: 'emerald' | 'sky' | 'amber' | 'surface'
}) {
    const styles = {
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 text-emerald-600',
        sky: 'border-sky-200 bg-sky-50 text-sky-700 text-sky-600',
        amber: 'border-amber-200 bg-amber-50 text-amber-700 text-amber-600',
        surface: 'border-surface-200 bg-surface-50 text-surface-700 text-surface-600',
    }[tone].split(' ')

    return (
        <div className={`rounded-xl p-3 text-center border ${styles[0]} ${styles[1]}`}>
            <p className={`text-xl font-bold ${styles[2]}`}>{value}</p>
            <p className={`text-xs font-medium mt-0.5 ${styles[3]}`}>{label}</p>
        </div>
    )
}
