import { useEffect, useMemo, useRef, useState, type MutableRefObject } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import {
    AlertTriangle,
    ExternalLink,
    MapPin,
    Navigation,
    RefreshCcw,
    Search,
    Wrench,
} from 'lucide-react'
import { unwrapData } from '@/lib/api'
import { getStatusEntry, priorityConfig, workOrderStatus } from '@/lib/status-config'
import { workOrderApi, type WorkOrderListResponse } from '@/lib/work-order-api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import type { WorkOrder } from '@/types/work-order'

const DEFAULT_CENTER: [number, number] = [-16.4673, -54.6353]
const ACTIVE_STATUS_FILTER = 'active'
const STATUS_FILTER_ALL = 'all'
const MAP_STATUS_OPTIONS = [
    ACTIVE_STATUS_FILTER,
    'open',
    'awaiting_dispatch',
    'in_displacement',
    'displacement_paused',
    'at_client',
    'in_service',
    'service_paused',
    'awaiting_return',
    'in_return',
    'return_paused',
    'waiting_parts',
    'waiting_approval',
    'completed',
    'delivered',
    'invoiced',
    'cancelled',
] as const
const ACTIVE_MAP_STATUSES = new Set<string>([
    'open',
    'awaiting_dispatch',
    'in_displacement',
    'displacement_paused',
    'at_client',
    'in_service',
    'service_paused',
    'awaiting_return',
    'in_return',
    'return_paused',
    'in_progress',
    'waiting_parts',
    'waiting_approval',
])

type WorkOrderMapSource = 'arrival' | 'customer'

interface WorkOrderMapItem {
    workOrder: WorkOrder
    latitude: number
    longitude: number
    source: WorkOrderMapSource
    locationLabel: string
    externalMapUrl: string
}

function parseCoordinate(value: number | string | null | undefined): number | null {
    if (value == null || value === '') return null
    const parsed = typeof value === 'number' ? value : Number.parseFloat(value)
    return Number.isFinite(parsed) ? parsed : null
}

function getWorkOrderIdentifier(workOrder: WorkOrder): string {
    return workOrder.business_number ?? workOrder.os_number ?? workOrder.number ?? `#${workOrder.id}`
}

function getStatusKey(status: string): string {
    return status === 'in_progress' ? 'in_service' : status
}

function resolveWorkOrderLocationLabel(workOrder: WorkOrder): string {
    return [
        workOrder.address,
        workOrder.city,
        workOrder.state,
    ].filter(Boolean).join(' - ')
        || [
            workOrder.customer?.address_city,
            workOrder.customer?.address_state,
        ].filter(Boolean).join('/')
        || 'Localizacao sem endereco detalhado'
}

function resolveExternalMapUrl(workOrder: WorkOrder, latitude: number, longitude: number): string {
    const customerMapsLink = workOrder.customer?.google_maps_link
    if (typeof customerMapsLink === 'string' && customerMapsLink.trim() !== '') {
        return customerMapsLink
    }

    if (typeof workOrder.google_maps_link === 'string' && workOrder.google_maps_link.trim() !== '') {
        return workOrder.google_maps_link
    }

    if (typeof workOrder.waze_link === 'string' && workOrder.waze_link.trim() !== '') {
        return workOrder.waze_link
    }

    return `https://www.google.com/maps?q=${latitude},${longitude}`
}

function resolveMapItem(workOrder: WorkOrder): WorkOrderMapItem | null {
    const arrivalLatitude = parseCoordinate(workOrder.arrival_latitude)
    const arrivalLongitude = parseCoordinate(workOrder.arrival_longitude)
    const customerLatitude = parseCoordinate(workOrder.customer?.latitude)
    const customerLongitude = parseCoordinate(workOrder.customer?.longitude)

    const hasArrivalLocation = arrivalLatitude != null && arrivalLongitude != null
    const hasCustomerLocation = customerLatitude != null && customerLongitude != null

    if (!hasArrivalLocation && !hasCustomerLocation) {
        return null
    }

    const latitude = hasArrivalLocation ? arrivalLatitude : customerLatitude
    const longitude = hasArrivalLocation ? arrivalLongitude : customerLongitude
    if (latitude == null || longitude == null) {
        return null
    }

    return {
        workOrder,
        latitude,
        longitude,
        source: hasArrivalLocation ? 'arrival' : 'customer',
        locationLabel: resolveWorkOrderLocationLabel(workOrder),
        externalMapUrl: resolveExternalMapUrl(workOrder, latitude, longitude),
    }
}

function createMarkerIcon(status: string, selected: boolean): L.DivIcon {
    const entry = getStatusEntry(workOrderStatus, getStatusKey(status))
    const colorMap: Record<string, string> = {
        info: '#2563eb',
        warning: '#f59e0b',
        success: '#16a34a',
        danger: '#dc2626',
        destructive: '#dc2626',
        brand: '#0d9488',
        default: '#64748b',
    }
    const fill = colorMap[entry.variant] ?? '#64748b'
    const ring = selected ? '#111827' : '#ffffff'

    return L.divIcon({
        className: 'custom-marker',
        iconSize: [26, 36],
        iconAnchor: [13, 36],
        popupAnchor: [0, -34],
        html: `
            <div style="position:relative;display:flex;align-items:center;justify-content:center;">
                <svg width="26" height="36" viewBox="0 0 26 36" fill="none">
                    <path d="M13 1C6.373 1 1 6.373 1 13c0 9.2 12 22 12 22s12-12.8 12-22C25 6.373 19.627 1 13 1z" fill="${fill}" stroke="${ring}" stroke-width="2"/>
                    <circle cx="13" cy="13" r="4.5" fill="white" opacity="0.92"/>
                </svg>
            </div>
        `,
    })
}

function FitBounds({ points }: { points: [number, number][] }) {
    const map = useMap()

    useEffect(() => {
        if (points.length === 0) return
        const bounds = L.latLngBounds(points)
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [32, 32], maxZoom: 14 })
        }
    }, [map, points])

    return null
}

function MapRefSetter({ mapRef }: { mapRef: MutableRefObject<L.Map | null> }) {
    const map = useMap()

    useEffect(() => {
        mapRef.current = map
    }, [map, mapRef])

    return null
}

export function WorkOrderMapPage() {
    const navigate = useNavigate()
    const mapRef = useRef<L.Map | null>(null)
    const { hasPermission } = useAuthStore()
    const canView = hasPermission('os.work_order.view')
    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState<string>(ACTIVE_STATUS_FILTER)
    const [selectedId, setSelectedId] = useState<number | null>(null)

    const { data, isLoading, isError, refetch, isFetching } = useQuery({
        queryKey: ['work-orders-map'],
        queryFn: async () => {
            const response = await workOrderApi.list({ per_page: 500 })
            return unwrapData<WorkOrderListResponse>(response)
        },
        enabled: canView,
        staleTime: 30_000,
        refetchInterval: 60_000,
    })

    const allOrders = data?.data ?? []
    const filteredOrders = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase()

        return allOrders.filter((workOrder) => {
            const normalizedStatus = getStatusKey(workOrder.status)
            if (statusFilter === ACTIVE_STATUS_FILTER && !ACTIVE_MAP_STATUSES.has(normalizedStatus)) {
                return false
            }

            if (statusFilter !== ACTIVE_STATUS_FILTER && statusFilter !== STATUS_FILTER_ALL && normalizedStatus !== statusFilter) {
                return false
            }

            if (!normalizedSearch) {
                return true
            }

            const haystack = [
                getWorkOrderIdentifier(workOrder),
                workOrder.customer?.name,
                workOrder.description,
                resolveWorkOrderLocationLabel(workOrder),
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase()

            return haystack.includes(normalizedSearch)
        })
    }, [allOrders, search, statusFilter])

    const mappedOrders = useMemo(() => {
        return filteredOrders
            .map(resolveMapItem)
            .filter((item): item is WorkOrderMapItem => item != null)
    }, [filteredOrders])

    const missingLocationOrders = useMemo(() => {
        return filteredOrders.filter((workOrder) => resolveMapItem(workOrder) == null)
    }, [filteredOrders])

    const points = useMemo<[number, number][]>(() => {
        return mappedOrders.map((item) => [item.latitude, item.longitude])
    }, [mappedOrders])

    const selectedItem = useMemo(() => {
        return mappedOrders.find((item) => item.workOrder.id === selectedId) ?? null
    }, [mappedOrders, selectedId])

    const summary = useMemo(() => {
        return {
            total: filteredOrders.length,
            geolocated: mappedOrders.length,
            missing: missingLocationOrders.length,
            urgent: filteredOrders.filter((workOrder) => workOrder.priority === 'urgent').length,
        }
    }, [filteredOrders, mappedOrders.length, missingLocationOrders.length])

    const availableStatusOptions = useMemo<string[]>(() => {
        return MAP_STATUS_OPTIONS.filter((status) => {
            if (status === ACTIVE_STATUS_FILTER) return true
            return allOrders.some((workOrder) => getStatusKey(workOrder.status) === status)
        })
    }, [allOrders])

    const handleSelectOrder = (item: WorkOrderMapItem) => {
        setSelectedId(item.workOrder.id)
        if (mapRef.current) {
            mapRef.current.flyTo([item.latitude, item.longitude], 14, { duration: 0.7 })
        }
    }

    if (!canView) {
        return (
            <div className="space-y-4">
                <PageHeader
                    title="Mapa de Ordens de Servico"
                    description="Visualizacao geografica operacional das OS"
                />
                <div className="rounded-xl border border-default bg-surface-0 p-6 text-sm text-surface-600 shadow-card">
                    Voce nao possui permissao para visualizar o mapa de ordens de servico.
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Mapa de Ordens de Servico"
                description="Operacao em campo por localizacao, status e prioridade"
                count={summary.total}
                icon={MapPin}
                actions={[
                    {
                        label: isFetching ? 'Atualizando...' : 'Atualizar',
                        onClick: () => {
                            void refetch()
                        },
                        icon: <RefreshCcw className="h-4 w-4" />,
                        variant: 'outline',
                        disabled: isFetching,
                    },
                ]}
            />

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <p className="text-xs font-medium uppercase tracking-wide text-surface-500">OS no recorte</p>
                    <p className="mt-2 text-2xl font-semibold text-surface-900">{summary.total}</p>
                </div>
                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-card">
                    <p className="text-xs font-medium uppercase tracking-wide text-emerald-700">No mapa</p>
                    <p className="mt-2 text-2xl font-semibold text-emerald-800">{summary.geolocated}</p>
                </div>
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-card">
                    <p className="text-xs font-medium uppercase tracking-wide text-amber-700">Sem coordenadas</p>
                    <p className="mt-2 text-2xl font-semibold text-amber-800">{summary.missing}</p>
                </div>
                <div className="rounded-xl border border-red-200 bg-red-50 p-4 shadow-card">
                    <p className="text-xs font-medium uppercase tracking-wide text-red-700">Urgentes</p>
                    <p className="mt-2 text-2xl font-semibold text-red-800">{summary.urgent}</p>
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_380px]">
                <div className="space-y-4">
                    <div className="flex flex-col gap-3 rounded-xl border border-default bg-surface-0 p-4 shadow-card lg:flex-row lg:items-center lg:justify-between">
                        <div className="relative w-full lg:max-w-sm">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Buscar OS, cliente ou cidade"
                                className="h-10 w-full rounded-lg border border-default bg-surface-0 pl-10 pr-3 text-sm text-surface-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15"
                            />
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <select
                                aria-label="Filtrar status do mapa de OS"
                                value={statusFilter}
                                onChange={(event) => setStatusFilter(event.target.value)}
                                className="h-10 rounded-lg border border-default bg-surface-0 px-3 text-sm text-surface-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15"
                            >
                                <option value={STATUS_FILTER_ALL}>Todos os status</option>
                                {availableStatusOptions.map((status) => (
                                    <option key={status} value={status}>
                                        {status === ACTIVE_STATUS_FILTER
                                            ? 'Somente operacionais'
                                            : getStatusEntry(workOrderStatus, status).label}
                                    </option>
                                ))}
                            </select>
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    setSearch('')
                                    setStatusFilter(ACTIVE_STATUS_FILTER)
                                }}
                                disabled={search === '' && statusFilter === ACTIVE_STATUS_FILTER}
                            >
                                Limpar filtros
                            </Button>
                        </div>
                    </div>

                    <div className="relative min-h-[520px] overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                        {isLoading ? (
                            <div className="flex min-h-[520px] items-center justify-center">
                                <div className="space-y-3 text-center">
                                    <div className="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-brand-200 border-t-brand-600" />
                                    <p className="text-sm text-surface-500">Carregando mapa operacional...</p>
                                </div>
                            </div>
                        ) : isError ? (
                            <div className="flex min-h-[520px] items-center justify-center p-6">
                                <div className="max-w-md space-y-3 text-center">
                                    <AlertTriangle className="mx-auto h-12 w-12 text-amber-500" />
                                    <p className="text-sm font-medium text-surface-800">
                                        Nao foi possivel carregar o mapa das ordens de servico.
                                    </p>
                                    <p className="text-sm text-surface-500">
                                        Verifique a conectividade da API e tente atualizar a tela.
                                    </p>
                                </div>
                            </div>
                        ) : mappedOrders.length === 0 ? (
                            <div className="flex min-h-[520px] items-center justify-center p-6">
                                <div className="max-w-md space-y-3 text-center">
                                    <MapPin className="mx-auto h-12 w-12 text-surface-300" />
                                    <p className="text-sm font-medium text-surface-800">
                                        Nenhuma ordem de servico geolocalizada neste recorte.
                                    </p>
                                    <p className="text-sm text-surface-500">
                                        As OS precisam de latitude/longitude no cliente ou GPS de chegada para aparecer no mapa.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <MapContainer
                                center={DEFAULT_CENTER}
                                zoom={10}
                                className="h-[520px] w-full"
                            >
                                <MapRefSetter mapRef={mapRef} />
                                <TileLayer
                                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                />
                                <FitBounds points={points} />
                                {mappedOrders.map((item) => {
                                    const entry = getStatusEntry(workOrderStatus, getStatusKey(item.workOrder.status))
                                    return (
                                        <Marker
                                            key={item.workOrder.id}
                                            position={[item.latitude, item.longitude]}
                                            icon={createMarkerIcon(item.workOrder.status, selectedId === item.workOrder.id)}
                                            eventHandlers={{
                                                click: () => setSelectedId(item.workOrder.id),
                                            }}
                                        >
                                            <Popup maxWidth={280}>
                                                <div className="space-y-2 p-1">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="text-xs font-mono text-surface-500">
                                                            {getWorkOrderIdentifier(item.workOrder)}
                                                        </span>
                                                        <Badge variant={entry.variant}>{entry.label}</Badge>
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-semibold text-surface-900">
                                                            {item.workOrder.customer?.name ?? 'Sem cliente'}
                                                        </p>
                                                        <p className="text-xs text-surface-500">{item.locationLabel}</p>
                                                    </div>
                                                    <div className="flex items-center gap-2 text-xs text-surface-500">
                                                        <span>Fonte: {item.source === 'arrival' ? 'GPS de chegada' : 'Cadastro do cliente'}</span>
                                                        <span>
                                                            {item.latitude.toFixed(5)}, {item.longitude.toFixed(5)}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-2 pt-1">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => navigate(`/os/${item.workOrder.id}`)}
                                                        >
                                                            Ver OS
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            onClick={() => window.open(item.externalMapUrl, '_blank', 'noopener,noreferrer')}
                                                            icon={<Navigation className="h-4 w-4" />}
                                                        >
                                                            Navegar
                                                        </Button>
                                                    </div>
                                                </div>
                                            </Popup>
                                        </Marker>
                                    )
                                })}
                            </MapContainer>
                        )}
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                        <div className="border-b border-subtle px-4 py-3">
                            <h2 className="text-sm font-semibold text-surface-900">
                                OS geolocalizadas ({mappedOrders.length})
                            </h2>
                            <p className="mt-1 text-xs text-surface-500">
                                Priorize atendimento e navegue direto para a OS.
                            </p>
                        </div>
                        <div className="max-h-[360px] divide-y divide-subtle overflow-y-auto">
                            {mappedOrders.length === 0 ? (
                                <p className="px-4 py-8 text-sm text-surface-400">
                                    Nenhuma OS com coordenadas neste filtro.
                                </p>
                            ) : (
                                mappedOrders.map((item) => {
                                    const statusEntry = getStatusEntry(workOrderStatus, getStatusKey(item.workOrder.status))
                                    const priorityEntry = priorityConfig[item.workOrder.priority]

                                    return (
                                        <button
                                            key={item.workOrder.id}
                                            onClick={() => handleSelectOrder(item)}
                                            className="w-full px-4 py-3 text-left transition-colors hover:bg-surface-50"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="text-sm font-semibold text-surface-900">
                                                        {getWorkOrderIdentifier(item.workOrder)}
                                                    </p>
                                                    <p className="truncate text-xs text-surface-500">
                                                        {item.workOrder.customer?.name ?? 'Sem cliente'}
                                                    </p>
                                                </div>
                                                <Badge variant={statusEntry.variant}>{statusEntry.label}</Badge>
                                            </div>
                                            <p className="mt-2 flex items-center gap-1 text-xs text-surface-500">
                                                <MapPin className="h-3.5 w-3.5" />
                                                {item.locationLabel}
                                            </p>
                                            <div className="mt-2 flex items-center gap-2 text-xs">
                                                <span className="rounded-full bg-surface-100 px-2 py-0.5 text-surface-600">
                                                    {item.source === 'arrival' ? 'GPS de chegada' : 'Cadastro do cliente'}
                                                </span>
                                                {priorityEntry && (
                                                    <span className="rounded-full bg-surface-100 px-2 py-0.5 text-surface-600">
                                                        Prioridade {priorityEntry.label}
                                                    </span>
                                                )}
                                            </div>
                                        </button>
                                    )
                                })
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                        <div className="border-b border-subtle px-4 py-3">
                            <h2 className="text-sm font-semibold text-surface-900">
                                OS sem coordenadas ({missingLocationOrders.length})
                            </h2>
                            <p className="mt-1 text-xs text-surface-500">
                                Essas OS nao aparecem no mapa e dependem de ajuste de cadastro ou check-in.
                            </p>
                        </div>
                        <div className="max-h-[280px] divide-y divide-subtle overflow-y-auto">
                            {missingLocationOrders.length === 0 ? (
                                <p className="px-4 py-8 text-sm text-surface-400">
                                    Nenhuma pendencia de geolocalizacao neste recorte.
                                </p>
                            ) : (
                                missingLocationOrders.map((workOrder) => (
                                    <div key={workOrder.id} className="px-4 py-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-sm font-semibold text-surface-900">
                                                    {getWorkOrderIdentifier(workOrder)}
                                                </p>
                                                <p className="truncate text-xs text-surface-500">
                                                    {workOrder.customer?.name ?? 'Sem cliente'}
                                                </p>
                                            </div>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => navigate(`/os/${workOrder.id}`)}
                                                icon={<ExternalLink className="h-4 w-4" />}
                                            >
                                                Abrir
                                            </Button>
                                        </div>
                                        <p className="mt-2 text-xs text-surface-500">
                                            Falta latitude/longitude do cliente e GPS de chegada.
                                        </p>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {selectedItem && (
                        <div className="rounded-xl border border-brand-200 bg-brand-50/60 p-4 shadow-card">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold text-surface-900">
                                        OS selecionada: {getWorkOrderIdentifier(selectedItem.workOrder)}
                                    </p>
                                    <p className="mt-1 text-xs text-surface-600">
                                        {selectedItem.workOrder.customer?.name ?? 'Sem cliente'} • {selectedItem.locationLabel}
                                    </p>
                                </div>
                                <Wrench className="h-5 w-5 text-brand-600" />
                            </div>
                            <div className="mt-3 flex gap-2">
                                <Button size="sm" onClick={() => navigate(`/os/${selectedItem.workOrder.id}`)}>
                                    Ir para a OS
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => window.open(selectedItem.externalMapUrl, '_blank', 'noopener,noreferrer')}
                                    icon={<Navigation className="h-4 w-4" />}
                                >
                                    Abrir rota
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    )
}

export default WorkOrderMapPage
