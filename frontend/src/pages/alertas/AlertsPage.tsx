import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { getApiErrorMessage } from '@/lib/api';
import { PageHeader } from '@/components/ui/pageheader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import { Bell, Check, Eye, X, RefreshCw, AlertTriangle, AlertCircle, Info, Download, LayoutGrid } from 'lucide-react';

const severityConfig: Record<string, { label: string; color: string; icon: React.ComponentType<{ className?: string }> }> = {
  critical: { label: 'Crítico', color: 'destructive', icon: AlertCircle },
  high: { label: 'Alto', color: 'warning', icon: AlertTriangle },
  medium: { label: 'Médio', color: 'secondary', icon: Info },
  low: { label: 'Baixo', color: 'outline', icon: Info },
};

const typeLabels: Record<string, string> = {
  unbilled_wo: 'OS sem faturamento',
  expiring_contract: 'Contrato vencendo',
  expiring_calibration: 'Calibração vencendo',
  calibration_overdue: 'Calibração vencida',
  tool_cal_overdue: 'Ferramenta calibração vencida',
  weight_cert_expiring: 'Peso padrão vencendo',
  quote_expiring: 'Orçamento vencendo',
  quote_expired: 'Orçamento expirado',
  overdue_receivable: 'Conta a receber em atraso',
  tool_cal_expiring: 'Ferramenta calibração vencendo',
  expense_pending: 'Despesa pendente',
  sla_breach: 'SLA estourado',
  low_stock: 'Estoque baixo',
  overdue_payable: 'Conta a pagar em atraso',
  expiring_payable: 'Conta a pagar vencendo',
  expiring_fleet_insurance: 'Seguro de frota vencendo',
  expiring_supplier_contract: 'Contrato fornecedor vencendo',
  commitment_overdue: 'Compromisso atrasado',
  important_date_upcoming: 'Data importante próxima',
  customer_no_contact: 'Cliente sem contato',
  overdue_follow_up: 'Follow-up em atraso',
  unattended_service_call: 'Chamado sem atendimento',
  renegotiation_pending: 'Renegociação pendente',
  receivables_concentration: 'Concentração inadimplência',
  scheduled_wo_not_started: 'OS recebida sem início',
  calibration_expiring: 'Calibração vencendo',
  deal_stalled: 'Negócio parado',
  health_dropping: 'Health score em queda',
  no_contact: 'Sem contato',
  contract_expiring: 'Contrato vencendo',
  opportunity_detected: 'Oportunidade detectada',
  nps_detractor: 'NPS detrator',
};

type GroupBy = 'none' | 'alert_type' | 'entity';

type AlertListItem = {
  id: number;
  priority: string;
  title: string;
  description: string;
  type: string;
  status: string;
  created_at: string;
  customer?: { id: number; name: string } | null;
  deal?: { id: number; title: string } | null;
  equipment?: { id: number; code?: string | null; brand?: string | null; model?: string | null } | null;
};

type AlertGroupItem = {
  key: string;
  label: string;
  count: number;
  latest_at?: string | null;
};

type AlertsResponse = {
  data?: AlertListItem[];
  meta?: {
    total?: number;
  };
};

function normalizeAlertsResponse(payload: AlertsResponse | null | undefined): AlertsResponse {
  if (!payload || !Array.isArray(payload.data)) {
    return { data: [], meta: { total: 0 } };
  }

  return payload;
}

function getEntityLabel(alert: AlertListItem): string {
  if (alert.customer?.name) {
    return alert.customer.name;
  }

  if (alert.deal?.title) {
    return alert.deal.title;
  }

  if (alert.equipment) {
    return [alert.equipment.code, alert.equipment.brand, alert.equipment.model]
      .filter(Boolean)
      .join(' - ') || `Equipamento #${alert.equipment.id}`;
  }

  return 'Sem entidade';
}

function groupAlerts(items: AlertListItem[], groupBy: GroupBy): AlertGroupItem[] {
  if (groupBy === 'none') {
    return [];
  }

  const grouped = new Map<string, AlertGroupItem>();

  items.forEach((alert) => {
    const key = groupBy === 'alert_type' ? alert.type : `${groupBy}:${getEntityLabel(alert)}`;
    const label = groupBy === 'alert_type'
      ? (typeLabels[alert.type] || alert.type)
      : getEntityLabel(alert);

    const current = grouped.get(key);
    if (!current) {
      grouped.set(key, {
        key,
        label,
        count: 1,
        latest_at: alert.created_at,
      });
      return;
    }

    grouped.set(key, {
      ...current,
      count: current.count + 1,
      latest_at: !current.latest_at || new Date(alert.created_at) > new Date(current.latest_at)
        ? alert.created_at
        : current.latest_at,
    });
  });

  return Array.from(grouped.values()).sort((left, right) => right.count - left.count);
}

function buildCsv(rows: string[][]): string {
  return rows
    .map((row) => row.map((value) => `"${value.replaceAll('"', '""')}"`).join(';'))
    .join('\n');
}

export default function AlertsPage() {
  const qc = useQueryClient();
  const [groupBy, setGroupBy] = useState<GroupBy>('none');

  const { data: alertsData, isLoading, isError, error } = useQuery({
    queryKey: ['alerts', groupBy],
    queryFn: () => {
      const params: Record<string, string | number> = { status: 'pending', per_page: 100 };
      return api.get('/alerts', { params }).then(r => normalizeAlertsResponse(r.data));
    },
  });

  const acknowledgeMut = useMutation({
    mutationFn: (id: number) => api.post(`/alerts/${id}/acknowledge`),
    onSuccess: () => { toast.success('Alerta reconhecido'); qc.invalidateQueries({ queryKey: ['alerts'] }); },
  });

  const resolveMut = useMutation({
    mutationFn: (id: number) => api.post(`/alerts/${id}/resolve`),
    onSuccess: () => { toast.success('Alerta resolvido'); qc.invalidateQueries({ queryKey: ['alerts'] }); },
  });

  const dismissMut = useMutation({
    mutationFn: (id: number) => api.post(`/alerts/${id}/dismiss`),
    onSuccess: () => { toast.success('Alerta descartado'); qc.invalidateQueries({ queryKey: ['alerts'] }); },
  });

  const runEngineMut = useMutation({
    mutationFn: () => api.post('/alerts/run-engine'),
    onSuccess: (res) => {
      toast.success(res.data?.message ?? 'Verificação concluída');
      qc.invalidateQueries({ queryKey: ['alerts'] });
    },
  });

  const exportMut = useMutation({
    mutationFn: async () => {
      const list = alertsData?.data ?? [];
      const groupedRows = groupAlerts(list, groupBy);
      const csv = groupBy === 'none'
        ? buildCsv([
          ['ID', 'Prioridade', 'Tipo', 'Titulo', 'Descricao', 'Entidade', 'Criado em'],
          ...list.map((alert) => [
            String(alert.id),
            alert.priority,
            typeLabels[alert.type] || alert.type,
            alert.title,
            alert.description,
            getEntityLabel(alert),
            new Date(alert.created_at).toLocaleString('pt-BR'),
          ]),
        ])
        : buildCsv([
          ['Grupo', 'Quantidade', 'Ultimo alerta'],
          ...groupedRows.map((row) => [
            row.label,
            String(row.count),
            row.latest_at ? new Date(row.latest_at).toLocaleString('pt-BR') : '-',
          ]),
        ]);

      const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `alertas-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    },
    onSuccess: () => toast.success('Exportação iniciada'),
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Falha ao exportar')),
  });

  const list = alertsData?.data ?? [];
  const grouped = groupBy !== 'none';
  const groupItems = groupAlerts(list, groupBy);
  const summary = {
    critical: list.filter((alert) => alert.priority === 'critical').length,
    high: list.filter((alert) => alert.priority === 'high').length,
    total_active: alertsData?.meta?.total ?? list.length,
  };

  return (
    <div className="space-y-6">
      <PageHeader title="Central de Alertas" subtitle="Monitoramento automático de eventos críticos do sistema" />

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-red-100"><AlertCircle className="h-5 w-5 text-red-600" /></div>
              <div>
                <p className="text-2xl font-bold">{summary?.critical ?? 0}</p>
                <p className="text-sm text-muted-foreground">Críticos</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-yellow-100"><AlertTriangle className="h-5 w-5 text-yellow-600" /></div>
              <div>
                <p className="text-2xl font-bold">{summary?.high ?? 0}</p>
                <p className="text-sm text-muted-foreground">Prioridade Alta</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-blue-100"><Bell className="h-5 w-5 text-blue-600" /></div>
              <div>
                <p className="text-2xl font-bold">{summary?.total_active ?? 0}</p>
                <p className="text-sm text-muted-foreground">Total Ativos</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6 flex items-center justify-center">
            <Button onClick={() => runEngineMut.mutate()} disabled={runEngineMut.isPending} className="w-full">
              <RefreshCw className={`h-4 w-4 mr-2 ${runEngineMut.isPending ? 'animate-spin' : ''}`} />
              {runEngineMut.isPending ? 'Verificando...' : 'Executar Verificação'}
            </Button>
          </CardContent>
        </Card>
      </div>

      {/* Alert List */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle>Alertas Ativos</CardTitle>
          <div className="flex items-center gap-2">
            <Select value={groupBy} onValueChange={(v: string) => setGroupBy(v as GroupBy)}>
              <SelectTrigger className="w-[180px]">
                <LayoutGrid className="h-4 w-4 mr-2" />
                <SelectValue placeholder="Agrupar por" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">Lista normal</SelectItem>
                <SelectItem value="alert_type">Por tipo</SelectItem>
                <SelectItem value="entity">Por entidade</SelectItem>
              </SelectContent>
            </Select>
            <Button variant="outline" size="sm" onClick={() => exportMut.mutate()} disabled={exportMut.isPending}>
              <Download className="h-4 w-4 mr-2" />
              Exportar CSV
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Carregando...</p>
          ) : isError ? (
            <div className="text-center py-12 text-red-500">
              <Bell className="h-12 w-12 mx-auto mb-3 opacity-30" />
              <p className="text-sm font-medium text-red-600">{(error as Error)?.message ?? 'Erro ao carregar alertas'}</p>
              <p className="text-xs text-muted-foreground mt-1">Tente novamente mais tarde</p>
            </div>
          ) : grouped && groupItems.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              <Bell className="h-12 w-12 mx-auto mb-3 opacity-30" />
              <p>Nenhum alerta ativo.</p>
            </div>
          ) : !grouped && list.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              <Bell className="h-12 w-12 mx-auto mb-3 opacity-30" />
              <p>Nenhum alerta ativo. Sistema operando normalmente.</p>
            </div>
          ) : grouped ? (
            <div className="space-y-3">
              {(groupItems || []).map((row) => (
                <div key={row.key} className="flex items-center gap-4 p-4 border rounded-lg bg-muted/30">
                  <Badge variant="outline">{row.label}</Badge>
                  <span className="font-medium">{row.count} alerta(s)</span>
                  <span className="text-sm text-muted-foreground">
                    Último: {row.latest_at ? new Date(row.latest_at).toLocaleString('pt-BR') : '-'}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <div className="space-y-3">
              {(list || []).map((alert) => {
                const sev = severityConfig[alert.priority] || severityConfig.medium;
                const Icon = sev.icon;
                return (
                  <div key={alert.id} className="flex items-start gap-4 p-4 border rounded-lg hover:bg-muted/50 transition-colors">
                    <div className="mt-0.5">
                      <Icon className={`h-5 w-5 ${alert.priority === 'critical' ? 'text-red-600' : alert.priority === 'high' ? 'text-yellow-600' : 'text-blue-600'}`} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="font-medium">{alert.title}</span>
                        <Badge variant={sev.color as 'destructive' | 'warning' | 'secondary' | 'outline'}>{sev.label}</Badge>
                        <Badge variant="outline">{typeLabels[alert.type] || alert.type}</Badge>
                      </div>
                      <p className="text-sm text-muted-foreground">{alert.description}</p>
                      <p className="text-xs text-muted-foreground mt-1">{getEntityLabel(alert)}</p>
                      <p className="text-xs text-muted-foreground mt-1">
                        {new Date(alert.created_at).toLocaleString('pt-BR')}
                      </p>
                    </div>
                    <div className="flex gap-1 shrink-0">
                      <Button size="icon" variant="ghost" title="Reconhecer" aria-label="Reconhecer alerta" onClick={() => acknowledgeMut.mutate(alert.id)}>
                        <Eye className="h-4 w-4" />
                      </Button>
                      <Button size="icon" variant="ghost" title="Resolver" aria-label="Resolver alerta" onClick={() => resolveMut.mutate(alert.id)}>
                        <Check className="h-4 w-4 text-green-600" />
                      </Button>
                      <Button size="icon" variant="ghost" title="Descartar" aria-label="Descartar alerta" onClick={() => dismissMut.mutate(alert.id)}>
                        <X className="h-4 w-4 text-muted-foreground" />
                      </Button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
