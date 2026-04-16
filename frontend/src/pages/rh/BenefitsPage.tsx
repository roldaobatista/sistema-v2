import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Search, Filter, Trash2, Edit, AlertCircle, RefreshCw } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { useBenefits, EmployeeBenefit } from '@/hooks/useBenefits'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { format } from 'date-fns'
import { CurrencyInput } from '@/components/common/CurrencyInput'
import { formatCurrency, cn } from '@/lib/utils'
import { z } from 'zod'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import api from '@/lib/api'

const benefitSchema = z.object({
    user_id: z.string().min(1, 'Colaborador é obrigatório'),
    type: z.enum(['vt', 'vr', 'va', 'health', 'dental', 'life_insurance', 'other']),
    provider: z.string().optional(),
    value: z.coerce.number().min(0, 'Valor inválido'),
    employee_contribution: z.coerce.number().min(0, 'Valor inválido'),
    start_date: z.string().min(1, 'A data de início é obrigatória'),
    end_date: z.string().optional(),
    is_active: z.boolean().default(true)
})

type BenefitFormData = z.infer<typeof benefitSchema>

const defaultBenefitVals: BenefitFormData = {
    user_id: '',
    type: 'vt',
    provider: '',
    value: 0,
    employee_contribution: 0,
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    is_active: true
}

export default function BenefitsPage() {
    const { user } = useAuthStore()
    const { benefits: rawBenefits, isLoading, createBenefit, updateBenefit, deleteBenefit } = useBenefits()
    const benefits = rawBenefits as EmployeeBenefit[]
    const [isModalOpen, setIsModalOpen] = useState(false)
    const [editingBenefit, setEditingBenefit] = useState<EmployeeBenefit | null>(null)
    const [searchTerm, setSearchTerm] = useState('')

    const { register, control, handleSubmit, reset, setValue, formState: { errors } } = useForm<BenefitFormData>({
        resolver: zodResolver(benefitSchema),
        defaultValues: defaultBenefitVals
    })
    const { data: usersRes } = useQuery({
        queryKey: ['hr-user-options-benefits'],
        queryFn: () => api.get('/hr/users/options').then(r => r.data),
    })
    const users: { id: number; name: string }[] = Array.isArray(usersRes) ? usersRes : []

    const filteredBenefits = (benefits || []).filter((b: EmployeeBenefit) =>
        (b.user?.name ?? '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        b.type.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (b.provider ?? '').toLowerCase().includes(searchTerm.toLowerCase())
    )

    const totalCost = filteredBenefits?.reduce((acc: number, b: EmployeeBenefit) => acc + Number(b.value), 0) || 0
    const totalContribution = filteredBenefits?.reduce((acc: number, b: EmployeeBenefit) => acc + Number(b.employee_contribution), 0) || 0

    const onSubmit = async (data: BenefitFormData) => {
        try {
            if (editingBenefit) {
                await updateBenefit.mutateAsync({ id: editingBenefit.id, data })
                toast.success('Benefício atualizado com sucesso!')
            } else {
                await createBenefit.mutateAsync(data)
                toast.success('Benefício criado com sucesso!')
            }
            setIsModalOpen(false)
            reset(defaultBenefitVals)
            setEditingBenefit(null)
        } catch (_error) {
            toast.error('Erro ao salvar benefício')
        }
    }

    const handleDelete = async (id: string) => {
        if (confirm('Tem certeza que deseja excluir este benefício?')) {
            try {
                await deleteBenefit.mutateAsync(id)
                toast.success('Benefício excluído com sucesso!')
            } catch (_error) {
                toast.error('Erro ao excluir benefício')
            }
        }
    }

    const openEdit = (benefit: EmployeeBenefit) => {
        setEditingBenefit(benefit)
        reset({
            user_id: String(benefit.user_id),
            type: benefit.type as BenefitFormData['type'],
            provider: benefit.provider || '',
            value: Number(benefit.value),
            employee_contribution: Number(benefit.employee_contribution),
            start_date: benefit.start_date ? String(benefit.start_date).split('T')[0] : '',
            end_date: benefit.end_date ? String(benefit.end_date).split('T')[0] : '',
            is_active: benefit.is_active
        })
        setIsModalOpen(true)
    }

    const openCreate = () => {
        setEditingBenefit(null)
        reset(defaultBenefitVals)
        setIsModalOpen(true)
    }

    const getBenefitLabel = (type: string) => {
        const labels: Record<string, string> = {
            vt: 'Vale Transporte',
            vr: 'Vale Refeição',
            va: 'Vale Alimentação',
            health: 'Plano de Saúde',
            dental: 'Plano Odontológico',
            life_insurance: 'Seguro de Vida',
            other: 'Outros'
        }
        return labels[type] || type
    }

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Gestão de Benefícios</h1>
                    <p className="text-surface-500">Gerencie os benefícios dos colaboradores.</p>
                </div>
                <Button onClick={openCreate} aria-label="Novo Benefício">
                    <Plus className="mr-2 h-4 w-4" /> Novo Benefício
                </Button>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Custo Total Mensal</CardTitle>
                        <AlertCircle className="h-4 w-4 text-surface-400" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {formatCurrency(totalCost)}
                        </div>
                        <p className="text-xs text-surface-500">Valor pago pela empresa</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Coparticipação</CardTitle>
                        <RefreshCw className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {formatCurrency(totalContribution)}
                        </div>
                        <p className="text-xs text-surface-500">Descontado dos colaboradores</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Beneficiários</CardTitle>
                        <Filter className="h-4 w-4 text-surface-400" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{new Set((filteredBenefits || []).map((b: EmployeeBenefit) => b.user_id)).size}</div>
                        <p className="text-xs text-muted-foreground">Colaboradores ativos com benefícios</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle>Benefícios Ativos</CardTitle>
                        <div className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-surface-400" />
                                <Input
                                    placeholder="Buscar por nome ou tipo..."
                                    className="pl-8 w-[250px]"
                                    value={searchTerm}
                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearchTerm(e.target.value)}
                                />
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="rounded-md border border-default">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-surface-50 text-surface-600">
                                <tr>
                                    <th className="p-3 font-medium">Colaborador</th>
                                    <th className="p-3 font-medium">Tipo</th>
                                    <th className="p-3 font-medium">Fornecedor</th>
                                    <th className="p-3 font-medium">Valor</th>
                                    <th className="p-3 font-medium">Coparticipação</th>
                                    <th className="p-3 font-medium">Início</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 font-medium text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {isLoading ? (
                                    <tr><td colSpan={8} className="p-4 text-center">Carregando...</td></tr>
                                ) : filteredBenefits?.length === 0 ? (
                                    <tr><td colSpan={8} className="p-4 text-center text-surface-500">Nenhum benefício encontrado.</td></tr>
                                ) : (
                                    (filteredBenefits || []).map((benefit: EmployeeBenefit) => (
                                        <tr key={benefit.id} className="hover:bg-surface-50/50">
                                            <td className="p-3">{benefit.user?.name || '---'}</td>
                                            <td className="p-3 font-medium">{getBenefitLabel(benefit.type)}</td>
                                            <td className="p-3">{benefit.provider || '-'}</td>
                                            <td className="p-3 text-emerald-600 font-medium">
                                                {formatCurrency(Number(benefit.value))}
                                            </td>
                                            <td className="p-3 text-red-600">
                                                {formatCurrency(Number(benefit.employee_contribution))}
                                            </td>
                                            <td className="p-3">{format(new Date(benefit.start_date), 'dd/MM/yyyy')}</td>
                                            <td className="p-3">
                                                <Badge variant={benefit.is_active ? 'default' : 'secondary'}>
                                                    {benefit.is_active ? 'Ativo' : 'Inativo'}
                                                </Badge>
                                            </td>
                                            <td className="p-3 text-right">
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(benefit)} aria-label="Editar benefício">
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon" className="text-red-500 hover:text-red-600" onClick={() => handleDelete(benefit.id)} aria-label="Excluir benefício">
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingBenefit ? 'Editar Benefício' : 'Novo Benefício'}</DialogTitle>
                        <DialogDescription>Preencha os detalhes do benefício.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                        <div className="space-y-2">
                            <Label>Colaborador *</Label>
                            <select
                                aria-label="Selecionar colaborador"
                                className={cn("w-full rounded-md border bg-surface-0 px-3 py-2 text-sm", errors.user_id ? "border-red-500" : "border-default")}
                                {...register('user_id')}
                            >
                                <option value="">Selecione</option>
                                {(users || []).map((option) => (
                                    <option key={option.id} value={String(option.id)}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            {errors.user_id && <p className="text-xs text-red-500">{errors.user_id.message}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tipo</Label>
                                <select
                                    aria-label="Tipo de benefício"
                                    className="w-full rounded-md border border-default bg-surface-0 px-3 py-2 text-sm"
                                    {...register('type')}
                                >
                                    <option value="vt">Vale Transporte</option>
                                    <option value="vr">Vale Refeição</option>
                                    <option value="va">Vale Alimentação</option>
                                    <option value="health">Plano de Saúde</option>
                                    <option value="dental">Plano Odontológico</option>
                                    <option value="life_insurance">Seguro de Vida</option>
                                    <option value="other">Outros</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label>Fornecedor</Label>
                                <Input {...register('provider')} />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Valor Empresa</Label>
                                <Controller
                                    name="value"
                                    control={control}
                                    render={({ field }) => (
                                        <CurrencyInput value={field.value} onChange={field.onChange} />
                                    )}
                                />
                                {errors.value && <p className="text-xs text-red-500">{errors.value.message}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label>Valor Coparticipação</Label>
                                <Controller
                                    name="employee_contribution"
                                    control={control}
                                    render={({ field }) => (
                                        <CurrencyInput value={field.value} onChange={field.onChange} />
                                    )}
                                />
                                {errors.employee_contribution && <p className="text-xs text-red-500">{errors.employee_contribution.message}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Data Início *</Label>
                                <Input type="date" {...register('start_date')} />
                                {errors.start_date && <p className="text-xs text-red-500">{errors.start_date.message}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label>Data Fim (Opcional)</Label>
                                <Input type="date" {...register('end_date')} />
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <input type="checkbox" {...register('is_active')} id="is_active" aria-label="Benefício ativo" />
                            <Label htmlFor="is_active">Ativo?</Label>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={createBenefit.isPending || updateBenefit.isPending}>
                                {createBenefit.isPending || updateBenefit.isPending ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    )
}
