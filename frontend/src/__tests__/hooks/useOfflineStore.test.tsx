import { act } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { renderHook, waitFor } from '@/__tests__/test-utils'
import { useOfflineStore, useUnsyncedItems } from '@/hooks/useOfflineStore'

const {
    mockToastError,
    mockCaptureError,
    mockGetDb,
    mockDb,
} = vi.hoisted(() => {
    const db = {
        getAll: vi.fn(),
        get: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        clear: vi.fn(),
        count: vi.fn(),
        getAllFromIndex: vi.fn(),
        transaction: vi.fn(),
    }

    return {
        mockToastError: vi.fn(),
        mockCaptureError: vi.fn(),
        mockGetDb: vi.fn(),
        mockDb: db,
    }
})

vi.mock('sonner', () => ({
    toast: {
        error: mockToastError,
    },
}))

vi.mock('@/lib/sentry', () => ({
    captureError: mockCaptureError,
}))

vi.mock('@/lib/offlineDb', () => ({
    getDb: (...args: unknown[]) => mockGetDb(...args),
}))

describe('useOfflineStore', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        const tx = {
            store: {
                put: vi.fn(),
            },
            done: Promise.resolve(),
        }

        mockDb.transaction.mockReturnValue(tx)
        mockDb.getAll.mockResolvedValue([])
        mockDb.get.mockResolvedValue(undefined)
        mockDb.put.mockResolvedValue(undefined)
        mockDb.delete.mockResolvedValue(undefined)
        mockDb.clear.mockResolvedValue(undefined)
        mockDb.count.mockResolvedValue(0)
        mockDb.getAllFromIndex.mockResolvedValue([])
        mockGetDb.mockResolvedValue(mockDb)
    })

    it('carrega itens e opera CRUD sem suprimir erros de tipagem', async () => {
        mockDb.getAll.mockResolvedValueOnce([
            { id: 1, status: 'pending', number: 'OS-1', updated_at: '2026-03-20T10:00:00Z' },
        ]).mockResolvedValue([])
        mockDb.get.mockResolvedValue({
            id: 1,
            status: 'pending',
            number: 'OS-1',
            updated_at: '2026-03-20T10:00:00Z',
        })
        mockDb.count.mockResolvedValue(3)

        const { result } = renderHook(() => useOfflineStore('work-orders'))

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(result.current.items).toHaveLength(1)

        await act(async () => {
            await result.current.getById(1)
            await result.current.put({
                id: 2,
                status: 'open',
                number: 'OS-2',
                updated_at: '2026-03-20T11:00:00Z',
            })
            await result.current.putMany([
                {
                    id: 3,
                    status: 'completed',
                    number: 'OS-3',
                    updated_at: '2026-03-20T12:00:00Z',
                },
            ])
            await result.current.remove(2)
            await result.current.clear()
        })

        const total = await result.current.count()

        expect(mockDb.get).toHaveBeenCalledWith('work-orders', 1)
        expect(mockDb.put).toHaveBeenCalledWith('work-orders', expect.objectContaining({ id: 2 }))
        expect(mockDb.transaction).toHaveBeenCalledWith('work-orders', 'readwrite')
        expect(txStorePut(mockDb)).toHaveBeenCalledWith(expect.objectContaining({ id: 3 }))
        expect(mockDb.delete).toHaveBeenCalledWith('work-orders', 2)
        expect(mockDb.clear).toHaveBeenCalledWith('work-orders')
        expect(total).toBe(3)
    })

    it('carrega itens pendentes sincronizaveis pelo indice by-synced', async () => {
        mockDb.getAllFromIndex.mockResolvedValue([
            {
                id: 'chk-1',
                work_order_id: 11,
                equipment_id: null,
                checklist_id: 7,
                responses: {},
                synced: false,
                updated_at: '2026-03-20T13:00:00Z',
            },
        ])

        const { result } = renderHook(() => useUnsyncedItems('checklist-responses'))

        await waitFor(() => {
            expect(result.current.pendingCount).toBe(1)
        })

        expect(mockDb.getAllFromIndex).toHaveBeenCalledWith('checklist-responses', 'by-synced', 0)
        expect(result.current.unsyncedItems[0]?.id).toBe('chk-1')
    })

    it('captura erro de refresh e mostra feedback ao usuario', async () => {
        const failure = new Error('IDB indisponivel')
        mockDb.getAll.mockRejectedValue(failure)

        const { result } = renderHook(() => useOfflineStore('photos'))

        await waitFor(() => {
            expect(result.current.isLoading).toBe(false)
        })

        expect(mockCaptureError).toHaveBeenCalledWith(failure, { storeName: 'photos' })
        expect(mockToastError).toHaveBeenCalledWith(`Erro offline DB: ${failure.message}`)
    })
})

function txStorePut(db: typeof mockDb) {
    return (db.transaction.mock.results[0]?.value as { store: { put: ReturnType<typeof vi.fn> } }).store.put
}
