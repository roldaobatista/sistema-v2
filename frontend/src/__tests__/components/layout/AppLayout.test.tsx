import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import userEvent from '@testing-library/user-event'

// Mock all heavy dependencies
const mockToggleSidebar = vi.fn()
const mockToggleMobileSidebar = vi.fn()
const mockLogout = vi.fn()

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        user: { id: 1, name: 'Admin User', email: 'admin@test.com', permissions: ['*'], avatar_url: null },
        logout: mockLogout,
        hasRole: (role: string) => role === 'super_admin',
        hasPermission: () => true,
    }),
}))

vi.mock('@/stores/ui-store', () => ({
    useUIStore: () => ({
        sidebarCollapsed: false,
        toggleSidebar: mockToggleSidebar,
        sidebarMobileOpen: false,
        toggleMobileSidebar: mockToggleMobileSidebar,
    }),
}))

vi.mock('@/hooks/useDarkMode', () => ({
    useDarkMode: () => ({ isDark: false, toggle: vi.fn() }),
}))

vi.mock('@/hooks/usePWA', () => ({
    usePWA: () => ({ isInstallable: false, isOnline: true, install: vi.fn() }),
}))

vi.mock('@/hooks/useAppMode', () => ({
    useAppMode: () => ({ currentMode: 'admin' }),
}))

vi.mock('@/hooks/useCurrentTenant', () => ({
    useCurrentTenant: () => ({ currentTenant: { name: 'Kalibrium' }, tenants: [], switchTenant: vi.fn(), isSwitching: false }),
}))

vi.mock('@/hooks/usePrefetchCriticalData', () => ({
    usePrefetchCriticalData: vi.fn(),
}))

vi.mock('@/hooks/useSwipeGesture', () => ({
    useSwipeGesture: vi.fn(),
}))

vi.mock('@/components/layout/AppBreadcrumb', () => ({
    AppBreadcrumb: () => <nav data-testid="breadcrumb">Breadcrumb</nav>,
}))

vi.mock('@/components/notifications/NotificationPanel', () => ({
    default: () => <div data-testid="notification-panel" />,
}))

vi.mock('@/components/agenda/QuickReminderButton', () => ({
    QuickReminderButton: () => null,
}))

vi.mock('@/components/pwa/OfflineIndicator', () => ({ default: () => null }))
vi.mock('@/components/pwa/ModeSwitcher', () => ({ ModeSwitcher: () => null }))
vi.mock('@/components/pwa/InstallBanner', () => ({ InstallBanner: () => null }))
vi.mock('@/components/pwa/UpdateBanner', () => ({ UpdateBanner: () => null }))
vi.mock('@/components/pwa/SyncStatusPanel', () => ({ SyncStatusPanel: () => null }))
vi.mock('@/components/pwa/NetworkBadge', () => ({ NetworkBadge: () => null }))
vi.mock('@/components/pwa/TeamStatusWidget', () => ({ TeamStatusWidget: () => null }))

import { AppLayout } from '@/components/layout/AppLayout'

describe('AppLayout', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders sidebar with KALIBRIUM brand', () => {
        render(
            <AppLayout>
                <div>Page content</div>
            </AppLayout>
        )
        expect(screen.getByText('KALIBRIUM')).toBeInTheDocument()
    })

    it('renders children content', () => {
        render(
            <AppLayout>
                <div data-testid="page">My Page</div>
            </AppLayout>
        )
        expect(screen.getByTestId('page')).toBeInTheDocument()
    })

    it('renders Dashboard navigation item', () => {
        render(
            <AppLayout>
                <div>Content</div>
            </AppLayout>
        )
        expect(screen.getByText('Dashboard')).toBeInTheDocument()
    })

    it('renders multiple navigation sections', () => {
        render(
            <AppLayout>
                <div>Content</div>
            </AppLayout>
        )
        expect(screen.getByText('Workspace')).toBeInTheDocument()
        expect(screen.getByText('Comercial & Vendas')).toBeInTheDocument()
        expect(screen.getByText('Operacional & Field Service')).toBeInTheDocument()
    })

    it('renders sidebar with navigation links', () => {
        render(
            <AppLayout>
                <div>Content</div>
            </AppLayout>
        )
        // Some top-level items that should always be visible for super_admin
        expect(screen.getByText('Dashboard')).toBeInTheDocument()
        expect(screen.getByText('Gestão CRM')).toBeInTheDocument()
    })

    it('renders the K brand logo', () => {
        render(
            <AppLayout>
                <div>Content</div>
            </AppLayout>
        )
        expect(screen.getByText('K')).toBeInTheDocument()
    })

    it('renders breadcrumb component', () => {
        render(
            <AppLayout>
                <div>Content</div>
            </AppLayout>
        )
        expect(screen.getByTestId('breadcrumb')).toBeInTheDocument()
    })

    it('expands submenu group when clicked', async () => {
        const user = userEvent.setup()
        render(
            <AppLayout>
                <div>Content</div>
            </AppLayout>
        )

        // CRM has children, clicking should expand
        const crmButton = screen.getByText('Gestão CRM')
        await user.click(crmButton)

        // After expand, should show child items like Pipeline
        expect(screen.getByText('Pipeline')).toBeInTheDocument()
    })
})
