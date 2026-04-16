import { useState, useEffect } from 'react'
import { useForm, Controller, useFieldArray } from 'react-hook-form'
import type { Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { Plus, Trash2, Loader2, Zap, CheckCircle2, Sparkles, Sprout, MapPin, AlertTriangle, DollarSign, Briefcase} from 'lucide-react'
import api from '@/lib/api'
import { customerApi } from '@/lib/customer-api'
import { queryKeys } from '@/lib/query-keys'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { IconButton } from '@/components/ui/iconbutton'
import { Badge } from '@/components/ui/badge'
import { FormField } from '@/components/ui/form-field'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { toast } from 'sonner'
import { handleFormError } from '@/lib/form-utils'
import { customerSchema, type CustomerFormData } from '@/schemas/customer'
import { maskPhone as maskPhoneUtil } from '@/lib/form-masks'
import { getApiErrorMessage } from '@/lib/api'
import type { CustomerWithContacts } from '@/types'

interface SellerOption {
  id: number
  name: string
}

interface ExternalLookupResponse {
  source?: string
  name?: string | null
  trade_name?: string | null
  email?: string | null
  phone?: string | null
  phone2?: string | null
  address_zip?: string | number | null
  address_street?: string | null
  address_number?: string | null
  address_complement?: string | null
  address_neighborhood?: string | null
  address_city?: string | null
  address_state?: string | null
  cnae_code?: string | null
  cnae_description?: string | null
  legal_nature?: string | null
  capital?: number | null
  company_status?: string | null
  opened_at?: string | null
  simples_nacional?: boolean | null
  mei?: boolean | null
  partners?: CustomerFormData['partners']
  secondary_activities?: CustomerFormData['secondary_activities']
  company_size?: string | null
}

interface ApiError {
  response?: {
    status?: number
    data?: {
      message?: string
      errors?: Record<string, string[]>
    }
  }
}

function maskCpfCnpj(value: string): string {
  const digits = value.replace(/\D/g, '')
  if (digits.length <= 11) {
    return digits.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')
  }
  return digits.replace(/^(\d{2})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2')
}

function maskPhone(value: string): string {
  return maskPhoneUtil(value)
}

function toDateInput(val: string | null | undefined): string {
  if (!val) return ''
  return val.substring(0, 10)
}

const defaultValues: CustomerFormData = {
  type: 'PJ', name: '', trade_name: '', document: '', email: '', phone: '', phone2: '', notes: '', is_active: true,
  address_zip: '', address_street: '', address_number: '', address_complement: '', address_neighborhood: '', address_city: '', address_state: '',
  latitude: '', longitude: '', google_maps_link: '',
  state_registration: '', municipal_registration: '', cnae_code: '', cnae_description: '', legal_nature: '', capital: '',
  simples_nacional: null, mei: null, company_status: '', opened_at: '', is_rural_producer: false, partners: [], secondary_activities: [],
  source: '', segment: '', company_size: '', rating: '', assigned_seller_id: '',
  annual_revenue_estimate: '', contract_type: '', contract_start: '', contract_end: '', contacts: [],
}

interface Props {
  open: boolean
  onClose: () => void
  customerId: number
  onSaved?: () => void
}

export function CustomerEditSheet({ open, onClose, customerId, onSaved }: Props) {
  const qc = useQueryClient()
  const [activeTab, setActiveTab] = useState<'info' | 'empresa' | 'address' | 'crm' | 'contacts'>('info')
  const [lookupLoading, setLookupLoading] = useState(false)
  const [enrichmentData, setEnrichmentData] = useState<ExternalLookupResponse | null>(null)

  const { register, control, handleSubmit, reset, setValue, getValues, watch, setError, formState: { errors } } = useForm<CustomerFormData>({
    resolver: zodResolver(customerSchema) as Resolver<CustomerFormData>,
    defaultValues,
  })
  const { fields: contactFields, append: appendContact, remove: removeContact, update: _updateContact } = useFieldArray({ control, name: 'contacts' })

  const { data: customerRes } = useQuery({
    queryKey: queryKeys.customers.detail(customerId),
    queryFn: () => customerApi.detail(customerId),
    enabled: open && !!customerId,
    staleTime: 0,
  })
  const customerData: CustomerWithContacts | undefined = customerRes

  const { data: crmOptions } = useQuery({
    queryKey: queryKeys.customers.options,
    queryFn: () => customerApi.options(),
    staleTime: 5 * 60 * 1000,
  })

  const { data: sellersRes } = useQuery({
    queryKey: queryKeys.customers.sellersOptions,
    queryFn: () => api.get('/users', { params: { role: 'vendedor', per_page: 100 } }).then(r => r.data),
    staleTime: 5 * 60 * 1000,
  })
  const sellers = (sellersRes?.data?.data ?? sellersRes?.data ?? []) as SellerOption[]

  useEffect(() => {
    if (customerData && open) {
      const c = customerData
      reset({
        type: (c.type ?? 'PJ') as 'PF' | 'PJ', name: c.name ?? '', trade_name: c.trade_name ?? '', document: c.document ?? '',
        email: c.email ?? '', phone: c.phone ?? '', phone2: c.phone2 ?? '', notes: c.notes ?? '', is_active: c.is_active ?? true,
        address_zip: c.address_zip ?? '', address_street: c.address_street ?? '', address_number: c.address_number ?? '',
        address_complement: c.address_complement ?? '', address_neighborhood: c.address_neighborhood ?? '',
        address_city: c.address_city ?? '', address_state: c.address_state ?? '',
        latitude: c.latitude != null ? String(c.latitude) : '', longitude: c.longitude != null ? String(c.longitude) : '',
        google_maps_link: String(c.google_maps_link ?? ''),
        state_registration: String(c.state_registration ?? ''), municipal_registration: String(c.municipal_registration ?? ''),
        cnae_code: String(c.cnae_code ?? ''), cnae_description: String(c.cnae_description ?? ''), legal_nature: String(c.legal_nature ?? ''), capital: String(c.capital ?? ''),
        simples_nacional: c.simples_nacional === true || c.simples_nacional === false ? c.simples_nacional : null,
        mei: c.mei === true || c.mei === false ? c.mei : null,
        company_status: typeof c.company_status === 'string' ? c.company_status : '',
        opened_at: toDateInput(c.opened_at),
        is_rural_producer: c.is_rural_producer === true || c.is_rural_producer === false ? c.is_rural_producer : false,
        partners: (c.partners ?? []) as CustomerFormData['partners'],
        secondary_activities: (c.secondary_activities ?? []) as CustomerFormData['secondary_activities'],
        source: c.source ?? '', segment: c.segment ?? '',
        company_size: typeof c.company_size === 'string' ? c.company_size : '',
        rating: c.rating ?? '',
        assigned_seller_id: c.assigned_seller_id?.toString() ?? '',
        annual_revenue_estimate: c.annual_revenue_estimate != null ? String(c.annual_revenue_estimate) : '',
        contract_type: c.contract_type ?? '',
        contract_start: toDateInput(c.contract_start),
        contract_end: toDateInput(c.contract_end),
        contacts: (Array.isArray(c.contacts) ? c.contacts : []).map((ct) => ({
          id: ct.id, name: ct.name ?? '', role: ct.role ?? '', phone: ct.phone ?? '', email: ct.email ?? '', is_primary: ct.is_primary ?? false,
        })),
      })
      setActiveTab('info')
      setEnrichmentData(null)
    }
  }, [customerData, open, reset])

  const saveMut = useMutation({
    mutationFn: (data: CustomerFormData) => {
      const sanitized: Record<string, unknown> = { ...data }
      const nullableStrings = ['trade_name', 'email', 'phone', 'phone2', 'source', 'segment', 'company_size', 'rating', 'assigned_seller_id', 'state_registration', 'municipal_registration', 'cnae_code', 'cnae_description', 'legal_nature', 'capital', 'company_status', 'opened_at', 'annual_revenue_estimate', 'contract_type', 'contract_start', 'contract_end', 'google_maps_link']
      for (const k of nullableStrings) { if (sanitized[k] === '') sanitized[k] = null }
      if (sanitized.assigned_seller_id) sanitized.assigned_seller_id = Number(sanitized.assigned_seller_id)
      if (sanitized.capital && typeof sanitized.capital === 'string') sanitized.capital = parseFloat(sanitized.capital) || null
      if (sanitized.annual_revenue_estimate && typeof sanitized.annual_revenue_estimate === 'string') sanitized.annual_revenue_estimate = parseFloat(sanitized.annual_revenue_estimate as string) || null
      sanitized.latitude = (sanitized.latitude as string) ? parseFloat(sanitized.latitude as string) || null : null
      sanitized.longitude = (sanitized.longitude as string) ? parseFloat(sanitized.longitude as string) || null : null
      if (enrichmentData && (enrichmentData as { source?: string }).source !== 'cpf_validation') {
        sanitized.enrichment_data = enrichmentData
        sanitized.enriched_at = new Date().toISOString()
      }
      return customerApi.update(customerId, sanitized)
    },
    onSuccess: () => {
      toast.success('Cliente atualizado!')
      qc.invalidateQueries({ queryKey: queryKeys.customers.all })
      qc.invalidateQueries({ queryKey: queryKeys.customers.customer360(customerId) })
      qc.invalidateQueries({ queryKey: queryKeys.customers.detail(customerId) })
      broadcastQueryInvalidation(['customers', 'customers-search'], 'Cliente')
      onSaved?.()
      onClose()
    },
    onError: (err) => {
      handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao salvar cliente')
      const data = (err as AxiosError<{ errors?: Record<string, string[]> }>)?.response?.data
      if (data?.errors?.document) setActiveTab('info')
    },
  })

  const lookupCep = async (cep: string) => {
    const digits = cep.replace(/\D/g, '')
    if (digits.length !== 8) return
    try {
      const r = await api.get(`/external/cep/${digits}`)
      if (r.data && !r.data.erro) {
        setValue('address_street', r.data.logradouro || getValues('address_street'))
        setValue('address_neighborhood', r.data.bairro || getValues('address_neighborhood'))
        setValue('address_city', r.data.localidade || getValues('address_city'))
        setValue('address_state', r.data.uf || getValues('address_state'))
      }
    } catch (err: unknown) {
      toast.error(getApiErrorMessage(err, 'Nao foi possivel consultar o CEP informado'))
    }
  }

  const mapCompanySize = (raw: string | null): string => {
    if (!raw) return ''
    const lower = raw.toLowerCase()
    if (lower.includes('micro') || lower === 'mei') return 'micro'
    if (lower.includes('peque')) return 'pequena'
    if (lower.includes('méd') || lower.includes('med')) return 'media'
    if (lower.includes('grand')) return 'grande'
    return ''
  }

  const lookupDocument = async (doc: string) => {
    const digits = doc.replace(/\D/g, '')
    if (digits.length !== 14 && digits.length !== 11) return
    setLookupLoading(true); setEnrichmentData(null)
    try {
      const r = await api.get(`/external/document/${digits}`)
      const d = r.data as ExternalLookupResponse | null
      if (!d) { toast.error('Documento não encontrado'); return }
      if (d.source === 'cpf_validation') { toast.success('CPF válido!'); setEnrichmentData(d); return }
      if (!getValues('name')) setValue('name', d.name ?? '')
      if (!getValues('trade_name')) setValue('trade_name', d.trade_name ?? '')
      if (!getValues('email')) setValue('email', d.email ?? '')
      if (!getValues('phone')) setValue('phone', d.phone ? maskPhone(d.phone) : '')
      if (!getValues('phone2')) setValue('phone2', d.phone2 ? maskPhone(d.phone2) : '')
      if (!getValues('address_zip')) setValue('address_zip', d.address_zip ? String(d.address_zip).replace(/\D/g, '') : '')
      if (!getValues('address_street')) setValue('address_street', d.address_street ?? '')
      if (!getValues('address_number')) setValue('address_number', d.address_number ?? '')
      if (!getValues('address_complement')) setValue('address_complement', d.address_complement ?? '')
      if (!getValues('address_neighborhood')) setValue('address_neighborhood', d.address_neighborhood ?? '')
      if (!getValues('address_city')) setValue('address_city', d.address_city ?? '')
      if (!getValues('address_state')) setValue('address_state', d.address_state ?? '')
      if (!getValues('cnae_code')) setValue('cnae_code', d.cnae_code ?? '')
      if (!getValues('cnae_description')) setValue('cnae_description', d.cnae_description ?? '')
      if (!getValues('legal_nature')) setValue('legal_nature', d.legal_nature ?? '')
      if (!getValues('capital')) setValue('capital', d.capital != null ? String(d.capital) : '')
      if (!getValues('company_status')) setValue('company_status', d.company_status ?? '')
      if (!getValues('opened_at')) setValue('opened_at', d.opened_at ?? '')
      if (d.simples_nacional != null) setValue('simples_nacional', d.simples_nacional)
      if (d.mei != null) setValue('mei', d.mei)
      if (d.partners?.length) setValue('partners', d.partners)
      if (d.secondary_activities?.length) setValue('secondary_activities', d.secondary_activities)
      if (!getValues('company_size')) setValue('company_size', mapCompanySize(d.company_size ?? null) || '')
      setEnrichmentData(d)
      toast.success('Dados atualizados da Receita Federal!')
    } catch (err: unknown) {
      const error = err as ApiError
      if (error.response?.status === 404) toast.error('Documento não encontrado')
      else if (error.response?.status === 422) toast.error(getApiErrorMessage(err, 'Documento invalido'))
      else toast.error(getApiErrorMessage(err, 'Erro ao consultar documento'))
    } finally { setLookupLoading(false) }
  }

  const parseGoogleMapsLink = (link: string) => {
    if (!link) return
    const regex = /(@|q=|query=|place\/|search\/)(-?\d+\.\d+),\s*(-?\d+\.\d+)/
    const match = link.match(regex)
    if (match) { setValue('latitude', match[2]); setValue('longitude', match[3]); toast.success('Coordenadas extraídas!') }
    else {
      const raw = link.trim().match(/^(-?\d+\.\d+),\s*(-?\d+\.\d+)$/)
      if (raw) { setValue('latitude', raw[1]); setValue('longitude', raw[2]); toast.success('Coordenadas identificadas!') }
      else toast.info('Não foi possível extrair coordenadas')
    }
  }

  const addContact = () => appendContact({ name: '', role: '', phone: '', email: '', is_primary: false })
  const removeContactAt = (i: number) => removeContact(i)

  const fmtBRL = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

  return (
    <Sheet open={open} onOpenChange={v => !v && onClose()}>
      <SheetContent side="right" className="!w-full !max-w-2xl overflow-y-auto">
        <SheetHeader className="mb-4">
          <SheetTitle>Editar Cliente</SheetTitle>
        </SheetHeader>

        <div className="flex border-b border-default mb-4 overflow-x-auto">
          {(['info', 'empresa', 'address', 'crm', 'contacts'] as const).map(tab => (
            <button key={tab} onClick={() => setActiveTab(tab)} className={`px-4 py-2 text-sm font-medium whitespace-nowrap ${activeTab === tab ? 'border-b-2 border-brand-600 text-brand-600' : 'text-surface-500 hover:text-surface-700'}`}>
              {{ info: 'Informações', empresa: 'Dados Empresa', address: 'Endereço', crm: 'CRM', contacts: 'Contatos' }[tab]}
            </button>
          ))}
        </div>

        {activeTab === 'info' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Controller
              control={control}
              name="type"
              render={({ field }) => (
                <div className="sm:col-span-2">
                  <label className="block text-sm font-medium text-surface-700 mb-1">Tipo *</label>
                  <div className="flex gap-2">
                    {(['PJ', 'PF'] as const).map(t => (
                      <button key={t} type="button" onClick={() => field.onChange(t)} className={`flex-1 py-2 px-3 rounded-lg text-sm font-medium border transition-colors ${field.value === t ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-default text-surface-600 hover:bg-surface-50'}`}>
                        {t === 'PJ' ? 'Pessoa Jurídica' : 'Pessoa Física'}
                      </button>
                    ))}
                  </div>
                </div>
              )}
            />
            <div className="sm:col-span-2">
              <FormField label={watch('type') === 'PJ' ? 'CNPJ' : 'CPF'}>
                <div className="flex gap-2 items-start">
                  <Controller
                    control={control}
                    name="document"
                    render={({ field }) => (
                      <input
                        value={field.value}
                        onChange={(e) => {
                          const m = maskCpfCnpj(e.target.value)
                          field.onChange(m)
                          if (m.replace(/\D/g, '').length === 14) setValue('type', 'PJ')
                          else if (m.replace(/\D/g, '').length === 11) setValue('type', 'PF')
                        }}
                        maxLength={watch('type') === 'PJ' ? 18 : 14}
                        placeholder={watch('type') === 'PJ' ? '00.000.000/0000-00' : '000.000.000-00'}
                        className="flex-1 px-3 py-2.5 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0 font-mono"
                      />
                    )}
                  />
                  <button type="button" onClick={() => lookupDocument(getValues('document'))} disabled={lookupLoading || getValues('document').replace(/\D/g, '').length < 11} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-gradient-to-r from-brand-600 to-emerald-600 hover:from-brand-500 hover:to-emerald-500 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm">
                    {lookupLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : enrichmentData ? <CheckCircle2 className="h-4 w-4" /> : <Zap className="h-4 w-4" />}
                    {lookupLoading ? 'Consultando...' : enrichmentData ? 'Consultado' : 'Consultar'}
                  </button>
                </div>
              </FormField>
            </div>
            {enrichmentData && (enrichmentData as { source?: string }).source !== 'cpf_validation' && (
              <div className="sm:col-span-2 rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50/50 to-emerald-50/30 p-4">
                <div className="flex items-center gap-2 mb-3">
                  <Sparkles className="h-4 w-4 text-brand-600" />
                  <h4 className="text-sm font-bold text-brand-800">Dados da Receita Federal</h4>
                  <Badge variant="info" size="sm">{(enrichmentData as { source?: string }).source}</Badge>
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                  {(enrichmentData as { capital?: number }).capital != null && <div className="rounded-lg bg-surface-0/60 p-2.5 border border-brand-100"><div className="text-surface-400 flex items-center gap-1"><DollarSign className="h-3 w-3" /> Capital</div><div className="font-bold text-surface-900 mt-0.5">{fmtBRL((enrichmentData as { capital: number }).capital)}</div></div>}
                  {(enrichmentData as { cnae_code?: string }).cnae_code && <div className="rounded-lg bg-surface-0/60 p-2.5 border border-brand-100"><div className="text-surface-400 flex items-center gap-1"><Briefcase className="h-3 w-3" /> CNAE</div><div className="font-bold text-surface-900 mt-0.5">{(enrichmentData as { cnae_code: string }).cnae_code}</div></div>}
                </div>
              </div>
            )}
            <div className="sm:col-span-2">
              <FormField label={watch('type') === 'PJ' ? 'Razão Social *' : 'Nome Completo *'} error={errors.name?.message} required>
                <Input {...register('name')} />
              </FormField>
            </div>
            {watch('type') === 'PJ' && (
              <div className="sm:col-span-2">
                <FormField label="Nome Fantasia" error={errors.trade_name?.message}>
                  <Input {...register('trade_name')} />
                </FormField>
              </div>
            )}
            <FormField label="E-mail" error={errors.email?.message}>
              <Input {...register('email')} type="email" />
            </FormField>
            <Controller control={control} name="phone" render={({ field }) => <FormField label="Telefone"><Input {...field} onChange={e => field.onChange(maskPhone(e.target.value))} maxLength={15} placeholder="(00) 00000-0000" /></FormField>} />
            <Controller control={control} name="phone2" render={({ field }) => <FormField label="Telefone 2"><Input {...field} onChange={e => field.onChange(maskPhone(e.target.value))} maxLength={15} /></FormField>} />
            <div className="sm:col-span-2 border-t border-default mt-2 pt-4">
              <h4 className="text-sm font-semibold text-surface-900 mb-3 flex items-center gap-2"><Sprout className="h-4 w-4 text-emerald-600" /> Produtor Rural / Registros</h4>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <Controller control={control} name="is_rural_producer" render={({ field }) => (
                  <div className="flex items-center gap-2"><input type="checkbox" id="edit_rural" checked={field.value} onChange={e => field.onChange(e.target.checked)} className="rounded border-default" /><label htmlFor="edit_rural" className="text-sm text-surface-700">Produtor Rural</label></div>
                )} />
                <FormField label="Inscrição Estadual"><Input {...register('state_registration')} /></FormField>
                <FormField label="Inscrição Municipal"><Input {...register('municipal_registration')} /></FormField>
              </div>
            </div>
            <FormField label="Observações" error={errors.notes?.message}>
              <textarea id="edit_notes" {...register('notes')} className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none" rows={3} />
            </FormField>
            <div className="sm:col-span-2 flex items-center gap-2">
              <Controller control={control} name="is_active" render={({ field }) => (
                <> <input type="checkbox" id="edit_active" checked={field.value} onChange={e => field.onChange(e.target.checked)} className="rounded border-default" /> <label htmlFor="edit_active" className="text-sm text-surface-700">Cliente ativo</label> </>
              )} />
            </div>
          </div>
        )}

        {activeTab === 'empresa' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="sm:col-span-2 flex items-center gap-3 rounded-lg border border-surface-200 bg-surface-50 p-3">
              <AlertTriangle className="h-4 w-4 text-amber-500 shrink-0" />
              <p className="text-xs text-surface-600">Preenchidos automaticamente ao consultar o CNPJ.</p>
            </div>
            <FormField label="CNAE Principal"><Input {...register('cnae_code')} /></FormField>
            <FormField label="Descrição CNAE"><Input {...register('cnae_description')} /></FormField>
            <FormField label="Natureza Jurídica"><Input {...register('legal_nature')} /></FormField>
            <FormField label="Capital Social (R$)"><Input {...register('capital')} /></FormField>
            <FormField label="Situação Cadastral"><Input {...register('company_status')} /></FormField>
            <FormField label="Data de Abertura"><Input {...register('opened_at')} type="date" /></FormField>
            <div className="flex items-center gap-4">
              <Controller control={control} name="simples_nacional" render={({ field }) => (
                <label className="flex items-center gap-2 text-sm text-surface-700"><input type="checkbox" checked={field.value === true} onChange={e => field.onChange(e.target.checked)} className="rounded border-default" /> Simples Nacional</label>
              )} />
              <Controller control={control} name="mei" render={({ field }) => (
                <label className="flex items-center gap-2 text-sm text-surface-700"><input type="checkbox" checked={field.value === true} onChange={e => field.onChange(e.target.checked)} className="rounded border-default" /> MEI</label>
              )} />
            </div>
            {watch('partners').length > 0 && (
              <div className="sm:col-span-2">
                <label className="block text-sm font-medium text-surface-700 mb-2">Quadro Societário</label>
                <div className="rounded-lg border border-default overflow-hidden">
                  <table className="w-full text-xs"><thead><tr className="bg-surface-50"><th className="text-left px-3 py-2 font-medium text-surface-500">Nome</th><th className="text-left px-3 py-2 font-medium text-surface-500">Qualificação</th></tr></thead><tbody className="divide-y divide-subtle">
                    {watch('partners').map((p: { name: string | null; role: string | null }, i: number) => <tr key={i} className="hover:bg-surface-50"><td className="px-3 py-1.5 font-medium text-surface-800">{p.name}</td><td className="px-3 py-1.5 text-surface-600">{p.role}</td></tr>)}
                  </tbody></table>
                </div>
              </div>
            )}
          </div>
        )}

        {activeTab === 'address' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="CEP">
              <Controller control={control} name="address_zip" render={({ field }) => (
                <Input {...field} value={field.value} onChange={e => field.onChange(e.target.value.replace(/\D/g, '').slice(0, 8))} onBlur={() => lookupCep(getValues('address_zip'))} maxLength={8} placeholder="00000000" />
              )} />
            </FormField>
            <div />
            <div className="sm:col-span-2"><FormField label="Rua"><Input {...register('address_street')} /></FormField></div>
            <FormField label="Número"><Input {...register('address_number')} /></FormField>
            <FormField label="Complemento"><Input {...register('address_complement')} /></FormField>
            <FormField label="Bairro"><Input {...register('address_neighborhood')} /></FormField>
            <FormField label="Cidade"><Input {...register('address_city')} /></FormField>
            <Controller control={control} name="address_state" render={({ field }) => <FormField label="UF"><Input {...field} onChange={e => field.onChange(e.target.value.toUpperCase().slice(0, 2))} maxLength={2} /></FormField>} />
            <div className="sm:col-span-2 border-t border-default mt-2 pt-4">
              <h4 className="text-sm font-semibold text-surface-900 mb-3 flex items-center gap-2"><MapPin className="w-4 h-4 text-brand-600" /> Localização (Google Maps)</h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="sm:col-span-2 flex gap-2 items-end">
                  <div className="flex-1"><FormField label="Link do Google Maps"><Input {...register('google_maps_link')} placeholder="https://maps.google.com/..." /></FormField></div>
                  <Button variant="secondary" onClick={() => parseGoogleMapsLink(getValues('google_maps_link'))} type="button" className="mb-0.5">Extrair</Button>
                </div>
                <FormField label="Latitude"><Input {...register('latitude')} placeholder="-00.000000" /></FormField>
                <FormField label="Longitude"><Input {...register('longitude')} placeholder="-00.000000" /></FormField>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'crm' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="Origem">
              <select {...register('source')} aria-label="Origem" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0"><option value="">Selecione...</option>{crmOptions?.sources && Object.entries(crmOptions.sources).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}</select>
            </FormField>
            <FormField label="Segmento">
              <select {...register('segment')} aria-label="Segmento" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0"><option value="">Selecione...</option>{crmOptions?.segments && Object.entries(crmOptions.segments).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}</select>
            </FormField>
            <FormField label="Porte">
              <select {...register('company_size')} aria-label="Porte" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0"><option value="">Selecione...</option>{crmOptions?.company_sizes && Object.entries(crmOptions.company_sizes).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}</select>
            </FormField>
            <FormField label="Classificação">
              <select {...register('rating')} aria-label="Classificação" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0"><option value="">Selecione...</option>{crmOptions?.ratings && Object.entries(crmOptions.ratings).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}</select>
            </FormField>
            <div className="sm:col-span-2">
              <FormField label="Vendedor Responsável">
                <select {...register('assigned_seller_id')} aria-label="Vendedor responsável" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0"><option value="">Nenhum</option>{(sellers || []).map((s: { id: number; name: string }) => <option key={s.id} value={s.id}>{s.name}</option>)}</select>
              </FormField>
            </div>
            <div className="sm:col-span-2 border-t border-default mt-2 pt-4">
              <h4 className="text-sm font-semibold text-surface-900 mb-3">Contrato e Faturamento</h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="Tipo de Contrato">
                  <select {...register('contract_type')} aria-label="Tipo de contrato" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0"><option value="">Selecione...</option>{crmOptions?.contract_types && Object.entries(crmOptions.contract_types).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}</select>
                </FormField>
                <FormField label="Receita Anual Estimada (R$)"><Input {...register('annual_revenue_estimate')} /></FormField>
                <FormField label="Início do Contrato"><Input {...register('contract_start')} type="date" /></FormField>
                <FormField label="Fim do Contrato"><Input {...register('contract_end')} type="date" /></FormField>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'contacts' && (
          <div className="space-y-4">
            {contactFields.length === 0 && <p className="text-sm text-surface-500 text-center py-4">Nenhum contato adicionado</p>}
            {contactFields.map((field, i) => (
              <div key={field.id} className="border border-surface-200 rounded-lg p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <p className="text-sm font-medium text-surface-700">Contato {i + 1}</p>
                  <div className="flex items-center gap-2">
                    <Controller control={control} name={`contacts.${i}.is_primary`} render={({ field: f }) => (
                      <label className="flex items-center gap-1.5 text-xs text-surface-600">
                        <input type="checkbox" checked={f.value} onChange={e => { f.onChange(e.target.checked); if (e.target.checked) contactFields.forEach((_, idx) => { if (idx !== i) setValue(`contacts.${idx}.is_primary`, false) }) }} className="rounded border-default" />
                        Principal
                      </label>
                    )} />
                    <IconButton icon={<Trash2 className="h-3.5 w-3.5" />} aria-label="Remover contato" tooltip="Remover" size="sm" variant="ghost" className="text-red-500 hover:text-red-700" onClick={() => removeContactAt(i)} />
                  </div>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <FormField label="Nome *"><Input {...register(`contacts.${i}.name`)} /></FormField>
                  <FormField label="Cargo"><Input {...register(`contacts.${i}.role`)} /></FormField>
                  <Controller control={control} name={`contacts.${i}.phone`} render={({ field: f }) => <FormField label="Telefone"><Input {...f} onChange={e => f.onChange(maskPhone(e.target.value))} maxLength={15} /></FormField>} />
                  <FormField label="E-mail"><Input {...register(`contacts.${i}.email`)} /></FormField>
                </div>
              </div>
            ))}
            <Button variant="outline" size="sm" onClick={addContact} icon={<Plus className="h-4 w-4" />}>Adicionar Contato</Button>
          </div>
        )}

        <div className="flex justify-end gap-2 mt-6 pt-4 border-t border-default">
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          <Button onClick={handleSubmit((data: CustomerFormData) => saveMut.mutate(data))} disabled={saveMut.isPending}>
            {saveMut.isPending ? 'Salvando...' : 'Salvar'}
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  )
}
