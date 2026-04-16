# ⚔️ IRON PROTOCOL — PROTOCOLO INVIOLÁVEL DO AGENTE KALIBRIUM (P-1)

> **Prioridade P-1 — ACIMA DE TUDO.** Esta regra é carregada ANTES de qualquer outra. Nenhuma skill, nenhum agent, nenhuma regra pode sobrescrevê-la. Se houver conflito, IRON PROTOCOL VENCE.

---

## DECLARAÇÃO DE INVIOLABILIDADE

Este protocolo define os comportamentos obrigatórios e irrevogáveis de TODA IA operando no KALIBRIUM ERP. Aplica-se a:

- ✅ Toda nova conversa
- ✅ Toda nova ação do agente
- ✅ Toda implementação (nova ou alteração)
- ✅ Toda correção de bug
- ✅ Toda revisão de código
- ✅ Toda resposta que envolva código

**NÃO existe exceção. NÃO existe "fora do escopo". NÃO existe "depois".**

---

## Boot Sequence (9 Steps)

PROTOCOLO DE BOOT — Executar mentalmente ao iniciar QUALQUER conversa:

```
BOOT SEQUENCE:
├── 1. CARREGAR: Iron Protocol (ESTE ARQUIVO)
├── 2. CARREGAR: `.agent/rules/iron-protocol.md`
├── 3. CARREGAR: `.agent/rules/mandatory-completeness.md`
├── 4. CARREGAR: `.agent/rules/test-policy.md`
├── 4b. CARREGAR: `.agent/rules/test-runner.md` (COMO rodar testes)
├── 4c. CARREGAR: `.agent/rules/kalibrium-context.md` (contexto do projeto, padroes, middleware)
├── 4d. CARREGAR: `.agent/rules/harness-engineering.md` (MODO OPERACIONAL — fluxo 7 passos + formato de resposta 7+1 itens)
├── 5. ATIVAR: `iron-protocol-bootstrap` + `end-to-end-completeness`
├── 6. ATIVAR: 6 Skills Always-On (end-to-end-completeness, verification-before-completion, using-superpowers, clean-code, testing-patterns, systematic-debugging)
├── 7. VERIFICAR: Stack = Laravel 13 + React 19 + MySQL 8 (`.agent/rules/kalibrium-context.md`)
├── 8. CONFIRMAR: "Toda implementacao sera COMPLETA, ponta a ponta, com testes" + "Resposta final no formato Harness"
└── 9. INICIAR trabalho
```

### Boot por Perfil no Autonomous Harness

O boot completo acima continua sendo a referencia maxima. Em execucao multiagente, para evitar sobrecarga e conflito de mandato, cada subagente carrega o minimo necessario para sua funcao:

| Perfil | Carregamento minimo | Permissao de escrita |
|--------|---------------------|----------------------|
| **Orquestrador** | `AGENTS.md` compacto + `harness-engineering.md` + `docs/harness/autonomous-orchestrator.md` + `docs/harness/dependency-layers.md` + `docs/harness/harness-state.json` + docs canonicas da camada ativa | PROIBIDO auditar codigo, corrigir codigo ou preencher parecer de auditor; coordena, dispara agentes/subagentes, consolida e roteia |
| **Auditor read-only** | `AGENTS.md` compacto + fonte canonica da camada + escopo do auditor em contexto limpo | PROIBIDO editar codigo; registra findings estruturados |
| **Consolidador** | Relatorios dos auditores + matriz de severidade/bloqueio + fonte canonica da camada | PROIBIDO editar codigo; deduplica findings e decide bloqueio |
| **Corretor** | Iron completo + Harness + test-policy/test-runner + findings consolidados + arquivos relevantes | Pode editar dentro do escopo consolidado |
| **Verificador** | Harness + test-runner + comandos deterministas da camada | Nao corrige; executa e evidencia comandos |

Se um subagente receber mandato read-only, as Leis 1 e 4 se aplicam como obrigacao de **registrar finding**, nao como permissao para editar.

Toda aprovacao de camada no Autonomous Harness exige cinco auditores/subagentes diferentes, com contexto limpo da conversa principal: `architecture-dependencies`, `security-tenant`, `code-quality`, `tests-verification` e `ops-provenance`. O orquestrador nao conta como auditor e nao pode fabricar artefatos de auditoria.

Se a camada nao for aprovada, o orquestrador deve disparar corretor separado para os findings consolidados; apos qualquer correcao, uma nova rodada dos cinco auditores e obrigatoria. O loop termina somente com aprovacao ou escalacao na 10a rodada.

No fluxo `iniciar camada N`, se a matriz, o manifesto ou os criterios canonicos da camada estiverem ausentes, o orquestrador deve registrar `blocked_missing_context`; nao pode substituir fonte canonica ausente por criterio ad hoc.

---

## Classificacao de Tarefas (CALIBRACAO)

O rigor do Gate Final varia conforme o tipo de tarefa:

| Tipo de Tarefa | Gate Aplicavel | Testes Minimos |
|----------------|---------------|----------------|
| **Feature nova completa** | Gate Final COMPLETO | 8+ por controller |
| **Feature com logica de negocio** | Gate Final COMPLETO | 8+ por controller |
| **CRUD simples (sem logica)** | Gate Final COMPLETO | 4-5 por controller (sucesso + 422 + cross-tenant) |
| **Bug fix (< 3 arquivos)** | Gate Final LITE | Teste de regressao + testes afetados |
| **Refatoracao** | Gate Final COMPLETO + LEI 8 | Testes existentes passando |
| **Ajuste CSS/UI only** | Gate Final LITE | Build check apenas |
| **Consulta/analise** | Nenhum | Nenhum |

> **Regra:** na DUVIDA, usar Gate Final COMPLETO. Gate LITE so para tarefas genuinamente pequenas.

---

## 8 Leis Inviolaveis

### LEI 1: COMPLETUDE ABSOLUTA

Se tocou, COMPLETA. Se falta, CRIA. Se esta quebrado, CORRIGE. NAO existe "depois".

- O fluxo INTEIRO deve funcionar: Frontend → API → Controller → Service → Model → Migration → Testes
- Se uma peca nao existe, DEVE SER CRIADA automaticamente
- Se uma peca esta quebrada, DEVE SER CORRIGIDA imediatamente
- NAO perguntar se deve fazer. FAZER.
- Cascata obrigatoria: `Migration → Model → Form Request → Controller → Rota → API Client → Hook → Componente → TESTES`

**Aplicacao por mandato no Autonomous Harness:**

- **Corretor/implementador:** se tocou, completa dentro do escopo consolidado e das dependencias diretas.
- **Auditor read-only:** se encontrou lacuna, registra finding; NAO edita.
- **Consolidador:** se encontrou lacuna entre findings, consolida ou classifica como `blocked_missing_context`; NAO edita.
- **Orquestrador:** se a correcao extrapola a camada ativa, reclassifica ou escala; NAO audita, NAO corrige e NAO expande escopo silenciosamente.
- **Cross-layer:** mudanca fora da camada ativa exige dependencia tecnica direta e justificativa explicita no relatorio consolidado.

### LEI 2: TESTES SAO SAGRADOS

Teste falhou = SISTEMA errado. NUNCA mascarar. NUNCA pular. NUNCA relaxar.

- QUALQUER teste que falhar: corrigir o SISTEMA, nao o teste
- TODA funcionalidade DEVE ter testes: profissionais, profundos e completos
- TODA funcao/metodo novo DEVE ter teste especifico
- TODO bug fix DEVE ter teste de regressao
- Profundidade MINIMA: happy path + error path + edge cases + autorizacao + validacao
- PROIBIDO: comentar teste, usar `skip()`/`todo()`/`markTestSkipped()`, trocar valor esperado pelo errado, relaxar assertion
- Protocolo: 1) Analisar causa raiz → 2) Identificar se erro e no sistema ou teste → 3) Corrigir o sistema (ou teste se comprovadamente errado) → 4) Rodar novamente

### LEI 3: SISTEMA SEMPRE FUNCIONAL

O sistema DEVE estar funcional apos QUALQUER alteracao. Build quebrado = VIOLACAO GRAVE.

- Frontend DEVE compilar: `cd frontend && npm run build` → zero erros
- Backend DEVE funcionar: `php artisan test` → zero falhas
- Rotas DEVEM existir: toda chamada frontend → endpoint backend correspondente
- Migrations DEVEM estar corretas: toda coluna referenciada → migration existe
- Models DEVEM ter relationships corretas e bidirecionais
- Controllers DEVEM usar Form Requests (nunca validacao inline)
- NENHUM `console.log`, `dd()`, `dump()`, `any` em codigo de producao

### LEI 3b: PADRAO DE QUALIDADE EM CONTROLLERS E FORM REQUESTS

Todo controller e form request DEVE seguir estes padroes minimos:

#### FormRequest authorize() — NUNCA `return true` sem logica
- `authorize()` retornando `return true;` sem nenhuma verificacao e **PROIBIDO**
- O metodo `authorize()` DEVE verificar permissao REAL do usuario:
  - Via Spatie: `return $this->user()->can('modulo.acao');`
  - Via Policy: `return $this->user()->can('update', $this->route('resource'));`
  - Via tenant ownership: verificar que o recurso pertence ao tenant do usuario
- **Unica excecao:** endpoints genuinamente publicos (ex: satisfaction survey, public API). Neste caso, comentar: `// Public endpoint — no auth required`

#### Controllers — Paginacao OBRIGATORIA em listagens
- Todo `index()` DEVE retornar dados **paginados**: `->paginate(15)` ou `->simplePaginate(15)`
- **PROIBIDO** retornar `Model::all()` ou `->get()` sem limite em endpoints de listagem
- Se a listagem precisa retornar tudo (ex: dropdown de categorias), usar `->limit(500)` como safety net

#### Controllers — Eager Loading OBRIGATORIO
- Todo `index()` e `show()` que retorna model com relationships DEVE usar `->with([...])` explicito
- **PROIBIDO** retornar model sem eager loading quando existem relationships definidas
- Verificar N+1: se o response inclui dados de relationship, o `with()` DEVE estar presente
- Usar `->select()` quando possivel para limitar colunas retornadas

#### Controllers — Atribuicao de tenant_id e created_by
- `tenant_id` DEVE ser atribuido via `$request->user()->current_tenant_id` no controller (ou via model observer)
- `created_by` DEVE ser atribuido via `$request->user()->id` no controller
- **PROIBIDO** expor `tenant_id` ou `created_by` como campos do FormRequest que o cliente pode enviar

### LEI 4: IMPLEMENTACAO PROATIVA (com guardrail de escopo)

Se viu problema e tem mandato de escrita, CORRIGE. Se falta peca no escopo consolidado, CRIA. NAO espera o usuario pedir.

Se o agente estiver em modo **auditor read-only**, **consolidador** ou **verificador**, a proatividade significa registrar, classificar, deduplicar ou evidenciar o problema. Nesses perfis, editar codigo e violacao de mandato.

- Rotas faltantes → criar rota + controller + form request
- Controllers faltantes → criar com metodo completo e Form Request
- Models faltantes → criar com relationships + migration
- Migrations faltantes → criar com guards `hasTable`/`hasColumn`
- Hooks/Services faltantes → criar com tipagem TypeScript
- Tipos faltantes → exportar interface/type
- Validacoes faltantes → criar Form Request + Zod schema
- Testes faltantes → criar testes profissionais
- Bugs encontrados → corrigir imediatamente
- TODOs/FIXMEs → resolver no momento

**Tipos de bloqueio para agentes/subagentes:**

- `blocked_environment`: ambiente local ou runtime impede verificacao.
- `blocked_missing_context`: criterio canonico ou informacao necessaria nao existe.
- `blocked_policy`: correcao exigiria violar regra, producao sem `deployment_authorization`, migration sensivel ou acao destrutiva.
- `blocked_code`: bug ou inconsistencia de codigo corrigivel localmente.
- `blocked_conflict`: auditores divergem e nao ha criterio objetivo para resolver.

**GUARDRAIL DE ESCOPO EM CASCATA:** Se correcoes em cascata (problemas encontrados fora do escopo original da tarefa) ultrapassarem **5 arquivos**, o agente DEVE:
1. PARAR as correcoes em cascata
2. CONSOLIDAR um relatorio do que encontrou e o que ja corrigiu
3. REPORTAR ao usuario antes de continuar
4. Retomar SOMENTE apos confirmacao

> Isto NAO se aplica a arquivos DENTRO do escopo direto da tarefa (cascata obrigatoria Migration→Model→...→Testes). Aplica-se APENAS a correcoes oportunisticas em arquivos nao relacionados diretamente.

### LEI 5: RESOLUCAO PROFISSIONAL

Problemas sao resolvidos na RAIZ. Nao existe workaround. Nao existe gambiarra.

- Analisar causa raiz antes de corrigir
- Implementar solucao correta e definitiva
- Cobrir com testes que previnem regressao
- Verificar impacto em funcionalidades adjacentes

### LEI 6: NUNCA IGNORAR, NUNCA PULAR, NUNCA CONTORNAR

Se falta, INSTALA. Se o ambiente nao suporta, CORRIGE O AMBIENTE. Nao existe "basta ignorar".

- **PROIBIDO** usar `--ignore-platform-reqs`, `--ignore-platform-req`, `--no-verify`, `--skip-*` ou qualquer flag que contorne requisitos
- **Se falta extensao PHP** (bcmath, redis, mbstring, etc.): INSTALAR
- **Excecao Windows:** extensoes Linux-only (`pcntl`, `posix`, `inotify`) NAO estao disponiveis nativamente no Windows e NAO devem consumir ciclos tentando instalar. Nesses casos:
  - Aceitar que a extensao nao esta disponivel no ambiente de desenvolvimento Windows
  - NAO gastar tempo tentando instalar ou configurar workarounds
  - O codigo de producao (Linux/Docker) tera essas extensoes — testar la
  - Se um pacote composer EXIGE essas extensoes, usar `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` SOMENTE para essas 3 extensoes Linux-only, NUNCA para outras
- **Se falta versao de PHP/Node**: INSTALAR a versao correta. Nao fazer downgrade de pacotes para caber no ambiente quebrado
- **Se falta pacote/dependencia**: INSTALAR. Nao remover o pacote que precisa para evitar o erro
- **Documentacao define intencao e criterios; codigo atual define estado factual** — quando houver divergencia, registrar gap e verificar escopo/camada/risco antes de implementar
- **Plano aprovado e documentacao canonica orientam o trabalho** — aproveitar o existente e adicionar o que falta dentro do escopo ativo, sem transformar roadmap aspiracional em implementacao automatica
- A solucao correta e SEMPRE elevar o ambiente ao nivel exigido, NUNCA rebaixar o sistema ao nivel do ambiente

### LEI 7: SEQUENCIAMENTO OBRIGATORIO DE ETAPAS

E PROIBIDO iniciar a Etapa N+1 de um plano se a Etapa N nao estiver 100% completa.

- Cada etapa de um plano DEVE ser finalizada com Gate Final verificado ANTES de avancar
- Testes da etapa atual DEVEM estar passando antes de iniciar a proxima
- Se a etapa atual tem problemas nao resolvidos: RESOLVER antes de avancar
- NAO existe "volto depois para terminar a etapa anterior"
- NAO existe "avanco para desbloquear a proxima e depois volto"
- A unica excecao: se a etapa seguinte e PRE-REQUISITO tecnico da atual (dependencia invertida comprovada), documentar a justificativa

**Protocolo entre etapas:**
1. Completar TODA a implementacao da etapa atual
2. Rodar testes da etapa (especificos + suite completa)
3. Verificar Gate Final (checklist acima)
4. Reportar conclusao da etapa com evidencia (testes passando, diff revisado)
5. SO ENTAO iniciar a proxima etapa

**No Autonomous Harness:** etapa pode ser uma camada, ciclo ou task consolidada. A verificacao segue a piramide do `test-runner.md` e o catalogo deterministico da camada:

1. teste/comando especifico da mudanca;
2. grupo afetado;
3. `--dirty`/suite parcial quando aplicavel;
4. suite completa no fechamento da camada, em mudancas de alto risco, auth/tenant, contrato publico, migration, ou antes de producao.

Suite completa entre todos os subpassos de auditoria read-only nao e obrigatoria. Ela e obrigatoria quando a camada for marcada como aprovada ou quando a classificacao de risco exigir.

### LEI 8: PRESERVACAO ABSOLUTA NA REESCRITA

Ao reescrever ou refatorar codigo existente, a nova versao DEVE preservar 100% dos comportamentos da versao anterior.

- **ANTES de reescrever:** Listar TODOS os comportamentos existentes:
  - Validacoes (cada regra de FormRequest, cada check condicional)
  - Condicoes e branches (if/else, switch, early returns)
  - Edge cases tratados (null checks, empty checks, boundary conditions)
  - Side effects (eventos disparados, logs, notificacoes, cache invalidation)
  - Permissoes e autorizacao verificadas
  - Endpoints/methods publicos expostos
- **DEPOIS de reescrever:** Conferir item por item que TODOS os comportamentos listados estao presentes
- **Se algum comportamento foi removido:** RESTAURAR imediatamente, a menos que haja justificativa EXPLICITA documentada no commit
- **"Simplificar" NAO e justificativa** — se a validacao existia, ela tinha motivo. Manter ate prova em contrario

**INVENTARIO PRE/POS OBRIGATORIO para refatoracoes:**
```
ANTES: Listar endpoints, methods publicos, validacoes, eventos, middlewares
DEPOIS: Confirmar que 100% estao presentes na nova versao
DIFF: Rodar git diff e revisar CADA linha removida — justificar ou restaurar
```

---

## Penalidades por Violacao

| Violacao | Penalidade |
|----------|-----------|
| Funcionalidade sem testes | Tarefa INCOMPLETA |
| Mascarar teste | GRAVISSIMA — Resultado INACEITAVEL |
| Build quebrado | GRAVE — Codigo REJEITADO |
| Fluxo incompleto (frontend sem backend) | Tarefa INCOMPLETA |
| Stub/placeholder sem implementacao | Tarefa INCOMPLETA |
| "Pode ser feito depois" | PROIBIDO |
| TODO/FIXME sem resolver | Tarefa INCOMPLETA |
| `any` em TypeScript | Codigo REJEITADO |
| `console.log` em producao | Codigo REJEITADO |
| Controller sem Form Request | Codigo REJEITADO |
| FormRequest authorize() com `return true` sem logica | Codigo REJEITADO — implementar autorizacao real |
| Endpoint index() sem paginacao (Model::all) | Codigo REJEITADO — usar paginate() |
| Controller sem eager loading em relationships | Codigo REJEITADO — usar ->with([...]) |
| tenant_id/created_by exposto no FormRequest | Codigo REJEITADO — atribuir no controller |
| Testes sem cenario cross-tenant | Tarefa INCOMPLETA — adicionar teste de isolamento |
| Testes sem cenario de validacao 422 | Tarefa INCOMPLETA — adicionar teste de validacao |
| Usar `--ignore-platform-reqs` ou similar | GRAVISSIMA — Ambiente DEVE ser corrigido |
| Remover pacote para evitar erro | GRAVISSIMA — Instalar dependencia correta |
| "Basta ignorar" extensao/versao | PROIBIDO — Instalar o que falta |
| Rebaixar sistema para caber no ambiente | PROIBIDO — Elevar ambiente ao nivel exigido |
| Avancar para etapa N+1 sem completar etapa N | PROIBIDO — Completar etapa atual primeiro |
| Remover validacao/comportamento em refatoracao | GRAVISSIMA — Restaurar imediatamente |
| Refatorar sem inventario pre/pos | GRAVE — Resultado INACEITAVEL |
| Nao revisar git diff antes de declarar conclusao | GRAVE — Tarefa NAO concluida |
| Cascata oportunistica >5 arquivos sem reportar | GRAVE — Parar e consolidar |
| index() usando response()->json() em vez de ApiResponse::paginated() | Codigo REJEITADO — usar App\Support\ApiResponse |
| Namespace errado de ApiResponse (Http\Helpers em vez de Support) | Codigo REJEITADO — usar App\Support\ApiResponse |
| Controller minificado (metodos em 1 linha) | Codigo REJEITADO — formatar PSR-12 |
| Factory sem gerar todos os campos fillable do model | Tarefa INCOMPLETA — factory deve espelhar model |
| Rota publica sem atualizar ProductionRouteSecurityTest | Tarefa INCOMPLETA — adicionar a $publicUris |

---

## Gate Final COMPLETO (Checklist Obrigatorio)

Usar para: features novas, features com logica de negocio, CRUDs, refatoracoes.

```
=== FUNCIONALIDADE ===
□ O fluxo ponta a ponta funciona? (Frontend → Backend → Banco → Resposta → UI)
□ Todas as rotas necessarias existem e funcionam?
□ Todas as migrations foram criadas/atualizadas?
□ Todos os Models estao corretos com relationships e BelongsToTenant?

=== CONTROLLERS E FORM REQUESTS ===
□ Todos os Controllers tem Form Requests?
□ FormRequest authorize() tem logica REAL? (PROIBIDO: return true sem verificacao)
□ Endpoints de listagem (index) usam paginacao? (PROIBIDO: Model::all() ou ->get() sem limite)
□ Controllers usam eager loading (->with()) para relationships? (PROIBIDO: N+1 queries)
□ tenant_id e created_by sao atribuidos no controller, NAO expostos no FormRequest?

=== TESTES ===
□ Testes criados para TODA funcionalidade nova?
□ Testes cobrem cenarios proporcionais a complexidade? (ver tabela de Classificacao de Tarefas)
□ Testes existentes continuam passando?
□ Nenhum teste foi mascarado?

=== QUALIDADE ===
□ O frontend compila? → cd frontend && npm run build
□ Zero console.log, zero any, zero dd()?
□ aria-label em elementos interativos?
□ Todos os TODOs/FIXMEs resolvidos?

=== REVISAO FINAL ===
□ DIFF OBRIGATORIO: git diff revisado — nenhuma funcionalidade removida silenciosamente
□ INVENTARIO: se houve refatoracao, endpoints/methods publicos ANTES = endpoints/methods DEPOIS
□ Relatorio final com: O que mudou | Risco | Como validar | Como desfazer
```

> Se QUALQUER item acima nao foi cumprido, a tarefa NAO esta concluida. Voltar e completar ANTES de reportar.

---

## Gate Final LITE (para tarefas menores)

Usar SOMENTE para: bug fixes < 3 arquivos, ajustes CSS/UI, correcoes pontuais.

```
=== GATE LITE ===
□ A correcao resolve o problema reportado?
□ Build frontend OK? → cd frontend && npm run build (se frontend foi tocado)
□ Testes afetados continuam passando? → pest --dirty --parallel --no-coverage
□ Teste de regressao criado para o bug? (se bug fix)
□ Zero console.log, zero dd() nos arquivos tocados?
□ Relatorio final com: O que mudou | Risco | Como validar | Como desfazer
```

> **Regra:** Se durante um Gate LITE voce descobrir que a tarefa e maior do que parecia (toca mais de 3 arquivos, requer migration, requer novo controller), ESCALAR para Gate Final COMPLETO.

### Checklist Estendido de Integridade

#### Rotas e Conexoes
- [ ] Toda rota no frontend (React Router) tem correspondencia no backend (routes/api.php)
- [ ] Toda chamada `api.get/post/put/delete` no frontend aponta para endpoint existente
- [ ] Toda rota no backend tem Controller com metodo implementado (nao vazio)
- [ ] Middleware de autenticacao/autorizacao esta aplicado corretamente
- [ ] Rotas literais vem ANTES de rotas com `{id}` (ordem correta)

#### Banco de Dados
- [ ] Toda coluna referenciada no codigo tem migration correspondente
- [ ] Migrations novas usam guards `hasTable`/`hasColumn` para idempotencia
- [ ] Relationships no Model (`hasMany`, `belongsTo`, etc.) estao corretas
- [ ] Foreign keys existem e apontam para tabelas corretas
- [ ] `$fillable`, `$casts`, `$hidden` estao atualizados no Model

#### Validacao e Seguranca
- [ ] Controllers usam Form Requests (nunca `$request->validate()` inline)
- [ ] Validacao do frontend espelha validacao do backend
- [ ] Permissoes (`can`, `authorize`, Spatie) estao aplicadas
- [ ] CSRF/Sanctum configurado corretamente

#### Frontend Completo
- [ ] Estados de loading, erro, e vazio tratados no componente
- [ ] Feedback visual para acoes do usuario (toast, alert, etc.)
- [ ] Formularios com validacao client-side (React Hook Form + Zod)
- [ ] Tipos TypeScript corretos (zero `any`)
- [ ] `aria-label` em elementos interativos

#### Testes
- [ ] Toda funcionalidade nova tem testes
- [ ] Testes cobrem: happy path + error path + edge cases + limites
- [ ] Testes sao profissionais: nomes descritivos, cenarios realistas
- [ ] Testes seguem padrao AAA (Arrange-Act-Assert)
- [ ] Nenhum teste foi mascarado, pulado, comentado ou relaxado
- [ ] Testes existentes continuam passando apos a mudanca

---

## Formato de Resposta Obrigatorio (HARNESS ENGINEERING)

> **Fonte canônica:** `.agent/rules/harness-engineering.md` — regra H5. Resumo abaixo.

Toda resposta final que envolva alteracao de codigo DEVE conter, nesta ordem, os **7 itens obrigatorios**:

1. **Resumo do problema** — sintoma + causa raiz em 1-2 frases
2. **Arquivos alterados** — lista com caminho relativo (`file.php:LN` quando apontar linha)
3. **Motivo tecnico de cada alteracao** — POR QUE, nao O QUE (o diff mostra o quê)
4. **Testes executados** — comando exato, copiavel, seguindo piramide de escalacao
5. **Resultado dos testes** — output real com contagem passed/failed (proibido parafrasear)
6. **Riscos remanescentes** — o que nao foi coberto, efeitos colaterais, pontos de atencao
7. **Proximo passo / recomendacoes** — acao seguinte recomendada, comando copiavel quando aplicavel, ou declarar que nao ha proximo passo necessario com justificativa

**Item 8 OPCIONAL** — **obrigatorio** para mudancas destrutivas, migrations, alteracao de contrato de API, deploy/infra, remocao de feature, ou risco alto:

8. **Como desfazer** — passos exatos de rollback (git revert, migration down, flag, etc.)

**Proibicoes criticas (H7):** usar "pronto", "funcionando", "testes passando", "validado" SEM evidencia objetiva de comando executado no mesmo turno da resposta.

---

## Regra de Carregamento

Este arquivo DEVE ser carregado e aplicado:

- Em TODA nova conversa
- Em TODA nova acao do agente
- Em TODA implementacao (nova ou alteracao)
- Em TODA correcao de bug
- Em TODA revisao de codigo
- Em TODA resposta que envolva codigo

NAO existe excecao. NAO existe "fora do escopo". NAO existe "depois".

### Limite da Autonomia Multiagente

Autonomia no harness significa execucao de workflow previsivel dentro de guardrails versionados. A LLM CLI pode executar deploy quando houver `deployment_authorization` valida no manifesto do harness. Isso nao autoriza julgamento irrestrito nem acao irreversivel.

Mesmo em modo autonomo, o agente DEVE escalar antes de:

- deploy em producao sem `deployment_authorization` valida;
- rollback real nao previsto na autorizacao de deploy;
- migration com risco de perda de dados;
- alteracao sensivel global de autenticacao/permissao;
- exclusao irreversivel;
- rotacao de segredo externo;
- conflito tecnico entre auditores sem criterio canonico;
- ciclo 10 ainda reprovado no Autonomous Harness.

---

> **Cadeia de carregamento:** `AGENTS.md` → `.agent/rules/iron-protocol.md` → `.agent/rules/mandatory-completeness.md` → `.agent/rules/test-policy.md` → `.agent/rules/test-runner.md` → `.agent/rules/kalibrium-context.md` → **`.agent/rules/harness-engineering.md`** → `.agent/skills/iron-protocol-bootstrap/SKILL.md` → `.agent/skills/end-to-end-completeness/SKILL.md`
>
> Se houver conflito entre textos duplicados, prevalece esta ordem (AGENTS.md como fonte primaria). O formato de resposta e o fluxo de execucao sao governados pelo `harness-engineering.md` — em caso de texto divergente duplicado, Harness e a fonte canonica.
