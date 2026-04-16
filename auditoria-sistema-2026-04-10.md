# Auditoria estática — sistema-main

Data: 2026-04-10
Escopo: auditoria de segurança, código, organização, inconsistências, funcionalidades, fluxos de negócio e recomendações.
Método: leitura estática do repositório ZIP enviado. Não houve execução completa do backend/frontend nem validação dinâmica de todos os fluxos.

\---

## 1\) Resumo executivo

O projeto está **acima da média em volume e ambição**, com sinais claros de maturidade estrutural:

* backend Laravel 13 / PHP 8.3;
* frontend React 19 / TypeScript 5.9 / Vite 8;
* multi-tenant, políticas, requests, eventos, jobs, webhooks e suíte de testes extensa.

Ao mesmo tempo, a auditoria encontrou **problemas reais de segurança, drift documental e quebra de alguns fluxos críticos**.

### Veredito objetivo

* **Arquitetura base:** boa.
* **Governança/documentação:** fraca e desatualizada.
* **Segurança operacional:** precisa de ação imediata.
* **Fluxos financeiros externos:** incompletos / inconsistentes.
* **Qualidade de organização:** razoável, mas com acúmulo de legado e duplicações.

### Prioridades máximas

1. **Revogar segredos expostos no repositório**.
2. **Corrigir o fluxo fim-a-fim de cobrança PSP (boleto/PIX + webhook + baixa)**.
3. **Sanear documentação/envs/scripts para evitar deploy/configuração errada**.
4. **Consolidar integrações duplicadas (especialmente WhatsApp/webhooks)**.
5. **Eliminar duplicações e drift no frontend/offline/API helpers**.

\---

## 2\) Dimensão real do sistema vs README

O README principal afirma números “atualizados em 25/03/2026”, mas a contagem do código no ZIP diverge.

### README principal

Arquivo: `README.md:33-46`

* Controllers: 245
* Models: 368
* Services: 121
* Form Requests: 744
* Policies: 62
* Frontend Pages: 350
* Components: 159
* Hooks: 57
* Vitest: 275
* E2E: 61

### Contagem observada no repositório enviado

* Controllers: **300**
* Models: **411**
* Services: **158**
* Form Requests: **835**
* Policies: **67**
* Enums: **39**
* Events: **45**
* Listeners: **42**
* Observers: **13**
* Jobs: **35**
* Migrations: **425**
* Frontend Pages: **371**
* Components: **167**
* Hooks: **61**
* Stores: **5**
* Types: **26**
* Testes Vitest: **285**
* E2E: **62**
* Testes backend: **735 arquivos PHP**

### Conclusão

O projeto **cresceu mais rápido do que a documentação acompanhou**. Isso impacta onboarding, estimativa de esforço, deploy e entendimento do estado real do produto.

\---

## 3\) Pontos fortes

### 3.1 Estrutura de backend relativamente madura

Há sinais claros de organização por camadas:

* `Form Requests` em volume alto;
* `Policies` por domínio;
* `Services`, `Actions`, `Events`, `Listeners`, `Observers`;
* carregamento modular de rotas em `backend/bootstrap/app.php:37-49`;
* proteção multi-tenant centralizada via middleware.

### 3.2 Multi-tenant parece tratado de forma séria

O bootstrap aplica `auth:sanctum` + `check.tenant` por padrão aos arquivos em `routes/api/\*.php` (exceto exceções explícitas), o que é bom para consistência de escopo.

### 3.3 Suíte de testes é volumosa

A presença de centenas de testes backend e frontend indica preocupação com regressão. O problema não é ausência de testes; o problema é que **há gaps relevantes que os testes atuais não estão capturando**.

### 3.4 Alguns gaps documentados anteriormente parecem já ter sido parcialmente ou totalmente corrigidos

Há drift entre auditorias antigas em `docs/` e o código atual. Exemplos:

* **Deal → Quote** hoje existe no código;
* **WhatsApp inbound → CRM** hoje parece existir, embora fragmentado;
* **bloqueio em produção para eventos eSocial stub** existe;
* **recurring billing placeholder fixo** não bate mais com a implementação atual;
* **PWA/offline** está mais avançado do que documentos antigos sugerem.

Esse ponto é positivo para o produto, mas negativo para a governança: a documentação interna está induzindo leitura errada do estado do sistema.

\---

## 4\) Achados de segurança

## 4.1 CRÍTICO — segredo/token exposto dentro do repositório

Arquivo: `tests/e2e/tmp/config.json:1-19`

O arquivo contém:

* credenciais de login locais: `admin@example.test / <configure local password>`;
* `API\_KEY` com valor `sk-user-...`;
* credenciais embutidas em URL de proxy;
* caminho local de máquina Windows.

Além disso, a busca em `.gitignore` não mostrou proteção para `tests/e2e/tmp/`.

### Impacto

* vazamento de segredo real ou reutilizável;
* risco de uso indevido de conta/serviço externo;
* contaminação do histórico Git;
* exposição de credenciais operacionais de teste.

### Ação recomendada

* revogar imediatamente o token exposto;
* trocar credenciais relacionadas;
* remover o arquivo do histórico Git, não apenas do branch atual;
* adicionar `tests/e2e/tmp/` ao `.gitignore`;
* impedir commit de artefatos temporários com pre-commit / scanner de segredos.

## 4.2 ALTO — template de ambiente “local” com perfil de produção

Arquivo: `backend/.env.example:1-5, 77-88`

O arquivo diz:

* `# Template para DESENVOLVIMENTO LOCAL.`

Mas define:

* `APP\_ENV=production`
* `APP\_DEBUG=false`
* `SESSION\_DRIVER=redis`
* `QUEUE\_CONNECTION=redis`
* `SESSION\_SECURE\_COOKIE=true`

### Impacto

* setup local enganoso;
* debug mascarado;
* onboarding quebrado;
* deploys e testes locais baseados em configuração conceitualmente errada.

### Ação recomendada

Separar claramente:

* `backend/.env.example` para desenvolvimento local real;
* `backend/.env.production.example` para produção.

Hoje o README manda copiar arquivos que **não existem**:

* `README.md:156` → `backend/.env.production.example`
* `README.md:160` → `frontend/.env.production.example`

## 4.3 MÉDIO/ALTO — CORS local excessivamente permissivo e conceitualmente inconsistente com credentials

Arquivo: `backend/config/cors.php:4-13`

Configuração observada:

* em ambiente `local`, `allowed\_origins = \['\*']`
* `supports\_credentials = true`

### Impacto

Mesmo em ambiente local, essa combinação aumenta confusão operacional e amplia superfície de erro. O correto é **origens explícitas** quando há credenciais/cookies.

### Ação recomendada

Substituir wildcard por lista explícita de origens, inclusive em dev.

## 4.4 MÉDIO — modelo híbrido Bearer + cookie aumenta superfície de autenticação

Arquivos:

* `backend/bootstrap/app.php:83`
* `backend/app/Http/Middleware/InjectBearerFromCookie.php:9-25`
* `backend/config/sanctum.php:40-48`

O bootstrap comenta “token-based auth only”, mas existe um middleware que:

* lê o cookie httpOnly de autenticação;
* injeta `Authorization: Bearer ...` para qualquer `api/\*` sem bearer explícito.

### Impacto

Não é prova automática de vulnerabilidade, mas é um desenho mais complexo do que o necessário. Misturar paradigmas de autenticação aumenta risco de erro em CORS, CSRF, cookies e troubleshooting.

### Ação recomendada

Escolher uma linha clara:

* ou Bearer puro;
* ou cookie/session stateful bem definido.

Hoje a implementação está funcionalmente híbrida.

## 4.5 MÉDIO — cabeçalhos de segurança incompletos

Arquivo: `backend/app/Http/Middleware/SecurityHeaders.php:15-23`

Há:

* `X-Content-Type-Options`
* `X-Frame-Options`
* `Referrer-Policy`
* `Permissions-Policy`
* HSTS em requests seguros

Não há CSP explícita.

### Impacto

Para um sistema SPA grande, ausência de Content-Security-Policy é lacuna relevante de hardening.

### Ação recomendada

Definir CSP mínima por ambiente e endurecer progressivamente.

## 4.6 MÉDIO — webhook fiscal ainda aceita segredo via body/query

Arquivo: `backend/app/Http/Middleware/VerifyFiscalWebhookSecret.php:10-30`

O middleware aceita:

* header `X-Fiscal-Webhook-Secret`
* ou `webhook\_secret` via body/query

### Impacto

Segredo em query/body tende a aparecer com mais facilidade em logs, traces e ferramentas intermediárias.

### Ação recomendada

Padronizar **somente header** e deprecar body/query.

## 4.7 MÉDIO — docker compose de desenvolvimento expõe infraestrutura sensível

Arquivo: `docker-compose.yml:11-12, 43-44, 64-65`

Exposições observadas:

* MySQL: `3307:3306`
* Redis: `6379:6379`
* phpMyAdmin: `8081:80`

### Impacto

Em dev local isolado isso é comum. O risco é esse compose ser reutilizado em ambiente compartilhado sem endurecimento.

### Ação recomendada

Documentar com clareza que esse compose é estritamente de desenvolvimento e fornecer compose de staging mais restrito.

\---

## 5\) Auditoria de código e organização

## 5.1 ALTO — `frontend/src/lib/api.ts` contém código duplicado

Arquivo: `frontend/src/lib/api.ts:262-340`

O arquivo repete:

* `unwrapData`
* `getApiOrigin`
* `buildStorageUrl`
* `export default api`

Há duas cópias consecutivas da mesma seção.

### Impacto

* ruído de manutenção;
* risco de divergência futura;
* indício de merge mal resolvido ou edição manual indevida.

### Ação recomendada

Remover duplicação e adicionar teste/lint que detecte redefinições/exports duplicados.

## 5.2 MÉDIO/ALTO — integração WhatsApp está fragmentada em controllers e rotas duplicadas

Arquivos:

* `backend/routes/api.php:72-90`
* `backend/app/Http/Controllers/Api/V1/Webhook/WhatsAppWebhookController.php`
* `backend/app/Http/Controllers/Api/V1/CrmMessageController.php:226+`

Há pelo menos dois caminhos públicos relacionados:

* `/webhooks/whatsapp/status` e `/webhooks/whatsapp/messages` via `WhatsAppWebhookController`
* `/webhooks/whatsapp` via `CrmMessageController::webhookWhatsApp`

### Impacto

* semântica de integração duplicada;
* troubleshooting difícil;
* maior chance de payloads tratados de formas diferentes;
* documentação/confiança operacional menores.

### Ação recomendada

Consolidar em um único boundary de integração para WhatsApp, com contrato de payload claro e testes de regressão.

## 5.3 MÉDIO — controller legado/órfão de conversão CRM

Arquivo: `backend/app/Http/Controllers/Api/V1/Crm/CrmConversionController.php:15-20`

Existe `CrmConversionController`, mas a busca por rotas não mostrou uso dele. O fluxo ativo de Deal → Quote passa por:

* `backend/routes/api/crm.php:53`
* `backend/app/Http/Controllers/Api/V1/CrmController.php:171-175`
* `backend/app/Services/CrmService.php:501+`

### Impacto

Código órfão ou legado aumenta custo cognitivo e pode induzir manutenção em lugar errado.

### Ação recomendada

Remover, deprecar explicitamente ou cobrir com comentário claro de legado.

## 5.4 MÉDIO — repositório com excesso de artefatos auxiliares e ruído documental

Pastas e artefatos como:

* `.agent/`
* documentos de auditoria antigos e parcialmente defasados
* scripts específicos de ambiente local

### Impacto

Onboarding fica mais difícil. O repositório mistura código do produto, automação local, metadocumentação e evidências temporárias.

### Ação recomendada

Separar:

* documentação viva do produto;
* documentação histórica de auditoria;
* tooling local;
* artefatos temporários.

## 5.5 MÉDIO — caminhos Windows e ambiente pessoal hardcoded

Arquivos:

* `scripts/php-runtime.mjs:40-41`
* `scripts/test-runner.mjs:79`
* `tests/e2e/tmp/config.json:10`

Exemplos:

* `$HOME/.../php.exe`
* `C:/projetos/sistema`
* `c:\\projetos\\sistema`

### Impacto

* baixa portabilidade;
* acoplamento ao ambiente do mantenedor;
* quebra em Linux/macOS/CI se não houver abstração adequada.

### Ação recomendada

Padronizar descoberta de runtime por variável de ambiente ou fallback portátil.

## 5.6 MÉDIO — backend README não serve como onboarding do produto

Arquivo: `backend/README.md:1-59`

O README do backend ainda é praticamente o README padrão do Laravel.

### Impacto

Quem entra no projeto não encontra documentação real do backend do produto.

### Ação recomendada

Trocar por README de operação real:

* como subir;
* variáveis mínimas;
* módulos críticos;
* autenticação;
* multi-tenant;
* webhooks;
* filas;
* testes.

\---

## 6\) Funcionalidades e fluxos de negócio

## 6.1 Deal → Quote existe no código atual

Fluxo encontrado:

* rota: `backend/routes/api/crm.php:53`
* controller: `backend/app/Http/Controllers/Api/V1/CrmController.php:171-175`
* service/action: `backend/app/Services/CrmService.php:501+` e `backend/app/Actions/Crm/ConvertDealToQuoteAction.php`
* frontend: `frontend/src/lib/crm-api.ts:208`

### Conclusão

Esse fluxo **não está ausente** como auditorias antigas sugerem. A documentação interna sobre isso está desatualizada.

## 6.2 WhatsApp → CRM parece implementado, mas de forma fragmentada

Há evidência de ingestão de mensagens no CRM. O problema atual não é “inexistência pura”, e sim **fragmentação e sobreposição de integrações**.

### Conclusão

Status correto hoje: **parcial/fragmentado**, não “inexistente”.

## 6.3 eSocial: parte real, parte stub, com bloqueio em produção

Arquivos:

* `backend/app/Services/ESocialService.php:46-54, 199-274`
* `backend/app/Services/ESocial/ESocialTransmissionService.php:55-56`

Eventos como `S-2205`, `S-2206`, `S-1210`, `S-2210`, `S-2220`, `S-2240` ainda retornam `buildStubXml(...)`, mas a transmissão em produção desses stubs está bloqueada.

### Conclusão

O risco foi mitigado parcialmente. Ainda é funcionalidade incompleta, mas **não vi a falha crítica antiga de permitir stub em produção pela trilha principal**.

## 6.4 Recurring billing placeholder antigo parece ter sido corrigido

Arquivo: `backend/app/Services/Contracts/RecurringBillingService.php:71-81`

A implementação atual usa:

* `monthly\_value` quando `billing\_type === 'fixed\_monthly'`
* ou soma dos itens do contrato

### Conclusão

A alegação antiga de valor fixo placeholder não bate mais com o código atual.

## 6.5 ALTO — cobrança boleto/PIX tem backend, mas o fluxo fim-a-fim está incompleto e provavelmente quebrado

### Evidências de backend existente

Rotas:

* `backend/routes/api/finance-advanced.php:53-60`

Controllers:

* `backend/app/Http/Controllers/Api/V1/Financial/InstallmentPaymentController.php`

Provider/gateway:

* `backend/app/Services/Payment/AsaasPaymentProvider.php`
* `backend/app/Services/Payment/PaymentGatewayService.php`

### Evidências de ausência de wiring claro no frontend

Não encontrei uso no frontend para:

* `generate-boleto`
* `generate-pix`
* `payment-status`
* campos PSP como `psp\_external\_id`, `boleto\_url`, `pix\_copy\_paste`

A API financeira do frontend (`frontend/src/lib/financial-api.ts`) não expõe esses endpoints.

### Problema técnico mais grave

No controller de geração, o metadata enviado contém:

* `installment\_id`
* `account\_receivable\_id`
* `tenant\_id`

Arquivo: `backend/app/Http/Controllers/Api/V1/Financial/InstallmentPaymentController.php:55-59, 136-140`

Mas o provider Asaas monta `externalReference` **somente** quando existir:

* `metadata\['payable\_id']`
* opcionalmente `metadata\['payable\_type']`

Arquivo: `backend/app/Services/Payment/AsaasPaymentProvider.php:123-131`

O webhook, por sua vez, tenta auto-criar/relacionar pagamento usando exatamente `externalReference` no formato `Tipo:ID`:

* `backend/app/Http/Controllers/Api/V1/Webhooks/PaymentWebhookController.php:41, 61-81`

### Efeito provável

* a cobrança pode ser gerada no PSP;
* o webhook pode chegar;
* mas a aplicação **pode não conseguir reconciliar automaticamente** o pagamento com o título/parcela, porque o `externalReference` necessário não foi montado.

### Observação adicional

Também não encontrei, na trilha principal de `PaymentWebhookController` + `HandlePaymentReceived`, a atualização explícita da **parcela** (`AccountReceivableInstallment`) para quitada. O listener trata comissões, notificações e health score, mas não fecha a parcela diretamente.

### Conclusão

Esse é um dos achados mais relevantes da auditoria. O produto tem “peças” de boleto/PIX, mas o **fluxo de negócio de cobrança automática fim-a-fim não está confiável**.

## 6.6 SaaS Subscription está deliberadamente desabilitado em produção

Arquivo: `backend/app/Http/Controllers/Api/V1/Billing/SaasSubscriptionController.php:29-33, 67-71, 89-93`

A criação, renovação e cancelamento estão bloqueados em produção por `DomainException`.

### Conclusão

Esse módulo existe, mas **não é operacional em produção** no estado atual.

## 6.7 PWA/offline está mais avançado que a documentação antiga, mas o desenho offline está fragmentado

Há evidências de:

* service worker robusto em `frontend/public/sw.js`
* cache de shell e API GET
* fila offline para mutações
* engine de sync
* UI de status offline

Ao mesmo tempo, coexistem pelo menos duas trilhas conceituais de offline:

* `frontend/src/lib/offlineDb.ts` com `mutation-queue`
* `frontend/src/lib/offline/indexedDB.ts` com `sync-queue`
* `frontend/src/lib/api.ts` importa `addToSyncQueue` desse segundo caminho, mas o helper nem aparece sendo utilizado ali

### Conclusão

O status correto não é “offline inexistente”. O status é **offline relativamente rico, porém com sinais de sobreposição/legado e risco de inconsistência**.

\---

## 7\) Inconsistências documentais e operacionais

## 7.1 README manda usar arquivos inexistentes

Arquivo: `README.md:156,160`

Referências inexistentes:

* `backend/.env.production.example`
* `frontend/.env.production.example`

## 7.2 README de frontend fala em Vite 7, package.json usa Vite 8

Arquivos:

* `frontend/README.md:3`
* `frontend/package.json:109`

## 7.3 Auditorias internas antigas ficaram defasadas em pontos importantes

Exemplos em `docs/raio-x-sistema.md` e `docs/auditoria/AUDITORIA-PRD-2026-04-06.md` ainda tratam como gaps absolutos itens que hoje já têm implementação parcial ou total:

* Deal → Quote
* WhatsApp → CRM
* PWA offline funcional
* bloqueio de stubs eSocial em produção
* recurring billing placeholder

### Impacto

A equipe pode tomar decisão errada com base em documentação já superada.

\---

## 8\) Problemas prioritários por severidade

### CRÍTICO

1. Segredo/token exposto em `tests/e2e/tmp/config.json`.

### ALTO

2. Fluxo de boleto/PIX com provável quebra de reconciliação via webhook (`externalReference` incompatível).
3. Template/env/documentação de deploy inconsistentes e potencialmente perigosos.
4. Duplicação concreta em `frontend/src/lib/api.ts`.
5. Módulo SaaS Subscription não operacional em produção.

### MÉDIO/ALTO

6. Integração WhatsApp fragmentada/duplicada.
7. CORS local permissivo com credentials.
8. Autenticação híbrida Bearer+cookie aumentando complexidade.
9. Offline/PWA com sinais de sobreposição de implementações.

### MÉDIO

10. Falta de CSP.
11. Webhook fiscal aceitando segredo por query/body.
12. Hardcodes Windows e paths locais.
13. Backend README genérico e onboarding documental ruim.
14. Compose de dev expondo serviços sensíveis.

\---

## 9\) Recomendações objetivas

## 9.1 Correções imediatas (0–7 dias)

* Revogar todos os segredos expostos.
* Limpar histórico Git do arquivo comprometido.
* Adicionar scanner de segredos no CI.
* Corrigir `frontend/src/lib/api.ts`.
* Corrigir `InstallmentPaymentController` para enviar `payable\_id` / `payable\_type` ou ajustar `AsaasPaymentProvider` para gerar `externalReference` compatível.
* Criar teste automatizado cobrindo geração de cobrança + webhook + baixa/reconciliação.

## 9.2 Correções de curto prazo (1–3 semanas)

* Separar `.env.example` local de `.env.production.example` real.
* Atualizar README raiz, backend e frontend.
* Consolidar webhooks/integração WhatsApp em uma única trilha.
* Rever estratégia de autenticação: Bearer puro ou cookie puro, sem ponte implícita.
* Definir CSP mínima.
* Eliminar hardcodes Windows dos scripts.

## 9.3 Correções de produto (3–6 semanas)

* Fechar UI/UX de boleto/PIX no frontend.
* Exibir status da cobrança, QR code, linha digitável, reconsulta e cancelamento quando aplicável.
* Garantir baixa automática de parcela e título com auditoria de eventos.
* Formalizar status de módulos ainda bloqueados em produção (SaaS Subscription, integrações parciais etc.).

## 9.4 Governança contínua

* instituir “documentação viva” por módulo;
* manter uma única matriz oficial de maturidade funcional;
* adicionar checklist de release com:

  * segredos;
  * envs;
  * webhooks;
  * cobranças PSP;
  * offline sync;
  * smoke tests críticos.

\---

## 10\) Funcionalidades faltantes ou incompletas que merecem roadmap

Com base no estado atual do código, eu priorizaria como lacunas de negócio:

1. **Cobrança digital fim-a-fim realmente operacional**

   * geração de boleto/PIX
   * exibição no frontend
   * webhook confiável
   * baixa automática e rastreável
   * conciliação auditável
2. **Maturação do módulo SaaS/Billing em produção**

   * hoje o controller está bloqueado em prod
   * falta confiança operacional para receita recorrente do próprio SaaS
3. **Consolidação da omnicanalidade CRM**

   * WhatsApp existe, mas precisa arquitetura única
   * ideal: timeline única, deduplicação de mensagens, status de entrega/leitura, vínculo forte com contato/deal/chamado
4. **Governança operacional do PWA/offline**

   * unificar a fila offline
   * evitar múltiplos mecanismos concorrentes
   * criar replay determinístico e observabilidade do sync
5. **Onboarding/deploy operável por terceiros**

   * hoje há dependência forte de conhecimento tácito e ambiente do mantenedor

\---

## 11\) Conclusão final

O sistema **não é um protótipo**. Ele já tem porte de produto grande e vários módulos com implementação relevante. O problema central não é falta de volume de código; é **coerência operacional**.

Em linguagem direta:

* a base técnica tem valor;
* o multi-tenant e a modularização são bons sinais;
* há muita funcionalidade real já construída;
* mas existem falhas de governança, segurança operacional e integração que podem comprometer produção e receita.

Se eu tivesse que resumir em uma frase:

> O projeto tem arquitetura suficiente para escalar, mas ainda carrega dívida operacional e integrações incompletas que precisam ser tratadas antes de ser considerado plenamente confiável para operação crítica.



Auditoria técnica e de negócio — Kalibrium

**Data:** 2026-04-10
**Escopo auditado:** snapshot do repositório enviado em `/mnt/data/repo/sistema-main`
**Método:** varredura estrutural do repositório inteiro + inspeção manual dos fluxos críticos de segurança, multiempresa, autenticação, PWA/offline, cobrança, webhooks, auditoria, rotas, documentação e organização de módulos.

## 1\) Limites honestos desta auditoria

Vou ser direto: este código é grande demais para eu alegar, com honestidade, que cada linha recebeu o mesmo nível de leitura manual. O que eu fiz foi:

* varrer **todo o repositório** em estrutura, contagem, padrões, migrações, rotas, testes e pontos de risco;
* ler manualmente os arquivos e trechos **mais críticos** para segurança, isolamento entre empresas, autenticação, offline/PWA, cobrança, auditoria, e consistência arquitetural;
* validar defeitos concretos de código quando havia evidência direta;
* confrontar código, README e PRD para entender o negócio e o desalinhamento entre discurso e implementação.

Também há uma limitação prática importante: o snapshot veio **sem `vendor/` e sem `node\\\_modules/`**, então **não foi possível** executar o ciclo completo real de build, testes backend, testes frontend e análise estática completa da forma como o projeto espera. Portanto, esta auditoria é profunda e materialmente útil, mas não substitui uma rodada com ambiente completo reproduzível.

## 2\) Diagnóstico executivo

### O que esse sistema é, de fato

Pelo código e pela documentação, o Kalibrium tenta ser simultaneamente:

1. **ERP vertical para empresas de calibração/metrologia e serviço técnico de campo**;
2. **FSM/Field Service** com OS, agenda, técnico, PWA offline e execução em campo;
3. **plataforma comercial/CRM**;
4. **financeiro/faturamento/cobrança**;
5. **compliance regulatório brasileiro** (INMETRO, fiscal, eSocial, Portaria 671);
6. **RH/ponto/folha parcial**;
7. **frota**;
8. **plataforma SaaS multiempresa** com billing de planos;
9. **camada analítica/BI/TV dashboard/observabilidade**.

O problema é que ele não está apenas grande. Ele está **superespalhado**. O sistema perdeu foco de produto.

### Minha leitura de negócio

O **core que faz sentido** para esse produto é:

* clientes e cadastros;
* equipamentos e histórico técnico;
* orçamento/proposta;
* chamado e ordem de serviço;
* agenda e execução em campo;
* calibração, certificado e compliance metrológico;
* contratos recorrentes;
* faturamento/financeiro básico;
* portal do cliente;
* PWA do técnico.

Tudo isso conversa com a proposta central do PRD: **reduzir o tempo entre execução de OS e geração de receita**.

### Onde o produto perde mão

Os módulos abaixo parecem **mais inchados do que maduros** ou estão em um lugar arquitetural ruim:

* **Billing SaaS da própria plataforma** misturado dentro do ERP do tenant;
* **RH/eSocial** com escopo grande demais para um produto cujo coração é operação técnica e metrologia;
* **frota** como módulo relativamente extenso, mas periférico para o core do produto;
* **OSINT/inteligência** com lógica mockada — não é produto sério ainda;
* **TV dashboard / CEO / analytics avançado** antes de fechar fundamentos do ciclo operacional;
* **funcionalidades de compliance vendáveis como completas**, quando o código ainda contém stub, bloqueio em produção e placeholders.

### Veredito executivo

O sistema tem base técnica respeitável em alguns pilares, mas hoje sofre de quatro doenças principais:

1. **escopo excessivo de produto**;
2. **governança fraca de arquitetura e documentação**;
3. **isolamento multiempresa bom no backend base, mas furado no offline/PWA**;
4. **maturidade desigual: partes boas convivendo com remendos, stubs e inconsistências reais**.

## 3\) Tamanho real do snapshot auditado

Contagem estrutural desta fotografia do repositório:

* **5.763 arquivos** no total;
* **3.689 arquivos PHP** no backend;
* **1.068 arquivos TypeScript/TSX** no frontend;
* **425 migrations**;
* **300 controllers**;
* **411 models**;
* **158 services**;
* **835 Form Requests**;
* **67 policies**;
* **39 enums**;
* **45 events**;
* **42 listeners**;
* **35 jobs**;
* **372 páginas frontend**;
* **167 componentes**;
* **61 hooks**;
* **26 arquivos de tipos**;
* **735 testes backend** (arquivos PHP em `backend/tests`);
* **347 testes frontend**;
* **94 ocorrências de `test.skip(...)` em E2E**, espalhadas por **30 arquivos**.

Isso importa porque o README e parte da documentação **não batem com a realidade atual** do código.

## 4\) Principais achados — por gravidade

## CRÍTICO

### C1. Falha séria de isolamento multiempresa no Service Worker (cache offline compartilhado)

**Arquivos:**

* `frontend/public/sw.js:11-13`
* `frontend/public/sw.js:40-97`
* `frontend/public/sw.js:270-278`
* `frontend/public/sw.js:328-381`
* `frontend/src/stores/auth-store.ts:124-127`
* `frontend/src/hooks/useCurrentTenant.ts:24-42`

O Service Worker usa caches globais:

* `kalibrium-api-v4`
* `kalibrium-api-meta-v1`

E faz cache de endpoints sensíveis como:

* `/api/v1/me`
* `/api/v1/work-orders`
* `/api/v1/customers`
* `/api/v1/products`
* `/api/v1/service-calls`
* `/api/v1/dashboard`
* comissões, caixa do técnico, checklists, estoque etc.

O problema não é o cache em si. O problema é que o cache **não varia por usuário, tenant ou sessão**. Ele é indexado pelo request/URL. Isso é grave em cenário de:

* troca de empresa no mesmo navegador;
* logout/login de outro usuário no mesmo dispositivo;
* uso offline após mudança de tenant;
* estação compartilhada.

Pior: o frontend só manda limpar cache no **logout** (`auth-store.ts`). Na **troca de tenant** (`useCurrentTenant.ts`) ele limpa React Query, mas **não limpa o cache do Service Worker**.

**Impacto:** vazamento de dados entre empresas/usuários em modo offline ou fallback de rede. Isso ataca diretamente a promessa de isolamento multiempresa.

**Conclusão:** hoje o isolamento multiempresa do backend é razoável; o isolamento do **PWA/offline é fraco e perigoso**.

\---

### C2. Replay de mutações offline pode reexecutar ações de um tenant com o token de outro tenant

**Arquivos:**

* `frontend/public/sw.js:396-405`
* `frontend/public/sw.js:453-467`
* `frontend/public/sw.js:487-552`
* `frontend/src/main.tsx:47-59`
* `frontend/src/hooks/useCurrentTenant.ts:24-42`

Quando a aplicação está offline, o SW salva mutações com:

* URL
* método
* headers
* body
* timestamp

Mas **não salva vínculo forte com `user\\\_id`, `tenant\\\_id`, fingerprint de sessão ou token original**.

Depois, ao sincronizar, o SW faz isto:

* pergunta a uma aba aberta qual é o token atual (`GET\\\_AUTH\\\_TOKEN`);
* recebe o token vindo de `localStorage`;
* reaplica todos os itens pendentes com **esse token atual**.

Isso significa que ações enfileiradas no contexto do **tenant A** podem ser reenviadas com o token atual do **tenant B** se o usuário trocou de empresa ou outro usuário assumiu o dispositivo.

**Impacto:** corrupção cross-tenant, gravações no tenant errado, efeitos colaterais difíceis de rastrear.

Esse é um defeito mais grave do que “mostrar dado velho”. Aqui existe risco de **escrever dado no lugar errado**.

\---

### C3. Tokens públicos sensíveis armazenados em texto puro e, em alguns fluxos, transportados por query string

**Arquivos:**

* `backend/app/Models/PortalGuestLink.php:15-25, 80-86`
* `backend/database/migrations/2026\\\_03\\\_26\\\_110849\\\_create\\\_portal\\\_guest\\\_links\\\_table.php:14-28`
* `backend/app/Models/CrmInteractiveProposal.php:34-39, 66-72`
* `backend/app/Models/Quote.php:426-445, 529-540`
* `backend/app/Http/Controllers/Api/V1/QuotePublicApprovalController.php:32-37, 83-87, 124-127`
* `backend/app/Http/Controllers/Api/V1/WorkOrderRatingController.php:37-40`

Há vários fluxos públicos baseados em token:

* guest links de portal;
* proposta pública (`magic\\\_token`);
* avaliação de OS (`rating\\\_token`);
* proposta interativa CRM.

Padrão encontrado:

* token é gerado com `Str::random(64)`;
* token é gravado em banco **em claro**;
* validação é feita com `where('token', $token)` ou `where('magic\\\_token', $magicToken)`;
* em `Quote::getPdfUrlAttribute()` o token vai na URL:
`.../public-pdf?token=...`

**Problemas concretos:**

* se o banco vaza, todos os links públicos viram credenciais prontas para uso;
* query string vaza em logs, histórico, analytics, referer e eventualmente screenshots/suporte;
* as consultas públicas são globais por design, sem tenant bind, e dependem 100% do segredo do token.

**Recomendação obrigatória:**

* armazenar apenas **hash do token** em banco;
* nunca persistir o token puro após emissão;
* remover token de query string sempre que possível;
* usar expiração curta e, por padrão, single-use;
* registrar trilha de uso mais forte (IP, UA, tentativa inválida, revogação).

\---

### C4. O frontend tem defeito real de merge/duplicação em `api.ts`

**Arquivo:** `frontend/src/lib/api.ts:262-340`

O arquivo contém duplicação literal de:

* `unwrapData`
* `getApiOrigin`
* `buildStorageUrl`
* `export default api`

Isso é erro concreto de código, não hipótese. É sintoma de merge mal resolvido.

**Impacto:** quebra de typecheck/build e evidencia fragilidade de revisão de código.

\---

## ALTO

### A1. Controller do INMETRO tem métodos que usam `$request` sem receber `Request`

**Arquivo:** `backend/app/Http/Controllers/Api/V1/InmetroController.php`

Métodos com bug direto:

* `instrumentTypes()` em `:94-97`
* `availableUfs()` em `:102-105`
* `municipalities()` em `:174-177`
* `availableDatasets()` em `:384-387`

Esses métodos chamam `$request->user()` sem parâmetro `Request $request`.

**Impacto:** 500 em runtime nesses endpoints.

Isso não é dívida técnica abstrata. É bug real e imediato.

\---

### A2. Bug de auditoria em RH: listener escreve em colunas que não batem com o schema real

**Arquivos:**

* `backend/app/Listeners/AuditHrActionListener.php:29-40`
* `backend/app/Models/AuditLog.php:18-20, 68-84`
* `backend/database/migrations/2026\\\_02\\\_07\\\_230000\\\_create\\\_rbac\\\_extensions.php:27-44`
* `backend/database/migrations/2026\\\_02\\\_07\\\_900000\\\_create\\\_audit\\\_settings\\\_tables.php:12-30`

O listener grava `audit\\\_logs` com colunas como:

* `action`
* `model\\\_type`
* `model\\\_id`

Mas o modelo/schema atual trabalha com:

* `auditable\\\_type`
* `auditable\\\_id`
* `description`
* `tenant\\\_id`
* `user\\\_agent`

Além disso, o listener não preenche `tenant\\\_id`.

Pior: o listener captura exceções e só loga erro. Ou seja, a trilha de auditoria pode **falhar silenciosamente**.

**Impacto:** compliance e rastreabilidade comprometidos exatamente em um módulo sensível.

\---

### A3. Histórico de migrations mostra forte churn e risco de ambientes divergentes

**Exemplos:**

* `audit\\\_logs` criado em dois arquivos diferentes;
* `commission\\\_goals` recriado depois com `dropIfExists`;
* `vehicle\\\_tires` criado múltiplas vezes;
* `webhooks`, `material\\\_requests`, `push\\\_subscriptions`, `equipment\\\_calibrations` também aparecem duplicados.

Foram detectadas **21 tabelas com `Schema::create(...)` duplicado** em migrations diferentes.

Casos concretos:

* `backend/database/migrations/2026\\\_02\\\_14\\\_003638\\\_create\\\_vehicle\\\_tires\\\_v2\\\_table.php:18-34` cria uma tabela relativamente completa;
* `backend/database/migrations/2026\\\_02\\\_14\\\_003739\\\_create\\\_vehicle\\\_tires\\\_table.php:18-21` cria uma versão quase vazia da mesma tabela;
* `backend/database/migrations/2026\\\_02\\\_25\\\_200000\\\_backfill\\\_fleet\\\_operational\\\_tables.php` faz remendos de colunas conforme o estado do banco.

Isso é padrão de projeto que foi sendo corrigido “por cima” em vez de consolidado.

**Impacto:**

* um ambiente novo pode nascer diferente de um ambiente antigo atualizado incrementalmente;
* troubleshooting fica caro;
* rollback e reproduzibilidade pioram;
* confiança no schema cai.

\---

### A4. Integração de cobrança/gateway é global, não verdadeiramente tenant-aware

**Arquivos:**

* `backend/config/payment.php:15-36`
* `backend/app/Services/Payment/AsaasPaymentProvider.php:25-31`
* `backend/app/Services/Payment/AsaasPaymentProvider.php:195-247`

A configuração do Asaas é única, via `.env` global:

* `ASAAS\\\_API\\\_URL`
* `ASAAS\\\_API\\\_KEY`
* `ASAAS\\\_WEBHOOK\\\_SECRET`

O provider resolve ou cria cliente no Asaas usando **essa conta global**. Na prática, todos os tenants compartilham a mesma integração PSP, salvo se houver outra camada externa não visível aqui.

Para um sistema multiempresa, isso muda a natureza do produto:

* ou é uma **plataforma centralizada que cobra em nome de todos**;
* ou deveria ser **tenant-configurable** com credenciais por empresa.

Hoje isso está no meio do caminho.

**Conclusão:** como desenho de negócio e de isolamento, isso está mal definido.

\---

### A5. Webhook de pagamento tem desenho perigoso: resolve classe dinamicamente a partir de `externalReference`

**Arquivo:** `backend/app/Http/Controllers/Api/V1/Webhooks/PaymentWebhookController.php:61-87`

No auto-create de pagamento:

* lê `externalReference` no formato `Tipo:ID`;
* monta `App\\\\Models\\\\{$type}`;
* usa `class\\\_exists`;
* faz `$payableClass::find($id)`;
* cria pagamento com `tenant\\\_id` do model encontrado.

Problemas:

* o lookup ocorre sem tenant bind no webhook público;
* há resolução dinâmica de classe a partir de string externa;
* isso depende demais da integridade do provedor e da convenção de referência;
* combinado com um PSP global, o processamento vira quase “platform-wide”, não tenant-isolado.

Eu não classifico isso como exploit comprovado aqui, mas como **desenho inseguro e frágil**.

**O certo é:** allowlist fechada por enum/mapa, metadado assinado, validação estrutural rígida e configuração de gateway coerente com a estratégia multiempresa.

\---

### A6. Segredo de webhook fiscal aceito por body/query

**Arquivo:** `backend/app/Http/Middleware/VerifyFiscalWebhookSecret.php:12-13, 28-31`

O middleware aceita o secret por:

* header `X-Fiscal-Webhook-Secret`; **ou**
* `webhook\\\_secret` via input/query.

Já o middleware genérico de webhook (`backend/app/Http/Middleware/VerifyWebhookSignature.php:28-31`) faz o correto e aceita **apenas header**, explicitamente para evitar vazamento em logs.

Ou seja, o próprio projeto já sabe o padrão certo, mas aplica um padrão mais fraco no webhook fiscal.

**Recomendação:** padronizar para header-only.

\---

## MÉDIO

### M1. `localStorage` para token mantém blast radius alto em caso de XSS

**Arquivos:**

* `frontend/src/stores/auth-store.ts:91-93`
* `frontend/src/main.tsx:54-57`
* `backend/app/Http/Middleware/InjectBearerFromCookie.php:17-23`

O projeto até suporta cookie httpOnly (`InjectBearerFromCookie`), o que é bom. Mas o frontend ainda guarda `auth\\\_token` em `localStorage` quando não está em modo cookie.

Com qualquer XSS relevante, o token fica facilmente exfiltrável.

**Conclusão:** para um sistema com dados operacionais, financeiros e multiempresa, o modo preferencial deveria ser **cookie httpOnly**, não `localStorage`.

\---

### M2. Há indícios de XSS/HTML injection em fluxos específicos do frontend

**Arquivos:**

* `frontend/src/hooks/useInmetro.ts:713-719`
* `frontend/src/components/common/QRCodeLabel.tsx:24-66`

Casos:

1. `useInmetro.ts` abre uma janela e faz `document.write(data.html)` com HTML vindo do backend.
O template atual do relatório parece escapar campos corretamente, mas o padrão do cliente é **cego**: escreve HTML bruto em janela nova.
2. `QRCodeLabel.tsx` injeta `${label}` dentro de HTML bruto em `<title>` via `document.write(...)`.
Dependendo da origem de `label`, isso pode virar vetor de XSS armazenado/refletido.

Nota positiva: há pontos do frontend usando DOMPurify corretamente, como:

* `frontend/src/pages/emails/EmailInboxPage.tsx:467-470`
* `frontend/src/components/ui/chart.tsx:96-99`

Isso mostra que o time sabe se defender, mas aplica de forma inconsistente.

\---

### M3. `HelpdeskService` ignora tenant ao alterar ticket

**Arquivos:**

* `backend/app/Services/HelpdeskService.php:13-17`
* `backend/app/Services/HelpdeskService.php:56`
* `backend/database/migrations/2026\\\_03\\\_16\\\_500000\\\_create\\\_portal\\\_tickets\\\_table.php:13-31`

O serviço carrega e atualiza `portal\\\_tickets` por `id` apenas, embora a tabela tenha `tenant\\\_id`.

Se esse serviço for chamado com um ID de outro tenant, ele pode alterar ticket indevido.

Hoje parece pouco exposto, mas como padrão é ruim.

\---

### M4. Há massa grande de testes, mas a efetividade não é comprovada nesta fotografia

Há bastante teste. Isso é bom. Mas há dois problemas:

1. o ambiente veio sem dependências, então eu **não pude validar a suíte real**;
2. os E2E têm **94 ocorrências de `test.skip(...)`** em **30 arquivos**, muitos dependentes de login, dados ou elementos de UI existentes.

Ou seja: **quantidade de teste não pode ser confundida com cobertura confiável**.

\---

### M5. A governança de análise estática existe, mas está sendo abafada por baseline gigante

**Arquivos:**

* `backend/phpstan.neon`
* `backend/app/PHPStan/Rules/TenantIdInQueriesRule.php`
* `backend/app/PHPStan/Rules/PaginateInsteadOfGetInControllersRule.php`
* `backend/phpstan-baseline.neon`

Ponto positivo: o projeto criou regras próprias para:

* exigir cuidado com `tenant\\\_id`;
* evitar `get()` em controllers onde deveria haver paginação.

Ponto negativo: a baseline tem **47.287 linhas**, com pelo menos:

* **215 ocorrências** de `kalibrium.tenantIdRequired` suprimidas;
* **175 ocorrências** de `kalibrium.paginateRequired` suprimidas.

Isso indica consciência do problema, mas também que a dívida foi **registrada, não resolvida**.

\---

### M6. Documentação está desatualizada e isso já virou risco operacional

O README informa números menores do que o código real. Exemplo:

* README: **376 migrations** → real: **425**;
* README: **620 testes backend** → real: **735**;
* README: **245 controllers** → real: **300**;
* README: **368 models** → real: **411**.

Além disso, a pasta `backend/docs/audits/` contém auditorias internas que já não refletem o estado atual em alguns pontos. Exemplo: o relatório de 2026-03-28 acusa ausência de `check.permission` em `modules-extra.php`, mas o arquivo atual já possui esses middlewares.

**Conclusão:** documentação deixou de ser fonte confiável. Isso tem custo real de onboarding, suporte e decisão de produto.

## 5\) Multiempresa / isolamento entre tenants

## O que está bom

Há uma base boa no backend:

* `BelongsToTenant` adiciona global scope por `tenant\\\_id` quando há `current\\\_tenant\\\_id`;
* `EnsureTenantScope` resolve tenant por token ability `tenant:{id}` ou tenant corrente;
* há validação de acesso do usuário ao tenant;
* o projeto usa `spatie/laravel-permission` com `teams => true` e `team\\\_foreign\\\_key => 'tenant\\\_id'`;
* na troca de tenant, o token é reemitido com ability específica do tenant.

Arquivos centrais:

* `backend/app/Models/Concerns/BelongsToTenant.php`
* `backend/app/Http/Middleware/EnsureTenantScope.php`
* `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php:227-285`
* `backend/config/permission.php`

## Onde o isolamento quebra

O problema não está tanto no CRUD padrão autenticado. Está nos **fluxos laterais**:

1. **Service Worker cache compartilhado**;
2. **fila offline reexecutada com token atual**;
3. **links públicos globais por token em texto puro**;
4. **serviços específicos usando `DB::table(...)->where('id', ...)` sem tenant**;
5. **gateway e webhooks de pagamento globalizados**.

### Síntese

Se eu tivesse que resumir em uma frase:

> O isolamento multiempresa do backend transacional parece razoável, mas o isolamento do ecossistema inteiro do produto ainda não é confiável, principalmente em PWA/offline, links públicos e pagamentos.

## 6\) Segurança

### Pontos positivos

* middleware de headers básicos existe (`SecurityHeaders`);
* há suporte a cookie auth com `InjectBearerFromCookie`;
* existe preocupação explícita com webhook signature em parte do código;
* o frontend já usa `DOMPurify` em alguns fluxos;
* existe instrumentação e observabilidade de API.

### Principais fraquezas de segurança

* autenticação com token em `localStorage` ainda é padrão viável;
* caches offline e fila offline ferem isolamento;
* tokens públicos não são hashados;
* token em query string em alguns fluxos;
* webhook fiscal aceita secret por query/body;
* resolução dinâmica de classe no webhook de pagamento;
* inconsistência de sanitização entre componentes frontend;
* muita confiança no segredo do token e pouca defesa em profundidade nos endpoints públicos.

## 7\) Organização de módulos e coerência de produto

## Módulos que fazem sentido

Fazem sentido forte para o produto atual:

* Cadastros
* Clientes
* Equipamentos
* Orçamentos
* Chamados
* Ordens de Serviço
* Agenda
* Contratos
* Calibração / certificados / INMETRO
* Financeiro básico (AP/AR, cobrança, baixa, faturamento)
* Portal do cliente
* Tech PWA
* Estoque básico relacionado à operação

## Módulos que existem, mas precisam de recorte melhor

* CRM: faz sentido, mas como **apoio** ao core, não como universo paralelo;
* Fiscal: faz sentido, mas precisa ser tratado como integração dependente, não como promessa resolvida;
* RH/ponto: faz sentido apenas no recorte mínimo ligado à operação de campo;
* Frota: faz sentido se o cliente-alvo realmente depende disso, mas hoje parece inchado para o estágio do produto.

## Módulos que eu reavaliaria seriamente

### 1\. Billing SaaS dentro do ERP do tenant

**Arquivos:**

* `backend/routes/api/billing.php`
* `backend/app/Http/Controllers/Api/V1/Billing/SaasPlanController.php`
* `backend/app/Http/Controllers/Api/V1/Billing/SaasSubscriptionController.php`

Esse módulo está no lugar errado. Billing do **produto/plataforma** não deveria ficar misturado no mesmo domínio operacional do cliente/tenant.

Além disso, várias operações estão **bloqueadas em produção** com `DomainException`, o que reforça que o módulo ainda não está maduro.

Minha recomendação é separar em:

* **backoffice de plataforma**;
* **ERP operacional do tenant**.

### 2\. RH/eSocial no escopo atual

O código mostra eventos eSocial stubados:

* `backend/app/Services/ESocialService.php:195-276`
* `backend/app/Services/ESocial/ESocialTransmissionService.php:54-62`

Se o produto não vai competir como suíte RH/compliance trabalhista de verdade, esse módulo deveria ser **reduzido ao essencial operacional**.

### 3\. OSINT / inteligência

**Arquivo:** `backend/app/Services/OsintIntelligenceService.php:24-51`

Hoje é mock. Isso não deve ser tratado como funcionalidade séria de produto.

## Problema maior de arquitetura de módulos

As rotas estão fragmentadas em muitos arquivos grandes, com nomes que já denunciam acúmulo incremental:

* `financial.php` com 419 linhas;
* `hr-quality-automation.php` com 401;
* `crm.php` com 387;
* `advanced-lots.php` com 337;
* `work-orders.php` com 283;
* `missing-routes.php` com 165.

A própria existência de `missing-routes.php` é sintoma claro de governança ruim de roteamento. Não é só nomenclatura feia. É sinal de que o mapa de domínio perdeu clareza.

No frontend também há taxonomia inconsistente:

* `operacional/`
* `operational/`
* `avancado/`
* `fleet/`
* `tech/`
* `iam/`
* `crm/`
* `tv/`

Isso parece detalhe, mas piora manutenção, onboarding e consistência mental do produto.

## 8\) Funcionalidades que parecem no lugar errado

### Estão no lugar errado ou misturadas demais

* **Billing SaaS** no domínio do tenant;
* **admin/plataforma** misturado com ERP operacional;
* **frota** talvez grande demais em relação ao core operacional/metrológico;
* **analytics/TV/CEO** antes de consolidar o fluxo operacional crítico;
* **módulos “avançados” genéricos** em arquivos como `advanced-features.php` e `advanced-lots.php`, em vez de bounded contexts claros.

### O que deveria ser reagrupado

Eu reorganizaria em macrodomínios:

1. **Core Operacional**
clientes, equipamentos, chamados, OS, agenda, checklists, contratos, técnico, portal.
2. **Core Metrológico**
calibração, certificados, pesos padrão, INMETRO, rastreabilidade.
3. **Core Receita**
orçamento, faturamento, contas a receber, cobrança, pagamentos, fiscal.
4. **Pessoas e Apoio Operacional**
ponto, férias, despesas, caixa do técnico, frota mínima.
5. **Plataforma/Admin**
tenants, planos, billing SaaS, observabilidade, integrações globais.

Hoje esses domínios estão parcialmente misturados.

## 9\) Funcionalidades novas que fazem sentido para o estágio atual

Eu não recomendaria “mais features” aleatórias. Recomendo **funcionalidades que reforçam o núcleo**:

### Prioridade alta

* purge/namespace de cache por `tenant\\\_id + user\\\_id + session\\\_id` no PWA;
* fila offline vinculada ao contexto original da sessão;
* trilha de auditoria operacional confiável e unificada;
* bloqueio/controle de link público com hash de token, expiração forte e revogação;
* painel de integridade operacional: OS concluída sem faturamento, faturamento sem cobrança, cobrança sem webhook, certificado pendente etc.;
* enforcement real de plano/módulos por tenant, se o SaaS billing permanecer.

### Prioridade média

* catálogo de capacidades por tenant/plano, com feature flags reais;
* matriz de dependências de integração por tenant (fiscal, cobrança, WhatsApp, eSocial);
* health check por tenant para saber se a empresa está “operacionalmente configurada” para emitir, cobrar e fechar ciclo;
* centro de inconsistências: links públicos ativos demais, filas offline presas, jobs com falha, webhooks sem confirmação.

### O que eu não priorizaria agora

* mais analytics “bonito”;
* mais dashboards de TV;
* mais módulos satélite;
* inteligência/IA vendida antes de resolver o básico operacional com consistência.

## 10\) Plano de correção recomendado

## 0–15 dias

1. Corrigir **Service Worker**:

   * limpar cache ao trocar tenant e ao trocar usuário;
   * ou melhor: **versionar cache por tenant/usuário/sessão**;
   * bloquear serving de cache quando contexto mudou.
2. Corrigir **fila offline**:

   * persistir `tenant\\\_id`, `user\\\_id`, fingerprint da sessão e modo de auth em cada item;
   * impedir replay se o contexto atual não coincidir;
   * invalidar fila pendente ao trocar tenant, se não houver estratégia segura.
3. Corrigir bugs concretos:

   * `frontend/src/lib/api.ts` duplicado;
   * métodos quebrados de `InmetroController`;
   * `AuditHrActionListener` incompatível com schema.
4. Endurecer links públicos:

   * hash em banco;
   * remover tokens de query string;
   * expiração curta;
   * single-use por padrão.
5. Padronizar webhook secret **apenas via header**.

## 15–45 dias

1. Revisar webhooks de pagamento:

   * remover `class\\\_exists("App\\\\\\\\Models\\\\\\\\{$type}")` dinâmico;
   * usar allowlist fechada.
2. Definir estratégia de PSP:

   * **conta única da plataforma** com regras explícitas, ou
   * **credenciais por tenant**.
3. Consolidar migrations:

   * criar baseline limpa para novos ambientes;
   * parar de remendar schema em cadeia infinita.
4. Reduzir `phpstan-baseline.neon` com meta clara, especialmente nos avisos de tenant.

## 45–90 dias

1. Separar **ERP do tenant** de **backoffice da plataforma**.
2. Reorganizar rotas por bounded context real.
3. Reescrever taxonomia de módulos/páginas para um padrão único.
4. Rebaixar ou esconder módulos ainda stubados/experimentais da superfície comercial do produto.

## 11\) Julgamento final

Minha avaliação franca:

* **base promissora**: sim;
* **produto com identidade forte no nicho**: sim;
* **arquitetura limpa e controlada**: não;
* **multiempresa realmente confiável ponta a ponta**: não;
* **pronto para prometer tudo o que o README/PRD insinuam**: não.

O maior risco aqui não é “um bug isolado”. O maior risco é este:

> o sistema está tentando ser grande demais antes de ficar sólido no que realmente paga a conta.

Se eu fosse auditor contratado para decisão executiva, meu parecer seria:

### Parecer

* **Aprovado com restrições severas** para continuidade técnica.
* **Não aprovado** para sustentar promessa ampla de produto “enterprise completo” do jeito que está.
* **Ação imediata obrigatória** em PWA/offline multiempresa, tokens públicos, auditoria de RH, bugs de controller e governança de migrations.

Em resumo: o Kalibrium tem potencial real como plataforma vertical de operação técnica e metrologia, mas precisa **encolher o discurso, endurecer a segurança e reorganizar a casa**.
