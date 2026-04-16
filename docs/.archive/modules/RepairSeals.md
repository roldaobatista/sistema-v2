# Módulo: Selos de Reparo (RepairSeals)

> **Status:** `planned` | **Tier:** `operational` | **Owner:** Metrologia
> **Bounded Context:** Metrological Intelligence
> **Depende de:** Inmetro, WorkOrders, Core (auth/tenant)

## Visão Geral

Módulo dedicado ao controle profissional e rastreamento completo de **selos de reparo Inmetro** e **lacres** utilizados em calibrações e reparos de balanças. Cada balança calibrada exige que o técnico aplique um selo de reparo e um ou mais lacres, todos com número individual único.

O módulo garante:
- **Rastreabilidade total** do ciclo de vida de cada selo e lacre
- **Accountability por técnico** — saber exatamente o que cada técnico possui
- **Conformidade com prazo PSEI** — registro obrigatório em até 5 dias úteis após uso
- **Integração nativa com PSEI** — envio automático dos selos ao portal do Inmetro
- **Auditoria completa** — histórico de toda movimentação (recebimento, atribuição, uso, devolução)

---

## Domínio e Terminologia

| Termo | Definição |
|-------|-----------|
| **Selo de Reparo** (`seal_reparo`) | Selo oficial do Inmetro aplicado na balança após reparo/calibração. Número único. Deve ser lançado no PSEI. |
| **Lacre** (`seal`) | Lacre de segurança aplicado nos pontos de acesso da balança. Número individual. Evidencia que o equipamento não foi violado. |
| **Lote** (`batch`) | Conjunto de selos/lacres recebidos do Inmetro com faixa numérica definida. |
| **PSEI** | Portal de Serviços de Instrumentos — sistema oficial do Inmetro onde devem ser registrados os selos de reparo. |
| **Prazo de 5 dias** | Regra regulatória: selo deve ser registrado no PSEI em no máximo 5 dias úteis após aplicação na balança. |
| **Técnico bloqueado** | Técnico com selo vencido (>5 dias sem registro PSEI) não pode receber novos selos até regularizar. |

---

## Models e Entidades

### InmetroSeal (evoluído)

Tabela: `inmetro_seals`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | Multi-tenant |
| `batch_id` | FK nullable → repair_seal_batches | Lote de origem |
| `type` | enum | `seal` (lacre) ou `seal_reparo` (selo Inmetro) |
| `number` | string(30) | Número único do selo/lacre |
| `status` | enum | `available`, `assigned`, `used`, `pending_psei`, `registered`, `returned`, `damaged`, `lost` |
| `assigned_to` | FK nullable → users | Técnico atual |
| `assigned_at` | timestamp | Quando foi atribuído |
| `work_order_id` | FK nullable → work_orders | OS onde foi usado |
| `equipment_id` | FK nullable → equipments | Equipamento onde foi aplicado |
| `photo_path` | string | Foto da aplicação (obrigatória) |
| `used_at` | timestamp | Data/hora de uso |
| `deadline_at` | timestamp | Prazo para registro PSEI (auto: used_at + 5 dias úteis) |
| `deadline_status` | enum | `ok`, `warning`, `critical`, `overdue`, `resolved` |
| `psei_status` | enum | `not_applicable`, `pending`, `submitted`, `confirmed`, `failed` |
| `psei_submitted_at` | timestamp | Quando enviou ao PSEI |
| `psei_protocol` | string(50) | Protocolo do PSEI |
| `returned_at` | timestamp | Se devolvido ao estoque |
| `returned_reason` | string | Motivo da devolução |
| `notes` | text | Observações |

**Ciclo de vida (status):**

```
                    ┌─────────────────────────────────────────┐
                    │              ESTOQUE                      │
                    │                                          │
    Recebimento     │   available ──── assigned ──── used      │
    (lote)  ───────►│       │              │           │       │
                    │       │              │           ▼       │
                    │       │              │      pending_psei │
                    │       │              │           │       │
                    │       │         returned         ▼       │
                    │       │              ▲       registered   │
                    │       │              │                    │
                    │       ▼              │                    │
                    │   damaged / lost     │                    │
                    │   (justificativa     │                    │
                    │    obrigatória)      │                    │
                    └─────────────────────────────────────────┘
```

**Regras de transição:**
- `available → assigned`: apenas admin/supervisor
- `assigned → used`: apenas o técnico que possui o selo, com foto e OS obrigatórias
- `used → pending_psei`: automático após uso (selo_reparo) ou N/A (lacre)
- `pending_psei → registered`: após confirmação do PSEI
- `assigned → returned`: técnico devolve ao estoque com motivo
- `assigned/available → damaged/lost`: com justificativa obrigatória e notificação

### RepairSealBatch

Tabela: `repair_seal_batches`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `type` | enum | `seal` ou `seal_reparo` |
| `batch_code` | string(50) | Código identificador do lote |
| `range_start` | string(30) | Número inicial da faixa |
| `range_end` | string(30) | Número final da faixa |
| `prefix` | string(10) | Prefixo dos números |
| `suffix` | string(10) | Sufixo dos números |
| `quantity` | integer | Total de itens no lote |
| `quantity_available` | integer | Disponíveis (cache denormalizado) |
| `supplier` | string | Fornecedor/órgão emissor |
| `invoice_number` | string | NF de recebimento |
| `received_at` | date | Data de recebimento |
| `received_by` | FK → users | Quem recebeu |
| `notes` | text | |

### RepairSealAssignment

Tabela: `repair_seal_assignments`

Log de auditoria de toda movimentação de selos entre estoque e técnicos.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `seal_id` | FK → inmetro_seals | |
| `technician_id` | FK → users | Técnico destino |
| `assigned_by` | FK → users | Quem realizou |
| `action` | enum | `assigned`, `returned`, `transferred` |
| `previous_technician_id` | FK nullable → users | Em transferências |
| `notes` | text | |

### PseiSubmission

Tabela: `psei_submissions`

Log detalhado de cada tentativa de envio ao PSEI.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `seal_id` | FK → inmetro_seals | |
| `work_order_id` | FK nullable → work_orders | |
| `equipment_id` | FK nullable → equipments | |
| `submission_type` | enum | `automatic`, `manual`, `retry` |
| `status` | enum | `queued`, `submitting`, `success`, `failed`, `timeout`, `captcha_blocked` |
| `attempt_number` | integer | Tentativa atual |
| `max_attempts` | integer | Máximo (default 3) |
| `protocol_number` | string(100) | Protocolo retornado pelo PSEI |
| `request_payload` | json | Dados enviados |
| `response_payload` | json | Resposta recebida |
| `error_message` | text | Erro se falhou |
| `submitted_at` | timestamp | |
| `confirmed_at` | timestamp | |
| `next_retry_at` | timestamp | Próxima tentativa agendada |

### RepairSealAlert

Tabela: `repair_seal_alerts`

Alertas gerados pelo sistema de monitoramento de prazos.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `seal_id` | FK → inmetro_seals | |
| `technician_id` | FK → users | |
| `alert_type` | enum | `warning_3d`, `critical_4d`, `overdue_5d`, `blocked`, `low_stock` |
| `severity` | enum | `info`, `warning`, `critical` |
| `message` | text | Mensagem descritiva |
| `acknowledged_at` | timestamp | Quando vista |
| `resolved_at` | timestamp | Quando resolvida |

---

## Fluxos Principais

### Fluxo 1: Recebimento de Lote

```
Admin recebe selos do Inmetro
  → POST /repair-seal-batches { type, batch_code, range_start, range_end, ... }
  → Sistema cria RepairSealBatch
  → Sistema gera InmetroSeal individual para cada número da faixa
  → Status de cada selo: available
  → Evento: SealBatchReceived
```

### Fluxo 2: Atribuição a Técnico

```
Admin/Supervisor atribui selos
  → POST /repair-seals/assign { seal_ids[], technician_id }
  → Validação: técnico ativo, não bloqueado, selos available
  → Status: available → assigned, assigned_at = now()
  → Cria RepairSealAssignment (auditoria)
  → Evento: SealAssignedToTechnician
  → Verifica estoque mínimo do técnico
```

### Fluxo 3: Uso na Ordem de Serviço (fluxo principal)

```
Técnico está em campo, calibrou a balança
  → Abre tela de "Aplicar Selo" no mobile
  → Seleciona selo do seu inventário
  → Tira foto do selo aplicado na balança
  → POST /repair-seals/use { seal_id, work_order_id, equipment_id, photo }
  → Validação: selo pertence ao técnico, WO existe, foto obrigatória
  → Status: assigned → used (para lacre) ou assigned → pending_psei (para selo_reparo)
  → deadline_at = used_at + 5 dias úteis
  → Evento: SealUsedOnWorkOrder
    → Listener: DispatchPseiSubmission (auto-submit se seal_reparo)
    → Listener: StartDeadlineCountdown

Repete para cada lacre aplicado (1+ por balança)

Técnico completa a OS
  → Sistema valida: tem pelo menos 1 selo_reparo + 1 lacre vinculados
  → Se não: bloqueia conclusão da OS, mostra erro
```

### Fluxo 4: Submissão Automática ao PSEI

```
Job: SubmitSealToPseiJob (queue: repair-seals)
  → PseiSealSubmissionService.authenticate() — sessão gov.br
  → PseiSealSubmissionService.submitSeal(seal) — POST no portal
  → Se sucesso:
    → PseiSubmission.status = success
    → PseiSubmission.protocol_number = "XXX"
    → InmetroSeal.psei_status = confirmed
    → InmetroSeal.psei_protocol = "XXX"
    → Evento: SealPseiSubmitted
    → Resolve alertas de deadline
  → Se falha (timeout/erro):
    → PseiSubmission.status = failed
    → PseiSubmission.error_message = detalhes
    → PseiSubmission.next_retry_at = agora + backoff exponencial
    → Retry até 3 tentativas
  → Se CAPTCHA:
    → PseiSubmission.status = captcha_blocked
    → Alerta ao admin para intervenção manual
```

### Fluxo 5: Monitoramento de Prazo (5 dias)

```
Job diário: CheckSealDeadlinesJob (08:00)
  → Busca todos os selos com status used/pending_psei e psei_status != confirmed
  → Para cada selo:
    → Calcula dias úteis desde used_at (excluindo feriados via HolidayService)
    → 3 dias úteis: cria alerta warning_3d → notifica técnico
    → 4 dias úteis: cria alerta critical_4d → notifica técnico + gerente
    → 5+ dias úteis: cria alerta overdue_5d → BLOQUEIA técnico
      → Técnico bloqueado não pode receber novos selos
      → Técnico bloqueado não pode completar novas OS
      → Administração é notificada
    → Quando selo é registrado no PSEI:
      → Resolve todos os alertas pendentes
      → Desbloqueia técnico se era o único selo pendente
```

### Fluxo 6: Devolução de Selos

```
Técnico devolve selos não utilizados
  → POST /repair-seals/return { seal_ids[], reason }
  → Status: assigned → returned
  → returned_at = now(), returned_reason = reason
  → Atualiza quantity_available no batch
  → Cria RepairSealAssignment com action = returned
  → Selos voltam ao estoque (available para reatribuição)
```

---

## Regras de Negócio

### RN-01: Número Único
Cada selo e lacre tem número individual único por tenant. Constraint: `UNIQUE(tenant_id, type, number)`.

### RN-02: Foto Obrigatória
Ao registrar uso de selo ou lacre, foto é **obrigatória**. Formatos: JPG, PNG, WebP. Max: 2MB.

### RN-03: Prazo de 5 Dias Úteis
Selo de reparo deve ser registrado no PSEI em **5 dias úteis** após aplicação. Dias úteis excluem finais de semana e feriados nacionais (via HolidayService).

### RN-04: Bloqueio por Inadimplência
Técnico com selo vencido (>5 dias sem registro PSEI) é **bloqueado**:
- Não recebe novos selos
- Não pode completar novas OS
- Alerta à administração
- Desbloqueio automático quando regularizar TODOS os selos pendentes

### RN-05: Selo Obrigatório na OS
OS de calibração/reparo de balança **não pode ser concluída** sem:
- Mínimo 1 selo de reparo (`seal_reparo`) vinculado
- Mínimo 1 lacre (`seal`) vinculado
- Todos com foto

### RN-06: Submissão Automática PSEI
Ao usar um selo de reparo, o sistema **automaticamente** envia ao PSEI via job assíncrono. Lacres **não** são enviados ao PSEI (apenas selos de reparo).

### RN-07: Rastreabilidade Total
Toda movimentação de selo (recebimento, atribuição, transferência, uso, devolução, perda) é registrada em `repair_seal_assignments` com timestamp, responsável e contexto.

### RN-08: Dano/Perda com Justificativa
Selo reportado como danificado ou perdido requer justificativa obrigatória. Gera alerta à administração.

### RN-09: Estoque Mínimo
Alerta automático quando técnico tem menos de:
- 5 selos de reparo disponíveis
- 20 lacres disponíveis
(Configurável por tenant)

### RN-10: Transferência Auditada
Transferência de selos entre técnicos requer autorização de admin/supervisor e registra ambos os lados na auditoria.

---

## API Endpoints

### Selos (`/api/v1/repair-seals`)

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/repair-seals` | `repair_seals.view` | Listar todos (paginado, filtros) |
| GET | `/repair-seals/dashboard` | `repair_seals.view` | Dashboard com stats gerais |
| GET | `/repair-seals/my-inventory` | `repair_seals.use` | Inventário do técnico logado |
| GET | `/repair-seals/technician/{id}/inventory` | `repair_seals.manage` | Inventário de um técnico |
| GET | `/repair-seals/{id}` | `repair_seals.view` | Detalhes de um selo |
| POST | `/repair-seals/use` | `repair_seals.use` | Registrar uso na OS |
| POST | `/repair-seals/assign` | `repair_seals.manage` | Atribuir a técnico |
| POST | `/repair-seals/transfer` | `repair_seals.manage` | Transferir entre técnicos |
| POST | `/repair-seals/return` | `repair_seals.use` | Devolver ao estoque |
| PATCH | `/repair-seals/{id}/report-damage` | `repair_seals.use` | Reportar dano/perda |
| GET | `/repair-seals/overdue` | `repair_seals.manage` | Selos com prazo vencido |
| GET | `/repair-seals/pending-psei` | `repair_seals.manage` | Aguardando envio PSEI |
| GET | `/repair-seals/export` | `repair_seals.manage` | Exportar CSV |

### Lotes (`/api/v1/repair-seal-batches`)

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/repair-seal-batches` | `repair_seals.manage` | Listar lotes |
| POST | `/repair-seal-batches` | `repair_seals.manage` | Registrar novo lote |
| GET | `/repair-seal-batches/{id}` | `repair_seals.manage` | Detalhes + selos do lote |

### Alertas (`/api/v1/repair-seal-alerts`)

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/repair-seal-alerts` | `repair_seals.view` | Listar alertas |
| GET | `/repair-seal-alerts/my-alerts` | `repair_seals.use` | Alertas do técnico logado |
| PATCH | `/repair-seal-alerts/{id}/acknowledge` | `repair_seals.use` | Marcar como visto |

### PSEI (`/api/v1/psei-submissions`)

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/psei-submissions` | `repair_seals.manage` | Listar submissões |
| POST | `/psei-submissions/{sealId}/retry` | `repair_seals.manage` | Reenviar manualmente |
| GET | `/psei-submissions/{id}` | `repair_seals.manage` | Detalhes da submissão |

---

## Dashboard

O dashboard do módulo apresenta:

### Cards principais
- **Total de selos em estoque** (available)
- **Selos com técnicos** (assigned) — com breakdown por técnico
- **Pendentes PSEI** (pending_psei) — com indicador de urgência
- **Vencidos** (overdue) — vermelho, ação imediata
- **Registrados este mês** (registered)

### Tabela de técnicos
| Técnico | Selos Disponíveis | Lacres Disponíveis | Pendentes PSEI | Vencidos | Status |
|---------|-------------------|---------------------|----------------|----------|--------|
| João    | 8                 | 32                  | 1              | 0        | OK     |
| Maria   | 3 ⚠️              | 15 ⚠️               | 0              | 0        | Estoque baixo |
| Pedro   | 0                 | 0                   | 2              | 1        | BLOQUEADO |

### Gráficos
- **Consumo mensal** de selos e lacres (line chart)
- **Tempo médio** entre uso e registro PSEI (bar chart)
- **Taxa de sucesso PSEI** (pie chart: success/failed/captcha)
- **Selos por status** (donut chart)

---

## Integração com PSEI

### Arquitetura

```
  ┌─────────────┐     ┌──────────────────┐     ┌────────────┐
  │ RepairSeal  │────►│ SubmitSealToPsei  │────►│   PSEI     │
  │  Service    │     │      Job          │     │  Portal    │
  │ (use seal)  │     │  (queue: repair-  │     │ (gov.br)   │
  └─────────────┘     │   seals)          │     └────────────┘
                      └──────────────────┘            │
                             │                         │
                             ▼                         ▼
                      ┌──────────────────┐     ┌────────────┐
                      │ PseiSubmission   │     │ Protocolo  │
                      │   Service        │◄────│ confirmado │
                      │ (HTTP/scraping)  │     └────────────┘
                      └──────────────────┘
```

### Estratégia de autenticação

1. **Sessão gov.br**: Login HTTP com credenciais armazenadas (encrypted) por tenant
2. **Cookie persistence**: Manter sessão ativa entre requests
3. **Refresh automático**: Se sessão expirou, re-autenticar antes de submeter
4. **CAPTCHA handling**: Se CAPTCHA detectado:
   - Tentar resolver via serviço OCR básico (se CAPTCHA simples)
   - Se reCAPTCHA: marcar como `captcha_blocked`, alerta ao admin
   - Admin pode resolver manualmente e trigger retry

### Dados enviados ao PSEI

```json
{
  "numero_selo": "RS-000142",
  "data_aplicacao": "2026-03-25",
  "numero_os": "OS-2026-001234",
  "equipamento": {
    "marca": "Toledo",
    "modelo": "Prix 3 Fitness",
    "serial": "ABC123456",
    "capacidade": "30kg",
    "tipo": "balanca_eletronica"
  },
  "tecnico": {
    "nome": "João da Silva",
    "registro": "CREA-SP 123456",
    "empresa_cnpj": "12.345.678/0001-90"
  },
  "local_aplicacao": {
    "endereco": "Rua X, 100",
    "cidade": "São Paulo",
    "uf": "SP"
  }
}
```

---

## Permissões

| Permissão | Descrição | Roles típicos |
|-----------|-----------|---------------|
| `repair_seals.view` | Ver selos, dashboard, relatórios | Admin, Supervisor, Gerente |
| `repair_seals.use` | Usar selos, devolver, ver próprio inventário | Técnico |
| `repair_seals.manage` | Gerenciar lotes, atribuir, transferir, PSEI | Admin, Supervisor |

---

## Configuração por Tenant

| Chave | Default | Descrição |
|-------|---------|-----------|
| `repair_seals.psei_auto_submit` | `true` | Submissão automática ao PSEI |
| `repair_seals.psei_gov_br_username` | — | Usuário gov.br (encrypted) |
| `repair_seals.psei_gov_br_token` | — | Token gov.br (encrypted) |
| `repair_seals.deadline_business_days` | `5` | Prazo em dias úteis |
| `repair_seals.warning_day` | `3` | Dia do alerta warning |
| `repair_seals.escalation_day` | `4` | Dia da escalação |
| `repair_seals.block_on_overdue` | `true` | Bloquear técnico |
| `repair_seals.low_stock_seal` | `5` | Estoque mínimo selos/técnico |
| `repair_seals.low_stock_lacre` | `20` | Estoque mínimo lacres/técnico |

---

## Edge Cases e Guardrails

| Cenário | Tratamento |
|---------|------------|
| PSEI fora do ar | Job retry com backoff exponencial. Selo fica `pending_psei`. Prazo continua contando. |
| CAPTCHA no PSEI | Marca `captcha_blocked`. Alerta admin. Permite retry manual. |
| Selo perdido/danificado | Justificativa obrigatória. Notifica admin. Baixa do inventário. Não gera prazo PSEI. |
| Técnico demitido | Todos os selos assigned devem ser devolvidos (returned). Alerta ao admin. |
| Feriado no prazo | HolidayService exclui feriados nacionais do cálculo de dias úteis. |
| Selo já registrado no PSEI | Idempotência: se `psei_status = confirmed`, não resubmete. |
| Lote com número duplicado | Constraint UNIQUE impede. Erro 422 com mensagem clara. |
| Técnico tenta usar selo de outro | Validação: `assigned_to == auth()->id()`. Erro 403. |
| OS sem selo ao completar | WorkOrder transition guard bloqueia. Erro 422: "Vincule selo e lacre antes de concluir." |

---

## Plano de Implementação

Referência completa: `docs/superpowers/plans/2026-03-26-plano-modulo-selos-reparo.md`

**6 batches de implementação:**
1. Data Model (migrations, models, factories)
2. Services Layer (RepairSealService, PseiSubmissionService, DeadlineService)
3. Jobs, Events & Scheduled Commands
4. Controllers, Requests & Routes
5. Integração WorkOrder + PSEI
6. Quality Gates, Docs & Rollout

---

## Relacionamento com Outros Módulos

| Módulo | Integração |
|--------|------------|
| **Inmetro** | Compartilha tabela `inmetro_seals`. RepairSeals é o módulo de "gestão operacional" dos selos que o Inmetro intelligence rastreia estrategicamente. |
| **WorkOrders** | OS de calibração/reparo exige selo + lacre vinculados para ser concluída. |
| **Core** | Auth, tenant isolation, permissions. |
| **Notifications** | Alertas de prazo, estoque baixo, bloqueio de técnico. |
| **Mobile** | Interface principal para técnicos registrarem uso de selo em campo. |
