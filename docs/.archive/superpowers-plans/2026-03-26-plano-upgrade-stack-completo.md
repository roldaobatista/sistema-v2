---
status: active
type: implementation
title: "Upgrade Completo da Stack — Laravel 13 + Vite 8 + TypeScript 6 + Tailwind 4.2"
created: 2026-03-26
estimated_batches: 6
---

# Plano de Upgrade Completo da Stack Kalibrium ERP

## Resumo Executivo

Atualização de todas as tecnologias do projeto para as versões mais recentes, aproveitando novas funcionalidades para melhorias concretas no Kalibrium ERP.

| Tecnologia | De | Para | Risco | Benefício |
|---|---|---|---|---|
| Laravel | 12.x | **13.x** | Baixo (zero breaking changes) | AI SDK, Passkeys, Vector Search, Queue Routing |
| PHP | ^8.2 | **^8.3** | Baixo (Docker já usa 8.4) | Requisito L13, typed class constants, json_validate() |
| Vite | ^7.2.4 | **^8.0** | Médio (Rolldown substitui esbuild) | Builds 10-30x mais rápidos |
| TypeScript | ~5.9.3 | **^6.0** | Médio (strict default, ES5 deprecated) | Preparação TS7, defaults modernos |
| Tailwind CSS | ^4.1.18 | **^4.2** | Baixo (minor) | Bugfixes, novos utilitários |
| React | ^19.2.0 | **19.2.4** | Nenhum (patch) | Bugfixes |

---

## Pré-requisitos

- [ ] Backup completo do projeto (branch de segurança)
- [ ] Todos os testes passando na branch atual
- [ ] Docker atualizado localmente

---

## Batch 1: Preparação e Branch de Upgrade

**Objetivo:** Criar ambiente isolado e garantir baseline estável.

### Tarefas
1. Criar branch `feat/stack-upgrade-2026`
2. Rodar suite completa de testes — documentar resultado baseline
3. Verificar que Docker já usa PHP 8.4 (✅ confirmado)
4. Criar snapshot do `composer.lock` e `package-lock.json` atuais

### Gate
- [ ] Branch criada
- [ ] Testes baseline documentados (quantidade, tempo)
- [ ] Nenhum teste falhando

---

## Batch 2: Backend — Laravel 13 + PHP 8.3

**Objetivo:** Atualizar Laravel para v13 e ajustar requisito PHP.

### Tarefas

#### 2.1 Atualizar requisito PHP no composer.json
```json
"php": "^8.3"
```
> O Docker já roda PHP 8.4, sem impacto na infra.

#### 2.2 Atualizar Laravel Framework
```bash
composer require laravel/framework:^13.0 --update-with-dependencies
```

#### 2.3 Atualizar pacotes satélite do Laravel
```bash
composer require laravel/sanctum:^5.0 laravel/horizon:^6.0 laravel/reverb:^2.0 laravel/tinker:^3.0 --update-with-dependencies
```
> Verificar compatibilidade de cada um com L13. Se algum não tiver versão compatível, manter atual.

#### 2.4 Atualizar pacotes de dev
```bash
composer require --dev pestphp/pest:^3.8 larastan/larastan:^3.9 laravel/pint:^1.24 --update-with-dependencies
```

#### 2.5 Publicar novos configs se necessário
```bash
php artisan vendor:publish --tag=laravel-config
```

#### 2.6 Rodar testes
```bash
./vendor/bin/pest --parallel --processes=16 --no-coverage
```

### Gate
- [ ] `composer install` sem erros
- [ ] `php artisan serve` funciona
- [ ] Suite completa de testes passando
- [ ] Commit: `chore: upgrade Laravel 12 → 13, PHP ^8.3`

---

## Batch 3: Backend — Aproveitar Features do Laravel 13

**Objetivo:** Integrar funcionalidades novas do L13 que agregam valor ao Kalibrium.

### 3.1 Laravel AI SDK (substituir openai-php/laravel)

**Situação atual:** `openai-php/laravel` usado no `EmailClassifierService`.

**Ação:** Migrar para o AI SDK nativo do Laravel 13 que suporta múltiplos providers (OpenAI, Anthropic) com API unificada.

```bash
composer require laravel/ai
composer remove openai-php/laravel
```

**Arquivos impactados:**
- `app/Services/EmailClassifierService.php` — trocar chamadas OpenAI pelo AI SDK
- `config/ai.php` — configurar providers

**Benefício para o Kalibrium:**
- API unificada para texto, embeddings, tool-calling
- Possibilidade futura de usar Anthropic como fallback
- Embeddings nativos para classificação de emails/OS

### 3.2 Queue Routing por Classe

**Ação:** Configurar `Queue::route()` no `AppServiceProvider` para rotear jobs pesados automaticamente.

```php
// AppServiceProvider::boot()
Queue::route(EmailClassificationJob::class, queue: 'ai', connection: 'redis');
Queue::route(PdfGenerationJob::class, queue: 'pdf', connection: 'redis');
```

**Benefício:** Jobs de AI e PDF não competem com jobs normais no Horizon.

### 3.3 Reverb Database Driver (opcional)

**Situação atual:** Reverb usa driver padrão (in-memory).

**Ação:** Avaliar migração para database driver se horizontal scaling for necessário no futuro. Por agora, apenas documentar a possibilidade.

### 3.4 Cache::touch()

**Ação:** Aplicar `Cache::touch()` em endpoints de listagem pesada que já usam cache, para estender TTL sem re-fetch.

**Exemplo em `DashboardController`:**
```php
// Antes: Cache::remember('dashboard:stats', 300, fn() => ...)
// Depois: adicionar touch em leituras frequentes
Cache::touch('dashboard:stats', 300);
```

### 3.5 JSON:API Resources (avaliar)

**Ação:** Avaliar para endpoints públicos (portal do cliente). Não migrar toda a API existente — usar apenas em novos endpoints.

### Gate
- [ ] EmailClassifierService migrado para AI SDK
- [ ] Queue routing configurado
- [ ] Testes passando
- [ ] Commit: `feat: integrar Laravel 13 AI SDK, Queue Routing e Cache::touch()`

---

## Batch 4: Frontend — Vite 8 + Rolldown

**Objetivo:** Migrar bundler para Vite 8 com Rolldown.

### 4.1 Atualizar Vite
```bash
cd frontend
npm install vite@^8.0 --save-dev
```

### 4.2 Adaptar vite.config.ts

**Breaking changes a resolver:**
- `build.rollupOptions` → `build.rolldownOptions`
- `optimizeDeps.esbuildOptions` → `optimizeDeps.rolldownOptions` (se usado)
- CSS minification agora usa Lightning CSS por padrão (melhor)
- Verificar plugins customizados (dev SW kill, manifest, Sentry) — compatibilidade com Rolldown

```typescript
// Antes
build: {
  rollupOptions: { ... }
}
// Depois
build: {
  rolldownOptions: { ... }
}
```

### 4.3 Atualizar plugins Vite
```bash
npm install @tailwindcss/vite@latest @vitejs/plugin-react@latest @sentry/vite-plugin@latest --save-dev
```

### 4.4 Testar build completo
```bash
npm run build
npm run preview
```

### 4.5 Testar HMR
- Verificar que hot reload funciona em dev
- Testar mudanças em componentes React, CSS, TypeScript

### Gate
- [ ] `npm run build` sem erros
- [ ] `npm run dev` com HMR funcional
- [ ] Bundle size igual ou menor que antes
- [ ] Tempo de build medido (esperar 10-30x melhoria)
- [ ] Commit: `chore: upgrade Vite 7 → 8 (Rolldown)`

---

## Batch 5: Frontend — TypeScript 6.0

**Objetivo:** Atualizar TypeScript para v6 com ajustes de compatibilidade.

### 5.1 Atualizar TypeScript
```bash
cd frontend
npm install typescript@^6.0 --save-dev
```

### 5.2 Ajustar tsconfig.json

**Mudanças de defaults no TS6:**
- `strict: true` agora é default (✅ projeto já usa strict — sem impacto)
- `module` default agora é `esnext` (✅ projeto já usa ESNext — sem impacto)
- `target` default agora é ES2025 (projeto usa ES2022 — avaliar subir)

```jsonc
// Atualizar target se desejado
{
  "compilerOptions": {
    "target": "ES2025", // era ES2022
    // remover configurações que agora são default:
    // "strict": true — agora é default, pode manter explícito para clareza
  }
}
```

### 5.3 Verificar erros de tipo
```bash
npx tsc --noEmit
```

**Possíveis problemas:**
- TS6 pode ser mais rígido em inferência de tipos
- `any` implícito em lugares antes tolerados pode virar erro
- Corrigir incrementalmente

### 5.4 Atualizar ESLint config se necessário
```bash
npm install @typescript-eslint/parser@latest @typescript-eslint/eslint-plugin@latest --save-dev
```

### Gate
- [ ] `npx tsc --noEmit` sem erros
- [ ] `npm run build` sem erros
- [ ] Testes frontend passando (`npx vitest run`)
- [ ] Commit: `chore: upgrade TypeScript 5.9 → 6.0`

---

## Batch 6: Frontend — Tailwind 4.2 + React Patch + Polimento

**Objetivo:** Atualizar dependências menores e aplicar melhorias finais.

### 6.1 Atualizar Tailwind CSS
```bash
cd frontend
npm install tailwindcss@^4.2 @tailwindcss/vite@^4.2 --save-dev
```
> Minor update, risco mínimo. O projeto já usa CSS-based config v4.

### 6.2 Atualizar React (patch)
```bash
npm update react react-dom
```
> Já coberto pelo `^19.2.0`, apenas aplica patch 19.2.4.

### 6.3 Atualizar demais dependências
```bash
npm update @tanstack/react-query react-hook-form zustand axios react-router-dom
```

### 6.4 Audit de segurança
```bash
npm audit
composer audit
```

### 6.5 Rodar suite completa de testes
```bash
# Backend
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage

# Frontend
cd frontend && npx vitest run

# E2E
cd frontend && npx playwright test
```

### Gate
- [ ] Zero vulnerabilidades críticas
- [ ] Todos os testes passando (backend + frontend + e2e)
- [ ] Build de produção funcional
- [ ] Commit: `chore: atualizar Tailwind 4.2, React 19.2.4 e dependências`

---

## Oportunidades de Melhoria Pós-Upgrade

### Imediatas (usar nas próximas sprints)

| Feature | Tecnologia | Aplicação no Kalibrium |
|---|---|---|
| **AI SDK unificado** | Laravel 13 | Classificação de emails, sugestões de orçamento, análise de OS |
| **Builds 10-30x mais rápidos** | Vite 8 | DX melhor, CI/CD mais rápido |
| **Cache::touch()** | Laravel 13 | Dashboard e relatórios com cache mais inteligente |
| **Queue Routing** | Laravel 13 | Separar filas de PDF, AI, email, notificação |

### Futuras (avaliar em 1-2 meses)

| Feature | Tecnologia | Aplicação no Kalibrium |
|---|---|---|
| **Passkey Auth** | Laravel 13 | Login sem senha para técnicos em campo (biometria) |
| **Vector Search** | Laravel 13 + pgvector | Busca semântica em produtos, serviços, OS (requer migração para PostgreSQL) |
| **JSON:API Resources** | Laravel 13 | Portal público do cliente com API padronizada |
| **Team Multi-Tenancy** | Laravel 13 Starter Kit | Avaliar se simplifica o tenant system atual |
| **TypeScript 7 (Go)** | TS 7.0 preview | Type-checking 10x mais rápido (quando estável) |
| **Reverb DB Driver** | Laravel 13 | Horizontal scaling de WebSocket sem Redis |

### Requer Mudança de Infra

| Feature | Requisito | Benefício |
|---|---|---|
| **Vector Search nativo** | PostgreSQL + pgvector | Busca semântica em todo o ERP |
| **Passkeys** | HTTPS obrigatório + WebAuthn | Eliminar senhas para usuários mobile |

---

## Riscos e Mitigações

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Pacote incompatível com L13 | Baixa | L13 tem zero breaking changes; testar cada pacote |
| Plugin Vite incompatível com Rolldown | Média | Testar plugins um a um; fallback para Vite 7 |
| TS6 strict demais em código legado | Baixa | Projeto já usa strict mode |
| Tailwind 4.2 quebra estilos | Muito baixa | Minor update, sem breaking changes |
| Rolldown CJS interop | Média | Testar imports de libs CJS (leaflet, etc.) |

---

## Ordem de Execução Recomendada

```
Batch 1 (Preparação)          ← 15 min
  ↓
Batch 2 (Laravel 13 + PHP)    ← 1-2h
  ↓
Batch 3 (Features L13)        ← 2-3h
  ↓
Batch 4 (Vite 8)              ← 1-2h
  ↓
Batch 5 (TypeScript 6)        ← 1h
  ↓
Batch 6 (Tailwind + Polish)   ← 30min
```

**Total estimado: ~6-8h de trabalho**

Cada batch tem seu próprio commit e gate de qualidade. Se qualquer batch falhar, os anteriores já estão commitados e estáveis.
