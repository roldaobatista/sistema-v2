import { useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import type { Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getPolicies, createPolicy, updatePolicy, deletePolicy } from '@/lib/crm-field-api'
import type { ContactPolicy } from '@/lib/crm-field-api'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { PageHeader } from '@/components/ui/pageheader'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Switch } from '@/components/ui/switch'
import { FormField } from '@/components/ui/form-field'
import { toast } from 'sonner'
import { ShieldCheck, Plus, Loader2, Pencil, Trash2, Clock } from 'lucide-react'
import { handleFormError } from '@/lib/form-utils'
import { optionalString, requiredString } from '@/schemas/common'
import { z } from 'zod'

const contactPolicySchema = z.object({
    name: requiredString('Nome é obrigatório'),
    target_type: z.enum(['all', 'rating', 'segment']).default('all'),
    target_value: optionalString,
    max_days_without_contact: z.coerce.number().min(1, 'Mín. 1 dia').default(30),
    warning_days_before: z.coerce.number().min(0).default(7),
    preferred_contact_type: optionalString,
    is_active: z.boolean().default(true),
    priority: z.coerce.number().default(0),
})

type ContactPolicyFormData = z.infer<typeof contactPolicySchema>

const defaultValues: ContactPolicyFormData = {
    name: '', target_type: 'all', target_value: '', max_days_without_contact: 30, warning_days_before: 7, preferred_contact_type: '', is_active: true, priority: 0,
}

export function CrmContactPoliciesPage() {
    const qc = useQueryClient()
    const [showDialog, setShowDialog] = useState(false)
    const [editing, setEditing] = useState<ContactPolicy | null>(null)

    const { register, handleSubmit, reset, control, setError, watch, formState: { errors } } = useForm<ContactPolicyFormData>({
        resolver: zodResolver(contactPolicySchema) as Resolver<ContactPolicyFormData>,
        defaultValues,
    })

    const { data: policies = [], isLoading } = useQuery<ContactPolicy[]>({ queryKey: ['contact-policies'], queryFn: getPolicies })

    const createMut = useMutation({
        mutationFn: (data: ContactPolicyFormData) => editing ? updatePolicy(editing.id, data) : createPolicy(data),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['contact-policies'] }); setShowDialog(false); setEditing(null); toast.success(editing ? 'Política atualizada!' : 'Política criada!') },
        onError: (err) => handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao salvar política'),
    })

    const deleteMut = useMutation({
        mutationFn: deletePolicy,
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['contact-policies'] }); toast.success('Política excluída!') },
    })

    const openEdit = (p: ContactPolicy) => {
        setEditing(p)
        reset({ name: p.name, target_type: p.target_type as 'all' | 'rating' | 'segment', target_value: p.target_value ?? '', max_days_without_contact: p.max_days_without_contact, warning_days_before: p.warning_days_before, preferred_contact_type: p.preferred_contact_type ?? '', is_active: p.is_active, priority: p.priority })
        setShowDialog(true)
    }

    const openCreate = () => {
        setEditing(null)
        reset(defaultValues)
        setShowDialog(true)
    }

    return (
        <div className="space-y-6">
            <PageHeader title="Políticas de Contato" description="Configure a frequência mínima de contato por tipo de cliente" />
            <div className="flex justify-end"><Button onClick={openCreate}><Plus className="h-4 w-4 mr-2" /> Nova Política</Button></div>

            {isLoading ? (
                <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-muted-foreground" /></div>
            ) : policies.length === 0 ? (
                <Card><CardContent className="py-12 text-center text-muted-foreground"><ShieldCheck className="h-12 w-12 mx-auto mb-4 opacity-30" /><p>Nenhuma política configurada</p></CardContent></Card>
            ) : (
                <div className="space-y-3">
                    {(policies || []).map(p => (
                        <Card key={p.id} className={!p.is_active ? 'opacity-50' : ''}>
                            <CardContent className="py-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <ShieldCheck className="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p className="font-medium">{p.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                <Clock className="h-3.5 w-3.5 inline mr-1" />
                                                Máx. {p.max_days_without_contact} dias sem contato · Alerta {p.warning_days_before}d antes ·
                                                {p.target_type === 'all' ? ' Todos os clientes' : p.target_type === 'rating' ? ` Rating ${p.target_value}` : ` Segmento: ${p.target_value}`}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={p.is_active ? 'default' : 'secondary'}>{p.is_active ? 'Ativa' : 'Inativa'}</Badge>
                                        <Button size="sm" variant="ghost" onClick={() => openEdit(p)}><Pencil className="h-3.5 w-3.5" /></Button>
                                        <Button size="sm" variant="ghost" onClick={() => { if (confirm('Excluir política?')) deleteMut.mutate(p.id) }}><Trash2 className="h-3.5 w-3.5" /></Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            <Dialog open={showDialog} onOpenChange={setShowDialog}>
                <DialogContent>
                    <DialogHeader><DialogTitle>{editing ? 'Editar Política' : 'Nova Política'}</DialogTitle></DialogHeader>
                    <form onSubmit={handleSubmit((data: ContactPolicyFormData) => createMut.mutate(data))} className="space-y-4">
                        <FormField label="Nome" error={errors.name?.message} required>
                            <Input {...register('name')} placeholder="Ex: Clientes A - Quinzenal" />
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <Controller control={control} name="target_type" render={({ field }) => (
                                <FormField label="Alvo" error={errors.target_type?.message}>
                                    <Select value={field.value} onValueChange={field.onChange}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent><SelectItem value="all">Todos</SelectItem><SelectItem value="rating">Rating</SelectItem><SelectItem value="segment">Segmento</SelectItem></SelectContent>
                                    </Select>
                                </FormField>
                            )} />
                            {watch('target_type') !== 'all' && (
                                <FormField label="Valor" error={errors.target_value?.message}>
                                    <Input {...register('target_value')} placeholder={watch('target_type') === 'rating' ? 'A, B, C ou D' : 'supermercado, farmacia...'} />
                                </FormField>
                            )}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Máx. Dias sem Contato *" error={errors.max_days_without_contact?.message} required>
                                <Input {...register('max_days_without_contact')} type="number" />
                            </FormField>
                            <FormField label="Alerta Dias Antes" error={errors.warning_days_before?.message}>
                                <Input {...register('warning_days_before')} type="number" />
                            </FormField>
                        </div>
                        <Controller control={control} name="is_active" render={({ field }) => (
                            <div className="flex items-center gap-2">
                                <Switch checked={field.value} onCheckedChange={field.onChange} />
                                <Label>Ativa</Label>
                            </div>
                        )} />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowDialog(false)}>Cancelar</Button>
                            <Button type="submit" disabled={createMut.isPending}>{createMut.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />}{editing ? 'Salvar' : 'Criar'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    )
}
