# KALA-P0-1: Deploy bloqueado por CI green (STOP-THE-LINE)

## Severidade
**P0 — Crítico, parar a linha**

## Problema
`.github/workflows/deploy.yml` dispara sem validar que `ci.yml`, `security.yml`, `nightly.yml` estão verdes em `main`. Pode deployar código quebrado em produção.

## Evidência
```
$ gh run list --repo roldaobatista/sistema --limit 20
...
failure  Deploy to Production  <commit>
```
Deploys falharam mas continuaram sendo disparados.

## Correção
Em `.github/workflows/deploy.yml`, adicionar:

```yaml
on:
  workflow_run:
    workflows: ["CI", "Security Scans", "Nightly Regression"]
    types: [completed]
    branches: [main]

jobs:
  validate:
    if: github.event.workflow_run.conclusion == 'success'
    ...
  deploy:
    needs: validate
    environment: production  # requer approval explícita
```

## Critério de aceite
- [ ] Deploy **não dispara** se qualquer dos 3 workflows falhou
- [ ] Approval manual de ambiente `production` obrigatório
- [ ] Teste: forçar CI red → confirmar deploy não dispara

## Dono
Auditor DevOps + Implementer
