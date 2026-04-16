import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Shield, AlertTriangle, CheckCircle2, XCircle, Loader2 } from 'lucide-react'
import { certificationApi, type TechnicianCertification } from '@/lib/certification-api'
import { PageHeader } from '@/components/ui/pageheader'
import { cn } from '@/lib/utils'

const statusConfig: Record<string, { icon: typeof Shield; label: string; color: string }> = {
  valid: { icon: CheckCircle2, label: 'Válido', color: 'text-green-600 bg-green-50' },
  expiring_soon: { icon: AlertTriangle, label: 'Vencendo', color: 'text-amber-600 bg-amber-50' },
  expired: { icon: XCircle, label: 'Vencido', color: 'text-red-600 bg-red-50' },
  revoked: { icon: XCircle, label: 'Revogado', color: 'text-slate-600 bg-slate-50' },
}

const TYPE_LABELS: Record<string, string> = {
  cnh: 'CNH',
  nr10: 'NR-10',
  nr11: 'NR-11',
  nr12: 'NR-12',
  nr35: 'NR-35',
  aso: 'ASO',
  treinamento: 'Treinamento',
  certificado: 'Certificado',
}

export default function CertificationsPage() {
  const [statusFilter, setStatusFilter] = useState<string>('')

  const { data: listData, isLoading } = useQuery({
    queryKey: ['certifications', statusFilter],
    queryFn: () => certificationApi.list({ status: statusFilter || undefined, per_page: 50 }),
  })

  const { data: expiringData } = useQuery({
    queryKey: ['certifications-expiring'],
    queryFn: () => certificationApi.expiring(30),
  })

  const certs = listData?.data ?? []
  const expiringCount = expiringData?.length ?? 0

  return (
    <div className="space-y-6">
      <PageHeader title="Certificações e Habilitações" subtitle="Controle de CNH, NRs, ASO e treinamentos" icon={Shield} />

      {expiringCount > 0 && (
        <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          <span><strong>{expiringCount}</strong> certificação(ões) vencendo nos próximos 30 dias.</span>
        </div>
      )}

      <div className="flex gap-2">
        {['', 'valid', 'expiring_soon', 'expired'].map((s) => (
          <button
            key={s}
            type="button"
            className={cn(
              'rounded-md border px-3 py-1.5 text-sm',
              statusFilter === s ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted',
            )}
            onClick={() => setStatusFilter(s)}
            aria-label={`Filtrar por ${s || 'todos'}`}
          >
            {s === '' ? 'Todos' : statusConfig[s]?.label ?? s}
          </button>
        ))}
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : certs.length === 0 ? (
        <div className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
          Nenhuma certificação encontrada.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-muted-foreground">
                <th className="px-3 py-2">Técnico</th>
                <th className="px-3 py-2">Tipo</th>
                <th className="px-3 py-2">Nome</th>
                <th className="px-3 py-2">Emissão</th>
                <th className="px-3 py-2">Vencimento</th>
                <th className="px-3 py-2">Emissor</th>
                <th className="px-3 py-2">Status</th>
              </tr>
            </thead>
            <tbody>
              {certs.map((cert: TechnicianCertification) => {
                const sc = statusConfig[cert.status] ?? statusConfig.valid
                const Icon = sc.icon
                return (
                  <tr key={cert.id} className="border-b hover:bg-muted/50">
                    <td className="px-3 py-2 font-medium">{cert.user?.name ?? '-'}</td>
                    <td className="px-3 py-2">{TYPE_LABELS[cert.type] ?? cert.type}</td>
                    <td className="px-3 py-2">{cert.name}</td>
                    <td className="px-3 py-2">{new Date(cert.issued_at + 'T12:00:00').toLocaleDateString('pt-BR')}</td>
                    <td className="px-3 py-2">{cert.expires_at ? new Date(cert.expires_at + 'T12:00:00').toLocaleDateString('pt-BR') : '—'}</td>
                    <td className="px-3 py-2">{cert.issuer ?? '—'}</td>
                    <td className="px-3 py-2">
                      <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', sc.color)}>
                        <Icon className="h-3 w-3" aria-hidden="true" />
                        {sc.label}
                      </span>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
