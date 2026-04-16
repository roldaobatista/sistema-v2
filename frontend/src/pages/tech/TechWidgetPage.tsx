import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Clipboard, ScanBarcode, Camera, DollarSign, Pen,
    ClipboardList, RefreshCw, ChevronRight, Loader2,
} from 'lucide-react'
import api from '@/lib/api'
import { useSyncStatus } from '@/hooks/useSyncStatus'
import { useAuthStore } from '@/stores/auth-store'
import { cn, getApiErrorMessage } from '@/lib/utils'

interface WidgetOS {
    id: number
    number?: string
    os_number?: string
    business_number?: string
    customer_name?: string
    status: string
    scheduled_start?: string
}

const statusLabels: Record<string, { label: string; color: string }> = {
    open: { label: 'Aberta', color: 'bg-amber-500' },
    awaiting_dispatch: { label: 'Aguard. Despacho', color: 'bg-amber-500' },
    in_displacement: { label: 'Em Deslocamento', color: 'bg-blue-500' },
    displacement_paused: { label: 'Desloc. Pausado', color: 'bg-amber-500' },
    at_client: { label: 'No Cliente', color: 'bg-emerald-500' },
    in_service: { label: 'Em Servico', color: 'bg-blue-500' },
    service_paused: { label: 'Servico Pausado', color: 'bg-amber-500' },
    awaiting_return: { label: 'Aguard. Retorno', color: 'bg-teal-500' },
    in_return: { label: 'Em Retorno', color: 'bg-blue-500' },
    return_paused: { label: 'Retorno Pausado', color: 'bg-amber-500' },
    completed: { label: 'Concluida', color: 'bg-emerald-500' },
}

function normalizeStatus(status: string): string {
    if (status === 'pending') return 'open'
    if (status === 'in_progress') return 'in_service'
    return status
}

export default function TechWidgetPage() {
    const queryClient = useQueryClient()
    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/tech-widget/${id}`),
        onSuccess: () => {
            toast.success('Removido com sucesso')
            queryClient.invalidateQueries({ queryKey: ['tech-widget'] })
        },
        onError: (err: unknown) => { toast.error(getApiErrorMessage(err, 'Erro ao remover')) },
    })
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null)
    const _handleDelete = (id: number) => { setConfirmDeleteId(id) }
    const _confirmDelete = () => {
        if (confirmDeleteId !== null) {
            deleteMutation.mutate(confirmDeleteId)
            setConfirmDeleteId(null)
        }
    }

    const [_searchTerm, _setSearchTerm] = useState('')
    const { hasPermission } = useAuthStore()
    void hasPermission

    const navigate = useNavigate()
    const { user } = useAuthStore()
    const sync = useSyncStatus()

    const { data, isLoading } = useQuery({
        queryKey: ['tech-widget-os'],
        queryFn: () => api.get('/technician/work-orders', {
            params: {
                per_page: 3,
                status: 'open,awaiting_dispatch,in_displacement,displacement_paused,at_client,in_service,service_paused,awaiting_return,in_return,return_paused',
            },
        }),
        staleTime: 60_000,
    })

    const workOrders: WidgetOS[] = (data?.data?.data ?? []).slice(0, 3)

    const shortcuts = [
        { label: 'Escanear', icon: ScanBarcode, path: '/tech/barcode', color: 'text-cyan-500 bg-cyan-100 dark:bg-cyan-900/30' },
        { label: 'Despesas', icon: DollarSign, path: '/tech/despesas', color: 'text-emerald-500 bg-emerald-100 dark:bg-emerald-900/30' },
        { label: 'Assinatura', icon: Pen, path: '/tech/os/0/signature', color: 'text-blue-500 bg-blue-100 dark:bg-blue-900/30' },
        { label: 'Checklist', icon: ClipboardList, path: '/tech/os/0/checklist', color: 'text-orange-500 bg-orange-100 dark:bg-orange-900/30' },
        { label: 'Camera', icon: Camera, path: '/tech/thermal-camera', color: 'text-red-500 bg-red-100 dark:bg-red-900/30' },
    ]

    const osIdentifier = (wo: WidgetOS) => wo.business_number ?? wo.os_number ?? wo.number ?? `#${wo.id}`

    return (
        <div className="flex flex-col h-full overflow-y-auto bg-surface-50">
            <div className="bg-gradient-to-r from-brand-600 to-brand-700 px-4 py-4">
                <p className="text-xs text-brand-200">Kalibrium · Acesso Rapido</p>
                <h1 className="text-lg font-bold text-white mt-0.5">
                    {user?.name?.split(' ')[0] ?? 'Tecnico'}
                </h1>

                <div className="mt-2 flex items-center gap-2">
                    {sync.pendingCount > 0 ? (
                        <div className="flex items-center gap-1.5 text-xs text-amber-200">
                            <RefreshCw className={cn('w-3.5 h-3.5', sync.isSyncing && 'animate-spin')} />
                            {sync.pendingCount} pendente{sync.pendingCount > 1 && 's'}
                        </div>
                    ) : (
                        <div className="flex items-center gap-1.5 text-xs text-emerald-200">
                            <div className="w-2 h-2 rounded-full bg-emerald-400" />
                            Tudo sincronizado
                        </div>
                    )}
                </div>
            </div>

            <div className="flex-1 px-4 py-4 space-y-4">
                <section>
                    <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide mb-2">
                        Proximas OS
                    </h3>

                    {isLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="w-5 h-5 animate-spin text-brand-500" />
                        </div>
                    ) : workOrders.length === 0 ? (
                        <div className="bg-card rounded-xl p-5 text-center">
                            <Clipboard className="w-8 h-8 text-surface-300 mx-auto mb-2" />
                            <p className="text-sm text-surface-500">Nenhuma OS ativa</p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {workOrders.map((wo) => {
                                const normalizedStatus = normalizeStatus(wo.status)

                                return (
                                    <button
                                        key={wo.id}
                                        onClick={() => navigate(`/tech/os/${wo.id}`)}
                                        className="w-full bg-card rounded-xl p-3.5 flex items-center gap-3 active:bg-surface-50 dark:active:bg-surface-700 transition-colors"
                                    >
                                        <div
                                            className={cn(
                                                'w-2 h-8 rounded-full shrink-0',
                                                statusLabels[normalizedStatus]?.color ?? 'bg-surface-300',
                                            )}
                                        />
                                        <div className="flex-1 text-left min-w-0">
                                            <p className="text-sm font-semibold text-foreground truncate">
                                                {osIdentifier(wo)}
                                            </p>
                                            <p className="text-xs text-surface-500 truncate">
                                                {wo.customer_name ?? 'Sem cliente'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            <span className="text-[10px] font-medium text-surface-400 uppercase">
                                                {statusLabels[normalizedStatus]?.label ?? normalizedStatus}
                                            </span>
                                            <ChevronRight className="w-4 h-4 text-surface-300" />
                                        </div>
                                    </button>
                                )
                            })}
                        </div>
                    )}

                    <button
                        onClick={() => navigate('/tech')}
                        className="w-full mt-2 py-2 text-xs text-brand-600 font-medium text-center"
                    >
                        Ver todas as OS {'>'}
                    </button>
                </section>

                <section>
                    <h3 className="text-xs font-semibold text-surface-400 uppercase tracking-wide mb-2">
                        Atalhos
                    </h3>
                    <div className="grid grid-cols-5 gap-2">
                        {shortcuts.map((shortcut) => (
                            <button
                                key={shortcut.path}
                                onClick={() => navigate(shortcut.path)}
                                className="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-card active:scale-95 transition-transform"
                            >
                                <div className={cn('w-10 h-10 rounded-lg flex items-center justify-center', shortcut.color)}>
                                    <shortcut.icon className="w-5 h-5" />
                                </div>
                                <span className="text-[10px] font-medium text-surface-600">
                                    {shortcut.label}
                                </span>
                            </button>
                        ))}
                    </div>
                </section>
            </div>
        </div>
    )
}
