import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { CommissionsPage } from '@/pages/financeiro/CommissionsPage'

const mockPermissions = vi.hoisted(() => new Set<string>())

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: (selector?: (state: { hasPermission: (permission: string) => boolean }) => unknown) => {
        const state = {
            hasPermission: (permission: string) => mockPermissions.has(permission),
        }

        return typeof selector === 'function' ? selector(state) : state
    },
}))

vi.mock('@/pages/financeiro/commissions/CommissionOverviewTab', () => ({
    CommissionOverviewTab: () => <div>overview content</div>,
}))

vi.mock('@/pages/financeiro/commissions/CommissionEventsTab', () => ({
    CommissionEventsTab: () => <div>events content</div>,
}))

vi.mock('@/pages/financeiro/commissions/CommissionRulesTab', () => ({
    CommissionRulesTab: () => <div>rules content</div>,
}))

vi.mock('@/pages/financeiro/commissions/CommissionSettlementsTab', () => ({
    CommissionSettlementsTab: () => <div>settlements content</div>,
}))

vi.mock('@/pages/financeiro/commissions/CommissionDisputesTab', () => ({
    CommissionDisputesTab: () => <div>disputes content</div>,
}))

vi.mock('@/pages/financeiro/commissions/CommissionGoalsTab', () => ({
    CommissionGoalsTab: () => <div>goals content</div>,
}))

vi.mock('@/pages/financeiro/commissions/CommissionCampaignsTab', () => ({
    CommissionCampaignsTab: () => <div>campaigns content</div>,
}))

vi.mock('@/pages/financeiro/commissions/CommissionRecurringTab', () => ({
    CommissionRecurringTab: () => <div>recurring content</div>,
}))

vi.mock('@/pages/financeiro/commissions/CommissionSimulatorTab', () => ({
    CommissionSimulatorTab: () => <div>simulator content</div>,
}))

describe('CommissionsPage', () => {
    beforeEach(() => {
        mockPermissions.clear()
        window.history.pushState({}, 'Test page', '/financeiro/comissoes')
    })

    it('abre o modulo pela primeira tab visivel quando usuario nao possui regra.view', async () => {
        mockPermissions.add('commissions.event.view')

        render(<CommissionsPage />, { route: '/financeiro/comissoes' })

        expect(screen.getByRole('tab', { name: 'Eventos' })).toBeInTheDocument()
        expect(screen.queryByRole('tab', { name: 'Visão Geral' })).not.toBeInTheDocument()
        expect(await screen.findByText('events content')).toBeInTheDocument()
    })

    it('redireciona a tab da url para uma aba permitida quando a solicitada nao e visivel', async () => {
        mockPermissions.add('commissions.settlement.view')

        render(<CommissionsPage />, { route: '/financeiro/comissoes?tab=rules' })

        expect(screen.getByRole('tab', { name: 'Fechamentos' })).toBeInTheDocument()
        expect(screen.queryByRole('tab', { name: 'Regras' })).not.toBeInTheDocument()
        expect(await screen.findByText('settlements content')).toBeInTheDocument()
    })
})
