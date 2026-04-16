import { useState, useEffect, useCallback, useRef } from 'react'
import { Monitor, Lock, Play, Pause, SkipForward, Settings, X } from 'lucide-react'
import { cn } from '@/lib/utils'

const KIOSK_PIN = '1234'
const KIOSK_KEY = 'kalibrium-kiosk-active'

interface KioskPanel {
    id: string
    label: string
    component: React.ReactNode
}

interface TvKioskModeProps {
    panels: KioskPanel[]
    rotationInterval?: number
}

export function TvKioskMode({ panels, rotationInterval = 30 }: TvKioskModeProps) {
    const [isActive, setIsActive] = useState(false)
    const [isPaused, setIsPaused] = useState(false)
    const [currentIndex, setCurrentIndex] = useState(0)
    const [showUnlockDialog, setShowUnlockDialog] = useState(false)
    const [pinInput, setPinInput] = useState('')
    const [pinError, setPinError] = useState(false)
    const [countdown, setCountdown] = useState(rotationInterval)
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

    const nextPanel = useCallback(() => {
        setCurrentIndex(prev => (prev + 1) % panels.length)
        setCountdown(rotationInterval)
    }, [panels.length, rotationInterval])

    useEffect(() => {
        if (!isActive || isPaused || panels.length <= 1) return
        intervalRef.current = setInterval(() => {
            setCountdown(prev => {
                if (prev <= 1) {
                    nextPanel()
                    return rotationInterval
                }
                return prev - 1
            })
        }, 1000)
        return () => { if (intervalRef.current) clearInterval(intervalRef.current) }
    }, [isActive, isPaused, nextPanel, rotationInterval, panels.length])

    const handleUnlock = () => {
        if (pinInput === KIOSK_PIN) {
            setIsActive(false)
            setShowUnlockDialog(false)
            setPinInput('')
            setPinError(false)
            try { sessionStorage.removeItem(KIOSK_KEY) } catch { /* ignore */ }
        } else {
            setPinError(true)
            setPinInput('')
        }
    }

    const activateKiosk = () => {
        setIsActive(true)
        setCurrentIndex(0)
        setCountdown(rotationInterval)
        setIsPaused(false)
        try { sessionStorage.setItem(KIOSK_KEY, 'true') } catch { /* ignore */ }
    }

    if (!isActive) {
        return (
            <button
                onClick={activateKiosk}
                className="flex items-center gap-2 rounded-lg bg-surface-100 dark:bg-surface-800 px-3 py-2 text-sm font-medium text-surface-600 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors"
                title="Ativar modo apresentação"
            >
                <Monitor className="w-4 h-4" />
                Modo Kiosk
            </button>
        )
    }

    const currentPanel = panels[currentIndex]

    return (
        <div className="fixed inset-0 z-[200] bg-background">
            {currentPanel?.component}

            {/* Controls overlay - appears on mouse move */}
            <KioskOverlay
                isPaused={isPaused}
                countdown={countdown}
                currentIndex={currentIndex}
                totalPanels={panels.length}
                panelLabel={currentPanel?.label ?? ''}
                onTogglePause={() => setIsPaused(!isPaused)}
                onNext={nextPanel}
                onRequestUnlock={() => setShowUnlockDialog(true)}
            />

            {showUnlockDialog && (
                <div className="fixed inset-0 z-[210] flex items-center justify-center bg-black/70 backdrop-blur-sm">
                    <div className="bg-card rounded-2xl p-6 w-80 shadow-2xl border border-border animate-in zoom-in-95">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg font-semibold text-foreground flex items-center gap-2">
                                <Lock className="w-5 h-5" />
                                Desbloquear
                            </h3>
                            <button
                                onClick={() => { setShowUnlockDialog(false); setPinInput(''); setPinError(false) }}
                                className="p-1 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800"
                                aria-label="Fechar"
                            >
                                <X className="w-5 h-5 text-surface-400" />
                            </button>
                        </div>
                        <p className="text-sm text-muted-foreground mb-4">
                            Digite o PIN para sair do modo kiosk.
                        </p>
                        <input
                            type="password"
                            maxLength={6}
                            value={pinInput}
                            onChange={(e) => { setPinInput(e.target.value); setPinError(false) }}
                            onKeyDown={(e) => e.key === 'Enter' && handleUnlock()}
                            className={cn(
                                'w-full rounded-xl border px-4 py-3 text-center text-2xl tracking-[0.5em] font-mono',
                                'bg-surface-50 dark:bg-surface-800 focus:outline-none focus:ring-2 focus:ring-brand-500',
                                pinError ? 'border-red-400 ring-red-400' : 'border-border'
                            )}
                            placeholder="····"
                            autoFocus
                        />
                        {pinError && (
                            <p className="mt-2 text-xs text-red-500 text-center">PIN incorreto</p>
                        )}
                        <button
                            onClick={handleUnlock}
                            className="mt-4 w-full rounded-xl bg-brand-600 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors"
                        >
                            Desbloquear
                        </button>
                    </div>
                </div>
            )}
        </div>
    )
}

function KioskOverlay({
    isPaused, countdown, currentIndex, totalPanels, panelLabel,
    onTogglePause, onNext, onRequestUnlock,
}: {
    isPaused: boolean
    countdown: number
    currentIndex: number
    totalPanels: number
    panelLabel: string
    onTogglePause: () => void
    onNext: () => void
    onRequestUnlock: () => void
}) {
    const [visible, setVisible] = useState(false)
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    useEffect(() => {
        const handleMove = () => {
            setVisible(true)
            if (timerRef.current) clearTimeout(timerRef.current)
            timerRef.current = setTimeout(() => setVisible(false), 3000)
        }
        window.addEventListener('mousemove', handleMove)
        window.addEventListener('touchstart', handleMove)
        return () => {
            window.removeEventListener('mousemove', handleMove)
            window.removeEventListener('touchstart', handleMove)
            if (timerRef.current) clearTimeout(timerRef.current)
        }
    }, [])

    return (
        <div className={cn(
            'fixed bottom-0 inset-x-0 z-[205] transition-all duration-500',
            visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-full pointer-events-none'
        )}>
            <div className="mx-auto max-w-lg mb-6 px-4">
                <div className="flex items-center gap-3 bg-black/80 backdrop-blur-md text-white rounded-2xl px-4 py-3 shadow-2xl">
                    <span className="text-xs font-medium text-white/60 flex-1 truncate">
                        {panelLabel} ({currentIndex + 1}/{totalPanels})
                    </span>

                    {totalPanels > 1 && (
                        <>
                            <button onClick={onTogglePause} className="p-1.5 rounded-lg hover:bg-white/10 transition-colors" aria-label={isPaused ? 'Reproduzir' : 'Pausar'}>
                                {isPaused ? <Play className="w-4 h-4" /> : <Pause className="w-4 h-4" />}
                            </button>
                            <button onClick={onNext} className="p-1.5 rounded-lg hover:bg-white/10 transition-colors" aria-label="Próximo painel">
                                <SkipForward className="w-4 h-4" />
                            </button>
                            {!isPaused && (
                                <span className="text-xs font-mono text-white/40 tabular-nums w-6 text-right">
                                    {countdown}s
                                </span>
                            )}
                        </>
                    )}

                    <div className="w-px h-5 bg-white/20" />

                    <button onClick={onRequestUnlock} className="p-1.5 rounded-lg hover:bg-white/10 transition-colors" aria-label="Configurações">
                        <Settings className="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    )
}
