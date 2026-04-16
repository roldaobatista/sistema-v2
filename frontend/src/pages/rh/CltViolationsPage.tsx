import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { AlertTriangle, Clock, Activity, CheckCircle, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';

interface Violation {
  id: number;
  date: string;
  violation_type: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  description: string;
  resolved: boolean;
  user: {
    id: number;
    name: string;
  };
}

export function CltViolationsPage() {
  const queryClient = useQueryClient();
  const [filter, setFilter] = useState<'pending' | 'resolved'>('pending');

  const { data: stats } = useQuery({
    queryKey: ['clt-violations-stats'],
    queryFn: async () => {
      const res = await api.get('/hr/violations/stats');
      return res.data;
    },
  });

  const { data: violationsResponse, isLoading } = useQuery({
    queryKey: ['clt-violations', filter],
    queryFn: async () => {
      const res = await api.get('/hr/violations', {
        params: { resolved: filter === 'resolved' ? 1 : 0 },
      });
      return res.data;
    },
  });

  const resolveMutation = useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/hr/violations/${id}/resolve`);
    },
    onSuccess: () => {
      toast.success('Violação marcada como resolvida.');
      queryClient.invalidateQueries({ queryKey: ['clt-violations'] });
      queryClient.invalidateQueries({ queryKey: ['clt-violations-stats'] });
    },
    onError: () => {
      toast.error('Erro ao resolver violação.');
    },
  });

  const violations: Violation[] = violationsResponse?.data || [];

  const typeLabels: Record<string, string> = {
    interjornada_11h: 'Interjornada (Menos de 11h)',
    intra_shift_short: 'Intrajornada (Curto)',
    intra_shift_missing: 'Intrajornada Ausente',
    overtime_limit_exceeded: 'Excesso de Horas Extras (>2h)',
    dsr_missing: 'DSR Ausente (7 dias consecutivos)',
  };

  const severityColors = {
    low: 'bg-blue-100 text-blue-800',
    medium: 'bg-yellow-100 text-yellow-800',
    high: 'bg-orange-100 text-orange-800',
    critical: 'bg-red-100 text-red-800',
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <ShieldAlert className="w-6 h-6 text-red-600" />
            Dashboard de Violações CLT
          </h1>
          <p className="text-gray-500">Monitoramento ativo da Portaria 671 e Consolidação das Leis do Trabalho</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4">
          <div className="p-3 bg-red-100 text-red-600 rounded-lg">
            <AlertTriangle className="w-6 h-6" />
          </div>
          <div>
            <p className="text-sm font-medium text-gray-500">Críticas (Pendentes)</p>
            <p className="text-2xl font-bold text-gray-900">{stats?.pending_by_severity?.critical || 0}</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4">
          <div className="p-3 bg-orange-100 text-orange-600 rounded-lg">
            <Activity className="w-6 h-6" />
          </div>
          <div>
            <p className="text-sm font-medium text-gray-500">Altas (Pendentes)</p>
            <p className="text-2xl font-bold text-gray-900">{stats?.pending_by_severity?.high || 0}</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4">
          <div className="p-3 bg-blue-100 text-blue-600 rounded-lg">
            <Clock className="w-6 h-6" />
          </div>
          <div>
            <p className="text-sm font-medium text-gray-500">Total de Pendências</p>
            <p className="text-2xl font-bold text-gray-900">{stats?.pending_total || 0}</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4">
          <div className="p-3 bg-green-100 text-green-600 rounded-lg">
            <CheckCircle className="w-6 h-6" />
          </div>
          <div>
            <p className="text-sm font-medium text-gray-500">Resolvidas</p>
            <p className="text-2xl font-bold text-gray-900">{stats?.resolved_total || 0}</p>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div className="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
          <div className="flex gap-2">
            <Button
              variant={filter === 'pending' ? 'default' : 'outline'}
              size="sm"
              onClick={() => setFilter('pending')}
            >
              Pendentes
            </Button>
            <Button
              variant={filter === 'resolved' ? 'default' : 'outline'}
              size="sm"
              onClick={() => setFilter('resolved')}
            >
              Resolvidas
            </Button>
          </div>
        </div>

        {isLoading ? (
          <div className="p-8 text-center text-gray-500">Carregando violações...</div>
        ) : violations.length === 0 ? (
          <div className="p-8 text-center text-gray-500">Nenhuma violação encontrada para este filtro.</div>
        ) : (
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Colaborador</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Infração</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Severidade</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {violations.map((v) => (
                <tr key={v.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {new Date(v.date).toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                    {v.user?.name}
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-600">
                    <div className="font-medium text-gray-900">
                      {typeLabels[v.violation_type] || v.violation_type}
                    </div>
                    <div className="text-xs text-gray-500 truncate max-w-xs">{v.description}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${severityColors[v.severity]}`}>
                      {v.severity.toUpperCase()}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    {!v.resolved && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => resolveMutation.mutate(v.id)}
                        disabled={resolveMutation.isPending}
                      >
                        Resolver
                      </Button>
                    )}
                    {v.resolved && <span className="text-green-600 flex items-center gap-1"><CheckCircle className="w-4 h-4"/> Resolvido</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
