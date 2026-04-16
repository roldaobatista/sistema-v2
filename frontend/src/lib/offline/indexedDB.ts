import { openDB, IDBPDatabase } from 'idb'

const DB_NAME = 'kalibrium-offline'
const DB_VERSION = 2
const STORE_NAME = 'sync-queue'
const JOURNEY_STORE = 'journey-events'

export interface OfflineRequest {
    id?: number
    uuid: string
    url: string
    method: string
    data: Record<string, unknown>
    headers: Record<string, string>
    timestamp: number
    localTimestamp: string
    status: 'pending' | 'syncing' | 'failed' | 'conflict'
    attempts: number
    eventType?: string
}

let dbPromise: Promise<IDBPDatabase> | null = null

export function getDB() {
    if (!dbPromise) {
        dbPromise = openDB(DB_NAME, DB_VERSION, {
            upgrade(db, oldVersion) {
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, {
                        keyPath: 'id',
                        autoIncrement: true,
                    })
                    store.createIndex('status', 'status')
                    store.createIndex('timestamp', 'timestamp')
                    store.createIndex('uuid', 'uuid', { unique: true })
                }
                if (oldVersion < 2 && !db.objectStoreNames.contains(JOURNEY_STORE)) {
                    const jStore = db.createObjectStore(JOURNEY_STORE, {
                        keyPath: 'id',
                        autoIncrement: true,
                    })
                    jStore.createIndex('uuid', 'uuid', { unique: true })
                    jStore.createIndex('eventType', 'eventType')
                    jStore.createIndex('timestamp', 'timestamp')
                }
            },
        })
    }
    return dbPromise
}

export function generateUUID(): string {
    return crypto.randomUUID()
}

export async function addToSyncQueue(request: Omit<OfflineRequest, 'id' | 'status' | 'attempts' | 'timestamp'>) {
    const db = await getDB()
    const entry: OfflineRequest = {
        ...request,
        uuid: request.uuid || generateUUID(),
        timestamp: Date.now(),
        localTimestamp: request.localTimestamp || new Date().toISOString(),
        status: 'pending',
        attempts: 0,
    }
    return db.add(STORE_NAME, entry)
}

export async function getQueueCount(): Promise<{ pending: number; failed: number; conflict: number }> {
    const db = await getDB()
    const all = await db.getAll(STORE_NAME)
    return {
        pending: all.filter((r) => r.status === 'pending').length,
        failed: all.filter((r) => r.status === 'failed').length,
        conflict: all.filter((r) => r.status === 'conflict').length,
    }
}

export async function getFailedRequests(): Promise<OfflineRequest[]> {
    const db = await getDB()
    return db.getAllFromIndex(STORE_NAME, 'status', 'failed')
}

export async function getConflictRequests(): Promise<OfflineRequest[]> {
    const db = await getDB()
    return db.getAllFromIndex(STORE_NAME, 'status', 'conflict')
}

export async function clearSyncedRequests(): Promise<void> {
    const db = await getDB()
    const tx = db.transaction(STORE_NAME, 'readwrite')
    const store = tx.objectStore(STORE_NAME)
    const all = await store.getAll()
    for (const entry of all) {
        if (entry.status === 'failed' || entry.status === 'conflict') {
            continue
        }
    }
    await tx.done
}

export async function getPendingRequests(): Promise<OfflineRequest[]> {
    const db = await getDB()
    return db.getAllFromIndex(STORE_NAME, 'status', 'pending')
}

export async function updateRequestStatus(id: number, status: OfflineRequest['status'], attempts?: number) {
    const db = await getDB()
    const tx = db.transaction(STORE_NAME, 'readwrite')
    const store = tx.objectStore(STORE_NAME)
    const entry = await store.get(id)
    if (entry) {
        entry.status = status
        if (typeof attempts === 'number') entry.attempts = attempts
        await store.put(entry)
    }
    await tx.done
}

export async function deleteRequest(id: number) {
    const db = await getDB()
    return db.delete(STORE_NAME, id)
}
