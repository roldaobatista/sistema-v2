import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

import {
    useEmailAccounts,
    useEmailAccount,
    useCreateEmailAccount,
    useUpdateEmailAccount,
    useDeleteEmailAccount,
    useSyncEmailAccount,
    useTestEmailConnection,
} from '@/hooks/useEmailAccounts'
import api from '@/lib/api'
import { toast } from 'sonner'

const mockGet = vi.mocked(api.get)
const mockPost = vi.mocked(api.post)
const mockPut = vi.mocked(api.put)
const mockDelete = vi.mocked(api.delete)

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })
    return ({ children }: { children: React.ReactNode }) =>
        React.createElement(QueryClientProvider, { client: queryClient }, children)
}

describe('useEmailAccounts', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should fetch email accounts', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: [{
                    id: 1,
                    tenant_id: 7,
                    label: 'Work',
                    email_address: 'work@test.com',
                    imap_host: 'imap.test.com',
                    imap_port: 993,
                    imap_encryption: 'ssl',
                    imap_username: 'work-user',
                    smtp_host: 'smtp.test.com',
                    smtp_port: 465,
                    smtp_encryption: 'ssl',
                    is_active: true,
                    sync_status: 'idle',
                    sync_error: null,
                    last_sync_at: '2026-03-20T10:00:00Z',
                }],
            },
        })

        const { result } = renderHook(() => useEmailAccounts(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(mockGet).toHaveBeenCalledWith('/email-accounts')
        expect(result.current.data).toEqual([{
            id: 1,
            tenant_id: 7,
            name: 'Work',
            email: 'work@test.com',
            imap_host: 'imap.test.com',
            imap_port: 993,
            imap_encryption: 'ssl',
            imapUsername: 'work-user',
            smtp_host: 'smtp.test.com',
            smtp_port: 465,
            smtp_encryption: 'ssl',
            is_active: true,
            sync_status: 'idle',
            sync_error: null,
            last_synced_at: '2026-03-20T10:00:00Z',
        }])
    })

    it('should handle fetch error', async () => {
        mockGet.mockRejectedValue(new Error('Network error'))

        const { result } = renderHook(() => useEmailAccounts(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isError).toBe(true)
        })
    })
})

describe('useEmailAccount', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should fetch a single email account', async () => {
        mockGet.mockResolvedValue({
            data: {
                data: {
                    id: 1,
                    tenant_id: 7,
                    label: 'Work',
                    email_address: 'work@test.com',
                    imap_host: 'imap.test.com',
                    imap_port: 993,
                    imap_encryption: 'ssl',
                    imap_username: 'work-user',
                    smtp_host: 'smtp.test.com',
                    smtp_port: 465,
                    smtp_encryption: 'ssl',
                    is_active: true,
                    sync_status: 'idle',
                    sync_error: null,
                    last_sync_at: null,
                },
            },
        })

        const { result } = renderHook(() => useEmailAccount(1), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(mockGet).toHaveBeenCalledWith('/email-accounts/1')
        expect(result.current.data?.name).toBe('Work')
    })

    it('should not fetch when id is null', () => {
        const { result } = renderHook(() => useEmailAccount(null), { wrapper: createWrapper() })
        expect(result.current.isLoading).toBe(false)
        expect(mockGet).not.toHaveBeenCalled()
    })
})

describe('useCreateEmailAccount', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should create an email account', async () => {
        mockPost.mockResolvedValue({ data: { id: 1 } })

        const { result } = renderHook(() => useCreateEmailAccount(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate({
                name: 'New Account',
                email: 'new@test.com',
                imap_host: 'imap.test.com',
                imap_port: 993,
                imap_encryption: 'ssl',
                imapUsername: 'user',
                imap_password: 'pass',
            })
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPost).toHaveBeenCalledWith('/email-accounts', {
            label: 'New Account',
            email_address: 'new@test.com',
            imap_host: 'imap.test.com',
            imap_port: 993,
            imap_encryption: 'ssl',
            imap_username: 'user',
            imap_password: 'pass',
            smtp_host: undefined,
            smtp_port: undefined,
            smtp_encryption: undefined,
            is_active: undefined,
        })
        expect(toast.success).toHaveBeenCalledWith('Conta de email criada com sucesso')
    })
})

describe('useUpdateEmailAccount', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should update an email account', async () => {
        mockPut.mockResolvedValue({ data: { id: 1 } })

        const { result } = renderHook(() => useUpdateEmailAccount(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate({ id: 1, data: { name: 'Updated' } })
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPut).toHaveBeenCalledWith('/email-accounts/1', {
            label: 'Updated',
            email_address: undefined,
            imap_host: undefined,
            imap_port: undefined,
            imap_encryption: undefined,
            imap_username: undefined,
            imap_password: undefined,
            smtp_host: undefined,
            smtp_port: undefined,
            smtp_encryption: undefined,
            is_active: undefined,
        })
        expect(toast.success).toHaveBeenCalledWith('Conta de email atualizada')
    })
})

describe('useDeleteEmailAccount', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should delete an email account', async () => {
        mockDelete.mockResolvedValue({ data: {} })

        const { result } = renderHook(() => useDeleteEmailAccount(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate(1)
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockDelete).toHaveBeenCalledWith('/email-accounts/1')
        expect(toast.success).toHaveBeenCalledWith('Conta de email removida')
    })
})

describe('useSyncEmailAccount', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should trigger sync for an email account', async () => {
        mockPost.mockResolvedValue({ data: {} })

        const { result } = renderHook(() => useSyncEmailAccount(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate(1)
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPost).toHaveBeenCalledWith('/email-accounts/1/sync')
        expect(toast.success).toHaveBeenCalledWith('Sincronização iniciada')
    })
})

describe('useTestEmailConnection', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('should test email connection', async () => {
        mockPost.mockResolvedValue({ data: { message: 'Connection OK' } })

        const { result } = renderHook(() => useTestEmailConnection(), { wrapper: createWrapper() })

        await act(async () => {
            result.current.mutate(1)
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPost).toHaveBeenCalledWith('/email-accounts/1/test-connection')
        expect(toast.success).toHaveBeenCalledWith('Connection OK')
    })
})
