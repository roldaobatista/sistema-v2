# KALA-P0-2: Testes de isolamento cross-tenant (S1)

## Severidade
**P0 — Crítico**

## Problema
Multi-tenancy é classificado como **S1 (vazamento cross-tenant = incidente crítico)** mas NÃO há teste automatizado que prove que tenant A não consegue ler/escrever em tenant B.

## Correção
Criar `tests/Feature/TenantIsolationTest.php` com **15+ cenários**, incluindo pelo menos:

1. **Read direto**: user A tenta GET /api/clients/<id-de-B> → 403/404
2. **Write direto**: user A tenta PUT /api/clients/<id-de-B> → 403/404
3. **Scope bypass**: user A tenta usar `withoutGlobalScopes()` via endpoint → bloqueado
4. **Cross-tenant query via relationship**: user A busca user B via join → 0 results
5. **Race condition**: 100 requests paralelos de tenants diferentes, zero vazamento
6. **Webhook inbound** com tenant_id falso → rejeitado
7. **Storage S3**: user A tenta ler `tenants/B/files/<path>` → 403
8. **Queue jobs**: job com tenant_id do A pickup por worker do B → bloqueado
9. **Console/Artisan**: `tinker` rodando em contexto A não vê dados B
10. **Raw SQL via model**: `DB::select()` sem scope → teste verifica que foi **explicitamente** permitido via policy
11. **Admin impersonation**: admin do A não pode impersonar user do B
12. **Export/Report**: relatório gerado por A não inclui dados de B
13. **API Resource leak**: resource transformer não expõe campos cross-tenant
14. **Search**: busca full-text no tenant A não retorna resultados de B
15. **Audit log**: audit entries de A não aparecem pro B

## CI
Adicionar job dedicado em `ci.yml`:
```yaml
tenant-isolation:
  name: Tenant Isolation (S1)
  needs: tests
  runs-on: ubuntu-latest
  steps:
    - run: php artisan test --filter TenantIsolationTest --parallel
```
Falha desse job **bloqueia** merge.

## Critério de aceite
- [ ] 15+ testes criados, todos RED primeiro (TDD)
- [ ] Fix da implementação faz todos VERDES
- [ ] Job dedicado em CI + branch protection required
- [ ] Red Team confirmou: tentou quebrar, não conseguiu

## Dono
Auditor Red Team (pentest) + Auditor Testes + Implementer
