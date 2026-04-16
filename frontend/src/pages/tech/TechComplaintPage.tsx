import { useState, useEffect, useRef } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
    ArrowLeft, Camera, Shield, CheckCircle2,
    Loader2, Flag, Send, WifiOff,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import api, { getApiErrorMessage } from '@/lib/api'
import { toast } from 'sonner'
import { useForm, Controller } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { useOfflineMutation } from '@/hooks/useOfflineMutation'

const TYPE_OPTIONS = [
    { value: 'customer_complaint', label: 'Reclamação do Cliente' },
    { value: 'nonconformity', label: 'Não Conformidade' },
    { value: 'safety', label: 'Problema de Segurança' },
    { value: 'equipment_damage', label: 'Dano ao Equipamento' },
    { value: 'other', label: 'Outro' },
]

const SEVERITY_OPTIONS = [
    { value: 'low', label: 'Baixa', className: 'bg-surface-100' },
    { value: 'medium', label: 'Média', className: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700' },
    { value: 'high', label: 'Alta', className: 'bg-red-100 dark:bg-red-900/30 text-red-700' },
    { value: 'critical', label: 'Crítica', className: 'bg-red-700/30 text-red-200 dark:text-red-300' },
]

const CATEGORY_MAP: Record<string, string> = {
    customer_complaint: 'service',
    nonconformity: 'other',
    safety: 'other',
    equipment_damage: 'other',
    other: 'other',
}

const complaintSchema = z.object({
    type: z.string().min(1, 'Selecione o tipo'),
    severity: z.string().min(1, 'Selecione a severidade'),
    title: z.string().min(1, 'O título é obrigatório'),
    description: z.string().optional(),
    openCorrectiveAction: z.boolean().default(false),
})

type ComplaintFormData = z.infer<typeof complaintSchema>

export default function TechComplaintPage() {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const [loading, setLoading] = useState(true)
    const [success, setSuccess] = useState(false)
    const [wo, setWo] = useState<{ number?: string; os_number?: string; customer?: { name: string } } | null>(null)
    const [photoFile, setPhotoFile] = useState<File | null>(null)
    const fileInputRef = useRef<HTMLInputElement | null>(null)

    const { mutate: offlineMutate, isPending: offlinePending, isOfflineQueued } = useOfflineMutation<unknown, { mutations: { type: string; data: Record<string, unknown> }[] }>({
        url: '/tech/sync/batch',
        offlineToast: 'Ocorrência salva offline. Será sincronizada quando houver conexão.',
        successToast: 'Ocorrência registrada com sucesso!',
        onSuccess: () => setSuccess(true),
        onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao registrar ocorrência')),
    })

    const {
        control,
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
    } = useForm<ComplaintFormData>({
        resolver: zodResolver(complaintSchema),
        defaultValues: {
            type: '',
            severity: '',
            title: '',
            description: '',
            openCorrectiveAction: false,
        },
    })

    useEffect(() => {
        if (!id) return
        api.get(`/work-orders/${id}`)
            .then((res) => setWo(res.data?.data ?? res.data))
            .catch(() => toast.error('Não foi possível carregar a OS'))
            .finally(() => setLoading(false))
    }, [id])

    const onSubmit = async (data: ComplaintFormData) => {
        const customerId = (wo as { customer_id?: number; customer?: { id?: number } })?.customer_id
            ?? (wo as { customer?: { id?: number } })?.customer?.id

        if (!id || !customerId) {
            toast.error('OS sem cliente vinculado')
            return
        }

        const desc = data.description?.trim() ? `${data.title}\n\n${data.description}` : data.title
        const complaintData: Record<string, unknown> = {
            work_order_id: Number(id),
            customer_id: customerId,
            category: CATEGORY_MAP[data.type] || 'other',
            severity: data.severity,
            description: desc,
            open_corrective_action: data.openCorrectiveAction,
        }

        await offlineMutate({ mutations: [{ type: 'complaint', data: complaintData }] })

        // Upload photo separately if online and mutation succeeded
        if (photoFile && id && !isOfflineQueued) {
            const formData = new FormData()
            formData.append('file', photoFile)
            try {
                await api.post(`/work-orders/${id}/attachments`, formData)
            } catch {
                toast.warning('Ocorrência registrada, mas a foto não foi enviada')
            }
        }
    }

    if (loading) {
        return (
            <div className="flex flex-col h-full items-center justify-center gap-3">
                <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                <span className="text-sm text-surface-500">Carregando OS...</span>
            </div>
        )
    }

    if (success) {
        return (
            <div className="flex flex-col h-full">
                <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                    <button
                        onClick={() => navigate(`/tech/os/${id}`)}
                        className="flex items-center gap-1 text-sm text-brand-600 mb-2"
                        title="Voltar para a OS"
                    >
                        <ArrowLeft className="w-4 h-4" /> Voltar
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-4 py-8 flex flex-col items-center justify-center gap-4">
                    <CheckCircle2 className="w-16 h-16 text-emerald-500" />
                    <h2 className="text-lg font-semibold text-foreground">
                        Ocorrência registrada
                    </h2>
                    <p className="text-sm text-surface-500 text-center max-w-xs">
                        A ocorrência foi registrada com sucesso e será analisada pela equipe de qualidade.
                    </p>
                    <button
                        onClick={() => navigate(`/tech/os/${id}`)}
                        className="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition"
                        title="Voltar para a OS"
                    >
                        Voltar para a OS
                    </button>
                </div>
            </div>
        )
    }

    return (
        <div className="flex flex-col h-full">
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border shadow-sm z-10">
                <button
                    onClick={() => navigate(`/tech/os/${id}`)}
                    className="flex items-center gap-1 text-sm text-brand-600 mb-2"
                    title="Voltar para a OS"
                >
                    <ArrowLeft className="w-4 h-4" /> Voltar
                </button>
                <h1 className="text-lg font-bold text-foreground flex items-center gap-2">
                    <Flag className="w-5 h-5 text-brand-600" />
                    Registrar Ocorrência
                </h1>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4 pb-24">
                {wo && (
                    <div className="bg-card rounded-xl p-4 shadow-sm border border-border">
                        <p className="text-xs text-surface-500 mb-1">OS</p>
                        <p className="font-semibold text-foreground">
                            {wo.os_number ?? wo.number} · {wo.customer?.name ?? '—'}
                        </p>
                    </div>
                )}

                <form id="complaint-form" onSubmit={handleSubmit(onSubmit)} className="space-y-5">
                    <div>
                        <label className="text-xs font-medium text-surface-600 mb-2 block">
                            Tipo
                        </label>
                        <Controller
                            name="type"
                            control={control}
                            render={({ field: { value, onChange } }) => (
                                <div className="flex flex-wrap gap-2">
                                    {TYPE_OPTIONS.map((opt) => (
                                        <button
                                            key={opt.value}
                                            type="button"
                                            title={opt.label}
                                            onClick={() => onChange(opt.value)}
                                            className={cn(
                                                'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors border',
                                                value === opt.value
                                                    ? 'bg-brand-50 border-brand-200 text-brand-700 dark:bg-brand-900/30 dark:border-brand-800'
                                                    : 'bg-surface-50 border-border text-surface-600 hover:bg-surface-100'
                                            )}
                                        >
                                            {opt.label}
                                        </button>
                                    ))}
                                </div>
                            )}
                        />
                        {errors.type && <p className="text-[10px] text-red-500 mt-1">{errors.type.message}</p>}
                    </div>

                    <div>
                        <label className="text-xs font-medium text-surface-600 mb-2 block">
                            Severidade
                        </label>
                        <Controller
                            name="severity"
                            control={control}
                            render={({ field: { value, onChange } }) => (
                                <div className="flex flex-wrap gap-2">
                                    {SEVERITY_OPTIONS.map((opt) => (
                                        <button
                                            key={opt.value}
                                            type="button"
                                            title={opt.label}
                                            onClick={() => onChange(opt.value)}
                                            className={cn(
                                                'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors border border-transparent',
                                                value === opt.value
                                                    ? cn(opt.className, 'ring-2 ring-brand-500 ring-offset-1 dark:ring-offset-background')
                                                    : 'bg-surface-50 border-border text-surface-600 hover:bg-surface-100'
                                            )}
                                        >
                                            {opt.label}
                                        </button>
                                    ))}
                                </div>
                            )}
                        />
                        {errors.severity && <p className="text-[10px] text-red-500 mt-1">{errors.severity.message}</p>}
                    </div>

                    <div>
                        <label className="text-xs font-medium text-surface-600 mb-1 block">
                            Título *
                        </label>
                        <input
                            type="text"
                            placeholder="Resumo da ocorrência"
                            title="Resumo da ocorrência"
                            className={cn(
                                "w-full px-3 py-2.5 rounded-lg bg-surface-50 border text-sm transition-colors focus:ring-2 focus:ring-brand-500/30 focus:outline-none",
                                errors.title ? "border-red-300 focus:border-red-500 focus:ring-red-500/20" : "border-border focus:border-brand-500"
                            )}
                            {...register('title')}
                        />
                        {errors.title && <p className="text-[10px] text-red-500 mt-1">{errors.title.message}</p>}
                    </div>

                    <div>
                        <label className="text-xs font-medium text-surface-600 mb-1 block">
                            Descrição
                        </label>
                        <textarea
                            rows={3}
                            placeholder="Detalhes adicionais..."
                            title="Detalhes adicionais"
                            className="w-full px-3 py-2.5 rounded-lg bg-surface-50 border border-border text-sm transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none resize-none"
                            {...register('description')}
                        />
                    </div>

                    <div>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            className="hidden"
                            title="Foto"
                            onChange={(e) => setPhotoFile(e.target.files?.[0] ?? null)}
                        />
                        <button
                            type="button"
                            title="Adicionar foto da câmera"
                            onClick={() => fileInputRef.current?.click()}
                            className="flex items-center justify-center gap-2 w-full px-4 py-3 rounded-lg border border-dashed border-border bg-surface-50 text-surface-600 text-sm font-medium hover:bg-surface-100 transition-colors"
                        >
                            <Camera className="w-5 h-5 text-surface-400" />
                            {photoFile ? <span className="text-brand-600">{photoFile.name} (Trocar)</span> : 'Adicionar evidência fotográfica'}
                        </button>
                    </div>

                    <label className="flex items-center gap-3 p-3 rounded-lg border border-border bg-surface-50 cursor-pointer hover:bg-surface-100 transition-colors">
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500"
                            title="Abrir Ação Corretiva"
                            {...register('openCorrectiveAction')}
                        />
                        <div className="flex-1">
                            <span className="text-sm font-medium text-surface-800 flex items-center gap-1.5">
                                Abrir Ação Corretiva
                            </span>
                            <span className="text-[10px] text-surface-500 block mt-0.5">Sinaliza alta prioridade para o setor da Qualidade</span>
                        </div>
                        <Shield className="w-5 h-5 text-brand-500/50" />
                    </label>
                </form>
            </div>

            {/* Bottom Floating Action Bar */}
            <div className="fixed bottom-0 left-0 right-0 p-4 bg-card/80 backdrop-blur-md border-t border-border z-20 pb-[max(1rem,env(safe-area-inset-bottom))]">
                {isOfflineQueued && (
                    <div className="flex items-center gap-2 px-4 py-2 mb-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 text-sm">
                        <WifiOff className="w-4 h-4 flex-shrink-0" />
                        Ocorrência salva offline. Será sincronizada automaticamente.
                    </div>
                )}
                <button
                    type="submit"
                    form="complaint-form"
                    disabled={isSubmitting || offlinePending}
                    title="Registrar Ocorrência"
                    className="w-full flex items-center justify-center gap-2 py-3.5 rounded-xl bg-brand-600 text-white font-semibold text-sm disabled:opacity-60 shadow-md shadow-brand-500/20 active:scale-[0.98] transition-all"
                >
                    {(isSubmitting || offlinePending) ? (
                        <Loader2 className="w-5 h-5 animate-spin" />
                    ) : (
                        <Send className="w-5 h-5" />
                    )}
                    Registrar Ocorrência
                </button>
            </div>
        </div>
    )
}
