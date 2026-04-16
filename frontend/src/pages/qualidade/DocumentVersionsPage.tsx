import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { FormField } from '@/components/ui/form-field'
import { toast } from 'sonner'
import { Plus, FileText, Loader2, Trash2 } from 'lucide-react'
import type { Resolver } from 'react-hook-form'
import { handleFormError } from '@/lib/form-utils'
import { optionalString, requiredString } from '@/schemas/common'
import { z } from 'zod'

const statusColors: Record<string, 'secondary' | 'warning' | 'default' | 'destructive'> = { draft: 'secondary', review: 'warning', approved: 'default', obsolete: 'destructive' }
const statusLabels: Record<string, string> = { draft: 'Rascunho', review: 'Em Revisão', approved: 'Aprovado', obsolete: 'Obsoleto' }
const typeLabels: Record<string, string> = { procedure: 'Procedimento', instruction: 'Instrução', form: 'Formulário', manual: 'Manual', policy: 'Política', record: 'Registro' }

const documentVersionSchema = z.object({
  title: requiredString('Título é obrigatório'),
  document_type: z.enum(['procedure', 'instruction', 'form', 'manual', 'policy', 'record']).default('procedure'),
  description: optionalString,
  effective_date: optionalString,
  review_date: optionalString,
})

type DocumentVersionFormData = {
  title: string
  document_type: 'procedure' | 'instruction' | 'form' | 'manual' | 'policy' | 'record'
  description: string
  effective_date: string
  review_date: string
}

const defaultValues: DocumentVersionFormData = {
  title: '',
  document_type: 'procedure',
  description: '',
  effective_date: '',
  review_date: '',
}

export function DocumentVersionsPage() {
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)

  const { register, handleSubmit, reset, setError, formState: { errors } } = useForm<DocumentVersionFormData>({
    resolver: zodResolver(documentVersionSchema) as Resolver<DocumentVersionFormData>,
    defaultValues,
  })

  const { data, isLoading } = useQuery({
    queryKey: ['document-versions'],
    queryFn: () => api.get('/document-versions').then(r => r.data),
  })

  const docs = data?.data ?? []

  const createMut = useMutation({
    mutationFn: (data: DocumentVersionFormData) => api.post('/document-versions', data),
    onSuccess: () => {
      toast.success('Documento criado')
      setShowForm(false)
      reset(defaultValues)
      qc.invalidateQueries({ queryKey: ['document-versions'] })
      broadcastQueryInvalidation(['document-versions'], 'Documentos')
    },
    onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao criar documento'),
  })

  const openForm = () => {
    reset(defaultValues)
    setShowForm(true)
  }

  const deleteMut = useMutation({
    mutationFn: (id: number) => api.delete(`/document-versions/${id}`),
    onSuccess: () => {
      toast.success('Documento excluído')
      qc.invalidateQueries({ queryKey: ['document-versions'] })
    },
  })

  return (
    <div className="space-y-6">
      <PageHeader title="Documentos Controlados" description="Controle de versões de documentos ISO/Qualidade" action={<Button onClick={openForm}><Plus className="w-4 h-4 mr-1" /> Novo Documento</Button>} />

      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="w-6 h-6 animate-spin text-muted-foreground" /></div>
      ) : !docs.length ? (
        <p className="text-sm text-muted-foreground text-center py-12">Nenhum documento cadastrado.</p>
      ) : (
        <div className="space-y-3">
          {(docs || []).map((d: { id: number; title: string; document_type: string; status: string; current_version?: string; version_number?: string; effective_date?: string }) => (
            <Card key={d.id}>
              <CardContent className="p-4 flex justify-between items-center">
                <div className="flex items-center gap-3">
                  <FileText className="w-5 h-5 text-muted-foreground" />
                  <div>
                    <p className="font-medium">{d.title}</p>
                    <div className="flex gap-2 items-center mt-1">
                      <Badge variant="outline">{typeLabels[d.document_type] ?? d.document_type}</Badge>
                      <Badge variant={statusColors[d.status]}>{statusLabels[d.status] ?? d.status}</Badge>
                      <span className="text-xs text-muted-foreground">v{d.version_number}</span>
                    </div>
                    {d.effective_date && <p className="text-xs text-muted-foreground mt-1">Vigência: {new Date(d.effective_date + 'T12:00:00').toLocaleDateString('pt-BR')}</p>}
                  </div>
                </div>
                <Button variant="ghost" size="icon" aria-label="Excluir documento" onClick={() => { if (confirm('Excluir documento?')) deleteMut.mutate(d.id) }}>
                  <Trash2 className="w-4 h-4 text-destructive" />
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent>
          <DialogHeader><DialogTitle>Novo Documento</DialogTitle></DialogHeader>
          <form onSubmit={handleSubmit((data: DocumentVersionFormData) => createMut.mutate(data))} className="space-y-4">
            <FormField label="Título" error={errors.title?.message} required>
              <Input {...register('title')} placeholder="Título" />
            </FormField>
            <FormField label="Tipo de documento" error={errors.document_type?.message}>
              <select {...register('document_type')} aria-label="Tipo de documento" className="w-full border rounded-md px-3 py-2 text-sm border-default bg-surface-50 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15">
                {Object.entries(typeLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
            </FormField>
            <FormField label="Descrição" error={errors.description?.message}>
              <Input {...register('description')} placeholder="Descrição" />
            </FormField>
            <div className="grid grid-cols-2 gap-3">
              <FormField label="Vigência" error={errors.effective_date?.message}>
                <Input {...register('effective_date')} type="date" />
              </FormField>
              <FormField label="Próxima Revisão" error={errors.review_date?.message}>
                <Input {...register('review_date')} type="date" />
              </FormField>
            </div>
            <Button type="submit" disabled={createMut.isPending}>
              {createMut.isPending ? <Loader2 className="w-4 h-4 animate-spin mr-1" /> : null} Criar
            </Button>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}

export default DocumentVersionsPage
