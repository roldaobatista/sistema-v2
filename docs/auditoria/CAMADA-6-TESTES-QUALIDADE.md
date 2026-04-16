> **NOTA:** Este documento é um índice resumido. Para a auditoria detalhada e completa, consultar:
> - `docs/auditoria/AUDITORIA-GAPS-DOCUMENTACAO-2026-03-25.md` (relatório completo com 156 gaps)
> - `docs/auditoria/RELATORIO-AUDITORIA-GAPS-2026-03-25.md` (sumário executivo)
> - `docs/auditoria/GAP-ANALYSIS-ISO-17025-9001.md` (análise de gaps ISO)

# Camada 6: Testes e Qualidade Geral

Esta camada governa a pirâmide de testes do Kalibrium SaaS e os comandos estritos de gatekeeping automatizados. Ela fecha qualidade global depois das Camadas 0-5: lint/análise estática, Pest/PHPUnit, Vitest, Playwright, triagem transversal de segurança, cobertura e ownership de regressões.

## Gate bloqueante da Camada 6

Para aprovação da camada, a evidência precisa cobrir estes critérios:

- **Lint e análise estática:** PHPStan/Larastan, TypeScript, ESLint e build frontend precisam rodar no escopo relevante sem mascaramento por baseline novo ou warnings ignorados.
- **Testes relevantes:** Pest, Vitest e Playwright devem seguir a pirâmide específico -> grupo -> suíte, registrando comando, saída e contagem real.
- **Triagem de segurança transversal:** testes de autenticação, tenant isolation, permissões, XSS e fluxos E2E de segurança não podem usar skips condicionais nem asserts permissivos.
- **Cobertura não piora:** remoções de teste, `skip`, `todo`, `markTestSkipped`, `assertTrue(true)` e assertions fracas bloqueiam o fechamento até virarem validação determinística.
- **Ownership reclassificado:** qualquer falha encontrada em teste de backend, frontend, E2E, infra ou harness deve apontar camada proprietária e caminho de correção; se exigir outro owner fora do write set, escalar em vez de mascarar.

Comandos determinísticos mínimos para esta camada:

```bash
cd backend && ./vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G --no-progress
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
cd frontend && npm run typecheck
cd frontend && npm run lint
cd frontend && npm run build
cd frontend && npm run test
cd frontend && npx playwright test <arquivos-e2e-afetados>
```

## 1. Testes de Backend (Obrigatório)

`php artisan test`

- A suíte tem > 6000 testes (`docs/operacional/mapa-testes.md`).
- Nenhum código backend entra em master com `incomplete`, `skipped`, `markTestSkipped`, `assertTrue(true)` sem comportamento real, ou `failed`.
- **Coberturas Requeridas**: Happy paths, Failure paths, Unauthorized (Boundary tests).

## 2. Testes Smoke (APIs Críticas)

Smoke Tests devem rodar sempre após o deploy para confirmar saúde de integrações externas essenciais:

- Banco central de Cotações, Fiscais, e Webhooks Asaas e MercadoPago.

## 3. Testes Assíncronos Frontend

- `Vitest` (unit boards das rules, stores, e math utils React).
- Os hooks customizados (Query) precisam ter validadores `renderHook(...)`.
- `npm run typecheck`, `npm run lint` e `npm run build` fazem parte do gate quando houver impacto em TypeScript/React.

## 4. Testes Ponta-a-Ponta Específicos (Playwright)

Validam workflows essenciais da Camada 4. Executados preferencialmente pré-merge request, num ambiente `testing`, emulando fluxos que necessitam de interações do Portal e Painel com perfis mistos de atores.

- Fluxos E2E críticos devem falhar quando login, seletor, fixture ou controle de segurança estiver indisponível.
- `test.skip` só é aceitável para cenários explicitamente fora do target da execução, nunca para esconder API indisponível, massa ausente ou seletor quebrado em arquivo afetado.
- Testes de segurança devem validar login, autorização, tenant isolation, XSS e mensagens de erro com assertions explícitas.

> **[AI_RULE][IRON PROTOCOL]**: MASCARAR testes mudando `assert` para bypass (apenas para ver o teste passar falsamente) é considerado o maior crime arquitetural possível. Um teste falho revela deficiência na IMPLEMENTAÇÃO, e a causa raiz do código deve ser tratada.
