import { render, screen } from '@testing-library/react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import TechShell from '../TechShell'
import { useAuthStore } from '@/stores/auth-store'
import { useSyncStatus } from '@/hooks/useSyncStatus'

// Mocks
vi.mock('@/stores/auth-store', () => ({
    useAuthStore: vi.fn()
}))

vi.mock('@/hooks/useSyncStatus', () => ({
    useSyncStatus: vi.fn()
}))

vi.mock('@/hooks/useCrossTabSync', () => ({
    useCrossTabSync: vi.fn()
}))

vi.mock('@tanstack/react-query', () => ({
    useQuery: vi.fn(() => ({ data: { abertos: 1, em_andamento: 2 } })),
    useQueryClient: vi.fn(() => ({
        invalidateQueries: vi.fn(),
    })),
    useMutation: vi.fn(() => ({
        mutate: vi.fn(),
        mutateAsync: vi.fn(),
        isPending: false,
    })),
}))

vi.mock('@/components/pwa/ModeSwitcher', () => ({ ModeSwitcher: () => <div data-testid="mode-switcher" /> }))
vi.mock('@/components/pwa/InstallBanner', () => ({ InstallBanner: () => null }))
vi.mock('@/components/tech/TechAlertBanner', () => ({ TechAlertBanner: () => null }))
vi.mock('@/components/tech/FloatingTimer', () => ({ FloatingTimer: () => null }))

// Helpers para setup
const setupAuth = (overrides = {}) => {
    vi.mocked(useAuthStore).mockReturnValue({
        isAuthenticated: true,
        user: { id: 1, name: 'Test' },
        hasRole: vi.fn((role) => role === 'tecnico'), // Por default atende `ALLOWED_TECH_ROLES`
        hasPermission: vi.fn(() => true), // default true para testar abas
        fetchMe: vi.fn().mockResolvedValue({}),
        logout: vi.fn().mockResolvedValue({}),
        ...overrides
    } as any)
}

describe('TechShell Layout', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        vi.mocked(useSyncStatus).mockReturnValue({
            isOnline: true,
             pendingCount: 0,
             syncErrorCount: 0,
             isSyncing: false,
             syncNow: vi.fn(),
             lastSyncAt: null,
             refreshPendingCount: vi.fn()
        } as any)
    })

    it('redirects to login if not authenticated', () => {
        setupAuth({ isAuthenticated: false, user: null })
        render(
            <MemoryRouter initialEntries={['/tech/dashboard']}>
                <TechShell />
            </MemoryRouter>
        )

        expect(screen.queryByText('Kalibrium')).not.toBeInTheDocument()
    })

    it('shows loading state when authenticated but no user loaded', () => {
        setupAuth({ isAuthenticated: true, user: null })
        render(
            <MemoryRouter>
                <TechShell />
            </MemoryRouter>
        )
        expect(screen.getByText(/Carregando painel tecnico/i)).toBeInTheDocument()
    })

    it('shows error if user has NO tech roles', () => {
        setupAuth({ hasRole: vi.fn(() => false) }) // false for all
        render(
            <MemoryRouter>
                <TechShell />
            </MemoryRouter>
        )
        expect(screen.getByText('Acesso negado')).toBeInTheDocument()
        expect(screen.getByText(/Você não tem permissão para acessar o painel técnico/)).toBeInTheDocument()
    })

    it('denies access to a protected route if missing specific permission', () => {
        setupAuth({
            hasRole: vi.fn((r) => r === 'tecnico'),
            hasPermission: vi.fn(() => false) // falha tem permissão "os.work_order.create" etc
        })

        render(
            <MemoryRouter initialEntries={['/tech/nova-os']}>
                <TechShell />
            </MemoryRouter>
        )
        expect(screen.getByText('Acesso negado')).toBeInTheDocument()
        expect(screen.getByText(/Voce nao tem permissao para acessar esta rota tecnica/)).toBeInTheDocument()
    })

    it('renders topbar and nav items correctly if authorized', () => {
        setupAuth()
        render(
            <MemoryRouter initialEntries={['/tech/dashboard']}>
                <TechShell />
            </MemoryRouter>
        )
        expect(screen.getByText('Kalibrium')).toBeInTheDocument()
        expect(screen.getByText('Painel')).toBeInTheDocument()
        expect(screen.getByText('OS')).toBeInTheDocument()
    })
})
