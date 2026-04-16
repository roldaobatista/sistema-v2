> **NOTA:** Este documento é um índice resumido. Para a auditoria detalhada e completa, consultar:
> - `docs/auditoria/AUDITORIA-GAPS-DOCUMENTACAO-2026-03-25.md` (relatório completo com 156 gaps)
> - `docs/auditoria/RELATORIO-AUDITORIA-GAPS-2026-03-25.md` (sumário executivo)
> - `docs/auditoria/GAP-ANALYSIS-ISO-17025-9001.md` (análise de gaps ISO)

# Camada 3: Frontend (React 19 & TypeScript)

Esta documentação compõe as regras obrigatórias de Frontend para a arquitetura Kalibrium SaaS SPA (Vite).

## 1. Strict TypeScript

- **Any is Banished**: O uso de `any` em arquivos TypeScript é violação gravíssima de qualidade (Lei 3 do Iron Protocol).
- **Consistência de Tipagem**: Interfaces e Types devem espelhar precisamente as estruturas de Resposta da API e formulários (`Zod`).
  - Exportar interfaces `export interface Ticket {...}` a partir da API models.

## 2. Acessibilidade (A11y) Obrigatória

Todos os componentes renderizados devem passar por checklist de acessibilidade nativo.

- Botões que possuem apenas ícones **DEVEM** conter a tag `aria-label=""`.
- Formulários devem conter `<label>` vinculados com `<input id="...">`.
- Não suprimir contornos de foco interativo sob pretextos visuais.

## 3. Build Determinístico

> **[AI_RULE]**: O Frontend DEVE compilar com zero erros antes que o agente conclua qualquer alteração ponta-a-ponta (Fullstack).

Comando de build e linting de verificação (Obrigatório em gate final):

```bash
cd frontend && npm run build
```

O build falho rebaixa o rating de código. O agente DEVE retroceder e corrigir os erros até sucesso total (`✅ build successful`).
