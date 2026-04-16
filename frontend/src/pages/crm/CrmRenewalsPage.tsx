import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { RefreshCw, FileCheck, AlertTriangle, CheckCircle, Clock, XCircle } from 'lucide-react'
import { crmFeaturesApi } from '@/lib/crm-features-api'
import type { CrmContractRenewal } from '@/lib/crm-features-api'
import { getApiErrorMessage } from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { TableSkeleton } from '@/components/ui/tableskeleton'
import { EmptyState } from '@/components/ui/emptystate'
import { toast } from 'sonner'
import { CurrencyInput } from '@/components/common/CurrencyInput'

const fmtBRL = (value: number | string) => Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDate = (date: string) => new Date(date).toLocaleDateString('pt-BR')

const STATUS_MAP: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'danger' | 'secondary' }> = {
    pending: { label: 'Pendente', variant: 'warning' },
    contacted: { label: 'Contactado', variant: 'info' },
    renewed: { label: 'Renovado', variant: 'success' },
    lost: { label: 'Perdido', variant: 'danger' },
    cancelled: { label: 'Cancelado', variant: 'secondary' },
}

export function CrmRenewalsPage() {
    const qc = useQueryClient()
    const [updateModal, setUpdateModal] = useState<CrmContractRenewal | null>(null)
    const [selectedStatus, setSelectedStatus] = useState('')
    const [renewalValue, setRenewalValue] = useState('')
    const [filterStatus, setFilterStatus] = useState('')

    const { data: renewals = [], isLoading } = useQuery<CrmContractRenewal[]>({
        queryKey: ['crm-renewals', filterStatus],
        queryFn: () => crmFeaturesApi.getRenewals(filterStatus ? { status: filterStatus } : undefined),
    })

    const generateMut = useMutation({
        mutationFn: crmFeaturesApi.generateRenewals,
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['crm-renewals'] })
            toast.success('Renovacoes geradas com sucesso')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao gerar renovacoes')),
    })

    const updateMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<CrmContractRenewal> }) => crmFeaturesApi.updateRenewal(id, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['crm-renewals'] })
            setUpdateModal(null)
            toast.success('Renovacao atualizada com sucesso')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao atualizar renovacao')),
    })

    const openUpdateModal = (renewal: CrmContractRenewal) => {
        setUpdateModal(renewal)
        setSelectedStatus(renewal.status)
        setRenewalValue(renewal.renewal_value?.toString() ?? renewal.current_value?.toString() ?? '')
    }

    const handleUpdate = () => {
        if (!updateModal) return

        updateMut.mutate({
            id: updateModal.id,
            data: {
                status: selectedStatus,
                renewal_value: Number(renewalValue) || null,
            },
        })
    }

    const statusCounts = renewals.reduce<Record<string, number>>((acc, renewal) => {
        acc[renewal.status] = (acc[renewal.status] || 0) + 1
        return acc
    }, {})

    return (
        <div className="space-y-6">
            <PageHeader
                title="Renovacoes de Contrato"
                subtitle="Gerencie e acompanhe as renovacoes de contratos dos clientes."
                count={renewals.length}
                icon={FileCheck}
            >
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => qc.invalidateQueries({ queryKey: ['crm-renewals'] })}
                    icon={<RefreshCw className="h-4 w-4" />}
                >
                    Atualizar
                </Button>
                <Button
                    size="sm"
                    onClick={() => generateMut.mutate()}
                    loading={generateMut.isPending}
                    icon={<FileCheck className="h-4 w-4" />}
                >
                    Gerar Renovacoes
                </Button>
            </PageHeader>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                {[
                    { key: 'pending', icon: Clock, color: 'amber', label: 'Pendentes' },
                    { key: 'contacted', icon: AlertTriangle, color: 'sky', label: 'Contactados' },
                    { key: 'renewed', icon: CheckCircle, color: 'emerald', label: 'Renovados' },
                    { key: 'lost', icon: XCircle, color: 'red', label: 'Perdidos' },
                ].map(({ key, icon: Icon, color, label }) => (
                    <div key={key} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className={`flex h-10 w-10 items-center justify-center rounded-full bg-${color}-100 text-${color}-600`}>
                                <Icon className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-surface-500">{label}</p>
                                <h3 className="text-2xl font-bold text-surface-900">{statusCounts[key] ?? 0}</h3>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-default p-4">
                    <h2 className="font-semibold text-surface-900">Lista de Renovacoes</h2>
                    <div className="flex items-center gap-2">
                        <label className="text-xs font-medium text-surface-500">Status:</label>
                        <select
                            value={filterStatus}
                            onChange={(event) => setFilterStatus(event.target.value)}
                            className="h-8 w-32 rounded-lg border-default px-2 text-xs"
                        >
                            <option value="">Todos</option>
                            {Object.entries(STATUS_MAP).map(([key, value]) => (
                                <option key={key} value={key}>{value.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {isLoading ? (
                    <TableSkeleton rows={6} cols={6} />
                ) : renewals.length === 0 ? (
                    <EmptyState
                        icon={FileCheck}
                        title="Nenhuma renovacao encontrada"
                        message='Clique em "Gerar Renovacoes" para identificar contratos proximos do vencimento.'
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-default bg-surface-50 text-surface-500">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Cliente</th>
                                    <th className="px-4 py-3 text-left font-medium">Negocio</th>
                                    <th className="px-4 py-3 text-center font-medium">Vencimento</th>
                                    <th className="px-4 py-3 text-right font-medium">Valor Atual</th>
                                    <th className="px-4 py-3 text-right font-medium">Valor Renovacao</th>
                                    <th className="px-4 py-3 text-center font-medium">Status</th>
                                    <th className="px-4 py-3 text-right font-medium">Acoes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {renewals.map((renewal) => {
                                    const status = STATUS_MAP[renewal.status] ?? STATUS_MAP.pending
                                    return (
                                        <tr key={renewal.id} className="transition-colors hover:bg-surface-50">
                                            <td className="px-4 py-3 font-medium text-surface-900">
                                                {renewal.customer?.name ?? `#${renewal.customer_id}`}
                                            </td>
                                            <td className="px-4 py-3 text-surface-600">
                                                {renewal.deal?.title ?? '-'}
                                            </td>
                                            <td className="px-4 py-3 text-center text-surface-600">
                                                {fmtDate(renewal.contract_end_date)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-surface-700">
                                                {fmtBRL(renewal.current_value)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums font-semibold text-emerald-600">
                                                {renewal.renewal_value ? fmtBRL(renewal.renewal_value) : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge variant={status.variant}>{status.label}</Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 text-xs"
                                                    onClick={() => openUpdateModal(renewal)}
                                                >
                                                    Atualizar
                                                </Button>
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Modal
                open={!!updateModal}
                onOpenChange={() => setUpdateModal(null)}
                title="Atualizar Renovacao"
            >
                <div className="space-y-4">
                    <div className="rounded-lg bg-surface-50 p-3 text-sm">
                        <p className="font-medium text-surface-900">{updateModal?.customer?.name}</p>
                        <p className="text-surface-500">Vencimento: {updateModal?.contract_end_date ? fmtDate(updateModal.contract_end_date) : '-'}</p>
                        <p className="text-surface-500">Valor atual: {fmtBRL(updateModal?.current_value ?? 0)}</p>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-surface-700">Status</label>
                        <select
                            value={selectedStatus}
                            onChange={(event) => setSelectedStatus(event.target.value)}
                            className="w-full rounded-lg border-default text-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                            {Object.entries(STATUS_MAP).map(([key, value]) => (
                                <option key={key} value={key}>{value.label}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-surface-700">Valor da Renovacao (R$)</label>
                        <CurrencyInput
                            value={Number(renewalValue) || 0}
                            onChange={(value) => setRenewalValue(String(value))}
                            className="w-full rounded-lg border-default px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500"
                            placeholder="0,00"
                        />
                    </div>

                    <div className="flex justify-end gap-2 border-t border-surface-100 pt-4">
                        <Button variant="outline" onClick={() => setUpdateModal(null)}>Cancelar</Button>
                        <Button onClick={handleUpdate} loading={updateMut.isPending}>Salvar</Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
