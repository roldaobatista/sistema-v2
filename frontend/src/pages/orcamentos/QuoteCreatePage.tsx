import React, { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import {
    ArrowLeft, ArrowRight, Plus, Trash2, Save,
} from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { customerApi } from '@/lib/customer-api'
import { quoteApi } from '@/lib/quote-api'
import { queryKeys } from '@/lib/query-keys'
import { Button } from '@/components/ui/button'
import { CustomerAsyncSelect, type CustomerAsyncSelectItem } from '@/components/common/CustomerAsyncSelect'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { formatCurrency } from '@/lib/utils'
import { DiscountInput, type DiscountMode } from '@/components/common/DiscountInput'
import { useAuthStore } from '@/stores/auth-store'
import PriceHistoryHint from '@/components/common/PriceHistoryHint'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import QuickEquipmentModal from '@/components/common/QuickEquipmentModal'
import QuickProductServiceModal from '@/components/common/QuickProductServiceModal'
import { ItemSearchCombobox } from '@/components/common/ItemSearchCombobox'
import { applyQuoteTemplateDefaults } from '@/features/quotes/templates'
import * as z from 'zod'
import type { ApiErrorLike } from '@/types/common'
import type { QuoteCreateStep, QuoteEquipmentBlockForm, QuoteItemRowForm, QuoteProductOption, QuoteServiceOption, QuoteTemplate } from '@/types/quote'
import { buildEquipmentDisplayName } from '@/lib/equipment-display'

const quoteCreateSchema = z.object({
    customer_id: z.number({ required_error: 'Cliente é obrigatório' }),
    seller_id: z.number().optional(),
    template_id: z.number().optional(),
    source: z.string().optional().nullable(),
    valid_until: z.string().optional().nullable(),
    discount_percentage: z.number().min(0).max(100).optional(),
    discount_amount: z.number().min(0).optional(),
    displacement_value: z.number().min(0).optional(),
    observations: z.string().optional().nullable(),
    internal_notes: z.string().optional().nullable(),
    general_conditions: z.string().optional().nullable(),
    payment_terms: z.string().optional().nullable(),
    payment_terms_detail: z.string().optional().nullable(),
    equipments: z.array(z.object({
        equipment_id: z.number(),
        description: z.string().optional(),
        items: z.array(z.object({
            type: z.enum(['product', 'service']),
            product_id: z.number().optional().nullable(),
            service_id: z.number().optional().nullable(),
            quantity: z.number().min(0.01, 'A quantidade deve ser maior que zero'),
            original_price: z.number().min(0, 'O preço deve ser positivo'),
            unit_price: z.number().min(0, 'O preço deve ser positivo'),
            discount_percentage: z.number().min(0).max(100),
        })).min(1, 'Cada equipamento deve ter pelo menos um item'),
    })).min(1, 'Adicione pelo menos um equipamento'),
})

// Strings constant for easy localization in the future
const STRINGS = {
    newQuote: 'Novo Orçamento',
    fillInfo: 'Preencha as informações do orçamento',
    stepCustomer: 'Cliente',
    stepEquipments: 'Equipamentos e Itens',
    stepReview: 'Revisão',
    selectCustomer: 'Selecionar Cliente',
    searchPlaceholder: 'Pesquisar cliente por nome, CPF/CNPJ...',
    validity: 'Validade',
    observations: 'Observações',
    next: 'Próximo',
    back: 'Voltar',
    customerEquipments: 'Equipamentos do Cliente',
    noEquipments: 'Nenhum equipamento cadastrado para este cliente',
    descriptionPlaceholder: 'Descrição do que será feito neste equipamento...',
    addProduct: '+ Produto',
    addService: '+ Serviço',
    type: 'Tipo',
    item: 'Item',
    quantity: 'Qtd',
    unitPrice: 'Preço Unit.',
    discount: 'Desconto',
    subtotal: 'Subtotal',
    summary: 'Resumo do Orçamento',
    totalItems: 'Total de itens',
    globalDiscount: 'Desconto global',
    total: 'Total',
    saveQuote: 'Salvar Orçamento',
    saving: 'Salvando...',
    errorSaving: 'Erro ao salvar orçamento. Verifique os dados e tente novamente.',
}

type CustomerOption = CustomerAsyncSelectItem

interface SettingItem {
    key: string
    value: string
}

interface CustomerEquipmentOption {
    id: number
    manufacturer?: string | null
    brand?: string | null
    model?: string | null
    serial_number?: string | null
    capacity?: number | string | null
    capacity_unit?: string | null
    resolution?: number | string | null
}

interface CustomerDetailsPayload extends CustomerOption {
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

export function QuoteCreatePage() {
    const { hasPermission } = useAuthStore()
    const [searchParams] = useSearchParams()
    const customerIdFromUrl = searchParams.get('customer_id')



    const navigate = useNavigate()
    const [step, setStep] = useState<QuoteCreateStep>('customer')
    const [customerId, setCustomerId] = useState<number | null>(customerIdFromUrl ? Number(customerIdFromUrl) : null)
    const [validUntil, setValidUntil] = useState('')
    const [discountPercentage, setDiscountPercentage] = useState(0)
    const [discountAmount, setDiscountAmount] = useState(0)
    const [globalDiscountMode, setGlobalDiscountMode] = useState<DiscountMode>('percent')
    const [displacementValue, setDisplacementValue] = useState(0)
    const [observations, setObservations] = useState('')
    const [internalNotes, setInternalNotes] = useState('')
    const [generalConditions, setGeneralConditions] = useState('')
    const [source, setSource] = useState<string>('')
    const [sellerId, setSellerId] = useState<number | null>(null)
    const [templateId, setTemplateId] = useState<number | null>(null)
    const [paymentTerms, setPaymentTerms] = useState<string>('')
    const [paymentTermsDetail, setPaymentTermsDetail] = useState('')
    const [blocks, setBlocks] = useState<QuoteEquipmentBlockForm[]>([])
    const [errorMsg, setErrorMsg] = useState<string | null>(null)
    const [showQuickEquipmentModal, setShowQuickEquipmentModal] = useState(false)
    const [showQuickProductService, setShowQuickProductService] = useState(false)
    const [quickPSTab, setQuickPSTab] = useState<'product' | 'service'>('product')

    const { data: preselectedCustomer } = useQuery<CustomerOption | undefined>({
        queryKey: customerIdFromUrl ? [...queryKeys.customers.detail(Number(customerIdFromUrl)), 'preselected'] : ['customer-preselected'],
        queryFn: async () => {
            const customer = await customerApi.detail(Number(customerIdFromUrl!))
            if (!customer) {
                return undefined
            }

            return {
                id: customer.id,
                name: customer.name,
                document: customer.document ?? undefined,
            }
        },
        enabled: !!customerIdFromUrl,
    })

    // Fetch default validity days from settings
    const { data: settingsRes } = useQuery({
        queryKey: queryKeys.settings,
        queryFn: () => api.get('/settings', { params: { group: 'quotes' } }),
    })

    const customer = preselectedCustomer as CustomerOption | undefined
    useEffect(() => {
        if (customer?.id && !customerId) {
            setCustomerId(customer.id)
        }
    }, [customer, customerId])

    // Auto-populate valid_until from setting
    useEffect(() => {
        if (validUntil) return // don't override if user already set it
        const settings = (settingsRes?.data?.data ?? settingsRes?.data ?? []) as SettingItem[]
        const daysSetting = settings.find((s) => s.key === 'quote_default_validity_days')
        const days = daysSetting ? parseInt(daysSetting.value) : 30
        if (days > 0) {
            const date = new Date()
            date.setDate(date.getDate() + days)
            setValidUntil(date.toISOString().slice(0, 10))
        }
    }, [settingsRes])


    const { data: customerPayload } = useQuery<CustomerDetailsPayload | null>({
        queryKey: customerId ? [...queryKeys.customers.detail(customerId), 'equipments'] : ['equipments-for-customer'],
        queryFn: async () => {
            const response = await api.get<{ data?: CustomerDetailsPayload } | CustomerDetailsPayload>(`/customers/${customerId!}`)
            return unwrapData<CustomerDetailsPayload>(response) ?? null
        },
        enabled: !!customerId,
    })
    const customerEquipments = customerPayload?.equipments ?? []
    const canViewTemplates = hasPermission('quotes.quote.view')

    const { data: templatesData } = useQuery<QuoteTemplate[]>({
        queryKey: queryKeys.quotes.templates,
        queryFn: () => quoteApi.templates(),
        enabled: canViewTemplates,
    })
    const templates = templatesData ?? []
    const selectedTemplate = templates.find((template) => template.id === templateId) ?? null

    const { data: sellers = [] } = useQuery<CustomerOption[]>({
        queryKey: queryKeys.users.list({ per_page: 200 }),
        queryFn: async () =>
            unwrapData<CustomerOption[]>(await api.get<{ data?: CustomerOption[] } | CustomerOption[]>('/users', { params: { per_page: 200 } })) ?? [],
    })

    const { data: products = [] } = useQuery<QuoteProductOption[]>({
        queryKey: queryKeys.products.list({ per_page: 200 }),
        queryFn: async () =>
            unwrapData<QuoteProductOption[]>(await api.get<{ data?: QuoteProductOption[] }>('/products', { params: { per_page: 200 } })) ?? [],
    })

    const { data: services = [] } = useQuery<QuoteServiceOption[]>({
        queryKey: queryKeys.services.list({ per_page: 200 }),
        queryFn: async () =>
            unwrapData<QuoteServiceOption[]>(await api.get<{ data?: QuoteServiceOption[] }>('/services', { params: { per_page: 200 } })) ?? [],
    })
    const productOptions = products.map(normalizeProductOption)
    const serviceOptions = services.map(normalizeServiceOption)

    const addBlock = (eqId: number, eqName: string) => {
        if (blocks.find(b => b.equipment_id === eqId)) return
        setBlocks(p => [...p, { equipment_id: eqId, equipmentName: eqName, description: '', items: [] }])
    }

    const removeBlock = (idx: number) => {
        if (!window.confirm('Tem certeza que deseja remover este equipamento e todos os seus itens?')) return
        setBlocks(p => (p || []).filter((_, i) => i !== idx))
    }

    const updateBlock = <K extends keyof QuoteEquipmentBlockForm>(idx: number, field: K, value: QuoteEquipmentBlockForm[K]) =>
        setBlocks(p => (p || []).map((b, i) => i === idx ? { ...b, [field]: value } : b))

    const addItem = (blockIdx: number, type: 'product' | 'service', id: number, name: string, price: number) => {
        setBlocks(p => (p || []).map((b, i) => i === blockIdx ? {
            ...b, items: [...b.items, {
                type, ...(type === 'product' ? { product_id: id } : { service_id: id }),
                name, quantity: 1, original_price: price, unit_price: price,
                discount_percentage: 0, discount_mode: 'percent' as DiscountMode, discount_value: 0,
            }]
        } : b))
    }

    const updateItem = <K extends keyof QuoteItemRowForm>(blockIdx: number, itemIdx: number, field: K, value: QuoteItemRowForm[K]) =>
        setBlocks(p => (p || []).map((b, bi) => bi === blockIdx ? {
            ...b, items: (b.items || []).map((it, ii) => ii === itemIdx ? { ...it, [field]: value } : it)
        } : b))

    const removeItem = (blockIdx: number, itemIdx: number) => {
        if (!window.confirm('Tem certeza que deseja remover este item?')) return
        setBlocks(p => (p || []).map((b, bi) => bi === blockIdx ? { ...b, items: (b.items || []).filter((_, ii) => ii !== itemIdx) } : b))
    }

    const calcItemSubtotal = (it: QuoteItemRowForm) => {
        if (it.discount_mode === 'value' && it.discount_value > 0) {
            return Math.max(0, it.unit_price * it.quantity - it.discount_value)
        }
        return it.unit_price * (1 - it.discount_percentage / 100) * it.quantity
    }

    const subtotal = blocks.reduce((acc, b) => acc + b.items.reduce((a, it) => a + calcItemSubtotal(it), 0), 0)
    const globalDiscount = globalDiscountMode === 'percent'
        ? (discountPercentage > 0 ? subtotal * discountPercentage / 100 : 0)
        : discountAmount
    const total = subtotal - globalDiscount + displacementValue

    const qc = useQueryClient()

    const saveMut = useMutation({
        mutationFn: () => {
            setErrorMsg(null)

            const payload = {
                customer_id: customerId as number,
                seller_id: sellerId || undefined,
                template_id: templateId || undefined,
                source: source || null,
                valid_until: validUntil || null,
                discount_percentage: globalDiscountMode === 'percent' ? discountPercentage : 0,
                discount_amount: globalDiscountMode === 'value' ? discountAmount : 0,
                displacement_value: displacementValue,
                observations: observations || null,
                internal_notes: internalNotes || null,
                general_conditions: generalConditions || null,
                payment_terms: paymentTerms || null,
                payment_terms_detail: paymentTermsDetail || null,
                equipments: (blocks || []).map(b => ({
                    equipment_id: b.equipment_id,
                    description: b.description,
                    items: (b.items || []).map(it => {
                        let discPct = it.discount_percentage
                        if (it.discount_mode === 'value' && it.discount_value > 0 && it.unit_price > 0) {
                            discPct = Math.round((it.discount_value / (it.unit_price * it.quantity)) * 10000) / 100
                        }
                        return {
                            type: it.type, product_id: it.product_id, service_id: it.service_id,
                            quantity: it.quantity, original_price: it.original_price,
                            unit_price: it.unit_price, discount_percentage: discPct,
                        }
                    }),
                })),
            }

            const parsed = quoteCreateSchema.safeParse(payload)
            if (!parsed.success) {
                const firstError = parsed.error.errors[0]?.message || 'Erro de validação nos dados do orçamento'
                setErrorMsg(firstError)
                toast.error(firstError)
                return Promise.reject(new Error(firstError))
            }

            return quoteApi.create(parsed.data as Record<string, unknown>)
        },
        onSuccess: () => {
            toast.success('Orçamento criado com sucesso!')
            qc.invalidateQueries({ queryKey: queryKeys.quotes.all })
            qc.invalidateQueries({ queryKey: queryKeys.quotes.summary })
            broadcastQueryInvalidation(['quotes', 'quotes-summary', 'quotes-advanced-summary', 'dashboard'], 'Orçamento')
            qc.invalidateQueries({ queryKey: queryKeys.quotes.advancedSummary })
            navigate('/orcamentos')
        },
        onError: (err: ApiErrorLike) => {
            const msg = getApiErrorMessage(err, STRINGS.errorSaving)
            setErrorMsg(msg)
            toast.error(msg)
        },
    })

    const handleNext = () => {
        if (step === 'customer') {
            if (!customerId) {
                toast.error('Selecione um cliente antes de continuar')
                return
            }
            setStep('equipments')
        } else if (step === 'equipments') {
            if (blocks.length === 0) {
                toast.error('Adicione pelo menos um equipamento')
                return
            }
            const hasEmptyBlock = blocks.some(b => b.items.length === 0)
            if (hasEmptyBlock) {
                toast.error('Todos os equipamentos devem ter pelo menos um item')
                return
            }
            setStep('review')
        }
    }

    const handleBack = () => {
        setStep(step === 'review' ? 'equipments' : 'customer')
    }

    // Verificação de permissão para criação de orçamentos
    if (!hasPermission('quotes.quote.create')) {
        return (
            <div className="max-w-4xl mx-auto py-20 text-center">
                <h2 className="text-lg font-semibold text-content-primary">Acesso Negado</h2>
                <p className="text-content-secondary mt-2">Você não tem permissão para criar orçamentos.</p>
                <Button variant="outline" className="mt-4" onClick={() => navigate('/orcamentos')}>Voltar</Button>
            </div>
        )
    }

    return (
        <div className="max-w-4xl mx-auto pb-20 space-y-6">
            {/* Header */}
            <div className="flex items-center gap-3">
                <button type="button" aria-label="Voltar para a lista de orçamentos" title="Voltar" onClick={() => navigate('/orcamentos')} className="rounded-lg p-1.5 hover:bg-surface-100">
                    <ArrowLeft className="h-5 w-5 text-surface-500" />
                </button>
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">{STRINGS.newQuote}</h1>
                    <p className="text-[13px] text-surface-500">{STRINGS.fillInfo}</p>
                </div>
            </div>

            {/* Steps Indicator */}
            <div className="flex items-center justify-center gap-4 mb-8">
                {(['customer', 'equipments', 'review'] as QuoteCreateStep[]).map((s, idx) => {
                    const stepNames = [STRINGS.stepCustomer, STRINGS.stepEquipments, STRINGS.stepReview]
                    const currentIdx = ['customer', 'equipments', 'review'].indexOf(step)
                    const isActive = idx <= currentIdx
                    return (
                        <React.Fragment key={s}>
                            <div className={`flex items-center gap-2 ${isActive ? 'text-brand-600' : 'text-surface-400'}`}>
                                <div className={`h-8 w-8 rounded-full flex items-center justify-center text-sm font-bold border ${isActive ? 'bg-brand-100 border-brand-500' : 'bg-surface-50 border-surface-300'}`}>
                                    {idx + 1}
                                </div>
                                <span className="text-sm font-medium hidden sm:block">{stepNames[idx]}</span>
                            </div>
                            {idx < 2 && <div className="w-12 h-px bg-surface-200" />}
                        </React.Fragment>
                    )
                })}
            </div>

            <div className="bg-surface-0 border border-default rounded-xl p-6 shadow-card">

                {/* Step 1: Customer */}
                {step === 'customer' && (
                    <div className="space-y-4">
                        <div>
                            <CustomerAsyncSelect
                                label={STRINGS.selectCustomer}
                                customerId={customerId}
                                initialCustomer={customer ?? null}
                                placeholder="Buscar cliente por nome, documento, telefone ou e-mail..."
                                onChange={(selectedCustomer) => {
                                    setCustomerId(selectedCustomer?.id ?? null)
                                    if (selectedCustomer) {
                                        // Update preselectedCustomer in cache to show info immediately
                                        qc.setQueryData(['customer-preselected'], selectedCustomer)
                                    }
                                }}
                            />
                            {customer && (customer.email || customer.phone || customer.phone2) && (
                                <div className="mt-2 text-sm text-surface-600 flex gap-4">
                                    {customer.email && <span><span className="font-medium mr-1">E-mail:</span>{customer.email}</span>}
                                    {customer.phone && <span><span className="font-medium mr-1">Tefone:</span>{customer.phone}</span>}
                                    {customer.phone2 && <span><span className="font-medium mr-1">Celular:</span>{customer.phone2}</span>}
                                </div>
                            )}
                        </div>
                        {canViewTemplates && (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">Template</label>
                                <select
                                    title="Template de orçamento"
                                    value={templateId ?? ''}
                                    onChange={(e) => {
                                        const nextTemplate = templates.find((template) => template.id === Number(e.target.value)) ?? null
                                        const nextState = applyQuoteTemplateDefaults(nextTemplate, paymentTermsDetail)
                                        setTemplateId(nextState.templateId)
                                        setPaymentTermsDetail(nextState.paymentTermsDetail)
                                    }}
                                    className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                                >
                                    <option value="">Sem template</option>
                                    {templates.map((template) => (
                                        <option key={template.id} value={template.id}>
                                            {template.name}
                                            {template.is_default ? ' (padrão)' : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                        {selectedTemplate && (
                            <div className="rounded-xl border border-brand-200 bg-brand-50/50 p-4 space-y-2">
                                <p className="text-sm font-semibold text-brand-800">Template aplicado: {selectedTemplate.name}</p>
                                {selectedTemplate.payment_terms_text && (
                                    <p className="text-sm text-surface-700">
                                        <span className="font-medium">Pagamento:</span> {selectedTemplate.payment_terms_text}
                                    </p>
                                )}
                                {selectedTemplate.warranty_terms && (
                                    <p className="text-sm text-surface-700">
                                        <span className="font-medium">Garantia:</span> {selectedTemplate.warranty_terms}
                                    </p>
                                )}
                                {selectedTemplate.delivery_terms && (
                                    <p className="text-sm text-surface-700">
                                        <span className="font-medium">Entrega:</span> {selectedTemplate.delivery_terms}
                                    </p>
                                )}
                                {selectedTemplate.general_conditions && (
                                    <p className="text-sm text-surface-700">
                                        <span className="font-medium">Condições gerais:</span> {selectedTemplate.general_conditions}
                                    </p>
                                )}
                            </div>
                        )}
                        <div>
                            <label className="block text-sm font-medium text-surface-700 mb-1">{STRINGS.validity}</label>
                            <input title="Validade" placeholder="dd/mm/aaaa" type="date" value={validUntil} onChange={e => setValidUntil(e.target.value)}
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 mb-1">{STRINGS.observations}</label>
                            <textarea title="Observações" placeholder="Observações adicionais..." value={observations} onChange={e => setObservations(e.target.value)} rows={3}
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 mb-1">Condições Gerais</label>
                            <textarea title="Condições Gerais" placeholder="Condições gerais do orçamento..." value={generalConditions} onChange={e => setGeneralConditions(e.target.value)} rows={3}
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 mb-1">Vendedor</label>
                            <select title="Vendedor" value={sellerId ?? ''} onChange={e => setSellerId(e.target.value ? Number(e.target.value) : null)}
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                                <option value="">Usuário atual (padrão)</option>
                                {(sellers || []).map((u: { id: number; name: string }) => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <LookupCombobox lookupType="quote-sources" valueField="slug" label="Origem Comercial" value={source} onChange={setSource} placeholder="Selecione (opcional)" className="w-full" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-surface-700 mb-1">Notas Internas</label>
                            <textarea title="Notas Internas" value={internalNotes} onChange={e => setInternalNotes(e.target.value)} rows={2}
                                placeholder="Notas visíveis apenas internamente..."
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <div>
                            <LookupCombobox lookupType="payment-terms" valueField="slug" label="Condições de Pagamento" value={paymentTerms} onChange={setPaymentTerms} placeholder="Selecione (opcional)" className="w-full" />
                        </div>
                        {(paymentTerms || paymentTermsDetail) && (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">Detalhes do Pagamento</label>
                                <input title="Detalhes do Pagamento" value={paymentTermsDetail} onChange={e => setPaymentTermsDetail(e.target.value)}
                                    placeholder="Detalhes adicionais sobre forma de pagamento..."
                                    className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                            </div>
                        )}
                        <div className="flex justify-end pt-4">
                            <Button onClick={handleNext} disabled={!customerId} icon={<ArrowRight className="h-4 w-4" />}>
                                {STRINGS.next}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Step 2: Equipments & Items */}
                {step === 'equipments' && (
                    <div className="space-y-6">
                        <div className="flex items-center justify-between mb-2">
                            <h3 className="text-sm font-semibold text-surface-900">{STRINGS.customerEquipments}</h3>
                            <button
                                type="button"
                                onClick={() => setShowQuickEquipmentModal(true)}
                                className="flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 hover:border-brand-300 transition-all"
                            >
                                <Plus className="h-3.5 w-3.5" />
                                Cadastrar Equipamento
                            </button>
                        </div>
                        {customerEquipments.length === 0 ? (
                            <p className="text-sm text-surface-400 italic">{STRINGS.noEquipments}</p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {(customerEquipments || []).map((eq) => {
                                    const equipmentLabel = buildEquipmentDisplayName(eq, eq.id)
                                    return (
                                    <button key={eq.id} type="button"
                                        onClick={() => addBlock(eq.id, equipmentLabel)}
                                        className={`rounded-lg border px-3 py-1.5 text-xs font-medium transition-all ${blocks.find(b => b.equipment_id === eq.id)
                                            ? 'border-brand-500 bg-brand-50 text-brand-700'
                                            : 'border-surface-200 text-surface-600 hover:border-surface-300'}`}>
                                        <Plus className="inline h-3 w-3 mr-1" />
                                        {equipmentLabel}
                                    </button>
                                    )
                                })}
                            </div>
                        )}

                        {(blocks || []).map((block, bIdx) => (
                            <div key={block.equipment_id} className="rounded-xl border border-default p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-sm font-semibold text-surface-800">{block.equipmentName}</h4>
                                    <button type="button" aria-label="Remover equipamento do orçamento" title="Remover Equipamento" onClick={() => removeBlock(bIdx)} className="text-red-400 hover:text-red-600">
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                                <textarea title="Descrição" value={block.description}
                                    onChange={e => updateBlock(bIdx, 'description', e.target.value)}
                                    placeholder={STRINGS.descriptionPlaceholder} rows={2}
                                    className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />

                                {/* Items */}
                                {(block.items || []).map((it, iIdx) => (
                                    <div key={iIdx} className="space-y-1">
                                        <div className="flex items-center gap-2 rounded-lg bg-surface-50 p-2 text-sm">
                                            <span className="w-16 text-xs text-surface-500">{it.type === 'product' ? 'Produto' : 'Serviço'}</span>
                                            <span className="flex-1 font-medium text-surface-800">{it.name}</span>
                                            <input title="Quantidade" placeholder="1" type="number" min={1} value={it.quantity}
                                                onChange={e => updateItem(bIdx, iIdx, 'quantity', Number(e.target.value))}
                                                className="w-16 rounded border border-default bg-surface-0 px-2 py-1 text-center text-sm" />
                                            <CurrencyInput title="Preço Unitário" placeholder="R$ 0,00" value={it.unit_price}
                                                onChange={val => updateItem(bIdx, iIdx, 'unit_price', val)}
                                                className="w-28 rounded border border-default bg-surface-0 px-2 py-1 text-right text-sm h-8" />
                                            <DiscountInput
                                                title="Desconto"
                                                mode={it.discount_mode}
                                                value={it.discount_mode === 'percent' ? it.discount_percentage : it.discount_value}
                                                referenceAmount={it.unit_price * it.quantity}
                                                className="w-32"
                                                onUpdate={(mode, val) => {
                                                    setBlocks(p => (p || []).map((b, bi) => bi !== bIdx ? b : {
                                                        ...b, items: (b.items || []).map((item, ii) => ii !== iIdx ? item : {
                                                            ...item,
                                                            discount_mode: mode,
                                                            discount_percentage: mode === 'percent' ? val : 0,
                                                            discount_value: mode === 'value' ? val : 0,
                                                        })
                                                    }))
                                                }}
                                            />
                                            <span className="w-24 text-right font-medium text-surface-900">
                                                {formatCurrency(calcItemSubtotal(it))}
                                            </span>
                                            <button type="button" aria-label="Remover item do orçamento" title="Remover Item" onClick={() => removeItem(bIdx, iIdx)} className="text-surface-400 hover:text-red-500">
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        {customerId && (it.product_id || it.service_id) && (
                                            <PriceHistoryHint
                                                customerId={customerId}
                                                type={it.type}
                                                referenceId={it.product_id || it.service_id}
                                                onApplyPrice={(price) => updateItem(bIdx, iIdx, 'unit_price', price)}
                                            />
                                        )}
                                    </div>
                                ))}

                                {/* Add item buttons */}
                                <div className="flex gap-2 flex-wrap">
                                    <div className="flex gap-1 items-center">
                                        <ItemSearchCombobox
                                            items={productOptions}
                                            type="product"
                                            placeholder={STRINGS.addProduct}
                                            className="w-[200px] h-[30px]"
                                            onSelect={(id) => {
                                                const product = productOptions.find((option) => option.id === id)
                                                if (product) addItem(bIdx, 'product', product.id, product.name, product.sell_price ?? 0)
                                            }}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => { setQuickPSTab('product'); setShowQuickProductService(true) }}
                                            aria-label="Cadastrar novo produto"
                                            title="Cadastrar novo produto"
                                            className="flex items-center justify-center rounded-lg border border-dashed border-brand-300 bg-brand-50 h-[30px] w-[30px] text-brand-600 hover:bg-brand-100 hover:border-brand-400 transition-colors"
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <div className="flex gap-1 items-center">
                                        <ItemSearchCombobox
                                            items={serviceOptions}
                                            type="service"
                                            placeholder={STRINGS.addService}
                                            className="w-[200px] h-[30px]"
                                            onSelect={(id) => {
                                                const service = serviceOptions.find((option) => option.id === id)
                                                if (service) addItem(bIdx, 'service', service.id, service.name, service.default_price ?? 0)
                                            }}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => { setQuickPSTab('service'); setShowQuickProductService(true) }}
                                            aria-label="Cadastrar novo serviço"
                                            title="Cadastrar novo serviço"
                                            className="flex items-center justify-center rounded-lg border border-dashed border-emerald-300 bg-emerald-50 h-[30px] w-[30px] text-emerald-600 hover:bg-emerald-100 hover:border-emerald-400 transition-colors"
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}

                        <div className="flex justify-between pt-4">
                            <Button variant="outline" onClick={handleBack} icon={<ArrowLeft className="h-4 w-4" />}>
                                {STRINGS.back}
                            </Button>
                            <Button onClick={handleNext} icon={<ArrowRight className="h-4 w-4" />}>
                                {STRINGS.next}
                            </Button>
                        </div>

                        {customerId && (
                            <QuickEquipmentModal
                                open={showQuickEquipmentModal}
                                onOpenChange={setShowQuickEquipmentModal}
                                customerId={customerId}
                                customerName={customer?.name ?? ''}
                            />
                        )}

                        <QuickProductServiceModal
                            open={showQuickProductService}
                            onOpenChange={setShowQuickProductService}
                            defaultTab={quickPSTab}
                        />
                    </div>
                )}

                {/* Step 3: Review */}
                {step === 'review' && (
                    <div className="space-y-6">
                        <h3 className="text-sm font-semibold text-surface-900">{STRINGS.summary}</h3>

                        {(blocks || []).map(block => (
                            <div key={block.equipment_id} className="rounded-lg border border-surface-100 p-3">
                                <p className="text-sm font-medium text-surface-800 mb-1">{block.equipmentName}</p>
                                {block.description && <p className="text-xs text-surface-500 mb-2">{block.description}</p>}
                                {(block.items || []).map((it, i) => (
                                    <div key={i} className="flex justify-between text-sm py-1">
                                        <span className="text-surface-700">{it.name} × {it.quantity}</span>
                                        <span className="font-medium text-surface-900">
                                            {formatCurrency(calcItemSubtotal(it))}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ))}

                        <div className="space-y-2 border-t border-subtle pt-4">
                            <div className="flex justify-between text-sm">
                                <span className="text-surface-600">{STRINGS.totalItems}</span>
                                <span>{blocks.reduce((a, b) => a + b.items.length, 0)}</span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-surface-600">Desconto global</span>
                                <DiscountInput
                                    title="Desconto Global"
                                    mode={globalDiscountMode}
                                    value={globalDiscountMode === 'percent' ? discountPercentage : discountAmount}
                                    referenceAmount={subtotal}
                                    className="w-36"
                                    onUpdate={(mode, val) => {
                                        setGlobalDiscountMode(mode)
                                        if (mode === 'percent') { setDiscountPercentage(val); setDiscountAmount(0) }
                                        else { setDiscountAmount(val); setDiscountPercentage(0) }
                                    }}
                                />
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-surface-600">Deslocamento (R$)</span>
                                <CurrencyInput title="Deslocamento (R$)" placeholder="R$ 0,00" value={displacementValue}
                                    onChange={val => setDisplacementValue(val)}
                                    className="w-28 rounded border border-default bg-surface-50 px-2 py-1 text-right text-sm h-8" />
                            </div>
                            {globalDiscount > 0 && (
                                <div className="flex justify-between text-sm text-red-500">
                                    <span>Desconto</span>
                                    <span>-{formatCurrency(globalDiscount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-base font-bold pt-2">
                                <span className="text-surface-900">{STRINGS.total}</span>
                                <span className="text-brand-600">{formatCurrency(total)}</span>
                            </div>
                        </div>

                        {errorMsg && <p className="text-sm text-red-600">{errorMsg}</p>}

                        <div className="flex justify-between pt-4">
                            <Button variant="outline" onClick={handleBack} icon={<ArrowLeft className="h-4 w-4" />}>
                                {STRINGS.back}
                            </Button>
                            <Button icon={<Save className="h-4 w-4" />}
                                onClick={() => { if (!saveMut.isPending) saveMut.mutate() }}
                                loading={saveMut.isPending}>
                                {saveMut.isPending ? STRINGS.saving : STRINGS.saveQuote}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    )
}
