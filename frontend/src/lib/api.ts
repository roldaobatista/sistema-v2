import axios from 'axios'
import { toast } from 'sonner'
import { reportSuccess, reportFailure } from '@/lib/api-health'

const _viteApi = (import.meta.env.VITE_API_URL || '').trim()

export const useAuthCookie = (import.meta.env.VITE_SANCTUM_USE_COOKIE ?? '') === 'true'

type PaginationMeta = {
    current_page?: number
    last_page?: number
    per_page?: number
    total?: number
    from?: number | null
    to?: number | null
}

function normalizeRequestPath(url?: string): string {
    if (!url) return ''

    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            return new URL(url).pathname.replace(/^\/api\/v1/, '')
        } catch {
            return url
        }
    }

    return url.replace(/^\/api\/v1/, '')
}

function isPortalRequest(url?: string): boolean {
    return normalizeRequestPath(url).startsWith('/portal')
}

function getAuthToken(url?: string): string | null {
    if (isPortalRequest(url)) {
        return localStorage.getItem('portal_token')
    }

    return localStorage.getItem('auth_token')
}

function clearPortalSession(): void {
    localStorage.removeItem('portal_token')
    localStorage.removeItem('portal-auth-store')
}

function clearAdminSession(): void {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth-store')
}

// URL relativa quando VITE_API_URL vazio — funciona com IP ou domínio (mesma origem)
const api = axios.create({
    baseURL: _viteApi || '/api/v1',
    timeout: 30000, // 30s default timeout
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
    withCredentials: useAuthCookie,
})

// Retry automático para 502/503/504 e erros de rede
const MAX_RETRIES = 2
const RETRY_DELAY_MS = 1000

export function normalizeResponseData<T>(payload: T): T {
    if (
        payload == null ||
        typeof payload !== 'object' ||
        Array.isArray(payload) ||
        !('data' in payload) ||
        !Array.isArray(payload.data)
    ) {
        return payload
    }

    const paginatedPayload = payload as T & { data: unknown[] } & PaginationMeta
    const items = paginatedPayload.data as unknown[]
    const meta: PaginationMeta = {
        current_page: paginatedPayload.current_page,
        last_page: paginatedPayload.last_page,
        per_page: paginatedPayload.per_page,
        total: paginatedPayload.total,
        from: paginatedPayload.from,
        to: paginatedPayload.to,
    }
    const extraDescriptors = Object.fromEntries(
        Object.entries(paginatedPayload as Record<string, unknown>)
            .filter(([key]) => !['data', 'current_page', 'last_page', 'per_page', 'total', 'from', 'to'].includes(key))
            .map(([key, value]) => [key, {
                value,
                enumerable: false,
                configurable: true,
            }])
    )

    Object.defineProperties(items, {
        __pagination: {
            value: meta,
            enumerable: false,
            configurable: true,
        },
        data: {
            value: items,
            enumerable: false,
            configurable: true,
        },
        current_page: {
            value: meta.current_page,
            enumerable: false,
            configurable: true,
        },
        last_page: {
            value: meta.last_page,
            enumerable: false,
            configurable: true,
        },
        per_page: {
            value: meta.per_page,
            enumerable: false,
            configurable: true,
        },
        total: {
            value: meta.total,
            enumerable: false,
            configurable: true,
        },
        from: {
            value: meta.from,
            enumerable: false,
            configurable: true,
        },
        to: {
            value: meta.to,
            enumerable: false,
            configurable: true,
        },
        ...extraDescriptors,
    })

    return items as T
}

api.interceptors.response.use(undefined, async (error) => {
    const config = error.config
    if (!config || config.__retryCount >= MAX_RETRIES) {
        return Promise.reject(error)
    }

    const status = error.response?.status
    const isRetryable =
        (!error.response && (error.code === 'ERR_NETWORK' || error.code === 'ECONNABORTED')) ||
        (status === 502 || status === 503 || status === 504)

    // Never retry non-idempotent methods
    const method = (config.method || 'get').toLowerCase()
    if (!isRetryable || (method !== 'get' && method !== 'head' && method !== 'options')) {
        return Promise.reject(error)
    }

    config.__retryCount = (config.__retryCount || 0) + 1
    const delay = RETRY_DELAY_MS * config.__retryCount

    await new Promise(resolve => setTimeout(resolve, delay))
    return api(config)
})

// Interceptor: injeta token de auth (só quando não usa cookie httpOnly)
api.interceptors.request.use((config) => {
    if (!useAuthCookie) {
        const token = getAuthToken(config.url)
        if (token) {
            config.headers.Authorization = `Bearer ${token}`
        }
    }
    return config
})

// Interceptor: normaliza respostas paginadas e alimenta circuit breaker
api.interceptors.response.use(
    (response) => {
        reportSuccess()
        response.data = normalizeResponseData(response.data)

        return response
    },
    (error) => {
        const status = error.response?.status
        const isNetworkError = !error.response && (error.code === 'ERR_NETWORK' || error.code === 'ECONNABORTED')
        const isBackendDown = status === 502 || status === 503 || status === 504

        if (isNetworkError || isBackendDown) {
            reportFailure()
            toast.error('Erro de conexão ou sistema indisponível.')
        }

        if (status === 401) {
            const portalRequest = isPortalRequest(error.config?.url)

            if (!useAuthCookie) {
                if (portalRequest) {
                    clearPortalSession()
                } else {
                    clearAdminSession()
                }
            } else if (portalRequest) {
                localStorage.removeItem('portal-auth-store')
            } else {
                localStorage.removeItem('auth-store')
            }

            window.location.href = portalRequest ? '/portal/login' : '/login'
        } else if (status === 403) {
            const data = error?.response?.data
            const msg = typeof data === 'object' && data && (data.message ?? data.error)
            const message = msg && typeof msg === 'string' ? msg : 'Você não tem permissão para realizar esta ação.'
            toast.error(message)
            window.dispatchEvent(new CustomEvent('api:forbidden', { detail: { message } }))
        } else if (status === 422) {
            const data = error?.response?.data
            const errors = data?.errors
            if (errors && typeof errors === 'object') {
                const msgs = Object.values(errors).flat().filter((m): m is string => typeof m === 'string')
                toast.error(msgs.slice(0, 3).join('; ') || 'Dados inválidos.')
            } else {
                const msg = typeof data === 'object' && data && (data.message ?? data.error)
                toast.error(msg && typeof msg === 'string' ? msg : 'Dados inválidos fornecidos.')
            }
        } else if (status >= 500 && !isBackendDown) {
            const data = error?.response?.data
            const msg = typeof data === 'object' && data && (data.message ?? data.error)
            toast.error(msg && typeof msg === 'string' ? msg : 'Ocorreu um erro interno no servidor.')
        } else if (status && status !== 401 && status !== 403 && status !== 422 && status < 500) {
            const data = error?.response?.data
            const msg = typeof data === 'object' && data && (data.message ?? data.error)
            toast.error(msg && typeof msg === 'string' ? msg : 'Ocorreu um erro na requisição.')
        }

        return Promise.reject(error)
    }
)

/** Extrai mensagem de erro da API para exibir ao usuário (onError, etc.). */
export function getApiErrorMessage(err: unknown, fallback: string): string {
    const e = err as { response?: { data?: { message?: string; error?: string; errors?: Record<string, string[]> } } }
    const errors = e?.response?.data?.errors
    if (errors && typeof errors === 'object') {
        const first = Object.values(errors).flat().find((m): m is string => typeof m === 'string')
        if (first) return first
    }
    return e?.response?.data?.message ?? e?.response?.data?.error ?? fallback
}

/**
 * Padrão de resposta da API: backend retorna { data: T } ou às vezes T direto.
 * Este helper normaliza para sempre obter o payload útil.
 */
export function unwrapData<T>(r: { data?: { data?: T } | T }): T
export function unwrapData<T>(r: { data?: unknown } | null | undefined): T
export function unwrapData<T>(r: { data?: unknown } | null | undefined): T {
    const d = r?.data
    if (d != null && typeof d === 'object' && 'data' in d) {
        return (d as { data: T }).data
    }
    return d as T
}

/** Origem da API (para URLs absolutas: storage, PDF, etc). Usa mesma origem quando VITE_API_URL vazio. */
export function getApiOrigin(): string {
    if (_viteApi) {
        const m = _viteApi.match(/^(https?:\/\/[^/]+)/)
        return m ? m[1] : (typeof window !== 'undefined' ? window.location.origin : '')
    }
    return typeof window !== 'undefined' ? window.location.origin : ''
}

export function buildStorageUrl(filePath: string | null | undefined): string | null {
    if (!filePath) {
        return null
    }

    if (/^https?:\/\//i.test(filePath)) {
        return filePath
    }

    const normalizedPath = String(filePath).replace(/^\/+/, '')
    const relativePath = normalizedPath.startsWith('storage/')
        ? normalizedPath.slice('storage/'.length)
        : normalizedPath

    return `${getApiOrigin()}/storage/${relativePath}`
}

export default api
