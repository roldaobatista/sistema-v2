# Plano de Implementacao — Motor de Jornada Operacional

**Projeto:** Kalibrium ERP
**Data:** 2026-04-09
**Autor:** Rolda + Claude
**Stack:** Laravel (PHP) + React + TypeScript + MySQL 8
**Brainstorming:** `_bmad-output/brainstorming/brainstorming-session-2026-04-09-motor-jornada-operacional.md`
**Especificacao Funcional:** `docs/plans/especificacao-funcional-motor-jornada.md`
**Base Legal:** CLT (DL 5452), Portaria MTP 671/2021, eSocial v S-1.3, LGPD (L13709), ANPD

---

## Resumo Executivo

O Kalibrium ja possui ~80% das pecas (250+ arquivos em HR, OS, Fleet, Technician). O plano foca em **conectar as pecas com inteligencia** criando o Motor de Jornada Operacional — um orquestrador que integra Ponto, OS, Deslocamento, Banco de Horas, Viagem, Folha, eSocial e Auditoria em um fluxo unico e coerente para tecnico de campo.

### Documentos de Referencia

- **Especificacao Funcional Completa:** `docs/plans/especificacao-funcional-motor-jornada.md` — 14 secoes, 27 entidades, 17 relatorios, 13 criterios de aceite
- **Brainstorming + Gap Analysis:** `_bmad-output/brainstorming/brainstorming-session-2026-04-09-motor-jornada-operacional.md`
- **PRD Kalibrium:** `docs/PRD-KALIBRIUM.md` — RFs, gaps conhecidos (v3.2+). Validar estado atual via grep direto no código.

### Principios Arquiteturais (da Especificacao)

1. **Regime por colaborador** — controle integral, por excecao, art.62 CLT, plantao/sobreaviso, 12x36, escala personalizada
2. **Jornada contratual SEPARADA da jornada realizada** — eSocial exige contratual, Portaria 671 exige realizada
3. **Tudo vinculado a evidencia** — OS, cliente, local, geo, dispositivo, veiculo, comprovante, justificativa, aprovador, trilha
4. **Deslocamento NAO substitui ponto** — sugere marcacao, politica parametrizavel
5. **Marcacao NUNCA e cega** — sempre tem contexto (ponto simples, vinculado a OS, viagem, base, excecao)

### 3 Camadas de Implementacao

- **Camada 1 — Nucleo Legal:** ponto, espelho, AFD/AEJ, banco de horas, fechamento, folha/eSocial
- **Camada 2 — Operacao de Campo:** deslocamento, check-in, vinculo OS, despesas, viagem, produtividade
- **Camada 3 — Inteligencia:** custo real/OS, ranking produtividade, mapa jornada, alertas risco trabalhista, previsao HE

---

## Fase 1 — Motor de Classificacao do Tempo + Orquestrador de Jornada

**Objetivo:** Criar a peca central que falta — o motor inteligente que classifica blocos de tempo e orquestra o fluxo ponta-a-ponta entre OS, Ponto e Banco de Horas.

**Por que primeiro:** Sem o classificador, cada subsistema continua com visao isolada do tempo. Tudo depende disso.

### Etapa 1.1 — Enum TimeClassification + Migration JourneyDay

**Arquivos a criar/modificar:**

```
backend/app/Enums/TimeClassificationType.php          (CRIAR)
backend/app/Models/JourneyDay.php                      (CRIAR)
backend/app/Models/JourneyBlock.php                    (CRIAR)
backend/database/migrations/XXXX_create_journey_days_table.php    (CRIAR)
backend/database/migrations/XXXX_create_journey_blocks_table.php  (CRIAR)
```

**Detalhes da migration `journey_days`:**

```php
Schema::create('journey_days', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('user_id')->constrained();           // tecnico/funcionario
    $table->date('reference_date');
    $table->string('regime_type');                          // clt_mensal, clt_6meses, cct_anual
    $table->integer('total_minutes_worked')->default(0);
    $table->integer('total_minutes_overtime')->default(0);
    $table->integer('total_minutes_travel')->default(0);
    $table->integer('total_minutes_wait')->default(0);
    $table->integer('total_minutes_break')->default(0);
    $table->integer('total_minutes_overnight')->default(0);
    $table->integer('total_minutes_oncall')->default(0);
    $table->string('operational_approval_status')->default('pending');  // pending, approved, rejected
    $table->foreignId('operational_approver_id')->nullable()->constrained('users');
    $table->timestamp('operational_approved_at')->nullable();
    $table->string('hr_approval_status')->default('pending');           // pending, approved, rejected
    $table->foreignId('hr_approver_id')->nullable()->constrained('users');
    $table->timestamp('hr_approved_at')->nullable();
    $table->boolean('is_closed')->default(false);
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['tenant_id', 'user_id', 'reference_date']);
    $table->index(['tenant_id', 'reference_date']);
});
```

**Detalhes da migration `journey_blocks`:**

```php
Schema::create('journey_blocks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('journey_day_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->string('classification');                      // TimeClassificationType enum
    $table->timestamp('started_at');
    $table->timestamp('ended_at')->nullable();
    $table->integer('duration_minutes')->nullable();
    $table->foreignId('work_order_id')->nullable()->constrained();
    $table->foreignId('time_clock_entry_id')->nullable()->constrained();
    $table->foreignId('fleet_trip_id')->nullable()->constrained();
    $table->foreignId('schedule_id')->nullable()->constrained();
    $table->json('metadata')->nullable();                  // dados extras (geoloc, device, etc)
    $table->string('source');                              // clock, os_checkin, displacement, manual
    $table->boolean('is_auto_classified')->default(true);
    $table->boolean('is_manually_adjusted')->default(false);
    $table->foreignId('adjusted_by')->nullable()->constrained('users');
    $table->text('adjustment_reason')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['tenant_id', 'user_id', 'started_at']);
    $table->index(['journey_day_id', 'classification']);
});
```

**Enum TimeClassificationType:**

```php
enum TimeClassificationType: string
{
    case JORNADA_NORMAL = 'jornada_normal';
    case HORA_EXTRA = 'hora_extra';
    case INTERVALO = 'intervalo';
    case DESLOCAMENTO_CLIENTE = 'deslocamento_cliente';
    case DESLOCAMENTO_ENTRE = 'deslocamento_entre';
    case ESPERA_LOCAL = 'espera_local';
    case EXECUCAO_SERVICO = 'execucao_servico';
    case ALMOCO_VIAGEM = 'almoco_viagem';
    case PERNOITE = 'pernoite';
    case SOBREAVISO = 'sobreaviso';
    case PLANTAO = 'plantao';
    case TEMPO_IMPRODUTIVO = 'tempo_improdutivo';
    case AUSENCIA = 'ausencia';
    case ATESTADO = 'atestado';
    case FOLGA = 'folga';
    case COMPENSACAO = 'compensacao';
    case ADICIONAL_NOTURNO = 'adicional_noturno';
    case DSR = 'dsr';
}
```

**Testes (RED primeiro):**
```
backend/tests/Feature/Journey/JourneyDayModelTest.php
backend/tests/Unit/Enums/TimeClassificationTypeTest.php
```

- [ ] Testar criacao de JourneyDay com tenant isolation
- [ ] Testar criacao de JourneyBlock vinculado a JourneyDay
- [ ] Testar unique constraint (tenant + user + date)
- [ ] Testar soft delete em cascata
- [ ] Testar que todos os valores do enum sao validos
- [ ] Testar cross-tenant 404

**Checkpoint:** Migration roda, models funcionam, testes passam.

---

### Etapa 1.2 — TimeClassificationEngine Service

**Arquivos a criar:**

```
backend/app/Services/Journey/TimeClassificationEngine.php     (CRIAR)
backend/app/Services/Journey/JourneyPolicyResolver.php        (CRIAR)
backend/app/Models/JourneyPolicy.php                          (CRIAR)
backend/database/migrations/XXXX_create_journey_policies_table.php (CRIAR)
```

**Responsabilidades do TimeClassificationEngine:**

1. Receber todos os eventos do dia de um tecnico (batidas, check-ins OS, deslocamentos, breaks)
2. Montar timeline cronologica
3. Classificar cada bloco de tempo na categoria correta
4. Respeitar regras da JourneyPolicy da empresa
5. Gerar/atualizar JourneyBlocks no JourneyDay

**JourneyPolicy (regras parametrizaveis por empresa):**

```php
Schema::create('journey_policies', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('regime_type');                    // clt_mensal, clt_6meses, cct_anual
    $table->integer('daily_hours_limit')->default(480);        // 8h em minutos
    $table->integer('weekly_hours_limit')->default(2640);      // 44h em minutos
    $table->integer('monthly_hours_limit')->nullable();
    $table->integer('break_minutes')->default(60);             // intervalo obrigatorio
    $table->boolean('displacement_counts_as_work')->default(false);
    $table->boolean('wait_time_counts_as_work')->default(true);
    $table->boolean('travel_meal_counts_as_break')->default(true);
    $table->boolean('auto_suggest_clock_on_displacement')->default(true);
    $table->boolean('pre_assigned_break')->default(false);     // pre-assinalacao
    $table->integer('overnight_min_hours')->default(11);       // horas fora base = pernoite
    $table->integer('oncall_multiplier_percent')->default(33); // % sobreaviso sobre hora
    $table->integer('overtime_50_percent_limit')->nullable();   // limite HE 50%
    $table->integer('overtime_100_percent_limit')->nullable();  // apos = 100%
    $table->boolean('saturday_is_overtime')->default(false);
    $table->boolean('sunday_is_overtime')->default(true);
    $table->json('custom_rules')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['tenant_id', 'is_active']);
});
```

**Logica do Engine (pseudocodigo):**

```
function classifyDay(userId, date, policy):
    events = collectAllEvents(userId, date)
    // events = batidas ponto + check-ins OS + deslocamentos + breaks

    timeline = buildTimeline(events)  // ordenar cronologicamente

    for each gap in timeline:
        block = new JourneyBlock
        block.classification = resolveClassification(gap, policy)
        // regras:
        //   - entre batida entrada e OS check-in = deslocamento_cliente (se policy permite)
        //   - durante OS = execucao_servico
        //   - entre OS e proxima OS = deslocamento_entre
        //   - break registrado = intervalo
        //   - apos limite diario = hora_extra
        //   - noturno (22h-5h) = adicional_noturno overlay
        //   - sem atividade por >X min = espera_local ou improdutivo

    journeyDay = upsertJourneyDay(userId, date, blocks)
    journeyDay.recalculateTotals()

    return journeyDay
```

**Testes (RED primeiro):**
```
backend/tests/Feature/Journey/TimeClassificationEngineTest.php
backend/tests/Feature/Journey/JourneyPolicyResolverTest.php
```

- [ ] Testar dia simples: entrada 08h → almoco 12h-13h → saida 17h = 8h jornada + 1h intervalo
- [ ] Testar com OS: entrada 08h → deslocamento 08:30 → check-in OS 09h → checkout 11h → deslocamento 11:15 → check-in OS2 11:45 → checkout 16h → retorno 16:30 → saida 17h
- [ ] Testar hora extra: trabalhou alem do limite diario da policy
- [ ] Testar adicional noturno: blocos entre 22h-5h
- [ ] Testar sobreaviso/plantao: tecnico em sobreaviso chamado para OS emergencial
- [ ] Testar pernoite: tecnico fora da base apos X horas (policy)
- [ ] Testar regra "deslocamento NAO conta como jornada" vs "deslocamento CONTA"
- [ ] Testar pre-assinalacao de intervalo
- [ ] Testar policy diferente por sindicato/CCT
- [ ] Testar reclassificacao apos ajuste manual

**Checkpoint:** Engine classifica cenarios reais, testes passam com diferentes policies.

---

### Etapa 1.3 — JourneyOrchestratorService

**Arquivos a criar/modificar:**

```
backend/app/Services/Journey/JourneyOrchestratorService.php   (CRIAR)
backend/app/Listeners/Journey/OnTimeClockEvent.php            (CRIAR)
backend/app/Listeners/Journey/OnWorkOrderCheckin.php          (CRIAR)
backend/app/Listeners/Journey/OnDisplacementEvent.php         (CRIAR)
backend/app/Events/JourneyDayUpdated.php                      (CRIAR)
backend/app/Events/JourneyBlockCreated.php                    (CRIAR)
```

**Responsabilidades do Orquestrador:**

1. Escutar eventos de TimeClockEntry, WorkOrder (check-in/check-out), Displacement
2. Chamar TimeClassificationEngine para reclassificar o dia
3. Propagar mudancas para HourBank (banco de horas)
4. Atualizar custo real na WorkOrder
5. Disparar evento JourneyDayUpdated para outros listeners

**Fluxo:**
```
TimeClockEntry criado → OnTimeClockEvent → JourneyOrchestrator.processEvent()
  → TimeClassificationEngine.classifyDay()
  → HourBankService.updateBalance() (existente, expandir)
  → WorkOrder.updateRealCost() (se vinculado)
  → Event: JourneyDayUpdated
```

**Testes:**
```
backend/tests/Feature/Journey/JourneyOrchestratorServiceTest.php
```

- [ ] Testar fluxo completo: batida ponto → OS check-in → OS checkout → saida → JourneyDay consolidado
- [ ] Testar que HourBank recebe atualizacao automatica
- [ ] Testar que WorkOrder recebe custo real
- [ ] Testar idempotencia (reprocessar mesmo dia nao duplica)
- [ ] Testar rollback se um passo falha (transaction)
- [ ] Testar tenant isolation no fluxo completo

**Checkpoint:** Fluxo ponta-a-ponta funciona: batida → classificacao → banco de horas → custo OS.

---

### Etapa 1.4 — API + Controller JourneyDay

**Arquivos a criar:**

```
backend/app/Http/Controllers/Api/V1/Journey/JourneyDayController.php        (CRIAR)
backend/app/Http/Controllers/Api/V1/Journey/JourneyBlockController.php      (CRIAR)
backend/app/Http/Controllers/Api/V1/Journey/JourneyPolicyController.php     (CRIAR)
backend/app/Http/Requests/Journey/IndexJourneyDayRequest.php                (CRIAR)
backend/app/Http/Requests/Journey/AdjustJourneyBlockRequest.php             (CRIAR)
backend/app/Http/Requests/Journey/StoreJourneyPolicyRequest.php             (CRIAR)
backend/app/Http/Requests/Journey/UpdateJourneyPolicyRequest.php            (CRIAR)
backend/app/Http/Resources/Journey/JourneyDayResource.php                   (CRIAR)
backend/app/Http/Resources/Journey/JourneyBlockResource.php                 (CRIAR)
backend/app/Policies/JourneyDayPolicy.php                                   (CRIAR)
backend/app/Policies/JourneyPolicyPolicy.php                                (CRIAR)
backend/routes/api/journey.php                                              (CRIAR)
```

**Endpoints:**

```
GET    /api/v1/journey/days                      — listar dias (com filtros: user, periodo)
GET    /api/v1/journey/days/{id}                 — detalhe do dia com blocks
POST   /api/v1/journey/days/{id}/reclassify      — forcar reclassificacao
POST   /api/v1/journey/blocks/{id}/adjust         — ajuste manual de bloco
GET    /api/v1/journey/policies                   — listar policies do tenant
POST   /api/v1/journey/policies                   — criar policy
PUT    /api/v1/journey/policies/{id}              — atualizar policy
DELETE /api/v1/journey/policies/{id}              — desativar policy
```

**Testes:**
```
backend/tests/Feature/Journey/JourneyDayControllerTest.php
backend/tests/Feature/Journey/JourneyPolicyControllerTest.php
```

- [ ] Testar CRUD completo de JourneyPolicy com tenant isolation
- [ ] Testar listagem de JourneyDays com paginacao e filtros
- [ ] Testar detalhe de JourneyDay com blocks eager loaded
- [ ] Testar ajuste manual de bloco com auditoria
- [ ] Testar reclassificacao forcada
- [ ] Testar permissoes (403)
- [ ] Testar cross-tenant (404)
- [ ] Testar validacao (422)

**Checkpoint:** API completa, testada, com permissoes e tenant isolation.

---

### Etapa 1.5 — Frontend: Timeline de Jornada + Config de Policy

**Arquivos a criar:**

```
frontend/src/pages/rh/JourneyDayPage.tsx                    (CRIAR)
frontend/src/pages/rh/JourneyPoliciesPage.tsx               (CRIAR)
frontend/src/components/journey/JourneyTimeline.tsx          (CRIAR)
frontend/src/components/journey/JourneyBlockCard.tsx         (CRIAR)
frontend/src/components/journey/JourneyDaySummary.tsx        (CRIAR)
frontend/src/components/journey/JourneyPolicyForm.tsx        (CRIAR)
frontend/src/lib/api/journey.ts                             (CRIAR)
frontend/src/types/journey.ts                               (CRIAR)
```

**Telas:**

1. **JourneyDayPage** — Timeline visual do dia do tecnico com blocos coloridos por classificacao
2. **JourneyPoliciesPage** — CRUD de politicas de jornada (admin)
3. **TechJourneyPage** — Visao do tecnico do seu proprio dia (autoatendimento)

**Checkpoint:** Telas funcionais, conectadas a API, mostrando dados reais.

---

### Gate Final Fase 1

- [ ] Migration roda sem erro
- [ ] Schema dump SQLite regenerado
- [ ] Todos os testes passam (pest --parallel)
- [ ] TimeClassificationEngine classifica corretamente 10+ cenarios
- [ ] JourneyOrchestrator propaga eventos entre subsistemas
- [ ] API funcional com tenant isolation
- [ ] Frontend exibe timeline de jornada
- [ ] Nenhum TODO/FIXME pendente

---

## Fase 2 — Banco de Horas Robusto + Aprovacao Dual

**Objetivo:** Expandir banco de horas para multi-regime e implementar dupla aprovacao (operacional + RH).

### Etapa 2.1 — HourBankPolicy Model + Migration

**Arquivos a criar/modificar:**

```
backend/app/Models/HourBankPolicy.php                              (CRIAR)
backend/database/migrations/XXXX_create_hour_bank_policies_table.php (CRIAR)
backend/app/Services/Journey/HourBankPolicyService.php             (CRIAR)
```

**Migration `hour_bank_policies`:**

```php
Schema::create('hour_bank_policies', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('name');
    $table->string('regime_type');               // individual_mensal, individual_6meses, cct_anual
    $table->integer('compensation_period_days'); // 30, 180, 365
    $table->integer('max_positive_balance_minutes')->nullable();  // teto positivo
    $table->integer('max_negative_balance_minutes')->nullable();  // teto negativo
    $table->boolean('block_on_negative_exceeded')->default(true);
    $table->boolean('auto_compensate')->default(false);
    $table->boolean('convert_expired_to_payment')->default(false);
    $table->decimal('overtime_50_multiplier', 4, 2)->default(1.50);
    $table->decimal('overtime_100_multiplier', 4, 2)->default(2.00);
    $table->json('applicable_roles')->nullable();     // cargos que usam esta policy
    $table->json('applicable_teams')->nullable();     // equipes
    $table->json('applicable_unions')->nullable();    // sindicatos/CCT
    $table->boolean('requires_two_level_approval')->default(true);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
```

**Testes:**
- [ ] Testar criacao de policy com diferentes regimes
- [ ] Testar aplicacao de policy por cargo/equipe/sindicato
- [ ] Testar vencimento de saldo (compensacao apos periodo)
- [ ] Testar bloqueio por saldo negativo excessivo
- [ ] Testar conversao de saldo expirado em pagamento
- [ ] Testar multiplas policies ativas no mesmo tenant

**Checkpoint:** Policy configuravel por regime, testada.

---

### Etapa 2.2 — Expandir HourBank Service Existente

**Arquivos a modificar:**

```
backend/app/Services/HR/HrAdvancedService.php                    (MODIFICAR — expandir banco horas)
backend/app/Http/Controllers/Api/V1/Hr/ReportsController.php     (MODIFICAR — novos relatorios)
```

**Novos comportamentos:**
- Saldo por dia, semana, mes e competencia
- Compensacao automatica baseada na policy
- Vencimento e alerta de saldo proximo de expirar
- Fechamento mensal com snapshot
- Relatorios de forecast (projecao)

**Testes:**
- [ ] Testar calculo de saldo com diferentes multiplicadores
- [ ] Testar compensacao automatica dentro do periodo
- [ ] Testar vencimento de saldo apos periodo
- [ ] Testar fechamento mensal gera snapshot correto
- [ ] Testar forecast de saldo futuro

---

### Etapa 2.3 — DualApprovalService + JourneyApproval Model

**Arquivos a criar:**

```
backend/app/Models/JourneyApproval.php                                    (CRIAR)
backend/database/migrations/XXXX_create_journey_approvals_table.php       (CRIAR)
backend/app/Services/Journey/DualApprovalService.php                      (CRIAR)
backend/app/Http/Controllers/Api/V1/Journey/JourneyApprovalController.php (CRIAR)
backend/app/Http/Requests/Journey/ApproveJourneyRequest.php               (CRIAR)
backend/app/Http/Requests/Journey/RejectJourneyRequest.php                (CRIAR)
backend/app/Notifications/JourneyPendingApproval.php                      (CRIAR)
```

**Fluxo de dupla aprovacao:**

```
JourneyDay fechado → Notifica gestor operacional
  → Gestor aprova/rejeita (confirma campo: presenca, tempo, espera, retrabalho)
  → Se aprovado → Notifica RH/DP
  → RH valida reflexo em folha (horas extras, banco, adicional)
  → Se ambos aprovados → JourneyDay.is_closed = true → alimenta folha
```

**Testes:**
- [ ] Testar fluxo completo: pendente → aprovado operacional → aprovado RH → fechado
- [ ] Testar rejeicao operacional bloqueia avanço para RH
- [ ] Testar rejeicao RH reabre para ajuste
- [ ] Testar escalacao por timeout
- [ ] Testar permissoes (quem pode aprovar cada nivel)
- [ ] Testar notificacoes disparadas

**Checkpoint:** Aprovacao dual funcional com notificacoes.

---

### Etapa 2.4 — Frontend: Telas de Aprovacao + Banco de Horas Expandido

**Arquivos a criar/modificar:**

```
frontend/src/pages/rh/JourneyApprovalPage.tsx              (CRIAR)
frontend/src/pages/rh/HourBankPoliciesPage.tsx             (CRIAR)
frontend/src/components/journey/ApprovalQueue.tsx           (CRIAR)
frontend/src/components/journey/DualApprovalStatus.tsx      (CRIAR)
frontend/src/pages/rh/HourBankPage.tsx                     (MODIFICAR — expandir)
frontend/src/pages/tech/TechHourBankPage.tsx               (CRIAR — visao tecnico)
```

### Gate Final Fase 2

- [ ] Banco de horas suporta multiplos regimes
- [ ] Dupla aprovacao funcional (operacional + RH)
- [ ] Saldo com vencimento e alerta
- [ ] Fechamento mensal com snapshot
- [ ] Testes passam
- [ ] Frontend conectado

---

## Fase 3 — Viagem e Pernoite

**Objetivo:** Completar fluxo de tecnico externo com viagem, pernoite, diarias e prestacao de contas.

### Etapa 3.1 — Models de Viagem

**Arquivos a criar:**

```
backend/app/Models/Journey/TravelRequest.php                        (CRIAR)
backend/app/Models/Journey/OvernightStay.php                        (CRIAR)
backend/app/Models/Journey/TravelAdvance.php                        (CRIAR)
backend/app/Models/Journey/TravelExpenseReport.php                  (CRIAR)
backend/app/Models/Journey/TravelExpenseItem.php                    (CRIAR)
backend/database/migrations/XXXX_create_travel_requests_table.php   (CRIAR)
backend/database/migrations/XXXX_create_overnight_stays_table.php   (CRIAR)
backend/database/migrations/XXXX_create_travel_advances_table.php   (CRIAR)
backend/database/migrations/XXXX_create_travel_expense_reports_table.php  (CRIAR)
backend/database/migrations/XXXX_create_travel_expense_items_table.php    (CRIAR)
```

**Migration `travel_requests`:**

```php
Schema::create('travel_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('user_id')->constrained();          // tecnico viajante
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->string('status')->default('pending');          // pending, approved, in_progress, completed, cancelled
    $table->string('destination');
    $table->text('purpose');
    $table->date('departure_date');
    $table->date('return_date');
    $table->time('departure_time')->nullable();
    $table->time('return_time')->nullable();
    $table->integer('estimated_days');
    $table->decimal('daily_allowance_amount', 10, 2)->nullable();
    $table->decimal('total_advance_requested', 10, 2)->nullable();
    $table->boolean('requires_vehicle')->default(false);
    $table->foreignId('fleet_vehicle_id')->nullable()->constrained();
    $table->boolean('requires_overnight')->default(false);
    $table->integer('rest_days_after')->default(0);        // dias de descanso apos viagem
    $table->boolean('overtime_authorized')->default(false);
    $table->json('work_orders')->nullable();               // OS vinculadas
    $table->json('itinerary')->nullable();                 // roteiro detalhado
    $table->json('meal_policy')->nullable();               // almoco/janta/hotel
    $table->timestamps();
    $table->softDeletes();
});
```

**Migration `overnight_stays`:**

```php
Schema::create('overnight_stays', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('travel_request_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->date('stay_date');
    $table->string('hotel_name')->nullable();
    $table->string('city');
    $table->string('state')->nullable();
    $table->decimal('cost', 10, 2)->nullable();
    $table->string('receipt_path')->nullable();            // comprovante
    $table->string('status')->default('pending');          // pending, approved, rejected
    $table->timestamps();
});
```

**Migration `travel_advances`:**

```php
Schema::create('travel_advances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('travel_request_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->decimal('amount', 10, 2);
    $table->string('status')->default('pending');          // pending, approved, paid, accounted
    $table->date('paid_at')->nullable();
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**Migration `travel_expense_reports` + `travel_expense_items`:**

```php
Schema::create('travel_expense_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('travel_request_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->decimal('total_expenses', 10, 2)->default(0);
    $table->decimal('total_advances', 10, 2)->default(0);
    $table->decimal('balance', 10, 2)->default(0);         // positivo = devolver, negativo = reembolsar
    $table->string('status')->default('draft');             // draft, submitted, approved, rejected
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->timestamps();
});

Schema::create('travel_expense_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('travel_expense_report_id')->constrained();
    $table->string('type');                                 // alimentacao, transporte, hospedagem, pedagio, combustivel, outros
    $table->string('description');
    $table->decimal('amount', 10, 2);
    $table->date('expense_date');
    $table->string('receipt_path')->nullable();
    $table->boolean('is_within_policy')->default(true);
    $table->timestamps();
});
```

**Testes:**
- [ ] Testar CRUD completo de TravelRequest com aprovacao
- [ ] Testar OvernightStay vinculado a viagem
- [ ] Testar TravelAdvance com status flow
- [ ] Testar TravelExpenseReport com calculo de balance
- [ ] Testar integracao com JourneyDay (classificacao pernoite)
- [ ] Testar tenant isolation em todos os models

**Checkpoint:** Models de viagem completos com testes.

---

### Etapa 3.2 — TravelPolicyService + Controllers + API

**Arquivos a criar:**

```
backend/app/Services/Journey/TravelPolicyService.php                         (CRIAR)
backend/app/Http/Controllers/Api/V1/Journey/TravelRequestController.php      (CRIAR)
backend/app/Http/Controllers/Api/V1/Journey/TravelExpenseController.php      (CRIAR)
backend/app/Http/Requests/Journey/StoreTravelRequestRequest.php              (CRIAR)
backend/app/Http/Requests/Journey/SubmitExpenseReportRequest.php             (CRIAR)
backend/routes/api/journey.php                                               (MODIFICAR)
```

### Etapa 3.3 — Frontend: Telas de Viagem

**Arquivos a criar:**

```
frontend/src/pages/rh/TravelRequestsPage.tsx                (CRIAR)
frontend/src/pages/rh/TravelExpensesPage.tsx                 (CRIAR)
frontend/src/pages/tech/TechTravelPage.tsx                   (CRIAR)
frontend/src/components/journey/TravelRequestForm.tsx        (CRIAR)
frontend/src/components/journey/ExpenseReportForm.tsx        (CRIAR)
frontend/src/components/journey/OvernightStayCard.tsx        (CRIAR)
frontend/src/types/travel.ts                                 (CRIAR)
frontend/src/lib/api/travel.ts                               (CRIAR)
```

### Gate Final Fase 3

- [ ] TravelRequest com fluxo completo (solicitacao → aprovacao → execucao → prestacao de contas)
- [ ] Pernoite registrado e classificado no Motor de Jornada
- [ ] Adiantamento e prestacao de contas funcionais
- [ ] Integracao com Fleet (veiculo da viagem)
- [ ] Testes passam
- [ ] Frontend conectado

---

## Fase 4 — Gestao de Pessoas de Campo + LGPD

**Objetivo:** Habilitacoes com vencimento, bloqueio de OS e compliance LGPD para biometria.

### Etapa 4.1 — TechnicianCertification + Vencimento

**Arquivos a criar:**

```
backend/app/Models/TechnicianCertification.php                          (CRIAR)
backend/database/migrations/XXXX_create_technician_certifications_table.php (CRIAR)
backend/app/Services/Journey/TechnicianEligibilityService.php           (CRIAR)
backend/app/Console/Commands/CheckExpiringCertifications.php            (CRIAR)
backend/app/Notifications/CertificationExpiringNotification.php         (CRIAR)
```

**Migration:**

```php
Schema::create('technician_certifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->string('type');           // cnh, nr10, nr35, aso, treinamento, certificado
    $table->string('name');
    $table->string('number')->nullable();
    $table->date('issued_at');
    $table->date('expires_at')->nullable();
    $table->string('issuer')->nullable();
    $table->string('document_path')->nullable();
    $table->string('status')->default('valid');  // valid, expiring_soon, expired, revoked
    $table->json('required_for_service_types')->nullable();  // tipos de OS que exigem
    $table->timestamps();
    $table->softDeletes();

    $table->index(['tenant_id', 'user_id', 'type']);
    $table->index(['tenant_id', 'expires_at']);
});
```

**TechnicianEligibilityService:**
- Verificar se tecnico tem todas as habilitacoes validas para o tipo de OS
- **Bloquear agendamento** se certificacao obrigatoria esta vencida
- Alertar 30/15/7 dias antes do vencimento
- Comando artisan diario para verificar vencimentos

**Testes:**
- [ ] Testar bloqueio de OS quando CNH vencida
- [ ] Testar alerta de vencimento proximo
- [ ] Testar que tecnico com certificacao valida eh liberado
- [ ] Testar multiple certificacoes por tecnico
- [ ] Testar integracao com AutoAssignmentService (nao sugerir tecnico sem habilitacao)

---

### Etapa 4.2 — LGPD BiometricConsent

**Arquivos a criar:**

```
backend/app/Models/BiometricConsent.php                              (CRIAR)
backend/database/migrations/XXXX_create_biometric_consents_table.php (CRIAR)
backend/app/Services/Journey/BiometricComplianceService.php          (CRIAR)
backend/app/Console/Commands/PurgeBiometricData.php                  (CRIAR)
```

**Migration:**

```php
Schema::create('biometric_consents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->string('data_type');              // geolocation, facial, fingerprint, voice
    $table->string('legal_basis');            // consent, legitimate_interest, legal_obligation
    $table->text('purpose');
    $table->date('consented_at');
    $table->date('expires_at')->nullable();
    $table->date('revoked_at')->nullable();
    $table->string('alternative_method')->nullable();  // metodo alternativo se recusar
    $table->integer('retention_days')->default(365);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**BiometricComplianceService:**
- Verificar consentimento antes de capturar biometria
- Criptografar dados biometricos em repouso
- Expurgo automatico apos periodo de retencao
- Gerar relatorio de impacto (RIPD)
- Oferecer metodo alternativo quando biometria nao consentida

### Etapa 4.3 — Frontend

```
frontend/src/pages/rh/CertificationsPage.tsx               (CRIAR)
frontend/src/pages/rh/BiometricConsentPage.tsx              (CRIAR)
frontend/src/pages/tech/TechCertificationsPage.tsx          (CRIAR)
frontend/src/components/journey/CertificationAlert.tsx      (CRIAR)
```

### Gate Final Fase 4

- [ ] Bloqueio de OS por certificacao vencida funcional
- [ ] Alertas de vencimento configurados
- [ ] Consentimento LGPD registrado antes de biometria
- [ ] Expurgo automatico configurado
- [ ] Integracao com AutoAssignment
- [ ] Testes passam

---

## Fase 5 — Modo Offline + Autoatendimento Completo

**Objetivo:** Tecnico sem conectividade consegue operar e depois sincronizar. Completar autoatendimento.

### Etapa 5.1 — Offline Queue Service (Frontend)

**Arquivos a criar/modificar:**

```
frontend/src/lib/offline/OfflineQueue.ts                  (CRIAR/EXPANDIR)
frontend/src/lib/offline/SyncManager.ts                   (CRIAR/EXPANDIR)
frontend/src/lib/offline/ConflictResolver.ts              (CRIAR)
frontend/src/hooks/useOfflineStatus.ts                    (CRIAR)
frontend/src/hooks/useOfflineQueue.ts                     (CRIAR)
```

**Funcionalidades:**
- IndexedDB para armazenar eventos pendentes
- Fila idempotente (cada evento com UUID, servidor rejeita duplicatas)
- Sync automatico quando reconectar
- Resolucao de conflitos (last-write-wins ou manual)
- Indicador visual de status offline/online
- Batida de ponto offline com timestamp local
- Check-in/checkout OS offline

### Etapa 5.2 — Backend: Idempotent Event Receiver

```
backend/app/Http/Controllers/Api/V1/Journey/OfflineSyncController.php  (CRIAR)
backend/app/Services/Journey/OfflineSyncService.php                    (CRIAR)
```

**Endpoint:**
```
POST /api/v1/journey/sync — recebe batch de eventos offline
```
- Cada evento tem UUID + timestamp local
- Servidor rejeita UUID duplicado (idempotente)
- Processa na ordem do timestamp local
- Retorna status de cada evento (accepted, duplicate, conflict)

### Etapa 5.3 — Completar Autoatendimento do Tecnico

**Verificar e completar:**

```
frontend/src/pages/tech/TechHourBankPage.tsx        (CRIAR se nao existir)
frontend/src/pages/tech/TechClockCorrectionPage.tsx (CRIAR se nao existir)
frontend/src/pages/tech/TechPayslipPage.tsx         (CRIAR se nao existir)
frontend/src/pages/tech/TechEpiReceiptPage.tsx      (CRIAR se nao existir)
```

- [ ] Saldo de banco de horas na visao do tecnico
- [ ] Justificativa de marcacao
- [ ] Solicitacao de correcao de ponto
- [ ] Consulta de holerite e comissoes
- [ ] Confirmacao de recebimento de EPI/ferramenta

### Gate Final Fase 5

- [ ] Batida offline funciona e sincroniza
- [ ] Check-in OS offline funciona
- [ ] Conflitos resolvidos corretamente
- [ ] Autoatendimento completo (saldo, correcao, holerite, EPI)
- [ ] Testes E2E do fluxo offline → online

---

## Fase 6 — eSocial + Folha Completa

**Objetivo:** Integracao real com eSocial (geracao XML, envio, retorno) e reflexos automaticos em folha.

### Etapa 6.1 — Eventos eSocial

**Arquivos a criar/modificar:**

```
backend/app/Services/ESocial/ESocialEventGenerator.php         (CRIAR)
backend/app/Services/ESocial/ESocialXmlBuilder.php             (CRIAR)
backend/app/Services/ESocial/ESocialSubmitter.php              (CRIAR)
backend/app/Models/ESocialEvent.php                            (CRIAR)
backend/database/migrations/XXXX_create_esocial_events_table.php (CRIAR)
```

**Eventos a implementar:**
- S-1200: Remuneracao (horas extras, adicionais, comissoes)
- S-1210: Pagamentos (reflexo de banco de horas convertido)
- S-2200: Admissao
- S-2299: Desligamento
- S-2230: Afastamento

### Etapa 6.2 — Reflexos Automaticos em Folha

**Modificar:**
```
backend/app/Services/HR/HrAdvancedService.php        (MODIFICAR)
backend/app/Http/Controllers/Api/V1/PayrollController.php (MODIFICAR)
```

**Reflexos de JourneyDay → Folha:**
- Horas extras (50%, 100%) com multiplicadores da policy
- Adicional noturno (22h-5h)
- DSR sobre verbas variaveis
- Faltas e atrasos
- Diarias de viagem com reflexo
- Comissoes por OS
- Banco de horas (saldo convertido)

### Etapa 6.3 — Frontend eSocial

```
frontend/src/pages/rh/ESocialPage.tsx               (MODIFICAR — expandir)
frontend/src/pages/rh/ESocialEventsPage.tsx          (CRIAR)
frontend/src/components/esocial/EventStatusCard.tsx  (CRIAR)
frontend/src/components/esocial/XmlPreview.tsx       (CRIAR)
```

### Gate Final Fase 6

- [ ] XML eSocial gerado corretamente para cada evento
- [ ] Reflexos em folha calculados automaticamente a partir de JourneyDay
- [ ] Fechamento mensal gera eventos eSocial
- [ ] Testes de geracao XML com schemas oficiais
- [ ] Frontend mostra status de envio

---

## Resumo de Entregas por Fase

| Fase | Entrega | Models | Services | Testes Estimados |
|------|---------|--------|----------|-----------------|
| 1 | Motor Classificacao + Orquestrador | 3 | 3 | ~40 |
| 2 | Banco Horas Robusto + Aprovacao Dual | 2 | 2 | ~30 |
| 3 | Viagem + Pernoite | 5 | 1 | ~25 |
| 4 | Certificacoes + LGPD | 2 | 2 | ~20 |
| 5 | Offline + Autoatendimento | 0 | 2 | ~15 |
| 6 | eSocial + Folha | 1 | 3 | ~20 |
| **Total** | | **13** | **13** | **~150** |

---

## Dependencias entre Fases

```
Fase 1 ──→ Fase 2 ──→ Fase 6
  │           │
  └──→ Fase 3 ┘
  │
  └──→ Fase 4 (independente)
  │
  └──→ Fase 5 (independente, pode parallelizar com 3 ou 4)
```

- **Fase 1 eh pre-requisito de TUDO** (Motor de Classificacao)
- Fase 2 depende de Fase 1 (banco de horas precisa dos blocos classificados)
- Fase 3 depende de Fase 1 (viagem precisa classificar pernoite)
- Fase 4 e 5 podem rodar em paralelo com Fase 2/3
- Fase 6 depende de Fase 1 + 2 (eSocial precisa dos dados consolidados)

---

## Riscos e Mitigacoes

| # | Risco | Probabilidade | Impacto | Mitigacao |
|---|-------|--------------|---------|-----------|
| 1 | Motor de classificacao com regras trabalhistas erradas | Media | Alto | Validar com advogado trabalhista; regras SEMPRE configuraveis |
| 2 | Automatismo gerando horas/custos indevidos | Media | Alto | Nenhuma regra automatica por padrao; tudo requer configuracao |
| 3 | Banco de horas incompativel com CCT especifica | Alta | Medio | Parametrizacao por sindicato; campo custom_rules JSON |
| 4 | Offline com perda de dados | Baixa | Alto | UUID idempotente + fila local + retry automatico |
| 5 | eSocial schema incompativel | Media | Medio | Validar XML contra schemas oficiais nos testes |
| 6 | LGPD violada em biometria | Baixa | Alto | Consentimento obrigatorio + expurgo + criptografia |
| 7 | Escopo grande demais por fase | Media | Medio | Fases incrementais, cada uma entrega valor isolado |

---

## Convencoes do Plano

- **Toda migration** → regenerar schema dump SQLite
- **Todo model** → usar BelongsToTenant trait
- **Todo controller** → FormRequest com permissao real, paginacao, eager loading
- **Todo endpoint** → teste de sucesso + 422 + 403 + cross-tenant 404
- **TDD** → escrever teste RED antes da implementacao
- **Sequenciamento** → etapa N+1 so apos gate final da etapa N

---

## Reconciliacao: Modelo de Dados da Especificacao vs Kalibrium Existente

A especificacao funcional define 27 entidades. Abaixo o mapeamento com o que ja existe:

| Entidade (Especificacao) | Kalibrium Existente | Status | Acao |
|--------------------------|-------------------|--------|------|
| employee | User + employee fields | Parcial | Expandir com campos faltantes (CBO, matricula, sindicato/CCT) |
| employee_contract | Nao existe como model separado | **GAP** | Criar — eSocial exige separacao contratual |
| employee_schedule | WorkSchedule | Existe | Expandir para regime por colaborador |
| employee_shift | Nao existe separado | **GAP** | Criar — turno especifico dentro da escala |
| time_punch | TimeClockEntry | Existe | Expandir com campos faltantes (branch_id, field_trip_id, vehicle_id) |
| time_punch_event | TimeClockAuditLog (parcial) | Parcial | Expandir ou criar model dedicado |
| time_adjustment_request | TimeClockAdjustment | Existe | Verificar completude |
| time_approval | Nao existe dedicado | **GAP** | Criar — aprovacao de marcacao/ajuste |
| timesheet_month | Nao existe | **GAP** | Criar — fechamento mensal (competencia) |
| bank_hours_ledger | HourBank (parcial) | Parcial | Refatorar para ledger com origem rastreavel |
| bank_hours_rule | Nao existe | **GAP** | Criar — regras por regime/CCT (= HourBankPolicy do plano) |
| field_trip | JourneyEntry + FleetTrip | Parcial | Unificar ou expandir |
| field_trip_expense | TechnicianCashTransaction (parcial) | Parcial | Expandir para prestacao de contas completa |
| travel_advance | TechnicianFundRequest (parcial) | Parcial | Expandir com vinculo a viagem |
| vehicle_usage | FleetTrip + FleetVehicle | Existe | OK — ja maduro |
| work_order_time_link | WorkOrderTimeLog | Existe | Expandir com vinculo a JourneyBlock |
| location_ping | WorkOrderDisplacementLocation | Existe | OK |
| attendance_occurrence | Parcial em leaves/absences | Parcial | Criar model unificado |
| leave_record | Existe em HR | Existe | OK |
| training_record | Existe em HR | Existe | Expandir com vencimento/bloqueio |
| certificate_record | TechnicianSkill (parcial) | Parcial | Expandir para TechnicianCertification |
| equipment_assignment | Nao existe | **GAP** | Criar — entrega de EPI/ferramenta |
| payroll_export_item | PayrollController (parcial) | Parcial | Criar model dedicado |
| esocial_contract_snapshot | Nao existe | **GAP** | Criar |
| esocial_remuneration_snapshot | Nao existe | **GAP** | Criar |
| audit_log | TimeClockAuditLog | Existe | OK — ja com hash chain |
| digital_receipt | Nao existe dedicado | **GAP** | Criar — comprovante PAdES (REP-P) |

### Resumo: 10 GAPs criticos, 8 parciais, 9 existentes

### Permissoes a Criar (Spatie)

```
// Journey
journey.day.view
journey.day.adjust
journey.day.reclassify
journey.policy.manage
journey.approval.operational
journey.approval.hr
journey.close-competence

// Travel
travel.request.create
travel.request.approve
travel.advance.approve
travel.expense.submit
travel.expense.approve

// Certifications
certification.manage
certification.block-os

// Biometric
biometric.consent.manage
biometric.data.access
biometric.data.purge

// Reports (legal)
report.afd.export
report.aej.export
report.espelho.view
report.payroll.export
report.esocial.generate
```

---

## Criterios de Aceite Finais (da Especificacao)

O modulo so e considerado PRONTO quando:

- [ ] Emitir comprovante por marcacao
- [ ] Gerar espelho de ponto
- [ ] Gerar AFD (Arquivo Fonte de Dados)
- [ ] Gerar AEJ (Arquivo Eletronico de Jornada)
- [ ] Manter trilha de ajustes auditavel
- [ ] Suportar banco de horas configuravel por regime juridico
- [ ] Separar jornada contratual de jornada realizada
- [ ] Integrar marcacoes com OS e deslocamento
- [ ] Exportar horas para folha com rubricas corretas
- [ ] Suportar enquadramento por regime de jornada (6 tipos)
- [ ] Bloquear alocacao de tecnico com treinamento vencido (configuravel)
- [ ] Operar offline no mobile com fila criptografada
- [ ] Manter logs auditaveis de ponta a ponta
- [ ] Gerar eventos eSocial (S-1200, S-1210, S-2200, S-2230, S-2299)
