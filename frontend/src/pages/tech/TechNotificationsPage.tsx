import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    ArrowLeft, Bell, BellOff, Loader2, CheckCheck, Trash2,
    Wrench, AlertCircle, MessageSquare, DollarSign, Calendar, Info,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import api from '@/lib/api'
import { extractNotificationList } from '@/lib/notifications'
import { toast } from 'sonner'

interface Notification {
    id: string
    type: string
    title: string
    body?: string
    message?: string
    data: Record<string, unknown> | null
    read_at: string | null
    created_at: string
}

const TYPE_CONFIG: Record<string, { icon: typeof Bell; color: string }> = {
    work_order: { icon: Wrench, color: 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' },
    alert: { icon: AlertCircle, color: 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' },
    message: { icon: MessageSquare, color: 'bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400' },
    financial: { icon: DollarSign, color: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' },
    schedule: { icon: Calendar, color: 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' },
    info: { icon: Info, color: 'bg-surface-100 text-surface-600' },
}

function formatTimeAgo(date: string) {
    const diff = Date.now() - new Date(date).getTime()
    const minutes = Math.floor(diff / 60000)
    if (minutes < 1) return 'agora'
    if (minutes < 60) return `${minutes}min`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d`
    return new Date(date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

export default function TechNotificationsPage() {
    const navigate = useNavigate()
    const qc = useQueryClient()
    const [filter, setFilter] = useState<'all' | 'unread'>('all')

    const { data: notifications = [], isLoading } = useQuery<Notification[]>({
        queryKey: ['tech-notifications'],
        queryFn: async () => {
            const { data } = await api.get('/notifications', { params: { per_page: 50 } })
            return extractNotificationList<Notification>(data)
        },
        staleTime: 30_000,
    })

    const invalidateAll = () => {
        qc.invalidateQueries({ queryKey: ['tech-notifications'] })
        qc.invalidateQueries({ queryKey: ['notifications'] })
        qc.invalidateQueries({ queryKey: ['notifications-count'] })
    }

    const markRead = useMutation({
        mutationFn: (id: string) => api.put(`/notifications/${id}/read`),
        onMutate: async (id) => {
            await qc.cancelQueries({ queryKey: ['tech-notifications'] })
            qc.setQueryData<Notification[]>(['tech-notifications'], old =>
                (old || []).map(n => n.id === id ? { ...n, read_at: new Date().toISOString() } : n)
            )
        },
        onSettled: invalidateAll,
    })

    const markAllRead = useMutation({
        mutationFn: () => api.put('/notifications/read-all'),
        onMutate: async () => {
            await qc.cancelQueries({ queryKey: ['tech-notifications'] })
            qc.setQueryData<Notification[]>(['tech-notifications'], old =>
                (old || []).map(n => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
            )
        },
        onSuccess: () => toast.success('Todas marcadas como lidas'),
        onError: () => toast.error('Erro ao marcar notificações'),
        onSettled: invalidateAll,
    })

    const deleteNotification = useMutation({
        mutationFn: (id: string) => api.delete(`/notifications/${id}`),
        onMutate: async (id) => {
            await qc.cancelQueries({ queryKey: ['tech-notifications'] })
            qc.setQueryData<Notification[]>(['tech-notifications'], old =>
                (old || []).filter(n => n.id !== id)
            )
        },
        onSuccess: () => toast.success('Notificação excluída'),
        onError: () => toast.error('Erro ao excluir notificação'),
        onSettled: invalidateAll,
    })

    function handleNotificationClick(notification: Notification) {
        if (!notification.read_at) markRead.mutate(notification.id)

        const data = notification.data as Record<string, unknown> | null
        if (data?.work_order_id) {
            navigate(`/tech/os/${data.work_order_id}`)
        }
    }

    const filtered = filter === 'unread'
        ? notifications.filter(n => !n.read_at)
        : notifications

    const unreadCount = notifications.filter(n => !n.read_at).length

    return (
        <div className="flex flex-col h-full">
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                <button onClick={() => navigate('/tech')} className="flex items-center gap-1 text-sm text-brand-600 mb-2">
                    <ArrowLeft className="w-4 h-4" /> Voltar
                </button>
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-foreground">Notificações</h1>
                    {unreadCount > 0 && (
                        <button
                            onClick={() => markAllRead.mutate()}
                            disabled={markAllRead.isPending}
                            className="flex items-center gap-1 text-xs font-medium text-brand-600 disabled:opacity-50"
                        >
                            <CheckCheck className="w-3.5 h-3.5" /> Ler todas
                        </button>
                    )}
                </div>
            </div>

            <div className="flex-1 overflow-y-auto">
                {/* Filter */}
                <div className="flex gap-2 px-4 pt-4 pb-2">
                    <button
                        onClick={() => setFilter('all')}
                        className={cn(
                            'px-3 py-1.5 rounded-full text-xs font-medium',
                            filter === 'all' ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                        )}
                    >
                        Todas
                    </button>
                    <button
                        onClick={() => setFilter('unread')}
                        className={cn(
                            'px-3 py-1.5 rounded-full text-xs font-medium',
                            filter === 'unread' ? 'bg-brand-600 text-white' : 'bg-surface-100 text-surface-600'
                        )}
                    >
                        Não lidas {unreadCount > 0 && `(${unreadCount})`}
                    </button>
                </div>

                {/* List */}
                <div className="px-4 pb-4 space-y-1">
                    {isLoading ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                            <p className="text-sm text-surface-500">Carregando...</p>
                        </div>
                    ) : filtered.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <BellOff className="w-12 h-12 text-surface-300" />
                            <p className="text-sm text-surface-500">
                                {filter === 'unread' ? 'Nenhuma notificação não lida' : 'Nenhuma notificação'}
                            </p>
                        </div>
                    ) : (
                        filtered.map(n => {
                            const typeConf = TYPE_CONFIG[n.type] || TYPE_CONFIG.info
                            const Icon = typeConf.icon
                            return (
                                <div
                                    key={n.id}
                                    className={cn(
                                        'w-full text-left flex items-start gap-3 p-3 rounded-xl transition-colors',
                                        !n.read_at
                                            ? 'bg-brand-50/50 dark:bg-brand-900/10'
                                            : 'bg-card',
                                    )}
                                >
                                    <button
                                        onClick={() => handleNotificationClick(n)}
                                        className="flex items-start gap-3 flex-1 min-w-0 text-left"
                                    >
                                        <div className={cn('w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5', typeConf.color)}>
                                            <Icon className="w-4 h-4" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-start justify-between gap-2">
                                                <p className={cn('text-sm text-foreground', !n.read_at && 'font-semibold')}>
                                                    {n.title}
                                                </p>
                                                <span className="text-[10px] text-surface-400 whitespace-nowrap flex-shrink-0">
                                                    {formatTimeAgo(n.created_at)}
                                                </span>
                                            </div>
                                            <p className="text-xs text-surface-500 line-clamp-2 mt-0.5">{n.body ?? n.message ?? ''}</p>
                                        </div>
                                        {!n.read_at && (
                                            <span className="w-2 h-2 rounded-full bg-brand-500 flex-shrink-0 mt-2" />
                                        )}
                                    </button>
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation()
                                            deleteNotification.mutate(n.id)
                                        }}
                                        className="p-1.5 rounded-lg text-surface-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors flex-shrink-0 mt-0.5"
                                        aria-label="Excluir notificação"
                                    >
                                        <Trash2 className="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            )
                        })
                    )}
                </div>
            </div>
        </div>
    )
}
