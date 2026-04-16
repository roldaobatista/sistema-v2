# MAPA DE TESTES DO KALIBRIUM — Referência Oficial

> **Última atualização:** 2026-03-16
>
> **TOTAL VERIFICADO: ~9.827 testes em 738 arquivos**
>
> ⚠️ Se você é uma IA e encontrou menos que isso, leia a seção "Erros Comuns" abaixo.

---

## CONTAGEM VERIFICADA (PowerShell recursivo real)

| Camada | Arquivos | Métodos de teste | Como contar |
|--------|----------|-----------------|-------------|
| **Backend `test_`** | 498 | 5.502 | `grep "public function test_"` |
| **Backend Pest `it()/test()`** | (inclusos) | 1.218 | `grep "^\s*(it\|test)\("` |
| **Backend `@test`** | (inclusos) | 130 | `grep "@test"` |
| **Backend SUBTOTAL** | **498** | **~6.850** | |
| **Frontend Vitest** | 204 | 2.688 | `grep "\bit\("` em `*.test.ts` |
| **Frontend E2E Playwright** | 36 | 289 | `grep "test\("` em `*.spec.ts` |
| **TOTAL** | **738** | **~9.827** | |

---

## ONDE ESTÃO OS ARQUIVOS

### Backend (`backend/tests/`) — 498 arquivos

```
tests/
├── Arch/          →   1 arquivo   (Pest arch tests)
├── Critical/      →  19 arquivos  (TenantIsolation, RBAC, Invariants)
├── Feature/       → 358 arquivos  ← MAIOR diretório
│   ├── (root)     → 182 arquivos  ← IAs perdem estes!
│   ├── Api/       → 108 arquivos  (V1/ tem subdiretórios profundos)
│   ├── Flow400/   →  14 arquivos
│   ├── EdgeCases/ →  17 arquivos
│   ├── Rbac/      →   9 arquivos
│   ├── Integration/→  8 arquivos
│   ├── Console/   →   5 arquivos
│   ├── Security/  →   5 arquivos
│   ├── Services/  →   3 arquivos
│   ├── Financial/ →   2 arquivos
│   ├── Auth/      →   1 arquivo
│   ├── Jobs/      →   1 arquivo
│   └── Performance/→  1 arquivo
├── Performance/   →   5 arquivos
├── Smoke/         →   3 arquivos
└── Unit/          → 112 arquivos
    ├── Models/    →  44 arquivos
    ├── Services/  →  32 arquivos
    ├── (root)     →  22 arquivos
    ├── Enums/     →   4 arquivos
    ├── Policies/  →   3 arquivos
    ├── Middleware/ →   2 arquivos
    ├── Listeners/ →   2 arquivos
    ├── FormRequests/→  1 arquivo
    ├── Jobs/      →   1 arquivo
    └── Rules/     →   1 arquivo
```

### Frontend Vitest (`frontend/src/__tests__/`) — 204 arquivos

```
src/__tests__/
├── utils/         → ~50+ arquivos
├── hooks/         → ~40 arquivos
├── services/      → ~20 arquivos
├── stores/        → ~20 arquivos
├── logic/         → ~17 arquivos
├── integration/   → ~15 arquivos
├── models/        → ~15 arquivos
├── pages/         → ~10 arquivos
├── helpers/       →  ~4 arquivos
├── features/      →  ~3 arquivos
├── api/           →  ~1 arquivo
├── components/    →  ~1 arquivo
└── (root)         →  ~5 arquivos
```

### Frontend E2E (`frontend/e2e/`) — 36 arquivos

```
e2e/
├── (root)          → 19 arquivos (.spec.ts)
├── auth/           →  2
├── financial/      →  2
├── modules/        →  2
├── security/       →  2
├── core/           →  1
├── crm/            →  1
├── cross-module/   →  1
├── customers/      →  1
├── quotes/         →  1
├── settings/       →  1
├── stock/          →  1
├── tech-pwa/       →  1
└── work-orders/    →  1
```

---

## SCRIPT DE CONTAGEM AUTOMÁTICA

**Cole no terminal e execute — funciona em PowerShell:**

```powershell
# ====================================================
# CONTAGEM OFICIAL DE TESTES DO KALIBRIUM
# Execute a partir de: c:\PROJETOS\sistema
# ====================================================

Write-Host "`n===== CONTAGEM DE TESTES DO KALIBRIUM =====" -ForegroundColor Cyan

# --- BACKEND ---
$backendFiles = Get-ChildItem -Recurse -Filter "*Test.php" backend\tests\
$backendFileCount = ($backendFiles | Measure).Count
$testUnderscore = (Select-String -Path $backendFiles -Pattern "public function test_" | Measure).Count
$pestTests = (Select-String -Path $backendFiles -Pattern "^\s*(it|test)\(" | Measure).Count
$annotTests = (Select-String -Path $backendFiles -Pattern "\* @test" | Measure).Count
$backendTotal = $testUnderscore + $pestTests + $annotTests

Write-Host "`n[BACKEND] $backendFileCount arquivos" -ForegroundColor Yellow
Write-Host "  PHPUnit test_: $testUnderscore"
Write-Host "  Pest it()/test(): $pestTests"
Write-Host "  @test annotation: $annotTests"
Write-Host "  SUBTOTAL: $backendTotal" -ForegroundColor Green

# --- FRONTEND VITEST ---
$vitestFiles = Get-ChildItem -Recurse -Include "*.test.ts","*.test.tsx" frontend\src\__tests__\
$vitestFileCount = ($vitestFiles | Measure).Count
$vitestTests = (Select-String -Path $vitestFiles -Pattern "\bit\(" | Measure).Count

Write-Host "`n[FRONTEND VITEST] $vitestFileCount arquivos" -ForegroundColor Yellow
Write-Host "  it(): $vitestTests"
Write-Host "  SUBTOTAL: $vitestTests" -ForegroundColor Green

# --- E2E PLAYWRIGHT ---
$e2eFiles = Get-ChildItem -Recurse -Include "*.spec.ts" frontend\e2e\
$e2eFileCount = ($e2eFiles | Measure).Count
$e2eTests = (Select-String -Path $e2eFiles -Pattern "test\(" | Measure).Count

Write-Host "`n[E2E PLAYWRIGHT] $e2eFileCount arquivos" -ForegroundColor Yellow
Write-Host "  test(): $e2eTests"
Write-Host "  SUBTOTAL: $e2eTests" -ForegroundColor Green

# --- TOTAL ---
$totalFiles = $backendFileCount + $vitestFileCount + $e2eFileCount
$totalTests = $backendTotal + $vitestTests + $e2eTests
Write-Host "`n===== TOTAL: $totalTests testes em $totalFiles arquivos =====" -ForegroundColor Cyan
```

---

## POR QUE OUTRAS IAS ENCONTRAM MENOS

> [!CAUTION]
> **A ferramenta `fd` (usada por `find_by_name`) tem limite de 50 resultados.**
> Se a IA usa apenas essa ferramenta para contar, vê no máximo 50 + reporta o total truncado.

### Erros comuns

| Erro | Causa | Resultado errado |
|------|-------|-----------------|
| **fd limitado a 50** | Tool `find_by_name` caps at 50 | "93 arquivos" (vê total mas sem listar) |
| **Busca não-recursiva** | `ls tests/*.php` vs `ls -R tests/**/*.php` | Perde subdiretórios |
| **Ignora Feature/(root)** | 182 arquivos na raiz de Feature/ | Perde metade dos testes |
| **Ignora Pest `it()`** | Só conta `function test_` | Perde 1.218 testes |
| **Ignora `@test`** | Só conta prefixo test_ | Perde 130 testes |
| **Ignora E2E** | E2E está em `frontend/e2e/` não em `__tests__/` | Perde 289 testes |
| **Ignora Critical/** | Suite separada fora de Unit/Feature | Perde 19 arquivos |

### Configuração do PHPUnit (`backend/phpunit.xml`)

```xml
<testsuites>
  <testsuite name="Unit"><directory suffix="Test.php">./tests/Unit</directory></testsuite>
  <testsuite name="Feature"><directory suffix="Test.php">./tests/Feature</directory></testsuite>
  <testsuite name="Critical"><directory suffix="Test.php">./tests/Critical</directory></testsuite>
  <testsuite name="Smoke"><directory suffix="Test.php">./tests/Smoke</directory></testsuite>
  <testsuite name="Arch"><directory suffix="Test.php">./tests/Arch</directory></testsuite>
  <testsuite name="E2E"><directory suffix="Test.php">./tests/E2E</directory></testsuite>
</testsuites>
```

### Vitest (`frontend/vitest.config.ts`)

```ts
include: ['src/**/*.{test,spec}.{ts,tsx}']
```

### Playwright

```
testDir: './e2e'
```
