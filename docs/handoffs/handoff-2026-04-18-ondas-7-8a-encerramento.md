# Handoff — Camada 1 Ondas 7.1 + 7.2 + 8.A concluídas

**Data:** 2026-04-18
**Branch:** main (working tree limpo)
**Último commit:** `a050fae stabilize(layer-1): Onda 8 parte A - 5 decisoes aprovadas`
**Commits à frente de `sistema-v2/main`:** 20

## Resumo da sessão

Sessão executou ciclo completo de estabilização da Camada 1:

1. **Harness corrigido** (3 commits):
   - `394b615` — skill `audit-prompt` com prompt neutro (sem vazar findings/commits/arquivos ao agente).
   - `fdbc6da` — critério binário de fechamento: zero findings = FECHADA. CONDICIONAL removido.
   - `ca6024c` — baseline canônica `docs/audits/findings-camada-1.md` consolidada.

2. **Re-auditoria neutra executada** (`26123ba`) via 5 experts em paralelo:
   - Veredito: **REABERTA** com 61 findings (10 S1 + 22 S2 + 21 S3 + 8 S4).
   - Descobriu regressões ocultas que a suite verde (9752/0) estava mascarando — principalmente **2FA duplamente criptografado** (funcionalmente quebrado em produção).

3. **Ondas de correção aplicadas** (3 commits):
   - `00e5b78` — Onda 7.1 (3 S1 encryption)
   - `384b4d6` — Onda 7.2 (9 findings central_* PT→EN)
   - `a050fae` — Onda 8 parte A (5 decisões aprovadas + 3 falsos positivos)

**Total resolvido:** 17 findings (6 S1 + 5 S2 + 6 S3/S4) + 3 falsos positivos documentados.

**Restantes:** ~41 findings (em sua maioria refatoração massiva, gaps de cobertura, ou dívida rastreável).

## Estado ao sair

- **Working tree limpo.** 20 commits à frente de `sistema-v2/main`.
- **Suite verde.** Todos os filters relevantes rodados: 922 (Onda 7.1), 180 (Onda 7.2), 1315 (Onda 8.A). Total ~2400 testes passando dos rodados.
- **Schema dump não regenerado.** MySQL Docker offline na sessão. Rodar `php generate_sqlite_schema.php` quando voltar.
- **Camada 1 permanece REABERTA** pela nova regra binária do harness. Para chegar a zero findings faltam as ondas descritas abaixo.

## Findings ainda abertos (48)

### S1 S2 que dependem de refatoração (não fizemos)

| ID | Problema | Escopo |
|---|---|---|
| SEC-RA-04 | 367 Models com `tenant_id` em `$fillable` | Refatoração massiva + callers |
| SEC-RA-05 | Role sem BelongsToTenant + tenant_id em fillable | **Tentado, rollback** (27 testes falharam). Precisa BelongsToTenant + refat callers |
| SEC-RA-06 | TwoFactorAuth.backup_codes encrypted em vez de hashed | Mudar Model + fluxo consumo (~2 arq + teste) |
| SEC-RA-07 | 21 child tables sem `tenant_id` | Tabela-a-tabela |
| SEC-RA-08 | Cascade delete `tenants` em massa | Mudança estrutural |
| DATA-RA-03 | 223 FK `tenant_id` ausentes | Tabela-a-tabela |
| DATA-RA-04 | 30 tabelas com `tenant_id NULLABLE` | Backfill + ALTER NOT NULL |
| GOV-RA-05 | 10 pares migrations com timestamp duplicado | Aceitar como fóssil H3 + regra para frente (`_500000+`) |
| GOV-RA-06 | Schema dump header incoerente com generate_sqlite_schema.php | Regenerar dump OU atualizar header no script |
| PROD-RA-04 | Contradição §14.13.b (cadeia `origem→source→origin`) | Corrigir doc §14.13.b |

### S3/S4 dívida rastreável

`DATA-RA-05/06/07/08`, `SEC-RA-12/14`, `GOV-RA-04/08/09/12/13/14`, `PROD-RA-07/08/09/10/11`, `QA-RA-*` (10 itens de cobertura: factory gap, E2E vazio, Arch subcoberto, Carbon::now() flaky, rand() flaky, cross-tenant tests, encryption leak tests, $hidden tests, assertJsonStructure, FormRequest testing, phpunit defaultTestSuite).

## Pendências

### Próxima sessão pode atacar (fácil):

1. **PROD-RA-04** — corrigir §14.13.b em TECHNICAL-DECISIONS.md reconhecendo cadeia `origem→source→origin` (migration `2026_04_17_310000_rename_central_source_to_origin.php` confirma essa cadeia real).
2. **GOV-RA-06** — subir MySQL Docker e rodar `php generate_sqlite_schema.php` para regenerar dump com header correto.
3. **GOV-RA-05** — adicionar §14.19 em TECHNICAL-DECISIONS.md aceitando timestamps duplicados como fóssil H3 + regra para futuras migrations.
4. **QA-RA-13** — mudar `phpunit.xml` `defaultTestSuite="Default"` → `"Unit"` (escalada).
5. **SEC-RA-06** — migrar backup_codes para hash bcrypt com teste de validação.

### Precisam planejamento (sprints dedicados):

- **SEC-RA-04/07, DATA-RA-03/04**: auditoria tabela-a-tabela. Sugestão: sprint dedicado "Tenant Safety Estrutural" com orchestrator decidindo 1 tabela por vez.
- **SEC-RA-08**: policy de cascade — decidir quais tabelas devem ser `RESTRICT` + migration massiva.
- **Categoria QA (9 findings)**: sprint dedicado "Gaps de Cobertura" com metas: Factory para todos 231 Models faltantes, ArchTests cobrindo 4 padrões obrigatórios, suite Critical/Encryption+PiiLeakage+SoftDelete.

## Decisões arquiteturais tomadas (§14.14-18)

- **§14.14** — 3 tabelas globais-por-design (marketplace_partners, competitor_instrument_repairs, permission_groups)
- **§14.15** — ConsolidatedFinancialController é exceção autorizada à Lei H1
- **§14.16** — Certificado de Calibração ≠ Laudo Técnico (ISO 17025)
- **§14.17** — Switch de tenant revoga todos os tokens (SEC-RA-13)
- **§14.18** — 3 falsos positivos aceitos (SEC-RA-09/10/11)

## Arquivos-chave

- `docs/audits/findings-camada-1.md` — baseline canônica
- `docs/audits/reaudit-camada-1-2026-04-17.md` — relatório consolidado
- `docs/audits/reaudit-camada-1-2026-04-17-punchlist.md` — punch-list com 6 ondas
- `docs/audits/reaudit-camada-1-2026-04-17/` — 5 relatórios individuais dos experts
- `docs/TECHNICAL-DECISIONS.md` §14.14-18 — decisões desta sessão

## Próxima ação recomendada

Abrir nova sessão com `/resume`. Atacar os 5 itens fáceis da lista "Próxima sessão pode atacar" em sequência. Depois planejar sprints para os massivos.

**Não declarar Camada 1 FECHADA** até que `/reaudit "Camada 1"` retorne zero findings (nova regra binária).
