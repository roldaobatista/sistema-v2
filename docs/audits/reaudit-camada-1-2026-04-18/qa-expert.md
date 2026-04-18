# QA Expert — Re-auditoria Camada 1 (Fundação do ERP)
**Data:** 2026-04-18
**Perímetro:** Testes do schema + migrations + models centrais + autenticação
**Diretórios investigados:** `backend/tests/{Feature,Unit,Critical,Arch,Smoke}`, `backend/database/factories/`, `backend/database/seeders/`, `backend/phpunit.xml`, `backend/tests/TestCase.php`, `backend/tests/CreatesTestDatabase.php`.

**Contagem de arquivos:** Feature=714, Unit=173, Critical=21, Arch=1, Smoke=4.

**Minha função foi INVESTIGAR. Findings abaixo são observações concretas com evidência, não veredito.**

---

## Findings por severidade

| Severidade | Qtd |
|---|---|
| S1 | 0 |
| S2 | 3 |
| S3 | 5 |
| S4 | 3 |

Total: **11 findings**

---

## S2 — Alto

### qa-01 — S2 — Factory `InmetroComplianceChecklistFactory` hardcoded `tenant_id => 1`
- **Arquivo:** `backend/database/factories/InmetroComplianceChecklistFactory.php:15`
- **Outras ocorrências idênticas:** `AgendaItemFactory.php:21`, `AlertConfigurationFactory.php:18`, `SystemAlertFactory.php:17`
- **Evidência:**
```
./AgendaItemFactory.php:21:            'tenant_id' => 1,
./AlertConfigurationFactory.php:18:            'tenant_id' => 1,
./InmetroComplianceChecklistFactory.php:15:            'tenant_id' => 1,
./SystemAlertFactory.php:17:            'tenant_id' => 1,
```
- **Descrição:** 4 factories têm `tenant_id` hardcoded como `1`. O padrão correto (seguido por `CustomerFactory`, `LgpdDataRequestFactory`, `InmetroOwnerFactory`) é `'tenant_id' => Tenant::factory()`.
- **Impacto:** (a) Em testes paralelos (16 processos), múltiplos processos podem colidir no mesmo tenant_id=1, gerando flakiness em asserções `->count()` e em filtros por tenant; (b) Se nenhum tenant com id=1 existir no DB de teste, FKs falham silenciosamente com SQLite `foreign_key_constraints => false` no template DB (`CreatesTestDatabase.php:45`); (c) Viola Lei 4 (tenant safety absoluto — tenant não pode ser fixado).

### qa-02 — S2 — Ausência de teste de regressão de schema dump (`sqlite-schema.sql` vs migrations)
- **Arquivo:** `backend/tests/` — apenas `Unit/EquipmentSchemaRegressionTest.php` cobre UM caso (equipment.status default). `Feature/Integration/TenantSchemaRegressionTest.php` cobre apenas `tenants.deleted_at` (arquivo com 11 linhas — `grep-tenant-migration-soft-only`).
- **Evidência:**
```
tenant-schema-reg-full (grep total: 11 linhas)
test('tenants table keeps deleted_at required by soft deletes', function () {
    expect(Schema::hasColumn('tenants', 'deleted_at'))->toBeTrue();
});
```
E `schema-dump-test` busca: apenas 2 arquivos de regressão existem.
- **Descrição:** Não existe teste que compare `backend/database/schema/sqlite-schema.sql` contra migrations pendentes/aplicadas. O `CreatesTestDatabase` carrega o dump como template; se migrations forem criadas sem regenerar o dump, testes passam em SQLite mas produção (MySQL) fica fora de sincronia. O checklist §7a exige isto explicitamente.
- **Impacto:** Divergência silenciosa schema dump ↔ migrations. Cenário do próprio histórico do projeto: a regeneração do dump em 2026-04-18 expôs 3 bugs latentes (commit `3443dda`) — sem o teste isso só foi pego por acidente na regeneração manual.

### qa-03 — S2 — Ausência de teste unitário do trait `BelongsToTenant`
- **Arquivo:** busca `grep -rln "Traits/BelongsToTenant|BelongsToTenantTest"` em `backend/tests/` → **nenhum resultado**.
- **Evidência:** comando `belongs-to-tenant-trait-unit` retornou `(no output)`. Ocorrências são apenas comentários em feature tests descrevendo comportamento (`CalibrationDecisionControllerTest.php:96`, `CrmFieldManagementTest.php:177` etc.).
- **Descrição:** Checklist §5a exige: "`BelongsToTenant` tem teste que grava em tenant A e prova que query em tenant B não vê". O trait é o mecanismo de defesa mais crítico do ERP multi-tenant e não tem teste unitário dedicado — cobertura depende inteiramente de feature tests que passam por controllers (podem mascarar a causa raiz se o trait quebrar).
- **Impacto:** Regressão no trait (ex: mudança de nome de coluna `current_tenant_id`, bug no global scope) pode passar despercebida até um feature test distante falhar. Diagnóstico difícil.

---

## S3 — Médio

### qa-04 — S3 — Teste com asserção placeholder (`expect(true)->toBeTrue()`)
- **Arquivo:** `backend/tests/Feature/Integration/ObserverTest.php:197`
- **Evidência:**
```
test('CustomerObserver does not recalculate on irrelevant field changes', function () {
    $customer = Customer::factory()->create([...]);
    $customer->update(['name' => 'New Name']);
    // No exception = no infinite recursion
    expect(true)->toBeTrue();
});
```
- **Descrição:** Checklist §3a veta `assertTrue(true)` e assertions vazias. O teste confia em "sem exceção = passou", mas não observa `health_score` (poderia usar `->not->toHaveChanged()` ou um spy). Viola Lei 2 (causa raiz) e o padrão do próprio README da suite (`README.md:97 — NUNCA mascarar testes (skip, assertTrue(true), relaxar assertions)`).
- **Impacto:** Regressão onde o observer passa a recalcular indevidamente em mudança de `name` não seria detectada.

### qa-05 — S3 — Testes de Customer usando valor de `type` inválido (`'company'`/`'individual'`)
- **Arquivo:** 22 ocorrências — exemplos:
  - `Unit/Models/CustomerDeepTest.php:122`
  - `Feature/Api/CoreCrudControllerTest.php:61,77`
  - `Feature/Api/CustomerControllerTest.php:56`
  - `Feature/Api/ValidationEdgeCasesTest.php:49,77,87,231,240,249,260,269`
  - `Feature/Security/{AdvancedSecurityTest,RbacDeepTest,SecurityHardeningTest}.php` (múltiplas linhas)
- **Evidência — migration:** `database/migrations/2026_02_07_300000_create_cadastros_tables.php:37 — $table->enum('type', ['PF', 'PJ'])->default('PF');`
- **Evidência — teste:**
```
Customer::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'company']);
$results = Customer::where('type', 'company')->get();
$this->assertTrue($results->contains('id', $company->id));
```
- **Descrição:** O schema MySQL declara enum `('PF','PJ')`. Em SQLite (teste) enums são apenas `varchar` sem enforcement, então esses testes passam — mas não refletem a realidade do banco de produção. Viola Lei 5 (preservação) e Lei 4 (status/valores em inglês não é aceitável quando o enum real é PF/PJ).
- **Impacto:** Se alguém adicionar `CHECK` constraint ou enum real no SQLite dump, 20+ testes quebram. Pior: esses testes dão falsa confiança sobre comportamento com `type='company'` que em produção nem seria aceito.

### qa-06 — S3 — Assertion relaxada em cross-tenant audit log (aceita 200 OU 422)
- **Arquivo:** `backend/tests/Feature/Api/V1/AuditLogControllerTest.php:78-110`
- **Evidência:**
```
$this->assertContains(
    $response->status(),
    [200, 422],
    'Filtro por user_id de outro tenant deve ser rejeitado ou ignorado, nunca vazar logs'
);
```
- **Descrição:** Checklist §3d: "Assertions relaxadas". O teste aceita dois comportamentos conflitantes (rejeitar via exists rule = 422, ou ignorar = 200 com lista vazia) como válidos. Isso é uma decisão de produto que deveria ser fixada — não testada como "qualquer um serve". Viola Lei 2 (causa raiz) e Lei 5 (preservação: o comportamento correto deve ser conhecido).
- **Impacto:** Controller pode oscilar entre 200 e 422 em refactors sem que o teste detecte. Usuário-atacante tem dois caminhos possíveis; o teste não exige um contrato.

### qa-07 — S3 — Teste de encryption não verifica ausência do campo sensível na resposta JSON
- **Arquivo:** `backend/tests/Critical/Encryption/EncryptionAtRestTest.php` (170+ linhas)
- **Evidência:** o teste cobre (1) ciphertext no DB, (2) reversibilidade única, (3) cast decriptando. **Não** cobre `$hidden` em responses JSON (checklist §4c).
- **Descrição:** Por exemplo, `TwoFactorAuth.secret` — nada garante que `GET /api/v1/security/2fa/status` não vaze o campo. `TwoFactorTest.php` testa estrutura (`data: [enabled, method, verified_at]`) — mas não afirma que `secret`/`backup_codes` NÃO aparecem. `assertJsonStructure` só garante presença, não ausência.
- **Impacto:** Regressão no `$hidden` do Model (ex: `secret` removido de `$hidden` acidentalmente) não é pega. Vazamento de secret TOTP/backup codes em JSON da API seria invisível nos testes.

### qa-08 — S3 — `DatabaseSeederCentralPermissionsTest` não cobre cross-tenant pollution
- **Arquivo:** `backend/tests/Feature/DatabaseSeederCentralPermissionsTest.php`
- **Evidência:** teste apenas verifica que roles recebem permissions. Não exercita o risco de `DatabaseSeeder` criar tenants/branches fixos (`DatabaseSeeder.php:26 Tenant::firstOrCreate`, `:35-36 Branch::firstOrCreate`) poluindo outros testes que dependem de contagem global.
- **Descrição:** Checklist §5b: "Seeder de teste polui com dados de tenant fixo". O `DatabaseSeeder` cria `t1`, `t2`, `t3` via `firstOrCreate` e depois insere `Branch`, `Customer` (`:149 ['document' => $c['document'], 'tenant_id' => $t1->id]`). Se outro teste chamar `$this->seed(DatabaseSeeder::class)` (que `CrmReferenceSeederTest.php` faz parcialmente via `CrmSeeder`), pode vazar dados entre cenários.
- **Impacto:** Flakiness em testes que contam registros globais. Difícil de rastrear.

---

## S4 — Baixo

### qa-09 — S4 — Factories de PII gerando documentos não-válidos (apenas máscara numérica)
- **Arquivo:** `backend/database/factories/{CustomerFactory,SupplierFactory,TenantFactory,InmetroOwnerFactory,LgpdDataRequestFactory}.php`
- **Evidência:**
```
./CustomerFactory.php:22: $type === 'PJ' ? fake()->numerify('##.###.###/####-##') : fake()->numerify('###.###.###-##')
./TenantFactory.php:17: fake()->unique()->numerify('##.###.###/####-##')
./InmetroOwnerFactory.php:14: $this->faker->unique()->numerify('##############')
./LgpdDataRequestFactory.php:24: $this->faker->numerify('###########')
```
- **Descrição:** Checklist §8c: "Factory de PII gera dados válidos para validadores (CPF/CNPJ)". `numerify` gera dígitos aleatórios, não CPF/CNPJ com DV válido. Se qualquer FormRequest ou Model usar `ValidatorCpfCnpj`, o factory falha.
- **Impacto:** Testes que persistem via factory passam, mas testes que simulam postJson com o mesmo documento falham validação — inconsistência de fixture. A "regressão silenciosa" é: quando um validador for adicionado, centenas de testes quebram simultaneamente.

### qa-10 — S4 — `UnitTestCase` não seta `APP_KEY`/`DB_CONNECTION` explicitamente, depende de phpunit.xml
- **Arquivo:** `backend/tests/UnitTestCase.php:16` e `backend/phpunit.xml:42-48`
- **Descrição:** `UnitTestCase` estende `BaseTestCase` sem database trait. Ok para testes puros. Mas não há teste arquitetural verificando que `UnitTestCase` é realmente usado apenas onde apropriado. Risco baixo mas detectável em §10c/d do checklist.
- **Impacto:** Desenvolvedor pode estender `UnitTestCase` em teste que precisa DB, recebe erro críptico em vez de guidance.

### qa-11 — S4 — Suíte `Arch` com apenas 1 arquivo — cobertura arquitetural muito rasa
- **Arquivo:** `backend/tests/Arch/ArchTest.php` (único — `tests-count-by-dir: Arch: 1`)
- **Descrição:** Checklist §10a: consistência entre `defaultTestSuite` + composer scripts + CI. O único arquitectural test cobre: Controllers suffix, Models extend Model, sem `dd()`, FormRequests extendem FormRequest, workflows existem, pint.json válido, phpstan nível 7+, enums backed. **Falta:** regra arquitetural garantindo que todo Model com tenant_id usa trait `BelongsToTenant`; regra garantindo que todo FormRequest tem `authorize()` não-trivial (Lei do CLAUDE.md); regra garantindo que migrations novas têm `hasTable/hasColumn` guards.
- **Impacto:** Violações dos princípios declarados em CLAUDE.md não são mecanicamente impedidas.

---

## Checklist — respostas item a item

1. **Cobertura por tipo** — (a) Não auditei cada controller individualmente (fora do escopo "fundação"); (b) idem; (c) ver qa-02; (d) não auditei todos endpoints.
2. **Cross-tenant isolation** — Presente: `CustomerIsolationTest`, `FinancialIsolationTest`, `CascadeDeleteTest`, `EagerLoadLeakTest` cobrem GET/UPDATE/DELETE → 404 corretamente. **OK.**
3. **Anti-patterns** — (a) 1 ocorrência (qa-04); (b,c) 0 ocorrências (`grep-markincomplete-deep` vazio — bom); (d) 1 ocorrência (qa-06) + 3502 usos de `assertOk()/assertStatus(200)` — não auditei cada um, mas o `assertJsonStructure` aparece com frequência razoável (mesma ordem de magnitude); (e) ver qa-09.
4. **Encryption/PII** — (a) coberto (§1 do `EncryptionAtRestTest`); (b) coberto (bcrypt `$2y$` + `Hash::check`); (c) ver qa-07 (não coberto).
5. **Multi-tenant** — (a) ver qa-03 (sem teste unitário do trait); (b) ver qa-08.
6. **Estrutura Pest** — `describe()` não encontrado nos arquivos centrais. `beforeEach()` usado corretamente. Custom expectations (`assertEncryptedField`) existem. Parcial — sem finding grave.
7. **Schema/migration** — ver qa-02. `ProductionMigrationRegressionTest.php` existe (não auditei conteúdo). Teste de `hasTable/hasColumn` guards existe para equipment mas não como regra geral (ver qa-11).
8. **Factories** — (a) 4 factories violam (qa-01); relacionamentos via `Tenant::factory()` em Customer/Lgpd/Inmetro está correto; (c) ver qa-09.
9. **FormRequest/validation** — (a) apenas `AuditLogControllerTest.php:78` cobre exists-cross-tenant (e com assertion relaxada — qa-06); **não encontrei grep amplo**. (b) `SecurityHardeningTest.php:68`, `IamTest.php:480`, `AuthSecurityTest.php:527` cobrem mass-assignment spoofing — OK.
10. **Performance / flakiness** — (a) `phpunit.xml` `defaultTestSuite="Default"` = Unit+Feature+Smoke+Arch, consistente com `composer test-fast`. **OK.** (b) `Carbon::now()` em TimeEntry tests — fixtures baseadas em tempo real, mas sem `Carbon::setTestNow` — risco de flakiness perto de meia-noite/fim de semana. Não listei como finding separado pois está fora do perímetro de "fundação". (c) 0 `sleep()` relevantes. (d) `LazilyRefreshDatabase` + `CreatesTestDatabase` OK.

---

**Fim do relatório.**
