#!/bin/bash
set -euo pipefail

# =============================================================================
# Kalibrium ERP - Deploy Profissional
# =============================================================================
# Usage a partir da raiz do repositorio:
#   bash deploy/deploy.sh                  # Deploy sem migrations
#   bash deploy/deploy.sh --migrate        # Deploy com migrations + seeders
#   bash deploy/deploy.sh --seed           # Seeders apenas (permissoes)
#   bash deploy/deploy.sh --rollback       # Rollback completo (codigo + banco)
#   bash deploy/deploy.sh --init-ssl       # Setup inicial SSL (Let's Encrypt)
#   bash deploy/deploy.sh --status         # Status dos containers
#   bash deploy/deploy.sh --logs           # Ultimas 100 linhas de log
#   bash deploy/deploy.sh --backup         # Backup manual do banco
# =============================================================================

# =============================================================================
# CORES E LOGS
# =============================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()   { echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn()  { echo -e "${YELLOW}[AVISO]${NC} $1"; }
error() { echo -e "${RED}[ERRO]${NC} $1" >&2; exit 1; }
info()  { echo -e "${BLUE}[INFO]${NC} $1"; }
step()  { echo -e "\n${BLUE}━━━ $1 ━━━${NC}"; }

# --- Configuração ---
BACKUP_DIR="/root/backups"
# Carrega .env raiz (DOMAIN, CERTBOT_EMAIL, DB_*, etc.) para o shell do deploy.
# Executar a partir da raiz do repo (mesmo requisito dos demais paths relativos).
if [ -f ".env" ]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

BACKUP_RETENTION_DAYS=30
HEALTH_CHECK_RETRIES=30
HEALTH_CHECK_INTERVAL=5
MYSQL_WAIT_RETRIES=45
MYSQL_WAIT_INTERVAL=3
PREVIOUS_RELEASE_TAG_FILE="${BACKUP_DIR}/.previous-release-tags"
ROLLBACK_IMAGE_SERVICES=(backend frontend queue scheduler reverb)

# Auto-detecta compose file preservando apenas arquivos versionados.
# docker-compose.prod-https.yml = HTTPS dedicado, quando certificado existe
# docker-compose.prod.yml       = stack unificada HTTP+HTTPS versionada
if [ -d "certbot/conf/live" ] && [ "$(ls -A certbot/conf/live 2>/dev/null)" ]; then
    COMPOSE_FILE="docker-compose.prod-https.yml"
    if [ ! -f "$COMPOSE_FILE" ]; then
        # Fallback para prod.yml se prod-https.yml não existir
        COMPOSE_FILE="docker-compose.prod.yml"
    fi
else
    COMPOSE_FILE="docker-compose.prod.yml"
    # Aviso se DOMAIN está definido mas SSL não está configurado
    if [ -n "${DOMAIN:-}" ] && [ "${DOMAIN:-}" != "your-domain.com" ]; then
        warn "DOMAIN=$DOMAIN definido mas sem certificados SSL!"
        warn "O frontend NÃO funcionará via HTTPS sem SSL."
        warn "Execute: DOMAIN=$DOMAIN CERTBOT_EMAIL=admin@$DOMAIN bash deploy/deploy.sh --init-ssl"
    fi
fi

DOMAIN="${DOMAIN:-}"
EMAIL="${CERTBOT_EMAIL:-admin@your-domain.com}"
DEPLOY_TAG="$(date +%Y%m%d_%H%M%S)"

read_env_value() {
    local file="$1"
    local key="$2"

    if [ ! -f "$file" ]; then
        echo ""
        return 0
    fi

    grep -E "^${key}=" "$file" 2>/dev/null | tail -1 | cut -d '=' -f2- | tr -d '\r' | sed -e "s/^[\"']//" -e "s/[\"']$//" || true
}

trim_value() {
    echo "$1" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//'
}

is_placeholder_value() {
    local normalized
    normalized=$(echo "$(trim_value "$1")" | tr '[:upper:]' '[:lower:]')

    case "$normalized" in
        ""|altere_esta_senha*|"change_me"|"changeme"|"placeholder"|"your-domain.com"|"https://your-domain.com"|"http://your-domain.com"|*"seu-dominio"*|*"seu-ip-ou-dominio"*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

is_insecure_origin() {
    local value normalized
    value=$(trim_value "$1")
    normalized=$(echo "$value" | tr '[:upper:]' '[:lower:]')

    if is_placeholder_value "$value"; then
        return 0
    fi

    case "$normalized" in
        "*"|http://localhost*|https://localhost*|http://127.0.0.1*|https://127.0.0.1*|http://0.0.0.0*|https://0.0.0.0*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

require_secret_env() {
    local file="$1"
    local key="$2"
    local label="$3"
    local value
    value=$(read_env_value "$file" "$key")

    if is_placeholder_value "$value"; then
        error "$key em $label ainda tem valor placeholder ou está vazio! Defina uma senha segura."
    fi

    echo "$value"
}

require_config_env() {
    local file="$1"
    local key="$2"
    local label="$3"
    local value
    value=$(read_env_value "$file" "$key")

    if is_placeholder_value "$value"; then
        error "$key em $label está vazio ou placeholder. Defina o valor real de produção."
    fi

    echo "$value"
}

require_origin_env() {
    local file="$1"
    local key="$2"
    local label="$3"
    local value origin
    value=$(read_env_value "$file" "$key")

    if is_placeholder_value "$value"; then
        error "$key em $label está vazio ou placeholder. Defina uma origem real de produção."
    fi

    IFS=',' read -ra origins <<< "$value"
    for origin in "${origins[@]}"; do
        origin=$(trim_value "$origin")
        if is_insecure_origin "$origin"; then
            error "$key em $label contém origem insegura para produção: '$origin'"
        fi
    done
}

compose_references_env() {
    local key="$1"
    grep -Fq "\${${key}" "$COMPOSE_FILE"
}

check_backend_redis() {
    local redis_output
    redis_output=$(docker compose -f "$COMPOSE_FILE" exec -T backend php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); try { Illuminate\Support\Facades\Redis::connection()->ping(); echo "REDIS_OK"; } catch (Throwable $e) { echo "REDIS_FAIL: " . $e->getMessage(); exit(1); }' 2>&1 || true)

    if [ -n "$redis_output" ]; then
        echo "$redis_output"
    else
        echo "REDIS_FAIL"
    fi
}

# =============================================================================
# PRE-FLIGHT: Validações antes de tudo (segurança em produção)
# =============================================================================
preflight() {
    step "ETAPA 1/6: Verificações pré-deploy"

    command -v docker >/dev/null 2>&1 || error "Docker não instalado"
    docker compose version >/dev/null 2>&1 || error "Docker Compose não instalado"

    [ -f "$COMPOSE_FILE" ] || error "$COMPOSE_FILE não encontrado"
    [ -f ".env" ] || error ".env raiz não encontrado. Configure as variáveis usadas pelo Docker Compose."
    [ -f "backend/.env" ] || error "backend/.env não encontrado. Copie de backend/.env.example e configure."

    # Senhas: não podem ser defaults, placeholders ou divergir entre compose e Laravel.
    local root_redis_pass backend_redis_pass root_db_pass backend_db_pass root_db_name backend_db_name root_db_user backend_db_user
    root_redis_pass=$(require_secret_env ".env" "REDIS_PASSWORD" ".env raiz")
    backend_redis_pass=$(require_secret_env "backend/.env" "REDIS_PASSWORD" "backend/.env")

    if [ "$root_redis_pass" != "$backend_redis_pass" ]; then
        error "REDIS_PASSWORD divergente entre .env raiz e backend/.env. Corrija antes do deploy."
    fi
    if compose_references_env "DB_ROOT_PASSWORD"; then
        require_secret_env ".env" "DB_ROOT_PASSWORD" ".env raiz" >/dev/null
    fi
    if compose_references_env "DB_PASSWORD"; then
        root_db_pass=$(require_secret_env ".env" "DB_PASSWORD" ".env raiz")
        backend_db_pass=$(require_secret_env "backend/.env" "DB_PASSWORD" "backend/.env")
        if [ "$root_db_pass" != "$backend_db_pass" ]; then
            error "DB_PASSWORD divergente entre .env raiz e backend/.env. Corrija antes do deploy."
        fi
        export DB_PASSWORD="$root_db_pass"
    fi
    if compose_references_env "DB_DATABASE"; then
        backend_db_name=$(require_config_env "backend/.env" "DB_DATABASE" "backend/.env")
        root_db_name=$(read_env_value ".env" "DB_DATABASE")
        if [ -n "$root_db_name" ] && [ "$root_db_name" != "$backend_db_name" ]; then
            error "DB_DATABASE divergente entre .env raiz e backend/.env. Corrija antes do deploy."
        fi
        export DB_DATABASE="${root_db_name:-$backend_db_name}"
    fi
    if compose_references_env "DB_USERNAME"; then
        backend_db_user=$(require_config_env "backend/.env" "DB_USERNAME" "backend/.env")
        root_db_user=$(read_env_value ".env" "DB_USERNAME")
        if [ -n "$root_db_user" ] && [ "$root_db_user" != "$backend_db_user" ]; then
            error "DB_USERNAME divergente entre .env raiz e backend/.env. Corrija antes do deploy."
        fi
        export DB_USERNAME="${root_db_user:-$backend_db_user}"
    fi

    # Origens de produção: bloquear vazias, placeholders e localhost.
    require_origin_env "backend/.env" "CORS_ALLOWED_ORIGINS" "backend/.env"
    require_origin_env "backend/.env" "FRONTEND_URL" "backend/.env"
    require_origin_env ".env" "FRONTEND_URL" ".env raiz"
    require_origin_env ".env" "GO2RTC_API_ORIGIN" ".env raiz"
    require_origin_env ".env" "APP_URL" ".env raiz"

    # Produção: APP_ENV deve ser production
    local app_env
    app_env=$(read_env_value "backend/.env" "APP_ENV")
    if [ -n "$app_env" ] && [ "$app_env" != "production" ]; then
        error "backend/.env deve ter APP_ENV=production em produção (atual: APP_ENV=$app_env)"
    fi

    # Produção: APP_DEBUG deve ser false
    local app_debug
    app_debug=$(read_env_value "backend/.env" "APP_DEBUG")
    app_debug="${app_debug:-true}"
    if [ "$app_debug" = "true" ]; then
        error "backend/.env deve ter APP_DEBUG=false em produção (segurança)"
    fi

    # Espaço em disco
    local disk_available
    disk_available=$(df -m / | awk 'NR==2{print $4}')
    if [ "$disk_available" -lt 500 ]; then
        error "Espaço em disco insuficiente: ${disk_available}MB disponível (mínimo: 500MB)"
    fi

    mkdir -p "$BACKUP_DIR"

    log "Verificações OK (compose: $COMPOSE_FILE, APP_ENV=$app_env, disco: ${disk_available}MB livres)"
}

# =============================================================================
# GIT PULL: Atualiza código do repositório
# =============================================================================
git_pull() {
    step "ETAPA 2/6: Atualizando código via Git"

    if [ ! -d ".git" ]; then
        warn "Repositório Git não encontrado. Pulando git pull."
        return 0
    fi

    local current_commit
    current_commit=$(git rev-parse --short HEAD)
    log "Commit atual: $current_commit"

    # Backup dos .env antes do reset (eles têm config de produção)
    log "Salvando .env de produção..."
    [ -f "backend/.env" ] && cp backend/.env /root/backend-env-backup 2>/dev/null || true
    [ -f ".env" ] && cp .env /root/root-env-backup 2>/dev/null || true
    [ -f "frontend/.env" ] && cp frontend/.env /root/frontend-env-backup 2>/dev/null || true

    if ! git fetch --prune origin; then
        error "Falha no git fetch origin. Deploy abortado para evitar uso de código desatualizado."
    fi

    local local_hash remote_hash
    local_hash=$(git rev-parse HEAD)
    remote_hash=$(git rev-parse refs/remotes/origin/main 2>/dev/null || true)

    if [ -z "$remote_hash" ]; then
        error "Não foi possível resolver refs/remotes/origin/main após git fetch."
    fi

    if [ "$local_hash" = "$remote_hash" ]; then
        log "Código já está atualizado."
        return 0
    fi

    # Servidor de deploy: código deve ser idêntico ao origin/main
    # Alterações locais no servidor são descartadas (config fica em .env, que é gitignored)
    git reset --hard origin/main || error "Falha no git reset. Verifique o repositório."

    # Restaura .env de produção (podem ter sido sobrescritos pelo reset)
    log "Restaurando .env de produção..."
    [ -f "/root/backend-env-backup" ] && cp /root/backend-env-backup backend/.env 2>/dev/null || true
    [ -f "/root/root-env-backup" ] && cp /root/root-env-backup .env 2>/dev/null || true
    [ -f "/root/frontend-env-backup" ] && cp /root/frontend-env-backup frontend/.env 2>/dev/null || true

    local new_commit
    new_commit=$(git rev-parse --short HEAD)
    log "Atualizado: $current_commit → $new_commit"
}

# =============================================================================
# BACKUP: Dump do banco MySQL antes de migrations
# =============================================================================
backup_database() {
    step "ETAPA 3/6: Backup do banco de dados"

    local db_container="kalibrium_mysql"

    if ! docker ps --format '{{.Names}}' | grep -q "^${db_container}$"; then
        warn "Container MySQL não está rodando. Pulando backup."
        return 0
    fi

    local db_name db_user db_pass
    db_name=$(grep -oP '^DB_DATABASE=\K.*' backend/.env | tail -1 | tr -d '\r' || echo "kalibrium")
    db_user=$(grep -oP '^DB_USERNAME=\K.*' backend/.env | tail -1 | tr -d '\r' || echo "kalibrium")
    db_pass=$(grep -oP '^DB_PASSWORD=\K.*' backend/.env | tail -1 | tr -d '\r' || echo "")

    local safe_tag
    safe_tag=$(echo "$DEPLOY_TAG" | tr -dc '0-9_')
    local backup_file="${BACKUP_DIR}/kalibrium_${safe_tag}.sql.gz"

    log "Fazendo backup de '${db_name}'..."

    if docker exec -e MYSQL_PWD="$db_pass" "$db_container" mysqldump \
        -u"$db_user" \
        --single-transaction --quick --lock-tables=false \
        "$db_name" 2>/dev/null | gzip > "$backup_file"; then

        local backup_size
        backup_size=$(du -h "$backup_file" | cut -f1)
        log "Backup salvo: $backup_file ($backup_size)"
        echo "$backup_file" > "${BACKUP_DIR}/.latest"
    else
        rm -f "$backup_file"
        error "Falha no backup do banco! Deploy abortado por segurança."
    fi

    # Rotação de backups antigos
    find "$BACKUP_DIR" -name "kalibrium_*.sql.gz" -mtime +$BACKUP_RETENTION_DAYS -delete 2>/dev/null
    local backup_count
    backup_count=$(find "$BACKUP_DIR" -name "kalibrium_*.sql.gz" | wc -l)
    info "Backups mantidos: $backup_count (retenção: ${BACKUP_RETENTION_DAYS} dias)"
}

# =============================================================================
# RESTORE: Restaura backup do banco
# =============================================================================
restore_database() {
    local backup_file="${1:-}"

    if [ -z "$backup_file" ] && [ -f "${BACKUP_DIR}/.latest" ]; then
        backup_file=$(cat "${BACKUP_DIR}/.latest")
    fi

    if [ -z "$backup_file" ] || [ ! -f "$backup_file" ]; then
        error "Nenhum backup encontrado para restaurar."
    fi

    local db_container="kalibrium_mysql"
    local db_name db_user db_pass
    db_name=$(grep -oP '^DB_DATABASE=\K.*' backend/.env | tail -1 | tr -d '\r' || echo "kalibrium")
    db_user=$(grep -oP '^DB_USERNAME=\K.*' backend/.env | tail -1 | tr -d '\r' || echo "kalibrium")
    db_pass=$(grep -oP '^DB_PASSWORD=\K.*' backend/.env | tail -1 | tr -d '\r' || echo "")

    warn "Restaurando banco de: $backup_file"

    if gunzip -c "$backup_file" | docker exec -i -e MYSQL_PWD="$db_pass" "$db_container" mysql -u"$db_user" "$db_name" 2>/dev/null; then
        log "Banco restaurado com sucesso!"
    else
        error "Falha ao restaurar backup. Intervenção manual necessária."
    fi
}

# =============================================================================
# WAIT FOR MYSQL: Polling inteligente
# =============================================================================
wait_for_mysql() {
    local retries=$MYSQL_WAIT_RETRIES
    local interval=$MYSQL_WAIT_INTERVAL
    local attempt=0

    log "Aguardando MySQL ficar pronto..."

    while [ $attempt -lt $retries ]; do
        attempt=$((attempt + 1))

        if docker compose -f "$COMPOSE_FILE" exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
            log "MySQL pronto! (tentativa $attempt/$retries)"
            return 0
        fi

        info "MySQL ainda iniciando... ($attempt/$retries)"
        sleep "$interval"
    done

    error "MySQL não respondeu após $((retries * interval)) segundos. Verifique os logs: docker logs kalibrium_mysql"
}

# =============================================================================
# WAIT FOR REDIS: Polling inteligente (OBRIGATÓRIO antes de config:cache)
# =============================================================================
wait_for_redis() {
    local retries=20
    local interval=2
    local attempt=0

    log "Aguardando Redis ficar pronto..."

    # Lê a senha do Redis do .env raiz (usado pelo Docker Compose)
    local redis_pass
    redis_pass=$(require_secret_env ".env" "REDIS_PASSWORD" ".env raiz")

    while [ $attempt -lt $retries ]; do
        attempt=$((attempt + 1))

        local ping_result
        ping_result=$(docker compose -f "$COMPOSE_FILE" exec -T redis redis-cli -a "$redis_pass" --no-auth-warning ping 2>/dev/null || echo "FAIL")

        if [ "$ping_result" = "PONG" ]; then
            log "Redis pronto! (tentativa $attempt/$retries)"
            return 0
        fi

        info "Redis ainda iniciando... ($attempt/$retries, response: $ping_result)"
        sleep "$interval"
    done

    error "Redis não respondeu após $((retries * interval)) segundos. Verifique: docker logs kalibrium_redis"
}

# =============================================================================
# HEALTH CHECK: Verifica se a API está respondendo
# =============================================================================
health_check() {
    step "ETAPA 6/6: Verificação de saúde"

    local retries=$HEALTH_CHECK_RETRIES
    local interval=$HEALTH_CHECK_INTERVAL
    local attempt=0

    log "Verificando se o sistema está respondendo..."

    while [ $attempt -lt $retries ]; do
        attempt=$((attempt + 1))

        # Verifica API (/up) e Frontend (/) separadamente
        local api_code frontend_code
        api_code=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/up" 2>/dev/null || echo "000")
        frontend_code=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/" 2>/dev/null || echo "000")

        if [ "$api_code" = "200" ] && [ "$frontend_code" = "200" ]; then
            log "Sistema saudável! (API: $api_code, Frontend: $frontend_code)"

            docker compose -f "$COMPOSE_FILE" ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || \
                docker compose -f "$COMPOSE_FILE" ps

            echo ""
            log "Deploy concluído com SUCESSO!"
            return 0
        fi

        info "Aguardando... (API: $api_code, Frontend: $frontend_code, $attempt/$retries)"
        sleep "$interval"
    done

    warn "Sistema não atendeu o critério mínimo após $((retries * interval)) segundos."
    warn "Critério obrigatório: API /up = 200 e frontend / = 200."
    warn "Verifique: curl http://localhost/up && curl http://localhost/"
    return 1
}

# =============================================================================
# RELEASE SNAPSHOT: preserva a release anterior antes do novo build
# =============================================================================
compose_service_repository() {
    local svc="$1"
    local container="kalibrium_${svc}"
    local img

    img=$(docker compose -f "$COMPOSE_FILE" images 2>/dev/null \
        | awk -v container="$container" '$1 == container { print $2; exit }' || true)

    if [ -z "$img" ]; then
        img=$(docker compose -f "$COMPOSE_FILE" images "$svc" --format "{{.Repository}}" 2>/dev/null | head -1 || true)
    fi

    printf '%s' "$img"
}

capture_previous_release() {
    log "Preservando snapshot da release anterior para rollback..."

    mkdir -p "$BACKUP_DIR"
    : > "$PREVIOUS_RELEASE_TAG_FILE"

    for svc in "${ROLLBACK_IMAGE_SERVICES[@]}"; do
        local img previous_tag
        img=$(compose_service_repository "$svc")
        previous_tag="previous-${svc}-${DEPLOY_TAG}"

        if [ -n "$img" ] && docker image inspect "$img" >/dev/null 2>&1; then
            if docker tag "$img" "${img}:${previous_tag}" 2>/dev/null; then
                printf '%s %s %s\n' "$svc" "$img" "$previous_tag" >> "$PREVIOUS_RELEASE_TAG_FILE"
                log "Snapshot preservado para $svc: ${img}:${previous_tag}"
            else
                warn "Não foi possível preservar snapshot de $svc para rollback."
            fi
        else
            warn "Imagem atual de $svc não encontrada; rollback de imagem pode exigir intervenção manual."
        fi
    done
}

# =============================================================================
# BUILD: Constrói imagens sem parar containers atuais
# =============================================================================
build_images() {
    step "ETAPA 4/6: Build das imagens Docker"

    capture_previous_release

    log "Construindo novas imagens (sistema continua no ar)..."

    if ! docker compose -f "$COMPOSE_FILE" build; then
        error "Build falhou! Sistema continua rodando a versão anterior. Corrija os erros e tente novamente."
    fi

    log "Build concluído com sucesso!"
}

# =============================================================================
# SWAP: Troca para novos containers
# =============================================================================
swap_containers() {
    step "ETAPA 5/6: Atualizando containers"

    log "Parando containers antigos..."
    docker compose -f "$COMPOSE_FILE" down --timeout 30 --remove-orphans

    if [ -n "$DOMAIN" ] && [ "$DOMAIN" != "your-domain.com" ] && [ -f "nginx/default.conf" ]; then
        sed -i "s|\${DOMAIN}|${DOMAIN}|g" nginx/default.conf
    fi

    log "Iniciando novos containers..."
    docker compose -f "$COMPOSE_FILE" up -d

    wait_for_mysql
    wait_for_redis

    # Nginx precisa ser reiniciado após recriar containers para reconhecer os novos IPs
    # Sem isso, o nginx aponta para o IP antigo do backend e retorna 502 Bad Gateway
    log "Reiniciando nginx para reconhecer novos containers..."
    docker compose -f "$COMPOSE_FILE" restart nginx 2>/dev/null || true
    sleep 2
}

# =============================================================================
# MIGRATIONS: Executa apenas migrate --force (NUNCA fresh/reset)
# =============================================================================
run_migrations() {
    log "Executando migrations (apenas migrate --force, nunca fresh/reset)..."

    if ! docker compose -f "$COMPOSE_FILE" exec -T backend php artisan migrate --force 2>&1; then
        warn "MIGRATIONS FALHARAM! Restaurando backup do banco..."
        restore_database
        error "Migrations falharam e o banco foi restaurado ao estado anterior. Corrija as migrations e tente novamente."
    fi

    log "Migrations executadas com sucesso!"

    log "Executando seeders de permissões..."
    docker compose -f "$COMPOSE_FILE" exec -T backend php artisan db:seed --class=PermissionsSeeder --force 2>&1 || \
        warn "Seeder de permissões falhou (pode ser ignorado se já executado antes)"
}

# =============================================================================
# POST-DEPLOY: Cache e otimizações
# =============================================================================
post_deploy() {
    # Permissões de storage (containers novos podem perder ownership)
    log "Corrigindo permissões de storage..."
    docker compose -f "$COMPOSE_FILE" exec -T backend chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    docker compose -f "$COMPOSE_FILE" exec -T backend chmod -R 775 storage bootstrap/cache 2>/dev/null || true

    # APP_KEY: verificar se existe, gerar se não
    if ! docker compose -f "$COMPOSE_FILE" exec -T backend grep -q "APP_KEY=base64:" .env 2>/dev/null; then
        warn "APP_KEY ausente! Gerando nova chave..."
        docker compose -f "$COMPOSE_FILE" exec -T backend php artisan key:generate --force
    fi

    # Docker Compose .env define a senha usada pelo container Redis.
    # backend/.env precisa ter a mesma senha para o PHP se conectar.
    local root_redis_pass backend_redis_pass
    root_redis_pass=$(require_secret_env ".env" "REDIS_PASSWORD" ".env raiz")
    backend_redis_pass=$(require_secret_env "backend/.env" "REDIS_PASSWORD" "backend/.env")

    if [ "$root_redis_pass" != "$backend_redis_pass" ]; then
        error "REDIS_PASSWORD divergente entre .env raiz e backend/.env após deploy. Corrija os arquivos de ambiente."
    fi

    # ─── Redis connectivity: esperar Redis ANTES de config:cache ─────
    wait_for_redis

    log "Otimizando caches..."
    docker compose -f "$COMPOSE_FILE" exec -T backend php artisan config:clear || warn "config:clear falhou"
    docker compose -f "$COMPOSE_FILE" exec -T backend php artisan config:cache || warn "config:cache falhou"
    docker compose -f "$COMPOSE_FILE" exec -T backend php artisan route:cache || warn "route:cache falhou"
    docker compose -f "$COMPOSE_FILE" exec -T backend php artisan view:cache 2>/dev/null || true
    docker compose -f "$COMPOSE_FILE" exec -T backend php artisan event:cache 2>/dev/null || true
    docker compose -f "$COMPOSE_FILE" exec -T backend php artisan storage:link 2>/dev/null || true

    # ─── Verificação de conectividade Redis pós-cache ────────────────
    log "Verificando conectividade Redis do backend..."
    local redis_test
    redis_test=$(check_backend_redis)

    if echo "$redis_test" | grep -q "REDIS_OK"; then
        log "✅ Backend → Redis: conectividade OK"
    else
        warn "❌ Backend → Redis: falha de conectividade!"
        warn "Detalhes: $redis_test"
        error "Backend não conseguiu autenticar no Redis. Corrija REDIS_PASSWORD antes de prosseguir."
    fi

    log "Reiniciando queue e reverb workers..."
    docker compose -f "$COMPOSE_FILE" restart queue reverb 2>/dev/null || true
    sleep 5

    # ─── Verificação final: queue deve estar Up, não Restarting ──────
    local queue_status
    queue_status=$(docker ps --filter "name=kalibrium_queue" --format '{{.Status}}' 2>/dev/null || echo "")
    if echo "$queue_status" | grep -qi "restarting"; then
        warn "❌ Queue em restart loop! Verificando logs..."
        docker compose -f "$COMPOSE_FILE" logs --tail=10 queue 2>/dev/null || true
        error "Queue não estabilizou. Verifique REDIS_PASSWORD e logs acima."
    else
        log "✅ Queue estável: $queue_status"
    fi
}

# =============================================================================
# DEPLOY: Fluxo principal
# =============================================================================
deploy() {
    local do_migrate=false

    if [ "${1:-}" = "--migrate" ]; then
        do_migrate=true
    fi

    preflight
    git_pull

    if [ "$do_migrate" = true ]; then
        backup_database
    fi

    build_images
    swap_containers

    if [ "$do_migrate" = true ]; then
        run_migrations
    fi

    post_deploy

    if ! health_check; then
        error "Health check não confirmou API e frontend saudáveis. Use: docker compose -f $COMPOSE_FILE logs backend"
    fi
}

# =============================================================================
# ROLLBACK: Rollback completo (código + banco)
# =============================================================================
rollback_full() {
    step "ROLLBACK: Revertendo deploy (compose: $COMPOSE_FILE)"

    # Restaura imagens Docker
    if [ -f "$PREVIOUS_RELEASE_TAG_FILE" ]; then
        while read -r svc img previous_tag; do
            [ -n "${svc:-}" ] || continue

            if docker image inspect "${img}:${previous_tag}" >/dev/null 2>&1; then
                docker tag "${img}:${previous_tag}" "${img}:latest" 2>/dev/null || true
                log "Restaurado $svc de ${img}:${previous_tag}"
            else
                warn "Snapshot anterior não encontrado para $svc (${img}:${previous_tag})."
            fi
        done < "$PREVIOUS_RELEASE_TAG_FILE"
    else
        warn "Arquivo de snapshot anterior ausente: $PREVIOUS_RELEASE_TAG_FILE"
        warn "Rollback de imagens não executado automaticamente para evitar selecionar release incorreta."
    fi

    # Restaura banco se houver backup
    if [ -f "${BACKUP_DIR}/.latest" ]; then
        warn "Restaurando banco do último backup..."
        restore_database
    fi

    # Reinicia containers
    docker compose -f "$COMPOSE_FILE" down --timeout 10 --remove-orphans 2>/dev/null || true
    docker compose -f "$COMPOSE_FILE" up -d

    wait_for_mysql

    post_deploy

    if health_check; then
        log "Rollback concluído com sucesso!"
    else
        error "Rollback falhou. Intervenção manual necessária. Verifique: docker compose -f $COMPOSE_FILE logs"
    fi
}

# =============================================================================
# SEED: Apenas seeders
# =============================================================================
seed_only() {
    preflight
    log "Executando seeders..."
    docker compose -f "$COMPOSE_FILE" exec -T backend php artisan db:seed --class=PermissionsSeeder --force
    log "Seeders concluídos!"
}

# =============================================================================
# BACKUP MANUAL
# =============================================================================
manual_backup() {
    preflight
    backup_database
}

# =============================================================================
# STATUS
# =============================================================================
show_status() {
    echo ""
    info "=== Status dos Containers ==="
    docker compose -f "$COMPOSE_FILE" ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || \
        docker compose -f "$COMPOSE_FILE" ps
    echo ""

    info "=== Espaço em Disco ==="
    df -h / | awk 'NR==2{printf "  Usado: %s / Total: %s (Livre: %s)\n", $3, $2, $4}'
    echo ""

    info "=== Backups ==="
    if [ -d "$BACKUP_DIR" ]; then
        local count
        count=$(find "$BACKUP_DIR" -name "kalibrium_*.sql.gz" 2>/dev/null | wc -l)
        echo "  Backups disponíveis: $count"
        find "$BACKUP_DIR" -name "kalibrium_*.sql.gz" -printf "  %t  %f (%s bytes)\n" 2>/dev/null | tail -5
    else
        echo "  Nenhum backup encontrado"
    fi
    echo ""

    info "=== Git ==="
    if [ -d ".git" ]; then
        echo "  Branch: $(git branch --show-current 2>/dev/null || echo 'N/A')"
        echo "  Commit: $(git rev-parse --short HEAD 2>/dev/null || echo 'N/A')"
        echo "  Data:   $(git log -1 --format='%ci' 2>/dev/null || echo 'N/A')"
    else
        echo "  Git não configurado"
    fi
}

# =============================================================================
# LOGS
# =============================================================================
show_logs() {
    docker compose -f "$COMPOSE_FILE" logs --tail=100 "${2:-backend}"
}

# =============================================================================
# SSL INIT
# =============================================================================
# Requer: domínio apontando para este servidor (Let's Encrypt não emite cert para IP).
# Uso: DOMAIN=gestao.empresa.com CERTBOT_EMAIL=admin@empresa.com ./deploy.sh --init-ssl
init_ssl() {
    if [ -z "$DOMAIN" ] || [ "$DOMAIN" = "your-domain.com" ]; then
        error "Defina DOMAIN (ex: gestao.empresa.com). Let's Encrypt não emite certificado para IP."
        error "Exemplo: DOMAIN=gestao.empresa.com CERTBOT_EMAIL=admin@empresa.com ./deploy.sh --init-ssl"
    fi
    if [ -z "$EMAIL" ] || [ "$EMAIL" = "admin@your-domain.com" ]; then
        error "Defina CERTBOT_EMAIL para o Let's Encrypt (ex: CERTBOT_EMAIL=admin@empresa.com)."
    fi

    log "Configurando SSL para $DOMAIN (e-mail: $EMAIL)..."

    mkdir -p certbot/conf certbot/www

    # Fase 1: nginx só em HTTP (porta 80) para o desafio ACME; certificado ainda não existe
    if [ ! -f "nginx/default-bootstrap.conf" ]; then
        error "Arquivo nginx/default-bootstrap.conf não encontrado. Execute a partir da raiz do projeto."
    fi
    cp nginx/default.conf nginx/default.conf.https
    cp nginx/default-bootstrap.conf nginx/default.conf

    # init_ssl always uses prod-https since that's the target config
    local SSL_COMPOSE="docker-compose.prod-https.yml"
    if [ ! -f "$SSL_COMPOSE" ]; then
        SSL_COMPOSE="docker-compose.prod.yml"
    fi

    log "Iniciando nginx em HTTP para validação Let's Encrypt..."
    docker compose -f "$SSL_COMPOSE" up -d nginx

    log "Solicitando certificado Let's Encrypt..."
    if ! docker compose -f "$SSL_COMPOSE" run --rm certbot \
        certbot certonly --webroot \
        --webroot-path=/var/www/certbot \
        --email "$EMAIL" \
        --agree-tos \
        --no-eff-email \
        --non-interactive \
        -d "$DOMAIN"; then
        cp nginx/default.conf.https nginx/default.conf
        error "Falha ao obter certificado. Verifique: DNS do domínio aponta para este servidor? Porta 80 acessível?"
    fi

    # Fase 2: ativar HTTPS (substituir DOMAIN no template e usar config com SSL)
    sed "s|\${DOMAIN}|$DOMAIN|g" nginx/default.conf.https > nginx/default.conf
    log "Reiniciando nginx com SSL..."
    docker compose -f "$SSL_COMPOSE" restart nginx

    log "SSL configurado para https://$DOMAIN"
}

# =============================================================================
# MAIN
# =============================================================================
echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     Kalibrium ERP - Deploy Profissional   ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════╝${NC}"
echo ""
info "Compose file: $COMPOSE_FILE"
echo ""

case "${1:-}" in
    --rollback)  preflight; rollback_full ;;
    --init-ssl)  preflight; init_ssl ;;
    --seed)      seed_only ;;
    --status)    show_status ;;
    --logs)      show_logs "$@" ;;
    --backup)    manual_backup ;;
    *)           deploy "$@" ;;
esac
