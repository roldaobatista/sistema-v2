# P0 Issues — Stop-the-line

**Todas as slices em progresso PAUSAM até os 3 P0s abaixo serem resolvidos.**

CEO: crie as 3 issues IMEDIATAMENTE no Paperclip ao ler isto. Use `paperclipai issue create` ou UI. Prefix `P0`, priority `critical`, status `todo`.

1. [`P0-1-deploy-gate.md`](./P0-1-deploy-gate.md) — deploy sem CI gate
2. [`P0-2-tenant-isolation-tests.md`](./P0-2-tenant-isolation-tests.md) — cross-tenant tests ausentes
3. [`P0-3-schema-dump-sync.md`](./P0-3-schema-dump-sync.md) — schema dump desync

## Ordem de execução
- P0-2 primeiro (risco mais alto — vazamento de dados)
- P0-1 em paralelo (bloqueia deploys perigosos)
- P0-3 por último (bloqueia detecção de regressão)

Só retomar slices da Camada 1 quando os 3 P0s estiverem `done` + CI main verde por 30min.
