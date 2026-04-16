import { useState } from 'react'
import { Plus, Wrench } from 'lucide-react'
import { Button } from '@/components/ui/button'
import MaintenanceReportForm from './MaintenanceReportForm'
import MaintenanceReportList from './MaintenanceReportList'
import {
    useMaintenanceReports,
    useCreateMaintenanceReport,
    useUpdateMaintenanceReport,
    useApproveMaintenanceReport,
    useDeleteMaintenanceReport,
} from '@/hooks/useMaintenanceReports'
import type { MaintenanceReport } from '@/types/work-order'
import type { MaintenanceReportPayload } from '@/lib/maintenance-report-api'

interface MaintenanceReportsTabProps {
    workOrderId: number
    equipmentId: number
}

export default function MaintenanceReportsTab({ workOrderId, equipmentId }: MaintenanceReportsTabProps) {
    const [formOpen, setFormOpen] = useState(false)
    const [editingReport, setEditingReport] = useState<MaintenanceReport | null>(null)

    const { data: reports = [], isLoading } = useMaintenanceReports(workOrderId)
    const createMutation = useCreateMaintenanceReport()
    const updateMutation = useUpdateMaintenanceReport()
    const approveMutation = useApproveMaintenanceReport()
    const deleteMutation = useDeleteMaintenanceReport()

    const handleOpenNew = () => {
        setEditingReport(null)
        setFormOpen(true)
    }

    const handleEdit = (report: MaintenanceReport) => {
        setEditingReport(report)
        setFormOpen(true)
    }

    const handleClose = () => {
        setFormOpen(false)
        setEditingReport(null)
    }

    const handleSubmit = (data: MaintenanceReportPayload) => {
        if (editingReport) {
            updateMutation.mutate(
                { id: editingReport.id, data },
                { onSuccess: () => handleClose() },
            )
        } else {
            createMutation.mutate(data, { onSuccess: () => handleClose() })
        }
    }

    const handleApprove = (id: number) => {
        approveMutation.mutate({ id, workOrderId })
    }

    const handleDelete = (id: number) => {
        deleteMutation.mutate({ id, workOrderId })
    }

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-sm font-semibold text-surface-900 flex items-center gap-2">
                        <Wrench className="h-4 w-4 text-brand-500" />
                        Relatórios de Manutenção
                    </h3>
                    <Button size="sm" onClick={handleOpenNew}>
                        <Plus className="h-4 w-4 mr-1" /> Novo Relatório
                    </Button>
                </div>

                <MaintenanceReportList
                    reports={reports}
                    isLoading={isLoading}
                    onEdit={handleEdit}
                    onApprove={handleApprove}
                    onDelete={handleDelete}
                    isApproving={approveMutation.isPending}
                    isDeleting={deleteMutation.isPending}
                />
            </div>

            <MaintenanceReportForm
                workOrderId={workOrderId}
                equipmentId={equipmentId}
                report={editingReport}
                open={formOpen}
                onClose={handleClose}
                onSubmit={handleSubmit}
                isPending={createMutation.isPending || updateMutation.isPending}
            />
        </div>
    )
}
