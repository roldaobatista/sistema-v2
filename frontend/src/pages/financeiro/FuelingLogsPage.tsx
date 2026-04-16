import { useMemo, useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { Clock, CheckCircle, XCircle, Fuel, Eye, Pencil, RotateCcw, Trash2, Car, MapPin, Plus, Search, Loader2 } from 'lucide-react'
import { fuelingLogSchema } from './schemas'
import { IconButton } from '@/components/ui/iconbutton'
import { formatCurrency } from '@/lib/utils'
import { toast } from 'sonner'
import {
    useFuelingLogs, useCreateFuelingLog, useUpdateFuelingLog, useApproveFuelingLog,
    useDeleteFuelingLog, useResubmitFuelingLog,
    type FuelingLogFormData,
} from '@/hooks/useFuelingLogs'
import { useQuery } from '@tanstack/react-query'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { useAuthStore } from '@/stores/auth-store'
import api from '@/lib/api'

interface _LookupItem {
    id: number
    name: string
    slug?: string
}

const FUEL_TYPE_FALLBACK: Record<string, string> = {
    diesel: 'Diesel',
    diesel_s10: 'Diesel S10',
    gasolina: 'Gasolina',
    etanol: 'Etanol',
}

const statusConfig: Record<string, { label: string; variant: 'warning' | 'success' | 'danger'; icon: React.ElementType }> = {
    pending: { label: 'Pendente', variant: 'warning', icon: Clock },
    approved: { label: 'Aprovado', variant: 'success', icon: CheckCircle },
    rejected: { label: 'Rejeitado', variant: 'danger', icon: XCircle },
}

const emptyForm: FuelingLogFormData = {
    vehicle_plate: '', odometer_km: 0, fuel_type: 'diesel',
    liters: 0, price_per_liter: 0, total_amount: 0, date: new Date().toISOString().split('T')[0],
    gas_station: '', notes: '', work_order_id: null,
}

export function FuelingLogsPage() {
    const { hasPermission } = useAuthStore()

    const canCreate = hasPermission('expenses.fueling_log.create')
    const canUpdate = hasPermission('expenses.fueling_log.update')
    const canApprove = hasPermission('expenses.fueling_log.approve')
    const canDelete = hasPermission('expenses.fueling_log.delete')

    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState('')
    const [page, setPage] = useState(1)
    const [formOpen, setFormOpen] = useState(false)
    const [editingId, setEditingId] = useState<number | null>(null)
    const [deleteTarget, setDeleteTarget] = useState<number | null>(null)
    const [rejectTarget, setRejectTarget] = useState<number | null>(null)
    const [rejectReason, setRejectReason] = useState('')
    const [detailTarget, setDetailTarget] = useState<Record<string, unknown> | null>(null)

    const form = useForm<FuelingLogFormData>({
        resolver: zodResolver(fuelingLogSchema),
        defaultValues: emptyForm,
    })

    const { data, isLoading } = useFuelingLogs({ search, status: statusFilter, page, per_page: 25 })
    const createMutation = useCreateFuelingLog()
    const updateMutation = useUpdateFuelingLog()
    const approveMutation = useApproveFuelingLog()
    const deleteMutation = useDeleteFuelingLog()
    const resubmitMutation = useResubmitFuelingLog()
    const { data: fuelTypeItems = [] } = useQuery({
        queryKey: ['lookups', 'fueling-fuel-types'],
        queryFn: async () => {
            const { data } = await api.get('/lookups/fueling-fuel-types')
            const payload = data?.data ?? data
            return Array.isArray(payload) ? payload : []
        },
        staleTime: 5 * 60_000,
    })

    const logs = data?.data ?? []
    const pagination = data ? { current_page: data.current_page, last_page: data.last_page, total: data.total } : null
    const fuelTypeLabelByValue = useMemo(() => {
        const labelMap: Record<string, string> = { ...FUEL_TYPE_FALLBACK }
        fuelTypeItems.forEach((item) => {
            if (item.slug) {
                labelMap[item.slug] = item.name
            }
            labelMap[item.name] = item.name
        })
        return labelMap
    }, [fuelTypeItems])
    const fuelTypeSelectOptions = useMemo(() => {
        const options: Array<{ value: string; label: string }> = Object.entries(FUEL_TYPE_FALLBACK)
            .map(([value, label]) => ({ value, label }))

        fuelTypeItems.forEach((item) => {
            const value = item.slug ?? item.name
            if (!options.some((option) => option.value === value)) {
                options.push({ value, label: item.name })
            }
        })

        return options
    }, [fuelTypeItems])

    const openCreate = () => {
        setEditingId(null)
        form.reset(emptyForm)
        setFormOpen(true)
    }

    const openEdit = (log: { id: number; vehicle_plate: string; odometer_km: string | number; fuel_type: string; liters: string | number; price_per_liter: string | number; total_amount: string | number; fueling_date?: string; date?: string; gas_station_name?: string; notes?: string; work_order_id?: number | null }) => {
        setEditingId(log.id)
        form.reset({
            vehicle_plate: log.vehicle_plate,
            odometer_km: Number(log.odometer_km),
            fuel_type: log.fuel_type,
            liters: Number(log.liters),
            price_per_liter: Number(log.price_per_liter),
            total_amount: Number(log.total_amount),
            date: log.fueling_date || log.date || '',
            gas_station: log.gas_station_name || '',
            notes: log.notes || '',
            work_order_id: log.work_order_id,
        })
        setFormOpen(true)
    }

    const handleSubmit = (data: FuelingLogFormData) => {
        if (editingId) {
            updateMutation.mutate({ id: editingId, ...data } as Parameters<typeof updateMutation.mutate>[0], {
                onSuccess: () => { setFormOpen(false); setEditingId(null) },
                onError: () => toast.error('Erro ao salvar abastecimento'),
            })
        } else {
            createMutation.mutate(data, {
                onSuccess: () => { setFormOpen(false); form.reset(emptyForm) },
                onError: () => toast.error('Erro ao registrar abastecimento'),
            })
        }
    }

    const handleReject = () => {
        if (!rejectTarget || !rejectReason.trim()) return
        approveMutation.mutate(
            { id: rejectTarget, action: 'reject', rejection_reason: rejectReason.trim() },
            { onSuccess: () => { setRejectTarget(null); setRejectReason('') } },
        )
    }

    const handleLitersChange = (liters: number) => {
        const total = parseFloat((liters * (form.getValues('price_per_liter') || 0)).toFixed(2))
        form.setValue('liters', liters)
        form.setValue('total_amount', total)
    }

    const handlePriceChange = (price: number) => {
        const total = parseFloat(((form.getValues('liters') || 0) * price).toFixed(2))
        form.setValue('price_per_liter', price)
        form.setValue('total_amount', total)
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-xl font-bold text-surface-900 flex items-center gap-2">
                        <Fuel className="h-6 w-6 text-brand-600" /> Controle de Abastecimento
                    </h1>
                    <p className="text-sm text-surface-500 mt-1">
                        {pagination ? `${pagination.total ?? 0} registros` : 'Carregando...'}
                    </p>
                </div>
                {canCreate && (
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 h-4 w-4" /> Novo Abastecimento
                    </Button>
                )}
            </div>

            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-col sm:flex-row gap-3">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
                            <input type="text" placeholder="Buscar por placa ou posto..."
                                value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
                                className="w-full pl-10 pr-4 py-2 rounded-lg border border-surface-200 text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500" />
                        </div>
                        <select value={statusFilter} onChange={e => { setStatusFilter(e.target.value); setPage(1) }}
                            title="Filtrar por status"
                            className="px-3 py-2 rounded-lg border border-surface-200 text-sm">
                            <option value="">Todos os status</option>
                            <option value="pending">Pendente</option>
                            <option value="approved">Aprovado</option>
                            <option value="rejected">Rejeitado</option>
                        </select>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="p-12 text-center">
                            <Loader2 className="h-8 w-8 animate-spin text-brand-600 mx-auto" />
                            <p className="text-sm text-surface-500 mt-2">Carregando registros...</p>
                        </div>
                    ) : logs.length === 0 ? (
                        <div className="p-12 text-center">
                            <Fuel className="h-12 w-12 text-surface-300 mx-auto" />
                            <p className="text-surface-500 mt-3 font-medium">Nenhum abastecimento registrado</p>
                            {canCreate && (
                                <Button variant="outline" size="sm" className="mt-3" onClick={openCreate}>
                                    <Plus className="mr-1 h-4 w-4" /> Registrar primeiro abastecimento
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-surface-50 border-b border-surface-200">
                                    <tr>
                                        <th className="text-left px-4 py-3 font-medium text-surface-600">Data</th>
                                        <th className="text-left px-4 py-3 font-medium text-surface-600">Motorista</th>
                                        <th className="text-left px-4 py-3 font-medium text-surface-600">Placa</th>
                                        <th className="text-left px-4 py-3 font-medium text-surface-600">Combustível</th>
                                        <th className="text-right px-4 py-3 font-medium text-surface-600">Litros</th>
                                        <th className="text-right px-4 py-3 font-medium text-surface-600">R$/L</th>
                                        <th className="text-right px-4 py-3 font-medium text-surface-600">Total</th>
                                        <th className="text-right px-4 py-3 font-medium text-surface-600">Km</th>
                                        <th className="text-center px-4 py-3 font-medium text-surface-600">Status</th>
                                        <th className="text-right px-4 py-3 font-medium text-surface-600">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-surface-100">
                                    {(logs || []).map((log: { id: number; status: string; vehicle_plate: string; fuel_type: string; liters: string | number; price_per_liter: string | number; total_amount: string | number; odometer_km: string | number; fueling_date?: string; date?: string; gas_station_name?: string; notes?: string; work_order_id?: number | null; user?: { name: string } }) => {
                                        const sc = statusConfig[log.status] || statusConfig.pending
                                        return (
                                            <tr key={log.id} className="hover:bg-surface-50 transition-colors">
                                                <td className="px-4 py-3 whitespace-nowrap">{new Date((log.fueling_date || log.date) + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                                <td className="px-4 py-3">{log.user?.name || '—'}</td>
                                                <td className="px-4 py-3 font-mono text-xs">{log.vehicle_plate}</td>
                                                <td className="px-4 py-3">{fuelTypeLabelByValue[log.fuel_type] ?? log.fuel_type}</td>
                                                <td className="px-4 py-3 text-right">{Number(log.liters).toFixed(2)}</td>
                                                <td className="px-4 py-3 text-right">{Number(log.price_per_liter).toFixed(4)}</td>
                                                <td className="px-4 py-3 text-right font-semibold">{formatCurrency(Number(log.total_amount))}</td>
                                                <td className="px-4 py-3 text-right">{Number(log.odometer_km).toLocaleString('pt-BR')}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <Badge variant={sc.variant}>{sc.label}</Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <IconButton label="Ver detalhes" icon={<Eye className="h-4 w-4" />} onClick={() => setDetailTarget(log)} />
                                                        {canUpdate && log.status === 'pending' && (
                                                            <IconButton label="Editar" icon={<Pencil className="h-4 w-4" />} onClick={() => openEdit(log)} className="hover:text-brand-600" />
                                                        )}
                                                        {canApprove && log.status === 'pending' && (
                                                            <>
                                                                <IconButton label="Aprovar" icon={<CheckCircle className="h-4 w-4" />}
                                                                    onClick={() => approveMutation.mutate({ id: log.id, action: 'approve' })}
                                                                    className="hover:text-emerald-600" />
                                                                <IconButton label="Rejeitar" icon={<XCircle className="h-4 w-4" />}
                                                                    onClick={() => { setRejectTarget(log.id); setRejectReason('') }}
                                                                    className="hover:text-red-600" />
                                                            </>
                                                        )}
                                                        {log.status === 'rejected' && (canUpdate || canApprove) && (
                                                            <IconButton label="Resubmeter" icon={<RotateCcw className="h-4 w-4" />}
                                                                onClick={() => resubmitMutation.mutate(log.id)}
                                                                className="hover:text-amber-600" />
                                                        )}
                                                        {canDelete && log.status === 'pending' && (
                                                            <IconButton label="Excluir" icon={<Trash2 className="h-4 w-4" />}
                                                                onClick={() => setDeleteTarget(log.id)}
                                                                className="hover:text-red-600" />
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination && pagination.last_page > 1 && (
                        <div className="flex items-center justify-between p-4 border-t border-surface-200">
                            <p className="text-sm text-surface-500">
                                Página {pagination.current_page} de {pagination.last_page} ({pagination.total} registros)
                            </p>
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage(p => p - 1)}>Anterior</Button>
                                <Button variant="outline" size="sm" disabled={page === pagination.last_page} onClick={() => setPage(p => p + 1)}>Próxima</Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Modal Criar/Editar */}
            <Modal open={formOpen} onOpenChange={(v) => { setFormOpen(v); if (!v) setEditingId(null) }} title={editingId ? 'Editar Abastecimento' : 'Registrar Abastecimento'} size="lg">
                <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <Controller control={form.control} name="vehicle_plate" render={({ field, fieldState }) => (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">Placa do Veículo *</label>
                                <div className="relative">
                                    <Car className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
                                    <input type="text" {...field}
                                        onChange={e => field.onChange(e.target.value.toUpperCase())}
                                        placeholder="ABC-1234" maxLength={10}
                                        className={`w-full pl-10 pr-3 py-2 rounded-lg border text-sm ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-surface-200 focus:ring-brand-500 focus:border-brand-500'}`} />
                                </div>
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />

                        <Controller control={form.control} name="date" render={({ field, fieldState }) => (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">Data *</label>
                                <input type="date" {...field} value={field.value || ''}
                                    className={`w-full px-3 py-2 rounded-lg border text-sm ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-surface-200 focus:ring-brand-500 focus:border-brand-500'}`} />
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Controller control={form.control} name="fuel_type" render={({ field, fieldState }) => (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">Combustível *</label>
                                <select {...field} value={field.value || ''} title="Tipo de combustível"
                                    className={`w-full px-3 py-2 rounded-lg border text-sm ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-surface-200 focus:ring-brand-500 focus:border-brand-500'}`}>
                                    {fuelTypeSelectOptions.map((fuelType) => (
                                        <option key={fuelType.value} value={fuelType.value}>{fuelType.label}</option>
                                    ))}
                                </select>
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />

                        <Controller control={form.control} name="odometer_km" render={({ field, fieldState }) => (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">Odômetro (km) *</label>
                                <input type="number" {...field} value={field.value === 0 && !fieldState.isTouched ? '' : field.value}
                                    onChange={e => field.onChange(Number(e.target.value))}
                                    className={`w-full px-3 py-2 rounded-lg border text-sm ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-surface-200 focus:ring-brand-500 focus:border-brand-500'}`} />
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <Controller control={form.control} name="liters" render={({ field, fieldState }) => (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">Litros *</label>
                                <input type="number" step="0.01" {...field} value={field.value === 0 && !fieldState.isTouched ? '' : field.value}
                                    onChange={e => handleLitersChange(Number(e.target.value))}
                                    className={`w-full px-3 py-2 rounded-lg border text-sm ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-surface-200 focus:ring-brand-500 focus:border-brand-500'}`} />
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />

                        <Controller control={form.control} name="price_per_liter" render={({ field, fieldState }) => (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">R$/Litro *</label>
                                <CurrencyInput value={field.value || 0}
                                    onChange={(val) => handlePriceChange(val)}
                                    className={`w-full ${fieldState.error ? 'border-red-500' : ''}`} />
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />

                        <Controller control={form.control} name="total_amount" render={({ field, fieldState }) => (
                            <div>
                                <label className="block text-sm font-medium text-surface-700 mb-1">Total (R$)</label>
                                <CurrencyInput value={field.value || 0} readOnly
                                    className={`w-full bg-surface-50 font-semibold ${fieldState.error ? 'border-red-500' : ''}`} />
                                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                            </div>
                        )} />
                    </div>

                    <Controller control={form.control} name="gas_station" render={({ field, fieldState }) => (
                        <div>
                            <label className="block text-sm font-medium text-surface-700 mb-1">
                                <MapPin className="inline h-3.5 w-3.5 mr-1" /> Posto / Localização
                            </label>
                            <input type="text" {...field} value={field.value || ''}
                                placeholder="Nome do posto ou localização"
                                className={`w-full px-3 py-2 rounded-lg border text-sm ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-surface-200 focus:ring-brand-500 focus:border-brand-500'}`} />
                            {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                        </div>
                    )} />

                    <Controller control={form.control} name="notes" render={({ field, fieldState }) => (
                        <div>
                            <label className="block text-sm font-medium text-surface-700 mb-1">Observações</label>
                            <textarea {...field} value={field.value || ''} rows={2}
                                className={`w-full px-3 py-2 rounded-lg border text-sm resize-none ${fieldState.error ? 'border-red-500 focus:ring-red-500/50' : 'border-surface-200 focus:ring-brand-500 focus:border-brand-500'}`} />
                            {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                        </div>
                    )} />

                    <div className="flex justify-end gap-3 pt-2 border-t">
                        <Button variant="outline" type="button" onClick={() => { setFormOpen(false); setEditingId(null) }}>Cancelar</Button>
                        <Button type="submit"
                            disabled={createMutation.isPending || updateMutation.isPending}>
                            {(createMutation.isPending || updateMutation.isPending) && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />}
                            {editingId ? 'Salvar' : 'Registrar'}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal Detalhes */}
            <Modal open={!!detailTarget} onOpenChange={() => setDetailTarget(null)} title="Detalhes do Abastecimento" size="lg">
                {detailTarget && (
                    <div className="grid gap-4 sm:grid-cols-2">
                                                <div><span className="text-xs text-surface-500">Motorista</span><p className="text-sm font-medium">{detailTarget.user?.name ?? '—'}</p></div>
                                                <div><span className="text-xs text-surface-500">Placa</span><p className="text-sm font-mono font-medium">{detailTarget.vehicle_plate}</p></div>
                        <div><span className="text-xs text-surface-500">Data</span><p className="text-sm">{new Date((detailTarget.fueling_date || detailTarget.date) + 'T00:00:00').toLocaleDateString('pt-BR')}</p></div>
                                                <div><span className="text-xs text-surface-500">Combustível</span><p className="text-sm">{fuelTypeLabelByValue[detailTarget.fuel_type] ?? detailTarget.fuel_type ?? '—'}</p></div>
                        <div><span className="text-xs text-surface-500">Litros</span><p className="text-sm">{Number(detailTarget.liters).toFixed(2)}</p></div>
                        <div><span className="text-xs text-surface-500">R$/Litro</span><p className="text-sm">{Number(detailTarget.price_per_liter).toFixed(4)}</p></div>
                        <div><span className="text-xs text-surface-500">Total</span><p className="text-sm font-bold">{formatCurrency(Number(detailTarget.total_amount))}</p></div>
                        <div><span className="text-xs text-surface-500">Odômetro</span><p className="text-sm">{Number(detailTarget.odometer_km).toLocaleString('pt-BR')} km</p></div>
                                                {detailTarget.gas_station_name && <div><span className="text-xs text-surface-500">Posto</span><p className="text-sm">{detailTarget.gas_station_name}</p></div>}
                                                <div><span className="text-xs text-surface-500">Status</span><Badge variant={statusConfig[detailTarget.status]?.variant}>{statusConfig[detailTarget.status]?.label}</Badge></div>
                                                {detailTarget.approver && <div><span className="text-xs text-surface-500">Aprovado por</span><p className="text-sm">{detailTarget.approver.name}</p></div>}
                                                {detailTarget.rejection_reason && (
                                                        <div className="col-span-2"><span className="text-xs text-surface-500">Motivo da rejeição</span><p className="text-sm text-red-600">{detailTarget.rejection_reason}</p></div>
                        )}
                                                {detailTarget.receipt_path && (
                            <div><span className="text-xs text-surface-500">Comprovante</span>
                                <p className="text-sm text-brand-600 underline">
                                    <a href={`${api.defaults.baseURL?.replace('/api', '')}${detailTarget.receipt_path}`} target="_blank" rel="noreferrer">Ver comprovante</a>
                                </p>
                            </div>
                        )}
                                                {detailTarget.notes && <div className="col-span-2"><span className="text-xs text-surface-500">Observações</span><p className="text-sm text-surface-600">{detailTarget.notes}</p></div>}
                                                {detailTarget.work_order && <div><span className="text-xs text-surface-500">OS</span><p className="text-sm text-brand-600 font-medium">{detailTarget.work_order.os_number}</p></div>}
                    </div>
                )}
            </Modal>

            {/* Modal Rejeitar */}
            <Modal open={rejectTarget !== null} onOpenChange={() => setRejectTarget(null)} title="Rejeitar Abastecimento">
                <form onSubmit={e => { e.preventDefault(); handleReject() }} className="space-y-4">
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-surface-700">Motivo da rejeição *</label>
                        <textarea value={rejectReason} onChange={e => setRejectReason(e.target.value)} rows={3} required
                            className="w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                            placeholder="Informe o motivo da rejeição..." />
                    </div>
                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button variant="outline" type="button" onClick={() => setRejectTarget(null)}>Cancelar</Button>
                        <Button type="submit" className="bg-red-600 hover:bg-red-700" loading={approveMutation.isPending} disabled={approveMutation.isPending || !rejectReason.trim()}>Rejeitar</Button>
                    </div>
                </form>
            </Modal>

            {/* Modal Excluir */}
            <Modal open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)} title="Excluir Registro?">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Tem certeza que deseja excluir este registro de abastecimento? Esta ação não pode ser desfeita.</p>
                    <div className="flex justify-end gap-3 border-t pt-4">
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
                        <Button className="bg-red-600 hover:bg-red-700"
                            onClick={() => { if (deleteTarget) { deleteMutation.mutate(deleteTarget, { onSuccess: () => setDeleteTarget(null) }) } }}
                            disabled={deleteMutation.isPending} loading={deleteMutation.isPending}>
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
