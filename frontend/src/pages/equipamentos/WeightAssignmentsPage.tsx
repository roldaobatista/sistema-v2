import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeftRight, Plus, RotateCcw, User } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { EmptyState } from '@/components/ui/emptystate'
import { PageHeader } from '@/components/ui/pageheader'
import { useAuthStore } from '@/stores/auth-store'
import type { EquipmentTechnicianOption, StandardWeight, WeightAssignmentRecord } from '@/types/equipment'
import { toast } from 'sonner'

type AssignmentFormState = {
    standard_weight_id: string
    user_id: string
    notes: string
}

const initialFormState: AssignmentFormState = {
    standard_weight_id: '',
    user_id: '',
    notes: '',
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleDateString('pt-BR')
}

function buildWeightLabel(assignment: WeightAssignmentRecord): string {
    if (assignment.weight_code) {
        const nominalValue = assignment.weight_nominal_value ?? '—'
        const unit = assignment.weight_unit ?? ''
        return `${assignment.weight_code} - ${nominalValue} ${unit}`.trim()
    }

    return `#${assignment.standard_weight_id}`
}

export default function WeightAssignmentsPage() {
    const { hasPermission, hasRole } = useAuthStore()
    const queryClient = useQueryClient()
    const [showDialog, setShowDialog] = useState(false)
    const [form, setForm] = useState<AssignmentFormState>(initialFormState)
    const canManageAssignments = hasRole('super_admin') || hasPermission('equipments.standard_weight.update')

    const assignmentsQuery = useQuery({
        queryKey: ['weight-assignments'],
        queryFn: async () => unwrapData<WeightAssignmentRecord[]>(await api.get('/weight-assignments')),
    })

    const weightsQuery = useQuery({
        queryKey: ['weight-assignments-weights'],
        queryFn: async () => unwrapData<StandardWeight[]>(await api.get('/standard-weights', {
            params: { per_page: 200, status: 'active' },
        })),
    })

    const techniciansQuery = useQuery({
        queryKey: ['weight-assignments-technicians'],
        queryFn: async () => unwrapData<EquipmentTechnicianOption[]>(await api.get('/technicians/options')),
    })

    const closeDialog = () => {
        setShowDialog(false)
        setForm(initialFormState)
    }

    const assignMutation = useMutation({
        mutationFn: () => api.post('/weight-assignments', {
            standard_weight_id: Number(form.standard_weight_id),
            user_id: Number(form.user_id),
            notes: form.notes.trim() || null,
        }),
        onSuccess: () => {
            toast.success('Peso atribuido com sucesso.')
            void queryClient.invalidateQueries({ queryKey: ['weight-assignments'] })
            void queryClient.invalidateQueries({ queryKey: ['weight-assignments-weights'] })
            closeDialog()
        },
        onError: (error) => {
            toast.error(getApiErrorMessage(error, 'Erro ao atribuir peso.'))
        },
    })

    const returnMutation = useMutation({
        mutationFn: (id: number) => api.put(`/weight-assignments/${id}`, { status: 'returned' }),
        onSuccess: () => {
            toast.success('Peso devolvido com sucesso.')
            void queryClient.invalidateQueries({ queryKey: ['weight-assignments'] })
            void queryClient.invalidateQueries({ queryKey: ['weight-assignments-weights'] })
        },
        onError: (error) => {
            toast.error(getApiErrorMessage(error, 'Erro ao devolver peso.'))
        },
    })

    const assignments = assignmentsQuery.data ?? []
    const availableWeights = (weightsQuery.data ?? []).filter(weight => weight.status === 'active')
    const technicians = techniciansQuery.data ?? []
    const activeAssignments = assignments.filter(assignment => !assignment.returned_at)
    const returnedAssignments = assignments.filter(assignment => assignment.returned_at)
    const isInitialLoading = assignmentsQuery.isLoading || weightsQuery.isLoading || techniciansQuery.isLoading

    return (
        <div className="space-y-6">
            <PageHeader
                title="Atribuicao de Pesos Padrao"
                subtitle="Controle de emprestimo e devolucao de pesos padrao para tecnicos."
                action={canManageAssignments ? (
                    <button
                        type="button"
                        onClick={() => setShowDialog(true)}
                        className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Atribuir Peso
                    </button>
                ) : undefined}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-xl border bg-card p-4">
                    <div className="text-sm text-muted-foreground">Em campo</div>
                    <div className="mt-1 text-2xl font-bold text-amber-600">{activeAssignments.length}</div>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <div className="text-sm text-muted-foreground">Devolvidos</div>
                    <div className="mt-1 text-2xl font-bold text-emerald-600">{returnedAssignments.length}</div>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <div className="text-sm text-muted-foreground">Total de registros</div>
                    <div className="mt-1 text-2xl font-bold">{assignments.length}</div>
                </div>
            </div>

            {isInitialLoading ? (
                <div className="flex justify-center py-12 text-muted-foreground">Carregando...</div>
            ) : assignments.length === 0 ? (
                <EmptyState
                    icon={ArrowLeftRight}
                    title="Nenhuma atribuicao"
                    description="Nenhum peso padrao foi atribuido ainda."
                />
            ) : (
                <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr>
                                <th className="p-3 text-left font-medium">Peso padrao</th>
                                <th className="p-3 text-left font-medium">Tipo</th>
                                <th className="p-3 text-left font-medium">Atribuido a</th>
                                <th className="p-3 text-left font-medium">Data saida</th>
                                <th className="p-3 text-left font-medium">Data devolucao</th>
                                <th className="p-3 text-left font-medium">Observacoes</th>
                                <th className="p-3 text-center font-medium">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {assignments.map(assignment => (
                                <tr key={assignment.id} className={assignment.returned_at ? 'opacity-60' : ''}>
                                    <td className="p-3 font-medium">{buildWeightLabel(assignment)}</td>
                                    <td className="p-3">
                                        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs">
                                            <User className="h-3 w-3" aria-hidden="true" />
                                            {assignment.assignment_type === 'user' ? 'Tecnico' : assignment.assignment_type}
                                        </span>
                                    </td>
                                    <td className="p-3">{assignment.user_name ?? `#${assignment.assigned_to_user_id ?? '—'}`}</td>
                                    <td className="p-3 text-xs">{formatDate(assignment.assigned_at)}</td>
                                    <td className="p-3 text-xs">
                                        {assignment.returned_at ? (
                                            formatDate(assignment.returned_at)
                                        ) : (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                                Em campo
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-3 text-xs text-muted-foreground">{assignment.notes ?? '—'}</td>
                                    <td className="p-3 text-center">
                                        {canManageAssignments && !assignment.returned_at && (
                                            <button
                                                type="button"
                                                onClick={() => returnMutation.mutate(assignment.id)}
                                                disabled={returnMutation.isPending}
                                                aria-label={`Devolver peso ${buildWeightLabel(assignment)}`}
                                                className="inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-xs hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <RotateCcw className="h-3 w-3" aria-hidden="true" />
                                                Devolver
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {canManageAssignments && showDialog && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    onClick={closeDialog}
                >
                    <div
                        className="w-full max-w-md rounded-xl bg-card p-6 shadow-xl"
                        onClick={event => event.stopPropagation()}
                    >
                        <h3 className="text-lg font-semibold">Atribuir Peso Padrao</h3>
                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium" htmlFor="assignment-weight">
                                    Peso padrao
                                </label>
                                <select
                                    id="assignment-weight"
                                    aria-label="Peso padrao"
                                    className="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    value={form.standard_weight_id}
                                    onChange={event => setForm(current => ({ ...current, standard_weight_id: event.target.value }))}
                                >
                                    <option value="">Selecione...</option>
                                    {availableWeights.map(weight => (
                                        <option key={weight.id} value={weight.id}>
                                            {weight.code} - {weight.nominal_value} {weight.unit}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium" htmlFor="assignment-technician">
                                    Tecnico
                                </label>
                                <select
                                    id="assignment-technician"
                                    aria-label="Tecnico"
                                    className="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    value={form.user_id}
                                    onChange={event => setForm(current => ({ ...current, user_id: event.target.value }))}
                                >
                                    <option value="">Selecione...</option>
                                    {technicians.map(technician => (
                                        <option key={technician.id} value={technician.id}>
                                            {technician.name}
                                        </option>
                                    ))}
                                </select>
                                {technicians.length === 0 && (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Nenhum tecnico ativo disponivel para atribuicao.
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium" htmlFor="assignment-notes">
                                    Observacoes
                                </label>
                                <textarea
                                    id="assignment-notes"
                                    className="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                                    value={form.notes}
                                    onChange={event => setForm(current => ({ ...current, notes: event.target.value }))}
                                    rows={2}
                                    placeholder="Observacoes sobre a atribuicao"
                                />
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={closeDialog}
                                className="rounded-lg border px-4 py-2 text-sm hover:bg-muted"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={() => assignMutation.mutate()}
                                disabled={
                                    assignMutation.isPending ||
                                    weightsQuery.isLoading ||
                                    techniciansQuery.isLoading ||
                                    !form.standard_weight_id ||
                                    !form.user_id
                                }
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                            >
                                {assignMutation.isPending ? 'Atribuindo...' : 'Atribuir'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
