---
name: iron-protocol-bootstrap
description: Bootstrap automático do Iron Protocol. Carrega rules, verifica stack, confirma completude.
trigger: always_on
---

# Iron Protocol Bootstrap

## Ativação

Este skill é ativado automaticamente no início de toda conversa.

## Sequência

1. Verificar existência de `.agent/rules/iron-protocol.md` → ler e aplicar
2. Verificar existência de `.agent/rules/mandatory-completeness.md` → ler e aplicar
3. Verificar existência de `.agent/rules/test-policy.md` → ler e aplicar
4. Verificar existência de `.agent/rules/test-runner.md` → ler e aplicar
5. Verificar existência de `.agent/rules/kalibrium-context.md` → ler e aplicar
6. Verificar existência de `.agent/rules/harness-engineering.md` → ler e aplicar (MODO OPERACIONAL: fluxo 7 passos + formato resposta 7+1)
7. Confirmar stack: verificar `composer.json` para Laravel, `package.json` para React/Vite
8. Emitir confirmação: "Iron Protocol + Harness Engineering ativos. Toda implementação será completa, ponta a ponta, com testes. Resposta final no formato Harness (7+1 itens). Fluxo: entender → localizar → propor → implementar → verificar → corrigir → evidenciar."

## Fallback

Se algum arquivo de rules não existir:
- Emitir warning: "Arquivo {path} não encontrado. Aplicando regras de AGENTS.md diretamente."
- Carregar regras do AGENTS.md como fallback (AGENTS.md contém o resumo compacto do Iron Protocol e do Harness H5)

## Regras Aplicadas

Após bootstrap, as seguintes regras estão ativas:
- 8 Leis Invioláveis (iron-protocol.md)
- Completude obrigatória ponta a ponta (mandatory-completeness.md)
- Política de testes com definição de mascaramento (test-policy.md)
- Runner de testes e pirâmide de escalação (test-runner.md)
- Contexto do projeto e stack (kalibrium-context.md)
- **Harness Engineering — modo operacional P-1** (harness-engineering.md):
  - Fluxo obrigatório de 7 passos (entender → localizar → propor → implementar → verificar → corrigir → evidenciar)
  - Formato de resposta final de 7 itens obrigatórios + 1 opcional (H5)
  - H0 — no Autonomous Harness, orquestrador coordena; auditores/corretores/verificadores executam com contexto limpo
  - H1 — tenant_id nunca do request body
  - H2 — escopo do tenant em toda persistência via BelongsToTenant
  - H3 — migrations antigas imutáveis (criar novas com guards)
  - H7 — proibido declarar conclusão sem evidência objetiva no mesmo turno
  - H8 — falha de teste/lint/build/typecheck é bloqueante
