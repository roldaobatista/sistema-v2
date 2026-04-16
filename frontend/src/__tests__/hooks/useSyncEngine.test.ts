import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('idb', () => ({ openDB: vi.fn() }))

vi.mock('@/lib/offlineDb', () => ({
    getDb: vi.fn().mockResolvedValue({
        getAll: vi.fn().mockResolvedValue([]),
        put: vi.fn().mockResolvedValue(undefined),
        get: vi.fn().mockResolvedValue(undefined),
        getAllFromIndex: vi.fn().mockResolvedValue([]),
        transaction: vi.fn().mockReturnValue({
            objectStore: vi.fn().mockReturnValue({
                put: vi.fn().mockResolvedValue(undefined),
                getAll: vi.fn().mockResolvedValue([]),
            }),
            done: Promise.resolve(),
        }),
    }),
    getAllMutations: vi.fn().mockResolvedValue([]),
    dequeueMutation: vi.fn().mockResolvedValue(undefined),
    enqueueMutation: vi.fn().mockResolvedValue('ulid-123'),
    updateMutation: vi.fn().mockResolvedValue(undefined),
    generateUlid: vi.fn().mockReturnValue('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: {} }),
        post: vi.fn().mockResolvedValue({ data: { processed: 0, errors: [], conflicts: [] } }),
        request: vi.fn().mockResolvedValue({ data: {} }),
    },
}))

describe('syncEngine', () => {
    beforeEach(() => {
        vi.resetModules()
    })

    it('exports singleton and wrappers', async () => {
        const mod = await import('@/lib/syncEngine')

        expect(mod.syncEngine).toBeDefined()
        expect(typeof mod.syncEngine.fullSync).toBe('function')
        expect(typeof mod.syncEngine.getIsSyncing).toBe('function')
        expect(typeof mod.syncEngine.onSyncComplete).toBe('function')
        expect(typeof mod.offlinePost).toBe('function')
        expect(typeof mod.offlinePut).toBe('function')
    })

    it('keeps last-write-wins behavior documented', () => {
        const local = { updated_at: '2026-02-12T18:00:00Z', value: 'local' }
        const remote = { updated_at: '2026-02-12T17:00:00Z', value: 'remote' }

        const winner = new Date(local.updated_at).getTime() >= new Date(remote.updated_at).getTime()
            ? local
            : remote

        expect(winner.value).toBe('local')
    })

    it('removes queued mutation on permanent 422 error', async () => {
        vi.resetModules()
        localStorage.setItem('auth_token', 'test-token')

        const dequeueMutation = vi.fn().mockResolvedValue(undefined)
        const updateMutation = vi.fn().mockResolvedValue(undefined)

        vi.doMock('@/lib/offlineDb', () => ({
            getDb: vi.fn().mockResolvedValue({
                getAll: vi.fn().mockResolvedValue([]),
                put: vi.fn().mockResolvedValue(undefined),
                get: vi.fn().mockResolvedValue(undefined),
                getAllFromIndex: vi.fn().mockResolvedValue([]),
                transaction: vi.fn().mockReturnValue({
                    objectStore: vi.fn().mockReturnValue({
                        put: vi.fn().mockResolvedValue(undefined),
                        getAll: vi.fn().mockResolvedValue([]),
                    }),
                    done: Promise.resolve(),
                }),
            }),
            getAllMutations: vi.fn().mockResolvedValue([
                {
                    id: 'mutation-422',
                    method: 'POST',
                    url: '/api/v1/work-orders/1/execution/start-service',
                    body: {},
                    created_at: new Date().toISOString(),
                    retries: 0,
                    last_error: null,
                },
            ]),
            dequeueMutation,
            enqueueMutation: vi.fn().mockResolvedValue('ulid-123'),
            updateMutation,
            generateUlid: vi.fn().mockReturnValue('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        }))

        vi.doMock('@/lib/api', () => ({
            default: {
                get: vi.fn().mockResolvedValue({ data: { work_orders: [], equipment: [], checklists: [], standard_weights: [] } }),
                post: vi.fn().mockResolvedValue({ data: { processed: 0, errors: [], conflicts: [] } }),
                request: vi.fn().mockRejectedValue({
                    response: { status: 422, data: { message: 'Transição inválida' } },
                    message: 'Request failed with status code 422',
                }),
            },
        }))

        const mod = await import('@/lib/syncEngine')
        const result = await mod.syncEngine.fullSync()

        expect(dequeueMutation).toHaveBeenCalledWith('mutation-422')
        expect(updateMutation).not.toHaveBeenCalled()
        expect(result.errors.some((error: string) => error.includes('erro permanente (422)'))).toBe(true)
        localStorage.removeItem('auth_token')
    })

    it('marca como sincronizadas apenas as despesas offline sem erro ou conflito', async () => {
        vi.resetModules()
        localStorage.setItem('auth_token', 'test-token')

        const dbPut = vi.fn().mockResolvedValue(undefined)

        vi.doMock('@/lib/offlineDb', () => ({
            getDb: vi.fn().mockResolvedValue({
                getAll: vi.fn().mockResolvedValue([]),
                put: dbPut,
                get: vi.fn().mockResolvedValue(undefined),
                getAllFromIndex: vi.fn().mockImplementation((storeName: string) => {
                    if (storeName === 'expenses') {
                        return Promise.resolve([
                            {
                                id: 'expense-ok',
                                work_order_id: 10,
                                description: 'Pedágio',
                                amount: '45.00',
                                expense_date: '2026-03-12',
                                affects_technician_cash: true,
                                affects_net_value: true,
                                synced: false,
                                created_at: '2026-03-12T10:00:00Z',
                                updated_at: '2026-03-12T10:00:00Z',
                            },
                            {
                                id: 'expense-error',
                                work_order_id: 10,
                                description: 'Combustível',
                                amount: '89.90',
                                expense_date: '2026-03-12',
                                affects_technician_cash: true,
                                affects_net_value: true,
                                synced: false,
                                created_at: '2026-03-12T10:05:00Z',
                                updated_at: '2026-03-12T10:05:00Z',
                            },
                        ])
                    }

                    return Promise.resolve([])
                }),
                transaction: vi.fn().mockReturnValue({
                    objectStore: vi.fn().mockReturnValue({
                        put: vi.fn().mockResolvedValue(undefined),
                        getAll: vi.fn().mockResolvedValue([]),
                    }),
                    done: Promise.resolve(),
                }),
            }),
            getAllMutations: vi.fn().mockResolvedValue([]),
            dequeueMutation: vi.fn().mockResolvedValue(undefined),
            enqueueMutation: vi.fn().mockResolvedValue('ulid-123'),
            updateMutation: vi.fn().mockResolvedValue(undefined),
            generateUlid: vi.fn().mockReturnValue('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        }))

        vi.doMock('@/lib/api', () => ({
            default: {
                get: vi.fn().mockResolvedValue({ data: { work_orders: [], equipment: [], checklists: [], standard_weights: [] } }),
                post: vi.fn().mockResolvedValue({
                    data: {
                        processed: 1,
                        conflicts: [],
                        errors: [{ type: 'expense', id: 'expense-error', message: 'Categoria inválida' }],
                    },
                }),
                request: vi.fn().mockResolvedValue({ data: {} }),
            },
        }))

        const mod = await import('@/lib/syncEngine')
        await mod.syncEngine.fullSync()

        expect(dbPut).toHaveBeenCalledWith('expenses', expect.objectContaining({ id: 'expense-ok', synced: true, sync_error: null }))
        expect(dbPut).toHaveBeenCalledWith('expenses', expect.objectContaining({ id: 'expense-error', sync_error: 'Categoria inválida' }))
        expect(dbPut).not.toHaveBeenCalledWith('expenses', expect.objectContaining({ id: 'expense-error', synced: true }))
        localStorage.removeItem('auth_token')
    })

    it('normaliza urls offline para manter o prefixo /v1 no replay da fila', async () => {
        vi.resetModules()
        localStorage.setItem('auth_token', 'test-token')

        const request = vi.fn().mockResolvedValue({ data: {} })

        vi.doMock('@/lib/offlineDb', () => ({
            getDb: vi.fn().mockResolvedValue({
                getAll: vi.fn().mockResolvedValue([]),
                put: vi.fn().mockResolvedValue(undefined),
                get: vi.fn().mockResolvedValue(undefined),
                getAllFromIndex: vi.fn().mockResolvedValue([]),
                transaction: vi.fn().mockReturnValue({
                    objectStore: vi.fn().mockReturnValue({
                        put: vi.fn().mockResolvedValue(undefined),
                        getAll: vi.fn().mockResolvedValue([]),
                    }),
                    done: Promise.resolve(),
                }),
            }),
            getAllMutations: vi.fn().mockResolvedValue([
                {
                    id: 'mutation-url',
                    method: 'POST',
                    url: '/api/work-orders/15/execution/finalize',
                    body: { notes: 'offline' },
                    created_at: new Date().toISOString(),
                    retries: 0,
                    last_error: null,
                },
            ]),
            dequeueMutation: vi.fn().mockResolvedValue(undefined),
            enqueueMutation: vi.fn().mockResolvedValue('ulid-123'),
            updateMutation: vi.fn().mockResolvedValue(undefined),
            generateUlid: vi.fn().mockReturnValue('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        }))

        vi.doMock('@/lib/api', () => ({
            default: {
                get: vi.fn().mockResolvedValue({ data: { work_orders: [], equipment: [], checklists: [], standard_weights: [] } }),
                post: vi.fn().mockResolvedValue({ data: { processed: 0, errors: [], conflicts: [] } }),
                request,
            },
        }))

        const mod = await import('@/lib/syncEngine')
        await mod.syncEngine.fullSync()

        expect(request).toHaveBeenCalledWith(expect.objectContaining({
            url: '/v1/work-orders/15/execution/finalize',
        }))

        localStorage.removeItem('auth_token')
    })

    it('marca inventario offline como sincronizado apos replay bem-sucedido da fila', async () => {
        vi.resetModules()
        localStorage.setItem('auth_token', 'test-token')

        const markOfflineFixedAssetInventorySync = vi.fn().mockResolvedValue(undefined)
        const dequeueMutation = vi.fn().mockResolvedValue(undefined)
        const request = vi.fn().mockResolvedValue({ data: {} })

        vi.doMock('@/lib/offlineDb', () => ({
            getDb: vi.fn().mockResolvedValue({
                getAll: vi.fn().mockResolvedValue([]),
                put: vi.fn().mockResolvedValue(undefined),
                get: vi.fn().mockResolvedValue(undefined),
                getAllFromIndex: vi.fn().mockResolvedValue([]),
                transaction: vi.fn().mockReturnValue({
                    objectStore: vi.fn().mockReturnValue({
                        put: vi.fn().mockResolvedValue(undefined),
                        getAll: vi.fn().mockResolvedValue([]),
                    }),
                    done: Promise.resolve(),
                }),
            }),
            getAllMutations: vi.fn().mockResolvedValue([
                {
                    id: 'mutation-fixed-asset',
                    method: 'POST',
                    url: '/fixed-assets/44/inventories',
                    body: {
                        inventory_date: '2026-03-27',
                        offline_reference: 'offline-fixed-asset-1',
                        synced_from_pwa: true,
                    },
                    created_at: new Date().toISOString(),
                    retries: 0,
                    last_error: null,
                },
            ]),
            dequeueMutation,
            enqueueMutation: vi.fn().mockResolvedValue('ulid-123'),
            updateMutation: vi.fn().mockResolvedValue(undefined),
            markOfflineFixedAssetInventorySync,
            generateUlid: vi.fn().mockReturnValue('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        }))

        vi.doMock('@/lib/api', () => ({
            default: {
                get: vi.fn().mockResolvedValue({ data: { work_orders: [], equipment: [], checklists: [], standard_weights: [] } }),
                post: vi.fn().mockResolvedValue({ data: { processed: 0, errors: [], conflicts: [] } }),
                request,
            },
        }))

        const mod = await import('@/lib/syncEngine')
        await mod.syncEngine.fullSync()

        expect(request).toHaveBeenCalledWith(expect.objectContaining({
            url: '/fixed-assets/44/inventories',
        }))
        expect(dequeueMutation).toHaveBeenCalledWith('mutation-fixed-asset')
        expect(markOfflineFixedAssetInventorySync).toHaveBeenCalledWith('offline-fixed-asset-1', {
            synced: true,
            sync_error: null,
        })

        localStorage.removeItem('auth_token')
    })

    it('enfileira mutacoes offline com rota relativa ao /api/v1', async () => {
        vi.resetModules()

        const enqueueMutation = vi.fn().mockResolvedValue('queued-id')

        vi.doMock('@/lib/offlineDb', () => ({
            getDb: vi.fn(),
            getAllMutations: vi.fn().mockResolvedValue([]),
            dequeueMutation: vi.fn().mockResolvedValue(undefined),
            enqueueMutation,
            updateMutation: vi.fn().mockResolvedValue(undefined),
            generateUlid: vi.fn().mockReturnValue('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        }))

        vi.doMock('@/lib/api', () => ({
            default: {
                post: vi.fn().mockRejectedValue(new Error('Network Error')),
                put: vi.fn().mockRejectedValue(new Error('Network Error')),
                get: vi.fn(),
                request: vi.fn(),
            },
        }))

        const originalNavigator = globalThis.navigator
        Object.defineProperty(globalThis, 'navigator', {
            value: { ...originalNavigator, onLine: false },
            configurable: true,
        })

        const mod = await import('@/lib/syncEngine')
        await mod.offlinePost('/work-orders/77/execution/start-service', { source: 'test' })
        await mod.offlinePut('/work-orders/77', { status: 'completed' })

        expect(enqueueMutation).toHaveBeenNthCalledWith(
            1,
            'POST',
            '/work-orders/77/execution/start-service',
            { source: 'test' },
        )
        expect(enqueueMutation).toHaveBeenNthCalledWith(
            2,
            'PUT',
            '/work-orders/77',
            { status: 'completed' },
        )
    })
})
