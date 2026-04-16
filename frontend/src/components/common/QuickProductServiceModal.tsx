import React, { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Save, Loader2, Package, Wrench } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import {
    Dialog, DialogContent, DialogHeader, DialogBody, DialogFooter,
    DialogTitle, DialogDescription,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { CurrencyInput } from '@/components/common/CurrencyInput'

interface QuickProductServiceModalProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    defaultTab?: 'product' | 'service'
    /** Called after a successful creation, with the new item's id, name and price */
    onCreated?: (item: { type: 'product' | 'service'; id: number; name: string; price: number }) => void
}

type CategoryOption = {
    id: number
    name: string
}

type CreatedProduct = {
    id: number
    name: string
    sell_price?: string | number | null
}

type CreatedService = {
    id: number
    name: string
    default_price?: string | number | null
}

type ValidationErrorPayload = {
    response?: {
        status?: number
        data?: {
            errors?: Record<string, string[]>
        }
    }
}

const emptyProductForm = {
    name: '', code: '', sell_price: 0, cost_price: 0,
    unit: 'UN', category_id: '' as string | number,
    description: '', is_active: true,
}

const emptyServiceForm = {
    name: '', code: '', default_price: 0,
    estimated_minutes: '' as string | number,
    category_id: '' as string | number,
    description: '', is_active: true,
}

function getFirstValidationError(err: unknown): string | null {
    const errors = (err as ValidationErrorPayload)?.response?.data?.errors
    if (!errors || typeof errors !== 'object') {
        return null
    }

    const first = Object.values(errors).flat().find((message): message is string => typeof message === 'string')
    return first ?? null
}

export default function QuickProductServiceModal({
    open, onOpenChange, defaultTab = 'product', onCreated,
}: QuickProductServiceModalProps) {
    const qc = useQueryClient()
    const [activeTab, setActiveTab] = useState<'product' | 'service'>(defaultTab)
    const [productForm, setProductForm] = useState(emptyProductForm)
    const [serviceForm, setServiceForm] = useState(emptyServiceForm)
    const [newCat, setNewCat] = useState('')

    // Reset forms when modal opens/closes
    React.useEffect(() => {
        if (open) {
            setActiveTab(defaultTab)
            setProductForm(emptyProductForm)
            setServiceForm(emptyServiceForm)
            setNewCat('')
        }
    }, [open, defaultTab])

    // Categories
    const { data: productCatsRes } = useQuery({
        queryKey: ['product-categories'],
        queryFn: () => api.get('/product-categories'),
        enabled: open && activeTab === 'product',
    })
    const productCategories = unwrapData<CategoryOption[]>(productCatsRes) ?? []

    const { data: serviceCatsRes } = useQuery({
        queryKey: ['service-categories'],
        queryFn: () => api.get('/service-categories'),
        enabled: open && activeTab === 'service',
    })
    const serviceCategories = unwrapData<CategoryOption[]>(serviceCatsRes) ?? []

    // Category creation
    const catMut = useMutation({
        mutationFn: (data: { endpoint: string; name: string }) =>
            api.post(data.endpoint, { name: data.name }),
        onSuccess: (_res, vars) => {
            const key = vars.endpoint.includes('product') ? 'product-categories' : 'service-categories'
            qc.invalidateQueries({ queryKey: [key] })
            setNewCat('')
            toast.success('Categoria criada!')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao criar categoria.')),
    })

    // Product save
    const productMut = useMutation({
        mutationFn: (data: typeof emptyProductForm) => api.post('/products', data),
        onSuccess: (res) => {
            const created = unwrapData<CreatedProduct>(res)
            toast.success('Produto criado com sucesso!')
            qc.invalidateQueries({ queryKey: ['products'] })
            qc.invalidateQueries({ queryKey: ['products-all'] })
            onCreated?.({
                type: 'product',
                id: created.id,
                name: created.name,
                price: Number.parseFloat(String(created.sell_price ?? 0)) || 0,
            })
            onOpenChange(false)
        },
        onError: (err: unknown) => {
            if ((err as ValidationErrorPayload)?.response?.status === 422) {
                toast.error(getFirstValidationError(err) ?? 'Verifique os campos obrigatórios')
            } else {
                toast.error(getApiErrorMessage(err, 'Erro ao criar produto'))
            }
        },
    })

    // Service save
    const serviceMut = useMutation({
        mutationFn: (data: typeof emptyServiceForm) => api.post('/services', data),
        onSuccess: (res) => {
            const created = unwrapData<CreatedService>(res)
            toast.success('Serviço criado com sucesso!')
            qc.invalidateQueries({ queryKey: ['services'] })
            qc.invalidateQueries({ queryKey: ['services-all'] })
            onCreated?.({
                type: 'service',
                id: created.id,
                name: created.name,
                price: Number.parseFloat(String(created.default_price ?? 0)) || 0,
            })
            onOpenChange(false)
        },
        onError: (err: unknown) => {
            if ((err as ValidationErrorPayload)?.response?.status === 422) {
                toast.error(getFirstValidationError(err) ?? 'Verifique os campos obrigatórios')
            } else {
                toast.error(getApiErrorMessage(err, 'Erro ao criar serviço'))
            }
        },
    })

    const isPending = productMut.isPending || serviceMut.isPending

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        if (activeTab === 'product') {
            productMut.mutate(productForm)
        } else {
            serviceMut.mutate(serviceForm)
        }
    }

    const pSet = <K extends keyof typeof emptyProductForm>(k: K, v: (typeof emptyProductForm)[K]) =>
        setProductForm(prev => ({ ...prev, [k]: v }))

    const sSet = <K extends keyof typeof emptyServiceForm>(k: K, v: (typeof emptyServiceForm)[K]) =>
        setServiceForm(prev => ({ ...prev, [k]: v }))

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {activeTab === 'product'
                            ? <Package size={18} className="text-brand-500" />
                            : <Wrench size={18} className="text-emerald-500" />
                        }
                        Cadastro Rápido
                    </DialogTitle>
                    <DialogDescription>
                        Cadastre um novo produto ou serviço sem sair do orçamento
                    </DialogDescription>
                </DialogHeader>

                {/* Tab Toggle */}
                <div className="flex gap-1 rounded-lg bg-surface-100 p-1 mx-6 mt-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('product')}
                        className={`flex-1 flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-all ${activeTab === 'product'
                                ? 'bg-surface-0 text-brand-700 shadow-sm'
                                : 'text-surface-500 hover:text-surface-700'
                            }`}
                    >
                        <Package size={15} />
                        Produto
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('service')}
                        className={`flex-1 flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-all ${activeTab === 'service'
                                ? 'bg-surface-0 text-emerald-700 shadow-sm'
                                : 'text-surface-500 hover:text-surface-700'
                            }`}
                    >
                        <Wrench size={15} />
                        Serviço
                    </button>
                </div>

                <form onSubmit={handleSubmit}>
                    <DialogBody>
                        {activeTab === 'product' ? (
                            <div className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Input
                                        label="Nome *"
                                        value={productForm.name}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => pSet('name', e.target.value)}
                                        required
                                        autoFocus
                                        placeholder="Nome do produto"
                                    />
                                    <Input
                                        label="Código"
                                        value={productForm.code}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => pSet('code', e.target.value)}
                                        placeholder="Opcional"
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <CurrencyInput
                                        label="Preço Venda (R$)"
                                        value={productForm.sell_price}
                                        onChange={(value) => pSet('sell_price', value)}
                                    />
                                    <CurrencyInput
                                        label="Preço Custo (R$)"
                                        value={productForm.cost_price}
                                        onChange={(value) => pSet('cost_price', value)}
                                    />
                                    <Input
                                        label="Unidade"
                                        value={productForm.unit}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => pSet('unit', e.target.value)}
                                        placeholder="UN, CX, KG..."
                                    />
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-surface-700">Categoria</label>
                                    <select
                                        value={productForm.category_id}
                                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => pSet('category_id', e.target.value)}
                                        className="w-full rounded-lg border border-surface-200 bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none"
                                    >
                                        <option value="">Sem categoria</option>
                                        {(productCategories || []).map((c: { id: number; name: string }) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </select>
                                    <div className="mt-1.5 flex gap-1">
                                        <input
                                            value={newCat}
                                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewCat(e.target.value)}
                                            placeholder="Nova categoria"
                                            className="flex-1 rounded-md border border-default px-2 py-1 text-xs"
                                        />
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            type="button"
                                            disabled={!newCat || catMut.isPending}
                                            onClick={() => catMut.mutate({ endpoint: '/product-categories', name: newCat })}
                                        >+</Button>
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-surface-700">Descrição</label>
                                    <textarea
                                        value={productForm.description}
                                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => pSet('description', e.target.value)}
                                        rows={2}
                                        placeholder="Descrição do produto (opcional)"
                                        className="w-full rounded-lg border border-surface-200 bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none"
                                    />
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Input
                                        label="Nome *"
                                        value={serviceForm.name}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => sSet('name', e.target.value)}
                                        required
                                        autoFocus
                                        placeholder="Nome do serviço"
                                    />
                                    <Input
                                        label="Código"
                                        value={serviceForm.code}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => sSet('code', e.target.value)}
                                        placeholder="Opcional"
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <CurrencyInput
                                        label="Preço Padrão (R$)"
                                        value={serviceForm.default_price}
                                        onChange={(value) => sSet('default_price', value)}
                                    />
                                    <Input
                                        label="Tempo Estimado (min)"
                                        type="number"
                                        value={serviceForm.estimated_minutes}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => sSet('estimated_minutes', e.target.value)}
                                        placeholder="Ex: 60"
                                    />
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Categoria</label>
                                        <select
                                            value={serviceForm.category_id}
                                            onChange={(e: React.ChangeEvent<HTMLSelectElement>) => sSet('category_id', e.target.value)}
                                            className="w-full rounded-lg border border-surface-200 bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none"
                                        >
                                            <option value="">Sem categoria</option>
                                            {(serviceCategories || []).map((c: { id: number; name: string }) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                        <div className="mt-1.5 flex gap-1">
                                            <input
                                                value={newCat}
                                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewCat(e.target.value)}
                                                placeholder="Nova categoria"
                                                className="flex-1 rounded-md border border-default px-2 py-1 text-xs"
                                            />
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="button"
                                                disabled={!newCat || catMut.isPending}
                                                onClick={() => catMut.mutate({ endpoint: '/service-categories', name: newCat })}
                                            >+</Button>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-surface-700">Descrição</label>
                                    <textarea
                                        value={serviceForm.description}
                                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => sSet('description', e.target.value)}
                                        rows={2}
                                        placeholder="Descrição do serviço (opcional)"
                                        className="w-full rounded-lg border border-surface-200 bg-surface-50 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none"
                                    />
                                </div>
                            </div>
                        )}
                    </DialogBody>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={isPending}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            loading={isPending}
                            icon={isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                        >
                            {isPending
                                ? 'Salvando...'
                                : activeTab === 'product'
                                    ? 'Salvar Produto'
                                    : 'Salvar Serviço'
                            }
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    )
}
