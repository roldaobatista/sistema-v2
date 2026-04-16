# Scripts

Scripts uteis para setup, testes e ferramentas AIDD. Executar a partir da **raiz do projeto**.

## Scripts Ativos

| Script | Descricao |
|--------|-----------|
| `setup.ps1` | Configuracao do ambiente local (Windows) |
| `setup-local.ps1` | Setup local simplificado |
| `test.ps1` | Rodar testes backend (Windows) |
| `test-runner.mjs` | Test runner Node.js |
| `test-runner-command.mjs` | Comando do test runner |
| `test-runner-plan.mjs` | Plano do test runner |
| `php-runtime.mjs` | Runtime PHP para Node |
| `terminal-streams.mjs` | Streams de terminal |
| `aidd_doc_audit.py` | Auditoria de documentacao AIDD |
| `aidd_endpoint_extractor.py` | Extrator de endpoints AIDD |

## Deploy

Scripts de deploy ficam em `deploy/`: `deploy.sh`, `deploy-prod.ps1`.

## Arquivo Morto

Scripts one-time (fix, scan, audit, hardening) usados durante desenvolvimento estao em `.archive/`. Nao devem ser executados — sao apenas historico.
