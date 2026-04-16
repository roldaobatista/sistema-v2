import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth-store'
import { useAppMode, MODE_STORAGE_KEY, type AppMode } from '@/hooks/useAppMode'
import { ModeSelectionScreen, shouldShowModeSelection } from '@/components/pwa/ModeSelectionScreen'
import { OnboardingTour, shouldShowOnboarding } from '@/components/pwa/OnboardingTour'

const FIELD_ONLY_ROLES = new Set(['tecnico', 'tecnico_vendedor', 'motorista'])
const MANAGEMENT_ROLES = new Set([
    'super_admin', 'admin', 'gerente', 'coordenador',
    'financeiro', 'comercial', 'atendimento', 'rh',
    'estoquista', 'qualidade', 'vendedor', 'monitor', 'visualizador',
])
const VENDEDOR_ROLES = new Set(['comercial', 'vendedor', 'tecnico_vendedor'])

export function TechAutoRedirect({ children }: { children: React.ReactNode }) {
    const { user } = useAuthStore()
    const { availableModes, switchMode } = useAppMode()
    const _navigate = useNavigate()
    const [showSelection, setShowSelection] = useState(() =>
        shouldShowModeSelection(availableModes.length)
    )
    const [showOnboarding, setShowOnboarding] = useState(false)
    const [selectedMode, setSelectedMode] = useState<AppMode | null>(null)

    if (!user) return <>{children}</>

    const roles = user.roles ?? user.all_roles ?? []
    const hasManagementRole = roles.some((r) => MANAGEMENT_ROLES.has(r))
    const hasFieldRole = roles.some((r) => FIELD_ONLY_ROLES.has(r))
    const hasVendedorRole = roles.some((r) => VENDEDOR_ROLES.has(r))

    if (availableModes.length === 1) {
        if (availableModes[0] === 'tecnico') return <Navigate to="/tech" replace />
        if (availableModes[0] === 'vendedor') return <Navigate to="/crm" replace />
        return <>{children}</>
    }

    if (showSelection) {
        return (
            <ModeSelectionScreen
                userName={user.name}
                availableModes={availableModes}
                onSelect={(mode) => {
                    setSelectedMode(mode)
                    setShowSelection(false)
                    if (shouldShowOnboarding(mode)) {
                        setShowOnboarding(true)
                    } else {
                        switchMode(mode)
                    }
                }}
            />
        )
    }

    if (showOnboarding && selectedMode) {
        return (
            <>
                {children}
                <OnboardingTour
                    mode={selectedMode}
                    onComplete={() => {
                        setShowOnboarding(false)
                        switchMode(selectedMode)
                    }}
                />
            </>
        )
    }

    let lastMode: string | null = null
    try {
        lastMode = localStorage.getItem(MODE_STORAGE_KEY)
    } catch {
        // ignore
    }

    if (lastMode === 'tecnico' && availableModes.includes('tecnico')) {
        return <Navigate to="/tech" replace />
    }
    if (lastMode === 'vendedor' && availableModes.includes('vendedor')) {
        return <Navigate to="/crm" replace />
    }

    if (hasFieldRole && !hasManagementRole && !hasVendedorRole) {
        return <Navigate to="/tech" replace />
    }

    return <>{children}</>
}
