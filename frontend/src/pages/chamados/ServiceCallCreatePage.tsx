import { useEffect, useMemo, useRef, useState, useCallback } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { ArrowLeft, Save, Wrench, MapPin, ExternalLink } from 'lucide-react'
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

export function ServiceCallCreatePage() {
    const navigate = useNavigate()
    const [searchParams] = useSearchParams()
    const customerIdFromUrl = searchParams.get('customer_id')
    const { hasPermission, hasRole } = useAuthStore()

    const canAssign = hasRole('super_admin') || hasPermission('service_calls.service_call.assign')
    const canViewEquipment = hasRole('super_admin') || hasPermission('equipments.equipment.view')

    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
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
        equipment_ids: [] as number[],
    })

    const { data: ibgeStates } = useIbgeStates()
    const { data: ibgeCities } = useIbgeCities(form.state)

    const { data: preselectedCustomer } = useQuery({
        queryKey: ['customer', customerIdFromUrl],
        queryFn: () => api.get(`/customers/${customerIdFromUrl}`).then(unwrapServiceCallPayload),
        enabled: !!customerIdFromUrl,
    })


    const initialCustomerOption = useMemo(() => {
        if (!preselectedCustomer) return null
        const c = preselectedCustomer as Customer
        return { id: c.id, label: c.name, subLabel: [c.document, c.address_city].filter(Boolean).join(' — '), value: c }
    }, [preselectedCustomer])

    const applyCustomerData = useCallback((customer: Customer) => {
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
            equipment_ids: [],
        }))
    }, [])

    const appliedPreselection = useRef(false)
    useEffect(() => {
        if (customerIdFromUrl && preselectedCustomer && !appliedPreselection.current) {
            appliedPreselection.current = true
            applyCustomerData(preselectedCustomer as Customer)
        }
    }, [customerIdFromUrl, preselectedCustomer, applyCustomerData])

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
        queryKey: ['service-call-create-equipments', form.customer_id],
        queryFn: () =>
            api
                .get('/equipments', {
                    params: { customer_id: form.customer_id, per_page: 100 },
                })
                .then((r) => safeArray<Equipment>(r.data)),
        enabled: canViewEquipment && !!form.customer_id,
    })

    const equipments = equipmentsRes ?? []

    const firstError = (field: string) => fieldErrors[field]?.[0]

    const mutation = useMutation({
        mutationFn: () => {
            const payload: Record<string, unknown> = {
                customer_id: Number(form.customer_id),
                priority: form.priority,
                observations: form.observations || undefined,
                address: form.address || undefined,
                city: form.city || undefined,
                state: form.state || undefined,
                latitude: form.latitude ? Number(form.latitude) : undefined,
                longitude: form.longitude ? Number(form.longitude) : undefined,
                google_maps_link: form.google_maps_link || undefined,
                scheduled_date: form.scheduled_date || undefined,
                equipment_ids: form.equipment_ids.length > 0 ? form.equipment_ids : undefined,
            }

            if (canAssign) {
                payload.technician_id = form.technician_id ? Number(form.technician_id) : undefined
                payload.driver_id = form.driver_id ? Number(form.driver_id) : undefined
            }

            return api.post('/service-calls', payload)
        },
        onSuccess: (response) => {
            toast.success('Chamado criado com sucesso')
            const serviceCallId = response?.data?.data?.id ?? response?.data?.id
            if (serviceCallId) {
                navigate(`/chamados/${serviceCallId}`)
            } else {
                navigate('/chamados')
            }
        },
        onError: (error: AxiosError<{ message?: string; errors?: FieldErrors }>) => {
            setFieldErrors(error?.response?.data?.errors ?? {})
            toast.error(error?.response?.data?.message || 'Erro ao criar chamado')
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
        setForm((previous) => {
            const exists = previous.equipment_ids.includes(equipmentId)
            return {
                ...previous,
                equipment_ids: exists
                    ? previous.equipment_ids.filter((id) => id !== equipmentId)
                    : [...previous.equipment_ids, equipmentId],
            }
        })
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

    return (
        <div className="mx-auto max-w-5xl space-y-6 pb-12">
            <div className="flex items-center gap-3">
                <Button variant="ghost" size="sm" onClick={() => navigate('/chamados')}>
                    <ArrowLeft className="h-4 w-4" />
                </Button>
                <div>
                    <h1 className="text-xl font-bold text-surface-900">Novo Chamado</h1>
                    <p className="text-sm text-surface-500">Preencha os dados para abrir um novo atendimento técnico.</p>
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
                                initialCustomer={preselectedCustomer as CustomerAsyncSelectItem | null}
                                placeholder="Buscar cliente por nome, documento, telefone ou e-mail..."
                                onChange={(customer) => {
                                    if (customer) {
                                        applyCustomerData(customer as Customer)
                                    } else {
                                        setForm((prev) => ({ ...prev, customer_id: '', equipment_ids: [] }))
                                    }
                                }}
                            />
                            {firstError('customer_id') && (
                                <p className="mt-1 text-xs text-red-600">{firstError('customer_id')}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="sc-priority" className="mb-1 block text-xs font-medium text-surface-500">Prioridade</label>
                            <select
                                id="sc-priority"
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
                        <label htmlFor="sc-scheduled-date" className="mb-1 block text-xs font-medium text-surface-500">Data agendada</label>
                        <input
                            id="sc-scheduled-date"
                            type="datetime-local"
                            value={form.scheduled_date}
                            onChange={(e) => setForm((prev) => ({ ...prev, scheduled_date: e.target.value }))}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                        />
                        {firstError('scheduled_date') && (
                            <p className="mt-1 text-xs text-red-600">{firstError('scheduled_date')}</p>
                        )}
                    </div>

                    <div>
                        <label htmlFor="sc-address" className="mb-1 block text-xs font-medium text-surface-500">Endereço</label>
                        <input
                            id="sc-address"
                            type="text"
                            value={form.address}
                            onChange={(e) => setForm((prev) => ({ ...prev, address: e.target.value }))}
                            placeholder="Rua, número, bairro"
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label htmlFor="sc-state" className="mb-1 block text-xs font-medium text-surface-500">UF</label>
                            <select
                                id="sc-state"
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
                            <label htmlFor="sc-city" className="mb-1 block text-xs font-medium text-surface-500">Cidade</label>
                            <select
                                id="sc-city"
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
                        <label htmlFor="sc-maps-link" className="mb-1 block text-xs font-medium text-surface-500 flex items-center gap-1">
                            <MapPin className="h-3.5 w-3.5" />
                            Link do Google Maps
                        </label>
                        <div className="flex gap-2">
                            <input
                                id="sc-maps-link"
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
                            <label htmlFor="sc-latitude" className="mb-1 block text-xs font-medium text-surface-500">Latitude</label>
                            <input
                                id="sc-latitude"
                                type="number"
                                step="0.000001"
                                value={form.latitude}
                                onChange={(e) => setForm((prev) => ({ ...prev, latitude: e.target.value }))}
                                placeholder="-15.000000"
                                className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label htmlFor="sc-longitude" className="mb-1 block text-xs font-medium text-surface-500">Longitude</label>
                            <input
                                id="sc-longitude"
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
                                <label htmlFor="sc-technician" className="mb-1 block text-xs font-medium text-surface-500">Técnico</label>
                                <select
                                    id="sc-technician"
                                    value={form.technician_id}
                                    onChange={(e) => setForm((prev) => ({ ...prev, technician_id: e.target.value }))}
                                    className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                                >
                                    <option value="">Não atribuir</option>
                                    {(technicians || []).map((tech) => (
                                        <option key={tech.id} value={tech.id}>{tech.name}</option>
                                    ))}
                                </select>
                                {firstError('technician_id') && (
                                    <p className="mt-1 text-xs text-red-600">{firstError('technician_id')}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="sc-driver" className="mb-1 block text-xs font-medium text-surface-500">Motorista</label>
                                <select
                                    id="sc-driver"
                                    value={form.driver_id}
                                    onChange={(e) => setForm((prev) => ({ ...prev, driver_id: e.target.value }))}
                                    className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                                >
                                    <option value="">Não atribuir</option>
                                    {(drivers || []).map((driver) => (
                                        <option key={driver.id} value={driver.id}>{driver.name}</option>
                                    ))}
                                </select>
                                {firstError('driver_id') && (
                                    <p className="mt-1 text-xs text-red-600">{firstError('driver_id')}</p>
                                )}
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
                        <label htmlFor="sc-observations" className="mb-1 block text-xs font-medium text-surface-500">Observações</label>
                        <textarea
                            id="sc-observations"
                            value={form.observations}
                            onChange={(e) => setForm((prev) => ({ ...prev, observations: e.target.value }))}
                            rows={5}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm"
                            placeholder="Descreva o atendimento solicitado..."
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
                <Button variant="outline" onClick={() => navigate('/chamados')}>
                    Cancelar
                </Button>
                <Button loading={mutation.isPending} onClick={handleSubmit}>
                    <Save className="mr-1 h-4 w-4" /> Criar chamado
                </Button>
            </div>
        </div>
    )
}
