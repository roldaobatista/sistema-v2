import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, HandCoins, Loader2, Plus, Search, X } from 'lucide-react'
import { toast } from 'sonner'
import api, { getApiErrorMessage } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type {
  DebtRenegotiationCustomerOption,
  DebtRenegotiationReceivableOption,
} from '@/types/financial'
import { buildDebtRenegotiationPayload, unwrapDebtRenegotiationPage, unwrapPaginatedResponse } from './debt-renegotiation-utils'
import { debtRenegotiationSchema, type DebtRenegotiationFormData } from './schemas'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { useAuthStore } from '@/stores/auth-store'

const statusLabels: Record<string, { label: string; variant: 'warning' | 'success' | 'destructive' | 'secondary' }> = {
  pending: { label: 'Pendente', variant: 'warning' },
  approved: { label: 'Aprovada', variant: 'success' },
  rejected: { label: 'Rejeitada', variant: 'destructive' },
  completed: { label: 'Concluida', variant: 'secondary' },
  cancelled: { label: 'Cancelada', variant: 'secondary' },
}

const fmtDate = (date: string) => new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR')
const fmt = (value: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
const today = () => new Date().toISOString().slice(0, 10)

const emptyForm: DebtRenegotiationFormData = {
  description: '',
  installments: 1,
  discount_percentage: 0,
  interest_rate: 0,
  new_due_date: '',
  notes: '',
}

export default function DebtRenegotiationPage() {
  const qc = useQueryClient()
  const { hasPermission, hasRole } = useAuthStore()
  const isSuperAdmin = hasRole('super_admin')
  const canView = isSuperAdmin || hasPermission('finance.receivable.view') || hasPermission('financeiro.accounts_receivable.view')
  const canCreate = isSuperAdmin || hasPermission('finance.receivable.create') || hasPermission('financeiro.accounts_receivable.create')
  const canApprove = isSuperAdmin || hasPermission('finance.receivable.update')
  const canUseForm = canView && canCreate

  const [showForm, setShowForm] = useState(false)
  const [customerSearch, setCustomerSearch] = useState('')
  const [selectedCustomerId, setSelectedCustomerId] = useState('')
  const [selectedCustomerName, setSelectedCustomerName] = useState('')
  const [customerDropdownOpen, setCustomerDropdownOpen] = useState(false)
  const [selectedReceivableIds, setSelectedReceivableIds] = useState<number[]>([])

  const form = useForm<DebtRenegotiationFormData>({
    resolver: zodResolver(debtRenegotiationSchema),
    defaultValues: emptyForm,
  })

  const { data: renegotiationsData, isLoading } = useQuery({
    queryKey: ['renegotiations'],
    queryFn: async () => {
      const response = await financialApi.debtRenegotiation.list()
      return unwrapDebtRenegotiationPage(response.data)
    },
    enabled: canView,
  })

  const { data: customersData, isFetching: customersLoading } = useQuery({
    queryKey: ['customers-search', customerSearch],
    queryFn: async () => {
      const response = await api.get<ApiResponse<DebtRenegotiationCustomerOption[]> | { data: DebtRenegotiationCustomerOption[] }>('/financial/lookups/customers', {
        params: { search: customerSearch.trim() || undefined, limit: 30 },
      })
      const payload = response.data
      return 'data' in payload ? payload.data : []
    },
    enabled: showForm && customerDropdownOpen && canUseForm,
  })

  const customerOptions = useMemo<DebtRenegotiationCustomerOption[]>(
    () => customersData ?? [],
    [customersData],
  )

  const { data: receivablesData, isFetching: receivablesLoading } = useQuery({
    queryKey: ['accounts-receivable', selectedCustomerId],
    queryFn: async () => {
      const response = await api.get<ApiResponse<PaginatedResponse<DebtRenegotiationReceivableOption>> | PaginatedResponse<DebtRenegotiationReceivableOption>>('/accounts-receivable', {
        params: {
          customer_id: selectedCustomerId,
          status: 'pending,partial,overdue',
          per_page: 100,
        },
      })
      return unwrapPaginatedResponse(response.data).data
    },
    enabled: showForm && !!selectedCustomerId && canView,
  })

  const receivables = useMemo<DebtRenegotiationReceivableOption[]>(
    () => receivablesData ?? [],
    [receivablesData],
  )

  const selectedTotal = useMemo(
    () => receivables
      .filter((receivable) => selectedReceivableIds.includes(receivable.id))
      .reduce((sum, receivable) => sum + (Number(receivable.amount) - Number(receivable.amount_paid)), 0),
    [receivables, selectedReceivableIds],
  )

  const createMut = useMutation({
    mutationFn: async (data: DebtRenegotiationFormData) => {
      // Manual validation for new_due_date since resolver might not block properly if we don't custom handle it but we check below anyway.
      return financialApi.debtRenegotiation.store(buildDebtRenegotiationPayload({
        customerId: selectedCustomerId,
        receivableIds: selectedReceivableIds,
        form: {
          ...data,
          installments: String(data.installments),
          discount_percentage: String(data.discount_percentage),
          interest_rate: String(data.interest_rate),
        },
      }))
    },
    onSuccess: () => {
      toast.success('Renegociacao criada')
      setShowForm(false)
      setSelectedCustomerId('')
      setSelectedCustomerName('')
      setSelectedReceivableIds([])
      setCustomerSearch('')
      form.reset(emptyForm)
      qc.invalidateQueries({ queryKey: ['renegotiations'] })
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao criar renegociacao')),
  })

  const approveMut = useMutation({
    mutationFn: (id: number) => financialApi.debtRenegotiation.approve(id),
    onSuccess: () => {
      toast.success('Renegociacao aprovada')
      qc.invalidateQueries({ queryKey: ['renegotiations'] })
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao aprovar renegociacao')),
  })

  const cancelMut = useMutation({
    mutationFn: (id: number) => financialApi.debtRenegotiation.cancel(id),
    onSuccess: () => {
      toast.info('Renegociacao cancelada')
      qc.invalidateQueries({ queryKey: ['renegotiations'] })
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao cancelar renegociacao')),
  })

  const onSubmit = form.handleSubmit((data) => {
    if (!selectedCustomerId || selectedReceivableIds.length === 0) {
      toast.error('Selecione o cliente e ao menos um titulo em aberto.')
      return
    }

    if (data.new_due_date <= today()) {
      toast.error('A nova data de vencimento deve ser futura.')
      return
    }

    createMut.mutate(data)
  })

  const toggleReceivable = (id: number) => {
    setSelectedReceivableIds((prev) => (
      prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
    ))
  }

  const selectAllReceivables = () => {
    if (selectedReceivableIds.length === receivables.length) {
      setSelectedReceivableIds([])
      return
    }

    setSelectedReceivableIds(receivables.map((receivable) => receivable.id))
  }

  const resetFormState = (open: boolean) => {
    setShowForm(open)
    if (open) {
      return
    }

    setCustomerSearch('')
    setSelectedCustomerId('')
    setSelectedCustomerName('')
    setSelectedReceivableIds([])
    form.reset(emptyForm)
  }

  const page = renegotiationsData ?? {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null,
    to: null,
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Renegociacao de Dividas" subtitle="Gerencie renegociacoes de parcelas em atraso">
        <Dialog open={showForm} onOpenChange={resetFormState}>
          <DialogTrigger asChild>
            <Button disabled={!canUseForm}><Plus className="mr-1 h-4 w-4" /> Nova Renegociacao</Button>
          </DialogTrigger>
          <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader><DialogTitle>Nova Renegociacao</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <div>
                <label htmlFor="reneg-customer" className="text-sm font-medium">Cliente</label>
                <div className="relative mt-1">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-content-secondary" />
                  <Input
                    id="reneg-customer"
                    placeholder="Buscar cliente por nome..."
                    value={selectedCustomerId ? selectedCustomerName : customerSearch}
                    onChange={(event) => {
                      setCustomerSearch(event.target.value)
                      setCustomerDropdownOpen(true)
                      if (!event.target.value) {
                        setSelectedCustomerId('')
                        setSelectedCustomerName('')
                        setSelectedReceivableIds([])
                      }
                    }}
                    onFocus={() => setCustomerDropdownOpen(true)}
                    onBlur={() => setTimeout(() => setCustomerDropdownOpen(false), 200)}
                    className="pl-9"
                  />
                </div>
                {customerDropdownOpen && !selectedCustomerId ? (
                  <ul className="mt-1 max-h-48 overflow-y-auto rounded-lg border bg-surface-0">
                    {customersLoading ? (
                      <li className="flex items-center gap-2 p-3 text-content-secondary">
                        <Loader2 className="h-4 w-4 animate-spin" /> Carregando...
                      </li>
                    ) : null}
                    {!customersLoading && customerOptions.length === 0 ? (
                      <li className="p-3 text-sm text-content-secondary">Nenhum cliente encontrado.</li>
                    ) : null}
                    {!customersLoading ? customerOptions.map((customer) => (
                      <li
                        key={customer.id}
                        className={`cursor-pointer p-3 hover:bg-surface-100 ${selectedCustomerId === String(customer.id) ? 'bg-brand-50' : ''}`}
                        onClick={() => {
                          setSelectedCustomerId(String(customer.id))
                          setSelectedCustomerName(customer.name)
                          setCustomerSearch('')
                          setSelectedReceivableIds([])
                        }}
                      >
                        <span className="font-medium">{customer.name}</span>
                        {customer.document ? <span className="ml-2 text-xs text-content-secondary">{customer.document}</span> : null}
                      </li>
                    )) : null}
                  </ul>
                ) : null}
              </div>

              {selectedCustomerId ? (
                <div>
                  <div className="mb-2 flex items-center justify-between">
                    <label className="text-sm font-medium">Titulos em aberto do cliente</label>
                    <Button type="button" variant="ghost" size="sm" onClick={selectAllReceivables}>
                      {selectedReceivableIds.length === receivables.length ? 'Desmarcar todos' : 'Selecionar todos'}
                    </Button>
                  </div>
                  {receivablesLoading ? (
                    <div className="flex items-center gap-2 py-4 text-content-secondary">
                      <Loader2 className="h-4 w-4 animate-spin" /> Carregando titulos...
                    </div>
                  ) : receivables.length === 0 ? (
                    <p className="py-2 text-sm text-content-secondary">Nenhum titulo em aberto para este cliente.</p>
                  ) : (
                    <ul className="max-h-48 overflow-y-auto divide-y rounded-lg border">
                      {receivables.map((receivable) => {
                        const remaining = Number(receivable.amount) - Number(receivable.amount_paid)
                        const checked = selectedReceivableIds.includes(receivable.id)

                        return (
                          <li
                            key={receivable.id}
                            className={`flex cursor-pointer items-center gap-3 p-3 hover:bg-surface-50 ${checked ? 'bg-brand-50' : ''}`}
                            onClick={() => toggleReceivable(receivable.id)}
                          >
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={() => toggleReceivable(receivable.id)}
                              onClick={(event) => event.stopPropagation()}
                              className="rounded border-default"
                              aria-label={`Selecionar titulo ${receivable.description || receivable.id}`}
                            />
                            <div className="min-w-0 flex-1">
                              <div className="truncate text-sm font-medium">{receivable.description || '-'}</div>
                              <div className="text-xs text-content-secondary">
                                Venc.: {fmtDate(receivable.due_date)} · {receivable.status}
                              </div>
                            </div>
                            <span className="shrink-0 font-medium">{fmt(remaining)}</span>
                          </li>
                        )
                      })}
                    </ul>
                  )}
                  {selectedReceivableIds.length > 0 ? (
                    <p className="mt-2 text-sm text-content-secondary">
                      Total selecionado: <strong>{fmt(selectedTotal)}</strong> ({selectedReceivableIds.length} titulo(s))
                    </p>
                  ) : null}
                </div>
              ) : null}

              <div>
                <label htmlFor="reneg-description" className="text-sm font-medium">Descricao do acordo</label>
                <Input
                  id="reneg-description"
                  {...form.register('description')}
                  placeholder="Ex.: Acordo comercial para regularizacao da carteira"
                  error={form.formState.errors.description?.message}
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label htmlFor="reneg-installments" className="text-sm font-medium">Numero de parcelas</label>
                  <Input
                    id="reneg-installments"
                    type="number"
                    min={1}
                    max={48}
                    {...form.register('installments')}
                    error={form.formState.errors.installments?.message}
                  />
                </div>
                <div>
                  <label htmlFor="reneg-due-date" className="text-sm font-medium">Primeiro vencimento</label>
                  <Input
                    id="reneg-due-date"
                    type="date"
                    min={today()}
                    {...form.register('new_due_date')}
                    error={form.formState.errors.new_due_date?.message}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label htmlFor="reneg-discount" className="text-sm font-medium">Desconto (%)</label>
                  <Input
                    id="reneg-discount"
                    type="number"
                    step="0.01"
                    min={0}
                    max={100}
                    {...form.register('discount_percentage')}
                    error={form.formState.errors.discount_percentage?.message}
                  />
                </div>
                <div>
                  <label htmlFor="reneg-interest" className="text-sm font-medium">Juros adicionais</label>
                  <Input
                    id="reneg-interest"
                    type="number"
                    step="0.01"
                    min={0}
                    {...form.register('interest_rate')}
                    error={form.formState.errors.interest_rate?.message}
                  />
                </div>
              </div>

              <div>
                <label htmlFor="reneg-notes" className="text-sm font-medium">Observacoes</label>
                <textarea
                  id="reneg-notes"
                  className={`w-full rounded-lg border bg-surface-0 px-3 py-2 text-sm ${form.formState.errors.notes ? 'border-red-500' : 'border-default'}`}
                  {...form.register('notes')}
                  rows={3}
                  placeholder="Contexto comercial, aprovacao interna ou detalhes combinados com o cliente"
                />
                {form.formState.errors.notes && <p className="mt-1 text-xs text-red-500">{form.formState.errors.notes.message}</p>}
              </div>

              <div className="rounded-lg border bg-surface-50 p-3 text-sm text-content-secondary">
                Valor aberto selecionado: <strong>{fmt(selectedTotal)}</strong>. O valor renegociado final sera calculado pelo backend com base no saldo em aberto e no desconto informado.
              </div>

              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => resetFormState(false)}>Cancelar</Button>
                <Button onClick={onSubmit} disabled={createMut.isPending || !selectedCustomerId || selectedReceivableIds.length === 0}>
                  {createMut.isPending ? 'Criando...' : 'Criar Renegociacao'}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </PageHeader>

      {!canView ? (
        <Card>
          <CardContent className="pt-6 text-sm text-content-secondary">
            Voce nao possui permissao para visualizar renegociacoes.
          </CardContent>
        </Card>
      ) : null}

      {canView && !canUseForm ? (
        <Card>
          <CardContent className="pt-6 text-sm text-content-secondary">
            Para criar uma renegociacao por esta tela, o perfil precisa visualizar e criar titulos a receber.
          </CardContent>
        </Card>
      ) : null}

      {canView ? (
        <Card>
          <CardContent className="pt-6">
            {isLoading ? (
              <p className="text-content-secondary">Carregando...</p>
            ) : page.data.length === 0 ? (
              <div className="py-12 text-center text-content-secondary">
                <HandCoins className="mx-auto mb-3 h-12 w-12 opacity-30" />
                <p>Nenhuma renegociacao registrada.</p>
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left">
                    <th className="p-3">#</th>
                    <th className="p-3">Cliente</th>
                    <th className="p-3">Descricao</th>
                    <th className="p-3">Valor Original</th>
                    <th className="p-3">Valor Negociado</th>
                    <th className="p-3">Parcelas</th>
                    <th className="p-3">Status</th>
                    <th className="p-3">Criado em</th>
                    <th className="p-3">Acoes</th>
                  </tr>
                </thead>
                <tbody>
                  {page.data.map((item) => {
                    const status = statusLabels[item.status] ?? statusLabels.pending

                    return (
                      <tr key={item.id} className="border-b hover:bg-surface-50">
                        <td className="p-3">{item.id}</td>
                        <td className="p-3 font-medium">{item.customer?.name ?? '-'}</td>
                        <td className="p-3 text-content-secondary">{item.description || item.notes || '-'}</td>
                        <td className="p-3">{fmt(Number(item.original_total))}</td>
                        <td className="p-3 font-medium">{fmt(Number(item.negotiated_total))}</td>
                        <td className="p-3">{item.new_installments}x</td>
                        <td className="p-3"><Badge variant={status.variant}>{status.label}</Badge></td>
                        <td className="p-3">{new Date(item.created_at).toLocaleDateString('pt-BR')}</td>
                        <td className="p-3">
                          {item.status === 'pending' && canApprove ? (
                            <div className="flex gap-1">
                              <Button size="sm" variant="outline" onClick={() => approveMut.mutate(item.id)}>
                                <Check className="mr-1 h-3 w-3" /> Aprovar
                              </Button>
                              <Button size="sm" variant="ghost" onClick={() => cancelMut.mutate(item.id)}>
                                <X className="mr-1 h-3 w-3" /> Cancelar
                              </Button>
                            </div>
                          ) : null}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            )}
          </CardContent>
        </Card>
      ) : null}
    </div>
  )
}
