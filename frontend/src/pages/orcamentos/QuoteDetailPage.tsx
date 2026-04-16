import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '@/stores/auth-store'
import { toast } from 'sonner'
import { getApiErrorMessage, getApiOrigin } from '@/lib/api'
import { customerApi } from '@/lib/customer-api'
import { quoteApi } from '@/lib/quote-api'
import { queryKeys } from '@/lib/query-keys'
import { broadcastQueryInvalidation } from '@/lib/cross-tab-sync'
import { crmFeaturesApi } from '@/lib/crm-features-api'
import { QUOTE_STATUS } from '@/lib/constants'
import { QUOTE_STATUS_CONFIG, isMutableQuoteStatus } from '@/features/quotes/constants'
import { getQuotePaymentSummary } from '@/features/quotes/payment-summary'
import type { CustomerWithContacts } from '@/types/customer'
import type { Quote, QuoteTimelineEntry, QuoteInstallment } from '@/types/quote'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
    ArrowLeft, Pencil, Send, CheckCircle, XCircle, Copy,
    ArrowRightLeft, FileDown, Eye, Trash2, RefreshCw, Link as LinkIcon, History, Phone, FileText,
    MessageCircle, Mail, Tag, DollarSign, FlaskConical, Wrench, MessageSquare
} from 'lucide-react'
import { formatCurrency } from '@/lib/utils'

function normalizeRejectReason(value: string): string | undefined {
    const normalized = value.trim()
    return normalized === '' ? undefined : normalized
}

function showApiError(error: unknown, fallback: string): void {
    toast.error(getApiErrorMessage(error, fallback))
}

function formatDateTime(value?: string | null): string {
    return value ? new Date(value).toLocaleString('pt-BR') : '—'
}

function formatApprovalChannel(value?: string | null): string {
    switch (value) {
        case 'portal':
            return 'Portal do cliente'
        case 'magic_link':
            return 'Link publico'
        case 'public_token':
            return 'Token publico'
        case 'internal':
            return 'Interno'
        default:
            return value ? value.replace(/[_-]+/g, ' ') : '—'
    }
}

export function QuoteDetailPage() {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const qc = useQueryClient()
    const { hasPermission } = useAuthStore()

    const [rejectOpen, setRejectOpen] = useState(false)
    const [rejectReason, setRejectReason] = useState('')
    const [deleteOpen, setDeleteOpen] = useState(false)
    const [proposalOpen, setProposalOpen] = useState(false)
    const [proposalExpires, setProposalExpires] = useState('')
    const [sendModalOpen, setSendModalOpen] = useState(false)
    const [sendStep, setSendStep] = useState<'channel' | 'contact'>('channel')
    const [sendChannel, setSendChannel] = useState<'whatsapp' | 'email' | 'none'>('none')
    const [emailOpen, setEmailOpen] = useState(false)
    const [emailTo, setEmailTo] = useState('')
    const [emailName, setEmailName] = useState('')
    const [emailBody, setEmailBody] = useState('')
    const [contactPickerOpen, setContactPickerOpen] = useState(false)
    const [contactPickerFor, setContactPickerFor] = useState<'whatsapp' | 'email'>('whatsapp')
    const [convertModalOpen, setConvertModalOpen] = useState(false)
    const [convertTarget, setConvertTarget] = useState<'os' | 'chamado'>('os')
    const [convertInstallationTesting, setConvertInstallationTesting] = useState(false)
    const [revertModalOpen, setRevertModalOpen] = useState(false)

    // Approval Modal State
    const [approveModalOpen, setApproveModalOpen] = useState(false)
    const [approvalChannel, setApprovalChannel] = useState<'whatsapp'|'email'|'phone'|'in_person'|'portal'|'integration'|'other' | ''>('')
    const [approvalNotes, setApprovalNotes] = useState('')
    const [termsAccepted, setTermsAccepted] = useState(false)

    const canUpdate = hasPermission('quotes.quote.update')
    const canDelete = hasPermission('quotes.quote.delete')
    const canSend = hasPermission('quotes.quote.send')
    const canApprove = hasPermission('quotes.quote.approve')
    const canInternalApprove = hasPermission('quotes.quote.internal_approve')
    const canCreate = hasPermission('quotes.quote.create')
    const canConvert = hasPermission('quotes.quote.convert')
    const canProposalView = hasPermission('crm.proposal.view')
    const canProposalManage = hasPermission('crm.proposal.manage')

    const {
        data: quote,
        isLoading,
        isError,
        error,
        refetch,
    } = useQuery<Quote>({
        queryKey: queryKeys.quotes.detail(Number(id!)),
        queryFn: () => quoteApi.detail(Number(id!)),
        enabled: !!id,
    })

    const { data: timelineData } = useQuery<QuoteTimelineEntry[]>({
        queryKey: [...queryKeys.quotes.detail(Number(id!)), 'timeline'],
        queryFn: () => quoteApi.timeline(Number(id!)),
        enabled: !!id,
    })
    const timeline = Array.isArray(timelineData) ? timelineData : []

    const { data: proposalList = [] } = useQuery({
        queryKey: ['crm-proposals-by-quote', id],
        queryFn: () => crmFeaturesApi.getProposals({ quote_id: Number(id), per_page: 1 }),
        enabled: !!id && !!canProposalView,
    })
    const hasProposal = proposalList.length > 0

    const createProposalMut = useMutation({
        mutationFn: (data: { quote_id: number; expires_at?: string }) => crmFeaturesApi.createProposal(data),
        onSuccess: () => {
            toast.success('Proposta interativa criada!')
            qc.invalidateQueries({ queryKey: ['crm-proposals-by-quote', id] })
            qc.invalidateQueries({ queryKey: ['crm-proposals'] })
            setProposalOpen(false)
            setProposalExpires('')
        },
        onError: (err) => showApiError(err, 'Erro ao criar proposta'),
    })

    const invalidateAll = () => {
        qc.invalidateQueries({ queryKey: queryKeys.quotes.detail(Number(id!)) })
        qc.invalidateQueries({ queryKey: queryKeys.quotes.all })
        qc.invalidateQueries({ queryKey: queryKeys.quotes.summary })
        qc.invalidateQueries({ queryKey: queryKeys.quotes.advancedSummary })
        broadcastQueryInvalidation(['quotes', 'quotes-summary', 'quotes-advanced-summary', 'dashboard'], 'Orçamento')
    }

    const requestInternalApprovalMut = useMutation({
        mutationFn: () => quoteApi.requestInternalApproval(Number(id!)),
        onSuccess: () => { toast.success('Solicitação de aprovação interna enviada!'); invalidateAll() },
        onError: (err) => showApiError(err, 'Erro ao solicitar aprovação'),
    })

    const internalApproveMut = useMutation({
        mutationFn: () => quoteApi.internalApprove(Number(id!)),
        onSuccess: () => { toast.success('Orçamento aprovado internamente!'); invalidateAll() },
        onError: (err) => showApiError(err, 'Erro ao aprovar internamente'),
    })

    const [pendingSendContact, setPendingSendContact] = useState<{ name: string; phone?: string; email?: string } | null>(null)

    const sendMut = useMutation({
        mutationFn: () => quoteApi.send(Number(id!)),
        onSuccess: async () => {
            toast.success('Orçamento marcado como enviado!')
            invalidateAll()
            setSendModalOpen(false)

            if (sendChannel === 'whatsapp' && pendingSendContact?.phone) {
                try {
                    const res = await quoteApi.getWhatsAppUrl(Number(id!), pendingSendContact.phone)
                    const url = res?.data?.data?.url ?? res?.data?.url
                    if (url) {
                        window.open(url, '_blank')
                    } else {
                        toast.error('Link de WhatsApp não disponível')
                    }
                } catch (err) {
                    showApiError(err, 'Erro ao gerar link WhatsApp')
                }
            } else if (sendChannel === 'email') {
                setEmailTo(pendingSendContact?.email ?? quote?.customer?.email ?? '')
                setEmailName(pendingSendContact?.name ?? quote?.customer?.name ?? '')
                setEmailOpen(true)
            }
            setPendingSendContact(null)
            setSendChannel('none')
            setSendStep('channel')
        },
        onError: (err) => { showApiError(err, 'Erro ao enviar'); setPendingSendContact(null); setSendChannel('none'); setSendStep('channel') },
    })

    const openSendModal = () => {
        setSendStep('channel')
        setSendChannel('none')
        setPendingSendContact(null)
        setSendModalOpen(true)
    }

    const handleSelectChannel = (channel: 'whatsapp' | 'email' | 'none') => {
        setSendChannel(channel)
        if (channel === 'none') {
            sendMut.mutate()
        } else {
            setSendStep('contact')
        }
    }

    const handleSelectContactAndSend = (contact: { name: string; phone?: string; email?: string }) => {
        setPendingSendContact(contact)
        sendMut.mutate()
    }

    const openContactPicker = (forChannel: 'whatsapp' | 'email') => {
        setContactPickerFor(forChannel)
        setContactPickerOpen(true)
    }

    const handleContactPickerSelect = async (contact: { name: string; phone?: string; email?: string }) => {
        setContactPickerOpen(false)
        if (contactPickerFor === 'whatsapp' && contact.phone) {
            try {
                const res = await quoteApi.getWhatsAppUrl(Number(id!), contact.phone)
                const url = res?.data?.data?.url ?? res?.data?.url
                if (url) {
                    window.open(url, '_blank')
                } else {
                    toast.error('Link de WhatsApp não disponível')
                }
            } catch (err) {
                showApiError(err, 'Erro ao gerar link WhatsApp')
            }
        } else if (contactPickerFor === 'email') {
            setEmailTo(contact.email ?? '')
            setEmailName(contact.name ?? '')
            setEmailOpen(true)
        }
    }

    const approveMut = useMutation({
        mutationFn: () => quoteApi.approve(Number(id!), {
            approval_channel: approvalChannel,
            approval_notes: approvalNotes || undefined,
            terms_accepted: termsAccepted
        }),
        onSuccess: () => { toast.success('Orçamento aprovado!'); setApproveModalOpen(false); invalidateAll() },
        onError: (err) => showApiError(err, 'Erro ao aprovar'),
    })

    const rejectMut = useMutation({
        mutationFn: () => quoteApi.reject(Number(id!), normalizeRejectReason(rejectReason)),
        onSuccess: () => { toast.success('Orçamento rejeitado'); setRejectOpen(false); setRejectReason(''); invalidateAll() },
        onError: (err) => showApiError(err, 'Erro ao rejeitar'),
    })

    const convertMut = useMutation({
        mutationFn: (params: { target: 'os' | 'chamado'; is_installation_testing: boolean }) =>
            params.target === 'os'
                ? quoteApi.convertToOs(Number(id!), params.is_installation_testing)
                : quoteApi.convertToChamado(Number(id!), params.is_installation_testing),
        onSuccess: (res, params) => {
            const label = params.target === 'os' ? 'OS' : 'Chamado'
            toast.success(`${label} criado a partir do orçamento!`)
            invalidateAll()
            // Invalidar queries de OS para que a nova OS apareça em outras abas/PWA
            qc.invalidateQueries({ queryKey: ['work-orders'] })
            qc.invalidateQueries({ queryKey: ['tech-work-orders'] })
            broadcastQueryInvalidation(['work-orders', 'tech-work-orders', 'service-calls'], label)
            setConvertModalOpen(false)
            const entityId = res?.data?.id ?? res?.data?.data?.id
            if (entityId) navigate(params.target === 'os' ? `/os/${entityId}` : `/chamados/${entityId}`)
        },
        onError: (err) => showApiError(err, 'Erro ao converter'),
    })

    const approveAfterTestMut = useMutation({
        mutationFn: () => quoteApi.approveAfterTest(Number(id!)),
        onSuccess: () => {
            toast.success('Cliente aprovou após teste!')
            invalidateAll()
        },
        onError: (err) => showApiError(err, 'Erro ao aprovar'),
    })

    const renegotiateMut = useMutation({
        mutationFn: () => quoteApi.renegotiate(Number(id!)),
        onSuccess: () => {
            toast.success('Orçamento enviado para renegociação')
            invalidateAll()
        },
        onError: (err) => showApiError(err, 'Erro ao renegociar'),
    })

    const revertRenegotiationMut = useMutation({
        mutationFn: (targetStatus: string) => quoteApi.revertRenegotiation(Number(id!), targetStatus),
        onSuccess: () => {
            toast.success('Orçamento revertido com sucesso!')
            invalidateAll()
            setRevertModalOpen(false)
        },
        onError: (err) => showApiError(err, 'Erro ao reverter'),
    })

    const duplicateMut = useMutation({
        mutationFn: () => quoteApi.duplicate(Number(id!)),
        onSuccess: (res) => {
            toast.success('Orçamento duplicado!')
            invalidateAll()
            const newId = res?.data?.id ?? res?.data?.data?.id
            navigate(newId ? `/orcamentos/${newId}` : '/orcamentos')
        },
        onError: (err) => showApiError(err, 'Erro ao duplicar'),
    })

    const deleteMut = useMutation({
        mutationFn: () => quoteApi.destroy(Number(id!)),
        onSuccess: () => {
            toast.success('Orçamento excluído!')
            invalidateAll()
            navigate('/orcamentos')
        },
        onError: (err) => { showApiError(err, 'Erro ao excluir'); setDeleteOpen(false) },
    })

    const reopenMut = useMutation({
        mutationFn: () => quoteApi.reopen(Number(id!)),
        onSuccess: () => { toast.success('Orçamento reaberto!'); invalidateAll() },
        onError: (err) => showApiError(err, 'Erro ao reabrir'),
    })

    const invoiceMut = useMutation({
        mutationFn: () => quoteApi.invoice(Number(id!)),
        onSuccess: () => { toast.success('Orçamento faturado!'); invalidateAll() },
        onError: (err) => showApiError(err, 'Erro ao faturar'),
    })

    const emitNfeMut = useMutation({
        mutationFn: () => quoteApi.emitNfe(Number(id!)),
        onSuccess: () => { toast.success('NF-e emitida com sucesso!'); invalidateAll() },
        onError: (err) => showApiError(err, 'Erro ao emitir NF-e'),
    })

    const handleDownloadPdf = async () => {
        try {
            const res = await quoteApi.getPdf(Number(id!))
            const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
            const a = document.createElement('a')
            a.href = url; a.download = `orçamento_${quote?.quote_number ?? id}.pdf`; a.click()
            URL.revokeObjectURL(url)
            toast.success('PDF baixado!')
        } catch (err) {
            showApiError(err, 'Erro ao gerar PDF')
        }
    }

    const handleViewPdf = async () => {
        try {
            const res = await quoteApi.getPdf(Number(id!), true)
            const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
            window.open(url, '_blank')
        } catch (err) {
            showApiError(err, 'Erro ao gerar PDF')
        }
    }

    const handleCopyApprovalLink = async () => {
        if (!quote?.approval_url) {
            toast.error('Link de aprovação não disponível')
            return
        }
        try {
            await navigator.clipboard.writeText(quote.approval_url)
            toast.success('Link copiado para a área de transferência!')
        } catch {
            toast.error('Não foi possível copiar o link de aprovação')
        }
    }

    const sendEmailMut = useMutation({
        mutationFn: (data: { recipient_email: string; recipient_name?: string; message?: string }) =>
            quoteApi.sendEmail(Number(id!), data),
        onSuccess: () => {
            toast.success('E-mail enviado com sucesso!')
            setEmailOpen(false); setEmailTo(''); setEmailName(''); setEmailBody('')
            invalidateAll()
        },
        onError: (err) => showApiError(err, 'Erro ao enviar e-mail'),
    })

    const { data: customerData } = useQuery<CustomerWithContacts>({
        queryKey: [...queryKeys.customers.detail(quote?.customer_id ?? 0), 'contacts'],
        queryFn: async () => {
            const r = await customerApi.detail(quote!.customer_id)
            const d = (r as { data?: CustomerWithContacts })?.data ?? r
            return d as CustomerWithContacts
        },
        enabled: !!quote?.customer_id && (sendModalOpen || contactPickerOpen),
    })
    const contacts = customerData?.contacts ?? []

    const { data: installmentsData } = useQuery<QuoteInstallment[]>({
        queryKey: [...queryKeys.quotes.detail(Number(id!)), 'installments'],
        queryFn: () => quoteApi.installments(Number(id!)),
        enabled: !!id,
    })

    if (isLoading) {
        return (
            <div className="space-y-6">
                <div className="h-8 w-48 bg-surface-100 rounded animate-pulse" />
                <div className="grid gap-6 md:grid-cols-3">
                    {[1, 2, 3].map(i => <div key={i} className="h-40 bg-surface-100 rounded-xl animate-pulse" />)}
                </div>
            </div>
        )
    }

    if (isError) {
        return (
            <div className="text-center py-20 space-y-4">
                <p className="text-content-secondary">{getApiErrorMessage(error, 'Erro ao carregar orçamento')}</p>
                <div className="flex items-center justify-center gap-3">
                    <Button variant="outline" onClick={() => navigate('/orcamentos')}>Voltar</Button>
                    <Button onClick={() => refetch()} icon={<RefreshCw className="h-4 w-4" />}>Tentar novamente</Button>
                </div>
            </div>
        )
    }

    if (!quote) {
        return (
            <div className="text-center py-20">
                <p className="text-content-secondary">Orçamento não encontrado</p>
                <Button variant="outline" className="mt-4" onClick={() => navigate('/orcamentos')}>Voltar</Button>
            </div>
        )
    }

    const cfg = QUOTE_STATUS_CONFIG[quote.status] ?? { label: quote.status, variant: 'default' }
    const isDraft = quote.status === QUOTE_STATUS.DRAFT
    const isPendingInternal = quote.status === QUOTE_STATUS.PENDING_INTERNAL
    const isInternallyApproved = quote.status === QUOTE_STATUS.INTERNALLY_APPROVED
    const isSent = quote.status === QUOTE_STATUS.SENT
    const isApproved = quote.status === QUOTE_STATUS.APPROVED
    const isRejected = quote.status === QUOTE_STATUS.REJECTED
    const isExpired = quote.status === QUOTE_STATUS.EXPIRED
    const isInExecution = quote.status === QUOTE_STATUS.IN_EXECUTION
    const isInvoiced = quote.status === QUOTE_STATUS.INVOICED
    const canInvoice = isApproved || isInExecution
    const isInstallationTesting = quote.status === QUOTE_STATUS.INSTALLATION_TESTING
    const isRenegotiation = quote.status === QUOTE_STATUS.RENEGOTIATION
    const isMutable = isMutableQuoteStatus(quote.status)
    const isConvertible = isApproved || isInternallyApproved
    const paymentSummary = getQuotePaymentSummary(quote)
    const hasPaymentBlock = Boolean(quote.payment_terms || quote.payment_terms_detail || paymentSummary.schedule.length > 0)

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" onClick={() => navigate('/orcamentos')} aria-label="Voltar à lista de orçamentos">
                        <ArrowLeft className="h-5 w-5" />
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-content-primary">
                            Orçamento {quote.quote_number}
                            {quote.revision > 1 && <span className="text-base text-content-tertiary ml-2">rev.{quote.revision}</span>}
                        </h1>
                        <Badge variant={cfg.variant} className="mt-1">{cfg.label}</Badge>
                    </div>
                </div>
                <div className="flex gap-2 flex-wrap">
                    {canUpdate && isMutable && (
                        <Button variant="outline" size="sm" icon={<Pencil className="h-4 w-4" />} onClick={() => navigate(`/orcamentos/${id}/editar`)}>Editar</Button>
                    )}
                    {canSend && isDraft && (
                        <Button size="sm" variant="outline" icon={<Send className="h-4 w-4" />} onClick={() => requestInternalApprovalMut.mutate()} disabled={requestInternalApprovalMut.isPending}>
                            {requestInternalApprovalMut.isPending ? 'Solicitando...' : 'Solicitar Aprovação Interna'}
                        </Button>
                    )}
                    {(canInternalApprove && (isDraft || isPendingInternal)) && (
                        <Button size="sm" variant="outline" icon={<CheckCircle className="h-4 w-4" />} onClick={() => internalApproveMut.mutate()} disabled={internalApproveMut.isPending}>
                            {internalApproveMut.isPending ? 'Aprovando...' : 'Aprovar internamente'}
                        </Button>
                    )}
                    {canSend && isInternallyApproved && (
                        <Button size="sm" icon={<Send className="h-4 w-4" />} onClick={openSendModal} disabled={sendMut.isPending}>
                            Enviar ao Cliente
                        </Button>
                    )}
                    {canApprove && isSent && (
                        <>
                            <Button size="sm" variant="success" icon={<CheckCircle className="h-4 w-4" />} onClick={() => setApproveModalOpen(true)} disabled={approveMut.isPending}>
                                Aprovar
                            </Button>
                            <Button size="sm" variant="danger" icon={<XCircle className="h-4 w-4" />} onClick={() => setRejectOpen(true)} disabled={rejectMut.isPending}>
                                Rejeitar
                            </Button>
                        </>
                    )}
                    {canConvert && isConvertible && (
                        <>
                            <Button size="sm" icon={<ArrowRightLeft className="h-4 w-4" />} onClick={() => { setConvertTarget('os'); setConvertInstallationTesting(isInternallyApproved); setConvertModalOpen(true) }} disabled={convertMut.isPending}>
                                Converter em OS
                            </Button>
                            <Button size="sm" variant="outline" icon={<Phone className="h-4 w-4" />} onClick={() => { setConvertTarget('chamado'); setConvertInstallationTesting(isInternallyApproved); setConvertModalOpen(true) }} disabled={convertMut.isPending}>
                                Converter em Chamado
                            </Button>
                        </>
                    )}
                    {canConvert && isInstallationTesting && (
                        <>
                            <Button size="sm" variant="success" icon={<CheckCircle className="h-4 w-4" />} onClick={() => approveAfterTestMut.mutate()} disabled={approveAfterTestMut.isPending}>
                                {approveAfterTestMut.isPending ? 'Aprovando...' : 'Cliente Aprovou'}
                            </Button>
                            <Button size="sm" variant="danger" icon={<MessageSquare className="h-4 w-4" />} onClick={() => renegotiateMut.mutate()} disabled={renegotiateMut.isPending}>
                                {renegotiateMut.isPending ? 'Enviando...' : 'Em Renegociação'}
                            </Button>
                        </>
                    )}
                    {canConvert && isRenegotiation && (
                        <Button size="sm" variant="outline" icon={<RefreshCw className="h-4 w-4" />} onClick={() => setRevertModalOpen(true)} disabled={revertRenegotiationMut.isPending}>
                            Reverter Renegociação
                        </Button>
                    )}
                    {canUpdate && (isRejected || isExpired) && (
                        <Button size="sm" variant="outline" icon={<RefreshCw className="h-4 w-4" />} onClick={() => reopenMut.mutate()} disabled={reopenMut.isPending}>
                            Reabrir
                        </Button>
                    )}
                    {canApprove && canInvoice && (
                        <Button size="sm" variant="success" icon={<FileText className="h-4 w-4" />} onClick={() => invoiceMut.mutate()} disabled={invoiceMut.isPending}>
                            {invoiceMut.isPending ? 'Faturando...' : 'Faturar'}
                        </Button>
                    )}
                    {canApprove && (isApproved || isInExecution || isInvoiced) && (
                        <Button size="sm" variant="outline" icon={<FileDown className="h-4 w-4" />} onClick={() => emitNfeMut.mutate()} disabled={emitNfeMut.isPending}>
                            {emitNfeMut.isPending ? 'Emitindo...' : 'Emitir NF-e'}
                        </Button>
                    )}
                    {hasPermission('quotes.quote.view') && (
                        <>
                            <Button variant="outline" size="sm" icon={<Eye className="h-4 w-4" />} onClick={handleViewPdf}>Visualizar</Button>
                            <Button variant="outline" size="sm" icon={<FileDown className="h-4 w-4" />} onClick={handleDownloadPdf}>PDF</Button>
                        </>
                    )}
                    {isSent && quote.approval_url && (
                        <Button variant="outline" size="sm" icon={<LinkIcon className="h-4 w-4" />} onClick={handleCopyApprovalLink}>
                            Copiar Link
                        </Button>
                    )}
                    {canSend && isSent && (
                        <Button variant="outline" size="sm" icon={<MessageCircle className="h-4 w-4" />} onClick={() => openContactPicker('whatsapp')} className="text-green-600" disabled={sendMut.isPending}>
                            WhatsApp
                        </Button>
                    )}
                    {canSend && isSent && (
                        <Button variant="outline" size="sm" icon={<Mail className="h-4 w-4" />} onClick={() => openContactPicker('email')} disabled={sendMut.isPending || sendEmailMut.isPending}>
                            E-mail
                        </Button>
                    )}
                    {canProposalView && hasProposal && (
                        <Badge variant="outline" className="text-xs">Proposta interativa enviada</Badge>
                    )}
                    {canProposalManage && !hasProposal && (
                        <Button variant="outline" size="sm" icon={<FileText className="h-4 w-4" />} onClick={() => setProposalOpen(true)} disabled={createProposalMut.isPending}>
                            Criar proposta interativa
                        </Button>
                    )}
                    {canCreate && (
                        <Button variant="outline" size="sm" icon={<Copy className="h-4 w-4" />} onClick={() => duplicateMut.mutate()} disabled={duplicateMut.isPending}>
                            Duplicar
                        </Button>
                    )}
                    {canDelete && isMutable && (
                        <Button variant="danger" size="sm" icon={<Trash2 className="h-4 w-4" />} onClick={() => setDeleteOpen(true)} disabled={deleteMut.isPending}>
                            Excluir
                        </Button>
                    )}
                </div>
            </div>

            {isRejected && quote.rejection_reason && (
                <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-sm font-medium text-red-800">Motivo da rejeição:</p>
                    <p className="text-sm text-red-700 mt-1">{quote.rejection_reason}</p>
                </div>
            )}

            {isExpired && (
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p className="text-sm font-medium text-amber-800">Este orçamento expirou em {quote.valid_until ? new Date(quote.valid_until).toLocaleDateString('pt-BR') : '—'}.</p>
                    <p className="text-sm text-amber-700 mt-1">Reabra o orçamento para editar e reenviar ao cliente.</p>
                </div>
            )}

            {isSent && quote.valid_until && (() => {
                // eslint-disable-next-line react-hooks/purity
                const daysLeft = Math.ceil((new Date(quote.valid_until).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
                if (daysLeft > 0 && daysLeft <= 3) {
                    return (
                        <div className="bg-orange-50 border border-orange-200 rounded-xl p-4">
                            <p className="text-sm font-medium text-orange-800">Atenção: Este orçamento expira em {daysLeft} dia{daysLeft > 1 ? 's' : ''}.</p>
                        </div>
                    )
                }
                return null
            })()}

            <div className="grid gap-6 md:grid-cols-3">
                <Card className="p-5">
                    <h3 className="text-sm font-semibold text-content-secondary mb-3">Cliente</h3>
                    <p className="font-medium text-content-primary">{quote.customer?.name ?? '—'}</p>
                    {quote.customer?.document && <p className="text-sm text-content-secondary mt-1">{quote.customer.document}</p>}
                    {quote.customer?.email && <p className="text-sm text-content-secondary">{quote.customer.email}</p>}
                    {quote.customer?.phone && <p className="text-sm text-content-secondary">{quote.customer.phone}</p>}
                </Card>

                <Card className="p-5">
                    <h3 className="text-sm font-semibold text-content-secondary mb-3">Resumo Financeiro</h3>
                    <div className="space-y-2">
                        <div className="flex justify-between"><span className="text-sm text-content-secondary">Subtotal</span><span className="font-medium">{formatCurrency(quote.subtotal)}</span></div>
                        {(parseFloat(String(quote.discount_amount)) > 0 || parseFloat(String(quote.discount_percentage)) > 0) && (
                            <div className="flex justify-between text-red-600">
                                <span className="text-sm">Desconto {parseFloat(String(quote.discount_percentage)) > 0 ? `(${quote.discount_percentage}%)` : ''}</span>
                                <span className="font-medium">- {formatCurrency(quote.discount_amount)}</span>
                            </div>
                        )}
                        {parseFloat(String(quote.displacement_value)) > 0 && (
                            <div className="flex justify-between text-content-secondary">
                                <span className="text-sm">Deslocamento</span>
                                <span className="font-medium">+ {formatCurrency(quote.displacement_value)}</span>
                            </div>
                        )}
                        <div className="flex justify-between border-t border-default pt-2">
                            <span className="font-semibold">Total</span>
                            <span className="text-xl font-bold text-brand-600">{formatCurrency(quote.total)}</span>
                        </div>
                    </div>
                </Card>

                <Card className="p-5">
                    <h3 className="text-sm font-semibold text-content-secondary mb-3">Informações</h3>
                    <div className="space-y-2 text-sm">
                        <div className="flex justify-between"><span className="text-content-secondary">Vendedor</span><span>{quote.seller?.name ?? '—'}</span></div>
                        {quote.source && <div className="flex justify-between"><span className="text-content-secondary">Origem</span><span className="capitalize">{quote.source.replace(/[_-]+/g, ' ')}</span></div>}
                        <div className="flex justify-between"><span className="text-content-secondary">Validade</span><span>{quote.valid_until ? new Date(quote.valid_until).toLocaleDateString('pt-BR') : '—'}</span></div>
                        <div className="flex justify-between"><span className="text-content-secondary">Criado em</span><span>{quote.created_at ? new Date(quote.created_at).toLocaleDateString('pt-BR') : '—'}</span></div>
                        {quote.creator && <div className="flex justify-between"><span className="text-content-secondary">Criado por</span><span>{quote.creator.name}</span></div>}
                        {quote.internal_approved_at && (
                            <div className="flex justify-between"><span className="text-content-secondary">Aprovação interna</span><span>{new Date(quote.internal_approved_at).toLocaleDateString('pt-BR')}</span></div>
                        )}
                        {quote.sent_at && <div className="flex justify-between"><span className="text-content-secondary">Enviado em</span><span>{new Date(quote.sent_at).toLocaleDateString('pt-BR')}</span></div>}
                        {quote.approved_at && <div className="flex justify-between"><span className="text-content-secondary">Aprovado em</span><span>{new Date(quote.approved_at).toLocaleDateString('pt-BR')}</span></div>}
                        {quote.approved_by_name && <div className="flex justify-between gap-3"><span className="text-content-secondary">Aprovado por</span><span className="text-right">{quote.approved_by_name}</span></div>}
                        {quote.approval_channel && <div className="flex justify-between gap-3"><span className="text-content-secondary">Canal</span><span className="text-right">{formatApprovalChannel(quote.approval_channel)}</span></div>}
                        {quote.client_view_count > 0 && <div className="flex justify-between"><span className="text-content-secondary">Visualizações do cliente</span><span>{quote.client_view_count}</span></div>}
                        {quote.client_viewed_at && <div className="flex justify-between gap-3"><span className="text-content-secondary">Última visualização</span><span className="text-right">{formatDateTime(quote.client_viewed_at)}</span></div>}
                        {quote.opportunity_id && (
                            <div className="flex justify-between gap-3">
                                <span className="text-content-secondary">Oportunidade CRM</span>
                                <a href={`/crm/deals/${quote.opportunity_id}`} className="text-brand-600 hover:underline text-right" onClick={(e) => { e.preventDefault(); navigate(`/crm/deals/${quote.opportunity_id}`) }}>
                                    Ver Deal
                                </a>
                            </div>
                        )}
                    </div>
                </Card>
            </div>

            {/* Rastreabilidade: OS e Chamados vinculados */}
            {(quote.work_orders?.length || quote.service_calls?.length || quote.account_receivables?.length) ? (
                <Card className="p-5">
                    <h3 className="text-sm font-semibold text-content-secondary mb-3 flex items-center gap-2">
                        <LinkIcon className="h-4 w-4" /> Entidades Vinculadas
                    </h3>
                    <div className="flex flex-wrap gap-2">
                        {(quote.work_orders ?? []).map((wo) => (
                            <button key={wo.id} onClick={() => navigate(`/os/${wo.id}`)}
                                aria-label={`Abrir ordem de serviço ${wo.os_number ?? wo.number}`}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-100 transition-colors border border-emerald-200">
                                <Wrench className="h-3.5 w-3.5" />
                                OS {wo.os_number ?? wo.number}
                                <span className="text-xs text-emerald-500 ml-1">({wo.status})</span>
                            </button>
                        ))}
                        {(quote.service_calls ?? []).map((sc) => (
                            <button key={sc.id} onClick={() => navigate(`/chamados/${sc.id}`)}
                                aria-label={`Abrir chamado ${sc.call_number}`}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-sky-50 px-3 py-1.5 text-sm font-medium text-sky-700 hover:bg-sky-100 transition-colors border border-sky-200">
                                <Phone className="h-3.5 w-3.5" />
                                Chamado {sc.call_number}
                                <span className="text-xs text-sky-500 ml-1">({sc.status})</span>
                            </button>
                        ))}
                        {(quote.account_receivables ?? []).map((ar) => (
                            <button key={ar.id} onClick={() => navigate(`/financeiro/receber`)}
                                aria-label={`Abrir conta a receber: ${ar.description}`}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-100 transition-colors border border-emerald-200">
                                <DollarSign className="h-3.5 w-3.5" />
                                {ar.description}
                                <span className="text-xs text-emerald-500 ml-1">({formatCurrency(ar.amount)} — {ar.status})</span>
                            </button>
                        ))}
                    </div>
                </Card>
            ) : null}

            {/* Payment Terms & Installments */}
            {(hasPaymentBlock || (installmentsData && installmentsData.length > 0)) && (
                <div className="grid gap-6 md:grid-cols-2">
                    {hasPaymentBlock && (
                        <Card className="p-5">
                            <h3 className="text-sm font-semibold text-content-secondary mb-3 flex items-center gap-2">
                                <DollarSign className="h-4 w-4" /> Condições de Pagamento
                            </h3>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-content-tertiary">Meio</p>
                                    <p className="text-sm text-content-primary font-medium">{paymentSummary.methodLabel}</p>
                                </div>
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-content-tertiary">Condição</p>
                                    <p className="text-sm text-content-secondary">{paymentSummary.conditionSummary}</p>
                                </div>
                                {paymentSummary.schedule.length > 0 && (
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-content-tertiary mb-2">Agenda de vencimentos</p>
                                        <div className="space-y-1.5">
                                            {paymentSummary.schedule.map((line) => (
                                                <div key={`${line.title}-${line.days}`} className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm text-content-primary">
                                                    {line.text}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {paymentSummary.detailText && (
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-content-tertiary">Observação</p>
                                        <p className="text-sm text-content-secondary">{paymentSummary.detailText}</p>
                                    </div>
                                )}
                            </div>
                        </Card>
                    )}
                    {installmentsData && installmentsData.length > 0 && (
                        <Card className="p-5">
                            <h3 className="text-sm font-semibold text-content-secondary mb-3">Simulação de Parcelas</h3>
                            <div className="space-y-1">
                                {(installmentsData || []).map((inst) => (
                                    <div key={inst.installments} className="flex justify-between text-sm">
                                        <span className="text-content-secondary">{inst.installments}x</span>
                                        <span className="font-medium">{formatCurrency(inst.value)}</span>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}
                </div>
            )}

            {/* Tags */}
            {quote.tags && quote.tags.length > 0 && (
                <div className="flex gap-2 flex-wrap">
                    {(quote.tags ?? []).map((t) => (
                        <span key={t.id} className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium" style={{ backgroundColor: `${t.color ?? ''}20`, color: t.color ?? undefined }}>
                            <Tag className="h-3 w-3" />{t.name}
                        </span>
                    ))}
                </div>
            )}

            {quote.equipments && quote.equipments.length > 0 && (
                <Card className="p-5">
                    <h3 className="text-sm font-semibold text-content-secondary mb-4">Equipamentos e Itens</h3>
                    <div className="space-y-6">
                        {(quote.equipments || []).map((eq) => (
                            <div key={eq.id} className="border border-default rounded-lg p-4">
                                <div className="flex items-center gap-2 mb-3">
                                    <span className="font-medium text-content-primary">{eq.equipment?.tag || eq.equipment?.model || 'Equipamento'}</span>
                                    {eq.description && <span className="text-sm text-content-secondary">— {eq.description}</span>}
                                </div>

                                {eq.photos && eq.photos.length > 0 && (
                                    <div className="flex flex-wrap gap-3 mb-4">
                                        {eq.photos.map((p) => (
                                            <a
                                                key={p.id}
                                                href={p.path.startsWith('http') ? p.path : `${getApiOrigin()}/storage/${p.path}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="relative group overflow-hidden rounded-md border border-default bg-surface-50 w-24 h-24 block shrink-0"
                                            >
                                                <img
                                                    src={p.path.startsWith('http') ? p.path : `${getApiOrigin()}/storage/${p.path}`}
                                                    alt={p.caption || 'Foto'}
                                                    className="w-full h-full object-cover transition-transform group-hover:scale-110"
                                                />
                                                {p.caption && (
                                                    <div className="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] truncate px-1.5 py-1 text-center" title={p.caption}>
                                                        {p.caption}
                                                    </div>
                                                )}
                                            </a>
                                        ))}
                                    </div>
                                )}

                                {eq.items && eq.items.length > 0 && (
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="text-content-secondary">
                                                <th className="text-left py-1">Item</th>
                                                <th className="text-right py-1">Qtd</th>
                                                <th className="text-right py-1">Preço Unit.</th>
                                                <th className="text-right py-1">Desc.</th>
                                                <th className="text-right py-1">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(eq.items || []).map((item) => (
                                                <tr key={item.id} className="border-t border-default/50">
                                                    <td className="py-2">{item.custom_description || item.product?.name || item.service?.name || '—'}</td>
                                                    <td className="text-right py-2">{item.quantity}</td>
                                                    <td className="text-right py-2">{formatCurrency(item.unit_price)}</td>
                                                    <td className="text-right py-2">{parseFloat(String(item.discount_percentage)) > 0 ? `${item.discount_percentage}%` : '—'}</td>
                                                    <td className="text-right py-2 font-medium">{formatCurrency(item.subtotal)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        ))}
                    </div>
                </Card>
            )}
            {(!quote.equipments || quote.equipments.length === 0) && (
                <Card className="p-5">
                    <h3 className="text-sm font-semibold text-content-secondary mb-2">Equipamentos e Itens</h3>
                    <p className="text-sm text-content-secondary">Sem dados de itens neste orçamento.</p>
                </Card>
            )}

            {/* Observações e Condições */}
            {(quote.observations || quote.internal_notes || quote.general_conditions) && (
                <div className="grid gap-6 md:grid-cols-2">
                    {quote.observations && (
                        <Card className="p-5">
                            <h3 className="text-sm font-semibold text-content-secondary mb-2">Observações</h3>
                            <p className="text-sm text-content-primary whitespace-pre-line">{quote.observations}</p>
                        </Card>
                    )}
                    {quote.general_conditions && (
                        <Card className="p-5">
                            <h3 className="text-sm font-semibold text-content-secondary mb-2">Condições Gerais</h3>
                            <p className="text-sm text-content-primary whitespace-pre-line">{quote.general_conditions}</p>
                        </Card>
                    )}
                    {quote.internal_notes && (
                        <Card className="p-5 md:col-span-2">
                            <h3 className="text-sm font-semibold text-content-secondary mb-2">Notas Internas</h3>
                            <p className="text-sm text-content-primary whitespace-pre-line">{quote.internal_notes}</p>
                        </Card>
                    )}
                </div>
            )}

            {quote.emails && quote.emails.length > 0 && (
                <Card className="p-5">
                    <h3 className="text-sm font-semibold text-content-secondary mb-3 flex items-center gap-2">
                        <Mail className="h-4 w-4" /> Historico de E-mails
                    </h3>
                    <div className="space-y-3">
                        {(quote.emails || []).map((email) => (
                            <div key={email.id} className="rounded-lg border border-default p-3 text-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="font-medium text-content-primary truncate">{email.recipient_name || email.recipient_email}</p>
                                        <p className="text-xs text-content-secondary truncate">{email.recipient_email}</p>
                                    </div>
                                    <Badge variant={email.status === 'failed' ? 'destructive' : email.status === 'sent' ? 'success' : 'outline'}>
                                        {email.status}
                                    </Badge>
                                </div>
                                <div className="mt-2 space-y-1 text-xs text-content-secondary">
                                    <p>Assunto: {email.subject}</p>
                                    <p>Enfileirado: {formatDateTime(email.queued_at || email.created_at)}</p>
                                    {email.sent_at && <p>Enviado: {formatDateTime(email.sent_at)}</p>}
                                    {email.failed_at && <p>Falhou em: {formatDateTime(email.failed_at)}</p>}
                                    {email.error_message && <p className="text-red-600">Erro: {email.error_message}</p>}
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            <Card className="p-5">
                <h3 className="text-sm font-semibold text-content-secondary mb-3 flex items-center gap-2">
                    <History className="h-4 w-4" /> Histórico
                </h3>
                {timeline.length === 0 ? (
                    <p className="text-sm text-content-tertiary">Nenhum evento registrado.</p>
                ) : (
                    <ul className="space-y-2">
                        {(timeline || []).map((log) => (
                            <li key={log.id} className="flex flex-wrap items-baseline gap-2 text-sm border-b border-default/50 pb-2 last:border-0 last:pb-0">
                                <span className="text-content-secondary shrink-0">
                                    {formatDateTime(log.created_at)}
                                </span>
                                <span className="rounded-full bg-surface-100 px-2 py-0.5 text-[11px] font-medium text-content-secondary">
                                    {log.action_label || log.action}
                                </span>
                                <span className="text-content-primary">{log.description || log.action}</span>
                                {log.user_name && <span className="text-xs text-content-secondary">por {log.user_name}</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {proposalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setProposalOpen(false)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-md mx-4 shadow-elevated w-full" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">Criar proposta interativa</h3>
                        <p className="text-content-secondary text-sm mb-4">Envie este orçamento como proposta pelo CRM (link para o cliente ver e aceitar).</p>
                        <label htmlFor="quote-proposal-expires" className="block text-sm font-medium text-content-secondary mb-1">Validade da proposta (opcional)</label>
                        <input
                            id="quote-proposal-expires"
                            type="date"
                            value={proposalExpires}
                            onChange={(e) => setProposalExpires(e.target.value)}
                            className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm mb-4"
                            aria-label="Validade da proposta"
                        />
                        <div className="flex gap-3 justify-end">
                            <Button variant="outline" size="sm" onClick={() => { setProposalOpen(false); setProposalExpires('') }}>Cancelar</Button>
                            <Button size="sm" onClick={() => createProposalMut.mutate({ quote_id: Number(id), expires_at: proposalExpires || undefined })} disabled={createProposalMut.isPending}>
                                {createProposalMut.isPending ? 'Criando...' : 'Criar'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Send Modal - Step 1: Channel, Step 2: Contact */}
            {sendModalOpen && quote && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => { if (!sendMut.isPending) { setSendModalOpen(false); setSendStep('channel') } }}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-md mx-4 shadow-elevated w-full" onClick={(e) => e.stopPropagation()}>

                        {sendStep === 'channel' && (
                            <>
                                <h3 className="text-lg font-semibold text-content-primary mb-1">Enviar Orçamento ao Cliente</h3>
                                <p className="text-content-secondary text-sm mb-4">
                                    Como deseja enviar <strong>{quote.quote_number}</strong>?
                                </p>

                                <div className="space-y-2">
                                    <button type="button" onClick={() => handleSelectChannel('whatsapp')}
                                        aria-label="Enviar orçamento por WhatsApp"
                                        className="flex w-full items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-3 text-left text-sm font-medium text-green-800 hover:bg-green-100 transition-colors">
                                        <MessageCircle className="h-5 w-5 text-green-600 shrink-0" />
                                        <div>
                                            <p>Enviar por WhatsApp</p>
                                            <p className="text-xs font-normal text-green-600">Marca como enviado e abre conversa</p>
                                        </div>
                                    </button>

                                    <button type="button" onClick={() => handleSelectChannel('email')}
                                        aria-label="Enviar orçamento por e-mail"
                                        className="flex w-full items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-left text-sm font-medium text-blue-800 hover:bg-blue-100 transition-colors">
                                        <Mail className="h-5 w-5 text-blue-600 shrink-0" />
                                        <div>
                                            <p>Enviar por E-mail</p>
                                            <p className="text-xs font-normal text-blue-600">Marca como enviado e envia e-mail com PDF</p>
                                        </div>
                                    </button>

                                    <button type="button" onClick={() => handleSelectChannel('none')} disabled={sendMut.isPending}
                                        aria-label="Apenas marcar orçamento como enviado"
                                        className="flex w-full items-center gap-3 rounded-lg border border-default bg-surface-50 p-3 text-left text-sm font-medium text-content-secondary hover:bg-surface-100 disabled:opacity-50 transition-colors">
                                        <CheckCircle className="h-5 w-5 text-surface-500 shrink-0" />
                                        <div>
                                            <p>Apenas marcar como enviado</p>
                                            <p className="text-xs font-normal text-surface-400">Altera o status sem enviar notificação</p>
                                        </div>
                                    </button>
                                </div>

                                {sendMut.isPending && <p className="text-xs text-brand-600 text-center mt-3 animate-pulse">Processando...</p>}

                                <div className="flex justify-end mt-4">
                                    <Button variant="outline" size="sm" onClick={() => setSendModalOpen(false)} disabled={sendMut.isPending}>Cancelar</Button>
                                </div>
                            </>
                        )}

                        {sendStep === 'contact' && (
                            <>
                                <h3 className="text-lg font-semibold text-content-primary mb-1">
                                    Selecione o Contato
                                </h3>
                                <p className="text-content-secondary text-sm mb-4">
                                    {sendChannel === 'whatsapp' ? 'Para qual contato enviar via WhatsApp?' : 'Para qual contato enviar o e-mail?'}
                                </p>

                                <div className="space-y-2 max-h-64 overflow-y-auto">
                                    {quote.customer?.phone && (
                                        <button type="button"
                                            onClick={() => handleSelectContactAndSend({ name: quote.customer?.name ?? 'Cliente', phone: quote.customer?.phone ?? undefined, email: quote.customer?.email ?? undefined })}
                                            disabled={sendMut.isPending || (sendChannel === 'whatsapp' && !quote.customer?.phone)}
                                            className="flex w-full items-center gap-3 rounded-lg border border-default bg-surface-0 p-3 text-left text-sm hover:bg-surface-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 shrink-0 text-xs font-bold">
                                                {(quote.customer?.name ?? 'C')[0].toUpperCase()}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium text-content-primary truncate">{quote.customer?.name} <span className="text-xs text-surface-400 font-normal">(principal)</span></p>
                                                <p className="text-xs text-content-secondary truncate">
                                                    {sendChannel === 'whatsapp' ? (quote.customer?.phone || 'Sem telefone') : (quote.customer?.email || 'Sem e-mail')}
                                                </p>
                                            </div>
                                        </button>
                                    )}
                                    {!quote.customer?.phone && !contacts.length && (
                                        <button type="button"
                                            onClick={() => handleSelectContactAndSend({ name: quote.customer?.name ?? 'Cliente', email: quote.customer?.email ?? undefined })}
                                            disabled={sendMut.isPending || (sendChannel === 'email' && !quote.customer?.email)}
                                            className="flex w-full items-center gap-3 rounded-lg border border-default bg-surface-0 p-3 text-left text-sm hover:bg-surface-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 shrink-0 text-xs font-bold">
                                                {(quote.customer?.name ?? 'C')[0].toUpperCase()}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium text-content-primary truncate">{quote.customer?.name} <span className="text-xs text-surface-400 font-normal">(principal)</span></p>
                                                <p className="text-xs text-content-secondary truncate">
                                                    {sendChannel === 'whatsapp' ? (quote.customer?.phone || 'Sem telefone') : (quote.customer?.email || 'Sem e-mail')}
                                                </p>
                                            </div>
                                        </button>
                                    )}

                                    {(contacts || []).map((c) => {
                                        const contactValue = sendChannel === 'whatsapp' ? c.phone : c.email
                                        return (
                                            <button key={c.id} type="button"
                                                onClick={() => handleSelectContactAndSend({ name: c.name, phone: c.phone ?? undefined, email: c.email ?? undefined })}
                                                disabled={sendMut.isPending || !contactValue}
                                                className="flex w-full items-center gap-3 rounded-lg border border-default bg-surface-0 p-3 text-left text-sm hover:bg-surface-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-surface-100 text-surface-600 shrink-0 text-xs font-bold">
                                                    {c.name[0].toUpperCase()}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="font-medium text-content-primary truncate">
                                                        {c.name}
                                                        {c.role && <span className="text-xs text-surface-400 font-normal ml-1">({c.role})</span>}
                                                        {c.is_primary && <span className="text-xs text-brand-600 font-normal ml-1">★</span>}
                                                    </p>
                                                    <p className="text-xs text-content-secondary truncate">
                                                        {contactValue || (sendChannel === 'whatsapp' ? 'Sem telefone' : 'Sem e-mail')}
                                                    </p>
                                                </div>
                                            </button>
                                        )
                                    })}
                                </div>

                                {sendMut.isPending && <p className="text-xs text-brand-600 text-center mt-3 animate-pulse">Enviando orçamento...</p>}

                                <div className="flex justify-between mt-4">
                                    <Button variant="ghost" size="sm" onClick={() => setSendStep('channel')} disabled={sendMut.isPending}>← Voltar</Button>
                                    <Button variant="outline" size="sm" onClick={() => { setSendModalOpen(false); setSendStep('channel') }} disabled={sendMut.isPending}>Cancelar</Button>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}

            {/* Contact Picker (for standalone WhatsApp/Email buttons) */}
            {contactPickerOpen && quote && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setContactPickerOpen(false)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-md mx-4 shadow-elevated w-full" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-1">
                            {contactPickerFor === 'whatsapp' ? 'Enviar WhatsApp' : 'Enviar E-mail'}
                        </h3>
                        <p className="text-content-secondary text-sm mb-4">Selecione o contato:</p>

                        <div className="space-y-2 max-h-64 overflow-y-auto">
                            {(() => {
                                const mainContact = { name: quote.customer?.name ?? 'Cliente', phone: quote.customer?.phone ?? undefined, email: quote.customer?.email ?? undefined }
                                const mainValue = contactPickerFor === 'whatsapp' ? mainContact.phone : mainContact.email
                                return (
                                    <button type="button"
                                        onClick={() => handleContactPickerSelect(mainContact)}
                                        disabled={!mainValue}
                                        className="flex w-full items-center gap-3 rounded-lg border border-default bg-surface-0 p-3 text-left text-sm hover:bg-surface-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-brand-700 shrink-0 text-xs font-bold">
                                            {mainContact.name[0].toUpperCase()}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium text-content-primary truncate">{mainContact.name} <span className="text-xs text-surface-400 font-normal">(principal)</span></p>
                                            <p className="text-xs text-content-secondary truncate">{mainValue || (contactPickerFor === 'whatsapp' ? 'Sem telefone' : 'Sem e-mail')}</p>
                                        </div>
                                    </button>
                                )
                            })()}

                            {(contacts || []).map((c) => {
                                const val = contactPickerFor === 'whatsapp' ? c.phone : c.email
                                return (
                                    <button key={c.id} type="button"
                                        onClick={() => handleContactPickerSelect({ name: c.name, phone: c.phone ?? undefined, email: c.email ?? undefined })}
                                        disabled={!val}
                                        className="flex w-full items-center gap-3 rounded-lg border border-default bg-surface-0 p-3 text-left text-sm hover:bg-surface-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-surface-100 text-surface-600 shrink-0 text-xs font-bold">
                                            {c.name[0].toUpperCase()}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium text-content-primary truncate">
                                                {c.name}
                                                {c.role && <span className="text-xs text-surface-400 font-normal ml-1">({c.role})</span>}
                                                {c.is_primary && <span className="text-xs text-brand-600 font-normal ml-1">★</span>}
                                            </p>
                                            <p className="text-xs text-content-secondary truncate">{val || (contactPickerFor === 'whatsapp' ? 'Sem telefone' : 'Sem e-mail')}</p>
                                        </div>
                                    </button>
                                )
                            })}
                        </div>

                        <div className="flex justify-end mt-4">
                            <Button variant="outline" size="sm" onClick={() => setContactPickerOpen(false)}>Cancelar</Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Email Modal */}
            {emailOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setEmailOpen(false)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-md mx-4 shadow-elevated w-full" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">Enviar Orçamento por E-mail</h3>
                        <p className="text-content-secondary text-sm mb-4">O PDF será anexado automaticamente ao e-mail.</p>
                        <div className="space-y-3">
                            <div>
                                <label className="block text-sm font-medium text-content-secondary mb-1">E-mail do destinatário *</label>
                                <input type="email" value={emailTo} onChange={(e) => setEmailTo(e.target.value)} className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm" placeholder="email@exemplo.com" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-content-secondary mb-1">Nome do destinatário</label>
                                <input value={emailName} onChange={(e) => setEmailName(e.target.value)} className="w-full rounded-lg border border-default bg-surface-0 px-3 py-2 text-sm" placeholder="Nome" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-content-secondary mb-1">Mensagem personalizada</label>
                                <textarea value={emailBody} onChange={(e) => setEmailBody(e.target.value)} className="w-full rounded-lg border border-default p-3 text-sm min-h-[80px]" placeholder="Mensagem opcional..." />
                            </div>
                        </div>
                        <div className="flex gap-3 justify-end mt-4">
                            <Button variant="outline" size="sm" onClick={() => setEmailOpen(false)}>Cancelar</Button>
                            <Button size="sm" icon={<Mail className="h-4 w-4" />} onClick={() => sendEmailMut.mutate({ recipient_email: emailTo, recipient_name: emailName || undefined, message: emailBody || undefined })} disabled={!emailTo || sendEmailMut.isPending}>
                                {sendEmailMut.isPending ? 'Enviando...' : 'Enviar E-mail'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {rejectOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setRejectOpen(false)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-md mx-4 shadow-elevated w-full" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">Rejeitar Orçamento</h3>
                        <p className="text-content-secondary text-sm mb-4">Informe o motivo da rejeição (opcional):</p>
                        <textarea
                            className="w-full rounded-lg border border-default p-3 text-sm min-h-[100px] focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                            placeholder="Motivo da rejeição..."
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                        />
                        <div className="flex gap-3 justify-end mt-4">
                            <Button variant="outline" size="sm" onClick={() => setRejectOpen(false)}>Cancelar</Button>
                            <Button variant="danger" size="sm" onClick={() => rejectMut.mutate()} disabled={rejectMut.isPending}>
                                {rejectMut.isPending ? 'Rejeitando...' : 'Rejeitar'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {deleteOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setDeleteOpen(false)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-sm mx-4 shadow-elevated" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">Excluir Orçamento</h3>
                        <p className="text-content-secondary text-sm mb-6">
                            Tem certeza que deseja excluir o orçamento <strong>{quote.quote_number}</strong>? Esta ação não pode ser desfeita.
                        </p>
                        <div className="flex gap-3 justify-end">
                            <Button variant="outline" size="sm" onClick={() => setDeleteOpen(false)}>Cancelar</Button>
                            <Button variant="danger" size="sm" onClick={() => deleteMut.mutate()} disabled={deleteMut.isPending}>
                                {deleteMut.isPending ? 'Excluindo...' : 'Excluir'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {convertModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setConvertModalOpen(false)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-md mx-4 shadow-elevated w-full" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">
                            Converter em {convertTarget === 'os' ? 'Ordem de Serviço' : 'Chamado Técnico'}
                        </h3>
                        <p className="text-content-secondary text-sm mb-4">
                            O orçamento <strong>{quote.quote_number}</strong> será convertido em {convertTarget === 'os' ? 'uma OS' : 'um Chamado'}.
                        </p>
                        <label className="flex items-center gap-3 p-3 rounded-lg border border-default hover:bg-surface-50 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                checked={convertInstallationTesting}
                                onChange={(e) => setConvertInstallationTesting(e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                            />
                            <div>
                                <span className="text-sm font-medium text-content-primary flex items-center gap-1.5">
                                    <FlaskConical className="h-4 w-4 text-orange-500" /> Instalação para Teste
                                </span>
                                <span className="text-xs text-content-secondary block mt-0.5">
                                    O cliente testará antes de aprovar. O orçamento ficará com status "Instalação p/ Teste".
                                </span>
                            </div>
                        </label>
                        <div className="flex gap-3 justify-end mt-5">
                            <Button variant="outline" size="sm" onClick={() => setConvertModalOpen(false)}>Cancelar</Button>
                            <Button size="sm" onClick={() => convertMut.mutate({ target: convertTarget, is_installation_testing: convertInstallationTesting })} disabled={convertMut.isPending}>
                                {convertMut.isPending ? 'Convertendo...' : 'Confirmar'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {approveModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setApproveModalOpen(false)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-md mx-4 shadow-elevated w-full" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">Aprovar Orçamento</h3>
                        <p className="text-content-secondary text-sm mb-4">Confirme as informações de aprovação do cliente.</p>

                        <div className="space-y-4">
                            <div>
                                <label htmlFor="approval-channel" className="block text-sm font-medium text-content-secondary mb-1">
                                    Canal de Aprovação <span className="text-red-500">*</span>
                                </label>
                                <select
                                    id="approval-channel"
                                    aria-label="Canal de Aprovação"
                                    value={approvalChannel}
                                    onChange={(e) => setApprovalChannel(e.target.value as typeof approvalChannel)}
                                    className="w-full rounded-lg border border-default p-2 text-sm bg-surface-0 focus:ring-2 focus:ring-brand-500"
                                >
                                    <option value="" disabled>Selecione o canal...</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">E-mail</option>
                                    <option value="phone">Ligação/Telefone</option>
                                    <option value="in_person">Presencial</option>
                                    <option value="portal">Portal do Cliente</option>
                                    <option value="integration">Integração Externa</option>
                                    <option value="other">Outro</option>
                                </select>
                            </div>

                            <div>
                                <label className="flex items-start gap-3 p-3 rounded-lg border border-default bg-surface-50 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={termsAccepted}
                                        onChange={(e) => setTermsAccepted(e.target.checked)}
                                        className="mt-1 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                    />
                                    <div className="text-sm">
                                        <span className="font-medium text-content-primary block">Termos Aceitos <span className="text-red-500">*</span></span>
                                        <span className="text-content-secondary">
                                            Confirmo que o cliente deu o aceite e concordou com as condições descritas no orçamento.
                                        </span>
                                    </div>
                                </label>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-content-secondary mb-1">Observações da Aprovação</label>
                                <textarea
                                    value={approvalNotes}
                                    onChange={(e) => setApprovalNotes(e.target.value)}
                                    className="w-full rounded-lg border border-default p-3 text-sm min-h-[80px]"
                                    placeholder="Ex: Cliente pediu urgência na instalação..."
                                />
                            </div>
                        </div>

                        <div className="flex gap-3 justify-end mt-5">
                            <Button variant="outline" size="sm" onClick={() => setApproveModalOpen(false)}>Cancelar</Button>
                            <Button size="sm" variant="success" onClick={() => approveMut.mutate()} disabled={!approvalChannel || !termsAccepted || approveMut.isPending}>
                                {approveMut.isPending ? 'Aprovando...' : 'Confirmar Aprovação'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {revertModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setRevertModalOpen(false)}>
                    <div className="bg-surface-0 rounded-xl p-6 max-w-sm mx-4 shadow-elevated w-full" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-lg font-semibold text-content-primary mb-2">Reverter Renegociação</h3>
                        <p className="text-content-secondary text-sm mb-4">Para onde deseja reverter o orçamento?</p>
                        <div className="space-y-2">
                            <Button variant="outline" className="w-full justify-start" onClick={() => revertRenegotiationMut.mutate('draft')} disabled={revertRenegotiationMut.isPending}>
                                <FileText className="h-4 w-4 mr-2" /> Voltar para Rascunho
                            </Button>
                            <Button variant="outline" className="w-full justify-start" onClick={() => revertRenegotiationMut.mutate('internally_approved')} disabled={revertRenegotiationMut.isPending}>
                                <CheckCircle className="h-4 w-4 mr-2" /> Voltar para Aprovado Internamente
                            </Button>
                        </div>
                        <div className="flex justify-end mt-4">
                            <Button variant="outline" size="sm" onClick={() => setRevertModalOpen(false)}>Cancelar</Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
