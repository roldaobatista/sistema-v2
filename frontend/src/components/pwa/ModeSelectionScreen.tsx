import { useState } from 'react'
import { LayoutDashboard, Wrench, Briefcase, ArrowRight, Check } from 'lucide-react'
import { type AppMode } from '@/hooks/useAppMode'
import { cn } from '@/lib/utils'

const MODE_SELECTION_KEY = 'kalibrium-mode-selected'
const MODE_STORAGE_KEY = 'kalibrium-mode'

interface ModeOption {
    mode: AppMode
    title: string
    subtitle: string
    description: string
    icon: typeof LayoutDashboard
    gradient: string
    features: string[]
}

const MODE_OPTIONS: ModeOption[] = [
    {
        mode: 'gestao',
        title: 'Gestão',
        subtitle: 'Painel Administrativo',
        description: 'Visão completa do negócio com dashboards, financeiro, relatórios e configurações.',
        icon: LayoutDashboard,
        gradient: 'from-blue-600 to-emerald-700',
        features: ['Dashboard & KPIs', 'Financeiro', 'Relatórios', 'Configurações'],
    },
    {
        mode: 'tecnico',
        title: 'Técnico',
        subtitle: 'App de Campo',
        description: 'Otimizado para trabalho em campo com suporte offline, GPS e câmera.',
        icon: Wrench,
        gradient: 'from-emerald-600 to-teal-700',
        features: ['Offline-first', 'OS no celular', 'Fotos & QR', 'Ponto & Rotas'],
    },
    {
        mode: 'vendedor',
        title: 'Vendedor',
        subtitle: 'CRM & Comercial',
        description: 'Pipeline de vendas, orçamentos, leads e gestão de clientes.',
        icon: Briefcase,
        gradient: 'from-cyan-600 to-teal-700',
        features: ['Pipeline CRM', 'Orçamentos', 'Leads & Clientes', 'Metas & Forecast'],
    },
]

interface ModeSelectionScreenProps {
    userName: string
    availableModes: AppMode[]
    onSelect: (mode: AppMode) => void
}

export function ModeSelectionScreen({ userName, availableModes, onSelect }: ModeSelectionScreenProps) {
    const [selected, setSelected] = useState<AppMode | null>(null)
    const [remember, setRemember] = useState(false)

    const filteredOptions = (MODE_OPTIONS || []).filter(opt => availableModes.includes(opt.mode))
    const firstName = userName.split(' ')[0]

    const handleConfirm = () => {
        if (!selected) return
        if (remember) {
            try {
                localStorage.setItem(MODE_SELECTION_KEY, 'remembered')
                localStorage.setItem(MODE_STORAGE_KEY, selected)
            } catch { /* ignore */ }
        }
        onSelect(selected)
    }

    return (
        <div className="fixed inset-0 z-[100] overflow-y-auto bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
            <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wMyI+PHBhdGggZD0iTTM2IDM0djZoLTZ2LTZoNnptMC0zMHY2aC02VjRoNnptMzAgMHY2aC02VjRoNnptMCAzMHY2aC02di02aDZ6Ii8+PC9nPjwvZz48L3N2Zz4=')] opacity-50 pointer-events-none" />

            <div className="flex min-h-full items-center justify-center py-6 px-4">
                <div className="relative w-full max-w-3xl animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div className="text-center mb-6 sm:mb-8">
                        <h1 className="text-2xl sm:text-4xl font-bold text-white tracking-tight">
                            Olá, {firstName}!
                        </h1>
                        <p className="mt-1.5 text-base sm:text-lg text-slate-400">
                            Como deseja trabalhar hoje?
                        </p>
                    </div>

                    <div className={cn(
                        'grid gap-3 sm:gap-4',
                        filteredOptions.length === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2 max-w-lg mx-auto'
                    )}>
                        {(filteredOptions || []).map((opt) => {
                            const Icon = opt.icon
                            const isSelected = selected === opt.mode
                            return (
                                <button
                                    key={opt.mode}
                                    onClick={() => setSelected(opt.mode)}
                                    className={cn(
                                        'group relative rounded-2xl p-4 sm:p-5 text-left transition-all duration-200',
                                        'border-2 backdrop-blur-sm',
                                        isSelected
                                            ? 'border-white/40 bg-white/15 scale-[1.02] shadow-xl shadow-black/20'
                                            : 'border-white/10 bg-white/5 hover:bg-white/10 hover:border-white/20'
                                    )}
                                >
                                    {isSelected && (
                                        <div className="absolute -top-2 -right-2 w-6 h-6 bg-white rounded-full flex items-center justify-center shadow-lg">
                                            <Check className="w-4 h-4 text-slate-900" />
                                        </div>
                                    )}

                                    <div className="flex items-start gap-3 sm:flex-col sm:gap-0">
                                        <div className={cn(
                                            'w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center bg-gradient-to-br shrink-0 sm:mb-4',
                                            opt.gradient
                                        )}>
                                            <Icon className="w-5 h-5 sm:w-6 sm:h-6 text-white" />
                                        </div>

                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-base sm:text-lg font-semibold text-white">{opt.title}</h3>
                                            <p className="text-xs font-medium text-slate-400 mt-0.5">{opt.subtitle}</p>
                                        </div>
                                    </div>

                                    <p className="text-sm text-slate-300 mt-2 leading-relaxed hidden sm:block">{opt.description}</p>

                                    <div className="mt-2.5 sm:mt-4 flex flex-wrap gap-1.5">
                                        {(opt.features || []).map(f => (
                                            <span
                                                key={f}
                                                className="inline-block rounded-full bg-white/10 px-2.5 py-0.5 text-[10px] font-medium text-slate-300"
                                            >
                                                {f}
                                            </span>
                                        ))}
                                    </div>
                                </button>
                            )
                        })}
                    </div>

                    <div className="mt-5 sm:mt-6 flex flex-col items-center gap-3 sm:gap-4">
                        <label className="flex items-center gap-2 cursor-pointer group">
                            <div
                                onClick={() => setRemember(!remember)}
                                className={cn(
                                    'w-5 h-5 rounded border-2 flex items-center justify-center transition-colors',
                                    remember
                                        ? 'bg-white border-white'
                                        : 'border-slate-500 group-hover:border-slate-400'
                                )}
                            >
                                {remember && <Check className="w-3.5 h-3.5 text-slate-900" />}
                            </div>
                            <span className="text-sm text-slate-400 select-none">
                                Lembrar minha escolha
                            </span>
                        </label>

                        <button
                            onClick={handleConfirm}
                            disabled={!selected}
                            className={cn(
                                'flex items-center gap-2 rounded-xl px-8 py-3 text-sm font-semibold transition-all duration-200',
                                selected
                                    ? 'bg-white text-slate-900 hover:bg-slate-100 shadow-lg shadow-white/10'
                                    : 'bg-white/10 text-slate-500 cursor-not-allowed'
                            )}
                        >
                            Continuar
                            <ArrowRight className="w-4 h-4" />
                        </button>
                    </div>

                    <p className="mt-4 pb-2 text-center text-xs text-slate-600">
                        Você pode trocar de modo a qualquer momento pelo menu superior.
                    </p>
                </div>
            </div>
        </div>
    )
}

export function shouldShowModeSelection(availableModesCount: number): boolean {
    if (availableModesCount <= 1) return false
    try {
        return localStorage.getItem(MODE_SELECTION_KEY) !== 'remembered'
    } catch {
        return true
    }
}

export function resetModeSelection() {
    try {
        localStorage.removeItem(MODE_SELECTION_KEY)
    } catch { /* ignore */ }
}
