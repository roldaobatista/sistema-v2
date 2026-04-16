import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useState } from 'react'
import {
    DollarSign, Save, Loader2, AlertTriangle, Settings,
} from 'lucide-react'
import api, { getApiErrorMessage } from '@/lib/api'
import { cn, formatCurrency } from '@/lib/utils'

interface ExpenseCategory {
    id: number
    name: string
    budget_limit: number | null
    current_month_total: number
    percentage_used: number
}

export function ExpenseLimitsConfigPage() {
    const queryClient = useQueryClient()
    const [editedLimits, setEditedLimits] = useState<Record<number, string>>({})

    const { data, isLoading } = useQuery<ExpenseCategory[]>({
        queryKey: ['expense-categories-limits'],
        queryFn: () => api.get('/expense-categories', {
            params: { with_usage: true },
        }).then(res => res.data?.data ?? res.data ?? []),
    })

    const saveMutation = useMutation({
        mutationFn: (updates: { id: number; budget_limit: number | null }[]) =>
            api.put('/expense-categories/batch-limits', { limits: updates }),
        onSuccess: () => {
            toast.success('Limites salvos com sucesso')
            setEditedLimits({})
            queryClient.invalidateQueries({ queryKey: ['expense-categories-limits'] })
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar limites')),
    })

    const categories: ExpenseCategory[] = Array.isArray(data) ? data : []

    const handleLimitChange = (id: number, value: string) => {
        setEditedLimits(prev => ({ ...prev, [id]: value }))
    }

    const handleSave = () => {
        const updates = Object.entries(editedLimits).map(([id, value]) => ({
            id: parseInt(id),
            budget_limit: value === '' ? null : parseFloat(value),
        }))

        if (updates.length === 0) {
            toast.info('Nenhuma alteração para salvar')
            return
        }

        saveMutation.mutate(updates)
    }

    const hasChanges = Object.keys(editedLimits).length > 0

    return (
        <div className="space-y-5 max-w-3xl">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">
                        Limites de Despesas
                    </h1>
                    <p className="mt-0.5 text-sm text-surface-500">
                        Configure limites orçamentários mensais por categoria de despesa.
                    </p>
                </div>
                {hasChanges && (
                    <button
                        onClick={handleSave}
                        disabled={saveMutation.isPending}
                        className="flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors disabled:opacity-50"
                    >
                        {saveMutation.isPending ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Save className="h-4 w-4" />
                        )}
                        Salvar Alterações
                    </button>
                )}
            </div>

            {isLoading ? (
                <div className="rounded-xl border border-default bg-surface-0 p-8 shadow-card text-center text-sm text-surface-500">
                    Carregando categorias...
                </div>
            ) : categories.length === 0 ? (
                <div className="rounded-xl border border-default bg-surface-0 p-12 shadow-card text-center">
                    <Settings className="mx-auto h-10 w-10 text-surface-300" />
                    <p className="mt-3 text-sm font-medium text-surface-600">Nenhuma categoria cadastrada</p>
                    <p className="mt-1 text-xs text-surface-400">Cadastre categorias de despesa primeiro.</p>
                </div>
            ) : (
                <div className="rounded-xl border border-default bg-surface-0 shadow-card overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-subtle bg-surface-50">
                                <th className="px-5 py-3 text-left text-xs font-semibold text-surface-600 uppercase tracking-wider">
                                    Categoria
                                </th>
                                <th className="px-5 py-3 text-right text-xs font-semibold text-surface-600 uppercase tracking-wider">
                                    Gasto Mês Atual
                                </th>
                                <th className="px-5 py-3 text-right text-xs font-semibold text-surface-600 uppercase tracking-wider">
                                    Limite Mensal (R$)
                                </th>
                                <th className="px-5 py-3 text-center text-xs font-semibold text-surface-600 uppercase tracking-wider">
                                    Uso
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-subtle">
                            {categories.map(cat => {
                                const editValue = editedLimits[cat.id]
                                const currentLimit = editValue !== undefined
                                    ? (editValue === '' ? null : parseFloat(editValue))
                                    : cat.budget_limit
                                const pct = currentLimit && currentLimit > 0
                                    ? Math.min(Math.round((cat.current_month_total / currentLimit) * 100), 100)
                                    : 0
                                const isOver = currentLimit !== null && cat.current_month_total > currentLimit
                                const isWarning = pct >= 80 && !isOver

                                return (
                                    <tr key={cat.id} className="hover:bg-surface-50 transition-colors">
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-2">
                                                <DollarSign className="h-4 w-4 text-surface-400" />
                                                <span className="text-sm font-medium text-surface-900">
                                                    {cat.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <span className={cn(
                                                'text-sm font-medium',
                                                isOver ? 'text-red-600' : 'text-surface-700'
                                            )}>
                                                {formatCurrency(cat.current_month_total)}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <input
                                                type="number"
                                                min="0"
                                                step="100"
                                                placeholder="Sem limite"
                                                value={editValue ?? (cat.budget_limit ?? '')}
                                                onChange={e => handleLimitChange(cat.id, e.target.value)}
                                                className={cn(
                                                    'w-32 rounded-lg border px-3 py-1.5 text-sm text-right outline-none transition-colors',
                                                    editValue !== undefined
                                                        ? 'border-brand-300 bg-brand-50 ring-1 ring-brand-200'
                                                        : 'border-default bg-surface-0 focus:border-brand-500'
                                                )}
                                            />
                                        </td>
                                        <td className="px-5 py-3">
                                            {currentLimit !== null && currentLimit > 0 ? (
                                                <div className="flex items-center justify-center gap-2">
                                                    <div className="w-20 h-2 rounded-full bg-surface-200 overflow-hidden">
                                                        <div
                                                            className={cn(
                                                                'h-full rounded-full transition-all',
                                                                isOver ? 'bg-red-500' :
                                                                    isWarning ? 'bg-amber-500' :
                                                                        'bg-emerald-500'
                                                            )}
                                                            style={{ width: `${Math.min(pct, 100)}%` }}
                                                        />
                                                    </div>
                                                    <span className={cn(
                                                        'text-xs font-semibold min-w-[2.5rem] text-right',
                                                        isOver ? 'text-red-600' :
                                                            isWarning ? 'text-amber-600' :
                                                                'text-surface-500'
                                                    )}>
                                                        {pct}%
                                                    </span>
                                                    {isOver && <AlertTriangle className="h-3.5 w-3.5 text-red-500" />}
                                                </div>
                                            ) : (
                                                <span className="flex justify-center text-xs text-surface-400">—</span>
                                            )}
                                        </td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Info */}
            <div className="rounded-xl border border-surface-200 bg-surface-50 p-4">
                <div className="flex items-start gap-2">
                    <AlertTriangle className="h-4 w-4 text-amber-500 mt-0.5 flex-shrink-0" />
                    <div className="text-xs text-surface-600 space-y-1">
                        <p><strong>Como funciona:</strong> Ao definir um limite, o sistema alerta quando 80% do orçamento é atingido e bloqueia novas despesas quando o limite é ultrapassado.</p>
                        <p>Deixe o campo vazio para não impor limite à categoria.</p>
                    </div>
                </div>
            </div>
        </div>
    )
}
