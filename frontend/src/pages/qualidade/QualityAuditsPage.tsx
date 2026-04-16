import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { unwrapData } from '@/lib/api';
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync';
import { PageHeader } from '@/components/ui/pageheader';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { toast } from 'sonner';
import { Plus, ClipboardCheck, CheckCircle, XCircle, AlertTriangle, Eye, Wrench, ExternalLink } from 'lucide-react';
import { safeArray } from '@/lib/safe-array';

interface AuditorOption {
  id: number
  name: string
}

interface QualityAuditListItem {
  id: number
  audit_number: string
  title: string
  type: string
  planned_date: string
  auditor?: { name: string }
  status: string
  non_conformities_found: number
  observations_found?: number
}

interface QualityAuditItemDetail {
  id: number
  requirement: string
  clause?: string
  question: string
  result?: string
  evidence?: string
  notes?: string
}

interface QualityAuditDetail {
  id: number
  status: string
  executed_date?: string | null
  summary?: string | null
  items?: QualityAuditItemDetail[]
}

const statusColors: Record<string, string> = {
  planned: 'secondary',
  in_progress: 'warning',
  completed: 'success',
  cancelled: 'destructive',
};

const statusLabels: Record<string, string> = {
  planned: 'Planejada',
  in_progress: 'Em Andamento',
  completed: 'Concluída',
  cancelled: 'Cancelada',
};

const resultLabels: Record<string, string> = {
  conform: 'Conforme',
  non_conform: 'Não Conforme',
  observation: 'Observação',
};

export default function QualityAuditsPage() {
  const navigate = useNavigate();
  const qc = useQueryClient();
  const [showForm, setShowForm] = useState(false);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [capaItem, setCapaItem] = useState<{ question: string; evidence?: string; id: number } | null>(null);
  const [form, setForm] = useState({ title: '', type: 'internal', scope: '', planned_date: '', auditor_id: '' });

  const { data: usersRes } = useQuery<AuditorOption[]>({
    queryKey: ['users-auditor'],
    queryFn: () => api.get('/users', { params: { per_page: 200 } }).then(response => safeArray<AuditorOption>(unwrapData(response))),
  });
  const users = usersRes ?? [];

  const { data: audits, isLoading, isError } = useQuery<QualityAuditListItem[]>({
    queryKey: ['quality-audits'],
    queryFn: () => api.get('/quality-audits').then(response => safeArray<QualityAuditListItem>(unwrapData(response))),
  });

  const { data: auditDetail, isLoading: loadingDetail } = useQuery<QualityAuditDetail>({
    queryKey: ['quality-audit-detail', detailId],
    queryFn: () => api.get(`/quality-audits/${detailId}`).then(response => unwrapData<QualityAuditDetail>(response)),
    enabled: !!detailId,
  });

  const createMut = useMutation({
    mutationFn: (data: { title: string; type: string; scope: string; planned_date: string; auditor_id: string | null }) => api.post('/quality-audits', data).then(response => unwrapData(response)),
    onSuccess: () => { toast.success('Auditoria criada'); setShowForm(false); qc.invalidateQueries({ queryKey: ['quality-audits'] }); broadcastQueryInvalidation(['quality-audits'], 'Auditorias'); },
    onError: (err: { response?: { data?: { message?: string } } }) => toast.error(err?.response?.data?.message ?? 'Erro ao criar auditoria'),
  });

  const updateAuditMut = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Record<string, string | null> }) => api.put(`/quality-audits/${id}`, data),
    onSuccess: () => { toast.success('Auditoria atualizada'); qc.invalidateQueries({ queryKey: ['quality-audits'] }); qc.invalidateQueries({ queryKey: ['quality-audit-detail', detailId] }); broadcastQueryInvalidation(['quality-audits', 'quality-audit-detail'], 'Auditorias'); },
    onError: (err: { response?: { data?: { message?: string } } }) => toast.error(err?.response?.data?.message ?? 'Erro ao atualizar'),
  });

  const updateItemMut = useMutation({
    mutationFn: ({ itemId, data }: { itemId: number; data: Record<string, string | null> }) => {
      if (!detailId) {
        return Promise.reject(new Error('Auditoria não selecionada para atualizar item'))
      }
      return api.put(`/quality-audits/${detailId}/items/${itemId}`, data)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['quality-audit-detail', detailId] }); qc.invalidateQueries({ queryKey: ['quality-audits'] }); broadcastQueryInvalidation(['quality-audit-detail', 'quality-audits'], 'Auditorias'); },
    onError: (err: { response?: { data?: { message?: string } } }) => toast.error(err?.response?.data?.message ?? 'Erro ao atualizar item'),
  });

  const createCapaFromAuditMut = useMutation({
    mutationFn: (payload: { sourceable_type: string; sourceable_id: number; nonconformity_description: string }) =>
      api.post('/quality/corrective-actions', { type: 'corrective', source: 'audit', ...payload }),
    onSuccess: () => { toast.success('Ação corretiva criada'); setCapaItem(null); qc.invalidateQueries({ queryKey: ['quality-corrective-actions'] }); broadcastQueryInvalidation(['quality-corrective-actions'], 'Ação Corretiva'); },
    onError: (e: { response?: { data?: { message?: string } } }) => toast.error(e?.response?.data?.message || 'Erro ao criar ação'),
  });

  const handleCreateCapa = (description: string) => {
    if (!capaItem) return;
    createCapaFromAuditMut.mutate({
      sourceable_type: 'App\\Models\\QualityAuditItem',
      sourceable_id: capaItem.id,
      nonconformity_description: description,
    });
  };

  return (
    <div className="space-y-6">
      <PageHeader title="Auditorias Internas" subtitle="Cronograma e registro de auditorias do sistema de gestão da qualidade">
        <Dialog open={showForm} onOpenChange={setShowForm}>
          <DialogTrigger asChild>
            <Button><Plus className="h-4 w-4 mr-1" /> Nova Auditoria</Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader><DialogTitle>Nova Auditoria</DialogTitle></DialogHeader>
            <div className="space-y-4">
              <div>
                <label className="text-sm font-medium">Título</label>
                <Input value={form.title} onChange={e => setForm(p => ({ ...p, title: e.target.value }))} placeholder="Ex: Auditoria Interna — Processos de calibração Q1/2026" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-sm font-medium">Tipo</label>
                  <select className="w-full border rounded px-3 py-2 text-sm" value={form.type} onChange={e => setForm(p => ({ ...p, type: e.target.value }))} aria-label="Tipo de auditoria">
                    <option value="internal">Interna</option>
                    <option value="external">Externa</option>
                    <option value="supplier">Fornecedor</option>
                  </select>
                </div>
                <div>
                  <label className="text-sm font-medium">Data Planejada</label>
                  <Input type="date" value={form.planned_date} onChange={e => setForm(p => ({ ...p, planned_date: e.target.value }))} />
                </div>
              </div>
              <div>
                <label className="text-sm font-medium">Escopo</label>
                <Input value={form.scope} onChange={e => setForm(p => ({ ...p, scope: e.target.value }))} placeholder="Ex: Processos de calibração" />
              </div>
              <div>
                <label className="text-sm font-medium">Auditor</label>
                <select className="w-full border rounded px-3 py-2 text-sm" value={form.auditor_id} onChange={e => setForm(p => ({ ...p, auditor_id: e.target.value }))} aria-label="Auditor">
                  <option value="">Selecione...</option>
                  {(users || []).map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                </select>
              </div>
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setShowForm(false)}>Cancelar</Button>
                <Button onClick={() => createMut.mutate({ ...form, auditor_id: form.auditor_id || null })} disabled={createMut.isPending || !form.auditor_id}>
                  {createMut.isPending ? 'Criando...' : 'Criar Auditoria'}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </PageHeader>

      <Card>
        <CardContent className="pt-6">
          {isLoading ? <p className="text-muted-foreground">Carregando...</p> : isError ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <AlertTriangle className="h-10 w-10 text-red-400 mb-3" />
              <p className="text-sm font-medium text-red-600">Erro ao carregar auditorias</p>
              <p className="text-xs text-muted-foreground mt-1">Tente novamente mais tarde</p>
            </div>
          ) : !audits?.length ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <ClipboardCheck className="h-10 w-10 text-muted-foreground/50 mb-3" />
              <p className="text-sm font-medium text-muted-foreground">Nenhuma auditoria cadastrada</p>
              <p className="text-xs text-muted-foreground/70 mt-1">Clique em "Nova Auditoria" para criar a primeira</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left">
                    <th className="p-3">Número</th>
                    <th className="p-3">Título</th>
                    <th className="p-3">Tipo</th>
                    <th className="p-3">Data</th>
                    <th className="p-3">Auditor</th>
                    <th className="p-3">Status</th>
                    <th className="p-3">NC</th>
                    <th className="p-3">Obs</th>
                    <th className="p-3">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {(audits || []).map((audit) => (
                    <tr key={audit.id} className="border-b hover:bg-muted/50">
                      <td className="p-3 font-mono">{audit.audit_number}</td>
                      <td className="p-3 font-medium">{audit.title}</td>
                      <td className="p-3">
                        <Badge variant="outline">
                          {audit.type === 'internal' ? 'Interna' : audit.type === 'external' ? 'Externa' : 'Fornecedor'}
                        </Badge>
                      </td>
                      <td className="p-3">{new Date(audit.planned_date).toLocaleDateString('pt-BR')}</td>
                      <td className="p-3">{audit.auditor?.name ?? '—'}</td>
                      <td className="p-3"><Badge variant={statusColors[audit.status] as "secondary" | "warning" | "success" | "destructive"}>{statusLabels[audit.status] ?? audit.status}</Badge></td>
                      <td className="p-3">
                        {audit.non_conformities_found > 0 ? (
                          <span className="flex items-center gap-1 text-red-600"><XCircle className="h-4 w-4" /> {audit.non_conformities_found}</span>
                        ) : <span className="text-green-600"><CheckCircle className="h-4 w-4" /></span>}
                      </td>
                      <td className="p-3">{audit.observations_found ?? '—'}</td>
                      <td className="p-3 flex gap-1">
                        <Button size="sm" variant="outline" onClick={() => setDetailId(audit.id)}><Eye className="h-4 w-4 mr-1" /> Resumo</Button>
                        <Button size="sm" variant="outline" onClick={() => navigate(`/qualidade/auditorias/${audit.id}`)}><ExternalLink className="h-4 w-4 mr-1" /> Abrir</Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog open={!!detailId} onOpenChange={(open) => !open && setDetailId(null)}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader><DialogTitle>Detalhe da Auditoria</DialogTitle></DialogHeader>
          {loadingDetail || !auditDetail ? (
            <p className="text-muted-foreground">Carregando...</p>
          ) : (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-xs font-medium text-muted-foreground">Status</label>
                  <select
                    className="w-full border rounded px-3 py-2 text-sm mt-0.5"
                    value={auditDetail.status}
                    onChange={e => updateAuditMut.mutate({ id: auditDetail.id, data: { status: e.target.value } })}
                    aria-label="Status da auditoria"
                  >
                    {Object.entries(statusLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-xs font-medium text-muted-foreground">Data de execução</label>
                  <Input
                    type="date"
                    className="mt-0.5"
                    value={auditDetail.executed_date ? auditDetail.executed_date.toString().slice(0, 10) : ''}
                    onChange={e => updateAuditMut.mutate({ id: auditDetail.id, data: { executed_date: e.target.value || null } })}
                  />
                </div>
              </div>
              {auditDetail.summary !== undefined && (
                <div>
                  <label className="text-xs font-medium text-muted-foreground">Resumo</label>
                  <textarea
                    className="w-full border rounded px-3 py-2 text-sm mt-0.5 min-h-[60px]"
                    value={auditDetail.summary ?? ''}
                    onChange={e => updateAuditMut.mutate({ id: auditDetail.id, data: { summary: e.target.value } })}
                  />
                </div>
              )}
              <div>
                <h4 className="text-sm font-semibold mb-2">Itens da auditoria</h4>
                <div className="space-y-2">
                  {(auditDetail.items || []).map((item) => (
                    <div key={item.id} className="border rounded-lg p-3 bg-muted/30">
                      <div className="flex justify-between items-start gap-2">
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium">{item.requirement} {item.clause && `(${item.clause})`}</p>
                          <p className="text-xs text-muted-foreground mt-0.5">{item.question}</p>
                        </div>
                        <div className="flex items-center gap-1 flex-shrink-0">
                          <select
                            className="border rounded px-2 py-1 text-xs"
                            value={item.result ?? ''}
                            onChange={e => updateItemMut.mutate({ itemId: item.id, data: { result: e.target.value || null } })}
                            aria-label="Resultado do item"
                          >
                            <option value="">—</option>
                            {Object.entries(resultLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                          </select>
                          {item.result === 'non_conform' && (
                            <Button size="sm" variant="outline" className="text-xs" onClick={() => setCapaItem({ id: item.id, question: item.question, evidence: item.evidence })}>
                              <Wrench className="h-3 w-3 mr-1" /> Ação corretiva
                            </Button>
                          )}
                        </div>
                      </div>
                      <div className="mt-2 grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-xs text-muted-foreground">Evidência</label>
                          <Input className="text-xs mt-0.5" value={item.evidence ?? ''} onChange={e => updateItemMut.mutate({ itemId: item.id, data: { evidence: e.target.value } })} />
                        </div>
                        <div>
                          <label className="text-xs text-muted-foreground">Notas</label>
                          <Input className="text-xs mt-0.5" value={item.notes ?? ''} onChange={e => updateItemMut.mutate({ itemId: item.id, data: { notes: e.target.value } })} />
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={!!capaItem} onOpenChange={(open) => !open && setCapaItem(null)}>
        <DialogContent>
          <DialogHeader><DialogTitle>Abrir ação corretiva</DialogTitle></DialogHeader>
          {capaItem && (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">Não conformidade: {capaItem.question}</p>
              <form onSubmit={e => { e.preventDefault(); const desc = (e.currentTarget.querySelector('[name="desc"]') as HTMLTextAreaElement)?.value; if (desc) handleCreateCapa(desc); }}>
                <label className="text-sm font-medium">Descrição da ação *</label>
                <textarea name="desc" rows={3} className="w-full border rounded px-3 py-2 text-sm mt-1" defaultValue={capaItem.evidence ? `${capaItem.question}\nEvidência: ${capaItem.evidence}` : capaItem.question} required />
                <div className="flex justify-end gap-2 mt-3">
                  <Button type="button" variant="outline" onClick={() => setCapaItem(null)}>Cancelar</Button>
                  <Button type="submit" disabled={createCapaFromAuditMut.isPending}>Criar ação corretiva</Button>
                </div>
              </form>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
