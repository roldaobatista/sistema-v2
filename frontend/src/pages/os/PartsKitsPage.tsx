import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Layers, Plus, Trash2, Pencil, Wrench, Package } from 'lucide-react'
import { toast } from 'sonner'
import api from '@/lib/api'
import { getApiErrorMessage } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { Input } from '@/components/ui/input'
import { IconButton } from '@/components/ui/iconbutton'

interface KitListItem {
    id: number
    name: string
    description: string | null
    is_active: boolean
    items_count: number
}

interface KitItemForm {
    type: 'product' | 'service'
    reference_id: string
    description: string
    quantity: string
    unit_price: string
}

interface ProductOption {
    id: number
    name: string
    sell_price?: string | number
}

interface ServiceOption {
    id: number
    name: string
    default_price?: string | number
}

const emptyItem: KitItemForm = {
    type: 'product',
    reference_id: '',
    description: '',
    quantity: '1',
    unit_price: '0',
}

const emptyForm = {
    name: '',
    description: '',
    is_active: true,
    items: [emptyItem],
}

export function PartsKitsPage() {
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()

    const canView = hasPermission('os.work_order.view')
    const canCreate = hasPermission('os.work_order.create')
    const canUpdate = hasPermission('os.work_order.update')
    const canDelete = hasPermission('os.work_order.delete')

    const [search, setSearch] = useState('')
    const [activeFilter, setActiveFilter] = useState<'all' | 'active' | 'inactive'>('all')
    const [showForm, setShowForm] = useState(false)
    const [editingId, setEditingId] = useState<number | null>(null)
    const [deleteTarget, setDeleteTarget] = useState<KitListItem | null>(null)
    const [form, setForm] = useState(emptyForm)

    const { data: listRes, isLoading, isError, refetch } = useQuery({
        queryKey: ['parts-kits', search, activeFilter],
        queryFn: () => api.get('/parts-kits', {
            params: {
                search: search || undefined,
                is_active: activeFilter === 'all' ? undefined : activeFilter === 'active' ? 1 : 0,
                per_page: 100,
            },
        }),
        enabled: canView,
    })

    const kits: KitListItem[] = listRes?.data?.data ?? []

    const { data: productsRes } = useQuery({
        queryKey: ['parts-kits-products'],
        queryFn: () => api.get('/products', { params: { per_page: 300, is_active: 1 } }),
        enabled: showForm,
    })
    const products: ProductOption[] = productsRes?.data?.data ?? []

    const { data: servicesRes } = useQuery({
        queryKey: ['parts-kits-services'],
        queryFn: () => api.get('/services', { params: { per_page: 300, is_active: 1 } }),
        enabled: showForm,
    })
    const services: ServiceOption[] = servicesRes?.data?.data ?? []

    const saveMut = useMutation({
        mutationFn: async () => {
            const payload = {
                name: form.name.trim(),
                description: form.description.trim() || null,
                is_active: form.is_active,
                items: form.items.map(item => ({
                    type: item.type,
                    reference_id: item.reference_id ? Number(item.reference_id) : null,
                    description: item.description.trim(),
                    quantity: Number(item.quantity),
                    unit_price: Number(item.unit_price),
                })),
            }

            if (editingId) {
                return api.put(`/parts-kits/${editingId}`, payload)
            }

            return api.post('/parts-kits', payload)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['parts-kits'] })
            toast.success(editingId ? 'Kit atualizado com sucesso' : 'Kit criado com sucesso')
            setShowForm(false)
            setEditingId(null)
            setForm(emptyForm)
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao salvar kit de pecas'))
        },
    })

    const deleteMut = useMutation({
        mutationFn: (id: number) => api.delete(`/parts-kits/${id}`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['parts-kits'] })
            toast.success('Kit removido com sucesso')
            setDeleteTarget(null)
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao remover kit'))
            setDeleteTarget(null)
        },
    })

    const setItem = (index: number, patch: Partial<KitItemForm>) => {
        setForm(prev => {
            const nextItems = [...prev.items]
            nextItems[index] = { ...nextItems[index], ...patch }
            return { ...prev, items: nextItems }
        })
    }

    const addItem = () => {
        setForm(prev => ({ ...prev, items: [...prev.items, { ...emptyItem }] }))
    }

    const removeItem = (index: number) => {
        setForm(prev => ({
            ...prev,
            items: prev.items.filter((_, currentIndex) => currentIndex !== index),
        }))
    }

    const handleReferenceChange = (index: number, referenceId: string) => {
        const item = form.items[index]
        const list = (item.type === 'product' ? products : services) as Array<ProductOption | ServiceOption>
        const selected = list.find(option => option.id === Number(referenceId))

        if (!selected) {
            setItem(index, { reference_id: referenceId })
            return
        }

        const defaultPrice = item.type === 'product'
            ? Number((selected as ProductOption).sell_price ?? 0)
            : Number((selected as ServiceOption).default_price ?? 0)

        setItem(index, {
            reference_id: referenceId,
            description: selected.name,
            unit_price: defaultPrice > 0 ? String(defaultPrice) : form.items[index].unit_price,
        })
    }

    const validateForm = (): boolean => {
        if (!form.name.trim()) {
            toast.error('Informe o nome do kit')
            return false
        }

        if (form.items.length === 0) {
            toast.error('Adicione ao menos um item ao kit')
            return false
        }

        for (const [index, item] of form.items.entries()) {
            if (!item.description.trim()) {
                toast.error(`Preencha a descricao do item ${index + 1}`)
                return false
            }

            if (!item.quantity || Number(item.quantity) <= 0) {
                toast.error(`Quantidade invalida no item ${index + 1}`)
                return false
            }

            if (item.unit_price === '' || Number(item.unit_price) < 0) {
                toast.error(`Preco unitario invalido no item ${index + 1}`)
                return false
            }
        }

        return true
    }

    const openCreate = () => {
        setEditingId(null)
        setForm(emptyForm)
        setShowForm(true)
    }

    const openEdit = async (kit: KitListItem) => {
        try {
            const { data } = await api.get(`/parts-kits/${kit.id}`)
            const details = data?.data

            if (!details) {
                toast.error('Nao foi possivel carregar o kit para edicao')
                return
            }

            setEditingId(kit.id)
            setForm({
                name: details.name ?? kit.name,
                description: details.description ?? '',
                is_active: details.is_active ?? true,
                items: (details.items ?? []).map((item: {
                    type: 'product' | 'service'
                    reference_id: number | null
                    description: string
                    quantity: string | number
                    unit_price: string | number
                }) => ({
                    type: item.type,
                    reference_id: item.reference_id ? String(item.reference_id) : '',
                    description: item.description,
                    quantity: String(item.quantity),
                    unit_price: String(item.unit_price),
                })),
            })
            setShowForm(true)
        } catch (error: unknown) {
            toast.error(getApiErrorMessage(error, 'Erro ao carregar kit'))
        }
    }

    const submit = () => {
        if (!validateForm()) return
        saveMut.mutate()
    }

    if (!canView) {
        return (
            <div className="space-y-5">
                <PageHeader title="Kits de Pecas" subtitle="Cadastro de kits para aplicacao rapida em OS" />
                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Voce nao possui permissao para visualizar kits de pecas.
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Kits de Pecas"
                subtitle="Monte kits padrao para agilizar o lancamento de itens na OS"
                count={kits.length}
                actions={[
                    { label: 'Atualizar', icon: <Wrench className="h-4 w-4" />, onClick: () => refetch(), variant: 'outline' },
                    ...(canCreate ? [{ label: 'Novo Kit', icon: <Plus className="h-4 w-4" />, onClick: openCreate }] : []),
                ]}
            />

            <div className="grid gap-3 rounded-xl border border-default bg-surface-0 p-4 shadow-card md:grid-cols-3">
                <Input
                    label="Busca"
                    value={search}
                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => setSearch(event.target.value)}
                    placeholder="Nome do kit"
                />
                <div>
                    <label className="mb-1.5 block text-sm font-medium text-surface-700">Status</label>
                    <select
                        value={activeFilter}
                        onChange={(event: React.ChangeEvent<HTMLSelectElement>) => setActiveFilter(event.target.value as 'all' | 'active' | 'inactive')}
                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none"
                    >
                        <option value="all">Todos</option>
                        <option value="active">Somente ativos</option>
                        <option value="inactive">Somente inativos</option>
                    </select>
                </div>
            </div>

            {isError ? (
                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Nao foi possivel carregar os kits de pecas.
                </div>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {isLoading ? (
                    <p className="col-span-full py-12 text-center text-sm text-surface-500">Carregando kits...</p>
                ) : kits.length === 0 ? (
                    <div className="col-span-full">
                        <EmptyState
                            icon={<Layers className="h-5 w-5 text-surface-300" />}
                            message="Nenhum kit cadastrado"
                            action={canCreate ? { label: 'Criar primeiro kit', onClick: openCreate, icon: <Plus className="h-4 w-4" /> } : undefined}
                        />
                    </div>
                ) : (kits || []).map(kit => (
                    <div key={kit.id} className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-surface-900">{kit.name}</p>
                                {kit.description ? <p className="mt-1 text-xs text-surface-500">{kit.description}</p> : null}
                            </div>
                            <Badge variant={kit.is_active ? 'success' : 'default'}>{kit.is_active ? 'Ativo' : 'Inativo'}</Badge>
                        </div>

                        <div className="mt-4 flex items-center justify-between border-t border-subtle pt-3">
                            <span className="text-xs text-surface-500">{kit.items_count} item(ns)</span>
                            <div className="flex items-center gap-1">
                                {canUpdate ? (
                                    <IconButton
                                        label="Editar kit"
                                        icon={<Pencil className="h-4 w-4" />}
                                        onClick={() => openEdit(kit)}
                                        className="hover:text-brand-600"
                                    />
                                ) : null}
                                {canDelete ? (
                                    <IconButton
                                        label="Excluir kit"
                                        icon={<Trash2 className="h-4 w-4" />}
                                        onClick={() => setDeleteTarget(kit)}
                                        className="hover:text-red-600"
                                    />
                                ) : null}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <Modal
                open={showForm}
                onOpenChange={(open: boolean) => {
                    setShowForm(open)
                    if (!open && !saveMut.isPending) {
                        setEditingId(null)
                        setForm(emptyForm)
                    }
                }}
                title={editingId ? 'Editar Kit de Pecas' : 'Novo Kit de Pecas'}
                size="xl"
            >
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input
                            label="Nome *"
                            value={form.name}
                            onChange={(event: React.ChangeEvent<HTMLInputElement>) => setForm(prev => ({ ...prev, name: event.target.value }))}
                            placeholder="Ex.: Kit Preventiva Basica"
                            required
                        />
                        <div className="flex items-end pb-1">
                            <label className="flex items-center gap-2 text-sm text-surface-700">
                                <input
                                    type="checkbox"
                                    checked={form.is_active}
                                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => setForm(prev => ({ ...prev, is_active: event.target.checked }))}
                                    className="h-4 w-4 rounded border-default"
                                />
                                Kit ativo
                            </label>
                        </div>
                    </div>

                    <Input
                        label="Descricao"
                        value={form.description}
                        onChange={(event: React.ChangeEvent<HTMLInputElement>) => setForm(prev => ({ ...prev, description: event.target.value }))}
                        placeholder="Quando usar este kit"
                    />

                    <div className="rounded-xl border border-default p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <p className="text-sm font-semibold text-surface-800">Itens do Kit</p>
                            <Button variant="outline" size="sm" icon={<Plus className="h-4 w-4" />} onClick={addItem}>Adicionar item</Button>
                        </div>

                        <div className="space-y-3">
                            {form.items.map((item, index) => (
                                <div key={index} className="grid gap-3 rounded-lg border border-subtle bg-surface-50 p-3 md:grid-cols-[130px_1fr_1.4fr_120px_130px_auto]">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-surface-600">Tipo</label>
                                        <select
                                            value={item.type}
                                            onChange={(event: React.ChangeEvent<HTMLSelectElement>) => setItem(index, {
                                                type: event.target.value as 'product' | 'service',
                                                reference_id: '',
                                                description: '',
                                            })}
                                            className="w-full rounded-lg border border-default bg-surface-0 px-2 py-2 text-sm"
                                        >
                                            <option value="product">Produto</option>
                                            <option value="service">Servico</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-surface-600">Referencia</label>
                                        <select
                                            value={item.reference_id}
                                            onChange={(event: React.ChangeEvent<HTMLSelectElement>) => handleReferenceChange(index, event.target.value)}
                                            className="w-full rounded-lg border border-default bg-surface-0 px-2 py-2 text-sm"
                                        >
                                            <option value="">Nao vincular</option>
                                            {(item.type === 'product' ? products : services).map(option => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <Input
                                        label="Descricao"
                                        value={item.description}
                                        onChange={(event: React.ChangeEvent<HTMLInputElement>) => setItem(index, { description: event.target.value })}
                                        placeholder="Descricao do item"
                                        required
                                    />

                                    <Input
                                        label="Qtd"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={item.quantity}
                                        onChange={(event: React.ChangeEvent<HTMLInputElement>) => setItem(index, { quantity: event.target.value })}
                                        required
                                    />

                                    <Input
                                        label="Preco Unit."
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={item.unit_price}
                                        onChange={(event: React.ChangeEvent<HTMLInputElement>) => setItem(index, { unit_price: event.target.value })}
                                        required
                                    />

                                    <div className="flex items-end pb-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeItem(index)}
                                            disabled={form.items.length === 1}
                                            aria-label="Remover item"
                                        >
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-subtle pt-4">
                        <Button variant="outline" onClick={() => setShowForm(false)} disabled={saveMut.isPending}>Cancelar</Button>
                        <Button onClick={submit} loading={saveMut.isPending} disabled={saveMut.isPending} icon={<Package className="h-4 w-4" />}>
                            {editingId ? 'Salvar alteracoes' : 'Criar kit'}
                        </Button>
                    </div>
                </div>
            </Modal>

            <Modal
                open={deleteTarget !== null}
                onOpenChange={() => setDeleteTarget(null)}
                title="Excluir Kit de Pecas"
                size="sm"
            >
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">
                        Deseja realmente excluir o kit <span className="font-semibold">{deleteTarget?.name}</span>?
                    </p>
                    <div className="flex justify-end gap-2 border-t border-subtle pt-4">
                        <Button variant="outline" onClick={() => setDeleteTarget(null)} disabled={deleteMut.isPending}>Cancelar</Button>
                        <Button
                            variant="danger"
                            loading={deleteMut.isPending}
                            disabled={deleteMut.isPending}
                            onClick={() => deleteTarget && deleteMut.mutate(deleteTarget.id)}
                        >
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
