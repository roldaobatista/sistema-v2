# Kalibrium ERP — Procedimentos de Rollback

> **Servidor:** `203.0.113.10` (Hetzner CCX23, Ubuntu 22.04) | **Diretorio:** `/srv/kalibrium` | **Branch:** `main`

---

## Indice

1. [Regras Criticas](#regras-criticas)
2. [Rollback de Deploy (Codigo)](#1-rollback-de-deploy-codigo)
3. [Rollback de Migration](#2-rollback-de-migration)
4. [Rollback de Dados (Banco)](#3-rollback-de-dados-banco)
5. [Rollback de Frontend](#4-rollback-de-frontend)
6. [Checklist Pos-Rollback](#5-checklist-pos-rollback)

---

## Regras Criticas

> [AI_RULE_CRITICAL] NUNCA rodar rollback em producao sem backup previo. Sempre executar backup antes de qualquer operacao destrutiva.

> [AI_RULE_CRITICAL] Invoice NUNCA e deletada — cancelamento gera nota de credito. Rollback de dados NAO pode apagar invoices; restaurar backup parcial exige verificacao manual das tabelas financeiras.

### Comandos PROIBIDOS em producao

```bash
# NUNCA executar no servidor:
php artisan migrate:fresh    # Apaga TODOS os dados
php artisan migrate:reset    # Apaga TODAS as tabelas
DROP TABLE ...               # NUNCA dropar tabelas manualmente
DELETE FROM invoices ...     # NUNCA deletar invoices
```

---

## 1. Rollback de Deploy (Codigo)

### 1.1. Via Script (Recomendado)

O metodo mais seguro e rapido. O script `deploy.sh --rollback` reverte codigo, restaura banco e reconstroi containers.

**Do PC local (PowerShell):**

```powershell
.\deploy-prod.ps1 -Rollback
```

**Direto no servidor (SSH):**

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && ./deploy/deploy.sh --rollback"
```

O que o script faz automaticamente:

1. `git reset --hard` para o commit anterior
2. Restaura ultimo backup `.sql.gz` de `/root/backups/`
3. Rebuild e restart dos containers
4. Health check

### 1.2. Rollback Manual (se o script falhar)

Conectar ao servidor:

```bash
ssh deploy@203.0.113.10
cd /srv/kalibrium
```

**Passo 1 — Identificar o commit estavel:**

```bash
# Ver historico de commits recentes
git log --oneline -10

# Exemplo de saida:
# a1b2c3d feat: nova feature (PROBLEMA)
# e4f5g6h fix: correcao anterior (ESTAVEL)
```

**Passo 2 — Fazer backup ANTES de qualquer alteracao:**

```bash
./deploy/deploy.sh --backup
# Backup salvo em /root/backups/kalibrium_YYYYMMDD_HHMMSS.sql.gz
```

**Passo 3 — Reverter para o commit estavel:**

```bash
# Salvar .env (nao e versionado)
cp backend/.env /tmp/backend_env_bak
cp .env /tmp/root_env_bak

# Reverter codigo
git fetch origin main
git reset --hard <COMMIT_HASH_ESTAVEL>

# Restaurar .env
cp /tmp/backend_env_bak backend/.env
cp /tmp/root_env_bak .env
```

**Passo 4 — Rebuild e restart dos containers:**

```bash
docker compose -f docker-compose.prod-https.yml build -q
docker compose -f docker-compose.prod-https.yml up -d
```

**Passo 5 — Limpar cache:**

```bash
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan optimize:clear
```

**Passo 6 — Verificar health:**

```bash
docker compose -f docker-compose.prod-https.yml ps
# Todos os containers devem estar "healthy" ou "running"
```

### 1.3. Rollback para Commit Especifico (mais de 1 commit atras)

```bash
ssh deploy@203.0.113.10
cd /srv/kalibrium

# 1. Backup
./deploy/deploy.sh --backup

# 2. Identificar commit alvo
git log --oneline -20

# 3. Reverter (preservando .env)
cp backend/.env /tmp/backend_env_bak
cp .env /tmp/root_env_bak
git reset --hard <COMMIT_HASH>
cp /tmp/backend_env_bak backend/.env
cp /tmp/root_env_bak .env

# 4. Rebuild
docker compose -f docker-compose.prod-https.yml build -q
docker compose -f docker-compose.prod-https.yml up -d

# 5. Cache
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan optimize:clear

# 6. Verificar
docker compose -f docker-compose.prod-https.yml ps
```

---

## 2. Rollback de Migration

### Regras

- **SEMPRE** fazer backup do banco antes de rodar rollback de migration
- **NUNCA** dropar tabelas manualmente com `DROP TABLE`
- **NUNCA** usar `migrate:fresh` ou `migrate:reset` em producao
- Reverter **uma migration por vez** com `--step=1` para controle granular
- Testar o rollback em ambiente local antes de executar em producao

### 2.1. Backup Pre-Rollback (OBRIGATORIO)

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && ./deploy/deploy.sh --backup"
# Confirmar que o backup foi criado:
ssh deploy@203.0.113.10 "ls -la /root/backups/ | tail -3"
```

### 2.2. Rollback da Ultima Migration

```bash
ssh deploy@203.0.113.10

cd /srv/kalibrium

# Ver qual migration sera revertida
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan migrate:status | tail -5

# Reverter a ultima migration
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan migrate:rollback --step=1 --force

# Confirmar status
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan migrate:status | tail -5
```

### 2.3. Rollback de Multiplas Migrations

```bash
# Reverter as ultimas N migrations (exemplo: 3)
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan migrate:rollback --step=3 --force
```

### 2.4. Rollback de Migration Especifica (por batch)

```bash
# Ver batch numbers
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan migrate:status

# Reverter todo o batch mais recente
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan migrate:rollback --force
```

### 2.5. Quando a Migration Nao Tem `down()`

Se a migration nao implementa o metodo `down()`, o rollback automatico nao funciona. Nesse caso:

1. **NAO** tente dropar tabelas/colunas manualmente
2. Crie uma **nova migration** que reverta as alteracoes
3. Faca deploy da nova migration normalmente

```bash
# No PC local:
php artisan make:migration revert_nome_da_alteracao

# Implementar o reverso da migration original no metodo up()
# Commitar, push, e deploy com --migrate
```

---

## 3. Rollback de Dados (Banco)

### 3.1. Restauracao Completa do Banco

> [AI_RULE_CRITICAL] NUNCA rodar rollback em producao sem backup previo. Confirme que o backup de destino esta integro antes de restaurar.

**Passo 1 — Listar backups disponiveis:**

```bash
ssh deploy@203.0.113.10 "ls -lah /root/backups/"
# Exemplo:
# kalibrium_20260321_094358.sql.gz  (598 KB)
# kalibrium_20260322_021341.sql.gz  (588 KB)
```

**Passo 2 — Fazer backup do estado ATUAL (antes de restaurar):**

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && ./deploy/deploy.sh --backup"
```

**Passo 3 — Restaurar backup completo:**

```bash
ssh deploy@203.0.113.10

cd /srv/kalibrium

# Descompactar e restaurar (substitua pelo arquivo desejado)
BACKUP_FILE="/root/backups/kalibrium_20260322_021341.sql.gz"

# Obter credenciais do .env
DB_USER=$(grep DB_USERNAME backend/.env | cut -d'=' -f2)
DB_PASS=$(grep DB_PASSWORD backend/.env | head -1 | cut -d'=' -f2)
DB_NAME=$(grep DB_DATABASE backend/.env | cut -d'=' -f2)

# Restaurar via container MySQL
gunzip -c "$BACKUP_FILE" | docker compose -f docker-compose.prod-https.yml exec -T mysql mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"

# Limpar cache apos restauracao
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan optimize:clear
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan cache:clear
```

**Passo 4 — Verificar integridade:**

```bash
# Contar registros nas tabelas principais
docker compose -f docker-compose.prod-https.yml exec -T mysql mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT 'users' as tabela, COUNT(*) as total FROM users
UNION ALL SELECT 'tenants', COUNT(*) FROM tenants
UNION ALL SELECT 'invoices', COUNT(*) FROM invoices;
"
```

### 3.2. Restauracao Parcial (Tabela Especifica)

Para restaurar apenas uma tabela sem afetar o resto do banco:

**Passo 1 — Extrair tabela do backup:**

```bash
ssh deploy@203.0.113.10

BACKUP_FILE="/root/backups/kalibrium_20260322_021341.sql.gz"
TABELA="nome_da_tabela"

# Extrair apenas a tabela desejada do dump
gunzip -c "$BACKUP_FILE" | sed -n "/^-- Table structure for table \`$TABELA\`/,/^-- Table structure for table/p" > /tmp/restore_$TABELA.sql
```

**Passo 2 — Revisar o conteudo extraido:**

```bash
# Verificar que extraiu a tabela correta
head -20 /tmp/restore_$TABELA.sql
wc -l /tmp/restore_$TABELA.sql
```

**Passo 3 — Restaurar a tabela:**

```bash
DB_USER=$(grep DB_USERNAME /srv/kalibrium/backend/.env | cut -d'=' -f2)
DB_PASS=$(grep DB_PASSWORD /srv/kalibrium/backend/.env | head -1 | cut -d'=' -f2)
DB_NAME=$(grep DB_DATABASE /srv/kalibrium/backend/.env | cut -d'=' -f2)

# Restaurar tabela (o dump ja contem DROP TABLE IF EXISTS)
docker compose -f docker-compose.prod-https.yml exec -T mysql mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < /tmp/restore_$TABELA.sql

# Limpar arquivo temporario
rm /tmp/restore_$TABELA.sql
```

> **ATENCAO:** Restauracao parcial pode causar inconsistencias de foreign keys. Verifique as tabelas relacionadas apos a restauracao.

> [AI_RULE_CRITICAL] Invoice NUNCA e deletada — cancelamento gera nota de credito. Ao restaurar parcialmente, NUNCA restaure a tabela `invoices` isoladamente sem restaurar tambem `invoice_items`, `accounts_receivable` e tabelas relacionadas.

---

## 4. Rollback de Frontend

### 4.1. Rebuild com Commit Anterior

Quando apenas o frontend precisa ser revertido (bug visual, erro de build React):

```bash
ssh deploy@203.0.113.10
cd /srv/kalibrium

# 1. Backup do estado atual
./deploy/deploy.sh --backup

# 2. Salvar .env
cp backend/.env /tmp/backend_env_bak
cp .env /tmp/root_env_bak

# 3. Reverter para commit estavel
git reset --hard <COMMIT_HASH_ESTAVEL>

# 4. Restaurar .env
cp /tmp/backend_env_bak backend/.env
cp /tmp/root_env_bak .env

# 5. Rebuild APENAS do frontend
docker compose -f docker-compose.prod-https.yml build frontend -q
docker compose -f docker-compose.prod-https.yml up -d frontend

# 6. Verificar
docker compose -f docker-compose.prod-https.yml ps frontend
```

### 4.2. Rebuild Forcado (Cache de Build)

Se o frontend nao atualiza mesmo apos rollback:

```bash
ssh deploy@203.0.113.10
cd /srv/kalibrium

# Build sem cache (mais lento, mas garante limpeza total)
docker compose -f docker-compose.prod-https.yml build frontend --no-cache
docker compose -f docker-compose.prod-https.yml up -d frontend
```

### 4.3. Rollback Apenas do Frontend via Script Local

```powershell
# Do PC local — rebuild apenas frontend com codigo atual
ssh deploy@203.0.113.10 "cd /srv/kalibrium && git pull origin main && docker compose -f docker-compose.prod-https.yml build frontend -q && docker compose -f docker-compose.prod-https.yml up -d frontend"
```

> **Nota:** O Vite gera hashes nos nomes dos assets (`main.a1b2c3.js`), entao o browser automaticamente busca a versao nova. Se o usuario ainda ve a versao antiga, o problema e cache de CDN ou service worker, nao do servidor.

---

## 5. Checklist Pos-Rollback

Apos qualquer rollback, executar **todos** os itens abaixo para garantir que o sistema esta operacional:

### 5.1. Health Check dos Containers

```bash
ssh deploy@203.0.113.10 "cd /srv/kalibrium && docker compose -f docker-compose.prod-https.yml ps"
```

Todos os containers devem estar `Up` e `healthy`:

| Container | Status Esperado |
|-----------|----------------|
| `kalibrium_backend` | Up (healthy) |
| `kalibrium_frontend` | Up |
| `kalibrium_mysql` | Up (healthy) |
| `kalibrium_redis` | Up (healthy) |
| `kalibrium_queue` | Up |
| `kalibrium_scheduler` | Up |
| `kalibrium_reverb` | Up |
| `kalibrium_nginx` | Up |

### 5.2. Teste de Login

```bash
# Verificar se a API responde
curl -s -o /dev/null -w "%{http_code}" https://app.example.test/api/health
# Esperado: 200
```

Alem do curl, fazer login manual no browser em `https://app.example.test` com um usuario de teste.

### 5.3. Verificacao Multi-Tenant

Apos rollback, confirmar que o isolamento de tenant esta funcionando:

```bash
# Verificar se queries de tenant estao funcionando
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan tinker --execute="
    \$tenants = \App\Models\Tenant::count();
    \$users = \App\Models\User::count();
    echo \"Tenants: \$tenants, Users: \$users\";
"
```

Logar com usuarios de tenants diferentes e confirmar que cada um ve apenas seus dados.

### 5.4. Filas (Queue Worker)

```bash
# Verificar se o queue worker esta processando
docker compose -f docker-compose.prod-https.yml logs --tail=20 queue

# Verificar se ha jobs pendentes/falhados
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan queue:failed

# Se houver jobs falhados apos rollback, limpar:
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan queue:flush

# Reiniciar queue worker
docker compose -f docker-compose.prod-https.yml restart queue
```

### 5.5. WebSocket (Reverb)

```bash
# Verificar se o Reverb esta rodando
docker compose -f docker-compose.prod-https.yml logs --tail=10 reverb

# Reiniciar se necessario
docker compose -f docker-compose.prod-https.yml restart reverb
```

Testar no browser: abrir duas abas com o mesmo usuario e verificar se notificacoes em tempo real funcionam.

### 5.6. Cache

```bash
# Limpar todos os caches (OBRIGATORIO apos rollback)
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan optimize:clear
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan cache:clear

# Reconstruir cache de configuracao
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan config:cache
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan route:cache
docker compose -f docker-compose.prod-https.yml exec -T backend php artisan view:cache
```

### 5.7. Verificar Logs de Erro

```bash
# Ultimas linhas do log Laravel
docker compose -f docker-compose.prod-https.yml exec -T backend tail -50 /var/www/storage/logs/laravel.log

# Ou via script
ssh deploy@203.0.113.10 "cd /srv/kalibrium && ./deploy/deploy.sh --logs"
```

Procurar por erros `500`, `QueryException`, `ModelNotFoundException` ou `TokenMismatchException`.

### 5.8. Notificacao da Equipe

Apos concluir o rollback e validar todos os itens acima, notificar a equipe com:

- **O que aconteceu:** descricao breve do problema
- **O que foi feito:** tipo de rollback (codigo, migration, banco, frontend)
- **Commit revertido para:** hash do commit estavel
- **Status atual:** sistema operacional / com restricoes
- **Acao necessaria:** se alguem precisa corrigir algo antes do proximo deploy

---

### Rollback de Eventos eSocial
- **Cenário:** Evento S-XXXX transmitido com dados incorretos
- **Procedimento:**
  1. Gerar evento de retificação (mesmo tipo, com `indRetif = 2` e `nrRecibo` do original)
  2. Transmitir via `ESocialTransmissionService::rectify($originalEvent, $correctedData)`
  3. Registrar retificação em `hr_esocial_events` com `type = 'rectification'`
  4. Nota: Exclusão de evento usa `indExclusao` — apenas para eventos enviados por erro (não retificação)

### Rollback de AFD Hash Chain
- **Cenário:** Hash chain do AFD corrompida (registro intermediário adulterado/perdido)
- **Procedimento:**
  1. Identificar o ponto de corrupção: `AFDExportService::validateChain($startDate, $endDate)`
  2. Se registros originais existem no banco: regenerar AFD a partir dos registros de ponto
  3. Se registros perdidos: documentar gap com justificativa assinada digitalmente
  4. Recalcular hashes a partir do ponto de corrupção: `AFDExportService::rebuildChain($fromDate)`
  5. **ATENÇÃO:** AFDs já exportados e assinados NÃO podem ser alterados. Gerar novo AFD complementar.

---

## Resumo Rapido de Comandos

| Situacao | Comando |
|----------|---------|
| Rollback completo (codigo + banco) | `.\deploy-prod.ps1 -Rollback` |
| Backup manual | `.\deploy-prod.ps1 -Backup` |
| Rollback ultima migration | `docker compose -f docker-compose.prod-https.yml exec -T backend php artisan migrate:rollback --step=1 --force` |
| Restaurar backup do banco | `gunzip -c /root/backups/ARQUIVO.sql.gz \| docker exec -i kalibrium_mysql mysql -uUSER -pPASS DB` |
| Rebuild frontend | `docker compose -f docker-compose.prod-https.yml build frontend -q && docker compose -f docker-compose.prod-https.yml up -d frontend` |
| Limpar cache | `docker compose -f docker-compose.prod-https.yml exec -T backend php artisan optimize:clear` |
| Status containers | `.\deploy-prod.ps1 -Status` |
| Logs Laravel | `.\deploy-prod.ps1 -Logs` |
