> **NOTA:** Este documento é um índice resumido. Para a auditoria detalhada e completa, consultar:
> - `docs/auditoria/AUDITORIA-GAPS-DOCUMENTACAO-2026-03-25.md` (relatório completo com 156 gaps)
> - `docs/auditoria/RELATORIO-AUDITORIA-GAPS-2026-03-25.md` (sumário executivo)
> - `docs/auditoria/GAP-ANALYSIS-ISO-17025-9001.md` (análise de gaps ISO)

# Camada 5: Infraestrutura, Docker e CI/CD

Esta documentação provê a diretriz padrão para os processos contínuos de infra e contêineres do projeto Kalibrium SaaS de acordo com os princípios AIDD.

## 1. Topologia de Contêineres

- O projeto depende de orquestração local `docker-compose.yml` e scripts automatizados de provisionamento para CI/CD.
- Serviços Padrão (Local E2E): `app`, `mysql`, `redis`, `meilisearch`.

## 2. CI/CD (GitHub Actions / GitLab CI)

Ações e Variáveis Invioláveis para E2E:

- Os runners hospedados DEVEM ser passíveis de espelhar o `docker-compose`.
- **Ambientes Isolados**: Os pipelines de Homologação rodam estritamente com `.env.testing`. Senha fixa, banco espelho, portas transientes.

## 3. Variáveis de Integração e Deploy (CI)

Todo commit à `main` detona um hook de validação:

- `php artisan config:cache`
- Regras de falha condicional: Extensões do ecossistema ausentes (`bcmath`, `pcntl`), a build aborta em nível Dockerfile.

> **[AI_RULE]**: Não contornar falhas de extensões ou pacotes com `--ignore-platform-reqs`, a integridade do contêiner é primária (Iron Protocol - Lei 6).

## 4. Auditoria Contábil de Recursos

Servidores de imagem ou artefatos consumidos pelas suítes testes devem possuir purge routines (S3 cleanup) após execuções efêmeras.
