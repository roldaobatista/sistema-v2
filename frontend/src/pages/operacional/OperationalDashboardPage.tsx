import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import {
  Wrench, AlertTriangle, Truck,
  ClipboardCheck, Calendar, Activity, Loader2
} from 'lucide-react'
import api, { unwrapData } from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { safeArray } from '@/lib/safe-array'
import { cn } from '@/lib/utils'

interface WorkOrderSummary {
  id: number
  number: string
  customer?: { name: string }
  technician?: { name: string }
  status: string
  priority?: string
  scheduled_date?: string
  title?: string
  type?: string
}

interface DashboardStats {
  work_orders: {
    open: number
    in_progress: number
    in_displacement: number
  }
  equipment: {
    overdue: number
    due_soon: number
  }
  financial: {
    due_today_count: number
    due_today_amount: number
  }
  last_updated: string
}

interface ActiveDisplacement {
  work_order_id: number
  work_order_number: string
  technician: string
  customer: string
  started_at: string
  destination: {
    lat: number | null
    lng: number | null
  }
}

// ... rest of constants ...

export default function OperationalDashboardPage() {
  const navigate = useNavigate()
  const today = new Date().toISOString().slice(0, 10)

  // Fetch unified operational dashboard stats
  const { data: stats, isLoading: loadingStats } = useQuery<DashboardStats>({
    queryKey: ['operational-dashboard-unified-stats'],
    queryFn: () => api.get('/operational-dashboard/stats').then(response => unwrapData<DashboardStats>(response)),
    refetchInterval: 30000, // Refresh every 30 seconds for "real-time" feel
  })

  // Fetch active displacements
  const { data: displacements, isLoading: loadingDisplacements } = useQuery<ActiveDisplacement[]>({
    queryKey: ['operational-active-displacements'],
    queryFn: () => api.get('/operational-dashboard/active-displacements').then(response => unwrapData<ActiveDisplacement[]>(response)),
    refetchInterval: 20000,
  })

  // Fetch today's work orders (existing)
  const { data: todayOrders, isLoading: loadingOrders } = useQuery<WorkOrderSummary[]>({
    queryKey: ['operational-today-orders', today],
    queryFn: () => api.get('/work-orders', {
      params: { scheduled_date: today, per_page: 50 }
    }).then(response => {
      const raw = unwrapData(response)
      if (raw && typeof raw === 'object' && 'data' in (raw as Record<string, unknown>)) {
        return safeArray<WorkOrderSummary>((raw as Record<string, unknown>).data)
      }
      return safeArray<WorkOrderSummary>(raw)
    }),
  })

  const isLoading = loadingStats || loadingOrders || loadingDisplacements

  return (
    <div className="space-y-6">
      <PageHeader
        title="Painel Operacional"
        subtitle={`Gestão em tempo real - ${new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}`}
      />

      {isLoading && !stats ? (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      ) : (
        <>
          {/* KPI Cards */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <KpiCard
              icon={<Wrench className="h-5 w-5 text-blue-500" />}
              label="O.S. Ativas"
              value={(stats?.work_orders.open ?? 0) + (stats?.work_orders.in_progress ?? 0)}
              sub={`${stats?.work_orders.open ?? 0} abertas / ${stats?.work_orders.in_progress ?? 0} em curso`}
              color="text-blue-600"
            />
            <KpiCard
              icon={<Truck className="h-5 w-5 text-emerald-500" />}
              label="Em Deslocamento"
              value={stats?.work_orders.in_displacement ?? 0}
              sub="Técnicos a caminho do cliente"
              color="text-emerald-600"
            />
            <KpiCard
              icon={<AlertTriangle className="h-5 w-5 text-amber-500" />}
              label="Calibração/Alertas"
              value={(stats?.equipment.overdue ?? 0) + (stats?.equipment.due_soon ?? 0)}
              sub={`${stats?.equipment.overdue ?? 0} vencidas / ${stats?.equipment.due_soon ?? 0} prox. 7 dias`}
              color="text-amber-600"
            />
            <KpiCard
              icon={<Calendar className="h-5 w-5 text-green-500" />}
              label="Recebíveis de Hoje"
              value={stats?.financial.due_today_count ?? 0}
              sub={new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(stats?.financial.due_today_amount ?? 0)}
              color="text-green-600"
            />
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Today's Work Orders - Main Column */}
            <div className="lg:col-span-2 space-y-6">
              <Card>
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-sm flex items-center gap-2">
                      <Calendar className="h-4 w-4" /> Agenda de Hoje ({todayOrders?.length ?? 0})
                    </CardTitle>
                    <Button size="sm" variant="outline" onClick={() => navigate('/os')}>
                      Ver Todas
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  {!todayOrders?.length ? (
                    <div className="flex flex-col items-center justify-center py-8 text-center">
                      <ClipboardCheck className="h-8 w-8 text-muted-foreground/50 mb-2" />
                      <p className="text-sm text-muted-foreground">Nenhuma O.S. agendada para hoje</p>
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-sm">
                        <thead>
                          <tr className="border-b text-left">
                            <th className="p-2">Número</th>
                            <th className="p-2">Cliente</th>
                            <th className="p-2">Técnico</th>
                            <th className="p-2">Status</th>
                            <th className="p-2 text-right">Ações</th>
                          </tr>
                        </thead>
                        <tbody>
                          {todayOrders.map((wo) => (
                            <tr key={wo.id} className="border-b hover:bg-muted/50">
                              <td className="p-2 font-mono text-xs font-semibold">{wo.number}</td>
                              <td className="p-2 truncate max-w-[150px]">{wo.customer?.name ?? '--'}</td>
                              <td className="p-2">{wo.technician?.name ?? 'Não atribuído'}</td>
                              <td className="p-2">
                                <span className={cn(
                                  'text-[10px] uppercase font-bold px-2 py-0.5 rounded-full',
                                  statusColors[wo.status] ?? 'bg-gray-100 text-gray-700'
                                )}>
                                  {statusLabels[wo.status] ?? wo.status}
                                </span>
                              </td>
                              <td className="p-2 text-right">
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  className="h-7 w-7 p-0"
                                  onClick={() => navigate(`/os/${wo.id}`)}
                                >
                                  <Activity className="h-4 w-4" />
                                </Button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Sidebar Column: Displacements & Alertas */}
            <div className="space-y-6">
              {/* Active Displacements */}
              <Card className="border-emerald-100 bg-emerald-50/30">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm flex items-center gap-2 text-emerald-900">
                    <Truck className="h-4 w-4" /> Técnicos em Trânsito
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {!displacements?.length ? (
                    <p className="text-xs text-emerald-600/60 py-4 text-center italic">Nenhum deslocamento ativo agora</p>
                  ) : (
                    <div className="space-y-3">
                      {displacements.map(d => (
                        <div key={d.work_order_id} className="bg-white p-3 rounded-lg border border-emerald-100 shadow-sm">
                          <div className="flex justify-between items-start mb-1">
                            <span className="text-xs font-bold text-emerald-900">{d.technician}</span>
                            <span className="text-[10px] text-emerald-500">{new Date(d.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                          </div>
                          <p className="text-[11px] text-surface-600 mb-2 truncate">Destino: {d.customer}</p>
                          <Button size="sm" variant="outline" className="w-full h-7 text-[10px] bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100" onClick={() => navigate(`/os/mapa?id=${d.work_order_id}`)}>
                            Ver no Mapa
                          </Button>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Operational Alerts */}
              <Card className="border-amber-100 bg-amber-50/30">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm flex items-center gap-2 text-amber-900">
                    <AlertTriangle className="h-4 w-4" /> Alertas Críticos
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                  {(stats?.equipment.overdue ?? 0) > 0 && (
                    <div className="flex items-center justify-between p-2 rounded bg-white border border-red-200 shadow-sm">
                      <span className="text-xs font-medium text-red-700">{stats?.equipment.overdue} Equip. Vencidos</span>
                      <Button size="sm" variant="ghost" className="h-6 px-2 text-[10px] text-red-600 hover:bg-red-50" onClick={() => navigate('/equipamentos?filter=overdue')}>Ver</Button>
                    </div>
                  )}
                  {(stats?.financial.due_today_count ?? 0) > 0 && (
                    <div className="flex items-center justify-between p-2 rounded bg-white border border-green-200 shadow-sm">
                      <span className="text-xs font-medium text-green-700">{stats?.financial.due_today_count} Faturas hoje</span>
                      <Button size="sm" variant="ghost" className="h-6 px-2 text-[10px] text-green-600 hover:bg-green-50" onClick={() => navigate('/financeiro/receber?due_date=' + today)}>Ver</Button>
                    </div>
                  )}
                  {!stats?.equipment.overdue && !stats?.financial.due_today_count && (
                    <div className="py-4 text-center text-xs text-amber-600/60 italic">Sem alertas críticos pendentes</div>
                  )}
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Quick Actions */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <Button variant="outline" className="h-auto py-3 flex-col gap-1 border-surface-200 hover:border-brand-300 hover:bg-brand-50" onClick={() => navigate('/os/nova')}>
              <Wrench className="h-5 w-5 text-surface-600" />
              <span className="text-xs">Nova O.S.</span>
            </Button>
            <Button variant="outline" className="h-auto py-3 flex-col gap-1 border-surface-200 hover:border-brand-300 hover:bg-brand-50" onClick={() => navigate('/os/kanban')}>
              <ClipboardCheck className="h-5 w-5 text-surface-600" />
              <span className="text-xs">Painel Kanban</span>
            </Button>
            <Button variant="outline" className="h-auto py-3 flex-col gap-1 border-surface-200 hover:border-brand-300 hover:bg-brand-50" onClick={() => navigate('/os/mapa')}>
              <Truck className="h-5 w-5 text-surface-600" />
              <span className="text-xs">Monitorar Campo</span>
            </Button>
            <Button variant="outline" className="h-auto py-3 flex-col gap-1 border-surface-200 hover:border-brand-300 hover:bg-brand-50" onClick={() => navigate('/tecnicos/agenda')}>
              <Calendar className="h-5 w-5 text-surface-600" />
              <span className="text-xs">Agenda Equipe</span>
            </Button>
          </div>
        </>
      )}
    </div>
  )
}
