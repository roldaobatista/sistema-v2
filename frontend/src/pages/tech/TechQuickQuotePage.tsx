import { useState, useEffect, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, Trash2, Send, Save, User, Loader2, ArrowLeft,
    ShoppingCart, Calculator, CheckCircle2,
} from 'lucide-react'
import { getApiErrorMessage, formatCurrency } from '@/lib/utils'
import api, { unwrapData } from '@/lib/api'
import { useAuthStore } from '@/stores/auth-store'
import { CustomerAsyncSelect, type CustomerAsyncSelectItem } from '@/components/common/CustomerAsyncSelect'
import { toast } from 'sonner'
import { CurrencyInputInline } from '@/components/common/CurrencyInput'
import { safeArray } from '@/lib/safe-array'

const itemSchema = z.object({
    type: z.enum(['service', 'product']),
    id: z.number(),
    name: z.string(),
    quantity: z.number().min(0.01),
    unit_price: z.number().min(0),
    service_id: z.number().optional(),
    product_id: z.number().optional(),
})

const quickQuoteSchema = z.object({
    customer_id: z.number({ required_error: 'Selecione um cliente' }),
    equipment_id: z.number().min(1, 'Cliente precisa de pelo menos 1 equipamento'),
    discount_percentage: z.number().min(0).max(100).optional(),
    observations: z.string().max(5000).optional(),
    items: z.array(itemSchema).min(1, 'Adicione pelo menos um item'),
})

type QuickQuoteFormData = z.infer<typeof quickQuoteSchema>

interface Customer extends CustomerAsyncSelectItem {
    id: number
    name: string
}

interface Service {
    id: number
    name: string
    price?: number
}

interface Product {
    id: number
    name: string
    price?: number
}

interface EquipmentResponseItem {
    id: number
    name?: string | null
    tag?: string | null
    brand?: string | null
    model?: string | null
}

interface QuoteResponse {
    id: number
    quote_number?: string | null
}

export default function TechQuickQuotePage() {
    const navigate = useNavigate()
    const { hasPermission } = useAuthStore()
    const canCreate = hasPermission('quotes.quote.create')

    const [customer, setCustomer] = useState<Customer | null>(null)
    const [customerEquipments, setCustomerEquipments] = useState<{ id: number; name: string }[]>([])

    // Search states
    const [showServiceSearch, setShowServiceSearch] = useState(false)
    const [showProductSearch, setShowProductSearch] = useState(false)
    const [serviceSearch, setServiceSearch] = useState('')
    const [productSearch, setProductSearch] = useState('')
    const [services, setServices] = useState<Service[]>([])
    const [products, setProducts] = useState<Product[]>([])

    // Success states
    const [savedQuoteId, setSavedQuoteId] = useState<number | null>(null)
    const [savedQuoteNumber, setSavedQuoteNumber] = useState<string | null>(null)

    // Form setup
    const {
        control,
        handleSubmit,
        watch,
        setValue,
        formState: { isSubmitting, errors },
    } = useForm<QuickQuoteFormData>({
        resolver: zodResolver(quickQuoteSchema),
        defaultValues: {
            items: [],
            discount_percentage: 0,
            observations: '',
        },
    })

    const { fields, append, remove, update } = useFieldArray({
        control,
        name: 'items',
    })

    const watchedItems = watch('items')
    const watchedDiscount = watch('discount_percentage') ?? 0

    // Calculations
    const subtotal = useMemo(() =>
        watchedItems.reduce((sum, it) => sum + (it.quantity || 0) * (it.unit_price || 0), 0), [watchedItems])
    const discountAmount = subtotal * (watchedDiscount / 100)
    const total = subtotal - discountAmount

    // Data fetching logic
    useEffect(() => {
        if (!customer?.id) {
            setCustomerEquipments([])
            setValue('customer_id', 0)
            setValue('equipment_id', 0)
            return
        }

        setValue('customer_id', customer.id, { shouldValidate: true })

        api.get('/equipments', { params: { customer_id: customer.id, per_page: 100 } })
            .then((res) => {
                const equipments = safeArray<EquipmentResponseItem>(unwrapData(res))
                const processed = equipments.map((equipment: EquipmentResponseItem) => ({
                    id: equipment.id,
                    name: equipment.name ?? equipment.tag ?? ([equipment.brand, equipment.model].filter(Boolean).join(' ') || `Equipamento #${equipment.id}`),
                }))
                setCustomerEquipments(processed)
                if (processed.length > 0) {
                    setValue('equipment_id', processed[0].id, { shouldValidate: true })
                } else {
                    setValue('equipment_id', 0)
                }
            })
            .catch(() => setCustomerEquipments([]))
    }, [customer, setValue])

    useEffect(() => {
        if (!serviceSearch || serviceSearch.length < 2) {
            setServices([])
            return
        }
        const t = setTimeout(() => {
            api.get('/services', { params: { search: serviceSearch, per_page: 50 } })
                .then((res) => setServices(safeArray<Service>(unwrapData(res))))
                .catch(() => setServices([]))
        }, 300)
        return () => clearTimeout(t)
    }, [serviceSearch])

    useEffect(() => {
        if (!productSearch || productSearch.length < 2) {
            setProducts([])
            return
        }
        const t = setTimeout(() => {
            api.get('/products', { params: { search: productSearch, per_page: 50 } })
                .then((res) => setProducts(safeArray<Product>(unwrapData(res))))
                .catch(() => setProducts([]))
        }, 300)
        return () => clearTimeout(t)
    }, [productSearch])

    const addService = (s: Service) => {
        append({
            type: 'service',
            id: s.id,
            name: s.name,
            quantity: 1,
            unit_price: s.price ?? 0,
            service_id: s.id,
        })
        setShowServiceSearch(false)
        setServiceSearch('')
    }

    const addProduct = (p: Product) => {
        append({
            type: 'product',
            id: p.id,
            name: p.name,
            quantity: 1,
            unit_price: p.price ?? 0,
            product_id: p.id,
        })
        setShowProductSearch(false)
        setProductSearch('')
    }

    const [isSavingDraft, setIsSavingDraft] = useState(false)
    const [isSending, setIsSending] = useState(false)

    // Handlers
    const onSaveDraft = async (data: QuickQuoteFormData) => {
        setIsSavingDraft(true)
        try {
            // "Salvar Rascunho" uses old multi-endpoint payload format
            const payload = {
                customer_id: data.customer_id,
                discount_percentage: data.discount_percentage,
                observations: data.observations || undefined,
                equipments: [{
                    equipment_id: data.equipment_id,
                    description: 'Orçamento rápido',
                    items: data.items.map((it) => ({
                        type: it.type,
                        product_id: it.type === 'product' ? it.product_id : undefined,
                        service_id: it.type === 'service' ? it.service_id : undefined,
                        quantity: it.quantity,
                        original_price: it.unit_price,
                        unit_price: it.unit_price,
                        discount_percentage: 0,
                    })),
                }],
            }
            const response = await api.post('/quotes', payload)
            const quote = unwrapData<QuoteResponse>(response)
            setSavedQuoteId(quote.id)
            setSavedQuoteNumber(quote.quote_number ?? `#${quote.id}`)
            toast.success('Orçamento salvo como rascunho!')
        } catch (err) {
            toast.error(getApiErrorMessage(err, 'Erro ao salvar rascunho'))
        } finally {
            setIsSavingDraft(false)
        }
    }

    const onSendQuote = async (data: QuickQuoteFormData) => {
        setIsSending(true)
        try {
            // "Enviar ao Cliente" uses new quick-quotes single-shot endpoint
            const payload = {
                ...data,
                send_to_client: true,
            }
            const response = await api.post('/technician/quick-quotes', payload)
            const result = unwrapData<{ id: number; quote_number?: string }>(response)
            setSavedQuoteId(result.id)
            setSavedQuoteNumber(result.quote_number ?? `#${result.id}`)
            toast.success('Orçamento enviado ao cliente!')
        } catch (err) {
            toast.error(getApiErrorMessage(err, 'Erro ao enviar orçamento'))
        } finally {
            setIsSending(false)
        }
    }

    if (savedQuoteId && savedQuoteNumber) {
        return (
            <div className="flex flex-col h-full bg-surface-50">
                <div className="flex-1 px-4 py-8 flex flex-col items-center justify-center gap-4">
                    <CheckCircle2 className="w-16 h-16 text-emerald-500" />
                    <div className="text-center">
                        <h2 className="text-xl font-bold text-foreground">Orçamento Salvo!</h2>
                        <p className="text-sm text-surface-500 mt-1">Nº {savedQuoteNumber}</p>
                    </div>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => navigate('/tech')}
                            className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 text-white font-medium shadow-sm hover:bg-brand-700 transition"
                        >
                            <ArrowLeft className="w-4 h-4" />
                            Painel Principal
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setSavedQuoteId(null);
                                setSavedQuoteNumber(null);
                                setCustomer(null);
                                setValue('items', []);
                                setValue('discount_percentage', 0);
                                setValue('observations', '');
                            }}
                            className="px-4 py-2.5 rounded-xl bg-white border border-surface-200 text-surface-700 font-medium shadow-sm hover:bg-surface-50 transition"
                        >
                            Novo Orçamento
                        </button>
                    </div>
                </div>
            </div>
        )
    }

    if (!canCreate) {
        return (
            <div className="flex justify-center p-8 text-center text-surface-500">
                Você não tem permissão para criar orçamentos.
            </div>
        )
    }

    return (
        <div className="flex flex-col h-full bg-surface-50/50">
            <header className="bg-white px-4 pt-3 pb-3 border-b border-border shadow-sm sticky top-0 z-10">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => navigate('/tech')}
                        aria-label="Voltar para o painel tecnico"
                        className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5 text-surface-600" />
                    </button>
                    <h1 className="text-lg font-bold text-foreground">Orçamento em Campo</h1>
                </div>
            </header>

            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                <form className="space-y-4 h-full flex flex-col">
                    {/* Clientes e Equipamentos */}
                    <div className="bg-white rounded-xl shadow-sm border border-surface-200 p-4">
                        <CustomerAsyncSelect
                            label="Cliente Solicitante"
                            customerId={customer?.id ?? null}
                            placeholder="Buscar cliente..."
                            onChange={(selectedCustomer) => setCustomer(selectedCustomer)}
                        />
                        {errors.customer_id && (
                            <p className="text-sm text-red-500 mt-1">{errors.customer_id.message}</p>
                        )}

                        {customer && (
                            <div className="mt-4 pt-4 border-t border-surface-100">
                                <div className="flex items-center gap-2 mb-2">
                                    <User className="w-4 h-4 text-brand-500" />
                                    <span className="font-semibold text-foreground">{customer.name}</span>
                                </div>
                                {customer.phone && <p className="text-xs text-surface-500">{customer.phone}</p>}

                                {customerEquipments.length === 0 ? (
                                    <p className="text-xs text-red-500 font-medium mt-2">
                                        Cliente não possui equipamentos vinculados.
                                    </p>
                                ) : (
                                    <p className="text-xs text-emerald-600 font-medium mt-2">
                                        Aplicando ao equipamento: {customerEquipments[0].name}
                                    </p>
                                )}
                                {errors.equipment_id && (
                                    <p className="text-sm text-red-500 mt-1">{errors.equipment_id.message}</p>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setCustomer(null)}
                                    className="text-xs text-brand-600 font-medium mt-3"
                                >
                                    Trocar cliente selecionado
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Itens */}
                    <div className="bg-white rounded-xl shadow-sm border border-surface-200 p-4">
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-sm font-semibold text-surface-900 flex items-center gap-2">
                                <ShoppingCart className="w-4 h-4" />
                                Itens do Orçamento
                            </span>
                            <div className="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowServiceSearch(true)}
                                    disabled={!customer}
                                    className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-surface-100 text-brand-700 text-xs font-semibold hover:bg-brand-50 transition shadow-sm disabled:opacity-50"
                                >
                                    <Plus className="w-3.5 h-3.5" /> Serviço
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setShowProductSearch(true)}
                                    disabled={!customer}
                                    className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-surface-100 text-brand-700 text-xs font-semibold hover:bg-brand-50 transition shadow-sm disabled:opacity-50"
                                >
                                    <Plus className="w-3.5 h-3.5" /> Produto
                                </button>
                            </div>
                        </div>

                        {/* Dropdown Buscas */}
                        {showServiceSearch && (
                            <div className="mb-4 p-3 rounded-xl bg-surface-50 border border-surface-200 shadow-inner">
                                <input
                                    type="text"
                                    value={serviceSearch}
                                    onChange={(e) => setServiceSearch(e.target.value)}
                                    placeholder="Digite o nome do serviço..."
                                    className="w-full px-3 py-2.5 rounded-lg border-surface-300 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition mb-2 bg-white"
                                    autoFocus
                                />
                                <ul className="max-h-40 overflow-y-auto space-y-1">
                                    {(services || []).map((s) => (
                                        <li key={`serv-${s.id}`}>
                                            <button
                                                type="button"
                                                onClick={() => addService(s)}
                                                className="w-full flex justify-between items-center px-3 py-2 rounded-lg text-sm hover:bg-brand-50 transition"
                                            >
                                                <span className="font-medium text-surface-800">{s.name}</span>
                                                {s.price != null && <span className="text-surface-500">{formatCurrency(s.price)}</span>}
                                            </button>
                                        </li>
                                    ))}
                                    {serviceSearch.length > 2 && services.length === 0 && (
                                        <li className="text-sm text-surface-500 text-center py-2">Nenhum serviço encontrado</li>
                                    )}
                                </ul>
                                <button
                                    type="button"
                                    onClick={() => { setShowServiceSearch(false); setServiceSearch('') }}
                                    className="w-full text-xs text-center text-surface-500 font-medium py-2 mt-1 hover:text-surface-700"
                                >
                                    Cancelar Busca
                                </button>
                            </div>
                        )}

                        {showProductSearch && (
                            <div className="mb-4 p-3 rounded-xl bg-surface-50 border border-surface-200 shadow-inner">
                                <input
                                    type="text"
                                    value={productSearch}
                                    onChange={(e) => setProductSearch(e.target.value)}
                                    placeholder="Digite o nome do produto..."
                                    className="w-full px-3 py-2.5 rounded-lg border-surface-300 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition mb-2 bg-white"
                                    autoFocus
                                />
                                <ul className="max-h-40 overflow-y-auto space-y-1">
                                    {(products || []).map((p) => (
                                        <li key={`prod-${p.id}`}>
                                            <button
                                                type="button"
                                                onClick={() => addProduct(p)}
                                                className="w-full flex justify-between items-center px-3 py-2 rounded-lg text-sm hover:bg-brand-50 transition"
                                            >
                                                <span className="font-medium text-surface-800">{p.name}</span>
                                                {p.price != null && <span className="text-surface-500">{formatCurrency(p.price)}</span>}
                                            </button>
                                        </li>
                                    ))}
                                    {productSearch.length > 2 && products.length === 0 && (
                                        <li className="text-sm text-surface-500 text-center py-2">Nenhum produto encontrado</li>
                                    )}
                                </ul>
                                <button
                                    type="button"
                                    onClick={() => { setShowProductSearch(false); setProductSearch('') }}
                                    className="w-full text-xs text-center text-surface-500 font-medium py-2 mt-1 hover:text-surface-700"
                                >
                                    Cancelar Busca
                                </button>
                            </div>
                        )}

                        {/* Itens adicionados */}
                        {fields.length === 0 ? (
                            <div className="p-6 bg-surface-50 rounded-xl border border-dashed border-surface-200 flex flex-col items-center justify-center text-center">
                                <ShoppingCart className="w-8 h-8 text-surface-300 mb-2" />
                                <p className="text-sm text-surface-500">O orçamento está vazio.<br/>Adicione produtos ou serviços acima.</p>
                            </div>
                        ) : (
                            <ul className="space-y-3">
                                {fields.map((it, idx) => (
                                    <li
                                        key={it.id}
                                        className="bg-surface-50 rounded-xl p-3 border border-surface-100 flex flex-col sm:flex-row gap-3 shadow-xs"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-semibold text-surface-900 truncate mb-2">{it.name}</p>
                                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                <div>
                                                    <label className="text-[10px] uppercase text-surface-500 font-semibold mb-1 block">Qtd</label>
                                                    <input
                                                        type="number"
                                                        min={0.01}
                                                        step={1}
                                                        value={it.quantity}
                                                        onChange={(e) => update(idx, { ...it, quantity: parseFloat(e.target.value) || 1 })}
                                                        className="w-full px-2 py-1.5 rounded-lg border-surface-300 bg-white text-sm"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="text-[10px] uppercase text-surface-500 font-semibold mb-1 block">Valor Unit.</label>
                                                    <CurrencyInputInline
                                                        value={it.unit_price}
                                                        onChange={(val) => update(idx, { ...it, unit_price: val })}
                                                        className="w-full px-2 py-1.5 rounded-lg border-surface-300 bg-white text-sm"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex flex-row sm:flex-col items-center justify-between sm:justify-center border-t sm:border-t-0 sm:border-l border-surface-200 pt-3 sm:pt-0 sm:pl-3 w-full sm:w-24">
                                            <div className="text-right sm:text-center w-full">
                                                <span className="text-[10px] uppercase text-surface-500 font-semibold block sm:mb-1">Total</span>
                                                <span className="text-sm font-bold text-surface-900">
                                                    {formatCurrency(it.quantity * it.unit_price)}
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => remove(idx)}
                                                className="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition mt-0 sm:mt-2 bg-white border border-surface-200 sm:border-transparent flex-shrink-0"
                                                aria-label="Remover item"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {errors.items && (
                            <p className="text-sm text-red-500 font-medium mt-2">{errors.items.message}</p>
                        )}
                    </div>

                    {/* Resumo e Observações */}
                    <div className="bg-white rounded-xl shadow-sm border border-surface-200 p-4">
                        <div className="flex items-center gap-2 mb-4 pb-3 border-b border-surface-100">
                            <Calculator className="w-4 h-4 text-brand-500" />
                            <span className="text-sm font-semibold text-surface-900">Fechamento</span>
                        </div>
                        <div className="space-y-3 text-sm">
                            <div className="flex justify-between items-center">
                                <span className="text-surface-600 font-medium">Subtotal</span>
                                <span className="font-semibold">{formatCurrency(subtotal)}</span>
                            </div>
                            <div className="flex justify-between items-center bg-surface-50 p-2 rounded-lg -mx-2">
                                <span className="text-surface-600 font-medium">Desc. Comercial (%)</span>
                                <Controller
                                    control={control}
                                    name="discount_percentage"
                                    render={({ field }) => (
                                        <input
                                            type="number"
                                            min={0}
                                            max={100}
                                            step={0.5}
                                            value={field.value}
                                            onChange={(e) => field.onChange(parseFloat(e.target.value) || 0)}
                                            className="w-20 px-2 py-1.5 rounded border border-surface-300 bg-white text-sm text-right"
                                        />
                                    )}
                                />
                            </div>
                            {discountAmount > 0 && (
                                <div className="flex justify-between items-center text-emerald-600">
                                    <span className="font-medium">Abatimento</span>
                                    <span className="font-semibold">-{formatCurrency(discountAmount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between items-center pt-3 mt-1 border-t border-surface-200">
                                <span className="text-base font-bold text-surface-900">Valor Final</span>
                                <span className="text-base font-bold text-brand-600">{formatCurrency(total)}</span>
                            </div>
                        </div>

                        <div className="mt-5 pt-4 border-t border-surface-100">
                            <label className="text-xs font-semibold uppercase text-surface-600 mb-2 block">
                                Informações Adicionais (Público)
                            </label>
                            <Controller
                                control={control}
                                name="observations"
                                render={({ field }) => (
                                    <textarea
                                        {...field}
                                        placeholder="Ex: Pagamento na entrega, prazo de 2 dias..."
                                        rows={2}
                                        className="w-full px-3 py-2.5 rounded-xl border border-surface-200 bg-surface-50 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition resize-none placeholder:text-surface-400"
                                    />
                                )}
                            />
                        </div>
                    </div>

                    {/* Ações (Espaçadores) */}
                    <div className="h-6"></div>
                </form>
            </div>

            {/* Bottom bar sticky actions */}
            <div className="bg-white border-t border-border p-4 shadow-[0_-4px_6px_-1px_rgb(0,0,0,0.05)] z-20 sticky bottom-0">
                <div className="flex flex-col sm:flex-row gap-3">
                    <button
                        type="button"
                        onClick={handleSubmit(onSaveDraft)}
                        disabled={isSavingDraft || isSending || isSubmitting}
                        className="flex-1 flex items-center justify-center gap-2 py-3.5 rounded-xl bg-surface-100 border border-surface-200 text-surface-700 font-semibold shadow-sm hover:bg-surface-200 hover:text-surface-900 transition disabled:opacity-50"
                    >
                        {isSavingDraft ? <Loader2 className="w-5 h-5 animate-spin" /> : <Save className="w-5 h-5" />}
                        Salvar Rascunho Interno
                    </button>
                    <button
                        type="button"
                        onClick={handleSubmit(onSendQuote)}
                        disabled={isSavingDraft || isSending || isSubmitting}
                        className="flex-[1.5] flex items-center justify-center gap-2 py-3.5 rounded-xl bg-brand-600 text-white font-semibold shadow-md active:scale-[0.98] hover:bg-brand-700 transition disabled:opacity-50"
                    >
                        {isSending ? <Loader2 className="w-5 h-5 animate-spin text-white" /> : <Send className="w-5 h-5" />}
                        Finalizar e Enviar (Validado)
                    </button>
                </div>
            </div>
        </div>
    )
}
