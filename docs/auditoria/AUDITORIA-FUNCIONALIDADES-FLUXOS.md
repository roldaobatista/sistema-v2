# Auditoria de Funcionalidades e Fluxos

Este doc mantém a matriz de rastreabilidade (Traceability Matrix) para garantir que toda funcionalidade descrita em Módulos atenda um Fluxo de Negócio explícito, visando eliminar o antipattern técnico de Orphane Functions.

## Estrutura da Matriz

Toda Feature deve existir dentro do tripé:

- `Módulo Origem` → `Ação Transformadora` → `Valor/Regra Impactada`

### Exemplo Base

- **Feature**: Recorrer Assinatura Mensal (Billing)
  - Modulo Origem: Finance / Contracts
  - Integração Associada: Payment Gateway Webhook
  - Fluxo Primário: [docs/fluxos/CICLO-FATURAMENTO-RECORRENTE.md](../fluxos/CICLO-FATURAMENTO-RECORRENTE.md)

## Regra Definitiva do AIDD

> Se um agente tentar implementar um Controller, Model ou Servico cujo Propósito de Negócio Central não conste preenchido em *NENHUM* documento listado em `docs/fluxos/*.md` ou não referenciado em Cúspide na arquitetura (Modulos de `docs/modules/`), a tarefa deve ser RECUSADA/PAUSADA com solicitação ao Usuário de primeiro solidificar a Regra Teórica e Documentada (Fase 1/2 do Blueprint AIDD).

Código só cresce até o teto estrito da Documentação Arquitetural. Não se inova sem prévia especificação sob pena de inviabilizar manutenibilidade sistêmica a longo prazo.
