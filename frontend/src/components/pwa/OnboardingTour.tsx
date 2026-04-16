import { useState } from 'react'
import {
    Download, WifiOff, RefreshCw, Smartphone,
    BarChart3, Zap, Command, X, ArrowRight, ArrowLeft, Check,
} from 'lucide-react'
import { type AppMode } from '@/hooks/useAppMode'
import { cn } from '@/lib/utils'

const ONBOARDING_KEY = 'kalibrium-onboarding-done'

interface Step {
    icon: typeof Download
    title: string
    description: string
    gradient: string
}

const STEPS_BY_MODE: Record<AppMode, Step[]> = {
    tecnico: [
        {
            icon: Download,
            title: 'Instale o App',
            description: 'Toque em "Adicionar à tela inicial" no seu navegador para usar como app nativo, com ícone na tela.',
            gradient: 'from-blue-500 to-blue-600',
        },
        {
            icon: WifiOff,
            title: 'Funciona Offline',
            description: 'Suas OS, checklists e fotos são salvos no celular. Trabalhe mesmo sem internet — tudo sincroniza quando voltar online.',
            gradient: 'from-emerald-500 to-emerald-600',
        },
        {
            icon: RefreshCw,
            title: 'Sincronização Automática',
            description: 'Quando sua conexão voltar, os dados pendentes são enviados automaticamente. Veja o status no topo da tela.',
            gradient: 'from-amber-500 to-orange-500',
        },
        {
            icon: Smartphone,
            title: 'Câmera & QR Code',
            description: 'Use a câmera para fotos antes/depois, escanear QR de peças e coletar assinaturas digitais.',
            gradient: 'from-cyan-500 to-teal-600',
        },
    ],
    vendedor: [
        {
            icon: BarChart3,
            title: 'Pipeline no Celular',
            description: 'Acompanhe seus deals, mova cards no pipeline e veja forecast em tempo real, de qualquer lugar.',
            gradient: 'from-cyan-500 to-teal-600',
        },
        {
            icon: Zap,
            title: 'Orçamentos Rápidos',
            description: 'Crie e envie orçamentos direto do celular. Seus clientes recebem na hora.',
            gradient: 'from-amber-500 to-orange-500',
        },
        {
            icon: Smartphone,
            title: 'Instale o App',
            description: 'Adicione à tela inicial para acesso rápido ao CRM com um toque.',
            gradient: 'from-blue-500 to-blue-600',
        },
    ],
    gestao: [
        {
            icon: BarChart3,
            title: 'Dashboard Personalizado',
            description: 'Acompanhe KPIs de OS, financeiro e equipe em tempo real. Tudo atualizado automaticamente.',
            gradient: 'from-blue-500 to-blue-600',
        },
        {
            icon: Command,
            title: 'Atalhos Rápidos',
            description: 'Pressione Ctrl+K (ou Cmd+K) para abrir a busca rápida e navegar para qualquer página instantaneamente.',
            gradient: 'from-emerald-500 to-emerald-600',
        },
        {
            icon: Zap,
            title: 'Favoritos na Sidebar',
            description: 'Clique na estrela ao lado de qualquer item do menu para fixá-lo no topo da sidebar.',
            gradient: 'from-amber-500 to-orange-500',
        },
    ],
}

interface OnboardingTourProps {
    mode: AppMode
    onComplete: () => void
}

export function OnboardingTour({ mode, onComplete }: OnboardingTourProps) {
    const [currentStep, setCurrentStep] = useState(0)
    const steps = STEPS_BY_MODE[mode]
    const step = steps[currentStep]
    const Icon = step.icon
    const isLast = currentStep === steps.length - 1

    const handleNext = () => {
        if (isLast) {
            markOnboardingDone(mode)
            onComplete()
        } else {
            setCurrentStep(prev => prev + 1)
        }
    }

    const handleSkip = () => {
        markOnboardingDone(mode)
        onComplete()
    }

    return (
        <div className="fixed inset-0 z-[99] flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div className="relative w-full max-w-md mx-4 bg-card rounded-2xl shadow-2xl border border-border overflow-hidden animate-in fade-in zoom-in-95 duration-300">
                <button
                    onClick={handleSkip}
                    className="absolute top-4 right-4 z-10 p-1.5 rounded-lg text-surface-400 hover:text-surface-600 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    aria-label="Pular tour"
                >
                    <X className="w-5 h-5" />
                </button>

                <div className={cn('p-8 flex justify-center bg-gradient-to-br', step.gradient)}>
                    <div className="w-20 h-20 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-sm">
                        <Icon className="w-10 h-10 text-white" />
                    </div>
                </div>

                <div className="px-6 pt-6 pb-4 text-center">
                    <h3 className="text-xl font-bold text-foreground">{step.title}</h3>
                    <p className="mt-3 text-sm text-muted-foreground leading-relaxed">
                        {step.description}
                    </p>
                </div>

                <div className="flex items-center justify-center gap-1.5 py-3">
                    {(steps || []).map((_, i) => (
                        <div
                            key={i}
                            className={cn(
                                'h-1.5 rounded-full transition-all duration-300',
                                i === currentStep ? 'w-6 bg-brand-600' : 'w-1.5 bg-surface-300 dark:bg-surface-600'
                            )}
                        />
                    ))}
                </div>

                <div className="flex items-center justify-between px-6 pb-6">
                    <button
                        onClick={() => setCurrentStep(prev => Math.max(0, prev - 1))}
                        disabled={currentStep === 0}
                        className={cn(
                            'flex items-center gap-1.5 text-sm font-medium transition-colors',
                            currentStep === 0 ? 'text-surface-300 cursor-not-allowed' : 'text-surface-500 hover:text-surface-700'
                        )}
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Anterior
                    </button>

                    <button
                        onClick={handleNext}
                        className="flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors"
                    >
                        {isLast ? (
                            <>
                                Começar
                                <Check className="w-4 h-4" />
                            </>
                        ) : (
                            <>
                                Próximo
                                <ArrowRight className="w-4 h-4" />
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>
    )
}

function markOnboardingDone(mode: AppMode) {
    try {
        const done = JSON.parse(localStorage.getItem(ONBOARDING_KEY) ?? '{}')
        done[mode] = true
        localStorage.setItem(ONBOARDING_KEY, JSON.stringify(done))
    } catch { /* ignore */ }
}

export function shouldShowOnboarding(mode: AppMode): boolean {
    try {
        const done = JSON.parse(localStorage.getItem(ONBOARDING_KEY) ?? '{}')
        return !done[mode]
    } catch {
        return true
    }
}
