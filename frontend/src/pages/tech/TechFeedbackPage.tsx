import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    MessageSquare, Send, Camera, Loader2, ArrowLeft, MessageCircle, Reply, WifiOff,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import api, { getApiErrorMessage, buildStorageUrl } from '@/lib/api'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useOfflineMutation } from '@/hooks/useOfflineMutation'
import { useForm, Controller } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

const TYPE_OPTIONS = [
    { id: 'praise', label: 'Positivo', color: 'emerald' },
    { id: 'suggestion', label: 'Sugestão', color: 'blue' },
    { id: 'concern', label: 'Problema', color: 'amber' },
    { id: 'urgent', label: 'Urgente', color: 'red' },
] as const

const CATEGORIES = ['Processo', 'Equipamento', 'Cliente', 'Segurança', 'Ferramenta', 'Veículo']

const TYPE_COLORS: Record<string, string> = {
    praise: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30',
    suggestion: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    concern: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30',
}

const STATUS_LABELS: Record<string, string> = {
    sent: 'Enviado',
    read: 'Lido',
    replied: 'Respondido',
}

interface FeedbackItem {
    id: number
    type: string
    content: string
    created_at: string
    attachment_path?: string | null
    fromUser?: { id?: number; name: string }
    fromUser_id?: number
    toUser?: { name: string }
    manager_reply?: string
    read_at?: string
}

const feedbackSchema = z.object({
    type: z.string().min(1, 'Selecione o tipo'),
    category: z.string().optional(),
    title: z.string().min(1, 'O título é obrigatório'),
    message: z.string().min(1, 'A mensagem é obrigatória'),
})

type FeedbackFormData = z.infer<typeof feedbackSchema>

export default function TechFeedbackPage() {
    const navigate = useNavigate()
    const { user } = useAuthStore()
    const [tab, setTab] = useState<'enviar' | 'historico'>('enviar')
    const [attachment, setAttachment] = useState<File | null>(null)
    const [history, setHistory] = useState<FeedbackItem[]>([])
    const [loadingHistory, setLoadingHistory] = useState(false)
    const [managerId, setManagerId] = useState<number | null>(null)

    const {
        control,
        register,
        handleSubmit,
        reset,
        formState: { errors, isSubmitting },
    } = useForm<FeedbackFormData>({
        resolver: zodResolver(feedbackSchema),
        defaultValues: {
            type: 'praise',
            category: '',
            title: '',
            message: '',
        },
    })

    const { mutate: offlineMutate, isPending: isOfflinePending, isOfflineQueued } = useOfflineMutation<unknown, { mutations: Array<{ type: string; data: Record<string, unknown> }> }>({
        url: '/tech/sync/batch',
        method: 'POST',
        offlineToast: 'Feedback salvo offline. Será sincronizado quando houver conexão.',
        successToast: 'Feedback enviado!',
        onSuccess: (_data, wasOffline) => {
            reset()
            setAttachment(null)
            if (!wasOffline) {
                setTab('historico')
            }
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Erro ao enviar feedback'))
        },
    })

    useEffect(() => {
        api.get('/me').then((res) => {
            const payload = res.data?.data ?? res.data
            const currentUser = payload?.user ?? payload
            setManagerId(currentUser?.manager_id ?? null)
        }).catch(() => {
            // Manager ID is optional, continue without it
        })
    }, [])

    useEffect(() => {
        if (tab === 'historico') fetchHistory()

    }, [tab, user?.id])

    async function fetchHistory() {
        setLoadingHistory(true)
        try {
            const { data } = await api.get('/hr/continuous-feedback', { params: { per_page: 50 } })
            const raw = data?.data ?? data ?? []
            const list = Array.isArray(raw) ? raw : raw?.data ?? []
            const myId = user?.id
            const fromMe = (f: FeedbackItem) => (f.fromUser_id ?? f.fromUser?.id) === myId
            setHistory(myId ? (list || []).filter(fromMe) : list)
        } catch {
            toast.error('Erro ao carregar histórico')
        } finally {
            setLoadingHistory(false)
        }
    }

    const onSubmit = async (data: FeedbackFormData) => {
        const toId = managerId
        if (!toId) {
            toast.error('Gestor não configurado. Entre em contato com o RH.')
            return
        }

        const apiType = data.type === 'urgent' ? 'concern' : data.type
        const content = data.category ? `[${data.category}] ${data.title}\n\n${data.message}` : `${data.title}\n\n${data.message}`

        // Attachments cannot be queued offline, so use direct API call when there's a file
        if (attachment) {
            try {
                const formPayload = new FormData()
                formPayload.append('to_user_id', String(toId))
                formPayload.append('content', content)
                formPayload.append('type', apiType)
                formPayload.append('visibility', 'manager_only')
                formPayload.append('attachment', attachment)
                await api.post('/hr/continuous-feedback', formPayload, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                })
                toast.success('Feedback enviado!')
                reset()
                setAttachment(null)
                setTab('historico')
            } catch (err: unknown) {
                toast.error(getApiErrorMessage(err, 'Erro ao enviar feedback'))
            }
            return
        }

        const feedbackData = {
            to_user_id: toId,
            content,
            type: apiType,
            visibility: 'manager_only',
        }
        await offlineMutate({ mutations: [{ type: 'feedback', data: feedbackData }] })
    }

    return (
        <div className="flex flex-col h-full">
            <header className="bg-card px-4 py-3 flex items-center gap-3 border-b border-border">
                <button onClick={() => navigate('/tech')} className="p-1" aria-label="Voltar para a área técnica">
                    <ArrowLeft className="w-5 h-5 text-surface-600" />
                </button>
                <MessageSquare className="w-5 h-5 text-brand-600" />
                <h1 className="text-lg font-bold text-foreground">Feedback</h1>
            </header>

            <div className="flex border-b border-border">
                <button
                    title="Aba Enviar"
                    onClick={() => setTab('enviar')}
                    className={cn(
                        'flex-1 py-3 text-sm font-medium',
                        tab === 'enviar'
                            ? 'text-brand-600 border-b-2 border-brand-500'
                            : 'text-surface-500'
                    )}
                >
                    Enviar
                </button>
                <button
                    title="Aba Histórico"
                    onClick={() => setTab('historico')}
                    className={cn(
                        'flex-1 py-3 text-sm font-medium',
                        tab === 'historico'
                            ? 'text-brand-600 border-b-2 border-brand-500'
                            : 'text-surface-500'
                    )}
                >
                    Histórico
                </button>
            </div>

            <div className="flex-1 overflow-y-auto p-4">
                {tab === 'enviar' && (
                    <form id="feedback-form" onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                        <div className="bg-card rounded-xl p-4">
                            <p className="text-xs font-semibold text-surface-500 mb-2">Tipo</p>
                            <Controller
                                name="type"
                                control={control}
                                render={({ field: { value, onChange } }) => (
                                    <div className="flex flex-wrap gap-2">
                                        {TYPE_OPTIONS.map((opt) => (
                                            <button
                                                key={opt.id}
                                                type="button"
                                                title={opt.label}
                                                onClick={() => onChange(opt.id)}
                                                className={cn(
                                                    'px-3 py-1.5 rounded-lg text-xs font-medium',
                                                    value === opt.id && opt.color === 'emerald' && 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 ring-2 ring-emerald-500 ring-offset-1',
                                                    value === opt.id && opt.color === 'blue' && 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 ring-2 ring-blue-500 ring-offset-1',
                                                    value === opt.id && opt.color === 'amber' && 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 ring-2 ring-amber-500 ring-offset-1',
                                                    value === opt.id && opt.color === 'red' && 'bg-red-100 text-red-700 dark:bg-red-900/30 ring-2 ring-red-500 ring-offset-1',
                                                    value !== opt.id && 'bg-surface-100 text-surface-600 dark:text-surface-400'
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

                        <div className="bg-card rounded-xl p-4">
                            <p className="text-xs font-semibold text-surface-500 mb-2">Categoria</p>
                            <Controller
                                name="category"
                                control={control}
                                render={({ field: { value, onChange } }) => (
                                    <div className="flex flex-wrap gap-2">
                                        {CATEGORIES.map((cat) => (
                                            <button
                                                key={cat}
                                                type="button"
                                                title={cat}
                                                onClick={() => onChange(value === cat ? '' : cat)}
                                                className={cn(
                                                    'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                                    value === cat
                                                        ? 'bg-brand-100 text-brand-700 ring-2 ring-brand-500 ring-offset-1 dark:ring-offset-surface-900'
                                                        : 'bg-surface-100 text-surface-600 dark:text-surface-400 hover:bg-surface-200'
                                                )}
                                            >
                                                {cat}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            />
                        </div>

                        <div className="bg-card rounded-xl p-4 space-y-3">
                            <div>
                                <input
                                    type="text"
                                    placeholder="Título"
                                    title="Título"
                                    className={cn(
                                        "w-full px-3 py-2.5 rounded-lg bg-surface-100 border text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none transition-colors",
                                        errors.title ? "border-red-300 focus:border-red-500 focus:ring-red-500/20" : "border-transparent focus:border-brand-500"
                                    )}
                                    {...register('title')}
                                />
                                {errors.title && <p className="text-[10px] text-red-500 mt-1">{errors.title.message}</p>}
                            </div>
                            <div>
                                <textarea
                                    placeholder="Mensagem"
                                    title="Mensagem"
                                    rows={4}
                                    className={cn(
                                        "w-full px-3 py-2.5 rounded-lg bg-surface-100 border text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none resize-none transition-colors",
                                        errors.message ? "border-red-300 focus:border-red-500 focus:ring-red-500/20" : "border-transparent focus:border-brand-500"
                                    )}
                                    {...register('message')}
                                />
                                {errors.message && <p className="text-[10px] text-red-500 mt-1">{errors.message.message}</p>}
                            </div>
                            <label className="block">
                                <input
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    title="Anexar Foto"
                                    onChange={(e) => setAttachment(e.target.files?.[0] ?? null)}
                                />
                                <span className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-surface-100 text-sm font-medium text-surface-700 cursor-pointer hover:bg-surface-200 transition-colors">
                                    <Camera className="w-4 h-4" />
                                    Anexar Foto
                                    {attachment && <span className="text-xs text-brand-600">({attachment.name})</span>}
                                </span>
                            </label>
                        </div>

                        {/* Offline queued indicator */}
                        {isOfflineQueued && (
                            <div className="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400 text-sm font-medium">
                                <WifiOff className="w-4 h-4 shrink-0" />
                                Feedback salvo offline. Será enviado automaticamente quando houver conexão.
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={isSubmitting || isOfflinePending}
                            title="Enviar Feedback"
                            className="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-brand-600 text-white font-medium disabled:opacity-60 shadow-md shadow-brand-500/20 active:scale-[0.98] transition-all"
                        >
                            {(isSubmitting || isOfflinePending) ? <Loader2 className="w-5 h-5 animate-spin" /> : <Send className="w-5 h-5" />}
                            Enviar Feedback
                        </button>
                    </form>
                )}

                {tab === 'historico' && (
                    <div className="space-y-3">
                        {loadingHistory ? (
                            <div className="flex justify-center py-12">
                                <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                            </div>
                        ) : history.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 gap-2">
                                <MessageCircle className="w-12 h-12 text-surface-300" />
                                <p className="text-sm text-surface-500">Nenhum feedback enviado</p>
                            </div>
                        ) : (
                            history.map((item) => {
                                const typeColor = TYPE_COLORS[item.type] || 'bg-surface-200 text-surface-600'
                                const status = item.manager_reply ? 'replied' : item.read_at ? 'read' : 'sent'
                                return (
                                    <div key={item.id} className="bg-card rounded-xl p-4 space-y-2 border border-border shadow-sm">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-medium', typeColor)}>
                                                {item.type === 'praise' ? 'Positivo' : item.type === 'suggestion' ? 'Sugestão' : 'Problema'}
                                            </span>
                                            <span className="text-[10px] text-surface-500">
                                                {new Date(item.created_at).toLocaleDateString('pt-BR')}
                                            </span>
                                            <span className={cn(
                                                'text-[10px] px-1.5 py-0.5 rounded',
                                                status === 'replied' && 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30',
                                                status === 'read' && 'bg-blue-100 text-blue-700 dark:bg-blue-900/30',
                                                status === 'sent' && 'bg-surface-100 text-surface-600 dark:text-surface-400'
                                            )}>
                                                {STATUS_LABELS[status]}
                                            </span>
                                        </div>
                                        <p className="text-sm font-medium text-foreground">
                                            {item.content?.split('\n')[0]?.replace(/^\[.*?\]\s*/, '') || 'Feedback'}
                                        </p>
                                        <p className="text-xs text-surface-500 line-clamp-3 overflow-hidden text-ellipsis">{item.content}</p>
                                        {item.attachment_path && (
                                            <a
                                                href={buildStorageUrl(item.attachment_path) ?? '#'}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700 mt-1"
                                                title="Ver anexo na nova guia"
                                            >
                                                <Camera className="w-3 h-3" />
                                                Ver anexo
                                            </a>
                                        )}
                                        {item.manager_reply && (
                                            <div className="mt-2 p-3 rounded-lg bg-brand-50 border-l-2 border-brand-500 dark:bg-brand-900/10 dark:border-brand-600">
                                                <p className="text-xs font-medium text-brand-700 dark:text-brand-400 flex items-center gap-1">
                                                    <Reply className="w-3 h-3" /> Resposta do gestor
                                                </p>
                                                <p className="text-sm text-surface-700 dark:text-surface-300 mt-1">{item.manager_reply}</p>
                                            </div>
                                        )}
                                    </div>
                                )
                            })
                        )}
                    </div>
                )}
            </div>
        </div>
    )
}
