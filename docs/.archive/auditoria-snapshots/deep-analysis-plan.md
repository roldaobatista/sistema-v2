# Plano de Análise Profunda (Auditoria de 10 Passos)

Sempre que acionado para uma "Auditoria Base" ou se o agente inferir derretimento arquitetural (falhas crônicas de tipagem ou ausências massivas de testes), deve-se executar a auditoria em 10 passes:

## Os 10 Passes Obrigatórios

1. **Passo 1 (Arquitetura Macro)**: Verificar `AGENTS.md`, `CLAUDE.md`, e os 6 arquivos de "skill" cruciais de Inviolabilidade (End-to-End, Iron Protocol).
2. **Passo 2 (Módulos Base)**: O `docs/README` confere com o `routes/api`? Rotas flutuantes?
3. **Passo 3 (Fluxos Transversais)**: Pastas `docs/fluxos/*.md` refletem as interações entre módulos perfeitamente (ex: O fechamento de proposta de fato assina e insere Workflow `Contract`)?
4. **Passo 4 (Base de Dados)**: Migrations possuem discrepâncias `dropColumn` pendentes entre live e src?
5. **Passo 5 (Models)**: Todos os Models têm `BelongsToTenant` ou `HasTenantScope` explícito e não bypassável?
6. **Passo 6 (Relatórios/Joins)**: Queries pesadas usam subqueries do Eloquent de forma O(N)? Necessário eager load `with()`.
7. **Passo 7 (FormRequests/Payloads)**: A API aceita massa de dados suja? Regras estritas presentes?
8. **Passo 8 (State Machines)**: Estados de Entidades (Tickets, OS, Oportunidades) transitam sob restrições fortes (Enums) ou magic strings?
9. **Passo 9 (Testes vs Src)**: Cobertura. Todos os `public function` de services essenciais cruzam com assertions reais, sem mocks cegos.
10. **Passo 10 (Frontend Symetry)**: Todo estado Zod aceita rigorosamente o DTO da API (em caso de breaking changes na API sem mexer no Frontend).

> **Aviso**: O outcome esperado de uma auditoria desta não é a correção imediata em linha contínua que estoure o contexto. O Agente deve emitir um **Master Plan** (`task.md`) fatiando o trabalho tático após documentar a anomalia.
