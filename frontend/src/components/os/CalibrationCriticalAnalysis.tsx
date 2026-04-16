import { Controller, type Control, type UseFormWatch } from 'react-hook-form'
import { Switch } from '@/components/ui/switch'

type CalibrationCriticalAnalysisForm = {
  service_modality?: string | null
  client_wants_conformity_declaration?: boolean | null
  decision_rule_agreed?: string | null
  decision_guard_band_mode?: string | null
  decision_guard_band_value?: number | null
  decision_producer_risk_alpha?: number | null
  decision_consumer_risk_beta?: number | null
  requires_adjustment?: boolean | null
  requires_maintenance?: boolean | null
  subject_to_legal_metrology?: boolean | null
  needs_ipem_interaction?: boolean | null
  will_emit_complementary_report?: boolean | null
  applicable_procedure?: string | null
  client_accepted_by?: string | null
  site_conditions?: string | null
  calibration_scope_notes?: string | null
}

interface CalibrationCriticalAnalysisProps {

  control: Control<CalibrationCriticalAnalysisForm>

  watch: UseFormWatch<CalibrationCriticalAnalysisForm>
  serviceType?: string | null
}

const selectClass =
  'w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15'

const textareaClass =
  'w-full rounded-lg border border-default bg-surface-50 px-3.5 py-2.5 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15'

export default function CalibrationCriticalAnalysis({
  control,
  watch,
  serviceType,
}: CalibrationCriticalAnalysisProps) {
  const isCalibration = serviceType === 'calibracao'
  const wantsConformity = watch('client_wants_conformity_declaration')
  const subjectToLegalMetrology = watch('subject_to_legal_metrology')
  const decisionRuleAgreed = watch('decision_rule_agreed')

  if (!isCalibration) return null

  return (
    <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card space-y-4">
      <h2 className="text-sm font-semibold text-surface-900">
        Análise Crítica (ISO 17025)
      </h2>

      <div className="grid gap-4 sm:grid-cols-2">
        {/* Modalidade de serviço */}
        <div>
          <label className="mb-1.5 block text-sm font-medium text-surface-700">
            Modalidade de Serviço
          </label>
          <Controller
            name="service_modality"
            control={control}
            render={({ field }) => (
              <select
                title="Modalidade de Serviço"
                value={field.value ?? ''}
                onChange={field.onChange}
                className={selectClass}
              >
                <option value="">Selecionar modalidade</option>
                <option value="calibration">Calibração</option>
                <option value="inspection">Inspeção</option>
                <option value="maintenance">Manutenção</option>
                <option value="adjustment">Ajuste</option>
                <option value="diagnostic">Diagnóstico</option>
              </select>
            )}
          />
        </div>

        {/* Regra de decisão — só visível quando client_wants_conformity_declaration */}
        {wantsConformity && (
          <div>
            <label className="mb-1.5 block text-sm font-medium text-surface-700">
              Regra de Decisão Acordada
            </label>
            <Controller
              name="decision_rule_agreed"
              control={control}
              render={({ field }) => (
                <select
                  title="Regra de Decisão"
                  value={field.value ?? ''}
                  onChange={field.onChange}
                  className={selectClass}
                >
                  <option value="">Selecionar regra</option>
                  <option value="simple">Simples (sem guarda)</option>
                  <option value="guard_band">Banda de Guarda</option>
                  <option value="shared_risk">Risco Compartilhado</option>
                </select>
              )}
            />
            <p className="mt-1 text-xs text-surface-400">
              ILAC G8:09/2019 §4.2 — acordado com o cliente antes da execução
            </p>
          </div>
        )}
      </div>

      {/* Parâmetros condicionais — Banda de Guarda */}
      {wantsConformity && decisionRuleAgreed === 'guard_band' && (
        <div className="rounded-lg border border-brand-200 bg-brand-50/40 p-4 space-y-3">
          <p className="text-xs font-semibold uppercase tracking-wide text-brand-700">
            Parâmetros da Banda de Guarda (ILAC G8 §4.2.2)
          </p>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-xs font-medium text-surface-700">
                Modo
              </label>
              <Controller
                name="decision_guard_band_mode"
                control={control}
                render={({ field }) => (
                  <select
                    title="Modo da banda de guarda"
                    value={field.value ?? ''}
                    onChange={field.onChange}
                    className={selectClass}
                  >
                    <option value="">Selecionar modo</option>
                    <option value="k_times_u">k × U (múltiplo da incerteza)</option>
                    <option value="percent_limit">% do limite (EMA)</option>
                    <option value="fixed_abs">Valor absoluto fixo</option>
                  </select>
                )}
              />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-surface-700">
                Valor
              </label>
              <Controller
                name="decision_guard_band_value"
                control={control}
                render={({ field }) => (
                  <input
                    type="number"
                    step="0.0001"
                    min="0"
                    value={field.value ?? ''}
                    onChange={(e) =>
                      field.onChange(e.target.value === '' ? null : Number(e.target.value))
                    }
                    placeholder="Ex: 1.0"
                    className={selectClass}
                  />
                )}
              />
            </div>
          </div>
        </div>
      )}

      {/* Parâmetros condicionais — Risco Compartilhado */}
      {wantsConformity && decisionRuleAgreed === 'shared_risk' && (
        <div className="rounded-lg border border-amber-200 bg-amber-50/40 p-4 space-y-3">
          <p className="text-xs font-semibold uppercase tracking-wide text-amber-700">
            Riscos Acordados (ILAC G8 §4.2.3 / JCGM 106 §9)
          </p>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-xs font-medium text-surface-700">
                Risco do Produtor α (0.0001–0.5)
              </label>
              <Controller
                name="decision_producer_risk_alpha"
                control={control}
                render={({ field }) => (
                  <input
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    max="0.5"
                    value={field.value ?? ''}
                    onChange={(e) =>
                      field.onChange(e.target.value === '' ? null : Number(e.target.value))
                    }
                    placeholder="0.05"
                    className={selectClass}
                  />
                )}
              />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-surface-700">
                Risco do Consumidor β (0.0001–0.5)
              </label>
              <Controller
                name="decision_consumer_risk_beta"
                control={control}
                render={({ field }) => (
                  <input
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    max="0.5"
                    value={field.value ?? ''}
                    onChange={(e) =>
                      field.onChange(e.target.value === '' ? null : Number(e.target.value))
                    }
                    placeholder="0.05"
                    className={selectClass}
                  />
                )}
              />
            </div>
          </div>
        </div>
      )}

      {/* Toggles */}
      <div className="space-y-3">
        <Controller
          name="requires_adjustment"
          control={control}
          render={({ field }) => (
            <label className="flex items-center justify-between gap-3 rounded-lg border border-default bg-surface-50 px-4 py-3">
              <div>
                <span className="text-sm font-medium text-surface-700">
                  Requer Ajuste
                </span>
                <p className="text-xs text-surface-400">
                  O instrumento precisa de ajuste antes ou após a calibração
                </p>
              </div>
              <Switch
                checked={!!field.value}
                onCheckedChange={field.onChange}
              />
            </label>
          )}
        />

        <Controller
          name="requires_maintenance"
          control={control}
          render={({ field }) => (
            <label className="flex items-center justify-between gap-3 rounded-lg border border-default bg-surface-50 px-4 py-3">
              <div>
                <span className="text-sm font-medium text-surface-700">
                  Requer Manutenção
                </span>
                <p className="text-xs text-surface-400">
                  O instrumento precisa de manutenção antes da calibração
                </p>
              </div>
              <Switch
                checked={!!field.value}
                onCheckedChange={field.onChange}
              />
            </label>
          )}
        />

        <Controller
          name="client_wants_conformity_declaration"
          control={control}
          render={({ field }) => (
            <label className="flex items-center justify-between gap-3 rounded-lg border border-default bg-surface-50 px-4 py-3">
              <div>
                <span className="text-sm font-medium text-surface-700">
                  Declaração de Conformidade
                </span>
                <p className="text-xs text-surface-400">
                  Cliente solicita declaração de conformidade no certificado
                </p>
              </div>
              <Switch
                checked={!!field.value}
                onCheckedChange={field.onChange}
              />
            </label>
          )}
        />

        <Controller
          name="subject_to_legal_metrology"
          control={control}
          render={({ field }) => (
            <label className="flex items-center justify-between gap-3 rounded-lg border border-default bg-surface-50 px-4 py-3">
              <div>
                <span className="text-sm font-medium text-surface-700">
                  Sujeito à Metrologia Legal
                </span>
                <p className="text-xs text-surface-400">
                  Instrumento regulado por Inmetro / metrologia legal
                </p>
              </div>
              <Switch
                checked={!!field.value}
                onCheckedChange={field.onChange}
              />
            </label>
          )}
        />

        {subjectToLegalMetrology && (
          <Controller
            name="needs_ipem_interaction"
            control={control}
            render={({ field }) => (
              <label className="flex items-center justify-between gap-3 rounded-lg border border-default bg-surface-50 px-4 py-3 ml-4">
                <div>
                  <span className="text-sm font-medium text-surface-700">
                    Necessita Interação com IPEM
                  </span>
                  <p className="text-xs text-surface-400">
                    Requer verificação ou acompanhamento do IPEM
                  </p>
                </div>
                <Switch
                  checked={!!field.value}
                  onCheckedChange={field.onChange}
                />
              </label>
            )}
          />
        )}

        <Controller
          name="will_emit_complementary_report"
          control={control}
          render={({ field }) => (
            <label className="flex items-center justify-between gap-3 rounded-lg border border-default bg-surface-50 px-4 py-3">
              <div>
                <span className="text-sm font-medium text-surface-700">
                  Emitir Relatório Complementar
                </span>
                <p className="text-xs text-surface-400">
                  Além do certificado, será emitido relatório complementar
                </p>
              </div>
              <Switch
                checked={!!field.value}
                onCheckedChange={field.onChange}
              />
            </label>
          )}
        />
      </div>

      {/* Procedimento técnico e aceite do cliente */}
      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1.5 block text-sm font-medium text-surface-700">
            Procedimento Técnico Aplicável
          </label>
          <Controller
            name="applicable_procedure"
            control={control}
            render={({ field }) => (
              <input
                type="text"
                value={field.value ?? ''}
                onChange={field.onChange}
                placeholder="Ex: POP-CAL-001, NBR ISO/IEC 17025..."
                className={selectClass}
              />
            )}
          />
        </div>
        <div>
          <label className="mb-1.5 block text-sm font-medium text-surface-700">
            Aceite do Cliente (nome/cargo)
          </label>
          <Controller
            name="client_accepted_by"
            control={control}
            render={({ field }) => (
              <input
                type="text"
                value={field.value ?? ''}
                onChange={field.onChange}
                placeholder="Nome e cargo de quem aceitou pelo cliente"
                className={selectClass}
              />
            )}
          />
        </div>
      </div>

      {/* Textareas */}
      <div className="space-y-4">
        <div>
          <label className="mb-1.5 block text-sm font-medium text-surface-700">
            Condições do Local
          </label>
          <Controller
            name="site_conditions"
            control={control}
            render={({ field }) => (
              <textarea
                aria-label="Condições do Local"
                value={field.value ?? ''}
                onChange={field.onChange}
                rows={2}
                placeholder="Descreva as condições ambientais do local de calibração (temperatura, umidade, vibrações, etc.)"
                className={textareaClass}
              />
            )}
          />
        </div>

        <div>
          <label className="mb-1.5 block text-sm font-medium text-surface-700">
            Observações do Escopo de Calibração
          </label>
          <Controller
            name="calibration_scope_notes"
            control={control}
            render={({ field }) => (
              <textarea
                aria-label="Observações do Escopo de Calibração"
                value={field.value ?? ''}
                onChange={field.onChange}
                rows={2}
                placeholder="Observações sobre faixas, pontos de calibração, restrições, etc."
                className={textareaClass}
              />
            )}
          />
        </div>
      </div>
    </div>
  )
}
