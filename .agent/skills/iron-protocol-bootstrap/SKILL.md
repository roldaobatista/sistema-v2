# Iron Protocol Bootstrap

Skill always-on do Kalibrium ERP para carregar o Iron Protocol antes de qualquer
trabalho tecnico.

## Quando aplicar

Aplicar em todo heartbeat, correcao, auditoria, revisao, PR e comentario
operacional que envolva o repositorio.

## Sequencia

1. Ler `AGENTS.md` (fonte canônica viva) e `CLAUDE.md` (só se agente for Claude Code).
2. Ler `.agent/rules/iron-protocol.md`.
3. Ler `.agent/rules/harness-engineering.md`.
4. Executar o fluxo Harness: entender, localizar, propor, implementar,
   verificar, corrigir e evidenciar.

## Restricoes

Nao remover gates, nao enfraquecer testes e nao mascarar falhas. Se a verificacao
falhar, registrar blocker ou corrigir a causa raiz.
