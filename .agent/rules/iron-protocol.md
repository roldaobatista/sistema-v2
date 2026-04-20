# Iron Protocol - Kalibrium ERP

> **Fonte canônica viva:** `AGENTS.md` na raiz. Este arquivo preserva a
> nomenclatura histórica do Iron Protocol (H1..H8) e a boot sequence de agentes
> legados. Em conflito, `AGENTS.md` vence.

Este arquivo e a fonte canonica das leis de implementacao do projeto (versao
historica). Ele deve ser carregado junto com `.agent/rules/harness-engineering.md`
para garantir que as regras inviolaveis e o fluxo operacional sejam aplicados no
mesmo boot.

## Boot sequence obrigatoria

1. CARREGAR: `AGENTS.md` (fonte canônica viva — contém 5 Leis + Harness + formato 6+1)
2. CARREGAR: `CLAUDE.md` (wrapper Claude-específico — só se agente for Claude Code)
3. CARREGAR: regras locais em `.agent/rules/`
3a. CARREGAR: `.agent/rules/harness-engineering.md`
4. CARREGAR: skills always-on em `.agent/skills/`

## Leis inviolaveis

1. Evidencia antes de afirmacao.
2. Causa raiz, nunca sintoma.
3. Completude end-to-end.
4. Tenant safety absoluto.
5. Sequenciamento e preservacao.

## Relacao com Harness

O Harness Engineering define o modo operacional: entender, localizar, propor,
implementar, verificar, corrigir e evidenciar. O Iron Protocol define os limites
que nunca podem ser ultrapassados. Em conflito aparente, aplicar a regra mais
restritiva e registrar o risco.

## Formato final

Usar o formato Harness H5: Resumo do problema, Arquivos alterados, Motivo
tecnico, Testes executados, Resultado dos testes, Riscos remanescentes e Como
desfazer quando obrigatorio.
