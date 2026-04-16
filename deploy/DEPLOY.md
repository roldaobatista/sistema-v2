# Deploy — Kalibrium ERP

## Infraestrutura

| Item | Valor |
|------|-------|
| **Servidor** | `203.0.113.10` (DigitalOcean, Ubuntu, 16GB RAM) |
| **Acesso SSH** | `ssh deploy@203.0.113.10` (chave Ed25519 do host local) |
| **Diretório do projeto** | `/srv/kalibrium` |
| **Container engine** | Docker Compose |
| **Compose file** | `docker-compose.prod-https.yml` (SSL ativo) |
| **Branch de produção** | `main` |
| **Repositório** | `https://github.com/roldaobatista/sistema` |
| **Banco de dados** | MySQL 8 (container `kalibrium_mysql`) |
| **Cache** | Redis (container `kalibrium_redis`) |
| **WebSocket** | Laravel Reverb (container `kalibrium_reverb`) |

## Containers

| Container | Função |
|-----------|--------|
| `kalibrium_backend` | PHP-FPM + Nginx (API Laravel) |
| `kalibrium_frontend` | Nginx servindo React/Vite build estático |
| `kalibrium_queue` | Laravel Queue Worker (jobs assíncronos) |
| `kalibrium_scheduler` | Laravel Scheduler (cron jobs) |
| `kalibrium_reverb` | WebSocket server (broadcast real-time) |
| `kalibrium_mysql` | Banco de dados MySQL |
| `kalibrium_redis` | Cache e sessões |
| `kalibrium_nginx` | Proxy reverso HTTPS (portas 80/443) |
| `kalibrium_certbot` | Renovação automática SSL Let's Encrypt |
| `kalibrium_go2rtc` | Streaming de câmeras |

## Como Fazer Deploy

### Deploy padrão (sem migrations)

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh"
```

### Deploy com migrations (quando há migrations novas)

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh --migrate"
```

### Deploy manual passo a passo (se o script falhar)

```bash
# 1. Conectar ao servidor
ssh deploy@203.0.113.10

# 2. Ir para o diretório do projeto
cd /srv/kalibrium

# 3. Atualizar código
git pull origin main

# 4. Rebuild de todos os containers
docker compose -f docker-compose.prod-https.yml build -q

# 5. Subir containers (zero downtime — recria apenas os alterados)
docker compose -f docker-compose.prod-https.yml up -d

# 6. Aguardar containers ficarem healthy
sleep 15

# 7. Rodar migrations (se houver)
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan migrate --force

# 8. Limpar cache
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan optimize:clear

# 9. Verificar status
docker compose -f docker-compose.prod-https.yml ps
```

### Deploy apenas do backend (mais rápido, quando só mudou PHP)

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && git pull origin main && docker compose -f docker-compose.prod-https.yml build backend -q && docker compose -f docker-compose.prod-https.yml up -d backend && sleep 12 && docker compose -f docker-compose.prod-https.yml exec -T backend php artisan optimize:clear"
```

### Deploy apenas do frontend (quando só mudou React/TS)

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && git pull origin main && docker compose -f docker-compose.prod-https.yml build frontend -q && docker compose -f docker-compose.prod-https.yml up -d frontend"
```

## Comandos Úteis

### Status dos containers

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh --status"
```

### Logs em tempo real

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh --logs"
```

### Logs de um container específico

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && docker compose -f docker-compose.prod-https.yml logs --tail=100 backend"
```

### Rodar artisan command

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && docker compose -f docker-compose.prod-https.yml exec -T backend php artisan <COMMAND>"
```

### Backup manual do banco

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh --backup"
```

### Rollback (código + banco)

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh --rollback"
```

### Rodar seeders de permissão

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && bash deploy/deploy.sh --seed"
```

## O que o deploy.sh Faz Automaticamente

1. **Verificações pré-deploy** — valida APP_ENV, disco, compose file
2. **Atualiza código via Git** — `git fetch && git reset --hard origin/main` (preserva .env)
3. **Backup do banco** — dump gzip em `/root/backups/` (retenção 7 dias)
4. **Build das imagens Docker** — rebuild com cache (sistema fica no ar durante build)
5. **Recria containers** — `docker compose up -d` (zero downtime)
6. **Migrations + Seeders** — se `--migrate` flag passado
7. **Health check** — verifica se containers ficaram healthy (até 30 tentativas)

## Cuidados

### Antes de fazer deploy

- Garantir que `git push origin main` foi feito com o código testado
- Se há migrations novas, usar `--migrate`
- O script faz backup automático do banco antes de qualquer deploy

### Migrations com guards

Sempre usar guards em migrations para evitar erros em re-run:

```php
// Para CREATE TABLE
if (!Schema::hasTable('nome_tabela')) {
    Schema::create('nome_tabela', function (Blueprint $table) { ... });
}

// Para ADD COLUMN
if (!Schema::hasColumn('tabela', 'coluna')) {
    Schema::table('tabela', function (Blueprint $table) {
        $table->string('coluna')->nullable();
    });
}

// Para ADD INDEX (Laravel 11+ não tem Doctrine)
$indexes = collect(DB::select("SHOW INDEX FROM `tabela`"))->pluck('Key_name')->unique()->toArray();
if (!in_array('nome_index', $indexes)) {
    $table->index(['col1', 'col2'], 'nome_index');
}
```

### Nome de tabelas

Verificar nome real da tabela no Model (`protected $table = '...'`) antes de referenciar em migrations. Exemplo: `WorkOrderStatusHistory` usa `work_order_status_history` (singular), não `work_order_status_histories`.

### .env de produção

O deploy.sh salva e restaura o `.env` automaticamente durante o `git pull`. **Nunca** commitar o `.env` de produção no repositório.

## Estrutura de Backups

```
/root/backups/
├── kalibrium_20260321_094358.sql.gz  (598 KB)
├── kalibrium_20260322_021341.sql.gz  (588 KB)
└── ... (retenção: 7 dias, ~15 backups)
```

## Branch Protection

### Regras Necessárias para `main`

| Regra | Valor |
|-------|-------|
| **Require PR reviews** | 1 approval mínimo |
| **Require status checks** | CI + Security (ver lista abaixo) |
| **No direct push** | Bloqueado para todos (incluindo admins recomendado) |
| **Require branches up to date** | Sim |

### Status Checks Obrigatórios

**CI Workflow** (`ci.yml`):
- `code-formatting` — Laravel Pint
- `static-analysis` — PHPStan Level 7
- `frontend-lint` — ESLint + TypeScript
- `backend-tests` — Pest parallel
- `frontend-build` — Vite build
- `accessibility` — axe-core
- `e2e-tests` — Playwright

**Security Workflow** (`security.yml`):
- `gitleaks` — Secret Scanning
- `semgrep` — SAST
- `dependency-review` — Dependency Review

### Script para Configurar via GitHub CLI

```bash
# Configurar branch protection para main
# Requer: gh CLI autenticado com permissões admin no repo

OWNER="roldaobatista"
REPO="sistema"

gh api repos/$OWNER/$REPO/branches/main/protection \
  --method PUT \
  --input - <<'EOF'
{
  "required_status_checks": {
    "strict": true,
    "contexts": [
      "code-formatting",
      "static-analysis",
      "frontend-lint",
      "backend-tests",
      "frontend-build",
      "accessibility",
      "e2e-tests",
      "gitleaks",
      "semgrep",
      "dependency-review"
    ]
  },
  "enforce_admins": true,
  "required_pull_request_reviews": {
    "required_approving_review_count": 1
  },
  "restrictions": null
}
EOF

echo "✅ Branch protection configurado para main"
```

### Verificar Configuração Atual

```bash
gh api repos/roldaobatista/sistema/branches/main/protection --jq '.required_status_checks.contexts[]'
```

## Troubleshooting

### Container não fica healthy

```bash
# Ver logs do container específico
docker compose -f docker-compose.prod-https.yml logs --tail=50 backend

# Reiniciar container específico
docker compose -f docker-compose.prod-https.yml restart backend
```

### Migration falha com "table already exists"

Adicionar `if (!Schema::hasTable(...))` guard na migration, commitar, push e re-deploy.

### Migration falha com "Doctrine not found"

Usar `DB::select("SHOW INDEX FROM ...")` ao invés de `getDoctrineSchemaManager()` (removido no Laravel 11).

### Redis/MySQL não conecta

```bash
# Verificar se estão rodando
docker compose -f docker-compose.prod-https.yml ps mysql redis

# Reiniciar
docker compose -f docker-compose.prod-https.yml restart mysql redis
```

### Frontend não atualiza (cache do browser)

O Vite gera hashes nos nomes dos assets automaticamente. Se mesmo assim não atualiza:

```bash
# Rebuild forçado do frontend
docker compose -f docker-compose.prod-https.yml build frontend --no-cache
docker compose -f docker-compose.prod-https.yml up -d frontend
```
