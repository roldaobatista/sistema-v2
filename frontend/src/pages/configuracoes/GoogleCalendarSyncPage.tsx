import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
    CalendarDays, Link2, Unlink, CheckCircle, AlertCircle,
    RefreshCw, Loader2,
} from 'lucide-react'
import api from '@/lib/api'
import { cn } from '@/lib/utils'

interface GoogleCalendarStatus {
    connected: boolean
    email?: string
    last_sync?: string
    events_synced?: number
}

export function GoogleCalendarSyncPage() {
    const queryClient = useQueryClient()

    const { data: status, isLoading } = useQuery<GoogleCalendarStatus>({
        queryKey: ['google-calendar-status'],
        queryFn: () => api.get('/integrations/google-calendar/status').then(res => res.data?.data ?? res.data),
    })

    const connectMutation = useMutation({
        mutationFn: () => api.get('/integrations/google-calendar/auth-url').then(res => res.data?.data ?? res.data),
        onSuccess: (data: { url: string }) => {
            window.location.href = data.url
        },
        onError: () => toast.error('Erro ao iniciar conexão com Google Calendar'),
    })

    const disconnectMutation = useMutation({
        mutationFn: () => api.post('/integrations/google-calendar/disconnect'),
        onSuccess: () => {
            toast.success('Google Calendar desconectado')
            queryClient.invalidateQueries({ queryKey: ['google-calendar-status'] })
        },
        onError: () => toast.error('Erro ao desconectar'),
    })

    const syncMutation = useMutation({
        mutationFn: () => api.post('/integrations/google-calendar/sync'),
        onSuccess: () => {
            toast.success('Sincronização iniciada')
            queryClient.invalidateQueries({ queryKey: ['google-calendar-status'] })
        },
        onError: () => toast.error('Erro ao sincronizar'),
    })

    const isConnected = status?.connected ?? false

    return (
        <div className="space-y-5 max-w-2xl">
            <div>
                <h1 className="text-lg font-semibold text-surface-900 tracking-tight">
                    Google Calendar
                </h1>
                <p className="mt-0.5 text-sm text-surface-500">
                    Sincronize sua agenda com o Google Calendar para visualizar compromissos em ambas as plataformas.
                </p>
            </div>

            {isLoading ? (
                <div className="rounded-xl border border-default bg-surface-0 p-8 shadow-card text-center text-sm text-surface-500">
                    Verificando conexão...
                </div>
            ) : (
                <>
                    {/* Connection Status */}
                    <div className={cn(
                        'rounded-xl border p-5 shadow-card',
                        isConnected
                            ? 'border-emerald-200 bg-emerald-50/50'
                            : 'border-default bg-surface-0'
                    )}>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className={cn(
                                    'rounded-lg p-2.5',
                                    isConnected ? 'bg-emerald-100 text-emerald-600' : 'bg-surface-100 text-surface-400'
                                )}>
                                    {isConnected ? <CheckCircle className="h-5 w-5" /> : <AlertCircle className="h-5 w-5" />}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-surface-900">
                                        {isConnected ? 'Conectado' : 'Não conectado'}
                                    </p>
                                    {status?.email && (
                                        <p className="text-xs text-surface-500 mt-0.5">{status.email}</p>
                                    )}
                                </div>
                            </div>
                            {isConnected ? (
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={() => syncMutation.mutate()}
                                        disabled={syncMutation.isPending}
                                        className="flex items-center gap-2 rounded-lg border border-default bg-surface-0 px-3 py-2 text-xs font-medium text-surface-700 hover:bg-surface-50 transition-colors disabled:opacity-50"
                                    >
                                        {syncMutation.isPending ? (
                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                        ) : (
                                            <RefreshCw className="h-3.5 w-3.5" />
                                        )}
                                        Sincronizar
                                    </button>
                                    <button
                                        onClick={() => disconnectMutation.mutate()}
                                        disabled={disconnectMutation.isPending}
                                        className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-100 transition-colors disabled:opacity-50"
                                    >
                                        <Unlink className="h-3.5 w-3.5" />
                                        Desconectar
                                    </button>
                                </div>
                            ) : (
                                <button
                                    onClick={() => connectMutation.mutate()}
                                    disabled={connectMutation.isPending}
                                    className="flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors disabled:opacity-50"
                                >
                                    {connectMutation.isPending ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Link2 className="h-4 w-4" />
                                    )}
                                    Conectar Google Calendar
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Sync Info */}
                    {isConnected && (
                        <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <h2 className="text-sm font-semibold text-surface-900 mb-4">Informações de Sincronização</h2>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-surface-500 uppercase tracking-wider">Última Sync</p>
                                    <p className="text-sm font-medium text-surface-900">
                                        {status?.last_sync ?? 'Nunca'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-surface-500 uppercase tracking-wider">Eventos Sincronizados</p>
                                    <p className="text-sm font-medium text-surface-900">
                                        {status?.events_synced ?? 0}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-surface-500 uppercase tracking-wider">Direção</p>
                                    <p className="text-sm font-medium text-surface-900">Bidirecional</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* What syncs */}
                    <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <h2 className="text-sm font-semibold text-surface-900 mb-3">O que é sincronizado</h2>
                        <div className="space-y-2">
                            {[
                                { icon: CalendarDays, text: 'Compromissos da agenda' },
                                { icon: CalendarDays, text: 'Chamados técnicos agendados' },
                                { icon: CalendarDays, text: 'Ordens de serviço com data agendada' },
                                { icon: CalendarDays, text: 'Atividades CRM (visitas, reuniões)' },
                            ].map((item, i) => (
                                <div key={i} className="flex items-center gap-3 py-1.5">
                                    <item.icon className="h-4 w-4 text-brand-500 flex-shrink-0" />
                                    <span className="text-sm text-surface-700">{item.text}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Help */}
                    <div className="rounded-xl border border-surface-200 bg-surface-50 p-4">
                        <p className="text-xs text-surface-500">
                            A sincronização é automática a cada alteração. Para forçar uma atualização manual, use o botão "Sincronizar".
                            Em caso de problemas, desconecte e reconecte sua conta.
                        </p>
                    </div>
                </>
            )}
        </div>
    )
}
