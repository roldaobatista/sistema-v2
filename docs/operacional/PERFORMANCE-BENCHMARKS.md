# Performance Benchmarks — Kalibrium ERP

Metas de performance para garantir experiencia de usuario aceitavel e estabilidade sob carga.

---

## 1. API Response Times

| Metrica | Target | Critico |
|---------|--------|---------|
| GET endpoints (listagem) p50 | < 200ms | > 500ms |
| GET endpoints (listagem) p95 | < 500ms | > 1000ms |
| GET endpoints (detalhe) p50 | < 100ms | > 300ms |
| GET endpoints (detalhe) p95 | < 300ms | > 500ms |
| POST/PUT (mutations) p50 | < 300ms | > 700ms |
| POST/PUT (mutations) p95 | < 500ms | > 1000ms |
| Login/Auth p50 | < 200ms | > 500ms |
| Health check | < 50ms | > 200ms |

### Como medir

```bash
# Tempo de resposta de um endpoint
curl -w "\n  time_namelookup: %{time_namelookup}\n  time_connect: %{time_connect}\n  time_starttransfer: %{time_starttransfer}\n  time_total: %{time_total}\n" \
  -o /dev/null -s -H "Authorization: Bearer $TOKEN" \
  http://localhost/api/v1/work-orders

# Benchmark com ab (Apache Bench) — 100 requests, 10 concorrentes
ab -n 100 -c 10 -H "Authorization: Bearer $TOKEN" http://localhost/api/v1/work-orders
```

---

## 2. Database Query Targets

| Metrica | Target | Critico |
|---------|--------|---------|
| Query individual max | < 100ms | > 500ms |
| Queries por request (max) | < 15 | > 30 |
| N+1 queries | 0 | > 0 |
| Slow query log (MySQL) | 0 entradas/hora | > 5/hora |
| Conexoes ativas simultaneas | < 50 | > 100 |

### Como medir

```bash
# Ativar slow query log no MySQL
mysql -e "SET GLOBAL slow_query_log = 'ON'; SET GLOBAL long_query_time = 0.5;"

# Monitor de conexoes
mysql -e "SHOW STATUS LIKE 'Threads_connected';"

# Query log no Laravel (adicionar em AppServiceProvider::boot)
# DB::listen(function ($query) {
#     if ($query->time > 100) {
#         Log::warning('Slow query', ['sql' => $query->sql, 'time' => $query->time]);
#     }
# });

# Verificar queries por request com Laravel Debugbar
php artisan debugbar:enable
```

---

## 3. Frontend Bundle Targets

| Metrica | Target | Critico |
|---------|--------|---------|
| Bundle principal (gzipped) | < 500KB | > 800KB |
| Largest Contentful Paint (LCP) | < 2.5s | > 4.0s |
| First Input Delay (FID) | < 100ms | > 300ms |
| Cumulative Layout Shift (CLS) | < 0.1 | > 0.25 |
| Time to Interactive (TTI) | < 3.5s | > 5.0s |
| Chunks por rota (lazy) | < 150KB | > 300KB |

### Como medir

```bash
# Tamanho do bundle
cd frontend && npx vite build 2>&1 | grep -E "dist|\.js|\.css"

# Visualizacao detalhada do bundle
cd frontend && npx vite-bundle-visualizer

# Lighthouse CLI
npx lighthouse http://localhost:5173 --only-categories=performance --output=json --output-path=./lighthouse.json

# Core Web Vitals (em producao, via web-vitals library)
# Ja deve estar integrado no frontend via reportWebVitals()
```

---

## 4. Queue / Jobs Targets

| Metrica | Target | Critico |
|---------|--------|---------|
| Job processing time (p95) | < 30s | > 60s |
| Queue latency (wait time) | < 5s | > 30s |
| Failed jobs / hora | 0 | > 5 |
| Queue size (backlog) | < 100 | > 500 |
| Email delivery time | < 10s | > 60s |

### Como medir

```bash
# Status das filas
php artisan queue:monitor default,emails,reports,notifications

# Failed jobs
php artisan queue:failed | wc -l

# Processar filas com timeout
php artisan queue:work --timeout=60 --tries=3 --queue=default,emails

# Limpar failed jobs antigos
php artisan queue:flush
```

---

## 5. Cache Targets

| Metrica | Target | Critico |
|---------|--------|---------|
| Cache hit rate (Redis) | > 85% | < 60% |
| Redis memory usage | < 512MB | > 1GB |
| Redis latency (p95) | < 5ms | > 20ms |
| Cache key count | < 100K | > 500K |

### Como medir

```bash
# Stats do Redis
redis-cli INFO stats | grep -E "keyspace_hits|keyspace_misses|used_memory_human"

# Hit rate calculado
redis-cli INFO stats | grep -E "keyspace_hits|keyspace_misses"
# hit_rate = hits / (hits + misses) * 100

# Latencia do Redis
redis-cli --latency

# Keys por database
redis-cli INFO keyspace

# Memory usage detalhado
redis-cli MEMORY STATS
```

---

## 6. WebSocket / Reverb Targets

| Metrica | Target | Critico |
|---------|--------|---------|
| Conexoes simultaneas | < 500 | > 1000 |
| Message delivery time | < 200ms | > 1000ms |
| Reconnect rate | < 1/min per client | > 5/min |
| Memory per connection | < 50KB | > 200KB |

### Como medir

```bash
# Status do Reverb
php artisan reverb:start --debug

# Conexoes ativas (via API interna do Reverb)
curl http://localhost:8080/apps/your-app-id/channels
```

---

## 7. Infraestrutura Geral

| Metrica | Target | Critico |
|---------|--------|---------|
| CPU usage (app server) | < 70% | > 90% |
| Memory usage (app server) | < 80% | > 95% |
| Disk usage | < 80% | > 90% |
| PHP-FPM pool utilization | < 75% | > 90% |
| Uptime | > 99.5% | < 99% |

### Como medir

```bash
# Recursos do servidor
top -bn1 | head -5
df -h
free -m

# PHP-FPM status
curl http://localhost/fpm-status

# Uptime
uptime
```

---

## Resumo de Alertas

### Stack de Monitoramento
- **APM:** Laravel Pulse (incluído no projeto) — métricas de request, queries lentas, jobs, cache
- **Alerting:** Pulse checks + custom health checks (`spatie/laravel-health`)
- **Roteamento de alertas:**
  | Severidade | Canal | Destinatário |
  |------------|-------|--------------|
  | P0 (sistema down) | Email + SMS | DevOps + CTO |
  | P1 (degradação) | Email | DevOps + Tech Lead |
  | P2 (warning) | Pulse dashboard | DevOps |
- **Dashboard:** Pulse dashboard em `/pulse` (protegido por gate `viewPulse`)
- **Nota:** Prometheus/Grafana é planejado para fase futura. Atualmente usar Pulse.

### Limites de Alerta

1. **P0 (Imediato)**: API response > 1s, failed jobs > 5/hora, Redis down, DB connections > 100
2. **P1 (15 min)**: Cache hit rate < 60%, queue backlog > 500, disk > 90%
3. **P2 (1 hora)**: Bundle > 800KB, LCP > 4s, slow queries > 5/hora
