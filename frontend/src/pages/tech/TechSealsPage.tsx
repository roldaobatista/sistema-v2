import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, ShieldCheck, Camera, Loader2, Package, CheckCircle2, AlertTriangle, WifiOff } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/lib/api'
import { toast } from 'sonner'
import { cn, getApiErrorMessage } from '@/lib/utils'
import { useOfflineMutation } from '@/hooks/useOfflineMutation'

const useSealSchema = z.object({
    equipment_id: z.number({ required_error: 'Selecione um equipamento' }),
    seal_id: z.number({ required_error: 'Selecione o selo/lacre' }),
    photo: z.instanceof(File, { message: 'A foto da aplicação é obrigatória' })
})
type UseSealFormValues = z.infer<typeof useSealSchema>

interface TechSeal {
    id: number
    number: string
    type: 'seal' | 'seal_reparo'
}

export default function TechSealsPage() {
    const { id: woId } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const qc = useQueryClient()

    const [photoPreview, setPhotoPreview] = useState<string | null>(null)

    const {
        handleSubmit,
        setValue,
        watch,
        formState: { errors }
    } = useForm<UseSealFormValues>({
        resolver: zodResolver(useSealSchema)
    })

    const selectedEquipmentId = watch('equipment_id')
    const selectedSealId = watch('seal_id')

    // Clean up object URL to avoid memory leaks
    useEffect(() => {
        return () => {
            if (photoPreview) {
                URL.revokeObjectURL(photoPreview)
            }
        }
    }, [photoPreview])

    // Buscar equipamentos da OS
    const { data: woRes } = useQuery({
        queryKey: ['tech-wo-detail', woId],
        queryFn: () => api.get(`/tech/os/${woId}`)
    })
    const equipments = woRes?.data?.equipments || []

    // Buscar meus selos
    const { data: mySealsRes, isLoading: loadingSeals } = useQuery({
        queryKey: ['my-seals'],
        queryFn: () => api.get('/repair-seals/my-inventory')
    })
    const mySeals: TechSeal[] = mySealsRes?.data?.data ?? mySealsRes?.data ?? []

    const useMut = useMutation({
        mutationFn: ({ data }: { sealId: number, data: FormData }) => api.post('/repair-seals/use', data, {
            headers: { 'Content-Type': 'multipart/form-data' }
        }),
        onSuccess: () => {
            toast.success('Selo aplicado com sucesso!')
            qc.invalidateQueries({ queryKey: ['my-seals'] })
            navigate(`/tech/os/${woId}`)
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao aplicar selo'))
    })

    const offlineMut = useOfflineMutation<unknown, { mutations: { type: string; data: unknown }[] }>({
        url: '/tech/sync/batch',
        invalidateKeys: [['my-seals'], ['tech-wo-detail', woId!]],
        onSuccess: (_data, wasOffline) => {
            if (wasOffline) {
                navigate(`/tech/os/${woId}`)
            }
        },
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao aplicar selo')),
        offlineToast: 'Selo registrado offline. Será sincronizado quando houver conexão.',
        successToast: 'Selo aplicado com sucesso!',
    })

    const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (file) {
            setValue('photo', file, { shouldValidate: true })
            if (photoPreview) URL.revokeObjectURL(photoPreview)
            setPhotoPreview(URL.createObjectURL(file))
        }
    }

    const onSubmit = (data: UseSealFormValues) => {
        if (!offlineMut.isOnline) {
            // Offline: queue without photo (FormData with files cannot be serialized offline)
            offlineMut.mutate({
                mutations: [{
                    type: 'seal_application',
                    data: {
                        work_order_id: Number(woId),
                        seals: [{
                            seal_id: data.seal_id,
                            equipment_id: data.equipment_id,
                        }],
                    },
                }],
            })
            return
        }

        const fd = new FormData()
        fd.append('seal_id', data.seal_id.toString())
        fd.append('work_order_id', woId!)
        fd.append('equipment_id', data.equipment_id.toString())
        fd.append('photo', data.photo)

        useMut.mutate({ sealId: data.seal_id, data: fd })
    }

    return (
        <div className="flex flex-col h-full bg-surface-50">
            {/* Header */}
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                <button onClick={() => navigate(`/tech/os/${woId}`)} className="flex items-center gap-1 text-sm text-brand-600 mb-2">
                    <ArrowLeft className="w-4 h-4" /> Voltar
                </button>
                <h1 className="text-lg font-bold text-foreground">Aplicar Selo/Lacre</h1>
                {!offlineMut.isOnline && (
                    <div className="mt-2 flex items-center gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                        <WifiOff className="w-3.5 h-3.5 flex-shrink-0" />
                        <span>Modo offline — foto será enviada na sincronização</span>
                    </div>
                )}
                {offlineMut.isOfflineQueued && (
                    <div className="mt-2 flex items-center gap-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-1.5">
                        <WifiOff className="w-3.5 h-3.5 flex-shrink-0" />
                        <span>Selo registrado offline — pendente de sincronização</span>
                    </div>
                )}
            </div>

            <div className="flex-1 overflow-y-auto p-4 space-y-6">
                {/* Equipamento */}
                <div className="space-y-3">
                    <label className="text-xs font-semibold text-surface-500 uppercase tracking-wider flex items-center justify-between">
                        <span className="flex items-center gap-2"><Package className="w-4 h-4" /> Equipamento</span>
                    </label>
                    <div className="grid gap-2">
                        {equipments.length === 0 ? (
                            <p className="text-sm text-surface-500 italic">Nenhum equipamento vinculado a esta OS.</p>
                        ) : (equipments || []).map((eq: { id: number; brand?: string; model?: string; serial_number?: string }) => (
                            <button
                                key={eq.id}
                                onClick={() => setValue('equipment_id', eq.id, { shouldValidate: true })}
                                className={cn(
                                    "p-4 rounded-xl border text-left transition-all",
                                    selectedEquipmentId === eq.id
                                        ? "border-brand-500 bg-brand-50 ring-1 ring-brand-500"
                                        : "border-surface-200 bg-card"
                                )}
                            >
                                <p className="text-sm font-semibold">{eq.brand} - {eq.model}</p>
                                <p className="text-xs text-surface-500">Série: {eq.serial_number || 'N/I'}</p>
                            </button>
                        ))}
                    </div>
                    {errors.equipment_id && <p className="text-sm text-red-500">{errors.equipment_id.message}</p>}
                </div>

                {/* Seleção do Selo */}
                <div className="space-y-3">
                    <label className="text-xs font-semibold text-surface-500 uppercase tracking-wider flex items-center gap-2">
                        <ShieldCheck className="w-4 h-4" /> Selecionar Selo/Lacre
                    </label>
                    {loadingSeals ? (
                        <div className="flex items-center gap-2 text-sm text-surface-500"><Loader2 className="w-4 h-4 animate-spin" /> Carregando seus selos...</div>
                    ) : mySeals.length === 0 ? (
                        <div className="p-4 rounded-xl border border-amber-200 bg-amber-50 flex items-center gap-3">
                            <AlertTriangle className="w-5 h-5 text-amber-600" />
                            <p className="text-sm text-amber-700">Você não possui selos atribuídos. Solicite ao administrativo.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-2">
                            {(mySeals || []).map(seal => (
                                <button
                                    key={seal.id}
                                    onClick={() => setValue('seal_id', seal.id, { shouldValidate: true })}
                                    className={cn(
                                        "p-3 rounded-lg border text-center transition-all",
                                        selectedSealId === seal.id
                                            ? "border-brand-500 bg-brand-50 ring-1 ring-brand-500 font-bold"
                                            : "border-surface-200 bg-card text-sm"
                                    )}
                                >
                                    {seal.number}
                                    <span className="block text-[10px] opacity-60 font-normal">
                                        {seal.type === 'seal_reparo' ? 'Selo' : 'Lacre'}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                    {errors.seal_id && <p className="text-sm text-red-500">{errors.seal_id.message}</p>}
                </div>

                {/* Foto Obrigatória */}
                <div className="space-y-3">
                    <label className="text-xs font-semibold text-surface-500 uppercase tracking-wider flex items-center gap-2">
                        <Camera className="w-4 h-4" /> Foto da Aplicação <span className="text-red-500">*</span>
                    </label>
                    <div className="relative">
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            onChange={handlePhotoChange}
                            className="hidden"
                            id="photo-input"
                        />
                        <label
                            htmlFor="photo-input"
                            className={cn(
                                "flex flex-col items-center justify-center w-full aspect-video rounded-2xl border-2 border-dashed transition-all cursor-pointer overflow-hidden",
                                photoPreview
                                    ? "border-emerald-500"
                                    : errors.photo ? "border-red-400 bg-red-50" : "border-border bg-card"
                            )}
                        >
                            {photoPreview ? (
                                <img src={photoPreview} alt="Preview" className="w-full h-full object-cover" />
                            ) : (
                                <>
                                    <Camera className="w-10 h-10 text-surface-300 mb-2" />
                                    <p className="text-sm text-surface-500 font-medium">Bater Foto</p>
                                    <p className="text-[11px] text-surface-400">Obrigatório para prosseguir</p>
                                </>
                            )}
                        </label>
                        {photoPreview && (
                            <button
                                onClick={() => {
                                    setPhotoPreview(null);
                                    setValue('photo', undefined as unknown as File, { shouldValidate: true });
                                }}
                                className="absolute top-2 right-2 bg-red-600 text-white p-1 rounded-full shadow-lg"
                                title="Remover foto"
                            >
                                <ArrowLeft className="w-4 h-4 rotate-45" />
                            </button>
                        )}
                    </div>
                    {errors.photo && <p className="text-sm text-red-500">{errors.photo.message}</p>}
                </div>
            </div>

            {/* Ação */}
            <div className="p-4 bg-card border-t border-border safe-area-bottom">
                <button
                    onClick={handleSubmit(onSubmit)}
                    disabled={useMut.isPending || offlineMut.isPending}
                    className={cn(
                        "w-full flex items-center justify-center gap-2 py-4 rounded-xl text-base font-bold text-white shadow-lg transition-all active:scale-95",
                        (useMut.isPending || offlineMut.isPending) ? "bg-surface-300 text-surface-500" : "bg-brand-600 hover:bg-brand-700 shadow-brand-500/20"
                    )}
                >
                    {(useMut.isPending || offlineMut.isPending) ? (
                        <Loader2 className="w-5 h-5 animate-spin" />
                    ) : (
                        <>
                            <CheckCircle2 className="w-5 h-5" /> Confirmar Aplicação
                        </>
                    )}
                </button>
            </div>
        </div>
    )
}
