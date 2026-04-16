# API Docs

Este diretório concentra o artefato exportado da documentação OpenAPI do Kalibrium ERP.

## Arquivos

- `openapi.json`: contrato OpenAPI exportado a partir do backend via Scramble.

## Como regenerar

No diretório `backend`:

```bash
composer docs:openapi
```

Se preferir via container de testes:

```bash
docker compose -f docker-compose.test.yml run --rm --entrypoint sh backend-tests -lc "mkdir -p ../docs/api && php -d memory_limit=1G artisan scramble:export --path=../docs/api/openapi.json"
```
