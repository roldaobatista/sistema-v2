# Troubleshooting Geral — Kalibrium ERP

Guia de resolucao de problemas comuns em ambiente de desenvolvimento e producao.

---

## 1. Redis

### 1.1 Connection Refused

**Sintoma**: `Connection refused [tcp://127.0.0.1:6379]` no Laravel.

**Diagnostico**:

```bash
# Verificar se Redis esta rodando
redis-cli ping
# Esperado: PONG

# Verificar status do servico
systemctl status redis
# Ou no Docker:
docker ps | grep redis
```

**Solucoes**:

1. Redis nao esta rodando:

   ```bash
   sudo systemctl start redis
   # Ou Docker:
   docker start kalibrium-redis
   ```

2. Redis em outro host/porta — verificar `.env`:

   ```
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   REDIS_PASSWORD=null
   ```

3. Firewall bloqueando:

   ```bash
   sudo ufw allow 6379
   ```

### 1.2 Cache Stale (dados desatualizados)

**Sintoma**: Alteracoes no banco nao refletem na API. Dados antigos persistem.

**Diagnostico**:

```bash
# Verificar TTL de uma chave
redis-cli TTL "laravel_cache:some_key"

# Listar chaves de cache
redis-cli KEYS "laravel_cache:*" | head -20
```

**Solucoes**:

1. Limpar cache do Laravel:

   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

2. Flush completo do Redis (cuidado em producao):

   ```bash
   redis-cli FLUSHDB
   ```

3. Verificar se a invalidacao on-write esta implementada nos Services. Ao salvar/atualizar, o Service deve chamar `Cache::forget('chave')`.

---

## 2. MySQL

### 2.1 Too Many Connections

**Sintoma**: `SQLSTATE[HY000] [1040] Too many connections`

**Diagnostico**:

```bash
mysql -e "SHOW STATUS LIKE 'Threads_connected';"
mysql -e "SHOW VARIABLES LIKE 'max_connections';"
mysql -e "SHOW PROCESSLIST;"
```

**Solucoes**:

1. Aumentar limite temporariamente:

   ```bash
   mysql -e "SET GLOBAL max_connections = 200;"
   ```

2. Matar conexoes idle:

   ```bash
   mysql -e "SELECT GROUP_CONCAT(id) FROM information_schema.processlist WHERE command='Sleep' AND time > 300;" | xargs -I{} mysql -e "KILL {};"
   ```

3. Verificar `.env` — pool do Laravel:

   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   ```

4. Verificar se ha connection leaks no codigo (conexoes nao fechadas em jobs de longa duracao).

### 2.2 Lock Wait Timeout

**Sintoma**: `SQLSTATE[HY000]: Lock wait timeout exceeded; try restarting transaction`

**Diagnostico**:

```bash
mysql -e "SHOW ENGINE INNODB STATUS\G" | grep -A 20 "LATEST DETECTED DEADLOCK"
mysql -e "SELECT * FROM information_schema.innodb_locks;"
mysql -e "SELECT * FROM information_schema.innodb_lock_waits;"
```

**Solucoes**:

1. Identificar a query bloqueante e otimizar (adicionar index, reduzir scope do lock).
2. Aumentar timeout temporariamente:

   ```bash
   mysql -e "SET GLOBAL innodb_lock_wait_timeout = 120;"
   ```

3. Verificar se transactions estao sendo mantidas abertas por muito tempo no codigo. Transactions devem ser curtas e objetivas.
4. Revisar se ha `DB::beginTransaction()` sem `commit()` ou `rollBack()` em algum try/catch.

### 2.3 Table Already Exists (em migrations)

**Sintoma**: `SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'xxx' already exists`

**Diagnostico**:

```bash
php artisan migrate:status | grep -i "xxx"
mysql -e "SHOW TABLES LIKE 'xxx';"
```

**Solucoes**:

1. A tabela existe mas a migration nao foi registrada:

   ```bash
   # Marcar migration como executada sem rodar
   php artisan migrate --pretend
   # Se confirmar que ja existe, inserir manualmente:
   mysql -e "INSERT INTO migrations (migration, batch) VALUES ('2024_01_01_000000_create_xxx_table', 99);"
   ```

2. Migration com `Schema::create` sem verificacao:

   ```php
   // Corrigir a migration:
   if (!Schema::hasTable('xxx')) {
       Schema::create('xxx', function (Blueprint $table) { ... });
   }
   ```

3. Reset completo em dev (NUNCA em producao):

   ```bash
   php artisan migrate:fresh --seed
   ```

---

## 3. Frontend Build

### 3.1 TypeScript Errors

**Sintoma**: `npm run build` falha com erros de tipo.

**Diagnostico**:

```bash
cd frontend && npx tsc --noEmit 2>&1 | head -50
```

**Solucoes**:

1. Verificar se as interfaces TS estao sincronizadas com o backend:

   ```bash
   # Listar tipos desatualizados
   cd frontend && npx tsc --noEmit 2>&1 | grep "error TS"
   ```

2. Erro comum — propriedade faltando na interface: atualizar a interface em `src/types/` para refletir o Resource do backend.
3. Erro de import: verificar se o path alias `@/` esta configurado no `tsconfig.json` e `vite.config.ts`.

### 3.2 Out of Memory

**Sintoma**: `FATAL ERROR: Reached heap limit Allocation failed - JavaScript heap out of memory`

**Solucoes**:

1. Aumentar memoria do Node:

   ```bash
   export NODE_OPTIONS="--max-old-space-size=4096"
   cd frontend && npm run build
   ```

2. Verificar se ha imports circulares:

   ```bash
   cd frontend && npx madge --circular src/
   ```

3. Reduzir tamanho do source map em `vite.config.ts`:

   ```ts
   build: { sourcemap: false } // ou 'hidden' para producao
   ```

### 3.3 Bundle Too Large

**Sintoma**: Bundle principal > 500KB gzipped.

**Diagnostico**:

```bash
cd frontend && npx vite-bundle-visualizer
```

**Solucoes**:

1. Code-splitting por rota com `React.lazy()`:

   ```tsx
   const WorkOrders = React.lazy(() => import('./pages/WorkOrders'));
   ```

2. Verificar dependencias pesadas:

   ```bash
   cd frontend && npx vite build 2>&1 | grep "kB"
   ```

3. Substituir libs pesadas: `moment` -> `dayjs`, `lodash` -> `lodash-es` (tree-shakeable).
4. Dynamic import para funcionalidades nao-criticas (graficos, editores de texto).

---

## 4. WebSocket / Reverb

### 4.1 Eventos nao chegam no frontend

**Sintoma**: Backend dispara evento, mas o frontend nao recebe via Echo/WebSocket.

**Diagnostico**:

```bash
# Verificar se Reverb esta rodando
php artisan reverb:start --debug

# Verificar configuracao
php artisan config:show broadcasting
```

**Solucoes**:

1. Verificar `.env`:

   ```
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=kalibrium
   REVERB_APP_KEY=your-key
   REVERB_APP_SECRET=your-secret
   REVERB_HOST=127.0.0.1
   REVERB_PORT=8080
   ```

2. Verificar que o evento implementa `ShouldBroadcast` (nao `ShouldBroadcastNow` a menos que necessario).
3. Verificar que o canal esta correto:

   ```php
   // No evento:
   public function broadcastOn() {
       return new PrivateChannel('tenant.' . $this->tenantId);
   }
   ```

4. Verificar que o frontend esta escutando o canal correto:

   ```ts
   Echo.private(`tenant.${tenantId}`).listen('EventName', (data) => { ... });
   ```

5. Verificar `channels.php` — o canal deve estar autorizado:

   ```php
   Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
       return $user->current_tenant_id === (int) $tenantId;
   });
   ```

### 4.2 Desconexoes frequentes

**Sintoma**: WebSocket desconecta e reconecta repetidamente.

**Solucoes**:

1. Verificar timeout do servidor (nginx/Apache):

   ```nginx
   # nginx.conf
   proxy_read_timeout 86400s;
   proxy_send_timeout 86400s;
   ```

2. Implementar heartbeat no frontend:

   ```ts
   // Echo config
   { wsHost: 'localhost', wsPort: 8080, forceTLS: false, enabledTransports: ['ws'] }
   ```

3. Verificar se ha limite de conexoes no servidor. Aumentar `ulimit`:

   ```bash
   ulimit -n 65535
   ```

---

## 5. Docker

### 5.1 Container nao inicia

**Sintoma**: `docker compose up` falha ou container reinicia em loop.

**Diagnostico**:

```bash
docker compose ps
docker compose logs --tail=50 app
docker compose logs --tail=50 mysql
```

**Solucoes**:

1. Porta ja em uso:

   ```bash
   # Identificar processo na porta
   lsof -i :8000
   # Ou no Windows:
   netstat -ano | findstr :8000
   # Matar processo ou mudar porta no docker-compose.yml
   ```

2. Volume com permissoes erradas:

   ```bash
   docker compose down -v  # Remove volumes (CUIDADO: perde dados do banco)
   docker compose up --build
   ```

3. Imagem desatualizada:

   ```bash
   docker compose build --no-cache
   docker compose up -d
   ```

4. Verificar `.env` do Docker vs `.env` do Laravel — variaveis de host devem usar nomes de servico Docker (ex: `DB_HOST=mysql`, nao `127.0.0.1`).

### 5.2 Disco cheio

**Sintoma**: `No space left on device` em qualquer operacao.

**Diagnostico**:

```bash
docker system df
df -h
```

**Solucoes**:

1. Limpar recursos Docker nao utilizados:

   ```bash
   # Remover containers parados, redes nao usadas, imagens dangling
   docker system prune -f

   # Mais agressivo — remover TUDO nao utilizado (imagens, volumes)
   docker system prune -a --volumes -f
   ```

2. Limpar logs de containers:

   ```bash
   # Truncar logs
   docker compose logs --tail=0
   # Ou limpar diretamente:
   truncate -s 0 /var/lib/docker/containers/*/*-json.log
   ```

3. Verificar se backups ou logs da aplicacao estao consumindo espaco:

   ```bash
   du -sh /var/www/storage/logs/*
   du -sh /var/www/storage/app/*
   ```

4. Configurar rotacao de logs no Laravel (`config/logging.php`): usar channel `daily` com `days => 14`.

---

## Checklist Geral de Debugging

Quando algo nao funciona e voce nao sabe por onde comecar:

1. **Logs do Laravel**: `tail -f backend/storage/logs/laravel.log`
2. **Console do browser**: F12 -> Console e Network tab
3. **Status dos servicos**: Redis, MySQL, Reverb, Queue Worker
4. **Cache stale**: `php artisan optimize:clear`
5. **Permissoes**: `chmod -R 775 storage bootstrap/cache`
6. **Composer/NPM**: `composer install && npm install` (dependencias faltando?)
7. **.env**: Variavel faltando ou errada? Comparar com `.env.example`
