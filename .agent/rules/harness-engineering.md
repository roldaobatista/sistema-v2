# ⚙️ HARNESS ENGINEERING — Protocolo Operacional do Agente Kalibrium

> **Prioridade:** P-1 (empatada com Iron Protocol). Este arquivo define **COMO** o agente executa e reporta trabalho. O Iron Protocol define **O QUE** é proibido/obrigatório. Ambos se complementam — não se contradizem.
>
> **Fonte canônica do formato de resposta, fluxo de execução e critérios de aceite de toda IA operando no Kalibrium ERP.**

---

## Declaração

Harness Engineering é o **modo operacional padrão** do agente. Não existe "modo normal" alternativo — toda interação que envolva código, investigação, correção ou implementação segue este protocolo. Não precisa ser ativado; é sempre-ligado.

Aplicação obrigatória em:
- Toda nova conversa (junto com Iron Protocol boot sequence)
- Toda tarefa de engenharia (feature, bug fix, refactor, investigação)
- Toda resposta final que envolva alteração de código
- Toda entrega de trabalho (inclusive relatórios de análise)

---

## Regras Invioláveis do Harness (aditivo ao Iron Protocol)

As regras abaixo são **aditivas** ao Iron Protocol. Onde há sobreposição, vale a regra mais estrita.

### H0 — Orquestrador nao audita nem corrige

No Autonomous Harness, o orquestrador e somente coordenador de ciclo:

- DEVE carregar fontes canonicas, abrir/fechar ciclos, disparar agentes/subagentes e validar artefatos.
- PROIBIDO auditar codigo, corrigir codigo, escrever parecer de auditor ou preencher `auditor-*.json` como substituto de agente.
- PROIBIDO contar a propria analise do orquestrador como uma das cinco auditorias obrigatorias.
- Se uma ferramenta nao permitir agentes/subagentes com contexto limpo, o ciclo deve ser `blocked_environment`.

### H1 — Tenant ID jamais do body
- **NUNCA** confiar em `tenant_id` vindo do `request->input()`, body JSON ou query string
- **SEMPRE** usar `$request->user()->current_tenant_id` no controller
- **SEMPRE** confiar no global scope `BelongsToTenant` para queries — não filtrar manualmente
- Se precisar de tenant_id explícito em um FormRequest, **remover** o campo — é uma porta de privilege escalation

### H2 — Escopo do tenant em toda persistência
- Toda query que toca dados de tenant DEVE passar pelo global scope `BelongsToTenant`
- Toda criação/atualização DEVE carimbar `tenant_id` no controller via `current_tenant_id`
- Toda relationship DEVE validar pertencimento ao tenant (regras `exists:table,id` considerando tenant)
- Qualquer bypass do scope (`withoutGlobalScope`) exige justificativa explícita + log de auditoria

### H3 — Imutabilidade de migrations antigas
- **PROIBIDO** alterar migration já mergeada em main. Criar **nova** migration com guards `hasTable`/`hasColumn`
- Exceção: migration ainda não executada em nenhum ambiente (dev/staging/prod) e criada na mesma branch de trabalho — pode ser ajustada antes do primeiro run
- Após `php artisan migrate`: a migration é fóssil. Novas alterações → nova migration.

### H4 — Fluxo de execução obrigatório (7 passos)

Toda tarefa de engenharia segue este fluxo em ordem. Não pular, não reordenar.

```
1. ENTENDER   → ler o pedido, identificar intenção real, esclarecer ambiguidade
                se crítica (antes de tocar arquivo)
2. LOCALIZAR  → grep/glob/read dos arquivos relevantes; mapear fluxo completo
                (rota → controller → service → model → migration → tipo → UI)
3. PROPOR     → definir alteração MÍNIMA e CORRETA; respeitar Lei 8 (preservação)
                e o guardrail de escopo em cascata (>5 arquivos = parar e reportar)
4. IMPLEMENTAR → aplicar edição cirúrgica; seguir padrões do projeto
5. VERIFICAR  → rodar testes/lint/build na piramide de escalação:
                específico → grupo → testsuite → suite (última opção)
6. CORRIGIR   → qualquer falha (teste, lint, types, build) é bloqueante —
                corrigir causa raiz, nunca mascarar
7. EVIDENCIAR → apresentar resposta no Formato Harness (7+1 itens) com output real
```

### H4a — Fluxo Autonomous Harness por camada

Toda camada do Autonomous Harness segue obrigatoriamente este loop:

1. Orquestrador abre a run/ciclo da camada.
2. Orquestrador dispara cinco auditores read-only diferentes, com contexto limpo da conversa.
3. Os cinco auditores procuram problemas, erros, inconsistencias, pendencias, seguranca, regressao, lacunas de teste e quebra de contrato.
4. Cada auditor gera somente seu `auditor-*.json`, com `scope.readonly = true` e `agent_provenance.context_mode = "clean"`.
5. Consolidador deduplica findings e gera `consolidated-findings.json`.
6. Se o consolidado nao aprovar, orquestrador dispara um corretor separado para corrigir os findings consolidados.
7. Se houve correcao, a camada volta para nova rodada dos cinco auditores, novamente com contexto limpo.
8. Repetir ate aprovacao ou ate 10 rodadas; na 10a rodada ainda reprovada, marcar `escalated`.

O quorum de aprovacao e sempre cinco auditores obrigatorios:

- `architecture-dependencies`
- `security-tenant`
- `code-quality`
- `tests-verification`
- `ops-provenance`

`targeted` e `verification_only` podem existir para investigacao auxiliar, mas nao podem fechar camada como `approved`.

### H5 — Formato de resposta final (7 itens obrigatórios + 1 opcional)

**Toda resposta final que envolva alteração de código, implementação, correção, auditoria ou investigação técnica DEVE conter, nesta ordem:**

```
1. RESUMO DO PROBLEMA
   Uma ou duas frases declarando o que estava errado / o que foi pedido.
   Inclui sintoma observável e causa raiz quando conhecida.

2. ARQUIVOS ALTERADOS
   Lista com caminho relativo de cada arquivo tocado.
   Formato: `path/to/file.php:LN` quando apontar linha específica.

3. MOTIVO TÉCNICO DE CADA ALTERAÇÃO
   Por arquivo, uma linha explicando POR QUÊ a mudança foi necessária.
   Não descrever O QUE mudou (o diff já faz isso) — descrever o PORQUÊ.

4. TESTES EXECUTADOS
   Comando exato rodado (copiável). Exemplo:
   `cd backend && ./vendor/bin/pest --filter=CalibrationControllerTest`
   Ordem de escalação respeitada (específico → grupo → suite).

5. RESULTADO DOS TESTES
   Output real (não parafraseado): contagem passed/failed, tempo, erros.
   Se lint/typecheck/build também foram rodados, incluir seus resultados.
   PROIBIDO: "testes passando" sem contagem. PROIBIDO: inventar números.

6. RISCOS REMANESCENTES
   O que a alteração NÃO cobre, cenários não testados, efeitos colaterais
   possíveis, pontos que precisam de atenção em produção.
   Se não houver riscos conhecidos: "Nenhum risco identificado" (com justificativa).

7. PRÓXIMO PASSO / RECOMENDAÇÕES
   Indicar a ação seguinte recomendada com comando copiável quando aplicável.
   Se não houver próximo passo útil, declarar isso explicitamente com justificativa.
   Em ciclos do Autonomous Harness, indicar a próxima camada, fase, comando ou bloqueio.

8. [OPCIONAL — obrigatório para mudanças destrutivas, migrations, ou risco alto]
   COMO DESFAZER
   Passos exatos para reverter a mudança (git revert, migration down, flag, etc.)
```

**Quando o item 8 é obrigatório:**
- Migration criada/alterada
- Alteração em rota pública (remoção/renomeação)
- Alteração em contrato de API (response shape, status codes)
- Deploy/config de infraestrutura
- Remoção de feature ou código de negócio
- Qualquer mudança classificada como "risco alto" pelo próprio agente

### H6 — Critérios de aceite (checklist antes de declarar conclusão)

Antes de apresentar a resposta final, o agente DEVE confirmar **todos** os critérios abaixo. Se qualquer um falhar, volta ao passo 6 (CORRIGIR).

```
□ Código consistente com a arquitetura atual (padrões do kalibrium-context.md)
□ Sem regressão visível (testes previamente verdes continuam verdes)
□ Testes relevantes à mudança estão verdes com evidência de execução
□ Resposta padronizada no formato Harness (7+1 itens)
□ Próximo passo ou recomendação acionável incluído na resposta final
□ Segurança e escopo de tenant preservados (H1, H2 verificados)
□ Iron Protocol — nenhuma Lei violada (inclui Lei 8 — preservação em refatoração)
□ Guardrail de escopo respeitado (cascata >5 arquivos foi reportada, não silenciada)
□ Se for ciclo de camada do Autonomous Harness, cinco auditores diferentes com contexto limpo aprovaram
□ Se houve correcao em ciclo de camada, a reauditoria pelos cinco auditores foi refeita apos a correcao
```

### H7 — Evidência antes de afirmação (reforço)

**PROIBIDO** usar os seguintes termos sem evidência objetiva na mesma resposta:
- "pronto" / "concluído" / "implementado" / "corrigido" / "funcionando"
- "testes passando" / "tudo verde" / "sem erros"
- "validado" / "verificado" / "testado"

**Evidência objetiva** = output de comando executado no mesmo turno da resposta. Referências a execuções anteriores **não contam** — output antigo vira mentira silenciosa se o estado mudou.

### H8 — Falha de verificação é bloqueante

Se **qualquer** um dos itens abaixo falhar durante a verificação, a tarefa **NÃO** está concluída. Corrigir antes de encerrar:

- Qualquer teste falhando (inclusive flaky — investigar, não ignorar)
- Lint com erros (warnings novos = corrigir também)
- TypeScript com erros
- Build do frontend quebrado (`cd frontend && npm run build`)
- PHPStan/Psalm com nível piorado

**Não existe** "esse teste já estava falhando" sem auditoria — investigar `git log` do teste; se realmente pré-existe, reportar no item 6 (riscos) da resposta, nunca silenciar.

---

## Sequenciamento do Harness no Boot

O Harness é carregado **junto** com o Iron Protocol no boot (passo 4e da boot sequence do Iron Protocol). Ordem de carga:

```
1. Iron Protocol
2. mandatory-completeness.md
3. test-policy.md
4. test-runner.md
5. kalibrium-context.md
6. HARNESS-ENGINEERING.md  ← ESTE ARQUIVO
7. Ativar skills always-on
8. Verificar stack
9. Iniciar trabalho
```

---

## Conflitos com outras camadas

Em caso de conflito entre Harness e outras fontes:

1. **Instruções explícitas do usuário na conversa atual** → vencem Harness
2. **Iron Protocol (Leis 1–8)** → empatadas com Harness; onde há sobreposição, vale a regra mais estrita
3. **CLAUDE.md (projeto)** → alinhado com Harness; em caso de texto duplicado divergente, Harness é a fonte canônica do formato de resposta e fluxo
4. **Skills genéricas (BMad, superpowers, .agent/skills)** → Harness e Iron Protocol vencem sempre
5. **Documentação funcional (PRD, architecture)** → define *o quê* do negócio; Harness define *como* entregar — não conflitam

---

## Regra de Carregamento

Este arquivo DEVE ser carregado e aplicado em toda nova conversa, toda nova ação, toda implementação e toda resposta final. Não existe exceção. Não existe "fora do escopo". Não existe "depois".

> **Cadeia de carregamento:** `CLAUDE.md` / `AGENTS.md` → `.agent/rules/iron-protocol.md` → `.agent/rules/mandatory-completeness.md` → `.agent/rules/test-policy.md` → `.agent/rules/test-runner.md` → `.agent/rules/kalibrium-context.md` → **`.agent/rules/harness-engineering.md`** → skills always-on.
