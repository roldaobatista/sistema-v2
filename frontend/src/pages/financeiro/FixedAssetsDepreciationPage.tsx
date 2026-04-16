import { useEffect } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { BarChart3, CalendarSync } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/pageheader'
import { runDepreciationSchema, type RunDepreciationValues } from '@/schemas/fixed-asset'
import { useDepreciationLogs, useFixedAssets, useRunMonthlyDepreciation } from '@/hooks/useFixedAssets'
import { formatCurrency } from '@/lib/utils'

export function FixedAssetsDepreciationPage() {
    const assetsQuery = useFixedAssets({ per_page: 100, status: 'active' })
    const runDepreciation = useRunMonthlyDepreciation()
    const form = useForm<RunDepreciationValues>({
        resolver: zodResolver(runDepreciationSchema),
        defaultValues: {
            reference_month: new Date().toISOString().slice(0, 7),
        },
    })

    const selectedAsset = assetsQuery.data?.data[0] ?? null
    const logsQuery = useDepreciationLogs(selectedAsset?.id ?? null, 10)

    useEffect(() => {
        if (runDepreciation.isSuccess && runDepreciation.data) {
            toast.success(
                `Depreciação executada: ${runDepreciation.data.processed_assets} ativos processados e ${runDepreciation.data.skipped_assets} ignorados.`
            )
        }
    }, [runDepreciation.data, runDepreciation.isSuccess])

    return (
        <div className="space-y-6">
            <PageHeader
                title="Depreciação Mensal"
                subtitle="Execute o fechamento mensal e acompanhe os últimos logs gerados."
                icon={<BarChart3 className="h-6 w-6" />}
                actions={[
                    { label: 'Voltar para ativos', href: '/financeiro/ativos', variant: 'outline' },
                ]}
            />

            <div className="grid gap-4 lg:grid-cols-[360px_1fr]">
                <Card>
                    <CardHeader><CardTitle>Executar fechamento</CardTitle></CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={form.handleSubmit(values => void runDepreciation.mutateAsync(values))}>
                            <Input
                                label="Mês de referência"
                                aria-label="Mês de referência"
                                placeholder="2026-03"
                                {...form.register('reference_month')}
                                error={form.formState.errors.reference_month?.message}
                            />
                            <Button type="submit" loading={runDepreciation.isPending} icon={<CalendarSync className="h-4 w-4" />}>
                                Rodar depreciação
                            </Button>
                        </form>

                        {runDepreciation.data && (
                            <div className="mt-6 rounded-[var(--radius-lg)] border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-800">
                                <p className="font-medium">Última execução manual</p>
                                <p className="mt-1">Referência: {runDepreciation.data.reference_month}</p>
                                <p>Processados: {runDepreciation.data.processed_assets}</p>
                                <p>Ignorados: {runDepreciation.data.skipped_assets}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Últimos logs do primeiro ativo ativo</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        {assetsQuery.isLoading ? (
                            <p className="text-sm text-surface-500">Carregando ativos...</p>
                        ) : !selectedAsset ? (
                            <p className="text-sm text-surface-500">Nenhum ativo ativo disponível para inspeção de logs.</p>
                        ) : (
                            <>
                                <div className="rounded-[var(--radius-lg)] border border-surface-100 p-4">
                                    <p className="text-xs uppercase tracking-wide text-surface-500">Ativo monitorado</p>
                                    <p className="mt-1 font-semibold text-surface-900 dark:text-white">
                                        {selectedAsset.code} - {selectedAsset.name}
                                    </p>
                                    <p className="text-sm text-surface-500">
                                        Valor contábil atual: {formatCurrency(selectedAsset.current_book_value)}
                                    </p>
                                </div>
                                {logsQuery.isLoading ? (
                                    <p className="text-sm text-surface-500">Carregando logs...</p>
                                ) : (logsQuery.data?.data.length ?? 0) === 0 ? (
                                    <p className="text-sm text-surface-500">Ainda não há logs de depreciação para o ativo monitorado.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-left text-sm">
                                            <thead className="border-b border-surface-200 text-surface-500">
                                                <tr>
                                                    <th className="px-3 py-3 font-medium">Referência</th>
                                                    <th className="px-3 py-3 font-medium">Valor</th>
                                                    <th className="px-3 py-3 font-medium">Saldo após</th>
                                                    <th className="px-3 py-3 font-medium">CIAP</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {logsQuery.data?.data.map(log => (
                                                    <tr key={log.id} className="border-b border-surface-100 last:border-0">
                                                        <td className="px-3 py-3">{log.reference_month.slice(0, 10)}</td>
                                                        <td className="px-3 py-3">{formatCurrency(log.depreciation_amount)}</td>
                                                        <td className="px-3 py-3">{formatCurrency(log.book_value_after)}</td>
                                                        <td className="px-3 py-3">
                                                            {log.ciap_installment_number
                                                                ? `Parcela ${log.ciap_installment_number} • ${formatCurrency(log.ciap_credit_value ?? 0)}`
                                                                : 'Sem apropriação'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    )
}
