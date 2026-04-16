# Critical Test Paths — Kalibrium ERP

Os 20% de testes que dao 80% de confianca. Use esta lista para validacao rapida antes de deploys e como guia de prioridade para cobertura de testes.

---

## 1. Smoke Tests (5 testes — validacao basica de vida)

Estes testes devem passar SEMPRE. Se qualquer um falhar, o deploy esta quebrado.

| # | Teste | Comando / Endpoint | Criterio de Sucesso |
|---|-------|--------------------|--------------------|
| S1 | Health Check | `curl /api/v1/health` | HTTP 200, JSON `{"status":"ok"}` |
| S2 | Login | `POST /api/v1/auth/login` com credenciais validas | HTTP 200, retorna token |
| S3 | Listar Work Orders | `GET /api/v1/work-orders` autenticado | HTTP 200, retorna array paginado |
| S4 | Criar Work Order | `POST /api/v1/work-orders` com payload valido | HTTP 201, retorna OS com ID |
| S5 | Frontend Build | `cd frontend && npm run build` | Exit code 0, sem erros TS |

### Comando rapido para smoke tests

```bash
# Backend
php artisan test --filter="HealthCheckTest|LoginTest|WorkOrderListTest|WorkOrderCreateTest"

# Frontend
cd frontend && npx tsc --noEmit && npm run build
```

---

## 2. Critical Path Tests (8 testes — fluxos de negocio essenciais)

Estes testes cobrem os fluxos que, se quebrarem, impedem o uso do sistema.

### CP1: Autenticacao Completa

```
Login -> Obter token -> Acessar rota protegida -> Refresh token -> Logout -> Token invalidado
```

- Verifica: token JWT/Sanctum funcional, middleware auth, logout invalida sessao

### CP2: Tenant Isolation

```
Login Tenant A -> Criar recurso -> Login Tenant B -> Listar recursos -> Recurso de A NAO aparece
```

- Verifica: BelongsToTenant scope, zero data leakage, queries filtradas

### CP3: Work Order Lifecycle

```
Criar OS (draft) -> Aprovar (approved) -> Iniciar (in_progress) -> Completar (completed) -> Faturar (invoiced)
```

- Verifica: maquina de estados, transicoes validas, transicoes invalidas rejeitadas

### CP4: Invoice -> Payment Flow

```
Criar Invoice -> Emitir (issued) -> Gerar Accounts Receivable -> Registrar Payment (partial) -> Registrar Payment (full) -> Status paid
```

- Verifica: criacao automatica de AR, calculo de saldo, status sync entre invoice e AR

### CP5: HR Timeclock (Ponto Digital)

```
Check-in -> Verificar geolocation -> Check-out -> Calcular horas trabalhadas -> Gerar espelho de ponto
```

- Verifica: compliance Portaria 671, calculo CLT, DSR, horas extras

### CP6: Calibration Certificate

```
Criar instrumento -> Agendar calibracao -> Registrar medicoes -> Calcular incerteza -> Gerar certificado -> Aprovar (double sign-off se ISO)
```

- Verifica: calculo metrologic, imutabilidade de medicoes, fluxo ISO 17025

### CP7: Service Call -> Work Order

```
Criar chamado (helpdesk) -> Classificar SLA -> Gerar OS automatica -> Atribuir tecnico -> Resolver -> Fechar chamado
```

- Verifica: criacao automatica de OS a partir de chamado, calculo de SLA, vinculo bidirecional

### CP8: SLA Calculation

```
Criar chamado com prioridade -> Clock inicia -> Pausar (on_hold) -> Retomar -> Resolver -> Verificar SLA met/breached
```

- Verifica: calculo de tempo com pausas, business hours, breach notification

---

## 3. Prioridade por Modulo

### P0 — Critico (Bloqueante para operacao)

| Modulo | Justificativa | Testes Minimos |
|--------|---------------|----------------|
| Core (Auth, Tenants, Users) | Sem auth nada funciona | Login, RBAC, tenant switch, permissions |
| Finance (Invoices, AR/AP) | Dinheiro = zero erro | CRUD invoice, payment flow, saldo, relatorios |
| WorkOrders | Core do negocio ERP | Lifecycle completo, atribuicao, status machine |

### P1 — Importante (Impacta operacao diaria)

| Modulo | Justificativa | Testes Minimos |
|--------|---------------|----------------|
| HR (Ponto, Ferias, Folha) | Compliance trabalhista | Check-in/out, calculo horas, DSR, ferias CLT |
| Lab (Calibracao, Certificados) | Compliance ISO 17025 | Medicoes, incerteza, certificado, audit trail |
| Helpdesk (Chamados, SLA) | Atendimento ao cliente | CRUD chamado, SLA calc, escalonamento |

### P2 — Relevante (Impacta eficiencia)

| Modulo | Justificativa | Testes Minimos |
|--------|---------------|----------------|
| CRM (Contatos, Pipeline) | Vendas e relacionamento | CRUD contato, pipeline stages, conversao |
| Inventory (Estoque) | Controle de materiais | Entrada, saida, saldo, alerta minimo |
| Procurement (Compras) | Abastecimento | Requisicao, aprovacao, ordem de compra |

### P3 — Complementar (Funcionalidades auxiliares)

| Modulo | Justificativa | Testes Minimos |
|--------|---------------|----------------|
| Portal (Cliente) | Self-service | Login portal, visualizar OS, abrir chamado |
| TvDashboard | Display passivo | Renderizacao, auto-refresh, dados corretos |
| Mobile/PWA | Campo | Offline sync, geolocation, push notification |

---

## 4. Matriz de Execucao

| Momento | Testes a executar | Tempo maximo |
|---------|-------------------|--------------|
| Pre-commit (hook) | Smoke S1-S5 | 30s |
| Pre-deploy (CI) | Smoke + Critical Path (CP1-CP8) | 5min |
| Pos-deploy | Smoke S1-S5 contra producao | 1min |
| Nightly (cron) | Suite completa P0 + P1 | 15min |
| Semanal | Suite completa todos os modulos | 30min |

---

## 5. Comando para rodar por prioridade

```bash
# P0 apenas (pre-deploy rapido)
php artisan test --group=p0

# P0 + P1 (nightly)
php artisan test --group=p0,p1

# Tudo
php artisan test

# Frontend
cd frontend && npx vitest run

# E2E
cd frontend && npx playwright test
```

> **Regra**: Se qualquer teste P0 falhar, o deploy NAO pode prosseguir. P1 falhas geram alerta mas nao bloqueiam. P2/P3 falhas sao registradas para correcao no proximo sprint.
