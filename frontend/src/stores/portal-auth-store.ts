import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import api, { unwrapData, useAuthCookie } from '@/lib/api'
import { clearAuthenticatedQueryCache } from '@/lib/query-client'
import { subscribeAuthenticatedCacheClear } from '@/lib/cross-tab-sync'

interface PortalUser {
    id: number
    name: string
    email: string
    customer_id: number
    tenant_id: number
    customer: {
        id: number
        name: string
    }
}

interface PortalAuthState {
    user: PortalUser | null
    token: string | null
    isAuthenticated: boolean
    isLoading: boolean

    login: (email: string, password: string, tenantId: number) => Promise<void>
    logout: () => Promise<void>
    fetchMe: () => Promise<void>
}

export const usePortalAuthStore = create<PortalAuthState>()(
    persist(
        (set, get) => ({
            user: null,
            token: null,
            isAuthenticated: false,
            isLoading: false,

            login: async (email, password, tenantId) => {
                clearAuthenticatedQueryCache()
                set({ isLoading: true })
                try {
                    const response = await api.post('/portal/login', { email, password, tenant_id: tenantId })
                    const payload = unwrapData<{ user: PortalUser; token: string }>(response)
                    if (!payload.token) {
                        throw new Error('Token de autenticação não recebido')
                    }
                    // FIX-19: Usar apenas portal_token, sem sobrescrever auth_token do admin
                    localStorage.setItem('portal_token', payload.token)

                    clearAuthenticatedQueryCache()
                    set({
                        user: payload.user,
                        token: payload.token,
                        isAuthenticated: true,
                    })

                    // Refresh user data from server to ensure completeness
                    await get().fetchMe()
                } catch (err: unknown) {
                    clearAuthenticatedQueryCache()
                    set({ isAuthenticated: false, user: null, token: null })
                    throw err
                } finally {
                    set({ isLoading: false })
                }
            },

            logout: async () => {
                clearAuthenticatedQueryCache()
                try {
                    await api.post('/portal/logout')
                } catch {
                    // ignore
                } finally {
                    localStorage.removeItem('portal_token')
                    localStorage.removeItem('portal-auth-store')
                    set({
                        user: null,
                        token: null,
                        isAuthenticated: false,
                    })
                    clearAuthenticatedQueryCache({ broadcast: true, scope: 'portal' })
                }
            },

            fetchMe: async () => {
                try {
                    const response = await api.get('/portal/me')
                    const payload = unwrapData<PortalUser>(response)
                    set({
                        user: payload,
                        isAuthenticated: true,
                    })
                } catch {
                    clearAuthenticatedQueryCache()
                    set({ isAuthenticated: false, user: null })
                    if (!useAuthCookie) {
                        localStorage.removeItem('portal_token')
                    }
                    localStorage.removeItem('portal-auth-store')
                    throw new Error('Portal session expired')
                }
            },
        }),
        {
            name: 'portal-auth-store',
            partialize: (state) => ({
                token: state.token,
                isAuthenticated: state.isAuthenticated,
            }),
        }
    )
)

subscribeAuthenticatedCacheClear((scope) => {
    if (scope !== 'portal' && scope !== 'all') {
        return
    }

    if (!useAuthCookie) {
        localStorage.removeItem('portal_token')
    }
    localStorage.removeItem('portal-auth-store')
    usePortalAuthStore.setState({
        user: null,
        token: null,
        isAuthenticated: false,
        isLoading: false,
    })
})
