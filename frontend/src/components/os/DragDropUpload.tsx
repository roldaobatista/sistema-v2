import { useState, useCallback } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { FileUp, X, CheckCircle2, AlertCircle } from 'lucide-react'
import { workOrderApi } from '@/lib/work-order-api'
import { queryKeys } from '@/lib/query-keys'
import { cn, getApiErrorMessage } from '@/lib/utils'
import { toast } from 'sonner'

interface DragDropUploadProps {
    workOrderId: number
}

interface UploadItem {
    id: string
    name: string
    status: 'uploading' | 'done' | 'error'
}

const MAX_ATTACHMENT_SIZE_BYTES = 50 * 1024 * 1024
const ALLOWED_ATTACHMENT_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'video/mp4',
    'video/quicktime',
    'video/x-msvideo',
    'video/x-matroska',
])

export default function DragDropUpload({ workOrderId }: DragDropUploadProps) {
    const qc = useQueryClient()
    const [isDragging, setIsDragging] = useState(false)
    const [uploads, setUploads] = useState<UploadItem[]>([])

    const uploadMut = useMutation({
        mutationFn: ({ file }: { file: File; uploadId: string }) => {
            const fd = new FormData()
            fd.append('file', file)
            fd.append('type', 'document')
            return workOrderApi.uploadAttachment(workOrderId, fd)
        },
        onSuccess: (_d, variables) => {
            setUploads(prev => (prev || []).map(u => u.id === variables.uploadId ? { ...u, status: 'done' as const } : u))
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            toast.success(`Arquivo "${variables.file.name}" enviado com sucesso.`)
        },
        onError: (error: unknown, variables) => {
            setUploads(prev => (prev || []).map(u => u.id === variables.uploadId ? { ...u, status: 'error' as const } : u))
            toast.error(getApiErrorMessage(error, `Erro ao enviar "${variables.file.name}".`))
        },
    })

    const handleFiles = useCallback((files: FileList | null) => {
        if (!files || files.length === 0) return
        const validFiles = Array.from(files).filter((file) => {
            if (!ALLOWED_ATTACHMENT_TYPES.has(file.type)) {
                toast.error(`"${file.name}" possui um formato nao suportado.`)
                return false
            }

            if (file.size > MAX_ATTACHMENT_SIZE_BYTES) {
                toast.error(`"${file.name}" excede o limite de 50MB.`)
                return false
            }

            return true
        })

        if (validFiles.length === 0) {
            return
        }

        const newUploads = validFiles.map((file, index) => ({
            id: `${file.name}-${file.size}-${file.lastModified}-${index}`,
            name: file.name,
            status: 'uploading' as const,
        }))
        setUploads(prev => [...prev, ...newUploads])
        validFiles.forEach((file, index) => {
            const uploadId = `${file.name}-${file.size}-${file.lastModified}-${index}`
            uploadMut.mutate({ file, uploadId })
        })
        toast.info(`Enviando ${validFiles.length} arquivo(s)...`)
    }, [uploadMut])

    const onDragOver = (e: React.DragEvent) => { e.preventDefault(); setIsDragging(true) }
    const onDragLeave = () => setIsDragging(false)
    const onDrop = (e: React.DragEvent) => {
        e.preventDefault()
        setIsDragging(false)
        handleFiles(e.dataTransfer.files)
    }

    const removeUpload = (uploadId: string) => setUploads(prev => (prev || []).filter(u => u.id !== uploadId))

    return (
        <div className="space-y-2">
            {/* Drop zone */}
            <div
                onDragOver={onDragOver}
                onDragLeave={onDragLeave}
                onDrop={onDrop}
                className={cn(
                    'rounded-xl border-2 border-dashed p-6 text-center transition-all cursor-pointer',
                    isDragging
                        ? 'border-brand-400 bg-brand-50 scale-[1.01]'
                        : 'border-surface-200 bg-surface-50 hover:border-brand-300 hover:bg-brand-50/50'
                )}
                onClick={() => {
                    const input = document.createElement('input')
                    input.type = 'file'
                    input.multiple = true
                    input.accept = '.jpg,.jpeg,.png,.gif,.pdf,.mp4,.mov,.avi,.mkv'
                    input.onchange = () => handleFiles(input.files)
                    input.click()
                }}
            >
                <FileUp className={cn('mx-auto h-8 w-8 mb-2', isDragging ? 'text-brand-500' : 'text-surface-300')} />
                <p className="text-sm font-medium text-surface-600">
                    {isDragging ? 'Solte os arquivos aqui' : 'Arraste arquivos ou clique para enviar'}
                </p>
                <p className="text-xs text-surface-400 mt-1">
                    Fotos, documentos, PDFs — até 50MB cada
                </p>
            </div>

            {/* Upload progress */}
            {uploads.length > 0 && (
                <div className="space-y-1">
                    {(uploads || []).map(u => (
                        <div key={u.id} className="flex items-center gap-2 rounded-lg bg-surface-50 px-3 py-1.5 text-xs">
                            {u.status === 'uploading' && <div className="h-3 w-3 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" />}
                            {u.status === 'done' && <CheckCircle2 className="h-3 w-3 text-emerald-500" />}
                            {u.status === 'error' && <AlertCircle className="h-3 w-3 text-red-500" />}
                            <span className="flex-1 truncate text-surface-600">{u.name}</span>
                            {u.status !== 'uploading' && (
                                <button onClick={() => removeUpload(u.id)} className="text-surface-400 hover:text-red-500" aria-label={`Remover ${u.name}`}>
                                    <X className="h-3 w-3" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    )
}
