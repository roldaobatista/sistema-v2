import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Send, FileText, Upload, Shield, RefreshCw, AlertCircle, CheckCircle2,
  Clock, XCircle, Eye,
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { toast } from 'sonner'
import { hrApi } from '@/lib/hr-api'
import type { ESocialEvent, ESocialCertificate, ESocialDashboard } from '@/types/hr'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'

const EVENT_TYPE_LABELS: Record<string, string> = {
  'S-1200': 'Remuneração de Trabalhador',
  'S-1210': 'Pagamentos de Rendimentos',
  'S-2200': 'Admissão',
  'S-2205': 'Alteração Cadastral',
  'S-2206': 'Alteração Contratual',
  'S-2210': 'CAT',
  'S-2220': 'ASO (Monitoramento Saúde)',
  'S-2230': 'Afastamento Temporário',
  'S-2240': 'Condições Ambientais',
  'S-2299': 'Desligamento',
}

const STATUS_CONFIG: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: typeof Clock }> = {
  pending: { label: 'Pendente', variant: 'outline', icon: Clock },
  generating: { label: 'Gerando', variant: 'secondary', icon: RefreshCw },
  sent: { label: 'Enviado', variant: 'default', icon: Send },
  accepted: { label: 'Aceito', variant: 'default', icon: CheckCircle2 },
  rejected: { label: 'Rejeitado', variant: 'destructive', icon: XCircle },
  cancelled: { label: 'Cancelado', variant: 'secondary', icon: XCircle },
}

export default function ESocialPage() {
  const queryClient = useQueryClient()
  const [activeTab, setActiveTab] = useState('dashboard')
  const [filters, setFilters] = useState<Record<string, string>>({})
  const [selectedEvents, setSelectedEvents] = useState<number[]>([])
  const [showGenerateDialog, setShowGenerateDialog] = useState(false)
  const [showDetailDialog, setShowDetailDialog] = useState(false)
  const [showCertDialog, setShowCertDialog] = useState(false)
  const [selectedEvent, setSelectedEvent] = useState<ESocialEvent | null>(null)
  const [generateForm, setGenerateForm] = useState({ event_type: '', related_type: '', related_id: '' })
  const [showExcludeDialog, setShowExcludeDialog] = useState(false)
  const [excludeTarget, setExcludeTarget] = useState<ESocialEvent | null>(null)
  const [excludeReason, setExcludeReason] = useState('')

  // Queries
  const { data: dashboardData, isLoading: loadingDashboard } = useQuery({
    queryKey: ['esocial-dashboard'],
    queryFn: () => hrApi.esocial.dashboard().then(r => r.data.data),
  })

  const { data: eventsData, isLoading: loadingEvents } = useQuery({
    queryKey: ['esocial-events', filters],
    queryFn: () => hrApi.esocial.events({ ...filters, per_page: 20 }).then(r => r.data),
    enabled: activeTab === 'events',
  })

  const { data: certificatesData, isLoading: loadingCerts } = useQuery({
    queryKey: ['esocial-certificates'],
    queryFn: () => hrApi.esocial.certificates().then(r => r.data.data),
    enabled: activeTab === 'certificates',
  })

  // Mutations
  const generateMutation = useMutation({
    mutationFn: (data: { event_type: string; related_type: string; related_id: number }) =>
      hrApi.esocial.generate(data),
    onSuccess: () => {
      toast.success('Evento eSocial gerado com sucesso.')
      queryClient.invalidateQueries({ queryKey: ['esocial-events'] })
      queryClient.invalidateQueries({ queryKey: ['esocial-dashboard'] })
      setShowGenerateDialog(false)
      setGenerateForm({ event_type: '', related_type: '', related_id: '' })
    },
    onError: () => toast.error('Erro ao gerar evento eSocial.'),
  })

  const sendBatchMutation = useMutation({
    mutationFn: (eventIds: number[]) => hrApi.esocial.sendBatch({ event_ids: eventIds }),
    onSuccess: (res) => {
      toast.success(`Lote enviado: ${res.data.data.batch_id}`)
      queryClient.invalidateQueries({ queryKey: ['esocial-events'] })
      queryClient.invalidateQueries({ queryKey: ['esocial-dashboard'] })
      setSelectedEvents([])
    },
    onError: () => toast.error('Erro ao enviar lote.'),
  })

  const uploadCertMutation = useMutation({
    mutationFn: (formData: FormData) => hrApi.esocial.uploadCertificate(formData),
    onSuccess: () => {
      toast.success('Certificado salvo com sucesso.')
      queryClient.invalidateQueries({ queryKey: ['esocial-certificates'] })
      queryClient.invalidateQueries({ queryKey: ['esocial-dashboard'] })
      setShowCertDialog(false)
    },
    onError: () => toast.error('Erro ao salvar certificado.'),
  })

  const excludeEventMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      hrApi.esocial.excludeEvent(id, reason),
    onSuccess: () => {
      toast.success('Evento excluído (S-3000) com sucesso.')
      queryClient.invalidateQueries({ queryKey: ['esocial-events'] })
      queryClient.invalidateQueries({ queryKey: ['esocial-dashboard'] })
      setShowExcludeDialog(false)
      setExcludeTarget(null)
      setExcludeReason('')
    },
    onError: () => toast.error('Erro ao excluir evento.'),
  })

  const generateRubricMutation = useMutation({
    mutationFn: () => hrApi.esocial.generateRubricTable(),
    onSuccess: () => {
      toast.success('Tabela de Rubricas (S-1010) gerada com sucesso.')
      queryClient.invalidateQueries({ queryKey: ['esocial-events'] })
      queryClient.invalidateQueries({ queryKey: ['esocial-dashboard'] })
    },
    onError: () => toast.error('Erro ao gerar tabela de rubricas.'),
  })

  const handleExcludeEvent = () => {
    if (!excludeTarget || !excludeReason.trim()) {
      toast.error('Informe o motivo da exclusão.')
      return
    }
    excludeEventMutation.mutate({ id: excludeTarget.id, reason: excludeReason })
  }

  const openExcludeDialog = (event: ESocialEvent) => {
    setExcludeTarget(event)
    setExcludeReason('')
    setShowExcludeDialog(true)
  }

  const handleGenerate = () => {
    if (!generateForm.event_type || !generateForm.related_type || !generateForm.related_id) {
      toast.error('Preencha todos os campos.')
      return
    }
    generateMutation.mutate({
      event_type: generateForm.event_type,
      related_type: generateForm.related_type,
      related_id: Number(generateForm.related_id),
    })
  }

  const handleSendBatch = () => {
    if (selectedEvents.length === 0) {
      toast.error('Selecione ao menos um evento pendente.')
      return
    }
    sendBatchMutation.mutate(selectedEvents)
  }

  const handleUploadCert = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const formData = new FormData(e.currentTarget)
    uploadCertMutation.mutate(formData)
  }

  const handleViewDetail = (event: ESocialEvent) => {
    setSelectedEvent(event)
    setShowDetailDialog(true)
  }

  const toggleEventSelection = (id: number) => {
    setSelectedEvents(prev =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    )
  }

  const dashboard = dashboardData as ESocialDashboard | undefined

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">eSocial</h1>
          <p className="text-muted-foreground">Eventos, certificados e integração com o governo federal</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setShowCertDialog(true)}>
            <Upload className="mr-2 h-4 w-4" />
            Certificado
          </Button>
          <Button
            variant="outline"
            onClick={() => generateRubricMutation.mutate()}
            disabled={generateRubricMutation.isPending}
          >
            {generateRubricMutation.isPending ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <FileText className="mr-2 h-4 w-4" />}
            Gerar S-1010 (Rubricas)
          </Button>
          <Button onClick={() => setShowGenerateDialog(true)}>
            <FileText className="mr-2 h-4 w-4" />
            Gerar Evento
          </Button>
        </div>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList>
          <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
          <TabsTrigger value="events">Eventos</TabsTrigger>
          <TabsTrigger value="certificates">Certificados</TabsTrigger>
        </TabsList>

        {/* ── Dashboard Tab ── */}
        <TabsContent value="dashboard" className="space-y-6">
          {loadingDashboard ? (
            <div className="flex items-center justify-center py-12">
              <RefreshCw className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : dashboard ? (
            <>
              {/* Status cards */}
              <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                <Card>
                  <CardContent className="pt-6">
                    <div className="text-2xl font-bold">{dashboard.counts.total}</div>
                    <p className="text-sm text-muted-foreground">Total</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="pt-6">
                    <div className="text-2xl font-bold text-yellow-600">{dashboard.counts.pending}</div>
                    <p className="text-sm text-muted-foreground">Pendentes</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="pt-6">
                    <div className="text-2xl font-bold text-blue-600">{dashboard.counts.sent}</div>
                    <p className="text-sm text-muted-foreground">Enviados</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="pt-6">
                    <div className="text-2xl font-bold text-green-600">{dashboard.counts.accepted}</div>
                    <p className="text-sm text-muted-foreground">Aceitos</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="pt-6">
                    <div className="text-2xl font-bold text-red-600">{dashboard.counts.rejected}</div>
                    <p className="text-sm text-muted-foreground">Rejeitados</p>
                  </CardContent>
                </Card>
              </div>

              {/* Certificate status */}
              {dashboard.certificate ? (
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Shield className="h-5 w-5" />
                      Certificado Digital
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="flex items-center gap-4">
                      <div>
                        <p className="text-sm text-muted-foreground">Emissor</p>
                        <p className="font-medium">{dashboard.certificate.issuer || 'N/A'}</p>
                      </div>
                      <div>
                        <p className="text-sm text-muted-foreground">Validade</p>
                        <p className="font-medium">
                          {dashboard.certificate.valid_until
                            ? format(new Date(dashboard.certificate.valid_until), 'dd/MM/yyyy', { locale: ptBR })
                            : 'N/A'}
                        </p>
                      </div>
                      <div>
                        <Badge variant={dashboard.certificate.is_expired ? 'destructive' : 'default'}>
                          {dashboard.certificate.is_expired ? 'Expirado' : 'Ativo'}
                        </Badge>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ) : (
                <Card>
                  <CardContent className="pt-6">
                    <div className="flex items-center gap-2 text-yellow-600">
                      <AlertCircle className="h-5 w-5" />
                      <span>Nenhum certificado digital cadastrado. Faça o upload para habilitar o envio.</span>
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Events by type */}
              {Object.keys(dashboard.by_type).length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle>Eventos por Tipo</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                      {Object.entries(dashboard.by_type).map(([type, count]) => (
                        <div key={type} className="flex items-center justify-between p-3 border rounded-lg">
                          <div>
                            <p className="text-xs text-muted-foreground">{type}</p>
                            <p className="text-xs">{EVENT_TYPE_LABELS[type] || type}</p>
                          </div>
                          <span className="text-lg font-bold">{count}</span>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Recent events */}
              {dashboard.recent_events && dashboard.recent_events.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle>Eventos Recentes</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-2">
                      {dashboard.recent_events.map((event) => {
                        const cfg = STATUS_CONFIG[event.status] || STATUS_CONFIG.pending
                        return (
                          <div key={event.id} className="flex items-center justify-between p-3 border rounded-lg">
                            <div className="flex items-center gap-3">
                              <Badge variant="outline">{event.event_type}</Badge>
                              <span className="text-sm">{EVENT_TYPE_LABELS[event.event_type] || event.event_type}</span>
                            </div>
                            <div className="flex items-center gap-3">
                              <Badge variant={cfg.variant}>{cfg.label}</Badge>
                              <span className="text-xs text-muted-foreground">
                                {format(new Date(event.created_at), 'dd/MM/yyyy HH:mm', { locale: ptBR })}
                              </span>
                            </div>
                          </div>
                        )
                      })}
                    </div>
                  </CardContent>
                </Card>
              )}
            </>
          ) : (
            <Card>
              <CardContent className="pt-6 text-center text-muted-foreground">
                Nenhum dado disponível.
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* ── Events Tab ── */}
        <TabsContent value="events" className="space-y-4">
          {/* Filters */}
          <Card>
            <CardContent className="pt-6">
              <div className="flex flex-wrap gap-3">
                <Select
                  value={filters.event_type || ''}
                  onValueChange={(v) => setFilters(f => ({ ...f, event_type: v === 'all' ? '' : v }))}
                >
                  <SelectTrigger className="w-48">
                    <SelectValue placeholder="Tipo de Evento" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos os tipos</SelectItem>
                    {Object.entries(EVENT_TYPE_LABELS).map(([key, label]) => (
                      <SelectItem key={key} value={key}>{key} - {label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                <Select
                  value={filters.status || ''}
                  onValueChange={(v) => setFilters(f => ({ ...f, status: v === 'all' ? '' : v }))}
                >
                  <SelectTrigger className="w-40">
                    <SelectValue placeholder="Status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos</SelectItem>
                    {Object.entries(STATUS_CONFIG).map(([key, cfg]) => (
                      <SelectItem key={key} value={key}>{cfg.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                <Input
                  type="date"
                  className="w-40"
                  placeholder="De"
                  value={filters.date_from || ''}
                  onChange={(e) => setFilters(f => ({ ...f, date_from: e.target.value }))}
                />
                <Input
                  type="date"
                  className="w-40"
                  placeholder="Até"
                  value={filters.date_to || ''}
                  onChange={(e) => setFilters(f => ({ ...f, date_to: e.target.value }))}
                />

                {selectedEvents.length > 0 && (
                  <Button onClick={handleSendBatch} disabled={sendBatchMutation.isPending}>
                    <Send className="mr-2 h-4 w-4" />
                    Enviar Lote ({selectedEvents.length})
                  </Button>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Events list */}
          {loadingEvents ? (
            <div className="flex items-center justify-center py-12">
              <RefreshCw className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : (
            <Card>
              <CardContent className="pt-6">
                <div className="space-y-2">
                  {eventsData?.data && eventsData.data.length > 0 ? (
                    eventsData.data.map((event: ESocialEvent) => {
                      const cfg = STATUS_CONFIG[event.status] || STATUS_CONFIG.pending
                      return (
                        <div
                          key={event.id}
                          className="flex items-center justify-between p-3 border rounded-lg hover:bg-muted/50 transition-colors"
                        >
                          <div className="flex items-center gap-3">
                            {event.status === 'pending' && (
                              <input
                                type="checkbox"
                                checked={selectedEvents.includes(event.id)}
                                onChange={() => toggleEventSelection(event.id)}
                                className="h-4 w-4 rounded border-gray-300"
                              />
                            )}
                            <Badge variant="outline">{event.event_type}</Badge>
                            <span className="text-sm">{EVENT_TYPE_LABELS[event.event_type] || event.event_type}</span>
                            {event.batch_id && (
                              <span className="text-xs text-muted-foreground">Lote: {event.batch_id}</span>
                            )}
                          </div>
                          <div className="flex items-center gap-3">
                            <Badge variant={cfg.variant}>{cfg.label}</Badge>
                            {event.error_message && (
                              <span className="text-xs text-red-500 max-w-48 truncate" title={event.error_message}>
                                {event.error_message}
                              </span>
                            )}
                            <span className="text-xs text-muted-foreground">
                              {format(new Date(event.created_at), 'dd/MM/yyyy HH:mm', { locale: ptBR })}
                            </span>
                            {event.status === 'accepted' && (
                              <Button
                                variant="ghost"
                                size="sm"
                                className="text-red-500"
                                onClick={() => openExcludeDialog(event)}
                              >
                                <XCircle className="h-4 w-4" />
                              </Button>
                            )}
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleViewDetail(event)}
                            >
                              <Eye className="h-4 w-4" />
                            </Button>
                          </div>
                        </div>
                      )
                    })
                  ) : (
                    <p className="text-center text-muted-foreground py-8">Nenhum evento encontrado.</p>
                  )}
                </div>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* ── Certificates Tab ── */}
        <TabsContent value="certificates" className="space-y-4">
          <div className="flex justify-end">
            <Button onClick={() => setShowCertDialog(true)}>
              <Upload className="mr-2 h-4 w-4" />
              Upload Certificado
            </Button>
          </div>

          {loadingCerts ? (
            <div className="flex items-center justify-center py-12">
              <RefreshCw className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : (
            <div className="grid gap-4">
              {certificatesData && certificatesData.length > 0 ? (
                certificatesData.map((cert: ESocialCertificate) => (
                  <Card key={cert.id}>
                    <CardContent className="pt-6">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-6">
                          <Shield className={`h-8 w-8 ${cert.is_expired ? 'text-red-500' : cert.is_active ? 'text-green-500' : 'text-gray-400'}`} />
                          <div>
                            <p className="font-medium">{cert.issuer || 'Emissor desconhecido'}</p>
                            <p className="text-sm text-muted-foreground">
                              Serial: {cert.serial_number || 'N/A'}
                            </p>
                          </div>
                          <div>
                            <p className="text-sm text-muted-foreground">Validade</p>
                            <p className="font-medium">
                              {cert.valid_from ? format(new Date(cert.valid_from), 'dd/MM/yyyy', { locale: ptBR }) : '?'}
                              {' - '}
                              {cert.valid_until ? format(new Date(cert.valid_until), 'dd/MM/yyyy', { locale: ptBR }) : '?'}
                            </p>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          {cert.is_expired && <Badge variant="destructive">Expirado</Badge>}
                          {cert.is_active && !cert.is_expired && <Badge variant="default">Ativo</Badge>}
                          {!cert.is_active && !cert.is_expired && <Badge variant="secondary">Inativo</Badge>}
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                ))
              ) : (
                <Card>
                  <CardContent className="pt-6 text-center text-muted-foreground">
                    Nenhum certificado cadastrado.
                  </CardContent>
                </Card>
              )}
            </div>
          )}
        </TabsContent>
      </Tabs>

      {/* ── Generate Event Dialog ── */}
      <Dialog open={showGenerateDialog} onOpenChange={setShowGenerateDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Gerar Evento eSocial</DialogTitle>
            <DialogDescription>Selecione o tipo de evento e a entidade relacionada.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Tipo de Evento</Label>
              <Select
                value={generateForm.event_type}
                onValueChange={(v) => setGenerateForm(f => ({ ...f, event_type: v }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o tipo" />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(EVENT_TYPE_LABELS).map(([key, label]) => (
                    <SelectItem key={key} value={key}>{key} - {label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Tipo de Entidade</Label>
              <Select
                value={generateForm.related_type}
                onValueChange={(v) => setGenerateForm(f => ({ ...f, related_type: v }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione a entidade" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="App\Models\User">Colaborador (User)</SelectItem>
                  <SelectItem value="App\Models\Payroll">Folha de Pagamento (Payroll)</SelectItem>
                  <SelectItem value="App\Models\Rescission">Rescisão (Rescission)</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>ID da Entidade</Label>
              <Input
                type="number"
                min={1}
                value={generateForm.related_id}
                onChange={(e) => setGenerateForm(f => ({ ...f, related_id: e.target.value }))}
                placeholder="Ex: 1"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowGenerateDialog(false)}>Cancelar</Button>
            <Button onClick={handleGenerate} disabled={generateMutation.isPending}>
              {generateMutation.isPending ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <FileText className="mr-2 h-4 w-4" />}
              Gerar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ── Event Detail Dialog ── */}
      <Dialog open={showDetailDialog} onOpenChange={setShowDetailDialog}>
        <DialogContent className="max-w-3xl max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              Evento {selectedEvent?.event_type} - {EVENT_TYPE_LABELS[selectedEvent?.event_type || ''] || ''}
            </DialogTitle>
            <DialogDescription>Detalhes do evento eSocial #{selectedEvent?.id}</DialogDescription>
          </DialogHeader>
          {selectedEvent && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label className="text-muted-foreground">Status</Label>
                  <div className="mt-1">
                    <Badge variant={STATUS_CONFIG[selectedEvent.status]?.variant || 'outline'}>
                      {STATUS_CONFIG[selectedEvent.status]?.label || selectedEvent.status}
                    </Badge>
                  </div>
                </div>
                <div>
                  <Label className="text-muted-foreground">Ambiente</Label>
                  <p className="mt-1">{selectedEvent.environment === 'production' ? 'Produção' : 'Homologação'}</p>
                </div>
                <div>
                  <Label className="text-muted-foreground">Protocolo</Label>
                  <p className="mt-1">{selectedEvent.protocol_number || 'N/A'}</p>
                </div>
                <div>
                  <Label className="text-muted-foreground">Recibo</Label>
                  <p className="mt-1">{selectedEvent.receipt_number || 'N/A'}</p>
                </div>
                <div>
                  <Label className="text-muted-foreground">Criado em</Label>
                  <p className="mt-1">{format(new Date(selectedEvent.created_at), 'dd/MM/yyyy HH:mm:ss', { locale: ptBR })}</p>
                </div>
                <div>
                  <Label className="text-muted-foreground">Enviado em</Label>
                  <p className="mt-1">{selectedEvent.sent_at ? format(new Date(selectedEvent.sent_at), 'dd/MM/yyyy HH:mm:ss', { locale: ptBR }) : 'N/A'}</p>
                </div>
                {selectedEvent.batch_id && (
                  <div>
                    <Label className="text-muted-foreground">Lote</Label>
                    <p className="mt-1 text-xs font-mono">{selectedEvent.batch_id}</p>
                  </div>
                )}
                {selectedEvent.error_message && (
                  <div className="col-span-2">
                    <Label className="text-muted-foreground">Erro</Label>
                    <p className="mt-1 text-red-500">{selectedEvent.error_message}</p>
                  </div>
                )}
              </div>

              {selectedEvent.xml_content && (
                <div>
                  <Label className="text-muted-foreground">XML do Evento</Label>
                  <pre className="mt-1 bg-muted p-4 rounded-lg text-xs overflow-x-auto max-h-64 whitespace-pre-wrap font-mono">
                    {selectedEvent.xml_content}
                  </pre>
                </div>
              )}

              {selectedEvent.response_xml && (
                <div>
                  <Label className="text-muted-foreground">XML de Resposta</Label>
                  <pre className="mt-1 bg-muted p-4 rounded-lg text-xs overflow-x-auto max-h-64 whitespace-pre-wrap font-mono">
                    {selectedEvent.response_xml}
                  </pre>
                </div>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* ── Upload Certificate Dialog ── */}
      <Dialog open={showCertDialog} onOpenChange={setShowCertDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Upload de Certificado Digital</DialogTitle>
            <DialogDescription>Envie o certificado A1 (.pfx/.p12) para integração com o eSocial.</DialogDescription>
          </DialogHeader>
          <form onSubmit={handleUploadCert} className="space-y-4">
            <div>
              <Label htmlFor="certificate">Arquivo do Certificado</Label>
              <Input
                id="certificate"
                name="certificate"
                type="file"
                accept=".pfx,.p12,.pem"
                required
                className="mt-1"
              />
            </div>
            <div>
              <Label htmlFor="password">Senha do Certificado</Label>
              <Input
                id="password"
                name="password"
                type="password"
                required
                className="mt-1"
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="serial_number">Número de Série</Label>
                <Input id="serial_number" name="serial_number" className="mt-1" />
              </div>
              <div>
                <Label htmlFor="issuer">Emissor</Label>
                <Input id="issuer" name="issuer" className="mt-1" />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="valid_from">Válido de</Label>
                <Input id="valid_from" name="valid_from" type="date" className="mt-1" />
              </div>
              <div>
                <Label htmlFor="valid_until">Válido até</Label>
                <Input id="valid_until" name="valid_until" type="date" className="mt-1" />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowCertDialog(false)}>Cancelar</Button>
              <Button type="submit" disabled={uploadCertMutation.isPending}>
                {uploadCertMutation.isPending ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}
                Salvar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* ── Exclude Event Dialog (S-3000) ── */}
      <Dialog open={showExcludeDialog} onOpenChange={setShowExcludeDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Excluir Evento (S-3000)</DialogTitle>
            <DialogDescription>
              Isso gerará um evento S-3000 de exclusão para o evento {excludeTarget?.event_type} #{excludeTarget?.id}.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Motivo da Exclusão</Label>
              <Input
                value={excludeReason}
                onChange={(e) => setExcludeReason(e.target.value)}
                placeholder="Informe o motivo da exclusão"
                className="mt-1"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowExcludeDialog(false)}>Cancelar</Button>
            <Button
              variant="destructive"
              onClick={handleExcludeEvent}
              disabled={excludeEventMutation.isPending || !excludeReason.trim()}
            >
              {excludeEventMutation.isPending ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <XCircle className="mr-2 h-4 w-4" />}
              Excluir Evento
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
