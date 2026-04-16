import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowRightLeft, Clock, Loader2, Pencil, Plus, Shield, Trash2, User } from 'lucide-react'
import { formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { workOrderApi } from '@/lib/work-order-api'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

interface AuditEntry {
    id: string | number
    action: string
    action_label: string
    description: string
    entity_type: string | null
    entity_id: string | number | null
    user: { id: number; name: string } | null
    old_values: Record<string, unknown> | null
    new_values: Record<string, unknown> | null
    ip_address: string | null
    created_at: string
}

interface AuditTrailTabProps {
    workOrderId: number
}

interface ApiError {
    response?: {
        status?: number
        data?: { message?: string }
    }
}

const ACTION_ICONS: Record<string, typeof Clock> = {
    created: Plus,
    updated: Pencil,
    deleted: Trash2,
    status_changed: ArrowRightLeft,
}

const ACTION_COLORS: Record<string, string> = {
    created: 'text-emerald-500 bg-emerald-50',
    updated: 'text-brand-500 bg-brand-50',
    deleted: 'text-red-500 bg-red-50',
    status_changed: 'text-amber-500 bg-amber-50',
}

const FIELD_LABELS: Record<string, string> = {
    status: 'Status',
    priority: 'Prioridade',
    description: 'Descricao',
    technical_report: 'Laudo Tecnico',
    internal_notes: 'Notas Internas',
    discount: 'Desconto',
    total: 'Total',
    displacement_value: 'Deslocamento',
    assigned_to: 'Responsavel',
    driver_id: 'Motorista',
    quantity: 'Quantidade',
    unit_price: 'Preco Unitario',
    sla_due_at: 'Prazo SLA',
}

const AUDIT_TRAIL_QUERY_KEYS = {
    entries: (workOrderId: number) => ['work-orders', workOrderId, 'audit-trail'] as const,
}

interface AuditTrailPayload {
    entries: AuditEntry[]
    warningMessage: string | null
}

function parseApiError(error: unknown, fallbackMessage: string): { status?: number; message: string } {
    const apiError = error as ApiError
    return {
        status: apiError?.response?.status,
        message: apiError?.response?.data?.message ?? fallbackMessage,
    }
}

function normalizeAuditTrailPayload(response: { data?: { data?: AuditEntry[]; warning?: string | null } }): AuditTrailPayload {
    const payload = response.data

    return {
        entries: Array.isArray(payload?.data) ? payload.data : [],
        warningMessage: payload?.warning ?? null,
    }
}

export default function AuditTrailTab({ workOrderId }: AuditTrailTabProps) {
    const [expandedId, setExpandedId] = useState<string | number | null>(null)
    const auditTrailQuery = useQuery({
        queryKey: AUDIT_TRAIL_QUERY_KEYS.entries(workOrderId),
        queryFn: async () => normalizeAuditTrailPayload(await workOrderApi.auditTrail(workOrderId)),
        enabled: workOrderId > 0,
        retry: false,
    })

    const parsedError = auditTrailQuery.error
        ? parseApiError(auditTrailQuery.error, 'Erro ao carregar trilha de auditoria.')
        : null
    const accessDenied = parsedError?.status === 403
    const errorMessage = parsedError && parsedError.status !== 403 ? parsedError.message : null
    const entries = auditTrailQuery.data?.entries ?? []
    const warningMessage = auditTrailQuery.data?.warningMessage ?? null
    const loading = auditTrailQuery.isLoading

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center py-20 gap-3 text-surface-400">
                <Loader2 className="w-8 h-8 animate-spin" />
                <p className="text-sm font-medium">Carregando trilha de auditoria...</p>
            </div>
        )
    }

    if (accessDenied) {
        return (
            <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                <div className="flex flex-col items-center justify-center py-12 gap-3 text-center">
                    <p className="text-sm font-semibold text-surface-700">Sem permissao para visualizar auditoria desta OS.</p>
                    <Button variant="outline" onClick={() => void auditTrailQuery.refetch()}>Tentar novamente</Button>
                </div>
            </div>
        )
    }

    if (errorMessage) {
        return (
            <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
                <div className="flex flex-col items-center justify-center py-12 gap-3 text-center">
                    <p className="text-sm font-semibold text-surface-700">{errorMessage}</p>
                    <Button variant="outline" onClick={() => void auditTrailQuery.refetch()}>Recarregar auditoria</Button>
                </div>
            </div>
        )
    }

    if (entries.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-20 gap-3 text-center">
                <div className="w-16 h-16 rounded-3xl bg-surface-100 flex items-center justify-center text-surface-300">
                    <Shield className="w-8 h-8" />
                </div>
                <p className="text-sm font-medium text-surface-500">Nenhum registro de auditoria para esta OS.</p>
            </div>
        )
    }

    return (
        <div className="rounded-xl border border-default bg-surface-0 shadow-card overflow-hidden">
            <div className="px-5 py-4 border-b border-subtle bg-surface-50">
                <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-surface-900 flex items-center gap-2">
                        <Clock className="h-4 w-4 text-brand-500" />
                        Trilha de Auditoria
                    </h3>
                    <span className="text-xs text-surface-400 font-medium">{entries.length} registros</span>
                </div>
                {warningMessage ? (
                    <p className="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2 py-1">
                        {warningMessage}
                    </p>
                ) : null}
            </div>

            <div className="divide-y divide-subtle max-h-[600px] overflow-y-auto">
                {entries.map((entry: AuditEntry) => {
                    const IconCmp = ACTION_ICONS[entry.action] ?? Clock
                    const colorClass = ACTION_COLORS[entry.action] ?? 'text-surface-400 bg-surface-100'
                    const isExpanded = expandedId === entry.id
                    const changedFields = getChangedFields(entry)
                    const hasChanges = changedFields.length > 0

                    return (
                        <div key={entry.id} className="group">
                            <button
                                type="button"
                                className="w-full text-left px-5 py-3 flex items-start gap-3 hover:bg-surface-50 transition-colors"
                                onClick={() => hasChanges && setExpandedId(isExpanded ? null : entry.id)}
                            >
                                <div className={cn('w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5', colorClass)}>
                                    <IconCmp className="w-4 h-4" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-0.5">
                                        <span className="text-xs font-bold uppercase tracking-wider text-surface-400">{entry.action_label}</span>
                                        {entry.entity_type && entry.entity_type !== 'WorkOrder' ? (
                                            <span className="text-[10px] bg-surface-100 px-1.5 py-0.5 rounded font-medium text-surface-500">
                                                {entry.entity_type === 'WorkOrderItem' ? 'Item' : entry.entity_type}
                                            </span>
                                        ) : null}
                                    </div>
                                    <p className="text-sm text-surface-700 leading-snug">{entry.description}</p>
                                    <div className="flex items-center gap-3 mt-1.5">
                                        <span className="text-[11px] text-surface-400 flex items-center gap-1">
                                            <User className="w-3 h-3" />
                                            {entry.user?.name ?? 'Sistema'}
                                        </span>
                                        <span className="text-[11px] text-surface-400 tabular-nums">
                                            {formatDistanceToNow(new Date(entry.created_at), { locale: ptBR, addSuffix: true })}
                                        </span>
                                        {entry.ip_address ? (
                                            <span className="text-[10px] text-surface-300 tabular-nums">{entry.ip_address}</span>
                                        ) : null}
                                    </div>
                                </div>
                            </button>

                            {isExpanded && hasChanges ? (
                                <div className="px-5 pb-4 pl-16">
                                    <div className="rounded-lg border border-border overflow-hidden">
                                        <table className="w-full text-xs">
                                            <thead>
                                                <tr className="bg-surface-50">
                                                    <th className="text-left px-3 py-2 font-semibold text-surface-600">Campo</th>
                                                    <th className="text-left px-3 py-2 font-semibold text-red-500">Antes</th>
                                                    <th className="text-left px-3 py-2 font-semibold text-emerald-500">Depois</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-subtle">
                                                {changedFields.map(({ field, oldVal, newVal }) => (
                                                    <tr key={field} className="hover:bg-surface-50/50">
                                                        <td className="px-3 py-2 font-medium text-surface-700">{FIELD_LABELS[field] ?? field}</td>
                                                        <td className="px-3 py-2 text-red-600 line-through">{formatValue(oldVal)}</td>
                                                        <td className="px-3 py-2 text-emerald-600 font-medium">{formatValue(newVal)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    )
                })}
            </div>
        </div>
    )
}

function getChangedFields(entry: AuditEntry): Array<{ field: string; oldVal: unknown; newVal: unknown }> {
    const oldValues = entry.old_values ?? {}
    const newValues = entry.new_values ?? {}
    const allKeys = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])]

    return allKeys
        .filter(key => !['updated_at', 'created_at', 'deleted_at', 'id', 'tenant_id'].includes(key))
        .filter(key => JSON.stringify(oldValues[key]) !== JSON.stringify(newValues[key]))
        .map(key => ({ field: key, oldVal: oldValues[key], newVal: newValues[key] }))
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '-'
    }
    if (typeof value === 'boolean') {
        return value ? 'Sim' : 'Nao'
    }
    return String(value)
}
