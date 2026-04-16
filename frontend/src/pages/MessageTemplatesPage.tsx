import React, { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Edit, FileText, Loader2, Mail, MessageCircle, Plus, Save, Smartphone, Trash2, X } from 'lucide-react'
import { toast } from 'sonner'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { getApiErrorMessage } from '@/lib/api'
import { crmApi } from '@/lib/crm-api'
import type { CrmMessageTemplate } from '@/lib/crm-api'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'

type Channel = 'whatsapp' | 'email' | 'sms'

const CHANNEL_META: Record<Channel, { icon: React.ElementType; color: string; label: string }> = {
    whatsapp: { icon: MessageCircle, color: 'text-green-600 bg-green-50', label: 'WhatsApp' },
    email: { icon: Mail, color: 'text-blue-600 bg-blue-50', label: 'E-mail' },
    sms: { icon: Smartphone, color: 'text-amber-600 bg-amber-50', label: 'SMS' },
}

export function MessageTemplatesPage() {
    const { hasPermission } = useAuthStore()
    const canManageTemplates = hasPermission('crm.message.send')

    const qc = useQueryClient()
    const [editing, setEditing] = useState<CrmMessageTemplate | null>(null)
    const [creating, setCreating] = useState(false)
    const [filterChannel, setFilterChannel] = useState<Channel | ''>('')

    const { data: templates = [], isLoading, isError } = useQuery({
        queryKey: ['crm', 'message-templates'],
        queryFn: () => crmApi.getMessageTemplates(undefined, { includeInactive: true }),
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => crmApi.deleteMessageTemplate(id),
        onSuccess: () => {
            toast.success('Operacao realizada com sucesso')
            qc.invalidateQueries({ queryKey: ['crm', 'message-templates'] })
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao excluir template'))
        },
    })

    const filtered = filterChannel
        ? templates.filter((template: CrmMessageTemplate) => template.channel === filterChannel)
        : templates

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-surface-900">Templates de Mensagem</h1>
                    <p className="mt-1 text-sm text-surface-500">Modelos reutilizaveis para WhatsApp e E-mail</p>
                </div>
                <Button
                    variant="primary"
                    size="sm"
                    onClick={() => setCreating(true)}
                    disabled={!canManageTemplates}
                >
                    <Plus className="mr-1 h-4 w-4" />
                    Novo Template
                </Button>
            </div>

            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={() => setFilterChannel('')}
                    className={cn(
                        'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                        !filterChannel ? 'bg-brand-100 text-brand-700' : 'bg-surface-100 text-surface-600 hover:bg-surface-200'
                    )}
                >
                    Todos ({templates.length})
                </button>
                {(['whatsapp', 'email', 'sms'] as Channel[]).map(channel => {
                    const count = templates.filter((template: CrmMessageTemplate) => template.channel === channel).length
                    const meta = CHANNEL_META[channel]

                    return (
                        <button
                            key={channel}
                            type="button"
                            onClick={() => setFilterChannel(channel)}
                            className={cn(
                                'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                                filterChannel === channel
                                    ? 'bg-brand-100 text-brand-700'
                                    : 'bg-surface-100 text-surface-600 hover:bg-surface-200'
                            )}
                        >
                            <meta.icon className="h-3 w-3" />
                            {meta.label} ({count})
                        </button>
                    )
                })}
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-brand-500" />
                </div>
            ) : isError ? (
                <div className="flex flex-col items-center justify-center py-16 text-center">
                    <div className="mb-3 rounded-full bg-red-50 p-4">
                        <FileText className="h-6 w-6 text-red-400" />
                    </div>
                    <p className="text-sm font-medium text-red-600">Erro ao carregar templates</p>
                    <p className="mt-1 text-xs text-surface-400">Tente novamente mais tarde</p>
                </div>
            ) : filtered.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-16 text-center">
                    <div className="mb-3 rounded-full bg-surface-100 p-4">
                        <FileText className="h-6 w-6 text-surface-400" />
                    </div>
                    <p className="text-sm text-surface-500">Nenhum template encontrado</p>
                    <p className="mt-1 text-xs text-surface-400">Crie um template para agilizar o envio de mensagens</p>
                </div>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((template: CrmMessageTemplate) => {
                        const meta = CHANNEL_META[template.channel]

                        return (
                            <div
                                key={template.id}
                                className="group rounded-xl border border-default bg-surface-0 p-5 shadow-card transition-all"
                            >
                                <div className="mb-3 flex items-start justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className={cn('flex h-8 w-8 items-center justify-center rounded-lg', meta.color)}>
                                            <meta.icon className="h-4 w-4" />
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-semibold text-surface-900">{template.name}</h3>
                                            <p className="text-xs font-mono text-surface-400">{template.slug}</p>
                                        </div>
                                    </div>
                                    <div className="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <button
                                            type="button"
                                            disabled={!canManageTemplates}
                                            onClick={() => setEditing(template)}
                                            aria-label={`Editar template ${template.name}`}
                                            className="rounded-md p-1 text-surface-400 hover:bg-surface-100 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            <Edit className="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            disabled={!canManageTemplates}
                                            onClick={() => {
                                                if (confirm('Excluir template?')) {
                                                    deleteMut.mutate(template.id)
                                                }
                                            }}
                                            aria-label={`Excluir template ${template.name}`}
                                            className="rounded-md p-1 text-surface-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>

                                {template.subject && (
                                    <p className="mb-2 text-xs font-medium text-surface-600">Assunto: {template.subject}</p>
                                )}

                                <p className="line-clamp-3 whitespace-pre-wrap text-xs leading-relaxed text-surface-500">
                                    {template.body}
                                </p>

                                {template.variables && template.variables.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-1">
                                        {template.variables.map((variable, index) => (
                                            <Badge key={index} variant="default">
                                                {`{{${variable.name}}}`}
                                            </Badge>
                                        ))}
                                    </div>
                                )}

                                <div className="mt-3 flex items-center justify-between border-t border-surface-100 pt-3">
                                    <Badge variant={template.is_active ? 'success' : 'default'}>
                                        {template.is_active ? 'Ativo' : 'Inativo'}
                                    </Badge>
                                    <Badge variant="info">{meta.label}</Badge>
                                </div>
                            </div>
                        )
                    })}
                </div>
            )}

            {canManageTemplates && (creating || editing) && (
                <TemplateFormModal
                    template={editing}
                    onClose={() => {
                        setCreating(false)
                        setEditing(null)
                    }}
                />
            )}
        </div>
    )
}

function TemplateFormModal({
    template,
    onClose,
}: {
    template: CrmMessageTemplate | null
    onClose: () => void
}) {
    const qc = useQueryClient()
    const isEdit = !!template

    const [name, setName] = useState(template?.name ?? '')
    const [slug, setSlug] = useState(template?.slug ?? '')
    const [body, setBody] = useState(template?.body ?? '')
    const [channel, setChannel] = useState<Channel>(template?.channel ?? 'whatsapp')
    const [subject, setSubject] = useState(template?.subject ?? '')

    const createMut = useMutation({
        mutationFn: (data: Partial<CrmMessageTemplate>) => crmApi.createMessageTemplate(data),
        onSuccess: () => {
            toast.success('Operacao realizada com sucesso')
            qc.invalidateQueries({ queryKey: ['crm', 'message-templates'] })
            onClose()
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao criar template'))
        },
    })

    const updateMut = useMutation({
        mutationFn: (data: Partial<CrmMessageTemplate>) => crmApi.updateMessageTemplate(template!.id, data),
        onSuccess: () => {
            toast.success('Operacao realizada com sucesso')
            qc.invalidateQueries({ queryKey: ['crm', 'message-templates'] })
            onClose()
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao atualizar template'))
        },
    })

    const isPending = createMut.isPending || updateMut.isPending

    const handleSubmit = () => {
        const data: Partial<CrmMessageTemplate> = {
            name,
            slug,
            channel,
            body,
            subject: channel === 'email' ? subject : null,
        }

        if (isEdit) {
            updateMut.mutate(data)
            return
        }

        createMut.mutate(data)
    }

    const canSave = name.trim() && slug.trim() && body.trim() && (channel !== 'email' || subject.trim())

    const autoSlug = (value: string) => {
        setName(value)
        if (!isEdit) {
            setSlug(value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''))
        }
    }

    return (
        <>
            <div className="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm" onClick={onClose} />
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div className="w-full max-w-lg rounded-2xl border border-default bg-surface-0 shadow-modal animate-in fade-in zoom-in-95 duration-200">
                    <div className="flex items-center justify-between border-b border-subtle px-5 py-4">
                        <h2 className="text-lg font-semibold text-surface-900">
                            {isEdit ? 'Editar Template' : 'Novo Template'}
                        </h2>
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label="Fechar modal de template"
                            className="rounded-lg p-1.5 text-surface-400 hover:bg-surface-100"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    <div className="space-y-4 p-5">
                        <div>
                            <label className="mb-1.5 block text-xs font-medium text-surface-500">Canal</label>
                            <div className="flex gap-2">
                                {(['whatsapp', 'email', 'sms'] as Channel[]).map(currentChannel => {
                                    const meta = CHANNEL_META[currentChannel]

                                    return (
                                        <button
                                            key={currentChannel}
                                            type="button"
                                            onClick={() => setChannel(currentChannel)}
                                            className={cn(
                                                'flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium transition-all',
                                                channel === currentChannel
                                                    ? 'bg-brand-50 text-brand-700 ring-1 ring-brand-300'
                                                    : 'bg-surface-50 text-surface-600 hover:bg-surface-100'
                                            )}
                                        >
                                            <meta.icon className="h-3.5 w-3.5" />
                                            {meta.label}
                                        </button>
                                    )
                                })}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-surface-500">Nome</label>
                                <input
                                    value={name}
                                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => autoSlug(event.target.value)}
                                    placeholder="Ex: Boas-vindas"
                                    className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm text-surface-900 outline-none placeholder:text-surface-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-surface-500">Slug</label>
                                <input
                                    value={slug}
                                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => setSlug(event.target.value)}
                                    placeholder="boas-vindas"
                                    className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 font-mono text-sm text-surface-900 outline-none placeholder:text-surface-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                        </div>

                        {channel === 'email' && (
                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-surface-500">Assunto</label>
                                <input
                                    value={subject}
                                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => setSubject(event.target.value)}
                                    placeholder="Assunto do e-mail..."
                                    className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm text-surface-900 outline-none placeholder:text-surface-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                        )}

                        <div>
                            <div className="mb-1.5 flex items-center justify-between">
                                <label className="text-xs font-medium text-surface-500">Corpo da mensagem</label>
                                <span className="text-xs text-surface-400">Use {'{{nome}}'}, {'{{valor}}'} para variaveis</span>
                            </div>
                            <textarea
                                value={body}
                                onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => setBody(event.target.value)}
                                placeholder="Ola {{nome}}, ..."
                                rows={6}
                                className="w-full resize-none rounded-lg border border-default bg-surface-0 px-3 py-2.5 font-mono text-sm text-surface-900 outline-none placeholder:text-surface-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-subtle px-5 py-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg px-4 py-2 text-sm font-medium text-surface-600 transition-colors hover:bg-surface-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={handleSubmit}
                            disabled={!canSave || isPending}
                            className="flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-700 disabled:bg-brand-300"
                        >
                            {isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            {isEdit ? 'Salvar' : 'Criar'}
                        </button>
                    </div>
                </div>
            </div>
        </>
    )
}
