# Diretrizes de Prompt (CLAUDE-TEMPLATE / System Prompts)

Este diretório mantém os templates e metadados para orquestrar os agentes de IA.

## Template Base para IA (DoD e Workflow)
Sempre que um agente for iniciar uma nova tarefa de código a partir do zero, ele deve obedecer ao seguinte fluxo validando a "Definição de Pronto" (DoD):

1. **Contextualização Obrigatória**: Ler o `docs/BLUEPRINT-AIDD.md` para entender a metodologia base.
2. **Verificar a Stack Tecnológica**: Ler `docs/architecture/STACK-TECNOLOGICA.md`. O agente não tem permissão para usar dependências fora do estipulado.
3. **Validar o Banco de Dados**: Inspecionar `backend/database/migrations/` e `backend/app/Models/`. A fonte da verdade para o backend sao as migrations Laravel e os Models Eloquent.
4. **Respeitar os Contornos de Domínio**: Ler o módulo específico em `docs/modules/` e focar estritamente em implementar a máquina de estado Mermaid descrita lá.
5. **Auditoria Legal**: Revisar `docs/compliance/` antes de criar rotas destrutivas (DELETE, UPDATE crítico) para garantir conformidade legal (ex: soft deletes, trilha de auditoria).
6. **Construção de Tela**: Aplicar rigidamente as regras de `docs/design-system/`.
7. **Autovalidação**: O código só é entregue após testar compilação/tipagem, linting e testes unitários.

## Protocolo de Qualidade Obrigatório

Antes de considerar qualquer tarefa concluída, verificar o **Iron Protocol**:
- [ ] `.agent/rules/iron-protocol.md` — 5 Leis Invioláveis cumpridas?
- [ ] `.agent/rules/mandatory-completeness.md` — Fluxo ponta a ponta funciona?
- [ ] `.agent/rules/test-policy.md` — Testes profissionais criados? Nenhum teste mascarado?
- [ ] `CLAUDE.md` — Todas as regras do projeto respeitadas?

**Formato de Resposta Obrigatório:**
1. O que mudou
2. Risco (baixo/médio/alto + justificativa)
3. Como validar rápido
4. Como desfazer
