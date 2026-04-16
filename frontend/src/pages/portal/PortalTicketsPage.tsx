import { useEffect, useState } from 'react'
import type { ChangeEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Ticket, Loader2 } from 'lucide-react'
import api, { getApiErrorMessage } from '@/lib/api'
import { PageHeader } from '@/components/ui/pageheader'
import { Badge, type BadgeProps } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { toast } from 'sonner'

interface PortalTicket {
  id: number
  subject: string
  status: string
  ticket_number: string
  created_at: string
}

interface PortalTicketForm {
  subject: string
  description: string
  priority: string
  category: string
}

const statusLabels: Record<string, { label: string; variant: BadgeProps['variant'] }> = {
  open: { label: 'Aberto', variant: 'warning' },
  in_progress: { label: 'Em Andamento', variant: 'default' },
  resolved: { label: 'Resolvido', variant: 'secondary' },
  closed: { label: 'Fechado', variant: 'outline' },
}

export function PortalTicketsPage() {
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState<PortalTicketForm>({
    subject: '',
    description: '',
    priority: 'normal',
    category: '',
  })

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['portal-tickets'],
    queryFn: () => api.get('/portal/tickets').then((response) => response.data),
  })

  const tickets: PortalTicket[] = data?.data ?? []

  useEffect(() => {
    if (isError) {
      toast.error(getApiErrorMessage(error, 'Erro ao carregar tickets'))
    }
  }, [error, isError])

  const createTicket = useMutation({
    mutationFn: (payload: PortalTicketForm) => api.post('/portal/tickets', payload),
    onSuccess: () => {
      toast.success('Ticket criado')
      setShowForm(false)
      setForm({
        subject: '',
        description: '',
        priority: 'normal',
        category: '',
      })
      queryClient.invalidateQueries({ queryKey: ['portal-tickets'] })
    },
    onError: (error: unknown) => {
      const message = error && typeof error === 'object' && 'response' in error
        ? (error as { response?: { data?: { message?: string } } }).response?.data?.message
        : null

      toast.error(message ?? 'Erro ao criar ticket')
    },
  })

  return (
    <div className="space-y-6">
      <PageHeader
        title="Meus Tickets"
        description="Abra e acompanhe seus tickets de suporte"
        action={(
          <Button onClick={() => setShowForm(true)}>
            <Plus className="mr-1 h-4 w-4" />
            Novo Ticket
          </Button>
        )}
      />

      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : isError ? (
        <div className="py-12 text-center">
          <p className="text-sm text-destructive">Erro ao carregar tickets</p>
          <Button className="mt-3" variant="outline" onClick={() => refetch()}>
            Tentar novamente
          </Button>
        </div>
      ) : !tickets.length ? (
        <p className="py-12 text-center text-sm text-muted-foreground">Nenhum ticket encontrado.</p>
      ) : (
        <div className="space-y-3">
          {tickets.map((ticket) => (
            <Card key={ticket.id}>
              <CardContent className="flex items-center justify-between p-4">
                <div className="flex items-center gap-3">
                  <Ticket className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="font-medium">{ticket.subject}</p>
                    <div className="mt-1 flex gap-2">
                      <Badge variant={statusLabels[ticket.status]?.variant}>
                        {statusLabels[ticket.status]?.label ?? ticket.status}
                      </Badge>
                      <span className="text-xs text-muted-foreground">{ticket.ticket_number}</span>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {new Date(ticket.created_at).toLocaleDateString('pt-BR')}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Novo Ticket</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <Input
              aria-label="Assunto"
              label="Assunto"
              placeholder="Assunto"
              value={form.subject}
              onChange={(event: ChangeEvent<HTMLInputElement>) => setForm({ ...form, subject: event.target.value })}
            />
            <textarea
              aria-label="Descricao do ticket"
              className="min-h-[100px] w-full rounded-md border px-3 py-2 text-sm"
              placeholder="Descreva seu problema..."
              value={form.description}
              onChange={(event: ChangeEvent<HTMLTextAreaElement>) => setForm({ ...form, description: event.target.value })}
            />
            <select
              aria-label="Prioridade"
              className="w-full rounded-md border px-3 py-2 text-sm"
              value={form.priority}
              onChange={(event: ChangeEvent<HTMLSelectElement>) => setForm({ ...form, priority: event.target.value })}
            >
              <option value="low">Baixa</option>
              <option value="normal">Normal</option>
              <option value="high">Alta</option>
              <option value="urgent">Urgente</option>
            </select>
            <Button
              onClick={() => createTicket.mutate(form)}
              disabled={createTicket.isPending || !form.subject || !form.description}
            >
              {createTicket.isPending ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : null}
              Enviar
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}

export default PortalTicketsPage
