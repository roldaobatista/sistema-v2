import { QueryClientProvider } from '@tanstack/react-query'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { ReactNode } from 'react'

import { act, renderHook, waitFor, createTestQueryClient } from '@/__tests__/test-utils'
import {
    EMAIL_QUERY_KEYS,
    useArchiveEmail,
    useEmails,
    type EmailItem,
} from '@/hooks/useEmails'
import {
    EMAIL_RULE_QUERY_KEYS,
    useEmailRules,
    type EmailRule,
} from '@/hooks/useEmailRules'

const { mockToast, mockGet, mockPost } = vi.hoisted(() => ({
    mockToast: { error: vi.fn(), success: vi.fn() },
    mockGet: vi.fn(),
    mockPost: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: mockToast }))

vi.mock('@/lib/api', () => ({
    default: {
        get: (...args: unknown[]) => mockGet(...args),
        post: (...args: unknown[]) => mockPost(...args),
    },
}))

function createWrapper(queryClient = createTestQueryClient()) {
    return ({ children }: { children: ReactNode }) => (
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    )
}

const baseEmail: EmailItem = {
    id: 17,
    email_account_id: 3,
    message_id: 'mail-17',
    folder: 'inbox',
    from_email: 'cliente@example.com',
    from_name: 'Cliente',
    to_email: 'suporte@example.com',
    subject: 'Solicitação',
    snippet: 'Preciso de suporte',
    body_text: null,
    body_html: null,
    date: '2026-04-15T08:00:00Z',
    is_read: false,
    is_starred: false,
    is_archived: false,
    has_attachments: false,
    direction: 'inbound',
    status: 'received',
    ai_category: null,
    ai_priority: null,
    ai_sentiment: null,
    ai_summary: null,
    ai_suggested_action: null,
    ai_confidence: null,
    customer_id: null,
    linked_type: null,
    linked_id: null,
}

const baseRule: EmailRule = {
    id: 8,
    tenant_id: 2,
    name: 'Criar tarefa',
    description: null,
    conditions: [{ field: 'subject', operator: 'contains', value: 'urgente' }],
    actions: [{ type: 'create_task' }],
    priority: 10,
    is_active: true,
    created_at: '2026-04-15T08:00:00Z',
    updated_at: '2026-04-15T08:00:00Z',
}

describe('hooks de server state de e-mail', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('useEmails usa query key descritiva e preserva payload paginado do Laravel', async () => {
        mockGet.mockResolvedValueOnce({
            data: {
                data: [baseEmail],
                meta: { current_page: 1, per_page: 25, total: 1, last_page: 1 },
                current_page: 1,
                per_page: 25,
                total: 1,
                last_page: 1,
            },
        })

        const filters = { folder: 'inbox', per_page: 25 }
        const { result } = renderHook(() => useEmails(filters), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockGet).toHaveBeenCalledWith('/emails', { params: filters })
        expect(result.current.data?.data).toEqual([baseEmail])
        expect(result.current.dataUpdatedAt).toBeGreaterThan(0)
        expect(EMAIL_QUERY_KEYS.list(filters)).toEqual(['emails', 'list', filters])
    })

    it('useArchiveEmail invalida todas as queries de e-mail depois da mutation', async () => {
        mockPost.mockResolvedValueOnce({ data: { message: 'Email arquivado' } })
        const queryClient = createTestQueryClient()
        const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries')
        const { result } = renderHook(() => useArchiveEmail(), { wrapper: createWrapper(queryClient) })

        await act(async () => {
            result.current.mutate(17)
        })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockPost).toHaveBeenCalledWith('/emails/17/archive')
        expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: EMAIL_QUERY_KEYS.all })
        expect(mockToast.success).toHaveBeenCalledWith('Email arquivado')
    })

    it('useEmailRules normaliza envelope paginado duplo mantendo contrato da tela', async () => {
        mockGet.mockResolvedValueOnce({
            data: {
                data: {
                    data: [baseRule],
                    current_page: 1,
                    per_page: 25,
                    total: 1,
                    last_page: 1,
                },
            },
        })

        const { result } = renderHook(() => useEmailRules(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(mockGet).toHaveBeenCalledWith('/email-rules')
        expect(result.current.data?.data).toEqual([baseRule])
        expect(result.current.data?.meta?.total).toBe(1)
        expect(EMAIL_RULE_QUERY_KEYS.all).toEqual(['email-rules'])
    })
})
