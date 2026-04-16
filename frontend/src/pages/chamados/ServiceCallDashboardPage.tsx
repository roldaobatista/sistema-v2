import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { serviceCallApi } from '@/lib/service-call-api'
import { queryKeys } from '@/lib/query-keys'
import type { ServiceCallKpi } from '@/types/service-call'
import { BarChart3, Clock, AlertTriangle, Users, TrendingUp, ArrowLeft, RefreshCw } from 'lucide-react'

type VolumeByDayEntry = ServiceCallKpi['volume_by_day'][number]
type TechnicianKpiEntry = ServiceCallKpi['by_technician'][number]
type TopCustomerEntry = ServiceCallKpi['top_customers'][number]

export default function ServiceCallDashboardPage() {
    const navigate = useNavigate()
    const [days, setDays] = useState(30)

    const { data: kpi, isLoading, isError, refetch } = useQuery<ServiceCallKpi>({
        queryKey: [...queryKeys.serviceCalls.kpi, days],
        queryFn: () => serviceCallApi.kpi({ days }),
        retry: 1,
    })

    const volumeDays = kpi?.volume_by_day ?? []
    const maxVolume = volumeDays.length > 0 ? Math.max(...volumeDays.map((day: VolumeByDayEntry) => day.total)) : 1

    if (isLoading) {
        return (
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '60vh' }}>
                <div style={{ width: 32, height: 32, border: '3px solid #e5e7eb', borderTopColor: '#3b82f6', borderRadius: '50%', animation: 'spin 1s linear infinite' }} />
            </div>
        )
    }

    if (isError) {
        return (
            <div style={{ display: 'flex', flexDirection: 'column', justifyContent: 'center', alignItems: 'center', height: '60vh', gap: 12 }}>
                <AlertTriangle size={40} color="#f59e0b" />
                <p style={{ fontSize: 16, color: '#374151', fontWeight: 600 }}>Erro ao carregar KPIs de chamados</p>
                <p style={{ fontSize: 13, color: '#6b7280' }}>Verifique os logs do servidor ou tente novamente.</p>
                <button onClick={() => refetch()} style={{ padding: '8px 16px', borderRadius: 8, border: '1px solid #3b82f6', background: '#3b82f6', color: 'white', fontSize: 13, cursor: 'pointer' }}>Tentar novamente</button>
            </div>
        )
    }

    return (
        <div style={{ padding: 24, maxWidth: 1400, margin: '0 auto' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
                <div>
                    <h1 style={{ fontSize: 24, fontWeight: 700, color: '#111827' }}>Dashboard KPI — Chamados</h1>
                    <p style={{ fontSize: 14, color: '#6b7280', marginTop: 4 }}>Métricas dos últimos {days} dias — {kpi?.total_period ?? 0} chamados</p>
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    {[7, 15, 30, 60, 90].map((value) => (
                        <button
                            key={value}
                            onClick={() => setDays(value)}
                            style={{
                                padding: '6px 14px', borderRadius: 8, border: '1px solid ' + (days === value ? '#3b82f6' : '#d1d5db'),
                                background: days === value ? '#3b82f6' : 'white', color: days === value ? 'white' : '#374151',
                                fontSize: 13, cursor: 'pointer', fontWeight: days === value ? 600 : 400,
                            }}
                        >
                            {value}d
                        </button>
                    ))}
                    <button onClick={() => refetch()} style={{ padding: '6px 10px', borderRadius: 8, border: '1px solid #d1d5db', background: 'white', cursor: 'pointer' }}>
                        <RefreshCw size={14} />
                    </button>
                    <button
                        onClick={() => navigate('/chamados')}
                        style={{ padding: '6px 14px', borderRadius: 8, border: '1px solid #d1d5db', background: 'white', fontSize: 13, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 4 }}
                    >
                        <ArrowLeft size={14} /> Lista
                    </button>
                </div>
            </div>

            {/* KPI Cards */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 16, marginBottom: 24 }}>
                <KpiCard icon={<Clock size={20} color="#3b82f6" />} label="MTTR (Resolução)" value={`${kpi?.mttr_hours ?? 0}h`} color="#3b82f6" />
                <KpiCard icon={<TrendingUp size={20} color="#0d9488" />} label="Tempo de Triagem" value={`${kpi?.mt_triage_hours ?? 0}h`} color="#0d9488" />
                <KpiCard icon={<AlertTriangle size={20} color="#ef4444" />} label="Taxa SLA Estourado" value={`${kpi?.sla_breach_rate ?? 0}%`} color="#ef4444" />
                <KpiCard icon={<RefreshCw size={20} color="#f59e0b" />} label="Taxa Reagendamento" value={`${kpi?.reschedule_rate ?? 0}%`} color="#f59e0b" />
            </div>

            {/* Charts Row */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 24 }}>
                {/* Volume by Day */}
                <div style={{ background: 'white', borderRadius: 12, border: '1px solid #e5e7eb', padding: 20 }}>
                    <h3 style={{ fontSize: 14, fontWeight: 600, color: '#374151', marginBottom: 16, display: 'flex', alignItems: 'center', gap: 6 }}>
                        <BarChart3 size={16} /> Volume Diário
                    </h3>
                    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 3, height: 160 }}>
                        {volumeDays.slice(-30).map((day: VolumeByDayEntry, index: number) => (
                            <div
                                key={index}
                                title={`${new Date(day.date).toLocaleDateString('pt-BR')}: ${day.total} chamados`}
                                style={{
                                    flex: 1,
                                    minWidth: 6,
                                    background: `linear-gradient(to top, #3b82f6, #60a5fa)`,
                                    borderRadius: '4px 4px 0 0',
                                    height: `${Math.max((day.total / maxVolume) * 100, 4)}%`,
                                    transition: 'height 0.3s',
                                    cursor: 'pointer',
                                }}
                            />
                        ))}
                    </div>
                    {volumeDays.length === 0 && (
                        <p style={{ textAlign: 'center', color: '#9ca3af', fontSize: 13 }}>Sem dados no período</p>
                    )}
                </div>

                {/* By Technician */}
                <div style={{ background: 'white', borderRadius: 12, border: '1px solid #e5e7eb', padding: 20 }}>
                    <h3 style={{ fontSize: 14, fontWeight: 600, color: '#374151', marginBottom: 16, display: 'flex', alignItems: 'center', gap: 6 }}>
                        <Users size={16} /> Por Técnico
                    </h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        {(kpi?.by_technician ?? []).sort((a: TechnicianKpiEntry, b: TechnicianKpiEntry) => b.total - a.total).slice(0, 8).map((technician, index: number) => {
                            const maxTech = Math.max(1, ...(kpi?.by_technician ?? []).map((entry: TechnicianKpiEntry) => entry.total))
                            return (
                                <div key={index} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <span style={{ fontSize: 12, color: '#374151', minWidth: 100, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{technician.technician}</span>
                                    <div style={{ flex: 1, background: '#f3f4f6', borderRadius: 4, height: 14, overflow: 'hidden' }}>
                                        <div style={{ height: '100%', background: '#0d9488', borderRadius: 4, width: `${(technician.total / maxTech) * 100}%`, transition: 'width 0.3s' }} />
                                    </div>
                                    <span style={{ fontSize: 12, fontWeight: 600, color: '#374151', minWidth: 24, textAlign: 'right' }}>{technician.total}</span>
                                </div>
                            )
                        })}
                    </div>
                </div>
            </div>

            {/* Top Customers */}
            <div style={{ background: 'white', borderRadius: 12, border: '1px solid #e5e7eb', padding: 20 }}>
                <h3 style={{ fontSize: 14, fontWeight: 600, color: '#374151', marginBottom: 16 }}>Top 10 Clientes Recorrentes</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))', gap: 8 }}>
                    {(kpi?.top_customers ?? []).map((customer: TopCustomerEntry, index: number) => (
                        <div key={index} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 12px', background: '#f9fafb', borderRadius: 8 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={{ fontWeight: 700, color: '#6b7280', fontSize: 12, minWidth: 20 }}>#{index + 1}</span>
                                <span style={{ fontSize: 13, color: '#374151' }}>{customer.customer ?? 'N/A'}</span>
                            </div>
                            <span style={{ fontWeight: 700, fontSize: 14, color: '#3b82f6' }}>{customer.total}</span>
                        </div>
                    ))}
                </div>
            </div>

            <style>{`@keyframes spin { from { transform: rotate(0deg) } to { transform: rotate(360deg) } }`}</style>
        </div>
    )
}

function KpiCard({ icon, label, value, color }: { icon: React.ReactNode; label: string; value: string; color: string }) {
    return (
        <div style={{ background: 'white', borderRadius: 12, border: '1px solid #e5e7eb', padding: 20, display: 'flex', alignItems: 'center', gap: 16 }}>
            <div style={{ width: 44, height: 44, borderRadius: 10, background: color + '12', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                {icon}
            </div>
            <div>
                <p style={{ fontSize: 12, color: '#6b7280', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{label}</p>
                <p style={{ fontSize: 24, fontWeight: 700, color: '#111827', marginTop: 2 }}>{value}</p>
            </div>
        </div>
    )
}
