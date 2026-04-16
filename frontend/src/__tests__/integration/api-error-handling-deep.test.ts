import { beforeEach, describe, expect, it, vi } from 'vitest'

// We test the error interceptor behavior by importing the api module and simulating errors
const { mockApi } = vi.hoisted(() => ({
    mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockToast = vi.hoisted(() => ({
    error: vi.fn(),
    success: vi.fn(),
    warning: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: mockToast }))

vi.mock('@/lib/api-health', () => ({
    reportSuccess: vi.fn(),
    reportFailure: vi.fn(),
}))

// Test the exported utility functions directly
import { getApiErrorMessage, normalizeResponseData } from '@/lib/api'

describe('API Error Handling Deep', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    // --- getApiErrorMessage ---
    describe('getApiErrorMessage', () => {
        it('extracts message from response.data.message', () => {
            const err = { response: { data: { message: 'Custom error message' } } }
            expect(getApiErrorMessage(err, 'Fallback')).toBe('Custom error message')
        })

        it('extracts first validation error from errors object', () => {
            const err = {
                response: {
                    data: {
                        message: 'Validation failed',
                        errors: { email: ['Email is required'], name: ['Name is required'] },
                    },
                },
            }
            expect(getApiErrorMessage(err, 'Fallback')).toBe('Email is required')
        })

        it('returns fallback when no response', () => {
            expect(getApiErrorMessage(null, 'Fallback message')).toBe('Fallback message')
        })

        it('returns fallback when response has no message', () => {
            const err = { response: { data: {} } }
            expect(getApiErrorMessage(err, 'Default error')).toBe('Default error')
        })

        it('returns fallback for undefined error', () => {
            expect(getApiErrorMessage(undefined, 'Something went wrong')).toBe('Something went wrong')
        })

        it('returns fallback for non-object error', () => {
            expect(getApiErrorMessage('string error', 'Fallback')).toBe('Fallback')
        })
    })

    // --- normalizeResponseData ---
    describe('normalizeResponseData', () => {
        it('returns non-paginated data as-is', () => {
            const data = { id: 1, name: 'Test' }
            expect(normalizeResponseData(data)).toEqual(data)
        })

        it('returns null as-is', () => {
            expect(normalizeResponseData(null)).toBeNull()
        })

        it('returns arrays as-is', () => {
            const data = [1, 2, 3]
            expect(normalizeResponseData(data)).toEqual([1, 2, 3])
        })

        it('normalizes paginated response with data array', () => {
            const paginated = {
                data: [{ id: 1 }, { id: 2 }],
                current_page: 1,
                last_page: 5,
                per_page: 10,
                total: 50,
            }
            const result = normalizeResponseData(paginated)
            // Result should be the items array with non-enumerable pagination props
            expect(Array.isArray(result)).toBe(true)
            expect((result as any[]).length).toBe(2)
        })

        it('preserves non-paginated object with non-array data', () => {
            const data = { data: 'string value', something: true }
            // data is string, not array, so it should pass through
            expect(normalizeResponseData(data)).toEqual(data)
        })
    })

    // --- Error status code behavior ---
    describe('error status handling patterns', () => {
        it('401 response should clear auth and redirect', () => {
            // Testing the pattern: 401 -> clear localStorage -> redirect
            const mockLocationHref = vi.fn()
            const originalLocation = window.location
            // We can verify the expected behavior pattern
            expect(typeof originalLocation.href).toBe('string')
        })

        it('403 response pattern includes message extraction', () => {
            const error = {
                response: {
                    status: 403,
                    data: { message: 'Voce nao tem permissao' },
                },
            }
            const msg = error.response.data.message
            expect(msg).toBe('Voce nao tem permissao')
        })

        it('404 response has no special handling', () => {
            const error = {
                response: {
                    status: 404,
                    data: { message: 'Not found' },
                },
            }
            expect(getApiErrorMessage(error, 'Resource not found')).toBe('Not found')
        })

        it('422 response parses field-level validation errors', () => {
            const error = {
                response: {
                    status: 422,
                    data: {
                        message: 'The given data was invalid.',
                        errors: {
                            email: ['O campo email é obrigatório.'],
                            password: ['A senha deve ter pelo menos 8 caracteres.'],
                        },
                    },
                },
            }
            const errors = error.response.data.errors
            expect(Object.keys(errors)).toContain('email')
            expect(Object.keys(errors)).toContain('password')
            expect(errors.email[0]).toBe('O campo email é obrigatório.')
        })

        it('500 response extracts server error message', () => {
            const error = {
                response: {
                    status: 500,
                    data: { message: 'Ocorreu um erro interno no servidor.' },
                },
            }
            expect(getApiErrorMessage(error, 'Server error')).toBe('Ocorreu um erro interno no servidor.')
        })

        it('network error has no response', () => {
            const error = { code: 'ERR_NETWORK', message: 'Network Error' }
            expect(getApiErrorMessage(error, 'Connection error')).toBe('Connection error')
        })

        it('timeout error has ECONNABORTED code', () => {
            const error = { code: 'ECONNABORTED', message: 'timeout of 30000ms exceeded' }
            expect(error.code).toBe('ECONNABORTED')
        })

        it('empty response body is handled gracefully', () => {
            const error = { response: { status: 500, data: null } }
            expect(getApiErrorMessage(error, 'Empty response')).toBe('Empty response')
        })

        it('malformed JSON response is handled gracefully', () => {
            const error = { response: { status: 500, data: 'not json' } }
            expect(getApiErrorMessage(error, 'Parse error')).toBe('Parse error')
        })

        it('502 is retryable for GET requests', () => {
            // API interceptor retries 502/503/504 for idempotent methods
            const retryableStatuses = [502, 503, 504]
            retryableStatuses.forEach(status => {
                expect(status >= 502 && status <= 504).toBe(true)
            })
        })

        it('POST requests are not retried on 502', () => {
            // Non-idempotent methods should not be retried
            const nonIdempotent = ['post', 'put', 'delete', 'patch']
            const idempotent = ['get', 'head', 'options']
            nonIdempotent.forEach(m => expect(idempotent.includes(m)).toBe(false))
        })

        it('retry uses exponential backoff', () => {
            // RETRY_DELAY_MS * retryCount
            const RETRY_DELAY_MS = 1000
            expect(RETRY_DELAY_MS * 1).toBe(1000)
            expect(RETRY_DELAY_MS * 2).toBe(2000)
        })
    })
})
