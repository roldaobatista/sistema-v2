import { useState, useEffect, useCallback, useRef } from 'react'
import { toast } from 'sonner'
import { captureError } from '@/lib/sentry'
import {
    getDb,
    type OfflineReadableStoreName,
    type OfflineStoreIndex,
    type OfflineStoreKey,
    type OfflineStoreValue,
} from '@/lib/offlineDb'

/* ─── Hook ───────────────────────────────────────────────── */

export function useOfflineStore<K extends OfflineReadableStoreName>(storeName: K) {
    type T = OfflineStoreValue<K>
    const [items, setItems] = useState<T[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const mountedRef = useRef(true)

    useEffect(() => {
        mountedRef.current = true
        return () => { mountedRef.current = false }
    }, [])

    const refresh = useCallback(async () => {
        setIsLoading(true)
        try {
            const db = await getDb()
            const all = await db.getAll(storeName) as T[]
            if (mountedRef.current) setItems(all)
        } catch (err) {
            captureError(err, { storeName })
            const msg = err instanceof Error ? err.message : String(err)
            toast.error(`Erro offline DB: ${msg}`)
        } finally {
            if (mountedRef.current) setIsLoading(false)
        }
    }, [storeName])

    useEffect(() => {
        refresh()
    }, [refresh])

    const getById = useCallback(async (id: OfflineStoreKey<K>): Promise<T | undefined> => {
        const db = await getDb()
        return db.get(storeName, id)
    }, [storeName])

    const put = useCallback(async (item: T): Promise<void> => {
        const db = await getDb()
        await db.put(storeName, item)
        await refresh()
    }, [storeName, refresh])

    const putMany = useCallback(async (newItems: T[]): Promise<void> => {
        const db = await getDb()
        const tx = db.transaction(storeName, 'readwrite')
        for (const item of newItems) {
            tx.store.put(item)
        }
        await tx.done
        await refresh()
    }, [storeName, refresh])

    const remove = useCallback(async (id: OfflineStoreKey<K>): Promise<void> => {
        const db = await getDb()
        await db.delete(storeName, id)
        await refresh()
    }, [storeName, refresh])

    const clear = useCallback(async (): Promise<void> => {
        const db = await getDb()
        await db.clear(storeName)
        if (mountedRef.current) setItems([])
    }, [storeName])

    const count = useCallback(async (): Promise<number> => {
        const db = await getDb()
        return db.count(storeName)
    }, [storeName])

    const getByIndex = useCallback(async (
        indexName: OfflineStoreIndex<K>,
        value: IDBValidKey,
    ): Promise<T[]> => {
        const db = await getDb()
        return db.getAllFromIndex(storeName, indexName, value)
    }, [storeName])

    return {
        items,
        isLoading,
        refresh,
        getById,
        put,
        putMany,
        remove,
        clear,
        count,
        getByIndex,
    }
}

/* ─── Specialized: unsynced items ────────────────────────── */

export function useUnsyncedItems<K extends 'checklist-responses' | 'expenses' | 'photos' | 'signatures'>(storeName: K) {
    type T = OfflineStoreValue<K>
    const [unsyncedItems, setUnsyncedItems] = useState<T[]>([])
    const [pendingCount, setPendingCount] = useState(0)

    const refresh = useCallback(async () => {
        const db = await getDb()
        // synced index stores boolean as 0/1 in IndexedDB
        const all = await db.getAllFromIndex(storeName, 'by-synced', 0)
        setUnsyncedItems(all)
        setPendingCount(all.length)
    }, [storeName])

    useEffect(() => {
        refresh()
    }, [refresh])

    return { unsyncedItems, pendingCount, refresh }
}
