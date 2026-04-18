# Re-auditoria — Camada 1 (Fundação / Schema + Migrations)

**Data:** 2026-04-17
**Baseline:** `docs/audits/findings-camada-1.md`
**Desenho:** prompt neutro via skill `audit-prompt` — agentes sem visibilidade de findings originais, commits ou arquivos tocados.
**Suite Pest:** 9752 passed / 0 failed / 28148 assertions / 242s / exit 0.

## Veredito: **REABERTA**

Critério: `não_resolvidos ≠ ∅` **E** `novos_S1 ≠ ∅`. Ambos atendidos.

- **10 S1 novos** encontrados — 3 bugs funcionais S1 em encryption (Wave 1/3 regressiva) + 3 S1 governance (PT residuais em `central_*`) + 3 S1 QA (testes de encryption/cross-tenant/hidden ausentes) + 1 S1 produto (terminologia ISO 17025).
- **PROD-003 não resolvido** — Wave 6.7 (alegadamente "central_* colunas PT→EN (11 cols × 5 tabelas)") deixou de fora `central_attachments`, `central_templates` (parcial), `central_subtasks` (parcial), `central_items` (colunas legacy residuais).
- **GOV-R2-015 não resolvido** — schema dump stale persiste (header incoerente com `generate_sqlite_schema.php`).
- **Regressão estrutural Lei 4** — `ConsolidatedFinancialController:58` lê `tenant_id` do body.

Camada 1 permanece **em progresso**. Antes de seguir para Camada 2, corrigir bloqueios S1 + S2 via `/fix` e re-rodar `/reaudit "Camada 1"`.

---

## Experts invocados (5)

| Expert | Arquivo | Totais |
|---|---|---|
| data-expert | `reaudit-camada-1-2026-04-17/data-expert.md` | 0 S1, 3 S2, 4 S3, 2 S4 |
| security-expert | `reaudit-camada-1-2026-04-17/security-expert.md` | 3 S1, 5 S2, 4 S3, 2 S4 |
| governance | `reaudit-camada-1-2026-04-17/governance.md` | 3 S1, 4 S2, 5 S3, 2 S4 |
| qa-expert | `reaudit-camada-1-2026-04-17/qa-expert.md` | 3 S1, 5 S2, 4 S3, 1 S4 |
| product-expert | `reaudit-camada-1-2026-04-17/product-expert.md` | 1 S1, 5 S2, 4 S3, 1 S4 |
| **Total** | — | **10 S1, 22 S2, 21 S3, 8 S4 = 61 findings** |

Experts foram invocados via `subagent_type=general-purpose` (agents `.claude/agents/*.md` não estão registrados como subagent_types neste harness) com instrução explícita de assumir a persona lendo o agent file correspondente. **Nenhum dos 5 recebeu a baseline, findings originais, commit range ou lista de arquivos tocados.** Proibições (`docs/audits/`, `docs/handoffs/`, `docs/plans/`, git history) foram confirmadas nos retornos.

---

## Set-difference mecânico

### Não resolvidos (originais que reapareceram)

| ID original | Finding re-auditoria | Match |
|---|---|---|
| **PROD-003** | DATA-RA-01, GOV-RA-01 (`central_attachments.nome`); DATA-RA-02, GOV-RA-01 (`central_templates` colunas PT + default `'TAREFA'`); GOV-RA-02 (`central_subtasks.concluido/ordem`) | `arquivo:linha` coincide em `sqlite-schema.sql:557-567`, `central_templates`, `central_subtasks`. Wave 6.7 apenas 3 de 5 tabelas `central_*` cobertas. |
| **PROD-005 / GOV-004** | GOV-RA-03 (`central_items` legacy `user_id` + `completed` coexistindo com `assignee_user_id`/`closed_at`) | Persiste resíduo. Wave 6.4 dropou `expenses.user_id` mas não tocou em `central_items`. |
| **SEC-010 / DATA-004** | DATA-RA-04 (30 tabelas `tenant_id NULLABLE`, 20 questionáveis) | Contagem reduziu de 69→30 mas não zerou. Parcialmente resolvido. |
| **DATA-003** | DATA-RA-09 (`marketplace_partners`, `competitor_instrument_repairs`, `permission_groups` sem tenant_id) + DATA-RA-03 (223 declarações `tenant_id` sem FK) | Wave 2A endereçou 12 standalone tables mas 3 candidatas persistem + 223 FK ausentes. |
| **GOV-001** | DATA-RA-05 / GOV-RA-09 (migrations ALTER/add sem guards H3) | Persiste. Aceito como dívida estrutural, mas ainda listado. |
| **GOV-010** | GOV-RA-05 (10 pares de migrations com timestamps duplicados) | Persiste. |
| **DATA-NEW-001 / GOV-R2-015** | GOV-RA-06 (header do schema dump incoerente com `generate_sqlite_schema.php`) | Persiste. |
| **DATA-010** | DATA-RA-07 (precisão decimal não ampliada em agregados payroll/fiscal/quotes) | Wave §14.10 cobriu o core mas agregados derivados persistem. |

### Novos findings (regressões e descobertas)

#### S1 — bloqueadores (10)

| ID novo | Arquivo:linha | Descrição | Origem provável |
|---|---|---|---|
| SEC-RA-01 | `TwoFactorController.php:45,88` + `TwoFactorAuth.php:32,34` | Dupla criptografia — controller faz `encrypt()` + Model faz cast `encrypted` → ciphertext duplo no DB, leitura retorna ciphertext, **2FA quebrado** | Regressão Wave 1 (encryption) |
| SEC-RA-02 | `Tenant.php:81-92` + `CertificateService.php:45` + `sqlite-schema.sql:6809` | `Tenant.fiscal_certificate_password` e `fiscal_nfse_token` sem cast encrypted; um criptografado manual via Service, outro em claro → assimetria + token fiscal em plain text | Nunca coberto pela baseline |
| SEC-RA-03 | `IntegrationController.php` | Mesmo padrão de dupla criptografia em credenciais SSO/payment/marketing | Regressão Wave 1/3 |
| GOV-RA-01 | `sqlite-schema.sql` — `central_templates` | `nome`, `categoria`, `ativo` PT residuais + default `'TAREFA'` UPPERCASE | Wave 6.7 incompleta — **reabertura de PROD-003** |
| GOV-RA-02 | `central_subtasks` | `concluido`, `ordem` PT residuais | Wave 6.7 incompleta |
| GOV-RA-03 | `central_items` | `user_id` + `completed` legacy coexistem com `assignee_user_id`/`closed_at` | Wave 6.6/6.7 incompleta |
| QA-RA-01 | 17 Models com cast `encrypted` | Zero testes `getRawOriginal()` para esses campos (6 existentes só testam status) — **é o que deixou SEC-RA-01 passar** | Nunca coberto |
| QA-RA-02 | 126/269 controllers (46,9%) | Sem cenário cross-tenant explícito — Lei 4 sem malha de segurança | Nunca coberto |
| QA-RA-03 | 17 Models com `$hidden` | Sem teste de não-vazamento em `toArray()`/JSON | Nunca coberto |
| PROD-RA-01 | schema + UI + portal cliente | "Laudo Técnico" vs "Certificado de Calibração" misturados — risco ISO 17025 §7.8 | Nunca coberto |

#### S2 — altos (22)

Ver relatórios individuais. Destaques:

- **GOV-RA-07** `ConsolidatedFinancialController.php:58` — `$request->input('tenant_id')` como fallback. **Regressão Lei 4.**
- **DATA-RA-03** — 223 declarações `tenant_id` sem FK no banco.
- **DATA-RA-04** — 30 tabelas `tenant_id NULLABLE` (20 questionáveis).
- **PROD-RA-04** — contradição entre `TECHNICAL-DECISIONS §14.13.b` (afirma `origem→origin` direto) e a cadeia real de migrations (`origem→source→origin`). Documentação desalinhada do código.
- **PROD-RA-06** — divergência `priority`: `work_orders` usa `'normal'`, `central_*` usa `'medium'`. Lei 5 (preservação + consistência).
- **GOV-RA-05** — 10 pares de migrations com timestamps duplicados.
- **GOV-RA-06** — schema dump header incoerente com o script de geração.
- **SEC-RA** (5 S2) — ver arquivo; várias relacionadas a mass-assignment/fillable em Models de integração.
- **QA-RA** (5 S2) — cobertura < 4 testes em controllers de financeiro + encryption + portal cliente.

#### S3 — médios (21) / S4 — advisories (8)

Dívida rastreável. Ver relatórios individuais. Não bloqueiam fechamento por si, mas se somados aos S1/S2 indicam margem estreita de qualidade.

### Resolvidos (originais que não reapareceram)

Com base no conjunto complementar (baseline `findings-camada-1.md` menos os "não resolvidos" acima):

| IDs resolvidos ou substancialmente tratados |
|---|
| S0: DATA-015, SEC-008, PROD-014 (decisões em §14.x) |
| S1 encryption core: SEC-001, SEC-003, SEC-004, SEC-005, SEC-006, SEC-007 (mas **SEC-RA-01/02/03 mostram regressão em SEC-002 + novos alvos** — ver S1 novos) |
| S1 GOV-002 (resíduos update_to_english) |
| S2 DATA-001, DATA-002, DATA-007, DATA-013, SEC-009, SEC-011 (índices + UNIQUE composto + pivots) |
| S2 DATA-005, SEC-012 (cascade — não reapareceram na re-audit; confirmar se não foi falso-negativo) |
| S2 PROD-001, PROD-002, PROD-004, GOV-003, GOV-005 (convenções EN das tabelas tocadas na Wave 6) |
| S2 GOV-009, GOV-014 (drift `company_id` + migration test columns) |
| S3 DATA-008, DATA-011, DATA-014, SEC-013, SEC-015, SEC-020 (hardening portal + audit + timezone + polymorphic) |
| Novos R2 DATA-NEW-006, PROD-015, PROD-016 (órfãs + mass-assignment Wave 1B) |

**Atenção:** "não reaparecer" ≠ "definitivamente resolvido" — pode ser gap de cobertura do agente. Confirmação final requer validar findings contra código atual (spot-check).

---

## Consolidado

```
FINDINGS ORIGINAIS (61 da baseline)
  ✅ Resolvidos (provável): ~44
  ❌ Não resolvidos (parcial ou total): 8 (PROD-003, PROD-005/GOV-004, SEC-010/DATA-004, DATA-003, GOV-001, GOV-010, GOV-R2-015, DATA-010)
  ⚠️  Incerto: ~9 (spot-check recomendado — cascade, decimal agregados, fósseis H3)

NOVOS FINDINGS (61 encontrados - ~8 matches com originais = ~53 novos)
  S1: 10
  S2: 22
  S3: 21
  S4: 8
```

---

## Próxima ação

1. **Triage S1 (10)** — decidir ordem de correção. Sugestão: SEC-RA-01/02/03 (bugs funcionais de encryption) **primeiro** — afetam 2FA, certificado fiscal e integrações.
2. **Corrigir via `/fix`** os S1 + S2 estruturais (GOV-RA-07 Lei 4, DATA-RA-03/04 tenant safety, PROD-RA-01 ISO 17025).
3. **Completar Wave 6.7** — `central_attachments`, `central_templates` residuais, `central_subtasks` residuais, `central_items.user_id/completed` legacy.
4. **Cobrir gap QA** — testes de `getRawOriginal()` para encryption + cross-tenant para os 126 controllers faltantes + `$hidden` vazamento.
5. **Re-rodar `/reaudit "Camada 1"`** após correções — não declarar fechamento antes do veredito FECHADA.

---

## Notas de processo

- **Limitação observada:** os agents especializados (`data-expert`, `security-expert`, etc.) **não estão registrados como `subagent_type`** no harness atual. Foi usado `general-purpose` com instrução de assumir a persona via leitura do agent file. Em sessão futura, considerar registrar os agents ou ajustar a skill `audit-prompt` para refletir essa limitação.
- **Baseline do 12º S1** — `findings-camada-1.md` anotou que a Rodada 1 declarava "12 S1" mas tabela consolidada listou 11. Re-auditoria não usou essa nota — irrelevante para o set-difference, já que trabalhamos com o que cada agente encontrou.
- **Suite verde ≠ fechamento confirmado.** A re-auditoria encontrou SEC-RA-01 (2FA duplamente cifrado, quebrado funcionalmente) com suite 9752/0. Testes não pegaram porque não há cobertura de `getRawOriginal()` (QA-RA-01). Exatamente o tipo de regressão que uma auditoria viciada (confirmando correção) esconderia.
