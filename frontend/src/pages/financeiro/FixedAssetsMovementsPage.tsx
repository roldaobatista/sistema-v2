import { useMemo, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { ArrowRightLeft } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/pageheader'
import { Textarea } from '@/components/ui/textarea'
import { fixedAssetMovementSchema, type FixedAssetMovementValues } from '@/schemas/fixed-asset'
import { useCreateFixedAssetMovement, useFixedAssetMovements, useFixedAssets } from '@/hooks/useFixedAssets'

const movementLabels: Record<string, string> = {
    transfer: 'Transferência',
    assignment: 'Responsável',
    maintenance: 'Manutenção',
    inventory_adjustment: 'Ajuste de inventário',
}

export function FixedAssetsMovementsPage() {
    const [assetId, setAssetId] = useState<number | null>(null)
    const assetsQuery = useFixedAssets({ per_page: 100 })
    const movementsQuery = useFixedAssetMovements(assetId, 20)
    const createMovement = useCreateFixedAssetMovement()
    const assets = assetsQuery.data?.data ?? []

    const movementForm = useForm<FixedAssetMovementValues>({
        resolver: zodResolver(fixedAssetMovementSchema),
        defaultValues: {
            movement_type: 'transfer',
            moved_at: new Date().toISOString().slice(0, 16),
            to_location: '',
            notes: '',
        },
    })

    const selectedAsset = useMemo(() => assets.find((asset) => asset.id === assetId) ?? null, [assetId, assets])

    async function handleSubmit(values: FixedAssetMovementValues) {
        if (!assetId) {
            toast.error('Selecione um ativo para registrar a movimentação.')
            return
        }

        await createMovement.mutateAsync({ assetId, payload: values })
        toast.success('Movimentação registrada com sucesso.')
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Movimentações de Ativos"
                subtitle="Controle transferências físicas, trocas de responsável e ajustes operacionais."
                icon={<ArrowRightLeft className="h-6 w-6" />}
            />

            <div className="grid gap-6 lg:grid-cols-[380px_1fr]">
                <Card>
                    <CardHeader><CardTitle>Nova movimentação</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-1.5">
                            <label className="block text-[13px] font-medium text-surface-700">Ativo</label>
                            <select
                                aria-label="Ativo para movimentação"
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
                                <p className="text-surface-500">Local atual: {selectedAsset.location || 'Sem localização'}</p>
                            </div>
                        ) : null}

                        <form className="space-y-4" onSubmit={movementForm.handleSubmit(values => void handleSubmit(values))}>
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Tipo</label>
                                <select aria-label="Tipo de movimentação" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm" {...movementForm.register('movement_type')}>
                                    {Object.entries(movementLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </div>
                            <Input type="datetime-local" label="Data e hora" aria-label="Data e hora da movimentação" {...movementForm.register('moved_at')} error={movementForm.formState.errors.moved_at?.message} />
                            <Input label="Novo local" aria-label="Novo local" {...movementForm.register('to_location')} />
                            <Input type="number" label="Novo responsável (ID)" aria-label="Novo responsável ID" {...movementForm.register('to_responsible_user_id')} />
                            <Textarea label="Observações" aria-label="Observações da movimentação" {...movementForm.register('notes')} />
                            <Button type="submit" className="w-full" loading={createMovement.isPending}>Salvar movimentação</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Histórico recente</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        {(movementsQuery.data?.data ?? []).map((movement) => (
                            <div key={movement.id} className="rounded-[var(--radius-lg)] border border-surface-200 p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="font-medium text-surface-900">{movement.asset_record?.code} - {movement.asset_record?.name}</p>
                                        <p className="text-sm text-surface-500">{movementLabels[movement.movement_type] ?? movement.movement_type}</p>
                                    </div>
                                    <span className="text-xs text-surface-500">{new Date(movement.moved_at).toLocaleString('pt-BR')}</span>
                                </div>
                                <p className="mt-2 text-sm text-surface-600">{movement.from_location || 'Origem não informada'} {'->'} {movement.to_location || 'Destino não informado'}</p>
                            </div>
                        ))}
                        {!movementsQuery.isLoading && (movementsQuery.data?.data ?? []).length === 0 ? (
                            <div className="rounded-[var(--radius-lg)] border border-dashed border-surface-200 p-8 text-center text-sm text-surface-500">
                                Nenhuma movimentação registrada ainda.
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </div>
    )
}
