import { useState } from 'react'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, Trash2, CreditCard } from 'lucide-react'
import { financialApi } from '@/lib/financial-api'
import { queryKeys } from '@/lib/query-keys'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/ui/iconbutton'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { FormField } from '@/components/ui/form-field'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { useAuthStore } from '@/stores/auth-store'
import { getApiErrorMessage } from '@/lib/api'
import { toast } from 'sonner'
import { handleFormError } from '@/lib/form-utils'
import { optionalString, requiredString } from '@/schemas/common'
import { z } from 'zod'
import type { PaymentMethod } from '@/types/financial'

const paymentMethodSchema = z.object({
    name: requiredString('Nome é obrigatório'),
    code: optionalString,
    is_active: z.boolean().default(true),
})

type PaymentMethodFormData = z.infer<typeof paymentMethodSchema>

const defaultValues: PaymentMethodFormData = { name: '', code: '', is_active: true }

export function PaymentMethodsPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()
    const [showForm, setShowForm] = useState(false)
    const [editing, setEditing] = useState<PaymentMethod | null>(null)
    const [deleteTarget, setDeleteTarget] = useState<PaymentMethod | null>(null)

    const { register, handleSubmit, reset, control, setError, formState: { errors } } = useForm<PaymentMethodFormData>({
        resolver: zodResolver(paymentMethodSchema) as Resolver<PaymentMethodFormData>,
        defaultValues,
    })

    const isSuperAdmin = hasRole('super_admin')
    const canView = isSuperAdmin || hasPermission('finance.payable.view') || hasPermission('finance.receivable.view') || hasPermission('financial.fund_transfer.create')
    const canCreate = isSuperAdmin || hasPermission('finance.payable.create')
    const canUpdate = isSuperAdmin || hasPermission('finance.payable.update')
    const canDelete = isSuperAdmin || hasPermission('finance.payable.delete')

    const { data: res, isLoading, isError } = useQuery({
        queryKey: queryKeys.financial.paymentMethods,
        queryFn: () => financialApi.paymentMethods.list(),
        enabled: canView,
    })
    const methodsRaw = res?.data
    const methods: PaymentMethod[] = Array.isArray(methodsRaw) ? methodsRaw : (methodsRaw as { data?: PaymentMethod[] })?.data ?? []

    const saveMut = useMutation({
        mutationFn: (data: PaymentMethodFormData) =>
            editing ? financialApi.paymentMethods.update(editing.id, data) : financialApi.paymentMethods.create(data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.paymentMethods })
            setShowForm(false)
            setEditing(null)
            toast.success(editing ? 'Forma atualizada com sucesso.' : 'Forma criada com sucesso.')
        },
        onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Falha ao salvar forma de pagamento.'),
    })

    const delMut = useMutation({
        mutationFn: (id: number) => financialApi.paymentMethods.destroy(id),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.paymentMethods })
            setDeleteTarget(null)
            toast.success('Forma excluida com sucesso.')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Falha ao excluir forma de pagamento.'))
        },
    })

    const openCreate = () => {
        if (!canCreate) {
            toast.error('Sem permissão para criar forma de pagamento.')
            return
        }
        setEditing(null)
        reset(defaultValues)
        setShowForm(true)
    }

    const openEdit = (method: PaymentMethod) => {
        if (!canUpdate) {
            toast.error('Sem permissão para editar forma de pagamento.')
            return
        }
        setEditing(method)
        reset({ name: method.name, code: method.code ?? '', is_active: method.is_active })
        setShowForm(true)
    }

    const openDelete = (method: PaymentMethod) => {
        if (!canDelete) {
            toast.error('Sem permissão para excluir forma de pagamento.')
            return
        }
        setDeleteTarget(method)
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Formas de Pagamento"
                subtitle="Métodos de pagamento configuráveis"
                count={canView ? methods.length : 0}
                actions={canCreate ? [{ label: 'Nova Forma', onClick: openCreate, icon: <Plus className="h-4 w-4" /> }] : []}
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {!canView && (canCreate || canUpdate || canDelete) ? (
                    <div className="col-span-full rounded-xl border border-dashed border-default bg-surface-0 p-4 text-sm text-surface-600 shadow-card">
                        Voce pode operar formas de pagamento, mas nao possui permissao para listar os metodos configurados.
                    </div>
                ) : isLoading ? (
                    <p className="col-span-full py-12 text-center text-sm text-surface-500">Carregando...</p>
                ) : isError ? (
                    <p className="col-span-full py-12 text-center text-sm text-red-600">Não foi possível carregar as formas de pagamento.</p>
                ) : methods.length === 0 ? (
                    <div className="col-span-full"><EmptyState icon={<CreditCard className="h-5 w-5 text-surface-300" />} message="Nenhuma forma cadastrada" action={canCreate ? { label: 'Nova Forma', onClick: openCreate, icon: <Plus className="h-4 w-4" /> } : undefined} /></div>
                ) : (methods || []).map(method => (
                    <div key={method.id} className="flex items-center justify-between rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50">
                                <CreditCard className="h-5 w-5 text-sky-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-surface-900">{method.name}</p>
                                {method.code && <p className="text-xs text-surface-400">Código: {method.code}</p>}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant={method.is_active ? 'success' : 'danger'}>
                                {method.is_active ? 'Ativo' : 'Inativo'}
                            </Badge>
                            {canUpdate && (
                                <IconButton label="Editar" icon={<Pencil className="h-4 w-4" />} onClick={() => openEdit(method)} className="hover:text-brand-600" />
                            )}
                            {canDelete && (
                                <IconButton label="Excluir" icon={<Trash2 className="h-4 w-4" />} onClick={() => openDelete(method)} className="hover:text-red-600" />
                            )}
                        </div>
                    </div>
                ))}
            </div>

            <Modal open={showForm} onOpenChange={setShowForm} title={editing ? 'Editar Forma de Pagamento' : 'Nova Forma de Pagamento'}>
                <form onSubmit={handleSubmit((data: PaymentMethodFormData) => saveMut.mutate(data))} className="space-y-4">
                    <FormField label="Nome" error={errors.name?.message} required>
                        <Input {...register('name')} placeholder="Ex: PIX, Boleto, Cartão..." />
                    </FormField>
                    <FormField label="Código" error={errors.code?.message}>
                        <Input {...register('code')} placeholder="Código interno (opcional)" />
                    </FormField>
                    <Controller control={control} name="is_active" render={({ field }) => (
                        <div className="flex items-center gap-2">
                            <input type="checkbox" id="pm-active" checked={field.value} onChange={e => field.onChange(e.target.checked)}
                                className="h-4 w-4 rounded border-default text-brand-600 focus:ring-brand-500" />
                            <label htmlFor="pm-active" className="text-sm text-surface-700">Ativo</label>
                        </div>
                    )} />
                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" type="button" onClick={() => { setShowForm(false); setEditing(null) }}>Cancelar</Button>
                        <Button type="submit" loading={saveMut.isPending}>{editing ? 'Salvar' : 'Criar'}</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)} title="Excluir Forma de Pagamento">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Tem certeza que deseja excluir {deleteTarget?.name}?</p>
                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
                        <Button variant="danger" loading={delMut.isPending} onClick={() => { if (deleteTarget) delMut.mutate(deleteTarget.id) }}>Excluir</Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
