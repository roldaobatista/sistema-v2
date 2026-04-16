---
type: architecture_overview
id: 00
---
# 00. Introdução aos Padrões Arquiteturais

> **[AI_RULE]** Os arquivos numerados nesta pasta são LEIS ABSOLUTAS. O agente nunca deve bypassar essas regras.

## 1. O Que é o Kalibrium ERP?

O **Kalibrium** é um ERP SaaS multi-tenant projetado para empresas de **assistência técnica em campo**, **laboratórios metrológicos** e **prestação de serviços técnicos**. O sistema cobre desde o CRM (captação de clientes e orçamentos) até a emissão fiscal, passando por ordens de serviço, gestão de frota, controle de estoque, RH/ponto digital e certificados de calibração rastreáveis ao INMETRO.

## 2. Público-Alvo desta Documentação

Esta documentação arquitetural serve dois consumidores principais:

1. **Agentes de IA (AIDD):** Todo agente que opera no código DEVE ler estes documentos antes de criar features, corrigir bugs ou propor refatorações. Os marcadores `[AI_RULE]` e `[AI_RULE_CRITICAL]` definem restrições invioláveis.
2. **Desenvolvedores Humanos:** Engenheiros que precisam entender as decisões estruturais, os bounded contexts e as fronteiras de cada módulo.

## 3. Estrutura dos Documentos `[AI_RULE]`

> **[AI_RULE]** Cada documento numerado (01 a 15+) cobre um pilar arquitetural específico. A leitura sequencial é recomendada para contexto completo, mas cada arquivo é auto-contido.

| Arquivo | Pilar |
|---------|-------|
| `01` | Escopo Arquitetural e Rastreabilidade (Trace IDs) |
| `02` | Por Que Modular Monolith |
| `03` | Bounded Contexts (Domínios) |
| `04` | Estrutura de Diretórios de um Módulo |
| `05` | Ownership e Fronteiras por Contexto |
| `07` | Comunicação Entre Módulos |
| `08` | Semântica de Eventos e Mensageria |
| `09` | CQRS (Command Query Responsibility Segregation) |
| `11` | Arquitetura Mobile/Offline e Sincronização |
| `12` | Anti-Corruption Layer (Integrações Externas) |
| `15` | API Versionada e Contratos |

## 4. Princípios Fundacionais

```
┌─────────────────────────────────────────────────────┐
│              Kalibrium ERP - Pilares                │
├─────────────────────────────────────────────────────┤
│  1. Multi-Tenancy Estrita (tenant_id em tudo)       │
│  2. Modular Monolith (não microsserviços)           │
│  3. Offline First (ULIDs no mobile)                 │
│  4. Segurança por Design (Sanctum + Spatie RBAC)    │
│  5. Observabilidade Nativa (Trace IDs, Health)      │
│  6. Acessibilidade Frontend (WCAG 2.1 AA)          │
└─────────────────────────────────────────────────────┘
```

## 5. Stack Resumida

- **Backend:** Laravel 13 (PHP 8.2+) com Modular Monolith em `app/Modules/`
- **Frontend:** React 19 + TypeScript + Vite (SPA) com Tailwind v4
- **Banco:** MySQL 8.0+ (dados) + Redis 7+ (cache, filas, sessões)
- **Realtime:** Laravel Reverb (WebSockets)
- **Auth:** Laravel Sanctum (tokens SPA + API)
- **Permissões:** Spatie Laravel Permission (roles + permissions por tenant)

## 6. Como Navegar

- Para entender a **filosofia**, comece pelo `02-por-que-modular-monolith.md`
- Para entender os **domínios**, leia `03-bounded-contexts.md`
- Para **criar um módulo novo**, siga `04-estrutura-de-um-módulo.md`
- Para **decisões já tomadas**, consulte `ADR.md`
- Para o **estado atual do projeto**, veja `CURRENT_STATE.md`
