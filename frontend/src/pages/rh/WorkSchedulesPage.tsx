import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api, { getApiErrorMessage } from '@/lib/api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { toast } from 'sonner'
import { Plus, Clock, Edit, Trash2, Loader2 } from 'lucide-react'

const typeLabels: Record<string, string> = { fixed: 'Fixa', flexible: 'Flexível', shift: 'Turno', scale: 'Escala' }
const dayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

interface WorkDay { day: number; start_time: string; end_time: string; break_start?: string; break_end?: string }

export function WorkSchedulesPage() {
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ name: '', description: '', type: 'fixed', tolerance_minutes: 10, overtime_allowed: true, work_days: [] as WorkDay[] })

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['work-schedules'],
    queryFn: () => api.get('/work-schedules').then(r => r.data),
  })

  const schedules = data?.data ?? []

  useEffect(() => {
    if (isError) {
      toast.error(getApiErrorMessage(error, 'Erro ao carregar escalas'))
    }
  }, [error, isError])

  const saveMut = useMutation({
    mutationFn: (payload: Record<string, unknown>) => editId ? api.put(`/work-schedules/${editId}`, payload) : api.post('/work-schedules', payload),
    onSuccess: () => {
      toast.success(editId ? 'Escala atualizada' : 'Escala criada')
      setShowForm(false)
      setEditId(null)
      qc.invalidateQueries({ queryKey: ['work-schedules'] })
      broadcastQueryInvalidation(['work-schedules'], 'Escalas')
    },
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar')),
  })

  const deleteMut = useMutation({
    mutationFn: (id: number) => api.delete(`/work-schedules/${id}`),
    onSuccess: () => {
      toast.success('Escala excluída')
      qc.invalidateQueries({ queryKey: ['work-schedules'] })
      broadcastQueryInvalidation(['work-schedules'], 'Escalas')
    },
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao excluir')),
  })

  const openCreate = () => {
    setEditId(null)
    setForm({ name: '', description: '', type: 'fixed', tolerance_minutes: 10, overtime_allowed: true, work_days: [1, 2, 3, 4, 5].map(d => ({ day: d, start_time: '08:00', end_time: '17:00', break_start: '12:00', break_end: '13:00' })) })
    setShowForm(true)
  }

  const openEdit = (s: { id: number; name: string; description?: string; type: string; tolerance_minutes?: number; overtime_allowed?: boolean; work_days?: unknown[] }) => {
    setEditId(s.id)
        setForm({ name: s.name, description: s.description ?? '', type: s.type, tolerance_minutes: s.tolerance_minutes ?? 10, overtime_allowed: s.overtime_allowed ?? true, work_days: s.work_days ?? [] })
    setShowForm(true)
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Escalas de Trabalho" description="Gerencie horários e escalas dos colaboradores" action={<Button onClick={openCreate}><Plus className="w-4 h-4 mr-1" /> Nova Escala</Button>} />

      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="w-6 h-6 animate-spin text-muted-foreground" /></div>
      ) : isError ? (
        <div className="space-y-3 py-12 text-center">
          <p className="text-sm text-destructive">Erro ao carregar escalas</p>
          <Button variant="outline" onClick={() => refetch()}>
            Tentar novamente
          </Button>
        </div>
      ) : !schedules.length ? (
        <p className="text-sm text-muted-foreground text-center py-12">Nenhuma escala cadastrada.</p>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {(schedules || []).map((s: { id: number; name: string; description?: string; type: string; tolerance_minutes?: number; overtime_allowed?: boolean; work_days?: unknown[] }) => (
            <Card key={s.id}>
              <CardContent className="p-4 space-y-3">
                <div className="flex justify-between items-start">
                  <div>
                    <p className="font-medium flex items-center gap-2"><Clock className="w-4 h-4" /> {s.name}</p>
                    <Badge variant="secondary" className="mt-1">{typeLabels[s.type] ?? s.type}</Badge>
                  </div>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="icon" aria-label="Editar escala" onClick={() => openEdit(s)}><Edit className="w-4 h-4" /></Button>
                    <Button variant="ghost" size="icon" aria-label="Excluir escala" onClick={() => { if (confirm('Excluir esta escala?')) deleteMut.mutate(s.id) }}><Trash2 className="w-4 h-4 text-destructive" /></Button>
                  </div>
                </div>
                {s.description && <p className="text-sm text-muted-foreground">{s.description}</p>}
                <div className="flex gap-1 flex-wrap">
                                    {(s.work_days ?? []).map((wd: WorkDay) => (
                    <Badge key={wd.day} variant="outline" className="text-xs">{dayLabels[wd.day]} {wd.start_time}-{wd.end_time}</Badge>
                  ))}
                </div>
                <p className="text-xs text-muted-foreground">Tolerância: {s.tolerance_minutes ?? 0}min | HE: {s.overtime_allowed ? 'Sim' : 'Não'}</p>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="max-w-lg max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editId ? 'Editar Escala' : 'Nova Escala'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <Input aria-label="Nome da escala" placeholder="Nome da escala" value={form.name} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, name: e.target.value })} />
            <Input aria-label="Descrição da escala" placeholder="Descrição (opcional)" value={form.description} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, description: e.target.value })} />
            <select aria-label="Tipo de escala" className="w-full border rounded-md px-3 py-2 text-sm" value={form.type} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setForm({ ...form, type: e.target.value })}>
              <option value="fixed">Fixa</option>
              <option value="flexible">Flexível</option>
              <option value="shift">Turno</option>
              <option value="scale">Escala</option>
            </select>
            <Input aria-label="Tolerância em minutos" type="number" placeholder="Tolerância (min)" value={form.tolerance_minutes} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, tolerance_minutes: Number(e.target.value) })} />

            <div className="space-y-2">
              <label className="text-sm font-medium">Dias de trabalho</label>
              {(form.work_days || []).map((wd, i) => (
                <div key={i} className="flex gap-2 items-center text-sm">
                  <span className="w-10 font-medium">{dayLabels[wd.day]}</span>
                  <Input type="time" value={wd.start_time} onChange={(e: React.ChangeEvent<HTMLInputElement>) => { const wds = [...form.work_days]; wds[i] = { ...wds[i], start_time: e.target.value }; setForm({ ...form, work_days: wds }) }} className="w-28" />
                  <span>-</span>
                  <Input type="time" value={wd.end_time} onChange={(e: React.ChangeEvent<HTMLInputElement>) => { const wds = [...form.work_days]; wds[i] = { ...wds[i], end_time: e.target.value }; setForm({ ...form, work_days: wds }) }} className="w-28" />
                </div>
              ))}
            </div>

            <Button onClick={() => saveMut.mutate(form)} disabled={saveMut.isPending || !form.name}>
              {saveMut.isPending ? <Loader2 className="w-4 h-4 animate-spin mr-1" /> : null}
              {editId ? 'Salvar' : 'Criar'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}

export default WorkSchedulesPage
