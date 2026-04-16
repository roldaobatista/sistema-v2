import { useMemo, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { ClipboardCheck, WifiOff } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/pageheader'
import { Textarea } from '@/components/ui/textarea'
import { fixedAssetInventorySchema, type FixedAssetInventoryValues } from '@/schemas/fixed-asset'
import { useCreateFixedAssetInventory, useFixedAssetInventories, useFixedAssets } from '@/hooks/useFixedAssets'
import { queueFixedAssetInventoryCount } from '@/lib/fixed-assets-offline'

const statusLabels: Record<string, string> = {
    active: 'Ativo',
    suspended: 'Suspenso',
    disposed: 'Baixado',
    fully_depreciated: 'Depreciado',
}

export function FixedAssetsInventoryPage() {
    const [assetId, setAssetId] = useState<number | null>(null)
    const assetsQuery = useFixedAssets({ per_page: 100 })
    const inventoryQuery = useFixedAssetInventories(assetId, 20)
    const createInventory = useCreateFixedAssetInventory()
    const assets = assetsQuery.data?.data ?? []
    const selectedAsset = useMemo(() => assets.find((asset) => asset.id === assetId) ?? null, [assetId, assets])

    const inventoryForm = useForm<FixedAssetInventoryValues>({
        resolver: zodResolver(fixedAssetInventorySchema),
        defaultValues: {
            inventory_date: new Date().toISOString().slice(0, 10),
            counted_location: '',
            counted_status: 'active',
            condition_ok: true,
            notes: '',
        },
    })

    async function handleOnlineSubmit(values: FixedAssetInventoryValues) {
        if (!assetId) {
            toast.error('Selecione um ativo para registrar o inventário.')
            return
        }

        await createInventory.mutateAsync({ assetId, payload: values })
        toast.success('Inventário registrado com sucesso.')
    }

    async function handleOfflineSubmit(values: FixedAssetInventoryValues) {
        if (!assetId) {
            toast.error('Selecione um ativo para registrar o inventário.')
            return
        }

        const result = await queueFixedAssetInventoryCount(assetId, values)
        toast.success(result.queuedOffline ? 'Inventário salvo offline e enfileirado para sync.' : 'Inventário sincronizado e salvo no cache local.')
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Inventário Patrimonial"
                subtitle="Conferência física com suporte a registro online e offline em IndexedDB."
                icon={<ClipboardCheck className="h-6 w-6" />}
            />

            <div className="grid gap-6 lg:grid-cols-[380px_1fr]">
                <Card>
                    <CardHeader><CardTitle>Registrar contagem</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-1.5">
                            <label className="block text-[13px] font-medium text-surface-700">Ativo</label>
                            <select
                                aria-label="Ativo para inventário"
                                className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm"
                                value={assetId ?? ''}
                                onChange={event => setAssetId(event.target.value ? Number(event.target.value) : null)}
                            >
                                <option value="">Selecione</option>
                                {assets.map((asset) => (
                                    <option key={asset.id} value={asset.id}>{asset.code} - {asset.name}</option>
                                ))}
                            </select>
                        </div>

                        {selectedAsset ? (
                            <div className="rounded-[var(--radius-lg)] border border-surface-200 bg-surface-50 p-3 text-sm">
                                <p className="font-medium text-surface-900">{selectedAsset.name}</p>
                                <p className="text-surface-500">Sistema: {selectedAsset.location || 'Sem localização'} / {statusLabels[selectedAsset.status] ?? selectedAsset.status}</p>
                            </div>
                        ) : null}

                        <form className="space-y-4" onSubmit={inventoryForm.handleSubmit(values => void handleOnlineSubmit(values))}>
                            <Input type="date" label="Data do inventário" aria-label="Data do inventário" {...inventoryForm.register('inventory_date')} error={inventoryForm.formState.errors.inventory_date?.message} />
                            <Input label="Local contado" aria-label="Local contado" {...inventoryForm.register('counted_location')} />
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Status contado</label>
                                <select aria-label="Status contado" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm" {...inventoryForm.register('counted_status')}>
                                    {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </div>
                            <label className="flex items-center gap-2 text-sm text-surface-700">
                                <input type="checkbox" {...inventoryForm.register('condition_ok')} />
                                Condição física aprovada
                            </label>
                            <Textarea label="Observações" aria-label="Observações do inventário" {...inventoryForm.register('notes')} />
                            <div className="grid gap-2 sm:grid-cols-2">
                                <Button type="submit" loading={createInventory.isPending}>Salvar online</Button>
                                <Button type="button" variant="outline" icon={<WifiOff className="h-4 w-4" />} onClick={() => void inventoryForm.handleSubmit(values => handleOfflineSubmit(values))()}>
                                    Salvar offline
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Conferências recentes</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        {(inventoryQuery.data?.data ?? []).map((inventory) => (
                            <div key={inventory.id} className="rounded-[var(--radius-lg)] border border-surface-200 p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="font-medium text-surface-900">{inventory.asset_record?.code} - {inventory.asset_record?.name}</p>
                                        <p className="text-sm text-surface-500">{inventory.counted_location || 'Sem local informado'} / {statusLabels[inventory.counted_status || 'active'] ?? inventory.counted_status}</p>
                                    </div>
                                    <span className={`text-xs font-medium ${inventory.divergent ? 'text-amber-600' : 'text-emerald-600'}`}>
                                        {inventory.divergent ? 'Com divergência' : 'Sem divergência'}
                                    </span>
                                </div>
                            </div>
                        ))}
                        {!inventoryQuery.isLoading && (inventoryQuery.data?.data ?? []).length === 0 ? (
                            <div className="rounded-[var(--radius-lg)] border border-dashed border-surface-200 p-8 text-center text-sm text-surface-500">
                                Nenhuma conferência registrada ainda.
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </div>
    )
}
