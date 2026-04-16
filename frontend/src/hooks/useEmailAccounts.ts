import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { toast } from 'sonner'
import { AxiosError } from 'axios'

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

export interface EmailAccount {
    id: number
    tenant_id: number
    name: string
    email: string
    imap_host: string
    imap_port: number
    imap_encryption: string
    imapUsername: string
    smtp_host: string | null
    smtp_port: number | null
    smtp_encryption: string | null
    is_active: boolean
    sync_status: 'idle' | 'syncing' | 'error'
    sync_error: string | null
    last_synced_at: string | null
}

export interface EmailAccountFormData {
    name: string
    email: string
    imap_host: string
    imap_port: number
    imap_encryption: string
    imapUsername: string
    imap_password: string
    smtp_host?: string
    smtp_port?: number
    smtp_encryption?: string
    is_active?: boolean
}

interface EmailAccountApiRecord {
    id: number
    tenant_id: number
    label: string
    email_address: string
    imap_host: string
    imap_port: number
    imap_encryption: string
    imap_username: string
    smtp_host: string | null
    smtp_port: number | null
    smtp_encryption: string | null
    is_active: boolean
    sync_status: 'idle' | 'syncing' | 'error'
    sync_error: string | null
    last_sync_at: string | null
}

function normalizeEmailAccount(account: EmailAccountApiRecord): EmailAccount {
    return {
        id: account.id,
        tenant_id: account.tenant_id,
        name: account.label,
        email: account.email_address,
        imap_host: account.imap_host,
        imap_port: account.imap_port,
        imap_encryption: account.imap_encryption,
        imapUsername: account.imap_username,
        smtp_host: account.smtp_host,
        smtp_port: account.smtp_port,
        smtp_encryption: account.smtp_encryption,
        is_active: account.is_active,
        sync_status: account.sync_status,
        sync_error: account.sync_error,
        last_synced_at: account.last_sync_at,
    }
}

function toApiPayload(data: Partial<EmailAccountFormData>) {
    return {
        label: data.name,
        email_address: data.email,
        imap_host: data.imap_host,
        imap_port: data.imap_port,
        imap_encryption: data.imap_encryption,
        imap_username: data.imapUsername,
        imap_password: data.imap_password,
        smtp_host: data.smtp_host,
        smtp_port: data.smtp_port,
        smtp_encryption: data.smtp_encryption,
        is_active: data.is_active,
    }
}

// ── Queries ──────────────────────────────────

export function useEmailAccounts() {
    return useQuery<EmailAccount[]>({
        queryKey: ['email-accounts'],
        queryFn: async () => {
            const response = await api.get<{ data: EmailAccountApiRecord[] }>('/email-accounts')
            return (response.data.data ?? []).map(normalizeEmailAccount)
        },
    })
}

export function useEmailAccount(id: number | null) {
    return useQuery<EmailAccount>({
        queryKey: ['email-accounts', id],
        queryFn: async () => {
            const response = await api.get<{ data: EmailAccountApiRecord }>(`/email-accounts/${id}`)
            return normalizeEmailAccount(response.data.data)
        },
        enabled: !!id,
    })
}

// ── Mutations ──────────────────────────────────

export function useCreateEmailAccount() {
    const qc = useQueryClient()
    return useMutation({
        mutationFn: (data: EmailAccountFormData) =>
            api.post('/email-accounts', toApiPayload(data)).then(r => r.data),
        onSuccess: () => {
            toast.success('Conta de email criada com sucesso')
            qc.invalidateQueries({ queryKey: ['email-accounts'] })
        },
        onError: handleMutationError,
    })
}

export function useUpdateEmailAccount() {
    const qc = useQueryClient()
    return useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<EmailAccountFormData> }) =>
            api.put(`/email-accounts/${id}`, toApiPayload(data)).then(r => r.data),
        onSuccess: () => {
            toast.success('Conta de email atualizada')
            qc.invalidateQueries({ queryKey: ['email-accounts'] })
        },
        onError: handleMutationError,
    })
}

export function useDeleteEmailAccount() {
    const qc = useQueryClient()
    return useMutation({
        mutationFn: (id: number) =>
            api.delete(`/email-accounts/${id}`).then(r => r.data),
        onSuccess: () => {
            toast.success('Conta de email removida')
            qc.invalidateQueries({ queryKey: ['email-accounts'] })
        },
        onError: handleMutationError,
    })
}

export function useSyncEmailAccount() {
    const qc = useQueryClient()
    return useMutation({
        mutationFn: (id: number) =>
            api.post(`/email-accounts/${id}/sync`).then(r => r.data),
        onSuccess: () => {
            toast.success('Sincronização iniciada')
            qc.invalidateQueries({ queryKey: ['email-accounts'] })
        },
        onError: handleMutationError,
    })
}

export function useTestEmailConnection() {
    return useMutation({
        mutationFn: (id: number) =>
            api.post(`/email-accounts/${id}/test-connection`).then(r => r.data),
        onSuccess: (data) => {
            toast.success(data.message || 'Conexão bem-sucedida')
        },
        onError: handleMutationError,
    })
}
