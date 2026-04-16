import { Archive, BarChart3, Coins, Landmark, Package } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageHeader } from '@/components/ui/pageheader'
import { useFixedAssetsDashboard } from '@/hooks/useFixedAssets'
import { formatCurrency } from '@/lib/utils'

const categoryLabels: Record<string, string> = {
    machinery: 'Máquinas',
    vehicle: 'Veículos',
    equipment: 'Equipamentos',
    furniture: 'Móveis',
    it: 'TI',
    tooling: 'Ferramental',
    other: 'Outros',
}

export function FixedAssetsDashboardPage() {
    const dashboardQuery = useFixedAssetsDashboard()
    const dashboard = dashboardQuery.data

    return (
        <div className="space-y-6">
            <PageHeader
                title="Dashboard Patrimonial"
                subtitle="Consolidação de valor contábil, depreciação e distribuição por categoria."
                icon={<Landmark className="h-6 w-6" />}
                actions={[
                    { label: 'Ativos', href: '/financeiro/ativos', icon: <Archive className="h-4 w-4" />, variant: 'outline' },
                    { label: 'Depreciação', href: '/financeiro/ativos/depreciacao', icon: <BarChart3 className="h-4 w-4" /> },
                ]}
            />

            <div className="grid gap-4 md:grid-cols-4">
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Aquisição</p><p className="mt-2 text-2xl font-bold">{formatCurrency(dashboard?.total_acquisition_value ?? 0)}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Valor contábil</p><p className="mt-2 text-2xl font-bold">{formatCurrency(dashboard?.total_current_book_value ?? 0)}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Depreciação acumulada</p><p className="mt-2 text-2xl font-bold">{formatCurrency(dashboard?.total_accumulated_depreciation ?? 0)}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Baixas no ano</p><p className="mt-2 text-2xl font-bold">{dashboard?.disposals_this_year ?? 0}</p></CardContent></Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                <Card>
                    <CardHeader><CardTitle>Distribuição por categoria</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        {dashboardQuery.isLoading ? (
                            <p className="text-sm text-surface-500">Carregando distribuição...</p>
                        ) : Object.entries(dashboard?.by_category ?? {}).length === 0 ? (
                            <p className="text-sm text-surface-500">Sem dados patrimoniais para o tenant atual.</p>
                        ) : (
                            Object.entries(dashboard?.by_category ?? {}).map(([category, value]) => (
                                <div key={category} className="flex items-center justify-between rounded-[var(--radius-lg)] border border-surface-100 p-4">
                                    <div>
                                        <p className="font-medium text-surface-900 dark:text-white">{categoryLabels[category] ?? category}</p>
                                        <p className="text-xs text-surface-500">{value.count} ativo(s)</p>
                                    </div>
                                    <p className="font-semibold text-surface-900 dark:text-white">{formatCurrency(value.book_value)}</p>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Atalhos</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        <Link to="/financeiro/ativos" className="flex items-center gap-3 rounded-[var(--radius-lg)] border border-surface-100 p-4 hover:bg-surface-50">
                            <Package className="h-5 w-5 text-prix-500" />
                            <div><p className="font-medium">Carteira de ativos</p><p className="text-xs text-surface-500">Cadastro e gestão diária</p></div>
                        </Link>
                        <Link to="/financeiro/ativos/depreciacao" className="flex items-center gap-3 rounded-[var(--radius-lg)] border border-surface-100 p-4 hover:bg-surface-50">
                            <Coins className="h-5 w-5 text-prix-500" />
                            <div><p className="font-medium">Depreciação mensal</p><p className="text-xs text-surface-500">Executar e acompanhar logs</p></div>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    )
}
