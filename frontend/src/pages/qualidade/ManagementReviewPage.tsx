import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { toast } from 'sonner'
import { Plus, Calendar, AlertTriangle, Clock } from 'lucide-react'
import { safeArray } from '@/lib/safe-array'

type UserOption = { id: number; name: string }
type ReviewAction = {
    id: number
    description: string
    due_date?: string | null
    status: string
    responsible?: { name: string } | null
}
type ManagementReview = {
    id: number
    meeting_date: string
    title: string
    participants?: string | null
    agenda?: string | null
    decisions?: string | null
    summary?: string | null
    actions?: ReviewAction[]
}

export default function ManagementReviewPage() {
    const qc = useQueryClient()
    const [showForm, setShowForm] = useState(false)
    const [detailId, setDetailId] = useState<number | null>(null)
    const [form, setForm] = useState({
        meeting_date: '',
        title: '',
        participants: '',
        agenda: '',
        decisions: '',
        summary: '',
    })
    const [actionForm, setActionForm] = useState({ description: '', responsible_id: '', due_date: '' })

    const { data: users = [] } = useQuery<UserOption[]>({
        queryKey: ['users-mgmt'],
        queryFn: () => api.get('/users', { params: { per_page: 200 } }).then(response => safeArray<UserOption>(unwrapData(response))),
    })

    const { data: reviews = [], isLoading, isError } = useQuery<ManagementReview[]>({
        queryKey: ['management-reviews'],
        queryFn: () => api.get('/management-reviews').then(response => safeArray<ManagementReview>(unwrapData(response))),
    })

    const { data: dashboard } = useQuery<Record<string, number>>({
        queryKey: ['management-reviews-dashboard'],
        queryFn: () => api.get('/management-reviews/dashboard').then(response => unwrapData<Record<string, number>>(response)),
    })

    const { data: detail } = useQuery<ManagementReview | null>({
        queryKey: ['management-review-detail', detailId],
        queryFn: () => api.get(`/management-reviews/${detailId}`).then(response => unwrapData<ManagementReview>(response)),
        enabled: !!detailId,
    })

    const createMut = useMutation({
        mutationFn: (data: typeof form) => api.post('/management-reviews', data),
        onSuccess: () => {
            toast.success('Revisao registrada')
            setShowForm(false)
            setForm({ meeting_date: '', title: '', participants: '', agenda: '', decisions: '', summary: '' })
            qc.invalidateQueries({ queryKey: ['management-reviews'] })
            qc.invalidateQueries({ queryKey: ['management-reviews-dashboard'] })
            broadcastQueryInvalidation(['management-reviews', 'management-reviews-dashboard'], 'Revisao pela Direcao')
        },
        onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao criar')),
    })

    const addActionMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: { description: string; responsible_id?: string; due_date?: string } }) =>
            api.post(`/management-reviews/${id}/actions`, data),
        onSuccess: () => {
            setActionForm({ description: '', responsible_id: '', due_date: '' })
            qc.invalidateQueries({ queryKey: ['management-review-detail', detailId] })
            qc.invalidateQueries({ queryKey: ['management-reviews-dashboard'] })
            broadcastQueryInvalidation(['management-review-detail', 'management-reviews-dashboard'], 'Revisao pela Direcao')
        },
        onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao adicionar acao')),
    })

    const updateActionMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Record<string, string> }) => api.put(`/management-reviews/actions/${id}`, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['management-review-detail', detailId] })
            qc.invalidateQueries({ queryKey: ['management-reviews-dashboard'] })
            broadcastQueryInvalidation(['management-review-detail', 'management-reviews-dashboard'], 'Revisao pela Direcao')
        },
        onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao atualizar')),
    })

    return (
        <div className="space-y-6">
            <PageHeader title="Revisao pela direcao" subtitle="Registro de reunioes de analise critica e acoes decorrentes">
                <Dialog open={showForm} onOpenChange={setShowForm}>
                    <DialogTrigger asChild>
                        <Button><Plus className="h-4 w-4 mr-1" /> Nova Revisao</Button>
                    </DialogTrigger>
                    <DialogContent className="max-w-lg">
                        <DialogHeader><DialogTitle>Nova Revisao pela Direcao</DialogTitle></DialogHeader>
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium">Data da reuniao *</label>
                                    <Input type="date" value={form.meeting_date} onChange={e => setForm(p => ({ ...p, meeting_date: e.target.value }))} className="mt-1" />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Titulo *</label>
                                    <Input value={form.title} onChange={e => setForm(p => ({ ...p, title: e.target.value }))} placeholder="Ex: Revisao Q1/2026" className="mt-1" />
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium">Participantes</label>
                                <textarea className="w-full border rounded px-3 py-2 text-sm mt-1 min-h-[60px]" value={form.participants} onChange={e => setForm(p => ({ ...p, participants: e.target.value }))} placeholder="Nomes ou cargos" />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Pauta</label>
                                <textarea className="w-full border rounded px-3 py-2 text-sm mt-1 min-h-[80px]" value={form.agenda} onChange={e => setForm(p => ({ ...p, agenda: e.target.value }))} placeholder="Itens da pauta" />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Decisoes</label>
                                <textarea className="w-full border rounded px-3 py-2 text-sm mt-1 min-h-[80px]" value={form.decisions} onChange={e => setForm(p => ({ ...p, decisions: e.target.value }))} placeholder="Decisoes tomadas" />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Resumo</label>
                                <textarea className="w-full border rounded px-3 py-2 text-sm mt-1 min-h-[60px]" value={form.summary} onChange={e => setForm(p => ({ ...p, summary: e.target.value }))} />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" onClick={() => setShowForm(false)}>Cancelar</Button>
                                <Button onClick={() => createMut.mutate(form)} disabled={createMut.isPending || !form.meeting_date || !form.title}>
                                    {createMut.isPending ? 'Salvando...' : 'Registrar'}
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </PageHeader>

            {dashboard && (
                <div className="grid grid-cols-2 gap-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-3">
                                <div className="rounded-lg bg-amber-50 p-2"><Clock className="h-6 w-6 text-amber-600" /></div>
                                <div>
                                    <p className="text-2xl font-bold text-surface-900">{dashboard.pending_actions ?? 0}</p>
                                    <p className="text-xs text-muted-foreground">Acoes pendentes</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            <Card>
                <CardContent className="pt-6">
                    {isLoading ? <p className="text-muted-foreground">Carregando...</p> : isError ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <AlertTriangle className="h-10 w-10 text-red-400 mb-3" />
                            <p className="text-sm font-medium text-red-600">Erro ao carregar</p>
                        </div>
                    ) : !reviews.length ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <Calendar className="h-10 w-10 text-muted-foreground/50 mb-3" />
                            <p className="text-sm font-medium text-muted-foreground">Nenhuma revisao registrada</p>
                            <p className="text-xs text-muted-foreground/70 mt-1">Clique em "Nova Revisao" para registrar a primeira</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="p-3">Data</th>
                                        <th className="p-3">Titulo</th>
                                        <th className="p-3">Participantes</th>
                                        <th className="p-3">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reviews.map((review) => (
                                        <tr key={review.id} className="border-b hover:bg-muted/50">
                                            <td className="p-3">{new Date(review.meeting_date).toLocaleDateString('pt-BR')}</td>
                                            <td className="p-3 font-medium">{review.title}</td>
                                            <td className="p-3 max-w-[200px] truncate text-muted-foreground">{review.participants || '—'}</td>
                                            <td className="p-3">
                                                <Button size="sm" variant="outline" onClick={() => setDetailId(review.id)}>Ver detalhe</Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog open={!!detailId} onOpenChange={(open) => !open && setDetailId(null)}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader><DialogTitle>Detalhe da Revisao</DialogTitle></DialogHeader>
                    {!detail ? <p className="text-muted-foreground">Carregando...</p> : (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-2 text-sm">
                                <div><span className="text-muted-foreground">Data:</span> {new Date(detail.meeting_date).toLocaleDateString('pt-BR')}</div>
                                <div><span className="text-muted-foreground">Titulo:</span> {detail.title}</div>
                            </div>
                            {detail.participants && <div><span className="text-muted-foreground text-sm">Participantes:</span><p className="text-sm mt-0.5 whitespace-pre-wrap">{detail.participants}</p></div>}
                            {detail.agenda && <div><span className="text-muted-foreground text-sm">Pauta:</span><p className="text-sm mt-0.5 whitespace-pre-wrap">{detail.agenda}</p></div>}
                            {detail.decisions && <div><span className="text-muted-foreground text-sm">Decisoes:</span><p className="text-sm mt-0.5 whitespace-pre-wrap">{detail.decisions}</p></div>}
                            {detail.summary && <div><span className="text-muted-foreground text-sm">Resumo:</span><p className="text-sm mt-0.5 whitespace-pre-wrap">{detail.summary}</p></div>}
                            <div>
                                <h4 className="text-sm font-semibold mb-2">Acoes</h4>
                                <div className="space-y-2 mb-4">
                                    {(detail.actions || []).map((action) => (
                                        <div key={action.id} className="flex items-center justify-between gap-2 border rounded p-2 bg-muted/30">
                                            <span className="text-sm flex-1">{action.description}</span>
                                            <div className="flex items-center gap-2">
                                                {action.responsible && <Badge variant="outline">{action.responsible.name}</Badge>}
                                                {action.due_date && <span className="text-xs text-muted-foreground">{new Date(action.due_date).toLocaleDateString('pt-BR')}</span>}
                                                <select className="border rounded px-2 py-0.5 text-xs" value={action.status} onChange={e => updateActionMut.mutate({ id: action.id, data: { status: e.target.value } })}>
                                                    <option value="pending">Pendente</option>
                                                    <option value="in_progress">Em andamento</option>
                                                    <option value="completed">Concluida</option>
                                                </select>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="flex gap-2 flex-wrap">
                                    <Input className="flex-1 min-w-[180px]" placeholder="Nova acao" value={actionForm.description} onChange={e => setActionForm(p => ({ ...p, description: e.target.value }))} />
                                    <select className="border rounded px-2 py-2 text-sm w-[140px]" value={actionForm.responsible_id} onChange={e => setActionForm(p => ({ ...p, responsible_id: e.target.value }))}>
                                        <option value="">Responsavel</option>
                                        {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                                    </select>
                                    <Input type="date" className="w-[130px]" value={actionForm.due_date} onChange={e => setActionForm(p => ({ ...p, due_date: e.target.value }))} />
                                    <Button
                                        size="sm"
                                        onClick={() => detailId && actionForm.description && addActionMut.mutate({
                                            id: detailId,
                                            data: {
                                                description: actionForm.description,
                                                responsible_id: actionForm.responsible_id || undefined,
                                                due_date: actionForm.due_date || undefined,
                                            },
                                        })}
                                        disabled={!actionForm.description || addActionMut.isPending}
                                    >
                                        Adicionar
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    )
}
