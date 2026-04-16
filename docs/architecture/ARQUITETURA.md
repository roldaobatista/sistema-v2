---
type: root_architecture
title: "Master Plan"
---
# Visão Geral Arquitetural Kalibrium (SaaS)

> **Esta é a "Pedra de Rosetta" do sistema Kalibrium.**
> Criado para ser desenvolvido 100% via IA (AIDD).

> **[AI_RULE_CRITICAL] REGRA DE BOOTSTRAP PARA AGENTES:**
> Ao iniciar qualquer nova feature, a IA **DEVE OBRIGATORIAMENTE** ler a metodologia em `../BLUEPRINT-AIDD.md` e validar as migrations em `backend/database/migrations/` e os Models em `backend/app/Models/`. Ignorar isso causará viés cognitivo e alucinação arquitetural.

Toda a base do sistema gira em torno do **Modular Monolith**. Todos os `Bounded Contexts` convivem no mesmo deploy Laravel, porém são protegidos sob namespaces brutais em `app/Modules/`.

## Leis Invioláveis do Kernel Kalibrium

1. **Dados Estritos por Inquilino:** Qualquer model de domínio compartilha o mesmo banco via a coluna `tenant_id` cravada por um `GlobalScope`.
2. **Resiliência Transacional:** Atualizações paralelas que quebram o código antes da linha de fim devem sofrer `rollback()` absoluto do SQL.
3. **Barreira de Eventos e ACLs:** Modulos não acessam a database dos visinhos. Troca-se interfaces `Contracts` (`Interface::class`) ou retransmite-se Eventos DTO pela fila (Jobs).
4. **Offline First (ULIDs):** Fronteiras Mobile (Ordens de Trabalho e Ponto Eletrônico) não utilizam banco de dados central auto-increment. Os IDs únicos nascem no cliente offline sob UUIDv4/ULID e sincronizam depois.
5. **Acessibilidade Frontend (a11y):** Componentes estéticos que quebram validadores (sem `aria-labels`, form fields mudos para Screen Readers) tem Zero tolerância no ciclo de merge.

## Navegando nas Sub-Arvores

- Se precisar estender uma Rota, leia primeiro **14. Camadas da Aplicação e 04. Estrutura de Modulos**.
- Se for usar dados assíncronos, devore **08. Semântica de Eventos**.
- Se for conectar com INMETRO, devore **12. Anti-Corruption Layer**.

## Stack Tecnológico

| Camada | Tecnologia | Versão | Propósito |
|--------|-----------|--------|-----------|
| **Backend** | Laravel (PHP 8.4) | 12.x | API REST, regras de negócio, filas |
| **Frontend** | React + TypeScript | 19.x | SPA com Vite, componentes reutilizáveis |
| **Banco de Dados** | MySQL | 8.x | Persistência principal, multi-tenant lógico |
| **Cache / Filas** | Redis | 7.x | Cache, sessions, queue driver, locks |
| **WebSockets** | Laravel Reverb | 1.x | Real-time events (dashboard, notificações) |
| **Autenticação** | Sanctum | 4.x | Token-based auth (SPA + API) |
| **Autorização** | Spatie Permissions | 6.x | Roles, permissions, teams (tenant-aware) |
| **Bundler** | Vite | 6.x | Build do frontend, HMR em dev |
| **PWA** | Service Worker | - | Offline-first para técnicos de campo |

## Mapa de Módulos do Sistema

O Kalibrium é organizado em Bounded Contexts (módulos) com responsabilidades claras:

```
┌─────────────────────────────────────────────────────────┐
│                    CORE / SHARED                         │
│  Auth, Users, Tenants, Settings, Notifications          │
└─────────────┬───────────────────────────┬───────────────┘
              │                           │
    ┌─────────▼─────────┐     ┌──────────▼──────────┐
    │   WORK ORDERS     │     │      FINANCE        │
    │   Ordens de       │────▶│   Faturas, Contas   │
    │   Serviço, Agenda │     │   Comissões, Caixa  │
    └─────────┬─────────┘     └──────────┬──────────┘
              │                           │
    ┌─────────▼─────────┐     ┌──────────▼──────────┐
    │      CRM          │     │      QUOTES         │
    │   Leads, Clientes │────▶│   Orçamentos        │
    │   Pipeline        │     │   Aprovações        │
    └───────────────────┘     └─────────────────────┘

    ┌───────────────────┐     ┌─────────────────────┐
    │   CALIBRATION     │     │        HR           │
    │   Certificados    │     │   Ponto Digital     │
    │   ISO 17025       │     │   Portaria 671      │
    │   Equipamentos    │     │   eSocial, CLT      │
    └───────────────────┘     └─────────────────────┘

    ┌───────────────────┐     ┌─────────────────────┐
    │    INVENTORY      │     │       PWA           │
    │   Estoque, Peças  │     │   Offline Sync      │
    │   Movimentações   │     │   Service Worker    │
    └───────────────────┘     └─────────────────────┘
```

### Dependências entre Módulos

- **WorkOrders → Finance**: Fechamento de OS gera fatura automaticamente.
- **WorkOrders → Quotes**: OS pode ser criada a partir de orçamento aprovado.
- **CRM → Quotes**: Lead convertido gera orçamento.
- **HR → Finance**: Folha de pagamento gera lançamentos financeiros.
- **Calibration → WorkOrders**: Calibração pode ser vinculada a uma OS.

> **[AI_RULE]** Dependências são SEMPRE via Events ou Contracts. Nunca import direto de Model de outro módulo.

## As 3 Leis Invioláveis (Resumo Executivo)

### Lei 1: Isolamento Total de Dados por Tenant

```php
// TODA query é filtrada automaticamente pelo TenantScope
// Documentação completa: 06-6-modelo-de-multi-tenancy.md
trait BelongsToTenant {
    protected static function bootBelongsToTenant(): void {
        static::addGlobalScope(new TenantScope);
        static::creating(fn ($m) => $m->tenant_id = $m->tenant_id ?? auth()->user()->current_tenant_id);
    }
}
```

### Lei 2: Consistência Transacional Absoluta

```php
// Operações dependentes SEMPRE dentro de DB::transaction
// Documentação completa: 10-10-consistência-transacional.md
DB::transaction(function () {
    $invoice = Invoice::create([...]);
    $invoice->items()->createMany([...]);
    AccountsReceivable::create(['invoice_id' => $invoice->id, ...]);
});
```

### Lei 3: Comunicação entre Módulos via Contratos

```php
// Módulos se comunicam via interfaces, NUNCA via imports diretos de Models
// Documentação completa: 16-16-critérios-de-extração-de-módulo.md
interface InvoiceServiceInterface {
    public function createFromWorkOrder(int $workOrderId): Invoice;
}
```

## Estrutura de Diretórios Principal

```
sistema/
├── backend/                    # Laravel 13
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/   # Controllers RESTful
│   │   │   ├── Requests/             # FormRequests (validação)
│   │   │   ├── Resources/V1/         # API Resources (transformação)
│   │   │   └── Middleware/            # Auth, CORS, Feature flags
│   │   ├── Models/                    # Eloquent Models + BelongsToTenant
│   │   ├── Services/                  # Regras de negócio
│   │   ├── Policies/                  # Autorização por model
│   │   ├── Events/                    # Eventos de domínio
│   │   ├── Listeners/                 # Side effects
│   │   ├── Jobs/                      # Processamento assíncrono
│   │   └── Exceptions/               # BusinessRuleException, etc.
│   ├── database/
│   │   ├── migrations/                # Schema do banco
│   │   ├── factories/                 # Factories para testes
│   │   └── seeders/                   # Seeds + PermissionsSeeder
│   ├── routes/
│   │   └── api.php                    # Rotas da API
│   └── tests/
│       ├── Feature/                   # Testes de endpoint (HTTP)
│       └── Unit/                      # Testes de service/model
│
├── frontend/                   # React 19 + TypeScript + Vite
│   ├── src/
│   │   ├── components/               # Componentes reutilizáveis
│   │   ├── pages/                    # Páginas por rota
│   │   ├── hooks/                    # Custom hooks
│   │   ├── services/                 # API clients (Axios)
│   │   ├── types/                    # Interfaces TypeScript
│   │   └── contexts/                 # React Contexts
│   └── public/                       # Assets estáticos
│
├── database/
│   └── schema.prisma                 # [LEGACY] Schema de referencia — consultar backend/database/migrations/ como fonte da verdade
│
└── docs/                       # Documentação arquitetural
    ├── architecture/                 # Padrões e decisões
    ├── modules/                      # Docs por módulo
    └── BLUEPRINT-AIDD.md            # Metodologia AIDD
```

## Índice de Documentos Arquiteturais

| # | Documento | Conteúdo |
|---|-----------|----------|
| 01 | Visão do Produto | Escopo, personas, proposta de valor |
| 02 | Requisitos Não-Funcionais | Performance, segurança, SLA |
| 03 | Stack Tecnológico | Decisões de tecnologia e justificativas |
| 04 | Estrutura de Módulos | Organização de namespaces e pastas |
| 05 | Modelo de Dados | ERD, convenções de naming |
| **06** | **Multi-Tenancy** | **BelongsToTenant, TenantScope, isolamento** |
| 07 | Autenticação e Autorização | Sanctum, Spatie, Policies |
| 08 | Semântica de Eventos | Events, Listeners, Jobs |
| 09 | API Design | REST conventions, versionamento, Resources |
| **10** | **Consistência Transacional** | **DB::transaction, Saga, idempotência** |
| 11 | Testes | PHPUnit, Feature, Unit, factories |
| 12 | Anti-Corruption Layer | Integrações externas (eSocial, INMETRO) |
| **13** | **Observabilidade** | **Logs estruturados, Correlation ID, Pulse** |
| **14** | **Camadas da Aplicação** | **Controller → Service → Model** |
| 15 | Frontend Architecture | React, TypeScript, componentes |
| **16** | **Extração de Módulo** | **Critérios, processo, comunicação** |
| 17 | Deploy e Infra | CI/CD, servidor, processos |
| **18** | **Configurabilidade por Tenant** | **Feature flags, TenantSetting** |
| **19** | **Estratégia de Cache** | **Redis, tags, invalidação, locks** |
| **20** | **Eventos, Listeners e Observers** | **Events, Listeners, Observers lifecycle** |

> **[AI_RULE]** Documentos em **negrito** são os mais referenciados por agentes IA. Ao começar uma task, leia pelo menos 06, 14 e o documento do módulo relevante.
