# Plano: Conformidade Normativa Plena — Módulo de Calibração (v2 — Revisado)

**Data:** 2026-04-09 | **Revisão:** v2 (pós-auditoria adversarial)
**Origem:** `docs/auditorias/auditoria-calibracao-normativa-2026-04-09.md`
**Objetivo:** Levar o módulo de calibração de 89% → 100% de conformidade normativa.
**Base normativa:** `memory/reference_calibracao_balancas.md`

---

## Changelog v1 → v2

| # | Correção | Motivo |
|---|----------|--------|
| 1 | **Fase 7 eliminada** — CRUD MaintenanceReport no frontend | Já existe: `MaintenanceReportForm.tsx`, `MaintenanceReportList.tsx`, `MaintenanceReportsTab.tsx`, hooks, API client. Tab já integrada no `WorkOrderDetailPage.tsx` |
| 2 | **Fase 9 reduzida** — StandardWeightsPage já existe | `StandardWeightsPage.tsx` em `pages/equipamentos/` com CRUD completo. Rota `/equipamentos/pesos-padrao` já definida. Reduzido a widget de alertas |
| 3 | **Fase 10 reduzida** — BeforeAfterPhotos.tsx já existe | Componente `BeforeAfterPhotos.tsx` com modo side-by-side já funcional. Reduzido a estender para dados textuais condition_as_found/left |
| 4 | **Fase 3.5 corrigida** — `EquipmentModel.category` é string, não FK | `EquipmentCategory` é lookup table com `slug`. Matching usa `$equipment->equipmentModel->category` (string) contra `AccreditationScope.equipment_categories` (JSON array de strings) |
| 5 | **CalibrationCertificateException** → usar `DomainException` | Consistente com o que o service já usa |
| 6 | **Fase 0 adicionada** — verificação de conexões existentes | MaintenanceReport rotas confirmadas em `routes/api/work-orders.php` (6 endpoints). Calibração em `routes/api/advanced-lots.php` |
| 7 | **DomPDF paginação corrigida** — CSS `@page` + `page-break`, não Puppeteer syntax | Sistema usa `Barryvdh\DomPDF`. Templates existentes usam `page-break-inside: avoid` |
| 8 | **Wizard step insertion** — auditoria de índices obrigatória | 7 renderStep functions + 7 comparações hardcoded (`step === 0` a `step === 6`). Inserir step requer renumerar TUDO |
| 9 | **Rotas novas** — LinearityTest em `advanced-lots.php`, AccreditationScope em `equipment-platform.php` | Confirmado pela organização existente |
| 10 | **Blade já referencia** `condition_as_found`/`condition_as_left` com null coalescing | Não é campo novo na view; migration + fillable fazem o campo funcionar |

---

## Visão Geral das Fases (Revisada)

| Fase | Escopo | Etapas | Prioridade |
|------|--------|--------|------------|
| **0** | Verificação de conexões existentes | 0.1–0.3 | Pré-requisito |
| **1** | Migrations & Schema (campos faltantes) | 1.1–1.4 | P0+P1 |
| **2** | LinearityTest — ponta a ponta | 2.1–2.6 | P0 |
| **3** | AccreditationScope — ponta a ponta | 3.1–3.7 | P0 |
| **4** | Backend: ajustes em services e gates | 4.1–4.4 | P1 |
| **5** | Certificado PDF: melhorias DomPDF | 5.1–5.3 | P1 |
| **6** | Frontend: Análise Crítica na OS (+campos) | 6.1–6.3 | P0 |
| **7** | Frontend: LinearityTest no Wizard | 7.1–7.4 | P0 |
| **8** | Frontend: Widget alertas StandardWeight | 8.1–8.2 | P1 |
| **9** | Frontend: Estender BeforeAfterPhotos | 9.1 | P2 |
| **10** | Testes de conformidade normativa | 10.1–10.5 | P1+P2 |
| **11** | Gate Final & Schema Dump | 11.1–11.3 | Obrigatório |

**Regra:** Cada fase só inicia após a anterior estar 100% com testes passando (Lei 7).

---

## Fase 0 — Verificação de Conexões Existentes

### Etapa 0.1 — Confirmar rotas do MaintenanceReport

**Rotas já existentes** em `routes/api/work-orders.php` (linhas 267-275):

```
GET    /maintenance-reports                              → index
GET    /maintenance-reports/{maintenance_report}         → show
POST   /maintenance-reports                              → store
PUT    /maintenance-reports/{maintenance_report}         → update
POST   /maintenance-reports/{maintenance_report}/approve → approve
DELETE /maintenance-reports/{maintenance_report}         → destroy
```

**Ação:** Testar cada endpoint via Pest. Se algum falhar, corrigir antes de prosseguir.

### Etapa 0.2 — Confirmar frontend MaintenanceReport integrado

**Componentes existentes:**
- `frontend/src/lib/maintenance-report-api.ts` — API client
- `frontend/src/hooks/useMaintenanceReports.ts` — React Query hooks
- `frontend/src/components/os/MaintenanceReportForm.tsx` — Formulário
- `frontend/src/components/os/MaintenanceReportList.tsx` — Lista
- `frontend/src/components/os/MaintenanceReportsTab.tsx` — Tab na OS

**Tab já integrada** em `WorkOrderDetailPage.tsx` (activeTab union type inclui `'maintenance'`).

**Ação:** Verificar que o fluxo funciona end-to-end: abrir OS → tab manutenção → criar relatório → aprovar. Se algo falhar, corrigir.

### Etapa 0.3 — Confirmar StandardWeightsPage funcional

**Página existente:** `frontend/src/pages/equipamentos/StandardWeightsPage.tsx`
**Rota existente:** `/equipamentos/pesos-padrao` com permissão `equipments.calibration.view`

**Ação:** Verificar CRUD funcional. Se falta algo, corrigir.

**Gate Fase 0:** Todas as funcionalidades existentes confirmadas operacionais.

---

## Fase 1 — Migrations & Schema (campos faltantes em tabelas existentes)

### Etapa 1.1 — Migration: campos na `equipment_calibrations`

**Arquivo:** `backend/database/migrations/2026_04_10_100001_add_normative_fields_to_equipment_calibrations.php`

```php
Schema::table('equipment_calibrations', function (Blueprint $table) {
    // P1: Datetime de início/fim da calibração
    $table->dateTime('calibration_started_at')->nullable()->after('calibration_date');
    $table->dateTime('calibration_completed_at')->nullable()->after('calibration_started_at');

    // P1: Condição como encontrado/deixado (texto explícito, filtrável)
    // NOTA: Blade template JÁ referencia esses campos via null coalescing.
    // Criar as colunas faz os campos funcionarem sem alterar a view.
    $table->text('condition_as_found')->nullable()->after('after_adjustment_data');
    $table->text('condition_as_left')->nullable()->after('condition_as_found');

    // P2: Flag de ajuste realizado
    $table->boolean('adjustment_performed')->default(false)->after('condition_as_left');

    // P0: Referência ao escopo de acreditação usado (FK criada na Fase 3)
    $table->unsignedBigInteger('accreditation_scope_id')->nullable()->after('scope_declaration');
});
```

**Nota:** A FK constraint para `accreditation_scope_id` será adicionada na migration da Fase 3 (após criar a tabela `accreditation_scopes`), evitando dependência circular.

### Etapa 1.2 — Migration: campos na `work_orders`

**Arquivo:** `backend/database/migrations/2026_04_10_100002_add_normative_fields_to_work_orders.php`

**Nota sobre WorkOrder:** O model já tem ~268 campos fillable. Os 3 campos abaixo são necessários para conformidade normativa e não justificam refatoração do model neste momento. Documentar como dívida técnica para futura extração de campos de calibração para tabela pivô.

```php
Schema::table('work_orders', function (Blueprint $table) {
    $table->dateTime('client_accepted_at')->nullable()->after('decision_rule_agreed');
    $table->string('client_accepted_by', 255)->nullable()->after('client_accepted_at');
    $table->string('applicable_procedure', 500)->nullable()->after('calibration_scope_notes');
});
```

### Etapa 1.3 — Migration: condições ambientais por leitura

**Arquivo:** `backend/database/migrations/2026_04_10_100003_add_environmental_to_calibration_readings.php`

```php
Schema::table('calibration_readings', function (Blueprint $table) {
    $table->decimal('temperature', 5, 2)->nullable()->after('unit');
    $table->decimal('humidity', 5, 2)->nullable()->after('temperature');
});
```

### Etapa 1.4 — Atualizar Models com novos campos

**Arquivos a editar:**

1. **`EquipmentCalibration.php`**
   - `$fillable` += `calibration_started_at`, `calibration_completed_at`, `condition_as_found`, `condition_as_left`, `adjustment_performed`, `accreditation_scope_id`
   - `$casts` += `calibration_started_at` → datetime, `calibration_completed_at` → datetime, `adjustment_performed` → boolean
   - Relationship: `accreditationScope()` → `BelongsTo(AccreditationScope::class)` (model criado na Fase 3)

2. **`WorkOrder.php`**
   - `$fillable` += `client_accepted_at`, `client_accepted_by`, `applicable_procedure`
   - `$casts` += `client_accepted_at` → datetime

3. **`CalibrationReading.php`**
   - `$fillable` += `temperature`, `humidity`
   - `$casts` += ambos `decimal:2`

**Validação:** Nenhum teste existente quebra.

**Gate Final Fase 1:** `./vendor/bin/pest --parallel --processes=16 --no-coverage` — todos passam.

---

## Fase 2 — LinearityTest (Model + Migration + Controller + Testes)

### Etapa 2.1 — Migration: `linearity_tests`

**Arquivo:** `backend/database/migrations/2026_04_10_200001_create_linearity_tests_table.php`

```php
Schema::create('linearity_tests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('equipment_calibration_id')->constrained()->cascadeOnDelete();

    $table->integer('point_order');
    $table->decimal('reference_value', 12, 4);
    $table->string('unit', 20)->default('g');

    $table->decimal('indication_increasing', 12, 4)->nullable();
    $table->decimal('indication_decreasing', 12, 4)->nullable();

    $table->decimal('error_increasing', 12, 4)->nullable();
    $table->decimal('error_decreasing', 12, 4)->nullable();
    $table->decimal('hysteresis', 12, 4)->nullable();
    $table->decimal('max_permissible_error', 12, 4)->nullable();
    $table->boolean('conforms')->default(true);

    $table->timestamps();
    $table->index(['tenant_id', 'equipment_calibration_id']);
});
```

### Etapa 2.2 — Model: `LinearityTest`

**Arquivo:** `backend/app/Models/LinearityTest.php`

- Trait: `BelongsToTenant`, `HasFactory`
- Fillable: todos os campos da migration
- Casts: decimals `decimal:4`, `conforms` boolean
- Relationship: `calibration()` → BelongsTo(EquipmentCalibration)
- Método `calculateErrors()`:
  - `error_increasing = indication_increasing - reference_value`
  - `error_decreasing = indication_decreasing - reference_value`
  - `hysteresis = abs(indication_increasing - indication_decreasing)`
  - `conforms = abs(error_inc) <= abs(EMA) AND abs(error_dec) <= abs(EMA) AND hysteresis <= abs(EMA)`

### Etapa 2.3 — Factory + Relationship inversa

- **Factory:** `LinearityTestFactory.php` — dados realistas para Classe III, 5 pontos de 150kg
- **EquipmentCalibration:** adicionar `linearityTests()` → `HasMany(LinearityTest::class)`

### Etapa 2.4 — Controller + FormRequest + Rotas

**Controller:** `LinearityTestController.php` (ou métodos em controller existente de calibração)

**Rotas em `routes/api/advanced-lots.php`:**

```php
Route::prefix('calibration/{calibration}')->group(function () {
    Route::get('linearity', [LinearityTestController::class, 'index']);
    Route::post('linearity', [LinearityTestController::class, 'store']);
    Route::delete('linearity', [LinearityTestController::class, 'destroyAll']);
});
```

**FormRequest:** `StoreLinearityTestsRequest`
- Valida array de pontos: `points.*.reference_value` required decimal, `points.*.indication_increasing` nullable decimal, `points.*.indication_decreasing` nullable decimal
- Valida que a calibração pertence ao tenant
- Permissão: `calibration.reading.create`

**Lógica do store:**
1. Receber array de pontos
2. Para cada ponto: calcular erros e histerese via `calculateErrors()`
3. Usar `EmaCalculator` para `max_permissible_error` baseado na `precision_class` e `verification_division_e` da calibração
4. Deletar pontos anteriores (replace batch) + inserir novos em transaction

### Etapa 2.5 — API Resource

**Arquivo:** `LinearityTestResource.php` — campos: point_order, reference_value, unit, indication_increasing, indication_decreasing, error_increasing, error_decreasing, hysteresis, max_permissible_error, conforms

### Etapa 2.6 — Testes

**Arquivo:** `backend/tests/Feature/Calibration/LinearityTestControllerTest.php`

**Cenários (10):**

1. Criar pontos com dados válidos → 201 + erros calculados
2. Erros increasing/decreasing calculados corretamente
3. Histerese = |ind_inc - ind_dec|
4. Conformidade quando dentro do EMA
5. Não-conformidade quando fora do EMA
6. EMA diferente por classe (I vs III) e tipo verificação (initial × 1x vs in_use × 2x)
7. Validação 422 — reference_value obrigatório
8. Cross-tenant 404
9. Permissão 403
10. Batch replace — POST substitui pontos anteriores

**Gate Final Fase 2:** LinearityTest funcional ponta a ponta com 10 testes passando.

---

## Fase 3 — AccreditationScope (ponta a ponta)

### Etapa 3.1 — Migration: `accreditation_scopes` + FK

**Arquivo:** `backend/database/migrations/2026_04_10_300001_create_accreditation_scopes_table.php`

```php
Schema::create('accreditation_scopes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

    $table->string('accreditation_number', 100);
    $table->string('accrediting_body', 100)->default('Cgcre/Inmetro');
    $table->text('scope_description');
    // Array de strings matching EquipmentModel.category
    // Ex: ["Balancas Comerciais", "Balancas Industriais", "Balancas Analiticas e de Precisao"]
    $table->json('equipment_categories');
    $table->date('valid_from');
    $table->date('valid_until');
    $table->string('certificate_file', 500)->nullable();
    $table->boolean('is_active')->default(true);

    $table->timestamps();
    $table->index(['tenant_id', 'is_active']);
});

// Agora que a tabela existe, adicionar a FK na equipment_calibrations
Schema::table('equipment_calibrations', function (Blueprint $table) {
    $table->foreign('accreditation_scope_id')
          ->references('id')
          ->on('accreditation_scopes')
          ->nullOnDelete();
});
```

### Etapa 3.2 — Model: `AccreditationScope`

**Arquivo:** `backend/app/Models/AccreditationScope.php`

- Trait: `BelongsToTenant`, `HasFactory`
- Casts: `equipment_categories` → array, `valid_from`/`valid_until` → date, `is_active` → boolean
- Scopes:
  - `scopeActive($q)` → `where('is_active', true)->where('valid_until', '>=', now())`
  - `scopeForCategory($q, string $category)` → `whereJsonContains('equipment_categories', $category)`
- Métodos:
  - `coversCategory(string $cat): bool` → `in_array($cat, $this->equipment_categories)`
  - `isExpired(): bool` → `$this->valid_until->isPast()`
  - `isValid(): bool` → `$this->is_active && !$this->isExpired()`
- Relationship: `calibrations()` → HasMany(EquipmentCalibration)

### Etapa 3.3 — Factory

`AccreditationScopeFactory.php` — Cgcre, cobre balanças, válido 2 anos.

### Etapa 3.4 — Controller + FormRequest + Rotas

**Rotas em `routes/api/equipment-platform.php`:**

```php
Route::apiResource('accreditation-scopes', AccreditationScopeController::class);
Route::get('accreditation-scopes-active', [AccreditationScopeController::class, 'active']);
```

**Permissão:** `accreditation.scope.manage` (adicionar no PermissionsSeeder, seguindo padrão `entity.action.verb`)

**FormRequest:** `StoreAccreditationScopeRequest` / `UpdateAccreditationScopeRequest`
- `accreditation_number` required string
- `equipment_categories` required array, `equipment_categories.*` string
- `valid_from` required date, `valid_until` required date after:valid_from

### Etapa 3.5 — Integrar no CalibrationCertificateService

**Editar:** `backend/app/Services/CalibrationCertificateService.php` → método `generate()`

**Lógica (CORRIGIDA — category é string, não FK):**

```php
// Pegar categoria do equipamento (string do EquipmentModel)
$category = $calibration->equipment->equipmentModel?->category;

// Buscar escopo ativo que cobre essa categoria
$scope = $category
    ? AccreditationScope::active()
        ->forCategory($category)
        ->where('tenant_id', $calibration->tenant_id)
        ->first()
    : null;

$isAccredited = $scope !== null && $scope->isValid();

// Salvar referência
$calibration->update(['accreditation_scope_id' => $scope?->id]);

// Passar para a view
$data['is_accredited'] = $isAccredited;
$data['accreditation'] = $isAccredited ? [
    'number' => $scope->accreditation_number,
    'body' => $scope->accrediting_body,
    'scope' => $scope->scope_description,
] : null;
```

### Etapa 3.6 — Atualizar Blade template para acreditação condicional

```blade
@if($is_accredited)
    <div class="accreditation-badge">
        <strong>Acreditação {{ $accreditation['body'] }}</strong>
        Nº {{ $accreditation['number'] }} — {{ $accreditation['scope'] }}
    </div>
@else
    <div class="no-accreditation-notice">
        Certificado de calibração sem referência à acreditação.
    </div>
@endif
```

### Etapa 3.7 — Testes

**Arquivo:** `backend/tests/Feature/Calibration/AccreditationScopeTest.php`

**Cenários (10):**

1. CRUD completo — criar, listar, atualizar, deletar
2. `forCategory()` filtra corretamente por JSON contains
3. Escopo expirado não aparece em `active()`
4. Certificado COM escopo válido → PDF inclui marca RBC
5. Certificado SEM escopo → PDF sem marca + aviso
6. Escopo para categoria errada → PDF sem marca
7. Escopo inativo → PDF sem marca
8. Cross-tenant 404
9. Permissão 403
10. Validação 422

**Gate Final Fase 3:** AccreditationScope funcional. Certificado condicional correto.

---

## Fase 4 — Backend: ajustes em services e gates

### Etapa 4.1 — Bloquear emissão com padrão vencido

**Editar:** `CalibrationCertificateService::generate()`

```php
$expiredWeights = $calibration->standardWeights()
    ->where('certificate_expiry', '<', now())
    ->get();

if ($expiredWeights->isNotEmpty()) {
    throw new \DomainException(
        'Padrões com certificado vencido: ' . $expiredWeights->pluck('code')->join(', ')
    );
}
```

**Usar `DomainException`** — consistente com o que o service já lança em outras validações.

### Etapa 4.2 — Incluir LinearityTest na validação ISO 17025

**Editar:** `CalibrationWizardService::validateIso17025()`

Adicionar check: se `$calibration->linearityTests->isNotEmpty()`, verificar que todos conformam. Se algum `conforms === false`, adicionar aos `missing_fields` como warning (não bloqueante — a não-conformidade é um resultado válido).

### Etapa 4.3 — Incluir LinearityTest no PDF

**Editar:** `CalibrationCertificateService::generate()` — adicionar `linearityTests` ao eager loading.

**Editar Blade:** Seção condicional "Ensaio de Linearidade":

```blade
@if($calibration->linearityTests->isNotEmpty())
<h3>Ensaio de Linearidade</h3>
<table>
    <thead>
        <tr><th>Ponto</th><th>Referência</th><th>Indicação ↑</th><th>Indicação ↓</th>
        <th>Erro ↑</th><th>Erro ↓</th><th>Histerese</th><th>EMA</th><th>Conforme</th></tr>
    </thead>
    <tbody>
        @foreach($calibration->linearityTests->sortBy('point_order') as $test)
        <tr>...</tr>
        @endforeach
    </tbody>
</table>
@endif
```

### Etapa 4.4 — Atualizar API Resources

- Atualizar `EquipmentCalibrationResource` para incluir: `linearityTests`, `accreditationScope`, novos campos (condition_as_found, etc.)
- Criar `AccreditationScopeResource`

**Gate Final Fase 4:** PDF com linearidade. Padrão vencido bloqueia. Resources atualizados.

---

## Fase 5 — Certificado PDF: melhorias DomPDF

### Etapa 5.1 — Paginação "Página X de Y" via CSS/DomPDF

**O sistema usa DomPDF** (`Barryvdh\DomPDF\Facade\Pdf`). Templates existentes usam `page-break-inside: avoid`.

**Abordagem correta para DomPDF:**

```css
@page {
    margin: 15mm 10mm 20mm 10mm;
}
```

```blade
<style>
    .page-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 8px;
        color: #666;
    }
    /* DomPDF suporta counter via script inline */
</style>

{{-- DomPDF injeta page numbers via script --}}
<script type="text/php">
    if (isset($pdf)) {
        $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
        $size = 8;
        $font = $fontMetrics->getFont("helvetica");
        $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
        $x = ($pdf->get_width() - $width) / 2;
        $y = $pdf->get_height() - 20;
        $pdf->page_text($x, $y, $text, $font, $size, [0, 0, 0]);
    }
</script>
```

**Pré-requisito:** Verificar que `'isPhpEnabled' => true` está no config do DomPDF (`config/dompdf.php`).

### Etapa 5.2 — Garantir que template não insere "validade" indevida

**Verificar no Blade:** O campo `no_undue_interval` no checklist já existe. Garantir que:
- O template NÃO renderiza "Válido até" ou "Próxima calibração" no corpo do certificado
- A coluna "Validade" na tabela de padrões refere-se ao certificado DO PADRÃO, não da calibração (isso está correto)
- Se `next_due_date` existir, renderizar APENAS em nota separada do certificado, não no corpo principal

### Etapa 5.3 — Seção de ajuste explícita

Se `$calibration->adjustment_performed === true`:
- Renderizar seção: "Ajuste realizado antes da medição final: **Sim**"
- Mostrar `before_adjustment_data` e `after_adjustment_data`
- Mostrar `condition_as_found` e `condition_as_left`

Se `false` ou campo novo não preenchido (retrocompatibilidade):
- Inferir de `before_adjustment_data !== null` como fallback

**Gate Final Fase 5:** PDF com paginação DomPDF. Sem validade indevida. Ajuste explícito.

---

## Fase 6 — Frontend: Análise Crítica na OS (+campos novos)

### Etapa 6.1 — Adicionar campos em CalibrationCriticalAnalysis.tsx

**Arquivo existente:** `frontend/src/components/os/CalibrationCriticalAnalysis.tsx` (~700 linhas, componente real e funcional)

**Campos a adicionar no formulário:**

1. `applicable_procedure` — Input text ou Select com procedimentos padrão ("POP-CAL-001", etc.)
2. `client_accepted_at` — DateTimePicker para registro do aceite formal
3. `client_accepted_by` — Input text (nome/cargo de quem aceitou pelo cliente)

**Posicionamento:** Após a seção de escopo existente, antes dos toggles de manutenção.

### Etapa 6.2 — Atualizar tipos TypeScript

**Editar:** `frontend/src/types/work-order.ts` (ou arquivo onde WorkOrder interface está)

Adicionar: `client_accepted_at?: string`, `client_accepted_by?: string`, `applicable_procedure?: string`

### Etapa 6.3 — Garantir que API salva os novos campos

Verificar que o FormRequest de WorkOrder no backend aceita os 3 novos campos. Se não, adicionar ao `StoreWorkOrderRequest` / `UpdateWorkOrderRequest`.

**Gate Final Fase 6:** Análise Crítica funcional com todos os campos normativos.

---

## Fase 7 — Frontend: LinearityTest no Wizard

### Etapa 7.1 — AUDITORIA DE ÍNDICES (obrigatório antes de inserir step)

**CRÍTICO:** O wizard usa 7 `renderStep` functions + 7 comparações hardcoded:

```typescript
// Linhas 1111-1117 — TODAS precisam ser renumeradas
step === 0  → Equipamento
step === 1  → Condições
step === 2  → Padrões
step === 3  → Leituras
step === 4  → Excentricidade    // será 5 após inserção
step === 5  → Repetibilidade    // será 6
step === 6  → Verificação       // será 7
```

**Ação ANTES de inserir:**
1. Listar TODAS as referências a índices numéricos no wizard (`step === N`, `setStep(N)`, `renderStepN`)
2. Refatorar: trocar comparações numéricas por keys do STEP_LABELS (`step === 'linearity'` ao invés de `step === 4`)
3. Ou: renumerar todas as referências de 4→5, 5→6, 6→7

**Recomendação:** Refatorar para keys (mais robusto para futuras mudanças).

### Etapa 7.2 — Adicionar step "Linearidade" no array

```typescript
STEP_LABELS = [
    { key: 'identification', ... },   // 0
    { key: 'environment', ... },       // 1
    { key: 'standards', ... },         // 2
    { key: 'readings', ... },          // 3
    { key: 'linearity', label: 'Linearidade', icon: TrendingUp },  // 4 ← NOVO
    { key: 'eccentricity', ... },      // 5
    { key: 'repeatability', ... },     // 6
    { key: 'verification', ... },      // 7
]
```

### Etapa 7.3 — Implementar renderStepLinearity

**Conteúdo:**
- Tabela com pontos sugeridos (reutilizar `suggestPoints` de `useCalibrationCalculations`)
- Para cada ponto: referência | indicação crescente | indicação decrescente
- Auto-cálculo client-side: erro crescente, erro decrescente, histerese, EMA, conforme
- Visual: ✅/❌ por ponto + resumo "X de Y pontos conformes"

### Etapa 7.4 — Hook + Tipos + API

**Hook:** `useLinearityCalculations.ts`
- `calculateLinearityErrors(ref, indInc, indDec)` → { errorInc, errorDec, hysteresis }
- `isLinearityConforming(errorInc, errorDec, hysteresis, ema)` → boolean

**Tipo:** Adicionar `LinearityTest` interface em `types/calibration.ts`

**API:**
- `POST /calibration/{calibrationId}/linearity` → salvar pontos
- `GET /calibration/{calibrationId}/linearity` → carregar pontos

**Gate Final Fase 7:** Step de linearidade funcional. 8 steps no wizard. Dados salvos e carregados.

---

## Fase 8 — Frontend: Widget alertas StandardWeight

**Contexto:** `StandardWeightsPage.tsx` já existe com CRUD completo. Falta widget de alertas para dashboard.

### Etapa 8.1 — Widget de alertas de vencimento

**Arquivo:** `frontend/src/components/dashboard/StandardWeightExpiryAlerts.tsx`

- Chamar `GET /standard-weights/expiring` (endpoint já existe)
- Renderizar card com contagem: 🔴 Vencidos (X) | 🟡 Vencem em 30 dias (Y) | 🟢 Em dia (Z)
- Link para `/equipamentos/pesos-padrao` com filtro aplicado

### Etapa 8.2 — Integrar no dashboard principal

Adicionar widget no dashboard admin/gerencial na seção de calibração.

**Gate Final Fase 8:** Widget funcional com dados reais.

---

## Fase 9 — Frontend: Estender BeforeAfterPhotos para dados textuais

### Etapa 9.1 — Adicionar seção de texto ao componente existente

**Arquivo existente:** `frontend/src/components/os/BeforeAfterPhotos.tsx`

**Extensão:** Abaixo das fotos, adicionar seção de texto para `condition_as_found` / `condition_as_left`:

```tsx
{/* Seção existente: fotos antes/depois */}
{/* Nova seção: condição textual */}
{calibration?.condition_as_found && (
    <div className="grid grid-cols-2 gap-4 mt-4">
        <div>
            <h4>Como Encontrado</h4>
            <p>{calibration.condition_as_found}</p>
        </div>
        <div>
            <h4>Como Deixado</h4>
            <p>{calibration.condition_as_left}</p>
        </div>
    </div>
)}
```

**Gate Final Fase 9:** Comparativo visual funcional.

---

## Fase 10 — Testes de Conformidade Normativa

### Etapa 10.1 — Testes parametrizados EMA (4 classes × fronteiras)

**Arquivo:** `backend/tests/Unit/Calibration/EmaCalculatorEdgeCaseTest.php`

Testar limites exatos:
- Classe I: exatamente 50.000e (deve dar 0.5e), 50.001e (deve dar 1.0e)
- Classe II: exatamente 5.000e, 20.000e, 100.000e
- Classe III: exatamente 500e, 2.000e, 10.000e
- Classe IIII: exatamente 50e, 200e, 1.000e
- Cada um com initial (1x) e in_use (2x)

### Etapa 10.2 — Teste de padrão vencido bloqueando emissão

**Arquivo:** `backend/tests/Feature/Calibration/ExpiredStandardBlocksTest.php`

1. Calibração com padrão vencido → `DomainException` ao gerar certificado
2. Atualizar validade → gerar com sucesso
3. Padrão sem data de validade → permitir (campo nullable)

### Etapa 10.3 — Teste cross-tenant StandardWeight

**Arquivo:** `backend/tests/Feature/Calibration/StandardWeightTenantIsolationTest.php`

1. Padrão do tenant A → invisível para tenant B
2. Tentativa de vincular a calibração do tenant B → erro

### Etapa 10.4 — Teste fluxo manutenção → calibração

**Arquivo:** `backend/tests/Feature/Calibration/MaintenanceToCalibrationFlowTest.php`

1. Criar OS com manutenção + MaintenanceReport com `requires_calibration_after = true`
2. Verificar sinalização
3. Executar calibração pós-manutenção → certificado gerado

### Etapa 10.5 — Teste certificado acreditado vs não acreditado (adicional ao 3.7)

Cenários extremos:
- Tenant com 2 escopos (um expirado, um ativo) → usar ativo
- Escopo cobre "Balancas Comerciais" mas equipamento é "Balancas Analiticas" → sem marca
- Alterar escopo para inativo DURANTE geração → sem marca

**Gate Final Fase 10:** Todos os testes de conformidade passam.

---

## Fase 11 — Gate Final & Schema Dump

### Etapa 11.1 — Regenerar schema SQLite

```bash
cd backend && php generate_sqlite_schema.php
```

### Etapa 11.2 — Suite completa

```bash
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
```

**Critério:** 100% passam. Zero falhas. Zero skipped.

### Etapa 11.3 — Checklist de consistência

- [ ] Todas as migrations rodam em sequência limpa
- [ ] Rollback funciona
- [ ] Schema dump atualizado com TODAS as novas tabelas/colunas
- [ ] Models: $fillable e $casts corretos
- [ ] Relationships bidirecionais (LinearityTest ↔ EquipmentCalibration, AccreditationScope ↔ EquipmentCalibration)
- [ ] FormRequests validam campos novos
- [ ] API Resources incluem campos novos
- [ ] Frontend types sincronizados
- [ ] Permissão `accreditation.scope.manage` no PermissionsSeeder
- [ ] PDF: linearidade + acreditação condicional + paginação DomPDF
- [ ] Nenhum TODO/FIXME
- [ ] Nenhum `any` desnecessário no TypeScript
- [ ] Wizard renumerado/refatorado (sem índices hardcoded quebrados)
- [ ] DomPDF config: `isPhpEnabled => true` para paginação

---

## Resumo de Arquivos

### Criar (~22 arquivos)

| Tipo | Arquivo | Fase |
|------|---------|------|
| Migration | `2026_04_10_100001_add_normative_fields_to_equipment_calibrations.php` | 1 |
| Migration | `2026_04_10_100002_add_normative_fields_to_work_orders.php` | 1 |
| Migration | `2026_04_10_100003_add_environmental_to_calibration_readings.php` | 1 |
| Migration | `2026_04_10_200001_create_linearity_tests_table.php` | 2 |
| Migration | `2026_04_10_300001_create_accreditation_scopes_table.php` | 3 |
| Model | `LinearityTest.php` | 2 |
| Model | `AccreditationScope.php` | 3 |
| Factory | `LinearityTestFactory.php` | 2 |
| Factory | `AccreditationScopeFactory.php` | 3 |
| Controller | `LinearityTestController.php` | 2 |
| Controller | `AccreditationScopeController.php` | 3 |
| FormRequest | `StoreLinearityTestsRequest.php` | 2 |
| FormRequest | `StoreAccreditationScopeRequest.php` | 3 |
| FormRequest | `UpdateAccreditationScopeRequest.php` | 3 |
| Resource | `LinearityTestResource.php` | 4 |
| Resource | `AccreditationScopeResource.php` | 4 |
| Hook | `useLinearityCalculations.ts` | 7 |
| Widget | `StandardWeightExpiryAlerts.tsx` | 8 |
| Test | `LinearityTestControllerTest.php` | 2 |
| Test | `AccreditationScopeTest.php` | 3 |
| Test | `EmaCalculatorEdgeCaseTest.php` | 10 |
| Test | `ExpiredStandardBlocksTest.php` | 10 |
| Test | `StandardWeightTenantIsolationTest.php` | 10 |
| Test | `MaintenanceToCalibrationFlowTest.php` | 10 |

### Editar (~12 arquivos)

| Arquivo | Mudança | Fase |
|---------|---------|------|
| `EquipmentCalibration.php` | +6 fillable, +3 casts, +2 relationships | 1, 2 |
| `WorkOrder.php` | +3 fillable, +1 cast | 1 |
| `CalibrationReading.php` | +2 fillable, +2 casts | 1 |
| `CalibrationCertificateService.php` | +acreditação, +padrão vencido, +linearidade eager load | 3, 4 |
| `CalibrationWizardService.php` | +linearidade na validação | 4 |
| `calibration-certificate.blade.php` | +paginação DomPDF, +linearidade, +acreditação, +ajuste | 4, 5 |
| `CalibrationCriticalAnalysis.tsx` | +3 campos (aceite, procedimento) | 6 |
| `CalibrationWizardPage.tsx` | +step linearidade, refatorar índices | 7 |
| `calibration.ts` (types) | +LinearityTest interface | 7 |
| `BeforeAfterPhotos.tsx` | +seção textual condition_as_found/left | 9 |
| `PermissionsSeeder.php` | +accreditation.scope.manage | 3 |
| Arquivo de rotas (`advanced-lots.php` + `equipment-platform.php`) | +rotas linearidade + accreditation | 2, 3 |

### Dívida técnica documentada

- WorkOrder com ~271 campos fillable — candidato a extração de campos de calibração para tabela pivô em sprint futuro
