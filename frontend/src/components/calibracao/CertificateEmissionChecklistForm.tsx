import { useState, useEffect, useCallback } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { certificateChecklistApi } from '@/lib/certificate-checklist-api'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { toast } from 'sonner'
import { getApiErrorMessage } from '@/lib/api'
import { CheckCircle, AlertTriangle, ClipboardCheck, Loader2 } from 'lucide-react'
import type { CertificateEmissionChecklist } from '@/types/calibration'

// ─── Checklist item definitions ────────────────────────────────────
const CHECKLIST_ITEMS: { key: keyof Pick<CertificateEmissionChecklist,
  'equipment_identified' | 'scope_defined' | 'critical_analysis_done' |
  'procedure_defined' | 'standards_traceable' | 'raw_data_recorded' |
  'uncertainty_calculated' | 'adjustment_documented' | 'no_undue_interval' |
  'conformity_declaration_valid' | 'accreditation_mark_correct'
>; label: string }[] = [
  { key: 'equipment_identified', label: 'Equipamento identificado sem ambiguidade (marca, modelo, nº série, capacidade, divisão)' },
  { key: 'scope_defined', label: 'OS define claramente o escopo do serviço' },
  { key: 'critical_analysis_done', label: 'Análise crítica do pedido/contrato realizada' },
  { key: 'procedure_defined', label: 'Procedimento técnico aplicável definido' },
  { key: 'standards_traceable', label: 'Padrões utilizados possuem rastreabilidade documentada' },
  { key: 'raw_data_recorded', label: 'Dados brutos registrados e arquivados' },
  { key: 'uncertainty_calculated', label: 'Incerteza de medição determinada e lançada' },
  { key: 'adjustment_documented', label: 'Se houve ajuste/manutenção, está documentado' },
  { key: 'no_undue_interval', label: 'Sem validade ou recomendação de intervalo indevida' },
  { key: 'conformity_declaration_valid', label: 'Se há declaração de conformidade: regra de decisão definida antes + resultados + incerteza presentes' },
  { key: 'accreditation_mark_correct', label: 'Uso de marca/símbolo de acreditação correto (ou ausente, se não acreditado)' },
]

type BooleanKeys = typeof CHECKLIST_ITEMS[number]['key']

interface Props {
  calibrationId: number
  onApproved?: () => void
}

export default function CertificateEmissionChecklistForm({ calibrationId, onApproved }: Props) {
  const qc = useQueryClient()

  const [checks, setChecks] = useState<Record<BooleanKeys, boolean>>(() => {
    const initial: Record<string, boolean> = {}
    for (const item of CHECKLIST_ITEMS) initial[item.key] = false
    return initial as Record<BooleanKeys, boolean>
  })
  const [observations, setObservations] = useState('')
  const [isApproved, setIsApproved] = useState(false)

  // ─── Fetch existing checklist ──────────────────────────
  const { data: existing, isLoading } = useQuery({
    queryKey: ['certificate-checklist', calibrationId],
    queryFn: () => certificateChecklistApi.show(calibrationId),
    enabled: !!calibrationId && calibrationId > 0,
    retry: false,
  })

  // ─── Populate from server data ─────────────────────────
  useEffect(() => {
    if (!existing) return
    const updated: Record<string, boolean> = {}
    for (const item of CHECKLIST_ITEMS) {
      updated[item.key] = !!existing[item.key]
    }
    setChecks(updated as Record<BooleanKeys, boolean>)
    setObservations(existing.observations ?? '')
    setIsApproved(!!existing.approved)
  }, [existing])

  // ─── Mutation ──────────────────────────────────────────
  const saveMutation = useMutation({
    mutationFn: (approved: boolean) =>
      certificateChecklistApi.storeOrUpdate({
        equipment_calibration_id: calibrationId,
        ...checks,
        observations: observations || null,
        approved,
      } as Partial<CertificateEmissionChecklist> & { equipment_calibration_id: number }),
    onSuccess: (data) => {
      setIsApproved(!!data.approved)
      qc.invalidateQueries({ queryKey: ['certificate-checklist', calibrationId] })
      if (data.approved) {
        toast.success('Checklist verificado e aprovado!')
        onApproved?.()
      } else {
        toast.success('Checklist salvo')
      }
    },
    onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar checklist')),
  })

  // ─── Derived state ────────────────────────────────────
  const checkedCount = Object.values(checks).filter(Boolean).length
  const allChecked = checkedCount === CHECKLIST_ITEMS.length

  const toggleCheck = useCallback((key: BooleanKeys) => {
    setChecks((prev) => ({ ...prev, [key]: !prev[key] }))
    setIsApproved(false) // reset approval when user changes checks
  }, [])

  const handleApprove = () => {
    if (!allChecked) {
      toast.warning('Todos os itens devem estar marcados para aprovar')
      return
    }
    saveMutation.mutate(true)
  }

  const handleSave = () => {
    saveMutation.mutate(false)
  }

  // ─── Render ────────────────────────────────────────────
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-32 gap-2">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        <span className="text-sm text-muted-foreground">Carregando checklist...</span>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Status badge */}
      <div className="flex items-center gap-3">
        <ClipboardCheck className="h-5 w-5 text-muted-foreground" />
        <span className="text-sm font-medium">Verificação pré-emissão do certificado</span>
        {isApproved ? (
          <Badge className="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 gap-1">
            <CheckCircle className="h-3 w-3" /> Aprovado
          </Badge>
        ) : (
          <Badge variant="outline" className="text-amber-600 border-amber-300 dark:text-amber-400 dark:border-amber-700 gap-1">
            <AlertTriangle className="h-3 w-3" /> Pendente ({checkedCount}/{CHECKLIST_ITEMS.length})
          </Badge>
        )}
      </div>

      {/* Checklist items */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-base">Itens de Verificação</CardTitle>
          <CardDescription>
            Verifique cada item antes de emitir o certificado. Todos devem estar conformes.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {CHECKLIST_ITEMS.map((item, index) => (
            <div
              key={item.key}
              className={`flex items-start gap-3 p-3 rounded-lg border transition-colors ${
                checks[item.key]
                  ? 'bg-green-50 border-green-200 dark:bg-green-950/20 dark:border-green-800'
                  : 'bg-background border-border hover:bg-muted/50'
              }`}
            >
              <Checkbox
                id={`check-${item.key}`}
                checked={checks[item.key]}
                onCheckedChange={() => toggleCheck(item.key)}
                className="mt-0.5"
              />
              <Label
                htmlFor={`check-${item.key}`}
                className="text-sm leading-relaxed cursor-pointer flex-1"
              >
                <span className="text-muted-foreground mr-1.5">{index + 1}.</span>
                {item.label}
              </Label>
            </div>
          ))}
        </CardContent>
      </Card>

      {/* Observations */}
      <div className="space-y-2">
        <Label htmlFor="checklist-observations">Observações (opcional)</Label>
        <Textarea
          id="checklist-observations"
          value={observations}
          onChange={(e) => setObservations(e.target.value)}
          placeholder="Registre observações ou ressalvas sobre a verificação..."
          rows={3}
        />
      </div>

      {/* Verifier info */}
      {existing?.verifier && existing.verified_at && (
        <div className="text-xs text-muted-foreground px-1">
          Verificado por <strong>{existing.verifier.name}</strong> em{' '}
          {new Date(existing.verified_at).toLocaleString('pt-BR')}
        </div>
      )}

      {/* Actions */}
      <div className="flex flex-wrap gap-3 justify-end">
        <Button
          variant="outline"
          size="sm"
          onClick={handleSave}
          disabled={saveMutation.isPending}
        >
          Salvar Rascunho
        </Button>
        <Button
          onClick={handleApprove}
          disabled={saveMutation.isPending || !allChecked}
          className="gap-1.5"
        >
          {saveMutation.isPending ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <CheckCircle className="h-4 w-4" />
          )}
          Verificar e Aprovar
        </Button>
      </div>
    </div>
  )
}
