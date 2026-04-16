import React, { useState, useEffect } from 'react'
import { toast } from 'sonner'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Save, Loader2, Scale, XCircle } from 'lucide-react'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import { CustomerAsyncSelect } from '@/components/common/CustomerAsyncSelect'
import api from '@/lib/api'
import { getApiErrorMessage } from '@/lib/api'
import { equipmentApi } from '@/lib/equipment-api'
import { normalizeEquipmentStatus } from '@/lib/equipment-status'
import { queryKeys } from '@/lib/query-keys'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { safeArray } from '@/lib/safe-array'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import { normalizeMeasurementInput } from '@/lib/equipment-display'

export default function EquipmentEditPage() {
    const { id } = useParams()
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canUpdate = hasPermission('equipments.equipment.update')

    useEffect(() => {
        if (!canUpdate) {
            toast.error('Sem permissao para editar equipamentos')
            navigate('/equipamentos')
        }
    }, [canUpdate, navigate])

    const { data, isLoading, error } = useQuery({
        queryKey: queryKeys.equipment.detail(Number(id!)),
        queryFn: () => equipmentApi.detail(Number(id!)),
        enabled: !!id,
    })

    const { data: constants } = useQuery({
        queryKey: queryKeys.equipment.constants,
        queryFn: () => equipmentApi.constants(),
    })
    const { data: modelsData } = useQuery({
        queryKey: queryKeys.equipment.models({ per_page: 200 }),
        queryFn: () => api.get('/equipment-models', { params: { per_page: 200 } }).then((r) => safeArray<{ id: number; name: string; brand: string | null }>(r.data)),
    })
    const equipmentModels = modelsData ?? []

    const [form, setForm] = useState({
        customer_id: '' as string | number,
        equipment_model_id: '' as string | number,
        type: '',
        category: '',
        brand: '',
        manufacturer: '',
        model: '',
        serial_number: '',
        capacity: '',
        capacity_unit: 'kg',
        resolution: '',
        precision_class: '',
        location: '',
        status: '',
        calibration_interval_months: '',
        inmetro_number: '',
        tag: '',
        is_critical: false,
        is_active: true,
        notes: '',
        purchase_value: '',
    })

    useEffect(() => {
        if (!data) return
        const eq = data
        const normalizedStatus = normalizeEquipmentStatus(eq.status) ?? 'active'
        setForm({
            customer_id: eq.customer_id ?? '',
            equipment_model_id: eq.equipment_model_id ?? '',
            type: eq.type ?? '',
            category: eq.category ?? '',
            brand: eq.brand ?? '',
            manufacturer: eq.manufacturer ?? '',
            model: eq.model ?? '',
            serial_number: eq.serial_number ?? '',
            capacity: eq.capacity != null ? String(eq.capacity) : '',
            capacity_unit: eq.capacity_unit ?? 'kg',
            resolution: eq.resolution != null ? String(eq.resolution) : '',
            precision_class: eq.precision_class ?? '',
            location: eq.location ?? '',
            status: normalizedStatus,
            calibration_interval_months: eq.calibration_interval_months != null ? String(eq.calibration_interval_months) : '',
            inmetro_number: eq.inmetro_number ?? '',
            tag: eq.tag ?? '',
            is_critical: eq.is_critical ?? false,
            is_active: eq.is_active !== false,
            notes: eq.notes ?? '',
            purchase_value: eq.purchase_value != null ? String(eq.purchase_value) : '',
        })
    }, [data])

    const mutation = useMutation({
        mutationFn: (payload: Record<string, unknown>) => equipmentApi.update(Number(id!), payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.equipment.detail(Number(id!)) })
            queryClient.invalidateQueries({ queryKey: queryKeys.equipment.all })
            broadcastQueryInvalidation(['equipments'], 'Equipamento')
            toast.success('Equipamento atualizado.')
            navigate(`/equipamentos/${id}`)
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Erro ao atualizar equipamento'))
        },
    })

    const update = (key: string, val: string | number | boolean) => setForm((f) => ({ ...f, [key]: val }))

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        mutation.mutate({
            customer_id: form.customer_id ? +form.customer_id : null,
            equipment_model_id: form.equipment_model_id ? +form.equipment_model_id : null,
            type: form.type || null,
            category: form.category || null,
            brand: form.brand || null,
            manufacturer: form.manufacturer || null,
            model: form.model || null,
            serial_number: form.serial_number || null,
            capacity: form.capacity ? +form.capacity : null,
            capacity_unit: form.capacity_unit || null,
            resolution: form.resolution ? +form.resolution : null,
            precision_class: form.precision_class || null,
            status: (normalizeEquipmentStatus(form.status) ?? form.status) || null,
            location: form.location || null,
            calibration_interval_months: form.calibration_interval_months ? +form.calibration_interval_months : null,
            inmetro_number: form.inmetro_number || null,
            tag: form.tag || null,
            is_critical: form.is_critical,
            is_active: form.is_active,
            notes: form.notes || null,
            purchase_value: form.purchase_value ? +form.purchase_value : null,
        })
    }

    const cats = constants?.categories ?? {}
    const rawConst = constants as { precision_classes?: Record<string, string>; statuses?: Record<string, string> } | undefined
    const classes = rawConst?.precision_classes ?? {}
    const statuses = rawConst?.statuses ?? { active: 'Ativo', in_calibration: 'Em Calibração', in_maintenance: 'Em Manutenção', out_of_service: 'Fora de Uso', discarded: 'Descartado' }


    if (!canUpdate) {
        return null
    }
    if (isLoading || !data) {
        return (
            <div className="space-y-5">
                <PageHeader title="Carregando..." backTo="/equipamentos" />
                <div className="flex justify-center py-12">
                    <Loader2 className="h-8 w-8 animate-spin text-brand-500" />
                </div>
            </div>
        )
    }
    if (error) {
        return (
            <div className="space-y-5">
                <PageHeader title="Equipamento" backTo="/equipamentos" />
                <div className="rounded-xl border border-red-200 bg-red-50 p-6 flex items-center gap-3">
                    <XCircle className="h-10 w-10 text-red-500 shrink-0" />
                    <div>
                        <p className="font-medium text-red-800">Não foi possível carregar o equipamento.</p>
                        <p className="text-sm text-red-600">Verifique o ID ou permissões.</p>
                    </div>
                    <button onClick={() => navigate('/equipamentos')} className="ml-auto rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                        Voltar
                    </button>
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title={`Editar ${data?.code ?? id}`}
                subtitle="Alterar dados do equipamento"
                backTo={`/equipamentos/${id}`}
            />

            <form onSubmit={handleSubmit} className="space-y-5">
                <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                    <h3 className="mb-4 flex items-center gap-2 font-semibold text-surface-900">
                        <Scale size={18} className="text-brand-500" />
                        Identificação
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Cliente</label>
                            <CustomerAsyncSelect
                                label="Cliente"
                                customerId={form.customer_id ? Number(form.customer_id) : null}
                                onChange={(customer) => update('customer_id', customer ? String(customer.id) : '')}
                            />
                        </div>
                        <div>
                            <LookupCombobox lookupType="equipment-types" label="Tipo" value={form.type} onChange={(v) => update('type', v)} placeholder="Selecionar tipo" className="w-full" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Categoria</label>
                            <select value={form.category} onChange={(e) => update('category', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Categoria">
                                {Object.entries(cats).map(([k, v]) => (
                                    <option key={k} value={k}>{v as string}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <LookupCombobox lookupType="equipment-brands" label="Marca" value={form.brand} onChange={(v) => update('brand', v)} placeholder="Selecionar marca" className="w-full" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Modelo de balança (catálogo)</label>
                            <select value={form.equipment_model_id} onChange={(e) => update('equipment_model_id', e.target.value === '' ? '' : Number(e.target.value))} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Modelo de balança">
                                <option value="">— Nenhum —</option>
                                {(equipmentModels || []).map((m) => (
                                    <option key={m.id} value={m.id}>{m.brand ? `${m.brand} - ${m.name}` : m.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Modelo (texto livre)</label>
                            <input value={form.model} onChange={(e) => update('model', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Modelo" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Nº Série</label>
                            <input value={form.serial_number} onChange={(e) => update('serial_number', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Número de série" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Status</label>
                            <select value={form.status} onChange={(e) => update('status', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Status">
                                {Object.entries(statuses).map(([k, v]) => (
                                    <option key={k} value={k}>{typeof v === 'string' ? v : (v as { label?: string })?.label ?? k}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Tag / Patrimônio</label>
                            <input value={form.tag} onChange={(e) => update('tag', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Tag" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Localização</label>
                            <input value={form.location} onChange={(e) => update('location', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Localização" />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                    <h3 className="mb-4 font-semibold text-surface-900">Especificações</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Capacidade</label>
                            <div className="flex gap-2">
                                <input type="number" step="any" value={form.capacity} onChange={(e) => update('capacity', e.target.value)} onBlur={() => update('capacity', form.capacity ? normalizeMeasurementInput(form.capacity) : '')} className="flex-1 rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Capacidade" />
                                <select value={form.capacity_unit} onChange={(e) => update('capacity_unit', e.target.value)} className="w-20 rounded-lg border border-surface-200 px-2 py-2.5 text-sm" aria-label="Unidade">
                                    <option>kg</option><option>g</option><option>mg</option><option>t</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Resolução</label>
                            <input type="number" step="any" value={form.resolution} onChange={(e) => update('resolution', e.target.value)} onBlur={() => {
                                const normalizedResolution = normalizeMeasurementInput(form.resolution)
                                update('resolution', normalizedResolution)
                                if (form.capacity) {
                                    update('capacity', normalizeMeasurementInput(form.capacity))
                                }
                            }} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Resolução" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Classe de Precisão</label>
                            <select value={form.precision_class} onChange={(e) => update('precision_class', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Classe de precisão">
                                <option value="">—</option>
                                {Object.entries(classes).map(([k, v]) => (
                                    <option key={k} value={k}>{v as string}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Nº INMETRO</label>
                            <input value={form.inmetro_number} onChange={(e) => update('inmetro_number', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Número INMETRO" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Intervalo Calibração (meses)</label>
                            <input type="number" value={form.calibration_interval_months} onChange={(e) => update('calibration_interval_months', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Intervalo de calibração" />
                        </div>
                        <div className="flex items-end gap-4">
                            <label className="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" checked={form.is_critical} onChange={(e) => update('is_critical', e.target.checked)} className="accent-red-600" aria-label="Equipamento crítico" />
                                <span className="text-[13px] font-medium text-surface-700">Crítico</span>
                            </label>
                            <label className="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" checked={form.is_active} onChange={(e) => update('is_active', e.target.checked)} className="accent-brand-600" aria-label="Ativo" />
                                <span className="text-[13px] font-medium text-surface-700">Ativo</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                    <h3 className="mb-4 font-semibold text-surface-900">Observações</h3>
                    <textarea value={form.notes} onChange={(e) => update('notes', e.target.value)} rows={3} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Observações" />
                </div>

                <div className="flex justify-end gap-3">
                    <button type="button" onClick={() => navigate(`/equipamentos/${id}`)} className="rounded-lg border border-surface-200 px-4 py-2.5 text-sm hover:bg-surface-50">
                        Cancelar
                    </button>
                    <button type="submit" disabled={mutation.isPending || !canUpdate} className="flex items-center gap-2 rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                        {mutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    )
}
