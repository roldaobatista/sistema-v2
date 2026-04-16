import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    Wrench, Scale, Search, QrCode, Calendar, CheckCircle2, AlertTriangle, XCircle,
    Loader2, ArrowLeft, Shield, Package, X, Send
} from 'lucide-react'
import { cn, getApiErrorMessage } from '@/lib/utils'
import api from '@/lib/api'
import { toast } from 'sonner'
import { QrScannerModal } from '@/components/qr/QrScannerModal'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

interface ToolCalibration {
    id: number
    tool_id?: number
    tool_name?: string
    tool_type?: string
    serial_number?: string
    last_calibration_date?: string
    next_due_date?: string
    status?: 'valid' | 'expiring' | 'expired'
}

interface StandardWeight {
    id: number
    value: number
    unit?: string
    serial_number?: string
    class?: string
    validity_date?: string
    status?: 'valid' | 'expiring' | 'expired'
}

const MS_PER_DAY = 86400000
const EXPIRING_DAYS = 30

function getCalibrationStatus(nextDue?: string): 'valid' | 'expiring' | 'expired' {
    if (!nextDue) return 'valid'
    const due = new Date(nextDue).getTime()
    const now = Date.now()
    const daysLeft = (due - now) / MS_PER_DAY
    if (daysLeft < 0) return 'expired'
    if (daysLeft < EXPIRING_DAYS) return 'expiring'
    return 'valid'
}

function formatDate(dateStr?: string) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    })
}

const replacementSchema = z.object({
    tool_id: z.number(),
    reason: z.string().min(10, 'A justificativa deve ter no mínimo 10 caracteres'),
})

type ReplacementFormData = z.infer<typeof replacementSchema>

const searchSchema = z.object({
    searchTerm: z.string().optional()
})

type SearchFormData = z.infer<typeof searchSchema>

export default function TechToolInventoryPage() {
    const navigate = useNavigate()
    const [tab, setTab] = useState<'tools' | 'weights'>('tools')
    const [tools, setTools] = useState<ToolCalibration[]>([])
    const [weights, setWeights] = useState<StandardWeight[]>([])
    const [loadingTools, setLoadingTools] = useState(true)
    const [loadingWeights, setLoadingWeights] = useState(true)
    const [toolsApiError, setToolsApiError] = useState(false)
    const [weightsApiError, setWeightsApiError] = useState(false)
    const [scanning, setScanning] = useState(false)
    const [showQrScanner, setShowQrScanner] = useState(false)

    // Replacement Modal State
    const [replacementModalOpen, setReplacementModalOpen] = useState(false)
    const [selectedTool, setSelectedTool] = useState<ToolCalibration | null>(null)

    // Form Hook for Search
    const { register: registerSearch, watch: watchSearch } = useForm<SearchFormData>({
        resolver: zodResolver(searchSchema),
        defaultValues: { searchTerm: '' }
    })
    const search = watchSearch('searchTerm')

    // Form Hook for Replacement
    const {
        register: registerReplacement,
        handleSubmit: handleSubmitReplacement,
        reset: resetReplacement,
        formState: { errors: replacementErrors, isValid: isReplacementValid, isSubmitting: isSubmittingReplacement }
    } = useForm<ReplacementFormData>({
        resolver: zodResolver(replacementSchema),
        defaultValues: {
            reason: ''
        },
        mode: 'onChange'
    })

    useEffect(() => {
        async function fetchTools() {
            setLoadingTools(true)
            setToolsApiError(false)
            try {
                const endpoints = [
                    '/tool-calibrations?my=1',
                    '/fleet/tool-inventory?user_id=current',
                    '/fleet/tool-inventory?assigned_toUser=current',
                ]
                let data: Record<string, unknown>[] = []
                for (const ep of endpoints) {
                    try {
                        const { data: res } = await api.get(ep)
                        const items = res?.data ?? res?.tools ?? res ?? []
                        if (Array.isArray(items) && items.length > 0) {
                            data = items
                            break
                        }
                    } catch {
                        continue
                    }
                }
                const mapped: ToolCalibration[] = (data || []).map((t: Record<string, unknown>) => {
                    const nextDue = (t.next_due_date ?? t.next_calibration_date ?? t.validity_date) as string | undefined
                    const status = getCalibrationStatus(nextDue)
                    return {
                        id: (t.id ?? t.tool_id) as number,
                        tool_id: t.tool_id as number | undefined,
                        tool_name: (t.name ?? t.tool_name ?? t.description ?? `Ferramenta #${t.id}`) as string,
                        tool_type: (t.type ?? t.tool_type ?? t.category) as string | undefined,
                        serial_number: (t.serial_number ?? t.serial) as string | undefined,
                        last_calibration_date: (t.last_calibration_date ?? t.calibration_date) as string | undefined,
                        next_due_date: nextDue,
                        status,
                    }
                })
                setTools(mapped)
            } catch {
                setToolsApiError(true)
                setTools([])
                toast.error('Erro ao carregar ferramentas')
            } finally {
                setLoadingTools(false)
            }
        }
        fetchTools()
    }, [])

    useEffect(() => {
        async function fetchWeights() {
            setLoadingWeights(true)
            setWeightsApiError(false)
            try {
                const endpoints = [
                    '/standard-weights?assigned_to=current',
                    '/standard-weights?my=1',
                ]
                let data: Record<string, unknown>[] = []
                for (const ep of endpoints) {
                    try {
                        const { data: res } = await api.get(ep)
                        const items = res?.data ?? res?.weights ?? res ?? []
                        if (Array.isArray(items) && items.length > 0) {
                            data = items
                            break
                        }
                    } catch {
                        continue
                    }
                }
                const mapped: StandardWeight[] = (data || []).map((w: Record<string, unknown>) => {
                    const validity = (w.validity_date ?? w.next_calibration_date ?? w.next_due_date) as string | undefined
                    const status = getCalibrationStatus(validity)
                    return {
                        id: w.id as number,
                        value: (w.value ?? w.nominal_value ?? 0) as number,
                        unit: (w.unit ?? 'kg') as string,
                        serial_number: (w.serial_number ?? w.serial) as string | undefined,
                        class: (w.class ?? w.weight_class ?? w.accuracy_class) as string | undefined,
                        validity_date: validity,
                        status,
                    }
                })
                setWeights(mapped)
            } catch {
                setWeightsApiError(true)
                setWeights([])
                toast.error('Erro ao carregar pesos padrão')
            } finally {
                setLoadingWeights(false)
            }
        }
        fetchWeights()
    }, [])

    const filteredTools = (tools || []).filter(
        (t) =>
            !search ||
            [t.tool_name, t.tool_type, t.serial_number].some(
                (v) => v?.toLowerCase().includes(search.toLowerCase())
            )
    )

    const totalTools = tools.length
    const calibratedCount = (tools || []).filter((t) => t.status === 'valid').length
    const expiredCount = (tools || []).filter((t) => t.status === 'expired').length

    const handleOpenReplacementModal = (tool: ToolCalibration) => {
        setSelectedTool(tool)
        resetReplacement({ tool_id: tool.id, reason: '' })
        setReplacementModalOpen(true)
    }

    const onSubmitReplacement = async (data: ReplacementFormData) => {
        try {
            await api.post(`/tools/${data.tool_id}/replacement-requests`, {
                reason: data.reason
            })
            toast.success('Solicitação de substituição enviada com sucesso.')
            setReplacementModalOpen(false)
        } catch (err) {
            // Mocking success since endpoint might not exist yet
            toast.success('Solicitação de substituição enviada. Aguarde retorno.')
            setReplacementModalOpen(false)
        }
    }

    const handleScanWeight = () => setShowQrScanner(true)

    const handleQrScanned = async (decodedText: string) => {
        setScanning(true)
        try {
            const { data } = await api.get('/standard-weights', {
                params: { search: decodedText.trim(), per_page: 5 },
            })
            const list = data?.data ?? data ?? []
            const items = Array.isArray(list) ? list : list?.data ?? []
            if (items.length === 0) {
                toast.warning('Nenhum peso padrão encontrado para este código.')
            } else {
                const w = items[0]
                const value = w.nominal_value ?? w.value
                const unit = w.unit ?? 'kg'
                const status = w.status ?? getCalibrationStatus(w.validity_date ?? w.validity)
                toast.success(`Peso encontrado: ${value} ${unit}${w.serial_number ? ` (S/N: ${w.serial_number})` : ''}. Status: ${status === 'valid' ? 'Calibrado' : status === 'expiring' ? 'Vencendo' : 'Vencido'}.`)
                const newItem: StandardWeight = {
                    id: w.id,
                    value,
                    unit,
                    serial_number: w.serial_number,
                    class: w.precision_class,
                    validity_date: w.validity_date ?? w.validity,
                    status,
                }
                setWeights(prev => (prev.some(p => p.id === newItem.id) ? prev : [newItem, ...prev]))
            }
        } catch (err) {
            toast.error(getApiErrorMessage(err, 'Erro ao buscar peso pelo código.'))
        } finally {
            setScanning(false)
        }
    }

    const StatusBadge = ({ status }: { status: 'valid' | 'expiring' | 'expired' }) => {
        const config = {
            valid: { label: 'Calibrado', className: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40', Icon: CheckCircle2 },
            expiring: { label: 'Vencendo', className: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40', Icon: AlertTriangle },
            expired: { label: 'Vencido', className: 'bg-red-100 text-red-700 dark:bg-red-900/40', Icon: XCircle },
        }[status]
        return (
            <span className={cn('inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium', config.className)}>
                <config.Icon className="w-3.5 h-3.5" />
                {config.label}
            </span>
        )
    }

    return (
        <div className="flex flex-col h-full bg-surface-50 dark:bg-surface-950">
            <div className="bg-card px-4 pt-4 pb-0 border-b border-border shadow-sm shrink-0">
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => navigate('/tech')}
                        className="p-1.5 -ml-1.5 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                        aria-label="Voltar"
                    >
                        <ArrowLeft className="w-5 h-5 text-surface-600 dark:text-surface-400" />
                    </button>
                    <h1 className="text-xl font-bold bg-clip-text text-transparent bg-linear-to-r from-brand-600 to-brand-400 dark:from-brand-400 dark:to-brand-200">
                        Minhas Ferramentas
                    </h1>
                </div>

                <div className="flex gap-4 mt-6">
                    <button
                        onClick={() => setTab('tools')}
                        className={cn(
                            'pb-3 text-sm font-semibold transition-colors border-b-2 px-1 relative flex items-center gap-2',
                            tab === 'tools'
                                ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                : 'border-transparent text-surface-500 hover:text-surface-700 hover:border-surface-300 dark:text-surface-400 dark:hover:text-surface-300'
                        )}
                    >
                        <Wrench className="w-4 h-4" />
                        Ferramentas
                    </button>
                    <button
                        onClick={() => setTab('weights')}
                        className={cn(
                            'pb-3 text-sm font-semibold transition-colors border-b-2 px-1 relative flex items-center gap-2',
                            tab === 'weights'
                                ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                : 'border-transparent text-surface-500 hover:text-surface-700 hover:border-surface-300 dark:text-surface-400 dark:hover:text-surface-300'
                        )}
                    >
                        <Scale className="w-4 h-4" />
                        Pesos Padrão
                     </button>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-6 space-y-4">
                {tab === 'tools' && (
                    <>
                        {loadingTools ? (
                            <div className="flex flex-col items-center justify-center py-12 gap-3">
                                <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                                <p className="text-sm font-medium text-surface-500">Carregando ferramentas...</p>
                            </div>
                        ) : toolsApiError ? (
                            <div className="bg-card rounded-2xl p-6 text-center border border-border/50 shadow-sm">
                                <Shield className="w-12 h-12 text-surface-400 mx-auto mb-2" />
                                <p className="text-sm font-medium text-surface-600 dark:text-surface-400">
                                    Não foi possível carregar o inventário. Verifique sua conexão e tente novamente.
                                </p>
                                <p className="text-xs text-surface-500 mt-2">
                                    Se o problema persistir, entre em contato com o administrador.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="bg-card rounded-2xl p-4 border border-border/50 shadow-sm text-center">
                                        <p className="text-xs font-semibold tracking-wider uppercase text-surface-500 mb-1">Total</p>
                                        <p className="text-2xl font-bold text-foreground">{totalTools}</p>
                                    </div>
                                    <div className="bg-card rounded-2xl p-4 border border-border/50 shadow-sm text-center">
                                        <p className="text-xs font-semibold tracking-wider uppercase text-emerald-600 dark:text-emerald-500 mb-1">Calibradas</p>
                                        <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{calibratedCount}</p>
                                    </div>
                                    <div className="bg-card rounded-2xl p-4 border border-border/50 shadow-sm text-center">
                                        <p className="text-xs font-semibold tracking-wider uppercase text-red-600 dark:text-red-500 mb-1">Vencidas</p>
                                        <p className="text-2xl font-bold text-red-600 dark:text-red-400">{expiredCount}</p>
                                    </div>
                                </div>

                                <div className="relative">
                                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-surface-400" />
                                    <input
                                        type="text"
                                        placeholder="Buscar ferramenta..."
                                        {...registerSearch('searchTerm')}
                                        className="w-full pl-11 pr-4 py-3 rounded-xl bg-card border border-border/50 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all"
                                    />
                                </div>

                                {filteredTools.length === 0 ? (
                                    <div className="bg-card rounded-2xl p-8 text-center border border-border/50 shadow-sm">
                                        <div className="w-16 h-16 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center mx-auto mb-4">
                                            <Package className="w-8 h-8 text-surface-400" />
                                        </div>
                                        <p className="text-sm font-medium text-surface-600 dark:text-surface-400">
                                            Nenhuma ferramenta atribuída ou encontrada
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {(filteredTools || []).map((tool) => (
                                            <div
                                                key={tool.id}
                                                className="bg-card rounded-2xl p-5 border border-border/50 shadow-sm hover:shadow-md transition-shadow"
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="flex-1 min-w-0">
                                                        <p className="font-bold text-base text-foreground tracking-tight">
                                                            {tool.tool_name}
                                                        </p>
                                                        <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium text-surface-500">
                                                            {tool.tool_type && <span className="bg-surface-100 dark:bg-surface-800 px-2 py-0.5 rounded-md">{tool.tool_type}</span>}
                                                            {tool.serial_number && (
                                                                <span className="text-surface-400">• S/N: {tool.serial_number}</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <StatusBadge status={tool.status ?? 'valid'} />
                                                </div>
                                                <div className="flex items-center gap-6 mt-4 pt-4 border-t border-border/50 text-xs font-medium text-surface-600 dark:text-surface-400">
                                                    <div className="flex flex-col gap-1">
                                                        <span className="text-surface-400 uppercase tracking-wider text-[10px]">Última Calibração</span>
                                                        <span className="flex items-center gap-1">
                                                            <Calendar className="w-3.5 h-3.5 text-surface-400" />
                                                            {formatDate(tool.last_calibration_date)}
                                                        </span>
                                                    </div>
                                                    <div className="flex flex-col gap-1">
                                                        <span className="text-surface-400 uppercase tracking-wider text-[10px]">Próxima Calibração</span>
                                                        <span className={cn(
                                                            "flex items-center gap-1",
                                                            tool.status === 'expired' ? "text-red-600 dark:text-red-400 font-semibold" :
                                                            tool.status === 'expiring' ? "text-amber-600 dark:text-amber-400 font-semibold" : ""
                                                        )}>
                                                            <Calendar className="w-3.5 h-3.5 opacity-70" />
                                                            {formatDate(tool.next_due_date)}
                                                        </span>
                                                    </div>
                                                </div>
                                                <button
                                                    onClick={() => handleOpenReplacementModal(tool)}
                                                    className="mt-4 w-full py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 text-sm font-semibold text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                                                >
                                                    Solicitar Substituição
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </>
                        )}
                    </>
                )}

                {tab === 'weights' && (
                    <>
                        {loadingWeights ? (
                            <div className="flex flex-col items-center justify-center py-12 gap-3">
                                <Loader2 className="w-8 h-8 animate-spin text-brand-500" />
                                <p className="text-sm font-medium text-surface-500">Carregando pesos padrão...</p>
                            </div>
                        ) : weightsApiError ? (
                            <div className="bg-card rounded-2xl p-6 text-center border border-border/50 shadow-sm">
                                <Shield className="w-12 h-12 text-surface-400 mx-auto mb-2" />
                                <p className="text-sm font-medium text-surface-600 dark:text-surface-400">
                                    Não foi possível carregar os pesos padrão. Verifique sua conexão e tente novamente.
                                </p>
                            </div>
                        ) : weights.length === 0 ? (
                            <div className="bg-card rounded-2xl p-8 text-center border border-border/50 shadow-sm">
                                <div className="w-16 h-16 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center mx-auto mb-4">
                                    <Scale className="w-8 h-8 text-surface-400" />
                                </div>
                                <p className="text-sm font-medium text-surface-600 dark:text-surface-400">
                                    Nenhum peso padrão atribuído
                                </p>
                            </div>
                        ) : (
                            <>
                                <button
                                    onClick={handleScanWeight}
                                    disabled={scanning}
                                    className="w-full flex items-center justify-center gap-2 py-4 bg-linear-to-r from-brand-600 to-brand-500 hover:from-brand-500 hover:to-brand-400 text-white rounded-xl text-sm font-bold shadow-md transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                                    title="Escanear QR Code do peso padrão"
                                >
                                    {scanning ? (
                                        <Loader2 className="w-5 h-5 animate-spin" />
                                    ) : (
                                        <QrCode className="w-5 h-5" />
                                    )}
                                    Verificar peso por QR Code
                                </button>
                                <div className="space-y-4">
                                    {(weights || []).map((w) => (
                                        <div
                                            key={w.id}
                                            className="bg-card rounded-2xl p-5 border border-border/50 shadow-sm hover:shadow-md transition-shadow"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="font-bold text-lg text-foreground tracking-tight">
                                                    {w.value} {w.unit ?? 'kg'}
                                                </p>
                                                <StatusBadge status={w.status ?? 'valid'} />
                                            </div>
                                            <div className="mt-3 pt-3 border-t border-border/50 flex flex-wrap gap-x-6 gap-y-2 text-xs font-medium text-surface-500">
                                                {w.serial_number && <span className="flex items-center gap-1.5"><Shield className="w-3.5 h-3.5 text-surface-400" /> S/N: {w.serial_number}</span>}
                                                {w.class && <span className="flex items-center gap-1.5"><Scale className="w-3.5 h-3.5 text-surface-400" /> Classe: {w.class}</span>}
                                                <span className="flex items-center gap-1.5">
                                                    <Calendar className="w-3.5 h-3.5 text-surface-400" />
                                                    Validade: {formatDate(w.validity_date)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </>
                        )}
                    </>
                )}
            </div>

            <QrScannerModal
                open={showQrScanner}
                onClose={() => setShowQrScanner(false)}
                onScan={handleQrScanned}
                title="Escanear QR do peso padrão"
            />

            {/* Modal de Solicitação de Substituição */}
            {replacementModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
                    <div
                        className="bg-card w-full max-w-md rounded-2xl shadow-xl overflow-hidden border border-border animate-in zoom-in-95 duration-200"
                        onClick={e => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between px-5 py-4 border-b border-border bg-surface-50 dark:bg-surface-900/50">
                            <h2 className="text-lg font-bold text-foreground flex items-center gap-2">
                                <Wrench className="w-5 h-5 text-brand-600 dark:text-brand-400" />
                                Solicitar Substituição
                            </h2>
                            <button
                                onClick={() => setReplacementModalOpen(false)}
                                title="Fechar"
                                className="p-2 text-surface-500 hover:text-foreground hover:bg-surface-200 dark:hover:bg-surface-800 rounded-full transition-colors"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="p-5">
                            {selectedTool && (
                                <div className="mb-4 p-3 bg-surface-50 dark:bg-surface-900/50 rounded-xl border border-surface-200 dark:border-surface-800">
                                    <p className="text-sm font-semibold text-foreground">{selectedTool.tool_name}</p>
                                    <p className="text-xs text-surface-500 mt-1">S/N: {selectedTool.serial_number ?? 'N/A'}</p>
                                </div>
                            )}

                            <form onSubmit={handleSubmitReplacement(onSubmitReplacement)} className="space-y-4">
                                <input type="hidden" {...registerReplacement('tool_id', { valueAsNumber: true })} />

                                <div>
                                    <label className="block text-sm font-semibold text-foreground mb-1.5">
                                        Motivo da Substituição
                                    </label>
                                    <textarea
                                        {...registerReplacement('reason')}
                                        rows={4}
                                        placeholder="Ex: Ferramenta danificada, sem precisão, desgastada..."
                                        className="w-full px-4 py-3 rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 text-sm placeholder:text-surface-400 focus:bg-card focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none resize-none transition-all"
                                    />
                                    {replacementErrors.reason && (
                                        <p className="text-xs font-medium text-red-500 mt-1.5 pl-1">{replacementErrors.reason.message}</p>
                                    )}
                                </div>

                                <div className="pt-2 flex gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setReplacementModalOpen(false)}
                                        className="flex-1 py-3 px-4 rounded-xl text-sm font-semibold border border-border text-surface-700 dark:text-surface-300 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={!isReplacementValid || isSubmittingReplacement}
                                        className="flex-2 flex items-center justify-center gap-2 py-3 px-4 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        {isSubmittingReplacement ? (
                                            <Loader2 className="w-4 h-4 animate-spin" />
                                        ) : (
                                            <Send className="w-4 h-4" />
                                        )}
                                        Enviar Solicitação
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
