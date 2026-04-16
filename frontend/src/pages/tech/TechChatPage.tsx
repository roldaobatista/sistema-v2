import { useState, useRef, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Send, Mic, MicOff, Clock } from 'lucide-react'
import { useChatStoreForward, type ChatMessage } from '@/hooks/useChatStoreForward'
import { useVoiceToText } from '@/hooks/useVoiceToText'
import { useAuthStore } from '@/stores/auth-store'
import { cn } from '@/lib/utils'
import { buildStorageUrl } from '@/lib/api'
import { formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'

export default function TechChatPage() {

    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const workOrderId = parseInt(id || '0')
    const { user } = useAuthStore()
    const chat = useChatStoreForward(workOrderId)
    const voice = useVoiceToText()
    const [input, setInput] = useState('')
    const messagesEndRef = useRef<HTMLDivElement>(null)
    const inputRef = useRef<HTMLInputElement>(null)

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
    }

    useEffect(() => {
        scrollToBottom()
    }, [chat.messages])

    const handleSend = async () => {
        const text = input.trim() || voice.transcript.trim()
        if (!text || !user) return

        await chat.sendMessage(text, user.id, user.name)
        setInput('')
        voice.clearTranscript()
    }

    const handleVoiceToggle = () => {
        if (voice.isListening) {
            voice.stopListening()
        } else {
            voice.startListening()
        }
    }

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault()
            handleSend()
        }
    }

    const formatTime = (iso: string) => {
        return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    }

    const isOwnMessage = (msg: ChatMessage) => msg.user_id === user?.id
    const isSystemMessage = (msg: ChatMessage) => msg.type === 'system'

    return (
        <div className="flex flex-col h-full bg-surface-100">
            {/* Header */}
            <div className="bg-card px-4 py-3 flex items-center gap-3 border-b border-border shrink-0">
                <button onClick={() => navigate(-1)} className="p-1" title="Voltar">
                    <ArrowLeft className="w-5 h-5 text-surface-600" />
                </button>
                <div className="flex-1">
                    <h1 className="text-sm font-bold text-foreground">
                        Chat da OS #{workOrderId}
                    </h1>
                    <p className="text-xs text-surface-500">
                        {chat.pendingCount > 0
                            ? `${chat.pendingCount} mensagem(ns) pendente(s) de envio`
                            : 'Mensagens sincronizadas'
                        }
                    </p>
                </div>
            </div>

            {/* Messages */}
            <div className="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                {chat.messages.length === 0 && !chat.isLoading && (
                    <div className="flex flex-col items-center justify-center h-full text-surface-400">
                        <Send className="w-8 h-8 mb-2 opacity-40" />
                        <p className="text-sm">Nenhuma mensagem ainda</p>
                        <p className="text-xs">Envie uma mensagem sobre esta OS</p>
                    </div>
                )}

                {(chat.messages || []).map(msg => {
                    if (isSystemMessage(msg)) {
                        return (
                            <div key={msg.id} className="flex justify-center my-2">
                                <div className="bg-surface-200/50 dark:bg-surface-800/50 px-3 py-1.5 rounded-full border border-surface-200/50 dark:border-surface-700/50">
                                    <p className="text-[10px] font-medium text-surface-500 text-center">
                                        {msg.message.replace(/\*\*/g, '')} • {formatDistanceToNow(new Date(msg.created_at), { locale: ptBR, addSuffix: true })}
                                    </p>
                                </div>
                            </div>
                        )
                    }

                    return (
                        <div
                            key={msg.id}
                            className={cn(
                                'flex',
                                isOwnMessage(msg) ? 'justify-end' : 'justify-start'
                            )}
                        >
                            <div className={cn(
                                'max-w-[80%] rounded-2xl px-4 py-2.5',
                                isOwnMessage(msg)
                                    ? 'bg-brand-600 text-white rounded-br-sm'
                                    : 'bg-card text-foreground rounded-bl-sm border border-border'
                            )}>
                                {!isOwnMessage(msg) && (
                                    <p className="text-xs font-medium text-brand-600 mb-0.5">
                                        {msg.user?.name}
                                    </p>
                                )}

                                {msg.type === 'file' ? (
                                    <div className="space-y-1">
                                        <p className="text-sm leading-relaxed whitespace-pre-wrap wrap-break-word">{msg.message}</p>
                                        {msg.file_path && (
                                            <a
                                                href={buildStorageUrl(msg.file_path) ?? '#'}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className={cn(
                                                    'inline-flex text-xs underline',
                                                    isOwnMessage(msg) ? 'text-white/90' : 'text-brand-600'
                                                )}
                                            >
                                                Abrir arquivo
                                            </a>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm whitespace-pre-wrap wrap-break-word leading-relaxed">{msg.message}</p>
                                )}

                                <div className={cn(
                                    'flex items-center gap-1 mt-1',
                                    isOwnMessage(msg) ? 'justify-end' : 'justify-start'
                                )}>
                                    <Clock className="w-3 h-3 opacity-60" />
                                    <span className="text-[10px] opacity-60">{formatTime(msg.created_at)}</span>
                                    {msg.synced === false && (
                                        <span className="text-[10px] opacity-60 ml-1">⏳</span>
                                    )}
                                </div>
                            </div>
                        </div>
                    )
                })}
                <div ref={messagesEndRef} />
            </div>

            {/* Voice transcript preview */}
            {voice.isListening && (voice.transcript || voice.interimTranscript) && (
                <div className="px-4 py-2 bg-brand-50 border-t border-brand-200">
                    <p className="text-sm text-brand-700">
                        {voice.transcript}
                        <span className="opacity-50">{voice.interimTranscript}</span>
                    </p>
                </div>
            )}

            {/* Input */}
            <div className="bg-card border-t border-border px-3 py-2 shrink-0">
                <div className="flex items-center gap-2">
                    <button
                        onClick={handleVoiceToggle}
                        title={voice.isListening ? "Parar de ouvir" : "Iniciar gravação de voz"}
                        className={cn(
                            'p-2.5 rounded-full transition-colors',
                            voice.isListening
                                ? 'bg-red-500 text-white animate-pulse'
                                : 'bg-surface-100 text-surface-600'
                        )}
                    >
                        {voice.isListening ? <MicOff className="w-5 h-5" /> : <Mic className="w-5 h-5" />}
                    </button>

                    <input
                        ref={inputRef}
                        type="text"
                        value={voice.transcript || input}
                        onChange={e => {
                            if (voice.transcript) {
                                voice.clearTranscript()
                            }
                            setInput(e.target.value)
                        }}
                        onKeyDown={handleKeyDown}
                        placeholder={voice.isListening ? 'Ouvindo...' : 'Mensagem...'}
                        className="flex-1 px-4 py-2.5 rounded-full bg-surface-100 text-foreground text-sm border-none outline-none placeholder:text-surface-400"
                    />

                    <button
                        onClick={handleSend}
                        disabled={!input.trim() && !voice.transcript.trim()}
                        title="Enviar mensagem"
                        className="p-2.5 rounded-full bg-brand-600 text-white disabled:opacity-40 transition-opacity"
                    >
                        <Send className="w-5 h-5" />
                    </button>
                </div>

                {voice.error && (
                    <p className="text-xs text-red-500 mt-1 px-2">{voice.error}</p>
                )}
            </div>
        </div>
    )
}
