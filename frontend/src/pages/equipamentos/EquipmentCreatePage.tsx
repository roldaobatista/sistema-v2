import React, { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery, useMutation } from '@tanstack/react-query'
import { Save, Loader2, Scale } from 'lucide-react'
import { LookupCombobox } from '@/components/common/LookupCombobox'
import { CustomerAsyncSelect, type CustomerAsyncSelectItem } from '@/components/common/CustomerAsyncSelect'
import api from '@/lib/api'
import { getApiErrorMessage } from '@/lib/api'
import { customerApi } from '@/lib/customer-api'
import { equipmentApi } from '@/lib/equipment-api'
import { queryKeys } from '@/lib/query-keys'
import { safeArray } from '@/lib/safe-array'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import { normalizeMeasurementInput } from '@/lib/equipment-display'
import type { Equipment } from '@/types/equipment'

export default function EquipmentCreatePage() {
    const { hasPermission } = useAuthStore()
    const canCreate = hasPermission('equipments.equipment.create')

    const navigate = useNavigate()
    const [searchParams] = useSearchParams()
    const customerIdFromUrl = searchParams.get('customer_id')

    useEffect(() => {
        if (!canCreate) {
            toast.error('Sem permissao para criar equipamentos')
            navigate('/equipamentos')
        }
    }, [canCreate, navigate])

    const { data: constants, isError: _constantsError } = useQuery({
        queryKey: queryKeys.equipment.constants,
        queryFn: () => equipmentApi.constants(),
        meta: { errorMessage: 'Erro ao carregar constantes de equipamentos' },
    })

    const customerIdNum = customerIdFromUrl ? Number(customerIdFromUrl) : 0
    const { data: preselectedCustomer } = useQuery({
        queryKey: queryKeys.customers.detail(customerIdNum),
        queryFn: () => customerApi.detail(customerIdNum),
        enabled: !!customerIdNum,
    })

    const { data: modelsData } = useQuery({
        queryKey: queryKeys.equipment.models({ per_page: 200 }),
        queryFn: () => api.get('/equipment-models', { params: { per_page: 200 } }).then((r) => safeArray<{ id: number; name: string; brand: string | null }>(r.data)),
    })
    const equipmentModels = modelsData ?? []

    const [form, setForm] = useState({
        customer_id: customerIdFromUrl || '',
        equipment_model_id: '' as string | number,
        type: 'Balança',
        category: 'balanca_plataforma',
        brand: '',
        manufacturer: '',
        model: '',
        serial_number: '',
        capacity: '',
        capacity_unit: 'kg',
        resolution: '',
        precision_class: '',
        location: '',
        calibration_interval_months: '12',
        inmetro_number: '',
        tag: '',
        is_critical: false,
        notes: '',
        purchase_value: '',
    })

    useEffect(() => {
        if (preselectedCustomer?.id && !form.customer_id) {
            setForm((prev) => ({ ...prev, customer_id: String(preselectedCustomer.id) }))
        }
    }, [form.customer_id, preselectedCustomer])

    const mutation = useMutation({
        mutationFn: (data: Record<string, unknown>) => equipmentApi.create(data),
        onSuccess: (equipment: Equipment) => {
            toast.success('Equipamento criado com sucesso!')
            const equipmentId = equipment.id
            if (equipmentId) {
                navigate(`/equipamentos/${equipmentId}`)
            }
        },
        onError: (err) => {
            toast.error(getApiErrorMessage(err, 'Erro ao criar equipamento'))
        },
    })

    const update = (key: string, val: string | number | boolean | null) => setForm(f => ({ ...f, [key]: val }))

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        mutation.mutate({
            ...form,
            customer_id: +form.customer_id || undefined,
            equipment_model_id: form.equipment_model_id ? +form.equipment_model_id : null,
            capacity: form.capacity ? +form.capacity : null,
            resolution: form.resolution ? +form.resolution : null,
            purchase_value: form.purchase_value ? +form.purchase_value : null,
            calibration_interval_months: form.calibration_interval_months ? +form.calibration_interval_months : null,
        })
    }

    const cats = constants?.categories ?? {}
    const classes = (constants as { precision_classes?: Record<string, string> } | undefined)?.precision_classes ?? {}

    if (!canCreate) {
        return null
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Novo Equipamento"
                subtitle="Cadastrar equipamento / instrumento de medição"
                backTo="/equipamentos"
            />

            <form onSubmit={handleSubmit} className="space-y-5">
                {/* Identificação */}
                <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                    <h3 className="mb-4 flex items-center gap-2 font-semibold text-surface-900">
                        <Scale size={18} className="text-brand-500" />
                        Identificação
                    </h3>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Cliente *</label>
                            <CustomerAsyncSelect
                                label="Cliente"
                                customerId={form.customer_id ? Number(form.customer_id) : null}
                                initialCustomer={preselectedCustomer as CustomerAsyncSelectItem | null}
                                onChange={(customer) => update('customer_id', customer ? String(customer.id) : '')}
                            />
                        </div>
                        <div>
                            <LookupCombobox lookupType="equipment-types" label="Tipo *" value={form.type} onChange={(v) => update('type', v)} placeholder="Selecionar tipo" className="w-full" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Categoria</label>
                            <select value={form.category} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => update('category', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Categoria">
                                {Object.entries(cats).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}
                            </select>
                        </div>
                        <div>
                            <LookupCombobox lookupType="equipment-brands" label="Marca" value={form.brand} onChange={(v) => update('brand', v)} placeholder="Selecionar marca" className="w-full" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Fabricante</label>
                            <input value={form.manufacturer} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('manufacturer', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Modelo de balança (catálogo)</label>
                            <select value={form.equipment_model_id} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => update('equipment_model_id', e.target.value === '' ? '' : Number(e.target.value))} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Modelo de balança (catálogo)">
                                <option value="">— Nenhum —</option>
                                {(equipmentModels || []).map((m) => (
                                    <option key={m.id} value={m.id}>{m.brand ? `${m.brand} - ${m.name}` : m.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Modelo (texto livre)</label>
                            <input value={form.model} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('model', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" placeholder="Ex: 2098" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Nº Série</label>
                            <input value={form.serial_number} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('serial_number', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Tag / Patrimônio</label>
                            <input value={form.tag} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('tag', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Localização</label>
                            <input value={form.location} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('location', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" />
                        </div>
                    </div>
                </div>

                {/* Especificações Técnicas */}
                <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                    <h3 className="mb-4 font-semibold text-surface-900">Especificações Técnicas</h3>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Capacidade</label>
                            <div className="flex gap-2">
                                <input type="number" step="any" value={form.capacity} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('capacity', e.target.value)} onBlur={() => update('capacity', form.capacity ? normalizeMeasurementInput(form.capacity) : '')} className="flex-1 rounded-lg border border-surface-200 px-3 py-2.5 text-sm" />
                                <select value={form.capacity_unit} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => update('capacity_unit', e.target.value)} className="w-20 rounded-lg border border-surface-200 px-2 py-2.5 text-sm" aria-label="Unidade de capacidade">
                                    <option>kg</option><option>g</option><option>mg</option><option>t</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Resolução</label>
                            <input type="number" step="any" value={form.resolution} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('resolution', e.target.value)} onBlur={() => {
                                const normalizedResolution = normalizeMeasurementInput(form.resolution)
                                update('resolution', normalizedResolution)
                                if (form.capacity) {
                                    update('capacity', normalizeMeasurementInput(form.capacity))
                                }
                            }} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Classe de Precisão</label>
                            <select value={form.precision_class} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => update('precision_class', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" aria-label="Classe de precisão">
                                <option value="">—</option>
                                {Object.entries(classes).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Nº INMETRO</label>
                            <input value={form.inmetro_number} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('inmetro_number', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-surface-600">Intervalo Calibração (meses)</label>
                            <input type="number" value={form.calibration_interval_months} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('calibration_interval_months', e.target.value)} className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm" />
                        </div>
                        <div className="flex items-end">
                            <label className="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" checked={form.is_critical} onChange={(e: React.ChangeEvent<HTMLInputElement>) => update('is_critical', e.target.checked)} className="accent-red-600" />
                                <span className="text-[13px] font-medium text-surface-700">Equipamento Crítico</span>
                            </label>
                        </div>
                    </div>
                </div>

                {/* Observações */}
                <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                    <h3 className="mb-4 font-semibold text-surface-900">Observações</h3>
                    <textarea
                        value={form.notes}
                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => update('notes', e.target.value)}
                        rows={3}
                        className="w-full rounded-lg border border-surface-200 px-3 py-2.5 text-sm"
                        placeholder="Observações gerais sobre o equipamento..."
                    />
                </div>

                {/* Actions */}
                <div className="flex justify-end gap-3">
                    <button type="button" onClick={() => navigate('/equipamentos')} className="rounded-lg border border-surface-200 px-4 py-2.5 text-sm hover:bg-surface-50">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        disabled={mutation.isPending || !form.customer_id || !canCreate}
                        className="flex items-center gap-2 rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                    >
                        {mutation.isPending ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                        Salvar Equipamento
                    </button>
                </div>
            </form>
        </div>
    )
}
