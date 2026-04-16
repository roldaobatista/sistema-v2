import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, ArrowRight, Check, Merge, Search } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Modal } from '@/components/ui/modal'
import { getApiErrorMessage } from '@/lib/api'
import { customerApi } from '@/lib/customer-api'
import { queryKeys } from '@/lib/query-keys'
import { useAuthStore } from '@/stores/auth-store'
import type { CustomerDuplicateGroup } from '@/types/customer'

export function CustomerMergePage() {
    const { hasPermission } = useAuthStore()
    const canManageMerge = hasPermission('cadastros.customer.update')

    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const [searchType, setSearchType] = useState<'name' | 'document' | 'email'>('name')
    const [selectedGroup, setSelectedGroup] = useState<CustomerDuplicateGroup | null>(null)
    const [primaryId, setPrimaryId] = useState<number | null>(null)
    const [selectedDuplicates, setSelectedDuplicates] = useState<number[]>([])
    const [showConfirmMerge, setShowConfirmMerge] = useState(false)

    const { data: duplicates, isLoading, refetch } = useQuery<CustomerDuplicateGroup[]>({
        queryKey: queryKeys.customers.duplicates(searchType),
        queryFn: () => customerApi.searchDuplicates(searchType),
        enabled: canManageMerge,
    })

    const mergeMutation = useMutation({
        mutationFn: (data: { primary_id: number; duplicate_ids: number[] }) => customerApi.merge(data),
        onSuccess: (res) => {
            toast.success((res as { data?: { message?: string } }).data?.message ?? 'Fusao realizada.')
            setSelectedGroup(null)
            setPrimaryId(null)
            setSelectedDuplicates([])
            setShowConfirmMerge(false)
            queryClient.invalidateQueries({ queryKey: queryKeys.customers.duplicates(searchType) })
            queryClient.invalidateQueries({ queryKey: queryKeys.customers.all })
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao realizar a fusao de clientes.'))
            setShowConfirmMerge(false)
        },
    })

    useEffect(() => {
        setSelectedGroup(null)
        setPrimaryId(null)
        setSelectedDuplicates([])
        setShowConfirmMerge(false)
    }, [searchType])

    const handleSelectGroup = (group: CustomerDuplicateGroup) => {
        setSelectedGroup(group)
        const sorted = [...group.customers].sort(
            (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
        )
        setPrimaryId(sorted[0]?.id ?? null)
        setSelectedDuplicates(sorted.slice(1).map((customer) => customer.id))
    }

    const handleMerge = () => {
        if (!primaryId || selectedDuplicates.length === 0) return
        setShowConfirmMerge(true)
    }

    const confirmMerge = () => {
        if (!primaryId || selectedDuplicates.length === 0) return
        mergeMutation.mutate({
            primary_id: primaryId,
            duplicate_ids: selectedDuplicates,
        })
    }

    if (!canManageMerge) {
        return (
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold tracking-tight text-surface-900">Fusao de Clientes Duplicados</h2>
                    <Button variant="outline" onClick={() => navigate('/cadastros/clientes')}>
                        Voltar para Clientes
                    </Button>
                </div>

                <div className="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                    Voce nao tem permissao para mesclar clientes. Solicite o acesso `cadastros.customer.update`.
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <h1 className="text-lg font-semibold tracking-tight text-surface-900">Fusao de Clientes Duplicados</h1>
                <Button variant="outline" onClick={() => navigate('/cadastros/clientes')}>
                    Voltar para Clientes
                </Button>
            </div>

            <div className="rounded-lg border border-surface-200 bg-surface-0 p-4 shadow">
                <div className="flex items-center gap-4">
                    <span className="text-[13px] font-medium text-surface-700">Buscar duplicatas por:</span>
                    <div className="flex gap-2">
                        <Button variant={searchType === 'name' ? 'primary' : 'outline'} onClick={() => setSearchType('name')} size="sm">
                            Nome
                        </Button>
                        <Button variant={searchType === 'document' ? 'primary' : 'outline'} onClick={() => setSearchType('document')} size="sm">
                            CPF/CNPJ
                        </Button>
                        <Button variant={searchType === 'email' ? 'primary' : 'outline'} onClick={() => setSearchType('email')} size="sm">
                            E-mail
                        </Button>
                    </div>
                    <Button variant="ghost" onClick={() => refetch()} className="ml-auto">
                        <Search className="mr-2 h-4 w-4" />
                        Atualizar Busca
                    </Button>
                </div>
            </div>

            {isLoading ? (
                <div className="py-8 text-center">Carregando duplicatas...</div>
            ) : (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-1">
                        <h2 className="text-lg font-semibold text-surface-800">Grupos Encontrados ({duplicates?.length || 0})</h2>
                        <div className="space-y-3">
                            {(duplicates ?? []).map((group, idx) => (
                                <div
                                    key={`${group.key}-${idx}`}
                                    onClick={() => handleSelectGroup(group)}
                                    className={`cursor-pointer rounded-lg border p-4 transition-colors ${
                                        selectedGroup === group
                                            ? 'border-brand-500 bg-brand-50'
                                            : 'border-surface-200 bg-surface-0 hover:bg-surface-50'
                                    }`}
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="font-medium text-surface-900">{group.key || '(Vazio)'}</p>
                                            <p className="text-[13px] text-surface-500">{group.count} registros</p>
                                        </div>
                                        <ArrowRight
                                            className={`h-5 w-5 ${selectedGroup === group ? 'text-brand-600' : 'text-surface-300'}`}
                                        />
                                    </div>
                                </div>
                            ))}
                            {duplicates?.length === 0 && (
                                <p className="text-sm text-surface-500">Nenhuma duplicata encontrada com este criterio.</p>
                            )}
                        </div>
                    </div>

                    <div className="lg:col-span-2">
                        {selectedGroup ? (
                            <div className="space-y-5 rounded-lg border border-surface-200 bg-surface-0 p-6 shadow">
                                <div>
                                    <h2 className="flex items-center text-[15px] font-semibold tabular-nums text-surface-900">
                                        <Merge className="mr-2 h-5 w-5 text-brand-600" />
                                        Configurar Fusao
                                    </h2>
                                    <p className="mt-1 text-[13px] text-surface-500">
                                        Selecione qual sera o cliente <strong>Principal</strong> e quais serao <strong>Mesclados</strong>.
                                    </p>
                                </div>

                                <div className="space-y-4">
                                    {selectedGroup.customers.map((customer) => (
                                        <div
                                            key={customer.id}
                                            className={`flex items-center rounded-md border p-4 ${
                                                primaryId === customer.id
                                                    ? 'border-emerald-500 bg-emerald-50'
                                                    : selectedDuplicates.includes(customer.id)
                                                        ? 'border-amber-300 bg-amber-50'
                                                        : 'border-surface-200'
                                            }`}
                                        >
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold text-surface-900">{customer.name}</span>
                                                    <span className="text-xs text-surface-500">#{customer.id}</span>
                                                </div>
                                                <div className="mt-1 space-x-3 text-[13px] text-surface-600">
                                                    <span>{customer.document || 'Sem documento'}</span>
                                                    <span>•</span>
                                                    <span>{customer.email || 'Sem e-mail'}</span>
                                                </div>
                                                <div className="mt-1 text-xs text-surface-400">
                                                    Criado em: {new Date(customer.created_at).toLocaleDateString()}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Button
                                                    size="sm"
                                                    variant={primaryId === customer.id ? 'success' : 'outline'}
                                                    onClick={() => {
                                                        setPrimaryId(customer.id)
                                                        setSelectedDuplicates(
                                                            selectedGroup.customers.filter((item) => item.id !== customer.id).map((item) => item.id)
                                                        )
                                                    }}
                                                >
                                                    {primaryId === customer.id ? <Check className="mr-1 h-4 w-4" /> : null}
                                                    {primaryId === customer.id ? 'Principal' : 'Definir Principal'}
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div className="flex items-start rounded-md bg-blue-50 p-4">
                                    <AlertTriangle className="mr-3 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600" />
                                    <div className="text-sm text-blue-800">
                                        <p className="font-medium">O que acontece na fusao?</p>
                                        <ul className="ml-4 mt-1 list-disc space-y-1">
                                            <li>Relacionamentos dos clientes duplicados sao movidos para o principal.</li>
                                            <li>As anotacoes sao concatenadas no historico do principal.</li>
                                            <li>Os clientes duplicados vao para soft delete e a timeline recebe auditoria automatica.</li>
                                        </ul>
                                    </div>
                                </div>

                                <div className="flex justify-end border-t border-subtle pt-4">
                                    <Button
                                        variant="primary"
                                        size="lg"
                                        onClick={handleMerge}
                                        disabled={!primaryId || selectedDuplicates.length === 0}
                                        className="bg-brand-600 hover:bg-brand-700"
                                    >
                                        Confirmar e Mesclar Clientes
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="flex h-full flex-col items-center justify-center rounded-lg border-2 border-dashed border-surface-200 p-12 text-surface-400">
                                <Merge className="mb-4 h-12 w-12 opacity-20" />
                                <p>Selecione um grupo de duplicatas ao lado para iniciar a fusao.</p>
                            </div>
                        )}
                    </div>
                </div>
            )}

            <Modal open={showConfirmMerge} onOpenChange={setShowConfirmMerge} size="sm" title="Confirmar Fusao">
                <div className="space-y-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <AlertTriangle className="h-5 w-5 text-amber-600" />
                        </div>
                        <div>
                            <h3 className="font-medium text-surface-900">Acao irreversivel</h3>
                            <p className="text-sm text-surface-500">
                                Deseja realmente mesclar <strong>{selectedDuplicates.length}</strong> cliente(s) no cliente <strong>#{primaryId}</strong>?
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg border border-amber-100 bg-amber-50 p-3 text-sm text-amber-700">
                        <p>Esta acao <strong>nao pode ser desfeita</strong>. Todos os dados dos clientes duplicados serao transferidos para o cliente principal.</p>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" onClick={() => setShowConfirmMerge(false)}>
                            Cancelar
                        </Button>
                        <Button className="bg-brand-600 text-white hover:bg-brand-700" loading={mergeMutation.isPending} onClick={confirmMerge}>
                            Confirmar Fusao
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    )
}
