import { useState } from 'react'
import { Pencil, Trash2, CheckCircle, Wrench, AlertTriangle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { Skeleton } from '@/components/ui/skeleton'
import type { MaintenanceReport } from '@/types/work-order'

interface MaintenanceReportListProps {
    reports: MaintenanceReport[]
    isLoading: boolean
    onEdit: (report: MaintenanceReport) => void
    onApprove: (id: number) => void
    onDelete: (id: number) => void
    isApproving?: boolean
    isDeleting?: boolean
}

const CONDITION_LABELS: Record<string, string> = {
    defective: 'Defeituoso',
    degraded: 'Degradado',
    functional: 'Funcional',
    unknown: 'Desconhecido',
    limited: 'Limitado',
    requires_calibration: 'Requer Calibração',
    not_repaired: 'Não Reparado',
}

const CONDITION_COLORS: Record<string, string> = {
    defective: 'bg-red-100 text-red-800',
    degraded: 'bg-amber-100 text-amber-800',
    functional: 'bg-green-100 text-green-800',
    unknown: 'bg-gray-100 text-gray-800',
    limited: 'bg-amber-100 text-amber-800',
    requires_calibration: 'bg-blue-100 text-blue-800',
    not_repaired: 'bg-red-100 text-red-800',
}

function formatDate(dateStr?: string | null): string {
    if (!dateStr) return '—'
    try {
        return new Date(dateStr).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    } catch {
        return dateStr
    }
}

export default function MaintenanceReportList({
    reports,
    isLoading,
    onEdit,
    onApprove,
    onDelete,
    isApproving,
    isDeleting,
}: MaintenanceReportListProps) {
    const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null)

    if (isLoading) {
        return (
            <div className="space-y-4">
                {[1, 2].map((i) => (
                    <div key={i} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        <Skeleton className="h-5 w-3/4 mb-3" />
                        <Skeleton className="h-4 w-1/2 mb-2" />
                        <Skeleton className="h-4 w-1/3" />
                    </div>
                ))}
            </div>
        )
    }

    if (reports.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-subtle bg-surface-50 py-12 text-center">
                <Wrench className="mx-auto h-10 w-10 text-surface-300 mb-3" />
                <p className="text-sm text-surface-500">Nenhum relatório de manutenção registrado</p>
                <p className="text-xs text-surface-400 mt-1">Clique em "Novo Relatório" para registrar uma manutenção</p>
            </div>
        )
    }

    return (
        <>
            <div className="space-y-4">
                {reports.map((report) => (
                    <div key={report.id} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                        {/* Header */}
                        <div className="flex items-start justify-between mb-3">
                            <div className="flex-1">
                                <div className="flex items-center gap-2 mb-1">
                                    <h4 className="text-sm font-semibold text-surface-900">Relatório #{report.id}</h4>
                                    {report.approver ? (
                                        <Badge variant="brand" className="text-xs">
                                            <CheckCircle className="h-3 w-3 mr-1" /> Aprovado
                                        </Badge>
                                    ) : (
                                        <Badge variant="warning" className="text-xs">Pendente</Badge>
                                    )}
                                    {report.requires_calibration_after && (
                                        <Badge variant="info" className="text-xs">
                                            <AlertTriangle className="h-3 w-3 mr-1" /> Requer Calibração
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-surface-600 line-clamp-2">{report.defect_found}</p>
                            </div>
                            <div className="flex items-center gap-1 ml-3">
                                {!report.approver && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onApprove(report.id)}
                                        disabled={isApproving}
                                        title="Aprovar"
                                        className="text-green-600 hover:text-green-800"
                                    >
                                        <CheckCircle className="h-4 w-4" />
                                    </Button>
                                )}
                                <Button variant="ghost" size="sm" onClick={() => onEdit(report)} title="Editar">
                                    <Pencil className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setDeleteConfirmId(report.id)}
                                    title="Excluir"
                                    className="text-red-500 hover:text-red-700"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        {/* Details grid */}
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div>
                                <span className="text-surface-400 block">Condição Antes</span>
                                <span className={`inline-block mt-0.5 px-2 py-0.5 rounded-full text-xs font-medium ${CONDITION_COLORS[report.condition_before] || 'bg-gray-100 text-gray-800'}`}>
                                    {CONDITION_LABELS[report.condition_before] || report.condition_before}
                                </span>
                            </div>
                            <div>
                                <span className="text-surface-400 block">Condição Depois</span>
                                <span className={`inline-block mt-0.5 px-2 py-0.5 rounded-full text-xs font-medium ${CONDITION_COLORS[report.condition_after] || 'bg-gray-100 text-gray-800'}`}>
                                    {CONDITION_LABELS[report.condition_after] || report.condition_after}
                                </span>
                            </div>
                            <div>
                                <span className="text-surface-400 block">Executante</span>
                                <span className="text-surface-700 font-medium">{report.performer?.name || '—'}</span>
                            </div>
                            <div>
                                <span className="text-surface-400 block">Data</span>
                                <span className="text-surface-700">{formatDate(report.created_at)}</span>
                            </div>
                        </div>

                        {/* Extra details */}
                        {(report.corrective_action || report.probable_cause) && (
                            <div className="mt-3 pt-3 border-t border-subtle grid grid-cols-2 gap-3 text-xs">
                                {report.probable_cause && (
                                    <div>
                                        <span className="text-surface-400 block">Causa Provável</span>
                                        <span className="text-surface-600">{report.probable_cause}</span>
                                    </div>
                                )}
                                {report.corrective_action && (
                                    <div>
                                        <span className="text-surface-400 block">Ação Corretiva</span>
                                        <span className="text-surface-600">{report.corrective_action}</span>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Parts replaced */}
                        {report.parts_replaced && report.parts_replaced.length > 0 && (
                            <div className="mt-3 pt-3 border-t border-subtle">
                                <span className="text-xs text-surface-400 block mb-1">Peças Substituídas</span>
                                <div className="flex flex-wrap gap-1">
                                    {report.parts_replaced.map((part, i) => (
                                        <span key={i} className="inline-flex items-center px-2 py-0.5 rounded-full bg-surface-100 text-xs text-surface-700">
                                            {part.name}{part.quantity && part.quantity > 1 ? ` (×${part.quantity})` : ''}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}

                        {report.approver && (
                            <div className="mt-3 pt-3 border-t border-subtle text-xs text-surface-400">
                                Aprovado por: <span className="text-surface-600 font-medium">{report.approver.name}</span>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {/* Delete confirmation modal */}
            <Modal
                open={deleteConfirmId !== null}
                onClose={() => setDeleteConfirmId(null)}
                title="Confirmar Exclusão"
            >
                <p className="text-sm text-surface-600 mb-6">
                    Tem certeza que deseja excluir este relatório de manutenção? Esta ação não pode ser desfeita.
                </p>
                <div className="flex justify-end gap-3">
                    <Button variant="ghost" onClick={() => setDeleteConfirmId(null)} disabled={isDeleting}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            if (deleteConfirmId) {
                                onDelete(deleteConfirmId)
                                setDeleteConfirmId(null)
                            }
                        }}
                        disabled={isDeleting}
                    >
                        {isDeleting ? 'Excluindo...' : 'Excluir'}
                    </Button>
                </div>
            </Modal>
        </>
    )
}
