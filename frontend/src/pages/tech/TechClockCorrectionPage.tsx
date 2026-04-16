import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Clock, Send, Loader2 } from 'lucide-react'
import api, { getApiErrorMessage } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'
import { cn } from '@/lib/utils'

interface ClockAdjustment {
  id: number
  original_clock_in: string
  original_clock_out: string | null
  adjusted_clock_in: string | null
  adjusted_clock_out: string | null
  reason: string
  status: 'pending' | 'approved' | 'rejected'
  decided_at: string | null
  created_at: string
}

interface ClockEntry {
  id: number
  clock_in: string
  clock_out: string | null
}

const statusLabels: Record<string, string> = {
  pending: 'Pendente',
  approved: 'Aprovado',
  rejected: 'Rejeitado',
}

const statusColors: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
}

export default function TechClockCorrectionPage() {
  const qc = useQueryClient()
  const { user } = useAuthStore()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({
    time_clock_entry_id: '',
    adjusted_clock_in: '',
    adjusted_clock_out: '',
    reason: '',
  })

  const { data: adjustments, isLoading } = useQuery({
    queryKey: ['tech-clock-adjustments'],
    queryFn: () =>
      api.get('/hr/adjustments', { params: { user_id: user?.id } })
        .then((r) => {
          const payload = r.data?.data ?? r.data ?? []
          return Array.isArray(payload) ? payload : payload.data ?? []
        }),
    enabled: !!user?.id,
  })

  const { data: entries = [] } = useQuery<ClockEntry[]>({
    queryKey: ['tech-clock-entries-for-adjustment', user?.id],
    queryFn: () =>
      api.get('/hr/clock/my', { params: { per_page: 30 } })
        .then((r) => {
          const payload = r.data?.data ?? r.data ?? []
          return Array.isArray(payload) ? payload : payload.data ?? []
        }),
    enabled: !!user?.id && showForm,
  })

  const submitMut = useMutation({
    mutationFn: (data: typeof form) =>
      api.post('/hr/adjustments', {
        time_clock_entry_id: Number(data.time_clock_entry_id),
        adjusted_clock_in: data.adjusted_clock_in || null,
        adjusted_clock_out: data.adjusted_clock_out || null,
        reason: data.reason,
      }),
    onSuccess: () => {
      toast.success('Solicitação de correção enviada')
      qc.invalidateQueries({ queryKey: ['tech-clock-adjustments'] })
      setShowForm(false)
      setForm({ time_clock_entry_id: '', adjusted_clock_in: '', adjusted_clock_out: '', reason: '' })
    },
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao enviar solicitação')),
  })

  const list: ClockAdjustment[] = adjustments ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Correção de Ponto"
        subtitle="Solicite ajustes nas suas marcações de ponto"
        icon={Clock}
      />

      <div className="flex justify-end">
        <Button size="sm" onClick={() => setShowForm(!showForm)} aria-label="Nova solicitação">
          <Send className="mr-1 h-4 w-4" />
          {showForm ? 'Cancelar' : 'Nova Solicitação'}
        </Button>
      </div>

      {showForm && (
        <div className="rounded-lg border p-4 space-y-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="text-sm font-medium" htmlFor="corr-entry">Registro original</label>
              <select
                id="corr-entry"
                className="mt-1 w-full rounded-md border px-3 py-1.5 text-sm"
                value={form.time_clock_entry_id}
                onChange={(e) => setForm((f) => ({ ...f, time_clock_entry_id: e.target.value }))}
              >
                <option value="">Selecione o registro</option>
                {entries.map((entry) => (
                  <option key={entry.id} value={entry.id}>
                    {new Date(entry.clock_in).toLocaleString('pt-BR')}
                    {entry.clock_out ? ` - ${new Date(entry.clock_out).toLocaleTimeString('pt-BR')}` : ''}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="text-sm font-medium" htmlFor="corr-in">Entrada Correta</label>
              <input
                id="corr-in"
                type="datetime-local"
                className="mt-1 w-full rounded-md border px-3 py-1.5 text-sm"
                value={form.adjusted_clock_in}
                onChange={(e) => setForm((f) => ({ ...f, adjusted_clock_in: e.target.value }))}
              />
            </div>
            <div>
              <label className="text-sm font-medium" htmlFor="corr-out">Saída Correta</label>
              <input
                id="corr-out"
                type="datetime-local"
                className="mt-1 w-full rounded-md border px-3 py-1.5 text-sm"
                value={form.adjusted_clock_out}
                onChange={(e) => setForm((f) => ({ ...f, adjusted_clock_out: e.target.value }))}
              />
            </div>
          </div>
          <div>
            <label className="text-sm font-medium" htmlFor="corr-reason">Motivo</label>
            <textarea
              id="corr-reason"
              className="mt-1 w-full rounded-md border px-3 py-1.5 text-sm"
              rows={2}
              placeholder="Descreva o motivo da correção..."
              value={form.reason}
              onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
            />
          </div>
          <Button
            size="sm"
            onClick={() => submitMut.mutate(form)}
            disabled={submitMut.isPending || !form.time_clock_entry_id || !form.reason}
            aria-label="Enviar solicitação de correção"
          >
            Enviar Solicitação
          </Button>
        </div>
      )}

      {isLoading ? (
        <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground" />
      ) : list.length === 0 ? (
        <div className="rounded-lg border border-dashed p-6 text-center text-muted-foreground">
          Nenhuma solicitação de correção encontrada.
        </div>
      ) : (
        <div className="space-y-2">
          {list.map((adj) => (
            <div key={adj.id} className="flex items-center justify-between rounded border px-3 py-2 text-sm">
              <div>
                <span className="font-medium">
                  {new Date(adj.created_at).toLocaleDateString('pt-BR')}
                </span>
                <span className="ml-2 text-muted-foreground">{adj.reason}</span>
              </div>
              <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', statusColors[adj.status])}>
                {statusLabels[adj.status] ?? adj.status}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
