import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { getApiErrorMessage } from '@/lib/api'
import { crmApi, type CreateCrmActivityPayload } from '@/lib/crm-api'
import { queryKeys } from '@/lib/query-keys'

interface ContactOption {
    id: number
    name: string
    is_primary?: boolean
    role?: string
}

interface Props {
    open: boolean
    onClose: () => void
    customerId: number
    dealId?: number | null
    contactId?: number | null
    contacts?: ContactOption[]
}

const TYPES = [
    { value: 'ligacao', label: 'Ligacao' },
    { value: 'email', label: 'E-mail' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'reuniao', label: 'Reuniao' },
    { value: 'visita', label: 'Visita' },
    { value: 'nota', label: 'Nota' },
    { value: 'tarefa', label: 'Tarefa' },
]

const OUTCOMES = [
    { value: 'conectou', label: 'Conectou' },
    { value: 'nao_atendeu', label: 'Nao Atendeu' },
    { value: 'reagendar', label: 'Reagendar' },
    { value: 'sucesso', label: 'Sucesso' },
    { value: 'sem_interesse', label: 'Sem Interesse' },
]

const CHANNELS = [
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'email', label: 'E-mail' },
    { value: 'phone', label: 'Telefone' },
    { value: 'in_person', label: 'Presencial' },
]

export function ActivityForm({ open, onClose, customerId, dealId, contactId, contacts }: Props) {
    const queryClient = useQueryClient()
    const [form, setForm] = useState({
        type: 'nota',
        title: '',
        description: '',
        channel: '',
        outcome: '',
        scheduled_at: '',
        completed_at: '',
        duration_minutes: '',
        contact_id: '',
    })

    useEffect(() => {
        if (open && contactId) {
            setForm((prev) => ({ ...prev, contact_id: String(contactId) }))
        }
    }, [open, contactId])

    const mutation = useMutation({
        mutationFn: () =>
            crmApi.createActivity({
                type: form.type,
                customer_id: customerId,
                deal_id: dealId ?? null,
                contact_id: form.contact_id ? Number(form.contact_id) : null,
                title: form.title,
                description: form.description || null,
                channel: form.channel || null,
                outcome: form.outcome || null,
                scheduled_at: form.scheduled_at || null,
                completed_at: form.completed_at || null,
                duration_minutes: form.duration_minutes ? Number(form.duration_minutes) : null,
            } satisfies CreateCrmActivityPayload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['crm'] })
            queryClient.invalidateQueries({ queryKey: queryKeys.customers.customer360(customerId) })
            toast.success('Atividade registrada com sucesso')
            onClose()
            setForm({
                type: 'nota',
                title: '',
                description: '',
                channel: '',
                outcome: '',
                scheduled_at: '',
                completed_at: '',
                duration_minutes: '',
                contact_id: '',
            })
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao registrar atividade'))
        },
    })

    const set = (key: keyof typeof form, value: string) => setForm((prev) => ({ ...prev, [key]: value }))

    return (
        <Modal open={open} onOpenChange={(value) => !value && onClose()} title="Registrar Atividade" size="lg">
            <div className="space-y-4">
                <div>
                    <label className="mb-2 block text-xs font-medium text-surface-600">Tipo</label>
                    <div className="flex flex-wrap gap-2">
                        {TYPES.map((type) => (
                            <button
                                key={type.value}
                                onClick={() => set('type', type.value)}
                                className={`rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors ${
                                    form.type === type.value
                                        ? 'border-brand-300 bg-brand-50 text-brand-700'
                                        : 'border-subtle bg-surface-0 text-surface-600 hover:bg-surface-50 dark:hover:bg-surface-700'
                                }`}
                            >
                                {type.label}
                            </button>
                        ))}
                    </div>
                </div>

                {contacts && contacts.length > 0 && (
                    <div>
                        <label className="mb-1 block text-xs font-medium text-surface-600">Contato vinculado</label>
                        <select
                            aria-label="Contato vinculado"
                            value={form.contact_id}
                            onChange={(e) => set('contact_id', e.target.value)}
                            className="w-full rounded-lg border border-default px-3 py-2 text-sm text-surface-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                        >
                            <option value="">Nenhum (geral do cliente)</option>
                            {contacts.map((contact) => (
                                <option key={contact.id} value={contact.id}>
                                    {contact.name}
                                    {contact.role ? ` (${contact.role})` : ''}
                                    {contact.is_primary ? ' *' : ''}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <div>
                    <label className="mb-1 block text-xs font-medium text-surface-600">Titulo *</label>
                    <Input value={form.title} onChange={(e) => set('title', e.target.value)} placeholder="Ex: Retorno sobre proposta" />
                </div>

                <div>
                    <label className="mb-1 block text-xs font-medium text-surface-600">Descricao</label>
                    <textarea
                        value={form.description}
                        onChange={(e) => set('description', e.target.value)}
                        className="w-full rounded-lg border border-default px-3 py-2 text-sm text-surface-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                        rows={3}
                        placeholder="Detalhes opcionais..."
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-surface-600">Canal</label>
                        <select
                            aria-label="Canal"
                            value={form.channel}
                            onChange={(e) => set('channel', e.target.value)}
                            className="w-full rounded-lg border border-default px-3 py-2 text-sm text-surface-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                        >
                            <option value="">-</option>
                            {CHANNELS.map((channel) => (
                                <option key={channel.value} value={channel.value}>
                                    {channel.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-surface-600">Resultado</label>
                        <select
                            aria-label="Resultado"
                            value={form.outcome}
                            onChange={(e) => set('outcome', e.target.value)}
                            className="w-full rounded-lg border border-default px-3 py-2 text-sm text-surface-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                        >
                            <option value="">-</option>
                            {OUTCOMES.map((outcome) => (
                                <option key={outcome.value} value={outcome.value}>
                                    {outcome.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-surface-600">Agendado para</label>
                        <Input type="datetime-local" value={form.scheduled_at} onChange={(e) => set('scheduled_at', e.target.value)} />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-surface-600">Concluido em</label>
                        <Input type="datetime-local" value={form.completed_at} onChange={(e) => set('completed_at', e.target.value)} />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-surface-600">Duracao (min)</label>
                        <Input
                            type="number"
                            value={form.duration_minutes}
                            onChange={(e) => set('duration_minutes', e.target.value)}
                            placeholder="-"
                        />
                    </div>
                </div>

                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button variant="primary" onClick={() => mutation.mutate()} disabled={!form.title || mutation.isPending}>
                        {mutation.isPending ? 'Salvando...' : 'Registrar Atividade'}
                    </Button>
                </div>
            </div>
        </Modal>
    )
}
