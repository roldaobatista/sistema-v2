import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    Package, Search, Plus, Minus, Trash2, Send, Loader2, ArrowLeft,
    ShoppingCart, CheckCircle2, Clock, Truck, WifiOff,
} from 'lucide-react'
import { cn, getApiErrorMessage } from '@/lib/utils'
import api from '@/lib/api'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useOfflineMutation } from '@/hooks/useOfflineMutation'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

interface MaterialRequest {
    id: number
    reference?: string
    description?: string
    items_count?: number
    status: string
    created_at: string
    work_order_id?: number
    work_order_number?: string
    requester_id?: number
}

interface Product {
    id: number
    name: string
    sku?: string
    unit?: string
}

const STATUS_MAP: Record<string, { label: string; color: string; icon: typeof Clock }> = {
    pending: { label: 'Pendente', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30', icon: Clock },
    approved: { label: 'Aprovada', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30', icon: CheckCircle2 },
    in_separation: { label: 'Em Separação', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', icon: Package },
    shipped: { label: 'Enviada', color: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400', icon: Truck },
    delivered: { label: 'Entregue', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30', icon: CheckCircle2 },
    rejected: { label: 'Rejeitada', color: 'bg-red-100 text-red-700 dark:bg-red-900/30', icon: Trash2 },
}

const requestSchema = z.object({
    work_order_id: z.string().optional(),
    items: z.array(z.object({
        product_id: z.number(),
        product_name: z.string(),
        quantity: z.number().min(0.01),
    })).min(1, 'Adicione pelo menos um produto'),
    notes: z.string().optional(),
})

type RequestFormData = z.infer<typeof requestSchema>

export default function TechMaterialRequestPage() {
    const navigate = useNavigate()
    const { user } = useAuthStore()
    const [activeTab, setActiveTab] = useState<'list' | 'new'>('list')
    const [requests, setRequests] = useState<MaterialRequest[]>([])
    const [loading, setLoading] = useState(true)
    const [productSearch, setProductSearch] = useState('')
    const [searchResults, setSearchResults] = useState<Product[]>([])
    const [searching, setSearching] = useState(false)

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        reset,
        formState: { errors, isValid, isSubmitting }
    } = useForm<RequestFormData>({
        resolver: zodResolver(requestSchema),
        defaultValues: {
            work_order_id: '',
            items: [],
            notes: '',
        },
        mode: 'onChange'
    })

    const selectedItems = watch('items')

    const { mutate: offlineMutate, isPending: isOfflinePending, isOfflineQueued } = useOfflineMutation<unknown, { mutations: Array<{ type: string; data: Record<string, unknown> }> }>({
        url: '/tech/sync/batch',
        method: 'POST',
        offlineToast: 'Solicitação salva offline. Será sincronizada quando houver conexão.',
        successToast: 'Solicitação criada com sucesso',
        onSuccess: (_data, wasOffline) => {
            if (!wasOffline) {
                fetchRequests()
            }
            setActiveTab('list')
            reset()
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Erro ao criar solicitação'))
        },
    })

    useEffect(() => {
        if (activeTab === 'list') {
            fetchRequests()
        }
    }, [activeTab])

    useEffect(() => {
        if (productSearch.length >= 2) {
            const timeoutId = setTimeout(() => {
                searchProducts()
            }, 300)
            return () => clearTimeout(timeoutId)
        } else {
            setSearchResults([])
        }
    }, [productSearch])

    async function fetchRequests() {
        try {
            setLoading(true)
            const { data } = await api.get('/material-requests')
            const allRequests = data.data || []
            // Filter by current user if my parameter is needed
            const filtered = user?.id
                ? (allRequests || []).filter((req: MaterialRequest) => req.requester_id === user.id)
                : allRequests
            setRequests(filtered)
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao carregar solicitações'))
        } finally {
            setLoading(false)
        }
    }

    async function searchProducts() {
        try {
            setSearching(true)
            const { data } = await api.get('/products', {
                params: {
                    search: productSearch,
                    per_page: 10,
                },
            })
            setSearchResults(data.data || [])
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao buscar produtos'))
        } finally {
            setSearching(false)
        }
    }

    const handleAddProduct = (product: Product) => {
        const existing = selectedItems.find((item) => item.product_id === product.id)
        if (existing) {
            setValue('items', selectedItems.map((item) =>
                item.product_id === product.id
                    ? { ...item, quantity: item.quantity + 1 }
                    : item
            ), { shouldValidate: true })
        } else {
            setValue('items', [
                ...selectedItems,
                {
                    product_id: product.id,
                    product_name: product.name,
                    quantity: 1,
                },
            ], { shouldValidate: true })
        }
        setProductSearch('')
        setSearchResults([])
    }

    const handleRemoveItem = (productId: number) => {
        setValue('items', selectedItems.filter((item) => item.product_id !== productId), { shouldValidate: true })
    }

    const handleUpdateQuantity = (productId: number, delta: number) => {
        setValue('items', selectedItems.map((item) => {
            if (item.product_id === productId) {
                const newQuantity = Math.max(0.01, item.quantity + delta)
                return { ...item, quantity: newQuantity }
            }
            return item
        }), { shouldValidate: true })
    }

    const handleExactQuantity = (productId: number, val: number) => {
        setValue('items', selectedItems.map((item) => {
            if (item.product_id === productId) {
                return { ...item, quantity: Math.max(0.01, val) }
            }
            return item
        }), { shouldValidate: true })
    }

    const onSubmit = async (data: RequestFormData) => {
        const formData = {
            work_order_id: data.work_order_id ? parseInt(data.work_order_id) : undefined,
            items: data.items.map((item) => ({
                product_id: item.product_id,
                quantity_requested: item.quantity,
            })),
            justification: data.notes || undefined,
        }
        await offlineMutate({ mutations: [{ type: 'material_request', data: formData }] })
    }

    return (
        <div className="flex flex-col h-full bg-surface-50 dark:bg-surface-950">
            {/* Header */}
            <div className="bg-card px-4 pt-4 pb-0 border-b border-border shadow-sm shrink-0">
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => navigate(-1)}
                        className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5 text-surface-600 dark:text-surface-400" />
                    </button>
                    <h1 className="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-600 to-brand-400 dark:from-brand-400 dark:to-brand-200">
                        Solicitação de Material
                    </h1>
                </div>

                {/* Tabs */}
                <div className="flex gap-4 mt-6">
                    <button
                        onClick={() => setActiveTab('list')}
                        className={cn(
                            'pb-3 text-sm font-semibold transition-colors border-b-2 px-1 relative',
                            activeTab === 'list'
                                ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                : 'border-transparent text-surface-500 hover:text-surface-700 hover:border-surface-300 dark:text-surface-400 dark:hover:text-surface-300'
                        )}
                    >
                        Minhas Solicitações
                    </button>
                    <button
                        onClick={() => setActiveTab('new')}
                        className={cn(
                            'pb-3 text-sm font-semibold transition-colors border-b-2 px-1 relative',
                            activeTab === 'new'
                                ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                : 'border-transparent text-surface-500 hover:text-surface-700 hover:border-surface-300 dark:text-surface-400 dark:hover:text-surface-300'
                        )}
                    >
                        Nova Solicitação
                    </button>
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto px-4 py-6">
                {activeTab === 'list' ? (
                    <>
                        {loading ? (
                            <div className="flex flex-col items-center justify-center py-20 gap-3">
                                <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                                <p className="text-sm font-medium text-surface-500">Carregando solicitações...</p>
                            </div>
                        ) : requests.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-20 gap-4">
                                <div className="w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
                                    <ShoppingCart className="w-10 h-10 text-surface-400 dark:text-surface-500" />
                                </div>
                                <p className="text-base font-medium text-surface-600 dark:text-surface-400">Nenhuma solicitação encontrada</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {(requests || []).map((request) => {
                                    const status = STATUS_MAP[request.status] || STATUS_MAP.pending
                                    const StatusIcon = status.icon

                                    return (
                                        <div
                                            key={request.id}
                                            className="bg-card rounded-2xl p-5 shadow-sm border border-border/50 transition-all hover:shadow-md"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-3 mb-2 flex-wrap">
                                                        <span className="font-bold text-base text-foreground tracking-tight">
                                                            {request.reference || `#${request.id}`}
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold shadow-sm',
                                                                status.color
                                                            )}
                                                        >
                                                            <StatusIcon className="w-3.5 h-3.5" />
                                                            {status.label}
                                                        </span>
                                                    </div>
                                                    {request.description && (
                                                        <p className="text-sm text-surface-600 dark:text-surface-400 line-clamp-2 mt-1.5 leading-relaxed">
                                                            {request.description}
                                                        </p>
                                                    )}
                                                    <div className="flex items-center gap-4 mt-4 pt-3 border-t border-border/50 text-xs font-medium text-surface-500 flex-wrap">
                                                        <span className="flex items-center gap-1.5 bg-surface-100 dark:bg-surface-800 px-2 py-1 rounded-md">
                                                            <Package className="w-3.5 h-3.5" />
                                                            {request.items_count || 0} item(ns)
                                                        </span>
                                                        {request.work_order_number && (
                                                            <span className="flex items-center gap-1.5 bg-surface-100 dark:bg-surface-800 px-2 py-1 rounded-md">
                                                                OS: {request.work_order_number}
                                                            </span>
                                                        )}
                                                        <span className="flex items-center gap-1.5 text-surface-400">
                                                            <Clock className="w-3.5 h-3.5" />
                                                            {new Date(request.created_at).toLocaleDateString('pt-BR')}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )
                                })}
                            </div>
                        )}
                    </>
                ) : (
                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 max-w-2xl mx-auto pb-6">
                        {/* Work Order */}
                        <div className="bg-card rounded-2xl p-5 shadow-sm border border-border/50">
                            <label className="block text-sm font-semibold text-foreground mb-2">
                                Ordem de Serviço (opcional)
                            </label>
                            <input
                                type="text"
                                {...register('work_order_id')}
                                placeholder="Número da OS"
                                className="w-full px-4 py-3 rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 text-sm placeholder:text-surface-400 focus:bg-card focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all"
                            />
                        </div>

                        {/* Product Search */}
                        <div className="bg-card rounded-2xl p-5 shadow-sm border border-border/50 relative z-10">
                            <label className="block text-sm font-semibold text-foreground mb-2">
                                Buscar Produtos
                            </label>
                            <div className="relative">
                                <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-surface-400" />
                                <input
                                    type="text"
                                    value={productSearch}
                                    onChange={(e) => setProductSearch(e.target.value)}
                                    placeholder="Digite para buscar..."
                                    className="w-full pl-11 pr-4 py-3 rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 text-sm placeholder:text-surface-400 focus:bg-card focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all"
                                />
                            </div>

                            {/* Search Results */}
                            {productSearch.length >= 2 && (
                                <div className="absolute left-0 right-0 top-full mt-2 space-y-1 max-h-60 overflow-y-auto bg-card rounded-xl shadow-xl border border-border p-2 z-20">
                                    {searching ? (
                                        <div className="flex items-center justify-center py-6">
                                            <Loader2 className="w-6 h-6 animate-spin text-brand-500" />
                                        </div>
                                    ) : searchResults.length === 0 ? (
                                        <div className="text-center py-6 text-sm font-medium text-surface-500">
                                            Nenhum produto encontrado
                                        </div>
                                    ) : (
                                        (searchResults || []).map((product) => (
                                            <button
                                                key={product.id}
                                                type="button"
                                                onClick={() => handleAddProduct(product)}
                                                className="w-full text-left px-4 py-3 rounded-lg hover:bg-surface-50 dark:hover:bg-surface-800 flex items-center justify-between group transition-colors"
                                            >
                                                <div>
                                                    <p className="text-sm font-bold text-foreground">
                                                        {product.name}
                                                    </p>
                                                    {product.sku && (
                                                        <p className="text-xs font-medium text-surface-500 mt-0.5">SKU: {product.sku}</p>
                                                    )}
                                                </div>
                                                <div className="p-1.5 rounded-full bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <Plus className="w-4 h-4" />
                                                </div>
                                            </button>
                                        ))
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Selected Items */}
                        <div className="bg-card rounded-2xl p-5 shadow-sm border border-border/50">
                            <label className="flex items-center justify-between text-sm font-semibold text-foreground mb-4">
                                <span>Itens Selecionados</span>
                                <span className="bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400 px-2 py-0.5 rounded-full text-xs">{selectedItems.length}</span>
                            </label>

                            {selectedItems.length === 0 ? (
                                <div className="text-center py-8 rounded-xl bg-surface-50 dark:bg-surface-900 border border-dashed border-surface-200 dark:border-surface-800">
                                    <Package className="w-8 h-8 text-surface-300 mx-auto mb-2" />
                                    <p className="text-sm font-medium text-surface-500">Nenhum item selecionado</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {(selectedItems || []).map((item) => (
                                        <div
                                            key={item.product_id}
                                            className="bg-surface-50 dark:bg-surface-900/50 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border border-surface-200/50 dark:border-surface-800/50"
                                        >
                                            <div className="flex-1 min-w-0 pl-1">
                                                <p className="text-sm font-bold text-foreground truncate">
                                                    {item.product_name}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-1 bg-card rounded-lg p-1 border border-border self-start sm:self-auto shadow-sm">
                                                <button
                                                    type="button"
                                                    onClick={() => handleUpdateQuantity(item.product_id, -0.5)}
                                                    className="p-1.5 rounded-md hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-600 dark:text-surface-400 transition-colors"
                                                >
                                                    <Minus className="w-4 h-4" />
                                                </button>
                                                <input
                                                    type="number"
                                                    value={item.quantity}
                                                    onChange={(e) => handleExactQuantity(item.product_id, parseFloat(e.target.value) || 0.01)}
                                                    min="0.01"
                                                    step="0.01"
                                                    className="w-16 px-1 py-1 text-sm font-semibold rounded-md bg-transparent border-none text-center focus:ring-0 focus:outline-none"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => handleUpdateQuantity(item.product_id, 0.5)}
                                                    className="p-1.5 rounded-md hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-600 dark:text-surface-400 transition-colors"
                                                >
                                                    <Plus className="w-4 h-4" />
                                                </button>
                                                <div className="w-px h-5 bg-border mx-1"></div>
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveItem(item.product_id)}
                                                    className="p-1.5 rounded-md hover:bg-red-50 dark:hover:bg-red-900/20 text-red-500 transition-colors"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {errors.items && (
                                <p className="text-xs font-medium text-red-500 mt-2 pl-1">{errors.items.message}</p>
                            )}
                        </div>

                        {/* Notes */}
                        <div className="bg-card rounded-2xl p-5 shadow-sm border border-border/50">
                            <label className="block text-sm font-semibold text-foreground mb-2">
                                Observações
                            </label>
                            <textarea
                                {...register('notes')}
                                placeholder="Observações sobre a solicitação..."
                                rows={3}
                                className="w-full px-4 py-3 rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 text-sm placeholder:text-surface-400 focus:bg-card focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none resize-none transition-all"
                            />
                        </div>

                        {/* Offline queued indicator */}
                        {isOfflineQueued && (
                            <div className="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400 text-sm font-medium">
                                <WifiOff className="w-4 h-4 shrink-0" />
                                Solicitação salva offline. Será enviada automaticamente quando houver conexão.
                            </div>
                        )}

                        {/* Submit Button */}
                        <button
                            type="submit"
                            disabled={isSubmitting || isOfflinePending || !isValid || selectedItems.length === 0}
                            className={cn(
                                "w-full flex items-center justify-center gap-2 px-4 py-4 rounded-xl text-sm font-bold shadow-md transition-all active:scale-[0.98]",
                                isValid && selectedItems.length > 0 && !isSubmitting && !isOfflinePending
                                    ? "bg-gradient-to-r from-brand-600 to-brand-500 text-white hover:shadow-lg hover:from-brand-500 hover:to-brand-400"
                                    : "bg-surface-200 text-surface-400 dark:bg-surface-800 dark:text-surface-600 cursor-not-allowed shadow-none"
                            )}
                        >
                            {(isSubmitting || isOfflinePending) ? (
                                <>
                                    <Loader2 className="w-5 h-5 animate-spin" />
                                    Enviando Solicitação...
                                </>
                            ) : (
                                <>
                                    <Send className="w-5 h-5" />
                                    Enviar Solicitação
                                </>
                            )}
                        </button>
                    </form>
                )}
            </div>
        </div>
    )
}
