# Environment — agentes do Paperclip

Este arquivo documenta o ambiente onde os agentes rodam, pra evitar tentativas de instalar o que já existe.

## Já instalado no VPS (/usr/bin)

- **Node.js 20.20.2** (npm 10.8.2)
- **pnpm 10.33.0** (gerenciador de pacotes pra monorepos)
- **Docker 29.4.0** + **Docker Compose v5.1.3**
- **PostgreSQL embutido** (usado pelo Paperclip em porta 54329)
- **git 2.43.0** + **gh CLI** (autenticado via PAT)
- **Claude Code 2.1.114** (adapter alternativo)
- **Codex CLI 0.121.0** (adapter atual, autenticado via ChatGPT)

## Falta instalar (provavelmente)

- **PHP 8.4 + extensões** (mbstring, xml, bcmath, intl, pdo_mysql, redis) — necessário pra Laravel
- **Composer** — gerenciador PHP
- **MySQL 8** (ou usar via docker-compose)
- **Redis** (ou usar via docker-compose)

## Permissões
- Usuário `paperclip` está no grupo `docker` (pode rodar `docker`, `docker compose`).
- `sudo` requer senha (NÃO disponível pro paperclip). Se precisar instalar pacote com `apt`, pedir ao board via issue.
- Home: `/opt/paperclip`
- Workspace: `/opt/paperclip/workspace/sistema`

## Ao precisar de um binário novo
1. Verifique aqui PRIMEIRO
2. Se não listado, tente `which X` antes de `apt install X`
3. Se `apt install` falhar por falta de sudo, criar issue `ops: instalar X` e deixar no inbox do board

## Portas em uso
- 3100 — Paperclip (UI + API)
- 54329 — PostgreSQL embutido do Paperclip
- 22 — SSH

Ao subir serviços Docker do Kalibrium ERP, use portas altas não conflitantes (ex: 8080 web, 33060 MySQL, 63790 Redis).
