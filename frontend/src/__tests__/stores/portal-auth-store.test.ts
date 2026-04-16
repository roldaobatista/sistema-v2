import { describe, it, expect, vi, beforeEach } from 'vitest'

// Mock api module before importing the store
vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

    return {
        ...actual,
        default: {
            post: vi.fn(),
            get: vi.fn(),
            interceptors: {
                request: { use: vi.fn() },
                response: { use: vi.fn() },
            },
        },
        useAuthCookie: false,
    }
})

import api from '@/lib/api'
import { usePortalAuthStore } from '@/stores/portal-auth-store'

describe('portal-auth-store', () => {
    beforeEach(() => {
        const _store = usePortalAuthStore.getState()
        usePortalAuthStore.setState({
            user: null,
            token: null,
            isAuthenticated: false,
            isLoading: false,
        })
        localStorage.clear()
    })

    it('has correct initial state', () => {
        const state = usePortalAuthStore.getState()
        expect(state.user).toBeNull()
        expect(state.token).toBeNull()
        expect(state.isAuthenticated).toBe(false)
        expect(state.isLoading).toBe(false)
    })

    it('persists only token and isAuthenticated via partialize', () => {
        // Set full state
        usePortalAuthStore.setState({
            user: { id: 1, name: 'Test', email: 'test@test', customer_id: 1, tenant_id: 1, customer: { id: 1, name: 'Customer' } },
            token: 'abc123',
            isAuthenticated: true,
            isLoading: true,
        })

        const stored = localStorage.getItem('portal-auth-store')
        if (stored) {
            const parsed = JSON.parse(stored)
            // Only token and isAuthenticated should be persisted
            expect(parsed.state).toHaveProperty('token')
            expect(parsed.state).toHaveProperty('isAuthenticated')
            expect(parsed.state).not.toHaveProperty('user')
            expect(parsed.state).not.toHaveProperty('isLoading')
        }
    })

    it('exposes login, logout, and fetchMe functions', () => {
        const state = usePortalAuthStore.getState()
        expect(typeof state.login).toBe('function')
        expect(typeof state.logout).toBe('function')
        expect(typeof state.fetchMe).toBe('function')
    })

    it('salva apenas portal_token no login', async () => {
        vi.mocked(api.post).mockResolvedValueOnce({
            data: {
                data: {
                    token: 'portal-123',
                    user: { id: 1, name: 'Portal', email: 'portal@test', customer_id: 1, tenant_id: 1, customer: { id: 1, name: 'Cliente' } },
                },
            },
        } as never)

        localStorage.setItem('auth_token', 'admin-456')

        await usePortalAuthStore.getState().login('portal@test', 'secret', 1)

        expect(localStorage.getItem('portal_token')).toBe('portal-123')
        expect(localStorage.getItem('auth_token')).toBe('admin-456')
        expect(usePortalAuthStore.getState().isAuthenticated).toBe(true)
    })

    it('logout remove apenas a sessao do portal', async () => {
        vi.mocked(api.post).mockResolvedValueOnce({ data: {} } as never)

        localStorage.setItem('portal_token', 'portal-123')
        localStorage.setItem('auth_token', 'admin-456')

        await usePortalAuthStore.getState().logout()

        expect(localStorage.getItem('portal_token')).toBeNull()
        expect(localStorage.getItem('auth_token')).toBe('admin-456')
        expect(usePortalAuthStore.getState().isAuthenticated).toBe(false)
    })

    it('fetchMe com erro limpa apenas a sessao do portal', async () => {
        vi.mocked(api.get).mockRejectedValueOnce(new Error('401'))

        localStorage.setItem('portal_token', 'portal-123')
        localStorage.setItem('auth_token', 'admin-456')

        await expect(usePortalAuthStore.getState().fetchMe()).rejects.toThrow('Portal session expired')

        expect(localStorage.getItem('portal_token')).toBeNull()
        expect(localStorage.getItem('auth_token')).toBe('admin-456')
        expect(usePortalAuthStore.getState().isAuthenticated).toBe(false)
    })
})
