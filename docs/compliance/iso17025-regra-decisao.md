# Regra de Decisão — ISO/IEC 17025 §7.8.6

> **Status:** Implementado (2026-04-10)
> **Normas vigentes:**
> - **ISO/IEC 17025:2017** §7.8.6.1 e §7.8.6.2
> - **ILAC G8:09/2019** — Guidelines on Decision Rules and Statements of Conformity
> - **ILAC P14:09/2020** — Policy for Uncertainty in Calibration (substitui P14:01/2013)
> - **JCGM 106:2012** — The role of measurement uncertainty in conformity assessment
> - **Eurachem/CITAC Guide 2ª ed. 2021** — Use of uncertainty information in compliance assessment

## Fundamento normativo

### ISO/IEC 17025:2017 §7.8.6
Quando uma declaração de conformidade a uma especificação é solicitada, o laboratório deve:
- **§7.8.6.1** — documentar a regra de decisão aplicada, considerando o nível de risco (aceitação falsa, rejeição falsa).
- **§7.8.6.2** — relatar a declaração de forma inequívoca, identificando a qual resultado se aplica, qual especificação e o resultado da regra.

### ILAC G8:09/2019 §3
A regra de decisão deve ser **acordada com o cliente antes da execução do serviço**. O certificado deve mencionar: regra, especificação, limite, `k`, nível de confiança, probabilidade de falsa aceitação/rejeição (quando aplicável) e o resultado.

### ILAC P14:09/2020
Incerteza expandida `U = k · u_c` declarada com nível de confiança (geralmente 95,45%, `k=2`). Base para qualquer cálculo de banda de guarda ou risco compartilhado.

## Regras suportadas pelo sistema

O motor `App\Services\Calibration\ConformityAssessmentService` implementa 3 modos. Valores canônicos do enum: `simple`, `guard_band`, `shared_risk`.

### 1. SIMPLE — Aceitação binária conservadora
Convenção brasileira RBC/INMETRO (alinhada a ABNT NBR ISO/IEC 17025 para balanças, Portaria INMETRO 157/2022):

```
accept  se  |err| + U  ≤  EMA
reject  caso contrário
```

**Observação normativa:** JCGM 106:2012 §8.3.1 define "simple acceptance" como `|err| ≤ EMA` (ignora U). A convenção adotada aqui é mais conservadora e funciona como guarda implícita com `w = U`, dentro da tolerância de ILAC G8 §4.3 ("shared risk with explicit uncertainty margin").

### 2. GUARD_BAND — Banda de guarda (ILAC G8 §4.2.2)
Define `w` pelo modo escolhido, combinando com a convenção conservadora:

| Modo | Cálculo de `w` |
|---|---|
| `k_times_u` | `w = k_gb · U` (multiplicador da incerteza expandida) |
| `percent_limit` | `w = (pct / 100) · EMA` |
| `fixed_abs` | `w = valor_absoluto` |

Decisão:
```
accept  se  |err| + U  ≤  EMA − w
reject  se  |err| − U  ≥  EMA + w
warn    caso contrário (zona de guarda / não conclusivo)
```

### 3. SHARED_RISK — Risco compartilhado (JCGM 106:2012 §9, ILAC G8 §4.2.3)
Usa desvio padrão combinado `u_c = U / k` e a função distribuição cumulativa normal Φ:

```
se |err| ≤ EMA:
  z   = (EMA − |err|) / u_c
  P_fa = 1 − Φ(z)                  ← probabilidade de falsa aceitação
  accept se P_fa ≤ β (consumer risk), senão warn

senão:
  z    = (|err| − EMA) / u_c
  P_fr = 1 − Φ(z)                  ← probabilidade de falso rejeite
  reject se P_fr ≤ α (producer risk), senão warn
```

A CDF normal é calculada pela aproximação **Abramowitz & Stegun 26.2.17** (erro absoluto < 7.5e-8), em PHP puro sem dependência de extensão.

## Tabela de exemplos (validados pelos testes unit)

| Entrada | Regra | Esperado |
|---|---|---|
| err=0.3, EMA=1.0, U=0.5 | simple | accept |
| err=0.6, EMA=1.0, U=0.5 | simple | reject |
| err=0.5, EMA=1.0, U=0.5 | simple | accept (boundary) |
| err=0.2, EMA=1.0, U=0.3, k_gb=1 | guard_band | accept (w=0.3) |
| err=0.5, EMA=1.0, U=0.3, k_gb=1 | guard_band | **warn** |
| err=2.0, EMA=1.0, U=0.3, k_gb=1 | guard_band | reject |
| err=0.5, EMA=1.0, U=0.1, pct=10 | guard_band | accept (w=0.1) |
| err=0.3, EMA=1.0, U=0.5, k=2, α=β=0.05 | shared_risk | accept (P_fa≈0.0026) |
| err=1.2, EMA=1.0, U=0.1, k=2, α=β=0.05 | shared_risk | reject (P_fr≈3.17e-5) |
| err=0.85, EMA=1.0, U=0.2, k=2, α=β=0.05 | shared_risk | warn (P_fa≈0.067) |

## Dupla fonte de verdade (acordo x aplicação)

- `work_orders.decision_rule_agreed` — **acordo com o cliente**, definido na análise crítica antes da execução (ILAC G8 §3).
- `equipment_calibrations.decision_rule` — **regra efetivamente aplicada** no cálculo. Copiada da OS no momento da execução; pode ser sobrescrita pelo técnico com justificativa em `decision_notes`.
- **Fonte de verdade do PDF:** `calibration.decision_rule` (fallback para `workOrder.decision_rule_agreed`).

## Persistência do resultado (ISO 17025 §7.8.6.2)

O resultado é gravado em `equipment_calibrations` e auditado em `calibration_decision_logs`:

| Coluna | Significado |
|---|---|
| `decision_result` | `accept` \| `warn` \| `reject` |
| `decision_z_value` | z (shared_risk) |
| `decision_false_accept_prob` | P_fa ou P_fr (shared_risk) |
| `decision_guard_band_applied` | w efetivamente usado (guard_band) |
| `decision_calculated_at/_by` | timestamp + usuário |
| `decision_notes` | observações / justificativa |

A tabela `calibration_decision_logs` guarda cada reavaliação (`inputs`, `outputs`, `engine_version`) para rastreabilidade.

## Arquivos do motor

| Caminho | Papel |
|---|---|
| `backend/app/Services/Calibration/ConformityAssessmentService.php` | motor (3 regras + Φ) |
| `backend/app/Services/Calibration/Decisions/DecisionInput.php` | DTO de entrada (readonly) |
| `backend/app/Services/Calibration/Decisions/DecisionOutput.php` | DTO de saída (readonly) |
| `backend/app/Actions/Calibration/EvaluateCalibrationDecisionAction.php` | Action que orquestra persistência + log |
| `backend/app/Http/Controllers/Api/V1/Calibration/CalibrationDecisionController.php` | endpoint REST |
| `backend/app/Http/Requests/Calibration/EvaluateDecisionRequest.php` | validação da entrada |
| `backend/app/Http/Resources/EquipmentCalibrationResource.php` | serializa bloco `decision` na API |
| `backend/resources/views/pdf/calibration-certificate.blade.php` (seção 6) | impressão ISO 17025 §7.8.6.2 |

## Endpoint

```
POST /api/v1/equipment-calibrations/{calibration}/evaluate-decision
Authorization: Bearer <token>
Permission: calibration.certificate.manage

{
  "rule": "guard_band",
  "coverage_factor_k": 2.0,
  "confidence_level": 95.45,
  "guard_band_mode": "k_times_u",
  "guard_band_value": 1.0,
  "notes": "Avaliado conforme ILAC G8:09/2019 §4.2.2"
}
```

Resposta: `EquipmentCalibrationResource` com o bloco `decision` populado.

## Gate de emissão do certificado

`CalibrationCertificateService::generate()` impede a geração do PDF quando a regra efetiva for `guard_band` ou `shared_risk` e `decision_result === null`. Lança `DomainException` instruindo o usuário a executar "Avaliar Conformidade" primeiro.

## Testes

- **Unit (15 casos):** `backend/tests/Unit/Services/Calibration/ConformityAssessmentServiceTest.php`
- **Feature controller (14 casos):** `backend/tests/Feature/Api/V1/Calibration/CalibrationDecisionControllerTest.php`
- **Feature PDF (4 casos):** `backend/tests/Feature/Pdf/CalibrationCertificateDecisionSectionTest.php`
- **Factory regressão (1 caso):** `backend/tests/Unit/Factories/EquipmentCalibrationFactoryTest.php`

Total: **34 testes dedicados** à regra de decisão, todos verdes em 2026-04-10.
