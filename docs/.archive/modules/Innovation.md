---
type: module_domain
module: Innovation
domain: platform
tier: platform
status: beta
---
# Modulo: Inovacao & Gamificacao

> **[AI_RULE]** Documentacao oficial do modulo de Inovacao. Entidades, campos e regras extraidos diretamente do codigo-fonte.

---

## 1. Visao Geral

O modulo Innovation agrupa funcionalidades de personalizacao, engajamento e ferramentas comerciais da plataforma: temas customizaveis por tenant, programa de indicacao (referral), calculadora de ROI para demonstracao comercial, dados de apresentacao institucional e easter eggs de gamificacao.

### Principios Fundamentais

- **Temas por tenant**: cada tenant configura cores, modo escuro, fonte e estilo de sidebar
- **Referral unico por usuario**: cada usuario gera no maximo um codigo de indicacao por tenant
- **ROI calculator**: ferramenta comercial sem persistencia (calculo stateless)
- **Gamificacao cross-domain**: badges e easter eggs conectam-se a CRM (deals) e WorkOrders (completed)

---

## 2. Entidades (Models) — Campos Completos

> **Nota:** As configurações de Tema (`CustomTheme`) são persistidas no model genérico `TenantSetting` ao invés de uma tabela dedicada. Abaixo estão as entidades reais do banco de dados relacionadas à gamificação e referral.

### 2.1 `GamificationBadge` (tabela: `gamification_badges`)

Selo de conquista da gamificação.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `tenant_id` | bigint FK | Tenant |
| `name` | string | Nome do badge |
| `slug` | string | Slug único |
| `description` | string | Descrição |
| `icon` | string | Ícone |
| `color` | string | Cor |
| `category` | string | Categoria do badge |
| `metric` | string | Métrica avaliada |
| `threshold` | mixed | Limite para conquista |
| `is_active` | boolean | Se está ativo |

### 2.2 `GamificationScore` (tabela: `gamification_scores`)

Pontuação dos usuários por período.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `tenant_id` | bigint FK | Tenant |
| `user_id` | bigint FK | Usuário |
| `period` | string | Período (ex: `2026-03`) |
| `visits_count` | integer | Visitas realizadas |
| `deals_won` | integer | Negócios fechados |
| `total_points` | integer | Pontuação total |
| `rank_position` | integer | Posição no ranking |

### 2.3 `CrmReferral` (tabela: `crm_referrals`)

Indicações no programa de referral.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `tenant_id` | bigint FK | Tenant |
| `referrer_customer_id` | bigint FK | Cliente que indicou |
| `referred_customer_id` | bigint FK | Cliente indicado |
| `deal_id` | bigint FK | Deal vinculado |
| `status` | string | Status da indicação |
| `reward_type` | string | Tipo de recompensa |
| `reward_value` | decimal | Valor da recompensa |

### 2.4 `Idea` (Hub de Ideias) (tabela: `innovation_ideas`)

Hub de ideias submetidas por usuários/clientes.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `tenant_id` | bigint FK | Tenant |
| `user_id` | bigint FK | Autor da ideia |
| `title` | string | Título da ideia |
| `description` | text | Detalhamento |
| `status` | string | `submitted`, `in_review`, `approved`, `rejected`, `implemented` |
| `score` | integer | Pontuação total (upvotes - downvotes) |
| `original_author_removed` | boolean| Para gestão de ideias órfãs |

### 2.5 `IdeaVote` (tabela: `innovation_idea_votes`)

Votação democrática nas ideias.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `tenant_id` | bigint FK | Tenant |
| `idea_id` | bigint FK | Ideia avaliada |
| `user_id` | bigint FK | Avaliador |
| `vote_value` | integer | 1 (upvote) ou -1 (downvote) |

---

## 3. Endpoints

> **[AI_RULE]** Todos os endpoints requerem autenticacao. Tenant isolado via `auth()->user()->current_tenant_id`.

### 3.1 Rotas Registradas (Codigo-Fonte)

Endpoints com rotas efetivamente registradas em `backend/routes/api/`:

| Metodo | Rota | Controller | Arquivo de Rota | Descricao |
|---|---|---|---|---|
| `GET` | `/api/v1/innovation/presentation` | `InnovationController@presentationData` | `finance-advanced.php` | Dados de apresentacao institucional (KPIs, tendencia mensal, certificados) |

**Referrals (via CRM)** — registrados em `crm.php` sob prefixo CRM Engagement:

| Metodo | Rota | Controller | Descricao |
|---|---|---|---|
| `GET` | `/api/v1/crm/referrals` | `CrmEngagementController@referrals` | Listar indicacoes |
| `GET` | `/api/v1/crm/referrals/stats` | `CrmEngagementController@referralStats` | Estatisticas de indicacoes |
| `GET` | `/api/v1/crm/referrals/options` | `CrmEngagementController@referralOptions` | Opcoes para formulario |
| `POST` | `/api/v1/crm/referrals` | `CrmEngagementController@storeReferral` | Criar indicacao |
| `PUT` | `/api/v1/crm/referrals/{referral}` | `CrmEngagementController@updateReferral` | Atualizar indicacao |
| `DELETE` | `/api/v1/crm/referrals/{referral}` | `CrmEngagementController@destroyReferral` | Remover indicacao |

**Gamificacao (via CRM)** — registrados em `crm.php` sob CRM Field Management:

| Metodo | Rota | Controller | Descricao |
|---|---|---|---|
| `GET` | `/api/v1/crm/gamification` | `CrmFieldManagementController@gamificationDashboard` | Dashboard de gamificacao |
| `POST` | `/api/v1/crm/gamification/recalculate` | `CrmFieldManagementController@gamificationRecalculate` | Recalcular pontuacoes |

### 3.2 Metodos do Controller Sem Rota Registrada [SPEC]

Metodos implementados em `InnovationController` mas sem rota registrada em `routes/api/`. Requerem registro de rota para ficarem acessiveis:

| Metodo | Rota Planejada | Controller | Descricao |
|---|---|---|---|
| `GET` | `/api/v1/innovation/theme` | `InnovationController@themeConfig` | Obter configuracao de tema do tenant (retorna defaults se nao existir) |
| `PUT` | `/api/v1/innovation/theme` | `InnovationController@updateThemeConfig` | Atualizar tema do tenant |
| `GET` | `/api/v1/innovation/referral` | `InnovationController@referralProgram` | Listar codigos de indicacao do usuario |
| `POST` | `/api/v1/innovation/referral/generate` | `InnovationController@generateReferralCode` | Gerar codigo de indicacao (max 1 por usuario/tenant, retorna 422 se ja existir) |
| `POST` | `/api/v1/innovation/roi-calculator` | `InnovationController@roiCalculator` | Calculadora de ROI (stateless, retorna metricas de economia e payback) |
| `GET` | `/api/v1/innovation/easter-egg/{code}` | `InnovationController@easterEgg` | Easter eggs de gamificacao (konami, matrix, rocket, coffee, calibrium) |

---

## 4. Regras de Negocio

> **[AI_RULE_CRITICAL] A Inovação do Lead Eterno**
> O módulo Innovation interage com o CRM para garantir que o cliente seja recompensado por sua lealdade contínua (Referrals e Gamificação). Assim como o funil nunca acaba, as pontuações de gamificação e os referrals alimentam o engajamento perpétuo.

> **[AI_RULE]** Referral code usa transacao DB para garantir unicidade. ROI calculator e stateless.

### 4.1 Referral

- Cada usuario pode ter no maximo **1 codigo de indicacao por tenant**
- Codigo gerado com `Str::random(8)` uppercase
- Recompensa padrao: `discount_percent` de 10%
- Tentativa de gerar segundo codigo retorna `422` com o codigo existente

### 4.2 ROI Calculator

Parametros de entrada:

- `monthly_os_count`: quantidade mensal de OS
- `avg_os_value`: valor medio por OS
- `current_monthly_cost`: custo mensal atual
- `system_monthly_cost`: custo mensal do sistema
- `time_saved_percent`: percentual de tempo economizado (default: 30%)

Retorno calculado:

- `additional_os_capacity`, `additional_monthly_revenue`, `monthly_savings`
- `annual_roi_percent`, `payback_months`

### 4.3 Presentation Data

Agrega KPIs do tenant para apresentacao comercial:

- Total de clientes, OS do ano, receita do ano, NPS medio, certificados emitidos
- Tendencia mensal de OS (agrupado por mes)

---

## 5. Regras Cross-Domain

| Modulo Destino | Integracao | Descricao |
|---|---|---|
| CRM | Deals fechados | Badges de gamificacao por metas de vendas |
| WorkOrders | OS concluidas | Badges por volume de OS completadas |
| Finance | Pagamentos | Referral codes geram descontos em faturas |

---

## Fluxos Relacionados

| Fluxo | Descricao |
|-------|-----------|
| [Ciclo Comercial](file:///c:/PROJETOS/sistema/docs/fluxos/CICLO-COMERCIAL.md) | Processo documentado em `docs/fluxos/CICLO-COMERCIAL.md` |

---

## 7. Controllers e Services Reais

### 7.1 `InnovationController`

**Arquivo:** `backend/app/Http/Controllers/Api/V1/InnovationController.php`

Unico controller do modulo. Implementa todos os endpoints descritos na secao 3.

### 7.2 FormRequests

| FormRequest | Arquivo |
|-------------|---------|
| `UpdateThemeConfigRequest` | `backend/app/Http/Requests/RemainingModules/UpdateThemeConfigRequest.php` |
| `StoreCrmReferralRequest` | `backend/app/Http/Requests/Crm/StoreCrmReferralRequest.php` |
| `UpdateCrmReferralRequest` | `backend/app/Http/Requests/Crm/UpdateCrmReferralRequest.php` |

---

## 8. Inventario Completo do Codigo

### 8.1 Models

| Arquivo | Model |
|---------|-------|
| `backend/app/Models/GamificationBadge.php` | GamificationBadge |
| `backend/app/Models/GamificationScore.php` | GamificationScore |
| `backend/app/Models/CrmReferral.php` | CrmReferral |

### 8.2 Controllers

| Arquivo | Controller |
|---------|------------|
| `backend/app/Http/Controllers/Api/V1/InnovationController.php` | InnovationController |

### 8.3 FormRequests

| Arquivo | FormRequest |
|---------|-------------|
| `backend/app/Http/Requests/RemainingModules/UpdateThemeConfigRequest.php` | UpdateThemeConfigRequest |
| `backend/app/Http/Requests/Crm/StoreCrmReferralRequest.php` | StoreCrmReferralRequest |
| `backend/app/Http/Requests/Crm/UpdateCrmReferralRequest.php` | UpdateCrmReferralRequest |

### 8.4 Route Files

| Arquivo | Escopo |
|---------|--------|
| `backend/routes/api/modules-extra.php` | Rotas de inovacao (tema, referral, ROI, easter eggs) |

---

## Edge Cases e Tratamento de Erros

| Cenário | Comportamento Esperado | Regra |
| --------- | ---------------------- | ------- |
| **Empate de score** (2+ ideias com pontuação idêntica no ranking) | Desempate por: 1) data de criação mais antiga (first-come). 2) Se mesmo dia: maior número de votos. 3) Se ainda empate: manter ordem alfabética por título. Nunca usar random. | `[AI_RULE]` |
| **Votação duplicada** (mesmo usuário vota 2x na mesma ideia) | Verificar existência de voto via unique constraint `(user_id, idea_id)`. Se duplicado: retornar 409 `already_voted`. Permitir alterar voto existente (update, não insert). | `[AI_RULE]` |
| **Pipeline overflow** (> 50 ideias em status `in_review` simultaneamente) | Alertar administrador quando threshold atingido. Não bloquear novas submissões. Ordenar fila por prioridade calculada (score + urgência). Sugerir arquivamento de ideias estagnadas (> 90 dias sem atividade). | `[AI_RULE]` |
| **Mudança de status retroativa** (`approved → submitted`) | Permitir regressão de status com motivo obrigatório (`regression_reason`). Logar no audit trail. Recalcular métricas de pipeline. Notificar autor da ideia sobre a mudança. | `[AI_RULE]` |
| **Ideia órfã** (autor removido do tenant) | Manter ideia no sistema. Transferir ownership para `admin` do tenant. Marcar `original_author_removed = true`. Manter histórico de votos e comentários intacto. | `[AI_RULE]` |

---

## Checklist de Implementação

### Backend
- [ ] Models com `BelongsToTenant`, `$fillable`, `$casts`, relationships
- [ ] Migrations com `tenant_id`, indexes, foreign keys
- [ ] Controllers seguem padrão Resource (index/store/show/update/destroy)
- [ ] FormRequests com validação completa (required, tipos, exists)
- [ ] Services encapsulam lógica de negócio e transições de estado
- [ ] Policies com permissões Spatie registradas
- [ ] Routes registradas em `routes/api/`
- [ ] Events/Listeners para integrações cross-domain
- [ ] Hub de Ideias: `Idea` Model com status machine (submitted → in_review → approved → implemented / rejected)
- [ ] Votação com unique constraint `(user_id, idea_id)` e suporte a update de voto existente
- [ ] Temas customizáveis por tenant (cores, modo escuro, fonte, sidebar) via TenantSetting
- [ ] Programa de referral com código único por usuário por tenant (CrmReferral)
- [ ] ROI Calculator stateless para demonstração comercial
- [ ] GamificationBadge com concessão automática baseada em eventos (CRM deals, WorkOrders completed)
- [ ] Easter eggs de gamificação conectados cross-domain

### Frontend
- [ ] Páginas de listagem, criação, edição
- [ ] Tipos TypeScript espelhando response da API
- [ ] Componentes seguem Design System (tokens, componentes)
- [ ] Configurador de tema com preview em tempo real
- [ ] Hub de ideias com votação inline e ranking
- [ ] Dashboard de gamificação com badges e leaderboard
- [ ] Calculadora ROI interativa

### Testes
- [ ] Feature tests para cada endpoint (happy path + error + validation + auth)
- [ ] Unit tests para Services (lógica de negócio, state machine)
- [ ] Tenant isolation verificado em todos os endpoints
- [ ] Testes de votação (unique constraint, update, 409 em duplicata)
- [ ] Testes de ranking com desempate (data, votos, alfabético)
- [ ] Testes de pipeline overflow (alerta em > 50 ideias in_review)
- [ ] Testes de regressão de status (motivo obrigatório, audit trail)

### Qualidade
- [ ] Zero `TODO` / `FIXME` no código
- [ ] Guard Rails `[AI_RULE]` implementados e testados
- [ ] Cross-domain integrations conectadas e funcionais (CRM ↔ Gamification ↔ WorkOrders)
