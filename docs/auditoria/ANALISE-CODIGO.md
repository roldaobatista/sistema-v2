# Guia de Análise de Código para Agentes (AIDD)

Este documento define o pipeline de leitura obrigatório quando uma IA recebe a tarefa de inspecionar, refatorar ou construir sobre código existente.

## 1. Top-Down Context Loading

Sempre siga esta ordem para garantir carregamento determinístico do contexto:

1. **Migrations**: Revelam a verdade absoluta do banco. Se não há coluna na migration, o dado é fantasma (alucinação).
2. **Models / Relationships**: Valida as proteções de Model (Fillable) e o isolamento obrigatório `BelongsToTenant`.
3. **FormRequests**: Entender os contratos exatos limitadores de Input/Output.
4. **Controllers**: Como as Actions/Services interagem usando o Input.
5. **Types Frontend (Zod/Interfaces)**: Garantir simetria espelho no Next/React/Vite.

## 2. Ponto Cego: Traits & Scopes

Agentes comumente esquecem de buscar lógicas injetadas via Trait (ex: `CheckSlaBreach`, `HasMedia`, `Auditable`). Se um save relata erro, confirme primeiro se os eventos intrínsecos de Model (Boot / Creating) não estão conflitando antes de refatorar o Controller.

## 3. Comandos Analíticos

Utilize o ast parser `php artisan model:show ModelName` (se aplicável na versão Laravel) ou leitura crua sistemática via listagens iterativas em vez de buscas rasas, sempre cruzando arquivo com sua contraparte de testes na subpasta `tests/`.
