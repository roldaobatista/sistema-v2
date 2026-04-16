import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
    apiGet: vi.fn(),
    apiPost: vi.fn(),
    clearAuthenticatedQueryCache: vi.fn(),
    setSentryUser: vi.fn(),
    subscribeAuthenticatedCacheClear: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mocks.apiGet,
        post: mocks.apiPost,
        interceptors: {
            request: { use: vi.fn() },
            response: { use: vi.fn() },
        },
    },
    unwrapData: <T>(response: { data?: { data?: T } | T } | null | undefined): T => {
        const data = response?.data

        if (data != null && typeof data === 'object' && 'data' in data) {
            return (data as { data: T }).data
        }

        return data as T
    },
    useAuthCookie: false,
}))

vi.mock('@/lib/query-client', () => ({
    clearAuthenticatedQueryCache: mocks.clearAuthenticatedQueryCache,
}))

vi.mock('@/lib/cross-tab-sync', () => ({
    subscribeAuthenticatedCacheClear: mocks.subscribeAuthenticatedCacheClear,
}))

vi.mock('@/lib/sentry', () => ({
    setSentryUser: mocks.setSentryUser,
}))

import { useAuthStore } from '@/stores/auth-store'
import { usePortalAuthStore } from '@/stores/portal-auth-store'

function resetAdminStore() {
    useAuthStore.setState({
        user: null,
        tenant: null,
        token: null,
        isAuthenticated: false,
        isLoading: false,
    })
}

function resetPortalStore() {
    usePortalAuthStore.setState({
        user: null,
        token: null,
        isAuthenticated: false,
        isLoading: false,
    })
}

describe('auth cache cross-tab boundaries', () => {
    beforeEach(() => {
        localStorage.clear()
        vi.clearAllMocks()
        resetAdminStore()
        resetPortalStore()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('nao emite broadcast cross-tab quando o login admin falha antes de confirmar sessao', async () => {
        mocks.apiPost.mockRejectedValueOnce({ response: { status: 401, data: { message: 'Credenciais invalidas.' } } })

        await expect(useAuthStore.getState().login('admin@example.com', 'wrong-password')).rejects.toMatchObject({
            response: { status: 401 },
        })

        expect(mocks.clearAuthenticatedQueryCache).toHaveBeenCalled()
        expect(mocks.clearAuthenticatedQueryCache).not.toHaveBeenCalledWith(expect.objectContaining({ broadcast: true }))
    })

    it('nao emite broadcast cross-tab quando fetchMe admin esgota tentativas locais', async () => {
        vi.useFakeTimers()
        mocks.apiGet.mockRejectedValue(new Error('backend indisponivel'))

        const fetchMePromise = useAuthStore.getState().fetchMe()
        const fetchMeRejection = expect(fetchMePromise).rejects.toThrow('backend indisponivel')
        await vi.advanceTimersByTimeAsync(1600)

        await fetchMeRejection
        expect(mocks.apiGet).toHaveBeenCalledTimes(3)
        expect(mocks.clearAuthenticatedQueryCache).toHaveBeenCalled()
        expect(mocks.clearAuthenticatedQueryCache).not.toHaveBeenCalledWith(expect.objectContaining({ broadcast: true }))
    })

    it('nao emite broadcast cross-tab quando o login do portal falha antes de confirmar sessao', async () => {
        mocks.apiPost.mockRejectedValueOnce({ response: { status: 401, data: { message: 'Credenciais invalidas.' } } })

        await expect(usePortalAuthStore.getState().login('portal@example.com', 'wrong-password', 10)).rejects.toMatchObject({
            response: { status: 401 },
        })

        expect(mocks.clearAuthenticatedQueryCache).toHaveBeenCalled()
        expect(mocks.clearAuthenticatedQueryCache).not.toHaveBeenCalledWith(expect.objectContaining({ broadcast: true }))
    })

    it('nao emite broadcast cross-tab quando fetchMe do portal falha localmente', async () => {
        mocks.apiGet.mockRejectedValueOnce(new Error('portal indisponivel'))

        await expect(usePortalAuthStore.getState().fetchMe()).rejects.toThrow('Portal session expired')

        expect(mocks.clearAuthenticatedQueryCache).toHaveBeenCalled()
        expect(mocks.clearAuthenticatedQueryCache).not.toHaveBeenCalledWith(expect.objectContaining({ broadcast: true }))
    })
})
