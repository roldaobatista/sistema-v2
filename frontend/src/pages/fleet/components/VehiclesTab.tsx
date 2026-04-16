import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Search, Plus, Eye, User, Trash2, Truck, X } from 'lucide-react'
import api from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { IconButton } from '@/components/ui/iconbutton'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/ui/modal'
import { useAuthStore } from '@/stores/auth-store'

interface ApiError {
  response?: {
    data?: {
      message?: string
    }
  }
}

interface LookupItem {
  id: number
  name: string
  slug?: string
}

interface LookupOption {
  value: string
  label: string
}

interface FleetVehicleItem {
  id: number
  plate?: string
  license_plate?: string
  brand?: string
  model?: string
  year?: number | string
  color?: string
  type?: string
  fuel_type?: string
  status?: string
  odometer_km?: number | string
  current_mileage_km?: number
  renavam?: string
  chassis?: string
  crlv_expiry?: string | null
  insurance_expiry?: string | null
  next_maintenance?: string | null
  purchase_value?: number | string
  notes?: string | null
  cost_per_km?: number | string
  assignedUser?: { name?: string | null } | null
  assigned_driver?: { name?: string | null } | null
}

interface FleetVehiclesResponse {
  data: FleetVehicleItem[]
  current_page?: number
  last_page?: number
}

const VEHICLE_TYPE_FALLBACK: LookupOption[] = [
  { value: 'car', label: 'Carro' },
  { value: 'truck', label: 'Caminhão' },
  { value: 'motorcycle', label: 'Moto' },
  { value: 'van', label: 'Van' },
]

const FUEL_TYPE_FALLBACK: LookupOption[] = [
  { value: 'flex', label: 'Flex' },
  { value: 'diesel', label: 'Diesel' },
  { value: 'gasoline', label: 'Gasolina' },
  { value: 'electric', label: 'Elétrico' },
  { value: 'ethanol', label: 'Etanol' },
]

const VEHICLE_STATUS_FALLBACK: LookupOption[] = [
  { value: 'active', label: 'Ativo' },
  { value: 'maintenance', label: 'Manutenção' },
  { value: 'inactive', label: 'Inativo' },
]

const emptyForm = {
  plate: '', brand: '', model: '', year: '', color: '', type: '', fuel_type: '',
  odometer_km: '', renavam: '', chassis: '', crlv_expiry: '', insurance_expiry: '',
  next_maintenance: '', purchase_value: '', status: 'active', notes: '',
}

function toDateStr(val: string | null | undefined): string {
  if (!val) return ''
  const d = val.split('T')[0]
  return d ?? ''
}

function getApiErrorMessage(error: unknown, fallback: string): string {
  const apiError = error as ApiError
  return apiError?.response?.data?.message ?? fallback
}

function buildLookupOptions(items: LookupItem[], fallback: LookupOption[]): LookupOption[] {
  const options = [...fallback]
  items.forEach((item) => {
    const value = item.slug ?? item.name
    if (!options.some((option) => option.value === value)) {
      options.push({ value, label: item.name })
    }
  })
  return options
}

function buildLookupLabelMap(items: LookupItem[], fallback: LookupOption[]): Record<string, string> {
  const labelMap = Object.fromEntries(fallback.map((option) => [option.value, option.label]))
  items.forEach((item) => {
    const value = item.slug ?? item.name
    labelMap[value] = item.name
    labelMap[item.name] = item.name
  })
  return labelMap
}

export function VehiclesTab() {
  const queryClient = useQueryClient()
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('fleet.vehicle.create')
  const canUpdate = hasPermission('fleet.vehicle.update')
  const canDelete = hasPermission('fleet.vehicle.delete')

  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [showForm, setShowForm] = useState(false)
  const [editingVehicle, setEditingVehicle] = useState<{ id: number } | null>(null)
  const [formData, setFormData] = useState(emptyForm)

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/fleet/vehicles/${id}`),
    onSuccess: () => {
      toast.success('Removido com sucesso')
      queryClient.invalidateQueries({ queryKey: ['fleet-vehicles'] })
    },
    onError: (error: unknown) => {
      toast.error(getApiErrorMessage(error, 'Erro ao remover'))
    },
  })

  const createMutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.post('/fleet/vehicles', payload),
    onSuccess: () => {
      toast.success('Veículo cadastrado com sucesso')
      queryClient.invalidateQueries({ queryKey: ['fleet-vehicles'] })
      closeForm()
    },
    onError: (error: unknown) => {
      toast.error(getApiErrorMessage(error, 'Erro ao cadastrar veículo'))
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      api.put(`/fleet/vehicles/${id}`, payload),
    onSuccess: () => {
      toast.success('Veículo atualizado com sucesso')
      queryClient.invalidateQueries({ queryKey: ['fleet-vehicles'] })
      closeForm()
    },
    onError: (error: unknown) => {
      toast.error(getApiErrorMessage(error, 'Erro ao atualizar veículo'))
    },
  })

  const { data: vehicleTypeItems = [] } = useQuery({
    queryKey: ['lookups', 'fleet-vehicle-types'],
    queryFn: async () => {
      const { data } = await api.get('/lookups/fleet-vehicle-types')
      const payload = data?.data ?? data
      return Array.isArray(payload) ? payload : []
    },
    staleTime: 5 * 60_000,
  })

  const { data: fuelTypeItems = [] } = useQuery({
    queryKey: ['lookups', 'fleet-fuel-types'],
    queryFn: async () => {
      const { data } = await api.get('/lookups/fleet-fuel-types')
      const payload = data?.data ?? data
      return Array.isArray(payload) ? payload : []
    },
    staleTime: 5 * 60_000,
  })

  const { data: vehicleStatusItems = [] } = useQuery({
    queryKey: ['lookups', 'fleet-vehicle-statuses'],
    queryFn: async () => {
      const { data } = await api.get('/lookups/fleet-vehicle-statuses')
      const payload = data?.data ?? data
      return Array.isArray(payload) ? payload : []
    },
    staleTime: 5 * 60_000,
  })

  function closeForm() {
    setShowForm(false)
    setEditingVehicle(null)
    setFormData(emptyForm)
  }

  function openCreate() {
    setEditingVehicle(null)
    setFormData(emptyForm)
    setShowForm(true)
  }

  async function openEdit(id: number) {
    try {
      const { data } = await api.get<{ data?: FleetVehicleItem }>(`/fleet/vehicles/${id}`)
      const v = data?.data
      if (!v) {
        toast.error('Veículo não encontrado')
        return
      }
      setFormData({
        plate: v.plate ?? '',
        brand: v.brand ?? '',
        model: v.model ?? '',
        year: v.year !== undefined && v.year !== null ? String(v.year) : '',
        color: v.color ?? '',
        type: v.type ?? '',
        fuel_type: v.fuel_type ?? '',
        odometer_km: v.odometer_km !== undefined && v.odometer_km !== null ? String(v.odometer_km) : '',
        renavam: v.renavam ?? '',
        chassis: v.chassis ?? '',
        crlv_expiry: toDateStr(v.crlv_expiry),
        insurance_expiry: toDateStr(v.insurance_expiry),
        next_maintenance: toDateStr(v.next_maintenance),
        purchase_value: v.purchase_value !== undefined && v.purchase_value !== null ? String(v.purchase_value) : '',
        status: v.status ?? 'active',
        notes: v.notes ?? '',
      })
      setEditingVehicle({ id })
      setShowForm(true)
    } catch (error: unknown) {
      toast.error(getApiErrorMessage(error, 'Erro ao carregar veículo'))
    }
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const payload: Record<string, unknown> = {
      plate: formData.plate.trim(),
      brand: formData.brand.trim() || null,
      model: formData.model.trim() || null,
      year: formData.year ? Number(formData.year) : null,
      color: formData.color.trim() || null,
      type: formData.type || null,
      fuel_type: formData.fuel_type || null,
      odometer_km: formData.odometer_km ? Number(formData.odometer_km) : null,
      renavam: formData.renavam.trim() || null,
      chassis: formData.chassis.trim() || null,
      crlv_expiry: formData.crlv_expiry || null,
      insurance_expiry: formData.insurance_expiry || null,
      next_maintenance: formData.next_maintenance || null,
      purchase_value: formData.purchase_value ? Number(formData.purchase_value) : null,
      status: formData.status || 'active',
      notes: formData.notes.trim() || null,
    }
    if (editingVehicle) {
      updateMutation.mutate({ id: editingVehicle.id, payload })
    } else {
      createMutation.mutate(payload)
    }
  }

  const handleDelete = (id: number) => {
    setConfirmDeleteId(id)
  }

  const confirmDelete = () => {
    if (confirmDeleteId === null) return
    deleteMutation.mutate(confirmDeleteId, {
      onSuccess: () => {
        setConfirmDeleteId(null)
      },
    })
  }

  const isPending = createMutation.isPending || updateMutation.isPending

  const { data: vehiclesData, isLoading } = useQuery<FleetVehiclesResponse>({
    queryKey: ['fleet-vehicles', search, page],
    queryFn: () => api.get('/fleet/vehicles', { params: { search: search || undefined, page, per_page: 20 } }).then(r => r.data),
  })

  const vehicles = vehiclesData?.data ?? []
  const vehicleTypeOptions = useMemo(
    () => buildLookupOptions(vehicleTypeItems, VEHICLE_TYPE_FALLBACK),
    [vehicleTypeItems]
  )
  const fuelTypeOptions = useMemo(
    () => buildLookupOptions(fuelTypeItems, FUEL_TYPE_FALLBACK),
    [fuelTypeItems]
  )
  const vehicleStatusOptions = useMemo(
    () => buildLookupOptions(vehicleStatusItems, VEHICLE_STATUS_FALLBACK),
    [vehicleStatusItems]
  )
  const vehicleTypeLabels = useMemo(
    () => buildLookupLabelMap(vehicleTypeItems, VEHICLE_TYPE_FALLBACK),
    [vehicleTypeItems]
  )
  const fuelTypeLabels = useMemo(
    () => buildLookupLabelMap(fuelTypeItems, FUEL_TYPE_FALLBACK),
    [fuelTypeItems]
  )
  const vehicleStatusLabels = useMemo(
    () => buildLookupLabelMap(vehicleStatusItems, VEHICLE_STATUS_FALLBACK),
    [vehicleStatusItems]
  )
  const pagination = {
    currentPage: vehiclesData?.current_page ?? 1,
    lastPage: vehiclesData?.last_page ?? 1,
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div className="relative w-full sm:max-w-xs">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-surface-400" />
          <input
            type="text"
            placeholder="Buscar placa ou modelo..."
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }}
            className="w-full rounded-xl border border-default bg-surface-0 py-2.5 pl-10 pr-4 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 transition-all font-medium"
          />
        </div>
        {canCreate && (
          <Button icon={<Plus size={16} />} className="w-full sm:w-auto" onClick={openCreate}>
            Cadastrar Veículo
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {isLoading && [1, 2, 3].map(i => <div key={i} className="h-48 bg-surface-100 animate-pulse rounded-2xl" />)}
        {vehicles.map((v) => (
          <div key={v.id} className="group p-5 rounded-2xl border border-default bg-surface-0 hover:border-brand-300 hover:shadow-card transition-all space-y-4">
            <div className="flex items-center justify-between">
              <div className="px-3 py-1 bg-surface-900 rounded border-2 border-surface-700 shadow-sm">
                <span className="text-xs font-mono font-bold text-white tracking-widest">{v.plate ?? v.license_plate}</span>
              </div>
              <Badge variant={v.status === 'active' ? 'success' : v.status === 'maintenance' ? 'warning' : 'secondary'}>
                {vehicleStatusLabels[v.status ?? ''] ?? v.status ?? '—'}
              </Badge>
            </div>

            <div>
              <h4 className="font-bold text-surface-900">{v.brand} {v.model}</h4>
              <p className="text-xs text-surface-500">
                {v.year ?? '—'} • {fuelTypeLabels[v.fuel_type ?? ''] ?? v.fuel_type ?? '—'}
              </p>
            </div>

            <div className="grid grid-cols-2 gap-2 py-3 border-y border-subtle">
              <div>
                <p className="text-xs uppercase text-surface-400 font-bold">Odômetro</p>
                <p className="text-xs font-semibold text-surface-700">{(v.odometer_km ?? v.current_mileage_km)?.toLocaleString()} km</p>
              </div>
              <div>
                <p className="text-xs uppercase text-surface-400 font-bold">Custo/KM</p>
                <p className="text-xs font-semibold text-brand-600">R$ {Number(v.cost_per_km || 0).toFixed(2)}</p>
              </div>
            </div>

            <div className="flex items-center justify-between pt-1">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-surface-100 flex items-center justify-center">
                  <User size={14} className="text-surface-500" />
                </div>
                <span className="text-xs text-surface-600 font-medium truncate max-w-[100px]">
                  {v.assignedUser?.name ?? v.assigned_driver?.name ?? 'Sem motorista'}
                </span>
              </div>
              <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                {canUpdate && (
                  <IconButton icon={<Eye size={14} />} variant="ghost" label="Editar" onClick={() => openEdit(v.id)} />
                )}
                {canDelete && (
                  <IconButton icon={<Trash2 size={14} />} variant="ghost" label="Excluir" className="text-red-400" onClick={() => handleDelete(v.id)} />
                )}
              </div>
            </div>
          </div>
        ))}
        {!isLoading && vehicles.length === 0 && (
          <div className="col-span-full py-20 text-center border-2 border-dashed border-default rounded-3xl">
            <Truck size={40} className="mx-auto text-surface-200 mb-4" />
            <p className="text-surface-500">Nenhum veículo encontrado</p>
          </div>
        )}
      </div>

      {pagination.lastPage > 1 && (
        <div className="flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={pagination.currentPage <= 1}
            onClick={() => setPage((current) => Math.max(1, current - 1))}
          >
            Anterior
          </Button>
          <span className="text-xs text-surface-500">
            Página {pagination.currentPage} de {pagination.lastPage}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={pagination.currentPage >= pagination.lastPage}
            onClick={() => setPage((current) => Math.min(pagination.lastPage, current + 1))}
          >
            Próxima
          </Button>
        </div>
      )}

      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-surface-0 rounded-2xl border border-default shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold text-surface-900">
                {editingVehicle ? 'Editar Veículo' : 'Cadastrar Veículo'}
              </h3>
              <button
                type="button"
                onClick={closeForm}
                className="p-2 rounded-lg hover:bg-surface-100 text-surface-500 hover:text-surface-700 transition-colors"
                aria-label="Fechar"
              >
                <X size={20} />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Placa *</label>
                  <input
                    required
                    maxLength={10}
                    value={formData.plate}
                    onChange={e => setFormData(f => ({ ...f, plate: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                    placeholder="ABC-1234"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Marca</label>
                  <input
                    value={formData.brand}
                    onChange={e => setFormData(f => ({ ...f, brand: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Modelo</label>
                  <input
                    value={formData.model}
                    onChange={e => setFormData(f => ({ ...f, model: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Ano</label>
                  <input
                    type="number"
                    min={1900}
                    max={2100}
                    value={formData.year}
                    onChange={e => setFormData(f => ({ ...f, year: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Cor</label>
                  <input
                    value={formData.color}
                    onChange={e => setFormData(f => ({ ...f, color: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Tipo</label>
                  <select
                    value={formData.type}
                    onChange={e => setFormData(f => ({ ...f, type: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  >
                    <option value="">Selecione</option>
                    {!vehicleTypeOptions.some((option) => option.value === formData.type) && formData.type && (
                      <option value={formData.type}>{vehicleTypeLabels[formData.type] ?? formData.type}</option>
                    )}
                    {vehicleTypeOptions.map((option) => (
                      <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Combustível</label>
                  <select
                    value={formData.fuel_type}
                    onChange={e => setFormData(f => ({ ...f, fuel_type: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  >
                    <option value="">Selecione</option>
                    {!fuelTypeOptions.some((option) => option.value === formData.fuel_type) && formData.fuel_type && (
                      <option value={formData.fuel_type}>{fuelTypeLabels[formData.fuel_type] ?? formData.fuel_type}</option>
                    )}
                    {fuelTypeOptions.map((option) => (
                      <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Odômetro (km)</label>
                  <input
                    type="number"
                    min={0}
                    value={formData.odometer_km}
                    onChange={e => setFormData(f => ({ ...f, odometer_km: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">RENAVAM</label>
                  <input
                    value={formData.renavam}
                    onChange={e => setFormData(f => ({ ...f, renavam: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Chassi</label>
                  <input
                    value={formData.chassis}
                    onChange={e => setFormData(f => ({ ...f, chassis: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Venc. CRLV</label>
                  <input
                    type="date"
                    value={formData.crlv_expiry}
                    onChange={e => setFormData(f => ({ ...f, crlv_expiry: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Venc. Seguro</label>
                  <input
                    type="date"
                    value={formData.insurance_expiry}
                    onChange={e => setFormData(f => ({ ...f, insurance_expiry: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Próx. Manutenção</label>
                  <input
                    type="date"
                    value={formData.next_maintenance}
                    onChange={e => setFormData(f => ({ ...f, next_maintenance: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Valor de Compra</label>
                  <input
                    type="number"
                    min={0}
                    step="0.01"
                    value={formData.purchase_value}
                    onChange={e => setFormData(f => ({ ...f, purchase_value: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-surface-600 mb-1">Status</label>
                  <select
                    value={formData.status}
                    onChange={e => setFormData(f => ({ ...f, status: e.target.value }))}
                    className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  >
                    {!vehicleStatusOptions.some((option) => option.value === formData.status) && formData.status && (
                      <option value={formData.status}>{vehicleStatusLabels[formData.status] ?? formData.status}</option>
                    )}
                    {vehicleStatusOptions.map((option) => (
                      <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-surface-600 mb-1">Observações</label>
                <textarea
                  rows={3}
                  value={formData.notes}
                  onChange={e => setFormData(f => ({ ...f, notes: e.target.value }))}
                  className="w-full rounded-xl border border-default bg-surface-0 py-2 px-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100"
                />
              </div>
              <div className="flex gap-2 pt-2">
                <Button type="submit" disabled={isPending}>
                  {isPending ? 'Salvando...' : 'Salvar'}
                </Button>
                <Button type="button" variant="outline" onClick={closeForm}>
                  Cancelar
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      <Modal
        open={confirmDeleteId !== null}
        onOpenChange={(isOpen: boolean) => { if (!isOpen) setConfirmDeleteId(null) }}
        title="Excluir Veículo"
        size="sm"
      >
        <p className="text-sm text-surface-600">
          Tem certeza que deseja excluir este veículo? Esta ação não pode ser desfeita.
        </p>
        <div className="flex justify-end gap-2 pt-4">
          <Button variant="outline" onClick={() => setConfirmDeleteId(null)}>Cancelar</Button>
          <Button
            className="bg-red-600 hover:bg-red-700"
            onClick={confirmDelete}
            loading={deleteMutation.isPending}
            disabled={deleteMutation.isPending}
          >
            Excluir
          </Button>
        </div>
      </Modal>
    </div>
  )
}
