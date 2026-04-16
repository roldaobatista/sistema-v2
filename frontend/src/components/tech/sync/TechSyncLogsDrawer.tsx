import { useState, useEffect } from 'react'
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet'
import { getDb, type KalibriumDB, type OfflineChecklistResponse, type OfflineExpense, type OfflineSignature, type OfflineMutation, type OfflinePhoto } from '@/lib/offlineDb'
import type { IDBPDatabase, StoreValue, StoreKey } from 'idb'
import { syncEngine } from '@/lib/syncEngine'
import { Button } from '@/components/ui/button'
import { AlertCircle, RotateCcw, Trash2, CloudOff } from 'lucide-react'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { toast } from 'sonner'

export interface TechSyncLogsDrawerProps {
    open: boolean
    onOpenChange: (open: boolean) => void
}

type SyncErrorItem = {
    localId: string | number
    store: 'checklist-responses' | 'expenses' | 'signatures' | 'photos' | 'mutation-queue'
    typeLabel: string
    description: string
    errorMessage: string
    date: string
    itemRef: OfflineChecklistResponse | OfflineExpense | OfflineSignature | OfflinePhoto | OfflineMutation
}

/** Type-safe wrappers for dynamic store operations on IDB */
type SyncStoreName = SyncErrorItem['store']

async function dbPut<S extends SyncStoreName>(
    db: IDBPDatabase<KalibriumDB>,
    store: S,
    value: StoreValue<KalibriumDB, S>,
): Promise<StoreKey<KalibriumDB, S>> {
    return db.put(store, value)
}

async function dbDelete<S extends SyncStoreName>(
    db: IDBPDatabase<KalibriumDB>,
    store: S,
    key: StoreKey<KalibriumDB, S>,
): Promise<void> {
    return db.delete(store, key)
}

export function TechSyncLogsDrawer({ open, onOpenChange }: TechSyncLogsDrawerProps) {
    const [errors, setErrors] = useState<SyncErrorItem[]>([])
    const [loading, setLoading] = useState(false)
    const [isSyncing, setIsSyncing] = useState(false)

    const loadErrors = async () => {
        setLoading(true)
        try {
            const db = await getDb()
            const items: SyncErrorItem[] = []

            // Mutations
            const mutations = await db.getAllFromIndex('mutation-queue', 'by-created')
            mutations.forEach((m) => {
                if (m.last_error) {
                    items.push({
                        localId: m.id,
                        store: 'mutation-queue',
                        typeLabel: 'Requisição Local',
                        description: `${m.method} ${m.url}`,
                        errorMessage: m.last_error,
                        date: m.created_at,
                        itemRef: m,
                    })
                }
            })

            // Checklists
            const checklists = await db.getAllFromIndex('checklist-responses', 'by-synced', 0)
            checklists.forEach((c) => {
                if (c.sync_error) {
                    items.push({
                        localId: c.id,
                        store: 'checklist-responses',
                        typeLabel: 'Checklist',
                        description: `OS #${c.work_order_id}`,
                        errorMessage: c.sync_error,
                        date: c.updated_at,
                        itemRef: c,
                    })
                }
            })

            // Expenses
            const expenses = await db.getAllFromIndex('expenses', 'by-synced', 0)
            expenses.forEach((e) => {
                if (e.sync_error) {
                    items.push({
                        localId: e.id,
                        store: 'expenses',
                        typeLabel: 'Despesa',
                        description: `${e.description} (OS #${e.work_order_id})`,
                        errorMessage: e.sync_error,
                        date: e.updated_at,
                        itemRef: e,
                    })
                }
            })

            // Signatures
            const signatures = await db.getAllFromIndex('signatures', 'by-synced', 0)
            signatures.forEach((s) => {
                if (s.sync_error) {
                    items.push({
                        localId: s.id,
                        store: 'signatures',
                        typeLabel: 'Assinatura',
                        description: `OS #${s.work_order_id} - ${s.signer_name}`,
                        errorMessage: s.sync_error,
                        date: s.captured_at,
                        itemRef: s,
                    })
                }
            })

            // Photos
            const photos = await db.getAllFromIndex('photos', 'by-synced', 0)
            photos.forEach((p) => {
                if (p.sync_error) {
                    items.push({
                        localId: p.id,
                        store: 'photos',
                        typeLabel: 'Foto/Anexo',
                        description: `OS #${p.work_order_id} - ${p.file_name}`,
                        errorMessage: p.sync_error,
                        date: p.created_at,
                        itemRef: p,
                    })
                }
            })

            // Sort by date desc
            items.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
            setErrors(items)
        } catch {
            // Silent fail — drawer will show empty state
        } finally {
            setLoading(false)
        }
    }

    useEffect(() => {
        if (open) {
            loadErrors()
        }
    }, [open])

    useEffect(() => {
        const unsubscribe = syncEngine.onSyncComplete(() => {
            if (open) loadErrors()
            setIsSyncing(false)
        })
        return unsubscribe
    }, [open])

    const handleRetry = async (item: SyncErrorItem) => {
        try {
            const db = await getDb()
            const record = { ...item.itemRef } as Record<string, unknown>

            if (item.store === 'mutation-queue') {
                record.retries = 0
                record.last_error = null
            } else {
                record.sync_error = null
            }

            await dbPut(db, item.store, record as StoreValue<KalibriumDB, typeof item.store>)
            toast.success('Pronto para nova tentativa de sincronização.')
            loadErrors()
        } catch {
            toast.error('Erro ao remarcar tentativa.')
        }
    }

    const handleDelete = async (item: SyncErrorItem) => {
        if (!confirm('Tem certeza? Este dado será perdido permanentemente se não estiver no servidor.')) return
        try {
            const db = await getDb()

            await dbDelete(db, item.store, item.localId as StoreKey<KalibriumDB, typeof item.store>)
            toast.success('Item removido da fila local.')
            loadErrors()
        } catch {
            toast.error('Erro ao excluir item.')
        }
    }

    const handleRetryAll = async () => {
        setIsSyncing(true)
        try {
            const db = await getDb()
            for (const item of errors) {
                const record = { ...item.itemRef } as Record<string, unknown>
                if (item.store === 'mutation-queue') {
                    record.retries = 0
                    record.last_error = null
                } else {
                    record.sync_error = null
                }

                await dbPut(db, item.store, record as StoreValue<KalibriumDB, typeof item.store>)
            }
            toast.info('Iniciando sincronização...')
            syncEngine.fullSync()
        } catch {
            setIsSyncing(false)
            toast.error('Erro ao preparar nova tentativa.')
        }
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-md overflow-y-auto">
                <SheetHeader className="mb-6">
                    <SheetTitle className="flex items-center gap-2 text-brand-700">
                        <AlertCircle className="w-5 h-5 text-red-500" />
                        Erros de Sincronização
                    </SheetTitle>
                    <SheetDescription>
                        Esses dados offline não puderam ser enviados ao servidor devido a conflitos ou perda de sessão.
                    </SheetDescription>
                </SheetHeader>

                {loading ? (
                    <div className="flex justify-center p-8">
                        <div className="w-6 h-6 border-2 border-brand-500 border-t-transparent rounded-full animate-spin" />
                    </div>
                ) : errors.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-8 text-center bg-surface-50 rounded-lg border border-default">
                        <CloudOff className="w-10 h-10 text-surface-400 mb-3" />
                        <p className="text-surface-600 font-medium">Nenhum erro pendente</p>
                        <p className="text-sm text-surface-500 mt-1">Sua fila de sincronização está saudável.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between pb-2 border-b border-default">
                            <span className="text-sm font-medium text-surface-600">
                                {errors.length} {errors.length === 1 ? 'item' : 'itens'}
                            </span>
                            <Button size="sm" variant="outline" onClick={handleRetryAll} loading={isSyncing}>
                                <RotateCcw className="w-4 h-4 mr-2" />
                                Tentar Todos
                            </Button>
                        </div>

                        {errors.map((error, idx) => (
                            <div key={`${error.store}-${error.localId}-${idx}`} className="bg-white border text-left border-red-200 rounded-lg p-3 shadow-sm relative overflow-hidden group">
                                <div className="absolute left-0 top-0 bottom-0 w-1 bg-red-500" />
                                <div className="pl-3">
                                    <div className="flex justify-between items-start mb-1">
                                        <h4 className="font-semibold text-sm text-surface-900 leading-tight">
                                            {error.typeLabel}
                                        </h4>
                                        <span className="text-[10px] text-surface-400 uppercase font-medium bg-surface-100 px-1.5 py-0.5 rounded">
                                            {format(new Date(error.date), "dd/MMM HH:mm", { locale: ptBR })}
                                        </span>
                                    </div>
                                    <p className="text-xs text-surface-600 mb-2 truncate" title={error.description}>
                                        {error.description}
                                    </p>
                                    <div className="bg-red-50 text-red-700 text-xs p-2 rounded border border-red-100 mb-3 line-clamp-2" title={error.errorMessage}>
                                        {error.errorMessage}
                                    </div>

                                    <div className="flex items-center gap-2 justify-end">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDelete(error)}
                                            className="h-7 text-xs text-red-600 hover:text-red-700 hover:bg-red-50"
                                        >
                                            <Trash2 className="w-3.5 h-3.5 mr-1" />
                                            Excluir
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => handleRetry(error)}
                                            className="h-7 text-xs bg-surface-100 hover:bg-surface-200"
                                        >
                                            <RotateCcw className="w-3.5 h-3.5 mr-1" />
                                            Tentar
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    )
}
