import { useQuery } from '@tanstack/react-query'
import { Plus, Receipt } from 'lucide-react'
import { financialApi } from '@/lib/financial-api'
import { queryKeys } from '@/lib/query-keys'
import { formatCurrency } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { useNavigate } from 'react-router-dom'
import { Skeleton } from '@/components/ui/skeleton'
import type { AccountPayableRow } from '@/types/financial'

export default function WoExpensesTab({ workOrderId }: { workOrderId: number }) {
    const navigate = useNavigate()

    const { data: res, isLoading } = useQuery({
        queryKey: [queryKeys.financial.payables, { work_order_id: workOrderId }],
        queryFn: () => financialApi.payables.list({ work_order_id: workOrderId, per_page: 50 }),
    })

    const accounts = res?.data ?? []

    const openNewExpense = () => {
        const params = new URLSearchParams()
        params.set('work_order_id', String(workOrderId))
        params.set('new', '1')
        navigate(`/financeiro/contas-pagar?${params.toString()}`)
    }

    if (isLoading) {
        return (
            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-4">
                <Skeleton className="h-4 w-48" />
                <Skeleton className="h-16 w-full" />
                <Skeleton className="h-16 w-full" />
            </div>
        )
    }

    const totalExpenses = accounts.reduce((acc: number, curr: AccountPayableRow) => acc + Number(curr.amount), 0)

    return (
        <div className="rounded-xl border border-default bg-surface-0 shadow-card overflow-hidden">
            <div className="flex items-center justify-between border-b border-subtle p-5">
                <div>
                    <h3 className="text-sm font-semibold text-surface-900 flex items-center gap-2">
                        <Receipt className="h-4 w-4 text-brand-500" />
                        Despesas e Contas a Pagar
                    </h3>
                    <p className="text-xs text-surface-500 mt-1">
                        Gerencie os custos associados a esta Ordem de Serviço
                    </p>
                </div>
                <div className="flex items-center gap-4">
                    <div className="text-right flex flex-col items-end">
                        <span className="text-xs text-surface-500 font-medium">Custo Total:</span>
                        <span className="text-sm font-bold text-surface-900">{formatCurrency(totalExpenses)}</span>
                    </div>
                    <button
                        onClick={openNewExpense}
                        className="flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition-colors"
                    >
                        <Plus className="h-3.5 w-3.5" />
                        Nova Despesa
                    </button>
                </div>
            </div>

            {accounts.length === 0 ? (
                <div className="p-8 text-center text-sm text-surface-400">
                    Nenhuma despesa lançada para esta OS.
                </div>
            ) : (
                <div className="divide-y divide-subtle">
                    {accounts.map((ap: AccountPayableRow) => (
                        <div key={ap.id} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-surface-50 transition-colors">
                            <div className="flex items-center gap-4">
                                <div className="h-10 w-10 flex items-center justify-center rounded-lg bg-surface-100 border border-subtle">
                                    <Receipt className="h-5 w-5 text-surface-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-surface-900">{ap.description}</p>
                                    <div className="flex items-center gap-2 text-xs text-surface-500 mt-1">
                                        <span>{ap.supplier_relation?.name ?? 'Sem fornecedor'}</span>
                                        <span>&bull;</span>
                                        <span>Venc: {new Date(ap.due_date).toLocaleDateString('pt-BR')}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center justify-between sm:w-auto gap-4">
                                <div className="text-right">
                                    <p className="text-sm font-bold text-surface-900">{formatCurrency(Number(ap.amount))}</p>
                                    <Badge variant={ap.status === 'paid' ? 'success' : ap.status === 'pending' ? 'warning' : 'destructive'} className="mt-1">
                                        {ap.status === 'paid' ? 'Pago' : ap.status === 'pending' ? 'Pendente' : 'Atrasado'}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    )
}
