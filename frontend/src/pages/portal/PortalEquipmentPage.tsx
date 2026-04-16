import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
    Search,
    Wrench,
    Calendar,
    AlertTriangle,
    CheckCircle,
    Clock,
    RefreshCw,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import api from '@/lib/api'
import { unwrapData } from '@/lib/api'
import { normalizePortalCalibrationStatus } from '@/lib/equipment-utils'
import { cn } from '@/lib/utils'

interface Equipment {
    id: number
    brand: string
    model: string
    serial_number: string
    tag?: string
    location?: string
    next_calibration_at?: string
    calibration_status?: string | null
    last_os_date?: string | null
    os_count?: number
}

const calibrationConfig: Record<string, { label: string; color: string; bg: string; icon: LucideIcon }> = {
    valid: { label: 'Válida', color: 'text-emerald-600', bg: 'bg-emerald-100', icon: CheckCircle },
    expiring: { label: 'Vencendo', color: 'text-amber-600', bg: 'bg-amber-100', icon: Clock },
    expired: { label: 'Vencida', color: 'text-red-600', bg: 'bg-red-100', icon: AlertTriangle },
}

const fmtDate = (date: string) => new Date(date).toLocaleDateString('pt-BR')

export function PortalEquipmentPage() {
    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState('')

    const { data, isLoading, isError, refetch } = useQuery({
        queryKey: ['portal-equipment'],
        queryFn: () => api.get('/portal/equipment').then(unwrapData<Equipment[]>),
    })

    const all: Equipment[] = data ?? []

    const filtered = useMemo(() => {
        let list = all

        if (statusFilter) {
            list = list.filter((equipment) => normalizePortalCalibrationStatus(equipment.calibration_status) === statusFilter)
        }

        if (search) {
            const query = search.toLowerCase()
            list = list.filter((equipment) =>
                (equipment.brand ?? '').toLowerCase().includes(query)
                || (equipment.model ?? '').toLowerCase().includes(query)
                || (equipment.serial_number ?? '').toLowerCase().includes(query)
                || (equipment.tag ?? '').toLowerCase().includes(query)
            )
        }

        return list
    }, [all, search, statusFilter])

    const counts = useMemo(() => {
        const countByStatus: Record<string, number> = { valid: 0, expiring: 0, expired: 0 }

        all.forEach((equipment) => {
            const normalizedStatus = normalizePortalCalibrationStatus(equipment.calibration_status)
            if (normalizedStatus && countByStatus[normalizedStatus] !== undefined) {
                countByStatus[normalizedStatus] += 1
            }
        })

        return countByStatus
    }, [all])

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-lg font-semibold text-surface-900 tracking-tight">
                    Meus Equipamentos
                </h1>
                <p className="mt-0.5 text-sm text-surface-500">
                    Equipamentos vinculados à sua empresa
                </p>
            </div>

            <div className="flex flex-wrap gap-2">
                <button
                    onClick={() => setStatusFilter('')}
                    className={cn(
                        'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                        !statusFilter ? 'border-brand-600 bg-brand-600 text-white' : 'border-default bg-surface-0 text-surface-600 hover:bg-surface-50',
                    )}
                >
                    Todos ({all.length})
                </button>
                {Object.entries(calibrationConfig).map(([key, config]) => (
                    counts[key] > 0 && (
                        <button
                            key={key}
                            onClick={() => setStatusFilter(statusFilter === key ? '' : key)}
                            className={cn(
                                'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                statusFilter === key ? `${config.bg} ${config.color} border-current` : 'border-default bg-surface-0 text-surface-600 hover:bg-surface-50',
                            )}
                        >
                            {config.label} ({counts[key]})
                        </button>
                    )
                ))}
            </div>

            <div className="relative max-w-sm">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                <input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Buscar equipamento..."
                    className="w-full rounded-lg border border-default bg-surface-50 py-2.5 pl-10 pr-4 text-sm focus:border-brand-500 focus:outline-none"
                />
            </div>

            {isLoading ? (
                <div className="space-y-3">
                    {[1, 2, 3].map((item) => (
                        <div key={item} className="animate-pulse rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <div className="mb-3 flex items-center gap-3">
                                <div className="h-10 w-10 rounded-lg bg-surface-200" />
                                <div className="flex-1 space-y-1">
                                    <div className="h-4 w-40 rounded bg-surface-200" />
                                    <div className="h-3 w-28 rounded bg-surface-100" />
                                </div>
                            </div>
                            <div className="flex gap-6">
                                <div className="h-3 w-20 rounded bg-surface-100" />
                                <div className="h-3 w-24 rounded bg-surface-100" />
                            </div>
                        </div>
                    ))}
                </div>
            ) : isError ? (
                <div className="py-12 text-center">
                    <RefreshCw className="mx-auto h-10 w-10 text-red-300" />
                    <p className="mt-2 text-sm text-surface-400">Erro ao carregar equipamentos</p>
                    <button onClick={() => refetch()} className="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Tentar novamente
                    </button>
                </div>
            ) : filtered.length === 0 ? (
                <div className="py-12 text-center">
                    <Wrench className="mx-auto h-10 w-10 text-surface-300" />
                    <p className="mt-2 text-sm text-surface-400">Nenhum equipamento encontrado</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {filtered.map((equipment) => {
                        const normalizedStatus = normalizePortalCalibrationStatus(equipment.calibration_status)
                        const config = normalizedStatus ? calibrationConfig[normalizedStatus] : null
                        const StatusIcon = config?.icon ?? Wrench

                        return (
                            <div key={equipment.id} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card transition-all hover:shadow-elevated">
                                <div className="mb-2 flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className={cn('rounded-lg p-2', config?.bg ?? 'bg-surface-100')}>
                                            <StatusIcon className={cn('h-4 w-4', config?.color ?? 'text-surface-400')} />
                                        </div>
                                        <div>
                                            <p className="text-sm font-bold text-surface-900">
                                                {equipment.brand} {equipment.model}
                                            </p>
                                            <p className="text-xs text-surface-500">
                                                S/N: {equipment.serial_number}
                                                {equipment.tag && <> · Tag: {equipment.tag}</>}
                                            </p>
                                        </div>
                                    </div>
                                    {config && (
                                        <span className={cn('rounded-full px-2.5 py-1 text-xs font-semibold', config.bg, config.color)}>
                                            {config.label}
                                        </span>
                                    )}
                                </div>

                                <div className="mt-3 flex flex-wrap gap-4 text-xs text-surface-500">
                                    {equipment.location && (
                                        <span>Local: <strong className="text-surface-700">{equipment.location}</strong></span>
                                    )}
                                    {equipment.next_calibration_at && (
                                        <span className="flex items-center gap-1">
                                            <Calendar className="h-3 w-3" />
                                            Próx. calibração: <strong className="text-surface-700">{fmtDate(equipment.next_calibration_at)}</strong>
                                        </span>
                                    )}
                                    {equipment.os_count !== undefined && equipment.os_count > 0 && (
                                        <span>{equipment.os_count} OS realizadas</span>
                                    )}
                                </div>
                            </div>
                        )
                    })}
                </div>
            )}
        </div>
    )
}
