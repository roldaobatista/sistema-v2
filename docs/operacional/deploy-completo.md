# Kalibrium ERP — Documentação Completa de Deploy

> **Última atualização:** 16/03/2026 | **Servidor:** Hetzner CCX23 (Ashburn) | **IP:** 203.0.113.10

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Infraestrutura](#2-infraestrutura)
3. [Pré-requisitos (Computador Local)](#3-pré-requisitos-computador-local)
4. [Deploy Rápido (dia a dia)](#4-deploy-rápido-dia-a-dia)
5. [Como Funciona o Deploy (Fluxo Completo)](#5-como-funciona-o-deploy-fluxo-completo)
6. [Setup Inicial de Servidor Novo](#6-setup-inicial-de-servidor-novo)
7. [Configuração de SSH (Computador Novo)](#7-configuração-de-ssh-computador-novo)
8. [SSL / HTTPS](#8-ssl--https)
9. [Docker — Serviços e Arquitetura](#9-docker--serviços-e-arquitetura)
10. [Variáveis de Ambiente](#10-variáveis-de-ambiente)
11. [Backups e Rollback](#11-backups-e-rollback)
12. [Segurança em Produção](#12-segurança-em-produção)
13. [Troubleshooting](#13-troubleshooting)
14. [Referência de Comandos](#14-referência-de-comandos)

---

## 1. Visão Geral

O Kalibrium ERP usa uma arquitetura **containerizada** com Docker Compose. O deploy é feito a partir do computador local (Windows) usando um script PowerShell que:

1. Verifica se o código está commitado
2. Faz push para o GitHub
3. Conecta via SSH no servidor
4. Executa o script de deploy remoto (`deploy.sh`)

```
┌─────────────┐     git push     ┌──────────┐     git pull     ┌──────────────┐
│  PC Local   │ ───────────────► │  GitHub  │ ◄─────────────── │  Servidor    │
│  (Windows)  │                  │          │                  │  (Hetzner)   │
│             │     SSH          │          │                  │              │
│  deploy-    │ ────────────────────────────────────────────►  │  deploy.sh   │
│  prod.ps1   │                                                │  Docker      │
└─────────────┘                                                └──────────────┘
```

---

## 2. Infraestrutura

### Servidor

| Item | Valor |
|------|-------|
| **Provedor** | Hetzner Cloud |
| **Plano** | CCX23 (8 vCPU, 16GB RAM, 160GB SSD) |
| **Localização** | Ashburn, Virginia (EUA) |
| **SO** | Ubuntu 22.04 LTS |
| **IP** | `203.0.113.10` |
| **IPv6** | `2a01:4ff:f0:5ef8::/64` |
| **Domínio** | `app.example.test` |
| **Diretório** | `/srv/kalibrium` |

### Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 13 + PHP 8.2-FPM |
| Frontend | React 19 + Vite 7 (SPA) |
| Banco de Dados | MySQL 8.0 (strict mode) |
| Cache/Filas | Redis 7 |
| WebSocket | Laravel Reverb |
| Proxy Reverso | Nginx Alpine |
| Streaming | go2rtc |
| SSL | Let's Encrypt + Certbot |
| Container | Docker + Docker Compose |

---

## 3. Pré-requisitos (Computador Local)

### Software necessário

- **Git** — para versionamento
- **SSH** — para conexão com o servidor (já vem no Windows 10/11)
- **PowerShell** — para executar o script de deploy

### Chave SSH

O deploy usa autenticação por chave SSH (sem senha). A chave deve estar em:

```
C:\Users\<seu-usuario>\.ssh\id_ed25519      ← chave privada
C:\Users\<seu-usuario>\.ssh\id_ed25519.pub  ← chave pública
```

**Se você NÃO tem uma chave SSH**, gere uma:

```powershell
ssh-keygen -t ed25519 -C "seu-email@exemplo.com"
```

**Se você tem a chave mas troca de computador**, veja a [Seção 7](#7-configuração-de-ssh-computador-novo).

### Verificar se tudo funciona

```powershell
# Testar conexão SSH
ssh -i $env:USERPROFILE\.ssh\id_ed25519 deploy@203.0.113.10 "echo OK"

# Se retornar "OK", está pronto para deploy!
```

---

## 4. Deploy Rápido (dia a dia)

### Deploy padrão (sem migrations)

```powershell
.\deploy-prod.ps1
```

### Deploy com migrations (faz backup automático do banco)

```powershell
.\deploy-prod.ps1 -Migrate
```

### Outros comandos

```powershell
.\deploy-prod.ps1 -Status          # Ver status dos containers
.\deploy-prod.ps1 -Logs            # Ver logs do backend (Laravel)
.\deploy-prod.ps1 -Backup          # Backup manual do banco
.\deploy-prod.ps1 -Rollback        # Reverter para versão anterior
.\deploy-prod.ps1 -Migrate -Seed   # Deploy com migrations + seeders
```

> **Tempo médio:** ~2-5 minutos (inclui build Docker, swap de containers e health check).

---

## 5. Como Funciona o Deploy (Fluxo Completo)

O `deploy-prod.ps1` (local) e o `deploy.sh` (servidor) trabalham juntos em **6 etapas**:

### Etapa 1 — Verificações pré-deploy (preflight)

O script valida:

- ✅ Docker e Docker Compose instalados
- ✅ `backend/.env` existe
- ✅ `APP_ENV=production` e `APP_DEBUG=false`
- ✅ `CORS_ALLOWED_ORIGINS` sem valores placeholder
- ✅ Senhas do banco e Redis não são default
- ✅ Espaço em disco ≥ 500MB
- ✅ Auto-detecta compose file (HTTP vs HTTPS)

### Etapa 2 — Git Pull

- Salva `.env` de produção (são ignorados pelo Git)
- Faz `git fetch origin main` + `git reset --hard origin/main`
- Restaura os `.env` salvos

### Etapa 3 — Backup do banco (se `--migrate`)

- Dump completo do MySQL com `mysqldump`
- Compactado com gzip
- Salvo em `/root/backups/kalibrium_YYYYMMDD_HHMMSS.sql.gz`
- Rotação automática: backups > 7 dias são removidos

### Etapa 4 — Build das imagens Docker

- Build de 5 imagens: `frontend`, `backend`, `queue`, `reverb`, `scheduler`
- **O sistema continua no ar** durante o build (containers antigos continuam rodando)
- Frontend: Node 22 → build Vite → Nginx para servir assets
- Backend: PHP 8.4-FPM com extensões necessárias

### Etapa 5 — Swap de containers

- Para containers antigos
- Inicia novos containers com as imagens recém-construídas
- `php artisan migrate --force` (se `--migrate`)
- **Nota dev/staging:** Após rodar migrations em desenvolvimento/staging, regenerar o schema dump para testes: `cd backend && php generate_sqlite_schema.php` (requer MySQL Docker rodando). Este passo NÃO se aplica em produção — apenas no ambiente de desenvolvimento onde os testes rodam.
- `php artisan config:cache` + `route:cache` + `view:cache`
- Verifica conectividade Redis do backend
- Reinicia queue e reverb workers

### Etapa 6 — Health Check

- Tenta acessar o backend via HTTP/HTTPS (até 30 tentativas, intervalo de 5s)
- Verifica se retorna HTTP 200
- Se falhar, exibe logs para diagnóstico

---

## 6. Setup Inicial de Servidor Novo

> Use esta seção apenas se estiver configurando um servidor **do zero**.

### 6.1. Criar servidor na Hetzner

1. Acesse [console.hetzner.com](https://console.hetzner.com)
2. Crie um servidor Ubuntu 22.04 (recomendado: CCX23 ou superior)
3. **Importante:** Adicione sua chave SSH pública durante a criação

### 6.2. Executar setup do servidor

```bash
ssh root@<IP_DO_SERVIDOR>
```

```bash
# Baixar e executar o setup
curl -sSL https://raw.githubusercontent.com/roldaobatista/sistema/main/deploy/setup-server.sh | bash
```

O script `setup-server.sh` instala e configura:

| Componente | O que faz |
|-----------|-----------|
| **Docker** | Engine + Compose para containers |
| **Git** | Para clonar/atualizar o repositório |
| **UFW** | Firewall: libera portas 22, 80, 443 |
| **fail2ban** | Protege SSH contra brute force (5 tentativas, ban 1h) |
| **unattended-upgrades** | Patches de segurança automáticos |
| **Swap 2GB** | Evita OOM killer (swappiness=10) |
| **Log rotation** | Docker logs limitados (10MB × 3 arquivos) |
| **SSH hardening** | Desabilita login por senha (somente chave) |

### 6.3. Clonar o repositório

```bash
cd /root
git clone git@github.com:roldaobatista/sistema.git
cd sistema
```

### 6.4. Configurar `.env`

```bash
# Backend
cp backend/.env.example backend/.env
nano backend/.env
```

Valores obrigatórios para produção:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.test

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=kalibrium
DB_USERNAME=kalibrium
DB_PASSWORD=<SENHA_FORTE>
DB_ROOT_PASSWORD=<SENHA_FORTE>

REDIS_HOST=redis
REDIS_PASSWORD=<SENHA_FORTE>

CORS_ALLOWED_ORIGINS=https://app.example.test
```

```bash
# Raiz do projeto
cat > .env << 'EOF'
DOMAIN=app.example.test
REVERB_APP_KEY=kalibrium-key
DB_ROOT_PASSWORD=<MESMA_SENHA>
DB_PASSWORD=<MESMA_SENHA>
DB_USERNAME=kalibrium
EOF
```

### 6.5. Primeiro deploy

```bash
./deploy/deploy.sh --migrate
```

---

## 7. Configuração de SSH (Computador Novo)

Quando você troca de computador, o novo PC tem uma chave SSH diferente. O servidor só aceita chaves que estão no arquivo `/root/.ssh/authorized_keys`.

### Método 1: Se você tem a senha root (mais simples)

```powershell
# Ler sua chave pública
type $env:USERPROFILE\.ssh\id_ed25519.pub

# Copiar para o servidor (será pedida a senha)
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh deploy@203.0.113.10 "cat >> ~/.ssh/authorized_keys"
```

### Método 2: Rescue Mode da Hetzner (se NÃO tem a senha)

Este é o método profissional para recuperar acesso SSH:

1. **Acesse** [console.hetzner.com](https://console.hetzner.com) → seu servidor → aba **Resgatar** (Rescue)
2. **Adicione sua chave SSH** no painel Hetzner → Segurança → Chaves SSH → "Adicionar chave SSH" (cole o conteúdo de `id_ed25519.pub`)
3. Na aba Rescue, selecione sua chave SSH e clique **"Ativar o ciclo de resgate e energia"**
4. O servidor reinicia no Rescue Mode e exibe uma **senha temporária**
5. Conecte com a senha temporária:

   ```powershell
   # Limpar fingerprint antigo
   ssh-keygen -R 203.0.113.10

   # Conectar (usar senha temporária quando pedida)
   ssh deploy@203.0.113.10
   ```

6. Monte o disco e adicione sua chave:

   ```bash
   mount /dev/sda1 /mnt
   mkdir -p /mnt/root/.ssh
   echo "SUA_CHAVE_PUBLICA_AQUI" >> /mnt/root/.ssh/authorized_keys
   chmod 600 /mnt/root/.ssh/authorized_keys
   chmod 700 /mnt/root/.ssh
   umount /mnt
   ```

7. **Desative o Rescue Mode** no painel Hetzner e reinicie o servidor (aba **Poder** → Power Cycle)
8. Teste:

   ```powershell
   ssh-keygen -R 203.0.113.10
   ssh -i $env:USERPROFILE\.ssh\id_ed25519 deploy@203.0.113.10 "echo OK"
   ```

### Método 3: Copiar chave do computador antigo

Se ainda tem acesso ao computador antigo, copie os arquivos:

```
C:\Users\<usuario>\.ssh\id_ed25519       → mesmo caminho no PC novo
C:\Users\<usuario>\.ssh\id_ed25519.pub   → mesmo caminho no PC novo
```

---

## 8. SSL / HTTPS

### Pré-requisitos

1. Domínio registrado (ex: `app.example.test`)
2. DNS tipo A apontando para `203.0.113.10`
3. Porta 80 acessível (para validação do Let's Encrypt)

### Ativar SSL

```bash
ssh deploy@203.0.113.10
cd /srv/kalibrium
DOMAIN=app.example.test CERTBOT_EMAIL=admin@example.test ./deploy/deploy.sh --init-ssl
```

### Renovação

O certificado é renovado automaticamente pelo container Certbot (a cada 12h).

Renovação manual (se necessário):

```bash
docker compose -f docker-compose.prod-https.yml run --rm certbot certbot renew
docker compose -f docker-compose.prod-https.yml restart nginx
```

### Regra HTTP vs HTTPS

| Situação | Compose File | Como saber |
|----------|-------------|-----------|
| Sem domínio/SSL | `docker-compose.prod-http.yml` | `certbot/conf/live/` vazio |
| Com SSL ativo | `docker-compose.prod-https.yml` | `certbot/conf/live/<dominio>/` existe |

> ⚠️ O `deploy.sh` auto-detecta qual usar. **NUNCA** force o compose errado manualmente.

---

## 9. Docker — Serviços e Arquitetura

### Os 9 Containers

| Container | Imagem | Porta | Função |
|-----------|--------|-------|--------|
| `kalibrium_nginx` | nginx:alpine | 80, 443 | Proxy reverso, SSL termination, rate limiting |
| `kalibrium_backend` | Custom (PHP 8.4-FPM) | 9000 | API Laravel |
| `kalibrium_queue` | Custom (PHP 8.4-FPM) | — | Processamento de filas (Laravel Queue) |
| `kalibrium_scheduler` | Custom (PHP 8.4-FPM) | — | Tarefas agendadas (Laravel Schedule) |
| `kalibrium_reverb` | Custom (PHP 8.4-FPM) | 8080 | WebSocket server (Laravel Reverb) |
| `kalibrium_frontend` | Custom (Node → Nginx) | 3000 | SPA React servido via Nginx |
| `kalibrium_mysql` | mysql:8.0 | 3306 | Banco de dados |
| `kalibrium_redis` | redis:7-alpine | 6379 | Cache e filas |
| `kalibrium_go2rtc` | alexxit/go2rtc | 1984, 8554, 8555 | Streaming de câmeras |

### Rede

Todos os containers compartilham a rede `kalibrium` (bridge). Comunicação interna por nome do container (ex: `mysql`, `redis`).

### Volumes persistentes

```
mysql_data     → dados do MySQL (persistem entre deploys)
redis_data     → dados do Redis
certbot_conf   → certificados SSL
certbot_www    → challenge files do Let's Encrypt
```

---

## 10. Variáveis de Ambiente

### `backend/.env` (principais)

| Variável | Exemplo | Obrigatório |
|----------|---------|:-----------:|
| `APP_ENV` | `production` | ✅ |
| `APP_DEBUG` | `false` | ✅ |
| `APP_URL` | `https://app.example.test` | ✅ |
| `APP_KEY` | `base64:...` (gerado no deploy) | ✅ |
| `DB_HOST` | `mysql` | ✅ |
| `DB_DATABASE` | `kalibrium` | ✅ |
| `DB_USERNAME` | `kalibrium` | ✅ |
| `DB_PASSWORD` | `<senha_forte>` | ✅ |
| `DB_ROOT_PASSWORD` | `<senha_forte>` | ✅ |
| `REDIS_HOST` | `redis` | ✅ |
| `REDIS_PASSWORD` | `<senha_forte>` | ✅ |
| `CORS_ALLOWED_ORIGINS` | URL real (não placeholder) | ✅ |
| `LOG_LEVEL` | `warning` | Recomendado |
| `SESSION_ENCRYPT` | `true` | Recomendado |

### `.env` (raiz do projeto)

| Variável | Uso |
|----------|-----|
| `DOMAIN` | Usado pelo Docker Compose para configurar Nginx e frontend |
| `REVERB_APP_KEY` | Chave do WebSocket |
| `DB_ROOT_PASSWORD` | Usada pelo container MySQL |
| `DB_PASSWORD` | Usada pelo container MySQL |
| `DB_USERNAME` | Usada pelo container MySQL |

> ⚠️ Os `.env` **NUNCA** são commitados no Git. Eles ficam apenas no servidor.

---

## 11. Backups e Rollback

### Backups automáticos

- Feitos automaticamente **antes** de cada migration
- Local: `/root/backups/kalibrium_YYYYMMDD_HHMMSS.sql.gz`
- Retenção: 7 dias
- Rotação automática

### Backup manual

```powershell
# Do PC local
.\deploy-prod.ps1 -Backup

# Ou direto no servidor
ssh deploy@203.0.113.10 "cd /srv/kalibrium && ./deploy/deploy.sh --backup"
```

### Rollback

```powershell
# Do PC local (pede confirmação)
.\deploy-prod.ps1 -Rollback

# Ou direto no servidor
ssh deploy@203.0.113.10 "cd /srv/kalibrium && ./deploy/deploy.sh --rollback"
```

O rollback reverte:

1. **Código:** `git reset --hard` para o commit anterior
2. **Banco:** restaura último backup `.sql.gz`
3. **Containers:** rebuild e restart

---

## 12. Segurança em Produção

### Checklist obrigatório

| Item | Verificação |
|------|-------------|
| `APP_ENV=production` | ✅ Validado pelo deploy |
| `APP_DEBUG=false` | ✅ Validado pelo deploy |
| Senhas fortes (DB, Redis) | ✅ Validado pelo deploy |
| CORS configurado | ✅ Validado pelo deploy |
| UFW ativo (portas 22,80,443) | ✅ Configurado pelo setup |
| fail2ban ativo (SSH) | ✅ Configurado pelo setup |
| Login SSH só por chave | ✅ Configurado pelo setup |
| Logs Docker limitados | ✅ Configurado pelo setup |
| Auto-update de segurança | ✅ unattended-upgrades |

### Headers de segurança (Nginx)

- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` restritiva
- `server_tokens off`
- HSTS (com domínio HTTPS)

### Comandos PROIBIDOS em produção

```bash
# ⛔ NUNCA executar no servidor:
php artisan migrate:fresh    # Apaga TODOS os dados
php artisan migrate:reset    # Apaga TODAS as tabelas
php artisan db:seed          # Sem --class, pode recriar dados teste
```

---

## 13. Troubleshooting

### "Permission denied (publickey,password)" ao conectar SSH

**Causa:** Sua chave SSH não está autorizada no servidor.
**Solução:** Veja [Seção 7 — Configuração de SSH](#7-configuração-de-ssh-computador-novo).

### "Faça commit antes de fazer deploy"

**Causa:** Existem alterações não commitadas no Git.
**Solução:**

```powershell
git add -A
git commit -m "chore: descrição das mudanças"
.\deploy-prod.ps1
```

### ERR_CONNECTION_REFUSED no login

**Causa:** Servidor usando compose HTTP, mas acessando via HTTPS (ou vice-versa).
**Solução:**

```bash
# No servidor
docker compose -f docker-compose.prod-http.yml down
docker compose -f docker-compose.prod-https.yml build --no-cache frontend
docker compose -f docker-compose.prod-https.yml up -d
```

### 500 Internal Server Error

**Causa:** Erro no código PHP (ex: coluna inexistente, migration faltando).
**Solução:**

```powershell
.\deploy-prod.ps1 -Logs
# Ou no servidor:
docker exec kalibrium_backend tail -100 /var/www/storage/logs/laravel.log
```

### Containers não iniciam (health check falha)

```bash
# No servidor: ver status
docker ps -a
# Ver logs do container que falhou
docker logs kalibrium_backend --tail 50
docker logs kalibrium_mysql --tail 50
```

### Certificado SSL expirado

```bash
docker compose -f docker-compose.prod-https.yml run --rm certbot certbot renew
docker compose -f docker-compose.prod-https.yml restart nginx
```

### Espaço em disco insuficiente

```bash
# Limpar imagens Docker não usadas
docker system prune -af
# Limpar logs antigos
truncate -s 0 /var/log/syslog
```

---

## 14. Referência de Comandos

### Comandos locais (PowerShell)

| Comando | Descrição |
|---------|-----------|
| `.\deploy-prod.ps1` | Deploy padrão |
| `.\deploy-prod.ps1 -Migrate` | Deploy + migrations |
| `.\deploy-prod.ps1 -Status` | Status dos containers |
| `.\deploy-prod.ps1 -Logs` | Logs do Laravel |
| `.\deploy-prod.ps1 -Backup` | Backup manual do banco |
| `.\deploy-prod.ps1 -Rollback` | Rollback emergencial |
| `.\deploy-prod.ps1 -Seed` | Apenas seeders |

### Comandos no servidor (SSH)

| Comando | Descrição |
|---------|-----------|
| `./deploy/deploy.sh` | Deploy padrão |
| `./deploy/deploy.sh --migrate` | Deploy + migrations |
| `./deploy/deploy.sh --rollback` | Rollback |
| `./deploy/deploy.sh --status` | Status |
| `./deploy/deploy.sh --logs` | Logs |
| `./deploy/deploy.sh --backup` | Backup |
| `./deploy/deploy.sh --init-ssl` | Setup inicial SSL |

### Conexão SSH

```powershell
# Conectar ao servidor
ssh -i $env:USERPROFILE\.ssh\id_ed25519 deploy@203.0.113.10

# Executar comando remoto
ssh -i $env:USERPROFILE\.ssh\id_ed25519 deploy@203.0.113.10 "cd /srv/kalibrium && docker ps"
```

---

## Arquivos Relacionados

| Arquivo | Descrição |
|---------|-----------|
| [`deploy-prod.ps1`](file:///c:/PROJETOS/sistema/deploy-prod.ps1) | Script local (PowerShell) |
| [`deploy.sh`](file:///c:/PROJETOS/sistema/deploy.sh) | Script remoto (Bash, 6 etapas) |
| [`deploy/setup-server.sh`](file:///c:/PROJETOS/sistema/deploy/setup-server.sh) | Setup inicial do servidor |
| [`deploy/DEPLOY-HETZNER.md`](file:///c:/PROJETOS/sistema/deploy/DEPLOY-HETZNER.md) | Guia rápido de deploy |
| [`deploy/SSL-SETUP.md`](file:///c:/PROJETOS/sistema/deploy/SSL-SETUP.md) | Configuração SSL |
| [`deploy/SEGURANCA-PRODUCAO.md`](file:///c:/PROJETOS/sistema/deploy/SEGURANCA-PRODUCAO.md) | Segurança em produção |
| [`docker-compose.prod-http.yml`](file:///c:/PROJETOS/sistema/docker-compose.prod-http.yml) | Compose HTTP |
| [`docker-compose.prod-https.yml`](file:///c:/PROJETOS/sistema/docker-compose.prod-https.yml) | Compose HTTPS |
| [`backend/.env.example`](file:///c:/PROJETOS/sistema/backend/.env.example) | Template de variáveis |
