import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Tag, Plus, X } from 'lucide-react'
import { workOrderApi } from '@/lib/work-order-api'
import { queryKeys } from '@/lib/query-keys'
import { cn, getApiErrorMessage } from '@/lib/utils'
import { toast } from 'sonner'

const presetColors = [
    'bg-red-100 text-red-700',
    'bg-amber-100 text-amber-700',
    'bg-emerald-100 text-emerald-700',
    'bg-sky-100 text-sky-700',
    'bg-cyan-100 text-cyan-700',
    'bg-pink-100 text-pink-700',
    'bg-surface-100 text-surface-700',
]

interface TagManagerProps {
    workOrderId: number
    currentTags?: string[]
    canEdit?: boolean
}

export default function TagManager({ workOrderId, currentTags = [], canEdit = true }: TagManagerProps) {
    const qc = useQueryClient()
    const [isAdding, setIsAdding] = useState(false)
    const [newTag, setNewTag] = useState('')

    const tagMut = useMutation({
        mutationFn: (tags: string[]) => workOrderApi.update(workOrderId, { tags }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
            qc.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            toast.success('Tags atualizadas')
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao atualizar tags')),
    })

    const addTag = () => {
        if (!canEdit) {
            return
        }

        const tag = newTag.trim().toLowerCase()
        if (!tag || currentTags.includes(tag)) {
            return
        }

        tagMut.mutate([...currentTags, tag])
        setNewTag('')
        setIsAdding(false)
    }

    const removeTag = (tag: string) => {
        if (!canEdit) {
            return
        }

        tagMut.mutate(currentTags.filter((currentTag) => currentTag !== tag))
    }

    const getTagColor = (tag: string) => {
        const hash = tag.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
        return presetColors[hash % presetColors.length]
    }

    return (
        <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-900">
                <Tag className="h-4 w-4 text-brand-500" />
                Tags
            </h3>

            <div className="flex flex-wrap gap-1.5">
                {currentTags.map((tag) => (
                    <span key={tag} className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium', getTagColor(tag))}>
                        {tag}
                        {canEdit && (
                            <button onClick={() => removeTag(tag)} className="hover:opacity-70" aria-label={`Remover tag ${tag}`}>
                                <X className="h-2.5 w-2.5" />
                            </button>
                        )}
                    </span>
                ))}

                {canEdit && isAdding ? (
                    <div className="flex items-center gap-1">
                        <input
                            autoFocus
                            value={newTag}
                            onChange={(event) => setNewTag(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') addTag()
                                if (event.key === 'Escape') setIsAdding(false)
                            }}
                            onBlur={() => {
                                if (newTag.trim()) addTag()
                                else setIsAdding(false)
                            }}
                            placeholder="nova tag..."
                            aria-label="Nome da nova tag"
                            className="w-24 rounded-full border border-brand-300 bg-brand-50 px-2.5 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                        />
                    </div>
                ) : canEdit ? (
                    <button
                        onClick={() => setIsAdding(true)}
                        className="inline-flex items-center gap-1 rounded-full border border-dashed border-surface-300 px-2.5 py-1 text-xs text-surface-400 transition-colors hover:border-brand-300 hover:text-brand-500"
                    >
                        <Plus className="h-3 w-3" /> Adicionar
                    </button>
                ) : null}
            </div>

            {!canEdit && (
                <p className="mt-3 text-xs text-surface-400">
                    Somente leitura.
                </p>
            )}
        </div>
    )
}
