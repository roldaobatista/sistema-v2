import { FileBarChart2 } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageHeader } from '@/components/ui/pageheader'
import { useFixedAssetInventories, useFixedAssetMovements, useFixedAssets, useFixedAssetsDashboard } from '@/hooks/useFixedAssets'
import { formatCurrency } from '@/lib/utils'

export function FixedAssetsReportsPage() {
    const dashboardQuery = useFixedAssetsDashboard()
    const assetsQuery = useFixedAssets({ per_page: 100 })
    const movementsQuery = useFixedAssetMovements(null, 10)
    const inventoriesQuery = useFixedAssetInventories(null, 10)

    const assets = assetsQuery.data?.data ?? []
    const summary = dashboardQuery.data
    const divergentInventories = (inventoriesQuery.data?.data ?? []).filter((item) => item.divergent)

    return (
        <div className="space-y-6">
            <PageHeader
                title="Relatórios de Ativos"
                subtitle="Visão consolidada do valor patrimonial, movimentação recente e divergências de inventário."
                icon={<FileBarChart2 className="h-6 w-6" />}
            />

            <div className="grid gap-4 md:grid-cols-4">
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Base cadastrada</p><p className="mt-2 text-2xl font-bold">{assets.length}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Valor patrimonial</p><p className="mt-2 text-2xl font-bold">{formatCurrency(summary?.total_current_book_value ?? 0)}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Movimentações recentes</p><p className="mt-2 text-2xl font-bold">{movementsQuery.data?.meta?.total ?? (movementsQuery.data?.data?.length ?? 0)}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Divergências físicas</p><p className="mt-2 text-2xl font-bold">{divergentInventories.length}</p></CardContent></Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Distribuição por categoria</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        {Object.entries(summary?.by_category ?? {}).map(([category, data]) => (
                            <div key={category} className="flex items-center justify-between rounded-[var(--radius-lg)] border border-surface-200 px-4 py-3 text-sm">
                                <span className="font-medium text-surface-900">{category}</span>
                                <span className="text-surface-500">{data.count} ativos / {formatCurrency(data.book_value)}</span>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Inventários com divergência</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        {divergentInventories.map((item) => (
                            <div key={item.id} className="rounded-[var(--radius-lg)] border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
                                <p className="font-medium text-surface-900">{item.asset_record?.code} - {item.asset_record?.name}</p>
                                <p className="text-surface-600">Sistema: {item.asset_record?.location || 'Sem local'} / Contagem: {item.counted_location || 'Sem local'}</p>
                            </div>
                        ))}
                        {divergentInventories.length === 0 ? (
                            <div className="rounded-[var(--radius-lg)] border border-dashed border-surface-200 p-8 text-center text-sm text-surface-500">
                                Nenhuma divergência física encontrada nas últimas contagens.
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </div>
    )
}
