import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, Trash2, FileText } from 'lucide-react'
import { workOrderApi } from '@/lib/work-order-api'
import { getApiErrorMessage } from '@/lib/utils'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { PageHeader } from '@/components/ui/pageheader'
import { safeArray } from '@/lib/safe-array'

interface TemplateDefaultItem {
    type: string
    description: string
    quantity: number
    unit_price: number
}

interface OsTemplate {
    id: number
    name: string
    description?: string | null
    priority?: string
    service_type?: string | null
    default_items?: { type: string; description: string; quantity: number; unit_price: number }[]
    created_at?: string
}

export function WorkOrderTemplatesPage() {
    const qc = useQueryClient()
    const [showForm, setShowForm] = useState(false)
    const [editing, setEditing] = useState<OsTemplate | null>(null)
    const [deleteId, setDeleteId] = useState<number | null>(null)
    const [form, setForm] = useState({ name: '', description: '', priority: 'normal', service_type: '' })
    const [defaultItems, setDefaultItems] = useState<TemplateDefaultItem[]>([])

    const { data: res, isLoading } = useQuery({
        queryKey: ['work-order-templates'],
        queryFn: () => workOrderApi.listTemplates().then((r: { data: unknown }) => safeArray<OsTemplate>(r.data)),
    })
    const templates = res ?? []

    const saveMut = useMutation({
        mutationFn: (data: Record<string, unknown>) =>
            editing ? workOrderApi.updateTemplate(editing.id, data) : workOrderApi.createTemplate(data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['work-order-templates'] })
            toast.success(editing ? 'Template atualizado!' : 'Template criado!')
            setShowForm(false)
            setEditing(null)
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar template')),
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => workOrderApi.destroyTemplate(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['work-order-templates'] })
            setDeleteId(null)
            toast.success('Template removido!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao remover template')),
    })

    const openCreate = () => {
        setEditing(null)
        setForm({ name: '', description: '', priority: 'normal', service_type: '' })
        setDefaultItems([])
        setShowForm(true)
    }

    const openEdit = (t: OsTemplate) => {
        setEditing(t)
        setForm({ name: t.name, description: t.description ?? '', priority: t.priority ?? 'normal', service_type: t.service_type ?? '' })
        setDefaultItems((t.default_items ?? []).map(i => ({ type: i.type || 'service', description: i.description || '', quantity: i.quantity || 1, unit_price: i.unit_price || 0 })))
        setShowForm(true)
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        if (!form.name.trim()) { toast.error('Nome é obrigatório'); return }
        saveMut.mutate({ ...form, service_type: form.service_type || null, default_items: defaultItems.length > 0 ? defaultItems : null })
    }

    const priorityLabels: Record<string, string> = {
        low: 'Baixa', normal: 'Normal', high: 'Alta', urgent: 'Urgente',
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Templates de OS"
                subtitle="Modelos pré-definidos para criar OS rapidamente"
                backTo="/os"
                actions={
                    <Button icon={<Plus className="h-4 w-4" />} onClick={openCreate}>
                        Novo Template
                    </Button>
                }
            />

            <div className="rounded-xl border border-default bg-surface-0 shadow-card overflow-hidden">
                {isLoading ? (
                    <div className="p-8 text-center">
                        <div className="animate-pulse space-y-3">
                            <div className="h-4 w-48 mx-auto rounded bg-surface-200" />
                            <div className="h-4 w-32 mx-auto rounded bg-surface-100" />
                        </div>
                    </div>
                ) : templates.length === 0 ? (
                    <div className="py-12 text-center">
                        <FileText className="mx-auto h-10 w-10 text-surface-300" />
                        <p className="mt-2 text-sm text-surface-500">Nenhum template criado ainda</p>
                        <Button variant="outline" className="mt-3" onClick={openCreate}>Criar primeiro template</Button>
                    </div>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-default bg-surface-50 text-xs font-medium text-surface-600 uppercase tracking-wider">
                            <tr>
                                <th className="px-4 py-3">Nome</th>
                                <th className="px-4 py-3">Descrição</th>
                                <th className="px-4 py-3">Prioridade</th>
                                <th className="px-4 py-3">Tipo Serviço</th>
                                <th className="px-4 py-3 text-center">Itens</th>
                                <th className="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-subtle">
                            {templates.map(t => (
                                <tr key={t.id} className="hover:bg-surface-50/50 transition-colors">
                                    <td className="px-4 py-3 font-medium text-surface-900">{t.name}</td>
                                    <td className="px-4 py-3 text-surface-600 max-w-xs truncate">{t.description || '—'}</td>
                                    <td className="px-4 py-3 text-surface-600">{priorityLabels[t.priority ?? 'normal'] ?? t.priority}</td>
                                    <td className="px-4 py-3 text-surface-600">{t.service_type || '—'}</td>
                                    <td className="px-4 py-3 text-surface-600 text-center">{(t.default_items ?? []).length || '—'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <button type="button" onClick={() => openEdit(t)} className="p-1.5 rounded-md hover:bg-surface-100 text-surface-500 hover:text-surface-700 transition-colors" aria-label={`Editar ${t.name}`}>
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button type="button" onClick={() => setDeleteId(t.id)} className="p-1.5 rounded-md hover:bg-red-50 text-surface-500 hover:text-red-600 transition-colors" aria-label={`Remover ${t.name}`}>
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {/* Create/Edit Modal */}
            <Modal open={showForm} onClose={() => { setShowForm(false); setEditing(null) }}
                title={editing ? 'Editar Template' : 'Novo Template'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input label="Nome *" value={form.name} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setForm(p => ({ ...p, name: e.target.value }))} />
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Descrição</label>
                        <textarea value={form.description} onChange={e => setForm(p => ({ ...p, description: e.target.value }))} rows={2}
                            className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Prioridade</label>
                            <select title="Prioridade" value={form.priority} onChange={e => setForm(p => ({ ...p, priority: e.target.value }))}
                                className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                <option value="low">Baixa</option>
                                <option value="normal">Normal</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Tipo de Serviço</label>
                            <select title="Tipo de Serviço" value={form.service_type} onChange={e => setForm(p => ({ ...p, service_type: e.target.value }))}
                                className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                <option value="">Nenhum</option>
                                <option value="diagnostico">Diagnóstico</option>
                                <option value="manutencao_corretiva">Manutenção Corretiva</option>
                                <option value="preventiva">Preventiva</option>
                                <option value="calibracao">Calibração</option>
                                <option value="instalacao">Instalação</option>
                                <option value="retorno">Retorno</option>
                                <option value="garantia">Garantia</option>
                            </select>
                        </div>
                    </div>

                    {/* Itens Padrão */}
                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <label className="text-sm font-medium text-surface-700">Itens Padrão</label>
                            <Button type="button" variant="outline" size="sm" icon={<Plus className="h-3 w-3" />}
                                onClick={() => setDefaultItems(prev => [...prev, { type: 'service', description: '', quantity: 1, unit_price: 0 }])}>
                                Adicionar Item
                            </Button>
                        </div>
                        {defaultItems.length === 0 ? (
                            <p className="text-xs text-surface-400 py-2">Nenhum item padrão. Ao criar OS deste template, a lista de itens ficará vazia.</p>
                        ) : (
                            <div className="space-y-2 max-h-48 overflow-y-auto">
                                {defaultItems.map((item, idx) => (
                                    <div key={idx} className="flex items-center gap-2 rounded-lg border border-default p-2 bg-surface-50">
                                        <select title="Tipo" value={item.type}
                                            onChange={e => setDefaultItems(prev => prev.map((it, i) => i === idx ? { ...it, type: e.target.value } : it))}
                                            className="rounded border border-default bg-surface-0 px-2 py-1 text-xs w-24">
                                            <option value="service">Serviço</option>
                                            <option value="product">Produto</option>
                                        </select>
                                        <input type="text" placeholder="Descrição" value={item.description} aria-label="Descrição do item"
                                            onChange={e => setDefaultItems(prev => prev.map((it, i) => i === idx ? { ...it, description: e.target.value } : it))}
                                            className="flex-1 min-w-0 rounded border border-default bg-surface-0 px-2 py-1 text-xs" />
                                        <input type="number" placeholder="Qtd" value={item.quantity} min={0.01} step={0.01} aria-label="Quantidade"
                                            onChange={e => setDefaultItems(prev => prev.map((it, i) => i === idx ? { ...it, quantity: parseFloat(e.target.value) || 0 } : it))}
                                            className="w-16 rounded border border-default bg-surface-0 px-2 py-1 text-xs text-right" />
                                        <input type="number" placeholder="Preço" value={item.unit_price} min={0} step={0.01} aria-label="Preço unitário"
                                            onChange={e => setDefaultItems(prev => prev.map((it, i) => i === idx ? { ...it, unit_price: parseFloat(e.target.value) || 0 } : it))}
                                            className="w-20 rounded border border-default bg-surface-0 px-2 py-1 text-xs text-right" />
                                        <button type="button" aria-label="Remover item"
                                            onClick={() => setDefaultItems(prev => prev.filter((_, i) => i !== idx))}
                                            className="p-1 rounded-md hover:bg-red-50 text-surface-500 hover:text-red-600 transition-colors shrink-0">
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" type="button" onClick={() => { setShowForm(false); setEditing(null) }}>Cancelar</Button>
                        <Button type="submit" loading={saveMut.isPending}>{editing ? 'Salvar' : 'Criar'}</Button>
                    </div>
                </form>
            </Modal>

            {/* Delete Confirm */}
            <Modal open={deleteId !== null} onClose={() => setDeleteId(null)} title="Confirmar Exclusão">
                <p className="text-sm text-surface-600">Tem certeza que deseja remover este template?</p>
                <div className="flex justify-end gap-2 mt-4">
                    <Button variant="outline" onClick={() => setDeleteId(null)}>Cancelar</Button>
                    <Button variant="destructive" loading={deleteMut.isPending} onClick={() => deleteId && deleteMut.mutate(deleteId)}>Remover</Button>
                </div>
            </Modal>
        </div>
    )
}
