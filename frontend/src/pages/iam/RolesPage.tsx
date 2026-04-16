import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Shield, Trash2, Copy, ShieldOff, Users } from 'lucide-react'
import api from '@/lib/api'
import { getApiErrorMessage } from '@/lib/api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { Modal } from '@/components/ui/modal'
import { Input } from '@/components/ui/input'
import { FormField } from '@/components/ui/form-field'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { handleFormError } from '@/lib/form-utils'
import { optionalString, requiredString } from '@/schemas/common'
import { z } from 'zod'
import type { PermissionEntry, PermissionGroup, Role, User } from '@/types/iam'
import { normalizePermissionGroups, normalizeRoleDetail, normalizeRoleList } from './role-contract'

const roleSchema = z.object({
    name: requiredString('Identificador é obrigatório'),
    display_name: optionalString.transform(s => s ?? ''),
    description: optionalString.transform(s => s ?? ''),
})

type RoleFormData = z.infer<typeof roleSchema>

const defaultValues: RoleFormData = { name: '', display_name: '', description: '' }

export function RolesPage() {
    const queryClient = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canCreate = hasPermission('iam.role.create')
    const canUpdate = hasPermission('iam.role.update')
    const canDelete = hasPermission('iam.role.delete')
    const [showForm, setShowForm] = useState(false)
    const [editingRole, setEditingRole] = useState<Role | null>(null)
    const [selectedPermissions, setSelectedPermissions] = useState<number[]>([])

    const { register, handleSubmit, reset, setError, formState: { errors } } = useForm<RoleFormData>({
        resolver: zodResolver(roleSchema),
        defaultValues,
    })
    const [loadingEditId, setLoadingEditId] = useState<number | null>(null)
    const [deleteConfirmRole, setDeleteConfirmRole] = useState<Role | null>(null)
    const [cloneRole, setCloneRole] = useState<Role | null>(null)
    const [cloneName, setCloneName] = useState('')
    const [cloneDisplayName, setCloneDisplayName] = useState('')
    const [viewingUsersRole, setViewingUsersRole] = useState<Role | null>(null)

    const { data: roleUsers, isLoading: isLoadingUsers } = useQuery({
        queryKey: ['role-users', viewingUsersRole?.id],
        queryFn: () => api.get(`/roles/${viewingUsersRole?.id}/users`).then(res => res.data as User[]),
        enabled: !!viewingUsersRole?.id,
    })

    const { data: rolesData, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['roles'],
        queryFn: () => api.get('/roles').then(normalizeRoleList),
    })
    const roles: Role[] = rolesData ?? []

    const { data: permGroupsData } = useQuery({
        queryKey: ['permissions'],
        queryFn: () => api.get('/permissions').then(normalizePermissionGroups),
    })
    const permissionGroups: PermissionGroup[] = permGroupsData ?? []

    const saveMutation = useMutation({
        mutationFn: (data: RoleFormData & { permissions: number[] }) =>
            editingRole
                ? api.put(`/roles/${editingRole.id}`, data)
                : api.post('/roles', data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['roles'] })
            broadcastQueryInvalidation(['roles'], 'Roles')
            setShowForm(false)
            toast.success(editingRole ? 'Role atualizada com sucesso!' : 'Role criada com sucesso!')
        },
        onError: (err: unknown) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao salvar role.'),
    })

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/roles/${id}`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['roles'] })
            broadcastQueryInvalidation(['roles'], 'Roles')
            setDeleteConfirmRole(null)
            toast.success('Role excluída com sucesso!')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao excluir role.'))
        },
    })

    const cloneMutation = useMutation({
        mutationFn: ({ roleId, name, display_name }: { roleId: number; name: string; display_name?: string }) =>
            api.post(`/roles/${roleId}/clone`, { name, ...(display_name ? { display_name } : {}) }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['roles'] })
            broadcastQueryInvalidation(['roles'], 'Roles')
            setCloneRole(null)
            setCloneName('')
            setCloneDisplayName('')
            toast.success('Role clonada com sucesso!')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao clonar role.'))
        },
    })

    const handleCloneSubmit = () => {
        if (cloneRole && cloneName.trim()) {
            cloneMutation.mutate({
                roleId: cloneRole.id,
                name: cloneName.trim(),
                display_name: cloneDisplayName.trim() || undefined,
            })
        }
    }

    const openCreate = () => {
        setEditingRole(null)
        reset(defaultValues)
        setSelectedPermissions([])
        setShowForm(true)
    }

    const openEdit = async (role: Role) => {
        setLoadingEditId(role.id)
        try {
            const roleDetail = normalizeRoleDetail(await api.get(`/roles/${role.id}`))
            setEditingRole(roleDetail)
            reset({ name: roleDetail.name, display_name: roleDetail.display_name ?? '', description: roleDetail.description ?? '' })
            setSelectedPermissions((roleDetail.permissions ?? []).map((permission) => permission.id))
            setShowForm(true)
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao carregar dados da role.'))
        } finally {
            setLoadingEditId(null)
        }
    }

    const togglePermission = (id: number) => {
        setSelectedPermissions(prev =>
            prev.includes(id) ? (prev || []).filter(p => p !== id) : [...prev, id]
        )
    }

    const toggleGroup = (groupPerms: { id: number }[]) => {
        const ids = (groupPerms || []).map(p => p.id)
        const allSelected = ids.every(id => selectedPermissions.includes(id))
        setSelectedPermissions(prev =>
            allSelected ? (prev || []).filter(id => !ids.includes(id)) : [...new Set([...prev, ...ids])]
        )
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Roles"
                subtitle="Gerencie os perfis de acesso"
                count={roles.length}
                actions={canCreate ? [{ label: 'Nova Role', onClick: openCreate, icon: <Plus className="h-4 w-4" /> }] : []}
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {isLoading ? (
                    <>{[...Array(6)].map((_, i) => (
                        <div key={`sk-${i}`} className="rounded-xl border border-default bg-surface-0 p-5 animate-pulse">
                            <div className="flex items-center gap-3">
                                <div className="h-10 w-10 rounded-lg bg-surface-200" />
                                <div className="space-y-1.5">
                                    <div className="h-4 w-24 rounded bg-surface-200" />
                                    <div className="h-3 w-32 rounded bg-surface-200" />
                                </div>
                            </div>
                            <div className="mt-4 flex items-center gap-2">
                                <div className="h-8 flex-1 rounded bg-surface-200" />
                                <div className="h-8 w-8 rounded bg-surface-200" />
                            </div>
                        </div>
                    ))}</>
                ) : isError ? (
                    <div className="col-span-full text-center py-12">
                        <p className="text-sm text-red-500">{(error as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erro ao carregar roles.'}</p>
                        <Button variant="outline" size="sm" className="mt-2" onClick={() => refetch()}>Tentar novamente</Button>
                    </div>
                ) : roles.length === 0 ? (
                    <div className="col-span-full">
                        <EmptyState
                            icon={<ShieldOff className="h-5 w-5 text-surface-300" />}
                            message="Nenhuma role encontrada. Crie a primeira role para definir níveis de acesso."
                            action={canCreate ? { label: 'Nova Role', onClick: openCreate, icon: <Plus className="h-4 w-4" /> } : undefined}
                        />
                    </div>
                ) : (roles || []).map((role) => (
                    <div
                        key={role.id}
                        className="group rounded-xl border border-default bg-surface-0 p-5 shadow-card hover:shadow-elevated transition-all duration-200"
                    >
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <div className="rounded-lg bg-brand-50 p-2.5">
                                    <Shield className="h-5 w-5 text-brand-600" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-surface-900">{role.display_name || role.label || role.name}</h3>
                                    <div className="mt-1 flex gap-3 text-xs text-surface-500">
                                        <span>{role.permissions_count} permissões</span>
                                        <span>·</span>
                                        <span>{role.users_count ?? 0} usuário(s)</span>
                                    </div>
                                    {role.description && (
                                        <p className="mt-1.5 text-xs text-surface-500 line-clamp-2">{role.description}</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 flex items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setViewingUsersRole(role)}
                                title="Ver usuários vinculados"
                                aria-label={`Ver usuários da role ${role.display_name || role.name}`}
                            >
                                <Users className="h-4 w-4 text-surface-500" />
                            </Button>
                            {canCreate && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => { setCloneRole(role); setCloneName(`${role.name} (cópia)`) }}
                                    loading={cloneMutation.isPending}
                                    title="Clonar role"
                                    aria-label={`Clonar role ${role.display_name || role.name}`}
                                >
                                    <Copy className="h-4 w-4 text-surface-500" />
                                </Button>
                            )}
                            {canUpdate && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => openEdit(role)}
                                    className="flex-1"
                                    disabled={role.is_protected || loadingEditId === role.id}
                                    loading={loadingEditId === role.id}
                                    aria-label={`Editar role ${role.display_name || role.name}`}
                                >
                                    {role.is_protected ? 'Protegida' : loadingEditId === role.id ? 'Carregando...' : 'Editar'}
                                </Button>
                            )}
                            {canDelete && !role.is_protected && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setDeleteConfirmRole(role)}
                                    aria-label={`Excluir role ${role.display_name || role.name}`}
                                >
                                    <Trash2 className="h-4 w-4 text-red-500" />
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            <Modal
                open={showForm}
                onOpenChange={(open: boolean) => { setShowForm(open); if (!open) { setEditingRole(null); reset(defaultValues); setSelectedPermissions([]) } }}
                title={editingRole ? `Editar: ${editingRole.name}` : 'Nova Role'}
                size="xl"
            >
                <form
                    onSubmit={handleSubmit((data) => saveMutation.mutate({ name: data.name, display_name: data.display_name ?? '', description: data.description ?? '', permissions: selectedPermissions }))}
                    className="space-y-4"
                >
                    <FormField label="Identificador (interno)" error={errors.name?.message} required>
                        <Input
                            {...register('name')}
                            placeholder="ex: supervisor"
                            disabled={!!editingRole?.is_protected}
                        />
                    </FormField>
                    <FormField label="Nome de Exibição" error={errors.display_name?.message}>
                        <Input {...register('display_name')} placeholder="ex: Supervisor de Campo" />
                    </FormField>
                    <FormField label="Descrição" error={errors.description?.message}>
                        <textarea
                            {...register('description')}
                            placeholder="Descrição do propósito desta role"
                            maxLength={500}
                            rows={2}
                            className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15 resize-none"
                        />
                    </FormField>

                    <div>
                        <label className="mb-3 block text-sm font-semibold text-surface-900">Permissões</label>
                        <div className="max-h-96 overflow-y-auto space-y-4 rounded-lg border border-default p-4">
                            {(permissionGroups || []).map((group) => (
                                <div key={group.id}>
                                    <div className="flex items-center justify-between mb-2">
                                        <h4 className="text-xs font-medium uppercase tracking-wider text-surface-500">
                                            {group.name}
                                        </h4>
                                        <button
                                            type="button"
                                            onClick={() => toggleGroup(group.permissions)}
                                            className="text-xs text-brand-600 hover:text-brand-700"
                                            aria-label={`${group.permissions.every((permission) => selectedPermissions.includes(permission.id)) ? 'Desmarcar' : 'Marcar'} todos de ${group.name}`}
                                        >
                                            {group.permissions.every((permission) => selectedPermissions.includes(permission.id)) ? 'Desmarcar' : 'Marcar'} todos
                                        </button>
                                    </div>
                                    <div className="flex flex-wrap gap-1.5">
                                        {(group.permissions || []).map((perm: PermissionEntry) => (
                                            <button
                                                key={perm.id}
                                                type="button"
                                                onClick={() => togglePermission(perm.id)}
                                                className={cn(
                                                    'rounded-md border px-2.5 py-1 text-xs font-medium transition-all',
                                                    selectedPermissions.includes(perm.id)
                                                        ? 'border-brand-400 bg-brand-50 text-brand-700'
                                                        : 'border-default text-surface-500 hover:border-surface-400'
                                                )}
                                                aria-label={`Alternar permissão ${perm.name}`}
                                            >
                                                {perm.name.split('.').slice(1).join('.')}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <p className="mt-2 text-xs text-surface-500">
                            {selectedPermissions.length} permissões selecionadas
                        </p>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" type="button" onClick={() => setShowForm(false)}>Cancelar</Button>
                        <Button type="submit" loading={saveMutation.isPending}>
                            {editingRole ? 'Salvar' : 'Criar Role'}
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={!!deleteConfirmRole}
                onOpenChange={(open: boolean) => { if (!open) setDeleteConfirmRole(null) }}
                title="Confirmar Exclusão"
                size="sm"
            >
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">
                        Tem certeza que deseja excluir a role <strong>{deleteConfirmRole?.name}</strong>?
                        Esta ação não pode ser desfeita.
                    </p>
                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" onClick={() => setDeleteConfirmRole(null)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="danger"
                            loading={deleteMutation.isPending}
                            onClick={() => deleteConfirmRole && deleteMutation.mutate(deleteConfirmRole.id)}
                        >
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>

            <Modal
                open={!!cloneRole}
                onOpenChange={(open: boolean) => { if (!open) { setCloneRole(null); setCloneName(''); setCloneDisplayName('') } }}
                title={`Clonar Role: ${cloneRole?.name ?? ''}`}
                size="sm"
            >
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-surface-700 mb-1">
                            Identificador (interno) <span className="text-red-500">*</span>
                        </label>
                        <Input
                            value={cloneName}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setCloneName(e.target.value)}
                            placeholder="ex: supervisor_campo"
                            autoFocus
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-surface-700 mb-1">
                            Nome de Exibição
                        </label>
                        <Input
                            value={cloneDisplayName}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setCloneDisplayName(e.target.value)}
                            placeholder="ex: Supervisor de Campo"
                        />
                    </div>
                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" onClick={() => { setCloneRole(null); setCloneName(''); setCloneDisplayName('') }}>
                            Cancelar
                        </Button>
                        <Button
                            loading={cloneMutation.isPending}
                            disabled={!cloneName.trim()}
                            onClick={handleCloneSubmit}
                        >
                            Clonar
                        </Button>
                    </div>
                </div>
            </Modal>

            <Modal
                open={!!viewingUsersRole}
                onOpenChange={(open: boolean) => { if (!open) setViewingUsersRole(null) }}
                title={`Usuários com a Role: ${viewingUsersRole?.display_name || viewingUsersRole?.name}`}
                size="md"
            >
                <div className="space-y-4 max-h-[60vh] overflow-y-auto">
                    {isLoadingUsers ? (
                        <div className="py-8 text-center text-sm text-surface-500">
                            Carregando usuários...
                        </div>
                    ) : roleUsers && roleUsers.length > 0 ? (
                        <div className="divide-y divide-subtle">
                            {roleUsers.map(user => (
                                <div key={user.id} className="py-3 flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-surface-900">{user.name}</p>
                                        <p className="text-xs text-surface-500">{user.email}</p>
                                    </div>
                                    {user.is_active ? (
                                        <span className="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Ativo</span>
                                    ) : (
                                        <span className="inline-flex items-center rounded-full bg-surface-100 px-2 py-1 text-xs font-medium text-surface-600 ring-1 ring-inset ring-surface-500/20">Inativo</span>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            icon={<Users className="h-8 w-8 text-surface-300" />}
                            title="Nenhum usuário"
                            description="Esta role não possui usuários vinculados."
                        />
                    )}
                    <div className="flex items-center justify-end border-t border-subtle pt-4">
                        <Button variant="outline" onClick={() => setViewingUsersRole(null)}>
                            Fechar
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
