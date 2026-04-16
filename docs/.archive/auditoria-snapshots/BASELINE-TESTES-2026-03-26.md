# Baseline de Testes Agentes IAs
**Data Base:** 2026-03-26
**Última atualização:** 2026-03-26 (pós-regularização Fase 4)

Este documento cristaliza o snapshot (estado da arte numérico) do Kalibrium SaaS.

## 1. Módulos Frontend (React / Vite)
*   **Build de Produção:** `npm run build`
    *   **Status de TS/Estilos:** Sem Erros Críticos (0 dependências faltantes / 0 erros de check).
    *   **Status de Warning:** Alguns dead variables (típico), não conflitantes.
*   **Decisão:** O framework web componentizado está operante e `completo` na saúde core.

## 2. Módulos Backend (Laravel / Pest 3)
*   **Teste Runner:** `./vendor/bin/pest --parallel --processes=16 --no-coverage`
*   **Duração Medida:** ~164 segundos (2 minutos e 44 segundos com 16 processos).

### 2.1 Placar Executivo (Atualizado 26/03/2026 — pós Fase 4)
*   **Testes Passados:** 7869
*   **Testes Falhos:** 0
*   **Testes Skipped:** 0
*   **Total de Assertions Validadas:** 22.091
*   **Total de Testes:** 7869

### 2.2 Evolução do Baseline

| Marco | Testes | Falhos | Skipped | Assertions | Data |
|-------|:------:|:------:|:-------:|:----------:|------|
| Baseline inicial (Fase 0.5) | 7648 | 13 | 2 | 21.329 | 2026-03-26 manhã |
| Pós-regularização Contracts/Helpdesk/Procurement | 7747 | 0 | 0 | 21.746 | 2026-03-26 tarde |
| **Pós Fase 4 completa (ATUAL)** | **7869** | **0** | **0** | **22.091** | **2026-03-26 noite** |

**Delta total: +221 testes, +762 assertions, -13 falhas, -2 skips**

## 3. Falhas do Baseline Original — TODAS RESOLVIDAS
As 13 falhas originais foram corrigidas durante a regularização. Os 2 testes skipped foram removidos (rotas já existiam, skip condicional desnecessário).

## 4. Testes Skipped — ZERO
Nenhum `markTestSkipped`, `->skip()`, `->todo()`, `assertTrue(true)` ou teste vazio no codebase.

## 5. Regra de Baseline
Este número (7869) é o **piso mínimo**. Nunca pode diminuir. Toda nova implementação deve manter ou aumentar.
