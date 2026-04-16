import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, FileText, Loader2, Paperclip, Send, User } from 'lucide-react'
import { formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { buildStorageUrl } from '@/lib/api'
import { workOrderApi } from '@/lib/work-order-api'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'

const CHAT_POLL_INTERVAL_MS = 10_000

const CHAT_QUERY_KEYS = {
    messages: (workOrderId: number) => ['work-orders', workOrderId, 'chats'] as const,
}

interface Message {
    id: number
    user_id: number
    user?: {
        name: string
        avatar_url?: string
    }
    message: string
    type: 'text' | 'system' | 'file'
    file_path?: string
    created_at: string
}

interface AdminChatTabProps {
    workOrderId: number
}

interface ApiError {
    response?: {
        status?: number
        data?: {
            message?: string
        }
    }
}

interface ApiEnvelope<T> {
    data?: T
}

interface SendChatVariables {
    payload: FormData | { message: string; type: 'text' }
    config?: { headers?: Record<string, string> }
}

function extractApiError(error: unknown, fallbackMessage: string): { status?: number; message: string } {
    const apiError = error as ApiError
    return {
        status: apiError?.response?.status,
        message: apiError?.response?.data?.message ?? fallbackMessage,
    }
}

function normalizeMessages(response: { data?: ApiEnvelope<Message[]> }): Message[] {
    const payload = response.data
    return Array.isArray(payload?.data) ? payload.data : []
}

function normalizeMessage(response: { data?: ApiEnvelope<Message> }): Message | undefined {
    return response.data?.data
}

export default function AdminChatTab({ workOrderId }: AdminChatTabProps) {
    const { user } = useAuthStore()
    const queryClient = useQueryClient()
    const [newMessage, setNewMessage] = useState('')
    const scrollRef = useRef<HTMLDivElement>(null)
    const fileInputRef = useRef<HTMLInputElement>(null)
    const markAsReadForWorkOrderRef = useRef<number | null>(null)

    const chatQuery = useQuery({
        queryKey: CHAT_QUERY_KEYS.messages(workOrderId),
        queryFn: async () => normalizeMessages(await workOrderApi.chats(workOrderId)),
        enabled: workOrderId > 0,
        retry: false,
        refetchInterval: (query) => {
            const parsed = query.state.error
                ? extractApiError(query.state.error, 'Erro ao carregar mensagens.')
                : null

            return parsed?.status === 403 ? false : CHAT_POLL_INTERVAL_MS
        },
    })

    const markAsReadMutation = useMutation({
        mutationFn: () => workOrderApi.markChatsRead(workOrderId),
        onError: (error: unknown) => {
            const parsed = extractApiError(error, 'Erro ao atualizar leitura do chat.')
            if (parsed.status !== 403) {
                toast.error(parsed.message)
            }
        },
    })

    const sendMessageMutation = useMutation({
        mutationFn: ({ payload, config }: SendChatVariables) =>
            workOrderApi.sendChatMessage(workOrderId, payload, config),
        onSuccess: (response) => {
            const message = normalizeMessage(response)
            if (message) {
                queryClient.setQueryData<Message[]>(
                    CHAT_QUERY_KEYS.messages(workOrderId),
                    (currentMessages) => [...(currentMessages ?? []), message]
                )
            }
            setNewMessage('')
        },
        onError: (error: unknown) => {
            const parsed = extractApiError(error, 'Erro ao enviar mensagem.')
            if (parsed.status !== 403) {
                toast.error(parsed.message)
            }
        },
    })

    useEffect(() => {
        markAsReadForWorkOrderRef.current = null
        markAsReadMutation.reset()
        sendMessageMutation.reset()
    }, [workOrderId])

    useEffect(() => {
        if (!chatQuery.isSuccess || markAsReadForWorkOrderRef.current === workOrderId) {
            return
        }

        markAsReadForWorkOrderRef.current = workOrderId
        markAsReadMutation.mutate()
    }, [chatQuery.isSuccess, markAsReadMutation, workOrderId])

    useEffect(() => {
        if (scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight
        }
    }, [chatQuery.data])

    const retryLoad = async () => {
        sendMessageMutation.reset()
        const result = await chatQuery.refetch()
        if (result.isSuccess) {
            markAsReadMutation.mutate()
        }
    }

    const handleSendMessage = async (event: React.FormEvent) => {
        event.preventDefault()

        if (!newMessage.trim() || sendMessageMutation.isPending || accessDenied) {
            return
        }

        sendMessageMutation.mutate({
            payload: {
                message: newMessage,
                type: 'text',
            },
        })
    }

    const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0]
        if (!file || sendMessageMutation.isPending || accessDenied) {
            return
        }

        const formData = new FormData()
        formData.append('file', file)
        formData.append('message', `Enviou um arquivo: ${file.name}`)
        formData.append('type', 'file')

        sendMessageMutation.mutate(
            {
                payload: formData,
                config: {
                    headers: { 'Content-Type': 'multipart/form-data' },
                },
            },
            {
                onSettled: () => {
                    if (fileInputRef.current) {
                        fileInputRef.current.value = ''
                    }
                },
            }
        )
    }

    const queryError = chatQuery.error ? extractApiError(chatQuery.error, 'Erro ao carregar mensagens.') : null
    const markAsReadError = markAsReadMutation.error
        ? extractApiError(markAsReadMutation.error, 'Erro ao atualizar leitura do chat.')
        : null
    const sendMessageError = sendMessageMutation.error
        ? extractApiError(sendMessageMutation.error, 'Erro ao enviar mensagem.')
        : null
    const accessDenied = queryError?.status === 403 || markAsReadError?.status === 403 || sendMessageError?.status === 403
    const errorMessage = queryError && queryError.status !== 403 ? queryError.message : null
    const messages = chatQuery.data ?? []
    const loading = chatQuery.isLoading
    const sending = sendMessageMutation.isPending

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center py-20 gap-3 text-surface-400">
                <Loader2 className="w-8 h-8 animate-spin" />
                <p className="text-sm font-medium">Carregando historico do chat...</p>
            </div>
        )
    }

    if (accessDenied) {
        return (
            <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                <div className="flex flex-col items-center justify-center py-12 gap-3 text-center">
                    <p className="text-sm font-semibold text-surface-700">Sem permissao para acessar o chat desta OS.</p>
                    <p className="text-xs text-surface-500">Verifique o tenant ativo e as permissoes do usuario.</p>
                    <Button variant="outline" onClick={retryLoad}>Tentar novamente</Button>
                </div>
            </div>
        )
    }

    if (errorMessage) {
        return (
            <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                <div className="flex flex-col items-center justify-center py-12 gap-3 text-center">
                    <p className="text-sm font-semibold text-surface-700">{errorMessage}</p>
                    <Button variant="outline" onClick={retryLoad}>Recarregar chat</Button>
                </div>
            </div>
        )
    }

    return (
        <div className="flex flex-col h-[600px] bg-surface-50 rounded-xl border border-default overflow-hidden">
            <div ref={scrollRef} className="flex-1 overflow-y-auto p-6 space-y-6">
                {messages.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-full gap-3 text-center px-8">
                        <div className="w-16 h-16 rounded-3xl bg-surface-100 flex items-center justify-center text-surface-300">
                            <Send className="w-8 h-8" />
                        </div>
                        <p className="text-sm font-medium text-surface-500 max-w-xs">
                            Nenhuma mensagem ainda. O chat interno registra a comunicacao entre o campo e o escritorio.
                        </p>
                    </div>
                ) : (
                    messages.map((message: Message) => {
                        const isOwn = message.user_id === user?.id
                        const isSystem = message.type === 'system'
                        const fileUrl = buildStorageUrl(message.file_path)

                        if (isSystem) {
                            return (
                                <div key={message.id} className="flex justify-center my-4">
                                    <div className="bg-surface-200/50 px-4 py-1.5 rounded-full border border-border/50">
                                        <p className="text-[11px] font-bold text-surface-500 text-center uppercase tracking-wider">
                                            {message.message.replace(/\*\*/g, '')} - {formatDistanceToNow(new Date(message.created_at), { locale: ptBR, addSuffix: true })}
                                        </p>
                                    </div>
                                </div>
                            )
                        }

                        return (
                            <div
                                key={message.id}
                                className={cn('flex gap-3 max-w-[80%]', isOwn ? 'ml-auto flex-row-reverse' : 'flex-row')}
                            >
                                <div className="flex-shrink-0 mt-1">
                                    <div className="w-8 h-8 rounded-full bg-surface-200 flex items-center justify-center overflow-hidden border border-default">
                                        {message.user?.avatar_url ? (
                                            <img src={message.user.avatar_url} alt={message.user.name} className="w-full h-full object-cover" />
                                        ) : (
                                            <User className="w-4 h-4 text-surface-400" />
                                        )}
                                    </div>
                                </div>

                                <div className={cn('flex flex-col', isOwn ? 'items-end' : 'items-start')}>
                                    <div className={cn('px-4 py-3 rounded-2xl text-sm shadow-sm', isOwn ? 'bg-brand-600 text-white rounded-tr-none' : 'bg-card text-foreground rounded-tl-none border border-default')}>
                                        {message.type === 'file' ? (
                                            <div className="flex items-center gap-3 pr-2">
                                                <div className="w-10 h-10 rounded-lg bg-black/10 flex items-center justify-center">
                                                    <FileText className="w-5 h-5" />
                                                </div>
                                                <div className="flex-1 min-w-0 pr-4">
                                                    <p className="font-medium text-xs truncate mb-1">Arquivo enviado</p>
                                                    {fileUrl && (
                                                        <a
                                                            href={fileUrl}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-[10px] underline opacity-80 hover:opacity-100 flex items-center gap-1"
                                                        >
                                                            <Download className="w-3 h-3" />
                                                            Baixar arquivo
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="leading-relaxed whitespace-pre-wrap">{message.message}</p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2 mt-1.5 px-1">
                                        <span className="text-[10px] font-bold text-surface-400 uppercase tracking-tight">{message.user?.name}</span>
                                        <span className="text-[10px] text-surface-400 tabular-nums">
                                            {formatDistanceToNow(new Date(message.created_at), { locale: ptBR, addSuffix: true })}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        )
                    })
                )}
            </div>

            <div className="px-6 py-4 bg-card border-t border-default">
                <form onSubmit={handleSendMessage} className="flex items-center gap-3">
                    <input
                        type="file"
                        ref={fileInputRef}
                        onChange={handleFileUpload}
                        className="hidden"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="rounded-xl bg-surface-50 hover:bg-surface-100"
                        onClick={() => fileInputRef.current?.click()}
                        aria-label="Anexar arquivo"
                        disabled={sending}
                    >
                        <Paperclip className="w-5 h-5 text-surface-500" />
                    </Button>

                    <div className="flex-1 relative">
                        <input
                            type="text"
                            value={newMessage}
                            onChange={event => setNewMessage(event.target.value)}
                            placeholder="Escreva uma mensagem para o tecnico..."
                            className="w-full bg-surface-50 border-none rounded-xl px-5 py-3 text-sm focus:ring-2 focus:ring-brand-500 transition-all"
                            disabled={sending}
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={!newMessage.trim() || sending}
                        className="rounded-xl px-6 h-[44px] shadow-lg shadow-brand-500/20"
                    >
                        {sending ? <Loader2 className="w-5 h-5 animate-spin" /> : <>Enviar <Send className="w-4 h-4 ml-2" /></>}
                    </Button>
                </form>
            </div>
        </div>
    )
}
