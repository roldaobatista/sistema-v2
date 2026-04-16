import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2, Clock, Download, Edit, Eye, Plus, Scale, Search, Trash2, XCircle } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { cn } from '@/lib/utils'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import { toast } from 'sonner'
import { getStandardWeightStatusLabel, normalizeStandardWeightsPage, normalizeStandardWeightSummary } from '@/lib/standard-weight-utils'
import type { StandardWeight, StandardWeightConstants, StandardWeightExpiringSummary } from '@/types/equipment'

type StandardWeightForm = {
  nominal_value: string
  unit: string
  serial_number: string
  manufacturer: string
  precision_class: string
  material: string
  shape: string
  certificate_number: string
  certificate_date: string
  certificate_expiry: string
  laboratory: string
  laboratory_accreditation: string
  traceability_chain: string
  status: string
  notes: string
}

const statusColors: Record<string, string> = {
  active: 'bg-emerald-100 text-emerald-700',
  in_calibration: 'bg-blue-100 text-blue-700',
  out_of_service: 'bg-red-100 text-red-700',
  discarded: 'bg-surface-200 text-surface-600',
}

const emptyForm: StandardWeightForm = {
  nominal_value: '',
  unit: 'kg',
  serial_number: '',
  manufacturer: '',
  precision_class: 'M1',
  material: '',
  shape: 'cilindrico',
  certificate_number: '',
  certificate_date: '',
  certificate_expiry: '',
  laboratory: '',
  laboratory_accreditation: '',
  traceability_chain: '',
  status: 'active',
  notes: '',
}

function formatDate(value: string | null | undefined) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('pt-BR')
}

function renderCertificateBadge(expiry: string | null | undefined) {
  if (!expiry) return null
  const diff = Math.ceil((new Date(expiry).getTime() - Date.now()) / 86400000)
  if (diff < 0) return <span className="inline-flex items-center gap-1 text-[11px] font-medium text-red-600"><XCircle className="h-3 w-3" /> Vencido</span>
  if (diff <= 30) return <span className="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600"><AlertTriangle className="h-3 w-3" /> {diff}d</span>
  return <span className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600"><CheckCircle2 className="h-3 w-3" /> OK</span>
}

function buildPayload(form: StandardWeightForm) {
  return {
    nominal_value: form.nominal_value,
    unit: form.unit,
    serial_number: form.serial_number || null,
    manufacturer: form.manufacturer || null,
    precision_class: form.precision_class || null,
    material: form.material || null,
    shape: form.shape || null,
    certificate_number: form.certificate_number || null,
    certificate_date: form.certificate_date || null,
    certificate_expiry: form.certificate_expiry || null,
    laboratory: form.laboratory || null,
    laboratory_accreditation: form.laboratory_accreditation || null,
    traceability_chain: form.traceability_chain || null,
    status: form.status || null,
    notes: form.notes || null,
  }
}

export default function StandardWeightsPage() {
  const queryClient = useQueryClient()
  const { hasPermission, hasRole } = useAuthStore()
  const canCreate = hasRole('super_admin') || hasPermission('equipments.standard_weight.create')
  const canUpdate = hasRole('super_admin') || hasPermission('equipments.standard_weight.update')
  const canDelete = hasRole('super_admin') || hasPermission('equipments.standard_weight.delete')

  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)
  const [showModal, setShowModal] = useState(false)
  const [editingWeight, setEditingWeight] = useState<StandardWeight | null>(null)
  const [showDetail, setShowDetail] = useState<StandardWeight | null>(null)
  const [form, setForm] = useState<StandardWeightForm>(emptyForm)

  const { data: constants } = useQuery<StandardWeightConstants>({
    queryKey: ['standard-weights-constants'],
    queryFn: () => api.get('/standard-weights/constants').then((response) => unwrapData<StandardWeightConstants>(response)),
  })

  const { data: weightsPage, isLoading } = useQuery({
    queryKey: ['standard-weights', search, statusFilter, page],
    queryFn: () => api.get('/standard-weights', { params: { search, status: statusFilter || undefined, page, per_page: 20 } }).then((response) => response.data),
  })

  const { data: expiringPayload } = useQuery<StandardWeightExpiringSummary>({
    queryKey: ['standard-weights-expiring'],
    queryFn: () => api.get('/standard-weights/expiring', { params: { days: 30 } }).then((response) => unwrapData<StandardWeightExpiringSummary>(response)),
  })

  const saveMutation = useMutation({
    mutationFn: (payload: StandardWeightForm) => {
      const request = buildPayload(payload)
      return editingWeight ? api.put(`/standard-weights/${editingWeight.id}`, request) : api.post('/standard-weights', request)
    },
    onSuccess: () => {
      toast.success(editingWeight ? 'Peso padrão atualizado.' : 'Peso padrão criado.')
      queryClient.invalidateQueries({ queryKey: ['standard-weights'] })
      queryClient.invalidateQueries({ queryKey: ['standard-weights-expiring'] })
      setShowModal(false)
      setEditingWeight(null)
      setForm(emptyForm)
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao salvar peso padrão')),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/standard-weights/${id}`),
    onSuccess: () => {
      toast.success('Peso padrão removido.')
      queryClient.invalidateQueries({ queryKey: ['standard-weights'] })
      queryClient.invalidateQueries({ queryKey: ['standard-weights-expiring'] })
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao excluir peso padrão')),
  })

  const predictWearMutation = useMutation({
    mutationFn: (id: number) => api.post(`/standard-weights/${id}/predict-wear`).then((response) => unwrapData<{ weight_id: number; wear_rate_percentage: number | null; expected_failure_date: string | null }>(response)),
    onSuccess: (result) => {
      toast.success(`Desgaste calculado: ${result.wear_rate_percentage ?? 0}%`)
      queryClient.invalidateQueries({ queryKey: ['standard-weights'] })
      queryClient.invalidateQueries({ queryKey: ['standard-weights-expiring'] })
      setShowDetail((current) => current && current.id === result.weight_id ? { ...current, wear_rate_percentage: result.wear_rate_percentage, expected_failure_date: result.expected_failure_date } : current)
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao calcular desgaste do peso padrão')),
  })

  const { weights, total, lastPage } = normalizeStandardWeightsPage(weightsPage)
  const expiringSummary = normalizeStandardWeightSummary(expiringPayload)

  const openCreate = () => { setEditingWeight(null); setForm(emptyForm); setShowModal(true) }
  const openEdit = (weight: StandardWeight) => {
    setEditingWeight(weight)
    setForm({
      nominal_value: weight.nominal_value,
      unit: weight.unit,
      serial_number: weight.serial_number ?? '',
      manufacturer: weight.manufacturer ?? '',
      precision_class: weight.precision_class ?? 'M1',
      material: weight.material ?? '',
      shape: weight.shape ?? 'cilindrico',
      certificate_number: weight.certificate_number ?? '',
      certificate_date: weight.certificate_date ?? '',
      certificate_expiry: weight.certificate_expiry ?? '',
      laboratory: weight.laboratory ?? '',
      laboratory_accreditation: weight.laboratory_accreditation ?? '',
      traceability_chain: weight.traceability_chain ?? '',
      status: weight.status,
      notes: weight.notes ?? '',
    })
    setShowModal(true)
  }

  const handleDelete = (weight: StandardWeight) => {
    if (confirm(`Deseja excluir o peso padrão ${weight.code}?`)) deleteMutation.mutate(weight.id)
  }

  const handleExport = async () => {
    try {
      const response = await api.get('/standard-weights/export', { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = `pesos-padrao-${new Date().toISOString().split('T')[0]}.csv`
      link.click()
      URL.revokeObjectURL(url)
      toast.success('Exportação concluída.')
    } catch (error) {
      toast.error(getApiErrorMessage(error, 'Erro ao exportar pesos padrão'))
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title="Pesos Padrão"
        subtitle={`${total} registro${total !== 1 ? 's' : ''}`}
        icon={Scale}
        actions={(
          <div className="flex items-center gap-2">
            <button type="button" onClick={handleExport} className="btn-ghost text-xs gap-1.5">
              <Download className="h-3.5 w-3.5" /> CSV
            </button>
            {canCreate && (
              <button type="button" onClick={openCreate} className="btn-primary text-xs gap-1.5">
                <Plus className="h-3.5 w-3.5" /> Novo Peso
              </button>
            )}
          </div>
        )}
      />

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded-lg border border-default bg-surface-0 p-3">
          <div className="mb-1 flex items-center gap-2 text-xs text-surface-500">
            <Scale className="h-3.5 w-3.5" /> Total
          </div>
          <div className="text-xl font-bold text-surface-900">{total}</div>
        </div>
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
          <div className="mb-1 flex items-center gap-2 text-xs text-amber-600">
            <AlertTriangle className="h-3.5 w-3.5" /> Vencendo (30d)
          </div>
          <div className="text-xl font-bold text-amber-700">{expiringSummary.expiring_count}</div>
        </div>
        <div className="rounded-lg border border-red-200 bg-red-50 p-3">
          <div className="mb-1 flex items-center gap-2 text-xs text-red-600">
            <XCircle className="h-3.5 w-3.5" /> Vencidos
          </div>
          <div className="text-xl font-bold text-red-700">{expiringSummary.expired_count}</div>
        </div>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-surface-400" />
          <input
            type="text"
            value={search}
            onChange={(event) => { setSearch(event.target.value); setPage(1) }}
            placeholder="Buscar por código, série ou certificado..."
            className="w-full rounded-md border border-default bg-surface-0 py-1.5 pl-8 pr-3 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/20"
          />
        </div>
        <select
          value={statusFilter}
          onChange={(event) => { setStatusFilter(event.target.value); setPage(1) }}
          className="rounded-md border border-default bg-surface-0 px-3 py-1.5 text-sm"
          aria-label="Filtrar pesos padrão por status"
        >
          <option value="">Todos os status</option>
          {constants?.statuses && Object.entries(constants.statuses).map(([key, value]) => (
            <option key={key} value={key}>
              {typeof value === 'object' && value !== null ? value.label : value}
            </option>
          ))}
        </select>
      </div>

      <div className="overflow-hidden rounded-xl border border-default bg-surface-0">
        {isLoading ? (
          <div className="flex justify-center p-8">
            <div className="h-6 w-6 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" />
          </div>
        ) : weights.length === 0 ? (
          <div className="p-8 text-center text-surface-400">
            <Scale className="mx-auto mb-2 h-10 w-10 opacity-40" />
            <p className="text-sm font-medium">Nenhum peso padrão encontrado</p>
            <p className="mt-1 text-xs">Cadastre um peso para começar</p>
            {canCreate && (
              <button type="button" onClick={openCreate} className="btn-primary mt-3 text-xs gap-1.5">
                <Plus className="h-3.5 w-3.5" /> Cadastrar Peso
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-default bg-surface-50 text-xs text-surface-500">
                  <th className="px-3 py-2 text-left font-medium">Código</th>
                  <th className="px-3 py-2 text-left font-medium">Valor Nominal</th>
                  <th className="px-3 py-2 text-left font-medium">Classe</th>
                  <th className="px-3 py-2 text-left font-medium">Nº Série</th>
                  <th className="px-3 py-2 text-left font-medium">Certificado</th>
                  <th className="px-3 py-2 text-left font-medium">Validade</th>
                  <th className="px-3 py-2 text-left font-medium">Acreditação</th>
                  <th className="px-3 py-2 text-left font-medium">Status</th>
                  <th className="px-3 py-2 text-right font-medium">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-default">
                {weights.map((weight) => (
                  <tr key={weight.id} className="transition-colors hover:bg-surface-50">
                    <td className="px-3 py-2 font-mono text-xs font-medium text-brand-600">{weight.code}</td>
                    <td className="px-3 py-2">{Number(weight.nominal_value).toLocaleString('pt-BR')} {weight.unit}</td>
                    <td className="px-3 py-2 text-xs"><span className="rounded-md bg-surface-100 px-1.5 py-0.5 font-mono">{weight.precision_class ?? '-'}</span></td>
                    <td className="px-3 py-2 text-surface-500">{weight.serial_number ?? '-'}</td>
                    <td className="px-3 py-2 text-xs text-surface-500">{weight.certificate_number ?? '-'}</td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-1.5">
                        <span className="text-xs text-surface-500">{formatDate(weight.certificate_expiry)}</span>
                        {renderCertificateBadge(weight.certificate_expiry)}
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      {weight.laboratory_accreditation ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700">
                          <CheckCircle2 className="h-3 w-3" /> {weight.laboratory_accreditation}
                        </span>
                      ) : (
                        <span className="text-[10px] text-surface-400">-</span>
                      )}
                    </td>
                    <td className="px-3 py-2">
                      <span className={cn('inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold', statusColors[weight.status] ?? 'bg-surface-100 text-surface-600')}>
                        {weight.status_label ?? getStandardWeightStatusLabel(constants?.statuses, weight.status)}
                      </span>
                    </td>
                    <td className="px-3 py-2 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button type="button" onClick={() => setShowDetail(weight)} className="rounded-md p-1 text-surface-400 hover:bg-surface-100 hover:text-surface-600" title="Detalhes" aria-label={`Ver detalhes do peso ${weight.code}`}>
                          <Eye className="h-3.5 w-3.5" />
                        </button>
                        {canUpdate && (
                          <button type="button" onClick={() => openEdit(weight)} className="rounded-md p-1 text-surface-400 hover:bg-surface-100 hover:text-blue-600" title="Editar" aria-label={`Editar peso ${weight.code}`}>
                            <Edit className="h-3.5 w-3.5" />
                          </button>
                        )}
                        {canDelete && (
                          <button type="button" onClick={() => handleDelete(weight)} className="rounded-md p-1 text-surface-400 hover:bg-red-50 hover:text-red-600" title="Excluir" aria-label={`Excluir peso ${weight.code}`}>
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {lastPage > 1 && (
          <div className="flex items-center justify-between border-t border-default px-3 py-2">
            <span className="text-xs text-surface-500">Página {page} de {lastPage}</span>
            <div className="flex gap-1">
              <button type="button" disabled={page <= 1} onClick={() => setPage((current) => current - 1)} className="rounded border border-default px-2 py-1 text-xs disabled:opacity-30">Anterior</button>
              <button type="button" disabled={page >= lastPage} onClick={() => setPage((current) => current + 1)} className="rounded border border-default px-2 py-1 text-xs disabled:opacity-30">Próxima</button>
            </div>
          </div>
        )}
      </div>
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-default bg-surface-0 shadow-xl">
            <div className="flex items-center justify-between border-b border-default px-4 py-3">
              <h3 className="text-sm font-semibold text-surface-900">{editingWeight ? 'Editar Peso Padrão' : 'Novo Peso Padrão'}</h3>
              <button type="button" onClick={() => { setShowModal(false); setEditingWeight(null) }} className="text-surface-400 hover:text-surface-600" aria-label="Fechar modal de peso padrão">×</button>
            </div>
            <form onSubmit={(event) => { event.preventDefault(); saveMutation.mutate(form) }} className="space-y-3 p-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs font-medium text-surface-600">Valor Nominal *</label>
                  <input type="number" step="any" required value={form.nominal_value} onChange={(event) => setForm({ ...form, nominal_value: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-surface-600">Unidade *</label>
                  <select value={form.unit} onChange={(event) => setForm({ ...form, unit: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" aria-label="Unidade do peso padrão">
                    {(constants?.units ?? ['kg', 'g', 'mg']).map((unit) => <option key={unit} value={unit}>{unit}</option>)}
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs font-medium text-surface-600">Nº Série</label>
                  <input type="text" value={form.serial_number} onChange={(event) => setForm({ ...form, serial_number: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-surface-600">Fabricante</label>
                  <input type="text" value={form.manufacturer} onChange={(event) => setForm({ ...form, manufacturer: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                </div>
              </div>
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="mb-1 block text-xs font-medium text-surface-600">Classe de Precisão</label>
                  <select value={form.precision_class} onChange={(event) => setForm({ ...form, precision_class: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" aria-label="Classe de precisão">
                    {constants && Object.entries(constants.precision_classes).map(([key, value]) => <option key={key} value={key}>{value}</option>)}
                  </select>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-surface-600">Material</label>
                  <input type="text" value={form.material} onChange={(event) => setForm({ ...form, material: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-surface-600">Formato</label>
                  <select value={form.shape} onChange={(event) => setForm({ ...form, shape: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" aria-label="Formato do peso padrão">
                    {constants && Object.entries(constants.shapes).map(([key, value]) => <option key={key} value={key}>{value}</option>)}
                  </select>
                </div>
              </div>
              <div className="border-t border-default pt-3">
                <p className="mb-2 text-xs font-semibold text-surface-600">Certificado</p>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="mb-1 block text-xs font-medium text-surface-600">Nº Certificado</label>
                    <input type="text" value={form.certificate_number} onChange={(event) => setForm({ ...form, certificate_number: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-medium text-surface-600">Laboratório</label>
                    <input type="text" value={form.laboratory} onChange={(event) => setForm({ ...form, laboratory: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                  </div>
                </div>
                <div className="mt-3 grid grid-cols-2 gap-3">
                  <div>
                    <label className="mb-1 block text-xs font-medium text-surface-600">Data Certificado</label>
                    <input type="date" value={form.certificate_date} onChange={(event) => setForm({ ...form, certificate_date: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-medium text-surface-600">Validade</label>
                    <input type="date" value={form.certificate_expiry} onChange={(event) => setForm({ ...form, certificate_expiry: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                  </div>
                </div>
                <div className="mt-3 grid grid-cols-1 gap-3">
                  <div>
                    <label className="mb-1 block text-xs font-medium text-surface-600">Acreditacao do Laboratorio</label>
                    <input type="text" value={form.laboratory_accreditation} onChange={(event) => setForm({ ...form, laboratory_accreditation: event.target.value })} placeholder="Ex: RBC/Cgcre CRL-0042" className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-medium text-surface-600">Cadeia de Rastreabilidade</label>
                    <textarea value={form.traceability_chain} onChange={(event) => setForm({ ...form, traceability_chain: event.target.value })} rows={2} placeholder="Descreva a cadeia de rastreabilidade metrologica" className="w-full resize-none rounded-md border border-default px-2.5 py-1.5 text-sm" />
                  </div>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="mb-1 block text-xs font-medium text-surface-600">Status</label>
                  <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })} className="w-full rounded-md border border-default px-2.5 py-1.5 text-sm" aria-label="Status do peso padrão">
                    {constants && Object.entries(constants.statuses).map(([key, value]) => <option key={key} value={key}>{typeof value === 'object' && value !== null ? value.label : value}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-surface-600">Observações</label>
                <textarea value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} rows={2} className="w-full resize-none rounded-md border border-default px-2.5 py-1.5 text-sm" />
              </div>
              <div className="flex justify-end gap-2 border-t border-default pt-3">
                <button type="button" onClick={() => { setShowModal(false); setEditingWeight(null) }} className="btn-ghost text-xs">Cancelar</button>
                <button type="submit" disabled={saveMutation.isPending} className="btn-primary text-xs gap-1.5">
                  {saveMutation.isPending ? <><Clock className="h-3.5 w-3.5 animate-spin" /> Salvando...</> : <>{editingWeight ? 'Salvar' : 'Criar'}</>}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      {showDetail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={() => setShowDetail(null)}>
          <div className="w-full max-w-md rounded-xl border border-default bg-surface-0 shadow-xl" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-center justify-between border-b border-default px-4 py-3">
              <h3 className="text-sm font-semibold text-surface-900">{showDetail.code} - Detalhes</h3>
              <button type="button" onClick={() => setShowDetail(null)} className="text-surface-400 hover:text-surface-600" aria-label="Fechar detalhes do peso padrão">×</button>
            </div>
            <div className="space-y-2 p-4 text-sm">
              <div className="grid grid-cols-2 gap-2">
                <div><span className="text-xs text-surface-400">Valor Nominal</span><p className="font-medium">{Number(showDetail.nominal_value).toLocaleString('pt-BR')} {showDetail.unit}</p></div>
                <div><span className="text-xs text-surface-400">Classe</span><p className="font-medium">{showDetail.precision_class ?? '-'}</p></div>
                <div><span className="text-xs text-surface-400">Nº Série</span><p className="font-medium">{showDetail.serial_number ?? '-'}</p></div>
                <div><span className="text-xs text-surface-400">Fabricante</span><p className="font-medium">{showDetail.manufacturer ?? '-'}</p></div>
                <div><span className="text-xs text-surface-400">Material</span><p className="font-medium">{showDetail.material ?? '-'}</p></div>
                <div><span className="text-xs text-surface-400">Formato</span><p className="font-medium">{showDetail.shape ?? '-'}</p></div>
              </div>
              <div className="mt-2 border-t border-default pt-2">
                <p className="mb-1 text-xs font-semibold text-surface-500">Certificado</p>
                <div className="grid grid-cols-2 gap-2">
                  <div><span className="text-xs text-surface-400">Número</span><p className="font-medium">{showDetail.certificate_number ?? '-'}</p></div>
                  <div><span className="text-xs text-surface-400">Laboratorio</span><p className="font-medium">{showDetail.laboratory ?? '-'}</p></div>
                  <div><span className="text-xs text-surface-400">Acreditacao</span><p className="font-medium">{showDetail.laboratory_accreditation ? <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700"><CheckCircle2 className="h-3 w-3" />{showDetail.laboratory_accreditation}</span> : '-'}</p></div>
                  <div><span className="text-xs text-surface-400">Data</span><p className="font-medium">{formatDate(showDetail.certificate_date)}</p></div>
                  <div><span className="text-xs text-surface-400">Validade</span><p className="flex items-center gap-1.5 font-medium">{formatDate(showDetail.certificate_expiry)} {renderCertificateBadge(showDetail.certificate_expiry)}</p></div>
                </div>
              </div>
              {showDetail.traceability_chain && (
                <div className="mt-2 border-t border-default pt-2">
                  <span className="text-xs text-surface-400">Cadeia de Rastreabilidade</span>
                  <p className="text-surface-600 whitespace-pre-line">{showDetail.traceability_chain}</p>
                </div>
              )}
              <div className="mt-2 border-t border-default pt-2">
                <div className="mb-2 flex items-center justify-between">
                  <p className="text-xs font-semibold text-surface-500">Metricas Analiticas (INMETRO)</p>
                  <button type="button" onClick={() => predictWearMutation.mutate(showDetail.id)} disabled={predictWearMutation.isPending} className="btn-ghost text-xs gap-1 opacity-80 hover:opacity-100">
                    <AlertTriangle className="h-3 w-3" />
                    {predictWearMutation.isPending ? 'Analisando...' : 'Reavaliar Desgaste'}
                  </button>
                </div>
                <div className="grid grid-cols-2 gap-2 rounded-lg border border-default bg-surface-50 p-2">
                  <div>
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-surface-400">Desgaste Acumulado (MPE)</span>
                    <div className="flex items-end gap-1 font-mono">
                      <span className={cn('text-lg font-bold', (showDetail.wear_rate_percentage || 0) > 80 ? 'text-red-600' : (showDetail.wear_rate_percentage || 0) > 50 ? 'text-amber-600' : 'text-emerald-600')}>{showDetail.wear_rate_percentage != null ? `${showDetail.wear_rate_percentage}%` : '-'}</span>
                    </div>
                  </div>
                  <div><span className="text-[10px] font-semibold uppercase tracking-wider text-surface-400">Falha Prevista</span><p className="font-medium text-surface-700">{formatDate(showDetail.expected_failure_date)}</p></div>
                </div>
              </div>
              {showDetail.notes && (
                <div className="mt-2 border-t border-default pt-2">
                  <span className="text-xs text-surface-400">Observações</span>
                  <p className="text-surface-600">{showDetail.notes}</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
