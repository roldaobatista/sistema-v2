import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Code, Copy, Eye, FileText, GripVertical, Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/emptystate'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { PageHeader } from '@/components/ui/pageheader'
import { Badge } from '@/components/ui/badge'
import { getApiErrorMessage } from '@/lib/api'
import { crmFeaturesApi, type CrmWebForm, type CrmWebFormOptions } from '@/lib/crm-features-api'

const FIELD_TYPES = [
    { value: 'text', label: 'Texto' },
    { value: 'email', label: 'E-mail' },
    { value: 'phone', label: 'Telefone' },
    { value: 'number', label: 'Numero' },
    { value: 'textarea', label: 'Texto longo' },
    { value: 'select', label: 'Selecao' },
]

interface FormField {
    name: string
    type: string
    label: string
    required: boolean
}

interface WebFormDraft {
    name: string
    description: string
    fields: FormField[]
    pipelineId: string
    assignTo: string
    sequenceId: string
    successMessage: string
    redirectUrl: string
    isActive: boolean
}

const DEFAULT_FIELDS: FormField[] = [
    { name: 'name', type: 'text', label: 'Nome', required: true },
    { name: 'email', type: 'email', label: 'E-mail', required: true },
    { name: 'phone', type: 'phone', label: 'Telefone', required: false },
]

const EMPTY_DRAFT: WebFormDraft = {
    name: '',
    description: '',
    fields: DEFAULT_FIELDS,
    pipelineId: '',
    assignTo: '',
    sequenceId: '',
    successMessage: '',
    redirectUrl: '',
    isActive: true,
}

function cloneFields(fields?: CrmWebForm['fields'] | FormField[]): FormField[] {
    if (!fields || fields.length === 0) {
        return DEFAULT_FIELDS.map(field => ({ ...field }))
    }

    return fields.map(field => ({
        name: field.name,
        type: field.type,
        label: field.label,
        required: Boolean(field.required),
    }))
}

function buildDraft(form?: CrmWebForm | null): WebFormDraft {
    if (!form) {
        return {
            ...EMPTY_DRAFT,
            fields: cloneFields(DEFAULT_FIELDS),
        }
    }

    return {
        name: form.name,
        description: form.description ?? '',
        fields: cloneFields(form.fields),
        pipelineId: form.pipeline_id ? String(form.pipeline_id) : '',
        assignTo: form.assign_to ? String(form.assign_to) : '',
        sequenceId: form.sequence_id ? String(form.sequence_id) : '',
        successMessage: form.success_message ?? '',
        redirectUrl: form.redirect_url ?? '',
        isActive: form.is_active,
    }
}

function toPayload(draft: WebFormDraft): Partial<CrmWebForm> {
    return {
        name: draft.name.trim(),
        description: draft.description.trim() || null,
        fields: draft.fields.map(field => ({
            name: field.name,
            type: field.type,
            label: field.label.trim(),
            required: field.required,
        })),
        pipeline_id: draft.pipelineId ? Number(draft.pipelineId) : null,
        assign_to: draft.assignTo ? Number(draft.assignTo) : null,
        sequence_id: draft.sequenceId ? Number(draft.sequenceId) : null,
        success_message: draft.successMessage.trim() || null,
        redirect_url: draft.redirectUrl.trim() || null,
        is_active: draft.isActive,
    }
}

function WebFormEditor({
    draft,
    onChange,
    options,
}: {
    draft: WebFormDraft
    onChange: (next: WebFormDraft) => void
    options?: CrmWebFormOptions
}) {
    const setDraft = (patch: Partial<WebFormDraft>) => onChange({ ...draft, ...patch })

    const addField = () => {
        setDraft({
            fields: [...draft.fields, { name: '', type: 'text', label: '', required: false }],
        })
    }

    const updateField = (index: number, patch: Partial<FormField>) => {
        const next = draft.fields.map((field, currentIndex) => {
            if (currentIndex !== index) {
                return field
            }

            const updated = { ...field, ...patch }
            if (patch.label != null) {
                updated.name = String(patch.label)
                    .toLowerCase()
                    .replace(/[^a-z0-9]/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '')
            }

            return updated
        })

        setDraft({ fields: next })
    }

    const removeField = (index: number) => {
        setDraft({ fields: draft.fields.filter((_, currentIndex) => currentIndex !== index) })
    }

    return (
        <div className="space-y-4">
            <Input
                label="Nome do formulario *"
                value={draft.name}
                onChange={event => setDraft({ name: event.target.value })}
                required
                placeholder="Ex: Captacao de Leads - Site"
            />

            <Input
                label="Descricao"
                value={draft.description}
                onChange={event => setDraft({ description: event.target.value })}
                placeholder="Descricao opcional do formulario"
            />

            <div className="grid gap-4 md:grid-cols-2">
                <label className="space-y-1.5 text-sm">
                    <span className="text-surface-700">Pipeline inicial</span>
                    <select
                        value={draft.pipelineId}
                        onChange={event => setDraft({ pipelineId: event.target.value })}
                        className="w-full rounded-md border border-default bg-background px-3 py-2 text-sm"
                    >
                        <option value="">Nenhum</option>
                        {(options?.pipelines ?? []).map(pipeline => (
                            <option key={pipeline.id} value={pipeline.id}>{pipeline.name}</option>
                        ))}
                    </select>
                </label>

                <label className="space-y-1.5 text-sm">
                    <span className="text-surface-700">Responsavel pelo lead</span>
                    <select
                        value={draft.assignTo}
                        onChange={event => setDraft({ assignTo: event.target.value })}
                        className="w-full rounded-md border border-default bg-background px-3 py-2 text-sm"
                    >
                        <option value="">Sem responsavel</option>
                        {(options?.users ?? []).map(user => (
                            <option key={user.id} value={user.id}>{user.name}</option>
                        ))}
                    </select>
                </label>

                <label className="space-y-1.5 text-sm">
                    <span className="text-surface-700">Cadencia automatica</span>
                    <select
                        value={draft.sequenceId}
                        onChange={event => setDraft({ sequenceId: event.target.value })}
                        className="w-full rounded-md border border-default bg-background px-3 py-2 text-sm"
                    >
                        <option value="">Nenhuma</option>
                        {(options?.sequences ?? []).map(sequence => (
                            <option key={sequence.id} value={sequence.id}>
                                {sequence.name} {sequence.status === 'active' ? '' : '(inativa)'}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="flex cursor-pointer items-center gap-2 self-end text-sm text-surface-700">
                    <input
                        type="checkbox"
                        checked={draft.isActive}
                        onChange={event => setDraft({ isActive: event.target.checked })}
                        className="rounded border-default"
                    />
                    Formulario ativo
                </label>
            </div>

            <Input
                label="Mensagem de sucesso"
                value={draft.successMessage}
                onChange={event => setDraft({ successMessage: event.target.value })}
                placeholder="Formulario enviado com sucesso!"
            />

            <Input
                label="URL de redirecionamento"
                value={draft.redirectUrl}
                onChange={event => setDraft({ redirectUrl: event.target.value })}
                placeholder="https://site.exemplo.com/obrigado"
            />

            <div>
                <div className="mb-2 flex items-center justify-between">
                    <label className="text-xs font-medium text-surface-700">Campos do formulario</label>
                    <Button type="button" size="sm" variant="outline" onClick={addField} icon={<Plus className="h-3 w-3" />}>
                        Adicionar campo
                    </Button>
                </div>

                <div className="max-h-64 space-y-2 overflow-y-auto">
                    {draft.fields.map((field, index) => (
                        <div key={`${field.name}-${index}`} className="flex items-center gap-2 rounded-lg bg-surface-50 p-2">
                            <div className="flex-1">
                                <input
                                    type="text"
                                    value={field.label}
                                    onChange={event => updateField(index, { label: event.target.value })}
                                    placeholder="Rotulo do campo"
                                    className="w-full rounded-md border-default px-2 py-1.5 text-xs focus:border-brand-500 focus:ring-brand-500"
                                />
                            </div>
                            <select
                                value={field.type}
                                onChange={event => updateField(index, { type: event.target.value })}
                                className="w-28 rounded-md border-default px-2 py-1.5 text-xs"
                                aria-label="Tipo do campo"
                            >
                                {FIELD_TYPES.map(type => (
                                    <option key={type.value} value={type.value}>{type.label}</option>
                                ))}
                            </select>
                            <label className="flex cursor-pointer items-center gap-1 whitespace-nowrap text-xs text-surface-600">
                                <input
                                    type="checkbox"
                                    checked={field.required}
                                    onChange={event => updateField(index, { required: event.target.checked })}
                                    className="rounded border-default"
                                />
                                Obrigatorio
                            </label>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="h-7 w-7 shrink-0 text-red-500"
                                onClick={() => removeField(index)}
                                aria-label="Remover campo"
                            >
                                <Trash2 className="h-3 w-3" />
                            </Button>
                        </div>
                    ))}
                </div>

                {draft.fields.length === 0 && (
                    <p className="py-4 text-center text-xs text-surface-400">Nenhum campo adicionado.</p>
                )}
            </div>
        </div>
    )
}

export function CrmWebFormsPage() {
    const queryClient = useQueryClient()
    const [showCreate, setShowCreate] = useState(false)
    const [editForm, setEditForm] = useState<CrmWebForm | null>(null)
    const [embedModal, setEmbedModal] = useState<CrmWebForm | null>(null)
    const [deleteId, setDeleteId] = useState<number | null>(null)
    const [createDraft, setCreateDraft] = useState<WebFormDraft>(() => buildDraft())
    const [editDraft, setEditDraft] = useState<WebFormDraft>(() => buildDraft())

    const { data: forms = [], isLoading } = useQuery<CrmWebForm[]>({
        queryKey: ['crm-web-forms'],
        queryFn: () => crmFeaturesApi.getWebForms(),
    })

    const { data: options } = useQuery({
        queryKey: ['crm-web-forms-options'],
        queryFn: () => crmFeaturesApi.getWebFormOptions(),
        staleTime: 300000,
    })

    useEffect(() => {
        if (editForm) {
            setEditDraft(buildDraft(editForm))
        }
    }, [editForm])

    const createMutation = useMutation({
        mutationFn: (data: Partial<CrmWebForm>) => crmFeaturesApi.createWebForm(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['crm-web-forms'] })
            setShowCreate(false)
            setCreateDraft(buildDraft())
            toast.success('Formulario criado com sucesso')
        },
        onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao criar formulario')),
    })

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<CrmWebForm> }) => crmFeaturesApi.updateWebForm(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['crm-web-forms'] })
            setEditForm(null)
            toast.success('Formulario atualizado com sucesso')
        },
        onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao atualizar formulario')),
    })

    const deleteMutation = useMutation({
        mutationFn: (id: number) => crmFeaturesApi.deleteWebForm(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['crm-web-forms'] })
            toast.success('Formulario excluido com sucesso')
        },
        onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao excluir formulario')),
    })

    const getEmbedCode = (form: CrmWebForm) => {
        const baseUrl = window.location.origin
        return `<iframe src="${baseUrl}/forms/${form.slug}" width="100%" height="500" frameborder="0" style="border:none;border-radius:8px;"></iframe>`
    }

    const copyEmbedCode = async (form: CrmWebForm) => {
        try {
            await navigator.clipboard.writeText(getEmbedCode(form))
            toast.success('Codigo copiado para a area de transferencia')
        } catch {
            toast.error('Nao foi possivel copiar o codigo de incorporacao')
        }
    }

    const handleCreate = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        if (!createDraft.name.trim()) {
            toast.error('Informe o nome do formulario')
            return
        }
        if (createDraft.fields.length === 0) {
            toast.error('Adicione ao menos um campo')
            return
        }

        createMutation.mutate(toPayload(createDraft))
    }

    const handleUpdate = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        if (!editForm) {
            return
        }
        if (!editDraft.name.trim()) {
            toast.error('Informe o nome do formulario')
            return
        }
        if (editDraft.fields.length === 0) {
            toast.error('Adicione ao menos um campo')
            return
        }

        updateMutation.mutate({
            id: editForm.id,
            data: toPayload(editDraft),
        })
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Formularios Web"
                subtitle="Crie formularios de captura de leads e configure pipeline, responsavel, cadencia e retorno ao visitante."
                count={forms.length}
                icon={FileText}
            >
                <Button size="sm" onClick={() => setShowCreate(true)} icon={<Plus className="h-4 w-4" />}>
                    Novo formulario
                </Button>
            </PageHeader>

            {isLoading ? (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {Array.from({ length: 3 }).map((_, index) => (
                        <div key={index} className="animate-pulse rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                            <div className="mb-3 h-4 w-3/4 rounded bg-surface-100" />
                            <div className="mb-4 h-3 w-1/2 rounded bg-surface-100" />
                            <div className="h-8 w-full rounded bg-surface-100" />
                        </div>
                    ))}
                </div>
            ) : forms.length === 0 ? (
                <EmptyState
                    icon={FileText}
                    title="Nenhum formulario criado"
                    message="Crie seu primeiro formulario de captura de leads."
                    action={{ label: 'Novo formulario', onClick: () => setShowCreate(true), icon: <Plus className="h-4 w-4" /> }}
                />
            ) : (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {forms.map(form => (
                        <div key={form.id} className="group relative rounded-xl border border-default bg-surface-0 p-6 shadow-card transition-shadow hover:shadow-md">
                            <div className="mb-3 flex items-start justify-between">
                                <div className="min-w-0 flex-1">
                                    <h3 className="truncate font-bold text-surface-900">{form.name}</h3>
                                    {form.description && (
                                        <p className="mt-0.5 line-clamp-2 text-xs text-surface-500">{form.description}</p>
                                    )}
                                </div>
                                <Badge variant={form.is_active ? 'success' : 'secondary'}>
                                    {form.is_active ? 'Ativo' : 'Inativo'}
                                </Badge>
                            </div>

                            <div className="mb-4 flex items-center gap-4 text-xs text-surface-500">
                                <span className="flex items-center gap-1">
                                    <GripVertical className="h-3 w-3" />
                                    {form.fields?.length ?? 0} campos
                                </span>
                                <span className="flex items-center gap-1">
                                    <Eye className="h-3 w-3" />
                                    {form.submissions_count} envios
                                </span>
                            </div>

                            <div className="mb-3 flex flex-wrap gap-1">
                                {form.fields?.slice(0, 4).map(field => (
                                    <Badge key={field.name} variant="outline" size="xs">
                                        {field.label}
                                        {field.required && <span className="text-red-400">*</span>}
                                    </Badge>
                                ))}
                                {(form.fields?.length ?? 0) > 4 && (
                                    <Badge variant="outline" size="xs">+{(form.fields?.length ?? 0) - 4}</Badge>
                                )}
                            </div>

                            <div className="mb-4 space-y-1 text-xs text-surface-500">
                                {form.pipeline_id && <p>Pipeline automatico configurado</p>}
                                {form.assign_to && <p>Responsavel automatico configurado</p>}
                                {form.sequence_id && <p>Cadencia automatica configurada</p>}
                                {form.redirect_url && <p className="truncate">Redirect: {form.redirect_url}</p>}
                            </div>

                            <div className="flex items-center gap-2 border-t border-surface-100 pt-3">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="h-7 text-xs"
                                    onClick={() => setEditForm(form)}
                                    icon={<Pencil className="h-3 w-3" />}
                                >
                                    Editar
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="flex-1 h-7 text-xs"
                                    onClick={() => setEmbedModal(form)}
                                    icon={<Code className="h-3 w-3" />}
                                >
                                    Embed
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="flex-1 h-7 text-xs"
                                    onClick={() => copyEmbedCode(form)}
                                    icon={<Copy className="h-3 w-3" />}
                                >
                                    Copiar
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="h-7 w-7 text-red-500 hover:bg-red-50 hover:text-red-700"
                                    onClick={() => setDeleteId(form.id)}
                                    aria-label="Excluir formulario"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Modal open={showCreate} onOpenChange={(open) => {
                setShowCreate(open)
                if (!open) {
                    setCreateDraft(buildDraft())
                }
            }} title="Novo formulario" size="lg">
                <form onSubmit={handleCreate} className="space-y-4">
                    <WebFormEditor draft={createDraft} onChange={setCreateDraft} options={options} />

                    <div className="flex justify-end gap-2 border-t border-surface-100 pt-4">
                        <Button variant="outline" type="button" onClick={() => {
                            setShowCreate(false)
                            setCreateDraft(buildDraft())
                        }}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={createMutation.isPending}>Criar formulario</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!embedModal} onOpenChange={() => setEmbedModal(null)} title="Codigo de incorporacao">
                {embedModal && (
                    <div className="space-y-4">
                        <p className="text-sm text-surface-600">
                            Copie o codigo abaixo e cole no HTML do seu site para exibir o formulario <strong>{embedModal.name}</strong>.
                        </p>
                        <div className="overflow-x-auto rounded-lg bg-surface-900 p-4">
                            <code className="break-all whitespace-pre-wrap font-mono text-xs text-emerald-400">
                                {getEmbedCode(embedModal)}
                            </code>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" onClick={() => setEmbedModal(null)}>Fechar</Button>
                            <Button onClick={() => copyEmbedCode(embedModal)} icon={<Copy className="h-4 w-4" />}>
                                Copiar codigo
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>

            <Modal open={!!editForm} onOpenChange={() => setEditForm(null)} title="Editar formulario" size="lg">
                {editForm && (
                    <form onSubmit={handleUpdate} className="space-y-4">
                        <WebFormEditor draft={editDraft} onChange={setEditDraft} options={options} />

                        <div className="flex justify-end gap-2 border-t border-surface-100 pt-4">
                            <Button type="button" variant="outline" onClick={() => setEditForm(null)}>Cancelar</Button>
                            <Button type="submit" loading={updateMutation.isPending}>Salvar</Button>
                        </div>
                    </form>
                )}
            </Modal>

            <Modal open={!!deleteId} onOpenChange={() => setDeleteId(null)} title="Excluir formulario">
                <p className="py-2 text-sm text-surface-600">
                    Deseja excluir este formulario? Os envios associados tambem serao removidos e esta acao nao pode ser desfeita.
                </p>
                <div className="flex justify-end gap-2 border-t border-surface-100 pt-4">
                    <Button variant="outline" onClick={() => setDeleteId(null)}>Cancelar</Button>
                    <Button
                        className="bg-red-600 text-white hover:bg-red-700"
                        loading={deleteMutation.isPending}
                        onClick={() => {
                            if (deleteId) {
                                deleteMutation.mutate(deleteId)
                            }
                            setDeleteId(null)
                        }}
                    >
                        Excluir
                    </Button>
                </div>
            </Modal>
        </div>
    )
}
