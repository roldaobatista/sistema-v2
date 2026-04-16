import { useState, useEffect, useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Outlet, NavLink, useLocation, useNavigate, Navigate } from 'react-router-dom'
import {
    Receipt,
    User,
    RefreshCw,
    Briefcase,
    ShieldAlert,
    Settings,
    ScanBarcode,
    QrCode,
    Calendar,
    Wallet,
    Bell,
    Clock,
    Plus,
    Gauge,
    Menu,
    X,
    Wrench,
    Car,
    MessageSquare,
    DollarSign,
    BarChart3,
    Target,
    Navigation,
    PhoneCall,
    AlertCircle,
    Package,
    FileText,
    Timer,
    MapPin,
} from 'lucide-react'
import api from '@/lib/api'
import { useSyncStatus } from '@/hooks/useSyncStatus'
import { useAuthStore } from '@/stores/auth-store'
import { ModeSwitcher } from '@/components/pwa/ModeSwitcher'
import { InstallBanner } from '@/components/pwa/InstallBanner'
import { UpdateBanner } from '@/components/pwa/UpdateBanner'
import { NetworkBadge } from '@/components/pwa/NetworkBadge'
import OfflineIndicator from '@/components/pwa/OfflineIndicator'
import { TechAlertBanner } from '@/components/tech/TechAlertBanner'
import { TechErrorBoundary } from '@/components/tech/TechErrorBoundary'
import { FloatingTimer } from '@/components/tech/FloatingTimer'
import { TechSyncLogsDrawer } from '@/components/tech/sync/TechSyncLogsDrawer'
import { SyncStatusPanel } from '@/components/pwa/SyncStatusPanel'
import NotificationPanel from '@/components/notifications/NotificationPanel'
import { useCrossTabSync } from '@/hooks/useCrossTabSync'
import { cn } from '@/lib/utils'

const NAV_ITEMS = [
    { path: '/tech/dashboard', icon: BarChart3, label: 'Painel' },
    { path: '/tech', icon: Briefcase, label: 'OS', end: true },
    { path: '/tech/agenda', icon: Calendar, label: 'Agenda' },
    { path: '/tech/caixa', icon: Wallet, label: 'Caixa' },
]

const MORE_ITEMS = [
    { path: '/tech/central', icon: Bell, label: 'Central' },
    { path: '/tech/nova-os', icon: Plus, label: 'Nova OS' },
    { path: '/tech/chamados', icon: PhoneCall, label: 'Chamados' },
    { path: '/tech/rota', icon: Navigation, label: 'Rota do Dia' },
    { path: '/tech/mapa', icon: MapPin, label: 'Mapa OS' },
    { path: '/tech/orcamento-rapido', icon: FileText, label: 'Orçamento' },
    { path: '/tech/comissoes', icon: DollarSign, label: 'Comissões' },
    { path: '/tech/metas', icon: Target, label: 'Metas' },
    { path: '/tech/resumo-diario', icon: Calendar, label: 'Resumo Dia' },
    { path: '/tech/solicitar-material', icon: Package, label: 'Material' },
    { path: '/tech/despesas', icon: Receipt, label: 'Despesas' },
    { path: '/tech/apontamentos', icon: Clock, label: 'Horas' },
    { path: '/tech/ponto', icon: Timer, label: 'Ponto' },
    { path: '/tech/equipamentos', icon: Gauge, label: 'Equipamentos' },
    { path: '/tech/scan-ativos', icon: QrCode, label: 'Scan Ativos' },
    { path: '/tech/ferramentas', icon: Wrench, label: 'Ferramentas' },
    { path: '/tech/veiculo', icon: Car, label: 'Veículo' },
    { path: '/tech/precos', icon: DollarSign, label: 'Preços' },
    { path: '/tech/feedback', icon: MessageSquare, label: 'Feedback' },
    { path: '/tech/notificacoes', icon: Bell, label: 'Notificações' },
    { path: '/tech/perfil', icon: User, label: 'Perfil' },
    { path: '/tech/barcode', icon: ScanBarcode, label: 'Scanner' },
    { path: '/tech/configuracoes', icon: Settings, label: 'Configurações' },
]

const ALLOWED_TECH_ROLES = ['tecnico', 'tecnico_vendedor', 'motorista', 'super_admin', 'admin', 'gerente']

const TECH_ROUTE_PERMISSIONS: Array<{ match: string; permission: string | null }> = [
    { match: '/tech/nova-os', permission: 'os.work_order.create' },
    { match: '/tech/chamados', permission: 'service_calls.service_call.view' },
    { match: '/tech/apontamentos', permission: 'technicians.time_entry.view' },
    { match: '/tech/caixa', permission: 'technicians.cashbox.view' },
    { match: '/tech/agenda', permission: 'technicians.schedule.view' },
    { match: '/tech/central', permission: 'agenda.item.view' },
    { match: '/tech/rota', permission: 'technicians.schedule.view' },
    { match: '/tech/mapa', permission: 'technicians.schedule.view' },
    { match: '/tech/despesas', permission: 'technicians.cashbox.view' },
    { match: '/tech/ponto', permission: 'hr.clock.view' },
    { match: '/tech/equipamentos', permission: 'equipments.equipment.view' },
    { match: '/tech/equipamento/', permission: 'equipments.equipment.view' },
    { match: '/tech/veiculo', permission: 'fleet.vehicle.view' },
    { match: '/tech/dashboard', permission: 'os.work_order.view' },
    { match: '/tech/comissoes', permission: 'os.work_order.view' },
    { match: '/tech/metas', permission: 'os.work_order.view' },
    { match: '/tech/scan-ativos', permission: 'equipments.equipment.view' },
    { match: '/tech/ferramentas', permission: 'equipments.equipment.view' },
    { match: '/tech/orcamento-rapido', permission: 'quotes.quote.create' },
    { match: '/tech/solicitar-material', permission: 'estoque.view' },
    { match: '/tech/estoque', permission: 'estoque.view' },
    { match: '/tech/inventory-scan', permission: 'estoque.view' },
    { match: '/tech/os/', permission: 'os.work_order.view' },
    { match: '/tech', permission: 'os.work_order.view' },
]

function resolveTechPermission(pathname: string): string | null {
    for (const rule of TECH_ROUTE_PERMISSIONS) {
        if (rule.match === pathname) {
            return rule.permission
        }

        if (rule.match !== '/tech' && rule.match.endsWith('/') && pathname.startsWith(rule.match)) {
            return rule.permission
        }

        if (rule.match !== '/tech' && !rule.match.endsWith('/') && pathname.startsWith(`${rule.match}/`)) {
            return rule.permission
        }
    }

    return null
}

function hasPermissionExpression(
    expression: string,
    hasPermission: (permission: string) => boolean
): boolean {
    return expression
        .split('|')
        .map(item => item.trim())
        .filter(Boolean)
        .some(permission => hasPermission(permission))
}

export default function TechShell() {
    const { isAuthenticated, user, hasRole, hasPermission, fetchMe, logout } = useAuthStore()
    const { isOnline, pendingCount, syncErrorCount, isSyncing, syncNow, lastSyncAt } = useSyncStatus()
    const [showSyncLogs, setShowSyncLogs] = useState(false)
    const location = useLocation()
    const navigate = useNavigate()
    const fetchedRef = useRef(false)
    const [showSyncBar, setShowSyncBar] = useState(false)
    const [showMoreMenu, setShowMoreMenu] = useState(false)
    const [timedOut, setTimedOut] = useState(false)
    const requiredPermission = resolveTechPermission(location.pathname)
    const isSuperAdmin = hasRole('super_admin')
    const canSeeSummary = isSuperAdmin || hasPermissionExpression('os.work_order.view|technicians.schedule.view', hasPermission)
    const canAccessTechPath = (path: string) => {
        const permission = resolveTechPermission(path)
        return !permission || isSuperAdmin || hasPermissionExpression(permission, hasPermission)
    }
    const visibleNavItems = NAV_ITEMS.filter(item => canAccessTechPath(item.path))
    const visibleMoreItems = MORE_ITEMS.filter(item => canAccessTechPath(item.path))

    useCrossTabSync()

    const { data: centralSummary } = useQuery({
        queryKey: ['central-summary'],
        queryFn: () => api.get('/agenda/summary').then(r => r.data),
        staleTime: 60_000,
        refetchInterval: 60_000,
        enabled: isAuthenticated && !!user && canSeeSummary,
    })
    const centralPending = (centralSummary?.abertos ?? 0) + (centralSummary?.em_andamento ?? 0)

    useEffect(() => {
        if (isAuthenticated && !user && !fetchedRef.current) {
            fetchedRef.current = true
            fetchMe().catch(() => {
                logout().catch(() => undefined)
            })
        }
    }, [isAuthenticated, user, fetchMe, logout])

    useEffect(() => {
        if (!isAuthenticated || user) {
            setTimedOut(false)
            return
        }

        const timer = setTimeout(() => {
            setTimedOut(true)
            logout().catch(() => undefined)
        }, 20_000)

        return () => clearTimeout(timer)
    }, [isAuthenticated, user, logout])

    // Show sync bar briefly when sync completes
    useEffect(() => {
        if (lastSyncAt) {
            setShowSyncBar(true)
            const timer = setTimeout(() => setShowSyncBar(false), 3000)
            return () => clearTimeout(timer)
        }
    }, [lastSyncAt])

    // GAP-18: Gate de autenticação
    if (!isAuthenticated) {
        return <Navigate to="/login" replace />
    }

    if (!user) {
        return (
            <div className="flex flex-col items-center justify-center h-dvh bg-surface-50 px-6 gap-4">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent" />
                <p className="text-sm text-surface-500">
                    {timedOut ? 'Sessao expirada. Redirecionando...' : 'Carregando painel tecnico...'}
                </p>
            </div>
        )
    }

    // GAP-18: Gate de role — apenas roles de campo e administrativas
    const hasAllowedRole = ALLOWED_TECH_ROLES.some(role => hasRole(role))
    if (!hasAllowedRole) {
        return (
            <div className="flex flex-col items-center justify-center h-dvh bg-surface-50 px-6">
                <div className="rounded-2xl border border-red-200 bg-red-50/70 dark:bg-red-950/30 p-8 text-center shadow-sm max-w-md">
                    <ShieldAlert className="mx-auto h-12 w-12 text-red-400 mb-4" />
                    <h2 className="text-lg font-semibold text-red-800 dark:text-red-300">Acesso negado</h2>
                    <p className="mt-2 text-sm text-red-700">
                        Você não tem permissão para acessar o painel técnico.
                    </p>
                    <button
                        onClick={() => navigate('/')}
                        className="mt-4 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors"
                    >
                        Ir para o Dashboard
                    </button>
                </div>
            </div>
        )
    }

    if (requiredPermission && !isSuperAdmin && !hasPermissionExpression(requiredPermission, hasPermission)) {
        return (
            <div className="flex flex-col items-center justify-center h-dvh bg-surface-50 px-6">
                <div className="rounded-2xl border border-red-200 bg-red-50/70 dark:bg-red-950/30 p-8 text-center shadow-sm max-w-md">
                    <ShieldAlert className="mx-auto h-12 w-12 text-red-400 mb-4" />
                    <h2 className="text-lg font-semibold text-red-800 dark:text-red-300">Acesso negado</h2>
                    <p className="mt-2 text-sm text-red-700">
                        Voce nao tem permissao para acessar esta rota tecnica.
                    </p>
                    <button
                        onClick={() => navigate('/tech')}
                        className="mt-4 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors"
                    >
                        Voltar para a agenda tecnica
                    </button>
                </div>
            </div>
        )
    }

    return (
        <div className="flex flex-col h-dvh bg-surface-50">
            <UpdateBanner />
            {/* ─── Top Bar ────────────────────────────────── */}
            <header className="flex items-center justify-between px-4 py-3 bg-card border-b border-border safe-area-top">
                <div className="flex items-center gap-2">
                    <span className="text-lg font-bold text-brand-600">
                        Kalibrium
                    </span>
                </div>

                <div className="flex items-center gap-3">
                    <ModeSwitcher />

                    {centralPending > 0 && (
                        <button
                            onClick={() => navigate('/tech/agenda')}
                            className="relative p-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800"
                            aria-label="Agenda - itens pendentes"
                        >
                            <Bell className="w-5 h-5 text-surface-600" />
                            <span className="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1">
                                {centralPending > 99 ? '99+' : centralPending}
                            </span>
                        </button>
                    )}

                    {/* Sync Errors badge */}
                    {syncErrorCount > 0 && (
                        <button
                            onClick={() => setShowSyncLogs(true)}
                            className="relative p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/30"
                            aria-label="Erros de sincronização"
                        >
                            <AlertCircle className="w-5 h-5 text-red-500" />
                            <span className="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1">
                                {syncErrorCount > 99 ? '99+' : syncErrorCount}
                            </span>
                        </button>
                    )}

                    {/* Pending sync badge */}
                    {pendingCount > 0 && (
                        <button
                            onClick={() => syncNow()}
                            disabled={isSyncing || !isOnline}
                            className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 text-xs font-medium"
                        >
                            <RefreshCw className={cn('w-3.5 h-3.5', isSyncing && 'animate-spin')} />
                            {pendingCount}
                        </button>
                    )}

                    <SyncStatusPanel />
                    <NotificationPanel />

                    {/* Network quality indicator */}
                    <NetworkBadge />
                </div>
            </header>

            {/* ─── Sync notification bar ─────────────────── */}
            {showSyncBar && (
                <div className="px-4 py-1.5 bg-emerald-500 text-white text-xs text-center font-medium animate-in slide-in-from-top-2">
                    ✓ Sincronizado com sucesso
                </div>
            )}

            <TechAlertBanner />

            {/* ─── Content Area ──────────────────────────── */}
            <main className="flex-1 overflow-y-auto overscroll-contain">
                <TechErrorBoundary>
                    <Outlet />
                </TechErrorBoundary>
            </main>

            {/* ─── More menu overlay ─────────────────────── */}
            {showMoreMenu && (
                <div className="absolute inset-0 z-50 flex flex-col">
                    <button
                        onClick={() => setShowMoreMenu(false)}
                        className="flex-1 bg-black/40 backdrop-blur-sm"
                        aria-label="Fechar menu"
                    />
                    <div className="bg-card border-t border-border rounded-t-2xl p-4 pb-6 safe-area-bottom animate-in slide-in-from-bottom-4">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-semibold text-surface-900">Mais opções</h3>
                            <button onClick={() => setShowMoreMenu(false)} className="p-1 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800" aria-label="Fechar">
                                <X className="w-5 h-5 text-surface-500" />
                            </button>
                        </div>
                        <div className="grid grid-cols-4 gap-3">
                            {(visibleMoreItems || []).map((item) => {
                                const isActive = location.pathname === item.path
                                return (
                                    <button
                                        key={item.path}
                                        onClick={() => { navigate(item.path); setShowMoreMenu(false) }}
                                        className="flex flex-col items-center gap-1.5 py-3 rounded-xl hover:bg-surface-50 dark:hover:bg-surface-800 active:scale-95 transition-all"
                                    >
                                        <div className={cn(
                                            'w-10 h-10 rounded-xl flex items-center justify-center',
                                            isActive ? 'bg-brand-100' : 'bg-surface-100'
                                        )}>
                                            <item.icon className={cn('w-5 h-5', isActive ? 'text-brand-600' : 'text-surface-600')} />
                                        </div>
                                        <span className={cn('text-[10px] font-medium', isActive ? 'text-brand-600' : 'text-surface-600')}>
                                            {item.label}
                                        </span>
                                    </button>
                                )
                            })}
                        </div>
                    </div>
                </div>
            )}

            {/* ─── Bottom Navigation ─────────────────────── */}
            <nav className="flex items-center justify-around bg-card border-t border-border safe-area-bottom">
                {(visibleNavItems || []).map((item) => (
                    <NavLink
                        key={item.path}
                        to={item.path}
                        end={item.end}
                        className={({ isActive }) => cn(
                            'flex flex-col items-center gap-0.5 py-2 px-3 text-xs font-medium transition-colors min-w-[60px]',
                            isActive
                                ? 'text-brand-600'
                                : 'text-surface-500'
                        )}
                    >
                        <item.icon className="w-5 h-5" />
                        <span>{item.label}</span>
                    </NavLink>
                ))}
                <button
                    onClick={() => setShowMoreMenu(true)}
                    className={cn(
                        'flex flex-col items-center gap-0.5 py-2 px-3 text-xs font-medium transition-colors min-w-[60px]',
                        showMoreMenu ? 'text-brand-600' : 'text-surface-500'
                    )}
                >
                    <Menu className="w-5 h-5" />
                    <span>Mais</span>
                </button>
            </nav>

            <FloatingTimer />
            <InstallBanner />
            <OfflineIndicator withBottomNavigation />
            <TechSyncLogsDrawer open={showSyncLogs} onOpenChange={setShowSyncLogs} />
        </div>
    )
}
