# Runbook: gate hierárquico de deploy

Este runbook documenta a validação obrigatória antes de qualquer deploy em produção.

## Gate obrigatório

O workflow `.github/workflows/deploy.yml` só pode executar o job `Deploy (Zero-Downtime)` quando os três workflows abaixo estiverem concluídos com `success` em `main` para o mesmo commit alvo:

- `CI`
- `Security Scans`
- `Nightly Regression`

O job `Validate Deploy Gates` consulta a API do GitHub Actions e bloqueia o deploy quando qualquer workflow estiver ausente, sem execução concluída para o commit alvo ou com conclusão diferente de `success`.

## Approval manual

O job de deploy mantém:

```yaml
environment: production
```

Esse ambiente deve continuar exigindo aprovação manual no GitHub antes de executar qualquer etapa de SSH.

## Teste operacional sem deploy real

Para validar o bloqueio sem executar deploy:

1. Criar um commit de teste em branch temporária que faça o `CI` falhar.
2. Abrir PR contra `main` e confirmar que o `CI` fica `failure`.
3. Não mergear o PR.
4. Em um ambiente de homologação do repositório ou fork com secrets falsos, tentar disparar `Deploy to Production` para o commit sem os três workflows verdes.
5. Confirmar que o job `Validate Deploy Gates` falha antes de qualquer etapa `Prepare SSH Key`.
6. Confirmar que nenhuma etapa `Deploy via SSH` ou `Health Check` é iniciada.

Critério esperado no log:

```text
Deploy gate blocked:
- CI: failure for <sha>
```

Quando o workflow obrigatório estiver ausente ou sem execução concluída para o commit:

```text
Deploy gate blocked:
- Nightly Regression: no completed run found for <sha>
```

## Evidência para PR

O PR que altera o gate deve incluir:

- Link para o run em que `Validate Deploy Gates` bloqueou deploy com workflow vermelho, ausente ou cancelado.
- Link para o run em que os três workflows obrigatórios estavam `success`.
- Confirmação de que `environment: production` continuou exigindo aprovação manual.

## Restrições

- Não executar deploy real durante testes de gate.
- Não adicionar bypass por input manual.
- Não substituir o gate por conferência visual de status.
- Não usar secrets de produção em fork ou simulação.
