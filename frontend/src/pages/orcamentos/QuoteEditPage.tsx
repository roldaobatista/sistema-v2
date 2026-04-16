import { useState, useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useParams, useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { quoteApi } from '@/lib/quote-api'
import { queryKeys } from '@/lib/query-keys'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { isMutableQuoteStatus } from '@/features/quotes/constants'
import type { ApiErrorLike } from '@/types/common'
import type { Quote, QuoteItem } from '@/types/quote'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import { ArrowLeft, Save, Plus, Trash2, Package, Wrench } from 'lucide-react'
import { useAuthStore } from '@/stores/auth-store'
import PriceHistoryHint from '@/components/common/PriceHistoryHint'
import QuickProductServiceModal from '@/components/common/QuickProductServiceModal'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { DiscountInput, type DiscountMode } from '@/components/common/DiscountInput'
import { formatCurrency } from '@/lib/utils'
import { ItemSearchCombobox } from '@/components/common/ItemSearchCombobox'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import * as z from 'zod'
import type { QuoteProductOption, QuoteServiceOption, QuoteItemForm } from '@/types/quote'
import { buildEquipmentDisplayName } from '@/lib/equipment-display'

const quoteUpdateGeneralSchema = z.object({
    seller_id: z.number().optional().nullable(),
    valid_until: z.string().optional().nullable(),
    source: z.string().optional().nullable(),
    observations: z.string().optional().nullable(),
    internal_notes: z.string().optional().nullable(),
    general_conditions: z.string().optional().nullable(),
    discount_percentage: z.number().min(0).max(100).optional(),
    discount_amount: z.number().min(0).optional(),
    displacement_value: z.number().min(0).optional(),
    payment_terms: z.string().optional().nullable(),
    payment_terms_detail: z.string().optional().nullable(),
})

const quoteItemSchema = z.object({
    type: z.enum(['product', 'service']),
    product_id: z.number().optional().nullable(),
    service_id: z.number().optional().nullable(),
    custom_description: z.string().optional().nullable(),
    quantity: z.number().min(0.01, 'A quantidade deve ser maior que zero'),
    original_price: z.number().min(0, 'O preço deve ser positivo'),
    unit_price: z.number().min(0, 'O preço deve ser positivo'),
    discount_percentage: z.number().min(0).max(100),
    discount_mode: z.enum(['percent', 'value']),
    discount_value: z.number().min(0).optional(),
})

interface CustomerEquipmentOption {
    id: number
    manufacturer?: string | null
    brand?: string | null
    model?: string | null
    tag?: string | null
    serial_number?: string | null
    capacity?: number | string | null
    capacity_unit?: string | null
    resolution?: number | string | null
}

interface CustomerDetailsPayload {
    id: number
    equipments?: CustomerEquipmentOption[]
}

interface CatalogComboboxItem {
    id: number
    name: string
    code?: string
    sell_price?: number
    default_price?: number
}

function normalizeProductOption(product: QuoteProductOption): CatalogComboboxItem {
    return {
        id: product.id,
        name: product.name?.trim() || `Produto #${product.id}`,
        sell_price: product.sell_price ?? 0,
    }
}

function normalizeServiceOption(service: QuoteServiceOption): CatalogComboboxItem {
    return {
        id: service.id,
        name: service.name?.trim() || `Serviço #${service.id}`,
        default_price: service.default_price ?? 0,
    }
}

export function QuoteEditPage() {
    const { hasPermission } = useAuthStore()

    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const qc = useQueryClient()

    const generalForm = useForm<z.infer<typeof quoteUpdateGeneralSchema>>({
        resolver: zodResolver(quoteUpdateGeneralSchema),
        defaultValues: {
            seller_id: null,
            valid_until: '',
            source: '',
            observations: '',
            internal_notes: '',
            general_conditions: '',
            discount_percentage: 0,
            discount_amount: 0,
            displacement_value: 0,
            payment_terms: '',
            payment_terms_detail: '',
        },
    })
    const [globalDiscountMode, setGlobalDiscountMode] = useState<DiscountMode>('percent')
    const [addItemEquipmentId, setAddItemEquipmentId] = useState<number | null>(null)
    const [newItem, setNewItem] = useState<QuoteItemForm>({ type: 'service', custom_description: '', quantity: 1, original_price: 0, unit_price: 0, discount_percentage: 0, discount_mode: 'percent', discount_value: 0 })
    const [showAddEquipment, setShowAddEquipment] = useState(false)
    const [showQuickProductService, setShowQuickProductService] = useState(false)
    const [quickPSTab, setQuickPSTab] = useState<'product' | 'service'>('product')
    const [removeEquipmentTarget, setRemoveEquipmentTarget] = useState<{ quoteEquipId: number; equipmentName: string } | null>(null)

    const { data: quote, isLoading } = useQuery<Quote>({
        queryKey: queryKeys.quotes.detail(Number(id!)),
        queryFn: () => quoteApi.detail(Number(id!)),
        enabled: !!id,
    })

    const customerDetailKey = quote?.customer_id ? [...queryKeys.customers.detail(quote.customer_id), 'equipments'] : ['quote-customer-equipments']
    const { data: customerPayload } = useQuery<CustomerDetailsPayload | null>({
        queryKey: customerDetailKey,
        queryFn: async () => {
            if (!quote?.customer_id) {
                return null
            }

            const response = await api.get<{ data?: CustomerDetailsPayload } | CustomerDetailsPayload>(`/customers/${quote.customer_id}`)
            return unwrapData<CustomerDetailsPayload>(response) ?? null
        },
        enabled: !!quote?.customer_id,
    })
    const customerEquipments = customerPayload?.equipments ?? []
    const getEquipmentDisplayName = (equipment: Partial<CustomerEquipmentOption> | null | undefined, fallbackId?: number) =>
        buildEquipmentDisplayName(equipment ?? {}, fallbackId)

    const { data: usersRes } = useQuery({
        queryKey: queryKeys.users.list({ per_page: 200 }),
        queryFn: () => api.get('/users', { params: { per_page: 200 } }).then(r => r.data),
    })
    const users: { id: number; name: string }[] = usersRes?.data ?? (Array.isArray(usersRes) ? usersRes : [])

    const { data: products } = useQuery<QuoteProductOption[]>({ queryKey: queryKeys.products.list({ per_page: 999 }), queryFn: () => api.get('/products', { params: { per_page: 999 } }).then(r => r.data?.data ?? r.data) })
    const { data: services } = useQuery<QuoteServiceOption[]>({ queryKey: queryKeys.services.list({ per_page: 999 }), queryFn: () => api.get('/services', { params: { per_page: 999 } }).then(r => r.data?.data ?? r.data) })
    const productOptions = (products ?? []).map(normalizeProductOption)
    const serviceOptions = (services ?? []).map(normalizeServiceOption)

    useEffect(() => {
        if (quote) {
            const pct = parseFloat(String(quote.discount_percentage)) || 0
            const amt = parseFloat(String(quote.discount_amount)) || 0
            generalForm.reset({
                seller_id: quote.seller_id ?? null,
                valid_until: quote.valid_until ? new Date(quote.valid_until).toISOString().slice(0, 10) : '',
                observations: quote.observations ?? '',
                internal_notes: quote.internal_notes ?? '',
                general_conditions: quote.general_conditions ?? '',
                source: quote.source ?? '',
                discount_percentage: pct,
                discount_amount: amt,
                displacement_value: parseFloat(String(quote.displacement_value)) || 0,
                payment_terms: quote.payment_terms ?? '',
                payment_terms_detail: quote.payment_terms_detail ?? '',
            })
            setGlobalDiscountMode(pct > 0 ? 'percent' : amt > 0 ? 'value' : 'percent')
        }
    }, [quote, generalForm])

    const invalidateAll = () => {
        qc.invalidateQueries({ queryKey: queryKeys.quotes.detail(Number(id!)) })
        qc.invalidateQueries({ queryKey: queryKeys.quotes.all })
        qc.invalidateQueries({ queryKey: queryKeys.quotes.summary })
        qc.invalidateQueries({ queryKey: queryKeys.quotes.advancedSummary })
        broadcastQueryInvalidation(['quotes', 'quotes-summary', 'quotes-advanced-summary', 'dashboard'], 'Orçamento')
    }

    const updateMut = useMutation({
        mutationFn: (data: Record<string, unknown>) => quoteApi.update(Number(id!), data),
        onSuccess: () => { toast.success('Orçamento atualizado!'); invalidateAll() },
        onError: (err: ApiErrorLike) => toast.error(getApiErrorMessage(err, 'Erro ao atualizar')),
    })

    const updateItemMut = useMutation({
        mutationFn: ({ itemId, data }: { itemId: number; data: Partial<QuoteItem> }) => quoteApi.updateItem(itemId, data as Record<string, unknown>),
        onSuccess: () => { toast.success('Item atualizado!'); invalidateAll() },
        onError: (err: ApiErrorLike) => toast.error(getApiErrorMessage(err, 'Erro ao atualizar item')),
    })

    const removeItemMut = useMutation({
        mutationFn: (itemId: number) => quoteApi.deleteItem(itemId),
        onSuccess: () => { toast.success('Item removido!'); invalidateAll() },
        onError: (err: ApiErrorLike) => toast.error(getApiErrorMessage(err, 'Erro ao remover item')),
    })

    const addItemMut = useMutation({
        mutationFn: ({ equipId, data }: { equipId: number; data: QuoteItemForm }) => {
            const parsed = quoteItemSchema.safeParse(data)
            if (!parsed.success) {
                const firstError = parsed.error.errors[0]?.message || 'Erro de validação no item'
                toast.error(firstError)
                return Promise.reject(new Error(firstError))
            }

            const { discount_mode, discount_value, ...payload } = data
            if (discount_mode === 'value' && discount_value > 0 && data.unit_price > 0) {
                payload.discount_percentage = Math.round((discount_value / (data.unit_price * data.quantity)) * 10000) / 100
            }
            return quoteApi.addEquipmentItem(equipId, payload as Record<string, unknown>)
        },
        onSuccess: () => {
            toast.success('Item adicionado!')
            setAddItemEquipmentId(null)
            setNewItem({ type: 'service', custom_description: '', quantity: 1, original_price: 0, unit_price: 0, discount_percentage: 0, discount_mode: 'percent', discount_value: 0 })
            invalidateAll()
        },
        onError: (err: ApiErrorLike) => toast.error(getApiErrorMessage(err, 'Erro ao adicionar item')),
    })

    const addEquipmentMut = useMutation({
        mutationFn: (equipmentId: number) => quoteApi.addEquipment(Number(id!), equipmentId),
        onSuccess: () => { toast.success('Equipamento adicionado!'); setShowAddEquipment(false); invalidateAll() },
        onError: (err: ApiErrorLike) => toast.error(getApiErrorMessage(err, 'Erro ao adicionar equipamento')),
    })

    const removeEquipmentMut = useMutation({
        mutationFn: ({ quoteEquipId }: { quoteEquipId: number }) => quoteApi.removeEquipment(Number(id!), quoteEquipId),
        onSuccess: () => { toast.success('Equipamento removido!'); invalidateAll() },
        onError: (err: ApiErrorLike) => toast.error(getApiErrorMessage(err, 'Erro ao remover equipamento')),
    })

    const handleSaveGeneral = generalForm.handleSubmit((data) => {
            const payload = {
                seller_id: data.seller_id || null,
                valid_until: data.valid_until || null,
                source: data.source || null,
                observations: data.observations || null,
                internal_notes: data.internal_notes || null,
                general_conditions: data.general_conditions || null,
                discount_percentage: globalDiscountMode === 'percent' ? (data.discount_percentage ?? 0) : 0,
                discount_amount: globalDiscountMode === 'value' ? (data.discount_amount ?? 0) : 0,
                displacement_value: data.displacement_value ?? 0,
                payment_terms: data.payment_terms || null,
                payment_terms_detail: data.payment_terms_detail || null,
            }

            updateMut.mutate(payload)
    })

    const isMutable = quote ? isMutableQuoteStatus(quote.status) : true

    useEffect(() => {
        if (quote && !isMutable) {
            toast.error('Orçamento não pode ser editado neste status')
            navigate(`/orcamentos/${id}`)
        }
    }, [quote, isMutable, navigate, id])

    // Verificação de permissão para edição de orçamentos
    if (!hasPermission('quotes.quote.update')) {
        return (
            <div className="max-w-4xl mx-auto py-20 text-center">
                <h2 className="text-lg font-semibold text-content-primary">Acesso Negado</h2>
                <p className="text-content-secondary mt-2">Você não tem permissão para editar orçamentos.</p>
                <Button variant="outline" className="mt-4" onClick={() => navigate('/orcamentos')}>Voltar</Button>
            </div>
        )
    }

    if (isLoading) {
        return (
            <div className="space-y-6">
                <div className="h-8 w-48 bg-surface-100 rounded animate-pulse" />
                {[1, 2].map(i => <div key={i} className="h-40 bg-surface-100 rounded-xl animate-pulse" />)}
            </div>
        )
    }

    if (!quote) {
        return (
            <div className="text-center py-20">
                <p className="text-content-secondary">Orçamento não encontrado</p>
                <Button variant="outline" className="mt-4" onClick={() => navigate('/orcamentos')}>Voltar</Button>
            </div>
        )
    }

    if (!isMutable) {
        return null
    }

    return (
        <div className="space-y-6 max-w-4xl mx-auto">
            <div className="flex items-start gap-3">
                <Button variant="ghost" size="icon" onClick={() => navigate(`/orcamentos/${id}`)} aria-label="Voltar ao orçamento" className="mt-1">
                    <ArrowLeft className="h-5 w-5" />
                </Button>
                <div>
                    <h1 className="text-2xl font-bold text-content-primary">
                        Editar Orçamento {quote.quote_number}
                    </h1>
                    {quote.customer && (
                        <div className="text-sm text-content-secondary mt-1 flex flex-wrap gap-x-4 items-center">
                            <span className="font-medium text-surface-800">{quote.customer.name}</span>
                            {quote.customer.email && <span>{quote.customer.email}</span>}
                            {quote.customer.phone && <span>{quote.customer.phone}</span>}
                        </div>
                    )}
                </div>
            </div>

            {/* Dados gerais */}
            <Card className="p-5">
                <h3 className="text-sm font-semibold text-content-secondary mb-4">Dados Gerais</h3>
                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-content-secondary mb-1">Vendedor</label>
                        <select
                            aria-label="Vendedor responsável"
                            value={generalForm.watch('seller_id') ?? ''}
                            onChange={(e) => generalForm.setValue('seller_id', e.target.value ? Number(e.target.value) : null)}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                        >
                            <option value="">Selecionar vendedor...</option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-content-secondary mb-1">Validade</label>
                        <Input type="date" {...generalForm.register('valid_until')} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-content-secondary mb-1">Desconto</label>
                        <DiscountInput
                            mode={globalDiscountMode}
                            value={globalDiscountMode === 'percent' ? (generalForm.watch('discount_percentage') ?? 0) : (generalForm.watch('discount_amount') ?? 0)}
                            onUpdate={(mode, val) => {
                                setGlobalDiscountMode(mode)
                                if (mode === 'percent') { generalForm.setValue('discount_percentage', val); generalForm.setValue('discount_amount', 0) }
                                else { generalForm.setValue('discount_amount', val); generalForm.setValue('discount_percentage', 0) }
                            }}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-content-secondary mb-1">Deslocamento (R$)</label>
                        <CurrencyInput value={generalForm.watch('displacement_value') ?? 0} onChange={val => generalForm.setValue('displacement_value', val)} />
                    </div>
                    <div className="md:col-span-2">
                        <label htmlFor="quote-observations" className="block text-sm font-medium text-content-secondary mb-1">Observações</label>
                        <textarea id="quote-observations" aria-label="Observações do orçamento" className="w-full rounded-lg border border-default p-3 text-sm min-h-[80px]" {...generalForm.register('observations')} placeholder="Observações visíveis ao cliente" />
                    </div>
                    <div className="md:col-span-2">
                        <label htmlFor="quote-general-conditions" className="block text-sm font-medium text-content-secondary mb-1">Condições Gerais</label>
                        <textarea id="quote-general-conditions" aria-label="Condições Gerais" className="w-full rounded-lg border border-default p-3 text-sm min-h-[80px]" {...generalForm.register('general_conditions')} placeholder="Condições gerais e legais do orçamento" />
                    </div>
                    <div className="md:col-span-2">
                        <label htmlFor="quote-internal-notes" className="block text-sm font-medium text-content-secondary mb-1">Notas Internas</label>
                        <textarea id="quote-internal-notes" aria-label="Notas internas do orçamento" className="w-full rounded-lg border border-default p-3 text-sm min-h-[60px]" {...generalForm.register('internal_notes')} placeholder="Notas visíveis apenas internamente" />
                    </div>
                    <div>
                        <LookupCombobox lookupType="quote-sources" valueField="slug" label="Origem Comercial" value={generalForm.watch('source') ?? ''} onChange={(val) => generalForm.setValue('source', val)} placeholder="Selecione (opcional)" className="w-full" />
                    </div>
                    <div>
                        <LookupCombobox lookupType="payment-terms" valueField="slug" label="Condições de Pagamento" value={generalForm.watch('payment_terms') ?? ''} onChange={(val) => generalForm.setValue('payment_terms', val)} placeholder="Selecione (opcional)" className="w-full" />
                    </div>
                    {generalForm.watch('payment_terms') && (
                        <div className="md:col-span-2">
                            <label className="block text-sm font-medium text-content-secondary mb-1">Detalhes do Pagamento</label>
                            <input {...generalForm.register('payment_terms_detail')}
                                placeholder="Detalhes adicionais sobre forma de pagamento..."
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                    )}
                </div>
                <div className="flex justify-end mt-4">
                    <Button icon={<Save className="h-4 w-4" />} onClick={handleSaveGeneral} disabled={updateMut.isPending}>
                        {updateMut.isPending ? 'Salvando...' : 'Salvar Alterações'}
                    </Button>
                </div>
            </Card>

            {/* Equipamentos e Itens */}
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold text-content-secondary">Equipamentos e Itens</h2>
                <Button variant="outline" size="sm" icon={<Plus className="h-4 w-4" />} onClick={() => setShowAddEquipment(!showAddEquipment)}>
                    Adicionar Equipamento
                </Button>
            </div>

            {showAddEquipment && (
                <Card className="p-4 border-dashed border-brand-300 bg-brand-50/30">
                    <h4 className="text-sm font-medium text-content-primary mb-3">Selecione um equipamento do cliente</h4>
                    {customerEquipments.length === 0 ? (
                        <p className="text-sm text-content-tertiary italic">Nenhum equipamento cadastrado para este cliente.</p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {customerEquipments
                                .filter(ce => !quote.equipments?.some(qe => qe.equipment_id === ce.id))
                                .map(ce => (
                                    <button
                                        key={ce.id}
                                        type="button"
                                        onClick={() => addEquipmentMut.mutate(ce.id)}
                                        disabled={addEquipmentMut.isPending}
                                        className="rounded-lg border border-surface-200 px-3 py-1.5 text-xs font-medium text-surface-600 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 transition-all"
                                    >
                                        <Plus className="inline h-3 w-3 mr-1" />
                                        {getEquipmentDisplayName(ce, ce.id)}
                                    </button>
                                ))
                            }
                            {(customerEquipments || []).filter(ce => !quote.equipments?.some(qe => qe.equipment_id === ce.id)).length === 0 && (
                                <p className="text-sm text-content-tertiary italic">Todos os equipamentos do cliente já foram adicionados.</p>
                            )}
                        </div>
                    )}
                </Card>
            )}

            {(quote.equipments || []).map((eq) => (
                <Card key={eq.id} className="p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="font-medium text-content-primary">
                            {getEquipmentDisplayName(
                                customerEquipments.find((customerEquipment) => customerEquipment.id === eq.equipment_id)
                                ?? eq.equipment,
                                eq.equipment_id
                            )}
                            {eq.description && <span className="text-sm text-content-secondary ml-2">— {eq.description}</span>}
                        </h3>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" icon={<Plus className="h-4 w-4" />} onClick={() => setAddItemEquipmentId(eq.id)}>
                                Adicionar Item
                            </Button>
                            <Button
                                variant="danger"
                                size="sm"
                                icon={<Trash2 className="h-4 w-4" />}
                                onClick={() => setRemoveEquipmentTarget({
                                    quoteEquipId: eq.id,
                                    equipmentName: getEquipmentDisplayName(
                                        customerEquipments.find((customerEquipment) => customerEquipment.id === eq.equipment_id)
                                        ?? eq.equipment,
                                        eq.equipment_id
                                    ),
                                })}
                                disabled={removeEquipmentMut.isPending}
                            >
                                Remover
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-3">
                        {(eq.items || []).map((item) => (
                            <EditableItemRow
                                key={item.id}
                                item={item}
                                onSave={(data) => updateItemMut.mutate({ itemId: item.id, data })}
                                onRemove={() => removeItemMut.mutate(item.id)}
                                saving={updateItemMut.isPending}
                            />
                        ))}
                    </div>

                    {/* Add item form */}
                    {addItemEquipmentId === eq.id && (
                        <div className="mt-4 p-4 border border-dashed border-brand-300 rounded-lg bg-brand-50/30">
                            <h4 className="text-sm font-medium text-content-primary mb-3">Novo Item</h4>
                            <div className="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label htmlFor="new-item-type" className="text-xs text-content-secondary">Tipo</label>
                                    <select id="new-item-type" aria-label="Tipo do item (produto ou serviço)" className="w-full mt-1 rounded-lg border border-default p-2 text-sm" value={newItem.type} onChange={(e) => setNewItem({ ...newItem, type: e.target.value as 'product' | 'service', product_id: null, service_id: null })}>
                                        <option value="product">Produto</option>
                                        <option value="service">Serviço</option>
                                    </select>
                                </div>
                                <div>
                                    <label htmlFor="new-item-product-service" className="text-xs text-content-secondary">{newItem.type === 'product' ? 'Produto' : 'Serviço'}</label>
                                    <div className="flex gap-1 items-center mt-1">
                                        <ItemSearchCombobox
                                            items={newItem.type === 'product' ? productOptions : serviceOptions}
                                            type={newItem.type}
                                            value={newItem.type === 'product' ? newItem.product_id : newItem.service_id}
                                            placeholder={`Pesquisar ${newItem.type === 'product' ? 'produto' : 'serviço'}...`}
                                            className="h-[34px] flex-1 min-w-0"
                                            onSelect={(id) => {
                                                if (newItem.type === 'product') {
                                                    const product = productOptions.find((option) => option.id === id)
                                                    const price = product?.sell_price ?? 0
                                                    setNewItem({ ...newItem, product_id: id, unit_price: price, original_price: price, custom_description: product?.name ?? '' })
                                                } else {
                                                    const service = serviceOptions.find((option) => option.id === id)
                                                    const price = service?.default_price ?? 0
                                                    setNewItem({ ...newItem, service_id: id, unit_price: price, original_price: price, custom_description: service?.name ?? '' })
                                                }
                                            }}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => { setQuickPSTab(newItem.type); setShowQuickProductService(true) }}
                                            title={`Cadastrar novo ${newItem.type === 'product' ? 'produto' : 'serviço'}`}
                                            aria-label={`Cadastrar novo ${newItem.type === 'product' ? 'produto' : 'serviço'}`}
                                            className={`flex items-center justify-center rounded-lg border border-dashed h-[34px] w-[34px] transition-colors ${newItem.type === 'product'
                                                ? 'border-brand-300 bg-brand-50 text-brand-600 hover:bg-brand-100 hover:border-brand-400'
                                                : 'border-emerald-300 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:border-emerald-400'
                                                }`}
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-xs text-content-secondary">Quantidade</label>
                                    <Input type="number" min={0.01} step={0.01} value={newItem.quantity} onChange={(e) => setNewItem({ ...newItem, quantity: parseFloat(e.target.value) || 0 })} />
                                </div>
                                <div>
                                    <label className="text-xs text-content-secondary">Preço Unitário</label>
                                    <CurrencyInput value={newItem.unit_price} onChange={(val) => setNewItem({ ...newItem, unit_price: val })} />
                                </div>
                                <div>
                                    <label className="text-xs text-content-secondary">Desconto</label>
                                    <DiscountInput
                                        mode={newItem.discount_mode}
                                        value={newItem.discount_mode === 'value' ? newItem.discount_value : newItem.discount_percentage}
                                        referenceAmount={newItem.unit_price * newItem.quantity}
                                        onUpdate={(mode, val) => {
                                            setNewItem(prev => ({
                                                ...prev,
                                                discount_mode: mode,
                                                discount_percentage: mode === 'percent' ? val : 0,
                                                discount_value: mode === 'value' ? val : 0,
                                            }))
                                        }}
                                    />
                                </div>
                                <div>
                                    <label className="text-xs text-content-secondary">Descrição</label>
                                    <Input value={newItem.custom_description} onChange={(e) => setNewItem({ ...newItem, custom_description: e.target.value })} />
                                </div>
                            </div>
                            {quote?.customer_id && (newItem.product_id || newItem.service_id) && (
                                <div className="mt-3">
                                    <PriceHistoryHint
                                        customerId={quote.customer_id}
                                        type={newItem.type}
                                        referenceId={newItem.product_id || newItem.service_id || undefined}
                                        onApplyPrice={(price) => setNewItem(prev => ({ ...prev, unit_price: price }))}
                                    />
                                </div>
                            )}
                            <div className="flex gap-2 justify-end mt-3">
                                <Button variant="outline" size="sm" onClick={() => setAddItemEquipmentId(null)}>Cancelar</Button>
                                <Button size="sm" onClick={() => addItemMut.mutate({ equipId: eq.id, data: newItem })} disabled={addItemMut.isPending}>
                                    {addItemMut.isPending ? 'Adicionando...' : 'Adicionar'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>
            ))}

            <QuickProductServiceModal
                open={showQuickProductService}
                onOpenChange={setShowQuickProductService}
                defaultTab={quickPSTab}
            />

            {removeEquipmentTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setRemoveEquipmentTarget(null)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-sm mx-4 shadow-elevated" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">Remover equipamento</h3>
                        <p className="text-content-secondary text-sm mb-6">
                            Remover <strong>{removeEquipmentTarget.equipmentName}</strong> e todos os itens vinculados? Esta ação não pode ser desfeita.
                        </p>
                        <div className="flex gap-3 justify-end">
                            <Button variant="outline" size="sm" onClick={() => setRemoveEquipmentTarget(null)}>Cancelar</Button>
                            <Button
                                variant="danger"
                                size="sm"
                                onClick={() => {
                                    removeEquipmentMut.mutate({ quoteEquipId: removeEquipmentTarget.quoteEquipId })
                                    setRemoveEquipmentTarget(null)
                                }}
                                disabled={removeEquipmentMut.isPending}
                            >
                                {removeEquipmentMut.isPending ? 'Removendo...' : 'Remover'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}

function EditableItemRow({ item, onSave, onRemove, saving }: {
    item: QuoteItem
    onSave: (data: Partial<QuoteItem>) => void
    onRemove: () => void
    saving: boolean
}) {
    const [editing, setEditing] = useState(false)
    const [qty, setQty] = useState(item.quantity)
    const [price, setPrice] = useState(parseFloat(String(item.unit_price)))
    const [disc, setDisc] = useState(parseFloat(String(item.discount_percentage)) || 0)
    const [discMode, setDiscMode] = useState<DiscountMode>('percent')
    const [discValue, setDiscValue] = useState(0)
    const [confirmDelete, setConfirmDelete] = useState(false)

    const name = item.custom_description || item.product?.name || item.service?.name || '—'
    const icon = item.type === 'product' ? <Package className="h-4 w-4 text-blue-500" /> : <Wrench className="h-4 w-4 text-amber-500" />

    if (!editing) {
        return (
            <div className="flex items-center justify-between p-3 rounded-lg bg-surface-50 group">
                <div className="flex items-center gap-2 flex-1">
                    {icon}
                    <span className="text-sm font-medium">{name}</span>
                    <span className="text-xs text-content-tertiary">×{item.quantity}</span>
                    <span className="text-xs text-content-tertiary">@ {formatCurrency(item.unit_price)}</span>
                    {parseFloat(String(item.discount_percentage)) > 0 && <span className="text-xs text-red-500">-{item.discount_percentage}%</span>}
                </div>
                <div className="flex items-center gap-2">
                    <span className="font-medium text-sm">{formatCurrency(item.subtotal)}</span>
                    <button type="button" onClick={() => setEditing(true)} className="p-1 rounded hover:bg-surface-200 opacity-0 group-hover:opacity-100 transition-opacity text-content-secondary" aria-label="Editar item">✏️</button>
                    {confirmDelete ? (
                        <div className="flex gap-1">
                            <button type="button" onClick={onRemove} className="text-xs text-red-600 font-medium" aria-label="Confirmar remoção do item">Sim</button>
                            <button type="button" onClick={() => setConfirmDelete(false)} className="text-xs text-content-secondary" aria-label="Cancelar remoção">Não</button>
                        </div>
                    ) : (
                        <button type="button" onClick={() => setConfirmDelete(true)} className="p-1 rounded hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-opacity text-red-500" aria-label="Remover item">
                            <Trash2 className="h-3.5 w-3.5" aria-hidden />
                        </button>
                    )}
                </div>
            </div>
        )
    }

    const handleSaveItem = () => {
        let finalDisc = disc
        if (discMode === 'value' && discValue > 0 && price > 0) {
            finalDisc = Math.round((discValue / (price * qty)) * 10000) / 100
        }
        onSave({ quantity: qty, unit_price: price, discount_percentage: finalDisc })
        setEditing(false)
    }

    return (
        <div className="p-3 rounded-lg border border-brand-300 bg-brand-50/20">
            <div className="grid gap-2 grid-cols-3">
                <div>
                    <label className="text-xs text-content-secondary">Quantidade</label>
                    <Input type="number" min={0.01} step={0.01} value={qty} onChange={(e) => setQty(parseFloat(e.target.value) || 0)} />
                </div>
                <div>
                    <label className="text-xs text-content-secondary">Preço Unitário</label>
                    <CurrencyInput value={price} onChange={(val) => setPrice(val)} />
                </div>
                <div>
                    <label className="text-xs text-content-secondary">Desconto</label>
                    <DiscountInput
                        mode={discMode}
                        value={discMode === 'percent' ? disc : discValue}
                        referenceAmount={price * qty}
                        onUpdate={(mode, val) => {
                            setDiscMode(mode)
                            if (mode === 'percent') { setDisc(val); setDiscValue(0) }
                            else { setDiscValue(val); setDisc(0) }
                        }}
                    />
                </div>
            </div>
            <div className="flex gap-2 justify-end mt-2">
                <Button variant="outline" size="sm" onClick={() => setEditing(false)}>Cancelar</Button>
                <Button size="sm" disabled={saving} onClick={handleSaveItem}>
                    Salvar
                </Button>
            </div>
        </div>
    )
}
