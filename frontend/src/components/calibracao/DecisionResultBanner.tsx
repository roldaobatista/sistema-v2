import type { DecisionResult } from '@/types/calibration'

interface DecisionResultBannerProps {
  decision: DecisionResult | null
}

/**
 * Semáforo 3 estados conforme ISO 17025 §7.8.6 / ILAC G8:09/2019:
 *  verde  — accept
 *  ambar  — warn (zona de guarda / não conclusivo)
 *  vermelho — reject
 */
export default function DecisionResultBanner({ decision }: DecisionResultBannerProps) {
  if (!decision || !decision.result) {
    return (
      <div className="rounded-lg border border-dashed border-surface-300 bg-surface-50 px-4 py-3 text-sm text-surface-500">
        Regra de decisão ainda não avaliada. Clique em <strong>Avaliar Conformidade</strong>.
      </div>
    )
  }

  const isAccept = decision.result === 'accept'
  const isWarn = decision.result === 'warn'
  const isReject = decision.result === 'reject'

  const color = isAccept
    ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
    : isWarn
      ? 'border-amber-300 bg-amber-50 text-amber-900'
      : 'border-rose-300 bg-rose-50 text-rose-900'

  const label = isAccept
    ? '✓ CONFORME'
    : isWarn
      ? '⚠ ZONA DE GUARDA'
      : '✗ NÃO CONFORME'

  const ruleLabel =
    decision.rule === 'simple'
      ? 'Binária Simples'
      : decision.rule === 'guard_band'
        ? 'Banda de Guarda'
        : decision.rule === 'shared_risk'
          ? 'Risco Compartilhado'
          : '—'

  return (
    <div className={`rounded-lg border-2 px-5 py-4 ${color}`}>
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide opacity-70">
            Regra: {ruleLabel} · k = {decision.coverage_factor_k ?? 2.0} · conf ={' '}
            {decision.confidence_level ?? 95.45}%
          </p>
          <p className="mt-1 text-2xl font-bold">{label}</p>
        </div>
      </div>

      {decision.rule === 'guard_band' && decision.guard_band_applied != null && (
        <p className="mt-2 text-xs opacity-80">
          Banda de guarda aplicada (w): {decision.guard_band_applied.toFixed(6)}
        </p>
      )}

      {decision.rule === 'shared_risk' && decision.z_value != null && (
        <p className="mt-2 text-xs opacity-80">
          z = {decision.z_value.toFixed(4)} ·{' '}
          {decision.false_accept_probability != null && (
            <>P(cauda) = {(decision.false_accept_probability * 100).toFixed(4)}%</>
          )}
        </p>
      )}

      {decision.calculated_at && (
        <p className="mt-2 text-[11px] opacity-60">
          Avaliado em{' '}
          {new Date(decision.calculated_at).toLocaleString('pt-BR')}
          {decision.calculated_by && ` por ${decision.calculated_by.name}`}
        </p>
      )}
    </div>
  )
}
