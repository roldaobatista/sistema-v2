import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Users, MapPin, Briefcase, Clock, ChevronUp, ChevronDown, X } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth-store'
import { cn } from '@/lib/utils'
import api, { unwrapData } from '@/lib/api'

const WIDGET_KEY = 'kalibrium-team-widget'
const MANAGER_ROLES = ['super_admin', 'admin', 'gerente', 'coordenador']

interface TeamStatus {
    total_technicians: number
    online: number
    in_transit: number
    working: number
    idle: number
    offline: number
    active_work_orders: number
    pending_work_orders: number
}

export function TeamStatusWidget() {
    const { hasRole } = useAuthStore()
    const navigate = useNavigate()
    const [expanded, setExpanded] = useState(false)
    const [dismissed, setDismissed] = useState(() => {
        try { return sessionStorage.getItem(WIDGET_KEY) === 'dismissed' } catch { return false }
    })

    const isManager = MANAGER_ROLES.some(r => hasRole(r))

    const { data, isLoading } = useQuery<TeamStatus>({
        queryKey: ['team-status-widget'],
        queryFn: async () => {
            const response = await api.get('/dashboard/team-status')
            return unwrapData<TeamStatus>(response)
        },
        refetchInterval: 60_000,
        staleTime: 30_000,
        retry: 1,
        enabled: isManager && !dismissed,
    })

    if (!isManager || dismissed) return null
    if (isLoading || !data) return null

    const handleDismiss = () => {
        setDismissed(true)
        try { sessionStorage.setItem(WIDGET_KEY, 'dismissed') } catch { /* ignore */ }
    }

    return (
        <div className="fixed bottom-20 right-6 z-30 animate-in slide-in-from-bottom-4 duration-300 pointer-events-auto">
            <div className={cn(
                'bg-card border border-border rounded-2xl shadow-2xl transition-all duration-300 overflow-hidden',
                expanded ? 'w-72' : 'w-auto'
            )}>
                {expanded ? (
                    <div className="p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h4 className="text-sm font-semibold text-foreground flex items-center gap-2">
                                <Users className="w-4 h-4 text-brand-600" />
                                Equipe em Campo
                            </h4>
                            <div className="flex items-center gap-1">
                                <button
                                    onClick={() => setExpanded(false)}
                                    className="p-1 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                                    aria-label="Minimizar"
                                >
                                    <ChevronDown className="w-4 h-4 text-surface-400" />
                                </button>
                                <button
                                    onClick={handleDismiss}
                                    className="p-1 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                                    aria-label="Fechar"
                                >
                                    <X className="w-4 h-4 text-surface-400" />
                                </button>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <StatusItem
                                icon={<div className="w-2 h-2 rounded-full bg-emerald-500" />}
                                label="Online"
                                value={data.online}
                            />
                            <StatusItem
                                icon={<MapPin className="w-3.5 h-3.5 text-blue-500" />}
                                label="Em trânsito"
                                value={data.in_transit}
                            />
                            <StatusItem
                                icon={<Briefcase className="w-3.5 h-3.5 text-amber-500" />}
                                label="Trabalhando"
                                value={data.working}
                            />
                            <StatusItem
                                icon={<Clock className="w-3.5 h-3.5 text-surface-400" />}
                                label="Ociosos"
                                value={data.idle}
                            />
                        </div>

                        <div className="mt-3 pt-3 border-t border-border flex items-center justify-between text-xs">
                            <span className="text-muted-foreground">
                                {data.active_work_orders} OS ativas · {data.pending_work_orders} pendentes
                            </span>
                            <button
                                onClick={() => navigate('/tv/dashboard')}
                                className="text-brand-600 font-medium hover:underline"
                            >
                                Ver mapa
                            </button>
                        </div>
                    </div>
                ) : (
                    <button
                        onClick={() => setExpanded(true)}
                        className="flex items-center gap-3 px-4 py-3 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                    >
                        <div className="relative">
                            <Users className="w-5 h-5 text-brand-600" />
                            <div className="absolute -top-1 -right-1 w-3 h-3 bg-emerald-500 rounded-full border-2 border-card" />
                        </div>
                        <div className="text-left">
                            <p className="text-xs font-semibold text-foreground">
                                {data.online + data.in_transit + data.working}/{data.total_technicians} técnicos
                            </p>
                            <p className="text-[10px] text-muted-foreground">
                                {data.active_work_orders} OS ativas
                            </p>
                        </div>
                        <ChevronUp className="w-4 h-4 text-surface-400 ml-1" />
                    </button>
                )}
            </div>
        </div>
    )
}

function StatusItem({ icon, label, value }: { icon: React.ReactNode; label: string; value: number }) {
    return (
        <div className="flex items-center gap-2 rounded-lg bg-surface-50 dark:bg-surface-800/50 px-3 py-2">
            {icon}
            <div>
                <p className="text-xs font-semibold text-foreground tabular-nums">{value}</p>
                <p className="text-[10px] text-muted-foreground">{label}</p>
            </div>
        </div>
    )
}
