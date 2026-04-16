import { useState, useMemo } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth-store'
import { toast } from 'sonner'
import { getApiErrorMessage } from '@/lib/api'
import { quoteApi } from '@/lib/quote-api'
import { queryKeys } from '@/lib/query-keys'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { QUOTE_STATUS } from '@/lib/constants'
import type { Quote, QuoteSummary, AdvancedQuoteSummary, TransitionPayload, TransitionDef } from '@/types/quote'
import {
    DndContext,
    DragOverlay,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
    useDroppable,
    useDraggable,
    closestCorners,
    type DraggableSyntheticListeners,
    type DragStartEvent,
    type DragEndEvent,
} from '@dnd-kit/core'
import {
    ArrowLeft, BarChart3, Kanban, TrendingUp, DollarSign,
    Clock, Users, Target, GripVertical, Tag,
} from 'lucide-react'
import { formatCurrency } from '@/lib/utils'

// --- Kanban columns ---
const KANBAN_COLUMNS: { key: string; label: string; color: string }[] = [
    { key: QUOTE_STATUS.DRAFT, label: 'Rascunho', color: '#6b7280' },
    { key: QUOTE_STATUS.PENDING_INTERNAL, label: 'Aprov. Interna', color: '#f59e0b' },
    { key: QUOTE_STATUS.INTERNALLY_APPROVED, label: 'Aprovado Int.', color: '#14b8a6' },
    { key: QUOTE_STATUS.SENT, label: 'Enviado', color: '#3b82f6' },
    { key: QUOTE_STATUS.APPROVED, label: 'Aprovado', color: '#22c55e' },
    { key: QUOTE_STATUS.IN_EXECUTION, label: 'Em Execução', color: '#059669' },
    { key: QUOTE_STATUS.RENEGOTIATION, label: 'Renegociação', color: '#f43f5e' },
    { key: QUOTE_STATUS.REJECTED, label: 'Rejeitado', color: '#ef4444' },
    { key: QUOTE_STATUS.EXPIRED, label: 'Expirado', color: '#9ca3af' },
    { key: QUOTE_STATUS.INVOICED, label: 'Faturado', color: '#0d9488' },
]

KANBAN_COLUMNS.splice(7, 0, {
    key: QUOTE_STATUS.INSTALLATION_TESTING,
    label: 'Instalação p/ Teste',
    color: '#f97316',
})



const QUOTE_TRANSITIONS: Record<string, TransitionDef[]> = {
    [QUOTE_STATUS.DRAFT]: [
        { target: QUOTE_STATUS.PENDING_INTERNAL, endpoint: 'request-internal-approval' },
    ],
    [QUOTE_STATUS.PENDING_INTERNAL]: [
        { target: QUOTE_STATUS.INTERNALLY_APPROVED, endpoint: 'internal-approve' },
    ],
    [QUOTE_STATUS.INTERNALLY_APPROVED]: [
        { target: QUOTE_STATUS.SENT, endpoint: 'send' },
    ],
    [QUOTE_STATUS.SENT]: [
        { target: QUOTE_STATUS.APPROVED, endpoint: 'approve' },
        { target: QUOTE_STATUS.REJECTED, endpoint: 'reject' },
    ],
    [QUOTE_STATUS.REJECTED]: [
        { target: QUOTE_STATUS.DRAFT, endpoint: 'reopen' },
    ],
    [QUOTE_STATUS.EXPIRED]: [
        { target: QUOTE_STATUS.DRAFT, endpoint: 'reopen' },
    ],
    [QUOTE_STATUS.APPROVED]: [
        { target: QUOTE_STATUS.RENEGOTIATION, endpoint: 'renegotiate' },
    ],
    [QUOTE_STATUS.RENEGOTIATION]: [
        {
            target: QUOTE_STATUS.INTERNALLY_APPROVED,
            endpoint: 'revert-renegotiation',
            method: 'post',
            payload: { target_status: QUOTE_STATUS.INTERNALLY_APPROVED },
        },
    ],
}

QUOTE_TRANSITIONS[QUOTE_STATUS.INSTALLATION_TESTING] = [
    { target: QUOTE_STATUS.APPROVED, endpoint: 'approve-after-test' },
    { target: QUOTE_STATUS.RENEGOTIATION, endpoint: 'renegotiate' },
]

QUOTE_TRANSITIONS[QUOTE_STATUS.RENEGOTIATION] = [
    {
        target: QUOTE_STATUS.DRAFT,
        endpoint: 'revert-renegotiation',
        method: 'post',
        payload: { target_status: QUOTE_STATUS.DRAFT },
    },
    {
        target: QUOTE_STATUS.INTERNALLY_APPROVED,
        endpoint: 'revert-renegotiation',
        method: 'post',
        payload: { target_status: QUOTE_STATUS.INTERNALLY_APPROVED },
    },
]

function findTransition(from: string, to: string): TransitionDef | null {
    const transitions = QUOTE_TRANSITIONS[from]
    if (!transitions) return null
    return transitions.find(t => t.target === to) ?? null
}

function getAllowedTargets(from: string): string[] {
    return (QUOTE_TRANSITIONS[from] ?? []).map(t => t.target)
}

// ====== MAIN COMPONENT ======

// Mapeamento de permissão necessária para cada status-alvo no Kanban
const KANBAN_STATUS_PERMISSION: Record<string, string> = {
    [QUOTE_STATUS.PENDING_INTERNAL]: 'quotes.quote.send',
    [QUOTE_STATUS.INTERNALLY_APPROVED]: 'quotes.quote.internal_approve',
    [QUOTE_STATUS.SENT]: 'quotes.quote.send',
    [QUOTE_STATUS.APPROVED]: 'quotes.quote.approve',
    [QUOTE_STATUS.REJECTED]: 'quotes.quote.approve',
    [QUOTE_STATUS.DRAFT]: 'quotes.quote.update',
    [QUOTE_STATUS.RENEGOTIATION]: 'quotes.quote.update',
    [QUOTE_STATUS.IN_EXECUTION]: 'quotes.quote.convert',
    [QUOTE_STATUS.INSTALLATION_TESTING]: 'quotes.quote.convert',
    [QUOTE_STATUS.INVOICED]: 'quotes.quote.approve',
}

export default function QuotesDashboardPage() {
    const navigate = useNavigate()
    const { hasPermission } = useAuthStore()
    const _queryClient = useQueryClient()
    const [view, setView] = useState<'dashboard' | 'kanban'>('dashboard')

    const { data: summary, isError: isSummaryError, isLoading: isSummaryLoading, refetch: refetchSummary } = useQuery<QuoteSummary>({
        queryKey: queryKeys.quotes.summary,
        queryFn: () => quoteApi.summary(),
        enabled: hasPermission('quotes.quote.view')
    })

    const { data: advanced, isError: isAdvancedError, isLoading: isAdvancedLoading, refetch: refetchAdvanced } = useQuery<AdvancedQuoteSummary>({
        queryKey: queryKeys.quotes.advancedSummary,
        queryFn: () => quoteApi.advancedSummary(),
        enabled: hasPermission('quotes.quote.view')
    })

    // Verificação de permissão de acesso à página
    if (!hasPermission('quotes.quote.view')) {
        return (
            <div style={{ padding: '40px 24px', textAlign: 'center' }}>
                <h2 style={{ fontSize: 18, fontWeight: 600, color: '#374151' }}>Acesso Negado</h2>
                <p style={{ fontSize: 14, color: '#6b7280', marginTop: 8 }}>Você não tem permissão para acessar o dashboard de orçamentos.</p>
            </div>
        )
    }

    const hasError = isSummaryError || isAdvancedError
    const isLoading = isSummaryLoading || isAdvancedLoading

    return (
        <div style={{ padding: '16px 24px', maxWidth: 1600, margin: '0 auto' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20, flexWrap: 'wrap', gap: 12 }}>
                <div>
                    <h1 style={{ fontSize: 22, fontWeight: 700, color: '#111827', margin: 0 }}>Dashboard — Orçamentos</h1>
                    <p style={{ fontSize: 13, color: '#6b7280', marginTop: 4 }}>
                        {advanced?.total_quotes ?? 0} orçamentos · {advanced?.conversion_rate ?? 0}% de conversão
                    </p>
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <TabBtn active={view === 'dashboard'} onClick={() => setView('dashboard')} icon={<BarChart3 size={14} />}>Dashboard</TabBtn>
                    <TabBtn active={view === 'kanban'} onClick={() => setView('kanban')} icon={<Kanban size={14} />}>Kanban</TabBtn>
                    <button
                        onClick={() => navigate('/orcamentos')}
                        aria-label="Voltar à lista de orçamentos"
                        style={{ padding: '6px 14px', borderRadius: 8, border: '1px solid #d1d5db', background: 'white', fontSize: 13, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 4 }}
                    >
                        <ArrowLeft size={14} /> Lista
                    </button>
                </div>
            </div>

            {hasError ? (
                <div style={{ padding: '40px 24px', textAlign: 'center', background: 'white', borderRadius: 12, border: '1px solid #fee2e2' }}>
                    <h2 style={{ fontSize: 16, fontWeight: 600, color: '#ef4444' }}>Erro ao carregar métricas</h2>
                    <p style={{ fontSize: 13, color: '#6b7280', marginTop: 8 }}>Não foi possível carregar os dados do dashboard.</p>
                    <button
                        onClick={() => { refetchSummary(); refetchAdvanced(); }}
                        aria-label="Tentar carregar métricas novamente"
                        style={{ marginTop: 12, padding: '6px 14px', borderRadius: 8, border: '1px solid #d1d5db', background: 'white', fontSize: 13, cursor: 'pointer' }}
                    >
                        Tentar novamente
                    </button>
                </div>
            ) : view === 'dashboard' ? (
                <DashboardView summary={summary} advanced={advanced} />
            ) : (
                <KanbanView />
            )}
        </div>
    )
}

// ====== TAB BUTTON ======
function TabBtn({ active, onClick, icon, children }: { active: boolean; onClick: () => void; icon: React.ReactNode; children: React.ReactNode }) {
    return (
        <button
            onClick={onClick}
            aria-pressed={active}
            style={{
                padding: '6px 14px', borderRadius: 8, fontSize: 13, cursor: 'pointer', fontWeight: active ? 600 : 400,
                border: '1px solid ' + (active ? '#3b82f6' : '#d1d5db'),
                background: active ? '#3b82f6' : 'white',
                color: active ? 'white' : '#374151',
                display: 'flex', alignItems: 'center', gap: 5, transition: 'all 0.15s',
            }}
        >
            {icon}{children}
        </button>
    )
}

// ====== DASHBOARD VIEW ======
function DashboardView({ summary, advanced }: {
    summary?: QuoteSummary
    advanced?: AdvancedQuoteSummary
}) {
    const kpiCards = [
        { icon: <Target size={20} color="#3b82f6" />, label: 'Total Orçamentos', value: String(advanced?.total_quotes ?? 0), color: '#3b82f6' },
        { icon: <TrendingUp size={20} color="#22c55e" />, label: 'Taxa de Conversão', value: `${advanced?.conversion_rate ?? 0}%`, color: '#22c55e' },
        { icon: <DollarSign size={20} color="#0d9488" />, label: 'Ticket Médio', value: formatCurrency(advanced?.avg_ticket ?? 0), color: '#0d9488' },
        { icon: <Clock size={20} color="#f59e0b" />, label: 'Tempo Médio Conversão', value: `${advanced?.avg_conversion_days ?? 0} dias`, color: '#f59e0b' },
        { icon: <DollarSign size={20} color="#059669" />, label: 'Total do Mês', value: formatCurrency(summary?.total_month ?? 0), color: '#059669' },
    ]

    const maxTrend = Math.max(1, ...(advanced?.monthly_trend ?? []).map(m => m.total))

    const funnelSteps = summary ? [
        { label: 'Rascunho', count: summary.draft, color: '#6b7280' },
        { label: 'Aprov. Interna', count: summary.pending_internal_approval ?? 0, color: '#f59e0b' },
        { label: 'Aprovado Int.', count: summary.internally_approved ?? 0, color: '#14b8a6' },
        { label: 'Enviados', count: summary.sent, color: '#3b82f6' },
        { label: 'Aprovados', count: summary.approved, color: '#22c55e' },
        { label: 'Faturados', count: summary.invoiced, color: '#0d9488' },
        { label: 'Rejeitados', count: summary.rejected ?? 0, color: '#ef4444' },
    ] : []
    const maxFunnel = Math.max(...funnelSteps.map(s => s.count), 1)

    return (
        <>
            {/* KPI Cards */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 14, marginBottom: 20 }}>
                {kpiCards.map(c => (
                    <div key={c.label} style={{ background: 'white', borderRadius: 12, border: '1px solid #e5e7eb', padding: '16px 18px', display: 'flex', alignItems: 'center', gap: 14 }}>
                        <div style={{ width: 42, height: 42, borderRadius: 10, background: c.color + '12', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                            {c.icon}
                        </div>
                        <div style={{ minWidth: 0 }}>
                            <p style={{ fontSize: 11, color: '#6b7280', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em', margin: 0 }}>{c.label}</p>
                            <p style={{ fontSize: 20, fontWeight: 700, color: '#111827', margin: '2px 0 0', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.value}</p>
                        </div>
                    </div>
                ))}
            </div>

            {/* Charts row */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: 16, marginBottom: 20 }}>
                {/* Monthly trend */}
                <div style={{ background: 'white', borderRadius: 12, border: '1px solid #e5e7eb', padding: 20 }}>
                    <h3 style={{ fontSize: 14, fontWeight: 600, color: '#374151', marginBottom: 16, display: 'flex', alignItems: 'center', gap: 6, marginTop: 0 }}>
                        <BarChart3 size={16} /> Tendência Mensal
                    </h3>
                    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, height: 160 }}>
                        {(advanced?.monthly_trend ?? []).slice().reverse().map((m, i) => {
                            const approvedPct = m.total > 0 ? (m.approved / m.total) : 0
                            return (
                                <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2, minWidth: 0 }}>
                                    <div
                                        title={`${m.month}: ${m.total} total, ${m.approved} aprovados`}
                                        style={{
                                            width: '100%', maxWidth: 32,
                                            height: `${Math.max((m.total / maxTrend) * 140, 6)}px`,
                                            background: `linear-gradient(to top, #3b82f6 ${approvedPct * 100}%, #93c5fd ${approvedPct * 100}%)`,
                                            borderRadius: '4px 4px 0 0', cursor: 'pointer', transition: 'height 0.3s',
                                        }}
                                    />
                                    <span style={{ fontSize: 9, color: '#9ca3af', whiteSpace: 'nowrap' }}>{m.month?.slice(5)}</span>
                                </div>
                            )
                        })}
                    </div>
                    {(advanced?.monthly_trend?.length ?? 0) === 0 && (
                        <p style={{ textAlign: 'center', color: '#9ca3af', fontSize: 13 }}>Sem dados</p>
                    )}
                    <div style={{ display: 'flex', gap: 16, marginTop: 10, justifyContent: 'center' }}>
                        <span style={{ fontSize: 11, color: '#6b7280', display: 'flex', alignItems: 'center', gap: 4 }}>
                            <span style={{ width: 10, height: 10, borderRadius: 2, background: '#93c5fd', display: 'inline-block' }} /> Total
                        </span>
                        <span style={{ fontSize: 11, color: '#6b7280', display: 'flex', alignItems: 'center', gap: 4 }}>
                            <span style={{ width: 10, height: 10, borderRadius: 2, background: '#3b82f6', display: 'inline-block' }} /> Aprovados
                        </span>
                    </div>
                </div>

                {/* Top sellers */}
                <div style={{ background: 'white', borderRadius: 12, border: '1px solid #e5e7eb', padding: 20 }}>
                    <h3 style={{ fontSize: 14, fontWeight: 600, color: '#374151', marginBottom: 16, display: 'flex', alignItems: 'center', gap: 6, marginTop: 0 }}>
                        <Users size={16} /> Top Vendedores
                    </h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                        {(advanced?.top_sellers ?? []).map((s, i) => {
                            const maxVal = Math.max(1, ...(advanced?.top_sellers ?? []).map(x => x.total_value))
                            return (
                                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                    <span style={{ fontSize: 12, color: '#374151', fontWeight: 500, minWidth: 110, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {s.seller?.name ?? `#${s.seller_id}`}
                                    </span>
                                    <div style={{ flex: 1, background: '#f3f4f6', borderRadius: 4, height: 16, overflow: 'hidden' }}>
                                        <div style={{ height: '100%', background: 'linear-gradient(to right, #0d9488, #14b8a6)', borderRadius: 4, width: `${(s.total_value / maxVal) * 100}%`, transition: 'width 0.3s' }} />
                                    </div>
                                    <span style={{ fontSize: 11, fontWeight: 600, color: '#374151', minWidth: 80, textAlign: 'right', whiteSpace: 'nowrap' }}>
                                        {formatCurrency(s.total_value)}
                                    </span>
                                </div>
                            )
                        })}
                        {(advanced?.top_sellers?.length ?? 0) === 0 && (
                            <p style={{ textAlign: 'center', color: '#9ca3af', fontSize: 13 }}>Sem dados</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Funnel */}
            <div style={{ background: 'white', borderRadius: 12, border: '1px solid #e5e7eb', padding: 20 }}>
                <h3 style={{ fontSize: 14, fontWeight: 600, color: '#374151', marginBottom: 16, display: 'flex', alignItems: 'center', gap: 6, marginTop: 0 }}>
                    <Target size={16} /> Funil de Status
                </h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))', gap: 12 }}>
                    {funnelSteps.map(step => (
                        <div key={step.label} style={{ textAlign: 'center' }}>
                            <div style={{
                                height: `${Math.max((step.count / maxFunnel) * 80, 8)}px`,
                                background: step.color + '20',
                                borderBottom: `3px solid ${step.color}`,
                                borderRadius: '6px 6px 0 0',
                                transition: 'height 0.3s',
                                display: 'flex', alignItems: 'flex-end', justifyContent: 'center', paddingBottom: 4,
                                minHeight: 30,
                            }}>
                                <span style={{ fontSize: 18, fontWeight: 700, color: step.color }}>{step.count}</span>
                            </div>
                            <p style={{ fontSize: 11, color: '#6b7280', marginTop: 6, fontWeight: 500 }}>{step.label}</p>
                        </div>
                    ))}
                </div>
            </div>
        </>
    )
}

// ====== KANBAN VIEW ======
function KanbanView() {
    const navigate = useNavigate()
    const { hasPermission: checkPerm } = useAuthStore()
    const queryClient = useQueryClient()
    const [activeId, setActiveId] = useState<number | null>(null)
    const [dragOverColumn, setDragOverColumn] = useState<string | null>(null)

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 6 } }),
    )

    const { data: quotes = [], isLoading } = useQuery<Quote[]>({
        queryKey: queryKeys.quotes.kanban,
        queryFn: async () => {
            const res = await quoteApi.list({ per_page: 300 })
            return res.data?.data ?? res.data ?? []
        },
    })

    const invalidateAll = () => {
        queryClient.invalidateQueries({ queryKey: queryKeys.quotes.kanban })
        queryClient.invalidateQueries({ queryKey: queryKeys.quotes.all })
        queryClient.invalidateQueries({ queryKey: queryKeys.quotes.summary })
        queryClient.invalidateQueries({ queryKey: queryKeys.quotes.advancedSummary })
        broadcastQueryInvalidation(['quotes-kanban', 'quotes', 'quotes-summary', 'quotes-advanced-summary'], 'Orçamento')
    }

    const transitionMut = useMutation({
        mutationFn: async ({ id, endpoint, payload }: { id: number; endpoint: string; payload?: TransitionPayload }) => {
            const res = await quoteApi.runAction(id, endpoint, payload)
            return res.data
        },
        onSuccess: () => {
            toast.success('Status atualizado')
            invalidateAll()
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao atualizar status'))
        },
    })

    const grouped = useMemo(() =>
        KANBAN_COLUMNS.map(col => ({
            ...col,
            items: quotes.filter(q => q.status === col.key),
        })),
        [quotes],
    )

    const activeQuote = activeId ? quotes.find(q => q.id === activeId) : null

    function handleDragStart(e: DragStartEvent) {
        setActiveId(Number(e.active.id))
    }

    function handleDragEnd(e: DragEndEvent) {
        const { active, over } = e
        setActiveId(null)
        setDragOverColumn(null)

        if (!over) return

        const quoteId = Number(active.id)
        const targetCol = String(over.id)
        const quote = quotes.find(q => q.id === quoteId)
        if (!quote || quote.status === targetCol) return

        const transition = findTransition(quote.status, targetCol)
        if (!transition) {
            toast.error('Transição de status não permitida')
            return
        }

        // Verificação RBAC: usuário precisa da permissão correspondente ao status-alvo
        const requiredPerm = KANBAN_STATUS_PERMISSION[targetCol]
        if (requiredPerm && !checkPerm(requiredPerm)) {
            toast.error('Você não tem permissão para esta transição de status')
            return
        }

        transitionMut.mutate({ id: quoteId, endpoint: transition.endpoint, payload: transition.payload })
    }

    if (isLoading) {
        return (
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '50vh' }}>
                <div style={{ width: 32, height: 32, border: '3px solid #e5e7eb', borderTopColor: '#3b82f6', borderRadius: '50%', animation: 'spin 1s linear infinite' }} />
            </div>
        )
    }

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragOver={(e) => {
                const overId = e.over?.id
                if (overId && KANBAN_COLUMNS.some(c => c.key === overId)) {
                    setDragOverColumn(String(overId))
                }
            }}
        >
            <div style={{ display: 'flex', gap: 12, overflowX: 'auto', paddingBottom: 16, minHeight: 'calc(100vh - 200px)' }}>
                {grouped.map(col => (
                    <KanbanColumn
                        key={col.key}
                        col={col}
                        isDragOver={dragOverColumn === col.key}
                        allowedFromActive={activeQuote ? getAllowedTargets(activeQuote.status) : []}
                        onCardClick={(id) => navigate(`/orcamentos/${id}`)}
                    />
                ))}
            </div>

            <DragOverlay dropAnimation={null}>
                {activeQuote ? <QuoteCard quote={activeQuote} isDragging /> : null}
            </DragOverlay>

            <style>{`@keyframes spin { from { transform: rotate(0deg) } to { transform: rotate(360deg) } }`}</style>
        </DndContext>
    )
}

// ====== KANBAN COLUMN (droppable) ======
function KanbanColumn({ col, isDragOver, allowedFromActive, onCardClick }: {
    col: { key: string; label: string; color: string; items: Quote[] }
    isDragOver: boolean
    allowedFromActive: string[]
    onCardClick: (id: number) => void
}) {
    const isAllowed = allowedFromActive.includes(col.key)
    const showHighlight = isDragOver && isAllowed
    const showBlocked = isDragOver && !isAllowed && allowedFromActive.length > 0

    return (
        <div
            id={col.key}
            data-droppable="true"
            style={{
                minWidth: 260,
                maxWidth: 310,
                flex: '1 1 0',
                background: showHighlight ? '#f0fdf4' : showBlocked ? '#fef2f2' : '#f9fafb',
                borderRadius: 12,
                border: showHighlight ? '2px dashed #22c55e' : showBlocked ? '2px dashed #ef4444' : '1px solid #e5e7eb',
                transition: 'all 0.2s',
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <div style={{ padding: '14px 14px 10px', borderBottom: '2px solid ' + col.color, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: col.color }} />
                    <span style={{ fontWeight: 600, fontSize: 13, color: '#374151' }}>{col.label}</span>
                </div>
                <span style={{ fontSize: 11, fontWeight: 600, color: '#6b7280', background: '#e5e7eb', borderRadius: 10, padding: '2px 8px' }}>
                    {col.items.length}
                </span>
            </div>

            <DroppableZone colKey={col.key}>
                <div style={{ padding: 6, flex: 1, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 6, minHeight: 80 }}>
                    {col.items.map(q => (
                        <DraggableCard key={q.id} quote={q} onClick={() => onCardClick(q.id)} />
                    ))}
                    {col.items.length === 0 && (
                        <div style={{ padding: 20, textAlign: 'center', color: '#9ca3af', fontSize: 12, fontStyle: 'italic' }}>
                            Nenhum orçamento
                        </div>
                    )}
                </div>
            </DroppableZone>
        </div>
    )
}

// ====== DROPPABLE ZONE ======
function DroppableZone({ colKey, children }: { colKey: string; children: React.ReactNode }) {
    const { setNodeRef } = useDroppable({ id: colKey })
    return <div ref={setNodeRef} style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>{children}</div>
}

// ====== DRAGGABLE CARD ======
function DraggableCard({ quote, onClick }: { quote: Quote; onClick: () => void }) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: quote.id })

    const style: React.CSSProperties = {
        transform: transform ? `translate(${transform.x}px, ${transform.y}px)` : undefined,
        opacity: isDragging ? 0.4 : 1,
    }

    return (
        <div ref={setNodeRef} style={style} {...attributes}>
            <QuoteCard
                quote={quote}
                onClick={onClick}
                dragListeners={listeners}
            />
        </div>
    )
}

// ====== QUOTE CARD ======
function QuoteCard({ quote, isDragging, onClick, dragListeners }: {
    quote: Quote
    isDragging?: boolean
    onClick?: () => void
    dragListeners?: DraggableSyntheticListeners
}) {
    const isExpired = quote.valid_until && new Date(quote.valid_until) < new Date() && quote.status === 'sent'

    return (
        <div
            onClick={onClick}
            style={{
                background: 'white',
                borderRadius: 10,
                padding: 12,
                border: '1px solid #e5e7eb',
                cursor: isDragging ? 'grabbing' : 'pointer',
                boxShadow: isDragging ? '0 8px 24px rgba(0,0,0,0.15)' : '0 1px 3px rgba(0,0,0,0.04)',
                transition: 'box-shadow 0.15s',
                position: 'relative',
            }}
            onMouseEnter={e => { if (!isDragging) e.currentTarget.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)' }}
            onMouseLeave={e => { if (!isDragging) e.currentTarget.style.boxShadow = '0 1px 3px rgba(0,0,0,0.04)' }}
        >
            {/* Drag handle */}
            <div
                {...dragListeners}
                style={{
                    position: 'absolute', top: 8, right: 6, cursor: 'grab', padding: 2,
                    color: '#d1d5db', borderRadius: 4,
                }}
                onClick={e => e.stopPropagation()}
            >
                <GripVertical size={14} />
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6, paddingRight: 20 }}>
                <span style={{ fontWeight: 600, fontSize: 12, color: '#374151' }}>
                    {quote.quote_number}
                    {quote.revision > 1 && <span style={{ color: '#9ca3af', fontWeight: 400 }}> rev.{quote.revision}</span>}
                </span>
            </div>

            {quote.customer && (
                <p style={{ fontSize: 12, color: '#374151', marginBottom: 4, fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {quote.customer.name}
                </p>
            )}

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 6 }}>
                <span style={{ fontSize: 13, fontWeight: 700, color: '#059669' }}>
                    {formatCurrency(quote.total)}
                </span>
                {quote.seller && (
                    <span style={{ fontSize: 11, color: '#9ca3af' }}>
                        {quote.seller.name?.split(' ')[0]}
                    </span>
                )}
            </div>

            {quote.valid_until && (
                <p style={{ fontSize: 10, color: isExpired ? '#ef4444' : '#9ca3af', marginTop: 4, fontWeight: isExpired ? 600 : 400 }}>
                    {isExpired ? 'Vencido: ' : 'Validade: '}{new Date(quote.valid_until).toLocaleDateString('pt-BR')}
                </p>
            )}

            {quote.tags && quote.tags.length > 0 && (
                <div style={{ display: 'flex', gap: 3, marginTop: 6, flexWrap: 'wrap' }}>
                    {quote.tags.slice(0, 3).map(t => (
                        <span key={t.id} style={{
                            fontSize: 9, fontWeight: 600, padding: '1px 5px', borderRadius: 4,
                            background: `${t.color}20`, color: t.color ?? '#6b7280',
                            display: 'flex', alignItems: 'center', gap: 2,
                        }}>
                            <Tag size={8} />{t.name}
                        </span>
                    ))}
                </div>
            )}
        </div>
    )
}
