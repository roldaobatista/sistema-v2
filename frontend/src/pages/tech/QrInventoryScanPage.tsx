import { useState, useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { RotateCcw, WifiOff, ScanBarcode, ArrowRight } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

const scanSchema = z.object({
    scannedHash: z.string().min(1, 'Código QR é obrigatório'),
    quantity: z.coerce.number().min(1, 'Quantidade inválida'),
    type: z.enum(['entry', 'exit']),
    warehouseId: z.string().min(1, 'Selecione o armazém'),
})

type ScanFormData = z.infer<typeof scanSchema>

export default function QrInventoryScanPage() {
    const navigate = useNavigate()
    const [searchParams] = useSearchParams()
    const codeFromUrl = searchParams.get('code') ?? ''
    const [isOnline, setIsOnline] = useState(() => navigator.onLine)

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

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        reset,
        formState: { errors }
    } = useForm<ScanFormData>({
        resolver: zodResolver(scanSchema),
        defaultValues: {
            scannedHash: '',
            quantity: 1,
            type: 'exit',
            warehouseId: '',
        }
    })

    const scannedHash = watch('scannedHash')

    const { data: warehouses } = useQuery({
        queryKey: ['tech-warehouses'],
        queryFn: async () => {
            const res = await api.get<{ data?: { id: number; name: string }[] }>('/stock/warehouses', { params: { per_page: 50 } })
            return res.data?.data ?? (res.data as { id: number; name: string }[]) ?? []
        },
    })

    const scanMutation = useMutation({
        mutationFn: (data: Record<string, string | number>) => api.post('/stock/scan-qr', data),
        onSuccess: (res) => {
            toast.success(`Movimentação do produto ${res.data.product?.name ?? ''} registrada!`)
            reset({ scannedHash: '', quantity: 1, type: 'exit', warehouseId: '' })
        },
        onError: (error: unknown) => {
            toast.error((error as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Erro ao processar QR Code')
        },
    })

    useEffect(() => {
        if (codeFromUrl && !scannedHash) {
            setValue('scannedHash', codeFromUrl, { shouldValidate: true })
        }
    }, [codeFromUrl, scannedHash, setValue])

    const onSubmit = (data: ScanFormData) => {
        if (!isOnline) {
            toast.warning('Você está offline. Operação não permitida.')
            return
        }
        scanMutation.mutate({
            qr_hash: data.scannedHash,
            quantity: data.quantity,
            type: data.type,
            warehouse_id: data.warehouseId
        })
    }

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between gap-2 px-1">
                <PageHeader title="Scanner PWA - Baú Móvel" subtitle="Leia o QR Code da peça para registrar movimentação" />
                {!isOnline && (
                    <span className="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 font-medium">
                        <WifiOff className="w-4 h-4" />
                        offline
                    </span>
                )}
            </div>

            {!isOnline && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <p className="text-sm text-amber-800 text-center">
                        Você está offline. A leitura de estoque exige uma conexão ativa.
                    </p>
                </div>
            )}

            {!scannedHash ? (
                <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card flex flex-col items-center justify-center space-y-4">
                    <div className="w-16 h-16 rounded-full bg-surface-100 flex items-center justify-center mb-2">
                        <ScanBarcode className="w-8 h-8 text-surface-400" />
                    </div>
                    <p className="text-center text-sm text-surface-500 max-w-[250px]">
                        Nenhum código detectado para registrar movimentação de estoque.
                    </p>
                    <Button
                        onClick={() => navigate('/tech/barcode')}
                        className="w-full flex items-center justify-center gap-2 mt-4"
                    >
                        Abrir Leitor de Códigos
                        <ArrowRight className="w-4 h-4" />
                    </Button>
                </div>
            ) : (
                <form onSubmit={handleSubmit(onSubmit)} className="rounded-xl border border-default bg-surface-0 p-6 shadow-card space-y-4">
                    <div>
                        <p className="text-sm font-medium text-brand-600">Código Capturado</p>
                        <p className="mt-1 break-all rounded bg-surface-50 p-2 text-sm font-mono">{scannedHash}</p>
                        <input type="hidden" {...register('scannedHash')} />
                        {errors.scannedHash && <p className="text-[10px] text-red-500 mt-1">{errors.scannedHash.message}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-surface-700">Armazém (Baú do Veículo)</label>
                        <select
                            {...register('warehouseId')}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            aria-label="Armazém"
                        >
                            <option value="">Selecione...</option>
                            {(warehouses ?? []).map((w: { id: number; name: string }) => (
                                <option key={w.id} value={w.id}>{w.name}</option>
                            ))}
                        </select>
                        {errors.warehouseId && <p className="text-[10px] text-red-500 mt-1">{errors.warehouseId.message}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-surface-700">Tipo de Movimentação</label>
                        <select
                            {...register('type')}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            aria-label="Tipo de movimentação"
                        >
                            <option value="exit">Saída (Retirada do Baú)</option>
                            <option value="entry">Entrada (Devolução ao Baú)</option>
                        </select>
                        {errors.type && <p className="text-[10px] text-red-500 mt-1">{errors.type.message}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-surface-700">Quantidade</label>
                        <input
                            type="number"
                            min={1}
                            {...register('quantity')}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                        />
                        {errors.quantity && <p className="text-[10px] text-red-500 mt-1">{errors.quantity.message}</p>}
                    </div>

                    <div className="flex flex-col gap-2 pt-2">
                        <Button
                            type="submit"
                            loading={scanMutation.isPending}
                            disabled={!isOnline}
                            className="w-full"
                        >
                            Confirmar Movimentação
                        </Button>
                        <Button type="button" variant="outline" onClick={() => { setValue('scannedHash', ''); navigate('/tech/barcode') }} className="w-full" icon={<RotateCcw className="h-4 w-4" />}>
                            Ler Outro Código
                        </Button>
                    </div>
                </form>
            )}
        </div>
    )
}
