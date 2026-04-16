import { useNavigate } from 'react-router-dom'
import { FileText, Plus, RefreshCcw, Calendar, DollarSign, AlertCircle } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api, { unwrapData } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { safeArray } from '@/lib/safe-array'

type ContractSummary = {
    id: number
    status: string
    value?: number | string | null
    name?: string | null
    customer?: { name?: string | null } | null
    frequency?: string | null
}

export function ContractsPage() {
    const navigate = useNavigate()

    const { data: contracts = [], isLoading } = useQuery<ContractSummary[]>({
        queryKey: ['recurring-contracts'],
        queryFn: () => api.get('/recurring-contracts?per_page=50').then(r => safeArray(unwrapData(r)) as ContractSummary[]),
    })

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight flex items-center gap-2">
                        <FileText className="w-5 h-5 text-brand-500" />
                        Contratos
                    </h1>
                    <p className="mt-0.5 text-sm text-surface-500">
                        Gestao de contratos recorrentes e de manutencao
                    </p>
                </div>
                <Button onClick={() => navigate('/os/contratos-recorrentes')}>
                    <RefreshCcw className="w-4 h-4 mr-1" />
                    Contratos Recorrentes
                </Button>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <div className="flex items-center gap-2 mb-2">
                        <FileText className="h-5 w-5 text-brand-500" />
                        <span className="text-xs text-surface-500">Total</span>
                    </div>
                    <p className="text-2xl font-bold text-surface-900">{contracts.length}</p>
                </div>
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <div className="flex items-center gap-2 mb-2">
                        <RefreshCcw className="h-5 w-5 text-emerald-500" />
                        <span className="text-xs text-surface-500">Ativos</span>
                    </div>
                    <p className="text-2xl font-bold text-emerald-600">
                        {contracts.filter((contract) => contract.status === 'active').length}
                    </p>
                </div>
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <div className="flex items-center gap-2 mb-2">
                        <Calendar className="h-5 w-5 text-amber-500" />
                        <span className="text-xs text-surface-500">Vencendo</span>
                    </div>
                    <p className="text-2xl font-bold text-amber-600">
                        {contracts.filter((contract) => contract.status === 'expiring_soon').length}
                    </p>
                </div>
                <div className="rounded-xl border border-default bg-surface-0 p-4 shadow-card">
                    <div className="flex items-center gap-2 mb-2">
                        <DollarSign className="h-5 w-5 text-teal-500" />
                        <span className="text-xs text-surface-500">Valor Mensal</span>
                    </div>
                    <p className="text-2xl font-bold text-teal-600">
                        R$ {contracts.reduce((sum, contract) => sum + Number(contract.value ?? 0), 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                    </p>
                </div>
            </div>

            <div className="rounded-xl border border-default bg-surface-0 shadow-card">
                <div className="border-b border-subtle px-5 py-3 flex items-center justify-between">
                    <h2 className="text-sm font-bold text-surface-900">Lista de Contratos</h2>
                    <Button size="sm" variant="outline" onClick={() => navigate('/os/contratos-recorrentes')}>
                        <Plus className="w-4 h-4 mr-1" /> Novo Contrato
                    </Button>
                </div>

                {isLoading ? (
                    <div className="px-5 py-8 text-center text-surface-400 text-sm">Carregando...</div>
                ) : contracts.length === 0 ? (
                    <div className="flex flex-col items-center py-12 text-surface-500">
                        <AlertCircle className="w-10 h-10 mb-3 opacity-30" />
                        <p className="text-sm">Nenhum contrato cadastrado</p>
                        <Button variant="outline" size="sm" className="mt-3" onClick={() => navigate('/os/contratos-recorrentes')}>
                            <Plus className="w-4 h-4 mr-1" /> Criar primeiro contrato
                        </Button>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-surface-50 text-surface-500">
                                    <th className="px-4 py-2 text-left font-medium">Nome</th>
                                    <th className="px-4 py-2 text-left font-medium">Cliente</th>
                                    <th className="px-4 py-2 text-left font-medium">Frequencia</th>
                                    <th className="px-4 py-2 text-left font-medium">Status</th>
                                    <th className="px-4 py-2 text-left font-medium">Valor</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {contracts.map((contract) => (
                                    <tr key={contract.id} className="hover:bg-surface-50/50 cursor-pointer"
                                        onClick={() => navigate('/os/contratos-recorrentes')}>
                                        <td className="px-4 py-2 font-medium text-surface-900">{contract.name || `Contrato #${contract.id}`}</td>
                                        <td className="px-4 py-2">{contract.customer?.name ?? '—'}</td>
                                        <td className="px-4 py-2 capitalize">{contract.frequency || '—'}</td>
                                        <td className="px-4 py-2">
                                            <Badge variant={contract.status === 'active' ? 'success' : 'default'}>
                                                {contract.status === 'active' ? 'Ativo' : contract.status || '—'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2">
                                            {contract.value ? `R$ ${Number(contract.value).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    )
}

export default ContractsPage
