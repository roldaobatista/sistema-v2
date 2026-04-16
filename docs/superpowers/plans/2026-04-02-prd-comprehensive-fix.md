# PRD-KALIBRIUM Comprehensive Fix — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolver todos os 27 findings da auditoria PRD-KALIBRIUM, eliminando inconsistencias, completando lacunas e elevando o documento ao padrao BMAD profissional.

**Architecture:** Edicao direta do arquivo `docs/PRD-KALIBRIUM.md` (1715 linhas, v2.1). Cada task modifica secoes especificas com line numbers mapeados. Ordem: primeiro correcoes criticas (conflitos que causam implementacao errada), depois completude (lacunas), depois qualidade.

**Tech Stack:** Markdown (PRD), validacao cruzada contra `docs/raio-x-sistema.md` e codigo-fonte.

**Spec Source:** `_bmad-output/planning-artifacts/prd-validation-report.md` (auditoria completa)

---

## File Structure

- **Modify:** `docs/PRD-KALIBRIUM.md` — arquivo unico do PRD (todas as tasks)
- **Reference (read-only):** `docs/raio-x-sistema.md` — fonte de verdade para status real
- **Reference (read-only):** `_bmad-output/planning-artifacts/prd-validation-report.md` — findings detalhados
- **Reference (read-only):** `backend/routes/api/` — rotas reais para validar RFs
- **Reference (read-only):** `backend/database/schema/sqlite-schema.sql` — schema real para validar campos

---

## FASE 1: CORRECOES CRITICAS (Conflitos Internos)

### Task 1: Corrigir Status Conflitantes RF-10.1/RF-10.2 (eSocial)

**Finding:** C-01 — Duas versoes de status no mesmo PRD
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao RF-10 (linhas ~712-735)

- [ ] **Step 1: Ler a secao RF-10 completa**

Ler `docs/PRD-KALIBRIUM.md` linhas 712-735 para identificar exatamente onde os status 🟢 vs 🟡 conflitam.

- [ ] **Step 2: Unificar RF-10.1 e RF-10.2 para status correto**

Alterar para:
```markdown
| RF-10.1 | Transmitir evento de admissao (cadastro do trabalhador) | Pos-MVP | 🟡 Estrutura implementada (ESocialController + Jobs). Pendente: validacao em ambiente producao governo |
| RF-10.2 | Transmitir evento de desligamento | Pos-MVP | 🟡 Estrutura implementada. Pendente: validacao em ambiente producao governo |
```

- [ ] **Step 3: Verificar que nenhuma outra secao referencia RF-10.1/10.2 com status diferente**

Buscar no PRD por "RF-10.1" e "RF-10.2" e confirmar que todos os status agora sao 🟡 consistentes.

- [ ] **Step 4: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "fix(prd): unify RF-10.1/10.2 eSocial status to 🟡 (production validation pending)"
```

---

### Task 2: Remover Rastreabilidade Duplicada (Manter V2)

**Finding:** C-02 — Duas tabelas de rastreabilidade FR↔Jornada incompativeis
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao "Rastreabilidade" (linha ~901)

- [ ] **Step 1: Ler secao de rastreabilidade completa**

Ler `docs/PRD-KALIBRIUM.md` linhas 901-930 para identificar as duas tabelas.

- [ ] **Step 2: Identificar qual e a V1 (incompleta) e qual e a V2 (completa)**

V1 (incompleta): nao tem J7-LGPD, J2 vai ate RF-05.5, J3 ate RF-03.10, Cross-domain ate RF-11.6
V2 (completa): tem J7, J2 ate RF-05.7, J3 ate RF-03.12, Cross-domain ate RF-11.9

- [ ] **Step 3: Remover a tabela V1 e manter apenas V2**

Manter a tabela que inclui:
```markdown
| **J7 — Titular LGPD (qualquer pessoa)** | RF-11.1–RF-11.9, RF-13.7 |
| **Cross-domain (Complementar)** | RF-12.1–RF-12.2 (J1-Gestor), RF-12.3 (J3-Financeiro), ... |
| **Cross-domain (Notificacoes)** | RF-13.1–RF-13.8 (todas as jornadas) |
```

- [ ] **Step 4: Adicionar linhas de rastreabilidade para RF-14, RF-15, RF-16**

Adicionar ao final da tabela:
```markdown
| **J2 — Tecnico (adicional)** | RF-14.1–RF-14.4 (Frota — checkin veiculo) |
| **J5 — Admin (adicional)** | RF-15.1–RF-15.3 (IA — consultas admin), RF-16.1–RF-16.3 (Projetos) |
```

- [ ] **Step 5: Verificar que todos os RFs do PRD estao mapeados na tabela**

Contar: RF-01 a RF-16 devem todos ter pelo menos uma entrada na rastreabilidade.

- [ ] **Step 6: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "fix(prd): remove duplicate traceability table, keep complete V2, add RF-14/15/16"
```

---

### Task 3: Criar Tabela RF-11 LGPD Completa (9 Sub-requisitos)

**Finding:** C-03 — RF-11 referencia 9 sub-requisitos mas so define 6 na tabela principal
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao RF-11 (linha ~737)

- [ ] **Step 1: Ler secao RF-11 atual**

Ler `docs/PRD-KALIBRIUM.md` linhas 737-752 para ver estado atual da tabela.

- [ ] **Step 2: Verificar RF-11.7, RF-11.8, RF-11.9 na secao de compliance**

Ler linhas 351-366 — la estao definidos:
- RF-11.7: configurar DPO por tenant (Art. 41)
- RF-11.8: registrar e notificar incidentes de seguranca (Art. 48)
- RF-11.9: gerar RIPD sob demanda da ANPD (Art. 38)

- [ ] **Step 3: Completar tabela RF-11 com os 9 sub-requisitos**

A tabela deve ficar:
```markdown
### RF-11: LGPD (Protecao de Dados)

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-11.1 | Registrar base legal por tipo de tratamento de dados pessoais | MVP | 🟢 |
| RF-11.2 | Permitir titular solicitar acesso aos seus dados pessoais | MVP | 🟢 |
| RF-11.3 | Permitir eliminacao de dados nao obrigatorios a pedido do titular | MVP | 🟢 |
| RF-11.4 | Exportar dados do titular em formato estruturado (portabilidade) | MVP | 🟢 |
| RF-11.5 | Registrar log de consentimento com timestamp e finalidade | MVP | 🟢 |
| RF-11.6 | Anonimizar dados pessoais apos periodo legal de retencao | Pos-MVP | 🔴 |
| RF-11.7 | Configurar DPO (Encarregado de Dados) por tenant com nome e email publico | MVP | 🟢 |
| RF-11.8 | Registrar e notificar incidentes de seguranca a ANPD e titulares em prazo razoavel | MVP | 🔴 |
| RF-11.9 | Gerar RIPD (Relatorio de Impacto a Protecao de Dados) sob demanda da ANPD | Pos-MVP | 🔴 |
```

- [ ] **Step 4: Validar status contra codigo real**

Verificar no backend se `LgpdController`, `LgpdDataRequestController`, `LgpdConsentService` existem e quais funcoes implementam. Ajustar status conforme realidade.

- [ ] **Step 5: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "fix(prd): complete RF-11 LGPD table with all 9 sub-requirements and verified status"
```

---

### Task 4: Criar RF-17 para Estoque (Modulo sem RF)

**Finding:** A-02 + A-04 — Estoque tem 15+ models e 7 sub-modulos mas ZERO requisitos funcionais no PRD
**Nota:** RF-04 no PRD e "Clientes" (nao Estoque). O modulo de Estoque nunca teve RF proprio — por isso recebe RF-17.
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — adicionar nova secao apos RF-16 (linha ~804)
- Reference: `docs/raio-x-sistema.md` — Dominio 5: ESTOQUE & PRODUTOS

- [ ] **Step 1: Ler Raio-X secao Estoque para levantar funcionalidades reais**

Buscar no raio-x-sistema.md por "Dominio 5" e "ESTOQUE" para listar todos sub-modulos implementados.

- [ ] **Step 2: Ler rotas de estoque para confirmar endpoints**

Ler `backend/routes/api/routes-stock.php` para confirmar controllers e operacoes disponiveis.

- [ ] **Step 3: Criar secao RF-17: Estoque e Produtos**

Inserir apos RF-16:
```markdown
### RF-17: Estoque e Produtos

> Gestao completa de estoque multi-armazem com rastreamento de lotes, movimentacoes e inventario fisico.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-17.1 | Gerenciar multiplos armazens com localizacoes e responsaveis | MVP | 🟢 |
| RF-17.2 | Registrar movimentacoes de estoque (entrada, saida, transferencia, ajuste, devolucao) | MVP | 🟢 |
| RF-17.3 | Catalogo de produtos com precificacao por tier e historico de precos | MVP | 🟢 |
| RF-17.4 | Catalogo de servicos com composicao (ServiceCatalog) | MVP | 🟢 |
| RF-17.5 | Rastreamento de lotes (batch/lot) para manufatura e origem | MVP | 🟢 |
| RF-17.6 | Transferencia entre armazens com fluxo de aprovacao | MVP | 🟢 |
| RF-17.7 | Inventario fisico com contagem e reconciliacao automatica | MVP | 🟢 |
| RF-17.8 | Registro de descarte com motivo e certificado | MVP | 🟢 |
| RF-17.9 | Alertas de estoque minimo configuravel por produto | MVP | 🟢 |
| RF-17.10 | Montagem/desmontagem de kits de produtos | Pos-MVP | 🟢 |
| RF-17.11 | Kardex (historico de movimentacao por produto) | MVP | 🟢 |
| RF-17.12 | Inteligencia de estoque (sugestao de compra baseada em consumo) | Pos-MVP | 🟡 |
```

- [ ] **Step 4: Validar cada RF contra controllers existentes**

Confirmar que StockController, WarehouseController, BatchController, InventoryController, KardexController, StockIntelligenceController existem.

- [ ] **Step 5: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add RF-17 Estoque e Produtos with 12 sub-requirements mapped to existing code"
```

---

### Task 5: Criar RF para Billing SaaS

**Finding:** C-04 — Modulo Billing implementado mas sem RF no PRD
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — adicionar nova secao apos RF-17
- Reference: `backend/routes/api/routes-billing.php`

- [ ] **Step 1: Ler schema de billing para mapear campos**

Buscar no sqlite-schema.sql por "saas_plans" e "saas_subscriptions" para ver campos reais.

- [ ] **Step 2: Ler controllers de billing para confirmar operacoes**

Ler `SaasPlanController` e `SaasSubscriptionController` para listar acoes implementadas.

- [ ] **Step 3: Criar secao RF-18: Billing SaaS**

```markdown
### RF-18: Billing SaaS

> Gestao de planos e assinaturas para modelo SaaS multi-tenant.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-18.1 | CRUD de planos SaaS com nome, preco, ciclo (mensal/anual) e modulos incluidos | MVP | 🟢 |
| RF-18.2 | Criar assinatura vinculando tenant a plano com periodo de trial | MVP | 🟢 |
| RF-18.3 | Cancelar assinatura com motivo obrigatorio e data efetiva | MVP | 🟢 |
| RF-18.4 | Renovar assinatura com novo periodo e preco atualizado | MVP | 🟢 |
| RF-18.5 | Controlar modulos ativos por tenant baseado no plano contratado | MVP | 🟡 Schema existe, enforcement no middleware pendente |
| RF-18.6 | Integrar cobranca recorrente com gateway de pagamento (Asaas) | Pos-MVP | 🔴 |
| RF-18.7 | Dashboad de billing para admin com MRR, churn e assinaturas ativas | Pos-MVP | 🔴 |
```

- [ ] **Step 4: Atualizar RF-08.1 para referenciar RF-18**

Na descricao de RF-08.1, adicionar: "Ver RF-18 para detalhes de planos e billing."

- [ ] **Step 5: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add RF-18 Billing SaaS with 7 sub-requirements, link from RF-08.1"
```

---

## FASE 2: COMPLETUDE (Lacunas de Cobertura)

### Task 6: Completar Criterios de Aceitacao para RF-10 (eSocial)

**Finding:** A-01 — RF-10 tem 0 ACs
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao Criterios de Aceitacao (apos AC-09, linha ~1380)

- [ ] **Step 1: Ler RF-10 completo para entender sub-requisitos**

Ler linhas 712-735 para listar todos RF-10.1 a RF-10.6.

- [ ] **Step 2: Criar ACs para RF-10 em formato Gherkin**

Inserir apos AC-09.4:
```markdown
### RF-10: eSocial

##### AC-10.1: Transmitir evento de admissao

- **Dado** um funcionario recem-admitido com dados completos (CPF, CTPS, cargo, salario)
- **Quando** o gestor confirma a admissao no sistema
- **Entao** o sistema gera XML do evento S-2200 no formato eSocial v1.2
- **E** transmite ao webservice do governo com certificado digital A1
- **E** registra o protocolo de recebimento ou erro de rejeicao
- **E** permite retry automatico com backoff em caso de falha

#### AC-10.2: Transmitir evento de desligamento

- **Dado** um funcionario com desligamento registrado
- **Quando** o RH confirma o desligamento
- **Entao** o sistema gera XML do evento S-2299
- **E** transmite ao governo e registra protocolo
- **E** vincula ao calculo de rescisao (se existir)

#### AC-10.3: Transmitir remuneracao mensal

- **Dado** a folha de pagamento fechada para um mes
- **Quando** o RH dispara a transmissao
- **Entao** o sistema gera evento S-1200 para cada funcionario ativo
- **E** consolida em lote e transmite ao governo

#### AC-10.5: Retry com backoff

- **Dado** uma transmissao eSocial que falhou por indisponibilidade do governo
- **Quando** o sistema detecta o erro
- **Entao** agenda retry com backoff exponencial (1min, 5min, 15min, 1h)
- **E** notifica o RH apos 3 falhas consecutivas
- **E** registra cada tentativa no log de transmissao

#### AC-10.6: Registrar protocolo

- **Dado** uma transmissao eSocial bem-sucedida
- **Quando** o governo retorna o protocolo
- **Entao** o sistema armazena numero do protocolo, data/hora, tipo de evento
- **E** vincula ao registro do funcionario
- **E** permite consulta futura por protocolo
```

- [ ] **Step 3: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add acceptance criteria for RF-10 eSocial (5 ACs in Gherkin format)"
```

---

### Task 7: Completar ACs para RF-12, RF-14, RF-15, RF-16

**Finding:** A-01 — Modulos complementares e novos sem ACs
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao Criterios de Aceitacao

- [ ] **Step 1: Criar ACs para RF-12 (Comissoes e Dashboards)**

```markdown
### RF-12: Comissoes e Dashboards Operacionais

##### AC-12.1: Calculo de comissoes

- **Dado** um tecnico com OS concluidas no periodo
- **Quando** o gestor consulta o dashboard de comissoes
- **Entao** o sistema calcula comissao baseada em regra configuravel (% sobre valor OS)
- **E** exibe detalhamento por OS, valor e percentual aplicado
- **E** permite filtrar por periodo e tecnico

#### AC-12.4: TV Dashboard em tempo real

- **Dado** uma tela TV configurada com widgets pelo admin
- **Quando** o dashboard e exibido
- **Entao** atualiza automaticamente a cada 30 segundos (intervalo configuravel)
- **E** exibe apenas dados do tenant logado
- **E** funciona em modo quiosque (sem interacao do usuario)

#### AC-12.6: SLA Dashboard

- **Dado** OS com prazo SLA definido
- **Quando** o gestor acessa o dashboard de SLA
- **Entao** exibe percentual de cumprimento por cliente e tipo de servico
- **E** destaca SLAs violados em vermelho com tempo de atraso

#### AC-12.8: Portal do Fornecedor

- **Dado** um fornecedor com login ativo
- **Quando** acessa o portal
- **Entao** visualiza pagamentos pendentes e realizados
- **E** pode baixar comprovantes e documentos fiscais
- **E** NAO tem acesso a dados de outros fornecedores ou dados internos
```

- [ ] **Step 2: Criar ACs para RF-14 (Frota)**

```markdown
### RF-14: Gestao de Frota

##### AC-14.1: Checkin de veiculo

- **Dado** um tecnico com veiculo atribuido
- **Quando** inicia o dia de trabalho
- **Entao** registra quilometragem atual, nivel de combustivel e condicao geral
- **E** pode anexar fotos de avarias
- **E** o registro e vinculado ao usuario, veiculo e data/hora com GPS
```

- [ ] **Step 3: Criar ACs para RF-15 (IA) e RF-16 (Projetos)**

```markdown
### RF-15: Inteligencia Artificial

##### AC-15.1: Consulta em linguagem natural

- **Dado** um usuario autenticado com permissao de IA
- **Quando** digita uma pergunta em linguagem natural (ex: "quantas OS abertas esta semana?")
- **Entao** o sistema interpreta a intencao e retorna resposta baseada em dados reais do tenant
- **E** NAO expoe dados de outros tenants
- **E** registra a consulta no log de auditoria

### RF-16: Projetos e Indicacoes

##### AC-16.1: Gerenciar projeto com milestones

- **Dado** um gestor criando um projeto
- **Quando** define milestones com datas e OS vinculadas
- **Entao** o sistema rastreia progresso baseado no status das OS
- **E** exibe percentual de conclusao por milestone

#### AC-16.3: Programa de indicacoes

- **Dado** um cliente cadastrado no CRM
- **Quando** indica outro cliente que se converte em OS
- **Entao** a indicacao e registrada com rastreabilidade completa
- **E** o indicador recebe credito configuravel
```

- [ ] **Step 4: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add acceptance criteria for RF-12/14/15/16 (10 ACs in Gherkin format)"
```

---

### Task 8: Criar ACs para RF-17 (Estoque) e RF-18 (Billing)

**Finding:** A-01 — Novos RFs criados nas Tasks 4-5 precisam de ACs
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao Criterios de Aceitacao

- [ ] **Step 1: Criar ACs para RF-17 (Estoque)**

```markdown
### RF-17: Estoque e Produtos

##### AC-17.1: Gerenciar armazens

- **Dado** um gestor de estoque autenticado
- **Quando** cria um novo armazem
- **Entao** define nome, localizacao, responsavel e status (ativo/inativo)
- **E** o armazem e isolado por tenant

#### AC-17.2: Registrar movimentacao

- **Dado** um produto em estoque
- **Quando** uma movimentacao e registrada (entrada, saida, transferencia, ajuste, devolucao)
- **Entao** o saldo e atualizado automaticamente
- **E** o historico (kardex) e registrado com usuario, data/hora, quantidade anterior e nova
- **E** movimentacoes de saida NAO permitem saldo negativo (exceto se config permitir)

#### AC-17.5: Rastreamento de lotes

- **Dado** um produto com controle de lote ativo
- **Quando** uma entrada e registrada
- **Entao** exige numero de lote, data de fabricacao e validade
- **E** saidas seguem regra FIFO ou FEFO conforme configuracao do tenant

#### AC-17.7: Inventario fisico

- **Dado** um armazem selecionado para inventario
- **Quando** o gestor inicia contagem fisica
- **Entao** o sistema gera lista de produtos esperados com quantidades do sistema
- **E** permite registrar quantidade contada por item
- **E** calcula divergencia e permite ajuste com justificativa obrigatoria
```

- [ ] **Step 2: Criar ACs para RF-18 (Billing)**

```markdown
### RF-18: Billing SaaS

##### AC-18.1: CRUD de planos

- **Dado** um admin do sistema
- **Quando** cria um plano SaaS
- **Entao** define nome, preco, ciclo (mensal/anual), modulos incluidos e limites
- **E** o plano fica disponivel para atribuicao a tenants

#### AC-18.2: Criar assinatura

- **Dado** um tenant sem assinatura ativa
- **Quando** o admin atribui um plano
- **Entao** a assinatura e criada com data de inicio, periodo de trial (se configurado) e ciclo
- **E** o status inicia como 'trial' ou 'active'
- **E** os modulos do plano sao ativados para o tenant

#### AC-18.3: Cancelar assinatura

- **Dado** uma assinatura ativa
- **Quando** o admin solicita cancelamento
- **Entao** exige motivo obrigatorio
- **E** registra data de cancelamento
- **E** o acesso permanece ate o fim do periodo pago
```

- [ ] **Step 3: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add acceptance criteria for RF-17 Estoque (4 ACs) and RF-18 Billing (3 ACs)"
```

---

### Task 9: Detalhar NFRs com Metodo de Verificacao

**Finding:** A-06 — Apenas RNF-02 tem tabela detalhada, outros 6 NFRs so tem metricas genericas
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao Requisitos Nao-Funcionais (linhas 819-900)

- [ ] **Step 1: Ler secao RNF-01 a RNF-07 completa**

Ler linhas 819-900 para entender formato atual de cada NFR.

- [ ] **Step 2: Adicionar tabela de verificacao para RNF-01 (Performance)**

```markdown
### RNF-01: Performance

| Requisito | Metrica | Metodo de Verificacao | Status |
|-----------|---------|----------------------|--------|
| API response time | p95 < 500ms | APM monitoring (Laravel Telescope / New Relic). Load test com k6: 100 concurrent users, 1000 requests | 🟢 |
| Dashboard load time | < 3s first contentful paint | Lighthouse CI no pipeline. Teste manual em 3G throttled | 🟡 |
| Report generation | < 10s para relatorios ate 10.000 registros | Teste automatizado com dataset de 10k. Medir com `DB::getQueryLog()` | 🟢 |
| Bulk operations | < 30s para importacao CSV de 5.000 linhas | Teste com fixture CSV 5k. Job queue com progress tracking | 🟢 |
```

- [ ] **Step 3: Adicionar tabela para RNF-03 (Disponibilidade)**

```markdown
### RNF-03: Disponibilidade e Resiliencia

| Requisito | Metrica | Metodo de Verificacao | Status |
|-----------|---------|----------------------|--------|
| Uptime | 99.5% durante horario comercial (8h-18h) | Monitoring externo (UptimeRobot/Pingdom). SLA report mensal | 🟡 |
| Rollback | < 5 min para reverter deploy com falha | Procedimento documentado em deploy/DEPLOY.md. Teste trimestral | 🟢 |
| Backup | Diario automatico com retencao de 30 dias | Cron job verificavel. Teste de restore mensal | 🟡 |
| Failover DB | RTO < 15 min, RPO < 1h | Replica MySQL read-only. Teste de failover semestral | 🔴 |
```

- [ ] **Step 4: Adicionar tabela para RNF-04 (Escalabilidade)**

```markdown
### RNF-04: Escalabilidade

| Requisito | Metrica | Metodo de Verificacao | Status |
|-----------|---------|----------------------|--------|
| Tenants concorrentes | Suportar 10 tenants com 50 usuarios cada | Load test multi-tenant com k6. Medir isolamento e performance | 🟡 |
| Database growth | Suportar 1M registros por tenant sem degradacao | Teste com seed de 1M records. Verificar query plans com EXPLAIN | 🟢 |
| Horizontal scaling | Queue workers escalaveis independentemente | Horizon com auto-scaling de processos por fila | 🟢 |
```

- [ ] **Step 5: Adicionar tabelas para RNF-05, RNF-06, RNF-07**

Seguir mesmo padrao: Requisito | Metrica | Metodo de Verificacao | Status. Consultar PRD existente para preencher metricas ja mencionadas.

- [ ] **Step 6: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add verification tables for all 7 NFRs with metrics, methods and status"
```

---

### Task 10: Adicionar SLA e RTO para Dependencias Externas

**Finding:** A-07 — Dependencias sem SLA, RTO ou alerting
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao Dependencias Externas

- [ ] **Step 1: Ler secao de dependencias atuais**

Localizar tabela de Dependencias Externas e ler formato atual.

- [ ] **Step 2: Expandir tabela com colunas SLA, RTO e Alerting**

```markdown
## Dependencias Externas

| Dependencia | Tipo | SLA Contratual | RTO Aceitavel | Fallback | Alerting |
|-------------|------|---------------|---------------|----------|----------|
| FocusNFe | Emissao fiscal | 99.5% | 4h (contingencia NuvemFiscal) | NuvemFiscal (automatico via ResilientFiscalProvider) | Circuit breaker + alerta Slack quando fallback ativado |
| Asaas | Pagamento | 99.9% | 24h (cobranca manual) | Cobranca manual via email | Health check a cada 5min + alerta quando indisponivel |
| SEFAZ | Receita Federal | Variavel | 8h (contingencia offline) | Fila de contingencia com retry | Monitoramento de status SEFAZ + retry automatico |
| eSocial | Governo Federal | Variavel | 72h (prazo legal permite) | Fila com retry exponencial | Alerta apos 3 falhas + dashboard de transmissoes pendentes |
| Google Maps API | Geolocalizacao | 99.9% | Indefinido (GPS nativo funciona) | GPS nativo sem mapa visual | Alerta quando quota > 80% |
| SMTP | Email | 99.9% | 4h (documentos ficam no portal) | Portal do cliente como alternativa | Bounce rate monitoring + alerta quando fila > 100 |
| Let's Encrypt | SSL | 99.99% | 30 dias (certificado renovado 60 dias antes) | Certbot auto-renova | Alerta 7 dias antes de expirar |
```

- [ ] **Step 3: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): expand external dependencies with SLA, RTO, fallback and alerting details"
```

---

## FASE 3: QUALIDADE (Melhorias BMAD)

### Task 11: Separar Status Codigo vs Status Producao

**Finding:** M-01 — RF-03.4/03.5/03.6 misturam status de implementacao com deployment
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao RF-03 (linhas ~617-633)

- [ ] **Step 1: Ler secao RF-03 completa**

Ler linhas 617-633.

- [ ] **Step 2: Adicionar coluna "Status Producao" nos RFs que tem bloqueio externo**

Alterar RF-03.4, RF-03.5, RF-03.6 para:
```markdown
| RF-03.4 | Emitir NFS-e automaticamente ao faturar OS | MVP | 🟢 Codigo | 🟡 Producao (pendente: contrato FocusNFe + credenciais) |
| RF-03.5 | Gerar boleto bancario para parcelas | MVP | 🟢 Codigo | 🟡 Producao (pendente: contrato Asaas + credenciais) |
| RF-03.6 | Gerar QR code PIX para pagamento instantaneo | MVP | 🟢 Codigo | 🟡 Producao (pendente: contrato Asaas + credenciais) |
```

Alternativa: manter coluna unica mas adicionar nota de rodape explicando a distincao.

- [ ] **Step 3: Aplicar mesmo padrao para RF-13.3 e RF-13.5**

Estes tambem dependem de gateway ativo.

- [ ] **Step 4: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "fix(prd): distinguish code status from production status for gateway-dependent RFs"
```

---

### Task 12: Adicionar Requisitos Negativos (Anti-Requisitos)

**Finding:** M-02 — Anti-personas existem mas sem requisitos negativos explicitos
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — nova subsecao apos "Escopo do Produto"

- [ ] **Step 1: Criar secao "O Que o Sistema Deliberadamente NAO Faz"**

Inserir apos secao de Escopo (antes de Jornadas):
```markdown
### Limites Explicitos de Escopo (Anti-Requisitos)

> Decisoes deliberadas do que o Kalibrium NAO implementa. Evita scope creep e orienta dev/suporte.

| ID | O Sistema NAO... | Motivo |
|----|------------------|--------|
| NR-01 | Processa folha para empresas com mais de 200 funcionarios por tenant | Foco em PMEs de servico tecnico, nao RH enterprise |
| NR-02 | Emite NF-e de produto (apenas NFS-e de servico) | Foco em prestacao de servico, nao comercio/industria |
| NR-03 | Suporta mais de 1 CNPJ por tenant | Cada CNPJ = tenant separado. Grupo economico usa multi-tenant |
| NR-04 | Integra com ERPs de terceiros (SAP, TOTVS) via importacao automatica | MVP foca em operacao standalone. Integracao via API REST e responsabilidade do integrador |
| NR-05 | Armazena dados de cartao de credito | Pagamentos via gateway (Asaas). PCI compliance delegado ao gateway |
| NR-06 | Funciona como marketplace conectando prestadores a clientes | E ferramenta interna de gestao, nao plataforma de matchmaking |
| NR-07 | Suporta idiomas alem de pt-BR | MVP 100% Brasil. i18n planejado para Visao futura |
```

- [ ] **Step 2: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add explicit anti-requirements section (7 NRs) to prevent scope creep"
```

---

### Task 13: Completar Glossario com Termos Tecnicos

**Finding:** M-03 — Termos tecnicos usados nos requisitos mas nao definidos
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao Glossario

- [ ] **Step 1: Localizar secao de Glossario**

Buscar por "Glossario" no PRD.

- [ ] **Step 2: Adicionar termos faltantes**

```markdown
| Circuit Breaker | Disjuntor | Padrao de resiliencia que interrompe chamadas a servico externo apos N falhas consecutivas, evitando cascata |
| Fallback | Alternativa | Servico secundario acionado quando o primario falha (ex: NuvemFiscal quando FocusNFe indisponivel) |
| Contingencia | Modo Degradado | Operacao com funcionalidade reduzida quando dependencia externa esta indisponivel |
| Global Scope | Escopo Global | Filtro automatico aplicado a toda query de um model, garantindo isolamento por tenant (BelongsToTenant) |
| Sync Engine | Motor de Sincronizacao | Componente PWA que gerencia fila de operacoes offline e sincroniza ao reconectar |
| MRR | Receita Recorrente Mensal | Metrica SaaS: soma de todas assinaturas ativas multiplicada por preco mensal |
| Churn | Taxa de Cancelamento | Percentual de clientes que cancelam assinatura em dado periodo |
| RTO | Recovery Time Objective | Tempo maximo aceitavel para restaurar servico apos falha |
| RPO | Recovery Point Objective | Quantidade maxima de dados que se aceita perder (ex: 1h = backup horario) |
```

- [ ] **Step 3: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add 9 technical terms to glossary (circuit breaker, fallback, MRR, RTO, RPO, etc)"
```

---

### Task 14: Adicionar Secao de Riscos e Mitigacoes

**Finding:** B-06 — PRD nao tem secao dedicada a riscos do produto
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — nova secao antes de Changelog

- [ ] **Step 1: Verificar se ja existe secao de riscos**

Buscar por "Risco" no PRD — ja existe "Risco de Inovacao" (linha ~441). Verificar se ha secao de riscos do produto.

- [ ] **Step 2: Criar secao "Riscos do Produto e Mitigacoes"**

```markdown
## Riscos do Produto e Mitigacoes

| # | Risco | Probabilidade | Impacto | Mitigacao |
|---|-------|--------------|---------|-----------|
| R-01 | Dependencia de gateway fiscal unico (FocusNFe) | Media | Alto | Fallback NuvemFiscal ja implementado. Monitorar SLA |
| R-02 | Regulamentacao eSocial muda esquema de eventos | Alta | Medio | Abstraction layer no ESocialTransmissionService. Monitorar publicacoes MTE |
| R-03 | LGPD — multa por nao conformidade em tratamento de dados | Baixa | Critico | RF-11 implementado (7/9). Priorizar RF-11.8 (incidentes) e RF-11.9 (RIPD) |
| R-04 | Churn alto no primeiro ano (pre-PMF) | Alta | Alto | Piloto com cliente proximo. Feedback loop semanal. NPS tracking |
| R-05 | Tenant com volume muito maior que esperado degrada multi-tenant | Baixa | Alto | Connection pooling, query optimization, Horizon auto-scaling. Plano enterprise com infra dedicada |
| R-06 | Tecnico em campo sem internet por periodos longos | Alta | Medio | PWA offline (RF-07.4). Sync engine com fila de acoes |
| R-07 | Migracao de dados do cliente legado falha | Media | Alto | Importacao CSV validada (RF-08.6). Dry-run obrigatorio antes de import real |
| R-08 | Certificado digital A1 para eSocial expira sem renovacao | Media | Alto | Alerta 30 dias antes de expirar. Dashboard de certificados por tenant |
```

- [ ] **Step 3: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add product risks and mitigations section (8 risks with probability/impact/mitigation)"
```

---

### Task 15: Adicionar Secao de Backup e Disaster Recovery

**Finding:** M-09 — Nenhuma mencao a backup, RPO ou DR
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — dentro ou apos RNF-03 (Disponibilidade)

- [ ] **Step 1: Adicionar subsecao de Backup/DR em RNF-03**

```markdown
#### Backup e Disaster Recovery

| Requisito | Metrica | Implementacao |
|-----------|---------|--------------|
| Backup automatico diario | RPO < 24h | MySQL dump diario + binlog para point-in-time recovery |
| Backup de arquivos | RPO < 24h | Storage sync para bucket secundario |
| Retencao | 30 dias | Rotacao automatica de backups antigos |
| Teste de restore | Mensal | Restore em ambiente staging e verificacao de integridade |
| Disaster Recovery | RTO < 4h | Playbook documentado: 1) Restore DB 2) Deploy app 3) Verificar integridade 4) DNS switch |
| Comunicacao de incidente | < 1h apos deteccao | Template de comunicacao para tenants afetados |
```

- [ ] **Step 2: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): add backup and disaster recovery requirements to RNF-03"
```

---

### Task 16: Corrigir Status RF-13.7 e Padronizar Formatacao

**Finding:** C-01 (adicional) + B-02 — RF-13.7 tem status conflitante (🔴 vs 🟢) e formatacao inconsistente
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao RF-13 e formatacao geral

- [ ] **Step 1: Verificar RF-13.7 no codigo**

Buscar no backend por `notifyDpo` ou `LgpdDataRequestController` para confirmar se funcionalidade existe.

- [ ] **Step 2: Unificar status RF-13.7**

Se `LgpdDataRequestController::notifyDpo()` existe e funciona:
```markdown
| RF-13.7 | Notificar DPO quando solicitacao LGPD do titular e registrada | MVP | 🟢 LgpdDataRequestController::notifyDpo() |
```

- [ ] **Step 3: Padronizar formato de status em TODOS os RFs**

Regra: `emoji` + texto curto (< 50 chars). Detalhes longos vao em nota de rodape ou coluna separada.

Exemplos de padronizacao:
- `🟢` → `🟢 Implementado`
- `🟢 FocusNFeProvider + NuvemFiscalProvider + ResilientFiscalProvider...` → `🟢 Implementado ¹` com nota
- `🟡 Codigo pronto (gateway fiscal + fallback + contingencia + circuit breaker). Pendente: contrato comercial + credenciais producao` → `🟡 Codigo pronto ²` com nota

- [ ] **Step 4: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "fix(prd): unify RF-13.7 status, standardize status format across all RFs"
```

---

### Task 17: Definir Escopo Offline (RF-07.4) e Visao Futura

**Finding:** M-04 + M-08 — Escopo offline incompleto e Visao vaga
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao RF-07.4 e secao Visao

- [ ] **Step 1: Expandir RF-07.4 com lista explicita de operacoes offline**

Adicionar nota a RF-07.4:
```markdown
| RF-07.4 | Funcionar offline com operacoes definidas | MVP | 🟡 |

> **Escopo offline definido:**
> - ✅ Consultar OS do dia
> - ✅ Registrar leituras de calibracao
> - ✅ Coletar assinatura digital
> - ❌ Criar nova OS (Pos-MVP)
> - ❌ Registrar ponto (Pos-MVP — depende de validacao GPS)
> - ❌ Consultar estoque (Pos-MVP)
```

- [ ] **Step 2: Refinar secao "Visao (Futuro)" com criterios de transicao**

```markdown
### Visao (Futuro)

| Feature | Gatilho de Decisao | Pre-requisito |
|---------|-------------------|---------------|
| App nativo iOS/Android | > 50% dos tecnicos relatando limitacoes PWA (camera, notificacoes) OU requisito de integracao com hardware especifico | PWA estavel com metricas de uso por 6 meses |
| BI avancado | > 5 clientes pedindo relatorios customizados que DRE/SLA dashboards nao atendem | Data warehouse separado ou read replica |
| Marketplace de servicos | > 20 clientes ativos e demanda comprovada de cross-selling | Modulo de billing maduro, rating system |
| Multi-idioma (i18n) | Cliente fora do Brasil ou parceiro de distribuicao internacional | Extracao de todas as strings para arquivos de traducao |
```

- [ ] **Step 3: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): define explicit offline scope for RF-07.4, add vision transition criteria"
```

---

### Task 18: Confirmar RF-12 Sub-requisitos Alinhados com Modulos Existentes

**Finding:** A-04 (parcial) + M-05 — RF-12 e catch-all; modulos Comissoes, Ativos Fixos, TV Dashboard, SLA, Observabilidade, Supplier Portal ja estao em RF-12.1-12.8 mas precisam de validacao
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao RF-12 (linha ~753)

- [ ] **Step 1: Ler RF-12 atual e confirmar cobertura dos 10 modulos descobertos**

Verificar que RF-12.1-12.2 cobrem Comissoes, RF-12.3 cobre Ativos Fixos, RF-12.4-12.5 cobrem TV Dashboard, RF-12.6 cobre SLA Dashboard, RF-12.7 cobre Observabilidade, RF-12.8 cobre Supplier Portal. RF-14 (Frota), RF-15 (IA), RF-16 (Projetos/Indicacoes) ja existem como RFs separados.

- [ ] **Step 2: Se algum modulo nao tem sub-requisito, adicionar**

Verificar que cada modulo tem pelo menos 1 sub-requisito com status. Se nao, criar. Ex: se Ativos Fixos esta como RF-12.3 mas sem detalhes de depreciacao, expandir.

- [ ] **Step 3: Adicionar nota explicativa no RF-12 sobre a decisao de manter agrupado**

```markdown
> **Nota de arquitetura:** RF-12 agrupa modulos operacionais transversais que compartilham contexto de gestao. Modulos com complexidade suficiente para RF proprio foram extraidos: RF-14 (Frota), RF-15 (IA), RF-16 (Projetos). Os demais permanecem agrupados por baixa complexidade individual.
```

- [ ] **Step 4: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "fix(prd): validate RF-12 coverage of all discovered modules, add architecture note"
```

---

### Task 19: Atualizar Jornadas com Modulos Complementares

**Finding:** A-05 — Jornadas J1-J7 nao cobrem modulos complementares
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao Jornadas de Usuario (linhas ~199-293)

- [ ] **Step 1: Identificar quais modulos complementares pertencem a qual persona**

| Modulo | Persona Principal |
|--------|------------------|
| Comissoes (RF-12.1-12.2) | J1 Gestor (configura regras) + J2 Tecnico (consulta suas comissoes) |
| Ativos Fixos (RF-12.3) | J1 Gestor (cadastra e deprecia) |
| TV Dashboard (RF-12.4-12.5) | J1 Gestor (configura) |
| SLA Dashboard (RF-12.6) | J1 Gestor (monitora) |
| Observabilidade (RF-12.7) | J5 Admin (monitora saude) |
| Supplier Portal (RF-12.8) | Nova persona: Fornecedor |
| Estoque (RF-17) | J1 Gestor (inventario) + J2 Tecnico (movimentacao em campo) |

- [ ] **Step 2: Adicionar micro-cenarios nas jornadas existentes**

Em cada jornada relevante, adicionar 1-2 frases sobre os modulos complementares. Ex em J1 (Dona Marcia):
```markdown
> Marcia tambem consulta o dashboard de comissoes para validar pagamentos aos tecnicos, monitora o SLA no dashboard operacional, e configura o TV Dashboard do escritorio para acompanhar OS em tempo real.
```

- [ ] **Step 3: Considerar criar J8 — Fornecedor (se Supplier Portal for relevante)**

Se RF-12.8 tem fluxo significativo, criar jornada curta do fornecedor acessando o portal.

- [ ] **Step 4: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): extend user journeys J1/J2/J5 with complementary module scenarios"
```

---

### Task 20: Detalhar RF-08.1 e Adicionar Baseline aos Criterios de Sucesso

**Finding:** A-08 (RF-08.1 gap unclear) + M-06 (success metrics without baseline)
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — secao RF-08 (linha ~691) e Criterios de Sucesso (linha ~93)

- [ ] **Step 1: Expandir descricao de RF-08.1 com detalhes do gap**

```markdown
| RF-08.1 | Criar e configurar novos tenants com CNPJ, plano e modulos ativos | MVP | 🟡 Tenant CRUD existe. Gap: vinculo com plano SaaS (ver RF-18.5) e ativacao automatica de modulos por plano |
```

- [ ] **Step 2: Adicionar baseline e fonte de dados nos Criterios de Sucesso**

Na secao "Resultados Mensuraveis" (linha ~129), adicionar coluna "Baseline / Fonte":
```markdown
| Metrica | Before | After | Baseline Fonte |
|---------|--------|-------|----------------|
| Tempo de emissao de certificado | 2h (estimativa do cliente-piloto em entrevista 2026-03) | 15min | Medicao real apos 30 dias de uso |
| Receita perdida por OS sem faturar | 15-25% (estimativa baseada em entrevistas com 3 prospects) | < 2% | Dashboard financeiro apos 3 meses |
```

- [ ] **Step 3: Commit**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "fix(prd): detail RF-08.1 gap, add baseline sources to success metrics"
```

---

### Task 21: Adicionar Versionamento e Validacao Final de Consistencia

**Finding:** B-05 + validacao cruzada de todas as correcoes anteriores
**Files:**
- Modify: `docs/PRD-KALIBRIUM.md` — frontmatter, rastreabilidade e changelog

- [ ] **Step 1: Adicionar campo de versao ao frontmatter**

```yaml
version: '2.2'
lastUpdated: '2026-04-02'
```

- [ ] **Step 2: Atualizar tabela de rastreabilidade para incluir RF-17 e RF-18**

```markdown
| **J1 — Gestor (adicional)** | RF-17.1 (armazens), RF-17.7 (inventario), RF-17.9 (alertas estoque) |
| **J2 — Tecnico (adicional)** | RF-14.1–RF-14.4 (Frota), RF-17.2 (movimentacao estoque) |
| **J5 — Admin (adicional)** | RF-18.1–RF-18.5 (Billing), RF-15.1–RF-15.3 (IA) |
```

- [ ] **Step 3: Adicionar entrada no Changelog**

```markdown
| 2026-04-02 | v2.2 | Auditoria profunda BMAD: +RF-17 Estoque (12 sub-req), +RF-18 Billing SaaS (7 sub-req), RF-11 completado (9/9 sub-req), 20+ ACs adicionados (RF-10/12/14/15/16/17/18), NFRs com tabelas de verificacao, dependencias com SLA/RTO, anti-requisitos (7 NRs), riscos (8), backup/DR, glossario expandido (+9 termos), rastreabilidade unificada, status padronizados |
```

- [ ] **Step 4: Validacao final de consistencia**

Rodar checklist:
1. Todos os RFs (RF-01 a RF-18) tem tabela de sub-requisitos? ✓
2. Todos os RFs tem pelo menos 1 AC? ✓
3. Rastreabilidade cobre RF-01 a RF-18? ✓
4. Nenhum status conflitante? ✓
5. NFRs tem metodo de verificacao? ✓
6. Dependencias tem SLA/RTO? ✓
7. Glossario cobre termos tecnicos? ✓
8. Jornadas cobrem modulos complementares? ✓
9. RF-12 sub-requisitos validados contra modulos reais? ✓
10. Criterios de sucesso com baseline? ✓

- [ ] **Step 5: Commit final**

```bash
git add docs/PRD-KALIBRIUM.md
git commit -m "feat(prd): v2.2 — complete BMAD audit resolution (18 RFs, 100% AC coverage, NFR verification, risks, DR)"
```

---

## FINDINGS EXPLICITAMENTE DEFERIDOS

Os seguintes findings de baixa gravidade sao deferidos por baixo impacto ou dependencia de recursos externos:

| Finding | Motivo do Deferimento |
|---------|----------------------|
| B-01 (violacoes menores de densidade) | Custo de reescrita alto vs impacto baixo. Corrigir oportunisticamente durante edicoes |
| B-03 (secao inovacao fraca) | Requer pesquisa competitiva dedicada, fora do escopo desta correcao |
| B-04 (falta diagrama C4) | Pertence ao artefato de Arquitetura (proximo na cadeia BMAD), nao ao PRD |

---

## RESUMO DO PLANO

| Fase | Tasks | Findings Resolvidos | Commits |
|------|-------|-------------------|---------|
| **Fase 1: Criticos** | Tasks 1-5 | C-01, C-02, C-03, C-04, A-02, A-03, A-04 (parcial) | 5 |
| **Fase 2: Completude** | Tasks 6-10 | A-01, A-06, A-07 | 5 |
| **Fase 3: Qualidade** | Tasks 11-17 | M-01, M-02, M-03, M-04, M-08, M-09, B-02, B-06 | 7 |
| **Fase 4: Correcoes Review** | Tasks 18-20 | A-04 (completo), A-05, A-08, M-05, M-06 | 3 |
| **Fase 5: Fechamento** | Task 21 | B-05 + validacao final | 1 |
| **Total** | **21 tasks** | **24/27 findings** (3 deferidos) | **21 commits** |

### Dependencias entre Tasks

```
Task 1 (RF-10 status)      → independente
Task 2 (rastreabilidade)   → depende de Tasks 4, 5, 18, 19 (novos RFs + modulos + jornadas)
Task 3 (RF-11 tabela)      → independente
Task 4 (RF-17 Estoque)     → independente
Task 5 (RF-18 Billing)     → independente
Tasks 6-8 (ACs)            → dependem de Tasks 3, 4, 5 (RFs devem existir antes dos ACs)
Task 9 (NFRs)              → independente
Task 10 (Dependencias)     → independente
Tasks 11-17 (Qualidade)    → independentes entre si
Task 18 (RF-12 validacao)  → independente
Task 19 (Jornadas update)  → depende de Task 18 (saber quais modulos estao em RF-12)
Task 20 (RF-08.1 + baseline) → independente
Task 21 (Final)            → depende de TODAS as anteriores
```

### Ordem de Execucao Recomendada

**Batch 1 (paralelo):** Tasks 1, 3, 4, 5 — correcoes criticas e novos RFs
**Batch 2 (paralelo):** Tasks 6, 7, 8, 9, 10, 18, 20 — ACs, NFRs, RF-12 validacao, baselines
**Batch 3 (paralelo):** Tasks 2, 11, 12, 13, 14, 15, 16, 17, 19 — rastreabilidade, qualidade, jornadas
**Batch 4 (sequencial):** Task 21 — validacao final e versionamento
