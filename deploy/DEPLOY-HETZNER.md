# Deploy no Hetzner — Kalibrium ERP

Servidor: **203.0.113.10** | Domínio: **app.example.test**

---

## Regra crítica: compose versionado

> O script `deploy/deploy.sh` auto-detecta o compose correto. **SEMPRE use `bash deploy/deploy.sh` a partir da raiz do repositório.**

| Situação | Compose File | Como saber |
|---|---|---|
| Primeiro deploy ou sem certificado local | `docker-compose.prod.yml` | `certbot/conf/live/` vazio |
| **Produção com domínio (atual)** | **`docker-compose.prod-https.yml`** | `certbot/conf/live/app.example.test/` existe |

**Se usar um compose inexistente ou divergente:**

- Deploy falha antes de subir containers
- Frontend pode não conseguir chamar a API
- Login falha completamente

---

## Deploy Rápido (Recomendado)

### Do seu PC (Windows)

```powershell
# Deploy padrão (sem migrations)
.\deploy\deploy-prod.ps1

# Deploy com migrations (faz backup automático)
.\deploy\deploy-prod.ps1 -Migrate

# Ver status
.\deploy\deploy-prod.ps1 -Status
```

### Direto no servidor (SSH)

```bash
cd /srv/kalibrium
bash deploy/deploy.sh              # Deploy padrão
bash deploy/deploy.sh --migrate    # Com migrations
bash deploy/deploy.sh --status     # Status
bash deploy/deploy.sh --rollback   # Rollback emergencial
```

---

## Setup Inicial (Primeira Vez)

### 1. Enviar projeto

```powershell
scp -i "%USERPROFILE%\\.ssh\\id_ed25519" -r C:\projetos\sistema deploy@203.0.113.10:/srv/kalibrium
```

### 2. Configurar `.env`

```bash
ssh -i "%USERPROFILE%\\.ssh\\id_ed25519" deploy@203.0.113.10
cd /srv/kalibrium
cp backend/.env.example backend/.env
nano backend/.env
```

Ajustar:

- `APP_URL=https://app.example.test`
- `FRONTEND_URL=https://app.example.test`
- `CORS_ALLOWED_ORIGINS=https://app.example.test`
- `DB_ROOT_PASSWORD=` → senha forte
- `DB_PASSWORD=` → senha forte
- `REDIS_PASSWORD=` → senha forte, igual ao `.env` raiz

Criar `.env` na raiz:

```bash
cat > .env << 'EOF'
DOMAIN=app.example.test
FRONTEND_URL=https://app.example.test
GO2RTC_API_ORIGIN=https://app.example.test
REVERB_APP_KEY=kalibrium-key
DB_ROOT_PASSWORD=sua_senha
DB_PASSWORD=sua_senha
DB_USERNAME=kalibrium
REDIS_PASSWORD=sua_senha_redis
EOF
```

### 3. Configurar SSL (se primeiro deploy com domínio)

```bash
DOMAIN=app.example.test CERTBOT_EMAIL=admin@example.test bash deploy/deploy.sh --init-ssl
```

### 4. Deploy

```bash
bash deploy/deploy.sh --migrate
```

---

## Deploy Manual (EMERGÊNCIA APENAS)

> Prefira SEMPRE `bash deploy/deploy.sh` ou `.\deploy\deploy-prod.ps1`. Deploy manual é para emergências.

```bash
cd /srv/kalibrium

# VERIFICAR qual compose usar !!!
if [ -d "certbot/conf/live" ] && [ "$(ls -A certbot/conf/live 2>/dev/null)" ]; then
    COMPOSE="docker-compose.prod-https.yml"
else
    COMPOSE="docker-compose.prod.yml"
fi
echo "Usando: $COMPOSE"

# Build e deploy
docker compose -f $COMPOSE build --no-cache frontend
docker compose -f $COMPOSE down
docker compose -f $COMPOSE up -d

# Migrations (se necessário)
docker compose -f $COMPOSE exec backend php artisan migrate --force
docker compose -f $COMPOSE exec backend php artisan config:cache
```

---

## Troubleshooting

### ERR_CONNECTION_REFUSED no login

**Causa provável:** compose ou Nginx divergente do estado real dos certificados.
**Solução:**

```bash
bash deploy/deploy.sh --status
docker compose -f docker-compose.prod-https.yml build --no-cache frontend
docker compose -f docker-compose.prod-https.yml up -d
```

### 500 Internal Server Error em endpoints

**Causa:** Código referencia colunas inexistentes no banco.
**Solução:** Verificar logs: `docker exec kalibrium_backend tail -100 /var/www/storage/logs/laravel.log`

### Certificado SSL expirado

```bash
docker compose -f docker-compose.prod-https.yml run --rm certbot certbot renew
docker compose -f docker-compose.prod-https.yml restart nginx
```
