import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { isAxiosError } from 'axios'
import api, { unwrapData, useAuthCookie } from '@/lib/api'
import { clearAuthenticatedQueryCache } from '@/lib/query-client'
import { subscribeAuthenticatedCacheClear } from '@/lib/cross-tab-sync'
import { setSentryUser } from '@/lib/sentry'

interface RoleDetail {
    name: string
    display_name: string
}

interface User {
    id: number
    name: string
    email: string
    phone: string | null
    tenant_id: number | null
    permissions: string[]
    roles: string[]
    role_details?: RoleDetail[]
    all_permissions?: string[]
    all_roles?: string[]
    tenant?: Tenant | null
}

interface Tenant {
    id: number
    name: string
    document: string | null
    email: string | null
    phone: string | null
    status: string
}

interface AuthState {
    user: User | null
    tenant: Tenant | null
    token: string | null
    isAuthenticated: boolean
    isLoading: boolean

    login: (email: string, password: string) => Promise<void>
    logout: () => Promise<void>
    fetchMe: () => Promise<void>
    hasPermission: (permission: string) => boolean
    hasRole: (role: string) => boolean
    setUser: (user: User) => void
}

function normalizeUser(rawUser: Partial<User> & Record<string, unknown> | User): User {
    const raw = rawUser as Partial<User> & Record<string, unknown>
    const permissions = Array.isArray(raw?.permissions)
        ? raw.permissions
        : (Array.isArray(raw?.all_permissions) ? raw.all_permissions : [])

    const roles = Array.isArray(raw?.roles)
        ? raw.roles
        : (Array.isArray(raw?.all_roles) ? raw.all_roles : [])

    return {
        id: Number(raw?.id) || 0,
        name: String(raw?.name || ''),
        email: String(raw?.email || ''),
        phone: raw?.phone ? String(raw.phone) : null,
        tenant_id: raw?.tenant_id ? Number(raw.tenant_id) : null,
        permissions,
        roles,
        role_details: Array.isArray(raw?.role_details) ? (raw.role_details as RoleDetail[]) : [],
        all_permissions: permissions,
        all_roles: roles,
        tenant: raw?.tenant ? (raw.tenant as Tenant) : null,
    }
}

export const useAuthStore = create<AuthState>()(
    persist(
        (set, get) => ({
            user: null,
            tenant: null,
            token: null,
            isAuthenticated: false,
            isLoading: false,

            login: async (email, password) => {
                clearAuthenticatedQueryCache()
                set({ isLoading: true })
                try {
                    const response = await api.post('/login', { email, password })
                    const payload = unwrapData<{ user: User; token?: string }>(response)
                    const user = normalizeUser(payload.user)

                    clearAuthenticatedQueryCache()
                    if (!useAuthCookie && payload.token) {
                        localStorage.setItem('auth_token', payload.token)
                    }
                    set({
                        user,
                        token: payload.token ?? null,
                        isAuthenticated: true,
                    })
                    setSentryUser({ id: user.id, email: user.email, name: user.name })

                    await get().fetchMe()
                } catch (err: unknown) {
                    clearAuthenticatedQueryCache()
                    setSentryUser(null)
                    set({ isAuthenticated: false, user: null, token: null })
                    if (isAxiosError(err) && err.response?.status === 403) {
                        throw new Error(err?.response?.data?.message ?? 'Conta desativada.')
                    }
                    throw err
                } finally {
                    set({ isLoading: false })
                }
            },

            logout: async () => {
                const token = !useAuthCookie ? localStorage.getItem('auth_token') : null
                clearAuthenticatedQueryCache()

                void api.post('/logout', undefined, token ? {
                    headers: { Authorization: `Bearer ${token}` },
                } : undefined).catch(() => {
                    // Ignorado: servidor pode estar indisponível; limpamos estado local imediatamente.
                })

                if (!useAuthCookie) {
                    localStorage.removeItem('auth_token')
                    localStorage.removeItem('auth-store')
                }
                // Clear PWA API caches on logout
                if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_CACHE' })
                }
                set({
                    user: null,
                    tenant: null,
                    token: null,
                    isAuthenticated: false,
                })
                setSentryUser(null)
                clearAuthenticatedQueryCache({ broadcast: true, scope: 'admin' })
            },

            fetchMe: async () => {
                const maxRetries = 2
                const retryDelayMs = 800
                let lastErr: unknown
                for (let attempt = 0; attempt <= maxRetries; attempt++) {
                    try {
                        const response = await api.get('/me')
                        const payload = unwrapData<{ user?: User } | User>(response)
                        const currentUser = payload && typeof payload === 'object' && 'user' in payload
                            ? payload.user
                            : payload
                        const normalizedUser = normalizeUser(currentUser ?? {})
                        const userData = {
                            ...normalizedUser,
                            tenant_id: normalizedUser.tenant?.id ?? normalizedUser.tenant_id ?? null,
                        }

                        set({
                            user: userData,
                            tenant: normalizedUser.tenant ?? null,
                            isAuthenticated: true,
                        })
                        setSentryUser({ id: userData.id, email: userData.email, name: userData.name })
                        return
                    } catch (err) {
                        lastErr = err
                        if (attempt < maxRetries) {
                            await new Promise(r => setTimeout(r, retryDelayMs))
                        }
                    }
                }
                setSentryUser(null)
                clearAuthenticatedQueryCache()
                set({ isAuthenticated: false, user: null, tenant: null })
                throw lastErr
            },

            hasPermission: (permission) => {
                const user = get().user
                if (!user) return false

                const roles = Array.isArray(user.roles)
                    ? user.roles
                    : (Array.isArray(user.all_roles) ? user.all_roles : [])

                if (roles.includes('super_admin')) return true

                const permissions = Array.isArray(user.permissions)
                    ? user.permissions
                    : (Array.isArray(user.all_permissions) ? user.all_permissions : [])

                return permissions.includes(permission)
            },

            hasRole: (role) => {
                const user = get().user
                if (!user) return false

                const roles = Array.isArray(user.roles)
                    ? user.roles
                    : (Array.isArray(user.all_roles) ? user.all_roles : [])

                return roles.includes(role)
            },

            setUser: (user) => set({ user: normalizeUser(user) }),
        }),
        {
            name: 'auth-store',
            partialize: (state) => ({
                token: state.token,
                isAuthenticated: state.isAuthenticated,
                user: state.user,
                tenant: state.tenant,
            }),
        }
    )
)

subscribeAuthenticatedCacheClear((scope) => {
    if (scope !== 'admin' && scope !== 'all') {
        return
    }

    setSentryUser(null)
    if (!useAuthCookie) {
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth-store')
    }
    useAuthStore.setState({
        user: null,
        tenant: null,
        token: null,
        isAuthenticated: false,
        isLoading: false,
    })
})
