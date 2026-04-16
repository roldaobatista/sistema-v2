/**
 * Safely extract an array from an API response.
 *
 * Handles all common Laravel response shapes:
 *   - Direct array: [...]
 *   - Wrapped: { data: [...] }
 *   - Paginated: { data: [...], current_page: 1, ... }
 *   - Double-wrapped (Axios): { data: { data: [...] } }
 *
 * Returns [] when the response is null, undefined, or not an array.
 */
export function safeArray<T = unknown>(response: T[] | { data: T[] | { data: T[] } } | null | undefined): T[] {
    if (Array.isArray(response)) return response

    if (response && typeof response === 'object') {
        const inner = (response as Record<string, unknown>).data
        if (Array.isArray(inner)) return inner
    }

    return []
}

/**
 * Safely extract paginated data from an API response.
 *
 * Returns { items, currentPage, lastPage, total }
 */
interface PaginatedShape<T> {
    data: T[]
    current_page?: number
    last_page?: number
    total?: number
}

type PaginatedResponse<T> =
    | PaginatedShape<T>
    | { data: PaginatedShape<T> | null }
    | null
    | undefined

export function safePaginated<T = unknown>(response: PaginatedResponse<T>): {
    items: T[]
    currentPage: number
    lastPage: number
    total: number
} {
    const fallback = { items: [] as T[], currentPage: 1, lastPage: 1, total: 0 }

    if (!response || typeof response !== 'object') return fallback

    const res = response as Record<string, unknown>

    // Shape: { data: [...], current_page, last_page, total }
    if (Array.isArray(res.data)) {
        return {
            items: res.data as T[],
            currentPage: (res.current_page as number) ?? 1,
            lastPage: (res.last_page as number) ?? 1,
            total: (res.total as number) ?? 0,
        }
    }

    // Shape: { data: { data: [...], current_page, ... } } (double-wrapped)
    if (res.data && typeof res.data === 'object') {
        const inner = res.data as Record<string, unknown>
        if (Array.isArray(inner.data)) {
            return {
                items: inner.data as T[],
                currentPage: (inner.current_page as number) ?? 1,
                lastPage: (inner.last_page as number) ?? 1,
                total: (inner.total as number) ?? 0,
            }
        }
    }

    return fallback
}
