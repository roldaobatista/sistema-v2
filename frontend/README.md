# Kalibrium Frontend

Bem-vindo ao frontend do Kalibrium, um SaaS SPA desenvolvido com **React 19, TypeScript 5.9 e Vite 8**.

## Stack e Ferramentas

- **React Router v7** para roteamento
- **TailwindCSS 4 + Radix UI + shadcn/ui** para tipografia e estilos
- **Zustand** para gerenciamento de estado global
- **Axios + TanStack Query** para requisições de API e cache state
- **Vitest + Playwright** para testes automatizados

## Estrutura do Projeto (src)

A arquitetura do frontend é dividida em módulos semânticos de domínio para melhor coesão:
- `components/`: Componentes visuais isolados (UI e específicos de domínios).
- `pages/`: Componentes em nível de página roteada, correspondentes aos domínios no backend.
- `hooks/`: Funções reaproveitáveis que extraem lógica dos componentes.
- `stores/`: Estado global do Zustand.
- `types/`: Declarações de interface TypeScript e contratos Types.
- `lib/`: Clientes estáticos, utilitários, wrappers Axios.

## Scripts npm Principais

- `npm run dev`: Executa em ambiente local com HMR.
- `npm run build`: Adiciona artefatos de build de produção no `dist`.
- `npm run typecheck`: Executa lint de tipos rigorosos.
- `npm run lint`: Inspeciona o código usando ESLint.
- `npm run test`: Testes unitários com Vitest.

Para mais detalhes da arquitetura em alto nível, veja os documentos em `docs/`.
