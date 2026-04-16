import { useEffect, useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckSquare, Square, Plus, Trash2, Camera, Copy } from 'lucide-react'
import { buildStorageUrl } from '@/lib/api'
import { workOrderApi } from '@/lib/work-order-api'
import { queryKeys } from '@/lib/query-keys'
import { toast } from 'sonner'
import { cn, getApiErrorMessage } from '@/lib/utils'
import type { WorkOrderPhotoChecklist, WorkOrderPhotoChecklistItem } from '@/types/work-order'

interface PhotoChecklistProps {
    workOrderId: number
    initialChecklist?: WorkOrderPhotoChecklist | null
    canEdit?: boolean
}

export default function PhotoChecklist({
    workOrderId,
    initialChecklist,
    canEdit = true,
}: PhotoChecklistProps) {
    const qc = useQueryClient()
    const [checklist, setChecklist] = useState<WorkOrderPhotoChecklist>(initialChecklist ?? {})
    const [items, setItems] = useState<WorkOrderPhotoChecklistItem[]>(initialChecklist?.items ?? [])
    const [newText, setNewText] = useState('')
    const fileInputRef = useRef<HTMLInputElement>(null)
    const [uploadingId, setUploadingId] = useState<string | null>(null)

    const resolveAttachmentUrl = (payload: { data?: { url?: string; file_path?: string }; url?: string; file_path?: string }) => {
        const url = payload?.data?.url ?? payload?.url
        if (url) {
            return url
        }

        const filePath = payload?.data?.file_path ?? payload?.file_path
        if (!filePath) {
            return null
        }

        return buildStorageUrl(filePath)
    }

    useEffect(() => {
        setChecklist(initialChecklist ?? {})
        setItems(initialChecklist?.items ?? [])
    }, [initialChecklist])

    const saveMut = useMutation({
        mutationFn: (photo_checklist: WorkOrderPhotoChecklist) =>
            workOrderApi.update(workOrderId, { photo_checklist }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao salvar checklist com fotos'))
        },
    })

    const save = (updatedItems: WorkOrderPhotoChecklistItem[]) => {
        const updatedChecklist = { ...checklist, items: updatedItems }
        setChecklist(updatedChecklist)
        setItems(updatedItems)
        saveMut.mutate(updatedChecklist)
    }

    const addItem = () => {
        if (!canEdit || !newText.trim()) return

        const item: WorkOrderPhotoChecklistItem = { id: crypto.randomUUID(), text: newText.trim(), checked: false }
        save([...items, item])
        setNewText('')
    }

    const toggleItem = (id: string) => {
        if (!canEdit) return
        save((items || []).map((item) => item.id === id ? { ...item, checked: !item.checked } : item))
    }

    const removeItem = (id: string) => {
        if (!canEdit) return
        save((items || []).filter((item) => item.id !== id))
    }

    const handlePhoto = (id: string) => {
        if (!canEdit) return
        setUploadingId(id)
        fileInputRef.current?.click()
    }

    const onFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!canEdit) {
            e.target.value = ''
            return
        }

        const file = e.target.files?.[0]
        if (!file || !uploadingId) return

        try {
            const fd = new FormData()
            fd.append('file', file)
            const res = await workOrderApi.uploadAttachment(workOrderId, fd)
            const url = resolveAttachmentUrl(res.data as { data?: { url?: string; file_path?: string }; url?: string; file_path?: string })

            if (url) {
                save((items || []).map((item) => item.id === uploadingId ? { ...item, photo_url: url } : item))
                toast.success('Foto anexada ao item')
            } else {
                toast.error('O anexo foi enviado, mas a URL da foto nao foi retornada.')
            }
        } catch (err: unknown) {
            toast.error(getApiErrorMessage(err, 'Erro ao enviar foto'))
        } finally {
            setUploadingId(null)
            e.target.value = ''
        }
    }

    const cloneChecklist = () => {
        navigator.clipboard.writeText(JSON.stringify(items))
            .then(() => toast.success('Checklist copiado para clipboard!'))
            .catch(() => toast.error('Nao foi possivel copiar o checklist.'))
    }

    const progress = items.length > 0 ? Math.round(((items || []).filter((item) => item.checked).length / items.length) * 100) : 0

    return (
        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="flex items-center gap-2 text-sm font-semibold text-surface-900">
                    <CheckSquare className="h-4 w-4 text-brand-500" />
                    Checklist
                    {items.length > 0 && (
                        <span className="text-[10px] font-normal text-surface-400">
                            {(items || []).filter((item) => item.checked).length}/{items.length}
                        </span>
                    )}
                </h3>
                {items.length > 0 && (
                    <button onClick={cloneChecklist} className="text-surface-400 hover:text-brand-500" aria-label="Copiar checklist">
                        <Copy className="h-3.5 w-3.5" />
                    </button>
                )}
            </div>

            {items.length > 0 && (
                <div className="mb-3">
                    <div className="mb-1 flex justify-between text-[10px] text-surface-400">
                        <span>Progresso</span>
                        <span>{progress}%</span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-surface-100">
                        <div className="h-full rounded-full bg-brand-500 transition-all" style={{ width: `${progress}%` }} />
                    </div>
                </div>
            )}

            <div className="space-y-1.5">
                {(items || []).map((item) => (
                    <div
                        key={item.id}
                        className={cn(
                            'group flex items-start gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-surface-50',
                            item.checked && 'opacity-60',
                        )}
                    >
                        {canEdit ? (
                            <button onClick={() => toggleItem(item.id)} className="mt-0.5" aria-label={item.checked ? 'Desmarcar item' : 'Marcar item como concluido'}>
                                {item.checked
                                    ? <CheckSquare className="h-4 w-4 text-emerald-500" />
                                    : <Square className="h-4 w-4 text-surface-300" />}
                            </button>
                        ) : (
                            <span className="mt-0.5" aria-hidden="true">
                                {item.checked
                                    ? <CheckSquare className="h-4 w-4 text-emerald-500" />
                                    : <Square className="h-4 w-4 text-surface-300" />}
                            </span>
                        )}
                        <div className="min-w-0 flex-1">
                            <span className={cn('text-xs text-surface-700', item.checked && 'line-through')}>
                                {item.text}
                            </span>
                            {item.photo_url && (
                                <img src={item.photo_url} alt="Foto do item" className="mt-1 h-12 w-16 rounded-md object-cover" />
                            )}
                        </div>
                        {canEdit && (
                            <div className="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                <button onClick={() => handlePhoto(item.id)} className="p-1 text-surface-400 hover:text-brand-500" aria-label="Anexar foto">
                                    <Camera className="h-3 w-3" />
                                </button>
                                <button onClick={() => removeItem(item.id)} className="p-1 text-surface-400 hover:text-red-500" aria-label="Remover item">
                                    <Trash2 className="h-3 w-3" />
                                </button>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {canEdit ? (
                <div className="mt-2 flex items-center gap-2">
                    <input
                        value={newText}
                        onChange={(e) => setNewText(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && addItem()}
                        placeholder="Novo item..."
                        aria-label="Texto do novo item do checklist"
                        className="flex-1 rounded-lg border border-subtle bg-surface-50 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    />
                    <button onClick={addItem} className="rounded-lg bg-brand-500 p-1.5 text-white transition-colors hover:bg-brand-600" aria-label="Adicionar item">
                        <Plus className="h-3.5 w-3.5" />
                    </button>
                </div>
            ) : (
                <p className="mt-3 text-xs text-surface-400">
                    Somente leitura.
                </p>
            )}

            <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={onFileChange} aria-label="Upload de foto para checklist" />
        </div>
    )
}
