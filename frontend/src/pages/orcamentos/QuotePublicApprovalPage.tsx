import { useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { CheckCircle2, Download, FileText, LoaderCircle, XCircle } from 'lucide-react'
import api, { getApiErrorMessage, getApiOrigin, unwrapData } from '@/lib/api'
import { formatCurrency } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import type { PublicQuotePayload } from '@/types/quote'

const fmtDate = (value: string | null) => {
    if (!value) {
        return 'Nao informado'
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR')
}

function buildPublicQuoteUrl(magicToken: string, action?: 'approve' | 'reject') {
    const suffix = action ? `/${action}` : ''
    return `${getApiOrigin()}/api/quotes/proposal/${magicToken}${suffix}`
}

export function QuotePublicApprovalPage() {
    const { magicToken = '' } = useParams<{ magicToken: string }>()
    const [acceptedTerms, setAcceptedTerms] = useState(false)
    const [successMessage, setSuccessMessage] = useState<string | null>(null)
    const [rejectedMessage, setRejectedMessage] = useState<string | null>(null)
    const [showRejectModal, setShowRejectModal] = useState(false)
    const [rejectReason, setRejectReason] = useState('')

    const { data, isLoading, isError, error, refetch } = useQuery<PublicQuotePayload>({
        queryKey: ['quote-public-approval', magicToken],
        queryFn: async () => {
            const response = await api.get<{ data: PublicQuotePayload }>(buildPublicQuoteUrl(magicToken))
            return unwrapData(response)
        },
        enabled: !!magicToken,
        retry: false,
    })

    const approveMutation = useMutation({
        mutationFn: async () => {
            const response = await api.post(buildPublicQuoteUrl(magicToken, 'approve'), {
                accept_terms: true,
            })

            return response.data
        },
        onSuccess: (response) => {
            setSuccessMessage(response?.message ?? 'Proposta aprovada com sucesso!')
        },
        onError: () => {
            // Erro tratado na UI via approveMutation.isError
        },
    })

    const rejectMutation = useMutation({
        mutationFn: async (reason: string) => {
            const payload: Record<string, string> = {}
            const normalized = reason?.trim()
            if (normalized) {
                payload.reason = normalized
            }
            const response = await api.post(buildPublicQuoteUrl(magicToken, 'reject'), payload)
            return response.data
        },
        onSuccess: (response) => {
            setRejectedMessage(response?.message ?? 'Proposta rejeitada.')
            setShowRejectModal(false)
            setRejectReason('')
        },
        onError: () => {
            // Erro tratado na UI via rejectMutation.isError
        },
    })

    const errorMessage = useMemo(() => {
        if (!isError) {
            return null
        }

        return getApiErrorMessage(error, 'Nao foi possivel carregar a proposta.')
    }, [error, isError])

    if (isLoading) {
        return (
            <div className="min-h-screen bg-surface-50 flex items-center justify-center p-6">
                <div className="flex items-center gap-3 rounded-2xl border border-surface-200 bg-white px-6 py-5 shadow-sm">
                    <LoaderCircle className="h-5 w-5 animate-spin text-brand-600" />
                    <span className="text-sm text-surface-600">Carregando proposta...</span>
                </div>
            </div>
        )
    }

    if (!data || isError) {
        return (
            <div className="min-h-screen bg-surface-50 flex items-center justify-center p-6">
                <div className="w-full max-w-lg rounded-3xl border border-surface-200 bg-white p-8 shadow-sm text-center space-y-4">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                        <FileText className="h-6 w-6" />
                    </div>
                    <div className="space-y-2">
                        <h1 className="text-xl font-semibold text-surface-900">Proposta indisponivel</h1>
                        <p className="text-sm text-surface-500">{errorMessage}</p>
                    </div>
                    <Button variant="outline" onClick={() => refetch()}>
                        Tentar novamente
                    </Button>
                </div>
            </div>
        )
    }

    return (
        <div className="min-h-screen bg-[linear-gradient(180deg,#f8fafc_0%,#eef6ff_100%)] p-4 sm:p-8">
            <div className="mx-auto max-w-4xl space-y-6">
                <div className="rounded-[28px] border border-surface-200 bg-white/95 p-6 shadow-[0_24px_80px_-32px_rgba(15,23,42,0.35)] backdrop-blur">
                    <div className="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600">
                                {data.company_name || 'Proposta comercial'}
                            </p>
                            <h1 className="text-3xl font-semibold tracking-tight text-surface-950">
                                {data.quote_number || data.reference}
                            </h1>
                            <p className="text-sm text-surface-500">
                                Cliente: <span className="font-medium text-surface-700">{data.customer_name}</span>
                            </p>
                            <p className="text-sm text-surface-500">
                                Validade: <span className="font-medium text-surface-700">{fmtDate(data.valid_until)}</span>
                            </p>
                        </div>
                        <div className="rounded-2xl bg-brand-50 px-5 py-4 text-brand-900">
                            <p className="text-xs uppercase tracking-[0.2em] text-brand-700">Total</p>
                            <p className="mt-1 text-3xl font-semibold">{formatCurrency(data.total)}</p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.5fr,0.9fr]">
                    <section className="space-y-6">
                        {/* Itens da proposta */}
                        <div className="rounded-[28px] border border-surface-200 bg-white p-6 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-lg font-semibold text-surface-900">Itens da proposta</h2>
                                    <p className="text-sm text-surface-500">Resumo do que sera aprovado.</p>
                                </div>
                                {data.pdf_url && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        icon={<Download className="h-4 w-4" />}
                                        onClick={() => window.open(data.pdf_url, '_blank', 'noopener,noreferrer')}
                                    >
                                        PDF
                                    </Button>
                                )}
                            </div>

                            <div className="mt-5 space-y-3">
                                {(data.items || []).length === 0 ? (
                                    <div className="rounded-2xl border border-dashed border-surface-200 px-4 py-8 text-center text-sm text-surface-500">
                                        Nenhum item detalhado foi disponibilizado nesta proposta.
                                    </div>
                                ) : (
                                    (data.items || []).map((item) => (
                                        <div key={item.id} className="rounded-2xl border border-surface-200 px-4 py-4">
                                            <div className="flex items-start justify-between gap-4">
                                                <div>
                                                    <p className="font-medium text-surface-900">{item.description}</p>
                                                    <p className="mt-1 text-sm text-surface-500">
                                                        {item.quantity} x {formatCurrency(item.unit_price)}
                                                    </p>
                                                </div>
                                                <p className="text-sm font-semibold text-surface-900">
                                                    {formatCurrency(item.subtotal)}
                                                </p>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Condicoes de pagamento e condicoes gerais */}
                        {(data.payment_terms || data.general_conditions) && (
                            <div className="rounded-[28px] border border-surface-200 bg-white p-6 shadow-sm space-y-4">
                                {data.payment_terms && (
                                    <div>
                                        <h3 className="text-sm font-semibold text-surface-900">Condicoes de pagamento</h3>
                                        <p className="mt-1 text-sm text-surface-600 whitespace-pre-line">{data.payment_terms}</p>
                                    </div>
                                )}
                                {data.general_conditions && (
                                    <div>
                                        <h3 className="text-sm font-semibold text-surface-900">Condicoes gerais</h3>
                                        <p className="mt-1 text-sm text-surface-600 whitespace-pre-line">{data.general_conditions}</p>
                                    </div>
                                )}
                            </div>
                        )}
                    </section>

                    <aside className="rounded-[28px] border border-surface-200 bg-white p-6 shadow-sm space-y-5">
                        <div className="space-y-2">
                            <h2 className="text-lg font-semibold text-surface-900">Aprovacao</h2>
                            <p className="text-sm text-surface-500">
                                Revise os dados e confirme o aceite para aprovar esta proposta.
                            </p>
                        </div>

                        {successMessage ? (
                            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-5 text-emerald-800">
                                <div className="flex items-center gap-3">
                                    <CheckCircle2 className="h-5 w-5" />
                                    <div>
                                        <p className="font-medium">Aprovacao concluida</p>
                                        <p className="text-sm">{successMessage}</p>
                                    </div>
                                </div>
                            </div>
                        ) : rejectedMessage ? (
                            <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-5 text-red-800">
                                <div className="flex items-center gap-3">
                                    <XCircle className="h-5 w-5" />
                                    <div>
                                        <p className="font-medium">Proposta rejeitada</p>
                                        <p className="text-sm">{rejectedMessage}</p>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <>
                                <label className="flex items-start gap-3 rounded-2xl border border-surface-200 px-4 py-4">
                                    <Checkbox
                                        checked={acceptedTerms}
                                        onCheckedChange={(checked) => setAcceptedTerms(checked === true)}
                                        aria-label="Aceitar termos da proposta"
                                        className="mt-0.5"
                                    />
                                    <span className="text-sm text-surface-600">
                                        Confirmo que li os detalhes da proposta e concordo com a aprovacao deste orcamento.
                                    </span>
                                </label>

                                <div className="flex gap-3">
                                    <Button
                                        className="flex-1"
                                        disabled={!acceptedTerms || approveMutation.isPending}
                                        onClick={() => approveMutation.mutate()}
                                    >
                                        {approveMutation.isPending ? 'Aprovando...' : 'Aprovar proposta'}
                                    </Button>

                                    <Button
                                        variant="outline"
                                        className="flex-1 border-red-200 text-red-700 hover:bg-red-50"
                                        disabled={rejectMutation.isPending}
                                        onClick={() => setShowRejectModal(true)}
                                    >
                                        Rejeitar
                                    </Button>
                                </div>

                                {approveMutation.isError && (
                                    <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                        {getApiErrorMessage(approveMutation.error, 'Nao foi possivel aprovar a proposta.')}
                                    </div>
                                )}

                                {rejectMutation.isError && (
                                    <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                        {getApiErrorMessage(rejectMutation.error, 'Nao foi possivel rejeitar a proposta.')}
                                    </div>
                                )}
                            </>
                        )}
                    </aside>
                </div>
            </div>

            {/* Modal de rejeicao */}
            {showRejectModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl space-y-4">
                        <h3 className="text-lg font-semibold text-surface-900">Rejeitar proposta</h3>
                        <p className="text-sm text-surface-500">
                            Informe o motivo da rejeicao (opcional).
                        </p>
                        <textarea
                            className="w-full rounded-xl border border-surface-200 p-3 text-sm text-surface-800 placeholder:text-surface-400 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400"
                            rows={4}
                            placeholder="Motivo da rejeicao..."
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                        />
                        <div className="flex justify-end gap-3">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setShowRejectModal(false)
                                    setRejectReason('')
                                }}
                                disabled={rejectMutation.isPending}
                            >
                                Cancelar
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={rejectMutation.isPending}
                                onClick={() => rejectMutation.mutate(rejectReason)}
                            >
                                {rejectMutation.isPending ? 'Rejeitando...' : 'Confirmar rejeicao'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
