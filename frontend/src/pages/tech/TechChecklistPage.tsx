import { useEffect, useState, useMemo } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
    ArrowLeft, CheckCircle2, Loader2, AlertCircle,
    ChevronDown, ChevronUp,
} from 'lucide-react'
import { toast } from 'sonner'
import { useOfflineStore } from '@/hooks/useOfflineStore'
import { offlinePost } from '@/lib/syncEngine'
import { generateUlid } from '@/lib/offlineDb'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { isPrivilegedFieldRole, isTechnicianLinkedToWorkOrder } from '@/lib/work-order-detail-utils'
import type { OfflineChecklist, OfflineChecklistResponse, OfflineWorkOrder } from '@/lib/offlineDb'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

interface ChecklistItem {
    id: string | number
    label: string
    type: string
    required: boolean
    options?: string[] | null
}

export default function TechChecklistPage() {
    const { id: woId } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const { items: checklists } = useOfflineStore('checklists')
    const { getById: getWorkOrderById } = useOfflineStore('work-orders')
    const { put: putResponse } = useOfflineStore('checklist-responses')
    const { user, hasPermission } = useAuthStore()
    const [selectedChecklist, setSelectedChecklist] = useState<OfflineChecklist | null>(null)
    const [expandedSections, setExpandedSections] = useState<Set<number>>(new Set())
    const [saving, setSaving] = useState(false)
    const [saved, setSaved] = useState(false)
    const [workOrderChecklistId, setWorkOrderChecklistId] = useState<number | null>(null)
    const [workOrder, setWorkOrder] = useState<OfflineWorkOrder | null>(null)

    useEffect(() => {
        if (!woId) return

        getWorkOrderById(Number(woId))
            .then((loadedWorkOrder) => {
                setWorkOrder(loadedWorkOrder ?? null)
                setWorkOrderChecklistId(loadedWorkOrder?.checklist_id ?? null)
            })
            .catch(() => {
                setWorkOrder(null)
                setWorkOrderChecklistId(null)
            })
    }, [getWorkOrderById, woId])

    const fieldRoles = Array.isArray(user?.roles)
        ? user.roles
        : (Array.isArray(user?.all_roles) ? user.all_roles : [])
    const isAdminFieldRole = isPrivilegedFieldRole(fieldRoles)
    const canEditChecklist = !!user
        && hasPermission('os.work_order.update')
        && !!workOrder
        && isTechnicianLinkedToWorkOrder(workOrder, user.id, isAdminFieldRole)

    // Dynamic schema generation
    const schema = useMemo(() => {
        if (!selectedChecklist || !selectedChecklist.items) return z.object({})
        const shape: Record<string, z.ZodTypeAny> = {}
        selectedChecklist.items.forEach((item: ChecklistItem) => {
            const key = String(item.id)
            if (item.type === 'boolean' || item.type === 'yes_no') {
                shape[key] = item.required
                    ? z.boolean({ required_error: 'Campo obrigatório', invalid_type_error: 'Campo obrigatório' })
                    : z.boolean().optional().nullable()
            } else {
                shape[key] = item.required
                    ? z.string({ required_error: 'Campo obrigatório' }).min(1, 'Campo obrigatório')
                    : z.string().optional().nullable()
            }
        })
        return z.object(shape)
    }, [selectedChecklist])

    type FormData = z.infer<typeof schema>

    const {
        control,
        handleSubmit,
        reset,
        formState: { errors, isValid },
    } = useForm<FormData>({
        resolver: zodResolver(schema),
        mode: 'onChange',
        defaultValues: {},
    })

    useEffect(() => {
        if (checklists.length === 0) return

        const matchingChecklist = workOrderChecklistId != null
            ? checklists.find((checklist) => checklist.id === workOrderChecklistId) ?? null
            : null

        const nextChecklist = matchingChecklist ?? checklists[0]

        if (selectedChecklist?.id === nextChecklist.id) return

        setSelectedChecklist(nextChecklist)
        setSaved(false)
        reset({}) // Clear form
        setExpandedSections(new Set((nextChecklist.items || []).map((_: ChecklistItem, i: number) => i)))
    }, [checklists, selectedChecklist?.id, workOrderChecklistId, reset])

    const toggleSection = (index: number) => {
        setExpandedSections((prev) => {
            const next = new Set(prev)
            if (next.has(index)) next.delete(index)
            else next.add(index)
            return next
        })
    }

    const onSubmit = async (data: FormData) => {
        if (!selectedChecklist || !woId) return
        if (!canEditChecklist) {
            toast.error('Você não pode salvar checklist nesta OS.')
            return
        }
        setSaving(true)
        try {
            const responseData: OfflineChecklistResponse = {
                id: generateUlid(),
                work_order_id: Number(woId),
                equipment_id: null,
                checklist_id: selectedChecklist.id,
                responses: data,
                completed_at: new Date().toISOString(),
                synced: false,
                updated_at: new Date().toISOString(),
            }

            const queued = await offlinePost('/tech/sync/batch', {
                mutations: [{
                    type: 'checklist_response',
                    data: responseData,
                }],
            })

            await putResponse({
                ...responseData,
                synced: !queued,
            })

            setSaved(true)
            toast.success(queued ? 'Checklist salvo offline para sincronizar depois' : 'Checklist sincronizado com sucesso')
        } catch {
            toast.error('Não foi possível salvar o checklist')
        } finally {
            setSaving(false)
        }
    }

    const items: ChecklistItem[] = selectedChecklist?.items || []

    return (
        <div className="flex flex-col h-full bg-background">
            {/* Header */}
            <div className="bg-card px-4 pt-3 pb-4 border-b border-border">
                <button onClick={() => navigate(`/tech/os/${woId}`)} className="flex items-center gap-1 text-sm text-brand-600 mb-2">
                    <ArrowLeft className="w-4 h-4" /> Voltar
                </button>
                <h1 className="text-lg font-bold text-foreground">Checklist</h1>

                {/* Checklist selector */}
                {checklists.length > 1 && (
                    <div className="mt-3 flex gap-2 overflow-x-auto no-scrollbar pb-1">
                        {(checklists || []).map((cl) => (
                            <button
                                key={cl.id}
                                onClick={() => {
                                    setSelectedChecklist(cl)
                                    setSaved(false)
                                    reset({})
                                    setExpandedSections(new Set((cl.items || []).map((_: ChecklistItem, i: number) => i)))
                                }}
                                className={cn(
                                    'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors',
                                    selectedChecklist?.id === cl.id
                                        ? 'bg-brand-600 text-white'
                                        : 'bg-surface-100 text-surface-600'
                                )}
                            >
                                {cl.name}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col flex-1 overflow-hidden">
                {/* Items */}
                <div className="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                    {items.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <AlertCircle className="w-10 h-10 text-surface-300" />
                            <p className="text-sm text-surface-500">Nenhum item no checklist</p>
                        </div>
                    ) : (
                        (items || []).map((item, index) => {
                            const isExpanded = expandedSections.has(index)
                            const fieldName = String(item.id)
                            const error = errors[fieldName]

                            return (
                                <Controller
                                    key={item.id}
                                    name={fieldName}
                                    control={control}
                                    render={({ field: { value, onChange } }) => {
                                        const isFilled = value !== undefined && value !== null && value !== ''

                                        return (
                                            <div className="bg-card rounded-xl overflow-hidden border border-border shadow-sm">
                                                <button
                                                    type="button"
                                                    onClick={() => toggleSection(index)}
                                                    className="w-full flex items-center gap-3 p-4"
                                                >
                                                    <div className={cn(
                                                        'w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0',
                                                        isFilled
                                                            ? 'bg-emerald-500 text-white'
                                                            : error
                                                                ? 'bg-red-100 text-red-600'
                                                                : item.required
                                                                    ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600'
                                                                    : 'bg-surface-100 text-surface-400'
                                                    )}>
                                                        {isFilled ? (
                                                            <CheckCircle2 className="w-4 h-4" />
                                                        ) : (
                                                            <span className="text-[10px] font-bold">{index + 1}</span>
                                                        )}
                                                    </div>
                                                    <div className="flex-1 text-left">
                                                        <p className="text-sm font-medium text-foreground">
                                                            {item.label}
                                                            {item.required && <span className="text-red-500 ml-1">*</span>}
                                                        </p>
                                                        {error && (
                                                            <p className="text-[10px] text-red-500 mt-0.5">{error.message as string}</p>
                                                        )}
                                                    </div>
                                                    {isExpanded ? (
                                                        <ChevronUp className="w-4 h-4 text-surface-400" />
                                                    ) : (
                                                        <ChevronDown className="w-4 h-4 text-surface-400" />
                                                    )}
                                                </button>

                                                {isExpanded && (
                                                    <div className="px-4 pb-4">
                                                        {item.type === 'boolean' || item.type === 'yes_no' ? (
                                                            <div className="flex gap-3">
                                                                {[
                                                                    { val: true, label: 'Conforme', color: 'bg-emerald-600' },
                                                                    { val: false, label: 'Não Conforme', color: 'bg-red-600' },
                                                                ].map((opt) => (
                                                                    <button
                                                                        key={String(opt.val)}
                                                                        type="button"
                                                                        onClick={() => onChange(opt.val)}
                                                                        className={cn(
                                                                            'flex-1 py-2.5 rounded-lg text-xs font-medium transition-colors',
                                                                            value === opt.val
                                                                                ? `${opt.color} text-white`
                                                                                : 'bg-surface-100 text-surface-600'
                                                                        )}
                                                                    >
                                                                        {opt.label}
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        ) : item.type === 'select' && item.options ? (
                                                            <div className="flex flex-wrap gap-2">
                                                                {(item.options || []).map((opt) => (
                                                                    <button
                                                                        key={opt}
                                                                        type="button"
                                                                        onClick={() => onChange(opt)}
                                                                        className={cn(
                                                                            'px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                                                                            value === opt
                                                                                ? 'bg-brand-600 text-white'
                                                                                : 'bg-surface-100 text-surface-600 hover:bg-surface-200'
                                                                        )}
                                                                    >
                                                                        {opt}
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        ) : (
                                                            <textarea
                                                                value={String(value || '')}
                                                                onChange={(e) => onChange(e.target.value)}
                                                                placeholder="Digite aqui..."
                                                                rows={2}
                                                                className={cn(
                                                                    "w-full px-3 py-2.5 rounded-lg bg-surface-100 border text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none resize-none",
                                                                    error ? "border-red-500 placeholder:text-red-300" : "border-transparent placeholder:text-surface-400"
                                                                )}
                                                            />
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )
                                    }}
                                />
                            )
                        })
                    )}
                </div>

                {/* Save button */}
                <div className="p-4 bg-card border-t border-border safe-area-bottom pb-8">
                    {!canEditChecklist && (
                        <p className="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Edição bloqueada: sua conta não possui permissão ou vínculo técnico com esta OS.
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={saving || !isValid || !canEditChecklist || items.length === 0}
                        className={cn(
                            'w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-colors',
                            saved
                                ? 'bg-emerald-600'
                                : isValid
                                    ? 'bg-brand-600 active:bg-brand-700'
                                    : 'bg-surface-300',
                            saving && 'opacity-70',
                        )}
                    >
                        {saving ? (
                            <Loader2 className="w-4 h-4 animate-spin" />
                        ) : saved ? (
                            <>
                                <CheckCircle2 className="w-4 h-4" /> Salvo
                            </>
                        ) : (
                            'Salvar Checklist'
                        )}
                    </button>
                </div>
            </form>
        </div>
    )
}
