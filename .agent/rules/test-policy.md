# Politica de Testes (OBRIGATORIA - NUNCA IGNORAR)

> Esta regra tem prioridade MAXIMA. Nenhuma outra regra pode sobrescreve-la.

> **⚙️ HARNESS ENGINEERING — Modo operacional sempre-ligado (P-1).** Esta política define *o que* testar e *como* escrever testes profissionais; o Harness define *como reportar* a execução. Toda resposta final que envolva código deve preencher, na ordem, o **formato Harness de 7+1 itens**: (1) resumo do problema, (2) arquivos alterados, (3) motivo técnico, (4) **testes executados** — comando exato copiável, seguindo a pirâmide de escalação, (5) **resultado dos testes** — output real com contagem passed/failed (proibido parafrasear ou inventar números), (6) riscos remanescentes, (7) próximo passo/recomendações, (8) como desfazer (quando aplicável). Regra **H7** do Harness: proibido usar "testes passando", "validado", "funcionando" sem evidência objetiva no mesmo turno da resposta. Regra **H8**: qualquer teste vermelho é bloqueante — corrigir causa raiz, nunca mascarar. Fonte canônica: `.agent/rules/harness-engineering.md`.

## Regra de Ouro (GRAVADA NA MEMORIA MAXIMA EM TODA CONVERSA)

**QUALQUER TESTE QUE FALHAR DEVE SER CORRIGIDO SEMPRE O PROBLEMA, NUNCA MASCARAR O TESTE, O TESTE DEVE SER SEMPRE PROFISSIONAL, COMPLETO E PROFUNDO.**

**SE UM TESTE FALHAR DEVE CORRIGIR O SISTEMA; ADICIONAR FUNCIONALIDADES FALTANTES, ADICIONAR FUNÇÕES; GARANTIR FLUXO COMPLETO; E SÓ ALTERAR O TESTE SE REALMENTE O TESTE ESTIVER ERRADO.**

**TODA FUNCIONALIDADE E FUNÇÃO QUE FIZER ALTERAÇÃO DEVE TER UM TESTE ESPECÍFICO, PROFUNDO E PROFISSIONAL PARA ELA; SE NÃO TIVER DEVE CRIAR E ETC.**

## Regras Inviolaveis Principais

### 1. NUNCA mascarar teste que falha
- Se um teste falha: **corrigir o codigo do sistema**, nao o teste.
- PROIBIDO: comentar teste, pular com `skip`/`todo`, relaxar assertion, remover caso de teste.
- PROIBIDO: trocar `toBe(expected)` por `toBe(actualWrongValue)` para "passar".
- **SÓ ALTERAR O TESTE SE REALMENTE O TESTE ESTIVER ERRADO.**

### 2. Teste falhou = DEVE corrigir o sistema
Quando um teste falha, a IA DEVE agir no sistema:
1. Adicionar funcionalidades faltantes.
2. Adicionar funcoes/metodos faltantes.
3. Garantir fluxo completo.
4. **SÓ alterar o teste se comprovadamente errado**, e justificar.

### 3. Toda funcionalidade (nova OU alterada) DEVE ter teste especifico
- Qualquer funcionalidade ou função **nova ou alterada** DEVE ter um teste específico, profundo e profissional para ela.
- "Alterada" inclui: refatoração, mudança de assinatura, mudança de comportamento, correção de bug.
- "Nova" inclui: novo endpoint, novo service method, novo model scope, novo job, novo event.
- Se não tiver teste, **DEVE CRIAR**. Não existe exceção a essa regra.

### 4. Qualidade dos testes

- Testes devem ser **profissionais**: nomes descritivos, cenarios reais, edge cases.
- Testes devem ser **profundos**: cobrir happy path, error path, edge cases, limites.
- Testes devem ser **completos**: nao testar so o basico, testar o fluxo inteiro.
- Seguir padrao AAA (Arrange-Act-Assert).
- Sem `console.log` em testes (usar assertions).
- Sem dados mockados que nao refletem a realidade.

### 5. Onde estao os testes (referencia rapida)

Consultar `docs/operacional/mapa-testes.md` para mapa completo. Resumo:

| Camada | Local | Framework | Testes |
|--------|-------|-----------|--------|
| Backend PHP | `backend/tests/` (Arch, Critical, E2E, Feature, Performance, Smoke, Unit) | PHPUnit + Pest | ~4.815 |
| Frontend React | `frontend/src/__tests__/` + `frontend/src/lib/*.test.ts` | Vitest | ~1.751 |
| E2E | `frontend/e2e/` + `frontend/tests/e2e/` | Playwright | ~234 |

### 6. Como executar

```bash
# Backend
cd backend && php artisan test

# Frontend unit
cd frontend && npm run test

# E2E
cd frontend && npx playwright test
```

### 7. Checklist antes de finalizar qualquer tarefa

- [ ] Todos os testes existentes passam? Se nao, corrigir o SISTEMA.
- [ ] Codigo novo tem testes? Se nao, CRIAR.
- [ ] Testes cobrem happy path + error path + edge cases?
- [ ] Nenhum teste foi mascarado, pulado ou relaxado?

## Definição Oficial: O Que Constitui "Mascarar Teste"

Mascarar teste é qualquer ação que faz um teste passar SEM corrigir o problema real. Inclui:

### Ações PROIBIDAS (constituem mascaramento)
1. **skip/todo/markTestSkipped** — Pular teste que falha
2. **Comentar teste** — Remover teste do suite
3. **Relaxar assertion** — Trocar `assertExact` por `assertContains` para aceitar resultado parcial
4. **Trocar valor esperado** — Mudar `toBe('correct')` para `toBe('wrong_but_actual')`
5. **Remover caso de teste** — Deletar test case que expõe bug
6. **Mockar a realidade** — Substituir integração real por mock que retorna valor fixo para evitar falha
7. **Catch genérico** — Adicionar try/catch que engole exceção para teste não falhar
8. **Alterar dados de teste** — Mudar input do teste para evitar cenário que falha
9. **Reduzir escopo** — Remover edge case ou error path que falha
10. **assertTrue(true)** — Teste que não verifica nada real

### Ação PERMITIDA (NÃO é mascaramento)
- Alterar o teste quando o TESTE está genuinamente errado (assertion incorreta, setup errado, expectativa baseada em lógica que mudou legitimamente). DEVE justificar no commit message.

---

## Cenários MÍNIMOS Obrigatórios por Controller

Todo controller novo ou alterado DEVE ter testes cobrindo no MÍNIMO estes 5 cenários:

### 1. Sucesso CRUD (Happy Path)
```php
it('can list resources', function () { /* index → 200 + assertJsonCount */ });
it('can create a resource', function () { /* store → 201 + assertDatabaseHas */ });
it('can show a resource', function () { /* show → 200 + assertJsonPath */ });
it('can update a resource', function () { /* update → 200 + assertDatabaseHas */ });
it('can delete a resource', function () { /* destroy → 200/204 + assertSoftDeleted */ });
```

### 2. Validação (422 — Error Path)
```php
it('fails validation when required fields are missing', function () {
    $response = $this->postJson("/api/v1/{resource}", []);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['field1', 'field2']);
});

it('fails validation with invalid data', function () {
    $response = $this->postJson("/api/v1/{resource}", ['email' => 'not-an-email']);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
```

### 3. Isolamento Cross-Tenant (404 — Segurança)
```php
it('cannot access resources from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherResource = Resource::factory()->create(['tenant_id' => $otherTenant->id]);

    // O BelongsToTenant global scope deve retornar 404, NÃO 403
    $this->getJson("/api/v1/{resource}/{$otherResource->id}")
        ->assertNotFound();
});

it('cannot list resources from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    Resource::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);
    Resource::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/v1/{resource}");
    $response->assertOk();
    // Deve retornar SOMENTE os 2 do tenant do usuário, NÃO os 3 do outro tenant
    expect($response->json('data'))->toHaveCount(2);
});
```

### 4. Permissão/Autorização (403 — quando aplicável)
```php
it('denies access without proper permission', function () {
    // Remover a permissão bypass do Gate
    Gate::before(fn () => null); // Remove o bypass

    $this->getJson("/api/v1/{resource}")
        ->assertForbidden();
});
```

### 5. Edge Cases
```php
it('returns paginated results', function () {
    Resource::factory()->count(20)->create(['tenant_id' => $this->tenant->id]);
    $response = $this->getJson("/api/v1/{resource}");
    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']); // ou 'current_page', 'per_page'
});

it('returns resource with eager loaded relationships', function () {
    $resource = Resource::factory()->create(['tenant_id' => $this->tenant->id]);
    $response = $this->getJson("/api/v1/{resource}/{$resource->id}");
    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'relationship_field']]);
});
```

> **REGRA ADAPTATIVA:** O minimo de testes depende da complexidade do controller:
> - **Features com logica de negocio:** 8+ testes (todos os cenarios acima)
> - **CRUDs simples (sem logica customizada):** 4-5 testes (sucesso CRUD + validacao 422 + cross-tenant)
> - **Bug fixes:** teste de regressao + testes afetados
> - Na DUVIDA, usar 8+ testes. Menos de 4 testes por controller e SEMPRE insuficiente.

---

## Validação da Estrutura de Resposta

Todo teste de controller DEVE validar a estrutura do JSON retornado:

```php
// PROIBIDO: teste que só verifica status code
it('can list resources', function () {
    $this->getJson('/api/v1/resources')->assertOk(); // ← INSUFICIENTE
});

// OBRIGATÓRIO: verificar estrutura + dados
it('can list resources', function () {
    Resource::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
    $response = $this->getJson('/api/v1/resources');
    $response->assertOk()
        ->assertJsonCount(3, 'data')  // ou assertJsonCount(3) se sem wrapper
        ->assertJsonStructure(['data' => [['id', 'name', 'created_at']]]);
});
```
