import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  DollarSign, Plus, Calculator, CheckCircle2, CreditCard, FileText,
  ChevronDown, ChevronRight, Search, AlertCircle, RefreshCw,
} from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import type { Payroll, PayrollLine } from '@/types/hr'

const PAYROLL_TYPES: Record<string, string> = {
  regular: 'Mensal',
  thirteenth_first: '13o - 1a Parcela',
  thirteenth_second: '13o - 2a Parcela',
  vacation: 'Ferias',
  rescission: 'Rescisao',
  advance: 'Adiantamento',
}

const STATUS_MAP: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
  draft: { label: 'Rascunho', variant: 'secondary' },
  calculated: { label: 'Calculada', variant: 'default' },
  approved: { label: 'Aprovada', variant: 'outline' },
  paid: { label: 'Paga', variant: 'default' },
  cancelled: { label: 'Cancelada', variant: 'destructive' },
}

function formatCurrency(value: number | string): string {
  return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatMonth(ym: string): string {
  const [y, m] = ym.split('-')
  const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']
  return `${months[parseInt(m, 10) - 1]}/${y}`
}

export default function PayrollPage() {
  const { hasPermission } = useAuthStore()
  const qc = useQueryClient()
  const canManage = hasPermission('hr.payroll.manage')

  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [typeFilter, setTypeFilter] = useState('')
  const [isCreateOpen, setIsCreateOpen] = useState(false)
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [newMonth, setNewMonth] = useState(() => {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
  })
  const [newType, setNewType] = useState('regular')
  const [newNotes, setNewNotes] = useState('')

  // Fetch payrolls
  const { data: payrolls, isLoading, error } = useQuery({
    queryKey: ['payrolls', statusFilter, typeFilter],
    queryFn: async () => {
      const params: Record<string, unknown> = { per_page: 50 }
      if (statusFilter) params.status = statusFilter
      if (typeFilter) params.type = typeFilter
      const res = await api.get('/hr/payroll', { params })
      return unwrapData<Payroll[]>(res)
    },
  })

  // Fetch single payroll with lines (for expanded view)
  const { data: expandedPayroll } = useQuery({
    queryKey: ['payroll', expandedId],
    queryFn: async () => {
      if (!expandedId) return null
      const res = await api.get(`/hr/payroll/${expandedId}`)
      return unwrapData<Payroll>(res)
    },
    enabled: !!expandedId,
  })

  // Mutations
  const createMut = useMutation({
    mutationFn: async () => {
      const res = await api.post('/hr/payroll', {
        reference_month: newMonth,
        type: newType,
        notes: newNotes || undefined,
      })
      return unwrapData<Payroll>(res)
    },
    onSuccess: () => {
      toast.success('Folha de pagamento criada com sucesso.')
      qc.invalidateQueries({ queryKey: ['payrolls'] })
      setIsCreateOpen(false)
      setNewNotes('')
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao criar folha.')),
  })

  const calculateMut = useMutation({
    mutationFn: (id: number) => api.post(`/hr/payroll/${id}/calculate`),
    onSuccess: () => {
      toast.success('Folha calculada com sucesso.')
      qc.invalidateQueries({ queryKey: ['payrolls'] })
      qc.invalidateQueries({ queryKey: ['payroll', expandedId] })
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao calcular.')),
  })

  const approveMut = useMutation({
    mutationFn: (id: number) => api.post(`/hr/payroll/${id}/approve`),
    onSuccess: () => {
      toast.success('Folha aprovada.')
      qc.invalidateQueries({ queryKey: ['payrolls'] })
      qc.invalidateQueries({ queryKey: ['payroll', expandedId] })
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao aprovar.')),
  })

  const markPaidMut = useMutation({
    mutationFn: (id: number) => api.post(`/hr/payroll/${id}/mark-paid`),
    onSuccess: () => {
      toast.success('Folha marcada como paga.')
      qc.invalidateQueries({ queryKey: ['payrolls'] })
      qc.invalidateQueries({ queryKey: ['payroll', expandedId] })
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao marcar como paga.')),
  })

  const genPayslipsMut = useMutation({
    mutationFn: (id: number) => api.post(`/hr/payroll/${id}/generate-payslips`),
    onSuccess: () => {
      toast.success('Holerites gerados com sucesso.')
      qc.invalidateQueries({ queryKey: ['payroll', expandedId] })
    },
    onError: (err) => toast.error(getApiErrorMessage(err, 'Erro ao gerar holerites.')),
  })

  const filtered = (payrolls ?? []).filter((p) => {
    if (search) {
      const s = search.toLowerCase()
      return p.reference_month.includes(s) || PAYROLL_TYPES[p.type]?.toLowerCase().includes(s)
    }
    return true
  })

  if (error) {
    return (
      <div className="flex items-center gap-2 text-red-500 p-8">
        <AlertCircle className="h-5 w-5" />
        <span>Erro ao carregar folhas de pagamento.</span>
      </div>
    )
  }

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
            <DollarSign className="h-6 w-6" /> Folha de Pagamento
          </h1>
          <p className="text-muted-foreground text-sm mt-1">
            Gerencie folhas de pagamento, calcule encargos e gere holerites.
          </p>
        </div>
        {canManage && (
          <Button onClick={() => setIsCreateOpen(true)}>
            <Plus className="h-4 w-4 mr-2" /> Nova Folha
          </Button>
        )}
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-4">
          <div className="flex flex-wrap gap-3 items-center">
            <div className="relative flex-1 min-w-[200px]">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Buscar por mes ou tipo..."
                className="pl-10"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <select
              className="rounded-md border border-default bg-surface-0 px-3 py-2 text-sm"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              aria-label="Filtrar por status"
            >
              <option value="">Todos os Status</option>
              <option value="draft">Rascunho</option>
              <option value="calculated">Calculada</option>
              <option value="approved">Aprovada</option>
              <option value="paid">Paga</option>
              <option value="cancelled">Cancelada</option>
            </select>
            <select
              className="rounded-md border border-default bg-surface-0 px-3 py-2 text-sm"
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              aria-label="Filtrar por tipo"
            >
              <option value="">Todos os Tipos</option>
              {Object.entries(PAYROLL_TYPES).map(([k, v]) => (
                <option key={k} value={k}>{v}</option>
              ))}
            </select>
          </div>
        </CardContent>
      </Card>

      {/* Payroll List */}
      <Card>
        <CardHeader>
          <CardTitle>Folhas de Pagamento</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex items-center justify-center py-12 text-muted-foreground">
              <RefreshCw className="h-5 w-5 animate-spin mr-2" /> Carregando...
            </div>
          ) : filtered.length === 0 ? (
            <p className="text-center text-muted-foreground py-8">Nenhuma folha encontrada.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="py-3 px-2 w-8"></th>
                    <th className="py-3 px-2">Referencia</th>
                    <th className="py-3 px-2">Tipo</th>
                    <th className="py-3 px-2">Status</th>
                    <th className="py-3 px-2 text-right">Bruto</th>
                    <th className="py-3 px-2 text-right">Liquido</th>
                    <th className="py-3 px-2 text-right">Funcionarios</th>
                    <th className="py-3 px-2">Acoes</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((p) => {
                    const isExpanded = expandedId === p.id
                    const st = STATUS_MAP[p.status] ?? STATUS_MAP.draft
                    return (
                      <PayrollRow
                        key={p.id}
                        payroll={p}
                        status={st}
                        isExpanded={isExpanded}
                        expandedPayroll={isExpanded ? expandedPayroll : null}
                        canManage={canManage}
                        onToggle={() => setExpandedId(isExpanded ? null : p.id)}
                        onCalculate={() => calculateMut.mutate(p.id)}
                        onApprove={() => approveMut.mutate(p.id)}
                        onMarkPaid={() => markPaidMut.mutate(p.id)}
                        onGenPayslips={() => genPayslipsMut.mutate(p.id)}
                        isCalculating={calculateMut.isPending}
                        isApproving={approveMut.isPending}
                        isMarkingPaid={markPaidMut.isPending}
                        isGenPayslips={genPayslipsMut.isPending}
                      />
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Create Dialog */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Nova Folha de Pagamento</DialogTitle>
            <DialogDescription>Selecione o mes de referencia e o tipo da folha.</DialogDescription>
          </DialogHeader>
          <form
            onSubmit={(e) => {
              e.preventDefault()
              createMut.mutate()
            }}
            className="space-y-4"
          >
            <div className="space-y-2">
              <Label>Mes de Referencia *</Label>
              <Input type="month" value={newMonth} onChange={(e) => setNewMonth(e.target.value)} required />
            </div>
            <div className="space-y-2">
              <Label>Tipo *</Label>
              <select
                className="w-full rounded-md border border-default bg-surface-0 px-3 py-2 text-sm"
                value={newType}
                onChange={(e) => setNewType(e.target.value)}
                aria-label="Tipo da folha"
              >
                {Object.entries(PAYROLL_TYPES).map(([k, v]) => (
                  <option key={k} value={k}>{v}</option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label>Observacoes</Label>
              <textarea
                className="w-full rounded-md border border-default bg-surface-0 px-3 py-2 text-sm min-h-[60px]"
                value={newNotes}
                onChange={(e) => setNewNotes(e.target.value)}
                placeholder="Observacoes opcionais..."
              />
            </div>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={createMut.isPending}>
                {createMut.isPending ? 'Criando...' : 'Criar Folha'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}

// ── Subcomponent: PayrollRow ──

interface PayrollRowProps {
  payroll: Payroll
  status: { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
  isExpanded: boolean
  expandedPayroll: Payroll | null | undefined
  canManage: boolean
  onToggle: () => void
  onCalculate: () => void
  onApprove: () => void
  onMarkPaid: () => void
  onGenPayslips: () => void
  isCalculating: boolean
  isApproving: boolean
  isMarkingPaid: boolean
  isGenPayslips: boolean
}

function PayrollRow({
  payroll: p, status: st, isExpanded, expandedPayroll, canManage,
  onToggle, onCalculate, onApprove, onMarkPaid, onGenPayslips,
  isCalculating, isApproving, isMarkingPaid, isGenPayslips,
}: PayrollRowProps) {
  return (
    <>
      <tr
        className={cn('border-b hover:bg-muted/50 cursor-pointer', isExpanded && 'bg-muted/30')}
        onClick={onToggle}
      >
        <td className="py-3 px-2">
          {isExpanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        </td>
        <td className="py-3 px-2 font-medium">{formatMonth(p.reference_month)}</td>
        <td className="py-3 px-2">{PAYROLL_TYPES[p.type] ?? p.type}</td>
        <td className="py-3 px-2">
          <Badge variant={st.variant}>{st.label}</Badge>
        </td>
        <td className="py-3 px-2 text-right">{formatCurrency(p.total_gross)}</td>
        <td className="py-3 px-2 text-right">{formatCurrency(p.total_net)}</td>
        <td className="py-3 px-2 text-right">{p.employee_count}</td>
        <td className="py-3 px-2" onClick={(e) => e.stopPropagation()}>
          <div className="flex gap-1">
            {canManage && (p.status === 'draft' || p.status === 'calculated') && (
              <Button size="sm" variant="outline" onClick={onCalculate} disabled={isCalculating} title="Calcular">
                <Calculator className="h-3.5 w-3.5" />
              </Button>
            )}
            {canManage && p.status === 'calculated' && (
              <Button size="sm" variant="outline" onClick={onApprove} disabled={isApproving} title="Aprovar">
                <CheckCircle2 className="h-3.5 w-3.5" />
              </Button>
            )}
            {canManage && p.status === 'approved' && (
              <Button size="sm" variant="outline" onClick={onMarkPaid} disabled={isMarkingPaid} title="Marcar como Paga">
                <CreditCard className="h-3.5 w-3.5" />
              </Button>
            )}
            {canManage && ['calculated', 'approved', 'paid'].includes(p.status) && (
              <Button size="sm" variant="outline" onClick={onGenPayslips} disabled={isGenPayslips} title="Gerar Holerites">
                <FileText className="h-3.5 w-3.5" />
              </Button>
            )}
          </div>
        </td>
      </tr>
      {isExpanded && (
        <tr>
          <td colSpan={8} className="p-0">
            <PayrollLinesDetail payroll={expandedPayroll} />
          </td>
        </tr>
      )}
    </>
  )
}

// ── Subcomponent: Lines detail ──

function PayrollLinesDetail({ payroll }: { payroll: Payroll | null | undefined }) {
  if (!payroll || !payroll.lines) {
    return (
      <div className="flex items-center justify-center py-6 text-muted-foreground">
        <RefreshCw className="h-4 w-4 animate-spin mr-2" /> Carregando detalhes...
      </div>
    )
  }

  if (payroll.lines.length === 0) {
    return <p className="text-center text-muted-foreground py-6">Nenhum funcionario nesta folha.</p>
  }

  return (
    <div className="bg-muted/20 border-t">
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 border-b">
        <Stat label="Total Bruto" value={formatCurrency(payroll.total_gross)} />
        <Stat label="Total Descontos" value={formatCurrency(payroll.total_deductions)} />
        <Stat label="Total Liquido" value={formatCurrency(payroll.total_net)} />
        <Stat label="FGTS Empresa" value={formatCurrency(payroll.total_fgts)} />
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-xs">
          <thead>
            <tr className="border-b text-muted-foreground">
              <th className="py-2 px-2 text-left">Funcionario</th>
              <th className="py-2 px-2 text-right">Base</th>
              <th className="py-2 px-2 text-right">HE 50%</th>
              <th className="py-2 px-2 text-right">HE 100%</th>
              <th className="py-2 px-2 text-right">Bruto</th>
              <th className="py-2 px-2 text-right">INSS</th>
              <th className="py-2 px-2 text-right">IRRF</th>
              <th className="py-2 px-2 text-right">Descontos</th>
              <th className="py-2 px-2 text-right">Liquido</th>
              <th className="py-2 px-2 text-right">FGTS</th>
            </tr>
          </thead>
          <tbody>
            {payroll.lines.map((line: PayrollLine) => (
              <tr key={line.id} className="border-b hover:bg-muted/30">
                <td className="py-2 px-2">{line.user?.name ?? `#${line.user_id}`}</td>
                <td className="py-2 px-2 text-right">{formatCurrency(line.base_salary)}</td>
                <td className="py-2 px-2 text-right">{formatCurrency(line.overtime_50_value)}</td>
                <td className="py-2 px-2 text-right">{formatCurrency(line.overtime_100_value)}</td>
                <td className="py-2 px-2 text-right font-medium">{formatCurrency(line.gross_salary)}</td>
                <td className="py-2 px-2 text-right text-red-600">{formatCurrency(line.inss_employee)}</td>
                <td className="py-2 px-2 text-right text-red-600">{formatCurrency(line.irrf)}</td>
                <td className="py-2 px-2 text-right text-red-600">
                  {formatCurrency(
                    Number(line.transportation_discount) + Number(line.meal_discount)
                    + Number(line.health_insurance_discount) + Number(line.other_deductions)
                    + Number(line.advance_discount)
                  )}
                </td>
                <td className="py-2 px-2 text-right font-medium text-green-600">{formatCurrency(line.net_salary)}</td>
                <td className="py-2 px-2 text-right">{formatCurrency(line.fgts_value)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="text-sm font-semibold">{value}</p>
    </div>
  )
}
