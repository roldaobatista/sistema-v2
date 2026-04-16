# Inventário de Testes Ignorados (Skip/Todo)
**Data:** 2026-03-26
**Atualizado:** 2026-03-26 (regularização)

## Status Atual: ZERO SKIPS

Todos os testes com skip/todo foram resolvidos. O codebase não possui nenhum `markTestSkipped`, `->skip()`, `->todo()`, `assertTrue(true)` ou teste vazio.

## Histórico de Resolução

| Arquivo | Linha | Categoria Original | Descrição | Resolução |
|---------|-------|-----------|-----------|-----------|
| `FinancialExtraControllerTest.php` | ~218 | (b) Feature não implementada | Boleto route skip condicional | **RESOLVIDO** — Rotas já existiam em `finance-advanced.php`. Skip condicional removido, teste agora executa normalmente. |
| `FinancialExtraControllerTest.php` | ~240 | (b) Feature não implementada | payment-gateway-config skip condicional | **RESOLVIDO** — Rotas já existiam. Skip condicional removido. |

*Zero testes com skip/todo no backend (Pest) ou frontend (Vitest).*
