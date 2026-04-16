import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { toast } from 'sonner'
import { AxiosError } from 'axios'

export const EMAIL_RULE_QUERY_KEYS = {
    all: ['email-rules'] as const,
    detail: (id: number | null) => ['email-rules', 'detail', id] as const,
}

function handleMutationError(error: unknown) {
    const err = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    if (err.response?.status === 403) {
        toast.error('Sem permissão para esta ação')
    } else if (err.response?.status === 422) {
        const msgs = err?.response?.data?.errors
        if (msgs) {
            Object.values(msgs).flat().forEach(m => toast.error(m))
        } else {
            toast.error(err?.response?.data?.message || 'Dados inválidos')
        }
    } else {
        toast.error(err?.response?.data?.message || 'Ocorreu um erro')
    }
}

// ── Types ──────────────────────────────────

export interface RuleCondition {
    field: 'from' | 'to' | 'subject' | 'body' | 'ai_category' | 'ai_priority' | 'ai_sentiment'
    operator: 'contains' | 'equals' | 'starts_with' | 'ends_with' | 'regex'
    value: string
}

export interface RuleAction {
    type: 'create_task' | 'create_chamado' | 'notify' | 'star' | 'archive' | 'mark_read' | 'assign_category'
    params?: Record<string, unknown>
}

export interface EmailRule {
    id: number
    tenant_id: number
    name: string
    description: string | null
    conditions: RuleCondition[]
    actions: RuleAction[]
    priority: number
    is_active: boolean
    created_at: string
    updated_at: string
}

export interface EmailRuleFormData {
    name: string
    description?: string
    conditions: RuleCondition[]
    actions: RuleAction[]
    priority?: number
    is_active?: boolean
}

interface PaginationMeta {
    current_page?: number
    per_page?: number
    total?: number
    last_page?: number
    from?: number | null
    to?: number | null
}

export interface EmailRulesResponse {
    data: EmailRule[]
    meta?: PaginationMeta
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object'
}

function getNumberMeta(source: Record<string, unknown>, key: keyof PaginationMeta): number | null | undefined {
    const value = source[key]
    return typeof value === 'number' || value === null ? value : undefined
}

function extractMeta(source: Record<string, unknown>): PaginationMeta {
    return {
        current_page: getNumberMeta(source, 'current_page') ?? undefined,
        per_page: getNumberMeta(source, 'per_page') ?? undefined,
        total: getNumberMeta(source, 'total') ?? undefined,
        last_page: getNumberMeta(source, 'last_page') ?? undefined,
        from: getNumberMeta(source, 'from'),
        to: getNumberMeta(source, 'to'),
    }
}

function normalizeEmailRulesResponse(response: { data?: unknown }): EmailRulesResponse {
    const payload = response.data

    if (Array.isArray(payload)) {
        return { data: payload as EmailRule[] }
    }

    if (!isRecord(payload)) {
        return { data: [] }
    }

    if (Array.isArray(payload.data)) {
        return {
            data: payload.data as EmailRule[],
            meta: extractMeta(payload),
        }
    }

    if (isRecord(payload.data) && Array.isArray(payload.data.data)) {
        return {
            data: payload.data.data as EmailRule[],
            meta: extractMeta(payload.data),
        }
    }

    return { data: [] }
}

// ── Queries ──────────────────────────────────

export function useEmailRules() {
    return useQuery<EmailRulesResponse>({
        queryKey: EMAIL_RULE_QUERY_KEYS.all,
        queryFn: () => api.get('/email-rules').then(normalizeEmailRulesResponse),
    })
}

export function useEmailRule(id: number | null) {
    return useQuery<{ data: EmailRule }>({
        queryKey: EMAIL_RULE_QUERY_KEYS.detail(id),
        queryFn: () => api.get(`/email-rules/${id}`).then(r => r.data),
        enabled: !!id,
    })
}

// ── Mutations ──────────────────────────────────

export function useCreateEmailRule() {
    const qc = useQueryClient()
    return useMutation({
        mutationFn: (data: EmailRuleFormData) =>
            api.post('/email-rules', data).then(r => r.data),
        onSuccess: () => {
            toast.success('Regra de email criada')
            qc.invalidateQueries({ queryKey: EMAIL_RULE_QUERY_KEYS.all })
        },
        onError: handleMutationError,
    })
}

export function useUpdateEmailRule() {
    const qc = useQueryClient()
    return useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<EmailRuleFormData> }) =>
            api.put(`/email-rules/${id}`, data).then(r => r.data),
        onSuccess: () => {
            toast.success('Regra atualizada')
            qc.invalidateQueries({ queryKey: EMAIL_RULE_QUERY_KEYS.all })
        },
        onError: handleMutationError,
    })
}

export function useDeleteEmailRule() {
    const qc = useQueryClient()
    return useMutation({
        mutationFn: (id: number) =>
            api.delete(`/email-rules/${id}`).then(r => r.data),
        onSuccess: () => {
            toast.success('Regra removida')
            qc.invalidateQueries({ queryKey: EMAIL_RULE_QUERY_KEYS.all })
        },
        onError: handleMutationError,
    })
}

export function useToggleEmailRuleActive() {
    const qc = useQueryClient()
    return useMutation({
        mutationFn: (id: number) =>
            api.post(`/email-rules/${id}/toggle-active`).then(r => r.data),
        onSuccess: (data) => {
            toast.success(data.message || 'Status atualizado')
            qc.invalidateQueries({ queryKey: EMAIL_RULE_QUERY_KEYS.all })
        },
        onError: handleMutationError,
    })
}
