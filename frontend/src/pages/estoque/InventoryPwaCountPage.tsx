import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Loader2, Package, AlertTriangle, Save, QrCode, WifiOff } from 'lucide-react'
import { parseLabelQrPayload } from '@/lib/labelQr'
import { QrScannerModal } from '@/components/qr/QrScannerModal'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getApiErrorMessage } from '@/lib/api'
import { stockApi } from '@/lib/stock-api'
import { cn } from '@/lib/utils'
import { toast } from 'sonner'
import type { PwaProductItem } from '@/types/stock'

const CACHE_PREFIX = 'pwa-inv-products:'

function getCachedProducts(warehouseId: number): PwaProductItem[] | null {
    try {
        const raw = localStorage.getItem(`${CACHE_PREFIX}${warehouseId}`)
        if (!raw) return null
        const { data, timestamp } = JSON.parse(raw)
        // Cache valid for 4 hours
        if (Date.now() - timestamp > 4 * 60 * 60 * 1000) return null
        return data as PwaProductItem[]
    } catch { return null }
}

function setCachedProducts(warehouseId: number, products: PwaProductItem[]) {
    try {
        localStorage.setItem(`${CACHE_PREFIX}${warehouseId}`, JSON.stringify({
            data: products,
            timestamp: Date.now(),
        }))
    } catch { /* storage full */ }
}

export default function InventoryPwaCountPage() {
    const { warehouseId } = useParams<{ warehouseId: string }>()
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const [counts, setCounts] = useState<Record<number, string>>({})
    const [showQrScanner, setShowQrScanner] = useState(false)
    const [isOnline, setIsOnline] = useState(navigator.onLine)
    const [offlineSubmitted, setOfflineSubmitted] = useState(false)

    useEffect(() => {
        const handleOnline = () => setIsOnline(true)
        const handleOffline = () => setIsOnline(false)
        window.addEventListener('online', handleOnline)
        window.addEventListener('offline', handleOffline)
        return () => {
            window.removeEventListener('online', handleOnline)
            window.removeEventListener('offline', handleOffline)
        }
    }, [])

    const id = Number(warehouseId)
    const { data: productsRes, isLoading, isError, error } = useQuery({
        queryKey: ['inventory-pwa', 'warehouse-products', id],
        queryFn: async () => {
            const res = await stockApi.inventoryPwa.warehouseProducts(id)
            // Cache for offline use
            if (res?.data?.data) {
                setCachedProducts(id, res.data.data)
            }
            return res
        },
        enabled: Number.isFinite(id),
    })

    // Use cached data if API fails (offline)
    const apiProducts: PwaProductItem[] = productsRes?.data?.data ?? []
    const cachedProducts = apiProducts.length === 0 ? getCachedProducts(id) : null
    const products = apiProducts.length > 0 ? apiProducts : (cachedProducts ?? [])
    const usingCache = apiProducts.length === 0 && cachedProducts !== null

    const submitMut = useMutation({
        mutationFn: async (payload: { warehouse_id: number; items: Array<{ product_id: number; counted_quantity: number }> }) => {
            try {
                return await stockApi.inventoryPwa.submitCounts(payload)
            } catch (err) {
                // If offline, queue in localStorage for later
                if (!navigator.onLine) {
                    const queue = JSON.parse(localStorage.getItem('pwa-inv-submit-queue') ?? '[]')
                    queue.push({ ...payload, queued_at: new Date().toISOString() })
                    localStorage.setItem('pwa-inv-submit-queue', JSON.stringify(queue))
                    setOfflineSubmitted(true)
                    return { data: { message: 'Contagem salva offline. Será enviada quando a conexão retornar.', has_discrepancy: false, offline: true } }
                }
                throw err
            }
        },
        onSuccess: (res: { data?: { message?: string; has_discrepancy?: boolean; offline?: boolean } }) => {
            toast.success(res?.data?.message ?? 'Contagem enviada.')
            queryClient.invalidateQueries({ queryKey: ['inventory-pwa'] })
            if (res?.data?.has_discrepancy) {
                toast.warning('Foi detectada diferença em relação ao esperado. O responsável foi notificado.')
            }
            navigate('/estoque/inventario-pwa')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao enviar contagem.'))
        },
    })

    // Process offline submit queue when back online
    useEffect(() => {
        if (!isOnline) return
        const queue = JSON.parse(localStorage.getItem('pwa-inv-submit-queue') ?? '[]')
        if (queue.length === 0) return

        const processQueue = async () => {
            const remaining = []
            for (const item of queue) {
                try {
                    await stockApi.inventoryPwa.submitCounts(item)
                    toast.success('Contagem offline sincronizada com sucesso.')
                } catch {
                    remaining.push(item)
                }
            }
            if (remaining.length > 0) {
                localStorage.setItem('pwa-inv-submit-queue', JSON.stringify(remaining))
            } else {
                localStorage.removeItem('pwa-inv-submit-queue')
            }
            queryClient.invalidateQueries({ queryKey: ['inventory-pwa'] })
        }
        processQueue()
    }, [isOnline, queryClient])

    const handleSubmit = () => {
        if (!allFilled) {
            toast.error('Preencha a contagem de todos os itens antes de enviar.')
            return
        }
        const items = (products || []).map((p) => ({
            product_id: p.product_id,
            counted_quantity: Number(counts[p.product_id]) || 0,
        }))
        submitMut.mutate({ warehouse_id: id, items })
    }

    const filledCount = (products || []).filter((p) => counts[p.product_id] !== undefined && counts[p.product_id] !== '').length
    const totalCount = products.length
    const allFilled = totalCount > 0 && filledCount === totalCount

    const handleScanLabel = () => setShowQrScanner(true)

    const handleQrScanned = (raw: string) => {
        const productId = parseLabelQrPayload(raw)
        if (!productId) {
            toast.error('Código inválido.')
            return
        }
        const found = products.some((p) => p.product_id === productId)
        if (!found) {
            toast.error('Este produto não está na lista deste armazém.')
            return
        }
        document.getElementById(`product-row-${productId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }

    if (isLoading || !Number.isFinite(id)) {
        return (
            <div className="flex flex-col items-center justify-center min-h-[50vh] gap-4">
                <Loader2 className="w-10 h-10 animate-spin text-brand-500" />
                <p className="text-surface-500 font-medium">Carregando produtos...</p>
            </div>
        )
    }

    if (isError && !usingCache) {
        return (
            <div className="flex flex-col items-center justify-center min-h-[50vh] gap-4 px-4">
                <AlertTriangle className="w-10 h-10 text-red-500" />
                <p className="text-surface-700 font-medium text-center">Erro ao carregar produtos</p>
                <p className="text-sm text-surface-500 text-center">{getApiErrorMessage(error, 'Não foi possível carregar os produtos deste armazém.')}</p>
                <button
                    type="button"
                    onClick={() => navigate('/estoque/inventario-pwa')}
                    className="mt-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors"
                >
                    Voltar
                </button>
            </div>
        )
    }

    return (
        <div className="flex flex-col min-h-screen bg-surface-50 pb-28">
            <header className="bg-surface-0 border-b border-default sticky top-0 z-10 px-4 py-3">
                <div className="max-w-2xl mx-auto flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => navigate('/estoque/inventario-pwa')}
                        className="p-2 hover:bg-surface-100 rounded-full transition-colors"
                        aria-label="Voltar"
                    >
                        <ArrowLeft className="w-5 h-5 text-surface-600" />
                    </button>
                    <div className="flex-1">
                        <h1 className="text-lg font-bold text-surface-900">Contagem</h1>
                        <p className="text-xs text-surface-500">Informe a quantidade contada de cada item</p>
                    </div>
                    {!isOnline && (
                        <div className="flex items-center gap-1 text-amber-600 text-xs font-medium">
                            <WifiOff className="w-3.5 h-3.5" />
                            Offline
                        </div>
                    )}
                    {products.length > 0 && (
                        <button
                            type="button"
                            onClick={handleScanLabel}
                            className="p-2 hover:bg-surface-100 rounded-full transition-colors"
                            title="Escanear etiqueta"
                            aria-label="Escanear etiqueta"
                        >
                            <QrCode className="w-5 h-5 text-surface-600" />
                        </button>
                    )}
                </div>
            </header>

            {usingCache && (
                <div className="bg-amber-50 border-b border-amber-200 px-4 py-2 text-center">
                    <p className="text-xs text-amber-700 font-medium flex items-center justify-center gap-1">
                        <WifiOff className="w-3.5 h-3.5" />
                        Usando dados em cache. A contagem será enviada quando a conexão retornar.
                    </p>
                </div>
            )}

            <main className="flex-1 max-w-2xl mx-auto w-full p-4 space-y-4">
                {products.length === 0 ? (
                    <div className="rounded-xl border border-default bg-surface-0 p-8 text-center text-surface-500">
                        Nenhum produto neste armazém para contagem.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {(products || []).map((item) => (
                            <div
                                id={`product-row-${item.product_id}`}
                                key={item.product_id}
                                className={cn(
                                    'rounded-xl border bg-surface-0 p-4',
                                    counts[item.product_id] !== undefined && counts[item.product_id] !== ''
                                        ? 'border-emerald-200 bg-emerald-50/30'
                                        : 'border-default'
                                )}
                            >
                                <div className="flex items-center justify-between gap-4">
                                    <div className="flex items-center gap-3 min-w-0">
                                        <div className="p-2 rounded-lg bg-surface-100 shrink-0">
                                            <Package className="w-5 h-5 text-surface-600" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="font-semibold text-surface-900 truncate">{item.product.name}</p>
                                            <p className="text-xs text-surface-500">
                                                {item.product.code ?? '—'} • Esperado: {Number(item.expected_quantity)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <label className="sr-only">Quantidade contada</label>
                                        <input
                                            type="number"
                                            min={0}
                                            step="any"
                                            value={counts[item.product_id] ?? ''}
                                            onChange={(e) =>
                                                setCounts((prev) => ({ ...prev, [item.product_id]: e.target.value }))
                                            }
                                            placeholder="0"
                                            className="w-20 text-right px-3 py-2 border border-default rounded-lg font-medium focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none"
                                        />
                                        {item.product.unit && (
                                            <span className="text-xs text-surface-500 w-6">{item.product.unit}</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </main>

            <div className="fixed bottom-0 left-0 right-0 bg-surface-0 border-t border-default p-4 safe-area-bottom">
                <div className="max-w-2xl mx-auto flex flex-col gap-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-surface-500">Itens preenchidos</span>
                        <span className="font-medium text-brand-600">
                            {filledCount}/{totalCount}
                        </span>
                    </div>
                    <div className="h-1.5 w-full bg-surface-100 rounded-full overflow-hidden">
                        <div
                            className="h-full bg-brand-500 transition-all duration-300"
                            style={{ width: `${totalCount ? (filledCount / totalCount) * 100 : 0}%` }}
                        />
                    </div>
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={submitMut.isPending || products.length === 0 || offlineSubmitted}
                        className={cn(
                            'w-full py-3 rounded-xl font-semibold flex items-center justify-center gap-2',
                            allFilled
                                ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                : 'bg-brand-600 hover:bg-brand-700 text-white',
                            (submitMut.isPending || products.length === 0 || offlineSubmitted) && 'opacity-60 cursor-not-allowed'
                        )}
                    >
                        {submitMut.isPending ? (
                            <Loader2 className="w-5 h-5 animate-spin" />
                        ) : offlineSubmitted ? (
                            <>
                                <WifiOff className="w-5 h-5" />
                                Contagem salva offline
                            </>
                        ) : (
                            <>
                                <Save className="w-5 h-5" />
                                {isOnline ? 'Enviar contagem' : 'Salvar offline'}
                            </>
                        )}
                    </button>
                    {!allFilled && products.length > 0 && (
                        <p className="text-xs text-surface-500 flex items-center gap-1">
                            <AlertTriangle className="w-3.5 h-3.5" />
                            Preencha todos os itens antes de enviar para maior precisão.
                        </p>
                    )}
                </div>
            </div>

            <QrScannerModal
                open={showQrScanner}
                onClose={() => setShowQrScanner(false)}
                onScan={handleQrScanned}
                title="Escanear etiqueta"
            />
        </div>
    )
}
