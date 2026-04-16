import { useEffect, useMemo, useState, useCallback } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { ArrowLeft, Save, Wrench, AlertCircle, MapPin, ExternalLink } from 'lucide-react'
import api from '@/lib/api'
import { parseCityStateFromAddress } from '@/lib/utils'
import { unwrapServiceCallAssignees, unwrapServiceCallPayload } from '@/lib/service-call-normalizers'
import { safeArray } from '@/lib/safe-array'
import { Button } from '@/components/ui/button'
import { CustomerAsyncSelect, type CustomerAsyncSelectItem } from '@/components/common/CustomerAsyncSelect'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { useIbgeStates, useIbgeCities } from '@/hooks/useIbge'

type FieldErrors = Record<string, string[]>

interface Customer {
    id: number
    name: string
    document?: string | null
    address_street?: string | null
    address_number?: string | null
    address_neighborhood?: string | null
    address_city?: string | null
    address_state?: string | null
    latitude?: number | null
    longitude?: number | null
    google_maps_link?: string | null
}

interface Equipment {
    id: number
    code?: string | null
    tag?: string | null
    brand?: string | null
    model?: string | null
    serial_number?: string | null
}

interface Assignee {
    id: number
    name: string
    email?: string | null
}

function parseGoogleMapsLink(url: string): { lat?: number; lng?: number } | null {
    if (!url) return null
    try {
        const qMatch = url.match(/[?&]q=(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/)
        if (qMatch) return { lat: parseFloat(qMatch[1]), lng: parseFloat(qMatch[2]) }

        const atMatch = url.match(/@(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/)
        if (atMatch) return { lat: parseFloat(atMatch[1]), lng: parseFloat(atMatch[2]) }

        const llMatch = url.match(/[?&]ll=(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/)
        if (llMatch) return { lat: parseFloat(llMatch[1]), lng: parseFloat(llMatch[2]) }

        const placeMatch = url.match(/\/maps\/place\/[^/]+\/(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/)
        if (placeMatch) return { lat: parseFloat(placeMatch[1]), lng: parseFloat(placeMatch[2]) }
    } catch {
        // Ignore parse errors
    }
    return null
}

export function ServiceCallEditPage() {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()

    const canAssign = hasRole('super_admin') || hasPermission('service_calls.service_call.assign')
    const canViewEquipment = hasRole('super_admin') || hasPermission('equipments.equipment.view')

    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
    const [initialized, setInitialized] = useState(false)
    const [form, setForm] = useState({
        customer_id: '',
        priority: 'normal',
        technician_id: '',
        driver_id: '',
        scheduled_date: '',
        address: '',
        city: '',
        state: '',
        latitude: '',
        longitude: '',
        google_maps_link: '',
        observations: '',
        resolution_notes: '',
        equipment_ids: [] as number[],
    })

    const { data: ibgeStates } = useIbgeStates()
    const { data: ibgeCities } = useIbgeCities(form.state)

    const { data: serviceCall, isLoading: loadingCall, isError: callError } = useQuery({
        queryKey: ['service-call', id],
        queryFn: () => api.get(`/service-calls/${id}`).then(unwrapServiceCallPayload),
        enabled: !!id,
    })

    useEffect(() => {
        if (!serviceCall || initialized) return
        setForm({
            customer_id: serviceCall.customer_id ? String(serviceCall.customer_id) : '',
            priority: serviceCall.priority || 'normal',
            technician_id: serviceCall.technician_id ? String(serviceCall.technician_id) : '',
            driver_id: serviceCall.driver_id ? String(serviceCall.driver_id) : '',
            scheduled_date: serviceCall.scheduled_date
                ? new Date(new Date(serviceCall.scheduled_date).getTime() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16)
                : '',
            address: serviceCall.address || '',
            city: serviceCall.city || '',
            state: serviceCall.state || '',
            latitude: serviceCall.latitude ? String(serviceCall.latitude) : '',
            longitude: serviceCall.longitude ? String(serviceCall.longitude) : '',
            google_maps_link: serviceCall.google_maps_link || '',
            observations: serviceCall.observations || '',
            resolution_notes: serviceCall.resolution_notes || '',
            equipment_ids: (serviceCall.equipments || []).map((e: { id: number }) => e.id) ?? [],
        })
        setInitialized(true)
    }, [serviceCall, initialized])

    const { data: currentCustomer } = useQuery({
        queryKey: ['customer', serviceCall?.customer_id],
        queryFn: () => api.get(`/customers/${serviceCall!.customer_id}`).then(unwrapServiceCallPayload),
        enabled: !!serviceCall?.customer_id,
    })

    const initialCustomerOption = useMemo(() => {
        if (!currentCustomer) return null
        const c = currentCustomer as Customer
        return { id: c.id, label: c.name, subLabel: [c.document, c.address_city].filter(Boolean).join(' — '), value: c }
    }, [currentCustomer])

    const { data: assigneesRes, isError: assigneesError } = useQuery({
        queryKey: ['service-call-assignees'],
        queryFn: () => api.get('/service-calls-assignees').then(unwrapServiceCallAssignees),
        enabled: canAssign,
    })
    const technicians: Assignee[] = assigneesRes?.technicians ?? []
    const drivers: Assignee[] = assigneesRes?.drivers ?? []

    const {
        data: equipmentsRes,
        isLoading: equipmentsLoading,
        isError: equipmentsError,
    } = useQuery({
        queryKey: ['service-call-edit-equipments', form.customer_id],
        queryFn: () =>
            api.get('/equipments', { params: { customer_id: form.customer_id, per_page: 100 } }).then((r) => safeArray<Equipment>(r.data)),
        enabled: canViewEquipment && !!form.customer_id,
    })
    const equipments = equipmentsRes ?? []


    const firstError = (field: string) => fieldErrors[field]?.[0]

    const applyCustomerData = useCallback((customer: Customer, resetEquipments = true) => {
        const parsed = (!customer.address_city || !customer.address_state)
            ? parseCityStateFromAddress(customer.address_street)
            : {}

        setForm((prev) => ({
            ...prev,
            customer_id: String(customer.id),
            address: [customer.address_street, customer.address_number, customer.address_neighborhood]
                .filter(Boolean)
                .join(', ') || prev.address,
            city: customer.address_city || parsed.city || prev.city,
            state: customer.address_state || parsed.state || prev.state,
            latitude: customer.latitude ? String(customer.latitude) : prev.latitude,
            longitude: customer.longitude ? String(customer.longitude) : prev.longitude,
            google_maps_link: customer.google_maps_link || prev.google_maps_link,
            equipment_ids: resetEquipments ? [] : prev.equipment_ids,
        }))
    }, [])

    const mutation = useMutation({
        mutationFn: () => {
            const payload: Record<string, unknown> = {
                customer_id: Number(form.customer_id),
                priority: form.priority,
                observations: form.observations || null,
                resolution_notes: form.resolution_notes || null,
                address: form.address || null,
                city: form.city || null,
                state: form.state || null,
                latitude: form.latitude ? Number(form.latitude) : null,
                longitude: form.longitude ? Number(form.longitude) : null,
                google_maps_link: form.google_maps_link || null,
                scheduled_date: form.scheduled_date || null,
                equipment_ids: form.equipment_ids,
            }

            if (canAssign) {
                payload.technician_id = form.technician_id ? Number(form.technician_id) : null
                payload.driver_id = form.driver_id ? Number(form.driver_id) : null
            }

            return api.put(`/service-calls/${id}`, payload)
        },
        onSuccess: () => {
            toast.success('Chamado atualizado com sucesso')
            queryClient.invalidateQueries({ queryKey: ['service-call', id] })
            queryClient.invalidateQueries({ queryKey: ['service-calls'] })
            queryClient.invalidateQueries({ queryKey: ['service-calls-summary'] })
            navigate(`/chamados/${id}`)
        },
        onError: (error: AxiosError<{ message?: string; errors?: FieldErrors }>) => {
            setFieldErrors(error?.response?.data?.errors ?? {})
            toast.error(error?.response?.data?.message || 'Erro ao atualizar chamado')
        },
    })

    const handleGoogleMapsLinkChange = (link: string) => {
        setForm((prev) => ({ ...prev, google_maps_link: link }))

        const parsed = parseGoogleMapsLink(link)
        if (parsed) {
            setForm((prev) => ({
                ...prev,
                google_maps_link: link,
                ...(parsed.lat != null ? { latitude: String(parsed.lat) } : {}),
                ...(parsed.lng != null ? { longitude: String(parsed.lng) } : {}),
            }))
            toast.success('Coordenadas extraídas do link do Google Maps')
        }
    }

    const handleStateChange = (newState: string) => {
        setForm((prev) => ({
            ...prev,
            state: newState,
            city: newState !== prev.state ? '' : prev.city,
        }))
    }

    const toggleEquipment = (equipmentId: number) => {
        setForm((prev) => ({
            ...prev,
            equipment_ids: prev.equipment_ids.includes(equipmentId)
                ? prev.equipment_ids.filter((eqId) => eqId !== equipmentId)
                : [...prev.equipment_ids, equipmentId],
        }))
    }

    const handleSubmit = () => {
        setFieldErrors({})
        if (!form.customer_id) {
            setFieldErrors({ customer_id: ['Selecione um cliente'] })
            toast.error('Selecione um cliente')
            return
        }
        mutation.mutate()
    }

    const googleMapsUrl = form.google_maps_link
        || (form.latitude && form.longitude
            ? `https://www.google.com/maps?q=${form.latitude},${form.longitude}`
            : null)

    if (loadingCall) {
        return (
            <div className="mx-auto max-w-5xl space-y-6 animate-pulse">
                <div className="h-8 bg-surface-200 rounded w-64" />
                <div className="bg-surface-0 rounded-xl p-6 space-y-4">
                    <div className="h-6 bg-surface-200 rounded w-48" />
                    <div className="h-4 bg-surface-200 rounded w-full" />
                </div>
            </div>
        )
    }

    if (callError || !serviceCall) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-surface-500">
                <AlertCircle className="w-12 h-12 mb-4 opacity-30" />
                <p className="text-lg font-medium">Chamado não encontrado</p>
                <Button variant="outline" className="mt-4" onClick={() => navigate('/chamados')}>
                    <ArrowLeft className="w-4 h-4 mr-1" /> Voltar
                </Button>
            </div>
        )
    }

    return (
        <div className="mx-auto max-w-5xl space-y-6 pb-12">
            <div className="flex items-center gap-3">
                <Button variant="ghost" size="sm" onClick={() => navigate(`/chamados/${id}`)}>
                    <ArrowLeft className="h-4 w-4" />
                </Button>
                <div>
                    <h1 className="text-xl font-bold text-surface-900">
                        Editar Chamado {serviceCall.call_number}
                    </h1>
                    <p className="text-sm text-surface-500">Atualize os dados do chamado técnico.</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Cliente e prioridade */}
                <div className="space-y-4 rounded-xl border border-default bg-surface-0 p-5 shadow-card lg:col-span-2">
                    <h2 className="text-sm font-semibold text-surface-900">Cliente e prioridade</h2>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="md:col-span-2">
                            <CustomerAsyncSelect
                                label="Cliente"
                                customerId={form.customer_id ? Number(form.customer_id) : null}
                                initialCustomer={currentCustomer as CustomerAsyncSelectItem | null}
                                placeholder="Buscar cliente por nome, documento, telefone ou e-mail..."
                                onChange={(customer) => {
                                    if (customer) {
                                        applyCustomerData(customer as Customer)
                                    } else {
                                        setForm((prev) => ({ ...prev, customer_id: '', equipment_ids: [] }))
                                    }
                                }}
                            />
                            {firstError('customer_id') && <p className="mt-1 text-xs text-red-600">{firstError('customer_id')}</p>}
                        </div>

                        <div>
                            <label htmlFor="sce-priority" className="mb-1 block text-xs font-medium text-surface-500">Prioridade</label>
                            <select
                                id="sce-priority"
                                value={form.priority}
                                onChange={(e) => setForm((prev) => ({ ...prev, priority: e.target.value }))}
                                className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            >
                                <option value="low">Baixa</option>
                                <option value="normal">Normal</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Local e horário */}
                <div className="space-y-4 rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                    <h2 className="text-sm font-semibold text-surface-900">Local e horário</h2>

                    <div>
                        <label htmlFor="sce-scheduled-date" className="mb-1 block text-xs font-medium text-surface-500">Data agendada</label>
                        <input
                            id="sce-scheduled-date"
                            type="datetime-local"
                            value={form.scheduled_date}
                            onChange={(e) => setForm((prev) => ({ ...prev, scheduled_date: e.target.value }))}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                        />
                        {firstError('scheduled_date') && <p className="mt-1 text-xs text-red-600">{firstError('scheduled_date')}</p>}
                    </div>

                    <div>
                        <label htmlFor="sce-address" className="mb-1 block text-xs font-medium text-surface-500">Endereço</label>
                        <input
                            id="sce-address"
                            type="text"
                            value={form.address}
                            onChange={(e) => setForm((prev) => ({ ...prev, address: e.target.value }))}
                            placeholder="Rua, número, bairro"
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label htmlFor="sce-state" className="mb-1 block text-xs font-medium text-surface-500">UF</label>
                            <select
                                id="sce-state"
                                value={form.state}
                                onChange={(e) => handleStateChange(e.target.value)}
                                className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            >
                                <option value="">Selecione</option>
                                {(ibgeStates || []).map((s) => (
                                    <option key={s.abbr} value={s.abbr}>
                                        {s.abbr} — {s.name}
                                    </option>
                                ))}
                            </select>
                            {firstError('state') && <p className="mt-1 text-xs text-red-600">{firstError('state')}</p>}
                        </div>
                        <div>
                            <label htmlFor="sce-city" className="mb-1 block text-xs font-medium text-surface-500">Cidade</label>
                            <select
                                id="sce-city"
                                value={form.city}
                                onChange={(e) => setForm((prev) => ({ ...prev, city: e.target.value }))}
                                disabled={!form.state}
                                className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            >
                                <option value="">{form.state ? 'Selecione a cidade' : 'Selecione o UF primeiro'}</option>
                                {form.city && !(ibgeCities || []).some((c) => c.name === form.city) && (
                                    <option value={form.city}>{form.city}</option>
                                )}
                                {(ibgeCities || []).map((c) => (
                                    <option key={c.id} value={c.name}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div>
                        <label htmlFor="sce-maps-link" className="mb-1 block text-xs font-medium text-surface-500 flex items-center gap-1">
                            <MapPin className="h-3.5 w-3.5" />
                            Link do Google Maps
                        </label>
                        <div className="flex gap-2">
                            <input
                                id="sce-maps-link"
                                type="text"
                                value={form.google_maps_link}
                                onChange={(e) => handleGoogleMapsLinkChange(e.target.value)}
                                placeholder="Cole aqui o link do Google Maps, Waze, etc."
                                className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            />
                            {googleMapsUrl && (
                                <a
                                    href={googleMapsUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-1 rounded-lg border border-default bg-surface-0 px-3 py-2 text-xs text-brand-600 hover:bg-brand-50 whitespace-nowrap"
                                    title="Abrir no Google Maps"
                                >
                                    <ExternalLink className="h-3.5 w-3.5" />
                                    Abrir
                                </a>
                            )}
                        </div>
                        <p className="mt-1 text-xs text-surface-400">
                            As coordenadas (lat/lng) serão extraídas automaticamente do link.
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label htmlFor="sce-latitude" className="mb-1 block text-xs font-medium text-surface-500">Latitude</label>
                            <input
                                id="sce-latitude"
                                type="number"
                                step="0.000001"
                                value={form.latitude}
                                onChange={(e) => setForm((prev) => ({ ...prev, latitude: e.target.value }))}
                                placeholder="-15.000000"
                                className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label htmlFor="sce-longitude" className="mb-1 block text-xs font-medium text-surface-500">Longitude</label>
                            <input
                                id="sce-longitude"
                                type="number"
                                step="0.000001"
                                value={form.longitude}
                                onChange={(e) => setForm((prev) => ({ ...prev, longitude: e.target.value }))}
                                placeholder="-56.000000"
                                className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>

                {/* Equipe e observações */}
                <div className="space-y-4 rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                    <h2 className="text-sm font-semibold text-surface-900">Equipe e observações</h2>

                    {canAssign ? (
                        <>
                            <div>
                                <label htmlFor="sce-technician" className="mb-1 block text-xs font-medium text-surface-500">Técnico</label>
                                <select
                                    id="sce-technician"
                                    value={form.technician_id}
                                    onChange={(e) => setForm((prev) => ({ ...prev, technician_id: e.target.value }))}
                                    className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                                >
                                    <option value="">Não atribuir</option>
                                    {(technicians || []).map((tech) => (
                                        <option key={tech.id} value={tech.id}>{tech.name}</option>
                                    ))}
                                </select>
                                {firstError('technician_id') && <p className="mt-1 text-xs text-red-600">{firstError('technician_id')}</p>}
                            </div>

                            <div>
                                <label htmlFor="sce-driver" className="mb-1 block text-xs font-medium text-surface-500">Motorista</label>
                                <select
                                    id="sce-driver"
                                    value={form.driver_id}
                                    onChange={(e) => setForm((prev) => ({ ...prev, driver_id: e.target.value }))}
                                    className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                                >
                                    <option value="">Não atribuir</option>
                                    {(drivers || []).map((driver) => (
                                        <option key={driver.id} value={driver.id}>{driver.name}</option>
                                    ))}
                                </select>
                                {firstError('driver_id') && <p className="mt-1 text-xs text-red-600">{firstError('driver_id')}</p>}
                            </div>
                            {assigneesError && (
                                <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                                    Não foi possível carregar técnicos e motoristas.
                                </p>
                            )}
                        </>
                    ) : (
                        <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                            Seu perfil não possui permissão para atribuir técnico no cadastro.
                        </p>
                    )}

                    <div>
                        <label htmlFor="sce-observations" className="mb-1 block text-xs font-medium text-surface-500">Observações</label>
                        <textarea
                            id="sce-observations"
                            value={form.observations}
                            onChange={(e) => setForm((prev) => ({ ...prev, observations: e.target.value }))}
                            rows={4}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            placeholder="Descreva o atendimento solicitado..."
                        />
                    </div>

                    <div>
                        <label htmlFor="sce-resolution" className="mb-1 block text-xs font-medium text-surface-500">Notas de resolução</label>
                        <textarea
                            id="sce-resolution"
                            value={form.resolution_notes}
                            onChange={(e) => setForm((prev) => ({ ...prev, resolution_notes: e.target.value }))}
                            rows={3}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            placeholder="Notas de resolução (opcional)..."
                        />
                    </div>
                </div>

                {/* Equipamentos */}
                {canViewEquipment && (
                    <div className="space-y-3 rounded-xl border border-default bg-surface-0 p-5 shadow-card lg:col-span-2">
                        <h2 className="text-sm font-semibold text-surface-900 flex items-center gap-2">
                            <Wrench className="h-4 w-4" /> Equipamentos vinculados
                        </h2>

                        {!form.customer_id ? (
                            <p className="text-sm text-surface-500">Selecione um cliente para listar os equipamentos.</p>
                        ) : equipmentsLoading ? (
                            <p className="text-sm text-surface-500">Carregando equipamentos...</p>
                        ) : equipmentsError ? (
                            <p className="text-sm text-red-600">Não foi possível carregar os equipamentos.</p>
                        ) : equipments.length === 0 ? (
                            <p className="text-sm text-surface-500">Nenhum equipamento encontrado para este cliente.</p>
                        ) : (
                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                                {equipments.map((equipment) => {
                                    const checked = form.equipment_ids.includes(equipment.id)
                                    return (
                                        <label
                                            key={equipment.id}
                                            className="flex items-center gap-3 rounded-lg border border-default px-3 py-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={() => toggleEquipment(equipment.id)}
                                            />
                                            <span>
                                                {equipment.tag || equipment.code || equipment.model || `Equipamento #${equipment.id}`}
                                                {equipment.serial_number ? ` (S/N: ${equipment.serial_number})` : ''}
                                            </span>
                                        </label>
                                    )
                                })}
                            </div>
                        )}
                    </div>
                )}
            </div>

            <div className="flex justify-end gap-3">
                <Button variant="outline" onClick={() => navigate(`/chamados/${id}`)}>
                    Cancelar
                </Button>
                <Button loading={mutation.isPending} onClick={handleSubmit}>
                    <Save className="mr-1 h-4 w-4" /> Salvar alterações
                </Button>
            </div>
        </div>
    )
}
