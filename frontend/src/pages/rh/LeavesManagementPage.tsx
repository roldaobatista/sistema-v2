import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Check, X, FileText } from 'lucide-react'
import { getApiErrorMessage } from '@/lib/api'
import { hrApi } from '@/lib/hr-api'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/ui/modal'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select } from '@/components/ui/select'
import { cn } from '@/lib/utils'
import { safeArray } from '@/lib/safe-array'
import { useAuthStore } from '@/stores/auth-store'
import type { LeaveRequest as HrLeaveRequest } from '@/types/hr'

type LeaveManagementRequest = HrLeaveRequest & {
    status: HrLeaveRequest['status'] | 'cancelled'
}

type LeaveFormData = {
    type: LeaveManagementRequest['type']
    start_date: string
    end_date: string
    reason: string
    document: File | null
}

type LeaveActionTarget = {
    id: number
    action: 'approve' | 'reject'
}

type LeaveListPayload = {
    data?: LeaveManagementRequest[]
}

function extractLeaveRows(payload: LeaveListPayload | LeaveManagementRequest[] | undefined): LeaveManagementRequest[] {
    if (Array.isArray(payload)) {
        return payload
    }

    return safeArray<LeaveManagementRequest>(payload?.data)
}

const typeMap = {
    vacation: 'Férias',
    medical: 'Atestado Médico',
    personal: 'Licença Pessoal',
    maternity: 'Licença Maternidade/Paternidade',
    paternity: 'Licença Paternidade',
    bereavement: 'Licença Nojo (Óbito)',
    other: 'Outro'
}

const statusMap = {
    pending: { label: 'Pendente', color: 'bg-amber-100 text-amber-700' },
    approved: { label: 'Aprovado', color: 'bg-emerald-100 text-emerald-700' },
    rejected: { label: 'Rejeitado', color: 'bg-red-100 text-red-700' },
    cancelled: { label: 'Cancelado', color: 'bg-slate-100 text-slate-700' }
}

export default function LeavesManagementPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const canManage = hasRole('super_admin') || hasPermission('hr.leave.approve')
    const canCreate = hasRole('super_admin') || hasPermission('hr.leave.create')

    const [isModalOpen, setIsModalOpen] = useState(false)
    const [actionTarget, setActionTarget] = useState<LeaveActionTarget | null>(null)
    const [actionNotes, setActionNotes] = useState('')

    const [formData, setFormData] = useState<LeaveFormData>({
        type: 'medical',
        start_date: '',
        end_date: '',
        reason: '',
        document: null,
    })

    const { data: leavesPage, isLoading } = useQuery({
        queryKey: ['leaves'],
        queryFn: () => hrApi.leaves.list().then((response) => extractLeaveRows(response.data))
    })

    const leaves = leavesPage ?? []

    const saveMut = useMutation({
        mutationFn: async (data: LeaveFormData) => {
            const fd = new FormData()
            fd.append('type', data.type)
            fd.append('start_date', data.start_date)
            fd.append('end_date', data.end_date)
            if (data.reason) fd.append('reason', data.reason)
            if (data.document) fd.append('document', data.document)

            return hrApi.leaves.create(fd)
        },
        onSuccess: () => {
            toast.success('Afastamento/licença solicitado com sucesso!')
            qc.invalidateQueries({ queryKey: ['leaves'] })
            setIsModalOpen(false)
            setFormData({
                type: 'medical',
                start_date: '',
                end_date: '',
                reason: '',
                document: null,
            })
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Erro ao solicitar'))
        }
    })

    const actionMut = useMutation({
        mutationFn: ({ id, action, notes }: { id: number, action: 'approve' | 'reject', notes: string }) => {
            if (action === 'approve') {
                return hrApi.leaves.approve(id)
            }

            return hrApi.leaves.reject(id, { rejection_reason: notes, reason: notes })
        },
        onSuccess: () => {
            toast.success('Ação realizada com sucesso!')
            qc.invalidateQueries({ queryKey: ['leaves'] })
            setActionTarget(null)
            setActionNotes('')
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Erro ao realizar ação'))
        }
    })

    const handleCreate = () => {
        setFormData({
            type: 'medical',
            start_date: '',
            end_date: '',
            reason: '',
            document: null
        })
        setIsModalOpen(true)
    }

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            setFormData({ ...formData, document: e.target.files[0] })
        }
    }

    return (
        <div className="space-y-6 max-w-6xl mx-auto">
            <PageHeader
                title="Gestão de Licenças e Afastamentos"
                subtitle="Acompanhe as solicitações de férias, atestados e outras licenças."
                actions={
                    canCreate ? (
                        <Button onClick={handleCreate} icon={<Plus className="w-4 h-4" />}>
                            Nova Solicitação
                        </Button>
                    ) : null
                }
            />

            <div className="bg-white border text-left border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs">
                        <tr>
                            <th className="px-6 py-4">Colaborador</th>
                            <th className="px-6 py-4">Tipo</th>
                            <th className="px-6 py-4">Período</th>
                            <th className="px-6 py-4">Dias</th>
                            <th className="px-6 py-4 text-center">Status</th>
                            <th className="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {isLoading ? (
                            <tr><td colSpan={6} className="px-6 py-12 text-center text-slate-500">Carregando...</td></tr>
                        ) : leaves.length === 0 ? (
                            <tr><td colSpan={6} className="px-6 py-12 text-center text-slate-500">Nenhuma solicitação encontrada.</td></tr>
                        ) : (
                            leaves.map(leave => (
                                <tr key={leave.id} className="hover:bg-slate-50/50">
                                    <td className="px-6 py-4 font-medium text-slate-700">
                                        {leave.user?.name || 'Desconhecido'}
                                    </td>
                                    <td className="px-6 py-4 text-slate-600">
                                        <div className="flex items-center gap-2">
                                            {leave.document_path && (
                                                <a href={`/api/v1/hr/leaves/${leave.id}/document`} target="_blank" rel="noreferrer" title="Baixar documento anexo" className="text-brand-500 hover:text-brand-600">
                                                    <FileText className="w-4 h-4" />
                                                </a>
                                            )}
                                            {typeMap[leave.type] || leave.type}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-slate-600">
                                        {new Date(leave.start_date).toLocaleDateString()} - {new Date(leave.end_date).toLocaleDateString()}
                                    </td>
                                    <td className="px-6 py-4 text-slate-600">
                                        {leave.days_count} dia(s)
                                    </td>
                                    <td className="px-6 py-4 text-center">
                                        <span className={cn(
                                            "px-2.5 py-1 rounded text-xs font-semibold uppercase tracking-wide",
                                            statusMap[leave.status]?.color || "bg-slate-100 text-slate-600"
                                        )}>
                                            {statusMap[leave.status]?.label || leave.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right flex justify-end gap-2">
                                        {canManage && leave.status === 'pending' && (
                                            <>
                                                <Button variant="ghost" size="sm" onClick={() => setActionTarget({ id: leave.id, action: 'approve' })} className="text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 h-8">
                                                    <Check className="w-4 h-4 mr-1" /> Aprovar
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => setActionTarget({ id: leave.id, action: 'reject' })} className="text-red-600 hover:text-red-700 hover:bg-red-50 h-8">
                                                    <X className="w-4 h-4 mr-1" /> Rejeitar
                                                </Button>
                                            </>
                                        )}
                                        {(!canManage || leave.status !== 'pending') && (
                                            <span className="text-slate-400 italic text-xs">Aberto</span>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <Modal
                open={isModalOpen}
                onOpenChange={setIsModalOpen}
                title="Nova Solicitação"
                className="max-w-md"
            >
                <div className="space-y-4 pt-4">
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-slate-700">Tipo de Afastamento</label>
                        <Select
                            value={formData.type}
                            onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                        >
                            <option value="medical">Atestado Médico</option>
                            <option value="vacation">Férias</option>
                            <option value="personal">Licença Pessoal</option>
                            <option value="maternity">Licença Maternidade/Paternidade</option>
                            <option value="paternity">Licença Paternidade</option>
                            <option value="bereavement">Licença Nojo (Óbito)</option>
                            <option value="other">Outro</option>
                        </Select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-slate-700">Data de Início</label>
                            <Input
                                type="date"
                                value={formData.start_date}
                                onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-slate-700">Data Final</label>
                            <Input
                                type="date"
                                value={formData.end_date}
                                onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-slate-700">Motivo (Opcional)</label>
                        <Textarea
                            rows={2}
                            value={formData.reason}
                            onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                            placeholder="Detalhes adicionais se houver..."
                        />
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium text-slate-700">Documento Comprobatório (Atestado)</label>
                        <input
                            type="file"
                            accept=".pdf,.png,.jpg,.jpeg"
                            onChange={handleFileChange}
                            className="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100"
                        />
                    </div>
                </div>

                <div className="flex justify-end gap-3 mt-8">
                    <Button variant="outline" onClick={() => setIsModalOpen(false)}>Cancelar</Button>
                    <Button onClick={() => saveMut.mutate(formData)} loading={saveMut.isPending} disabled={!formData.type || !formData.start_date || !formData.end_date}>
                        Solicitar
                    </Button>
                </div>
            </Modal>

            <Modal
                open={!!actionTarget}
                onOpenChange={() => setActionTarget(null)}
                title={actionTarget?.action === 'approve' ? 'Aprovar Solicitação' : 'Rejeitar Solicitação'}
                size="sm"
            >
                <div className="space-y-4 pt-4">
                    <p className="text-sm text-slate-600">
                        {actionTarget?.action === 'approve'
                            ? 'Você está prestes a aprovar este afastamento. O RH será notificado.'
                            : 'Você está prestes a rejeitar este afastamento. Por favor, forneça um motivo:'}
                    </p>

                    <Textarea
                        rows={3}
                        value={actionNotes}
                        onChange={(e) => setActionNotes(e.target.value)}
                        placeholder="Observações (opcional para aprovação, recomendado para rejeição)"
                    />
                </div>

                <div className="flex justify-end gap-3 mt-8">
                    <Button variant="outline" onClick={() => setActionTarget(null)}>Cancelar</Button>
                    <Button
                        onClick={() => actionTarget && actionMut.mutate({ id: actionTarget.id, action: actionTarget.action, notes: actionNotes })}
                        loading={actionMut.isPending}
                        className={actionTarget?.action === 'reject' ? 'bg-red-600 hover:bg-red-700 text-white' : ''}
                    >
                        Confirmar
                    </Button>
                </div>
            </Modal>
        </div>
    )
}
