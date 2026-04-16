import ScheduleCalendar from '@/components/os/ScheduleCalendar'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'

export default function OSCalendarPage() {
    const { hasPermission } = useAuthStore()
    const canViewWorkOrders = hasPermission('os.work_order.view')

    if (!canViewWorkOrders) {
        return (
            <div className="space-y-6 p-6">
                <PageHeader
                    title="Agenda de Ordens de Servico"
                    subtitle="Visualizacao de agendamentos operacionais"
                    backTo="/os"
                />
                <div className="rounded-xl border border-default bg-surface-0 p-6 text-sm text-surface-600 shadow-card">
                    Voce nao possui permissao para visualizar a agenda de ordens de servico.
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-6 p-6">
            <PageHeader
                title="Agenda de Ordens de Servico"
                subtitle="Visualizacao de agendamentos operacionais"
                backTo="/os"
            />
            <ScheduleCalendar />
        </div>
    )
}
