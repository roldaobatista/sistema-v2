---
type: devops_architecture
---
# Stack Tecnologica Oficial (BOM)

> **[AI_RULE]** Instalar plugins aleatorios cria um monstro de supply chain attacks no Kalibrium.

## 1. Controle de Bibliotecas (Bill of Materials) `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] O Fechamento NPM / Composer**
> E **EXPRESSAMENTE PROIBIDO** para a Inteligencia Artificial sugerir "npm install X" ou "composer require Y" para resolver problemas triviais de algoritmo (ex: formatador de mascara CPF de Terceiros).
>
> - A dependencia DEVE estar restrita a pacotes Tier 1 (Spatie, Laravel nativo, pacotes ultra-comprovados tipo Tailwind/React Aria).
> - Cada novo pacote adiciona risco de abandono (bus factor) ou falha de supply chain. A IA precisa exaurir o uso de Vanilla TS/PHP antes de corromper o `package.json`.

## 2. Stack Master

### Backend (PHP)

| Tecnologia | Versao | Proposito |
|-----------|--------|-----------|
| PHP | 8.4 | Runtime |
| Laravel | 12.x | Framework web |
| Laravel Sanctum | latest | Autenticacao SPA (cookie) + API (token) |
| Laravel Reverb | latest | WebSockets (real-time) |
| Laravel Horizon | latest | Dashboard de filas Redis |
| Spatie Laravel Permission | latest | Roles, permissions, multi-tenant teams |
| Spatie Laravel Activitylog | latest | Audit log automatico |
| Spatie Laravel MediaLibrary | latest | Upload e gestao de arquivos |
| Laravel Pint | latest | Code style (PSR-12) |
| Pest | latest | Framework de testes (preferencial) |
| PHPUnit | latest | Framework de testes (legado) |
| PHPStan / Larastan | nivel maximo | Analise estatica |
| Spatie Laravel Health | latest | Health checks e monitoramento de servicos |

### Database e Cache

| Tecnologia | Versao | Proposito |
|-----------|--------|-----------|
| MySQL | 8.0+ | Banco relacional (strict mode) |
| Redis | 7+ | Cache, sessions, queues, pub/sub |

### Frontend (TypeScript)

| Tecnologia | Versao | Proposito |
|-----------|--------|-----------|
| React | 19.x | UI library |
| Vite | latest | Build tool e dev server |
| TypeScript | 5.x | Type safety |
| Tailwind CSS | v4 | Utility-first CSS |
| React Query (TanStack) | v5 | Estado de servidor e cache |
| Zustand | latest | Estado UI local |
| React Hook Form | latest | Formularios |
| Zod | latest | Validacao de schemas |
| Axios | latest | HTTP client |
| Vitest | latest | Testes unitarios |
| Playwright | latest | Testes E2E |
| React Aria | latest | Acessibilidade (a11y) |

### Infra e DevOps

| Tecnologia | Proposito |
|-----------|-----------|
| Nginx | Reverse proxy, static files, SSL |
| Supervisor | Process manager para queue workers |
| Docker + Docker Compose | Containerizacao — metodo canonico de deploy em producao |
| Git + GitHub | Versionamento e CI |

## 3. Pacotes Proibidos `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** Os seguintes pacotes estao **PROIBIDOS** por redundancia ou risco:

| Pacote | Motivo | Alternativa |
|--------|--------|-------------|
| `next`, `nuxt`, `angular` | Stack e React SPA + Vite | — |
| `passport` | Sanctum e suficiente | Laravel Sanctum |
| `jwt-auth` | Sanctum cobre o caso | Laravel Sanctum |
| `redux`, `mobx` | Zustand + React Query cobrem | Zustand |
| `moment.js` | Abandonado, pesado | `date-fns` ou nativo |
| `lodash` | Maioria disponivel em JS nativo | Vanilla JS/TS |
| `bootstrap`, `material-ui` | Design system proprio (Tailwind) | Tailwind CSS |
| `prisma` | Stack usa Laravel Migrations/Eloquent | Eloquent ORM |
| `knex`, `typeorm` | Stack usa Eloquent | Eloquent ORM |
| Qualquer CDN jQuery | Legado, nao necessario | React |

## 4. Criterio para Adicionar Dependencia

Antes de adicionar qualquer pacote novo, o agente deve:

1. **Verificar se o BOM ja cobre** — React Query, Zustand, Spatie, etc ja resolvem?
2. **Tentar Vanilla primeiro** — PHP/TS nativo resolve em < 50 linhas?
3. **Verificar manutencao** — ultimo commit < 6 meses? > 1000 stars? Sem CVEs abertas?
4. **Registrar ADR** — Se aprovado, documentar em `ADR.md` com justificativa

> **[AI_RULE]** Na duvida, NAO instale. Codigo vanilla e mais seguro que dependencia morta.
