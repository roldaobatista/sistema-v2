import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Fingerprint, CheckCircle2, XCircle, Loader2 } from 'lucide-react'
import { biometricConsentApi, type BiometricConsentStatus } from '@/lib/certification-api'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/ui/pageheader'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'

const DATA_TYPES = [
  { key: 'geolocation', label: 'Geolocalização', desc: 'Registro de coordenadas GPS durante deslocamento e ponto' },
  { key: 'facial', label: 'Reconhecimento Facial', desc: 'Selfie para validação de identidade no registro de ponto' },
  { key: 'fingerprint', label: 'Impressão Digital', desc: 'Biometria digital para autenticação' },
  { key: 'voice', label: 'Reconhecimento de Voz', desc: 'Biometria vocal para verificação remota' },
]

export default function BiometricConsentPage() {
  const qc = useQueryClient()
  const { user } = useAuthStore()

  const { data: consents, isLoading } = useQuery({
    queryKey: ['biometric-consents', user?.id],
    queryFn: () => biometricConsentApi.list(user?.id),
    enabled: !!user?.id,
  })

  const grantMut = useMutation({
    mutationFn: (dataType: string) =>
      biometricConsentApi.grant({
        user_id: user!.id,
        data_type: dataType,
        legal_basis: 'consent',
        purpose: `Consentimento para uso de ${dataType} no registro de jornada`,
      }),
    onSuccess: () => {
      toast.success('Consentimento registrado')
      qc.invalidateQueries({ queryKey: ['biometric-consents'] })
    },
    onError: () => toast.error('Erro ao registrar consentimento'),
  })

  const revokeMut = useMutation({
    mutationFn: (dataType: string) => biometricConsentApi.revoke(user!.id, dataType),
    onSuccess: () => {
      toast.success('Consentimento revogado')
      qc.invalidateQueries({ queryKey: ['biometric-consents'] })
    },
    onError: () => toast.error('Erro ao revogar'),
  })

  return (
    <div className="space-y-6">
      <PageHeader
        title="Consentimento Biométrico (LGPD)"
        subtitle="Gerencie seus consentimentos para coleta de dados biométricos"
        icon={Fingerprint}
      />

      <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
        <strong>Sobre seus dados:</strong> Seus dados biométricos são protegidos pela LGPD (Lei 13.709/2018).
        Você pode conceder ou revogar consentimento a qualquer momento. Quando revogado, um método alternativo
        será oferecido para o registro de ponto.
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {DATA_TYPES.map(({ key, label, desc }) => {
            const status: BiometricConsentStatus | undefined = consents?.[key]
            const hasConsent = status?.has_consent ?? false

            return (
              <div key={key} className="rounded-lg border p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="font-semibold">{label}</h3>
                    <p className="text-sm text-muted-foreground">{desc}</p>
                  </div>
                  <div className={cn('flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium',
                    hasConsent ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700',
                  )}>
                    {hasConsent
                      ? <><CheckCircle2 className="h-3 w-3" aria-hidden="true" /> Ativo</>
                      : <><XCircle className="h-3 w-3" aria-hidden="true" /> Inativo</>
                    }
                  </div>
                </div>

                {status?.consent && (
                  <div className="text-xs text-muted-foreground space-y-0.5">
                    <div>Consentido em: {new Date(status.consent.consented_at + 'T12:00:00').toLocaleDateString('pt-BR')}</div>
                    <div>Base legal: {status.consent.legal_basis}</div>
                    {status.consent.alternative_method && (
                      <div>Método alternativo: {status.consent.alternative_method}</div>
                    )}
                  </div>
                )}

                <div>
                  {hasConsent ? (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => revokeMut.mutate(key)}
                      disabled={revokeMut.isPending}
                      aria-label={`Revogar consentimento de ${label}`}
                    >
                      Revogar Consentimento
                    </Button>
                  ) : (
                    <Button
                      size="sm"
                      onClick={() => grantMut.mutate(key)}
                      disabled={grantMut.isPending}
                      aria-label={`Conceder consentimento de ${label}`}
                    >
                      Conceder Consentimento
                    </Button>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
