import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Truck, Fuel, AlertCircle, TrendingUp, DollarSign } from 'lucide-react'
import api, { unwrapData } from '@/lib/api'
import { cn } from '@/lib/utils'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth-store'

type FleetDashboard = {
    avg_cost_per_km?: number
    avg_consumption_diesel?: number
    availability_rate?: number
    upcoming_maintenances?: number
    alerts?: Array<{ severity: 'critical' | 'warning'; title: string; description: string; days_left: number }>
    active_count?: number
    maintenance_count?: number
    pool_waiting_count?: number
    accident_count?: number
    total_vehicles?: number
}

export function FleetDashboardTab() {
    const _handleAction = () => {}
    const [SearchTerm, _setSearchTerm] = useState('')
    const { hasPermission } = useAuthStore()

    const { data: dashboard, isLoading, isError, refetch } = useQuery({
        queryKey: ['fleet-dashboard-advanced'],
        queryFn: () => api.get('/fleet/dashboard').then(response => unwrapData<FleetDashboard>(response)),
    })

    if (isError) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
                    <AlertCircle className="h-10 w-10 text-red-500" />
                    <p className="text-sm text-surface-600">Falha ao carregar indicadores da frota.</p>
                    <Button variant="outline" size="sm" onClick={() => refetch()}>
                        Tentar novamente
                    </Button>
                </CardContent>
            </Card>
        )
    }

    if (isLoading || !dashboard) {
        return (
            <div className="animate-pulse space-y-4">
                <div className="h-32 bg-surface-100 rounded-xl" />
                <div className="grid grid-cols-3 gap-4">
                    <div className="h-40 bg-surface-100 rounded-xl" />
                    <div className="h-40 bg-surface-100 rounded-xl" />
                    <div className="h-40 bg-surface-100 rounded-xl" />
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    title="Custo Medio por KM"
                    value={`R$ ${Number(dashboard.avg_cost_per_km || 0).toFixed(2)}`}
                    icon={<DollarSign className="text-brand-600" />}
                    trend="+2.5% vs mes anterior"
                />
                <StatsCard
                    title="Consumo Medio (Diesel)"
                    value={`${Number(dashboard.avg_consumption_diesel || 0).toFixed(1)} km/L`}
                    icon={<Fuel className="text-brand-600" />}
                />
                <StatsCard
                    title="Disponibilidade"
                    value={`${dashboard.availability_rate || 0}%`}
                    icon={<Truck className="text-brand-600" />}
                />
                <StatsCard
                    title="Manutencoes Proximas"
                    value={dashboard.upcoming_maintenances || 0}
                    icon={<AlertCircle className="text-amber-600" />}
                    variant="warning"
                />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">Alertas de Documentacao</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {(dashboard.alerts || []).map((alert, i) => (
                            <div key={i} className="flex items-center justify-between p-3 rounded-lg bg-surface-50 border border-default">
                                <div className="flex items-center gap-3">
                                    <div className={cn('p-2 rounded-full', alert.severity === 'critical' ? 'bg-red-100' : 'bg-amber-100')}>
                                        <AlertCircle size={16} className={alert.severity === 'critical' ? 'text-red-600' : 'text-amber-600'} />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-surface-900">{alert.title}</p>
                                        <p className="text-xs text-surface-500">{alert.description}</p>
                                    </div>
                                </div>
                                <Badge variant={alert.severity === 'critical' ? 'danger' : 'warning'}>{alert.days_left} dias</Badge>
                            </div>
                        ))}
                        {(!dashboard.alerts || dashboard.alerts.length === 0) && (
                            <p className="text-center text-sm text-surface-500 py-4">Nenhum alerta pendente</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">Situacao dos Veiculos</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <StatusProgress label="Ativos" count={dashboard.active_count ?? 0} total={dashboard.total_vehicles ?? 0} color="bg-emerald-500" />
                        <StatusProgress label="Em Manutencao" count={dashboard.maintenance_count ?? 0} total={dashboard.total_vehicles ?? 0} color="bg-amber-500" />
                        <StatusProgress label="Aguardando Pool" count={dashboard.pool_waiting_count ?? 0} total={dashboard.total_vehicles ?? 0} color="bg-brand-500" />
                        <StatusProgress label="Em Sinistro" count={dashboard.accident_count ?? 0} total={dashboard.total_vehicles ?? 0} color="bg-red-500" />
                    </CardContent>
                </Card>
            </div>
        </div>
    )
}

function StatsCard({ title, value, icon, trend, variant = 'default' }: { title: string; value: string | number; icon: React.ReactNode; trend?: string; variant?: 'default' | 'warning' }) {
    return (
        <Card className={cn(variant === 'warning' && 'border-amber-200 bg-amber-50/50')}>
            <CardContent className="pt-6">
                <div className="flex items-center justify-between">
                    <div className="p-2 bg-surface-0 rounded-lg border border-default shadow-card">{icon}</div>
                    {trend && <span className="text-xs font-medium text-surface-500 flex items-center gap-0.5"><TrendingUp size={10} /> {trend}</span>}
                </div>
                <div className="mt-4">
                    <p className="text-2xl font-bold text-surface-900">{value}</p>
                    <p className="text-xs text-surface-500 mt-1">{title}</p>
                </div>
            </CardContent>
        </Card>
    )
}

function StatusProgress({ label, count, total, color }: { label: string; count: number; total: number; color: string }) {
    const percent = total > 0 ? (count / total) * 100 : 0
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between text-xs">
                <span className="font-medium text-surface-700">{label}</span>
                <span className="text-surface-500">{count} / {total}</span>
            </div>
            <div className="h-1.5 w-full bg-surface-100 rounded-full overflow-hidden">
                <div className={cn('h-full transition-all duration-500', color)} style={{ width: `${percent}%` }} />
            </div>
        </div>
    )
}
