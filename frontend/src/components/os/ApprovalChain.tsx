import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ShieldCheck, Clock, CheckCircle2, XCircle, Send, ChevronDown, ChevronUp } from 'lucide-react'
import api from '@/lib/api'
import { workOrderApi } from '@/lib/work-order-api'
import { queryKeys } from '@/lib/query-keys'
import { cn, getApiErrorMessage } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'

interface ApprovalItem {
    id: number
    approver_id: number
    approver_name: string
    status: 'pending' | 'approved' | 'rejected' | 'cancelled'
    notes?: string | null
    response_notes?: string | null
    responded_at?: string | null
    created_at: string
}

interface UserOption {
    id: number
    name: string
}

interface ApprovalChainProps {
    workOrderId: number
    currentUserId: number
}

const statusConfig = {
    pending: { label: 'Pendente', icon: Clock, color: 'text-amber-600 bg-amber-50' },
    approved: { label: 'Aprovado', icon: CheckCircle2, color: 'text-emerald-600 bg-emerald-50' },
    rejected: { label: 'Rejeitado', icon: XCircle, color: 'text-red-600 bg-red-50' },
    cancelled: { label: 'Cancelado', icon: XCircle, color: 'text-surface-500 bg-surface-100' },
    partially_approved: { label: 'Parcial', icon: ShieldCheck, color: 'text-sky-600 bg-sky-50' },
} as const

export default function ApprovalChainView({ workOrderId, currentUserId }: ApprovalChainProps) {
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()
    const [expanded, setExpanded] = useState(true)
    const [comment, setComment] = useState('')
    const [showCommentFor, setShowCommentFor] = useState<number | null>(null)
    const [selectedApproverIds, setSelectedApproverIds] = useState<number[]>([])
    const [requestNotes, setRequestNotes] = useState('')
    const canManageApprovals = hasPermission('os.work_order.update')

    const { data: approvalsRes } = useQuery({
        queryKey: ['approval-chain', workOrderId],
        queryFn: () => workOrderApi.approvals(workOrderId),
    })
    const approvals: ApprovalItem[] = approvalsRes?.data?.data ?? approvalsRes?.data ?? []

    const { data: usersRes } = useQuery({
        queryKey: ['approval-users', workOrderId],
        queryFn: () => api.get('/users', { params: { per_page: 100 } }),
        enabled: canManageApprovals,
    })
    const users: UserOption[] = usersRes?.data?.data ?? []

    const approveMut = useMutation({
        mutationFn: ({ approverId, action }: { approverId: number; action: 'approve' | 'reject' }) =>
            workOrderApi.respondApproval(workOrderId, approverId, action, { notes: comment || undefined }),
        onSuccess: (_, { action }) => {
            qc.invalidateQueries({ queryKey: ['approval-chain', workOrderId] })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            toast.success(action === 'approve' ? 'Aprovacao registrada com sucesso!' : 'Rejeicao registrada')
            setComment('')
            setShowCommentFor(null)
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao processar aprovacao')),
    })

    const requestMut = useMutation({
        mutationFn: () => workOrderApi.requestApproval(workOrderId, {
            approver_ids: selectedApproverIds,
            notes: requestNotes || undefined,
        }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['approval-chain', workOrderId] })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            toast.success('Solicitacao de aprovacao enviada!')
            setSelectedApproverIds([])
            setRequestNotes('')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao solicitar aprovacao')),
    })

    const toggleApprover = (userId: number) => {
        setSelectedApproverIds((prev) =>
            prev.includes(userId) ? prev.filter((id) => id !== userId) : [...prev, userId]
        )
    }

    const approvedCount = approvals.filter((item) => item.status === 'approved').length
    const rejectedCount = approvals.filter((item) => item.status === 'rejected').length
    const pendingCount = approvals.filter((item) => item.status === 'pending').length
    const chainStatus =
        rejectedCount > 0 ? statusConfig.rejected
            : approvals.length > 0 && pendingCount === 0 ? statusConfig.approved
                : approvedCount > 0 ? statusConfig.partially_approved
                    : statusConfig.pending
    const ChainIcon = chainStatus.icon

    if (approvals.length === 0) {
        return (
            <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                    <ShieldCheck className="h-4 w-4 text-brand-500" />
                    Aprovacao
                </h3>
                {canManageApprovals ? (
                    <div className="space-y-3">
                        <div className="flex flex-wrap gap-2">
                            {(users || []).map((user) => (
                                <button
                                    key={user.id}
                                    type="button"
                                    onClick={() => toggleApprover(user.id)}
                                    className={cn(
                                        'rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors',
                                        selectedApproverIds.includes(user.id)
                                            ? 'border-brand-500 bg-brand-50 text-brand-700'
                                            : 'border-default text-surface-600 hover:border-surface-300 hover:bg-surface-50'
                                    )}
                                >
                                    {user.name}
                                </button>
                            ))}
                        </div>
                        <textarea
                            value={requestNotes}
                            onChange={(event) => setRequestNotes(event.target.value)}
                            placeholder="Motivo da solicitacao (opcional)..."
                            aria-label="Observacoes da solicitacao de aprovacao"
                            className="min-h-20 w-full rounded-lg border border-subtle bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => requestMut.mutate()}
                            loading={requestMut.isPending}
                            disabled={selectedApproverIds.length === 0}
                            icon={<Send className="h-3.5 w-3.5" />}
                        >
                            <span>Solicitar Aprovacao</span>
                        </Button>
                    </div>
                ) : (
                    <p className="text-sm text-surface-500">
                        Nenhuma aprovacao registrada para esta OS.
                    </p>
                )}
            </div>
        )
    }

    return (
        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
            <button
                onClick={() => setExpanded(!expanded)}
                className="flex w-full items-center justify-between"
            >
                <h3 className="flex items-center gap-2 text-sm font-semibold text-surface-900">
                    <ShieldCheck className="h-4 w-4 text-brand-500" />
                    Cadeia de Aprovacao
                </h3>
                <div className="flex items-center gap-2">
                    <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold', chainStatus.color)}>
                        <ChainIcon className="h-3 w-3" />
                        {chainStatus.label}
                    </span>
                    {expanded ? <ChevronUp className="h-3 w-3 text-surface-400" /> : <ChevronDown className="h-3 w-3 text-surface-400" />}
                </div>
            </button>

            {expanded && (
                <div className="mt-3 space-y-0">
                    {approvals
                        .slice()
                        .sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
                        .map((approval, idx) => {
                            const isCurrentUser = approval.approver_id === currentUserId
                            const canAct = canManageApprovals && isCurrentUser && approval.status === 'pending'
                            const status = statusConfig[approval.status] ?? statusConfig.pending
                            const StatusIcon = status.icon

                            return (
                                <div key={approval.id}>
                                    {idx > 0 && (
                                        <div className="flex justify-center">
                                            <div className={cn(
                                                'h-4 w-0.5',
                                                approval.status === 'approved' ? 'bg-emerald-300' :
                                                    approval.status === 'rejected' ? 'bg-red-300' : 'bg-surface-200'
                                            )} />
                                        </div>
                                    )}

                                    <div className={cn(
                                        'rounded-lg border px-3 py-2.5 transition-colors',
                                        canAct ? 'border-brand-300 bg-brand-50/50 ring-1 ring-brand-200' : 'border-subtle bg-surface-50'
                                    )}>
                                        <div className="flex items-center gap-2">
                                            <div className={cn('rounded-full p-1', status.color)}>
                                                <StatusIcon className="h-3 w-3" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <span className="text-xs font-medium text-surface-800">{approval.approver_name}</span>
                                            </div>
                                            <span className={cn('text-[10px] font-medium', status.color.split(' ')[0])}>
                                                Etapa {idx + 1}
                                            </span>
                                        </div>

                                        {(approval.response_notes ?? approval.notes) && (
                                            <p className="ml-6 mt-1.5 border-l-2 border-surface-200 pl-2 text-[11px] italic text-surface-500">
                                                "{approval.response_notes ?? approval.notes}"
                                            </p>
                                        )}

                                        {approval.responded_at && (
                                            <p className="ml-6 mt-0.5 text-[9px] text-surface-400">
                                                {new Date(approval.responded_at).toLocaleString('pt-BR', {
                                                    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
                                                })}
                                            </p>
                                        )}

                                        {canAct && (
                                            <div className="ml-6 mt-2 space-y-2">
                                                {showCommentFor === approval.id ? (
                                                    <div className="space-y-1.5">
                                                        <textarea
                                                            value={comment}
                                                            onChange={(event) => setComment(event.target.value)}
                                                            placeholder="Comentario (opcional)..."
                                                            aria-label="Comentario de aprovacao"
                                                            className="w-full resize-none rounded-lg border border-subtle bg-white px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                                            rows={2}
                                                        />
                                                        <div className="flex gap-1.5">
                                                            <Button
                                                                size="sm"
                                                                onClick={() => approveMut.mutate({ approverId: approval.approver_id, action: 'approve' })}
                                                                loading={approveMut.isPending}
                                                                icon={<CheckCircle2 className="h-3 w-3" />}
                                                            >
                                                                <span>Aprovar</span>
                                                            </Button>
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() => approveMut.mutate({ approverId: approval.approver_id, action: 'reject' })}
                                                                loading={approveMut.isPending}
                                                                icon={<XCircle className="h-3 w-3" />}
                                                            >
                                                                <span>Rejeitar</span>
                                                            </Button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <button
                                                        onClick={() => setShowCommentFor(approval.id)}
                                                        className="text-xs font-medium text-brand-600 hover:text-brand-700"
                                                    >
                                                        Responder &gt;
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )
                        })}
                </div>
            )}
        </div>
    )
}
