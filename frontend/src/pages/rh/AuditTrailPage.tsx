import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { RefreshCw, Shield, ChevronLeft, ChevronRight } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import { hrApi } from '@/lib/hr-api'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'

interface AuditEntry {
  id: number
  action: string
  user_name: string
  employee_name: string | null
  entry_nsr: string | null
  ip_address: string | null
  details: string | null
  created_at: string
}

interface AuditResponse {
  data: AuditEntry[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

const ACTION_OPTIONS = [
  { value: '', label: 'Todas as ações' },
  { value: 'created', label: 'Criado' },
  { value: 'approved', label: 'Aprovado' },
  { value: 'rejected', label: 'Rejeitado' },
  { value: 'adjusted', label: 'Ajustado' },
  { value: 'tampering_attempt', label: 'Tentativa de Fraude' },
  { value: 'deleted', label: 'Excluído' },
  { value: 'exported', label: 'Exportado' },
]

const ACTION_BADGE_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  created: 'default',
  approved: 'default',
  rejected: 'destructive',
  adjusted: 'secondary',
  tampering_attempt: 'destructive',
  deleted: 'destructive',
  exported: 'outline',
}

export default function AuditTrailPage() {
  const today = new Date().toISOString().split('T')[0]
  // eslint-disable-next-line react-hooks/purity
  const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]

  const [filters, setFilters] = useState({
    start_date: thirtyDaysAgo,
    end_date: today,
    action: '',
    page: 1,
  })

  const { data: auditData, isLoading } = useQuery({
    queryKey: ['hr-audit-trail', filters],
    queryFn: () => hrApi.auditTrail.report({
      start_date: filters.start_date,
      end_date: filters.end_date,
      action: filters.action || undefined,
      ...({ page: filters.page, per_page: 20 }),
    }).then(r => r.data as AuditResponse),
  })

  const entries = auditData?.data ?? []
  const meta = auditData?.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0 }

  const updateFilter = (key: string, value: string | number) => {
    setFilters(prev => ({ ...prev, [key]: value, ...(key !== 'page' ? { page: 1 } : {}) }))
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Shield className="h-6 w-6" />
            Audit Trail - Ponto Digital
          </h1>
          <p className="text-muted-foreground">Histórico de auditoria de registros de ponto</p>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex flex-wrap gap-3 items-end">
            <div>
              <label className="text-sm font-medium mb-1 block">Data Início</label>
              <Input
                type="date"
                value={filters.start_date}
                onChange={(e) => updateFilter('start_date', e.target.value)}
                className="w-40"
              />
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Data Fim</label>
              <Input
                type="date"
                value={filters.end_date}
                onChange={(e) => updateFilter('end_date', e.target.value)}
                className="w-40"
              />
            </div>
            <div>
              <label className="text-sm font-medium mb-1 block">Ação</label>
              <Select
                value={filters.action || 'all'}
                onValueChange={(v) => updateFilter('action', v === 'all' ? '' : v)}
              >
                <SelectTrigger className="w-52">
                  <SelectValue placeholder="Todas as ações" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todas as ações</SelectItem>
                  {ACTION_OPTIONS.filter(o => o.value).map(opt => (
                    <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Results */}
      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <RefreshCw className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center justify-between">
              <span>Registros de Auditoria</span>
              <Badge variant="outline">{meta.total} registro(s)</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2 px-3">Data/Hora</th>
                    <th className="text-left py-2 px-3">Ação</th>
                    <th className="text-left py-2 px-3">Colaborador</th>
                    <th className="text-left py-2 px-3">NSR</th>
                    <th className="text-left py-2 px-3">IP</th>
                    <th className="text-left py-2 px-3">Detalhes</th>
                  </tr>
                </thead>
                <tbody>
                  {entries.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="text-center py-8 text-muted-foreground">
                        Nenhum registro encontrado para o período selecionado.
                      </td>
                    </tr>
                  ) : (
                    entries.map((entry) => (
                      <tr key={entry.id} className="border-b hover:bg-muted/50 transition-colors">
                        <td className="py-2 px-3 whitespace-nowrap">
                          {format(new Date(entry.created_at), 'dd/MM/yyyy HH:mm:ss', { locale: ptBR })}
                        </td>
                        <td className="py-2 px-3">
                          <Badge variant={ACTION_BADGE_VARIANT[entry.action] || 'outline'}>
                            {ACTION_OPTIONS.find(o => o.value === entry.action)?.label || entry.action}
                          </Badge>
                        </td>
                        <td className="py-2 px-3">{entry.employee_name || entry.user_name}</td>
                        <td className="py-2 px-3 font-mono text-xs">{entry.entry_nsr || '-'}</td>
                        <td className="py-2 px-3 font-mono text-xs">{entry.ip_address || '-'}</td>
                        <td className="py-2 px-3 text-xs max-w-64 truncate" title={entry.details || ''}>
                          {entry.details || '-'}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {meta.last_page > 1 && (
              <div className="flex items-center justify-between mt-4 pt-4 border-t">
                <p className="text-sm text-muted-foreground">
                  Página {meta.current_page} de {meta.last_page}
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={meta.current_page <= 1}
                    onClick={() => updateFilter('page', meta.current_page - 1)}
                  >
                    <ChevronLeft className="h-4 w-4 mr-1" />
                    Anterior
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => updateFilter('page', meta.current_page + 1)}
                  >
                    Próxima
                    <ChevronRight className="h-4 w-4 ml-1" />
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
