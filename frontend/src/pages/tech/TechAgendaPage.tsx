import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api, { unwrapData } from '@/lib/api'
import {
    Inbox,
    CheckCircle2,
    Clock,
    AlertTriangle,
    Bell,
    BellOff,
    ChevronRight,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth-store'
import { Badge } from '@/components/ui/badge'
import { safeArray } from '@/lib/safe-array'

type Tab = 'pendentes' | 'seguindo' | 'concluidos'
type CentralItem = {
    id: number
    titulo: string
    descricao_curta?: string
    tipo: string
    status: string
    prioridade: string
    due_at?: string
    responsavel?: { id: number; name: string }
    criado_por?: { id: number; name: string }
    watchers?: { user_id: number }[]
    contexto?: Record<string, unknown>
}

const STATUS_CONFIG: Record<string, { icon: typeof Inbox; color: string }> = {
    open: { icon: Inbox, color: 'text-blue-500' },
    in_progress: { icon: Clock, color: 'text-amber-500' },
    completed: { icon: CheckCircle2, color: 'text-emerald-500' },
    cancelled: { icon: AlertTriangle, color: 'text-red-400' },
}

const DEFAULT_STATUS_CONFIG = STATUS_CONFIG.open

const PRIO_COLOR: Record<string, string> = {
    urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    low: 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400',
}

export default function TechAgendaPage() {
    const navigate = useNavigate()
    const { user } = useAuthStore()
    const qc = useQueryClient()
    const [tab, setTab] = useState<Tab>('pendentes')

    const { data: summary } = useQuery({
        queryKey: ['central-summary'],
        queryFn: () => api.get('/agenda/summary').then(r => unwrapData(r)),
        staleTime: 30_000,
    })

    const { data: items = [], isLoading } = useQuery({
        queryKey: ['central-items-tech', tab],
        queryFn: () => {
            const params: Record<string, string> = { per_page: '30' }
            if (tab === 'pendentes') {
                params.tab = 'meus'
                params.status = 'open,in_progress'
            } else if (tab === 'seguindo') {
                params.tab = 'seguindo'
            } else {
                params.tab = 'meus'
                params.status = 'completed'
            }
            return api.get('/agenda/items', { params }).then(r => safeArray<CentralItem>(unwrapData(r)))
        },
        staleTime: 15_000,
    })

    const toggleFollow = useMutation({
        mutationFn: (itemId: number) => api.post(`/agenda/items/${itemId}/toggle-follow`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['central-items-tech'] })
            qc.invalidateQueries({ queryKey: ['central-summary'] })
        },
    })

    const isFollowing = (item: CentralItem) =>
        item.watchers?.some(w => w.user_id === user?.id) ?? false

    const tabs: { key: Tab; label: string; count?: number }[] = [
        { key: 'pendentes', label: 'Pendentes', count: (summary?.abertos ?? 0) + (summary?.em_andamento ?? 0) },
        { key: 'seguindo', label: 'Seguindo', count: summary?.seguindo ?? 0 },
        { key: 'concluidos', label: 'Concluídos', count: summary?.concluidos ?? 0 },
    ]

    return (
        <div className="flex flex-col h-full">
            {/* Header */}
            <div className="px-4 pt-4 pb-2">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-surface-900">Central</h1>
                    <button
                        onClick={() => navigate('/agenda')}
                        className="text-xs text-brand-600 font-medium flex items-center gap-1"
                    >
                        Ver completo <ChevronRight className="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            {/* Tabs */}
            <div className="flex gap-1 px-4 pb-3">
                {tabs.map(t => (
                    <button
                        key={t.key}
                        onClick={() => setTab(t.key)}
                        className={cn(
                            'flex-1 py-2 text-xs font-medium rounded-lg transition-colors text-center',
                            tab === t.key
                                ? 'bg-brand-600 text-white'
                                : 'bg-surface-100 dark:bg-surface-800 text-surface-600'
                        )}
                    >
                        {t.label}
                        {t.count != null && t.count > 0 && (
                            <span className={cn(
                                'ml-1.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold',
                                tab === t.key ? 'bg-white/20 text-white' : 'bg-surface-200 dark:bg-surface-700 text-surface-700 dark:text-surface-300'
                            )}>
                                {t.count}
                            </span>
                        )}
                    </button>
                ))}
            </div>

            {/* List */}
            <div className="flex-1 overflow-y-auto px-4 pb-4 space-y-2">
                {isLoading ? (
                    <div className="flex items-center justify-center py-12">
                        <div className="w-6 h-6 border-2 border-brand-600 border-t-transparent rounded-full animate-spin" />
                    </div>
                ) : items.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-surface-400">
                        <Inbox className="w-10 h-10 mb-2" />
                        <span className="text-sm">Nenhum item</span>
                    </div>
                ) : (
                    items.map((item: CentralItem) => {
                        const cfg = STATUS_CONFIG[item.status] ?? DEFAULT_STATUS_CONFIG
                        const Icon = cfg.icon
                        const overdue = item.due_at && new Date(item.due_at) < new Date() && item.status !== 'completed'
                        return (
                            <div
                                key={item.id}
                                className="bg-card rounded-xl border border-border p-3 active:scale-[0.98] transition-transform"
                            >
                                <div className="flex items-start gap-2.5">
                                    <Icon className={cn('w-5 h-5 mt-0.5 shrink-0', cfg.color)} />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-surface-900 truncate">
                                            {item.titulo}
                                        </p>
                                        {item.descricao_curta && (
                                            <p className="text-xs text-surface-500 mt-0.5 line-clamp-2">
                                                {item.descricao_curta}
                                            </p>
                                        )}
                                        <div className="flex items-center gap-2 mt-1.5 flex-wrap">
                                            <Badge variant="outline" className={cn('text-[10px] px-1.5 py-0', PRIO_COLOR[item.prioridade])}>
                                                {item.prioridade}
                                            </Badge>
                                            <span className="text-[10px] text-surface-400">
                                                {item.tipo}
                                            </span>
                                            {overdue && (
                                                <span className="text-[10px] font-medium text-red-500">
                                                    Atrasado
                                                </span>
                                            )}
                                            {item.due_at && !overdue && (
                                                <span className="text-[10px] text-surface-400">
                                                    {new Date(item.due_at).toLocaleDateString('pt-BR')}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation()
                                            toggleFollow.mutate(item.id)
                                        }}
                                        aria-label={isFollowing(item) ? 'Deixar de seguir item' : 'Seguir item'}
                                        className="p-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800"
                                        title={isFollowing(item) ? 'Deixar de seguir' : 'Seguir'}
                                    >
                                        {isFollowing(item) ? (
                                            <Bell className="w-4 h-4 text-brand-500" />
                                        ) : (
                                            <BellOff className="w-4 h-4 text-surface-400" />
                                        )}
                                    </button>
                                </div>
                                {item.contexto && typeof item.contexto === 'object' && 'link' in item.contexto && (
                                    <button
                                        onClick={() => navigate(item.contexto!.link as string)}
                                        className="mt-2 text-xs text-brand-600 font-medium flex items-center gap-1"
                                    >
                                        Abrir origem <ChevronRight className="w-3 h-3" />
                                    </button>
                                )}
                            </div>
                        )
                    })
                )}
            </div>
        </div>
    )
}
