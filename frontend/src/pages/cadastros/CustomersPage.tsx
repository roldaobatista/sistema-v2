import { useState } from 'react'
import { useForm, Controller, useFieldArray } from 'react-hook-form'
import type { Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import type { AxiosError } from 'axios'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, Trash2, Users, Search, X, Heart, Building2, User, ChevronLeft, ChevronRight, UploadCloud, FileText, MapPin, Loader2, CheckCircle2, Zap, Sprout, Briefcase, DollarSign, AlertTriangle, Sparkles, Merge } from 'lucide-react'
import api, { getApiErrorMessage } from '@/lib/api'
import { customerApi } from '@/lib/customer-api'
import { useDebounce } from '@/hooks/useDebounce'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/ui/iconbutton'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/ui/modal'
import { FormField } from '@/components/ui/form-field'
import { PageHeader } from '@/components/ui/pageheader'
import { EmptyState } from '@/components/ui/emptystate'
import { Skeleton } from '@/components/ui/skeleton'
import { toast } from 'sonner'
import { useAuvoExport } from '@/hooks/useAuvoExport'
import { queryKeys } from '@/lib/query-keys'
import { handleFormError } from '@/lib/form-utils'
import { customerSchema, type CustomerFormData } from '@/schemas/customer'
import { maskPhone as maskPhoneUtil } from '@/lib/form-masks'
import type { CustomerContact, CustomerPartner, CustomerWithContacts, DeleteDependencies } from '@/types/customer'

// â”€â”€â”€ Masks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function maskCpfCnpj(value: string): string {
  const digits = value.replace(/\D/g, '')
  if (digits.length <= 11) {
    return digits
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
  }
  return digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2')
}

function maskPhone(value: string): string {
  return maskPhoneUtil(value)
}

// â”€â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const defaultValues: CustomerFormData = {
  type: 'PJ', name: '', trade_name: '', document: '', email: '', phone: '', phone2: '', notes: '', is_active: true,
  address_zip: '', address_street: '', address_number: '', address_complement: '',
  address_neighborhood: '', address_city: '', address_state: '',
  latitude: '', longitude: '', google_maps_link: '',
  state_registration: '', municipal_registration: '',
  cnae_code: '', cnae_description: '', legal_nature: '', capital: '',
  simples_nacional: null, mei: null, company_status: '', opened_at: '',
  is_rural_producer: false, partners: [], secondary_activities: [],
  source: '', segment: '', company_size: '', rating: '', assigned_seller_id: '',
  annual_revenue_estimate: '', contract_type: '', contract_start: '', contract_end: '',
  contacts: [],
}

function toDateInput(val: string | null | undefined): string {
  if (!val) return ''
  return val.substring(0, 10)
}

const RATING_COLORS: Record<string, string> = {
  A: 'bg-emerald-100 text-emerald-700',
  B: 'bg-blue-100 text-blue-700',
  C: 'bg-amber-100 text-amber-700',
  D: 'bg-red-100 text-red-700',
}

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
  partners?: CustomerPartner[]
  secondary_activities?: { code: string; description: string | null }[]
  company_size?: string | null
}

interface ApiError {
  response?: {
    status?: number
    data?: {
      message?: string
      dependencies?: DeleteDependencies
      errors?: Record<string, string[]>
    }
  }
}

// â”€â”€â”€ Component â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
export function CustomersPage() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('cadastros.customer.create')
  const canUpdate = hasPermission('cadastros.customer.update')
  const canDelete = hasPermission('cadastros.customer.delete')
  const canExportAuvo = hasPermission('auvo.export.execute')

  const { exportCustomer } = useAuvoExport()

  // Filters
  const [search, setSearch] = useState('')
  const [typeFilter, setTypeFilter] = useState<'' | 'PF' | 'PJ'>('')
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [segmentFilter, setSegmentFilter] = useState<string>('')
  const [ratingFilter, setRatingFilter] = useState<string>('')
  const [sourceFilter, setSourceFilter] = useState<string>('')
  const [sellerFilter, setSellerFilter] = useState<string>('')
  const [page, setPage] = useState(1)
  const perPage = 20
  const debouncedSearch = useDebounce(search, 300)

  // Modal
  const [open, setOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [activeTab, setActiveTab] = useState<'info' | 'empresa' | 'address' | 'crm' | 'contacts'>('info')

  const { register, control, handleSubmit, reset, setValue, getValues, watch, setError, formState: { errors } } = useForm<CustomerFormData>({
    resolver: zodResolver(customerSchema) as Resolver<CustomerFormData>,
    defaultValues,
  })
  const { fields: contactFields, append: appendContact, remove: removeContactAt } = useFieldArray({ control, name: 'contacts' })

  // Delete
  const [delId, setDelId] = useState<number | null>(null)
  const [delDeps, setDelDeps] = useState<DeleteDependencies | null>(null)
  const [_delMessage, setDelMessage] = useState<string | null>(null)

  // Fetch CRM options
  const { data: crmOptions } = useQuery({
    queryKey: queryKeys.customers.options,
    queryFn: () => customerApi.options(),
    staleTime: 5 * 60 * 1000,
  })

  // Sellers
  const { data: sellersRes } = useQuery({
    queryKey: queryKeys.customers.sellersOptions,
    queryFn: () => api.get('/users', { params: { role: 'vendedor', per_page: 100 } }).then(r => r.data),
    staleTime: 5 * 60 * 1000,
  })
  const sellers = (sellersRes?.data?.data ?? sellersRes?.data ?? []) as SellerOption[]

  // Fetch customers
  const { data: res, isLoading } = useQuery({
    queryKey: queryKeys.customers.list({ search: debouncedSearch, type: typeFilter, status: statusFilter, segment: segmentFilter, rating: ratingFilter, source: sourceFilter, seller: sellerFilter, page }),
    queryFn: () => customerApi.list({
      search: debouncedSearch || undefined,
      type: typeFilter || undefined,
      is_active: statusFilter === '' ? undefined : statusFilter === '1',
      segment: segmentFilter || undefined,
      rating: ratingFilter || undefined,
      source: sourceFilter || undefined,
      assigned_seller_id: sellerFilter || undefined,
      page,
      per_page: perPage,
    }),
  })

  const customers = res?.data?.data ?? []
  const totalCount = res?.data?.meta?.total ?? res?.data?.total ?? 0
  const lastPage = res?.data?.meta?.last_page ?? res?.data?.last_page ?? 1

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

  // Document lookup state
  const [lookupLoading, setLookupLoading] = useState(false)
  const [enrichmentData, setEnrichmentData] = useState<ExternalLookupResponse | null>(null)

  const fmtBRL = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

  const lookupDocument = async (doc: string) => {
    const digits = doc.replace(/\D/g, '')
    if (digits.length !== 14 && digits.length !== 11) return
    setLookupLoading(true)
    setEnrichmentData(null)
    try {
      const r = await api.get(`/external/document/${digits}`)
      const d = r.data as ExternalLookupResponse | null
      if (!d) { toast.error('Documento não encontrado'); return }

      if (d.source === 'cpf_validation') {
        toast.success('CPF válido!')
        setEnrichmentData(d)
        return
      }

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
      toast.success(`Dados obtidos via ${d.source === 'brasilapi' ? 'BrasilAPI' : d.source === 'opencnpj' ? 'OpenCNPJ' : 'CNPJ.ws'}!`)
    } catch (err: unknown) {
      const error = err as ApiError
      if (error.response?.status === 404) {
        toast.error('Documento não encontrado na base da Receita Federal')
      } else if (error.response?.status === 422) {
        toast.error(getApiErrorMessage(err, 'Documento invalido'))
      } else {
        toast.error(getApiErrorMessage(err, 'Erro ao consultar documento. Tente novamente.'))
      }
    } finally {
      setLookupLoading(false)
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

  // Google Maps Parser
  const parseGoogleMapsLink = (link: string) => {
    if (!link) return
    // Patterns: @lat,lng or q=lat,lng or just lat,lng
    const regex = /(@|q=|query=|place\/|search\/)(-?\d+\.\d+),\s*(-?\d+\.\d+)/
    const match = link.match(regex)

    if (match) {
      setValue('latitude', match[2])
      setValue('longitude', match[3])
      toast.success('Coordenadas extraídas do link!')
    } else {
      const rawRegex = /^(-?\d+\.\d+),\s*(-?\d+\.\d+)$/
      const rawMatch = link.trim().match(rawRegex)
      if (rawMatch) {
        setValue('latitude', rawMatch[1])
        setValue('longitude', rawMatch[2])
        toast.success('Coordenadas identificadas!')
      } else {
        toast.info('Não foi possível extrair coordenadas deste link automaticamente. Tente preencher manualmente.')
      }
    }
  }

  // Validation Helpers
  const _validateDoc = (doc: string, type: 'PF' | 'PJ') => {
    const digits = doc.replace(/\D/g, '')
    if (type === 'PF') return digits.length === 11
    if (type === 'PJ') return digits.length === 14
    return false
  }

  // Save
  const saveMut = useMutation({
    mutationFn: (data: CustomerFormData) => {
      const sanitized: Record<string, unknown> = { ...data }
      const nullableStrings = [
        'trade_name', 'email', 'phone', 'phone2', 'source', 'segment',
        'company_size', 'rating', 'assigned_seller_id',
        'state_registration', 'municipal_registration', 'cnae_code',
        'cnae_description', 'legal_nature', 'capital', 'company_status', 'opened_at',
        'annual_revenue_estimate', 'contract_type', 'contract_start', 'contract_end',
        'google_maps_link',
      ]
      for (const k of nullableStrings) {
        if (sanitized[k] === '') sanitized[k] = null
      }
      if (sanitized.assigned_seller_id) sanitized.assigned_seller_id = Number(sanitized.assigned_seller_id)
      if (sanitized.capital && typeof sanitized.capital === 'string') sanitized.capital = parseFloat(sanitized.capital) || null
      if (sanitized.annual_revenue_estimate && typeof sanitized.annual_revenue_estimate === 'string') {
        sanitized.annual_revenue_estimate = parseFloat(sanitized.annual_revenue_estimate as string) || null
      }

      const latStr = sanitized.latitude as string
      const lngStr = sanitized.longitude as string
      sanitized.latitude = latStr ? parseFloat(latStr) || null : null
      sanitized.longitude = lngStr ? parseFloat(lngStr) || null : null

      if (enrichmentData && enrichmentData.source !== 'cpf_validation') {
        sanitized.enrichment_data = enrichmentData
        sanitized.enriched_at = new Date().toISOString()
      }
      return editingId
        ? customerApi.update(editingId, sanitized)
        : customerApi.create(sanitized)
    },
    onSuccess: () => {
      toast.success(editingId ? 'Cliente atualizado!' : 'Cliente criado!')
      qc.invalidateQueries({ queryKey: queryKeys.customers.all })
      broadcastQueryInvalidation(['customers', 'customers-search'], 'Cliente')
      closeModal()
    },
    onError: (err) => {
      handleFormError(err as AxiosError<{ message: string; errors?: Record<string, string[]> }>, setError, 'Erro ao salvar cliente')
      const data = (err as AxiosError<{ errors?: Record<string, string[]> }>)?.response?.data
      if (data?.errors) {
        const firstField = Object.keys(data.errors)[0]
        if (['address_zip', 'address_street', 'address_number', 'address_complement', 'address_neighborhood', 'address_city', 'address_state', 'latitude', 'longitude', 'google_maps_link'].includes(firstField)) setActiveTab('address')
        else if (['cnae_code', 'cnae_description', 'legal_nature', 'capital', 'company_status', 'opened_at', 'simples_nacional', 'mei'].includes(firstField) || firstField.startsWith('partners') || firstField.startsWith('secondary_activities')) setActiveTab('empresa')
        else if (['source', 'segment', 'company_size', 'rating', 'assigned_seller_id'].includes(firstField)) setActiveTab('crm')
        else if (firstField.startsWith('contacts')) setActiveTab('contacts')
        else setActiveTab('info')
      }
      if ((err as AxiosError<{ status?: number }>)?.response?.status === 403) toast.error('Você não tem permissão')
    },
  })

  // Delete
  const deleteMut = useMutation({
    mutationFn: (id: number) => customerApi.destroy(id),
    onSuccess: () => {
      toast.success('Cliente excluído!')
      qc.invalidateQueries({ queryKey: queryKeys.customers.all })
      broadcastQueryInvalidation(['customers', 'customers-search'], 'Cliente')
      setDelId(null)
      setDelDeps(null)
    },
    onError: (err: unknown) => {
      const error = err as ApiError
      if (error.response?.status === 409) {
        setDelDeps(error.response?.data?.dependencies ?? null)
      } else if (error.response?.status === 403) {
        toast.error('Você não tem permissão')
        setDelId(null)
      } else {
        toast.error(getApiErrorMessage(err, 'Erro ao excluir'))
      }
    },
  })

  function openCreate() {
    setEditingId(null)
    reset(defaultValues)
    setEnrichmentData(null)
    setActiveTab('info')
    setOpen(true)
  }

  function openEdit(c: CustomerWithContacts) {
    setEditingId(c.id)
    setEnrichmentData(null)
    reset({
      type: (c.type ?? 'PJ') as 'PF' | 'PJ',
      name: c.name ?? '',
      trade_name: c.trade_name ?? '',
      document: c.document ?? '',
      email: c.email ?? '',
      phone: c.phone ?? '',
      phone2: c.phone2 ?? '',
      notes: c.notes ?? '',
      is_active: c.is_active ?? true,
      address_zip: c.address_zip ?? '',
      address_street: c.address_street ?? '',
      address_number: c.address_number ?? '',
      address_complement: c.address_complement ?? '',
      address_neighborhood: c.address_neighborhood ?? '',
      address_city: c.address_city ?? '',
      address_state: c.address_state ?? '',
      latitude: c.latitude != null ? String(c.latitude) : '',
      longitude: c.longitude != null ? String(c.longitude) : '',
      google_maps_link: c.google_maps_link ?? '',
      state_registration: c.state_registration ?? '',
      municipal_registration: c.municipal_registration ?? '',
      cnae_code: c.cnae_code ?? '',
      cnae_description: c.cnae_description ?? '',
      legal_nature: c.legal_nature ?? '',
      capital: c.capital != null ? String(c.capital) : '',
      simples_nacional: c.simples_nacional ?? null,
      mei: c.mei ?? null,
      company_status: c.company_status ?? '',
      opened_at: toDateInput(c.opened_at),
      is_rural_producer: c.is_rural_producer ?? false,
      partners: c.partners ?? [],
      secondary_activities: c.secondary_activities ?? [],
      source: c.source ?? '',
      segment: c.segment ?? '',
      company_size: c.company_size ?? '',
      rating: c.rating ?? '',
      assigned_seller_id: c.assigned_seller_id?.toString() ?? '',
      annual_revenue_estimate: c.annual_revenue_estimate != null ? String(c.annual_revenue_estimate) : '',
      contract_type: c.contract_type ?? '',
      contract_start: toDateInput(c.contract_start),
      contract_end: toDateInput(c.contract_end),
      contacts: (c.contacts ?? []).map(ct => ({
        id: ct.id,
        name: ct.name ?? '',
        role: ct.role ?? '',
        phone: ct.phone ?? '',
        email: ct.email ?? '',
        is_primary: ct.is_primary ?? false,
      })),
    })
    setActiveTab('info')
    setOpen(true)
  }

  function closeModal() {
    setOpen(false)
    setEditingId(null)
    reset(defaultValues)
  }

  function addContact() { appendContact({ name: '', role: '', phone: '', email: '', is_primary: false }) }

  const healthColor = (score: number) => {
    if (score >= 80) return 'text-emerald-600'
    if (score >= 50) return 'text-amber-600'
    return 'text-red-600'
  }

  // Handle pagination reset on filter change
  const handleSearch = (val: string) => { setSearch(val); setPage(1) }
  const handleTypeFilter = (val: '' | 'PF' | 'PJ') => { setTypeFilter(val); setPage(1) }
  const handleStatusFilter = (val: string) => { setStatusFilter(val); setPage(1) }
  const handleSegmentFilter = (val: string) => { setSegmentFilter(val); setPage(1) }
  const handleRatingFilter = (val: string) => { setRatingFilter(val); setPage(1) }
  const handleSourceFilter = (val: string) => { setSourceFilter(val); setPage(1) }
  const handleSellerFilter = (val: string) => { setSellerFilter(val); setPage(1) }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Clientes"
        subtitle="Gerencie seus clientes e informações de contato"
        count={totalCount}
        actions={[
          {
            label: 'Fundir Duplicados',
            onClick: () => navigate('/cadastros/clientes/fusao'),
            icon: <Merge className="h-4 w-4" />,
            variant: 'outline' as const,
            permission: canCreate,
          },
          {
            label: 'Novo Cliente',
            onClick: openCreate,
            icon: <Plus className="h-4 w-4" />,
            permission: canCreate,
          },
        ]}
      />

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[220px]">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
          <input
            type="text"
            placeholder="Buscar por nome, documento, e-mail..."
            value={search}
            onChange={(e) => handleSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-surface-0"
          />
          {search && (
            <button onClick={() => handleSearch('')} aria-label="Limpar busca" className="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600">
              <X className="h-4 w-4" />
            </button>
          )}
        </div>
        <select
          value={typeFilter}
          onChange={(e) => handleTypeFilter(e.target.value as '' | 'PF' | 'PJ')}
          aria-label="Filtrar por tipo"
          className="text-sm border border-default rounded-lg px-3 py-2 bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500"
        >
          <option value="">Todos os tipos</option>
          <option value="PF">Pessoa Física</option>
          <option value="PJ">Pessoa Jurídica</option>
        </select>
        <select
          value={statusFilter}
          onChange={(e) => handleStatusFilter(e.target.value)}
          aria-label="Filtrar por status"
          className="text-sm border border-default rounded-lg px-3 py-2 bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500"
        >
          <option value="">Todos os status</option>
          <option value="1">Ativos</option>
          <option value="0">Inativos</option>
        </select>
        <select
          value={segmentFilter}
          onChange={(e) => handleSegmentFilter(e.target.value)}
          aria-label="Filtrar por segmento"
          className="text-sm border border-default rounded-lg px-3 py-2 bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500"
        >
          <option value="">Todos segmentos</option>
          {crmOptions?.segments && Object.entries(crmOptions.segments).map(([k, v]) => (
            <option key={k} value={k}>{v as string}</option>
          ))}
        </select>
        <select
          value={ratingFilter}
          onChange={(e) => handleRatingFilter(e.target.value)}
          aria-label="Filtrar por rating"
          className="text-sm border border-default rounded-lg px-3 py-2 bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500"
        >
          <option value="">Todos ratings</option>
          {crmOptions?.ratings && Object.entries(crmOptions.ratings).map(([k, v]) => (
            <option key={k} value={k}>{v as string}</option>
          ))}
        </select>
        <select
          value={sourceFilter}
          onChange={(e) => handleSourceFilter(e.target.value)}
          aria-label="Filtrar por origem"
          className="text-sm border border-default rounded-lg px-3 py-2 bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500"
        >
          <option value="">Todas origens</option>
          {crmOptions?.sources && Object.entries(crmOptions.sources).map(([k, v]) => (
            <option key={k} value={k}>{v as string}</option>
          ))}
        </select>
        <select
          value={sellerFilter}
          onChange={(e) => handleSellerFilter(e.target.value)}
          aria-label="Filtrar por vendedor"
          className="text-sm border border-default rounded-lg px-3 py-2 bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500"
        >
          <option value="">Todos vendedores</option>
          {sellers.map((s) => (
            <option key={s.id} value={s.id}>{s.name}</option>
          ))}
        </select>
      </div>

      {isLoading ? (
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-36 rounded-xl" />
          ))}
        </div>
      ) : customers.length === 0 ? (
        <EmptyState
          icon={<Users className="h-8 w-8" />}
          title="Nenhum cliente encontrado"
          description={search || typeFilter || statusFilter || segmentFilter || ratingFilter || sourceFilter || sellerFilter ? 'Tente ajustar os filtros de busca' : 'Comece cadastrando seu primeiro cliente'}
          action={canCreate ? { label: 'Novo Cliente', onClick: openCreate, icon: <Plus className="h-4 w-4" /> } : undefined}
        />
      ) : (
        <>
          <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            {customers.map((c: CustomerWithContacts) => (
              <div
                key={c.id}
                data-testid="customer-card"
                className="group relative rounded-xl border border-default bg-surface-0 p-4 hover:shadow-md hover:border-brand-200 transition-all cursor-pointer"
                onClick={() => navigate(`/cadastros/clientes/${c.id}`)}
              >
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-2.5 min-w-0">
                    <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${c.type === 'PJ' ? 'bg-blue-100 text-blue-600' : 'bg-teal-100 text-teal-600'}`}>
                      {c.type === 'PJ' ? <Building2 className="h-4 w-4" /> : <User className="h-4 w-4" />}
                    </div>
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-surface-900 truncate">{c.name}</p>
                      {c.trade_name && (
                        <p className="text-xs text-surface-500 truncate">{c.trade_name}</p>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-1">
                    {c.rating && (
                      <span className={`text-xs font-bold px-1.5 py-0.5 rounded ${RATING_COLORS[c.rating] || 'bg-surface-100 text-surface-500'}`}>
                        {c.rating}
                      </span>
                    )}
                    <Badge variant={c.is_active ? 'success' : 'default'} size="sm">
                      {c.is_active ? 'Ativo' : 'Inativo'}
                    </Badge>
                  </div>
                </div>
                <div className="mt-3 space-y-1 text-xs text-surface-500">
                  {c.document && <p>{maskCpfCnpj(c.document)}</p>}
                  {c.email && <p>{c.email}</p>}
                  {c.phone && <p>{maskPhone(c.phone)}</p>}
                  {(c.contacts?.length ?? 0) > 0 && (() => {
                    const contacts = c.contacts ?? []
                    const primary = contacts.find((ct: CustomerContact) => ct.is_primary) || contacts[0]
                    return primary ? (
                      <p className="text-surface-400 flex items-center gap-1">
                        <User className="h-3 w-3" />
                        {primary.name}{primary.role ? ` (${primary.role})` : ''}
                      </p>
                    ) : null
                  })()}
                </div>
                <div className="mt-3 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {typeof c.health_score === 'number' && (
                      <span className={`flex items-center gap-1 text-xs font-medium ${healthColor(c.health_score)}`}>
                        <Heart className="h-3 w-3" />
                        {c.health_score}
                      </span>
                    )}
                    {c.assigned_seller && (
                      <span className="text-xs text-surface-400">
                        {c.assigned_seller.name}
                      </span>
                    )}
                    {(c.documents_count ?? 0) > 0 && (
                      <span className="flex items-center gap-1 text-xs font-bold text-surface-400" title={`${c.documents_count} documentos anexados`}>
                        <FileText className="h-3 w-3" />
                        {c.documents_count}
                      </span>
                    )}
                  </div>
                  <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    {canUpdate && (
                      <IconButton
                        icon={<Pencil className="h-3.5 w-3.5" />}
                        aria-label="Editar"
                        tooltip="Editar"
                        size="sm"
                        variant="ghost"
                        onClick={(e) => { e.stopPropagation(); openEdit(c) }}
                      />
                    )}
                    {canExportAuvo && (
                      <IconButton
                        icon={<UploadCloud className="h-3.5 w-3.5" />}
                        aria-label="Exportar para Auvo"
                        tooltip="Exportar para Auvo"
                        size="sm"
                        variant="ghost"
                        className="hover:text-blue-600 hover:bg-blue-50"
                        disabled={exportCustomer.isPending}
                        onClick={(e) => { e.stopPropagation(); exportCustomer.mutate(c.id) }}
                      />
                    )}
                    {canDelete && (
                      <IconButton
                        icon={<Trash2 className="h-3.5 w-3.5" />}
                        aria-label="Excluir"
                        tooltip="Excluir"
                        size="sm"
                        variant="ghost"
                        className="text-red-500 hover:text-red-700 hover:bg-red-50"
                        onClick={(e) => {
                          e.stopPropagation()
                          setDelId(c.id)
                          setDelDeps(null)
                          setDelMessage(null)
                        }}
                      />
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>

          {lastPage > 1 && (
            <div className="flex items-center justify-between pt-2">
              <p className="text-xs text-surface-500">
                Mostrando {((page - 1) * perPage) + 1}—{Math.min(page * perPage, totalCount)} de {totalCount}
              </p>
              <div className="flex items-center gap-1">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page <= 1}
                  onClick={() => setPage(p => p - 1)}
                  icon={<ChevronLeft className="h-4 w-4" />}
                >
                  Anterior
                </Button>
                <span className="text-xs text-surface-600 px-2 tabular-nums">
                  {page} / {lastPage}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page >= lastPage}
                  onClick={() => setPage(p => p + 1)}
                  icon={<ChevronRight className="h-4 w-4" />}
                >
                  Próxima
                </Button>
              </div>
            </div>
          )}
        </>
      )}

      <Modal
        isOpen={open}
        onClose={closeModal}
        title={editingId ? 'Editar Cliente' : 'Novo Cliente'}
        size="lg"
        footer={
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={closeModal}>Cancelar</Button>
            <Button onClick={handleSubmit((data: CustomerFormData) => saveMut.mutate(data))} disabled={saveMut.isPending}>
              {saveMut.isPending ? 'Salvando...' : 'Salvar'}
            </Button>
          </div>
        }
      >
        <div className="flex border-b border-default mb-4 -mx-1 overflow-x-auto">
          {(['info', 'empresa', 'address', 'crm', 'contacts'] as const).map(tab => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`px-4 py-2 text-sm font-medium whitespace-nowrap ${activeTab === tab ? 'border-b-2 border-brand-600 text-brand-600' : 'text-surface-500 hover:text-surface-700'}`}
            >
              {{ info: 'Informações', empresa: 'Dados Empresa', address: 'Endereço', crm: 'CRM', contacts: 'Contatos' }[tab]}
            </button>
          ))}
        </div>

        {activeTab === 'info' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Controller control={control} name="type" render={({ field }) => (
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
            )} />
            <div className="sm:col-span-2">
              <FormField label={watch('type') === 'PJ' ? 'CNPJ — Consulta Automática' : 'CPF — Consulta Automática'}>
                <div className="flex gap-2 items-start">
                  <Controller control={control} name="document" render={({ field }) => (
                    <input value={field.value} onChange={(e) => { const masked = maskCpfCnpj(e.target.value); field.onChange(masked); if (masked.replace(/\D/g, '').length === 14) setValue('type', 'PJ'); else if (masked.replace(/\D/g, '').length === 11) setValue('type', 'PF') }} maxLength={watch('type') === 'PJ' ? 18 : 14} placeholder={watch('type') === 'PJ' ? '00.000.000/0000-00' : '000.000.000-00'} className="w-full px-3 py-2.5 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0 font-mono tracking-wide" />
                  )} />
                  <button type="button" onClick={() => lookupDocument(getValues('document'))} disabled={lookupLoading || getValues('document').replace(/\D/g, '').length < 11} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-gradient-to-r from-brand-600 to-emerald-600 hover:from-brand-500 hover:to-emerald-500 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm">
                    {lookupLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : enrichmentData ? <CheckCircle2 className="h-4 w-4" /> : <Zap className="h-4 w-4" />}
                    {lookupLoading ? 'Consultando...' : enrichmentData ? 'Consultado' : 'Consultar'}
                  </button>
                </div>
              </FormField>
              <p className="text-xs text-surface-400 mt-1.5">
                {watch('type') === 'PJ' ? 'Preencha o CNPJ e clique em Consultar para importar automaticamente todos os dados da Receita Federal (razão social, endereço, sócios, CNAE, Simples Nacional, etc.)' : 'Preencha o CPF para validação automática. Para produtores rurais, marque a flag abaixo.'}
              </p>
            </div>
            {enrichmentData && (enrichmentData as { source?: string }).source !== 'cpf_validation' && (
              <div className="sm:col-span-2 rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50/50 to-emerald-50/30 p-4 animate-fade-in">
                <div className="flex items-center gap-2 mb-3">
                  <Sparkles className="h-4 w-4 text-brand-600" />
                  <h4 className="text-sm font-bold text-brand-800">Dados Obtidos da Receita Federal</h4>
                  <Badge variant="info" size="sm">{(enrichmentData as { source?: string }).source}</Badge>
                  {(enrichmentData as { company_status?: string }).company_status && (
                    <Badge variant={((enrichmentData as { company_status: string }).company_status).toUpperCase().includes('ATIVA') ? 'success' : 'danger'} size="sm">
                      {(enrichmentData as { company_status: string }).company_status}
                    </Badge>
                  )}
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                  {(enrichmentData as { capital?: number }).capital != null && (
                    <div className="rounded-lg bg-surface-0/60 dark:bg-surface-800/60 p-2.5 border border-brand-100 dark:border-brand-900">
                      <div className="text-surface-400 flex items-center gap-1"><DollarSign className="h-3 w-3" /> Capital Social</div>
                      <div className="font-bold text-surface-900 mt-0.5">{fmtBRL((enrichmentData as { capital: number }).capital)}</div>
                    </div>
                  )}
                  {(enrichmentData as { cnae_code?: string }).cnae_code && (
                    <div className="rounded-lg bg-surface-0/60 dark:bg-surface-800/60 p-2.5 border border-brand-100 dark:border-brand-900">
                      <div className="text-surface-400 flex items-center gap-1"><Briefcase className="h-3 w-3" /> CNAE Principal</div>
                      <div className="font-bold text-surface-900 mt-0.5">{(enrichmentData as { cnae_code: string }).cnae_code}</div>
                      <div className="text-surface-500 truncate">{(enrichmentData as { cnae_description?: string }).cnae_description}</div>
                    </div>
                  )}
                  {(enrichmentData as { simples_nacional?: boolean }).simples_nacional != null && (
                    <div className="rounded-lg bg-surface-0/60 dark:bg-surface-800/60 p-2.5 border border-brand-100 dark:border-brand-900">
                      <div className="text-surface-400">Simples Nacional</div>
                      <div className={`font-bold mt-0.5 ${(enrichmentData as { simples_nacional: boolean }).simples_nacional ? 'text-emerald-600' : 'text-surface-500'}`}>
                        {(enrichmentData as { simples_nacional: boolean }).simples_nacional ? 'Optante' : 'Não optante'}
                      </div>
                    </div>
                  )}
                  {(enrichmentData as { opened_at?: string }).opened_at && (
                    <div className="rounded-lg bg-surface-0/60 dark:bg-surface-800/60 p-2.5 border border-brand-100 dark:border-brand-900">
                      <div className="text-surface-400">Abertura</div>
                      <div className="font-bold text-surface-900 mt-0.5">{(enrichmentData as { opened_at: string }).opened_at}</div>
                    </div>
                  )}
                </div>
                {((enrichmentData as { partners?: CustomerPartner[] }).partners ?? []).length > 0 && (
                  <div className="mt-3">
                    <p className="text-xs font-semibold text-brand-700 mb-1.5 flex items-center gap-1">
                      <Users className="h-3 w-3" /> Quadro Societário ({((enrichmentData as { partners?: CustomerPartner[] }).partners ?? []).length})
                    </p>
                    <div className="grid gap-1.5">
                      {((enrichmentData as { partners?: CustomerPartner[] }).partners ?? []).slice(0, 5).map((p, i) => (
                        <div key={i} className="flex items-center justify-between rounded-lg bg-surface-0/60 dark:bg-surface-800/60 px-3 py-1.5 border border-brand-100 dark:border-brand-900 text-xs">
                          <span className="font-medium text-surface-800">{p.name}</span>
                          <span className="text-surface-400">{p.role}</span>
                        </div>
                      ))}
                      {((enrichmentData as { partners?: CustomerPartner[] }).partners ?? []).length > 5 && (
                        <p className="text-xs text-surface-400 text-center">+ {((enrichmentData as { partners?: CustomerPartner[] }).partners ?? []).length - 5} sócio(s)</p>
                      )}
                    </div>
                  </div>
                )}
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
            <Controller control={control} name="phone2" render={({ field }) => <FormField label="Telefone 2"><Input {...field} onChange={e => field.onChange(maskPhone(e.target.value))} maxLength={15} placeholder="(00) 00000-0000" /></FormField>} />
            <div className="sm:col-span-2 border-t border-default mt-2 pt-4">
              <h4 className="text-sm font-semibold text-surface-900 mb-3 flex items-center gap-2"><Sprout className="h-4 w-4 text-emerald-600" /> Produtor Rural / Registros</h4>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <Controller control={control} name="is_rural_producer" render={({ field }) => (
                  <div className="flex items-center gap-2"><input type="checkbox" id="is_rural_producer" checked={field.value} onChange={e => field.onChange(e.target.checked)} className="rounded border-default" /><label htmlFor="is_rural_producer" className="text-sm text-surface-700">Produtor Rural</label></div>
                )} />
                <FormField label="Inscrição Estadual (IE)"><Input {...register('state_registration')} placeholder="Ex: 123.456.789.012" /></FormField>
                <FormField label="Inscrição Municipal (IM)"><Input {...register('municipal_registration')} placeholder="Ex: 12345678" /></FormField>
              </div>
            </div>
            <FormField label="Observações" error={errors.notes?.message}>
              <textarea id="customer_notes" {...register('notes')} className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none" rows={3} />
            </FormField>
            <div className="sm:col-span-2 flex items-center gap-2">
              <Controller control={control} name="is_active" render={({ field }) => (
                <> <input type="checkbox" id="is_active" checked={field.value} onChange={e => field.onChange(e.target.checked)} className="rounded border-default" /> <label htmlFor="is_active" className="text-sm text-surface-700">Cliente ativo</label> </>
              )} />
            </div>
          </div>
        )}

        {activeTab === 'empresa' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="sm:col-span-2 flex items-center gap-3 rounded-lg border border-surface-200 bg-surface-50 p-3">
              <AlertTriangle className="h-4 w-4 text-amber-500 shrink-0" />
              <p className="text-xs text-surface-600">
                Estes campos são preenchidos automaticamente ao consultar o CNPJ. Você pode editá-los manualmente se necessário.
              </p>
            </div>
            <FormField label="CNAE Principal"><Input {...register('cnae_code')} placeholder="Ex: 4321500" /></FormField>
            <FormField label="Descrição CNAE"><Input {...register('cnae_description')} /></FormField>
            <FormField label="Natureza Jurídica"><Input {...register('legal_nature')} /></FormField>
            <FormField label="Capital Social (R$)"><Input {...register('capital')} placeholder="Ex: 100000.00" /></FormField>
            <FormField label="Situação Cadastral"><Input {...register('company_status')} placeholder="Ex: ATIVA" /></FormField>
            <FormField label="Data de Abertura"><Input {...register('opened_at')} type="date" /></FormField>
            <div className="flex items-center gap-4">
              <Controller control={control} name="simples_nacional" render={({ field }) => (
                <label className="flex items-center gap-2 text-sm text-surface-700"><input type="checkbox" checked={field.value === true} onChange={e => field.onChange(e.target.checked)} className="rounded border-default" /> Simples Nacional</label>
              )} />
              <Controller control={control} name="mei" render={({ field }) => (
                <label className="flex items-center gap-2 text-sm text-surface-700"><input type="checkbox" checked={field.value === true} onChange={e => field.onChange(e.target.checked)} className="rounded border-default" /> MEI</label>
              )} />
            </div>
            {watch('secondary_activities').length > 0 && (
              <div className="sm:col-span-2">
                <label className="block text-sm font-medium text-surface-700 mb-2">Atividades Secundárias</label>
                <div className="rounded-lg border border-default overflow-hidden">
                  <table className="w-full text-xs">
                    <thead><tr className="bg-surface-50"><th className="text-left px-3 py-2 font-medium text-surface-500">Código</th><th className="text-left px-3 py-2 font-medium text-surface-500">Descrição</th></tr></thead>
                    <tbody className="divide-y divide-subtle">
                      {(watch('secondary_activities') || []).map((act: { code: string; description: string | null }, i: number) => (
                        <tr key={i} className="hover:bg-surface-50"><td className="px-3 py-1.5 font-mono text-surface-700">{act.code}</td><td className="px-3 py-1.5 text-surface-600">{act.description}</td></tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
            {watch('partners').length > 0 && (
              <div className="sm:col-span-2">
                <label className="block text-sm font-medium text-surface-700 mb-2">Quadro Societário</label>
                <div className="rounded-lg border border-default overflow-hidden">
                  <table className="w-full text-xs">
                    <thead><tr className="bg-surface-50"><th className="text-left px-3 py-2 font-medium text-surface-500">Nome</th><th className="text-left px-3 py-2 font-medium text-surface-500">Qualificação</th><th className="text-left px-3 py-2 font-medium text-surface-500">Entrada</th></tr></thead>
                    <tbody className="divide-y divide-subtle">
                      {(watch('partners') || []).map((p: CustomerPartner, i: number) => (
                        <tr key={i} className="hover:bg-surface-50"><td className="px-3 py-1.5 font-medium text-surface-800">{p.name}</td><td className="px-3 py-1.5 text-surface-600">{p.role}</td><td className="px-3 py-1.5 text-surface-500">{p.entry_date || '—'}</td></tr>
                      ))}
                    </tbody>
                  </table>
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
                <div className="sm:col-span-2">
                  <div className="flex gap-2 items-end">
                    <div className="flex-1"><FormField label="Link do Google Maps"><Input {...register('google_maps_link')} placeholder="Ex: https://maps.google.com/?q=-23.5,-46.6" /></FormField></div>
                    <Button variant="secondary" onClick={() => parseGoogleMapsLink(getValues('google_maps_link'))} type="button" className="mb-0.5">Extrair Coordenadas</Button>
                  </div>
                  <p className="text-xs text-surface-500 mt-1">Cole o link do Google Maps para tentar preencher Latitude e Longitude automaticamente.</p>
                </div>
                <FormField label="Latitude"><Input {...register('latitude')} placeholder="-00.000000" /></FormField>
                <FormField label="Longitude"><Input {...register('longitude')} placeholder="-00.000000" /></FormField>
                {watch('latitude') && watch('longitude') && (
                  <div className="sm:col-span-2">
                    <a href={`https://www.google.com/maps/search/?api=1&query=${watch('latitude')},${watch('longitude')}`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 text-sm text-brand-600 hover:text-brand-700 font-medium">
                      <MapPin className="w-4 h-4" /> Visualizar localização no Google Maps <UploadCloud className="w-3 h-3 rotate-45" />
                    </a>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {activeTab === 'crm' && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="Origem">
              <select {...register('source')} id="crm-source" aria-label="Origem" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0">
                <option value="">Selecione...</option>
                {crmOptions?.sources && Object.entries(crmOptions.sources).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}
              </select>
            </FormField>
            <FormField label="Segmento">
              <select {...register('segment')} id="crm-segment" aria-label="Segmento" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0">
                <option value="">Selecione...</option>
                {crmOptions?.segments && Object.entries(crmOptions.segments).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}
              </select>
            </FormField>
            <FormField label="Porte">
              <select {...register('company_size')} id="crm-company-size" aria-label="Porte" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0">
                <option value="">Selecione...</option>
                {crmOptions?.company_sizes && Object.entries(crmOptions.company_sizes).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}
              </select>
            </FormField>
            <FormField label="Classificação">
              <select {...register('rating')} id="crm-rating" aria-label="Classificação" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0">
                <option value="">Selecione...</option>
                {crmOptions?.ratings && Object.entries(crmOptions.ratings).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}
              </select>
            </FormField>
            <div className="sm:col-span-2">
              <FormField label="Vendedor Responsável">
                <select {...register('assigned_seller_id')} id="crm-seller" aria-label="Vendedor responsável" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0">
                  <option value="">Nenhum</option>
                  {(sellers || []).map((s: { id: number; name: string }) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
              </FormField>
            </div>
            <div className="sm:col-span-2 border-t border-default mt-2 pt-4">
              <h4 className="text-sm font-semibold text-surface-900 mb-3">Contrato e Faturamento</h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="Tipo de Contrato">
                  <select {...register('contract_type')} id="crm-contract-type" aria-label="Tipo de contrato" className="w-full px-3 py-2 text-sm border border-default rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 bg-surface-0">
                    <option value="">Selecione...</option>
                    {crmOptions?.contract_types && Object.entries(crmOptions.contract_types).map(([k, v]) => <option key={k} value={k}>{v as string}</option>)}
                  </select>
                </FormField>
                <FormField label="Receita Anual Estimada (R$)"><Input {...register('annual_revenue_estimate')} placeholder="Ex: 50000.00" /></FormField>
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
      </Modal>

      <Modal
        isOpen={delId !== null}
        onClose={() => { setDelId(null); setDelDeps(null); setDelMessage(null) }}
        title="Excluir Cliente"
        size="sm"
        footer={
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => { setDelId(null); setDelDeps(null); setDelMessage(null) }}>Cancelar</Button>
            {!delDeps && (
              <Button
                variant="danger"
                onClick={() => {
                  if (delId) {
                    deleteMut.mutate(delId)
                  }
                }}
                disabled={deleteMut.isPending}
              >
                {deleteMut.isPending ? 'Excluindo...' : 'Excluir'}
              </Button>
            )}
          </div>
        }
      >
        {delDeps ? (
          <div className="space-y-2">
            <p className="text-sm text-surface-700">Não é possível excluir este cliente. Existem dependências:</p>
            <ul className="text-sm text-surface-600 list-disc pl-5 space-y-1">
              {delDeps.active_work_orders && <li>Ordens de serviço ativas</li>}
              {delDeps.receivables && <li>Pendências financeiras</li>}
              {(delDeps.quotes ?? 0) > 0 && <li>{delDeps.quotes} orçamento(s)</li>}
              {(delDeps.deals ?? 0) > 0 && <li>{delDeps.deals} negociação(ões)</li>}
            </ul>
          </div>
        ) : (
          <p className="text-sm text-surface-700">Tem certeza que deseja excluir este cliente? Esta ação não pode ser desfeita.</p>
        )}
      </Modal>
    </div>
  )
}
