import { useRef, useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Loader2, CheckCircle2, RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { offlinePost } from '@/lib/syncEngine'
import { useOfflineStore } from '@/hooks/useOfflineStore'
import { generateUlid, type OfflineWorkOrder } from '@/lib/offlineDb'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { isPrivilegedFieldRole, isTechnicianLinkedToWorkOrder } from '@/lib/work-order-detail-utils'
import { z } from 'zod'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

const signatureSchema = z.object({
    signerName: z.string().min(3, 'Nome deve ter pelo menos 3 caracteres').max(100, 'Nome muito longo'),
})

type SignatureFormData = z.infer<typeof signatureSchema>

export default function TechSignaturePage() {
    const { id: woId } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const canvasRef = useRef<HTMLCanvasElement>(null)
    const { put: putSignature } = useOfflineStore('signatures')
    const { getById: getWorkOrderById } = useOfflineStore('work-orders')
    const { user, hasPermission } = useAuthStore()
    const [isDrawing, setIsDrawing] = useState(false)
    const [hasStrokes, setHasStrokes] = useState(false)
    const [saving, setSaving] = useState(false)
    const [saved, setSaved] = useState(false)
    const [workOrder, setWorkOrder] = useState<OfflineWorkOrder | null>(null)

    const {
        register,
        handleSubmit,
        formState: { errors, isValid },
    } = useForm<SignatureFormData>({
        resolver: zodResolver(signatureSchema),
        defaultValues: {
            signerName: '',
        },
        mode: 'onChange',
    })

    useEffect(() => {
        if (!woId) return

        getWorkOrderById(Number(woId))
            .then((loadedWorkOrder) => setWorkOrder(loadedWorkOrder ?? null))
            .catch(() => setWorkOrder(null))
    }, [getWorkOrderById, woId])

    const fieldRoles = Array.isArray(user?.roles)
        ? user.roles
        : (Array.isArray(user?.all_roles) ? user.all_roles : [])
    const isAdminFieldRole = isPrivilegedFieldRole(fieldRoles)
    const canSaveSignature = !!user
        && hasPermission('os.work_order.update')
        && !!workOrder
        && isTechnicianLinkedToWorkOrder(workOrder, user.id, isAdminFieldRole)

    // Setup canvas
    useEffect(() => {
        const canvas = canvasRef.current
        if (!canvas) return
        const ctx = canvas.getContext('2d')
        if (!ctx) return

        // Retina support
        const rect = canvas.getBoundingClientRect()
        const dpr = window.devicePixelRatio || 1
        canvas.width = rect.width * dpr
        canvas.height = rect.height * dpr
        ctx.scale(dpr, dpr)
        ctx.lineCap = 'round'
        ctx.lineJoin = 'round'
        ctx.lineWidth = 2.5
        ctx.strokeStyle = '#1e293b'

        // Fill white background
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, rect.width, rect.height)
    }, [])

    const getPos = (e: React.TouchEvent | React.MouseEvent) => {
        const canvas = canvasRef.current
        if (!canvas) return { x: 0, y: 0 }
        const rect = canvas.getBoundingClientRect()
        if ('touches' in e) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top,
            }
        }
        return {
            x: (e as React.MouseEvent).clientX - rect.left,
            y: (e as React.MouseEvent).clientY - rect.top,
        }
    }

    const startDraw = (e: React.TouchEvent | React.MouseEvent) => {
        const ctx = canvasRef.current?.getContext('2d')
        if (!ctx) return
        const pos = getPos(e)
        ctx.beginPath()
        ctx.moveTo(pos.x, pos.y)
        setIsDrawing(true)
        setHasStrokes(true)
    }

    const draw = (e: React.TouchEvent | React.MouseEvent) => {
        if (!isDrawing) return
        const ctx = canvasRef.current?.getContext('2d')
        if (!ctx) return
        const pos = getPos(e)
        ctx.lineTo(pos.x, pos.y)
        ctx.stroke()
    }

    const endDraw = () => setIsDrawing(false)

    const clearCanvas = () => {
        const canvas = canvasRef.current
        if (!canvas) return
        const ctx = canvas.getContext('2d')
        if (!ctx) return
        const rect = canvas.getBoundingClientRect()
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, rect.width, rect.height)
        setHasStrokes(false)
        setSaved(false)
    }

    const onSubmit = async (data: SignatureFormData) => {
        const canvas = canvasRef.current
        if (!canvas || !woId) return

        if (!hasStrokes) {
            toast.error('Informe a assinatura antes de salvar.')
            return
        }

        if (!canSaveSignature) {
            toast.error('Você não pode salvar assinatura nesta OS.')
            return
        }

        setSaving(true)

        try {
            // Get PNG base64
            const dataUrl = canvas.toDataURL('image/png')
            const base64 = dataUrl.split(',')[1]

            const signatureData = {
                id: generateUlid(),
                work_order_id: Number(woId),
                signer_name: data.signerName.trim(),
                png_base64: base64,
                captured_at: new Date().toISOString(),
                synced: false,
            }

            const queued = await offlinePost('/tech/sync/batch', {
                mutations: [{
                    type: 'signature',
                    data: signatureData,
                }],
            })

            await putSignature({
                ...signatureData,
                synced: !queued,
            })

            setSaved(true)
            toast.success(queued ? 'Assinatura salva offline para sincronizar depois' : 'Assinatura sincronizada com sucesso')
        } catch {
            toast.error('Não foi possível salvar a assinatura')
        } finally {
            setSaving(false)
        }
    }

    return (
        <div className="flex flex-col h-full bg-background">
            {/* Header */}
            <div className="px-4 pt-3 pb-4 border-b border-border">
                <button onClick={() => navigate(`/tech/os/${woId}`)} className="flex items-center gap-1 text-sm text-brand-600 mb-2">
                    <ArrowLeft className="w-4 h-4" /> Voltar
                </button>
                <h1 className="text-lg font-bold text-foreground">Assinatura do Cliente</h1>
            </div>

            <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col h-full">
                {/* Signer name */}
                <div className="px-4 py-3">
                    <label className="text-xs text-surface-500 font-medium mb-1.5 block">Nome do assinante</label>
                    <input
                        type="text"
                        {...register('signerName')}
                        placeholder="Nome completo"
                        aria-label="Nome do assinante"
                        className={cn(
                            "w-full px-3 py-2.5 rounded-lg bg-surface-100 border text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none",
                            errors.signerName ? "border-red-500 placeholder:text-red-300" : "border-transparent placeholder:text-surface-400"
                        )}
                    />
                    {errors.signerName && (
                        <p className="mt-1 text-xs text-red-500">{errors.signerName.message}</p>
                    )}
                </div>

                {/* Canvas */}
                <div className="flex-1 px-4 pb-4">
                    <div className="relative h-full rounded-xl border-2 border-dashed border-surface-300 overflow-hidden">
                        <canvas
                            ref={canvasRef}
                            className="w-full h-full touch-none cursor-crosshair"
                            onMouseDown={startDraw}
                            onMouseMove={draw}
                            onMouseUp={endDraw}
                            onMouseLeave={endDraw}
                            onTouchStart={startDraw}
                            onTouchMove={draw}
                            onTouchEnd={endDraw}
                        />

                        {!hasStrokes && (
                            <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <p className="text-sm text-surface-400">Assine aqui</p>
                            </div>
                        )}

                        {/* Clear button */}
                        {hasStrokes && (
                            <button
                                type="button"
                                onClick={clearCanvas}
                                aria-label="Limpar assinatura"
                                className="absolute top-3 right-3 w-8 h-8 rounded-full bg-surface-100 flex items-center justify-center text-surface-500 shadow-sm"
                            >
                                <RotateCcw className="w-4 h-4" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Save */}
                <div className="p-4 border-t border-border safe-area-bottom pb-8">
                    {!canSaveSignature && (
                        <p className="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Edição bloqueada: sua conta não possui permissão ou vínculo técnico com esta OS.
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={saving || !hasStrokes || !isValid || !canSaveSignature}
                        className={cn(
                            'w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-colors',
                            saved
                                ? 'bg-emerald-600'
                                : hasStrokes && isValid
                                    ? 'bg-brand-600 active:bg-brand-700'
                                    : 'bg-surface-300',
                            saving && 'opacity-70',
                        )}
                    >
                        {saving ? (
                            <Loader2 className="w-4 h-4 animate-spin" />
                        ) : saved ? (
                            <><CheckCircle2 className="w-4 h-4" /> Assinatura Salva</>
                        ) : (
                            'Salvar Assinatura'
                        )}
                    </button>
                </div>
            </form>
        </div>
    )
}
