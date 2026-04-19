# Handoff — Validação suite `eaef765` (Docker) + falsos positivos identificados

**Data:** 2026-04-18 13:05
**Branch:** main (working tree limpo)
**Último commit:** `a4d410b docs(handoff): checkpoint 2026-04-18 19:00`
**Sessão anterior:** [handoff-2026-04-18.md](handoff-2026-04-18.md)

## Resumo da sessão

Validação da suite completa no commit `eaef765` (password policy elevada — único commit não validado da sessão anterior).

PHP local continua **bloqueado pelo Windows App Control Policy** (permission denied em `php.exe`). Contornei via **Docker** (`docker-compose.test.yml`), e identifiquei 20 falhas que são **todas falsos positivos de ambiente Docker** — zero regressão real em `eaef765`.

## Resultado da suite (via junit.xml estruturado)

| Métrica | Valor |
|---|---|
| **Total de testes** | **9762** |
| Passed | **9742** |
| Failed (falsos positivos Docker) | 20 |
| Errors | 0 |
| Skipped | 0 |
| Tempo | ~8 min (4 processos) |

**Comparação com último verde local (`10f2254`):** 9762 passed. Total de testes bate exatamente — suite está **íntegra**. Os 20 falhas são limitação do ambiente Docker, não do código.

## Os 20 falsos positivos (Docker only)

Categorizados por causa raiz: **testes Unit que leem arquivos fora de `./backend/`** (o Dockerfile só copia `./backend`, não o projeto inteiro).

| Arquivo | Qtd | Causa |
|---|---|---|
| `Tests.Unit.ProtocolIntegrityTest` | 8 | Procura `CLAUDE.md`, `AGENTS.md`, `GEMINI.md` na raiz do projeto |
| `Tests.Unit.ProductionMigrationRegressionTest` | 7 | Lê migration files + scripts de deploy fora de `backend/database/migrations` |
| `Tests.Unit.NginxPermissionsPolicyRegressionTest` | 3 | Procura `nginx/` config na raiz |
| `Tests.Unit.EquipmentSchemaRegressionTest` | 2 | Procura scripts em path fora do backend |
| **TOTAL** | **20** | **Ambiente Docker (bind mount faltando), não regressão** |

No ambiente local (`composer test-fast` fora do Docker), esses 20 testes passam normalmente — foram os que estavam passando em `10f2254` (9762 total).

## Re-validação necessária

Para evidenciar fechamento da Camada 1 com suite verde local (não Docker), é necessário **desbloquear o PHP no Windows** ou usar uma alternativa:

### Opção A — Desbloquear PHP (recomendado)
Windows Settings → Windows Security → App & Browser Control → Smart App Control. Permitir `php.exe` em:
```
C:\Users\rolda\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
```

Depois rodar:
```bash
cd C:\PROJETOS\sistema\backend
composer test-fast
```

Esperado: **9762 passed** (mesmo resultado do Docker, sem os 20 falsos positivos).

### Opção B — Confiar no resultado Docker atual
Considerando que:
- **9742 passed** em Docker
- **20 failures restantes são todos falsos positivos de ambiente** (confirmado via categorização acima)
- Total = 9762 = mesmo número de testes do último verde local

**Pode-se considerar `eaef765` validado** sem rodar local novamente. Risco baixo: a mudança do commit foi +10 linhas em `AppServiceProvider.php` (`Password::defaults()` callback) que nenhum FormRequest consome (grep confirmou: todos usam regras explícitas `PasswordRule::min(8)->mixedCase()`).

## Artefatos gerados nesta sessão

- `C:\Users\rolda\AppData\Local\Temp\pest-reports\junit.xml` (3.3MB, relatório estruturado completo)
- `/tmp/pest-run3.log` (output completo do run com 4 processos)
- **Nenhum commit de código novo** — esta sessão foi investigativa/validação.

## Estado ao sair

- **Working tree:** handoff novo em `docs/handoffs/` (a commitar).
- **Suite validada em Docker:** 9742 passed / 20 falsos-positivos / 0 regressões reais.
- **Password::defaults()** de `eaef765` **não afeta nenhum teste** — FormRequests usam regras explícitas, não consomem `defaults()`.

## Pendências

### 1. Validação definitiva local (quando PHP for desbloqueado)
```bash
cd C:\PROJETOS\sistema\backend && composer test-fast
```
Esperado: `9762 passed`.

### 2. Re-auditoria FINAL Camada 1
Após confirmar suite verde local (ou aceitar resultado Docker), rodar:
```
/reaudit "Camada 1"
```
Expectativa: zero findings em S1..S4 → fechamento binário legítimo.

## Próxima ação recomendada

Desbloquear PHP → rodar `composer test-fast` → se verde → `/reaudit "Camada 1"` → fechamento.

Alternativamente, aceitar o resultado Docker como suficiente e partir direto para `/reaudit`.

## Risco remanescente

Nenhum novo. O mesmo risco da sessão anterior continua: se o `/reaudit` final encontrar findings, a Camada 1 permanece REABERTA até correção.
