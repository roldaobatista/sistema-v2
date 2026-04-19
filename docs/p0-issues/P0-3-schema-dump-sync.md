# KALA-P0-3: Validação de schema dump em CI

## Severidade
**P0 — Crítico**

## Problema
Pest usa `database/schema/*.sql` dumped pra subir SQLite in-memory rápido. Se uma migration muda `app/Models/*` ou `database/migrations/*` e o dump NÃO é regenerado, os testes rodam contra schema antigo → passam falsamente → bug vai pra main → produção quebra.

## Correção
Adicionar em `.github/workflows/ci.yml`, novo job:

```yaml
schema-drift:
  name: Schema Dump Sync Check
  runs-on: ubuntu-latest
  services:
    postgres:
      image: postgres:17
      env:
        POSTGRES_PASSWORD: secret
      ports: ['5432:5432']
  steps:
    - uses: actions/checkout@v4
    - name: Setup PHP 8.4
      uses: shivammathur/setup-php@v2
      with: { php-version: '8.4', extensions: 'pdo_pgsql' }
    - run: composer install --no-interaction --prefer-dist
    - name: Run migrations fresh
      run: php artisan migrate:fresh --force
    - name: Dump current schema
      run: php artisan schema:dump --database=pgsql --path=/tmp/schema.sql
    - name: Compare with committed dump
      run: |
        if ! diff -u database/schema/pgsql-schema.sql /tmp/schema.sql; then
          echo "::error::Schema dump out of sync. Run 'php artisan schema:dump' locally and commit."
          exit 1
        fi
```

## Critério de aceite
- [ ] Job criado e passando
- [ ] Teste: criar migration dummy → CI falha até dump atualizado
- [ ] Documentar em `docs/runbooks/SCHEMA_DUMP.md`
- [ ] Git hook pre-push sugerido (opcional)

## Dono
Auditor DevOps + Auditor Testes
