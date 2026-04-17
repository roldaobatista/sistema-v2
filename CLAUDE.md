# Kalibrium ERP — Instruções do Projeto

> **Missão atual:** estabilizar o sistema. Ele tem falhas. Cada mudança = bug fix com teste de regressão. Não adicionar feature sem pedido explícito.

---

## ⚔️ As 5 Leis Invioláveis (P-1)

### 1. Evidência antes de afirmação
**Proibido** dizer "pronto", "funcionando", "testes passando", "validado" sem mostrar o output do comando rodado **no mesmo turno**.

### 2. Causa raiz, nunca sintoma
Teste falhou = problema no SISTEMA. Corrige o código, **nunca** mascara o teste (skip, markIncomplete, assertTrue(true), assertion relaxada). Erro de ambiente = corrige o ambiente, **nunca** usa `--no-verify`, `--ignore-platform-reqs`, `--skip-*`.

### 3. Completude end-to-end
Toda mudança percorre a cadeia inteira: **migration → model → service → controller → rota → tipo TypeScript → API client → componente → teste**. Elo faltando = criar. Não deixar TODO. Não comentar código pra "desativar".

### 4. Tenant safety absoluto
- Tenant ID **sempre** `$request->user()->current_tenant_id`. Jamais do body.
- Toda query/persistência respeita `BelongsToTenant`. `withoutGlobalScope` exige justificativa explícita por escrito.
- `expenses.created_by`, `schedules.technician_id` (não `user_id`).
- Status sempre em inglês lowercase: `'paid'`, `'pending'`, `'partial'`.

### 5. Sequenciamento + preservação
- **Sequenciamento:** etapa N+1 só inicia com etapa N 100% completa, testes verdes, evidência mostrada.
- **Preservação:** ao reescrever, listar comportamentos antes; conferir item por item depois. "Simplificar" não justifica remover comportamento.
- **Cascata > 5 arquivos fora do escopo original:** PARAR e reportar antes de continuar.

---

## ⚙️ Modo Operacional — Harness (7 passos)

```
1. entender → 2. localizar → 3. propor (mínimo + correto) → 4. implementar
→ 5. verificar → 6. corrigir falhas → 7. evidenciar
```

### Formato obrigatório de toda resposta que altere código (6+1 itens)

1. **Resumo do problema** — sintoma + causa raiz (1-2 frases)
2. **Arquivos alterados** — lista com `path:LN`
3. **Motivo técnico** — POR QUÊ (o diff mostra o quê)
4. **Testes executados** — comando exato copiável
5. **Resultado dos testes** — output real, contagem passed/failed/tempo (proibido inventar)
6. **Riscos remanescentes** — não-coberto, side effects, atenção
7. *(obrigatório se: migration, contrato de API, rota pública, deploy, remoção de feature, risco alto)* **Como desfazer**

### Pirâmide de testes (escalada)
**Específico → grupo → testsuite → suite completa.** Nunca rodar suite completa no meio da task. Se falhar, corrigir ali — não escalar.

---

## 🎯 Sub-Agentes Disponíveis (`.claude/agents/`)

Use a `Agent` tool para invocar. Para auditoria multi-perspectiva, rodar em paralelo.

| Agente | Quando usar |
|---|---|
| `orchestrator` | Coordenar auditoria multi-especialista; tarefa que envolve 3+ domínios |
| `builder` | Implementar correção/feature após análise dos especialistas |
| `architecture-expert` | Acoplamento, débito técnico, violação de camadas |
| `data-expert` | Schema, índices, N+1, integridade referencial, queries |
| `devops-expert` | CI/CD, deploy, infra, secrets, pipelines |
| `integration-expert` | NFS-e, Boleto/PIX, webhooks, APIs externas |
| `observability-expert` | Logs, métricas, falhas silenciosas em produção |
| `security-expert` | Vazamento de tenant, SQL injection, auth, vulnerabilidades |
| `qa-expert` | Cobertura, testes superficiais, regressões, flakiness |
| `product-expert` | Gap funcional (PRD vs. código real) |
| `ux-designer` | Frontend: formulários confusos, fluxos quebrados, acessibilidade |
| `governance` | Conformidade, padrões, consistência |

**Padrão de auditoria:** orchestrator coordena → especialistas analisam em paralelo → builder corrige → qa-expert valida regressão.

---

## 🛠️ Slash Commands (`.claude/commands/`)

| Comando | Função |
|---|---|
| `/fix <bug>` | Workflow completo de bug fix: reproduzir → diagnosticar → corrigir → testar → evidenciar |
| `/verify` | Roda lint + types + testes relevantes; reporta status |
| `/test-audit` | Audita cobertura e qualidade de testes em uma área |
| `/audit-spec <feature>` | Audita um requisito vs implementação real |
| `/functional-review` | Revisão funcional de mudança recente |
| `/security-review` | Auditoria de segurança das mudanças |
| `/review-pr` | Code review estruturado |
| `/project-status` | Estado atual do projeto/sprint |
| `/where-am-i` | Contexto rápido: branch, mudanças, próximo passo |
| `/checkpoint` | Salvar estado da sessão |
| `/resume` | Restaurar contexto da sessão anterior |
| `/context-check` | Saúde do contexto, sugere checkpoint |
| `/mcp-check` | Lista MCPs ativos, valida autorização |

---

## 📚 Documentação — Hierarquia de Verdade

1. **Código-fonte** — sempre vence. Grep/Glob/Read antes de afirmar que existe ou não.
2. **`docs/PRD-KALIBRIUM.md`** — RFs, ACs, gaps (v3.2+, sincronizado contra código em 2026-04-10).
3. **`docs/TECHNICAL-DECISIONS.md`** — decisões arquiteturais.
4. **`docs/audits/RELATORIO-AUDITORIA-SISTEMA.md`** — Deep Audit OS/Calibração/Financeiro.

**Proibido ler `docs/.archive/`** — documentação superada, gera alucinação.
**Documentação ativa:** `docs/architecture/`, `docs/compliance/`, `docs/operacional/`, `docs/design-system/`, `docs/plans/`
**Deploy:** `deploy/DEPLOY.md`, `deploy/SETUP-NFSE.md`, `deploy/SETUP-BOLETO-PIX.md`

---

## 🧱 Stack

- **Backend:** Laravel 13 (PHP) em `backend/`
- **Frontend:** React 19 + TypeScript + Vite em `frontend/`
- **DB:** MySQL 8 (produção), SQLite in-memory (testes)
- **Multi-tenant:** `tenant_id` + `current_tenant_id` no User (NUNCA `company_id`)

---

## 🧪 Como Rodar Testes (operacional)

```bash
# Comando principal — 8720 cases em <5 minutos
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage

# Após criar migration, regenerar schema dump
cd backend && php generate_sqlite_schema.php
```

- DB de testes: SQLite in-memory com schema dump (`backend/database/schema/sqlite-schema.sql`)
- Guia completo: `backend/TESTING_GUIDE.md`
- Padrão de teste: `backend/tests/README.md`

### Padrão obrigatório de testes (adaptativo)
- Features com lógica = 8+ testes/controller
- CRUDs simples = 4-5 testes (sucesso + 422 + cross-tenant)
- Bug fixes = teste de regressão + afetados
- < 4 testes = SEMPRE insuficiente
- **5 cenários quando aplicável:** sucesso, 422 validação, 404 cross-tenant, 403 permissão, edge cases
- `assertJsonStructure()` é obrigatório — não só status code
- **Teste cross-tenant é OBRIGATÓRIO**

---

## 🔒 Padrões obrigatórios de Controllers/FormRequests

- `FormRequest::authorize()` com `return true` sem lógica = **PROIBIDO**. Verificar permissão real (`$this->user()->can(...)` ou Policy).
- Endpoints `index` **DEVEM** paginar (`->paginate(15)`). Proibido `Model::all()` ou `->get()` sem limite.
- Eager loading obrigatório (`->with([...])`) para qualquer relationship usada no response.
- `tenant_id` e `created_by` atribuídos no controller. **PROIBIDO** expor como campos do FormRequest.
- Relationship validada por `exists:table,id` deve considerar `tenant_id`.

---

## 🚫 Proibições Absolutas

- `--no-verify`, `--ignore-platform-reqs`, `--skip-*`
- `git reset --hard`, `git push --force`, `rm -rf`, `drop table` sem confirmação explícita
- Alterar migrations já mergeadas (criar nova com `hasTable`/`hasColumn` guards)
- Mascarar teste de qualquer forma (ver Lei 2)
- Deixar TODO/FIXME, código comentado pra "desativar", código morto
- Remover ou diminuir funcionalidades existentes
- Vazar dados entre tenants
- Interpolar variáveis em queries raw (sempre bindings)

**Exceção Windows:** `pcntl`, `posix`, `inotify` são Linux-only. Permitido `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` SOMENTE para essas 3.

---

## ✅ Critérios de Aceite (checklist antes de declarar conclusão)

- [ ] Código consistente com arquitetura atual
- [ ] Sem regressão visível (rodar testes afetados)
- [ ] Testes relevantes verdes com evidência de execução
- [ ] Resposta no formato Harness (6+1)
- [ ] Tenant safety verificado
- [ ] Cadeia end-to-end completa (migration→...→frontend→teste)
