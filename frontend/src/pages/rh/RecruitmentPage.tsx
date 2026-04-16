import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Search, Filter, User, Calendar, Pencil, Trash2 } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useRecruitment, JobPosting } from '@/hooks/useRecruitment'
import { toast } from 'sonner'
import { format } from 'date-fns'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { safeArray } from '@/lib/safe-array'

interface DepartmentOption {
    id: number
    name: string
}

interface PositionOption {
    id: number
    name: string
    department_id?: number
}

const emptyForm: Partial<JobPosting> = {
    title: '',
    description: '',
    status: 'open',
    salary_range_min: 0,
    salary_range_max: 0,
    requirements: '',
    department_id: undefined,
    position_id: undefined,
}

export default function RecruitmentPage() {
    const { jobs, isLoading, createJob, updateJob, deleteJob } = useRecruitment()
    const [isModalOpen, setIsModalOpen] = useState(false)
    const [editingJob, setEditingJob] = useState<JobPosting | null>(null)
    const [formData, setFormData] = useState<Partial<JobPosting>>(emptyForm)
    const [searchTerm, setSearchTerm] = useState('')

    const { data: departmentsRes } = useQuery({
        queryKey: ['hr-departments-options-recruitment'],
        queryFn: () => api.get('/hr/departments').then(response => safeArray<DepartmentOption>(unwrapData(response))),
    })
    const departments: DepartmentOption[] = departmentsRes ?? []

    const { data: positionsRes } = useQuery({
        queryKey: ['hr-positions-options-recruitment'],
        queryFn: () => api.get('/hr/positions').then(response => safeArray<PositionOption>(unwrapData(response))),
    })
    const positions: PositionOption[] = positionsRes ?? []

    const filteredJobs = (jobs || []).filter(job =>
        job.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (job.department?.name ?? '').toLowerCase().includes(searchTerm.toLowerCase())
    )

    const filteredPositions = (positions || []).filter(position => {
        if (!formData.department_id) return true
        return String(position.department_id) === String(formData.department_id)
    })

    const resetForm = () => {
        setEditingJob(null)
        setFormData(emptyForm)
    }

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        const payload: Partial<JobPosting> = {
            ...formData,
            department_id: formData.department_id ? String(formData.department_id) : undefined,
            position_id: formData.position_id ? String(formData.position_id) : undefined,
        }

        try {
            if (editingJob) {
                await updateJob.mutateAsync({ id: editingJob.id, data: payload })
                toast.success('Vaga atualizada!')
            } else {
                await createJob.mutateAsync(payload)
                toast.success('Vaga criada!')
            }
            setIsModalOpen(false)
            resetForm()
        } catch (err) {
            toast.error(getApiErrorMessage(err, 'Erro ao salvar vaga'))
        }
    }

    const handleDelete = async () => {
        if (!editingJob) return

        try {
            await deleteJob.mutateAsync(editingJob.id)
            toast.success('Vaga excluída!')
            setIsModalOpen(false)
            resetForm()
        } catch (err) {
            toast.error(getApiErrorMessage(err, 'Erro ao excluir vaga'))
        }
    }

    const openEdit = (job: JobPosting) => {
        setEditingJob(job)
        setFormData({
            ...job,
            department_id: job.department_id,
            position_id: job.position_id,
            salary_range_min: Number(job.salary_range_min ?? 0),
            salary_range_max: Number(job.salary_range_max ?? 0),
        })
        setIsModalOpen(true)
    }

    const openCreate = () => {
        resetForm()
        setIsModalOpen(true)
    }

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'open': return 'default'
            case 'closed': return 'secondary'
            case 'on_hold': return 'outline'
            default: return 'default'
        }
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Recrutamento</h1>
                    <p className="text-muted-foreground">Gerencie vagas e candidatos (ATS Lite).</p>
                </div>
                <Button onClick={openCreate}>
                    <Plus className="mr-2 h-4 w-4" /> Nova Vaga
                </Button>
            </div>

            <div className="flex items-center gap-2">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder="Buscar vagas..."
                        className="pl-8"
                        value={searchTerm}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearchTerm(e.target.value)}
                    />
                </div>
                <Button variant="outline" size="icon" aria-label="Filtrar vagas">
                    <Filter className="h-4 w-4" />
                </Button>
            </div>

            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                {isLoading ? (
                    <p>Carregando...</p>
                ) : filteredJobs?.length === 0 ? (
                    <p className="col-span-3 py-10 text-center text-muted-foreground">Nenhuma vaga encontrada.</p>
                ) : (
                    (filteredJobs || []).map((job) => (
                        <Card key={job.id} className="transition-shadow hover:shadow-md">
                            <CardHeader className="pb-3">
                                <div className="flex items-start justify-between">
                                    <Badge variant={getStatusVariant(job.status)} className="mb-2">
                                        {job.status === 'open' ? 'Aberta' : job.status === 'closed' ? 'Fechada' : 'Em Espera'}
                                    </Badge>
                                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(job)} aria-label="Editar vaga">
                                        <Pencil className="h-4 w-4" />
                                    </Button>
                                </div>
                                <CardTitle className="line-clamp-1">{job.title}</CardTitle>
                                <CardDescription>{job.department?.name || 'Sem departamento'}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <p className="min-h-[40px] line-clamp-2 text-sm text-muted-foreground">
                                        {job.description}
                                    </p>
                                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                        <div className="flex items-center gap-1">
                                            <User className="h-4 w-4" />
                                            <span>{job.candidates?.length || 0} candidatos</span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Calendar className="h-4 w-4" />
                                            <span>{job.opened_at ? format(new Date(job.opened_at), 'dd/MM/yyyy') : '-'}</span>
                                        </div>
                                    </div>
                                    <div className="pt-2">
                                        <Button variant="outline" className="w-full" onClick={() => window.location.href = `/rh/recrutamento/${job.id}`}>
                                            Ver Candidatos
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>

            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editingJob ? 'Editar Vaga' : 'Nova Vaga'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Título do Cargo</Label>
                                <Input value={formData.title} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFormData({ ...formData, title: e.target.value })} required />
                            </div>
                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select
                                    value={formData.status}
                                    onValueChange={(value: string) => setFormData({ ...formData, status: value as 'open' | 'on_hold' | 'closed' })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="open">Aberta</SelectItem>
                                        <SelectItem value="on_hold">Em Espera</SelectItem>
                                        <SelectItem value="closed">Fechada</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Departamento</Label>
                                <Select
                                    value={formData.department_id ? String(formData.department_id) : 'none'}
                                    onValueChange={(value: string) => setFormData({ ...formData, department_id: value === 'none' ? undefined : value, position_id: undefined })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecione" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sem departamento</SelectItem>
                                        {(departments || []).map(department => (
                                            <SelectItem key={department.id} value={String(department.id)}>{department.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Cargo</Label>
                                <Select
                                    value={formData.position_id ? String(formData.position_id) : 'none'}
                                    onValueChange={(value: string) => setFormData({ ...formData, position_id: value === 'none' ? undefined : value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecione" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sem cargo</SelectItem>
                                        {(filteredPositions || []).map(position => (
                                            <SelectItem key={position.id} value={String(position.id)}>{position.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Descrição</Label>
                            <Textarea
                                className="min-h-[100px]"
                                value={formData.description}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setFormData({ ...formData, description: e.target.value })}
                                required
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Requisitos</Label>
                            <Textarea
                                className="min-h-[100px]"
                                value={formData.requirements || ''}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setFormData({ ...formData, requirements: e.target.value })}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Salário Mínimo</Label>
                                <CurrencyInput value={Number(formData.salary_range_min) || 0} onChange={(value) => setFormData({ ...formData, salary_range_min: value })} />
                            </div>
                            <div className="space-y-2">
                                <Label>Salário Máximo</Label>
                                <CurrencyInput value={Number(formData.salary_range_max) || 0} onChange={(value) => setFormData({ ...formData, salary_range_max: value })} />
                            </div>
                        </div>

                        <DialogFooter>
                            {editingJob && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={handleDelete}
                                    disabled={deleteJob.isPending}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" /> Excluir
                                </Button>
                            )}
                            <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={createJob.isPending || updateJob.isPending}>Salvar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    )
}
