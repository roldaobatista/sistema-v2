import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Edit, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { financialApi } from '@/lib/financial-api'
import { queryKeys } from '@/lib/query-keys'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/ui/iconbutton'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { getApiErrorMessage } from '@/lib/api'
import { handleFormError } from '@/lib/form-utils'
import type { AccountPayableCategory } from '@/types/financial'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

const presetColors = [
    '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#0d9488',
    '#ec4899', '#06b6d4', '#f97316', '#059669', '#14b8a6',
]

const categorySchema = z.object({
    name: z.string().min(1, 'Nome é obrigatório').max(255, 'O nome deve ter no máximo 255 caracteres'),
    color: z.string().min(1, 'Cor é obrigatória'),
    description: z.string().max(255, 'A descrição deve ter no máximo 255 caracteres').nullable().optional(),
})

type CategoryFormData = z.infer<typeof categorySchema>

const emptyForm: CategoryFormData = {
    name: '',
    color: '#3b82f6',
    description: '',
}

export function AccountPayableCategoriesPage() {
    const qc = useQueryClient()
    const { hasPermission, hasRole } = useAuthStore()

    const isSuperAdmin = hasRole('super_admin')
    const canView = isSuperAdmin || hasPermission('finance.payable.view')
    const canCreate = isSuperAdmin || hasPermission('finance.payable.create')
    const canUpdate = isSuperAdmin || hasPermission('finance.payable.update')
    const canDelete = isSuperAdmin || hasPermission('finance.payable.delete')

    const [showForm, setShowForm] = useState(false)
    const [editing, setEditing] = useState<AccountPayableCategory | null>(null)
    const [deleteTarget, setDeleteTarget] = useState<AccountPayableCategory | null>(null)

    const methods = useForm<CategoryFormData>({
        resolver: zodResolver(categorySchema),
        defaultValues: emptyForm,
    })

    const categoriesQuery = useQuery({
        queryKey: queryKeys.financial.payables.categories,
        queryFn: () => financialApi.payablesCategories.list(),
        enabled: canView,
    })
    const categories: AccountPayableCategory[] = categoriesQuery.data?.data?.data ?? categoriesQuery.data?.data ?? categoriesQuery.data ?? []

    const saveMut = useMutation({
        mutationFn: async (data: CategoryFormData) => {
            const payload = {
                name: data.name.trim(),
                color: data.color,
                description: data.description?.trim() || null,
            }

            if (editing) {
                await financialApi.payablesCategories.update(editing.id, payload)
                return
            }
            await financialApi.payablesCategories.create(payload)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.payables.categories })
            resetForm()
            toast.success(editing ? 'Categoria atualizada com sucesso' : 'Categoria criada com sucesso')
        },
        onError: (error: unknown) => {
            if (handleFormError(error, methods.setError)) {
                return
            }
            toast.error(getApiErrorMessage(error, 'Erro ao salvar categoria'))
        },
    })

    const deleteMut = useMutation({
        mutationFn: async (id: number) => {
            await financialApi.payablesCategories.destroy(id)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.financial.payables.categories })
            setDeleteTarget(null)
            toast.success('Categoria excluida com sucesso')
        },
        onError: (error: unknown) => {
            toast.error(getApiErrorMessage(error, 'Erro ao excluir categoria'))
        },
    })

    const resetForm = () => {
        setShowForm(false)
        setEditing(null)
        methods.reset(emptyForm)
    }

    const openCreate = () => {
        if (!canCreate) {
            toast.error('Sem permissão para criar categoria')
            return
        }
        setEditing(null)
        methods.reset(emptyForm)
        setShowForm(true)
    }

    const openEdit = (category: AccountPayableCategory) => {
        if (!canUpdate) {
            toast.error('Sem permissão para editar categoria')
            return
        }
        setEditing(category)
        methods.reset({
            name: category.name,
            color: category.color ?? '#3b82f6',
            description: category.description ?? '',
        })
        setShowForm(true)
    }

    const openDelete = (category: AccountPayableCategory) => {
        if (!canDelete) {
            toast.error('Sem permissão para excluir categoria')
            return
        }
        setDeleteTarget(category)
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Categorias de Contas a Pagar"
                subtitle="Gerencie as categorias de classificação"
                count={canView ? categories.length : 0}
                actions={canCreate ? [{ label: 'Nova Categoria', onClick: openCreate, icon: <Plus className="h-4 w-4" /> }] : []}
            />

            {!canView && (canCreate || canUpdate || canDelete) ? (
                <div className="rounded-xl border border-dashed border-default bg-surface-0 p-4 text-sm text-surface-600 shadow-card">
                    Voce pode operar categorias, mas nao possui permissao para listar as categorias existentes.
                </div>
            ) : categoriesQuery.isLoading ? (
                <div className="py-12 text-center text-sm text-surface-500">Carregando...</div>
            ) : categoriesQuery.isError ? (
                <div className="py-12 text-center text-sm text-red-600">
                    Erro ao carregar categorias. <button className="underline" onClick={() => categoriesQuery.refetch()}>Tentar novamente</button>
                </div>
            ) : categories.length === 0 ? (
                <EmptyState icon={<Plus className="h-5 w-5 text-surface-300" />} message="Nenhuma categoria cadastrada" action={canCreate ? { label: 'Nova Categoria', onClick: openCreate, icon: <Plus className="h-4 w-4" /> } : undefined} />
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {(categories || []).map((category) => (
                        <div key={category.id} className="flex items-center gap-3 rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                            <div className="h-10 w-10 shrink-0 rounded-lg" style={{ backgroundColor: category.color ?? '#e2e8f0' }} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-surface-900">{category.name}</p>
                                {category.description && <p className="truncate text-xs text-surface-500">{category.description}</p>}
                            </div>
                            <div className="flex gap-1">
                                {canUpdate && (
                                    <IconButton label="Editar" icon={<Edit className="h-3.5 w-3.5" />} onClick={() => openEdit(category)} className="hover:text-brand-600" />
                                )}
                                {canDelete && (
                                    <IconButton label="Excluir" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => openDelete(category)} className="hover:text-red-600" />
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Modal open={showForm} onOpenChange={setShowForm} title={editing ? 'Editar Categoria' : 'Nova Categoria'}>
                <form onSubmit={methods.handleSubmit((data) => saveMut.mutate(data))} className="space-y-4">
                    <Controller
                        control={methods.control}
                        name="name"
                        render={({ field, fieldState }) => (
                            <Input label="Nome *" {...field} error={fieldState.error?.message} required />
                        )}
                    />
                    <Controller
                        control={methods.control}
                        name="description"
                        render={({ field, fieldState }) => (
                            <Input label="Descrição" {...field} value={field.value ?? ''} error={fieldState.error?.message} />
                        )}
                    />

                    <div>
                        <label className="mb-2 block text-sm font-medium text-surface-700">Cor</label>
                        <Controller
                            control={methods.control}
                            name="color"
                            render={({ field, fieldState }) => (
                                <>
                                    <div className="flex flex-wrap gap-2">
                                        {(presetColors || []).map((color) => (
                                            <button
                                                key={color}
                                                type="button"
                                                onClick={() => field.onChange(color)}
                                                aria-label={`Selecionar cor ${color}`}
                                                className={`h-8 w-8 rounded-full border-2 transition-transform ${field.value === color ? 'scale-110 border-surface-900' : 'border-transparent'}`}
                                                style={{ backgroundColor: color }}
                                            />
                                        ))}
                                    </div>
                                    {fieldState.error && <p className="text-[10px] text-red-500 mt-1">{fieldState.error.message}</p>}
                                </>
                            )}
                        />
                    </div>

                    <div className="flex justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" type="button" onClick={resetForm}>Cancelar</Button>
                        <Button type="submit" loading={saveMut.isPending} disabled={!methods.watch('name')?.trim()}>Salvar</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)} title="Excluir Categoria">
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">Tem certeza que deseja excluir a categoria {deleteTarget?.name}?</p>
                    <div className="flex justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
                        <Button variant="danger" loading={deleteMut.isPending} onClick={() => { if (deleteTarget) deleteMut.mutate(deleteTarget.id) }}>
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
