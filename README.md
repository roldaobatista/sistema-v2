# Kalibrium

Sistema de gestao completo para empresas de calibracao e servicos tecnicos de campo.

## Stack Tecnologica

### Backend
- **Framework:** Laravel 13 (PHP 8.3+)
- **Banco de Dados:** MySQL 8.0
- **Cache/Filas:** Redis
- **Autenticacao:** Laravel Sanctum
- **WebSockets:** Laravel Reverb
- **Permissoes:** Spatie Laravel Permission
- **Multi-tenant:** BelongsToTenant trait + EnsureTenantScope middleware

### Frontend
- **Framework:** React 19 + TypeScript 5.9
- **Build:** Vite 8
- **Estilizacao:** TailwindCSS 4 + Radix UI + shadcn/ui
- **Estado:** Zustand
- **Requisicoes:** Axios + TanStack Query
- **Roteamento:** React Router v7
- **Testes:** Vitest + Playwright

### Infraestrutura
- **Containerizacao:** Docker + Docker Compose
- **Web Server:** Nginx (reverse proxy + SSL)
- **Certificados:** Let's Encrypt (Certbot)
- **CI/CD:** GitHub Actions

---

## Numeros do Sistema (Atualizado em 10/04/2026)

| Backend | Quantidade | Frontend | Quantidade |
|---------|-----------|----------|-----------|
| Controllers | 309 | Paginas | 371 |
| Models | 423 | Componentes | 167 |
| Services | 168 | Hooks | 61 |
| Form Requests | 844 | Stores | 5 |
| Policies | 67 | Types | 26 |
| Enums | 39 | Lib/API | 38 |
| Events | 45 | Modulos | 39 |
| Listeners | 42 | Testes Vitest | 285 arq. |
| Observers | 13 | Testes E2E | 62 arq. |
| Jobs | 35 | | |
| Middlewares | 8 | | |
| Migrations | 442 | | |
| Testes Backend | 748 arq. | | |
| Endpoints API | ~2500+ | | |

---

## Modulos (33)

| Modulo | Descricao |
|---|---|
| Cadastros | 35+ tipos de lookup, Registration Engine generico |
| Orcamentos | Aprovacao multi-canal, versionamento, conversao em Chamado/OS |
| Chamados Tecnicos | SLA automatico, auto-assignment, mapa, conversao em OS |
| Ordens de Servico | Kanban, 17 status, GPS, checklists, assinaturas, apontamento de tempo, recorrencia |
| Faturamento | Evento-driven: OS -> Invoice + CR + estoque + comissao + NF-e |
| Financeiro | CR/CP, conciliacao bancaria, DRE, fluxo caixa, aging, cobranca, renegociacao |
| Comissoes | 11 tipos de calculo, split multi-tecnico, campanhas, metas, disputas |
| Despesas | Comprovante obrigatorio, limites, caixa do tecnico, reembolso |
| Clientes | PF/PJ, Customer 360, health score, RFM, churn, merge |
| CRM | Pipeline Kanban, lead scoring, sequencias, territorios, forecasting, gamificacao |
| Agenda | Multi-tipo, Google Calendar OAuth2, deteccao conflitos, 6 auto-criacao listeners |
| Equipamentos | Calibracoes ISO 17025, pesos padrao, carta de controle, deteccao fraude |
| Estoque | Multi-almoxarifado, WMS, kits, serial/lote, etiquetas ZPL, Curva ABC, Kardex |
| Frota | Veiculos, GPS, abastecimento, manutencao, pneus, multas, driver scoring |
| Fiscal | NF-e (SEFAZ) + NFS-e (ABRASF/DSF), certificado A1, contingencia |
| RH | Ponto eletronico (geofence), ferias, organograma, recrutamento, skills, onboarding |
| INMETRO | Prospeccao PSIE, compliance, selos, concorrentes, geolocalizacao, 15 services |
| E-mail | Inbox IMAP, classificacao IA, templates, regras automaticas |
| IA & Analytics | Manutencao preditiva, churn, pricing, anomalias, BI |
| Alertas | Motor de alertas, configuracao por severidade, acknowledge/resolve, export |
| Qualidade | ISO, auditorias, CAPA, NPS, SGA |
| Portal do Cliente | Auth separada, dashboard, OS, orcamentos, certificados, equipamentos |
| Contratos | Recorrencia, renovacao automatica, gera CR |
| Inovacao | Temas customizaveis, programa de indicacao, ROI, gamificacao, badges |
| Mobile | Preferencias usuario, sync offline, push, quiosque, assinatura/foto/voz |
| Pesos e Ferramentas | Pesos padrao, calibracao de ferramentas, rastreabilidade ISO |
| Configuracoes | Multi-empresa, numeracao, 2FA, audit log, notificacoes |
| Relatorios | 14 categorias, Excel/PDF, agendamento, background |
| Integracoes | Auvo, Brasil API, Google Calendar, WhatsApp, SMS, IoT/Cameras |
| Tech PWA | App mobile para tecnicos (47 paginas, offline-first) |
| TV Dashboard | Wallboard com KPIs em tempo real via WebSockets |
| Features Avancadas | RMA, SSO, laboratorio, webhooks |
| Automacao | Workflows, relatorios agendados, regras automaticas |

---

## Requisitos

- Docker e Docker Compose
- PHP 8.4+ (para desenvolvimento local sem Docker)
- Node.js 20+
- MySQL 8.0
- Redis

---

## Instalacao (Desenvolvimento)

### 1. Clone o repositorio

```bash
git clone <repo-url> && cd sistema
```

### 2. Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### 3. Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

### 4. Com Docker (alternativa)

> **Nota:** O `docker-compose.yml` de desenvolvimento sobe apenas a infraestrutura (MySQL, Redis, phpMyAdmin). As aplicações (Backend API e Frontend) devem ser executadas fora do Docker com `php artisan serve` e `npm run dev`.

```bash
docker-compose up -d
```

Acesse:
- Frontend: http://localhost:3000 (ou 5173 conforme vite.config)
- Backend API: http://127.0.0.1:8000/api/v1
- MySQL: 127.0.0.1:3307
- phpMyAdmin: http://localhost:8081

**Se aparecer "Erro ao conectar com o servidor" no login:** Verifique se as variáveis de ambiente locais refletem os serviços corretos. Reinicie o frontend (`npm run dev`) após alterar o .env.

---

## Instalacao (Producao)

### 1. Configure as variaveis de ambiente

```bash
# Backend
cd backend && cp .env.production.example .env
# Edite .env com as credenciais de producao (DB, Redis, Mail, etc.)

# Frontend
cd frontend && cp .env.production.example .env
# Configure VITE_API_URL com a URL da API
```

### 2. Deploy com Docker

```bash
# Build e deploy
./deploy/deploy.sh

# Ou manualmente
docker-compose -f docker-compose.prod.yml up -d --build
```

### 3. Pos-deploy

```bash
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache
```

---

## Testes

### Backend
```bash
cd backend
php artisan test
php artisan test --coverage
```

### Frontend
```bash
cd frontend
npm run test
npm run test:coverage
npx playwright test  # E2E
```

---

## Variaveis de Ambiente Importantes

### Backend (.env)
| Variavel | Descricao |
|---|---|
| `APP_ENV` | `local` ou `production` |
| `APP_DEBUG` | `true` (dev) ou `false` (prod) |
| `DB_*` | Credenciais do MySQL |
| `REDIS_*` | Configuracao do Redis |
| `MAIL_*` | Configuracao de email (SMTP) |
| `REVERB_*` | Configuracao do WebSocket |

### Frontend (.env)
| Variavel | Descricao |
|---|---|
| `VITE_API_URL` | URL base da API (ex: `https://api.kalibrium.com/api/v1`) |
| `VITE_ERROR_REPORTING_URL` | URL do servico de monitoramento de erros (opcional) |

---

## Estrutura do Projeto

```
sistema/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/   # Controllers por dominio
│   │   ├── Models/                     # Eloquent models
│   │   ├── Services/                   # Logica de negocio
│   │   ├── Http/Requests/              # Form Requests (validacao)
│   │   ├── Policies/                   # Policies (autorizacao)
│   │   ├── Enums/                      # Status e tipos tipados
│   │   ├── Events/                     # Domain events
│   │   ├── Listeners/                  # Event listeners
│   │   ├── Observers/                  # Model observers
│   │   ├── Jobs/                       # Queue jobs
│   │   └── Middleware/                 # HTTP middleware
│   ├── database/migrations/            # Migrations
│   ├── routes/api/                     # Route files modulares
│   └── tests/                          # Testes backend (Pest)
├── frontend/                # React SPA
│   ├── src/
│   │   ├── pages/           # Paginas por modulo
│   │   ├── components/      # Componentes reutilizaveis
│   │   ├── hooks/           # React hooks
│   │   ├── stores/          # Zustand stores
│   │   ├── types/           # TypeScript definitions
│   │   └── lib/             # API clients e utilitarios
│   ├── src/__tests__/       # Testes unitarios (Vitest)
│   └── e2e/                 # Testes E2E (Playwright)
├── docs/                    # Documentacao do projeto
│   ├── PRD-KALIBRIUM.md    # Product Requirements Document (fonte de verdade funcional, v3.2+)
│   ├── TECHNICAL-DECISIONS.md # Decisoes arquiteturais duraveis
│   ├── BLUEPRINT-AIDD.md   # Metodologia AIDD
│   ├── audits/              # Deep Audits (RELATORIO-AUDITORIA-SISTEMA.md)
│   ├── api/                 # Documentacao de API
│   ├── architecture/        # Decisoes arquiteturais
│   ├── auditoria/           # Relatorios de auditoria
│   ├── compliance/          # Compliance e regulatorio
│   ├── design-system/       # Design system Kalibrium
│   ├── fluxos/              # Fluxos de negocio
│   ├── modules/             # Documentacao por modulo
│   ├── operacional/         # Docs operacionais
│   ├── superpowers/         # Planos de implementacao
│   └── .archive/            # Reports e screenshots historicos
├── deploy/                  # Deploy e infraestrutura de producao
│   ├── deploy.sh            # Script principal de deploy
│   ├── deploy-prod.ps1      # Deploy via PowerShell (Windows)
│   ├── DEPLOY.md            # Guia de deploy
│   ├── DEPLOY-HETZNER.md   # Setup Hetzner
│   ├── SSL-SETUP.md         # Configuracao SSL/HTTPS
│   ├── SEGURANCA-PRODUCAO.md # Checklist de seguranca
│   ├── setup-server.sh      # Setup inicial do servidor
│   ├── setup-git-server.sh  # Setup Git no servidor
│   ├── patches/             # Patches de producao
│   └── env-examples/        # Exemplos de .env para deploy
├── infra/                   # Configuracoes de infraestrutura
│   ├── docker/              # MySQL init scripts
│   └── observability/       # OpenTelemetry config
├── nginx/                   # Configuracao Nginx (usado pelo docker-compose)
├── scripts/                 # Scripts uteis (setup, test-runner, AIDD)
│   └── .archive/            # Scripts one-time arquivados
├── tests/                   # Testes cross-stack
│   ├── e2e/                 # Testes E2E Python (TestSprite)
│   └── performance/         # Testes de performance
├── docker-compose.yml       # Desenvolvimento
├── docker-compose.prod.yml  # Producao (HTTPS)
├── docker-compose.prod-http.yml  # Producao (HTTP)
├── docker-compose.test.yml  # Ambiente de testes
├── docker-compose.observability.yml # Observabilidade
└── .github/workflows/       # CI/CD Pipeline
```

---

## CI/CD

O pipeline GitHub Actions executa automaticamente em push para `main` e `develop`:

1. **Backend Tests** - PHPUnit com MySQL real
2. **Frontend Build** - TypeScript check + build de producao
3. **E2E Tests** - Playwright com Chromium

---

## Licenca

Projeto proprietario. Todos os direitos reservados.
