import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Camera } from 'lucide-react'
import { buildStorageUrl, unwrapData } from '@/lib/api'
import { workOrderApi } from '@/lib/work-order-api'
import { getApiErrorMessage } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'

interface AttachmentItem {
    id: number
    file_name: string
    file_path: string
    description?: string | null
}

interface BeforeAfterPhotosProps {
    workOrderId: number
    canUpload?: boolean
    conditionAsFound?: string | null
    conditionAsLeft?: string | null
}

const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024
const ALLOWED_IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])

export default function BeforeAfterPhotos({ workOrderId, canUpload = true, conditionAsFound, conditionAsLeft }: BeforeAfterPhotosProps) {
    const qc = useQueryClient()
    const fileInputRef = useRef<HTMLInputElement>(null)
    const [uploadType, setUploadType] = useState<'before' | 'after'>('before')
    const [compareMode, setCompareMode] = useState(false)

    const {
        data: attachRes,
        isError,
        error,
    } = useQuery({
        queryKey: ['work-order-attachments', workOrderId],
        queryFn: () => workOrderApi.attachments(workOrderId),
    })

    const allAttachments = unwrapData<AttachmentItem[]>(attachRes ?? {}) ?? []

    const isImageAttachment = (attachment: AttachmentItem) =>
        /\.(jpg|jpeg|png|webp|gif)$/i.test(attachment.file_name ?? '')

    const hasBeforeLabel = (attachment: AttachmentItem) =>
        (attachment.description ?? '').toLowerCase().includes('antes')

    const hasAfterLabel = (attachment: AttachmentItem) =>
        (attachment.description ?? '').toLowerCase().includes('depois')

    const beforePhotos = allAttachments.filter((attachment) => isImageAttachment(attachment) && hasBeforeLabel(attachment))
    const afterPhotos = allAttachments.filter((attachment) => isImageAttachment(attachment) && hasAfterLabel(attachment))

    const uploadMut = useMutation({
        mutationFn: (file: File) => {
            const formData = new FormData()
            formData.append('file', file)
            formData.append('description', uploadType === 'before' ? 'Foto antes do servico' : 'Foto depois do servico')

            return workOrderApi.uploadAttachment(workOrderId, formData)
        },
        onSuccess: () => {
            toast.success(`Foto "${uploadType === 'before' ? 'antes' : 'depois'}" salva`)
            qc.invalidateQueries({ queryKey: ['work-order-attachments', workOrderId] })
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao enviar foto')),
    })

    const handleUpload = (type: 'before' | 'after') => {
        setUploadType(type)
        fileInputRef.current?.click()
    }

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0]

        if (!file) {
            event.target.value = ''
            return
        }

        if (!ALLOWED_IMAGE_TYPES.has(file.type)) {
            toast.error('Envie uma imagem JPG, PNG, WEBP ou GIF.')
            event.target.value = ''
            return
        }

        if (file.size > MAX_IMAGE_SIZE_BYTES) {
            toast.error('A imagem excede o limite de 10 MB.')
            event.target.value = ''
            return
        }

        uploadMut.mutate(file)
        event.target.value = ''
    }

    const getUrl = (attachment: AttachmentItem) => buildStorageUrl(attachment.file_path) ?? ''

    const hasPhotos = beforePhotos.length > 0 || afterPhotos.length > 0

    return (
        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                <Camera className="h-4 w-4 text-brand-500" />
                Fotos Antes / Depois
            </h3>

            {(conditionAsFound || conditionAsLeft) && (
                <div className="grid grid-cols-2 gap-4 mb-4">
                    <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-3">
                        <div className="text-xs font-semibold text-amber-700 mb-1">Como Encontrado</div>
                        <p className="text-sm text-surface-700">{conditionAsFound || '—'}</p>
                    </div>
                    <div className="rounded-lg border border-green-200 bg-green-50/50 p-3">
                        <div className="text-xs font-semibold text-green-700 mb-1">Como Deixado</div>
                        <p className="text-sm text-surface-700">{conditionAsLeft || '—'}</p>
                    </div>
                </div>
            )}

            {canUpload && (
                <>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*"
                        capture="environment"
                        className="hidden"
                        aria-label="Upload de foto"
                        onChange={handleFileChange}
                    />

                    <div className="mb-3 flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => handleUpload('before')}
                            loading={uploadMut.isPending && uploadType === 'before'}
                            icon={<Camera className="h-3.5 w-3.5" />}
                        >
                            Antes
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => handleUpload('after')}
                            loading={uploadMut.isPending && uploadType === 'after'}
                            icon={<Camera className="h-3.5 w-3.5" />}
                        >
                            Depois
                        </Button>
                    </div>
                </>
            )}

            {isError && (
                <p className="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {getApiErrorMessage(error, 'Nao foi possivel carregar as fotos desta OS.')}
                </p>
            )}

            {hasPhotos && (
                <div className="space-y-3">
                    {compareMode && beforePhotos.length > 0 && afterPhotos.length > 0 ? (
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <p className="mb-1 text-[10px] font-semibold uppercase tracking-wider text-red-500">Antes</p>
                                <img src={getUrl(beforePhotos[0])} alt="Antes" className="h-24 w-full rounded-lg border border-red-200 object-cover" />
                            </div>
                            <div>
                                <p className="mb-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-500">Depois</p>
                                <img src={getUrl(afterPhotos[0])} alt="Depois" className="h-24 w-full rounded-lg border border-emerald-200 object-cover" />
                            </div>
                        </div>
                    ) : (
                        <>
                            {beforePhotos.length > 0 && (
                                <div>
                                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-wider text-red-500">Antes ({beforePhotos.length})</p>
                                    <div className="flex gap-1.5 overflow-x-auto">
                                        {beforePhotos.map((photo) => (
                                            <img key={photo.id} src={getUrl(photo)} alt="Antes" className="h-16 w-16 flex-shrink-0 rounded-lg border border-red-200 object-cover" />
                                        ))}
                                    </div>
                                </div>
                            )}
                            {afterPhotos.length > 0 && (
                                <div>
                                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-500">Depois ({afterPhotos.length})</p>
                                    <div className="flex gap-1.5 overflow-x-auto">
                                        {afterPhotos.map((photo) => (
                                            <img key={photo.id} src={getUrl(photo)} alt="Depois" className="h-16 w-16 flex-shrink-0 rounded-lg border border-emerald-200 object-cover" />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {beforePhotos.length > 0 && afterPhotos.length > 0 && (
                        <button
                            onClick={() => setCompareMode(!compareMode)}
                            className="text-[11px] font-medium text-brand-600 hover:text-brand-700"
                        >
                            {compareMode ? 'Ver todas' : 'Comparar lado a lado'}
                        </button>
                    )}
                </div>
            )}

            {!hasPhotos && !isError && (
                <p className="py-2 text-center text-xs text-surface-400">
                    Nenhuma foto registrada
                </p>
            )}
        </div>
    )
}
