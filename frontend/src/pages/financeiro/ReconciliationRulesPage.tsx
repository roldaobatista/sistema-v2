import { useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  ArrowUpDown,
  Beaker,
  Check,
  Edit,
  Loader2,
  Plus,
  Power,
  PowerOff,
  Search,
  Trash2,
  X,
  Zap,
} from 'lucide-react'
import { toast } from 'sonner'
import { getApiErrorMessage, unwrapData } from '@/lib/api'
import { financialApi } from '@/lib/financial-api'
import { queryKeys } from '@/lib/query-keys'
import { reconciliationRuleSchema, type ReconciliationRuleForm } from './schemas'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { useAuthStore } from '@/stores/auth-store'
import type { PaginatedResponse } from '@/types/api'
import type { ReconciliationRule, ReconciliationTestResult } from '@/types/financial'

const MATCH_FIELDS = [
  { value: 'description', label: 'Descricao' },
  { value: 'amount', label: 'Valor' },
  { value: 'cnpj', label: 'CNPJ' },
  { value: 'combined', label: 'Combinado (Desc + Valor)' },
]

const MATCH_OPERATORS = [
  { value: 'contains', label: 'Contem' },
  { value: 'equals', label: 'Igual a' },
  { value: 'regex', label: 'Regex' },
  { value: 'between', label: 'Entre (valores)' },
]

const ACTIONS = [
  { value: 'match_receivable', label: 'Conciliar com A/R', color: 'text-green-600 dark:text-green-400' },
  { value: 'match_payable', label: 'Conciliar com A/P', color: 'text-blue-600 dark:text-blue-400' },
  { value: 'ignore', label: 'Ignorar', color: 'text-yellow-600 dark:text-yellow-400' },
  { value: 'categorize', label: 'Categorizar', color: 'text-teal-600 dark:text-teal-400' },
]

const emptyForm: ReconciliationRuleForm = {
  name: '',
  match_field: 'description',
  match_operator: 'contains',
  match_value: '',
  match_amount_min: '',
  match_amount_max: '',
  action: 'categorize',
  category: '',
  priority: '50',
  is_active: true,
}

type ReconciliationRulePayload = {
  name: string
  match_field: string
  match_operator: string
  match_value: string | null
  match_amount_min?: number
  match_amount_max?: number
  action: string
  category: string | null
  priority: number
  is_active: boolean
}

export function ReconciliationRulesPage() {
  const { hasPermission, hasRole } = useAuthStore()
  const isSuperAdmin = hasRole('super_admin')
  const canView = isSuperAdmin || hasPermission('finance.receivable.view') || hasPermission('finance.payable.view')
  const canManage = isSuperAdmin || hasPermission('finance.receivable.create') || hasPermission('finance.payable.create')
  const canDelete = isSuperAdmin || hasPermission('finance.receivable.delete') || hasPermission('finance.payable.delete')

  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)

  const form = useForm<ReconciliationRuleForm>({
    resolver: zodResolver(reconciliationRuleSchema),
    defaultValues: emptyForm,
  })

  const [testResult, setTestResult] = useState<ReconciliationTestResult | null>(null)
  const [showTestPanel, setShowTestPanel] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<ReconciliationRule | null>(null)

  const { data: rulesPage, isLoading } = useQuery({
    queryKey: queryKeys.financial.reconciliationRules({ search: search || undefined }),
    queryFn: async () => {
      const response = await financialApi.reconciliationRules.list({ search: search || undefined })
      return unwrapData<PaginatedResponse<ReconciliationRule>>(response)
    },
    enabled: canView,
  })

  const rules = rulesPage?.data ?? []

  const storeMutation = useMutation({
    mutationFn: (payload: ReconciliationRulePayload) => financialApi.reconciliationRules.create(payload),
    onSuccess: () => {
      toast.success(editingId ? 'Regra atualizada' : 'Regra criada')
      queryClient.invalidateQueries({ queryKey: ['reconciliation-rules'] })
      resetForm()
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao salvar regra')),
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: ReconciliationRulePayload }) =>
      financialApi.reconciliationRules.update(id, payload),
    onSuccess: () => {
      toast.success('Regra atualizada')
      queryClient.invalidateQueries({ queryKey: ['reconciliation-rules'] })
      resetForm()
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao atualizar regra')),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => financialApi.reconciliationRules.destroy(id),
    onSuccess: () => {
      toast.success('Regra excluida')
      queryClient.invalidateQueries({ queryKey: ['reconciliation-rules'] })
      setDeleteTarget(null)
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao excluir regra')),
  })

  const toggleMutation = useMutation({
    mutationFn: (id: number) => financialApi.reconciliationRules.toggle(id),
    onSuccess: () => {
      toast.success('Status alterado')
      queryClient.invalidateQueries({ queryKey: ['reconciliation-rules'] })
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao alternar regra')),
  })

  const testMutation = useMutation({
    mutationFn: (payload: Pick<ReconciliationRulePayload, 'match_field' | 'match_operator' | 'match_value' | 'match_amount_min' | 'match_amount_max'>) =>
      financialApi.reconciliationRules.test(payload),
    onSuccess: (response) => {
      setTestResult(unwrapData<ReconciliationTestResult>(response))
      setShowTestPanel(true)
    },
    onError: (error: unknown) => toast.error(getApiErrorMessage(error, 'Erro ao testar regra')),
  })

  const resetForm = () => {
    form.reset(emptyForm)
    setEditingId(null)
    setShowForm(false)
    setTestResult(null)
    setShowTestPanel(false)
  }

  const startEdit = (rule: ReconciliationRule) => {
    if (!canManage) {
      return
    }

    form.reset({
      name: rule.name,
      match_field: rule.match_field,
      match_operator: rule.match_operator,
      match_value: rule.match_value || '',
      match_amount_min: rule.match_amount_min?.toString() || '',
      match_amount_max: rule.match_amount_max?.toString() || '',
      action: rule.action,
      category: rule.category || '',
      priority: rule.priority.toString(),
      is_active: rule.is_active,
    })
    setEditingId(rule.id)
    setShowForm(true)
  }

  const buildPayload = (data: ReconciliationRuleForm): ReconciliationRulePayload => {
    const payload: ReconciliationRulePayload = {
      name: data.name.trim(),
      match_field: data.match_field,
      match_operator: data.match_operator,
      match_value: data.match_value?.trim() || null,
      action: data.action,
      category: data.category?.trim() || null,
      priority: parseInt(data.priority, 10) || 50,
      is_active: data.is_active,
    }

    if (data.match_amount_min) {
      payload.match_amount_min = parseFloat(data.match_amount_min)
    }

    if (data.match_amount_max) {
      payload.match_amount_max = parseFloat(data.match_amount_max)
    }

    return payload
  }

  const handleSubmit = (data: ReconciliationRuleForm) => {
    const payload = buildPayload(data)
    if (editingId) {
      updateMutation.mutate({ id: editingId, payload })
      return
    }

    storeMutation.mutate(payload)
  }

  const handleTestRule = () => {
    const data = form.getValues()
    const payload = buildPayload(data)
    testMutation.mutate({
      match_field: payload.match_field,
      match_operator: payload.match_operator,
      match_value: payload.match_value,
      match_amount_min: payload.match_amount_min,
      match_amount_max: payload.match_amount_max,
    })
  }

  const isSaving = storeMutation.isPending || updateMutation.isPending
  const inputClasses = 'w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50'
  const labelClasses = 'mb-1 block text-sm font-medium text-content-primary'

  return (
    <div className="space-y-6">
      <PageHeader
        title="Regras de Conciliacao Automatica"
        subtitle="Configure regras para conciliar e categorizar lancamentos automaticamente"
      />

      {!canView ? (
        <EmptyState
          icon={Zap}
          title="Sem permissao para visualizar regras"
          description="Seu perfil precisa da permissao de visualizacao do financeiro para consultar as regras de conciliacao."
        />
      ) : null}

      {canView ? (
        <div className="flex items-center justify-between gap-4">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-content-secondary" />
            <Input
              type="text"
              aria-label="Buscar regras"
              placeholder="Buscar regras..."
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="pl-10"
            />
          </div>
          {canManage ? (
            <Button onClick={() => { resetForm(); setShowForm(true) }} className="flex items-center gap-2">
              <Plus className="h-4 w-4" />
              Nova Regra
            </Button>
          ) : null}
        </div>
      ) : null}

      {showForm && canManage ? (
        <div className="rounded-xl border border-default bg-surface-0 p-6 shadow-card">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="text-lg font-semibold">{editingId ? 'Editar Regra' : 'Nova Regra'}</h3>
            <button onClick={resetForm} className="text-content-secondary hover:text-content-primary" aria-label="Fechar formulario">
              <X className="h-5 w-5" />
            </button>
          </div>

          <form onSubmit={form.handleSubmit(handleSubmit)} className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Controller control={form.control} name="name" render={({ field, fieldState }) => (
              <div className="lg:col-span-2">
                <label className={labelClasses}>Nome da Regra</label>
                <input
                  type="text"
                  {...field}
                  placeholder="Ex: PIX Recebidos - Cliente XYZ"
                  className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}
                />
                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
              </div>
            )} />

            <Controller control={form.control} name="priority" render={({ field, fieldState }) => (
              <div>
                <label className={labelClasses}>Prioridade (1-100)</label>
                <input
                  type="number"
                  {...field}
                  min="1"
                  max="100"
                  className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}
                />
                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
              </div>
            )} />

            <Controller control={form.control} name="match_field" render={({ field, fieldState }) => (
              <div>
                <label className={labelClasses}>Campo de Matching</label>
                <select {...field} className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}>
                  {MATCH_FIELDS.map((item) => (
                    <option key={item.value} value={item.value}>{item.label}</option>
                  ))}
                </select>
                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
              </div>
            )} />

            <Controller control={form.control} name="match_operator" render={({ field, fieldState }) => (
              <div>
                <label className={labelClasses}>Operador</label>
                <select {...field} className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}>
                  {MATCH_OPERATORS.map((item) => (
                    <option key={item.value} value={item.value}>{item.label}</option>
                  ))}
                </select>
                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
              </div>
            )} />

            <Controller control={form.control} name="match_value" render={({ field, fieldState }) => (
              <div>
                <label className={labelClasses}>Valor/Padrao</label>
                <input
                  type="text"
                  {...field}
                  value={field.value || ''}
                  placeholder={form.watch('match_operator') === 'regex' ? '^PIX.*RECEBIDO' : 'texto para buscar'}
                  className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}
                />
                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
              </div>
            )} />

            {(form.watch('match_field') === 'amount' || form.watch('match_field') === 'combined') ? (
              <>
                <Controller control={form.control} name="match_amount_min" render={({ field, fieldState }) => (
                  <div>
                    <label className={labelClasses}>Valor Minimo</label>
                    <input
                      type="number"
                      step="0.01"
                      {...field}
                      value={field.value || ''}
                      className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}
                    />
                    {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                  </div>
                )} />
                <Controller control={form.control} name="match_amount_max" render={({ field, fieldState }) => (
                  <div>
                    <label className={labelClasses}>Valor Maximo</label>
                    <input
                      type="number"
                      step="0.01"
                      {...field}
                      value={field.value || ''}
                      className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}
                    />
                    {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
                  </div>
                )} />
              </>
            ) : null}

            <Controller control={form.control} name="action" render={({ field, fieldState }) => (
              <div>
                <label className={labelClasses}>Acao</label>
                <select {...field} className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}>
                  {ACTIONS.map((item) => (
                    <option key={item.value} value={item.value}>{item.label}</option>
                  ))}
                </select>
                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
              </div>
            )} />

            <Controller control={form.control} name="category" render={({ field, fieldState }) => (
              <div>
                <label className={labelClasses}>Categoria</label>
                <input
                  type="text"
                  {...field}
                  value={field.value || ''}
                  placeholder="Ex: Receita PIX, Tarifa Bancaria"
                  className={cn(inputClasses, fieldState.error && 'border-red-500 focus:ring-red-500/50')}
                />
                {fieldState.error?.message ? <p className="mt-1 text-xs text-red-500">{fieldState.error.message}</p> : null}
              </div>
            )} />

            <Controller control={form.control} name="is_active" render={({ field }) => (
              <div className="flex items-center gap-3 pt-6">
                <label className="relative inline-flex cursor-pointer items-center">
                  <input
                    type="checkbox"
                    checked={field.value}
                    onChange={(event) => field.onChange(event.target.checked)}
                    className="peer sr-only"
                  />
                  <div className="h-5 w-9 rounded-full bg-surface-300 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-green-500 peer-checked:after:translate-x-full" />
                </label>
                <span className="text-sm text-content-secondary">{field.value ? 'Ativa' : 'Inativa'}</span>
              </div>
            )} />

            <div className="flex items-center gap-3 border-t border-default pt-4 lg:col-span-3">
              <Button type="submit" disabled={isSaving} className="flex items-center gap-2">
                {isSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
                {editingId ? 'Atualizar' : 'Criar'} Regra
              </Button>

              <Button type="button" variant="outline" onClick={handleTestRule} disabled={testMutation.isPending} className="flex items-center gap-2">
                {testMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Beaker className="h-4 w-4" />}
                Testar Regra
              </Button>

              <Button type="button" variant="ghost" onClick={resetForm}>Cancelar</Button>
            </div>
          </form>

          {showTestPanel && testResult ? (
            <div className="mt-4 rounded-lg border border-default bg-surface-50 p-4">
              <h4 className="mb-2 flex items-center gap-2 text-sm font-semibold">
                <Beaker className="h-4 w-4 text-brand-500" />
                Resultado do Teste
              </h4>
              <div className="mb-3 grid grid-cols-2 gap-4">
                <div className="rounded-lg border border-default bg-surface-0 p-3 text-center">
                  <p className="text-2xl font-bold">{testResult.total_tested}</p>
                  <p className="text-xs text-content-secondary">Testados</p>
                </div>
                <div className="rounded-lg border border-default bg-surface-0 p-3 text-center">
                  <p className={cn('text-2xl font-bold', testResult.total_matched > 0 ? 'text-green-600 dark:text-green-400' : 'text-content-secondary')}>
                    {testResult.total_matched}
                  </p>
                  <p className="text-xs text-content-secondary">Correspondem</p>
                </div>
              </div>
              {testResult.sample.length > 0 ? (
                <div className="space-y-1">
                  <p className="mb-1 text-xs text-content-secondary">Exemplos de correspondencia:</p>
                  {testResult.sample.map((sample) => (
                    <div key={sample.id} className="flex items-center gap-3 rounded border border-default bg-surface-0 px-3 py-1.5 text-xs">
                      <span className={cn('font-mono', sample.type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')}>
                        R$ {sample.amount.toFixed(2)}
                      </span>
                      <span className="flex-1 truncate">{sample.description}</span>
                      <span className="text-content-secondary">{sample.date}</span>
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          ) : null}
        </div>
      ) : null}

      {canView && isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin text-content-secondary" />
        </div>
      ) : null}

      {canView && !isLoading && rules.length === 0 ? (
        <EmptyState
          icon={Zap}
          title="Nenhuma regra configurada"
          description="Crie regras para automatizar a conciliacao de lancamentos bancarios."
          action={canManage ? (
            <Button onClick={() => { resetForm(); setShowForm(true) }} className="flex items-center gap-2">
              <Plus className="h-4 w-4" />
              Criar Primeira Regra
            </Button>
          ) : undefined}
        />
      ) : null}

      {canView && !isLoading && rules.length > 0 ? (
        <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
          <table className="w-full">
            <thead>
              <tr className="border-b border-default">
                <th className="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">Status</th>
                <th className="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">
                  <span className="flex items-center gap-1"><ArrowUpDown className="h-3 w-3" /> Pri</span>
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">Nome</th>
                <th className="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">Matching</th>
                <th className="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">Acao</th>
                <th className="px-4 py-3 text-left text-xs font-medium uppercase text-content-secondary">Usos</th>
                <th className="px-4 py-3 text-right text-xs font-medium uppercase text-content-secondary">Acoes</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-default">
              {rules.map((rule) => {
                const actionMeta = ACTIONS.find((item) => item.value === rule.action)
                const fieldMeta = MATCH_FIELDS.find((item) => item.value === rule.match_field)
                const operatorMeta = MATCH_OPERATORS.find((item) => item.value === rule.match_operator)

                return (
                  <tr key={rule.id} className={cn('transition-colors hover:bg-surface-50', !rule.is_active && 'opacity-50')}>
                    <td className="px-4 py-3">
                      <button
                        disabled={!canManage}
                        onClick={() => toggleMutation.mutate(rule.id)}
                        aria-label={rule.is_active ? `Desativar regra ${rule.name}` : `Ativar regra ${rule.name}`}
                        className={cn(
                          'rounded-lg p-1.5 transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                          rule.is_active
                            ? 'bg-green-100 text-green-600 hover:bg-green-200 dark:bg-green-500/20 dark:text-green-400 dark:hover:bg-green-500/30'
                            : 'bg-surface-100 text-content-secondary hover:bg-surface-200',
                        )}
                        title={rule.is_active ? 'Desativar' : 'Ativar'}
                      >
                        {rule.is_active ? <Power className="h-4 w-4" /> : <PowerOff className="h-4 w-4" />}
                      </button>
                    </td>
                    <td className="px-4 py-3"><span className="text-sm font-mono">{rule.priority}</span></td>
                    <td className="px-4 py-3">
                      <div>
                        <p className="text-sm font-medium">{rule.name}</p>
                        {rule.category ? (
                          <span className="mt-1 inline-flex rounded-full bg-teal-100 px-2 py-0.5 text-xs text-teal-700 dark:bg-teal-500/20 dark:text-teal-300">
                            {rule.category}
                          </span>
                        ) : null}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="space-y-0.5 text-xs text-content-secondary">
                        <p>{fieldMeta?.label} {operatorMeta?.label}</p>
                        {rule.match_value ? <p className="max-w-[200px] truncate font-mono">"{rule.match_value}"</p> : null}
                        {rule.match_amount_min != null && rule.match_amount_max != null ? (
                          <p className="font-mono">R$ {rule.match_amount_min} ~ R$ {rule.match_amount_max}</p>
                        ) : null}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <span className={cn('text-sm font-medium', actionMeta?.color)}>{actionMeta?.label}</span>
                    </td>
                    <td className="px-4 py-3"><span className="text-sm">{rule.times_applied}x</span></td>
                    <td className="px-4 py-3">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          disabled={!canManage}
                          onClick={() => startEdit(rule)}
                          aria-label={`Editar regra ${rule.name}`}
                          className="p-1.5 text-content-secondary transition-colors hover:text-brand-500 disabled:cursor-not-allowed disabled:opacity-50"
                          title="Editar"
                        >
                          <Edit className="h-4 w-4" />
                        </button>
                        <button
                          disabled={!canDelete}
                          onClick={() => setDeleteTarget(rule)}
                          aria-label={`Excluir regra ${rule.name}`}
                          className="p-1.5 text-content-secondary transition-colors hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                          title="Excluir"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      ) : null}

      <Modal open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)} title="Excluir Regra" size="sm">
        <p className="text-sm text-content-secondary">
          Tem certeza que deseja excluir a regra <strong>{deleteTarget?.name}</strong>? Esta acao nao pode ser desfeita.
        </p>
        <div className="flex justify-end gap-2 pt-4">
          <Button variant="outline" type="button" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
          <Button
            className="bg-red-600 text-white hover:bg-red-700"
            loading={deleteMutation.isPending}
            onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
          >
            Excluir
          </Button>
        </div>
      </Modal>
    </div>
  )
}

export default ReconciliationRulesPage
