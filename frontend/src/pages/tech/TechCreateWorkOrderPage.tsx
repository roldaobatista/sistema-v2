import { useNavigate } from 'react-router-dom'
import {
    ArrowLeft, Plus, Loader2, Camera, Trash2, MapPin, WifiOff,
} from 'lucide-react'
import { cn, getApiErrorMessage } from '@/lib/utils'
import api from '@/lib/api'
import { CustomerAsyncSelect, type CustomerAsyncSelectItem } from '@/components/common/CustomerAsyncSelect'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useOfflineMutation } from '@/hooks/useOfflineMutation'

interface Customer extends CustomerAsyncSelectItem {
    id: number
    name: string
}

const PRIORITY_OPTIONS = [
    { value: 'low', label: 'Baixa', color: 'bg-surface-200 text-surface-600' },
    { value: 'normal', label: 'Normal', color: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' },
    { value: 'high', label: 'Alta', color: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400' },
    { value: 'urgent', label: 'Urgente', color: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' },
] as const

const createWoSchema = z.object({
    customer: z.object({
        id: z.number(),
        name: z.string(),
    }).nullable().refine((val) => val !== null, { message: 'Selecione um cliente' }),
    description: z.string().min(1, 'A descrição é obrigatória'),
    priority: z.enum(['low', 'normal', 'high', 'urgent']).default('normal'),
    scheduled_date: z.string().optional(),
    scheduled_time: z.string().optional(),
    notes: z.string().optional(),
    service_type: z.string().optional(),
    address: z.string().optional(),
    photos: z.array(z.string()).default([]),
})

type CreateWoFormData = z.infer<typeof createWoSchema>

export default function TechCreateWorkOrderPage() {
    const navigate = useNavigate()
    const { user } = useAuthStore()

    const {
        register,
        control,
        handleSubmit,
        setValue,
        watch,
        formState: { errors, isSubmitting, isValid },
    } = useForm<CreateWoFormData>({
        resolver: zodResolver(createWoSchema),
        defaultValues: {
            customer: null,
            description: '',
            priority: 'normal',
            scheduled_date: '',
            scheduled_time: '',
            notes: '',
            service_type: '',
            address: '',
            photos: [],
        },
        mode: 'onChange',
    })

    const photos = watch('photos')

    const { mutate: offlineCreateWo, isPending: isOfflinePending, isOfflineQueued, isOnline } = useOfflineMutation<
        unknown,
        { mutations: Array<{ type: string; data: Record<string, unknown> }> }
    >({
        url: '/tech/sync/batch',
        invalidateKeys: [['work-orders'], ['tech-work-orders']],
        offlineToast: 'OS salva offline. Será sincronizada quando houver conexão.',
        onSuccess: (_data, wasOffline) => {
            if (wasOffline) {
                navigate('/tech')
            }
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Erro ao criar OS'))
        },
    })

    async function dataUrlToFile(dataUrl: string, fileName: string): Promise<File> {
        const response = await fetch(dataUrl)
        const blob = await response.blob()
        return new File([blob], fileName, { type: blob.type || 'image/jpeg' })
    }

    async function uploadCapturedPhotos(workOrderId: number, photosData: string[]) {
        for (const [index, photo] of photosData.entries()) {
            const formData = new FormData()
            const file = await dataUrlToFile(photo, `tech-capture-${index + 1}.jpg`)
            formData.append('file', file)
            formData.append('description', `Foto capturada no app do técnico #${index + 1}`)
            await api.post(`/work-orders/${workOrderId}/attachments`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
        }
    }

    function handlePhotoCapture(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0]
        if (!file) return
        const reader = new FileReader()
        reader.onload = () => {
            setValue('photos', [...photos, reader.result as string], { shouldValidate: true })
        }
        reader.readAsDataURL(file)
    }

    async function onSubmit(data: CreateWoFormData) {
        if (!data.customer) return

        const payload: Record<string, number | string | null | undefined> = {
            customer_id: data.customer.id,
            description: data.description,
            priority: data.priority,
            internal_notes: data.notes || null,
            assigned_to: user?.id,
            service_type: data.service_type || null,
            address: data.address || null,
        }
        if (data.scheduled_date) {
            payload.scheduled_date = data.scheduled_time
                ? `${data.scheduled_date} ${data.scheduled_time}`
                : data.scheduled_date
        }

        if (!isOnline) {
            // Offline: queue via sync engine
            await offlineCreateWo({
                mutations: [{ type: 'work_order_create', data: payload }],
            })
            return
        }

        // Online: direct API call (preserves photo upload flow)
        try {
            const { data: responseData } = await api.post('/work-orders', payload)
            const workOrderId = responseData.data?.id ?? responseData.id
            if (typeof workOrderId !== 'number') {
                throw new Error('ID da OS não retornado pela API')
            }
            if (typeof workOrderId === 'number' && data.photos.length > 0) {
                try {
                    await uploadCapturedPhotos(workOrderId, data.photos)
                } catch (uploadErr) {
                    toast.warning(getApiErrorMessage(uploadErr, 'OS criada, mas houve falha no envio das fotos'))
                }
            }
            toast.success('OS criada com sucesso!')
            navigate(`/tech/os/${workOrderId}`)
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao criar OS'))
        }
    }

    return (
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col h-full overflow-hidden">
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border shrink-0">
                <button type="button" onClick={() => navigate('/tech')} className="flex items-center gap-1 text-sm text-brand-600 mb-2">
                    <ArrowLeft className="w-4 h-4" /> Voltar
                </button>
                <h1 className="text-lg font-bold text-foreground">Nova Ordem de Serviço</h1>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                {/* Customer search */}
                <div className="bg-card rounded-xl p-4 space-y-3">
                    <Controller
                        name="customer"
                        control={control}
                        render={({ field }) => (
                            <CustomerAsyncSelect
                                label="Cliente *"
                                customerId={field.value?.id ?? null}
                                placeholder="Buscar cliente por nome, documento, telefone ou e-mail..."
                                onChange={(customer) => field.onChange((customer as Customer) || null)}
                            />
                        )}
                    />
                    {errors.customer && (
                        <p className="text-xs text-red-500">{errors.customer.message}</p>
                    )}
                </div>

                {/* Description */}
                <div className="bg-card rounded-xl p-4 space-y-3">
                    <label className="text-xs text-surface-500 font-medium">Descrição do Serviço *</label>
                    <textarea
                        {...register('description')}
                        placeholder="Descreva o serviço a ser realizado..."
                        rows={3}
                        className={cn(
                            "w-full px-3 py-2.5 rounded-lg bg-surface-100 border text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30 focus:outline-none resize-none",
                            errors.description ? "border-red-500" : "border-transparent"
                        )}
                    />
                    {errors.description && (
                        <p className="text-xs text-red-500">{errors.description.message}</p>
                    )}
                </div>

                {/* Priority */}
                <div className="bg-card rounded-xl p-4 space-y-3">
                    <label className="text-xs text-surface-500 font-medium">Prioridade</label>
                    <Controller
                        name="priority"
                        control={control}
                        render={({ field }) => (
                            <div className="flex gap-2">
                                {(PRIORITY_OPTIONS || []).map((opt) => (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() => field.onChange(opt.value)}
                                        className={cn(
                                            'flex-1 py-2 rounded-lg text-xs font-medium transition-all',
                                            field.value === opt.value
                                                ? cn(opt.color, 'ring-2 ring-brand-500/30')
                                                : 'bg-surface-100 text-surface-500'
                                        )}
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                        )}
                    />
                </div>

                {/* Schedule */}
                <div className="bg-card rounded-xl p-4 space-y-3">
                    <label className="text-xs text-surface-500 font-medium">Agendamento</label>
                    <div className="grid grid-cols-2 gap-2">
                        <input
                            type="date"
                            {...register('scheduled_date')}
                            className="px-3 py-2.5 rounded-lg bg-surface-100 border-0 text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none"
                        />
                        <input
                            type="time"
                            {...register('scheduled_time')}
                            className="px-3 py-2.5 rounded-lg bg-surface-100 border-0 text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none"
                        />
                    </div>
                </div>

                {/* Notes */}
                <div className="bg-card rounded-xl p-4 space-y-3">
                    <label className="text-xs text-surface-500 font-medium">Observações</label>
                    <textarea
                        {...register('notes')}
                        placeholder="Observações adicionais..."
                        rows={2}
                        className="w-full px-3 py-2.5 rounded-lg bg-surface-100 border-0 text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30 focus:outline-none resize-none"
                    />
                </div>

                {/* Tipo de Serviço */}
                <div className="bg-card rounded-xl p-4 space-y-3">
                    <label className="text-xs text-surface-500 font-medium">Tipo de Serviço</label>
                    <select
                        {...register('service_type')}
                        className="w-full px-3 py-2.5 rounded-lg bg-surface-100 border-0 text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none"
                        aria-label="Tipo de serviço"
                    >
                        <option value="">Selecione...</option>
                        <option value="corretiva">Manutenção Corretiva</option>
                        <option value="preventiva">Preventiva</option>
                        <option value="instalacao">Instalação</option>
                        <option value="calibracao">Calibração</option>
                        <option value="vistoria">Vistoria</option>
                        <option value="diagnostico">Diagnóstico</option>
                        <option value="retorno">Retorno</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>

                {/* Endereço */}
                <div className="bg-card rounded-xl p-4 space-y-3">
                    <label className="text-xs text-surface-500 font-medium flex items-center gap-1">
                        <MapPin className="w-3 h-3" /> Endereço do Serviço
                    </label>
                    <input
                        type="text"
                        {...register('address')}
                        placeholder="Endereço onde o serviço será realizado..."
                        className="w-full px-3 py-2.5 rounded-lg bg-surface-100 border-0 text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30 focus:outline-none"
                        aria-label="Endereço do serviço"
                    />
                </div>

                {/* Photos */}
                <div className="bg-card rounded-xl p-4 space-y-3">
                    <label className="text-xs text-surface-500 font-medium">Fotos</label>
                    <div className="grid grid-cols-3 gap-2">
                        {(photos || []).map((p, i) => (
                            <div key={i} className="relative aspect-square">
                                <img src={p} alt="" className="w-full h-full object-cover rounded-lg" />
                                <button
                                    type="button"
                                    onClick={() => setValue('photos', (photos || []).filter((_, idx) => idx !== i), { shouldValidate: true })}
                                    className="absolute top-1 right-1 w-6 h-6 rounded-full bg-red-600 text-white flex items-center justify-center shadow"
                                >
                                    <Trash2 className="w-3 h-3" />
                                </button>
                            </div>
                        ))}
                        <label className="aspect-square flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-surface-300 cursor-pointer active:bg-surface-50 transition-colors">
                            <Camera className="w-6 h-6 text-surface-400" />
                            <span className="text-[10px] text-surface-400 mt-1">Foto</span>
                            <input type="file" accept="image/*" capture="environment" onChange={handlePhotoCapture} className="hidden" />
                        </label>
                    </div>
                </div>

                {/* Offline indicator */}
                {!isOnline && (
                    <div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-xs font-medium">
                        <WifiOff className="w-4 h-4 shrink-0" />
                        <span>Sem conexão. A OS será salva offline e sincronizada automaticamente.</span>
                    </div>
                )}

                {isOfflineQueued && (
                    <div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-medium">
                        <WifiOff className="w-4 h-4 shrink-0" />
                        <span>OS salva na fila offline. Será enviada quando a conexão voltar.</span>
                    </div>
                )}

                {/* Submit */}
                <button
                    type="submit"
                    disabled={isSubmitting || isOfflinePending || !isValid}
                    className={cn(
                        'w-full flex items-center justify-center gap-2 py-3.5 rounded-xl text-sm font-semibold text-white transition-colors',
                        isValid
                            ? 'bg-brand-600 active:bg-brand-700'
                            : 'bg-surface-300',
                        (isSubmitting || isOfflinePending) && 'opacity-70',
                    )}
                >
                    {(isSubmitting || isOfflinePending) ? <Loader2 className="w-4 h-4 animate-spin" /> : <Plus className="w-4 h-4" />}
                    {!isOnline ? 'Salvar Offline' : 'Criar Ordem de Serviço'}
                </button>

                <div className="h-4" />
            </div>
        </form>
    )
}
