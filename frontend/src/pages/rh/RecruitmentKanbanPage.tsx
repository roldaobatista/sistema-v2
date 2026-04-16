import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    closestCorners,
    type DragEndEvent,
    type DragStartEvent,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core'
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { ArrowLeft, GripVertical, Loader2, Pencil, Plus, Trash2 } from 'lucide-react'
import api from '@/lib/api'
import { type Candidate, type JobPosting } from '@/hooks/useRecruitment'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { toast } from 'sonner'

type CandidateFormData = {
    name: string
    email: string
    phone: string
    stage: Candidate['stage']
    notes: string
    rating: string
    rejected_reason: string
}

const STAGES: Array<{ id: Candidate['stage']; label: string; color: string }> = [
    { id: 'applied', label: 'Aplicado', color: 'bg-surface-100 border-surface-200' },
    { id: 'screening', label: 'Triagem', color: 'bg-blue-50 border-blue-200' },
    { id: 'interview', label: 'Entrevista', color: 'bg-emerald-50 border-emerald-200' },
    { id: 'technical_test', label: 'Teste Tecnico', color: 'bg-teal-50 border-teal-200' },
    { id: 'offer', label: 'Proposta', color: 'bg-amber-50 border-amber-200' },
    { id: 'hired', label: 'Contratado', color: 'bg-emerald-50 border-emerald-200' },
    { id: 'rejected', label: 'Rejeitado', color: 'bg-red-50 border-red-200' },
]

const emptyCandidateForm: CandidateFormData = {
    name: '',
    email: '',
    phone: '',
    stage: 'applied',
    notes: '',
    rating: '',
    rejected_reason: '',
}

function getErrorMessage(error: unknown, fallback: string): string {
    const apiError = error as { response?: { data?: { message?: string } } }
    return apiError?.response?.data?.message ?? fallback
}

function idsMatch(a: unknown, b: unknown): boolean {
    return String(a) === String(b)
}

function mapCandidateToForm(candidate: Candidate): CandidateFormData {
    return {
        name: candidate.name ?? '',
        email: candidate.email ?? '',
        phone: candidate.phone ?? '',
        stage: candidate.stage ?? 'applied',
        notes: candidate.notes ?? '',
        rating: candidate.rating ? String(candidate.rating) : '',
        rejected_reason: candidate.rejected_reason ?? '',
    }
}

function DroppableColumn({ stageId, children }: { stageId: Candidate['stage']; children: React.ReactNode }) {
    const { setNodeRef, isOver } = useDroppable({ id: stageId })

    return (
        <div
            ref={setNodeRef}
            className={`flex-1 overflow-y-auto p-2 space-y-2 min-h-[60px] transition-colors rounded-b-lg ${
                isOver ? 'bg-brand-50/50 ring-2 ring-brand-200 ring-inset' : ''
            }`}
        >
            {children}
        </div>
    )
}

function DraggableCard({
    candidate,
    onSelectChange,
    onEdit,
    onDelete,
}: {
    candidate: Candidate
    onSelectChange: (id: string, stage: Candidate['stage']) => void
    onEdit: (candidate: Candidate) => void
    onDelete: (candidate: Candidate) => void
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: String(candidate.id),
        data: { stage: candidate.stage },
    })

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
    }

    return (
        <Card ref={setNodeRef} style={style} className="cursor-grab transition-all bg-surface-0 active:cursor-grabbing">
            <CardContent className="p-3 space-y-2">
                <div className="flex items-start gap-2">
                    <div {...attributes} {...listeners} className="mt-0.5 cursor-grab text-surface-300 hover:text-surface-500">
                        <GripVertical className="h-4 w-4" />
                    </div>

                    <div className="flex-1 min-w-0 space-y-1">
                        <div className="font-medium">{candidate.name}</div>
                        <div className="text-xs text-surface-500 truncate">{candidate.email}</div>
                        {candidate.phone && <div className="text-xs text-surface-500">{candidate.phone}</div>}
                        {candidate.rating ? <Badge variant="outline">Nota {candidate.rating}</Badge> : null}
                    </div>

                    <div className="flex items-center gap-1" onClick={e => e.stopPropagation()} onPointerDown={e => e.stopPropagation()}>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-7 w-7"
                            onClick={() => onEdit(candidate)}
                            aria-label={`Editar candidato ${candidate.name}`}
                        >
                            <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-7 w-7 text-red-600 hover:text-red-700"
                            onClick={() => onDelete(candidate)}
                            aria-label={`Excluir candidato ${candidate.name}`}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div className="flex justify-end pt-1">
                    <select
                        className="text-xs border rounded p-1"
                        value={candidate.stage}
                        onChange={e => onSelectChange(String(candidate.id), e.target.value as Candidate['stage'])}
                        onClick={e => e.stopPropagation()}
                        onPointerDown={e => e.stopPropagation()}
                        aria-label="Alterar fase do candidato"
                    >
                        {STAGES.map(stage => (
                            <option key={stage.id} value={stage.id}>
                                {stage.label}
                            </option>
                        ))}
                    </select>
                </div>
            </CardContent>
        </Card>
    )
}

export default function RecruitmentKanbanPage() {
    const { id } = useParams()
    const navigate = useNavigate()
    const [job, setJob] = useState<JobPosting | null>(null)
    const [candidates, setCandidates] = useState<Candidate[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [activeCandidate, setActiveCandidate] = useState<Candidate | null>(null)
    const [isCandidateModalOpen, setIsCandidateModalOpen] = useState(false)
    const [isSavingCandidate, setIsSavingCandidate] = useState(false)
    const [editingCandidate, setEditingCandidate] = useState<Candidate | null>(null)
    const [candidateToDelete, setCandidateToDelete] = useState<Candidate | null>(null)
    const [isDeletingCandidate, setIsDeletingCandidate] = useState(false)
    const [formData, setFormData] = useState<CandidateFormData>(emptyCandidateForm)

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor)
    )

    const fetchJobDetails = useCallback(async () => {
        if (!id) {
            setJob(null)
            setCandidates([])
            setIsLoading(false)
            return
        }

        setIsLoading(true)

        try {
            const response = await api.get(`/hr/job-postings/${id}`)
            const payload = response.data?.data ?? response.data
            setJob(payload)
            setCandidates(Array.isArray(payload?.candidates) ? payload.candidates : [])
        } catch (error) {
            toast.error(getErrorMessage(error, 'Erro ao carregar vaga'))
        } finally {
            setIsLoading(false)
        }
    }, [id])

    useEffect(() => {
        fetchJobDetails()
    }, [fetchJobDetails])

    const closeCandidateModal = () => {
        setIsCandidateModalOpen(false)
        setEditingCandidate(null)
        setFormData(emptyCandidateForm)
    }

    const openCreateCandidate = () => {
        setEditingCandidate(null)
        setFormData(emptyCandidateForm)
        setIsCandidateModalOpen(true)
    }

    const openEditCandidate = (candidate: Candidate) => {
        setEditingCandidate(candidate)
        setFormData(mapCandidateToForm(candidate))
        setIsCandidateModalOpen(true)
    }

    const buildCandidatePayload = () => {
        const parsedRating = Number(formData.rating)
        const hasRating = formData.rating.trim() !== '' && !Number.isNaN(parsedRating)

        return {
            name: formData.name.trim(),
            email: formData.email.trim(),
            phone: formData.phone.trim() || null,
            stage: formData.stage,
            notes: formData.notes.trim() || null,
            rating: hasRating ? parsedRating : null,
            rejected_reason: formData.stage === 'rejected' ? formData.rejected_reason.trim() || null : null,
        }
    }

    const handleCandidateSubmit = async (event: React.FormEvent) => {
        event.preventDefault()

        if (!id) {
            toast.error('Vaga nao identificada')
            return
        }

        setIsSavingCandidate(true)

        try {
            const payload = buildCandidatePayload()

            if (editingCandidate) {
                await api.put(`/hr/candidates/${editingCandidate.id}`, payload)
                toast.success('Candidato atualizado com sucesso')
            } else {
                await api.post(`/hr/job-postings/${id}/candidates`, payload)
                toast.success('Candidato adicionado com sucesso')
            }

            closeCandidateModal()
            await fetchJobDetails()
        } catch (error) {
            toast.error(getErrorMessage(error, 'Erro ao salvar candidato'))
        } finally {
            setIsSavingCandidate(false)
        }
    }

    const handleDeleteCandidate = async (candidate: Candidate | null) => {
        if (!candidate) {
            return
        }

        setIsDeletingCandidate(true)

        try {
            await api.delete(`/hr/candidates/${candidate.id}`)
            setCandidates(prev => prev.filter(item => !idsMatch(item.id, candidate.id)))
            if (editingCandidate && idsMatch(editingCandidate.id, candidate.id)) {
                closeCandidateModal()
            }
            toast.success('Candidato excluido com sucesso')
            setCandidateToDelete(null)
        } catch (error) {
            toast.error(getErrorMessage(error, 'Erro ao excluir candidato'))
        } finally {
            setIsDeletingCandidate(false)
        }
    }

    const updateStage = useCallback(
        async (candidateId: string, newStage: Candidate['stage']) => {
            const previousCandidates = [...candidates]

            setCandidates(prev =>
                prev.map(candidate =>
                    idsMatch(candidate.id, candidateId) ? { ...candidate, stage: newStage } : candidate
                )
            )

            try {
                await api.put(`/hr/candidates/${candidateId}`, { stage: newStage })
            } catch (error) {
                setCandidates(previousCandidates)
                toast.error(getErrorMessage(error, 'Erro ao atualizar fase'))
            }
        },
        [candidates]
    )

    const handleDragStart = (event: DragStartEvent) => {
        const candidate = candidates.find(item => idsMatch(item.id, event.active.id))
        setActiveCandidate(candidate ?? null)
    }

    const handleDragEnd = (event: DragEndEvent) => {
        setActiveCandidate(null)
        const { active, over } = event

        if (!over) {
            return
        }

        const candidateId = String(active.id)
        const overId = String(over.id)

        const targetStage = STAGES.find(stage => stage.id === overId)
        if (targetStage) {
            const sourceCandidate = candidates.find(candidate => idsMatch(candidate.id, candidateId))
            if (sourceCandidate && sourceCandidate.stage !== targetStage.id) {
                updateStage(candidateId, targetStage.id)
            }
            return
        }

        const targetCandidate = candidates.find(candidate => idsMatch(candidate.id, overId))
        if (!targetCandidate) {
            return
        }

        const sourceCandidate = candidates.find(candidate => idsMatch(candidate.id, candidateId))
        if (sourceCandidate && sourceCandidate.stage !== targetCandidate.stage) {
            updateStage(candidateId, targetCandidate.stage)
        }
    }

    if (isLoading) {
        return <div className="p-8 text-center">Carregando...</div>
    }

    if (!job) {
        return <div className="p-8 text-center">Vaga nao encontrada.</div>
    }

    return (
        <div className="flex flex-col h-[calc(100vh-4rem)]">
            <div className="flex items-center justify-between p-4 border-b bg-surface-0">
                <div className="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => navigate('/rh/recrutamento')}
                        aria-label="Voltar ao recrutamento"
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Button>

                    <div>
                        <h1 className="text-xl font-bold flex items-center gap-2">
                            {job.title}
                            <Badge variant="outline">{job.status}</Badge>
                        </h1>
                        <p className="text-sm text-surface-500">
                            {job.department?.name ?? 'Sem departamento'} - {candidates.length} candidatos
                        </p>
                    </div>
                </div>

                <Button onClick={openCreateCandidate}>
                    <Plus className="mr-2 h-4 w-4" />
                    Adicionar candidato
                </Button>
            </div>

            <DndContext
                sensors={sensors}
                collisionDetection={closestCorners}
                onDragStart={handleDragStart}
                onDragEnd={handleDragEnd}
            >
                <div className="flex-1 overflow-x-auto overflow-y-hidden p-4">
                    <div className="flex h-full gap-4 min-w-[1200px]">
                        {STAGES.map(stage => {
                            const stageCandidates = candidates.filter(candidate => candidate.stage === stage.id)

                            return (
                                <div
                                    key={stage.id}
                                    className={`flex-1 min-w-[200px] flex flex-col rounded-lg border ${stage.color} bg-opacity-50`}
                                >
                                    <div className="p-3 font-semibold text-sm flex justify-between items-center border-b border-subtle">
                                        {stage.label}
                                        <Badge variant="secondary" className="bg-surface-0/50">
                                            {stageCandidates.length}
                                        </Badge>
                                    </div>

                                    <DroppableColumn stageId={stage.id}>
                                        <SortableContext
                                            items={stageCandidates.map(candidate => String(candidate.id))}
                                            strategy={verticalListSortingStrategy}
                                        >
                                            {stageCandidates.map(candidate => (
                                                <DraggableCard
                                                    key={candidate.id}
                                                    candidate={candidate}
                                                    onSelectChange={updateStage}
                                                    onEdit={openEditCandidate}
                                                    onDelete={setCandidateToDelete}
                                                />
                                            ))}
                                        </SortableContext>

                                        {stageCandidates.length === 0 ? (
                                            <div className="text-center text-xs text-surface-400 py-4">
                                                Arraste candidatos aqui
                                            </div>
                                        ) : null}
                                    </DroppableColumn>
                                </div>
                            )
                        })}
                    </div>
                </div>

                <DragOverlay>
                    {activeCandidate ? (
                        <Card className="shadow-xl rotate-2 bg-surface-0 w-[220px]">
                            <CardContent className="p-3 space-y-1">
                                <div className="font-medium">{activeCandidate.name}</div>
                                <div className="text-xs text-surface-500 truncate">{activeCandidate.email}</div>
                            </CardContent>
                        </Card>
                    ) : null}
                </DragOverlay>
            </DndContext>

            <Dialog open={isCandidateModalOpen} onOpenChange={open => (open ? setIsCandidateModalOpen(true) : closeCandidateModal())}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingCandidate ? 'Editar candidato' : 'Novo candidato'}</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCandidateSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="candidate-name">Nome completo</Label>
                            <Input
                                id="candidate-name"
                                value={formData.name}
                                onChange={e => setFormData(prev => ({ ...prev, name: e.target.value }))}
                                required
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="candidate-email">Email</Label>
                            <Input
                                id="candidate-email"
                                type="email"
                                value={formData.email}
                                onChange={e => setFormData(prev => ({ ...prev, email: e.target.value }))}
                                required
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="candidate-phone">Telefone</Label>
                                <Input
                                    id="candidate-phone"
                                    value={formData.phone}
                                    onChange={e => setFormData(prev => ({ ...prev, phone: e.target.value }))}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="candidate-rating">Nota (1 a 5)</Label>
                                <Input
                                    id="candidate-rating"
                                    type="number"
                                    min={1}
                                    max={5}
                                    value={formData.rating}
                                    onChange={e => setFormData(prev => ({ ...prev, rating: e.target.value }))}
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="candidate-stage">Fase</Label>
                            <select
                                id="candidate-stage"
                                className="w-full h-10 rounded-md border border-input bg-background px-3 text-sm"
                                value={formData.stage}
                                onChange={e => setFormData(prev => ({ ...prev, stage: e.target.value as Candidate['stage'] }))}
                            >
                                {STAGES.map(stage => (
                                    <option key={stage.id} value={stage.id}>
                                        {stage.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {formData.stage === 'rejected' ? (
                            <div className="space-y-2">
                                <Label htmlFor="candidate-rejected-reason">Motivo da rejeicao</Label>
                                <Textarea
                                    id="candidate-rejected-reason"
                                    value={formData.rejected_reason}
                                    onChange={e => setFormData(prev => ({ ...prev, rejected_reason: e.target.value }))}
                                    required
                                />
                            </div>
                        ) : null}

                        <div className="space-y-2">
                            <Label htmlFor="candidate-notes">Observacoes</Label>
                            <Textarea
                                id="candidate-notes"
                                value={formData.notes}
                                onChange={e => setFormData(prev => ({ ...prev, notes: e.target.value }))}
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeCandidateModal} disabled={isSavingCandidate}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={isSavingCandidate}>
                                {isSavingCandidate ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Salvando...
                                    </>
                                ) : (
                                    'Salvar'
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={Boolean(candidateToDelete)} onOpenChange={open => (!open ? setCandidateToDelete(null) : null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Excluir candidato?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta acao remove o candidato {candidateToDelete?.name ? `"${candidateToDelete.name}"` : ''} da vaga.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeletingCandidate}>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => handleDeleteCandidate(candidateToDelete)}
                            disabled={isDeletingCandidate}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {isDeletingCandidate ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Excluindo...
                                </>
                            ) : (
                                'Excluir'
                            )}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    )
}
