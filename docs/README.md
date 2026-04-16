# Documentacao do Kalibrium ERP

**Atualizado em:** 2026-04-10

## Hierarquia de Fontes de Verdade

1. **Codigo-fonte** (`backend/`, `frontend/`) — unico juiz definitivo. Grep/glob antes de afirmar que algo existe ou nao.
2. **[PRD-KALIBRIUM.md](PRD-KALIBRIUM.md)** — requisitos funcionais (RFs), ACs, gaps conhecidos (v3.2+, sincronizado contra codigo em 2026-04-10)
3. **[TECHNICAL-DECISIONS.md](TECHNICAL-DECISIONS.md)** — decisoes arquiteturais duraveis
4. **[audits/RELATORIO-AUDITORIA-SISTEMA.md](audits/RELATORIO-AUDITORIA-SISTEMA.md)** — Deep Audit 10/04 (OS, Calibracao, Financeiro)

> ⚠️ `docs/raio-x-sistema.md` foi **REMOVIDO em 2026-04-10** — gerava falsos negativos (marcava codigo existente como gap). Nao recriar sem verificacao linha-a-linha contra source. Ver `docs/PRD-KALIBRIUM-CHANGELOG.md` entrada v3.2.

---

## Estrutura da Documentacao

```
docs/
├── PRD-KALIBRIUM.md            # FONTE DE VERDADE FUNCIONAL — RFs, gaps, ACs
├── PRD-KALIBRIUM-CHANGELOG.md  # Historico de versoes do PRD
├── TECHNICAL-DECISIONS.md      # Decisoes arquiteturais duraveis
├── BLUEPRINT-AIDD.md           # Metodologia de desenvolvimento
├── architecture/               # Decisoes arquiteturais (validas)
├── audits/                     # Deep Audits (RELATORIO-AUDITORIA-SISTEMA.md)
├── auditoria/                  # Auditorias historicas por camada (referencia)
├── compliance/                 # ISO 17025, ISO 9001, Portaria 671
├── design-system/              # Tokens visuais e componentes
├── operacional/                # Guias de operacao e troubleshooting
├── plans/                      # Planos ativos (seguranca, ISO 17025, etc)
├── superpowers/                # (vazio - planos migrados para .archive)
└── .archive/                   # DOCUMENTACAO ANTIGA — NAO USAR
```

---

## Regras para Agentes IA

1. **Codigo vence sempre** — antes de afirmar que algo e gap, faca `grep`/`glob` no source. O PRD pode estar desatualizado.
2. **COMECE pelo `PRD-KALIBRIUM.md`** para contexto funcional (RFs, ACs, gaps conhecidos)
3. **`TECHNICAL-DECISIONS.md`** para decisoes arquiteturais duraveis
4. **`audits/RELATORIO-AUDITORIA-SISTEMA.md`** para bloqueadores reais de go-live
5. **`architecture/`** — decisoes arquiteturais validas, com [AI_RULE] markers
6. **`compliance/`** — regras regulatorias inviolaveis (ISO, Portaria 671)
7. **`operacional/`** — guias praticos para deploy e troubleshooting
8. **NUNCA leia `.archive/`** — contem documentacao superada que causa confusao

## O que esta em `.archive/` (NAO usar)

> **Nota (2026-04-10):** o item `PRD-KALIBRIUM.md` abaixo refere-se ao PRD aspiracional ANTIGO arquivado. O PRD atual em `docs/PRD-KALIBRIUM.md` (v3.2+) e a fonte de verdade funcional e NAO esta arquivado.

| Pasta | Motivo do Arquivamento |
|-------|----------------------|
| `PRD-KALIBRIUM.md` (antigo) | PRD aspiracional de 121KB, nao refletia estado real. Substituido pelo PRD novo em `docs/PRD-KALIBRIUM.md` (v3.2+) |
| `README-ORIGINAL.md` | README antigo com ordem de leitura desatualizada |
| `modules/` | 38 specs de modulos — aspiracionais, podem nao refletir codigo |
| `fluxos/` | 32 fluxos — mistura de draft e implementado, sem marcacao clara |
| `superpowers-plans/` | 9 planos de implementacao datados de marco/2026 |
| `superpowers-specs/` | 3 specs de design desatualizadas |
| `auditoria-snapshots/` | Baseline e inventarios datados (2026-03-26) |
| `api/` | OpenAPI spec sem data, provavelmente desatualizado |
| `reports/` | Relatorios de auditoria de fevereiro/2026 |
| `screenshots/` | Screenshots antigos da interface |
| `prompts/` | Templates de prompt para IA (superados) |
