import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2, Package, Briefcase, Users, Truck, Tag, X } from 'lucide-react'
import { workOrderApi } from '@/lib/work-order-api'
import { refDataApi } from '@/lib/ref-data-api'
import { queryKeys } from '@/lib/query-keys'
import { customerApi } from '@/lib/customer-api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { cn, formatCurrency } from '@/lib/utils'
import { formatPhone, formatCep, fetchAddressByCep } from '@/lib/cep-utils'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { CustomerAsyncSelect, toCustomerAsyncSelectOption, type CustomerAsyncSelectItem } from '@/components/common/CustomerAsyncSelect'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import { usePriceGate } from '@/hooks/usePriceGate'
import PriceHistoryHint from '@/components/common/PriceHistoryHint'
import { ItemSearchCombobox } from '@/components/common/ItemSearchCombobox'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import { CurrencyInput, CurrencyInputInline } from '@/components/common/CurrencyInput'
import { workOrderCreateSchema, type WorkOrderCreateInput } from '@/lib/work-order-create-schema'
import CalibrationCriticalAnalysis from '@/components/os/CalibrationCriticalAnalysis'
import type { Customer } from '@/types'

interface ItemForm {
    type: 'product' | 'service'
    reference_id: number | ''
    description: string
    quantity: string
    unit_price: string
    discount: string
}

interface Equipment {
    id: number
    type: string
    brand?: string
    model?: string
    serial_number?: string
}

interface UserOption {
    id: number
    name: string
}

interface CatalogItem {
    id: number
    name?: string | null
    sell_price?: string
    default_price?: string
    code?: string | null
}

interface CatalogComboboxItem {
    id: number
    name: string
    code?: string
    sell_price?: number
    default_price?: number
}

function normalizeCatalogItem(item: CatalogItem, type: 'product' | 'service'): CatalogComboboxItem {
    const priceKey = type === 'product' ? 'sell_price' : 'default_price'
    const rawPrice = item[priceKey]

    return {
        id: item.id,
        name: item.name?.trim() || `${type === 'product' ? 'Produto' : 'Serviço'} #${item.id}`,
        code: item.code ?? undefined,
        ...(type === 'product'
            ? { sell_price: Number(rawPrice ?? 0) }
            : { default_price: Number(rawPrice ?? 0) }),
    }
}

const emptyItem: ItemForm = {
    type: 'product', reference_id: '', description: '',
    quantity: '1', unit_price: '0', discount: '0',
}

const emptyNewEquipment = {
    type: '',
    brand: '',
    model: '',
    serial_number: '',
}

function EquipmentSelector({ customerId, selectedIds, onToggle }: {
    customerId: string | number; selectedIds: number[];
    onToggle: (id: number) => void;
}) {
    const { data: eqRes, isLoading } = useQuery({
        queryKey: queryKeys.workOrders.customerEquipments(Number(customerId)),
        queryFn: () => refDataApi.customerEquipments(Number(customerId)).then(items => items as unknown as Equipment[]),
        enabled: !!customerId,
    })
    const equipments = eqRes ?? []

    if (isLoading) return <div className="animate-pulse h-8 w-full rounded bg-surface-100" />
    if (equipments.length === 0) return <p className="text-xs text-surface-400">Nenhum equipamento cadastrado para este cliente.</p>

    return (
        <div className="flex flex-wrap gap-2">
            {(equipments || []).map((eq) => (
                <button key={eq.id} type="button" onClick={() => onToggle(eq.id)}
                    aria-label={`Selecionar equipamento ${eq.type} ${eq.brand ?? ''} ${eq.model ?? ''}`}
                    className={cn('rounded-lg border px-3 py-1.5 text-xs font-medium transition-all',
                        selectedIds.includes(eq.id)
                            ? 'border-brand-500 bg-brand-50 text-brand-700'
                            : 'border-default text-surface-600 hover:border-surface-400')}>
                    {eq.type} {eq.brand ?? ''} {eq.model ?? ''} {eq.serial_number ? `(S/N: ${eq.serial_number})` : ''}
                </button>
            ))}
        </div>
    )
}

export function WorkOrderCreatePage() {
    const { hasPermission } = useAuthStore()
    const { canViewPrices } = usePriceGate()
    const canCreateWorkOrder = hasPermission('os.work_order.create')

    const navigate = useNavigate()
    const qc = useQueryClient()
    const [searchParams] = useSearchParams()

    const customerIdFromUrl = searchParams.get('customer_id')
    const parentIdFromUrl = searchParams.get('parent_id')

    const {
        control,
        handleSubmit,
        watch,
        setValue,
        formState: { errors },
    } = useForm<WorkOrderCreateInput>({
        resolver: zodResolver(workOrderCreateSchema),
        defaultValues: {
            customer_id: customerIdFromUrl ? Number(customerIdFromUrl) : ('' as unknown as number),
            description: '',
            priority: 'normal',
            initial_status: 'open',
            service_type: '',
            is_warranty: false,
            assigned_to: '' as unknown as number,
            seller_id: '' as unknown as number,
            driver_id: '' as unknown as number,
            equipment_id: '' as unknown as number,
            branch_id: '' as unknown as number,
            checklist_id: '' as unknown as number,
            quote_id: searchParams.get('quote_id') ? Number(searchParams.get('quote_id')) : ('' as unknown as number),
            service_call_id: searchParams.get('service_call_id') ? Number(searchParams.get('service_call_id')) : ('' as unknown as number),
            origin_type: searchParams.get('origin') || 'manual',
            os_number: '',
            lead_source: '',
            discount: '0',
            discount_percentage: '0',
            displacement_value: '0',
            internal_notes: '',
            manual_justification: '',
            received_at: '',
            started_at: '',
            completed_at: '',
            delivered_at: '',
            delivery_forecast: '',
            agreed_payment_method: '',
            agreed_payment_notes: '',
            address: '',
            city: '',
            state: '',
            zip_code: '',
            contact_phone: '',
            scheduled_date: '',
            tags: [],
            // Análise Crítica (ISO 17025)
            service_modality: '',
            requires_adjustment: false,
            requires_maintenance: false,
            client_wants_conformity_declaration: false,
            decision_rule_agreed: '',
            subject_to_legal_metrology: false,
            needs_ipem_interaction: false,
            site_conditions: '',
            calibration_scope_notes: '',
            will_emit_complementary_report: false,
        },
    })

    const watchedCustomerId = watch('customer_id')
    const watchedInitialStatus = watch('initial_status')
    const watchedDiscount = watch('discount')
    const watchedDiscountPercentage = watch('discount_percentage')
    const watchedDisplacementValue = watch('displacement_value')
    const watchedQuoteId = watch('quote_id')
    const watchedServiceCallId = watch('service_call_id')
    const watchedDescription = watch('description')
    const watchedIsWarranty = watch('is_warranty')
    const watchedZipCode = watch('zip_code')
    const watchedServiceType = watch('service_type')

    const [tags, setTags] = useState<string[]>([])
    const [tagInput, setTagInput] = useState('')
    const [selectedTechIds, setSelectedTechIds] = useState<number[]>([])
    const [selectedEquipIds, setSelectedEquipIds] = useState<number[]>([])
    const [newEquip, setNewEquip] = useState(emptyNewEquipment)
    const [showNewEquip, setShowNewEquip] = useState(false)
    const [items, setItems] = useState<ItemForm[]>([])
    const [useCustomAddress, setUseCustomAddress] = useState(false)

    const customerIdNum = customerIdFromUrl ? Number(customerIdFromUrl) : 0
    const { data: preselectedCustomer } = useQuery<Customer | undefined>({
        queryKey: queryKeys.customers.detail(customerIdNum),
        queryFn: () => customerApi.detail(customerIdNum),
        enabled: !!customerIdFromUrl && !!customerIdNum,
    })
    const customerData = preselectedCustomer

    useEffect(() => {
        if (customerData?.id && !watchedCustomerId) {
            setValue('customer_id', customerData.id as number)
        }
    }, [customerData, watchedCustomerId, setValue])

    const initialCustomerOption = customerData ? toCustomerAsyncSelectOption(customerData as CustomerAsyncSelectItem) : null

    const { data: productsRes } = useQuery({
        queryKey: queryKeys.products.options,
        queryFn: () => refDataApi.products().then(items => items as unknown as CatalogItem[]),
    })

    const { data: servicesRes } = useQuery({
        queryKey: queryKeys.services.options,
        queryFn: () => refDataApi.services().then(items => items as unknown as CatalogItem[]),
    })

    const { data: techsRes } = useQuery({
        queryKey: ['technicians-by-role'],
        queryFn: () => refDataApi.technicians().then(items => items as unknown as UserOption[]),
    })

    const { data: allUsersRes } = useQuery({
        queryKey: ['users-for-selectors'],
        queryFn: () => refDataApi.allUsers().then(items => items as unknown as UserOption[]),
    })

    const { data: branchesRes } = useQuery({
        queryKey: ['branches'],
        queryFn: () => refDataApi.branches(),
    })

    const { data: checklistsRes } = useQuery({
        queryKey: ['service-checklists'],
        queryFn: () => refDataApi.checklists(),
    })

    const products = (productsRes ?? []).map((item) => normalizeCatalogItem(item, 'product'))
    const services = (servicesRes ?? []).map((item) => normalizeCatalogItem(item, 'service'))
    const technicians = techsRes ?? []
    const allUsers = allUsersRes ?? []
    const branches = branchesRes ?? []
    const checklists = checklistsRes ?? []
    const hasRetroactiveTimeline = watchedInitialStatus !== 'open'
    const requiresDeliveredAt = watchedInitialStatus === 'delivered' || watchedInitialStatus === 'invoiced'

    const handleCustomerChange = (nextCustomerId: string | number) => {
        const normalizedCustomerId = nextCustomerId === '' ? '' : Number(nextCustomerId)
        const currentFormCustomerId = watchedCustomerId

        if (currentFormCustomerId === normalizedCustomerId) return

        setValue('customer_id', normalizedCustomerId as number)
        setValue('equipment_id', '' as unknown as number)
        setValue('quote_id', '' as unknown as number)
        setValue('service_call_id', '' as unknown as number)
        setValue('origin_type', 'manual')

        setSelectedEquipIds([])
        setShowNewEquip(false)
        setNewEquip(emptyNewEquipment)
    }

    const saveMut = useMutation({
        mutationFn: (data: Record<string, unknown>) => workOrderApi.create(data),
        onSuccess: (res) => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            broadcastQueryInvalidation(['work-orders', 'dashboard'], 'Ordem de Serviço')
            toast.success('OS criada com sucesso!')
            const body = res?.data as { warranty_warning?: string; data?: { id: number }; id?: number }
            const warrantyWarning = body?.warranty_warning
            if (warrantyWarning) {
                toast.warning(warrantyWarning, { duration: 8000 })
            }
            const workOrderId = body?.data?.id ?? body?.id
            if (workOrderId) navigate(`/os/${workOrderId}`)
        },
        onError: () => { /* 422/500 tratados pelo interceptor global em api.ts */ },
    })

    const addItem = () => setItems(prev => [...prev, { ...emptyItem }])

    const updateItem = (i: number, field: keyof ItemForm, val: ItemForm[keyof ItemForm]) => {
        setItems(prev => (prev || []).map((item, idx) => {
            if (idx !== i) return item
            const updated = { ...item, [field]: val }
            if (field === 'type' && val !== item.type) {
                updated.reference_id = ''
                updated.description = ''
                updated.unit_price = '0'
            }
            if (field === 'reference_id' && val) {
                const list = updated.type === 'product' ? products : services
                const ref = list.find((r) => r.id === Number(val))
                if (ref) {
                    updated.description = ref.name
                    updated.unit_price = (updated.type === 'product' ? ref.sell_price : ref.default_price) ?? '0'
                }
            }
            return updated
        }))
    }

    const removeItem = (i: number) => {
        if (window.confirm('Tem certeza que deseja remover este item?')) {
            setItems(prev => (prev || []).filter((_, idx) => idx !== i))
        }
    }

    const itemTotal = (it: ItemForm) => Math.max(0, (parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0) - (parseFloat(it.discount) || 0))
    const round2 = (n: number) => Math.round(n * 100) / 100
    const subtotal = items.reduce((sum, it) => sum + itemTotal(it), 0)
    const discountFixed = parseFloat(watchedDiscount || '0') || 0
    const discountPercent = parseFloat(watchedDiscountPercentage || '0') || 0
    const displacement = parseFloat(watchedDisplacementValue || '0') || 0
    const discountAmount = discountPercent > 0
        ? round2(subtotal * discountPercent / 100)
        : discountFixed
    const grandTotal = Math.max(0, subtotal - discountAmount + displacement)

    const onSubmit = (data: WorkOrderCreateInput) => {
        if (!canCreateWorkOrder) {
            toast.error('Voce nao possui permissao para criar ordem de servico')
            return
        }

        let finalDiscount = data.discount || '0'
        let finalPercent = data.discount_percentage || '0'
        if (parseFloat(finalPercent) > 0) {
            finalDiscount = '0'
        } else if (parseFloat(finalDiscount) > 0) {
            finalPercent = '0'
        } else {
            finalDiscount = '0'
            finalPercent = '0'
        }

        const nullIfEmpty = <T,>(v: T | '' | undefined): T | null => (v === '' || v === undefined) ? null : v

        const payload: Record<string, unknown> = {
            ...data,
            equipment_id: nullIfEmpty(data.equipment_id),
            assigned_to: nullIfEmpty(data.assigned_to),
            seller_id: nullIfEmpty(data.seller_id),
            driver_id: nullIfEmpty(data.driver_id),
            quote_id: nullIfEmpty(data.quote_id),
            service_call_id: nullIfEmpty(data.service_call_id),
            service_type: nullIfEmpty(data.service_type),
            os_number: nullIfEmpty(data.os_number),
            received_at: nullIfEmpty(data.received_at),
            started_at: nullIfEmpty(data.started_at),
            completed_at: nullIfEmpty(data.completed_at),
            delivered_at: nullIfEmpty(data.delivered_at),
            manual_justification: nullIfEmpty(data.manual_justification),
            internal_notes: nullIfEmpty(data.internal_notes),
            lead_source: nullIfEmpty(data.lead_source),
            delivery_forecast: nullIfEmpty(data.delivery_forecast),
            agreed_payment_method: nullIfEmpty(data.agreed_payment_method),
            agreed_payment_notes: nullIfEmpty(data.agreed_payment_notes),
            branch_id: nullIfEmpty(data.branch_id),
            checklist_id: nullIfEmpty(data.checklist_id),
            address: nullIfEmpty(data.address),
            city: nullIfEmpty(data.city),
            state: nullIfEmpty(data.state),
            zip_code: nullIfEmpty(data.zip_code),
            contact_phone: nullIfEmpty(data.contact_phone),
            scheduled_date: nullIfEmpty(data.scheduled_date),
            tags: tags.length > 0 ? tags : [],
            discount: finalDiscount,
            discount_percentage: finalPercent,
            technician_ids: selectedTechIds.length > 0 ? selectedTechIds : undefined,
            equipment_ids: selectedEquipIds.length > 0 ? selectedEquipIds : undefined,
            items: (items || []).map(it => ({
                ...it,
                reference_id: it.reference_id || null,
            })),
        }
        // Análise Crítica (ISO 17025) — incluir apenas se calibração
        if (data.service_type === 'calibracao') {
            payload.service_modality = nullIfEmpty(data.service_modality) ?? null
            payload.requires_adjustment = data.requires_adjustment ?? false
            payload.requires_maintenance = data.requires_maintenance ?? false
            payload.client_wants_conformity_declaration = data.client_wants_conformity_declaration ?? false
            payload.decision_rule_agreed = data.client_wants_conformity_declaration
                ? (nullIfEmpty(data.decision_rule_agreed) ?? null)
                : null
            payload.subject_to_legal_metrology = data.subject_to_legal_metrology ?? false
            payload.needs_ipem_interaction = data.subject_to_legal_metrology
                ? (data.needs_ipem_interaction ?? false)
                : false
            payload.site_conditions = nullIfEmpty(data.site_conditions) ?? null
            payload.calibration_scope_notes = nullIfEmpty(data.calibration_scope_notes) ?? null
            payload.will_emit_complementary_report = data.will_emit_complementary_report ?? false
        }

        if (showNewEquip && newEquip.type) {
            payload.new_equipment = newEquip
        }
        if (parentIdFromUrl) {
            payload.parent_id = Number(parentIdFromUrl)
        }
        saveMut.mutate(payload)
    }

    if (!canCreateWorkOrder) {
        return (
            <div className="space-y-5">
                <PageHeader
                    title="Nova Ordem de Servico"
                    subtitle="Preencha os dados para abrir uma OS"
                    backTo="/os"
                />
                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    Voce nao possui permissao para criar ordem de servico.
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Nova Ordem de Serviço"
                subtitle="Preencha os dados para abrir uma OS"
                backTo="/os"
            />

            <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
                <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-4">
                    <h2 className="text-sm font-semibold text-surface-900">Dados Gerais</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Controller
                                name="customer_id"
                                control={control}
                                render={({ field }) => (
                                    <CustomerAsyncSelect
                                        label="Cliente *"
                                        customerId={field.value ? Number(field.value) : null}
                                        initialCustomer={customerData as CustomerAsyncSelectItem | null}
                                        placeholder="Buscar cliente por nome, documento, telefone ou e-mail..."
                                        onChange={(customer) => handleCustomerChange(customer?.id ?? '')}
                                    />
                                )}
                            />
                            {errors.customer_id && (
                                <p className="mt-1 text-xs text-red-600">{errors.customer_id.message}</p>
                            )}
                        </div>

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Prioridade</label>
                            <Controller
                                name="priority"
                                control={control}
                                render={({ field }) => (
                                    <select title="Prioridade" value={field.value} onChange={field.onChange}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="low">Baixa</option>
                                        <option value="normal">Normal</option>
                                        <option value="high">Alta</option>
                                        <option value="urgent">Urgente</option>
                                    </select>
                                )}
                            />
                        </div>

                        <div>
                            <Controller
                                name="service_type"
                                control={control}
                                render={({ field }) => (
                                    <LookupCombobox lookupType="service-types" label="Tipo de Atendimento" value={field.value ?? ''} onChange={field.onChange} valueField="slug" placeholder="Selecionar tipo" className="w-full" />
                                )}
                            />
                        </div>

                        <div className="flex items-center gap-2 pt-6">
                            <Controller
                                name="is_warranty"
                                control={control}
                                render={({ field }) => (
                                    <label className="flex items-center gap-2 text-sm text-surface-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            aria-label="OS de garantia"
                                            checked={field.value}
                                            onChange={field.onChange}
                                            className="rounded border-surface-400 text-brand-600 focus:ring-brand-500"
                                        />
                                        OS de Garantia
                                        <span className="text-xs text-surface-400">(não gera comissão)</span>
                                    </label>
                                )}
                            />
                        </div>

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Status Inicial</label>
                            <Controller
                                name="initial_status"
                                control={control}
                                render={({ field }) => (
                                    <select title="Status Inicial" value={field.value} onChange={field.onChange}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="open">Aberta</option>
                                        <option value="awaiting_dispatch">Aguardando despacho (retroativo)</option>
                                        <option value="in_displacement">Em deslocamento (retroativo)</option>
                                        <option value="in_service">Em atendimento (retroativo)</option>
                                        <option value="completed">Concluída (retroativo)</option>
                                        <option value="delivered">Entregue (retroativo)</option>
                                        <option value="invoiced">Faturada (retroativo)</option>
                                    </select>
                                )}
                            />
                        </div>

                        {hasRetroactiveTimeline && (
                            <>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-surface-700">Data e hora de recebimento</label>
                                    <Controller
                                        name="received_at"
                                        control={control}
                                        render={({ field }) => (
                                            <Input aria-label="Data e hora de recebimento" type="datetime-local" value={field.value ?? ''} onChange={field.onChange} />
                                        )}
                                    />
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-surface-700">Data e hora de inicio</label>
                                    <Controller
                                        name="started_at"
                                        control={control}
                                        render={({ field }) => (
                                            <Input aria-label="Data e hora de inicio" type="datetime-local" value={field.value ?? ''} onChange={field.onChange} />
                                        )}
                                    />
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-surface-700">Data Conclusão</label>
                                    <Controller
                                        name="completed_at"
                                        control={control}
                                        render={({ field }) => (
                                            <Input aria-label="Data e hora de conclusao" type="datetime-local" value={field.value ?? ''} onChange={field.onChange} />
                                        )}
                                    />
                                </div>
                                {requiresDeliveredAt && (
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Data e hora de entrega</label>
                                        <Controller
                                            name="delivered_at"
                                            control={control}
                                            render={({ field }) => (
                                                <Input aria-label="Data e hora de entrega" type="datetime-local" value={field.value ?? ''} onChange={field.onChange} />
                                            )}
                                        />
                                    </div>
                                )}
                            </>
                        )}

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Técnico</label>
                            <Controller
                                name="assigned_to"
                                control={control}
                                render={({ field }) => (
                                    <select title="Técnico" value={field.value ?? ''} onChange={field.onChange}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="">Sem atribuição</option>
                                        {(technicians || []).map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                    </select>
                                )}
                            />
                        </div>

                        {branches.length > 0 && (
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-surface-700">Filial</label>
                                <Controller
                                    name="branch_id"
                                    control={control}
                                    render={({ field }) => (
                                        <select title="Filial" value={field.value ?? ''} onChange={field.onChange}
                                            className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                            <option value="">Selecione a filial</option>
                                            {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                                        </select>
                                    )}
                                />
                            </div>
                        )}

                        {checklists.length > 0 && (
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-surface-700">Checklist de Serviço</label>
                                <Controller
                                    name="checklist_id"
                                    control={control}
                                    render={({ field }) => (
                                        <select title="Checklist de Serviço" value={field.value ?? ''} onChange={field.onChange}
                                            className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                            <option value="">Nenhum checklist selecionado</option>
                                            {checklists.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                    )}
                                />
                            </div>
                        )}
                    </div>

                    <div className="space-y-3">
                        <label className="flex items-center gap-2 text-sm text-surface-700 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={useCustomAddress}
                                onChange={e => {
                                    setUseCustomAddress(e.target.checked)
                                    if (!e.target.checked) {
                                        setValue('address', '')
                                        setValue('city', '')
                                        setValue('state', '')
                                        setValue('zip_code', '')
                                        setValue('contact_phone', '')
                                    }
                                }}
                                className="rounded border-surface-400 text-brand-600 focus:ring-brand-500"
                            />
                            Endereço diferente do cliente
                        </label>

                        {useCustomAddress && (
                            <>
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-surface-700">CEP</label>
                                        <Controller
                                            name="zip_code"
                                            control={control}
                                            render={({ field }) => (
                                                <input aria-label="CEP" value={field.value ?? ''} onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                                    field.onChange(formatCep(e.target.value))
                                                }}
                                                onBlur={async () => {
                                                    const clean = (field.value ?? '').replace(/\D/g, '')
                                                    if (clean.length === 8) {
                                                        const data = await fetchAddressByCep(clean)
                                                        if (data) {
                                                            setValue('address', data.address)
                                                            setValue('city', data.city)
                                                            setValue('state', data.state)
                                                        }
                                                    }
                                                }}
                                                placeholder="00000-000"
                                                className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                            )}
                                        />
                                    </div>
                                    <div className="md:col-span-2">
                                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Endereço</label>
                                        <Controller
                                            name="address"
                                            control={control}
                                            render={({ field }) => (
                                                <Input aria-label="Endereço" value={field.value ?? ''} onChange={field.onChange} placeholder="Rua, número, complemento" />
                                            )}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Tel. Contato</label>
                                        <Controller
                                            name="contact_phone"
                                            control={control}
                                            render={({ field }) => (
                                                <input aria-label="Telefone de contato" value={field.value ?? ''} onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                                                    field.onChange(formatPhone(e.target.value))
                                                }}
                                                placeholder="(99) 99999-9999"
                                                className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                            )}
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Cidade</label>
                                        <Controller
                                            name="city"
                                            control={control}
                                            render={({ field }) => (
                                                <Input aria-label="Cidade" value={field.value ?? ''} onChange={field.onChange} />
                                            )}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Estado</label>
                                        <Controller
                                            name="state"
                                            control={control}
                                            render={({ field }) => (
                                                <Input aria-label="Estado" value={field.value ?? ''} onChange={field.onChange} />
                                            )}
                                        />
                                    </div>
                                </div>
                            </>
                        )}
                    </div>

                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Defeito Relatado / Descrição *</label>
                        <Controller
                            name="description"
                            control={control}
                            render={({ field }) => (
                                <textarea aria-label="Defeito Relatado / Descrição" value={field.value} onChange={field.onChange}
                                    rows={3} placeholder="Descreva o problema..."
                                    className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                            )}
                        />
                        {errors.description && (
                            <p className="mt-1 text-xs text-red-600">{errors.description.message}</p>
                        )}
                    </div>

                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Observações Internas</label>
                        <Controller
                            name="internal_notes"
                            control={control}
                            render={({ field }) => (
                                <textarea aria-label="Observações Internas" value={field.value ?? ''} onChange={field.onChange}
                                    rows={2} placeholder="Notas internas..."
                                    className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                            )}
                        />
                        {errors.internal_notes && (
                            <p className="mt-1 text-xs text-red-600">{errors.internal_notes.message}</p>
                        )}
                    </div>

                    {(!watchedQuoteId && !watchedServiceCallId) && (
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Justificativa de Abertura Manual</label>
                            <Controller
                                name="manual_justification"
                                control={control}
                                render={({ field }) => (
                                    <textarea aria-label="Justificativa de Abertura Manual" value={field.value ?? ''} onChange={field.onChange}
                                        rows={2} placeholder="Ex: urgência, retorno em garantia, visita diagnóstica..."
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                )}
                            />
                            <p className="text-xs text-surface-400 mt-1">Recomendado para OS sem orçamento ou chamado de origem.</p>
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-4">
                    <h2 className="text-sm font-semibold text-surface-900 flex items-center gap-2"><Users className="h-4 w-4 text-brand-500" />Equipe e Origem</h2>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Nº OS (manual)</label>
                            <Controller
                                name="os_number"
                                control={control}
                                render={({ field }) => (
                                    <input aria-label="Nº OS (manual)" value={field.value ?? ''} onChange={field.onChange} placeholder="Ex: 001234"
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                )}
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Vendedor</label>
                            <Controller
                                name="seller_id"
                                control={control}
                                render={({ field }) => (
                                    <select title="Vendedor" value={field.value ?? ''} onChange={field.onChange}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="">Nenhum</option>
                                        {(allUsers || []).map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                                    </select>
                                )}
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700 flex items-center gap-1"><Truck className="h-3.5 w-3.5" />Motorista (UMC)</label>
                            <Controller
                                name="driver_id"
                                control={control}
                                render={({ field }) => (
                                    <select title="Motorista (UMC)" value={field.value ?? ''} onChange={field.onChange}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="">Nenhum</option>
                                        {(allUsers || []).map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                                    </select>
                                )}
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Origem do Lead</label>
                            <Controller
                                name="lead_source"
                                control={control}
                                render={({ field }) => (
                                    <select title="Origem do Lead" value={field.value ?? ''} onChange={field.onChange}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="">Nenhuma</option>
                                        <option value="prospeccao">Prospecção</option>
                                        <option value="retorno">Retorno</option>
                                        <option value="contato_direto">Contato Direto</option>
                                        <option value="indicacao">Indicação</option>
                                        <option value="site">Site</option>
                                        <option value="telefone">Telefone</option>
                                        <option value="email">E-mail</option>
                                        <option value="visita">Visita</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                )}
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Previsão de Entrega</label>
                            <Controller
                                name="delivery_forecast"
                                control={control}
                                render={({ field }) => (
                                    <Input aria-label="Previsão de Entrega" type="date" value={field.value ?? ''} onChange={field.onChange} />
                                )}
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Data Agendada</label>
                            <Controller
                                name="scheduled_date"
                                control={control}
                                render={({ field }) => (
                                    <Input aria-label="Data Agendada" type="datetime-local" value={field.value ?? ''} onChange={field.onChange} />
                                )}
                            />
                        </div>
                    </div>
                    <div>
                        <label className="mb-2 block text-sm font-medium text-surface-700">Técnicos (múltiplos)</label>
                        <div className="flex flex-wrap gap-2">
                            {(technicians || []).map((t) => (
                                <button key={t.id} type="button" onClick={() => setSelectedTechIds(prev => prev.includes(t.id) ? (prev || []).filter(id => id !== t.id) : [...prev, t.id])}
                                    aria-label={`Selecionar técnico ${t.name}`}
                                    className={cn('rounded-lg border px-3 py-1.5 text-xs font-medium transition-all',
                                        selectedTechIds.includes(t.id) ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-default text-surface-600 hover:border-surface-400')}>
                                    {t.name}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-surface-900">Equipamentos</h2>
                        <Button variant="ghost" size="sm" type="button" onClick={() => setShowNewEquip(!showNewEquip)} aria-label={showNewEquip ? 'Cancelar novo equipamento' : 'Adicionar novo equipamento'}>
                            {showNewEquip ? 'Cancelar novo' : '+ Novo Equipamento'}
                        </Button>
                    </div>
                    {watchedCustomerId && (
                        <EquipmentSelector
                            customerId={watchedCustomerId}
                            selectedIds={selectedEquipIds}
                            onToggle={(eqId) => setSelectedEquipIds(prev => prev.includes(eqId) ? (prev || []).filter(id => id !== eqId) : [...prev, eqId])}
                        />
                    )}
                    {!watchedCustomerId && !showNewEquip && (
                        <p className="text-sm text-surface-400 text-center py-3">Selecione um cliente para ver os equipamentos cadastrados.</p>
                    )}
                    {showNewEquip && (
                        <div className="grid gap-3 sm:grid-cols-4">
                            <LookupCombobox lookupType="equipment-types" label="Tipo *" value={newEquip.type} onChange={(v) => setNewEquip(p => ({ ...p, type: v }))} placeholder="Selecionar tipo" className="w-full" />
                            <LookupCombobox lookupType="equipment-brands" label="Marca" value={newEquip.brand} onChange={(v) => setNewEquip(p => ({ ...p, brand: v }))} placeholder="Selecionar marca" className="w-full" />
                            <Input label="Modelo" value={newEquip.model} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewEquip(p => ({ ...p, model: e.target.value }))} />
                            <Input label="Nº Série" value={newEquip.serial_number} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewEquip(p => ({ ...p, serial_number: e.target.value }))} />
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-surface-900">Itens (Produtos & Serviços)</h2>
                        <Button variant="ghost" size="sm" type="button" onClick={addItem} icon={<Plus className="h-4 w-4" />} aria-label="Adicionar item">
                            Adicionar
                        </Button>
                    </div>

                    {items.length === 0 ? (
                        <p className="py-6 text-center text-sm text-surface-400">Nenhum item adicionado</p>
                    ) : (
                        <div className="space-y-3">
                            {(items || []).map((item, i) => (
                                <div key={i} className="rounded-lg border border-subtle p-2.5 space-y-3">
                                    <div className="flex items-center gap-2">
                                        <div className="flex rounded-lg border border-default overflow-hidden">
                                            <button type="button" onClick={() => updateItem(i, 'type', 'product')}
                                                aria-label="Selecionar tipo produto"
                                                className={cn('flex items-center gap-1 px-3 py-1.5 text-xs font-medium transition-colors',
                                                    item.type === 'product' ? 'bg-brand-50 text-brand-700' : 'text-surface-500 hover:bg-surface-50')}>
                                                <Package className="h-3 w-3" /> Produto
                                            </button>
                                            <button type="button" onClick={() => updateItem(i, 'type', 'service')}
                                                aria-label="Selecionar tipo serviço"
                                                className={cn('flex items-center gap-1 px-3 py-1.5 text-xs font-medium transition-colors',
                                                    item.type === 'service' ? 'bg-emerald-50 text-emerald-700' : 'text-surface-500 hover:bg-surface-50')}>
                                                <Briefcase className="h-3 w-3" /> Serviço
                                            </button>
                                        </div>
                                        <div className="flex-1">
                                            <ItemSearchCombobox
                                                items={item.type === 'product' ? products : services}
                                                type={item.type}
                                                value={item.reference_id || null}
                                                onSelect={(id) => updateItem(i, 'reference_id', id)}
                                                placeholder={`Selecionar ${item.type === 'product' ? 'produto' : 'serviço'}`}
                                                className="w-full h-8 text-xs"
                                            />
                                        </div>
                                        <Button variant="ghost" size="sm" type="button" onClick={() => removeItem(i)} aria-label="Remover item">
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </div>
                                    {canViewPrices && item.reference_id && watchedCustomerId && (
                                        <PriceHistoryHint
                                            customerId={watchedCustomerId}
                                            type={item.type}
                                            referenceId={item.reference_id}
                                            onApplyPrice={(price) => updateItem(i, 'unit_price', String(price))}
                                        />
                                    )}
                                    <div className={`grid gap-3 ${canViewPrices ? 'sm:grid-cols-4' : 'sm:grid-cols-2'}`}>
                                        <Input label="Descrição" value={item.description} onChange={(e: React.ChangeEvent<HTMLInputElement>) => updateItem(i, 'description', e.target.value)} />
                                        <Input label="Qtd" type="number" step="0.01" value={item.quantity} onChange={(e: React.ChangeEvent<HTMLInputElement>) => updateItem(i, 'quantity', e.target.value)} />
                                        {canViewPrices && (
                                            <>
                                                <CurrencyInput label="Preço Unitário" value={parseFloat(item.unit_price) || 0} onChange={(val) => updateItem(i, 'unit_price', String(val))} />
                                                <div>
                                                    <CurrencyInput label="Desconto" value={parseFloat(item.discount) || 0} onChange={(val) => updateItem(i, 'discount', String(val))} />
                                                    <p className="mt-1 text-right text-xs font-medium text-surface-600">
                                                        Subtotal: {formatCurrency(itemTotal(item))}
                                                    </p>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {canViewPrices && (
                        <div className="border-t border-subtle pt-4 space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-surface-600">Subtotal</span>
                                <span className="font-medium">{formatCurrency(subtotal)}</span>
                            </div>
                            <div className="flex items-center justify-between gap-4 text-sm">
                                <span className="text-surface-600">Desconto Fixo (R$)</span>
                                <Controller
                                    name="discount"
                                    control={control}
                                    render={({ field }) => (
                                        <CurrencyInputInline title="Desconto Fixo (R$)" value={parseFloat(field.value || '0') || 0} onChange={(val) => field.onChange(String(val))}
                                            disabled={discountPercent > 0}
                                            className="w-28 rounded-lg border border-default px-2.5 py-1.5 text-right text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15 disabled:opacity-50 disabled:cursor-not-allowed" />
                                    )}
                                />
                            </div>
                            <div className="flex items-center justify-between gap-4 text-sm">
                                <span className="text-surface-600">Desconto Global (%)</span>
                                <Controller
                                    name="discount_percentage"
                                    control={control}
                                    render={({ field }) => (
                                        <input aria-label="Desconto Global (%)" title="Desconto Global (%)" placeholder="0.00" type="number" step="0.01" min="0" max="100" value={field.value ?? ''} onChange={field.onChange}
                                            disabled={discountFixed > 0}
                                            className="w-28 rounded-lg border border-default px-2.5 py-1.5 text-right text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15 disabled:opacity-50 disabled:cursor-not-allowed" />
                                    )}
                                />
                            </div>
                            {discountFixed > 0 && discountPercent > 0 && (
                                <p className="text-xs text-amber-600">Apenas um tipo de desconto pode ser aplicado. O desconto percentual terá prioridade.</p>
                            )}
                            <div className="flex items-center justify-between gap-4 text-sm">
                                <span className="text-surface-600">Valor Deslocamento (R$)</span>
                                <Controller
                                    name="displacement_value"
                                    control={control}
                                    render={({ field }) => (
                                        <CurrencyInputInline title="Valor Deslocamento (R$)" value={parseFloat(field.value || '0') || 0} onChange={(val) => field.onChange(String(val))}
                                            className="w-28 rounded-lg border border-default px-2.5 py-1.5 text-right text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                    )}
                                />
                            </div>
                            <div className="flex items-center justify-between text-base border-t border-subtle pt-2">
                                <span className="font-semibold text-surface-900">Total</span>
                                <span className="font-bold text-brand-600">{formatCurrency(grandTotal)}</span>
                            </div>
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-4">
                    <h2 className="text-sm font-semibold text-surface-900 flex items-center gap-2"><Tag className="h-4 w-4 text-brand-500" />Pagamento Acordado</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Forma de Pagamento</label>
                            <Controller
                                name="agreed_payment_method"
                                control={control}
                                render={({ field }) => (
                                    <select title="Forma de Pagamento" value={field.value ?? ''} onChange={field.onChange}
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                                        <option value="">A combinar</option>
                                        <option value="boleto">Boleto</option>
                                        <option value="pix">PIX</option>
                                        <option value="cartao_credito">Cartão de Crédito</option>
                                        <option value="cartao_debito">Cartão de Débito</option>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="transferencia">Transferência</option>
                                        <option value="a_combinar">A Combinar</option>
                                    </select>
                                )}
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-surface-700">Observações de Pagamento</label>
                            <Controller
                                name="agreed_payment_notes"
                                control={control}
                                render={({ field }) => (
                                    <textarea aria-label="Observações de Pagamento" value={field.value ?? ''} onChange={field.onChange}
                                        rows={2} placeholder="Condições especiais, parcelamento, etc..."
                                        className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                )}
                            />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-4">
                    <h2 className="text-sm font-semibold text-surface-900 flex items-center gap-2"><Tag className="h-4 w-4 text-brand-500" />Tags</h2>
                    <div className="flex flex-wrap gap-2">
                        {tags.map((tag, idx) => (
                            <span key={idx} className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 border border-brand-200">
                                {tag}
                                <button type="button" onClick={() => setTags(prev => prev.filter((_, i) => i !== idx))} aria-label={`Remover tag ${tag}`} className="ml-0.5 hover:text-red-500 transition-colors">
                                    <X className="h-3 w-3" />
                                </button>
                            </span>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <input
                            aria-label="Nova tag"
                            value={tagInput}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setTagInput(e.target.value)}
                            onKeyDown={(e: React.KeyboardEvent<HTMLInputElement>) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault()
                                    const tag = tagInput.trim().toLowerCase()
                                    if (tag && !tags.includes(tag)) {
                                        setTags(prev => [...prev, tag])
                                        setTagInput('')
                                    }
                                }
                            }}
                            placeholder="Digite e pressione Enter para adicionar..."
                            className="flex-1 rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                        />
                        <Button variant="outline" size="sm" type="button" aria-label="Adicionar tag" onClick={() => {
                            const tag = tagInput.trim().toLowerCase()
                            if (tag && !tags.includes(tag)) {
                                setTags(prev => [...prev, tag])
                                setTagInput('')
                            }
                        }}>
                            <Plus className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                {/* Análise Crítica ISO 17025 — visível somente para calibração */}
                <CalibrationCriticalAnalysis
                    control={control}
                    watch={watch}
                    serviceType={watchedServiceType}
                />

                <div className="flex items-center justify-end gap-3">
                    <Button variant="outline" type="button" onClick={() => navigate('/os')}>Cancelar</Button>
                    <Button type="submit" loading={saveMut.isPending} disabled={!watchedCustomerId || !watchedDescription}>
                        Abrir OS
                    </Button>
                </div>
            </form>
        </div>
    )
}
