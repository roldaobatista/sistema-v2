import { useState, useCallback } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Search, Trash2, UserCheck, UserX, KeyRound, Download, CheckSquare, Square, Monitor, LogOut, Users, UserPlus, UserMinus, AlertCircle, History, ShieldCheck, ShieldOff, ShieldAlert, Eye, EyeOff } from 'lucide-react'
import { AxiosError } from 'axios'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/lib/api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { maskPhone } from '@/lib/form-masks'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { Input } from '@/components/ui/input'
import { FormField } from '@/components/ui/form-field'
import { useAuthStore } from '@/stores/auth-store'
import { handleFormError } from '@/lib/form-utils'
import type { ApiError } from '@/types/common'
import type { Role, User, Branch, Session, AuditEntry, PermissionGroup } from '@/types/iam'

const createUserSchema = z.object({
    name: z.string().min(1, 'Nome é obrigatório'),
    email: z.string().email('E-mail inválido'),
    phone: z.string().nullable().optional(),
    password: z.string().min(8, 'Senha deve ter pelo menos 8 caracteres'),
    password_confirmation: z.string().min(1, 'Confirmação de senha é obrigatória'),
    is_active: z.boolean().default(true),
    branch_id: z.number().nullable().optional(),
    roles: z.array(z.number()).default([]),
}).refine(data => data.password === data.password_confirmation, {
    message: "As senhas não coincidem",
    path: ["password_confirmation"],
})

const editUserSchema = z.object({
    name: z.string().min(1, 'Nome é obrigatório'),
    email: z.string().email('E-mail inválido'),
    phone: z.string().nullable().optional(),
    password: z.string().optional(),
    password_confirmation: z.string().optional(),
    is_active: z.boolean().default(true),
    branch_id: z.number().nullable().optional(),
    roles: z.array(z.number()).default([]),
}).refine(data => {
    if (data.password && data.password !== data.password_confirmation) return false;
    return true;
}, {
    message: "As senhas não coincidem",
    path: ["password_confirmation"],
})

const resetPasswordSchema = z.object({
    password: z.string().min(8, 'Senha deve ter pelo menos 8 caracteres'),
    password_confirmation: z.string().min(1, 'Confirmação é obrigatória'),
}).refine(data => data.password === data.password_confirmation, {
    message: "As senhas não coincidem",
    path: ["password_confirmation"],
})

type UserFormData = z.infer<typeof createUserSchema>
type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>
const defaultValues: UserFormData = { name: '', email: '', phone: '', password: '', password_confirmation: '', roles: [], is_active: true, branch_id: null }

export function UsersPage() {
    const queryClient = useQueryClient()
    const { hasPermission } = useAuthStore()
    const canView = hasPermission('iam.user.view')
    const canCreate = hasPermission('iam.user.create')
    const canUpdate = hasPermission('iam.user.update')
    const canDelete = hasPermission('iam.user.delete')
    const [page, setPage] = useState(1)
    const [search, setSearch] = useState('')
    const [searchInput, setSearchInput] = useState('')
    const [showForm, setShowForm] = useState(false)
    const [editingUser, setEditingUser] = useState<User | null>(null)
    const { register, handleSubmit, reset, setValue, watch, setError, formState: { errors } } = useForm<UserFormData>({
        resolver: zodResolver(editingUser ? editUserSchema : createUserSchema),
        defaultValues,
    })
    const formData = watch()
    const [showPassword, setShowPassword] = useState(false)
    const [showResetPassword, setShowResetPassword] = useState(false)
    const [resetPasswordUser, setResetPasswordUser] = useState<User | null>(null)
    const resetPwForm = useForm<ResetPasswordFormData>({
        resolver: zodResolver(resetPasswordSchema),
        defaultValues: { password: '', password_confirmation: '' },
    })
    const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all')
    const [roleFilter, setRoleFilter] = useState('')
    const [selectedIds, setSelectedIds] = useState<number[]>([])
    const [deleteConfirmUser, setDeleteConfirmUser] = useState<User | null>(null)
    const [sessionsUser, setSessionsUser] = useState<User | null>(null)
    const [auditTrailUser, setAuditTrailUser] = useState<User | null>(null)
    const [permissionsUser, setPermissionsUser] = useState<User | null>(null)
    const canManagePermissions = hasPermission('iam.permission.manage')

    const debouncedSearch = useCallback(
        (() => {
            let timer: ReturnType<typeof setTimeout>
            return (val: string) => {
                clearTimeout(timer)
                timer = setTimeout(() => { setSearch(val); setPage(1) }, 300)
            }
        })(),
        []
    )

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['users', page, search, statusFilter, roleFilter],
        queryFn: () => api.get('/users', { params: { page, search, per_page: 20, ...(statusFilter !== 'all' && { is_active: statusFilter === 'active' ? 1 : 0 }), ...(roleFilter && { role: roleFilter }) } }).then(r => r.data),
    })

    const users: User[] = data?.data ?? []
    const lastPage = data?.last_page ?? 1
    const totalUsers = data?.total ?? 0

    const { data: rolesData } = useQuery({
        queryKey: ['roles'],
        queryFn: () => api.get('/roles').then(r => r.data),
    })
    const roles: Role[] = rolesData?.data ?? rolesData ?? []

    const { data: branchesData } = useQuery({
        queryKey: ['branches'],
        queryFn: () => api.get('/branches').then(r => r.data),
    })
    const branches: Branch[] = branchesData?.data ?? branchesData ?? []

    const { data: statsData } = useQuery({
        queryKey: ['users-stats'],
        queryFn: () => api.get('/users/stats').then(r => r.data),
        enabled: canView,
    })

    const saveMutation = useMutation({
        mutationFn: (data: UserFormData) => {
            const payload = { ...data } as Record<string, unknown>
            if (!data.password || data.password.trim() === '') {
                delete payload.password
                delete payload.password_confirmation
            }
            return editingUser
                ? api.put(`/users/${editingUser.id}`, payload)
                : api.post('/users', payload)
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
            broadcastQueryInvalidation(['users'], 'Usuários')
            closeForm()
            toast.success(editingUser ? 'Usuário atualizado com sucesso!' : 'Usuário criado com sucesso!')
        },
        onError: (err: unknown) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao salvar usuário.'),
    })

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/users/${id}`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
            broadcastQueryInvalidation(['users'], 'Usuários')
            setDeleteConfirmUser(null)
            toast.success('Usuário excluído com sucesso!')
        },
        onError: (err: unknown) => {
            toast.error((err as AxiosError<ApiError>).response?.data?.message ?? 'Erro ao excluir usuário.')
        },
    })

    const toggleMutation = useMutation({
        mutationFn: (id: number) => api.post(`/users/${id}/toggle-active`),
        onSuccess: (res: { data?: { message?: string } }) => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
            broadcastQueryInvalidation(['users'], 'Usuários')
            const isActive = (res.data as Record<string, unknown>)?.is_active
            toast.success(`Usuário ${isActive ? 'ativado' : 'desativado'} com sucesso!`)
        },
        onError: (err: unknown) => {
            toast.error((err as AxiosError<ApiError>).response?.data?.message ?? 'Erro ao alterar status do usuário.')
        },
    })

    const resetPasswordMutation = useMutation({
        mutationFn: ({ userId, password, password_confirmation }: { userId: number; password: string; password_confirmation: string }) =>
            api.post(`/users/${userId}/reset-password`, { password, password_confirmation }),
        onSuccess: () => {
            setResetPasswordUser(null)
            resetPwForm.reset()
            toast.success('Senha redefinida com sucesso!')
        },
        onError: (err: unknown) => {
            toast.error((err as AxiosError<ApiError>).response?.data?.message ?? 'Erro ao redefinir senha.')
        },
    })

    const bulkToggleMutation = useMutation({
        mutationFn: (data: { user_ids: number[]; is_active: boolean }) =>
            api.post('/users/bulk-toggle-active', data),
        onSuccess: (res: { data?: { message?: string } }) => {
            queryClient.invalidateQueries({ queryKey: ['users'] })
            broadcastQueryInvalidation(['users'], 'Usuários')
            setSelectedIds([])
            toast.success(res.data?.message ?? 'Status alterado com sucesso!')
        },
        onError: (err: unknown) => {
            toast.error((err as AxiosError<ApiError>).response?.data?.message ?? 'Erro ao alterar status em lote.')
        },
    })

    const sessionsQuery = useQuery({
        queryKey: ['user-sessions', sessionsUser?.id],
        queryFn: () => api.get(`/users/${sessionsUser!.id}/sessions`).then(r => r.data),
        enabled: !!sessionsUser,
    })

    const revokeSessionMutation = useMutation({
        mutationFn: ({ userId, tokenId }: { userId: number; tokenId: number }) =>
            api.delete(`/users/${userId}/sessions/${tokenId}`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['user-sessions', sessionsUser?.id] })
            broadcastQueryInvalidation(['user-sessions'], 'Sessões')
            toast.success('Sessão revogada com sucesso!')
        },
        onError: (err: unknown) => {
            toast.error((err as AxiosError<ApiError>).response?.data?.message ?? 'Erro ao revogar sessão.')
        },
    })

    const forceLogoutMutation = useMutation({
        mutationFn: (userId: number) => api.post(`/users/${userId}/force-logout`),
        onSuccess: (res: { data?: { message?: string } }) => {
            queryClient.invalidateQueries({ queryKey: ['user-sessions'] })
            broadcastQueryInvalidation(['user-sessions'], 'Sessões')
            toast.success(res.data?.message ?? 'Sessões revogadas com sucesso!')
        },
        onError: (err: unknown) => {
            toast.error((err as AxiosError<ApiError>).response?.data?.message ?? 'Erro ao revogar sessões.')
        },
    })

    const handleExportCsv = () => {
        api.get('/users/export', { responseType: 'blob' }).then((res) => {
            const url = URL.createObjectURL(res.data)
            const link = document.createElement('a')
            link.href = url
            link.download = `usuarios_${new Date().toISOString().slice(0, 10)}.csv`
            link.click()
            URL.revokeObjectURL(url)
        }).catch(() => toast.error('Erro ao exportar CSV.'))
    }

    const toggleSelectAll = () => {
        if (selectedIds.length === users.length) {
            setSelectedIds([])
        } else {
            setSelectedIds((users || []).map(u => u.id))
        }
    }

    const toggleSelect = (id: number) => {
        setSelectedIds(prev =>
            prev.includes(id) ? (prev || []).filter(x => x !== id) : [...prev, id]
        )
    }

    const openCreate = () => {
        setEditingUser(null)
        reset(defaultValues)
        setShowPassword(false)
        setShowForm(true)
    }

    const openEdit = (user: User) => {
        setEditingUser(user)
        reset({
            name: user.name,
            email: user.email,
            phone: user.phone ? maskPhone(user.phone) : '',
            password: '',
            password_confirmation: '',
            roles: (user.roles || []).map(r => r.id),
            is_active: user.is_active,
            branch_id: user.branch_id ?? null,
        })
        setShowPassword(false)
        setShowForm(true)
    }

    const closeForm = () => {
        setShowForm(false)
        setEditingUser(null)
        reset(defaultValues)
    }

    const toggleRole = (roleId: number) => {
        const currentRoles = watch('roles') || []
        setValue('roles',
            currentRoles.includes(roleId)
                ? currentRoles.filter(r => r !== roleId)
                : [...currentRoles, roleId]
        )
    }

    return (
        <div className="space-y-5">
            <PageHeader
                title="Usuários"
                subtitle="Gerencie os usuários do sistema"
                count={totalUsers}
                actions={[
                    ...((hasPermission('iam.user.export') || canView) ? [{ label: 'Exportar CSV', onClick: handleExportCsv, icon: <Download className="h-4 w-4" />, variant: 'outline' as const }] : []),
                    ...(canCreate ? [{ label: 'Novo Usuário', onClick: openCreate, icon: <Plus className="h-4 w-4" /> }] : []),
                ]}
            />

            {canView && statsData && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-brand-50 p-2.5"><Users className="h-5 w-5 text-brand-600" /></div>
                            <div>
                                <p className="text-2xl font-bold text-surface-900">{statsData.total}</p>
                                <p className="text-xs text-surface-500">Total de usuários</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-emerald-50 p-2.5"><UserPlus className="h-5 w-5 text-emerald-600" /></div>
                            <div>
                                <p className="text-2xl font-bold text-emerald-700">{statsData.active}</p>
                                <p className="text-xs text-surface-500">Ativos</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-red-50 p-2.5"><UserMinus className="h-5 w-5 text-red-600" /></div>
                            <div>
                                <p className="text-2xl font-bold text-red-700">{statsData.inactive}</p>
                                <p className="text-xs text-surface-500">Inativos</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-amber-50 p-2.5"><AlertCircle className="h-5 w-5 text-amber-600" /></div>
                            <div>
                                <p className="text-2xl font-bold text-amber-700">{statsData.never_logged}</p>
                                <p className="text-xs text-surface-500">Nunca logaram</p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {canView && statsData && (statsData.by_role || statsData.recent_users) && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {statsData.by_role && Object.keys(statsData.by_role).length > 0 && (
                        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                            <h3 className="text-sm font-semibold text-surface-900 mb-3">Distribuição por Role</h3>
                            <div className="space-y-2">
                                {Object.entries(statsData.by_role as Record<string, number>).map(([role, count]) => (
                                    <div key={role} className="flex items-center justify-between">
                                        <span className="text-sm text-surface-600 capitalize">{role.replace(/_/g, ' ')}</span>
                                        <Badge variant="brand">{count}</Badge>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    {statsData.recent_users && (statsData.recent_users as { id: number; name: string; email: string; created_at: string }[]).length > 0 && (
                        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                            <h3 className="text-sm font-semibold text-surface-900 mb-3">Últimos Cadastrados</h3>
                            <div className="space-y-2">
                                {(statsData.recent_users as { id: number; name: string; email: string; created_at: string }[]).map((u) => (
                                    <div key={u.id} className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-surface-800">{u.name}</p>
                                            <p className="text-xs text-surface-500">{u.email}</p>
                                        </div>
                                        <span className="text-xs text-surface-400">
                                            {u.created_at ? new Date(u.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : '—'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            <div className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-1 rounded-lg border border-default bg-surface-50 p-1">
                    {([['all', 'Todos'], ['active', 'Ativos'], ['inactive', 'Inativos']] as const).map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => { setStatusFilter(key); setPage(1); setSelectedIds([]) }}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-xs font-medium transition-all',
                                statusFilter === key
                                    ? 'bg-surface-0 text-surface-900 shadow-sm'
                                    : 'text-surface-500 hover:text-surface-700'
                            )}
                            aria-label={`Filtrar por ${label.toLowerCase()}`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={roleFilter}
                        onChange={(e) => { setRoleFilter(e.target.value); setPage(1) }}
                        className="rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm text-surface-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                        aria-label="Filtrar por role"
                    >
                        <option value="">Todas as roles</option>
                        {(roles || []).map(r => (
                            <option key={r.id} value={r.name}>{r.display_name || r.label || r.name}</option>
                        ))}
                    </select>
                    <div className="relative max-w-sm w-full">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                        <input
                            type="text"
                            value={searchInput}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => { setSearchInput(e.target.value); debouncedSearch(e.target.value) }}
                            placeholder="Buscar por nome ou email..."
                            className="w-full rounded-lg border border-default bg-surface-50 py-2.5 pl-10 pr-4 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                        />
                    </div>
                </div>
            </div>

            {selectedIds.length > 0 && canUpdate && (
                <div className="flex items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-2.5">
                    <span className="text-sm font-medium text-brand-700">
                        {selectedIds.length} selecionado(s)
                    </span>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => bulkToggleMutation.mutate({ user_ids: selectedIds, is_active: true })}
                            loading={bulkToggleMutation.isPending}
                            aria-label="Ativar usuários selecionados"
                        >
                            <UserCheck className="h-3.5 w-3.5 mr-1" /> Ativar
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => bulkToggleMutation.mutate({ user_ids: selectedIds, is_active: false })}
                            loading={bulkToggleMutation.isPending}
                            aria-label="Desativar usuários selecionados"
                        >
                            <UserX className="h-3.5 w-3.5 mr-1" /> Desativar
                        </Button>
                    </div>
                    <button
                        onClick={() => setSelectedIds([])}
                        className="ml-auto text-xs text-surface-500 hover:text-surface-700"
                    >
                        Limpar seleção
                    </button>
                </div>
            )}

            <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-subtle bg-surface-50">
                            {canUpdate && (
                                <th className="w-10 px-3 py-2.5">
                                    <button onClick={toggleSelectAll} className="text-surface-400 hover:text-surface-600" aria-label="Selecionar todos os usuários">
                                        {selectedIds.length === users.length && users.length > 0
                                            ? <CheckSquare className="h-4 w-4 text-brand-500" />
                                            : <Square className="h-4 w-4" />}
                                    </button>
                                </th>
                            )}
                            <th className="px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500">Nome</th>
                            <th className="px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500">E-mail</th>
                            <th className="px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500">Roles</th>
                            <th className="px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500 hidden md:table-cell">Filial</th>
                            <th className="px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500">Status</th>
                            <th className="px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500 hidden lg:table-cell">Último Login</th>
                            <th className="px-3.5 py-2.5 text-left text-xs font-medium uppercase tracking-wider text-surface-500 hidden xl:table-cell">Criado em</th>
                            <th className="px-3.5 py-2.5 text-right text-xs font-medium uppercase tracking-wider text-surface-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-subtle">
                        {isLoading ? (
                            <>{[...Array(5)].map((_, i) => (
                                <tr key={`sk-${i}`} className="animate-pulse">
                                    {canUpdate && <td className="w-10 px-3 py-4"><div className="h-4 w-4 rounded bg-surface-200" /></td>}
                                    <td className="px-4 py-4"><div className="flex items-center gap-3"><div className="h-9 w-9 rounded-full bg-surface-200" /><div className="space-y-1.5"><div className="h-3.5 w-28 rounded bg-surface-200" /><div className="h-3 w-16 rounded bg-surface-200" /></div></div></td>
                                    <td className="px-4 py-4"><div className="h-3.5 w-36 rounded bg-surface-200" /></td>
                                    <td className="px-4 py-4"><div className="h-5 w-16 rounded bg-surface-200" /></td>
                                    <td className="px-4 py-4"><div className="h-5 w-14 rounded bg-surface-200" /></td>
                                    <td className="px-4 py-4 hidden lg:table-cell"><div className="h-3.5 w-24 rounded bg-surface-200" /></td>
                                    <td className="px-4 py-4 hidden xl:table-cell"><div className="h-3.5 w-20 rounded bg-surface-200" /></td>
                                    <td className="px-4 py-4"><div className="h-6 w-20 rounded bg-surface-200 ml-auto" /></td>
                                </tr>
                            ))}</>
                        ) : isError ? (
                            <tr><td colSpan={canUpdate ? 9 : 8} className="px-4 py-12 text-center">
                                <p className="text-sm text-red-500">{(error as AxiosError<ApiError>)?.response?.data?.message ?? 'Erro ao carregar usuários.'}</p>
                                <Button variant="outline" size="sm" className="mt-2" onClick={() => refetch()}>Tentar novamente</Button>
                            </td></tr>
                        ) : users.length === 0 ? (
                            <tr><td colSpan={canUpdate ? 9 : 8} className="px-4 py-12 text-center text-sm text-surface-500">Nenhum usuário encontrado</td></tr>
                        ) : (users || []).map((user) => (
                            <tr key={user.id} className="hover:bg-surface-50 transition-colors duration-100">
                                {canUpdate && (
                                    <td className="w-10 px-3 py-3">
                                        <button onClick={() => toggleSelect(user.id)} className="text-surface-400 hover:text-surface-600" aria-label={`Selecionar usuário ${user.name}`}>
                                            {selectedIds.includes(user.id)
                                                ? <CheckSquare className="h-4 w-4 text-brand-500" />
                                                : <Square className="h-4 w-4" />}
                                        </button>
                                    </td>
                                )}
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-brand-700 text-sm font-bold">
                                            {user.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-surface-900">{user.name}</p>
                                            <p className="text-xs text-surface-500">{user.phone ?? '—'}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-sm text-surface-600">{user.email}</td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1">
                                        {(user.roles || []).map(role => (
                                            <Badge key={role.id} variant="brand">{role.display_name || role.label || role.name}</Badge>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-4 py-3 hidden md:table-cell">
                                    <span className="text-sm text-surface-600">{user.branch?.name ?? '—'}</span>
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant={user.is_active ? 'success' : 'danger'} dot>
                                        {user.is_active ? 'Ativo' : 'Inativo'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 hidden lg:table-cell">
                                    <span className="text-xs text-surface-500">
                                        {user.last_login_at
                                            ? new Date(user.last_login_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
                                            : 'Nunca'}
                                    </span>
                                </td>
                                <td className="px-4 py-3 hidden xl:table-cell">
                                    <span className="text-xs text-surface-500">
                                        {user.created_at
                                            ? new Date(user.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' })
                                            : '—'}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center justify-end gap-1">
                                        {canUpdate && (
                                            <Button variant="ghost" size="sm" onClick={() => openEdit(user)} aria-label={`Editar ${user.name}`}>
                                                Editar
                                            </Button>
                                        )}
                                        {canUpdate && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => toggleMutation.mutate(user.id)}
                                                title={user.is_active ? 'Desativar' : 'Ativar'}
                                                aria-label={`${user.is_active ? 'Desativar' : 'Ativar'} ${user.name}`}
                                            >
                                                {user.is_active ? <UserX className="h-4 w-4 text-red-500" /> : <UserCheck className="h-4 w-4 text-emerald-500" />}
                                            </Button>
                                        )}
                                        {canUpdate && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => { setResetPasswordUser(user); setNewPassword('') }}
                                                title="Redefinir Senha"
                                                aria-label={`Redefinir senha de ${user.name}`}
                                            >
                                                <KeyRound className="h-4 w-4 text-amber-500" />
                                            </Button>
                                        )}
                                        {canUpdate && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setSessionsUser(user)}
                                                title="Sessões Ativas"
                                                aria-label={`Sessões ativas de ${user.name}`}
                                            >
                                                <Monitor className="h-4 w-4 text-blue-500" />
                                            </Button>
                                        )}
                                        {canManagePermissions && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setPermissionsUser(user)}
                                                title="Permissões Individuais"
                                                aria-label={`Permissões individuais de ${user.name}`}
                                            >
                                                <ShieldCheck className="h-4 w-4 text-emerald-500" />
                                            </Button>
                                        )}
                                        {canUpdate && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setAuditTrailUser(user)}
                                                title="Histórico de Ações"
                                                aria-label={`Histórico de ações de ${user.name}`}
                                            >
                                                <History className="h-4 w-4 text-teal-500" />
                                            </Button>
                                        )}
                                        {canUpdate && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => forceLogoutMutation.mutate(user.id)}
                                                title="Encerrar Todas as Sessões"
                                                aria-label={`Encerrar todas as sessões de ${user.name}`}
                                                loading={forceLogoutMutation.isPending}
                                            >
                                                <LogOut className="h-4 w-4 text-orange-500" />
                                            </Button>
                                        )}
                                        {canDelete && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setDeleteConfirmUser(user)}
                                                aria-label={`Excluir ${user.name}`}
                                                title="Excluir"
                                            >
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* Paginação */}
                {lastPage > 1 && (
                    <div className="flex items-center justify-between border-t border-subtle px-4 py-3">
                        <p className="text-sm text-surface-500">
                            Mostrando página {page} de {lastPage} ({totalUsers} usuários)
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={page <= 1}
                                onClick={() => setPage(p => Math.max(1, p - 1))}
                            >
                                Anterior
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={page >= lastPage}
                                onClick={() => setPage(p => p + 1)}
                            >
                                Próximo
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <Modal
                open={showForm}
                onOpenChange={(open: boolean) => setShowForm(open)}
                title={editingUser ? 'Editar Usuário' : 'Novo Usuário'}
                size="lg"
            >
                <form onSubmit={handleSubmit((data) => saveMutation.mutate(data))} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField label="Nome" error={errors.name?.message} required>
                            <Input
                                {...register('name')}
                                placeholder="ex: João Silva"
                            />
                        </FormField>
                        <FormField label="E-mail" error={errors.email?.message} required>
                            <Input
                                {...register('email')}
                                type="email"
                                placeholder="ex: joao@email.com"
                            />
                        </FormField>
                        <FormField label="Telefone" error={errors.phone?.message}>
                            <Input
                                {...register('phone')}
                                onChange={(e) => {
                                    register('phone').onChange(e);
                                    setValue('phone', maskPhone(e.target.value));
                                }}
                                placeholder="(00) 00000-0000"
                                maxLength={15}
                                inputMode="tel"
                            />
                        </FormField>
                        <FormField label={editingUser ? 'Nova Senha (deixe vazio para manter)' : 'Senha'} required={!editingUser} error={errors.password?.message}>
                            <div className="relative">
                                <Input
                                    {...register('password')}
                                    type={showPassword ? 'text' : 'password'}
                                    placeholder="Digite a senha"
                                    className="pr-10"
                                />
                                <button type="button" onClick={() => setShowPassword(v => !v)} className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 transition-colors" aria-label="Mostrar ou ocultar senha">
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                        </FormField>
                        <FormField label={editingUser ? 'Confirmar Nova Senha' : 'Confirmar Senha'} required={!editingUser} error={errors.password_confirmation?.message}>
                            <div className="relative">
                                <Input
                                    {...register('password_confirmation')}
                                    type={showPassword ? 'text' : 'password'}
                                    placeholder="Repita a senha"
                                    className="pr-10"
                                />
                                <button type="button" onClick={() => setShowPassword(v => !v)} className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 transition-colors" aria-label="Mostrar ou ocultar senha">
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                        </FormField>
                    </div>

                    {branches.length > 0 && (
                        <div>
                            <label className="mb-1 block text-sm font-medium text-surface-700">Filial</label>
                            <select
                                {...register('branch_id', { setValueAs: v => (v === "" ? null : Number(v)) })}
                                className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2.5 text-sm text-surface-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                                aria-label="Selecionar filial"
                            >
                                <option value="">Sem filial</option>
                                {(branches || []).map(b => (
                                    <option key={b.id} value={b.id}>{b.name}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Roles */}
                    <div>
                        <label className="mb-2 block text-sm font-medium text-surface-700">Roles</label>
                        <div className="flex flex-wrap gap-2">
                            {(roles || []).map((role: Role) => (
                                <button
                                    key={role.id}
                                    type="button"
                                    onClick={() => toggleRole(role.id)}
                                    className={cn(
                                        'rounded-full border px-3 py-1.5 text-xs font-medium transition-all',
                                        formData.roles?.includes(role.id)
                                            ? 'border-brand-500 bg-brand-50 text-brand-700'
                                            : 'border-default text-surface-600 hover:border-surface-400'
                                    )}
                                >
                                    {role.display_name || role.label || role.name}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setValue('is_active', !formData.is_active)}
                            className={cn(
                                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                                formData.is_active ? 'bg-brand-500' : 'bg-surface-400'
                            )}
                            aria-label={formData.is_active ? 'Desativar usuário' : 'Ativar usuário'}
                        >
                            <span className={cn(
                                'inline-block h-4 w-4 transform rounded-full bg-surface-0 transition-transform',
                                formData.is_active ? 'translate-x-6' : 'translate-x-1'
                            )} />
                        </button>
                        <span className="text-sm font-medium text-surface-700">
                            {formData.is_active ? 'Ativo' : 'Inativo'}
                        </span>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" type="button" onClick={closeForm}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={saveMutation.isPending}>
                            {editingUser ? 'Salvar' : 'Criar Usuário'}
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={!!resetPasswordUser}
                onOpenChange={(open: boolean) => { if (!open) { setResetPasswordUser(null); resetPwForm.reset() } }}
                title={`Redefinir Senha: ${resetPasswordUser?.name ?? ''}`}
                size="sm"
            >
                <form
                    onSubmit={resetPwForm.handleSubmit((data) => {
                        if (resetPasswordUser) {
                            resetPasswordMutation.mutate({ userId: resetPasswordUser.id, password: data.password, password_confirmation: data.password_confirmation })
                        }
                    })}
                    className="space-y-4"
                >
                    <FormField label="Nova Senha" error={resetPwForm.formState.errors.password?.message} required>
                        <div className="relative">
                            <Input
                                {...resetPwForm.register('password')}
                                type={showResetPassword ? 'text' : 'password'}
                                placeholder="Mínimo 8 caracteres"
                                className="pr-10"
                            />
                            <button type="button" onClick={() => setShowResetPassword(v => !v)} className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 transition-colors" aria-label="Mostrar ou ocultar senha">
                                {showResetPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </div>
                    </FormField>
                    <FormField label="Confirmar Senha" error={resetPwForm.formState.errors.password_confirmation?.message} required>
                        <div className="relative">
                            <Input
                                {...resetPwForm.register('password_confirmation')}
                                type={showResetPassword ? 'text' : 'password'}
                                placeholder="Repita a nova senha"
                                className="pr-10"
                            />
                            <button type="button" onClick={() => setShowResetPassword(v => !v)} className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 transition-colors" aria-label="Mostrar ou ocultar senha">
                                {showResetPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </div>
                    </FormField>
                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" type="button" onClick={() => { setResetPasswordUser(null); resetPwForm.reset() }}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={resetPasswordMutation.isPending}>
                            Redefinir Senha
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={!!deleteConfirmUser}
                onOpenChange={(open: boolean) => { if (!open) setDeleteConfirmUser(null) }}
                title="Confirmar Exclusão"
                size="sm"
            >
                <div className="space-y-4">
                    <p className="text-sm text-surface-600">
                        Tem certeza que deseja excluir o usuário <strong>{deleteConfirmUser?.name}</strong>?
                        Esta ação não pode ser desfeita.
                    </p>
                    <div className="flex items-center justify-end gap-3 border-t border-subtle pt-4">
                        <Button variant="outline" onClick={() => setDeleteConfirmUser(null)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="danger"
                            loading={deleteMutation.isPending}
                            onClick={() => deleteConfirmUser && deleteMutation.mutate(deleteConfirmUser.id)}
                        >
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>

            <Modal
                open={!!sessionsUser}
                onOpenChange={(open: boolean) => { if (!open) setSessionsUser(null) }}
                title={`Sessões Ativas: ${sessionsUser?.name ?? ''}`}
                size="lg"
            >
                <div className="space-y-3">
                    {sessionsQuery.isLoading ? (
                        <p className="text-center text-sm text-surface-500 py-6">Carregando sessões...</p>
                    ) : sessionsQuery.isError ? (
                        <p className="text-center text-sm text-red-500 py-6">Erro ao carregar sessões.</p>
                    ) : (sessionsQuery.data?.data ?? []).length === 0 ? (
                        <p className="text-center text-sm text-surface-500 py-6">Nenhuma sessão ativa.</p>
                    ) : (
                        (sessionsQuery.data?.data ?? []).map((session: Session) => (
                            <div key={session.id} className="flex items-center justify-between rounded-lg border border-default bg-surface-50 px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium text-surface-800">{session.name ?? 'Token'}</p>
                                    <div className="flex items-center gap-3 mt-1 text-xs text-surface-500">
                                        {session.last_used_at && (
                                            <span>
                                                Último uso: {new Date(session.last_used_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        )}
                                        {session.expires_at && (
                                            <span>
                                                Expira: {new Date(session.expires_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => sessionsUser && revokeSessionMutation.mutate({ userId: sessionsUser.id, tokenId: session.id })}
                                    loading={revokeSessionMutation.isPending}
                                    title="Revogar Sessão"
                                >
                                    <Trash2 className="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        ))
                    )}
                </div>
                <div className="flex justify-end border-t border-subtle pt-4">
                    <Button variant="outline" onClick={() => setSessionsUser(null)}>
                        Fechar
                    </Button>
                </div>
            </Modal>

            <AuditTrailModal user={auditTrailUser} onClose={() => setAuditTrailUser(null)} />
            <UserPermissionsModal user={permissionsUser} onClose={() => setPermissionsUser(null)} />
        </div>
    )
}

function AuditTrailModal({ user, onClose }: { user: User | null; onClose: () => void }) {
    const [page, setPage] = useState(1)

    const { data, isLoading, isError } = useQuery({
        queryKey: ['user-audit-trail', user?.id, page],
        queryFn: () => api.get(`/users/${user!.id}/audit-trail`, { params: { page } }).then(r => r.data),
        enabled: !!user,
    })

    // Suportando paginação do backend (se houver) ou array plano
    const entries: AuditEntry[] = data?.data ?? data ?? []
    const totalPages = data?.last_page ?? 1

    const actionColors: Record<string, string> = {
        created: 'bg-emerald-100 text-emerald-700',
        updated: 'bg-blue-100 text-blue-700',
        deleted: 'bg-red-100 text-red-700',
        login: 'bg-teal-100 text-teal-700',
        logout: 'bg-orange-100 text-orange-700',
        status_changed: 'bg-amber-100 text-amber-700',
        commented: 'bg-sky-100 text-sky-700',
        tenant_switch: 'bg-emerald-100 text-emerald-700',
        password_reset: 'bg-rose-100 text-rose-700',
    }

    const actionLabels: Record<string, string> = {
        created: 'Criado',
        updated: 'Atualizado',
        deleted: 'Excluído',
        login: 'Login',
        logout: 'Logout',
        status_changed: 'Status',
        commented: 'Comentário',
        tenant_switch: 'Empresa',
        password_reset: 'Senha',
    }

    return (
        <Modal
            open={!!user}
            onOpenChange={(open: boolean) => { if (!open) { onClose(); setPage(1) } }}
            title={`Histórico: ${user?.name ?? ''}`}
            size="lg"
        >
            <div className="space-y-3 max-h-96 overflow-y-auto pr-1">
                {isLoading ? (
                    <div className="space-y-3 py-2">
                        {[...Array(4)].map((_, i) => (
                            <div key={i} className="flex gap-3 animate-pulse">
                                <div className="h-6 w-16 rounded bg-surface-200" />
                                <div className="flex-1 space-y-1">
                                    <div className="h-4 w-3/4 rounded bg-surface-200" />
                                    <div className="h-3 w-1/3 rounded bg-surface-200" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : isError ? (
                    <p className="text-center text-sm text-red-500 py-6">Erro ao carregar histórico.</p>
                ) : entries.length === 0 ? (
                    <div className="text-center py-8">
                        <History className="h-8 w-8 text-surface-300 mx-auto mb-2" />
                        <p className="text-sm text-surface-500">Nenhum registro de atividade encontrado.</p>
                    </div>
                ) : (
                    (entries || []).map((entry: AuditEntry, idx: number) => (
                        <div key={entry.id ?? idx} className="flex items-start gap-3 rounded-lg border border-default bg-surface-50 px-4 py-3">
                            <span className={cn('mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold uppercase', actionColors[entry.action] ?? 'bg-surface-200 text-surface-700')}>
                                {actionLabels[entry.action] ?? entry.action}
                            </span>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm text-surface-800">{entry.description}</p>
                                <div className="flex items-center gap-3 mt-1 text-xs text-surface-500">
                                    {entry.created_at && (
                                        <span>
                                            {new Date(entry.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })}
                                        </span>
                                    )}
                                    {entry.ip_address && <span>IP: {entry.ip_address}</span>}
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {(totalPages > 1 || page > 1) && (
                <div className="flex items-center justify-between border-t border-subtle pt-4 mt-2">
                    <span className="text-xs text-surface-500">Página {page} de {Math.max(totalPages, page)}</span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={page === 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                        >
                            Anterior
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={page >= totalPages && entries.length < 15}
                            onClick={() => setPage((p) => p + 1)}
                        >
                            Próxima
                        </Button>
                    </div>
                </div>
            )}

            <div className="flex justify-end border-t border-subtle pt-4 mt-2">
                <Button variant="outline" onClick={() => { onClose(); setPage(1) }}>
                    Fechar
                </Button>
            </div>
        </Modal>
    )
}

function UserPermissionsModal({ user, onClose }: { user: User | null; onClose: () => void }) {
    const queryClient = useQueryClient()
    const [tab, setTab] = useState<'direct' | 'denied'>('direct')
    const [search, setSearch] = useState('')

    const { data: permData, isLoading: permLoading } = useQuery({
        queryKey: ['user-permissions', user?.id],
        queryFn: () => api.get(`/users/${user!.id}/permissions`).then(r => r.data),
        enabled: !!user,
    })

    const { data: permGroupsData } = useQuery({
        queryKey: ['permissions'],
        queryFn: () => api.get('/permissions').then(r => r.data),
        enabled: !!user,
    })

    const permissionGroups: PermissionGroup[] = Array.isArray(permGroupsData) ? permGroupsData : permGroupsData?.data ?? []

    const directPermissions: string[] = permData?.direct_permissions ?? []
    const deniedPermissions: string[] = permData?.denied_permissions ?? []
    const rolePermissions: string[] = permData?.role_permissions ?? []

    const syncDirectMut = useMutation({
        mutationFn: (permissions: string[]) =>
            api.put(`/users/${user!.id}/permissions`, { permissions }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['user-permissions', user?.id] })
            toast.success('Permissões diretas atualizadas!')
        },
        onError: (err) => toast.error((err as AxiosError<ApiError>).response?.data?.message ?? 'Erro ao atualizar permissões.'),
    })

    const syncDeniedMut = useMutation({
        mutationFn: (denied_permissions: string[]) =>
            api.put(`/users/${user!.id}/denied-permissions`, { denied_permissions }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['user-permissions', user?.id] })
            toast.success('Permissões negadas atualizadas!')
        },
        onError: (err) => toast.error((err as AxiosError<ApiError>).response?.data?.message ?? 'Erro ao atualizar permissões negadas.'),
    })

    const toggleDirect = (permName: string) => {
        const updated = directPermissions.includes(permName)
            ? (directPermissions || []).filter(p => p !== permName)
            : [...directPermissions, permName]
        syncDirectMut.mutate(updated)
    }

    const toggleDenied = (permName: string) => {
        const updated = deniedPermissions.includes(permName)
            ? (deniedPermissions || []).filter(p => p !== permName)
            : [...deniedPermissions, permName]
        syncDeniedMut.mutate(updated)
    }

    const filteredGroups = permissionGroups
        .map(g => ({
            ...g,
            permissions: (g.permissions || []).filter(p =>
                !search || p.name.toLowerCase().includes(search.toLowerCase())
            ),
        }))
        .filter(g => g.permissions.length > 0)

    return (
        <Modal
            open={!!user}
            onOpenChange={(open: boolean) => { if (!open) { onClose(); setSearch(''); setTab('direct') } }}
            title={`Permissões: ${user?.name ?? ''}`}
            size="xl"
        >
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <div className="flex items-center gap-1 rounded-lg border border-default bg-surface-50 p-1">
                        <button
                            onClick={() => setTab('direct')}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-xs font-medium transition-all',
                                tab === 'direct'
                                    ? 'bg-surface-0 text-surface-900 shadow-sm'
                                    : 'text-surface-500 hover:text-surface-700'
                            )}
                        >
                            <ShieldCheck className="h-3.5 w-3.5 inline mr-1" />
                            Diretas ({directPermissions.length})
                        </button>
                        <button
                            onClick={() => setTab('denied')}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-xs font-medium transition-all',
                                tab === 'denied'
                                    ? 'bg-surface-0 text-red-700 shadow-sm'
                                    : 'text-surface-500 hover:text-surface-700'
                            )}
                        >
                            <ShieldOff className="h-3.5 w-3.5 inline mr-1" />
                            Negadas ({deniedPermissions.length})
                        </button>
                    </div>
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-surface-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearch(e.target.value)}
                            placeholder="Filtrar permissões..."
                            className="w-full rounded-lg border border-default bg-surface-50 py-2 pl-9 pr-3 text-xs focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15"
                        />
                    </div>
                </div>

                {tab === 'direct' && (
                    <div className="rounded-lg border border-brand-200 bg-brand-50/30 px-3 py-2">
                        <p className="text-xs text-brand-700 flex items-center gap-1.5">
                            <ShieldAlert className="h-3.5 w-3.5" />
                            Permissões diretas são adicionadas <strong>além</strong> das permissões herdadas pelas roles do usuário.
                        </p>
                    </div>
                )}
                {tab === 'denied' && (
                    <div className="rounded-lg border border-red-200 bg-red-50/30 px-3 py-2">
                        <p className="text-xs text-red-700 flex items-center gap-1.5">
                            <ShieldAlert className="h-3.5 w-3.5" />
                            Permissões negadas <strong>removem</strong> o acesso mesmo que concedido pelas roles.
                        </p>
                    </div>
                )}

                <div className="max-h-96 overflow-y-auto space-y-3 rounded-lg border border-default p-3">
                    {permLoading ? (
                        <div className="space-y-3 py-2">
                            {[...Array(4)].map((_, i) => (
                                <div key={i} className="h-6 w-full rounded bg-surface-200 animate-pulse" />
                            ))}
                        </div>
                    ) : filteredGroups.length === 0 ? (
                        <p className="text-center text-sm text-surface-500 py-4">
                            {search ? 'Nenhuma permissão encontrada.' : 'Não há permissões cadastradas.'}
                        </p>
                    ) : (filteredGroups || []).map((group) => (
                        <div key={group.id}>
                            <h4 className="text-xs font-medium uppercase tracking-wider text-surface-500 mb-1.5">
                                {group.name}
                            </h4>
                            <div className="flex flex-wrap gap-1.5">
                                {(group.permissions || []).map((perm) => {
                                    const isDirect = directPermissions.includes(perm.name)
                                    const isDenied = deniedPermissions.includes(perm.name)
                                    const isFromRole = rolePermissions.includes(perm.name)
                                    const isLoading = syncDirectMut.isPending || syncDeniedMut.isPending

                                    if (tab === 'direct') {
                                        return (
                                            <button
                                                key={perm.id}
                                                type="button"
                                                disabled={isLoading}
                                                onClick={() => toggleDirect(perm.name)}
                                                className={cn(
                                                    'rounded-md border px-2 py-1 text-xs font-medium transition-all disabled:opacity-50',
                                                    isDirect
                                                        ? 'border-brand-400 bg-brand-50 text-brand-700'
                                                        : isFromRole
                                                            ? 'border-emerald-300 bg-emerald-50/50 text-emerald-600'
                                                            : 'border-default text-surface-500 hover:border-surface-400'
                                                )}
                                                title={isDirect ? 'Concedida diretamente (clique para remover)' : isFromRole ? 'Herdada da role' : 'Clique para conceder'}
                                            >
                                                {perm.name.split('.').slice(1).join('.')}
                                                {isFromRole && !isDirect && <span className="ml-1 text-[10px] opacity-60">role</span>}
                                            </button>
                                        )
                                    }

                                    return (
                                        <button
                                            key={perm.id}
                                            type="button"
                                            disabled={isLoading}
                                            onClick={() => toggleDenied(perm.name)}
                                            className={cn(
                                                'rounded-md border px-2 py-1 text-xs font-medium transition-all disabled:opacity-50',
                                                isDenied
                                                    ? 'border-red-400 bg-red-50 text-red-700 line-through'
                                                    : 'border-default text-surface-500 hover:border-surface-400'
                                            )}
                                            title={isDenied ? 'Negada (clique para permitir)' : 'Clique para negar'}
                                        >
                                            {perm.name.split('.').slice(1).join('.')}
                                        </button>
                                    )
                                })}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="flex items-center justify-between border-t border-subtle pt-4">
                    <div className="flex items-center gap-3 text-xs text-surface-500">
                        <span className="flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-brand-200 border border-brand-400" /> Direta</span>
                        <span className="flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-emerald-100 border border-emerald-300" /> Via Role</span>
                        <span className="flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-red-100 border border-red-400" /> Negada</span>
                    </div>
                    <Button variant="outline" onClick={() => { onClose(); setSearch(''); setTab('direct') }}>
                        Fechar
                    </Button>
                </div>
            </div>
        </Modal>
    )
}
