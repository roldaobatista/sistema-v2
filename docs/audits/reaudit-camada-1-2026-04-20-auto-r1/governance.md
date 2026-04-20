# Governance — Camada 1 — auto r1

- **ID:** gov-01
  **Severidade:** S1
  **Arquivo:** `.githooks/pre-commit:58-64,77-83`
  **Descrição:** o hook usa pipelines com `tail` sem `pipefail`, então a saída de `composer analyse`, `pest`, `typecheck` e `lint` pode falhar sem quebrar o comando do subshell.
  **Evidência:** o script não ativa `set -o pipefail`; a prova mecânica é que `bash -lc 'false | tail -1; printf "exit:%s\n" "$?"'` retornou `exit:0`.
  **Impacto:** o gate local pode deixar passar commit com análise/testes quebrados, anulando o enforcement da Lei 2.

- **ID:** gov-02
  **Severidade:** S2
  **Arquivo:** `.githooks/README.md:27-35`
  **Descrição:** a documentação do hook ensina um bypass manual com `git -c core.hooksPath='' commit ...`, que contradiz a regra de não bypassar gates.
  **Evidência:** a própria seção "Desativar temporariamente" instrui a desativação do hook em cenário de emergência.
  **Impacto:** normaliza exceção operacional sem trilha mecânica equivalente no hook, enfraquecendo a governança do commit.

- **ID:** gov-03
  **Severidade:** S3
  **Arquivo:** `AGENTS.md:221-224` e `CLAUDE.md:58-69`
  **Descrição:** há referências ativas a arquivos que não existem no workspace atual: `.claude/skills/simplify.md`, `.claude/skills/fewer-permission-prompts.md`, `.claude/skills/update-config.md`, `.claude/skills/keybindings-help.md` e `.claude/settings.json`.
  **Evidência:** `Test-Path` retornou `False` para todos esses caminhos; ao mesmo tempo, AGENTS/CLAUDE continuam apontando para eles como se fossem parte do harness.
  **Impacto:** agentes seguem instruções que levam a caminhos mortos, e a alegação de hook `SessionStart` em `.claude/settings.json` fica sem suporte no tree atual.

- **ID:** gov-04
  **Severidade:** S3
  **Arquivo:** `backend/app/Http/Requests/Supplier/StoreSupplierRequest.php:59-64`
  **Descrição:** `withoutGlobalScope('tenant')` é usado sem o marcador obrigatório `LEI 4 JUSTIFICATIVA:`.
  **Evidência:** o arquivo faz a consulta cross-tenant com `->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)` sem a justificativa padronizada; o mesmo padrão aparece em `backend/app/Http/Requests/Customer/StoreCustomerRequest.php:73-78`.
  **Impacto:** o escape de tenant existe, mas não é auditável por regra mecânica; isso fragiliza a revisão de segurança e a rastreabilidade da exceção.

- **ID:** gov-05
  **Severidade:** S3
  **Arquivo:** `backend/app/Http/Controllers/Api/V1/OrganizationController.php:182-189`
  **Descrição:** o endpoint `orgChart()` carrega `Department::with(['manager', 'positions.users'])->get()` sem limite/paginação.
  **Evidência:** a resposta monta a árvore inteira de departamentos com relações aninhadas; o mesmo padrão de coleção sem limite aparece em outros endpoints de listagem auxiliar como `ProductController::categories()` e `ServiceController::categories()`.
  **Impacto:** em tenants grandes, a API pode devolver coleções e grafos inteiros, aumentando latência, memória e risco de degradação.

- **ID:** gov-06
  **Severidade:** S4
  **Arquivo:** código ativo inspecionado em `backend/app`, `frontend/src`, `.claude`, `.githooks`, `scripts`
  **Descrição:** nada encontrado em TODO/FIXME no código ativo.
  **Evidência:** a busca retornou apenas comentários de checagem e referências textuais, sem marcadores reais de TODO/FIXME nas áreas ativas revisadas.
  **Impacto:** sem impacto a reportar nesse item.
