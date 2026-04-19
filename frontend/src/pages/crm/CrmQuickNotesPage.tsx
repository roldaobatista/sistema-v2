import { useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import type { Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getQuickNotes, createQuickNote, updateQuickNote, deleteQuickNote } from '@/lib/crm-field-api'
import type { QuickNote } from '@/lib/crm-field-api'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { FormField } from '@/components/ui/form-field'
import { toast } from 'sonner'
import { StickyNote, Plus, Loader2, Pin, SmilePlus, Meh, Frown, Phone, Monitor, MessageCircle, Mail, Trash2 } from 'lucide-react'
import api, { unwrapData } from '@/lib/api'
import { handleFormError } from '@/lib/form-utils'
import { requiredString } from '@/schemas/common'
import { z } from 'zod'
import { safeArray } from '@/lib/safe-array'

const fmtDate = (d: string) => new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
const channelIcons: Record<string, React.ElementType> = { phone: Phone, in_person: Monitor, whatsapp: MessageCircle, email: Mail }
const sentimentIcons: Record<string, { icon: React.ElementType; color: string }> = { positive: { icon: SmilePlus, color: 'text-green-600' }, neutral: { icon: Meh, color: 'text-amber-600' }, negative: { icon: Frown, color: 'text-red-600' } }

const quickNoteSchema = z.object({
    customer_id: z.string().min(1, 'Selecione um cliente'),
    channel: z.enum(['phone', 'in_person', 'whatsapp', 'email']).default('phone'),
    sentiment: z.enum(['positive', 'neutral', 'negative']).default('neutral'),
    content: requiredString('Conteúdo é obrigatório'),
})

type QuickNoteFormData = z.infer<typeof quickNoteSchema>

const defaultValues: QuickNoteFormData = { customer_id: '', channel: 'phone', sentiment: 'neutral', content: '' }

export function CrmQuickNotesPage() {
    const qc = useQueryClient()
    const [showDialog, setShowDialog] = useState(false)
    const [searchCustomer, setSearchCustomer] = useState('')

    const { register, handleSubmit, reset, setValue, setError, control, watch, formState: { errors } } = useForm<QuickNoteFormData>({
        resolver: zodResolver(quickNoteSchema) as Resolver<QuickNoteFormData>,
        defaultValues,
    })

    const { data: notesRes, isLoading } = useQuery({ queryKey: ['quick-notes'], queryFn: () => getQuickNotes() })
    const notes: QuickNote[] = notesRes?.data?.data ?? notesRes?.data ?? []

    const searchQ = useQuery({
        queryKey: ['customers-qn-search', searchCustomer],
        queryFn: () => api.get('/customers', { params: { search: searchCustomer, per_page: 8, is_active: true } }).then(r => safeArray<{ id: number; name: string }>(unwrapData(r))),
        enabled: searchCustomer.length >= 2,
    })

    const createMut = useMutation({
        mutationFn: (data: { customer_id: number; channel: string; sentiment: string; content: string }) => createQuickNote({ ...data, customer_id: data.customer_id }),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['quick-notes'] }); setShowDialog(false); reset(defaultValues); setSearchCustomer(''); toast.success('Nota registrada!') },
        onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao registrar nota'),
    })

    const deleteMut = useMutation({
        mutationFn: deleteQuickNote,
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['quick-notes'] }); toast.success('Nota excluída!') },
    })

    const pinMut = useMutation({
        mutationFn: ({ id, pinned }: { id: number; pinned: boolean }) => updateQuickNote(id, { is_pinned: pinned }),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['quick-notes'] }),
    })

    return (
        <div className="space-y-6">
            <PageHeader title="Notas Rápidas" description="Registre conversas e interações informais em segundos" />
            <div className="flex justify-end"><Button onClick={() => { reset(defaultValues); setSearchCustomer(''); setShowDialog(true) }}><Plus className="h-4 w-4 mr-2" /> Nova Nota</Button></div>

            {isLoading ? (
                <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-muted-foreground" /></div>
            ) : notes.length === 0 ? (
                <Card><CardContent className="py-12 text-center text-muted-foreground"><StickyNote className="h-12 w-12 mx-auto mb-4 opacity-30" /><p>Nenhuma nota ainda</p></CardContent></Card>
            ) : (
                <div className="space-y-2">
                    {(notes || []).map(note => {
                        const ChIcon = note.channel ? channelIcons[note.channel] ?? StickyNote : StickyNote
                        const si = note.sentiment ? sentimentIcons[note.sentiment] : null
                        return (
                            <Card key={note.id} className={`hover:shadow-sm transition-shadow ${note.is_pinned ? 'border-amber-300 bg-amber-50/30' : ''}`}>
                                <CardContent className="py-3">
                                    <div className="flex items-start gap-3">
                                        <ChIcon className="h-5 w-5 text-muted-foreground mt-0.5" />
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="font-medium text-sm">{note.customer?.name}</span>
                                                <span className="text-xs text-muted-foreground">{fmtDate(note.created_at)}</span>
                                                <span className="text-xs text-muted-foreground">por {note.user?.name}</span>
                                                {si && (() => { const SentimentIcon = si.icon; return <SentimentIcon className={`h-3.5 w-3.5 ${si.color}`} /> })()}
                                                {note.is_pinned && <Pin className="h-3.5 w-3.5 text-amber-600" />}
                                            </div>
                                            <p className="text-sm">{note.content}</p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button size="sm" variant="ghost" onClick={() => pinMut.mutate({ id: note.id, pinned: !note.is_pinned })}><Pin className={`h-3.5 w-3.5 ${note.is_pinned ? 'text-amber-600' : ''}`} /></Button>
                                            <Button size="sm" variant="ghost" onClick={() => { if (confirm('Excluir nota?')) deleteMut.mutate(note.id) }}><Trash2 className="h-3.5 w-3.5" /></Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )
                    })}
                </div>
            )}

            <Dialog open={showDialog} onOpenChange={setShowDialog}>
                <DialogContent>
                    <DialogHeader><DialogTitle>Nota Rápida</DialogTitle></DialogHeader>
                    <form onSubmit={handleSubmit((data: QuickNoteFormData) => createMut.mutate({ customer_id: Number(data.customer_id), channel: data.channel, sentiment: data.sentiment, content: data.content }))} className="space-y-4">
                        <FormField label="Cliente *" error={errors.customer_id?.message} required>
                            <div>
                                <Input placeholder="Buscar..." value={searchCustomer} onChange={e => setSearchCustomer(e.target.value)} />
                                {(searchQ.data ?? []).length > 0 && searchCustomer.length >= 2 && (
                                    <div className="border rounded-md max-h-32 overflow-auto mt-1 border-default">
                                        {(searchQ.data ?? []).map((c: { id: number; name: string }) => (
                                            <button key={c.id} type="button" className={`w-full text-left px-3 py-1.5 hover:bg-accent text-sm ${String(c.id) === watch('customer_id') ? 'bg-accent' : ''}`} onClick={() => { setValue('customer_id', String(c.id)); setSearchCustomer(c.name) }}>{c.name}</button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <Controller control={control} name="channel" render={({ field }) => (
                                <FormField label="Canal" error={errors.channel?.message}>
                                    <Select value={field.value} onValueChange={field.onChange}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="phone">Telefone</SelectItem><SelectItem value="in_person">Presencial</SelectItem><SelectItem value="whatsapp">WhatsApp</SelectItem><SelectItem value="email">E-mail</SelectItem></SelectContent></Select>
                                </FormField>
                            )} />
                            <Controller control={control} name="sentiment" render={({ field }) => (
                                <FormField label="Sentimento" error={errors.sentiment?.message}>
                                    <Select value={field.value} onValueChange={field.onChange}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="positive">Positivo</SelectItem><SelectItem value="neutral">Neutro</SelectItem><SelectItem value="negative">Negativo</SelectItem></SelectContent></Select>
                                </FormField>
                            )} />
                        </div>
                        <FormField label="Conteúdo *" error={errors.content?.message} required>
                            <Textarea {...register('content')} rows={3} placeholder="O que foi conversado..." />
                        </FormField>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowDialog(false)}>Cancelar</Button>
                            <Button type="submit" disabled={createMut.isPending}>{createMut.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />}Registrar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    )
}
