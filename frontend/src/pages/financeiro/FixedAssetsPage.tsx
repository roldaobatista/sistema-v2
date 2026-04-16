import { useMemo, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Archive, ArrowRightLeft, BarChart3, ClipboardCheck, PauseCircle, PlayCircle, Plus, RefreshCw } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/pageheader'
import { Textarea } from '@/components/ui/textarea'
import { fixedAssetFormSchema, disposeAssetSchema, type DisposeAssetValues, type FixedAssetFormValues } from '@/schemas/fixed-asset'
import {
    useCreateFixedAsset,
    useDisposeAsset,
    useFixedAssets,
    useFixedAssetsDashboard,
    useReactivateAsset,
    useSuspendAsset,
} from '@/hooks/useFixedAssets'
import type { FixedAsset } from '@/types/fixed-assets'
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

const statusLabels: Record<string, string> = {
    active: 'Ativo',
    suspended: 'Suspenso',
    disposed: 'Baixado',
    fully_depreciated: 'Totalmente depreciado',
}

export function FixedAssetsPage() {
    const [category, setCategory] = useState('')
    const [status, setStatus] = useState('')
    const [showCreateDialog, setShowCreateDialog] = useState(false)
    const [assetToDispose, setAssetToDispose] = useState<FixedAsset | null>(null)

    const filters = useMemo(() => ({
        category: category as '' | FixedAsset['category'],
        status: status as '' | FixedAsset['status'],
        per_page: 25,
    }), [category, status])

    const assetsQuery = useFixedAssets(filters)
    const dashboardQuery = useFixedAssetsDashboard()
    const createAsset = useCreateFixedAsset()
    const suspendAsset = useSuspendAsset()
    const reactivateAsset = useReactivateAsset()
    const disposeAsset = useDisposeAsset()

    const createForm = useForm<FixedAssetFormValues>({
        resolver: zodResolver(fixedAssetFormSchema),
        defaultValues: {
            name: '',
            description: '',
            category: 'equipment',
            acquisition_date: new Date().toISOString().slice(0, 10),
            acquisition_value: 0,
            residual_value: 0,
            useful_life_months: 60,
            depreciation_method: 'linear',
            location: '',
            ciap_credit_type: 'none',
        },
    })

    const disposeForm = useForm<DisposeAssetValues>({
        resolver: zodResolver(disposeAssetSchema),
        defaultValues: {
            disposal_date: new Date().toISOString().slice(0, 10),
            reason: 'sale',
            disposal_value: 0,
            notes: '',
            approved_by: undefined,
        },
    })

    const assets = assetsQuery.data?.data ?? []
    const summary = dashboardQuery.data

    async function handleCreate(values: FixedAssetFormValues) {
        await createAsset.mutateAsync(values)
        toast.success('Ativo cadastrado com sucesso.')
        setShowCreateDialog(false)
        createForm.reset()
    }

    async function handleToggleStatus(asset: FixedAsset) {
        if (asset.status === 'suspended') {
            await reactivateAsset.mutateAsync(asset.id)
            toast.success(`Ativo ${asset.code} reativado.`)
            return
        }

        await suspendAsset.mutateAsync(asset.id)
        toast.success(`Ativo ${asset.code} suspenso.`)
    }

    async function handleDispose(values: DisposeAssetValues) {
        if (!assetToDispose) return

        await disposeAsset.mutateAsync({
            assetId: assetToDispose.id,
            payload: values,
        })
        toast.success(`Baixa registrada para ${assetToDispose.code}.`)
        setAssetToDispose(null)
        disposeForm.reset()
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Ativo Imobilizado"
                subtitle="Cadastre ativos, acompanhe saldos e acione baixas patrimoniais."
                icon={<Archive className="h-6 w-6" />}
                actions={[
                    {
                        label: 'Dashboard',
                        href: '/financeiro/ativos/dashboard',
                        icon: <BarChart3 className="h-4 w-4" />,
                        variant: 'outline',
                    },
                    {
                        label: 'Movimentações',
                        href: '/financeiro/ativos/movimentacoes',
                        icon: <ArrowRightLeft className="h-4 w-4" />,
                        variant: 'outline',
                    },
                    {
                        label: 'Inventário',
                        href: '/financeiro/ativos/inventario',
                        icon: <ClipboardCheck className="h-4 w-4" />,
                        variant: 'outline',
                    },
                    {
                        label: 'Novo ativo',
                        onClick: () => setShowCreateDialog(true),
                        icon: <Plus className="h-4 w-4" />,
                    },
                ]}
            />

            <div className="grid gap-4 md:grid-cols-4">
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Ativos</p><p className="mt-2 text-2xl font-bold">{summary?.total_assets ?? 0}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Valor contábil</p><p className="mt-2 text-2xl font-bold">{formatCurrency(summary?.total_current_book_value ?? 0)}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Depreciação acumulada</p><p className="mt-2 text-2xl font-bold">{formatCurrency(summary?.total_accumulated_depreciation ?? 0)}</p></CardContent></Card>
                <Card><CardContent className="p-5"><p className="text-sm text-surface-500">Parcelas CIAP pendentes</p><p className="mt-2 text-2xl font-bold">{summary?.ciap_credits_pending ?? 0}</p></CardContent></Card>
            </div>

            <Card>
                <CardHeader className="space-y-4">
                    <div>
                        <CardTitle>Carteira de ativos</CardTitle>
                        <p className="text-sm text-surface-500">Filtre por categoria e status para acompanhar o parque patrimonial.</p>
                    </div>
                    <div className="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                        <div className="space-y-1.5">
                            <label className="block text-[13px] font-medium text-surface-700">Categoria</label>
                            <select
                                aria-label="Filtro por categoria"
                                className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]"
                                value={category}
                                onChange={event => setCategory(event.target.value)}
                            >
                                <option value="">Todas</option>
                                {Object.entries(categoryLabels).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <label className="block text-[13px] font-medium text-surface-700">Status</label>
                            <select
                                aria-label="Filtro por status"
                                className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]"
                                value={status}
                                onChange={event => setStatus(event.target.value)}
                            >
                                <option value="">Todos</option>
                                {Object.entries(statusLabels).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <Button variant="outline" icon={<RefreshCw className="h-4 w-4" />} onClick={() => void assetsQuery.refetch()}>
                                Atualizar
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {assetsQuery.isLoading ? (
                        <div className="rounded-[var(--radius-lg)] border border-dashed border-surface-200 p-8 text-center text-sm text-surface-500">
                            Carregando ativos...
                        </div>
                    ) : assets.length === 0 ? (
                        <div className="rounded-[var(--radius-lg)] border border-dashed border-surface-200 p-8 text-center text-sm text-surface-500">
                            Nenhum ativo encontrado para os filtros atuais.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="border-b border-surface-200 text-surface-500">
                                    <tr>
                                        <th className="px-3 py-3 font-medium">Código</th>
                                        <th className="px-3 py-3 font-medium">Ativo</th>
                                        <th className="px-3 py-3 font-medium">Categoria</th>
                                        <th className="px-3 py-3 font-medium">Valor contábil</th>
                                        <th className="px-3 py-3 font-medium">Status</th>
                                        <th className="px-3 py-3 font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {assets.map(asset => (
                                        <tr key={asset.id} className="border-b border-surface-100 last:border-0">
                                            <td className="px-3 py-3 font-semibold text-surface-900 dark:text-white">{asset.code}</td>
                                            <td className="px-3 py-3">
                                                <div className="font-medium text-surface-900 dark:text-white">{asset.name}</div>
                                                <div className="text-xs text-surface-500">{asset.location || 'Sem localização'}</div>
                                            </td>
                                            <td className="px-3 py-3">{categoryLabels[asset.category] ?? asset.category}</td>
                                            <td className="px-3 py-3">{formatCurrency(asset.current_book_value)}</td>
                                            <td className="px-3 py-3">{statusLabels[asset.status] ?? asset.status}</td>
                                            <td className="px-3 py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        icon={asset.status === 'suspended'
                                                            ? <PlayCircle className="h-4 w-4" />
                                                            : <PauseCircle className="h-4 w-4" />}
                                                        onClick={() => void handleToggleStatus(asset)}
                                                        disabled={asset.status === 'disposed'}
                                                    >
                                                        {asset.status === 'suspended' ? 'Reativar' : 'Suspender'}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="danger"
                                                        icon={<Archive className="h-4 w-4" />}
                                                        onClick={() => {
                                                            setAssetToDispose(asset)
                                                            disposeForm.reset({
                                                                disposal_date: new Date().toISOString().slice(0, 10),
                                                                reason: 'sale',
                                                                disposal_value: Number(asset.current_book_value),
                                                                notes: '',
                                                                approved_by: undefined,
                                                            })
                                                        }}
                                                        disabled={asset.status === 'disposed'}
                                                    >
                                                        Baixar
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                <DialogContent size="lg">
                    <DialogHeader><DialogTitle>Novo ativo imobilizado</DialogTitle></DialogHeader>
                    <DialogBody>
                        <form id="fixed-asset-create-form" className="grid gap-4 md:grid-cols-2" onSubmit={createForm.handleSubmit(values => void handleCreate(values))}>
                            <Input label="Nome" aria-label="Nome do ativo" {...createForm.register('name')} error={createForm.formState.errors.name?.message} />
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Categoria</label>
                                <select aria-label="Categoria do ativo" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]" {...createForm.register('category')}>
                                    {Object.entries(categoryLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </div>
                            <Input type="date" label="Data de aquisição" aria-label="Data de aquisição" {...createForm.register('acquisition_date')} error={createForm.formState.errors.acquisition_date?.message} />
                            <Input type="number" step="0.01" label="Valor de aquisição" aria-label="Valor de aquisição" {...createForm.register('acquisition_value')} error={createForm.formState.errors.acquisition_value?.message} />
                            <Input type="number" step="0.01" label="Valor residual" aria-label="Valor residual" {...createForm.register('residual_value')} error={createForm.formState.errors.residual_value?.message} />
                            <Input type="number" label="Vida útil (meses)" aria-label="Vida útil em meses" {...createForm.register('useful_life_months')} error={createForm.formState.errors.useful_life_months?.message} />
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Método de depreciação</label>
                                <select aria-label="Método de depreciação" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]" {...createForm.register('depreciation_method')}>
                                    <option value="linear">Linear</option>
                                    <option value="accelerated">Acelerado</option>
                                    <option value="units_produced">Unidades produzidas</option>
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">CIAP</label>
                                <select aria-label="Tipo de crédito CIAP" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]" {...createForm.register('ciap_credit_type')}>
                                    <option value="none">Sem crédito</option>
                                    <option value="icms_full">ICMS integral</option>
                                    <option value="icms_48">ICMS em 48 parcelas</option>
                                </select>
                            </div>
                            <Input label="Localização" aria-label="Localização física" {...createForm.register('location')} />
                            <Input type="number" label="Responsável (ID)" aria-label="ID do responsável" {...createForm.register('responsible_user_id')} />
                            <Input type="number" label="Fornecedor (ID)" aria-label="ID do fornecedor" {...createForm.register('supplier_id')} />
                            <Input type="number" label="Veículo da frota (ID)" aria-label="ID do veículo da frota" {...createForm.register('fleet_vehicle_id')} />
                            <Input label="NF" aria-label="Número da nota fiscal" {...createForm.register('nf_number')} />
                            <Input label="Série" aria-label="Série da nota fiscal" {...createForm.register('nf_serie')} />
                            <div className="md:col-span-2">
                                <Textarea label="Descrição" aria-label="Descrição do ativo" {...createForm.register('description')} error={createForm.formState.errors.description?.message} />
                            </div>
                        </form>
                    </DialogBody>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowCreateDialog(false)}>Cancelar</Button>
                        <Button form="fixed-asset-create-form" type="submit" loading={createAsset.isPending}>Salvar ativo</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={assetToDispose !== null} onOpenChange={open => !open && setAssetToDispose(null)}>
                <DialogContent>
                    <DialogHeader><DialogTitle>Registrar baixa patrimonial</DialogTitle></DialogHeader>
                    <DialogBody>
                        <form id="fixed-asset-dispose-form" className="space-y-4" onSubmit={disposeForm.handleSubmit(values => void handleDispose(values))}>
                            <p className="text-sm text-surface-500">
                                Você está registrando a baixa do ativo <strong>{assetToDispose?.code}</strong> com valor contábil atual de{' '}
                                <strong>{formatCurrency(assetToDispose?.current_book_value ?? 0)}</strong>.
                            </p>
                            <Input type="date" label="Data da baixa" aria-label="Data da baixa" {...disposeForm.register('disposal_date')} error={disposeForm.formState.errors.disposal_date?.message} />
                            <div className="space-y-1.5">
                                <label className="block text-[13px] font-medium text-surface-700">Motivo</label>
                                <select aria-label="Motivo da baixa" className="w-full rounded-[var(--radius-md)] border border-surface-200 bg-white px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-[#0F0F12]" {...disposeForm.register('reason')}>
                                    <option value="sale">Venda</option>
                                    <option value="loss">Perda</option>
                                    <option value="scrap">Sucata</option>
                                    <option value="donation">Doação</option>
                                    <option value="theft">Furto</option>
                                </select>
                            </div>
                            <Input type="number" step="0.01" label="Valor da baixa" aria-label="Valor da baixa" {...disposeForm.register('disposal_value')} error={disposeForm.formState.errors.disposal_value?.message} />
                            <Input type="number" label="Aprovador (ID)" aria-label="ID do aprovador" {...disposeForm.register('approved_by')} error={disposeForm.formState.errors.approved_by?.message} />
                            <Textarea label="Observações" aria-label="Observações da baixa" {...disposeForm.register('notes')} />
                        </form>
                    </DialogBody>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAssetToDispose(null)}>Cancelar</Button>
                        <Button form="fixed-asset-dispose-form" type="submit" variant="danger" loading={disposeAsset.isPending}>Confirmar baixa</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="flex flex-wrap justify-end gap-4">
                <Link to="/financeiro/ativos/depreciacao" className="text-sm font-medium text-prix-500 hover:underline">
                    Execução mensal de depreciação
                </Link>
                <Link to="/financeiro/ativos/relatorios" className="text-sm font-medium text-prix-500 hover:underline">
                    Relatórios patrimoniais
                </Link>
            </div>
        </div>
    )
}
