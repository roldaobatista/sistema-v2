import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, Box, Loader2, Package, Search, PackageSearch, AlertCircle, ScanBarcode } from 'lucide-react'
import api from '@/lib/api'
import { toast } from 'sonner'
import { useForm } from 'react-hook-form'

interface Warehouse {
    id: number
    name: string
    code?: string
    type: string
}

interface ProductStock {
    product_id: number
    expected_quantity: number
    product: {
        id: number
        name: string
        code?: string
        unit?: string
    }
}

interface SearchFormData {
    searchTerm: string
}

export default function TechVanStockPage() {
    const navigate = useNavigate()

    const [warehouses, setWarehouses] = useState<Warehouse[]>([])
    const [selectedWarehouseId, setSelectedWarehouseId] = useState<number | null>(null)
    const [products, setProducts] = useState<ProductStock[]>([])

    const [loadingWarehouses, setLoadingWarehouses] = useState(true)
    const [loadingProducts, setLoadingProducts] = useState(false)
    const [error, setError] = useState(false)

    const { register, watch } = useForm<SearchFormData>({
        defaultValues: { searchTerm: '' }
    })
    const search = watch('searchTerm')

    // 1. Fetch Warehouses associated with the technician/vehicle
    useEffect(() => {
        async function fetchWarehouses() {
            setLoadingWarehouses(true)
            setError(false)
            try {
                const { data } = await api.get('/stock/inventory-pwa/my-warehouses')
                const w = data?.data ?? data ?? []
                setWarehouses(w)
                if (w.length > 0) {
                    setSelectedWarehouseId(w[0].id)
                }
            } catch (err) {
                setError(true)
                toast.error('Erro ao buscar armazéns disponíveis.')
            } finally {
                setLoadingWarehouses(false)
            }
        }
        fetchWarehouses()
    }, [])

    // 2. Fetch Products for the selected warehouse
    useEffect(() => {
        if (!selectedWarehouseId) return

        async function fetchProducts() {
            setLoadingProducts(true)
            try {
                const { data } = await api.get(`/stock/inventory-pwa/warehouses/${selectedWarehouseId}/products`)
                const p = data?.data ?? data ?? []
                setProducts(p)
            } catch (err) {
                toast.error('Erro ao buscar produtos do armazém.')
                setProducts([])
            } finally {
                setLoadingProducts(false)
            }
        }
        fetchProducts()
    }, [selectedWarehouseId])

    const filteredProducts = products.filter(
        (p) =>
            !search ||
            [p.product?.name, p.product?.code].some(
                (v) => v?.toLowerCase().includes(search.toLowerCase())
            )
    )

    const totalProducts = products.length
    const totalItems = products.reduce((acc, p) => acc + (p.expected_quantity || 0), 0)

    return (
        <div className="flex flex-col h-full bg-surface-50 dark:bg-surface-950">
            {/* Header */}
            <div className="bg-card px-4 pt-4 pb-4 border-b border-border shadow-sm shrink-0">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => navigate('/tech')}
                            className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                            aria-label="Voltar"
                        >
                            <ArrowLeft className="w-5 h-5 text-surface-600 dark:text-surface-400" />
                        </button>
                        <h1 className="text-xl font-bold bg-clip-text text-transparent bg-linear-to-r from-brand-600 to-brand-400 dark:from-brand-400 dark:to-brand-200">
                            Estoque da Van
                        </h1>
                    </div>
                    {/* Botão de Transferência (Scanner) */}
                    <button
                        onClick={() => navigate('/tech/barcode')}
                        className="p-2 rounded-lg bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300 transition-colors"
                        title="Escanear e Transferir"
                    >
                        <ScanBarcode className="w-5 h-5" />
                    </button>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-6 space-y-4">
                {loadingWarehouses ? (
                    <div className="flex flex-col items-center justify-center py-12 gap-3">
                        <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                        <p className="text-sm font-medium text-surface-500">Carregando armazéns...</p>
                    </div>
                ) : error ? (
                    <div className="bg-card rounded-2xl p-6 text-center border border-border/50 shadow-sm">
                        <AlertCircle className="w-12 h-12 text-surface-400 mx-auto mb-2" />
                        <p className="text-sm font-medium text-surface-600 dark:text-surface-400">
                            Não foi possível carregar seu estoque.
                        </p>
                    </div>
                ) : warehouses.length === 0 ? (
                    <div className="bg-card rounded-2xl p-8 text-center border border-border/50 shadow-sm">
                        <div className="w-16 h-16 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center mx-auto mb-4">
                            <PackageSearch className="w-8 h-8 text-surface-400" />
                        </div>
                        <p className="text-sm font-medium text-surface-600 dark:text-surface-400">
                            Nenhum armazém (veículo/técnico) associado ao seu usuário.
                        </p>
                    </div>
                ) : (
                    <>
                        {/* Seletor de Armazém (caso tenha mais de um) */}
                        {warehouses.length > 1 && (
                            <div className="bg-card p-4 rounded-2xl border border-border/50 shadow-sm">
                                <label className="block text-xs font-semibold text-surface-500 uppercase tracking-wider mb-2">
                                    Selecione o Armazém
                                </label>
                                <select
                                    title="Selecione o Armazém"
                                    aria-label="Selecione o Armazém"
                                    value={selectedWarehouseId || ''}
                                    onChange={(e) => setSelectedWarehouseId(Number(e.target.value))}
                                    className="w-full bg-surface-50 dark:bg-surface-900 border border-border rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"
                                >
                                    {warehouses.map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name} {w.code ? `(${w.code})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {/* Estatísticas (Se houver armazém selecionado) */}
                        {selectedWarehouseId && (
                            <div className="grid grid-cols-2 gap-3">
                                <div className="bg-card rounded-2xl p-4 border border-border/50 shadow-sm text-center">
                                    <p className="text-xs font-semibold tracking-wider uppercase text-surface-500 mb-1">Qtd Tipos</p>
                                    <p className="text-2xl font-bold text-foreground">
                                        {loadingProducts ? <Loader2 className="w-5 h-5 animate-spin mx-auto" /> : totalProducts}
                                    </p>
                                </div>
                                <div className="bg-card rounded-2xl p-4 border border-border/50 shadow-sm text-center">
                                    <p className="text-xs font-semibold tracking-wider uppercase text-brand-600 dark:text-brand-500 mb-1">Total Peças</p>
                                    <p className="text-2xl font-bold text-brand-600 dark:text-brand-400">
                                        {loadingProducts ? <Loader2 className="w-5 h-5 animate-spin mx-auto" /> : totalItems}
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Busca */}
                        <div className="relative">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-surface-400" />
                            <input
                                type="text"
                                placeholder="Buscar produto por nome ou código..."
                                {...register('searchTerm')}
                                className="w-full pl-11 pr-4 py-3 rounded-xl bg-card border border-border/50 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all"
                            />
                        </div>

                        {/* Lista de Produtos */}
                        {loadingProducts ? (
                            <div className="flex flex-col items-center justify-center py-8">
                                <Loader2 className="w-6 h-6 animate-spin text-brand-500" />
                            </div>
                        ) : filteredProducts.length === 0 ? (
                            <div className="bg-card rounded-2xl p-8 text-center border border-border/50 shadow-sm mt-4">
                                <div className="w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center mx-auto mb-3">
                                    <Box className="w-6 h-6 text-surface-400" />
                                </div>
                                <p className="text-sm font-medium text-surface-600 dark:text-surface-400">
                                    {search ? 'Nenhum item encontrado na busca.' : 'Este armazém está vazio.'}
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {filteredProducts.map((p) => (
                                    <div
                                        key={p.product_id}
                                        className="bg-card rounded-2xl p-4 border border-border/50 shadow-sm flex items-center justify-between gap-3"
                                    >
                                        <div className="flex items-center gap-4 flex-1 min-w-0">
                                            <div className="w-10 h-10 shrink-0 rounded-xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
                                                <Package className="w-5 h-5 text-surface-500" />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="font-bold text-sm text-foreground truncate">
                                                    {p.product?.name}
                                                </p>
                                                <div className="flex items-center gap-2 mt-0.5">
                                                    {p.product?.code && (
                                                        <span className="text-xs font-medium text-surface-500 bg-surface-100 dark:bg-surface-800 px-1.5 py-0.5 rounded-md">
                                                            {p.product.code}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="shrink-0 text-right">
                                            <div className="flex items-baseline gap-1">
                                                <span className="text-xl font-bold text-foreground">
                                                    {p.expected_quantity}
                                                </span>
                                                <span className="text-xs font-semibold text-surface-500 uppercase">
                                                    {p.product?.unit || 'UN'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    )
}
