import { useMemo, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { BarChart3, CheckCircle2, FolderKanban, PauseCircle, PlayCircle, Plus, ReceiptText, TimerReset } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/pageheader'
import { Textarea } from '@/components/ui/textarea'
import { useAuthStore } from '@/stores/auth-store'
import {
    useCompleteProjectMilestone,
    useCreateProject,
    useCreateProjectMilestone,
    useCreateProjectResource,
    useCreateProjectTimeEntry,
    useInvoiceProjectMilestone,
    useProjectGantt,
    useProjectLifecycleAction,
    useProjectMilestones,
    useProjectResources,
    useProjects,
    useProjectsDashboard,
    useProjectTimeEntries,
} from '@/hooks/useProjects'
import {
    projectFormSchema,
    projectMilestoneFormSchema,
    projectResourceFormSchema,
    projectTimeEntryFormSchema,
    type ProjectFormValues,
    type ProjectMilestoneFormValues,
    type ProjectResourceFormValues,
    type ProjectTimeEntryFormValues,
} from '@/schemas/project'
import type { Project } from '@/types/projects'
import { formatCurrency, formatDate, getApiErrorMessage } from '@/lib/utils'

const statusLabels: Record<string, string> = {
    planning: 'Planejamento',
    active: 'Ativo',
    on_hold: 'Em espera',
    completed: 'Concluído',
    cancelled: 'Cancelado',
    pending: 'Pendente',
    invoiced: 'Faturado',
}

const priorityLabels: Record<string, string> = {
    low: 'Baixa',
    medium: 'Média',
    high: 'Alta',
    critical: 'Crítica',
}

const billingTypeLabels: Record<string, string> = {
    milestone: 'Por marco',
    hourly: 'Por hora',
    fixed_price: 'Preço fechado',
}

function parseDependencies(value: string): number[] | undefined {
    const dependencies = value
        .split(',')
        .map(item => item.trim())
        .filter(Boolean)
        .map(item => Number(item))
        .filter(item => Number.isInteger(item) && item > 0)

    return dependencies.length > 0 ? dependencies : undefined
}

export function ProjectsPage() {
    const [status, setStatus] = useState('')
    const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null)
    const [showCreateDialog, setShowCreateDialog] = useState(false)
    const { hasPermission } = useAuthStore()

    const canViewDashboard = hasPermission('projects.dashboard.view')
    const canCreateProject = hasPermission('projects.project.create')
    const canUpdateProject = hasPermission('projects.project.update')
    const canManageMilestones = hasPermission('projects.milestone.manage')
    const canCompleteMilestones = hasPermission('projects.milestone.complete')
    const canManageResources = hasPermission('projects.resource.manage')
    const canCreateTimeEntries = hasPermission('projects.time_entry.create')
    const canInvoiceMilestones = hasPermission('projects.invoice.generate')

    const filters = useMemo(() => ({
        status: status as '' | Project['status'],
        per_page: 20,
    }), [status])

    const projectsQuery = useProjects(filters)
    const dashboardQuery = useProjectsDashboard(canViewDashboard)
    const createProject = useCreateProject()
    const startProject = useProjectLifecycleAction('start')
    const pauseProject = useProjectLifecycleAction('pause')
    const resumeProject = useProjectLifecycleAction('resume')
    const completeProject = useProjectLifecycleAction('complete')
    const createMilestone = useCreateProjectMilestone()
    const completeMilestone = useCompleteProjectMilestone()
    const invoiceMilestone = useInvoiceProjectMilestone()
    const createResource = useCreateProjectResource()
    const createTimeEntry = useCreateProjectTimeEntry()

    const projects = projectsQuery.data?.data ?? []
    const selectedProject = projects.find(project => project.id === selectedProjectId) ?? projects[0] ?? null
    const effectiveProjectId = selectedProject?.id ?? null
    const ganttQuery = useProjectGantt(effectiveProjectId)
    const milestonesQuery = useProjectMilestones(effectiveProjectId)
    const resourcesQuery = useProjectResources(effectiveProjectId)
    const timeEntriesQuery = useProjectTimeEntries(effectiveProjectId)

    const projectForm = useForm<ProjectFormValues>({
        resolver: zodResolver(projectFormSchema),
        defaultValues: {
            customer_id: undefined,
            name: '',
            description: '',
            status: 'planning',
            priority: 'medium',
            start_date: new Date().toISOString().slice(0, 10),
            end_date: '',
            budget: undefined,
            billing_type: 'milestone',
            hourly_rate: undefined,
            crm_deal_id: undefined,
            manager_id: undefined,
            tags: '',
        },
    })

    const milestoneForm = useForm<ProjectMilestoneFormValues>({
        resolver: zodResolver(projectMilestoneFormSchema),
        defaultValues: {
            name: '',
            planned_start: '',
            planned_end: '',
            billing_value: undefined,
            weight: 10,
            order: 1,
            dependencies: '',
            deliverables: '',
        },
    })

    const resourceForm = useForm<ProjectResourceFormValues>({
        resolver: zodResolver(projectResourceFormSchema),
        defaultValues: {
            user_id: undefined,
            role: '',
            allocation_percent: 100,
            start_date: new Date().toISOString().slice(0, 10),
            end_date: '',
            hourly_rate: undefined,
            total_hours_planned: undefined,
        },
    })

    const timeEntryForm = useForm<ProjectTimeEntryFormValues>({
        resolver: zodResolver(projectTimeEntryFormSchema),
        defaultValues: {
            project_resource_id: undefined,
            milestone_id: undefined,
            work_order_id: undefined,
            date: new Date().toISOString().slice(0, 10),
            hours: 1,
            description: '',
            billable: true,
        },
    })

    async function handleCreateProject(values: ProjectFormValues) {
        try {
            await createProject.mutateAsync({
                customer_id: values.customer_id,
                name: values.name,
                description: values.description || undefined,
                status: values.status,
                priority: values.priority,
                start_date: values.start_date || undefined,
                end_date: values.end_date || undefined,
                budget: values.budget,
                billing_type: values.billing_type,
                hourly_rate: values.hourly_rate,
                crm_deal_id: values.crm_deal_id,
                manager_id: values.manager_id,
                tags: values.tags ? values.tags.split(',').map(tag => tag.trim()).filter(Boolean) : undefined,
            })
            toast.success('Projeto criado com sucesso.')
            setShowCreateDialog(false)
            projectForm.reset()
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Não foi possível criar o projeto.'))
        }
    }

    async function handleLifecycle(project: Project, action: 'start' | 'pause' | 'resume' | 'complete') {
        try {
            if (action === 'start') await startProject.mutateAsync(project.id)
            if (action === 'pause') await pauseProject.mutateAsync(project.id)
            if (action === 'resume') await resumeProject.mutateAsync(project.id)
            if (action === 'complete') await completeProject.mutateAsync(project.id)
            toast.success(`Projeto ${project.code} atualizado.`)
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Não foi possível alterar o status do projeto.'))
        }
    }

    async function handleCreateMilestone(values: ProjectMilestoneFormValues) {
        if (!effectiveProjectId) return

        try {
            await createMilestone.mutateAsync({
                projectId: effectiveProjectId,
                payload: {
                    name: values.name,
                    planned_start: values.planned_start || undefined,
                    planned_end: values.planned_end || undefined,
                    billing_value: values.billing_value,
                    weight: values.weight,
                    order: values.order,
                    dependencies: parseDependencies(values.dependencies),
                    deliverables: values.deliverables || undefined,
                },
            })
            toast.success('Marco criado com sucesso.')
            milestoneForm.reset({ name: '', planned_start: '', planned_end: '', billing_value: undefined, weight: 10, order: 1, dependencies: '', deliverables: '' })
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Não foi possível criar o marco.'))
        }
    }

    async function handleCreateResource(values: ProjectResourceFormValues) {
        if (!effectiveProjectId) return

        try {
            await createResource.mutateAsync({
                projectId: effectiveProjectId,
                payload: {
                    user_id: values.user_id,
                    role: values.role,
                    allocation_percent: values.allocation_percent,
                    start_date: values.start_date,
                    end_date: values.end_date,
                    hourly_rate: values.hourly_rate,
                    total_hours_planned: values.total_hours_planned,
                },
            })
            toast.success('Recurso alocado ao projeto.')
            resourceForm.reset({ user_id: undefined, role: '', allocation_percent: 100, start_date: new Date().toISOString().slice(0, 10), end_date: '', hourly_rate: undefined, total_hours_planned: undefined })
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Não foi possível alocar o recurso.'))
        }
    }

    async function handleCreateTimeEntry(values: ProjectTimeEntryFormValues) {
        if (!effectiveProjectId) return

        try {
            await createTimeEntry.mutateAsync({
                projectId: effectiveProjectId,
                payload: {
                    project_resource_id: values.project_resource_id,
                    milestone_id: values.milestone_id,
                    work_order_id: values.work_order_id,
                    date: values.date,
                    hours: values.hours,
                    description: values.description || undefined,
                    billable: values.billable,
                },
            })
            toast.success('Apontamento registrado.')
            timeEntryForm.reset({
                project_resource_id: undefined,
                milestone_id: undefined,
                work_order_id: undefined,
                date: new Date().toISOString().slice(0, 10),
                hours: 1,
                description: '',
                billable: true,
            })
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Não foi possível registrar o apontamento.'))
        }
    }

    async function handleCompleteMilestone(milestoneId: number) {
        if (!effectiveProjectId) return

        try {
            await completeMilestone.mutateAsync({ projectId: effectiveProjectId, milestoneId })
            toast.success('Marco concluído.')
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Não foi possível concluir o marco.'))
        }
    }

    async function handleInvoiceMilestone(milestoneId: number) {
        if (!effectiveProjectId) return

        try {
            await invoiceMilestone.mutateAsync({ projectId: effectiveProjectId, milestoneId })
            toast.success('Fatura gerada para o marco.')
        } catch (error) {
            toast.error(getApiErrorMessage(error, 'Não foi possível faturar o marco.'))
        }
    }

    const milestones = milestonesQuery.data?.data ?? []
    const resources = resourcesQuery.data?.data ?? []
    const timeEntries = timeEntriesQuery.data?.data ?? []
    const dashboard = dashboardQuery.data
    const gantt = ganttQuery.data

    return (
        <div className="space-y-6">
            <PageHeader
                title="Projetos"
                subtitle="Planeje entregas, acompanhe recursos, marcos e apontamentos faturáveis em um só fluxo."
                icon={<FolderKanban className="h-6 w-6" />}
                actions={[
                    { label: 'Dashboard', onClick: () => void dashboardQuery.refetch(), icon: <BarChart3 className="h-4 w-4" />, variant: 'outline', permission: canViewDashboard },
                    { label: 'Novo projeto', onClick: () => setShowCreateDialog(true), icon: <Plus className="h-4 w-4" />, permission: canCreateProject },
                ]}
            />

            {canViewDashboard && (
                <div className="grid gap-4 md:grid-cols-4">
                    <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Projetos</p><p className="mt-2 text-2xl font-bold">{dashboard?.total_projects ?? 0}</p></CardContent></Card>
                    <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Ativos</p><p className="mt-2 text-2xl font-bold">{dashboard?.active_projects ?? 0}</p></CardContent></Card>
                    <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Orçamento total</p><p className="mt-2 text-2xl font-bold">{formatCurrency(dashboard?.budget_total ?? 0)}</p></CardContent></Card>
                    <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Executado</p><p className="mt-2 text-2xl font-bold">{formatCurrency(dashboard?.spent_total ?? 0)}</p></CardContent></Card>
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-[1.25fr_1fr]">
                <Card>
                    <CardHeader className="space-y-4">
                        <div>
                            <CardTitle>Portfólio</CardTitle>
                            <p className="text-sm text-surface-500">Selecione um projeto para abrir o cockpit operacional.</p>
                        </div>
                        <div className="grid gap-3 md:grid-cols-[1fr_auto]">
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Status</label>
                                <select
                                    aria-label="Filtro por status do projeto"
                                    className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]"
                                    value={status}
                                    onChange={event => setStatus(event.target.value)}
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(statusLabels).filter(([key]) => ['planning', 'active', 'on_hold', 'completed', 'cancelled'].includes(key)).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-end">
                                <Button variant="outline" onClick={() => void projectsQuery.refetch()}>Atualizar</Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {projectsQuery.isLoading ? (
                            <div className="rounded-[var(--radius-lg)] border border-dashed border-surface-200 p-8 text-center text-sm text-surface-500">Carregando projetos...</div>
                        ) : projects.length === 0 ? (
                            <div className="rounded-[var(--radius-lg)] border border-dashed border-surface-200 p-8 text-center text-sm text-surface-500">Nenhum projeto encontrado para os filtros atuais.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead className="border-b border-surface-200 text-surface-500">
                                        <tr>
                                            <th className="px-3 py-3 font-medium">Projeto</th>
                                            <th className="px-3 py-3 font-medium">Cliente</th>
                                            <th className="px-3 py-3 font-medium">Prioridade</th>
                                            <th className="px-3 py-3 font-medium">Progresso</th>
                                            <th className="px-3 py-3 font-medium">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {projects.map(project => (
                                            <tr key={project.id} className={`border-b border-surface-100 last:border-0 ${selectedProject?.id === project.id ? 'bg-surface-50/80 dark:bg-white/[0.04]' : ''}`}>
                                                <td className="px-3 py-3">
                                                    <button type="button" className="text-left" onClick={() => setSelectedProjectId(project.id)} aria-label={`Selecionar projeto ${project.name}`}>
                                                        <div className="font-semibold text-surface-900 dark:text-white">{project.code}</div>
                                                        <div className="text-xs text-surface-500">{project.name}</div>
                                                        <div className="text-xs text-surface-500">{statusLabels[project.status] ?? project.status}</div>
                                                    </button>
                                                </td>
                                                <td className="px-3 py-3">{project.customer?.business_name || project.customer?.name || `#${project.customer_id}`}</td>
                                                <td className="px-3 py-3">{priorityLabels[project.priority] ?? project.priority}</td>
                                                <td className="px-3 py-3">{Number(project.progress_percent ?? 0).toFixed(0)}%</td>
                                                <td className="px-3 py-3">
                                                    <div className="flex flex-wrap gap-2">
                                                        {canUpdateProject && project.status === 'planning' && <Button size="sm" variant="outline" icon={<PlayCircle className="h-4 w-4" />} onClick={() => void handleLifecycle(project, 'start')}>Iniciar</Button>}
                                                        {canUpdateProject && project.status === 'active' && (
                                                            <>
                                                                <Button size="sm" variant="outline" icon={<PauseCircle className="h-4 w-4" />} onClick={() => void handleLifecycle(project, 'pause')}>Pausar</Button>
                                                                <Button size="sm" icon={<CheckCircle2 className="h-4 w-4" />} onClick={() => void handleLifecycle(project, 'complete')}>Concluir</Button>
                                                            </>
                                                        )}
                                                        {canUpdateProject && project.status === 'on_hold' && <Button size="sm" variant="outline" icon={<TimerReset className="h-4 w-4" />} onClick={() => void handleLifecycle(project, 'resume')}>Retomar</Button>}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Cockpit do projeto</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        {!selectedProject ? (
                            <p className="text-sm text-surface-500">Selecione um projeto para visualizar marcos, recursos e apontamentos.</p>
                        ) : (
                            <>
                                <div className="rounded-[var(--radius-lg)] border border-surface-100 p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="text-lg font-semibold text-surface-900 dark:text-white">{selectedProject.name}</p>
                                            <p className="text-sm text-surface-500">{billingTypeLabels[selectedProject.billing_type] ?? selectedProject.billing_type} • gerente {selectedProject.manager?.name || 'não informado'}</p>
                                        </div>
                                        <span className="rounded-full bg-prix-50 px-3 py-1 text-xs font-semibold text-prix-600 dark:bg-prix-500/15 dark:text-prix-200">{Number(selectedProject.progress_percent ?? 0).toFixed(0)}%</span>
                                    </div>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p className="text-xs uppercase tracking-[0.12em] text-surface-500">Prazo</p>
                                            <p className="mt-1 text-sm text-surface-900 dark:text-white">{formatDate(selectedProject.start_date)} até {formatDate(selectedProject.end_date)}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs uppercase tracking-[0.12em] text-surface-500">Financeiro</p>
                                            <p className="mt-1 text-sm text-surface-900 dark:text-white">{formatCurrency(selectedProject.spent ?? 0)} de {formatCurrency(selectedProject.budget ?? 0)}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-[var(--radius-lg)] border border-surface-100 p-4"><p className="text-xs uppercase tracking-[0.12em] text-surface-500">Marcos</p><p className="mt-2 text-2xl font-bold">{gantt?.milestones.length ?? milestones.length}</p></div>
                                    <div className="rounded-[var(--radius-lg)] border border-surface-100 p-4"><p className="text-xs uppercase tracking-[0.12em] text-surface-500">Recursos</p><p className="mt-2 text-2xl font-bold">{gantt?.resources.length ?? resources.length}</p></div>
                                    <div className="rounded-[var(--radius-lg)] border border-surface-100 p-4"><p className="text-xs uppercase tracking-[0.12em] text-surface-500">Horas lançadas</p><p className="mt-2 text-2xl font-bold">{(gantt?.time_entries ?? timeEntries).reduce((total, entry) => total + Number(entry.hours), 0).toFixed(2)}h</p></div>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            {selectedProject && (
                <div className="grid gap-6 xl:grid-cols-3">
                    <Card>
                        <CardHeader><CardTitle>Marcos</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            {canManageMilestones && (
                                <form className="grid gap-3" onSubmit={milestoneForm.handleSubmit(values => void handleCreateMilestone(values))}>
                                    <Input label="Marco" aria-label="Nome do marco" {...milestoneForm.register('name')} error={milestoneForm.formState.errors.name?.message} />
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Input type="date" label="Início planejado" aria-label="Início planejado" {...milestoneForm.register('planned_start')} />
                                        <Input type="date" label="Fim planejado" aria-label="Fim planejado" {...milestoneForm.register('planned_end')} error={milestoneForm.formState.errors.planned_end?.message} />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <Input type="number" step="0.01" label="Faturável" aria-label="Valor faturável do marco" {...milestoneForm.register('billing_value')} />
                                        <Input type="number" step="0.1" label="Peso" aria-label="Peso do marco" {...milestoneForm.register('weight')} />
                                        <Input type="number" label="Ordem" aria-label="Ordem do marco" {...milestoneForm.register('order')} error={milestoneForm.formState.errors.order?.message} />
                                    </div>
                                    <Input label="Dependências (IDs)" aria-label="Dependências do marco" placeholder="1,2,3" {...milestoneForm.register('dependencies')} />
                                    <Textarea label="Entregáveis" aria-label="Entregáveis do marco" {...milestoneForm.register('deliverables')} />
                                    <Button type="submit" loading={createMilestone.isPending}>Adicionar marco</Button>
                                </form>
                            )}

                            <div className="space-y-3">
                                {milestones.map(milestone => (
                                    <div key={milestone.id} className="rounded-[var(--radius-lg)] border border-surface-100 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-surface-900 dark:text-white">{milestone.name}</p>
                                                <p className="text-xs text-surface-500">Ordem {milestone.order} • {statusLabels[milestone.status] ?? milestone.status}</p>
                                            </div>
                                            <span className="text-sm font-semibold text-surface-900 dark:text-white">{formatCurrency(milestone.billing_value ?? 0)}</span>
                                        </div>
                                        <p className="mt-2 text-xs text-surface-500">Planejado: {formatDate(milestone.planned_start)} até {formatDate(milestone.planned_end)}</p>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {canCompleteMilestones && milestone.status === 'pending' && <Button size="sm" variant="outline" icon={<CheckCircle2 className="h-4 w-4" />} onClick={() => void handleCompleteMilestone(milestone.id)}>Concluir</Button>}
                                            {canInvoiceMilestones && milestone.status === 'completed' && <Button size="sm" icon={<ReceiptText className="h-4 w-4" />} onClick={() => void handleInvoiceMilestone(milestone.id)}>Faturar</Button>}
                                        </div>
                                    </div>
                                ))}
                                {milestones.length === 0 && <p className="text-sm text-surface-500">Nenhum marco cadastrado para este projeto.</p>}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Recursos</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            {canManageResources && (
                                <form className="grid gap-3" onSubmit={resourceForm.handleSubmit(values => void handleCreateResource(values))}>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Input type="number" label="Usuário (ID)" aria-label="Usuário do recurso" {...resourceForm.register('user_id')} error={resourceForm.formState.errors.user_id?.message} />
                                        <Input label="Função" aria-label="Função do recurso" {...resourceForm.register('role')} error={resourceForm.formState.errors.role?.message} />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <Input type="number" label="Alocação %" aria-label="Alocação do recurso" {...resourceForm.register('allocation_percent')} error={resourceForm.formState.errors.allocation_percent?.message} />
                                        <Input type="date" label="Início" aria-label="Data inicial do recurso" {...resourceForm.register('start_date')} error={resourceForm.formState.errors.start_date?.message} />
                                        <Input type="date" label="Fim" aria-label="Data final do recurso" {...resourceForm.register('end_date')} error={resourceForm.formState.errors.end_date?.message} />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Input type="number" step="0.01" label="Valor/hora" aria-label="Valor por hora do recurso" {...resourceForm.register('hourly_rate')} />
                                        <Input type="number" step="0.01" label="Horas planejadas" aria-label="Horas planejadas do recurso" {...resourceForm.register('total_hours_planned')} />
                                    </div>
                                    <Button type="submit" loading={createResource.isPending}>Adicionar recurso</Button>
                                </form>
                            )}

                            <div className="space-y-3">
                                {resources.map(resource => (
                                    <div key={resource.id} className="rounded-[var(--radius-lg)] border border-surface-100 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-surface-900 dark:text-white">{resource.user?.name || `Usuário #${resource.user_id}`}</p>
                                                <p className="text-xs text-surface-500">{resource.role}</p>
                                            </div>
                                            <span className="text-sm font-semibold text-surface-900 dark:text-white">{Number(resource.allocation_percent).toFixed(0)}%</span>
                                        </div>
                                        <p className="mt-2 text-xs text-surface-500">{formatDate(resource.start_date)} até {formatDate(resource.end_date)}</p>
                                        <p className="mt-2 text-xs text-surface-500">Planejado {Number(resource.total_hours_planned ?? 0).toFixed(2)}h • Lançado {Number(resource.total_hours_logged ?? 0).toFixed(2)}h</p>
                                    </div>
                                ))}
                                {resources.length === 0 && <p className="text-sm text-surface-500">Nenhum recurso vinculado a este projeto.</p>}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Apontamentos</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            {canCreateTimeEntries && (
                                <form className="grid gap-3" onSubmit={timeEntryForm.handleSubmit(values => void handleCreateTimeEntry(values))}>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Input type="number" label="Recurso (ID)" aria-label="ID do recurso do apontamento" {...timeEntryForm.register('project_resource_id')} error={timeEntryForm.formState.errors.project_resource_id?.message} />
                                        <Input type="date" label="Data" aria-label="Data do apontamento" {...timeEntryForm.register('date')} error={timeEntryForm.formState.errors.date?.message} />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <Input type="number" step="0.25" label="Horas" aria-label="Horas apontadas" {...timeEntryForm.register('hours')} error={timeEntryForm.formState.errors.hours?.message} />
                                        <Input type="number" label="Marco (ID)" aria-label="ID do marco do apontamento" {...timeEntryForm.register('milestone_id')} />
                                        <Input type="number" label="OS (ID)" aria-label="ID da ordem de serviço" {...timeEntryForm.register('work_order_id')} />
                                    </div>
                                    <Textarea label="Descrição" aria-label="Descrição do apontamento" {...timeEntryForm.register('description')} />
                                    <label className="flex items-center gap-2 text-sm text-surface-700">
                                        <input type="checkbox" aria-label="Apontamento faturável" {...timeEntryForm.register('billable')} />
                                        Apontamento faturável
                                    </label>
                                    <Button type="submit" loading={createTimeEntry.isPending}>Registrar apontamento</Button>
                                </form>
                            )}

                            <div className="space-y-3">
                                {timeEntries.map(entry => (
                                    <div key={entry.id} className="rounded-[var(--radius-lg)] border border-surface-100 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-surface-900 dark:text-white">{entry.resource?.user?.name || `Recurso #${entry.project_resource_id}`}</p>
                                                <p className="text-xs text-surface-500">{entry.milestone?.name || 'Sem marco'} • {entry.billable ? 'Faturável' : 'Interno'}</p>
                                            </div>
                                            <span className="text-sm font-semibold text-surface-900 dark:text-white">{Number(entry.hours).toFixed(2)}h</span>
                                        </div>
                                        <p className="mt-2 text-xs text-surface-500">{formatDate(entry.date)} • {entry.description || 'Sem descrição'}</p>
                                    </div>
                                ))}
                                {timeEntries.length === 0 && <p className="text-sm text-surface-500">Nenhum apontamento registrado para este projeto.</p>}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                <DialogContent size="lg">
                    <DialogHeader><DialogTitle>Novo projeto</DialogTitle></DialogHeader>
                    <DialogBody>
                        <form id="project-create-form" className="grid gap-4 md:grid-cols-2" onSubmit={projectForm.handleSubmit(values => void handleCreateProject(values))}>
                            <Input type="number" label="Cliente (ID)" aria-label="ID do cliente" {...projectForm.register('customer_id')} error={projectForm.formState.errors.customer_id?.message} />
                            <Input label="Nome do projeto" aria-label="Nome do projeto" {...projectForm.register('name')} error={projectForm.formState.errors.name?.message} />
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Status</label>
                                <select aria-label="Status do projeto" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]" {...projectForm.register('status')}>
                                    {Object.entries(statusLabels).filter(([key]) => ['planning', 'active', 'on_hold', 'completed', 'cancelled'].includes(key)).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Prioridade</label>
                                <select aria-label="Prioridade do projeto" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]" {...projectForm.register('priority')}>
                                    {Object.entries(priorityLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Cobrança</label>
                                <select aria-label="Tipo de cobrança do projeto" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]" {...projectForm.register('billing_type')}>
                                    {Object.entries(billingTypeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </div>
                            <Input type="date" label="Início planejado" aria-label="Data inicial planejada" {...projectForm.register('start_date')} />
                            <Input type="date" label="Fim planejado" aria-label="Data final planejada" {...projectForm.register('end_date')} error={projectForm.formState.errors.end_date?.message} />
                            <Input type="number" step="0.01" label="Orçamento" aria-label="Orçamento do projeto" {...projectForm.register('budget')} />
                            <Input type="number" step="0.01" label="Valor hora" aria-label="Valor hora do projeto" {...projectForm.register('hourly_rate')} />
                            <Input type="number" label="Gerente (ID)" aria-label="ID do gerente" {...projectForm.register('manager_id')} />
                            <Input type="number" label="Deal CRM (ID)" aria-label="ID do deal CRM" {...projectForm.register('crm_deal_id')} />
                            <div className="md:col-span-2"><Input label="Tags" aria-label="Tags do projeto" placeholder="implantacao, premium" {...projectForm.register('tags')} /></div>
                            <div className="md:col-span-2"><Textarea label="Descrição" aria-label="Descrição do projeto" {...projectForm.register('description')} /></div>
                        </form>
                    </DialogBody>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowCreateDialog(false)}>Cancelar</Button>
                        <Button form="project-create-form" type="submit" loading={createProject.isPending}>Salvar projeto</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    )
}
