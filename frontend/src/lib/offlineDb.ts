/**
 * @deprecated Use `./offline/indexedDB.ts` (sync-queue) para novas features offline.
 *
 * Este modulo (mutation-queue) e o sistema offline legado, especializado para Work Orders.
 * O novo sistema em `./offline/indexedDB.ts` e generico e suporta journey-events.
 * Migrar gradualmente os consumidores deste modulo para o novo.
 */
import { openDB, type DBSchema, type IDBPDatabase } from 'idb'

/* ─── Schema ─────────────────────────────────────────────── */

export interface OfflineWorkOrder {
    id: number
    number: string
    os_number?: string | null
    status: string
    assigned_to?: number | null
    checklist_id?: number | null
    priority?: string | null
    scheduled_date?: string | null
    customer_id?: number | null
    customer_name?: string | null
    customer_phone?: string | null
    customer_address?: string | null
    city?: string | null
    description?: string | null
    sla_due_at?: string | null
    google_maps_link?: string | null
    waze_link?: string | null
    equipment_ids?: number[]
    technician_ids?: number[]
    updated_at: string
    displacement_started_at?: string | null
    displacement_arrived_at?: string | null
    displacement_duration_minutes?: number | null
    displacement_status?: 'not_started' | 'in_progress' | 'paused' | 'arrived'
    displacement_stops?: Array<{ id: number | string; type: string; started_at: string; ended_at?: string | null }>
    service_started_at?: string | null
    wait_time_minutes?: number | null
    service_duration_minutes?: number | null
    return_started_at?: string | null
    return_destination?: string | null
    return_arrived_at?: string | null
    return_duration_minutes?: number | null
    total_duration_minutes?: number | null
    completed_at?: string | null
    // Parity fields with main WorkOrder detail page
    service_type_name?: string | null
    service_type?: string | null
    technical_report?: string | null
    internal_notes?: string | null
    is_warranty?: boolean | null
    total_amount?: number | string | null
    displacement_value?: number | string | null
    items?: Array<{
        id: number
        type: 'product' | 'service'
        description: string
        quantity: number | string
        unit_price: number | string
        discount?: number | string
        line_total: number | string
    }>
    equipment_refs?: Array<{
        id: number
        type?: string | null
        brand?: string | null
        model?: string | null
        serial_number?: string | null
        tag?: string | null
    }>
    status_history?: Array<{
        id: number
        from_status?: string | null
        to_status: string
        notes?: string | null
        user_name?: string | null
        created_at: string
    }>
    comments_count?: number | null
    state?: string | null
    contact_phone?: string | null
    allowed_transitions?: string[]
}

export interface OfflineEquipment {
    id: number
    work_order_id?: number | null
    customer_id?: number | null
    type?: string | null
    brand?: string | null
    model?: string | null
    serial_number?: string | null
    capacity?: string | null
    resolution?: string | null
    location?: string | null
    updated_at: string
}

export interface OfflineChecklist {
    id: number
    name: string
    service_type?: string | null
    items: Array<{
        id: number
        label: string
        type: 'boolean' | 'text' | 'number' | 'photo' | 'select'
        required: boolean
        options?: string[]
    }>
    updated_at: string
}

export interface OfflineChecklistResponse {
    id: string // ULID — gerado localmente quando offline
    work_order_id: number
    equipment_id: number | null
    checklist_id: number
    responses: Record<string, string | number | boolean | null>
    completed_at?: string | null
    synced: boolean
    sync_error?: string | null
    updated_at: string
}

export interface OfflineStandardWeight {
    id: number
    code: string
    nominal_value: string
    precision_class?: string | null
    certificate_number?: string | null
    certificate_expiry?: string | null
    updated_at: string
}

export interface OfflineExpense {
    id: string // ULID local
    work_order_id: number
    expense_category_id?: number | null
    description: string
    amount: string
    expense_date: string
    payment_method?: 'cash' | 'corporate_card' | null
    notes?: string | null
    receipt_photo_id?: string | null // ref → photos store
    affects_technician_cash: boolean
    affects_net_value: boolean
    synced: boolean
    sync_error?: string | null
    created_at: string
    updated_at: string
}

export interface OfflinePhoto {
    id: string // ULID local
    work_order_id: number
    entity_type: 'before' | 'after' | 'checklist' | 'expense' | 'general'
    entity_id?: string | null
    blob: Blob
    mime_type: string
    file_name: string
    synced: boolean
    sync_error?: string | null
    created_at: string
    preview?: string
}

export interface OfflineSignature {
    id: string // ULID local
    work_order_id: number
    signer_name: string
    png_base64: string
    captured_at: string
    synced: boolean
    sync_error?: string | null
}

export interface OfflineMutation {
    id: string // ULID local
    method: 'POST' | 'PUT' | 'PATCH' | 'DELETE'
    url: string
    body?: unknown
    headers?: Record<string, string>
    created_at: string
    retries: number
    last_error?: string | null
}

export interface OfflineFixedAssetInventory {
    id: string
    asset_id: number
    inventory_date: string
    counted_location?: string | null
    counted_status?: string | null
    condition_ok: boolean
    notes?: string | null
    synced: boolean
    sync_error?: string | null
    created_at: string
    updated_at: string
}

export interface SyncMeta {
    store: string
    last_synced_at: string
    last_pulled_at: string
    version: number
}

/* ─── DB Schema ──────────────────────────────────────────── */

export interface KalibriumDB extends DBSchema {
    'work-orders': {
        key: number
        value: OfflineWorkOrder
        indexes: { 'by-status': string; 'by-updated': string }
    }
    'equipment': {
        key: number
        value: OfflineEquipment
        indexes: { 'by-work-order': number; 'by-customer': number }
    }
    'checklists': {
        key: number
        value: OfflineChecklist
    }
    'checklist-responses': {
        key: string
        value: OfflineChecklistResponse
        indexes: { 'by-work-order': number; 'by-synced': boolean }
    }
    'standard-weights': {
        key: number
        value: OfflineStandardWeight
    }
    'expenses': {
        key: string
        value: OfflineExpense
        indexes: { 'by-work-order': number; 'by-synced': boolean }
    }
    'photos': {
        key: string
        value: OfflinePhoto
        indexes: { 'by-work-order': number; 'by-synced': boolean; 'by-entity': string }
    }
    'signatures': {
        key: string
        value: OfflineSignature
        indexes: { 'by-work-order': number; 'by-synced': boolean }
    }
    'mutation-queue': {
        key: string
        value: OfflineMutation
        indexes: { 'by-created': string }
    }
    'sync-metadata': {
        key: string
        value: SyncMeta
    }
    'customer-capsules': {
        key: number
        value: {
            id: number
            data: Record<string, unknown>
            updated_at: string
        }
    }
    'fixed-asset-inventories': {
        key: string
        value: OfflineFixedAssetInventory
        indexes: { 'by-asset': number; 'by-synced': boolean }
    }
}

/* ─── Database Singleton ─────────────────────────────────── */

const DB_NAME = 'kalibrium-offline'
const DB_VERSION = 3

let dbInstance: IDBPDatabase<KalibriumDB> | null = null

export async function getDb(): Promise<IDBPDatabase<KalibriumDB>> {
    if (dbInstance) return dbInstance

    dbInstance = await openDB<KalibriumDB>(DB_NAME, DB_VERSION, {
        upgrade(db, oldVersion) {
            if (oldVersion < 1) {
                // Work Orders
                const woStore = db.createObjectStore('work-orders', { keyPath: 'id' })
                woStore.createIndex('by-status', 'status')
                woStore.createIndex('by-updated', 'updated_at')

                // Equipment
                const eqStore = db.createObjectStore('equipment', { keyPath: 'id' })
                eqStore.createIndex('by-work-order', 'work_order_id')
                eqStore.createIndex('by-customer', 'customer_id')

                // Checklists (templates)
                db.createObjectStore('checklists', { keyPath: 'id' })

                // Checklist responses (offline writes)
                const crStore = db.createObjectStore('checklist-responses', { keyPath: 'id' })
                crStore.createIndex('by-work-order', 'work_order_id')
                crStore.createIndex('by-synced', 'synced')

                // Standard weights (read-only cache)
                db.createObjectStore('standard-weights', { keyPath: 'id' })

                // Expenses (offline writes)
                const expStore = db.createObjectStore('expenses', { keyPath: 'id' })
                expStore.createIndex('by-work-order', 'work_order_id')
                expStore.createIndex('by-synced', 'synced')

                // Photos (offline blobs)
                const phStore = db.createObjectStore('photos', { keyPath: 'id' })
                phStore.createIndex('by-work-order', 'work_order_id')
                phStore.createIndex('by-synced', 'synced')
                phStore.createIndex('by-entity', 'entity_id')

                // Signatures
                const sigStore = db.createObjectStore('signatures', { keyPath: 'id' })
                sigStore.createIndex('by-work-order', 'work_order_id')
                sigStore.createIndex('by-synced', 'synced')

                // Mutation queue
                const mqStore = db.createObjectStore('mutation-queue', { keyPath: 'id' })
                mqStore.createIndex('by-created', 'created_at')

                // Sync metadata
                db.createObjectStore('sync-metadata', { keyPath: 'store' })
            }

            if (oldVersion < 2) {
                // Customer Capsules (Added in v2)
                if (!db.objectStoreNames.contains('customer-capsules')) {
                    db.createObjectStore('customer-capsules', { keyPath: 'id' })
                }
            }

            if (oldVersion < 3) {
                if (!db.objectStoreNames.contains('fixed-asset-inventories')) {
                    const inventoryStore = db.createObjectStore('fixed-asset-inventories', { keyPath: 'id' })
                    inventoryStore.createIndex('by-asset', 'asset_id')
                    inventoryStore.createIndex('by-synced', 'synced')
                }
            }
        },
    })

    return dbInstance
}

/* ─── ULID Generator (lightweight, no deps) ──────────────── */

const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'

export function generateUlid(): string {
    const now = Date.now()
    let timeStr = ''
    let t = now
    for (let i = 9; i >= 0; i--) {
        timeStr = ENCODING[t % 32] + timeStr
        t = Math.floor(t / 32)
    }

    let randomStr = ''
    for (let i = 0; i < 16; i++) {
        randomStr += ENCODING[Math.floor(Math.random() * 32)]
    }

    return timeStr + randomStr
}

/* ─── Convenience helpers ────────────────────────────────── */

type StoreNames =
    | 'work-orders'
    | 'equipment'
    | 'checklists'
    | 'checklist-responses'
    | 'standard-weights'
    | 'expenses'
    | 'photos'
    | 'signatures'
    | 'mutation-queue'
    | 'sync-metadata'
    | 'customer-capsules'
    | 'fixed-asset-inventories'

export type OfflineReadableStoreName = Exclude<StoreNames, 'mutation-queue' | 'sync-metadata'>
export type OfflineStoreValue<K extends OfflineReadableStoreName> = KalibriumDB[K]['value']
export type OfflineStoreKey<K extends OfflineReadableStoreName> = KalibriumDB[K]['key']
export type OfflineStoreIndex<K extends OfflineReadableStoreName> =
    keyof KalibriumDB[K]['indexes']

export async function clearStore(storeName: StoreNames): Promise<void> {
    const db = await getDb()
    await db.clear(storeName)
}

export async function getCount(storeName: StoreNames): Promise<number> {
    const db = await getDb()
    return db.count(storeName)
}

export async function getMutationQueueCount(): Promise<number> {
    return getCount('mutation-queue')
}

export async function enqueueMutation(
    method: OfflineMutation['method'],
    url: string,
    body?: unknown,
    headers?: Record<string, string>,
): Promise<string> {
    const db = await getDb()
    const id = generateUlid()
    await db.put('mutation-queue', {
        id,
        method,
        url,
        body,
        headers,
        created_at: new Date().toISOString(),
        retries: 0,
        last_error: null,
    })
    return id
}

export async function dequeueMutation(id: string): Promise<void> {
    const db = await getDb()
    await db.delete('mutation-queue', id)
}

export async function updateMutation(id: string, changes: Partial<OfflineMutation>): Promise<void> {
    const db = await getDb()
    const current = await db.get('mutation-queue', id)
    if (!current) return

    await db.put('mutation-queue', {
        ...current,
        ...changes,
    })
}

export async function getAllMutations(): Promise<OfflineMutation[]> {
    const db = await getDb()
    return db.getAllFromIndex('mutation-queue', 'by-created')
}

export async function getSyncErrorCount(): Promise<number> {
    const db = await getDb()
    let count = 0

    const mutations = await db.getAllFromIndex('mutation-queue', 'by-created')
    count += mutations.filter(m => !!m.last_error).length

    const checklists = await db.getAllFromIndex('checklist-responses', 'by-synced', false)
    count += checklists.filter((c) => !!c.sync_error).length

    const expenses = await db.getAllFromIndex('expenses', 'by-synced', false)
    count += expenses.filter((e) => !!e.sync_error).length

    const signatures = await db.getAllFromIndex('signatures', 'by-synced', false)
    count += signatures.filter((s) => !!s.sync_error).length

    const photos = await db.getAllFromIndex('photos', 'by-synced', false)
    count += photos.filter((p) => !!p.sync_error).length

    const fixedAssetInventories = await db.getAllFromIndex('fixed-asset-inventories', 'by-synced', false)
    count += fixedAssetInventories.filter((item) => !!item.sync_error).length

    return count
}

export async function saveOfflineFixedAssetInventory(record: Omit<OfflineFixedAssetInventory, 'id' | 'created_at' | 'updated_at' | 'synced' | 'sync_error'>): Promise<string> {
    const db = await getDb()
    const id = generateUlid()
    const now = new Date().toISOString()

    await db.put('fixed-asset-inventories', {
        id,
        ...record,
        synced: false,
        sync_error: null,
        created_at: now,
        updated_at: now,
    })

    return id
}

export async function markOfflineFixedAssetInventorySync(
    id: string,
    changes: Pick<OfflineFixedAssetInventory, 'synced' | 'sync_error'>,
): Promise<void> {
    const db = await getDb()
    const current = await db.get('fixed-asset-inventories', id)
    if (!current) return

    await db.put('fixed-asset-inventories', {
        ...current,
        ...changes,
        updated_at: new Date().toISOString(),
    })
}

export async function listOfflineFixedAssetInventories(): Promise<OfflineFixedAssetInventory[]> {
    const db = await getDb()
    return db.getAll('fixed-asset-inventories')
}
