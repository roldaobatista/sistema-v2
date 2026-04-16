import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
    Award,
    Download,
    Calendar,
    Clock,
    CheckCircle,
    AlertCircle,
    FileText,
    Search,
    Filter,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import api from '@/lib/api'
import { cn, formatDate } from '@/lib/utils'

interface Certificate {
    id: number
    certificate_number: string
    equipment_name: string
    equipment_tag: string
    calibration_date: string
    next_calibration_date: string
    status: string
    download_url: string | null
    measurements_count: number
}

const statusConfig: Record<string, { label: string; color: string; bg: string; icon: LucideIcon }> = {
    valid: { label: 'Válido', color: 'text-emerald-700', bg: 'bg-emerald-50 border-emerald-200', icon: CheckCircle },
    expiring_soon: { label: 'Vencendo', color: 'text-amber-700', bg: 'bg-amber-50 border-amber-200', icon: Clock },
    expired: { label: 'Vencido', color: 'text-red-700', bg: 'bg-red-50 border-red-200', icon: AlertCircle },
    draft: { label: 'Rascunho', color: 'text-surface-500', bg: 'bg-surface-50 border-surface-200', icon: FileText },
}

export function PortalCertificatesPage() {
    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState<string>('all')

    const { data, isLoading, isError } = useQuery({
        queryKey: ['portal-certificates', statusFilter],
        queryFn: () => api.get('/portal/certificates', {
            params: { status: statusFilter !== 'all' ? statusFilter : undefined },
        }).then((res) => res.data?.data ?? res.data ?? []),
    })

    useEffect(() => {
        if (isError) {
            toast.error('Erro ao carregar certificados')
        }
    }, [isError])

    const [now, setNow] = useState(() => 0)
    useEffect(() => { setNow(Date.now()) }, [data])
    const certificates: Certificate[] = Array.isArray(data) ? data : data?.data ?? []

    const filtered = certificates.filter((certificate) =>
        !search
        || certificate.certificate_number?.toLowerCase().includes(search.toLowerCase())
        || certificate.equipment_name?.toLowerCase().includes(search.toLowerCase())
        || certificate.equipment_tag?.toLowerCase().includes(search.toLowerCase())
    )

    const stats = {
        total: certificates.length,
        valid: certificates.filter((certificate) => certificate.status === 'valid').length,
        expiring: certificates.filter((certificate) => certificate.status === 'expiring_soon').length,
        expired: certificates.filter((certificate) => certificate.status === 'expired').length,
    }

    const handleDownload = async (certificate: Certificate) => {
        if (!certificate.download_url) {
            toast.error('Certificado não disponível para download')
            return
        }

        try {
            const response = await api.get(certificate.download_url, { responseType: 'blob' })
            const url = window.URL.createObjectURL(new Blob([response.data]))
            const link = document.createElement('a')
            link.href = url
            link.setAttribute('download', `certificado_${certificate.certificate_number}.pdf`)
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.URL.revokeObjectURL(url)
            toast.success('Download iniciado')
        } catch {
            toast.error('Erro ao baixar certificado')
        }
    }

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-lg font-semibold text-surface-900 tracking-tight">
                    Certificados de Calibração
                </h1>
                <p className="mt-0.5 text-sm text-surface-500">
                    Consulte e baixe os certificados de calibração dos seus equipamentos.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-4">
                {[
                    { label: 'Total', value: stats.total, icon: Award, color: 'text-brand-600 bg-brand-50' },
                    { label: 'Válidos', value: stats.valid, icon: CheckCircle, color: 'text-emerald-600 bg-emerald-50' },
                    { label: 'Vencendo', value: stats.expiring, icon: Clock, color: 'text-amber-600 bg-amber-50' },
                    { label: 'Vencidos', value: stats.expired, icon: AlertCircle, color: 'text-red-600 bg-red-50' },
                ].map((stat) => (
                    <div key={stat.label} className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className={cn('rounded-lg p-2', stat.color)}>
                                <stat.icon className="h-4 w-4" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-surface-500 uppercase tracking-wider">{stat.label}</p>
                                <p className="text-lg font-bold text-surface-900">{stat.value}</p>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                    <input
                        type="text"
                        placeholder="Buscar por número, equipamento ou tag..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="w-full rounded-lg border border-default bg-surface-0 py-2 pl-9 pr-3 text-sm placeholder:text-surface-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Filter className="h-4 w-4 text-surface-400" />
                    <select
                        aria-label="Filtrar por status"
                        value={statusFilter}
                        onChange={(event) => setStatusFilter(event.target.value)}
                        className="rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm focus:border-brand-500 outline-none"
                    >
                        <option value="all">Todos</option>
                        <option value="valid">Válidos</option>
                        <option value="expiring_soon">Vencendo</option>
                        <option value="expired">Vencidos</option>
                    </select>
                </div>
            </div>

            {isLoading ? (
                <div className="rounded-xl border border-default bg-surface-0 p-8 text-center text-sm text-surface-500 shadow-card">
                    Carregando certificados...
                </div>
            ) : filtered.length === 0 ? (
                <div className="rounded-xl border border-default bg-surface-0 p-12 text-center shadow-card">
                    <FileText className="mx-auto h-10 w-10 text-surface-300" />
                    <p className="mt-3 text-sm font-medium text-surface-600">Nenhum certificado encontrado</p>
                    <p className="mt-1 text-xs text-surface-400">Seus certificados aparecerão aqui após a calibração.</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {filtered.map((certificate) => {
                        const config = statusConfig[certificate.status] ?? statusConfig.draft
                        const daysUntilExpiry = certificate.next_calibration_date
                            ? Math.ceil((new Date(certificate.next_calibration_date).getTime() - now) / (1000 * 60 * 60 * 24))
                            : null

                        return (
                            <div key={certificate.id} className="rounded-xl border border-default bg-surface-0 p-4 shadow-card transition-shadow hover:shadow-elevated">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 flex-1">
                                        <div className="mb-1 flex items-center gap-2">
                                            <Award className="h-4 w-4 flex-shrink-0 text-brand-500" />
                                            <span className="truncate text-sm font-bold text-surface-900">
                                                {certificate.certificate_number}
                                            </span>
                                            <span className={cn('rounded-full border px-2 py-0.5 text-xs font-semibold', config.bg, config.color)}>
                                                {config.label}
                                            </span>
                                        </div>
                                        <p className="truncate text-sm text-surface-700">
                                            {certificate.equipment_name}
                                            {certificate.equipment_tag && <span className="ml-1 text-surface-400">({certificate.equipment_tag})</span>}
                                        </p>
                                        <div className="mt-2 flex items-center gap-4 text-xs text-surface-500">
                                            <span className="flex items-center gap-1">
                                                <Calendar className="h-3 w-3" />
                                                Calibrado: {formatDate(certificate.calibration_date)}
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <Clock className="h-3 w-3" />
                                                Próxima: {formatDate(certificate.next_calibration_date)}
                                            </span>
                                            {daysUntilExpiry !== null && daysUntilExpiry > 0 && (
                                                <span className={cn(
                                                    'font-medium',
                                                    daysUntilExpiry <= 30 ? 'text-amber-600' : 'text-surface-500',
                                                )}>
                                                    ({daysUntilExpiry} dias)
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => handleDownload(certificate)}
                                        disabled={!certificate.download_url}
                                        className={cn(
                                            'flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                                            certificate.download_url
                                                ? 'bg-brand-600 text-white hover:bg-brand-700'
                                                : 'cursor-not-allowed bg-surface-100 text-surface-400',
                                        )}
                                    >
                                        <Download className="h-4 w-4" />
                                        PDF
                                    </button>
                                </div>
                            </div>
                        )
                    })}
                </div>
            )}
        </div>
    )
}
