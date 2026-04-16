import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { hrApi } from '@/lib/hr-api'
import type { Rescission } from '@/types/hr'
import { extractApiError } from '@/types/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { toast } from 'sonner'
import { UserX, Plus, CheckCircle, DollarSign, FileText, Eye, ArrowLeft } from 'lucide-react'
import api from '@/lib/api'

const TYPE_LABELS: Record<string, string> = {
  sem_justa_causa: 'Demissão sem Justa Causa',
  justa_causa: 'Demissão por Justa Causa',
  pedido_demissao: 'Pedido de Demissão',
  acordo_mutuo: 'Acordo Mútuo',
  termino_contrato: 'Término de Contrato',
}

const NOTICE_TYPE_LABELS: Record<string, string> = {
  worked: 'Trabalhado',
  indemnified: 'Indenizado',
  waived: 'Dispensado',
}

const STATUS_LABELS: Record<string, string> = {
  draft: 'Rascunho',
  calculated: 'Calculada',
  approved: 'Aprovada',
  paid: 'Paga',
  cancelled: 'Cancelada',
}

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-800',
  calculated: 'bg-blue-100 text-blue-800',
  approved: 'bg-green-100 text-green-800',
  paid: 'bg-emerald-100 text-emerald-800',
  cancelled: 'bg-red-100 text-red-800',
}

function formatCurrency(value: number | string): string {
  const num = typeof value === 'string' ? parseFloat(value) : value
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(num || 0)
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '--'
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR')
}

export default function RescissionPage() {
  const queryClient = useQueryClient()
  const [createOpen, setCreateOpen] = useState(false)
  const [selectedRescission, setSelectedRescission] = useState<Rescission | null>(null)
  const [filterStatus, setFilterStatus] = useState<string>('')
  const [formData, setFormData] = useState({
    user_id: '',
    type: '',
    termination_date: '',
    notice_type: '',
    notes: '',
  })

  // Fetch users for dropdown
  const { data: usersRes } = useQuery({
    queryKey: ['hr-users-options'],
    queryFn: () => api.get('/hr/users/options'),
  })
  const users: Array<{ id: number; name: string }> = usersRes?.data?.data ?? []

  // Fetch rescissions
  const { data: rescissionsRes, isLoading } = useQuery({
    queryKey: ['rescissions', filterStatus],
    queryFn: () => hrApi.rescissions.list(filterStatus ? { status: filterStatus } : {}),
  })
  const rescissions: Rescission[] = rescissionsRes?.data?.data ?? []

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: { user_id: number; type: string; termination_date: string; notice_type?: string; notes?: string }) =>
      hrApi.rescissions.create(data),
    onSuccess: () => {
      toast.success('Rescisao calculada com sucesso!')
      queryClient.invalidateQueries({ queryKey: ['rescissions'] })
      setCreateOpen(false)
      setFormData({ user_id: '', type: '', termination_date: '', notice_type: '', notes: '' })
    },
    onError: (err: Error) => {
      toast.error(extractApiError(err, 'Erro ao calcular rescisao'))
    },
  })

  // Approve mutation
  const approveMutation = useMutation({
    mutationFn: (id: number) => hrApi.rescissions.approve(id),
    onSuccess: () => {
      toast.success('Rescisao aprovada!')
      queryClient.invalidateQueries({ queryKey: ['rescissions'] })
      setSelectedRescission(null)
    },
    onError: (err: Error) => {
      toast.error(extractApiError(err, 'Erro ao aprovar'))
    },
  })

  // Mark paid mutation
  const markPaidMutation = useMutation({
    mutationFn: (id: number) => hrApi.rescissions.markPaid(id),
    onSuccess: () => {
      toast.success('Rescisao marcada como paga!')
      queryClient.invalidateQueries({ queryKey: ['rescissions'] })
      setSelectedRescission(null)
    },
    onError: (err: Error) => {
      toast.error(extractApiError(err, 'Erro ao marcar como paga'))
    },
  })

  const handleCreate = () => {
    if (!formData.user_id || !formData.type || !formData.termination_date) {
      toast.error('Preencha todos os campos obrigatorios')
      return
    }
    createMutation.mutate({
      user_id: parseInt(formData.user_id),
      type: formData.type,
      termination_date: formData.termination_date,
      notice_type: formData.notice_type || undefined,
      notes: formData.notes || undefined,
    })
  }

  const handleGenerateTRCT = async (id: number) => {
    try {
      const res = await hrApi.rescissions.trct(id)
      const blob = new Blob([res.data], { type: 'text/html' })
      const url = URL.createObjectURL(blob)
      window.open(url, '_blank')
    } catch {
      toast.error('Erro ao gerar TRCT')
    }
  }

  // Detail view
  if (selectedRescission) {
    const r = selectedRescission
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" onClick={() => setSelectedRescission(null)}>
            <ArrowLeft className="h-4 w-4 mr-1" /> Voltar
          </Button>
          <h1 className="text-2xl font-bold">Detalhes da Rescisao</h1>
          <Badge className={STATUS_COLORS[r.status]}>{STATUS_LABELS[r.status]}</Badge>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm text-muted-foreground">Colaborador</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="font-semibold">{r.user?.name ?? `#${r.user_id}`}</p>
              <p className="text-sm text-muted-foreground">{r.user?.cpf}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm text-muted-foreground">Tipo</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="font-semibold">{TYPE_LABELS[r.type] ?? r.type}</p>
              <p className="text-sm text-muted-foreground">Data: {formatDate(r.termination_date)}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm text-muted-foreground">Valor Liquido</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold text-green-600">{formatCurrency(r.total_net)}</p>
            </CardContent>
          </Card>
        </div>

        {/* Breakdown */}
        <Card>
          <CardHeader>
            <CardTitle>Discriminacao de Verbas</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Verba</TableHead>
                  <TableHead className="text-center">Referencia</TableHead>
                  <TableHead className="text-right">Valor</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow>
                  <TableCell>Saldo de Salario</TableCell>
                  <TableCell className="text-center">{r.salary_balance_days} dias</TableCell>
                  <TableCell className="text-right">{formatCurrency(r.salary_balance_value)}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell>Aviso Previo ({r.notice_type ? NOTICE_TYPE_LABELS[r.notice_type] : '--'})</TableCell>
                  <TableCell className="text-center">{r.notice_days} dias</TableCell>
                  <TableCell className="text-right">{formatCurrency(r.notice_value)}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell>Ferias Proporcionais</TableCell>
                  <TableCell className="text-center">{r.vacation_proportional_days} dias</TableCell>
                  <TableCell className="text-right">{formatCurrency(r.vacation_proportional_value)}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell>1/3 Ferias Proporcionais</TableCell>
                  <TableCell className="text-center"></TableCell>
                  <TableCell className="text-right">{formatCurrency(r.vacation_bonus_value)}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell>Ferias Vencidas</TableCell>
                  <TableCell className="text-center">{r.vacation_overdue_days} dias</TableCell>
                  <TableCell className="text-right">{formatCurrency(r.vacation_overdue_value)}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell>1/3 Ferias Vencidas</TableCell>
                  <TableCell className="text-center"></TableCell>
                  <TableCell className="text-right">{formatCurrency(r.vacation_overdue_bonus_value)}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell>13o Proporcional</TableCell>
                  <TableCell className="text-center">{r.thirteenth_proportional_months} meses</TableCell>
                  <TableCell className="text-right">{formatCurrency(r.thirteenth_proportional_value)}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell>Multa FGTS ({r.fgts_penalty_rate}%)</TableCell>
                  <TableCell className="text-center">Saldo: {formatCurrency(r.fgts_balance)}</TableCell>
                  <TableCell className="text-right">{formatCurrency(r.fgts_penalty_value)}</TableCell>
                </TableRow>
                <TableRow className="bg-blue-50 font-semibold">
                  <TableCell colSpan={2}>Total Bruto</TableCell>
                  <TableCell className="text-right">{formatCurrency(r.total_gross)}</TableCell>
                </TableRow>

                {/* Deductions */}
                <TableRow className="border-t-2">
                  <TableCell className="text-red-600">INSS</TableCell>
                  <TableCell></TableCell>
                  <TableCell className="text-right text-red-600">- {formatCurrency(r.inss_deduction)}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell className="text-red-600">IRRF</TableCell>
                  <TableCell></TableCell>
                  <TableCell className="text-right text-red-600">- {formatCurrency(r.irrf_deduction)}</TableCell>
                </TableRow>
                <TableRow className="bg-red-50 font-semibold">
                  <TableCell colSpan={2}>Total Descontos</TableCell>
                  <TableCell className="text-right text-red-600">- {formatCurrency(r.total_deductions)}</TableCell>
                </TableRow>

                <TableRow className="bg-green-50 font-bold text-lg">
                  <TableCell colSpan={2}>VALOR LIQUIDO</TableCell>
                  <TableCell className="text-right text-green-700">{formatCurrency(r.total_net)}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        {/* Actions */}
        <div className="flex gap-3">
          {r.status === 'calculated' && (
            <Button onClick={() => approveMutation.mutate(r.id)} disabled={approveMutation.isPending}>
              <CheckCircle className="h-4 w-4 mr-1" /> Aprovar
            </Button>
          )}
          {r.status === 'approved' && (
            <Button onClick={() => markPaidMutation.mutate(r.id)} disabled={markPaidMutation.isPending} variant="default">
              <DollarSign className="h-4 w-4 mr-1" /> Marcar como Paga
            </Button>
          )}
          {['calculated', 'approved', 'paid'].includes(r.status) && (
            <Button variant="outline" onClick={() => handleGenerateTRCT(r.id)}>
              <FileText className="h-4 w-4 mr-1" /> Gerar TRCT
            </Button>
          )}
        </div>
      </div>
    )
  }

  // List view
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <UserX className="h-6 w-6" />
          <h1 className="text-2xl font-bold">Rescisoes</h1>
        </div>
        <div className="flex items-center gap-3">
          <Select value={filterStatus} onValueChange={setFilterStatus}>
            <SelectTrigger className="w-[180px]">
              <SelectValue placeholder="Todos os status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">Todos</SelectItem>
              <SelectItem value="draft">Rascunho</SelectItem>
              <SelectItem value="calculated">Calculada</SelectItem>
              <SelectItem value="approved">Aprovada</SelectItem>
              <SelectItem value="paid">Paga</SelectItem>
              <SelectItem value="cancelled">Cancelada</SelectItem>
            </SelectContent>
          </Select>

          <Dialog open={createOpen} onOpenChange={setCreateOpen}>
            <DialogTrigger asChild>
              <Button><Plus className="h-4 w-4 mr-1" /> Nova Rescisao</Button>
            </DialogTrigger>
            <DialogContent className="max-w-lg">
              <DialogHeader>
                <DialogTitle>Calcular Rescisao</DialogTitle>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label>Colaborador *</Label>
                  <Select value={formData.user_id} onValueChange={v => setFormData(p => ({ ...p, user_id: v }))}>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o colaborador" />
                    </SelectTrigger>
                    <SelectContent>
                      {users.map(u => (
                        <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label>Tipo de Rescisao *</Label>
                  <Select value={formData.type} onValueChange={v => setFormData(p => ({ ...p, type: v }))}>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o tipo" />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(TYPE_LABELS).map(([key, label]) => (
                        <SelectItem key={key} value={key}>{label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label>Data de Desligamento *</Label>
                  <Input
                    type="date"
                    value={formData.termination_date}
                    onChange={e => setFormData(p => ({ ...p, termination_date: e.target.value }))}
                  />
                </div>
                {(formData.type === 'sem_justa_causa' || formData.type === 'acordo_mutuo') && (
                  <div>
                    <Label>Tipo de Aviso Previo</Label>
                    <Select value={formData.notice_type} onValueChange={v => setFormData(p => ({ ...p, notice_type: v }))}>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione" />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(NOTICE_TYPE_LABELS).map(([key, label]) => (
                          <SelectItem key={key} value={key}>{label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                )}
                <div>
                  <Label>Observacoes</Label>
                  <Textarea
                    value={formData.notes}
                    onChange={e => setFormData(p => ({ ...p, notes: e.target.value }))}
                    rows={3}
                  />
                </div>
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setCreateOpen(false)}>Cancelar</Button>
                <Button onClick={handleCreate} disabled={createMutation.isPending}>
                  {createMutation.isPending ? 'Calculando...' : 'Calcular Rescisao'}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Colaborador</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Data Desligamento</TableHead>
                <TableHead className="text-right">Valor Liquido</TableHead>
                <TableHead>Status</TableHead>
                <TableHead></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                    Carregando...
                  </TableCell>
                </TableRow>
              ) : rescissions.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                    Nenhuma rescisao encontrada
                  </TableCell>
                </TableRow>
              ) : (
                rescissions.map(r => (
                  <TableRow key={r.id} className="cursor-pointer hover:bg-muted/50" onClick={() => setSelectedRescission(r)}>
                    <TableCell className="font-medium">{r.user?.name ?? `#${r.user_id}`}</TableCell>
                    <TableCell>{TYPE_LABELS[r.type] ?? r.type}</TableCell>
                    <TableCell>{formatDate(r.termination_date)}</TableCell>
                    <TableCell className="text-right font-semibold">{formatCurrency(r.total_net)}</TableCell>
                    <TableCell>
                      <Badge className={STATUS_COLORS[r.status]}>{STATUS_LABELS[r.status]}</Badge>
                    </TableCell>
                    <TableCell>
                      <Button variant="ghost" size="sm" onClick={e => { e.stopPropagation(); setSelectedRescission(r) }}>
                        <Eye className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
