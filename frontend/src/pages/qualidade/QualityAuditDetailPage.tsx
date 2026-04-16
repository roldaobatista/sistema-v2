import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api, { unwrapData } from '@/lib/api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { toast } from 'sonner'
import {
  ArrowLeft, CheckCircle, XCircle, AlertTriangle, Wrench,
  ClipboardCheck, Calendar, User, FileText, Loader2
} from 'lucide-react'
import { safeArray } from '@/lib/safe-array'

interface AuditItem {
  id: number
  requirement: string
  clause?: string
  question: string
  result?: string
  evidence?: string
  notes?: string
}

interface CorrectiveAction {
  id: number
  type: string
  nonconformity_description: string
  root_cause?: string
  action_plan?: string
  status: string
  deadline?: string
  responsible?: { name: string }
}

interface QualityAuditFull {
  id: number
  audit_number: string
  title: string
  type: string
  scope?: string
  planned_date: string
  executed_date?: string | null
  auditor?: { id: number; name: string }
  status: string
  summary?: string | null
  non_conformities_found: number
  observations_found?: number
  items?: AuditItem[]
}

const statusColors: Record<string, string> = {
  planned: 'secondary',
  in_progress: 'warning',
  completed: 'success',
  cancelled: 'destructive',
}

const statusLabels: Record<string, string> = {
  planned: 'Planejada',
  in_progress: 'Em Andamento',
  completed: 'Concluida',
  cancelled: 'Cancelada',
}

const resultLabels: Record<string, string> = {
  conform: 'Conforme',
  non_conform: 'Nao Conforme',
  observation: 'Observacao',
}

const resultColors: Record<string, string> = {
  conform: 'bg-green-100 text-green-700',
  non_conform: 'bg-red-100 text-red-700',
  observation: 'bg-amber-100 text-amber-700',
}

const typeLabels: Record<string, string> = {
  internal: 'Interna',
  external: 'Externa',
  supplier: 'Fornecedor',
}

const capaStatusLabels: Record<string, string> = {
  open: 'Aberta',
  in_progress: 'Em Andamento',
  closed: 'Fechada',
  verified: 'Verificada',
}

export default function QualityAuditDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [capaItem, setCapaItem] = useState<{ question: string; evidence?: string; id: number } | null>(null)
  const [statusFilter, setStatusFilter] = useState<string>('all')

  const { data: audit, isLoading, isError } = useQuery<QualityAuditFull>({
    queryKey: ['quality-audit-detail', id],
    queryFn: () => api.get(`/quality-audits/${id}`).then(response => unwrapData<QualityAuditFull>(response)),
    enabled: !!id,
  })

  const { data: correctiveActions, isLoading: loadingCapa } = useQuery<CorrectiveAction[]>({
    queryKey: ['quality-audit-capa', id],
    queryFn: () => api.get(`/quality-audits/${id}/corrective-actions`).then(response => safeArray<CorrectiveAction>(unwrapData(response))),
    enabled: !!id,
  })

  const updateAuditMut = useMutation({
    mutationFn: (data: Record<string, string | null>) => api.put(`/quality-audits/${id}`, data),
    onSuccess: () => {
      toast.success('Auditoria atualizada')
      qc.invalidateQueries({ queryKey: ['quality-audit-detail', id] })
      qc.invalidateQueries({ queryKey: ['quality-audits'] })
      broadcastQueryInvalidation(['quality-audit-detail', 'quality-audits'], 'Auditorias')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => toast.error(err?.response?.data?.message ?? 'Erro ao atualizar'),
  })

  const updateItemMut = useMutation({
    mutationFn: ({ itemId, data }: { itemId: number; data: Record<string, string | null> }) =>
      api.put(`/quality-audits/${id}/items/${itemId}`, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['quality-audit-detail', id] })
      qc.invalidateQueries({ queryKey: ['quality-audits'] })
      broadcastQueryInvalidation(['quality-audit-detail', 'quality-audits'], 'Auditorias')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => toast.error(err?.response?.data?.message ?? 'Erro ao atualizar item'),
  })

  const createCapaMut = useMutation({
    mutationFn: (payload: { sourceable_type: string; sourceable_id: number; nonconformity_description: string }) =>
      api.post('/quality/corrective-actions', { type: 'corrective', source: 'audit', ...payload }),
    onSuccess: () => {
      toast.success('Acao corretiva criada')
      setCapaItem(null)
      qc.invalidateQueries({ queryKey: ['quality-audit-capa', id] })
      qc.invalidateQueries({ queryKey: ['quality-corrective-actions'] })
      broadcastQueryInvalidation(['quality-audit-capa', 'quality-corrective-actions'], 'Acao Corretiva')
    },
    onError: (e: { response?: { data?: { message?: string } } }) => toast.error(e?.response?.data?.message || 'Erro ao criar acao'),
  })

  const handleCreateCapa = (description: string) => {
    if (!capaItem) return
    createCapaMut.mutate({
      sourceable_type: 'App\\Models\\QualityAuditItem',
      sourceable_id: capaItem.id,
      nonconformity_description: description,
    })
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (isError || !audit) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <AlertTriangle className="h-10 w-10 text-red-400 mb-3" />
        <p className="text-sm font-medium text-red-600">Erro ao carregar auditoria</p>
        <Button variant="outline" className="mt-4" onClick={() => navigate('/qualidade/auditorias')}>
          <ArrowLeft className="h-4 w-4 mr-1" /> Voltar
        </Button>
      </div>
    )
  }

  const items = audit.items ?? []
  const filteredItems = statusFilter === 'all'
    ? items
    : items.filter(i => i.result === statusFilter || (!i.result && statusFilter === 'pending'))

  const conformCount = items.filter(i => i.result === 'conform').length
  const nonConformCount = items.filter(i => i.result === 'non_conform').length
  const observationCount = items.filter(i => i.result === 'observation').length
  const pendingCount = items.filter(i => !i.result).length

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Auditoria ${audit.audit_number}`}
        subtitle={audit.title}
      >
        <Button variant="outline" onClick={() => navigate('/qualidade/auditorias')}>
          <ArrowLeft className="h-4 w-4 mr-1" /> Voltar
        </Button>
      </PageHeader>

      {/* Header Info Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardContent className="pt-4 pb-4">
            <div className="flex items-center gap-2 mb-1">
              <ClipboardCheck className="h-4 w-4 text-muted-foreground" />
              <span className="text-xs text-muted-foreground">Status</span>
            </div>
            <select
              className="w-full border rounded px-3 py-1.5 text-sm"
              value={audit.status}
              onChange={e => updateAuditMut.mutate({ status: e.target.value })}
              aria-label="Status da auditoria"
            >
              {Object.entries(statusLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-4 pb-4">
            <div className="flex items-center gap-2 mb-1">
              <Calendar className="h-4 w-4 text-muted-foreground" />
              <span className="text-xs text-muted-foreground">Datas</span>
            </div>
            <p className="text-sm">Planejada: {new Date(audit.planned_date).toLocaleDateString('pt-BR')}</p>
            <div className="mt-1">
              <label className="text-xs text-muted-foreground">Executada:</label>
              <Input
                type="date"
                className="text-xs h-7 mt-0.5"
                value={audit.executed_date ? audit.executed_date.toString().slice(0, 10) : ''}
                onChange={e => updateAuditMut.mutate({ executed_date: e.target.value || null })}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-4 pb-4">
            <div className="flex items-center gap-2 mb-1">
              <User className="h-4 w-4 text-muted-foreground" />
              <span className="text-xs text-muted-foreground">Auditor</span>
            </div>
            <p className="text-sm font-medium">{audit.auditor?.name ?? 'Nao atribuido'}</p>
            <Badge variant="outline" className="mt-1">
              {typeLabels[audit.type] ?? audit.type}
            </Badge>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-4 pb-4">
            <div className="flex items-center gap-2 mb-1">
              <FileText className="h-4 w-4 text-muted-foreground" />
              <span className="text-xs text-muted-foreground">Resultados</span>
            </div>
            <div className="grid grid-cols-2 gap-1 text-xs">
              <span className="flex items-center gap-1 text-green-600"><CheckCircle className="h-3 w-3" /> {conformCount} conformes</span>
              <span className="flex items-center gap-1 text-red-600"><XCircle className="h-3 w-3" /> {nonConformCount} NC</span>
              <span className="flex items-center gap-1 text-amber-600"><AlertTriangle className="h-3 w-3" /> {observationCount} obs</span>
              <span className="text-muted-foreground">{pendingCount} pendentes</span>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Summary */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm">Resumo / Escopo</CardTitle>
        </CardHeader>
        <CardContent>
          <textarea
            className="w-full border rounded px-3 py-2 text-sm min-h-[60px]"
            placeholder="Resumo da auditoria..."
            value={audit.summary ?? ''}
            onChange={e => updateAuditMut.mutate({ summary: e.target.value })}
          />
          {audit.scope && (
            <p className="text-xs text-muted-foreground mt-2">Escopo: {audit.scope}</p>
          )}
        </CardContent>
      </Card>

      {/* Audit Items (Findings) */}
      <Card>
        <CardHeader className="pb-2">
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm">Itens da Auditoria ({items.length})</CardTitle>
            <div className="flex gap-1">
              {[
                { key: 'all', label: 'Todos' },
                { key: 'conform', label: 'Conformes' },
                { key: 'non_conform', label: 'NC' },
                { key: 'observation', label: 'Obs' },
                { key: 'pending', label: 'Pendentes' },
              ].map(f => (
                <Button
                  key={f.key}
                  size="sm"
                  variant={statusFilter === f.key ? 'default' : 'outline'}
                  className="text-xs h-7"
                  onClick={() => setStatusFilter(f.key)}
                >
                  {f.label}
                </Button>
              ))}
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {filteredItems.length === 0 ? (
            <p className="text-sm text-muted-foreground py-4 text-center">Nenhum item encontrado</p>
          ) : (
            <div className="space-y-3">
              {filteredItems.map((item) => (
                <div key={item.id} className="border rounded-lg p-4 bg-muted/30">
                  <div className="flex justify-between items-start gap-3">
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium">
                        {item.requirement}
                        {item.clause && <span className="text-muted-foreground ml-1">({item.clause})</span>}
                      </p>
                      <p className="text-xs text-muted-foreground mt-0.5">{item.question}</p>
                    </div>
                    <div className="flex items-center gap-2 flex-shrink-0">
                      <select
                        className="border rounded px-2 py-1 text-xs"
                        value={item.result ?? ''}
                        onChange={e => updateItemMut.mutate({ itemId: item.id, data: { result: e.target.value || null } })}
                        aria-label="Resultado do item"
                      >
                        <option value="">Pendente</option>
                        {Object.entries(resultLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                      </select>
                      {item.result && (
                        <span className={`text-xs px-2 py-0.5 rounded-full ${resultColors[item.result] ?? 'bg-gray-100'}`}>
                          {resultLabels[item.result] ?? item.result}
                        </span>
                      )}
                      {item.result === 'non_conform' && (
                        <Button
                          size="sm"
                          variant="outline"
                          className="text-xs h-7"
                          onClick={() => setCapaItem({ id: item.id, question: item.question, evidence: item.evidence })}
                        >
                          <Wrench className="h-3 w-3 mr-1" /> CAPA
                        </Button>
                      )}
                    </div>
                  </div>
                  <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                      <label className="text-xs text-muted-foreground">Evidencia</label>
                      <Input
                        className="text-xs mt-0.5"
                        value={item.evidence ?? ''}
                        onChange={e => updateItemMut.mutate({ itemId: item.id, data: { evidence: e.target.value } })}
                        placeholder="Descreva a evidencia encontrada..."
                      />
                    </div>
                    <div>
                      <label className="text-xs text-muted-foreground">Notas</label>
                      <Input
                        className="text-xs mt-0.5"
                        value={item.notes ?? ''}
                        onChange={e => updateItemMut.mutate({ itemId: item.id, data: { notes: e.target.value } })}
                        placeholder="Observacoes adicionais..."
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Corrective Actions */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm flex items-center gap-2">
            <Wrench className="h-4 w-4" />
            Acoes Corretivas ({correctiveActions?.length ?? 0})
          </CardTitle>
        </CardHeader>
        <CardContent>
          {loadingCapa ? (
            <div className="flex items-center gap-2 py-4">
              <Loader2 className="h-4 w-4 animate-spin" />
              <span className="text-sm text-muted-foreground">Carregando...</span>
            </div>
          ) : !correctiveActions?.length ? (
            <p className="text-sm text-muted-foreground py-4 text-center">
              Nenhuma acao corretiva registrada. Marque itens como "Nao Conforme" para criar.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left">
                    <th className="p-2">Descricao</th>
                    <th className="p-2">Causa Raiz</th>
                    <th className="p-2">Plano de Acao</th>
                    <th className="p-2">Prazo</th>
                    <th className="p-2">Responsavel</th>
                    <th className="p-2">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {correctiveActions.map((ca) => (
                    <tr key={ca.id} className="border-b hover:bg-muted/50">
                      <td className="p-2 max-w-[200px] truncate">{ca.nonconformity_description}</td>
                      <td className="p-2 max-w-[150px] truncate">{ca.root_cause ?? '--'}</td>
                      <td className="p-2 max-w-[150px] truncate">{ca.action_plan ?? '--'}</td>
                      <td className="p-2 whitespace-nowrap">
                        {ca.deadline ? new Date(ca.deadline).toLocaleDateString('pt-BR') : '--'}
                      </td>
                      <td className="p-2">{ca.responsible?.name ?? '--'}</td>
                      <td className="p-2">
                        <Badge variant={ca.status === 'closed' || ca.status === 'verified' ? 'success' : ca.status === 'in_progress' ? 'warning' : 'secondary'}>
                          {capaStatusLabels[ca.status] ?? ca.status}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* CAPA Creation Dialog */}
      <Dialog open={!!capaItem} onOpenChange={(open) => !open && setCapaItem(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Abrir acao corretiva</DialogTitle></DialogHeader>
          {capaItem && (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">Nao conformidade: {capaItem.question}</p>
              <form onSubmit={e => {
                e.preventDefault()
                const desc = (e.currentTarget.querySelector('[name="desc"]') as HTMLTextAreaElement)?.value
                if (desc) handleCreateCapa(desc)
              }}>
                <label className="text-sm font-medium">Descricao da acao *</label>
                <textarea
                  name="desc"
                  rows={3}
                  className="w-full border rounded px-3 py-2 text-sm mt-1"
                  defaultValue={capaItem.evidence ? `${capaItem.question}\nEvidencia: ${capaItem.evidence}` : capaItem.question}
                  required
                />
                <div className="flex justify-end gap-2 mt-3">
                  <Button type="button" variant="outline" onClick={() => setCapaItem(null)}>Cancelar</Button>
                  <Button type="submit" disabled={createCapaMut.isPending}>
                    {createCapaMut.isPending ? 'Criando...' : 'Criar acao corretiva'}
                  </Button>
                </div>
              </form>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
