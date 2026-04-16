import api from '@/lib/api'
import {
    getDb,
    getAllMutations,
    dequeueMutation,
    enqueueMutation,
    markOfflineFixedAssetInventorySync,
    updateMutation,
    type OfflineWorkOrder,
    type OfflineEquipment,
    type OfflineChecklist,
    type OfflineStandardWeight,
    type OfflineChecklistResponse,
    type OfflineExpense,
    type OfflineSignature,
    type OfflinePhoto,
} from '@/lib/offlineDb'

/* ─── Types ──────────────────────────────────────────────── */

interface SyncPullResponse {
    work_orders: OfflineWorkOrder[]
    equipment: OfflineEquipment[]
    checklists: OfflineChecklist[]
    standard_weights: OfflineStandardWeight[]
    updated_at: string
}

interface SyncBatchItem {
    type: 'checklist_response' | 'expense' | 'signature' | 'status_change' | 'displacement_start' | 'displacement_arrive' | 'displacement_location' | 'displacement_stop'
    data: Record<string, unknown>
}

interface SyncBatchResponse {
    processed: number
    conflicts: Array<{ type: string; id: string; server_updated_at: string }>
    errors: Array<{ type: string; id: string; message: string }>
}

function normalizeOfflineMutationUrl(url: string): string {
    if (!url) {
        return '/'
    }

    if (url.startsWith('/api/v1/')) {
        return url.slice('/api/v1'.length)
    }

    if (url.startsWith('/api/')) {
        return `/v1${url.slice('/api'.length)}`
    }

    return url.startsWith('/') ? url : `/${url}`
}

function extractFixedAssetOfflineReference(body: unknown): string | null {
    if (!body || typeof body !== 'object') {
        return null
    }

    const candidate = (body as { offline_reference?: unknown }).offline_reference
    return typeof candidate === 'string' && candidate.trim() ? candidate : null
}

export interface SyncResult {
    pullCount: number
    pushCount: number
    errors: string[]
    timestamp: string
}

/* ─── Sync Engine ────────────────────────────────────────── */

/**
 * Sync API compatível com a estrutura offline atual (IndexedDB).
 * Os mesmos endpoints e estratégia de conflito podem ser usados por um app
 * mobile com WatermelonDB (GET/POST /api/v1/tech/sync e /tech/sync/batch).
 */
class SyncEngine {
    private isSyncing = false
    private listeners: Array<(result: SyncResult) => void> = []

    onSyncComplete(listener: (result: SyncResult) => void) {
        this.listeners.push(listener)
        return () => {
            this.listeners = (this.listeners || []).filter((l) => l !== listener)
        }
    }

    private emit(result: SyncResult) {
        (this.listeners || []).forEach((l) => l(result))
    }

    private buildFailedMutationKeySet(data: SyncBatchResponse): Set<string> {
        const failedKeys = new Set<string>()

        for (const conflict of data.conflicts || []) {
            failedKeys.add(`${conflict.type}:${conflict.id}`)
        }

        for (const error of data.errors || []) {
            failedKeys.add(`${error.type}:${error.id}`)
        }

        return failedKeys
    }

    async fullSync(): Promise<SyncResult> {
        if (this.isSyncing) {
            return { pullCount: 0, pushCount: 0, errors: ['Sync already in progress'], timestamp: new Date().toISOString() }
        }

        const useAuthCookie = (import.meta.env.VITE_SANCTUM_USE_COOKIE ?? '') === 'true'
        const token = useAuthCookie ? 'cookie' : (typeof localStorage !== 'undefined' ? localStorage.getItem('auth_token') : null)
        if (!useAuthCookie && !token?.trim()) {
            return {
                pullCount: 0,
                pushCount: 0,
                errors: ['Autenticação necessária. Faça login para sincronizar.'],
                timestamp: new Date().toISOString(),
            }
        }

        this.isSyncing = true
        const errors: string[] = []
        let pullCount = 0
        let pushCount = 0

        try {
            // 1. Push: send unsynced data first
            pushCount = await this.pushUnsyncedData(errors)

            // 2. Replay mutation queue
            const replayResult = await this.replayMutationQueue(errors)
            pushCount += replayResult

            // 3. Pull: fetch updated data from server
            pullCount = await this.pullData(errors)

        } catch (err) {
            errors.push(`Sync failed: ${err instanceof Error ? err.message : String(err)}`)
        } finally {
            this.isSyncing = false
        }

        const result: SyncResult = {
            pullCount,
            pushCount,
            errors,
            timestamp: new Date().toISOString(),
        }

        // Update sync metadata
        await this.updateSyncMeta(result.timestamp)

        this.emit(result)
        return result
    }

    async pullData(errors: string[]): Promise<number> {
        const db = await getDb()
        const meta = await db.get('sync-metadata', 'last-pull')
        const since = meta?.last_pulled_at || '1970-01-01T00:00:00Z'

        try {
            const { data } = await api.get<SyncPullResponse>('/tech/sync', {
                params: { since },
            })

            let count = 0

            // Upsert work orders com resolução de conflito: servidor ganha apenas se mais recente ou igual
            if (data.work_orders?.length) {
                const ids = (data.work_orders || []).map((wo) => wo.id)
                const existingMap = new Map<number, string>()
                for (const id of ids) {
                    const existing = await db.get('work-orders', id)
                    if (existing?.updated_at) existingMap.set(id, existing.updated_at)
                }
                const tx = db.transaction('work-orders', 'readwrite')
                for (const wo of data.work_orders) {
                    const localUpdated = existingMap.get(wo.id)
                    const serverUpdated = wo.updated_at ?? ''
                    if (localUpdated && serverUpdated && new Date(localUpdated).getTime() > new Date(serverUpdated).getTime()) {
                        continue
                    }
                    await tx.store.put(wo)
                    count += 1
                }
                await tx.done
            }

            // Equipment: servidor como fonte de verdade (mesma estratégia por updated_at se quiser)
            if (data.equipment?.length) {
                const tx = db.transaction('equipment', 'readwrite')
                for (const eq of data.equipment) {
                    tx.store.put(eq)
                }
                await tx.done
                count += data.equipment.length
            }

            // Checklists
            if (data.checklists?.length) {
                const tx = db.transaction('checklists', 'readwrite')
                for (const cl of data.checklists) {
                    tx.store.put(cl)
                }
                await tx.done
                count += data.checklists.length
            }

            // Standard weights (pesos padrão) — atualizações do servidor
            if (data.standard_weights?.length) {
                const tx = db.transaction('standard-weights', 'readwrite')
                for (const sw of data.standard_weights) {
                    tx.store.put(sw)
                }
                await tx.done
                count += data.standard_weights.length
            }

            return count
        } catch (err) {
            errors.push(`Pull failed: ${err instanceof Error ? err.message : String(err)}`)
            return 0
        }
    }

    async pushUnsyncedData(errors: string[]): Promise<number> {
        const db = await getDb()
        let pushed = 0

        try {
            const batch: SyncBatchItem[] = []

            // Collect unsynced checklist responses (inclui updated_at da OS local para resolução de conflitos)
            const responses = await db.getAllFromIndex('checklist-responses', 'by-synced', 0) as OfflineChecklistResponse[]
            for (const r of responses) {
                const wo = await db.get('work-orders', r.work_order_id)
                const payload: Record<string, unknown> = { ...r }
                if (wo?.updated_at) payload.client_work_order_updated_at = wo.updated_at
                batch.push({ type: 'checklist_response', data: payload })
            }

            // Collect unsynced expenses
            const expenses = await db.getAllFromIndex('expenses', 'by-synced', 0) as OfflineExpense[]
            for (const e of expenses) {
                batch.push({ type: 'expense', data: { ...e } as Record<string, unknown> })
            }

            // Collect unsynced signatures
            const signatures = await db.getAllFromIndex('signatures', 'by-synced', 0) as OfflineSignature[]
            for (const s of signatures) {
                batch.push({ type: 'signature', data: { ...s } as Record<string, unknown> })
            }

            if (batch.length === 0) return 0

            const { data } = await api.post<SyncBatchResponse>('/tech/sync/batch', { mutations: batch })

            for (const conflict of data.conflicts || []) {
                errors.push(`Conflito: ${conflict.type} #${conflict.id}`)
            }

            const failedKeys = this.buildFailedMutationKeySet(data)

            const getErrorMsg = (type: string, id: string) => {
                const err = data.errors?.find(e => e.type === type && String(e.id) === String(id))
                if (err) return err.message
                const conflict = data.conflicts?.find(c => c.type === type && String(c.id) === String(id))
                if (conflict) return `Conflito de versão (servidor: ${conflict.server_updated_at})`
                return 'Erro desconhecido na sincronização'
            }

            for (const response of responses) {
                if (failedKeys.has(`checklist_response:${response.id}`)) {
                    response.sync_error = getErrorMsg('checklist_response', response.id)
                    await db.put('checklist-responses', response)
                    continue
                }
                response.synced = true
                response.sync_error = null
                await db.put('checklist-responses', response)
            }

            for (const expense of expenses) {
                if (failedKeys.has(`expense:${expense.id}`)) {
                    expense.sync_error = getErrorMsg('expense', String(expense.id))
                    await db.put('expenses', expense)
                    continue
                }
                expense.synced = true
                expense.sync_error = null
                await db.put('expenses', expense)
            }

            for (const signature of signatures) {
                if (failedKeys.has(`signature:${signature.id}`)) {
                    signature.sync_error = getErrorMsg('signature', String(signature.id))
                    await db.put('signatures', signature)
                    continue
                }
                signature.synced = true
                signature.sync_error = null
                await db.put('signatures', signature)
            }

            pushed = data.processed

            // Push photos separately (multipart)
            pushed += await this.pushPhotos(errors)

        } catch (err) {
            errors.push(`Push failed: ${err instanceof Error ? err.message : String(err)}`)
        }

        return pushed
    }

    private async pushPhotos(errors: string[]): Promise<number> {
        const db = await getDb()
        const photos = await db.getAllFromIndex('photos', 'by-synced', 0) as OfflinePhoto[]
        let pushed = 0

        for (const photo of photos) {
            try {
                const formData = new FormData()
                formData.append('file', photo.blob, photo.file_name)
                formData.append('work_order_id', String(photo.work_order_id))
                formData.append('entity_type', photo.entity_type)
                if (photo.entity_id) formData.append('entity_id', photo.entity_id)

                await api.post('/tech/sync/photo', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                })

                photo.synced = true
                photo.sync_error = null
                await db.put('photos', photo)
                pushed++
            } catch (err: unknown) {
                const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || (err as Error)?.message || 'Erro desconhecido no upload'
                errors.push(`Photo upload failed: ${photo.file_name} - ${msg}`)
                photo.sync_error = msg
                await db.put('photos', photo)
            }
        }

        return pushed
    }

    private async replayMutationQueue(errors: string[]): Promise<number> {
        const mutations = await getAllMutations()
        let replayed = 0

        for (const mutation of mutations) {
            if (mutation.retries >= 5) {
                errors.push(`Mutation ${mutation.id}: max retries exceeded`)
                continue
            }

            try {
                await api.request({
                    method: mutation.method,
                    url: normalizeOfflineMutationUrl(mutation.url),
                    data: mutation.body,
                    headers: mutation.headers,
                })
                const offlineReference = extractFixedAssetOfflineReference(mutation.body)
                if (offlineReference) {
                    await markOfflineFixedAssetInventorySync(offlineReference, {
                        synced: true,
                        sync_error: null,
                    })
                }
                await dequeueMutation(mutation.id)
                replayed++
            } catch (err) {
                const error = err as {
                    response?: { status?: number; data?: { message?: string } }
                    message?: string
                }
                const status = error.response?.status
                const message = error.response?.data?.message || error.message || 'Erro ao sincronizar mutação offline'
                const nextRetries = mutation.retries + 1
                const isPermanentFailure = typeof status === 'number' && status >= 400 && status < 500 && status !== 408 && status !== 409 && status !== 429
                const offlineReference = extractFixedAssetOfflineReference(mutation.body)

                if (offlineReference) {
                    await markOfflineFixedAssetInventorySync(offlineReference, {
                        synced: false,
                        sync_error: message,
                    })
                }

                if (isPermanentFailure) {
                    errors.push(`Mutation ${mutation.id}: descartada por erro permanente (${status})`)
                    await dequeueMutation(mutation.id)
                    continue
                }

                await updateMutation(mutation.id, {
                    retries: nextRetries,
                    last_error: message,
                })
            }
        }

        return replayed
    }

    private async updateSyncMeta(timestamp: string) {
        const db = await getDb()
        await db.put('sync-metadata', {
            store: 'last-pull',
            last_synced_at: timestamp,
            last_pulled_at: timestamp,
            version: 1,
        })
    }

    getIsSyncing() {
        return this.isSyncing
    }
}

/* ─── Singleton Export ────────────────────────────────────── */

export const syncEngine = new SyncEngine()

/* ─── Offline-aware API wrapper ──────────────────────────── */

export async function offlinePost(url: string, body: unknown): Promise<boolean> {
    if (navigator.onLine) {
        try {
            await api.post(url, body)
            return false
        } catch (err) {
            if (err instanceof Error && !err.message?.includes('Network Error')) throw err
            // Fall through to offline queue
        }
    }

    // Queue for later
    await enqueueMutation('POST', normalizeOfflineMutationUrl(url), body)

    // Request Background Sync
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
        const reg = await navigator.serviceWorker.ready
        await (reg.sync as { register: (tag: string) => Promise<void> }).register('sync-mutations')
    }

    return true
}

export async function offlinePut(url: string, body: unknown): Promise<boolean> {
    if (navigator.onLine) {
        try {
            await api.put(url, body)
            return false
        } catch (err) {
            if (err instanceof Error && !err.message?.includes('Network Error')) throw err
        }
    }

    await enqueueMutation('PUT', normalizeOfflineMutationUrl(url), body)

    if ('serviceWorker' in navigator && 'SyncManager' in window) {
        const reg = await navigator.serviceWorker.ready
        await (reg.sync as { register: (tag: string) => Promise<void> }).register('sync-mutations')
    }

    return true
}
