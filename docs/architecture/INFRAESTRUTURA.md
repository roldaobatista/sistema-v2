---
type: devops_architecture
---
# Infraestrutura de Deploy

> **[AI_RULE]** Um ambiente desestabilizado afunda o ERP. A CI/CD e a Producao sao santuarios.

## 1. Padroes de Servidor e Docker `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] A Lei do Deploy Zero-Downtime**
> A IA **JAMAIS** pode instruir o usuario ou escrever um script bash que realiza `git pull` cru no diretorio de producao com composer install nativo parando o trafego HTTP (`php artisan down`).
> Todos os scripts de CD gerados devem mirar em symlinks (Envoyer style) ou imagens Docker (Blue/Green Deployment) atreladas a maquina `203.0.113.10`.

### Arquitetura de Servidor

```
┌─────────────────────────────────────────────┐
│  Servidor de Producao (203.0.113.10)     │
│                                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │  Nginx   │  │  PHP-FPM │  │  Node    │  │
│  │  (proxy) │→ │  8.4│  │  (Vite)  │  │
│  └──────────┘  └──────────┘  └──────────┘  │
│                                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │  MySQL 8 │  │  Redis 7 │  │  Reverb  │  │
│  │  (dados) │  │  (cache) │  │  (ws)    │  │
│  └──────────┘  └──────────┘  └──────────┘  │
│                                              │
│  ┌──────────┐  ┌──────────┐                 │
│  │ Horizon  │  │ Scheduler│                 │
│  │ (queues) │  │ (cron)   │                 │
│  └──────────┘  └──────────┘                 │
└─────────────────────────────────────────────┘
```

### Processo de Deploy (Envoyer-style)

```bash
# 1. Criar novo release em diretorio isolado
releases/20260324100000/

# 2. Clone e build
git clone --depth=1 . releases/20260324100000/
cd releases/20260324100000/backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Frontend build
cd ../frontend
npm ci && npm run build

# 4. Symlink swap (zero-downtime)
ln -sfn releases/20260324100000 current

# 5. Reload PHP-FPM (sem downtime)
sudo systemctl reload php8.4-fpm

# 6. Migrations (se houver)
cd current/backend && php artisan migrate --force
```

## 2. Bloqueio de Cache Inseguro

- Nao alterar `.env` de production sem rotear chaves KMS seguras.
- Arquivos de Queue Worker (Supervisor) e Scheduler Crons DEVEM obrigatoriamente compor o repositorio (`docker-compose.yml` ou `/docker/supervisor.conf`) impedindo "configs soltas" esquecidas dentro do servidor bash manualmente.

### Supervisor para Queue Workers

```ini
[program:kalibrium-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/current/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/kalibrium/worker.log
```

### Cron para Scheduler

```cron
* * * * * cd /var/www/current/backend && php artisan schedule:run >> /dev/null 2>&1
```

## 3. Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name app.kalibrium.com.br;

    root /var/www/current/frontend/dist;
    index index.html;

    # API proxy
    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket (Reverb)
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

## 4. Backup e Disaster Recovery

| Item | Frequencia | Retencao | Metodo |
|------|-----------|----------|--------|
| MySQL dump | Diario (03:00) | 30 dias | mysqldump + gzip → S3 |
| Redis RDB | A cada 6h | 7 dias | redis-cli bgsave → S3 |
| Storage/uploads | Diario | 90 dias | rsync → S3 |
| Codigo | Git | Permanente | GitHub |

> **[AI_RULE_CRITICAL]** NUNCA rodar `DROP DATABASE`, `TRUNCATE`, ou `DELETE` sem WHERE em producao. Toda operacao destrutiva deve ter backup verificado e aprovacao humana.
