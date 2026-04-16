import React, { useState, useMemo } from 'react'
import {
    DndContext,
    DragOverlay,
    closestCorners,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type DragStartEvent,
    type DragOverEvent,
    type DragEndEvent,
    defaultDropAnimationSideEffects,
    type DropAnimation,
} from '@dnd-kit/core'
import {
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
    useSortable,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Search, Plus, User, AlertTriangle, X,
} from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { workOrderStatus } from '@/lib/status-config'
import { workOrderApi } from '@/lib/work-order-api'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'
import { cn, formatCurrency, getApiErrorMessage } from '@/lib/utils'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import type { BadgeProps } from '@/components/ui/badge'
import { useAuthStore } from '@/stores/auth-store'
import { PageHeader } from '@/components/ui/pageheader'
import type { WorkOrder } from '@/types/work-order'


const statusConfig = workOrderStatus
type BadgeVariant = NonNullable<BadgeProps['variant']>

const priorityConfig: Record<string, { label: string; variant: BadgeVariant }> = {
    low: { label: 'Baixa', variant: 'default' },
    normal: { label: 'Normal', variant: 'info' },
    high: { label: 'Alta', variant: 'warning' },
    urgent: { label: 'Urgente', variant: 'danger' },
}

const columns = Object.keys(statusConfig)
const woIdentifier = (wo?: { number: string; os_number?: string | null; business_number?: string | null } | null) =>
    wo?.business_number ?? wo?.os_number ?? wo?.number ?? '—'


// --- Sortable Item Component ---
function SortableItem({ id, workOrder, onClick, canDrag }: { id: number; workOrder: WorkOrder; onClick: () => void; canDrag: boolean }) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id, disabled: !canDrag })

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    }

    const formatBRL = (v: string) => formatCurrency(parseFloat(v))

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            {...listeners}
            onClick={onClick}
            className={cn(
                "bg-surface-0 p-3 rounded-lg border border-default shadow-card transition-all mb-2",
                canDrag ? "cursor-grab active:cursor-grabbing" : "cursor-pointer",
                isDragging && "ring-2 ring-brand-500 ring-opacity-50 z-50"
            )}
        >
            <div className="flex justify-between items-start mb-2">
                <span className="text-xs font-bold text-brand-600">{woIdentifier(workOrder)}</span>
                {workOrder.priority !== 'normal' && (
                    <Badge variant={priorityConfig[workOrder.priority]?.variant ?? 'default'} className="px-1.5 py-0 text-xs">
                        {workOrder.priority === 'urgent' && <AlertTriangle className="h-3 w-3 mr-0.5 inline" />}
                        {priorityConfig[workOrder.priority]?.label}
                    </Badge>
                )}
            </div>
            <p className="text-sm font-medium text-surface-900 mb-2 line-clamp-2">{workOrder.description}</p>
            <div className="flex items-center gap-1.5 text-xs text-surface-500 mb-2">
                <User className="h-3 w-3" />
                <span className="truncate">{workOrder.customer?.name ?? '—'}</span>
            </div>
            <div className="flex justify-between items-center text-xs pt-2 border-t border-subtle">
                <span className="font-semibold text-surface-700">{formatBRL(String(workOrder.total ?? 0))}</span>
                {workOrder.assignee && (
                    <span className="bg-surface-100 px-1.5 py-0.5 rounded text-surface-600 truncate max-w-[80px]">
                        {workOrder.assignee.name.split(' ')[0]}
                    </span>
                )}
            </div>
        </div>
    )
}

// --- Main Page Component ---
export function WorkOrderKanbanPage() {
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const [search, setSearch] = useState('')
    const [priorityFilter, setPriorityFilter] = useState('')
    const [techFilter, setTechFilter] = useState('')
    const [dateFrom, setDateFrom] = useState('')
    const [dateTo, setDateTo] = useState('')
    const [serviceTypeFilter, setServiceTypeFilter] = useState('')
    const [activeId, setActiveId] = useState<number | null>(null)
    const [expandedCols, setExpandedCols] = useState<Set<string>>(new Set())
    const KANBAN_COL_LIMIT = 50
    const { hasPermission } = useAuthStore()
    const canViewWorkOrders = hasPermission('os.work_order.view')
    const canChangeStatus = hasPermission('os.work_order.change_status')

    const hasActiveFilters = !!(search || priorityFilter || techFilter || dateFrom || dateTo || serviceTypeFilter)

    const clearAllFilters = () => {
        setSearch(''); setPriorityFilter(''); setTechFilter(''); setDateFrom(''); setDateTo(''); setServiceTypeFilter('')
    }

    // Fetch technicians for filter
    const { data: techniciansRes } = useQuery({
        queryKey: ['users', 'technicians'],
        queryFn: () => api.get('/users', { params: { role: 'technician', per_page: 100 } }).then(r => r.data),
        staleTime: 5 * 60 * 1000,
        enabled: canViewWorkOrders,
    })
    const techniciansList: { id: number; name: string }[] = techniciansRes?.data?.data ?? techniciansRes?.data ?? []

    // Fetch Data — server-side filters
    const { data: res, isLoading, isError, refetch } = useQuery({
        queryKey: queryKeys.workOrders.kanban(JSON.stringify({ search, priority: priorityFilter, assigned_to: techFilter, date_from: dateFrom, date_to: dateTo, service_type: serviceTypeFilter })),
        queryFn: () => workOrderApi.list({
            search: search || undefined,
            per_page: 500,
            status: columns.join(','),
            priority: priorityFilter || undefined,
            assigned_to: techFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            service_type: serviceTypeFilter || undefined,
        }),
        enabled: canViewWorkOrders,
    })

    // Process Data into Columns — no more client-side filtering, data comes pre-filtered
    const items = useMemo(() => {
            const raw = (res?.data?.data ?? []) as WorkOrder[]
            const grouped: Record<string, WorkOrder[]> = {};
            (columns || []).forEach(col => grouped[col] = [])
            ;(raw || []).forEach(wo => {
                if (grouped[wo.status]) {
                    grouped[wo.status].push(wo)
                }
            })
            return grouped
        }, [res])

    const totalByCol = useMemo(() => {
            const totals: Record<string, number> = {};
            (columns || []).forEach(col => {
                totals[col] = (items[col] ?? []).reduce((acc, wo) => acc + parseFloat(String(wo.total ?? '0')), 0)
            })
            return totals
        }, [items])

    // Mutation for status update
    const updateStatusMutation = useMutation({
        mutationFn: ({ id, status }: { id: number; status: string }) =>
            workOrderApi.updateStatus(id, { status }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            toast.success('Status atualizado com sucesso!')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao alterar status'))
        },
    })

    // Drag & Drop Sensors
    const sensors = useSensors(
            useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
            useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
        )

    if (!canViewWorkOrders) {
        return (
            <div className="space-y-5">
                <PageHeader
                    title="Kanban de OS"
                    subtitle="Visualize e gerencie o fluxo de trabalho"
                />
                <div className="rounded-xl border border-default bg-surface-0 p-6 text-sm text-surface-600 shadow-card">
                    Voce nao possui permissao para visualizar o kanban de ordens de servico.
                </div>
            </div>
        )
    }

    // Drag Handlers
    const handleDragStart = (event: DragStartEvent) => {
            if (!canChangeStatus) return
            setActiveId(event.active.id as number)
        }

    const handleDragOver = (_event: DragOverEvent) => {
            // Optimization: We could handle optimistic UI updates here for smoother sorting
        }

    const handleDragEnd = (event: DragEndEvent) => {
            if (!canChangeStatus) {
                setActiveId(null)
                return
            }

            const { active, over } = event

            if (!over) return

            const activeId = active.id as number
            const overId = over.id

            // Find the work order and its current status
            let activeWorkOrder: WorkOrder | undefined
            let currentStatus = ''

            for (const col of columns) {
                const found = items[col].find(i => i.id === activeId)
                if (found) {
                    activeWorkOrder = found
                    currentStatus = col
                    break
                }
            }

            if (!activeWorkOrder) return

            let newStatus = ''

            // Check if dropped on a column container
            if (columns.includes(overId as string)) {
                newStatus = overId as string
            } else {
                // Find which column the "over" item belongs to
                for (const col of columns) {
                    if (items[col].find(i => i.id === overId)) {
                        newStatus = col
                        break
                    }
                }
            }

            if (newStatus && newStatus !== currentStatus) {
                const allowed = activeWorkOrder.allowed_transitions ?? []
                if (!allowed.includes(newStatus)) {
                    const fromLabel = statusConfig[currentStatus]?.label ?? currentStatus
                    const toLabel = statusConfig[newStatus]?.label ?? newStatus
                    toast.warning(`Transição não permitida: ${fromLabel} → ${toLabel}`)
                } else {
                    updateStatusMutation.mutate({ id: activeId, status: newStatus })
                }
            }

            setActiveId(null)
        }

    const dropAnimation: DropAnimation = {
            sideEffects: defaultDropAnimationSideEffects({
                styles: {
                    active: { opacity: '0.5' },
                },
            }),
        }

    return(
        <div className = "h-full flex flex-col overflow-hidden" >
                <div className="flex-none px-6 py-4 border-b border-default bg-surface-0 space-y-3">
                <PageHeader
                    title="Kanban de OS"
                    subtitle="Visualize e gerencie o fluxo de trabalho"
                    actions={hasPermission('os.work_order.create') ? [
                        {
                            label: 'Nova OS',
                            icon: <Plus className="h-4 w-4" />,
                            onClick: () => navigate('/os/nova'),
                        },
                    ] : []}
                />
                <div className="flex items-center gap-3">
                    <div className="relative w-64">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar..."
                            className="w-full rounded-lg border border-default pl-9 pr-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        />
                    </div>
                    <select value={priorityFilter} onChange={(e) => setPriorityFilter(e.target.value)}
                        className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                        aria-label="Filtrar por prioridade">
                        <option value="">Todas prioridades</option>
                        <option value="low">Baixa</option>
                        <option value="normal">Normal</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                    <select value={techFilter} onChange={(e) => setTechFilter(e.target.value)}
                        className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                        aria-label="Filtrar por técnico">
                        <option value="">Todos técnicos</option>
                        {techniciansList.map(t => (
                            <option key={t.id} value={t.id}>{t.name}</option>
                        ))}
                    </select>
                    <select value={serviceTypeFilter} onChange={(e) => setServiceTypeFilter(e.target.value)}
                        className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                        aria-label="Filtrar por tipo de serviço">
                        <option value="">Todos tipos</option>
                        <option value="corretiva">Corretiva</option>
                        <option value="preventiva">Preventiva</option>
                        <option value="instalacao">Instalação</option>
                        <option value="calibracao">Calibração</option>
                        <option value="vistoria">Vistoria</option>
                        <option value="outro">Outro</option>
                    </select>
                    <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} title="Data início" aria-label="Data início"
                        className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                    <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} title="Data fim" aria-label="Data fim"
                        className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearAllFilters} icon={<X className="h-4 w-4" />}
                            aria-label="Limpar todos os filtros" className="text-surface-500 hover:text-red-600">
                            Limpar
                        </Button>
                    )}
                </div>
                {!canChangeStatus && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        Modo somente leitura: seu perfil pode visualizar o kanban, mas nao pode arrastar nem alterar status.
                    </div>
                )}
            </div>

        <div className="flex-1 overflow-x-auto overflow-y-hidden bg-surface-50 p-6">
        {
            isError?(
                    <div className = "flex flex-col items-center justify-center h-full" >
                        <AlertTriangle className="h-12 w-12 text-red-300" />
                        <p className="mt-3 text-sm text-surface-500">Erro ao carregar ordens de serviço</p>
                        <Button className="mt-3" variant="outline" onClick={() => refetch()}>Tentar novamente</Button>
                    </div>
                ) : (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={handleDragStart}
            onDragOver={handleDragOver}
            onDragEnd={handleDragEnd}
        >
            <div className="flex h-full gap-4 min-w-max">
                {(columns || []).map(colId => (
                    <div key={colId} className="w-72 flex flex-col rounded-xl bg-surface-100 border border-default h-full max-h-full">
                        <div className={cn(
                            "p-3 rounded-t-xl border-b border-default bg-surface-0 sticky top-0 z-10",
                            statusConfig[colId].variant === 'success' && "border-t-4 border-t-green-500",
                            statusConfig[colId].variant === 'warning' && "border-t-4 border-t-amber-500",
                            statusConfig[colId].variant === 'danger' && "border-t-4 border-t-red-500",
                            statusConfig[colId].variant === 'info' && "border-t-4 border-t-sky-500",
                            statusConfig[colId].variant === 'brand' && "border-t-4 border-t-brand-500",
                        )}>
                            <div className="flex justify-between items-center">
                                <h3 className="font-semibold text-surface-700 text-sm">{statusConfig[colId].label}</h3>
                                <Badge variant="outline" className="bg-surface-50 text-surface-600 border-default">
                                    {items[colId]?.length ?? 0}
                                </Badge>
                            </div>
                            {(totalByCol[colId] ?? 0) > 0 && (
                                <p className="text-xs text-surface-400 mt-1 font-medium">
                                    {formatCurrency(Number(totalByCol[colId]))}
                                </p>
                            )}
                        </div>

                        <SortableContext
                            id={colId} // Column ID acts as droppable container
                            items={items[colId]?.map(i => i.id) ?? []}
                            strategy={verticalListSortingStrategy}
                        >
                            <div className="flex-1 overflow-y-auto p-2 scrollbar-thin scrollbar-thumb-surface-300 scrollbar-track-transparent">
                                {isLoading ? (
                                    <div className="space-y-2 p-2">
                                        {[1, 2, 3].map(i => (
                                            <div key={i} className="animate-pulse rounded-lg bg-surface-0 p-3 border border-default">
                                                <div className="flex justify-between mb-2">
                                                    <div className="h-3 w-16 rounded bg-surface-200" />
                                                    <div className="h-3 w-12 rounded bg-surface-100" />
                                                </div>
                                                <div className="h-4 w-full rounded bg-surface-100 mb-2" />
                                                <div className="h-3 w-2/3 rounded bg-surface-100" />
                                            </div>
                                        ))}
                                    </div>
                                ) : items[colId]?.length === 0 ? (
                                    <div className="p-4 text-center text-xs text-surface-400 border-2 border-dashed border-default rounded-lg m-2">
                                        Vazio
                                    </div>
                                ) : (() => {
                                    const colItems = items[colId] ?? []
                                    const isExpanded = expandedCols.has(colId)
                                    const visible = isExpanded ? colItems : colItems.slice(0, KANBAN_COL_LIMIT)
                                    const remaining = colItems.length - KANBAN_COL_LIMIT
                                    return (
                                        <>
                                            {visible.map(wo => (
                                                <SortableItem
                                                    key={wo.id}
                                                    id={wo.id}
                                                    workOrder={wo}
                                                    canDrag={canChangeStatus}
                                                    onClick={() => navigate(`/os/${wo.id}`)}
                                                />
                                            ))}
                                            {!isExpanded && remaining > 0 && (
                                                <button
                                                    onClick={() => setExpandedCols(prev => new Set([...prev, colId]))}
                                                    className="w-full text-center text-xs text-brand-600 hover:text-brand-700 py-2 mt-1 border border-dashed border-brand-300 rounded-lg hover:bg-brand-50 transition-colors"
                                                >
                                                    Mostrar mais {remaining} OS
                                                </button>
                                            )}
                                        </>
                                    )
                                })()}
                            </div>
                        </SortableContext>
                    </div>
                ))}
            </div>

            {/* Drag Overlay */}
            <DragOverlay dropAnimation={dropAnimation}>
                {canChangeStatus && activeId ? (() => {
                    const activeWo = Object.values(items).flat().find(wo => wo.id === activeId)
                    return (
                        <div className="bg-surface-0 p-3 rounded-lg border border-brand-200 shadow-card w-72 rotate-1 cursor-grabbing ring-2 ring-brand-500">
                            <div className="flex justify-between items-start mb-2">
                                <span className="text-xs font-bold text-brand-600">{activeWo ? woIdentifier(activeWo) : `#${activeId}`}</span>
                            </div>
                            {activeWo ? (
                                <>
                                    <p className="text-sm font-medium text-surface-900 mb-2 line-clamp-2">{activeWo.description}</p>
                                    <div className="flex items-center gap-1.5 text-xs text-surface-500">
                                        <User className="h-3 w-3" />
                                        <span className="truncate">{activeWo.customer?.name ?? '—'}</span>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="h-4 bg-surface-100 rounded w-3/4 mb-2"></div>
                                    <div className="h-3 bg-surface-50 rounded w-1/2"></div>
                                </>
                            )}
                        </div>
                    )
                })() : null}
            </DragOverlay>
        </DndContext>
    )
}
            </div>
        </div>
    )
}
