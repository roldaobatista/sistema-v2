import { useMemo, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { ClipboardList, Plus, CheckCircle2, Circle, Play, Users, Trash2, Pencil } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { PageHeader } from '@/components/ui/pageheader'
import { Progress } from '@/components/ui/progress'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { safeArray } from '@/lib/safe-array'
import { useAuthStore } from '@/stores/auth-store'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

const templateSchema = z.object({
    name: z.string().min(1, 'Nome é obrigatório'),
    type: z.string().min(1, 'Tipo é obrigatório'),
    tasks: z.string().min(1, 'Tarefas são obrigatórias')
})
type TemplateFormData = z.infer<typeof templateSchema>

const startSchema = z.object({
    user_id: z.string().min(1, 'Colaborador é obrigatório'),
    template_id: z.string().min(1, 'Template é obrigatório')
})
type StartFormData = z.infer<typeof startSchema>

interface TemplateTask {
    title: string
    description?: string | null
}

interface Template {
    id: number
    name: string
    type: string
    default_tasks?: TemplateTask[]
    is_active: boolean
}

interface Checklist {
    id: number
    user?: { name: string }
    template?: { name: string; type?: string }
    status: 'in_progress' | 'completed' | 'cancelled'
    started_at: string
    completed_at: string | null
    items?: ChecklistItem[]
}

interface ChecklistItem {
    id: number
    title: string
    description: string
    is_completed: boolean
    completed_at: string | null
    responsible?: { name: string }
}

interface _LookupItem {
    id: number
    name: string
    slug?: string
}

const statusColors: Record<string, string> = {
    in_progress: 'bg-blue-100 text-blue-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-surface-100 text-surface-500',
}

const statusLabels: Record<string, string> = {
    in_progress: 'Em Andamento',
    completed: 'Concluido',
    cancelled: 'Cancelado',
}

const TEMPLATE_TYPE_FALLBACK: Array<{ value: string; label: string }> = [
    { value: 'admission', label: 'Admissao' },
    { value: 'dismissal', label: 'Desligamento' },
]

const emptyTemplateForm = { name: '', type: 'admission', tasks: '' }

export default function OnboardingPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const canManage = hasRole('super_admin') || hasPermission('hr.onboarding.manage')

    const [tab, setTab] = useState<'checklists' | 'templates'>('checklists')
    const [showTemplateModal, setShowTemplateModal] = useState(false)
    const [showStartModal, setShowStartModal] = useState(false)
    const [editingTemplate, setEditingTemplate] = useState<Template | null>(null)

    const { register: registerTmpl, handleSubmit: handleSubmitTmpl, reset: resetTmpl, formState: { errors: errorsTmpl } } = useForm<TemplateFormData>({
        resolver: zodResolver(templateSchema),
        defaultValues: emptyTemplateForm
    })

    const { register: registerStart, handleSubmit: handleSubmitStart, reset: resetStart, formState: { errors: errorsStart } } = useForm<StartFormData>({
        resolver: zodResolver(startSchema),
        defaultValues: { user_id: '', template_id: '' }
    })
    const [deleteTemplateTarget, setDeleteTemplateTarget] = useState<Template | null>(null)
    const [deleteChecklistId, setDeleteChecklistId] = useState<number | null>(null)
    const [cancelChecklistId, setCancelChecklistId] = useState<number | null>(null)

    const { data: templatesRes } = useQuery({
        queryKey: ['onboarding-templates'],
        queryFn: () => api.get('/hr/onboarding/templates').then(response => safeArray<Template>(unwrapData(response))),
    })
    const templates: Template[] = templatesRes ?? []
    const { data: templateTypeItems = [] } = useQuery<_LookupItem[]>({
        queryKey: ['lookups', 'onboarding-template-types'],
        queryFn: async () => {
            const response = await api.get('/lookups/onboarding-template-types')
            return safeArray<_LookupItem>(unwrapData(response))
        },
        staleTime: 5 * 60_000,
    })

    const { data: checklistsRes, isLoading } = useQuery({
        queryKey: ['onboarding-checklists'],
        queryFn: () => api.get('/hr/onboarding/checklists').then(response => safeArray<Checklist>(unwrapData(response))),
    })
    const checklists: Checklist[] = checklistsRes ?? []

    const { data: usersRes } = useQuery({
        queryKey: ['hr-user-options-onboarding'],
        queryFn: () => api.get('/hr/users/options').then(response => safeArray<{ id: number; name: string }>(unwrapData(response))),
    })
    const users: { id: number; name: string }[] = usersRes ?? []
    const templateTypeOptions = useMemo(() => {
        const options = [...TEMPLATE_TYPE_FALLBACK]
        templateTypeItems.forEach((item) => {
            const value = item.slug ?? item.name
            if (!options.some((option) => option.value === value)) {
                options.push({ value, label: item.name })
            }
        })
        return options
    }, [templateTypeItems])
    const templateTypeLabelByValue = useMemo(() => {
        const labels: Record<string, string> = Object.fromEntries(TEMPLATE_TYPE_FALLBACK.map((option) => [option.value, option.label]))
        templateTypeItems.forEach((item) => {
            if (item.slug) {
                labels[item.slug] = item.name
            }
            labels[item.name] = item.name
        })
        return labels
    }, [templateTypeItems])

    const invalidateTemplates = () => {
        qc.invalidateQueries({ queryKey: ['onboarding-templates'] })
        broadcastQueryInvalidation(['onboarding-templates'], 'Onboarding')
    }

    const invalidateChecklists = () => {
        qc.invalidateQueries({ queryKey: ['onboarding-checklists'] })
        broadcastQueryInvalidation(['onboarding-checklists'], 'Onboarding')
    }

    const createTmplMut = useMutation({
        mutationFn: (data: { name: string; type: string; default_tasks: Array<{ title: string; description: null }> }) => api.post('/hr/onboarding/templates', data),
        onSuccess: () => {
            invalidateTemplates()
            setShowTemplateModal(false)
            setEditingTemplate(null)
            resetTmpl()
            toast.success('Template criado')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao criar template')),
    })

    const updateTmplMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: unknown }) => api.put(`/hr/onboarding/templates/${id}`, data),
        onSuccess: () => {
            invalidateTemplates()
            setShowTemplateModal(false)
            setEditingTemplate(null)
            resetTmpl()
            toast.success('Template atualizado')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao atualizar template')),
    })

    const deleteTmplMut = useMutation({
        mutationFn: (id: number) => api.delete(`/hr/onboarding/templates/${id}`),
        onSuccess: () => {
            invalidateTemplates()
            setDeleteTemplateTarget(null)
            toast.success('Template removido')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao remover template')),
    })

    const startMut = useMutation({
        mutationFn: (data: { user_id: number; template_id: number }) => api.post('/hr/onboarding/start', data),
        onSuccess: () => {
            invalidateChecklists()
            setShowStartModal(false)
            toast.success('Onboarding iniciado')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao iniciar onboarding')),
    })

    const completeMut = useMutation({
        mutationFn: ({ checklistId, itemId }: { checklistId: number; itemId: number }) => api.post(`/hr/onboarding/checklists/${checklistId}/items/${itemId}/complete`),
        onSuccess: () => {
            invalidateChecklists()
            toast.success('Tarefa concluída')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao concluir tarefa')),
    })

    const cancelChecklistMut = useMutation({
        mutationFn: (checklistId: number) => api.put(`/hr/onboarding/checklists/${checklistId}`, { status: 'cancelled' }),
        onSuccess: () => {
            invalidateChecklists()
            setCancelChecklistId(null)
            toast.success('Checklist cancelado')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao cancelar checklist')),
    })

    const deleteChecklistMut = useMutation({
        mutationFn: (checklistId: number) => api.delete(`/hr/onboarding/checklists/${checklistId}`),
        onSuccess: () => {
            invalidateChecklists()
            setDeleteChecklistId(null)
            toast.success('Checklist removido')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao remover checklist')),
    })

    const handleCreateTemplate = (data: TemplateFormData) => {
        const defaultTasks = data.tasks
            .split('\n')
            .map(t => t.trim())
            .filter(Boolean)
            .map(task => ({ title: task, description: null }))

        const payload = {
            name: data.name,
            type: data.type,
            default_tasks: defaultTasks,
        }

        if (editingTemplate) {
            updateTmplMut.mutate({ id: editingTemplate.id, data: payload })
            return
        }

        createTmplMut.mutate(payload)
    }

    const openEditTemplate = (template: Template) => {
        setEditingTemplate(template)
        resetTmpl({
            name: template.name,
            type: template.type,
            tasks: (template.default_tasks ?? []).map(task => task.title).join('\n'),
        })
        setShowTemplateModal(true)
    }

    const openCreateTemplate = () => {
        setEditingTemplate(null)
        resetTmpl(emptyTemplateForm)
        setShowTemplateModal(true)
    }

    const handleStart = (data: StartFormData) => {
        startMut.mutate({ user_id: Number(data.user_id), template_id: Number(data.template_id) })
    }

    const fmtDate = (d: string | null) => d ? new Date(d).toLocaleDateString('pt-BR') : 'â€”'

    const getTemplateTasks = (template: Template): string[] => {
        return (template.default_tasks ?? []).map(task => task.title).filter(Boolean)
    }

    return (
        <div className="space-y-5">
            <PageHeader title="Onboarding / Offboarding" subtitle="Checklists de admissão e desligamento" />

            <div className="flex items-center justify-between gap-3">
                <div className="flex gap-1 rounded-lg border border-default bg-surface-50 p-0.5">
                    {(['checklists', 'templates'] as const).map(t => (
                        <button
                            key={t}
                            onClick={() => setTab(t)}
                            className={cn(
                                'rounded-md px-4 py-1.5 text-xs font-medium transition-all',
                                tab === t ? 'bg-surface-0 text-brand-700 shadow-sm' : 'text-surface-500 hover:text-surface-700'
                            )}
                        >
                            {t === 'checklists' ? 'Checklists Ativos' : 'Templates'}
                        </button>
                    ))}
                </div>
                {canManage && (
                    <div className="flex gap-2">
                        {tab === 'templates' && (
                            <Button variant="outline" onClick={openCreateTemplate} icon={<Plus className="h-4 w-4" />}>
                                Novo Template
                            </Button>
                        )}
                        {tab === 'checklists' && templates.length > 0 && (
                            <Button
                                onClick={() => {
                                    resetStart()
                                    setShowStartModal(true)
                                }}
                                icon={<Play className="h-4 w-4" />}
                            >
                                Iniciar Onboarding
                            </Button>
                        )}
                    </div>
                )}
            </div>

            {tab === 'templates' && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {templates.length === 0 && (
                        <div className="col-span-full rounded-xl border border-dashed border-default bg-surface-50 py-12 text-center">
                            <ClipboardList className="mx-auto h-8 w-8 text-surface-300" />
                            <p className="mt-2 text-sm text-surface-400">Nenhum template criado</p>
                            {canManage && (
                                <Button variant="outline" size="sm" className="mt-3" onClick={openCreateTemplate}>
                                    Criar Template
                                </Button>
                            )}
                        </div>
                    )}

                    {(templates || []).map(template => {
                        const tasks = getTemplateTasks(template)

                        return (
                            <div key={template.id} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <h3 className="font-semibold text-surface-900">{template.name}</h3>
                                        <span
                                            className={cn(
                                                'mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                template.type === 'admission' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'
                                            )}
                                        >
                                            {templateTypeLabelByValue[template.type] ?? template.type}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-bold text-surface-500">{tasks.length} tarefas</span>
                                        {canManage && (
                                            <div className="flex gap-1">
                                                <button
                                                    title="Editar template"
                                                    aria-label={`Editar template ${template.name}`}
                                                    onClick={() => openEditTemplate(template)}
                                                    className="rounded-lg p-1.5 text-surface-400 hover:bg-surface-100 hover:text-surface-600"
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </button>
                                                <button
                                                    title="Excluir template"
                                                    aria-label={`Excluir template ${template.name}`}
                                                    onClick={() => setDeleteTemplateTarget(template)}
                                                    className="rounded-lg p-1.5 text-surface-400 hover:bg-red-50 hover:text-red-600"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <ul className="mt-3 space-y-1.5">
                                    {tasks.slice(0, 5).map((task, index) => (
                                        <li key={`${template.id}-${index}`} className="flex items-center gap-2 text-xs text-surface-500">
                                            <Circle className="h-3 w-3 shrink-0 text-surface-300" /> {task}
                                        </li>
                                    ))}
                                    {tasks.length > 5 && (
                                        <li className="pl-5 text-xs text-surface-400">+{tasks.length - 5} mais</li>
                                    )}
                                </ul>
                            </div>
                        )
                    })}
                </div>
            )}

            {tab === 'checklists' && (
                <div className="space-y-4">
                    {isLoading && <p className="text-sm text-surface-400">Carregando...</p>}
                    {!isLoading && checklists.length === 0 && (
                        <div className="rounded-xl border border-dashed border-default bg-surface-50 py-12 text-center">
                            <Users className="mx-auto h-8 w-8 text-surface-300" />
                            <p className="mt-2 text-sm text-surface-400">Nenhum onboarding em andamento</p>
                        </div>
                    )}

                    {(checklists || []).map(checklist => {
                        const items = checklist.items ?? []
                        const completed = items.filter(i => i.is_completed).length
                        const total = items.length
                        const pct = total > 0 ? Math.round((completed / total) * 100) : 0

                        return (
                            <div key={checklist.id} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                                <div className="mb-3 flex items-center justify-between">
                                    <div>
                                        <h3 className="font-semibold text-surface-900">{checklist.user?.name ?? 'â€”'}</h3>
                                        <p className="text-xs text-surface-500">
                                            {checklist.template?.name} Â· Início: {fmtDate(checklist.started_at)}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm font-bold text-surface-700">{pct}%</span>
                                        <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-medium', statusColors[checklist.status])}>
                                            {statusLabels[checklist.status]}
                                        </span>
                                        {canManage && (
                                            <div className="flex gap-1">
                                                {checklist.status === 'in_progress' && (
                                                    <button
                                                        title="Cancelar checklist"
                                                        aria-label="Cancelar checklist"
                                                        onClick={() => setCancelChecklistId(checklist.id)}
                                                        className="rounded-lg p-1.5 text-surface-400 hover:bg-amber-50 hover:text-amber-600"
                                                    >
                                                        <Circle className="h-3.5 w-3.5" />
                                                    </button>
                                                )}
                                                <button
                                                    title="Excluir checklist"
                                                    aria-label="Excluir checklist"
                                                    onClick={() => setDeleteChecklistId(checklist.id)}
                                                    className="rounded-lg p-1.5 text-surface-400 hover:bg-red-50 hover:text-red-600"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <Progress value={pct} className="mb-4 h-2" indicatorClassName={pct === 100 ? 'bg-emerald-500' : 'bg-brand-500'} />
                                <ul className="space-y-2">
                                    {(items || []).map(item => (
                                        <li key={item.id} className="flex items-center justify-between rounded-lg border border-subtle p-3">
                                            <div className="flex items-center gap-3">
                                                {item.is_completed ? (
                                                    <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-500" />
                                                ) : (
                                                    <Circle className="h-5 w-5 shrink-0 text-surface-300" />
                                                )}
                                                <div>
                                                    <p className={cn('text-sm', item.is_completed && 'line-through text-surface-400')}>
                                                        {item.title}
                                                    </p>
                                                    {item.responsible && (
                                                        <p className="text-xs text-surface-400">Resp: {item.responsible.name}</p>
                                                    )}
                                                </div>
                                            </div>
                                            {!item.is_completed && canManage && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => completeMut.mutate({ checklistId: checklist.id, itemId: item.id })}
                                                    loading={completeMut.isPending}
                                                >
                                                    Concluir
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )
                    })}
                </div>
            )}

            <Modal
                open={showTemplateModal && canManage}
                onOpenChange={setShowTemplateModal}
                title={editingTemplate ? 'Editar Template' : 'Novo Template'}
                size="md"
            >
                <form onSubmit={handleSubmitTmpl(handleCreateTemplate)} className="space-y-4">
                    <div>
                        <Input
                            label="Nome *"
                            {...registerTmpl('name')}
                        />
                        {errorsTmpl.name && <p className="mt-1 text-xs text-red-500">{errorsTmpl.name.message}</p>}
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Tipo *</label>
                        <select
                            aria-label="Tipo de template"
                            {...registerTmpl('type')}
                            className={cn(
                                "w-full rounded-lg border bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15",
                                errorsTmpl.type ? "border-red-500" : "border-default"
                            )}
                        >
                            {templateTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                        {errorsTmpl.type && <p className="mt-1 text-xs text-red-500">{errorsTmpl.type.message}</p>}
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Tarefas * (uma por linha)</label>
                        <textarea
                            {...registerTmpl('tasks')}
                            rows={6}
                            placeholder="Coletar documentos&#10;Criar conta corporativa&#10;Configurar equipamento&#10;..."
                            className={cn(
                                "w-full rounded-lg border bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15",
                                errorsTmpl.tasks ? "border-red-500" : "border-default"
                            )}
                        />
                        {errorsTmpl.tasks && <p className="mt-1 text-xs text-red-500">{errorsTmpl.tasks.message}</p>}
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" type="button" onClick={() => setShowTemplateModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={createTmplMut.isPending || updateTmplMut.isPending}>
                            {editingTemplate ? 'Salvar' : 'Criar Template'}
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={showStartModal && canManage} onOpenChange={setShowStartModal} title="Iniciar Onboarding" size="sm">
                <form onSubmit={handleSubmitStart(handleStart)} className="space-y-4">
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Colaborador *</label>
                        <select
                            aria-label="Selecionar colaborador"
                            {...registerStart('user_id')}
                            className={cn(
                                "w-full rounded-lg border bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15",
                                errorsStart.user_id ? "border-red-500" : "border-default"
                            )}
                        >
                            <option value="">- Selecionar -</option>
                            {(users || []).map(user => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                        {errorsStart.user_id && <p className="mt-1 text-xs text-red-500">{errorsStart.user_id.message}</p>}
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Template *</label>
                        <select
                            aria-label="Selecionar template"
                            {...registerStart('template_id')}
                            className={cn(
                                "w-full rounded-lg border bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15",
                                errorsStart.template_id ? "border-red-500" : "border-default"
                            )}
                        >
                            <option value="">- Selecionar -</option>
                            {(templates || []).map(template => (
                                <option key={template.id} value={template.id}>
                                    {template.name} ({templateTypeLabelByValue[template.type] ?? template.type})
                                </option>
                            ))}
                        </select>
                        {errorsStart.template_id && <p className="mt-1 text-xs text-red-500">{errorsStart.template_id.message}</p>}
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" type="button" onClick={() => setShowStartModal(false)}>Cancelar</Button>
                        <Button type="submit" loading={startMut.isPending}>Iniciar</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!deleteTemplateTarget} onOpenChange={() => setDeleteTemplateTarget(null)} title="Excluir Template" size="sm">
                <p className="text-sm text-surface-600">
                    Tem certeza que deseja excluir o template <strong>{deleteTemplateTarget?.name}</strong>?
                </p>
                <div className="flex justify-end gap-2 pt-4">
                    <Button variant="outline" onClick={() => setDeleteTemplateTarget(null)}>Cancelar</Button>
                    <Button
                        className="bg-red-600 hover:bg-red-700"
                        onClick={() => deleteTemplateTarget && deleteTmplMut.mutate(deleteTemplateTarget.id)}
                        loading={deleteTmplMut.isPending}
                    >
                        Excluir
                    </Button>
                </div>
            </Modal>

            <Modal open={cancelChecklistId !== null} onOpenChange={() => setCancelChecklistId(null)} title="Cancelar Checklist" size="sm">
                <p className="text-sm text-surface-600">Deseja cancelar este checklist de onboarding?</p>
                <div className="flex justify-end gap-2 pt-4">
                    <Button variant="outline" onClick={() => setCancelChecklistId(null)}>Voltar</Button>
                    <Button
                        className="bg-amber-600 hover:bg-amber-700"
                        onClick={() => cancelChecklistId !== null && cancelChecklistMut.mutate(cancelChecklistId)}
                        loading={cancelChecklistMut.isPending}
                    >
                        Cancelar Checklist
                    </Button>
                </div>
            </Modal>

            <Modal open={deleteChecklistId !== null} onOpenChange={() => setDeleteChecklistId(null)} title="Excluir Checklist" size="sm">
                <p className="text-sm text-surface-600">Deseja remover este checklist e todos os seus itens?</p>
                <div className="flex justify-end gap-2 pt-4">
                    <Button variant="outline" onClick={() => setDeleteChecklistId(null)}>Voltar</Button>
                    <Button
                        className="bg-red-600 hover:bg-red-700"
                        onClick={() => deleteChecklistId !== null && deleteChecklistMut.mutate(deleteChecklistId)}
                        loading={deleteChecklistMut.isPending}
                    >
                        Excluir
                    </Button>
                </div>
            </Modal>
        </div>
    )
}
