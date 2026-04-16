import { useState, useCallback, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import { PageHeader } from '@/components/ui/pageheader'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { useAuthStore } from '@/stores/auth-store'
import { CommissionOverviewTab } from './commissions/CommissionOverviewTab'
import { CommissionEventsTab } from './commissions/CommissionEventsTab'
import { CommissionRulesTab } from './commissions/CommissionRulesTab'
import { CommissionSettlementsTab } from './commissions/CommissionSettlementsTab'
import { CommissionDisputesTab } from './commissions/CommissionDisputesTab'
import { CommissionGoalsTab } from './commissions/CommissionGoalsTab'
import { CommissionCampaignsTab } from './commissions/CommissionCampaignsTab'
import { CommissionRecurringTab } from './commissions/CommissionRecurringTab'
import { CommissionSimulatorTab } from './commissions/CommissionSimulatorTab'

const VALID_TABS = ['overview', 'events', 'rules', 'settlements', 'disputes', 'goals', 'campaigns', 'recurring', 'simulator'] as const
type TabValue = typeof VALID_TABS[number]

const TAB_CONFIG: Array<{ value: TabValue; label: string; permission: string }> = [
    { value: 'overview', label: 'Visão Geral', permission: 'commissions.rule.view' },
    { value: 'events', label: 'Eventos', permission: 'commissions.event.view' },
    { value: 'rules', label: 'Regras', permission: 'commissions.rule.view' },
    { value: 'settlements', label: 'Fechamentos', permission: 'commissions.settlement.view' },
    { value: 'disputes', label: 'Contestações', permission: 'commissions.dispute.view' },
    { value: 'goals', label: 'Metas', permission: 'commissions.goal.view' },
    { value: 'campaigns', label: 'Campanhas', permission: 'commissions.campaign.view' },
    { value: 'recurring', label: 'Recorrentes', permission: 'commissions.recurring.view' },
    { value: 'simulator', label: 'Simulador', permission: 'commissions.rule.view' },
]

function hasPermissionExpression(expression: string, hasPermission: (permission: string) => boolean): boolean {
    return expression
        .split('|')
        .map((permission) => permission.trim())
        .filter(Boolean)
        .some((permission) => hasPermission(permission))
}

export function CommissionsPage() {
    const { hasPermission } = useAuthStore()
    const [searchParams, setSearchParams] = useSearchParams()
    const visibleTabs = TAB_CONFIG.filter((tab) => hasPermissionExpression(tab.permission, hasPermission))
    const fallbackTab = visibleTabs[0]?.value ?? 'overview'

    const tabFromUrl = searchParams.get('tab') as TabValue | null
    const initialTab = tabFromUrl && visibleTabs.some((tab) => tab.value === tabFromUrl) ? tabFromUrl : fallbackTab
    const [activeTab, setActiveTab] = useState<TabValue>(initialTab)

    const initialEventFilters: Record<string, string> = {}
    const statusParam = searchParams.get('status')
    if (statusParam) initialEventFilters.status = statusParam

    const [eventFilters, setEventFilters] = useState<Record<string, string>>(initialEventFilters)

    const handleTabChange = useCallback((tab: string) => {
        const t = tab as TabValue
        setActiveTab(t)
        setSearchParams(prev => {
            const next = new URLSearchParams(prev)
            next.set('tab', t)
            // Clear filter params when switching tabs manually
            next.delete('status')
            return next
        }, { replace: true })
    }, [setSearchParams])

    const handleNavigateTab = useCallback((tab: string, filters?: Record<string, string>) => {
        const t = tab as TabValue
        setActiveTab(t)
        if (filters) setEventFilters(filters)
        setSearchParams(prev => {
            const next = new URLSearchParams(prev)
            next.set('tab', t)
            if (filters?.status) next.set('status', filters.status)
            else next.delete('status')
            return next
        }, { replace: true })
    }, [setSearchParams])

    // Sync tab from URL on back/forward navigation
    useEffect(() => {
        const t = searchParams.get('tab') as TabValue | null
        if (t && VALID_TABS.includes(t) && visibleTabs.some((tab) => tab.value === t) && t !== activeTab) {
            setActiveTab(t)
        }
    }, [searchParams, activeTab, visibleTabs])

    useEffect(() => {
        if (visibleTabs.length === 0) {
            return
        }

        if (visibleTabs.some((tab) => tab.value === activeTab)) {
            return
        }

        setActiveTab(fallbackTab)
        setSearchParams(prev => {
            const next = new URLSearchParams(prev)
            next.set('tab', fallbackTab)
            next.delete('status')
            return next
        }, { replace: true })
    }, [activeTab, fallbackTab, setSearchParams, visibleTabs])

    if (visibleTabs.length === 0) {
        return (
            <div className='space-y-6'>
                <PageHeader
                    title='Gestão de Comissões'
                    subtitle='Configure regras, acompanhe eventos e realize fechamentos.'
                />

                <div className='rounded-xl border border-default bg-surface-0 p-5 shadow-card text-sm text-surface-500'>
                    Você não possui permissões de visualização para acessar este módulo.
                </div>
            </div>
        )
    }

    return (
        <div className='space-y-6'>
            <PageHeader
                title='Gestão de Comissões'
                subtitle='Configure regras, acompanhe eventos e realize fechamentos.'
            />

            <Tabs value={activeTab} onValueChange={handleTabChange} className='space-y-4'>
                <TabsList>
                    {visibleTabs.map((tab) => (
                        <TabsTrigger key={tab.value} value={tab.value}>{tab.label}</TabsTrigger>
                    ))}
                </TabsList>

                {visibleTabs.some((tab) => tab.value === 'overview') && (
                    <TabsContent value='overview'><CommissionOverviewTab onNavigateTab={handleNavigateTab} /></TabsContent>
                )}
                {visibleTabs.some((tab) => tab.value === 'events') && (
                    <TabsContent value='events'><CommissionEventsTab initialFilters={eventFilters} /></TabsContent>
                )}
                {visibleTabs.some((tab) => tab.value === 'rules') && (
                    <TabsContent value='rules'><CommissionRulesTab /></TabsContent>
                )}
                {visibleTabs.some((tab) => tab.value === 'settlements') && (
                    <TabsContent value='settlements'><CommissionSettlementsTab /></TabsContent>
                )}
                {visibleTabs.some((tab) => tab.value === 'disputes') && (
                    <TabsContent value='disputes'><CommissionDisputesTab /></TabsContent>
                )}
                {visibleTabs.some((tab) => tab.value === 'goals') && (
                    <TabsContent value='goals'><CommissionGoalsTab /></TabsContent>
                )}
                {visibleTabs.some((tab) => tab.value === 'campaigns') && (
                    <TabsContent value='campaigns'><CommissionCampaignsTab /></TabsContent>
                )}
                {visibleTabs.some((tab) => tab.value === 'recurring') && (
                    <TabsContent value='recurring'><CommissionRecurringTab /></TabsContent>
                )}
                {visibleTabs.some((tab) => tab.value === 'simulator') && (
                    <TabsContent value='simulator'><CommissionSimulatorTab /></TabsContent>
                )}
            </Tabs>
        </div>
    )
}
