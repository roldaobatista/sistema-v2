# Plano: Regras de Decisão de Conformidade — ISO/IEC 17025 §7.8.6 + ILAC G8/P14

**Data:** 2026-04-10
**Base:** Auditoria direta do código-fonte (não dos planos anteriores).
**Escopo:** Implementar os 3 modos de decisão normativa (SIMPLE / GUARD_BAND / SHARED_RISK) com cálculo real, persistência, UI e impressão no certificado.
**Norma aplicável:** ISO/IEC 17025:2017 §7.8.6, ILAC G8:09/2019, ILAC P14:01/2013 (incerteza).
**Runner:** host Windows (sem Sail)
**Branch:** `main` (commits atômicos por etapa)

---

## 0. Fonte normativa (o que a norma exige)

| Norma | Cláusula | Exigência |
|---|---|---|
| ISO 17025 | §7.8.6.1 | Quando declarar conformidade a especificação/limite, documentar **regra de decisão** aplicada considerando nível de risco (ex.: aceitação falsa, rejeição falsa). |
| ISO 17025 | §7.8.6.2 | O laboratório deve **relatar** a declaração de conformidade de forma inequívoca, identificando a qual resultado se aplica, qual especificação, e o **resultado da regra**. |
| ILAC G8 | §3, §4 | Regra deve ser **acordada com o cliente** antes do serviço. Certificado deve mencionar: regra, especificação, limite, `k`, nível de confiança, **probabilidade de falsa aceitação** (shared-risk), e o resultado. |
| ILAC P14 | §5 | Incerteza de medição declarada com `k`, nível de confiança e `U` expandida. Base para qualquer cálculo de guard-band. |

**Regras de decisão suportadas obrigatoriamente:**
1. **SIMPLE (Binary Statement with Simple Acceptance)** — aceita se `|erro| + U ≤ EMA`.
2. **GUARD_BAND (Binary with Guard Band)** — define `w = k·U` (ou % do limite, ou valor absoluto). 3 estados: ACCEPT (`|erro| + w ≤ EMA`), WARN (`EMA − w < |erro| + U ≤ EMA + w`), REJECT (restante).
3. **SHARED_RISK** — cliente aceita risco. Calcula `z = (|erro| − EMA)/U`, obtém `P_fa` pela CDF normal, aceita se `P_fa ≤ α` (produtor) e `P_fr ≤ β` (consumidor).

---

## 1. Auditoria do código real (verificado com grep/sed)

### 1.1 Banco de dados — o que já existe

| Tabela | Coluna | Tipo | Origem | Uso atual |
|---|---|---|---|---|
| `equipment_calibrations` | `decision_rule` | varchar(30) default 'simple' | migration `2026_02_19_100000` | comment "simple\|guard_band\|shared_risk" |
| `equipment_calibrations` | `uncertainty_budget` | JSON | migration `2026_02_19_100000` | orçamento 6-componentes (NIT-DICLA-021) |
| `equipment_calibrations` | `conformity_declaration` | text | preexistente | texto livre impresso no PDF |
| `equipment_calibrations` | `max_permissible_error` | decimal(12,4) | preexistente | EMA do cálculo |
| `equipment_calibrations` | `max_error_found` | decimal(12,4) | preexistente | maior erro nas leituras |
| `equipment_calibrations` | `gravity_acceleration`, `laboratory_address`, `scope_declaration` | — | `2026_02_19_100000` | cabeçalho do certificado |
| `equipment_calibrations` | `condition_as_found`, `condition_as_left`, `adjustment_performed`, `accreditation_scope_id` | — | `2026_04_10_100001` | normativo |
| `calibration_readings` | `ema`, `conforms`, `expanded_uncertainty` | — | `2026_02_19_100000` + preexistente | EMA por ponto + U expandida por ponto |
| `work_orders` | `client_wants_conformity_declaration` | boolean | `2026_04_09_210000` | gatilho da análise crítica |
| `work_orders` | `decision_rule_agreed` | varchar(30) | `2026_04_09_210000` | regra acordada no momento da OS |
| `work_orders` | `subject_to_legal_metrology` | boolean | `2026_04_09_210000` | flag Portaria 157 |

**⚠️ Duplicação confirmada:** o campo `decision_rule` existe em `equipment_calibrations` **e** em `work_orders.decision_rule_agreed`. O PDF usa o da WO (linha 310), o wizard escreve no da calibração (`CalibrationWizardPage.tsx:376`). O form de criação da OS escreve no da WO (`WorkOrderCreatePage.tsx:407`). **Fonte divergente** — este plano consolida (Fase 1).

### 1.2 Backend — services/controllers

| Arquivo | Linha | Estado |
|---|---|---|
| `backend/app/Services/Calibration/EmaCalculator.php` | 154 | `isConforming($error, $ema)` — compara `|erro| ≤ EMA` apenas (SIMPLE parcial; não considera U). |
| `backend/app/Services/Calibration/CalibrationWizardService.php` | 150-175 | `calculateExpandedUncertainty()` — calcula `U = k · u_combined` corretamente, com `u_A`, `u_B_resolution`, `u_weight`. **Não avalia regra**. |
| `backend/app/Services/Calibration/CalibrationWizardService.php` | 199-203 | Verifica apenas se existe `conformity_declaration` (texto). Não é avaliação normativa. |
| `backend/app/Http/Resources/WorkOrderResource.php` | 203 | Serializa `decision_rule_agreed`. Não serializa resultado de decisão. |
| `backend/app/Http/Requests/WorkOrder/StoreWorkOrderRequest.php` | 164 | `'decision_rule_agreed' => 'nullable\|string\|max:30'` — **não valida enum**, aceita qualquer string. |
| `backend/app/Http/Requests/WorkOrder/UpdateWorkOrderRequest.php` | 137 | Mesmo bug. |
| `backend/app/Http/Requests/Features/UpdateCalibrationWizardRequest.php` | 36 | `'decision_rule' => 'nullable\|string\|max:500'` — **max errado** (tabela é varchar(30)). |
| `backend/database/factories/EquipmentCalibrationFactory.php` | 80 | **Bug**: `'decision_rule' => 'simple_acceptance'` — valor não existe no enum. Deveria ser `'simple'`. |

**Inexistente (verificado com grep em `backend/app/`):**
- `ConformityAssessmentService` (não existe).
- `DecisionRule`, `ConformityDecision`, `ClientAgreementDecisionRule` models (não existem).
- Tabela `calibration_decision_logs` (não existe).
- Nenhum endpoint `apply-decision-rule` (não existe).

### 1.3 Frontend — componentes

| Arquivo | Linha | Estado |
|---|---|---|
| `frontend/src/types/work-order.ts` | 69 | `export type DecisionRuleAgreed = 'simple' \| 'guard_band' \| 'shared_risk'` — enum existe. |
| `frontend/src/types/calibration.ts` | 200 | `decisionRule: 'simple' \| 'guard_band' \| 'shared_risk'` — tipo wizard. |
| `frontend/src/components/os/CalibrationCriticalAnalysis.tsx` | 69-83 | `<select>` com 3 opções. **Sem inputs de config** (guard_band_mode, k, valor, producer/consumer risk). |
| `frontend/src/pages/calibracao/CalibrationWizardPage.tsx` | 687-688 | Segundo `<select>` (dentro do wizard) — **duplicação visual** do campo já existente na OS. |
| `frontend/src/pages/os/WorkOrderCreatePage.tsx` | 407 | Envia `decision_rule_agreed` condicional a `client_wants_conformity_declaration`. |

### 1.4 PDF — template Blade

| Linha | Conteúdo |
|---|---|
| 268 | Tabela de leituras já imprime `expanded_uncertainty` por ponto. |
| 305-352 | Seção 6 "Declaração de Conformidade". |
| 310 | Lê `workOrder->decision_rule_agreed` (e não `calibration->decision_rule`). |
| 311-315 | Labels dos 3 modos (em PT-BR). |
| 323-324 | Imprime `conformity_declaration` (texto livre). |
| 347 | Semáforo **binário**: `✓ CONFORME` / `✗ NÃO CONFORME`. **Sem estado WARN.** |

**PDF não imprime**: `k`, nível de confiança, `U` no bloco de decisão, guard_band aplicado, z-value, P_fa, identificação do acordo.

### 1.5 Testes

- Grep por `decision_rule\|guard_band\|shared_risk\|ConformityAssessment` em `backend/tests/` retornou **zero** arquivos. Nenhum teste cobre as 3 regras.

---

## 2. Problemas consolidados

| # | Problema | Gravidade | Origem |
|---|---|---|---|
| P1 | Duplicação `equipment_calibrations.decision_rule` vs `work_orders.decision_rule_agreed` — fontes divergem entre PDF/wizard/form | **Crítico** | arquitetural |
| P2 | `ConformityAssessmentService` não existe — não há código que calcule guard_band nem shared_risk | **Crítico** | faltante |
| P3 | `EmaCalculator::isConforming()` ignora `U` — viola ILAC G8 §3 mesmo no modo SIMPLE (`|erro| + U ≤ EMA` é o correto, não `|erro| ≤ EMA`) | **Crítico** | bug normativo |
| P4 | Sem colunas para guardar **resultado** da decisão (ACCEPT/WARN/REJECT), `z`, `P_fa`, parâmetros aplicados | **Crítico** | faltante |
| P5 | Sem colunas para **parâmetros** de guard_band (`guard_band_mode`, `guard_band_value`) e shared_risk (`producer_risk_alpha`, `consumer_risk_beta`) | **Crítico** | faltante |
| P6 | FormRequests aceitam qualquer string em `decision_rule_agreed` (`max:30` só) — não valida enum | Alto | bug |
| P7 | `UpdateCalibrationWizardRequest:36` valida `max:500` num campo varchar(30) — quebraria INSERT | Alto | bug |
| P8 | `EquipmentCalibrationFactory:80` usa `'simple_acceptance'` — valor inválido quebra qualquer teste futuro que use o enum | Médio | bug |
| P9 | Frontend não coleta parâmetros de guard_band/shared_risk (só escolhe modo) | **Crítico** | faltante |
| P10 | PDF não imprime `k`, confiança, `U` e 3º estado WARN — descumpre ILAC G8 §4 | **Crítico** | faltante |
| P11 | Nenhum teste cobrindo regras de decisão | Alto | faltante |
| P12 | Nenhum log de auditoria do cálculo de decisão (quem calculou, quando, com quais parâmetros) | Médio | faltante |

---

## 3. Decisões arquiteturais (tomar antes de codar)

### 3.1 Consolidação do campo duplicado (P1)

**Decisão:** manter **os dois campos com propósitos distintos**, mas explicitar o papel:

- `work_orders.decision_rule_agreed` = **acordo com o cliente** (entra na análise crítica, antes da execução). Imutável após aprovação da OS.
- `equipment_calibrations.decision_rule` = **regra efetivamente aplicada** no cálculo (copia da WO no momento da execução; pode ser sobrescrita pelo técnico com justificativa).

**Justificativa:** ILAC G8 §3 exige o acordo prévio (campo da WO) e ISO 17025 §7.8.6.2 exige registro do que foi usado (campo da calibração). Separar evita perda de rastro se o técnico precisar ajustar.

**Fonte de verdade para o PDF:** `calibration->decision_rule` (o que foi aplicado). Atualizar linha 310 do blade para usar este em vez do `workOrder->decision_rule_agreed`, com fallback.

### 3.2 Acordo global por cliente (opcional — fora do escopo desta iteração)

O subagente inicial sugeriu tabela `client_agreement_decision_rules` global por cliente. **Não será criada nesta fase.** Motivo: o acordo por OS (`work_orders.decision_rule_agreed`) já satisfaz ILAC G8 §3. Um acordo-padrão por cliente é conveniência, não requisito normativo. Fica como melhoria futura.

### 3.3 Nomenclatura do enum

**Valores canônicos** (únicos permitidos em todo o sistema):
```
simple | guard_band | shared_risk
```

Banir em todo lugar: `simple_acceptance`, `simples`, `guardband`, etc.

---

## 4. Plano em fases

> **Regra:** cada fase só inicia quando a anterior está 100% verde (testes + Gate Final). Conforme Lei 7 do Iron Protocol.

---

### Fase 1 — DB + consolidação de schema

**Objetivo:** adicionar colunas faltantes, documentar semântica do campo duplicado, corrigir bugs de validação/factory.

#### Etapa 1.1 — Migration: parâmetros de regra em `equipment_calibrations`

**Arquivo novo:** `backend/database/migrations/2026_04_10_210001_add_decision_rule_parameters_to_equipment_calibrations.php`

```php
Schema::table('equipment_calibrations', function (Blueprint $table) {
    // Parâmetros de cobertura (ILAC P14)
    $table->decimal('coverage_factor_k', 5, 2)->nullable()->after('uncertainty_budget')
        ->comment('Coverage factor k (typically 2.00 for 95.45%)');
    $table->decimal('confidence_level', 5, 2)->nullable()->after('coverage_factor_k')
        ->comment('Confidence level % (e.g. 95.45)');

    // Parâmetros guard_band
    $table->string('guard_band_mode', 20)->nullable()->after('confidence_level')
        ->comment('k_times_u | percent_limit | fixed_abs');
    $table->decimal('guard_band_value', 12, 6)->nullable()->after('guard_band_mode')
        ->comment('Numeric value: k multiplier, % or absolute');

    // Parâmetros shared_risk (α produtor, β consumidor)
    $table->decimal('producer_risk_alpha', 6, 4)->nullable()->after('guard_band_value')
        ->comment('Max false reject probability (e.g. 0.0500)');
    $table->decimal('consumer_risk_beta', 6, 4)->nullable()->after('producer_risk_alpha')
        ->comment('Max false accept probability (e.g. 0.0500)');
});
```

#### Etapa 1.2 — Migration: resultado da decisão em `equipment_calibrations`

**Arquivo novo:** `backend/database/migrations/2026_04_10_210002_add_decision_result_to_equipment_calibrations.php`

```php
Schema::table('equipment_calibrations', function (Blueprint $table) {
    $table->string('decision_result', 10)->nullable()->after('consumer_risk_beta')
        ->comment('accept | warn | reject (computed)');
    $table->decimal('decision_z_value', 10, 4)->nullable()->after('decision_result')
        ->comment('z = (|err|-EMA)/U (shared_risk)');
    $table->decimal('decision_false_accept_prob', 8, 6)->nullable()->after('decision_z_value')
        ->comment('P_fa from normal CDF');
    $table->decimal('decision_guard_band_applied', 12, 6)->nullable()->after('decision_false_accept_prob')
        ->comment('w = k·U (or %, or abs) actually used');
    $table->dateTime('decision_calculated_at')->nullable()->after('decision_guard_band_applied');
    $table->unsignedBigInteger('decision_calculated_by')->nullable()->after('decision_calculated_at');
    $table->text('decision_notes')->nullable()->after('decision_calculated_by');

    $table->foreign('decision_calculated_by')->references('id')->on('users')->nullOnDelete();
    $table->index(['tenant_id', 'decision_result']);
});
```

#### Etapa 1.3 — Migration: log de auditoria (P12)

**Arquivo novo:** `backend/database/migrations/2026_04_10_210003_create_calibration_decision_logs_table.php`

```php
Schema::create('calibration_decision_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('equipment_calibration_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->string('decision_rule', 20);
    $table->json('inputs');       // {max_error_found, max_permissible_error, U, k, guard_band_*, ...}
    $table->json('outputs');      // {decision_result, z, P_fa, w_applied, ...}
    $table->string('engine_version', 20);  // versão do ConformityAssessmentService usada
    $table->timestamps();
    $table->index(['tenant_id', 'equipment_calibration_id']);
});
```

#### Etapa 1.4 — Model updates

**`backend/app/Models/EquipmentCalibration.php`**
- Adicionar ao `$fillable`: `coverage_factor_k`, `confidence_level`, `guard_band_mode`, `guard_band_value`, `producer_risk_alpha`, `consumer_risk_beta`, `decision_result`, `decision_z_value`, `decision_false_accept_prob`, `decision_guard_band_applied`, `decision_calculated_at`, `decision_calculated_by`, `decision_notes`.
- Adicionar aos casts: `coverage_factor_k => decimal:2`, `confidence_level => decimal:2`, `guard_band_value => decimal:6`, `producer_risk_alpha/consumer_risk_beta => decimal:4`, `decision_z_value => decimal:4`, `decision_false_accept_prob => decimal:6`, `decision_guard_band_applied => decimal:6`, `decision_calculated_at => datetime`.
- Adicionar relation: `decisionCalculator()` → `BelongsTo(User::class, 'decision_calculated_by')`.
- Adicionar HasMany: `decisionLogs()` → `HasMany(CalibrationDecisionLog::class)`.

**Novo model:** `backend/app/Models/CalibrationDecisionLog.php`
- Trait `BelongsToTenant`, `HasFactory`.
- Fillable: todos os campos da migration 1.3.
- Casts: `inputs => array`, `outputs => array`.
- Relations: `calibration()`, `user()`.

#### Etapa 1.5 — Corrigir bugs existentes

| Arquivo | Correção |
|---|---|
| `backend/database/factories/EquipmentCalibrationFactory.php:80` | `'simple_acceptance'` → `'simple'`. |
| `backend/app/Http/Requests/Features/UpdateCalibrationWizardRequest.php:36` | `'max:500'` → `'in:simple,guard_band,shared_risk'`. |
| `backend/app/Http/Requests/WorkOrder/StoreWorkOrderRequest.php:164` | `'max:30'` → `'in:simple,guard_band,shared_risk'` (manter `nullable`). |
| `backend/app/Http/Requests/WorkOrder/UpdateWorkOrderRequest.php:137` | idem. |

#### Etapa 1.6 — Regenerar schema dump SQLite

```bash
cd backend && php generate_sqlite_schema.php
```

**Gate Final Fase 1:**
- `php artisan migrate --pretend` mostra as 3 migrations.
- `./vendor/bin/pest --filter=EquipmentCalibrationFactoryTest` verde (criar este teste simples se não existir para validar o fix).
- Schema dump commitado.

---

### Fase 2 — `ConformityAssessmentService` + testes unitários

**Objetivo:** implementar o motor das 3 regras, isolado, testável, sem dependência de Eloquent.

#### Etapa 2.1 — Criar o service

**Arquivo novo:** `backend/app/Services/Calibration/ConformityAssessmentService.php`

**Interface pública:**
```php
namespace App\Services\Calibration;

use App\Services\Calibration\Decisions\DecisionInput;
use App\Services\Calibration\Decisions\DecisionOutput;

class ConformityAssessmentService
{
    public const ENGINE_VERSION = '1.0';

    public function evaluate(DecisionInput $in): DecisionOutput;
}
```

**DTOs novos:** `backend/app/Services/Calibration/Decisions/DecisionInput.php` e `DecisionOutput.php`

```php
// DecisionInput (readonly)
public function __construct(
    public readonly string $rule,              // simple|guard_band|shared_risk
    public readonly float $measuredError,      // valor absoluto aceito
    public readonly float $limit,              // EMA
    public readonly float $expandedUncertainty,// U = k·u_c
    public readonly float $coverageFactor,     // k (default 2.0)
    public readonly ?string $guardBandMode = null,  // k_times_u|percent_limit|fixed_abs
    public readonly ?float $guardBandValue = null,
    public readonly ?float $producerRiskAlpha = null,
    public readonly ?float $consumerRiskBeta = null,
) {}

// DecisionOutput (readonly)
public function __construct(
    public readonly string $result,    // accept|warn|reject
    public readonly ?float $zValue,
    public readonly ?float $falseAcceptProbability,
    public readonly ?float $guardBandApplied,
    public readonly string $ruleApplied,
    public readonly array $trace,      // {formula, intermediate_values}
) {}
```

**Lógica — modo SIMPLE (ILAC G8 §3.2.1):**
```
if (|err| + U <= EMA) → accept
else                   → reject
```

**Lógica — modo GUARD_BAND (ILAC G8 §3.2.2):**
```
w = compute_guard_band(mode, value, U, EMA)
   k_times_u     → w = value · U      (value é o multiplicador k_gb)
   percent_limit → w = (value/100) · EMA
   fixed_abs     → w = value

if (|err| + U <= EMA - w)     → accept  (inside acceptance zone)
elif (|err| - U >= EMA + w)   → reject  (outside acceptance zone)
else                           → warn    (guard zone)
```

**Lógica — modo SHARED_RISK (ILAC G8 §3.2.3 + ILAC P14):**
```
z = (|err| - EMA) / U        // distância em desvios-padrão
P_fa = 1 - Φ(z)              // prob. de falso aceite (cauda superior)

Se |err| <= EMA:
  se P_fa <= consumer_beta   → accept
  senão                       → warn
Senão:
  se (1-Φ(-z)) <= producer_alpha → warn (falso rejeite aceitável)
  senão                           → reject
```

**Nota implementação:** `Φ` (CDF normal) via aproximação de Abramowitz & Stegun 26.2.17 (puro PHP, sem extensão). Acurácia 7.5e-8 — mais que suficiente.

#### Etapa 2.2 — Testes unitários

**Arquivo novo:** `backend/tests/Unit/Services/Calibration/ConformityAssessmentServiceTest.php`

Casos mínimos (Pest, sem DB):

| # | Rule | Entrada | Esperado |
|---|---|---|---|
| 1 | simple | err=0.3, EMA=1.0, U=0.5 | accept |
| 2 | simple | err=0.6, EMA=1.0, U=0.5 | reject (0.6+0.5 > 1.0) |
| 3 | simple | err=0.5, EMA=1.0, U=0.5 | accept (boundary = ok) |
| 4 | guard_band/k_times_u | err=0.2, EMA=1.0, U=0.3, k_gb=1 | accept (w=0.3; 0.2+0.3 ≤ 0.7) |
| 5 | guard_band/k_times_u | err=0.5, EMA=1.0, U=0.3, k_gb=1 | warn (0.5+0.3=0.8 > 0.7, mas 0.5-0.3=0.2 < 1.3) |
| 6 | guard_band/k_times_u | err=1.5, EMA=1.0, U=0.3, k_gb=1 | reject (1.5-0.3=1.2 < 1.3 é false → reject) |
| 7 | guard_band/percent_limit | err=0.5, EMA=1.0, U=0.1, pct=10 | accept (w=0.1) |
| 8 | guard_band/fixed_abs | err=0.5, EMA=1.0, U=0.1, abs=0.2 | accept |
| 9 | shared_risk | err=0.3, EMA=1.0, U=0.5, α=β=0.05 | accept (z<0, Pfa≈0.08 mas err<EMA → verificar consumer) |
| 10 | shared_risk | err=1.2, EMA=1.0, U=0.1, α=β=0.05 | reject (z=2, Pfa≈0.023 mas α rejeitado) |
| 11 | shared_risk boundary | z exatamente no limite | warn |
| 12 | simple com U=0 | err=0.5, EMA=1.0, U=0 | accept (degenera para EmaCalculator::isConforming) |
| 13 | input inválido (guard_band sem value) | — | throw `InvalidArgumentException` |
| 14 | rule desconhecida | — | throw `InvalidArgumentException` |
| 15 | EMA=0 | — | throw `InvalidArgumentException` (divisão por zero em shared_risk) |

**Gate Final Fase 2:** `./vendor/bin/pest tests/Unit/Services/Calibration/ConformityAssessmentServiceTest.php` — 15/15 verdes.

---

### Fase 3 — Integração backend (endpoint + persistência + log)

#### Etapa 3.1 — Action: `EvaluateCalibrationDecisionAction`

**Arquivo novo:** `backend/app/Actions/Calibration/EvaluateCalibrationDecisionAction.php`

Responsabilidades:
1. Carregar `EquipmentCalibration` com `workOrder` eager.
2. Resolver regra efetiva: prioridade `calibration->decision_rule` > `workOrder->decision_rule_agreed` > `'simple'`.
3. Extrair inputs: `max_error_found`, `max_permissible_error`, maior `expanded_uncertainty` entre as readings (ou o do budget).
4. Montar `DecisionInput` e chamar `ConformityAssessmentService::evaluate()`.
5. **Persistir** no `equipment_calibrations`: `decision_result`, `decision_z_value`, `decision_false_accept_prob`, `decision_guard_band_applied`, `decision_calculated_at = now()`, `decision_calculated_by = auth()->id()`.
6. **Criar** registro em `calibration_decision_logs` com `inputs`, `outputs`, `engine_version`.
7. Envolver em `DB::transaction()`.

#### Etapa 3.2 — Endpoint REST

**Rota nova:** `routes/api/calibration.php` (ou arquivo equivalente — verificar onde ficam as rotas de EquipmentCalibration atualmente)

```php
Route::post('equipment-calibrations/{calibration}/evaluate-decision', [CalibrationDecisionController::class, 'evaluate'])
    ->middleware('can:calibration.certificate.manage')
    ->name('calibrations.evaluate-decision');
```

**Controller novo:** `backend/app/Http/Controllers/Api/V1/Calibration/CalibrationDecisionController.php`

- Método `evaluate(EquipmentCalibration $calibration, EvaluateDecisionRequest $request, EvaluateCalibrationDecisionAction $action)`.
- Autorização via `$this->authorize('update', $calibration)` OR permissão Spatie.
- Retorna `EquipmentCalibrationResource` (ou um Resource dedicado).
- Cross-tenant: Route Model Binding + trait `BelongsToTenant` já resolvem 404.

**FormRequest novo:** `backend/app/Http/Requests/Calibration/EvaluateDecisionRequest.php`

```php
public function authorize(): bool {
    return $this->user()->can('calibration.certificate.manage');
}

public function rules(): array {
    return [
        'rule' => 'required|in:simple,guard_band,shared_risk',
        'coverage_factor_k' => 'required|numeric|min:1|max:5',
        'guard_band_mode' => 'nullable|required_if:rule,guard_band|in:k_times_u,percent_limit,fixed_abs',
        'guard_band_value' => 'nullable|required_if:rule,guard_band|numeric|min:0',
        'producer_risk_alpha' => 'nullable|required_if:rule,shared_risk|numeric|between:0.0001,0.5',
        'consumer_risk_beta' => 'nullable|required_if:rule,shared_risk|numeric|between:0.0001,0.5',
        'notes' => 'nullable|string|max:1000',
    ];
}
```

#### Etapa 3.3 — `EmaCalculator::isConforming()` — corrigir P3

**Decisão:** **não alterar** a assinatura atual (outros usos legados dependem dela). Em vez disso, adicionar novo método:

```php
public static function isConformingWithUncertainty(float $error, float $ema, float $u): bool
{
    return bccomp(self::fmt(abs($error) + $u), self::fmt(abs($ema)), 6) <= 0;
}
```

O `ConformityAssessmentService` no modo SIMPLE usa essa nova função. O método antigo continua como está para não quebrar pontos que já o chamam — mas nenhum **cálculo de decisão** deve chamá-lo depois desta fase (adicionar `@deprecated` no PHPDoc + comentário "use ConformityAssessmentService instead").

#### Etapa 3.4 — Serializar decisão no Resource

**`backend/app/Http/Resources/EquipmentCalibrationResource.php`** (verificar se existe; criar se não)

Adicionar ao array de retorno:
```php
'decision' => [
    'rule' => $this->decision_rule,
    'result' => $this->decision_result,
    'coverage_factor_k' => $this->coverage_factor_k,
    'confidence_level' => $this->confidence_level,
    'guard_band_mode' => $this->guard_band_mode,
    'guard_band_value' => $this->guard_band_value,
    'guard_band_applied' => $this->decision_guard_band_applied,
    'producer_risk_alpha' => $this->producer_risk_alpha,
    'consumer_risk_beta' => $this->consumer_risk_beta,
    'z_value' => $this->decision_z_value,
    'false_accept_probability' => $this->decision_false_accept_prob,
    'calculated_at' => $this->decision_calculated_at,
    'calculated_by' => $this->whenLoaded('decisionCalculator', fn () => [
        'id' => $this->decisionCalculator->id,
        'name' => $this->decisionCalculator->name,
    ]),
    'notes' => $this->decision_notes,
],
```

#### Etapa 3.5 — Testes de integração

**Arquivo novo:** `backend/tests/Feature/Api/V1/Calibration/CalibrationDecisionControllerTest.php`

Casos (Pest Feature, com DB):

1. `it_requires_authentication` → 401.
2. `it_requires_permission` → 403.
3. `it_returns_404_for_cross_tenant_calibration` → 404.
4. `it_validates_required_fields` → 422 (sem `rule`, `coverage_factor_k`).
5. `it_validates_guard_band_params_when_rule_is_guard_band` → 422.
6. `it_validates_shared_risk_params_when_rule_is_shared_risk` → 422.
7. `it_validates_enum_for_rule` → 422 ao passar `'simple_acceptance'`.
8. `it_persists_simple_decision_accept` — response 200, `decision_result=accept` no DB.
9. `it_persists_guard_band_decision_warn` — response 200, `guard_band_applied` preenchido.
10. `it_persists_shared_risk_decision_with_z_and_pfa` — response 200, `z_value` e `false_accept_probability` preenchidos.
11. `it_creates_calibration_decision_log` — log criado com inputs/outputs/engine_version.
12. `it_updates_existing_decision_on_reevaluation` — sobrescreve e cria novo log.
13. `it_recognizes_rule_from_work_order_when_calibration_rule_is_null` — fallback.
14. `it_returns_decision_block_in_resource` — JSON estrutura correta.

**Gate Final Fase 3:** `./vendor/bin/pest tests/Feature/Api/V1/Calibration/CalibrationDecisionControllerTest.php` — 14/14 verdes + suite anterior não quebrou.

---

### Fase 4 — Frontend

#### Etapa 4.1 — Estender `CalibrationCriticalAnalysis.tsx`

Quando `decision_rule_agreed !== 'simple'`, renderizar campos adicionais via `<Controller>` do react-hook-form:

**Se `guard_band`:**
- Select `decision_guard_band_mode` → `{k_times_u, percent_limit, fixed_abs}`.
- Input numérico `decision_guard_band_value` com máscara + unidade contextual (ex.: "× U", "% do limite", "g").

**Se `shared_risk`:**
- Input numérico `decision_producer_risk_alpha` (0.0001–0.5, default 0.05).
- Input numérico `decision_consumer_risk_beta` (0.0001–0.5, default 0.05).

#### Etapa 4.2 — Tipos TypeScript

**`frontend/src/types/work-order.ts`** — adicionar à interface `CriticalAnalysisFields`:
```ts
decision_guard_band_mode?: 'k_times_u' | 'percent_limit' | 'fixed_abs' | null
decision_guard_band_value?: number | null
decision_producer_risk_alpha?: number | null
decision_consumer_risk_beta?: number | null
```

**`frontend/src/types/calibration.ts`** — adicionar interface `DecisionResult`:
```ts
export interface DecisionResult {
  rule: 'simple' | 'guard_band' | 'shared_risk'
  result: 'accept' | 'warn' | 'reject' | null
  coverage_factor_k: number
  confidence_level: number
  guard_band_mode?: string | null
  guard_band_value?: number | null
  guard_band_applied?: number | null
  producer_risk_alpha?: number | null
  consumer_risk_beta?: number | null
  z_value?: number | null
  false_accept_probability?: number | null
  calculated_at?: string | null
  calculated_by?: { id: number; name: string } | null
  notes?: string | null
}
```

#### Etapa 4.3 — Schema zod

**`frontend/src/lib/work-order-create-schema.ts`** — estender schema com refine condicional:
```ts
.refine((d) => d.decision_rule_agreed !== 'guard_band' || (d.decision_guard_band_mode && d.decision_guard_band_value != null),
  { message: 'Modo e valor de banda de guarda são obrigatórios', path: ['decision_guard_band_value'] })
.refine((d) => d.decision_rule_agreed !== 'shared_risk' || (d.decision_producer_risk_alpha != null && d.decision_consumer_risk_beta != null),
  { message: 'Riscos α e β são obrigatórios para shared_risk', path: ['decision_producer_risk_alpha'] })
```

#### Etapa 4.4 — Wizard: botão "Calcular Decisão"

**`frontend/src/pages/calibracao/CalibrationWizardPage.tsx`** — no step de leituras/resultado:

- Botão "Avaliar Conformidade" chama novo método em `lib/calibration-api.ts`:
  ```ts
  export const evaluateDecision = (calibrationId: number, payload: EvaluateDecisionPayload) =>
    api.post(`/equipment-calibrations/${calibrationId}/evaluate-decision`, payload)
  ```
- Após resposta, exibir **semáforo 3 estados**:
  - verde (`accept`) · amarelo (`warn`) · vermelho (`reject`)
- Mostrar linha "z = X · P_fa = Y%" quando `shared_risk`.
- Bloquear botão "Gerar Certificado" se `result !== 'accept'` ou se usuário não confirmar decisão WARN/REJECT com justificativa.

#### Etapa 4.5 — **Remover** o `<select>` duplicado no wizard

`CalibrationWizardPage.tsx:687-688` tem um segundo select de regra de decisão dentro do wizard. **Remover** e sempre usar o valor vindo do `work_orders.decision_rule_agreed` (exibir como read-only com link "Editar na OS"). Se o usuário precisar sobrescrever, abre modal específico que preenche `calibration->decision_rule` com justificativa em `decision_notes` (cai no endpoint da Fase 3).

**Gate Final Fase 4:**
- `npm run typecheck` verde.
- `npm run build` verde.
- Fluxo manual: criar OS `client_wants_conformity=true`, `rule=guard_band`, preencher params, rodar wizard, calcular decisão, ver semáforo 3 estados.

---

### Fase 5 — PDF do certificado

#### Etapa 5.1 — Ajustar fonte da verdade

`backend/resources/views/pdf/calibration-certificate.blade.php:310`

```blade
@php
    // Fonte: regra efetivamente aplicada > regra acordada > fallback
    $decisionRule = $calibration->decision_rule
        ?? $calibration->workOrder?->decision_rule_agreed
        ?? 'simple';
@endphp
```

#### Etapa 5.2 — Bloco normativo completo

Substituir linhas 305-352 (seção 6) por um bloco que imprima **todos** os campos exigidos:

```blade
<div class="info-box">
  <div class="info-box-title">6. Declaração de Conformidade (ISO/IEC 17025 §7.8.6)</div>

  {{-- Identificação da regra --}}
  <p><strong>Regra de Decisão:</strong> {{ $decisionRuleLabels[$decisionRule] ?? $decisionRule }}</p>
  <p><strong>Fator de Cobertura (k):</strong> {{ number_format($calibration->coverage_factor_k ?? 2.00, 2, ',', '.') }}</p>
  <p><strong>Nível de Confiança:</strong> {{ number_format($calibration->confidence_level ?? 95.45, 2, ',', '.') }}%</p>
  <p><strong>Incerteza Expandida (U):</strong> {{ number_format($maxExpandedU, 4, ',', '.') }} {{ $calibration->mass_unit }}</p>

  {{-- Parâmetros específicos --}}
  @if($decisionRule === 'guard_band')
    <p><strong>Modo de Banda de Guarda:</strong> {{ $guardBandModeLabels[$calibration->guard_band_mode] ?? '—' }}</p>
    <p><strong>Valor da Banda de Guarda Aplicada (w):</strong> {{ number_format($calibration->decision_guard_band_applied, 4, ',', '.') }} {{ $calibration->mass_unit }}</p>
  @elseif($decisionRule === 'shared_risk')
    <p><strong>Risco do Produtor (α):</strong> {{ number_format($calibration->producer_risk_alpha * 100, 2, ',', '.') }}%</p>
    <p><strong>Risco do Consumidor (β):</strong> {{ number_format($calibration->consumer_risk_beta * 100, 2, ',', '.') }}%</p>
    <p><strong>z calculado:</strong> {{ number_format($calibration->decision_z_value, 4, ',', '.') }}</p>
    <p><strong>P(falsa aceitação):</strong> {{ number_format($calibration->decision_false_accept_prob * 100, 4, ',', '.') }}%</p>
  @endif

  {{-- Referência ao acordo --}}
  <p style="font-size: 9px; color: #64748b;">
    Regra acordada na OS #{{ $calibration->workOrder?->number }}
    (cláusula: análise crítica, ILAC G8 §3.2)
  </p>

  {{-- Semáforo 3 estados --}}
  @php
    $resultColors = ['accept' => ['#16a34a', '✓ CONFORME'], 'warn' => ['#f59e0b', '⚠ ZONA DE GUARDA'], 'reject' => ['#dc2626', '✗ NÃO CONFORME']];
    [$color, $label] = $resultColors[$calibration->decision_result] ?? ['#64748b', '— NÃO AVALIADO'];
  @endphp
  <div style="margin-top: 8px; padding: 8px; border: 2px solid {{ $color }}; text-align: center;">
    <strong style="color: {{ $color }}; font-size: 14px;">{{ $label }}</strong>
  </div>
</div>
```

#### Etapa 5.3 — Gate de emissão (consolidar P2)

`backend/app/Services/CalibrationCertificateService.php` (ou onde esteja a geração do PDF) — **impedir** geração quando:
- `decision_rule !== 'simple'` **e** `decision_result === null`
  → lançar `DomainException("Regra de decisão não foi avaliada. Execute 'Avaliar Conformidade' antes de emitir o certificado.")`.

Isto é complementar ao gate do `CertificateEmissionChecklist` já existente — não substitui.

#### Etapa 5.4 — Teste de snapshot do PDF

**Arquivo novo:** `backend/tests/Feature/Pdf/CalibrationCertificateDecisionSectionTest.php`

3 testes:
1. `it_prints_simple_rule_with_k_and_confidence` — gera PDF, asserta HTML contém `k = 2,00`, `95,45%`, `✓ CONFORME`.
2. `it_prints_guard_band_with_applied_w` — HTML contém `Banda de Guarda`, `w = `, `⚠ ZONA DE GUARDA` (quando aplicável).
3. `it_prints_shared_risk_with_z_and_pfa` — HTML contém `z = `, `P(falsa aceitação)`.
4. `it_refuses_emission_when_decision_not_evaluated` — endpoint de emissão retorna 422.

**Gate Final Fase 5:** testes acima verdes + inspeção visual de 1 PDF gerado.

---

### Fase 6 — Qualidade, schema, pint, docs

#### Etapa 6.1 — Regenerar schema dump SQLite
```bash
cd backend && php generate_sqlite_schema.php
```

#### Etapa 6.2 — Rodar suíte escalada (Iron Protocol)
1. Unit service: `./vendor/bin/pest tests/Unit/Services/Calibration/ConformityAssessmentServiceTest.php`
2. Feature controller: `./vendor/bin/pest tests/Feature/Api/V1/Calibration/`
3. Feature PDF: `./vendor/bin/pest tests/Feature/Pdf/CalibrationCertificateDecisionSectionTest.php`
4. Só então: `./vendor/bin/pest --parallel --processes=16 --no-coverage` completa.

#### Etapa 6.3 — Pint + frontend checks
```bash
cd backend && ./vendor/bin/pint
cd ../frontend && npm run typecheck && npm run build
```

#### Etapa 6.4 — Atualizar documentação
- `docs/compliance/` — criar (ou atualizar) `iso17025-regra-decisao.md` com: fundamento normativo, fórmulas implementadas, tabela de exemplos, caminho dos arquivos do motor.
- `docs/PRD-KALIBRIUM.md` — RF de calibração (seção ISO 17025 §7.8.6): marcar regra de decisão como 🟢 implementada, referenciando `EvaluateCalibrationDecisionAction` + `ConformityAssessmentService` + teste `CalibrationDecisionControllerTest`.
- `docs/TECHNICAL-DECISIONS.md` — seção "Calibração" com os 3 modos e o valor canônico do enum.

**Gate Final Fase 6:** suite completa verde, sem TODO, sem código morto, sem console.log. Zero warnings no pint. Build frontend limpo.

---

## 5. Resumo de arquivos por fase

### Fase 1 — DB
- ✏️ `backend/database/migrations/2026_04_10_210001_add_decision_rule_parameters_to_equipment_calibrations.php` (novo)
- ✏️ `backend/database/migrations/2026_04_10_210002_add_decision_result_to_equipment_calibrations.php` (novo)
- ✏️ `backend/database/migrations/2026_04_10_210003_create_calibration_decision_logs_table.php` (novo)
- ✏️ `backend/app/Models/EquipmentCalibration.php` (update fillable/casts/relations)
- ✏️ `backend/app/Models/CalibrationDecisionLog.php` (novo)
- 🐛 `backend/database/factories/EquipmentCalibrationFactory.php:80`
- 🐛 `backend/app/Http/Requests/Features/UpdateCalibrationWizardRequest.php:36`
- 🐛 `backend/app/Http/Requests/WorkOrder/StoreWorkOrderRequest.php:164`
- 🐛 `backend/app/Http/Requests/WorkOrder/UpdateWorkOrderRequest.php:137`
- ♻️ `backend/database/schema/sqlite-schema.sql` (regenerar)

### Fase 2 — Motor
- ✏️ `backend/app/Services/Calibration/ConformityAssessmentService.php` (novo)
- ✏️ `backend/app/Services/Calibration/Decisions/DecisionInput.php` (novo)
- ✏️ `backend/app/Services/Calibration/Decisions/DecisionOutput.php` (novo)
- ✏️ `backend/tests/Unit/Services/Calibration/ConformityAssessmentServiceTest.php` (novo, 15 casos)

### Fase 3 — Integração backend
- ✏️ `backend/app/Actions/Calibration/EvaluateCalibrationDecisionAction.php` (novo)
- ✏️ `backend/app/Http/Controllers/Api/V1/Calibration/CalibrationDecisionController.php` (novo)
- ✏️ `backend/app/Http/Requests/Calibration/EvaluateDecisionRequest.php` (novo)
- ✏️ rotas da API (adicionar rota)
- ✏️ `backend/app/Http/Resources/EquipmentCalibrationResource.php` (update/criar)
- 🐛 `backend/app/Services/Calibration/EmaCalculator.php` (novo método `isConformingWithUncertainty` + deprecate do antigo)
- ✏️ `backend/tests/Feature/Api/V1/Calibration/CalibrationDecisionControllerTest.php` (novo, 14 casos)

### Fase 4 — Frontend
- ✏️ `frontend/src/components/os/CalibrationCriticalAnalysis.tsx` (campos condicionais)
- ✏️ `frontend/src/types/work-order.ts` (estender interface)
- ✏️ `frontend/src/types/calibration.ts` (nova interface `DecisionResult`)
- ✏️ `frontend/src/lib/work-order-create-schema.ts` (refines condicionais)
- ✏️ `frontend/src/lib/calibration-api.ts` (método `evaluateDecision`)
- ✏️ `frontend/src/pages/calibracao/CalibrationWizardPage.tsx` (botão "Avaliar", semáforo 3 estados, remover select duplicado linhas 687-688)
- ✏️ `frontend/src/components/calibracao/DecisionResultBanner.tsx` (novo — semáforo)

### Fase 5 — PDF
- ✏️ `backend/resources/views/pdf/calibration-certificate.blade.php` (seção 6 reescrita)
- ✏️ `backend/app/Services/CalibrationCertificateService.php` (gate de emissão)
- ✏️ `backend/tests/Feature/Pdf/CalibrationCertificateDecisionSectionTest.php` (novo, 4 casos)

### Fase 6 — Qualidade
- ♻️ `backend/database/schema/sqlite-schema.sql`
- ✏️ `docs/compliance/iso17025-regra-decisao.md` (novo)
- ✏️ `docs/PRD-KALIBRIUM.md` (update RF ISO 17025 §7.8.6)
- ✏️ `docs/TECHNICAL-DECISIONS.md` (seção nova)

---

## 6. Dependências entre fases

```
Fase 1 (DB)
   └─→ Fase 2 (Service isolado; não depende do DB mas depende do enum fixo)
         └─→ Fase 3 (Action/Controller; depende de DB + Service)
               └─→ Fase 4 (Frontend; consome endpoint da Fase 3)
               └─→ Fase 5 (PDF; consome campos persistidos na Fase 3)
                     └─→ Fase 6 (Quality + docs)
```

Fases 4 e 5 podem rodar em paralelo após Fase 3 (tocam arquivos diferentes).

## 7. Estimativa de commits

**6 commits atômicos**, 1 por fase, cada um com testes verdes no Gate Final antes do próximo.

## 8. O que este plano NÃO faz (escopo explícito)

- ❌ Não cria tabela `client_agreement_decision_rules` (acordo global por cliente). **Justificativa:** ILAC G8 já é satisfeita com o acordo por OS. Fica como melhoria futura se usuário pedir.
- ❌ Não remove `equipment_calibrations.decision_rule` nem `work_orders.decision_rule_agreed`. **Justificativa:** seção 3.1 explica o papel distinto de cada um.
- ❌ Não refatora `CalibrationWizardService::calculateExpandedUncertainty()`. Está correto.
- ❌ Não mexe em `EmaCalculator::calculate()` (tabela EMA da Portaria 157/2022). Continua fonte de verdade do EMA.
- ❌ Não altera o checklist de emissão (`CertificateEmissionChecklist`). Gate de decisão é **adicional**, não substitui.
