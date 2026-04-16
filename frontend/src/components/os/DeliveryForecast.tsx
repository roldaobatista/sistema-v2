import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Truck, Calendar } from 'lucide-react'
import { workOrderApi } from '@/lib/work-order-api'
import { queryKeys } from '@/lib/query-keys'
import { getApiErrorMessage } from '@/lib/utils'
import { toast } from 'sonner'

interface DeliveryForecastProps {
    workOrderId: number
    currentForecast?: string | null
    canEdit?: boolean
}

export default function DeliveryForecast({
    workOrderId,
    currentForecast,
    canEdit = true,
}: DeliveryForecastProps) {
    const qc = useQueryClient()
    const [date, setDate] = useState(currentForecast ?? '')
    const [editing, setEditing] = useState(false)

    useEffect(() => {
        setDate(currentForecast ?? '')
    }, [currentForecast])

    useEffect(() => {
        if (!canEdit) {
            setEditing(false)
        }
    }, [canEdit])

    const saveMut = useMutation({
        mutationFn: (deliveryForecast: string) =>
            workOrderApi.update(workOrderId, { delivery_forecast: deliveryForecast }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            toast.success('Previsao atualizada')
            setEditing(false)
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao atualizar previsao')),
    })

    const formatDate = (value: string) =>
        new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })

    const isOverdue = date ? new Date(`${date}T23:59:59`) < new Date() : false

    return (
        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                <Truck className="h-4 w-4 text-brand-500" />
                Previsao de Entrega
            </h3>

            {editing && canEdit ? (
                <div className="flex gap-2">
                    <input
                        type="date"
                        value={date}
                        onChange={(event) => setDate(event.target.value)}
                        aria-label="Data de previsao de entrega"
                        className="flex-1 rounded-lg border border-subtle bg-surface-50 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    />
                    <button
                        onClick={() => date && saveMut.mutate(date)}
                        disabled={!date || saveMut.isPending}
                        className="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600 disabled:opacity-50"
                    >
                        Salvar
                    </button>
                </div>
            ) : (
                <div className="w-full text-left">
                    {date ? (
                        <div className="flex items-center gap-2">
                            <Calendar className={`h-4 w-4 ${isOverdue ? 'text-red-500' : 'text-emerald-500'}`} />
                            <span className={`text-sm font-medium ${isOverdue ? 'text-red-600' : 'text-surface-700'}`}>
                                {formatDate(date)}
                            </span>
                            {isOverdue && (
                                <span className="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">Atrasado</span>
                            )}
                        </div>
                    ) : (
                        <span className={`text-xs ${canEdit ? 'text-surface-400 hover:text-brand-500' : 'text-surface-400'}`}>
                            {canEdit ? '+ Definir previsao de entrega' : 'Nenhuma previsao informada'}
                        </span>
                    )}
                    {canEdit && (
                        <button
                            onClick={() => setEditing(true)}
                            className="mt-2 text-xs font-medium text-brand-600 hover:text-brand-700"
                        >
                            {date ? 'Editar previsao' : 'Definir previsao'}
                        </button>
                    )}
                </div>
            )}
        </div>
    )
}
