# Auditoria Central (Hub)

Este documento é o índice primário de governança arquitetural do projeto Kalibrium SaaS. Todos os agentes (AIs) e desenvolvedores devem utilizar os links abaixo para validar a conformidade de suas entregas antes do fechamento de qualquer tarefa.

## Governança por Camadas (As 7 Camadas)

1. [Camada 1: Fundação, Autenticação e Permissões](CAMADA-1-FUNDACAO.md)
2. [Camada 2: API & Backend (Contratos e Rotas)](CAMADA-2-API-BACKEND.md)
3. [Camada 3: Frontend (React 19 & TypeScript)](CAMADA-3-FRONTEND.md)
4. [Camada 4: Módulos Operacionais e E2E Playwright](CAMADA-4-MODULOS-E2E.md)
5. [Camada 5: Infraestrutura, Docker e CI/CD](CAMADA-5-INFRA-DEPLOY.md)
6. [Camada 6: Testes e Qualidade Geral](CAMADA-6-TESTES-QUALIDADE.md)
7. [Camada 7: Produção, Security e Deploy Definitivo](CAMADA-7-PRODUCAO-DEPLOY.md)

## Guias Processuais Obligatórios

- [Análise de Código (Agentes)](ANALISE-CODIGO.md)
- [Revisão e Correções (Bugfix Protocol)](REVISAO-CORRECOES.md)
- [Auditoria de Funcionalidades e Fluxos](AUDITORIA-FUNCIONALIDADES-FLUXOS.md)
- [Plano de Análise Profunda (Auditoria de 10 passos)](deep-analysis-plan.md)

> **[AI_RULE]**: Toda validação final de tarefa (gatekeeper) deve garantir que as alterações propostas não violem as DIRETRIZES TÉCNICAS de cada uma das 7 Camadas da arquitetura.
