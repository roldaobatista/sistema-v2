/**
 * Global API type definitions for the KALIBRIUM ERP frontend.
 * Centralizes common response shapes and error handling types.
 */

import type { AxiosError } from 'axios'

/** Standard paginated API response from Laravel */
export interface PaginatedResponse<T> {
    data: T[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
}

/** Standard API response wrapper */
export interface ApiResponse<T> {
    data: T
    message?: string
}

/** Standard API list response (non-paginated) */
export interface ApiListResponse<T> {
    data: T[]
}

/** Laravel validation error response */
export interface ApiValidationError {
    message: string
    errors: Record<string, string[]>
}

export interface ApiErrorPayload {
    message?: string
    error?: string
    errors?: Record<string, string[]> | unknown
    dependencies?: Record<string, number> | unknown
}

/** Type guard for Axios errors */
export function isAxiosError(err: unknown): err is AxiosError<ApiErrorPayload> {
    return (
        typeof err === 'object' &&
        err !== null &&
        'isAxiosError' in err &&
        (err as AxiosError).isAxiosError === true
    )
}

/** Extract error message from API response for toast display */
export function extractApiError(err: unknown, fallback: string): string {
    if (isAxiosError(err)) {
        const payload = err.response?.data
        const validationErrors = payload?.errors

        if (validationErrors && typeof validationErrors === 'object' && !Array.isArray(validationErrors)) {
            const firstValidationMessage = Object.values(validationErrors)
                .flat()
                .find((message): message is string => typeof message === 'string')

            if (firstValidationMessage) {
                return firstValidationMessage
            }
        }

        if (payload?.message && typeof payload.message === 'string') {
            return payload.message
        }

        if (payload?.error && typeof payload.error === 'string') {
            return payload.error
        }
    }

    return fallback
}

export interface ApiDeleteConflictPayload {
    message: string | null
    dependencies: Record<string, number> | null
}

export function extractDeleteConflict(err: unknown): ApiDeleteConflictPayload | null {
    if (!isAxiosError(err)) {
        return null
    }

    const status = err.response?.status
    if (status !== 409 && status !== 422) {
        return null
    }

    const payload = err.response?.data
    const dependencies = payload?.dependencies

    return {
        message: payload?.message && typeof payload.message === 'string' ? payload.message : null,
        dependencies:
            dependencies && typeof dependencies === 'object' && !Array.isArray(dependencies)
                ? Object.fromEntries(
                    Object.entries(dependencies).filter((entry): entry is [string, number] => typeof entry[1] === 'number')
                )
                : null,
    }
}
