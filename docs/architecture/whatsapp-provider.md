# Arquitetura — Provider WhatsApp Business

> **Status:** Decisão provisória registrada (2026-04-10) — aguarda ratificação formal do dono antes de iniciar Fase 1 do plano Agente CEO IA
> **Decisão provisória:** **Meta WhatsApp Cloud API** em produção + **Evolution API** em dev/homologação. Fallback em escada: 360dialog → Gupshup → Twilio → Infobip. Detalhes em §6.
> **Criado:** 2026-04-10
> **Owner:** Plataforma / Agente CEO IA
> **Contexto:** Plano `docs/plans/agente-ceo-ia.md` — Fase 5 bloqueada até ratificação + conclusão do onboarding Meta (conta Business Manager, verificação de negócio, templates HSM aprovados)
> **Ação imediata:** ratificar decisão → criar conta Meta Business Manager → iniciar verificação de negócio → submeter catálogo HSM inicial (10 templates, ver §9). **Este é um pré-requisito da Fase 1, não ação paralela.**

---

## 1. Objetivo

Definir o provider WhatsApp Business que o Kalibrium ERP usará para:
- Inbound: receber mensagens de clientes via webhook (fluxo atendimento, suporte, leads)
- Outbound: enviar mensagens proativas (cobrança, lembrete, proposta, certificado, alertas)
- Envio de mídia: PDF (certificado, boleto, proposta), imagens
- Templates HSM (Highly Structured Messages) para comunicação fora da janela de 24h

O provider é o ponto de integração crítico do Agente CEO IA com o canal de maior engajamento no Brasil. Má escolha aqui = retrabalho + risco de ban do número + impacto operacional.

---

## 2. Requisitos Funcionais

| # | Requisito | Prioridade |
|---|-----------|------------|
| F1 | Enviar e receber mensagens de texto | MUST |
| F2 | Enviar documentos PDF (certificado, boleto, proposta) | MUST |
| F3 | Enviar templates HSM aprovados pelo Meta | MUST |
| F4 | Webhook de entrada com assinatura/validação | MUST |
| F5 | Suporte a múltiplos números (multi-tenant) | MUST |
| F6 | Status de entrega (sent/delivered/read/failed) | MUST |
| F7 | Quality Rating monitorável (green/yellow/red) | MUST |
| F8 | Envio de imagens e áudio | SHOULD |
| F9 | Grupos (inbound apenas) | COULD |
| F10 | Lista de contatos sincronizada | WON'T (v1) |

## 3. Requisitos Não-Funcionais

| # | Requisito | Alvo |
|---|-----------|------|
| NF1 | Latência de envio (P95) | < 2s |
| NF2 | Latência de webhook → processamento | < 500ms |
| NF3 | Disponibilidade | >= 99.5% |
| NF4 | Custo por mensagem | < R$ 0,10 (conversa utility) |
| NF5 | Suporte em português | SIM |
| NF6 | SLA de resposta do provider | < 4h em incidentes críticos |
| NF7 | LGPD — armazenamento em território brasileiro ou UE | SIM |

---

## 4. Opções Avaliadas

### 4.1 Evolution API (self-hosted)

**Descrição:** Wrapper open-source sobre WhatsApp Web (Baileys) e WhatsApp Business Cloud API oficial do Meta. Self-hosted em container próprio.

**Prós:**
- Custo baixo: apenas infra (VPS ~R$ 50/mês para 1-2 tenants)
- Controle total sobre dados (LGPD friendly)
- Suporta tanto a API oficial do Meta Cloud quanto a WebSocket do WhatsApp Web
- Multi-instância nativa (multi-tenant via API keys)
- Extensível (pode criar middlewares customizados)
- Comunidade ativa, código auditável
- Sem lock-in

**Contras:**
- Operacional: precisa manter container, Redis, MongoDB, monitorar uptime
- Modo WhatsApp Web (não-oficial) tem risco de ban do Meta — só o modo **Cloud API** é oficial e seguro para produção
- Onboarding: requer conta Meta Business Manager, verificação de negócio, aprovação de templates
- Sem SLA: se quebra, só você conserta
- Debugging de problemas de entrega pode ser complexo

**Custos estimados (modo Cloud API oficial):**
- Infra: R$ 50-150/mês (VPS ou container dedicado)
- Mensagens: tabela oficial do Meta (conversas utility ~R$ 0,04-0,08 por conversa de 24h)
- Dev/ops: ~4-8h/mês manutenção preventiva

### 4.2 Z-API (SaaS brasileiro)

**Descrição:** SaaS brasileiro que expõe WhatsApp Business como REST API. Multi-tenant nativo, dashboard próprio.

**Prós:**
- Setup em minutos (scan QR code + API ready)
- Dashboard próprio para troubleshooting
- Suporte em português, atendimento humano
- SLA documentado
- Multi-instância nativa
- Sem preocupação com infra
- Documentação e SDK maduros

**Contras:**
- Custo fixo mensal por número (~R$ 99-199/mês por instância)
- Baseado principalmente em WhatsApp Web (Baileys) — risco latente de ban do Meta se usar número não verificado
- Dados trafegam pelos servidores do Z-API (análise LGPD necessária)
- Vendor lock-in (migração exige reescrita da camada de integração, embora a interface `WhatsAppProvider` mitigue isso)
- Limites de RPS e features dependem do plano contratado
- Quality Rating não é diretamente gerenciável pelo cliente final

**Custos estimados:**
- Plano base: ~R$ 99/mês por instância
- Plano profissional: ~R$ 199/mês por instância (recomendado para negócio crítico)
- Sem custos variáveis por mensagem (ilimitado no plano)

### 4.3 BSPs Oficiais (Meta Business Solution Providers)

**Descrição:** parceiros oficiais do Meta que oferecem WhatsApp Cloud API gerenciado, com onboarding simplificado, templates pré-aprovados e SLA comercial. Servem como plano B caso a verificação direta do Meta emperre.

| BSP | Pontos fortes | Pontos fracos | Custo aproximado |
|-----|---------------|---------------|------------------|
| **Gupshup** | Onboarding rápido (~3 dias), BSP Tier 1, dashboards, templates assistidos, suporte 24/7 | Custo por mensagem + fee mensal (~$50-100), lock-in moderado | Fee plataforma + tarifa Meta + markup BSP (~10-20%) |
| **360dialog** | BSP Tier 1 europeu, LGPD/GDPR-first, API transparente próxima ao Cloud API oficial, sem markup por mensagem | Setup documentado em inglês/alemão, menos suporte pt-BR | ~€49/mês por número + tarifa Meta oficial (sem markup) |
| **Twilio** | Maturidade altíssima, SDK PHP oficial, integração com Twilio Studio/Flex, confiabilidade enterprise | Custo mais alto, markup por mensagem, overhead de ser multi-canal | Fee + tarifa Meta + markup (~30-50%) |
| **Infobip** | BSP global, integração com omnichannel, compliance forte | Pricing enterprise, contrato anual típico | Sob consulta (normalmente > R$ 500/mês) |

**Quando considerar um BSP:**
- Verificação Meta direta atrasada > 4 semanas
- Dono quer terceirizar gestão de templates HSM
- Precisa suporte humano em português (Gupshup tem pt-BR)
- Compliance rigoroso exigido por auditoria (360dialog LGPD/GDPR-first)

**Recomendação de fallback ordenada:**
1. **360dialog** — mais próximo do Cloud API oficial, sem markup, LGPD-first
2. **Gupshup** — onboarding mais rápido, suporte pt-BR
3. **Twilio** — só se já existir contrato Twilio no tenant

### 4.4 Meta WhatsApp Cloud API (direto)

**Descrição:** API oficial do Meta, sem intermediários. Maior confiabilidade e menor risco regulatório.

**Prós:**
- Oficial — zero risco de ban por violação de ToS
- SLA do próprio Meta
- Quality Rating direto no Meta Business
- Templates aprovados diretamente no Meta Business Manager
- Webhooks confiáveis assinados por X-Hub-Signature-256
- Melhor opção para escalar (20+ tenants)

**Contras:**
- Onboarding complexo: verificação de negócio, número comercial verificado, display name approval
- Sem SDK PHP oficial — cliente HTTP nativo (mas já é a abordagem do plano)
- Precisa construir o webhook handler do zero
- Custo por conversa (não por mensagem) conforme tabela do Meta
- Limite de rate inicial baixo até aumentar tier

**Custos estimados (Brasil, 2026):**
- Conversas utility iniciadas pelo negócio: ~R$ 0,04-0,08
- Conversas marketing iniciadas pelo negócio: ~R$ 0,12-0,18
- Conversas iniciadas pelo usuário (service): gratuitas nos primeiros 1.000/mês
- Sem custo fixo mensal

---

## 5. Comparativo

| Critério | Evolution API | Z-API | 360dialog (BSP) | Gupshup (BSP) | Meta Cloud API |
|----------|---------------|-------|-----------------|---------------|----------------|
| Custo mensal base | R$ 50-150 (infra) | R$ 99-199 | ~€49/número | ~$50-100 + tarifa | R$ 0 |
| Custo variável | Tabela Meta | Ilimitado | Tabela Meta (sem markup) | Tabela Meta + markup | Tabela Meta |
| Risco de ban | Baixo (se Cloud API) / Médio (se Baileys) | Médio (Baileys) | Zero (oficial Meta) | Zero (oficial Meta) | Zero |
| Time-to-market | 1-2 semanas | 1-2 dias | ~1 semana | ~3 dias | 2-4 semanas |
| Manutenção | Alta | Zero | Zero | Zero | Média |
| LGPD | Ótima (self-hosted) | Média (terceiro) | Ótima (GDPR-first) | Boa | Boa |
| Escalabilidade | Média-alta | Média | Alta | Alta | Alta |
| Controle | Total | Baixo | Alto | Médio | Alto |
| Suporte | Comunidade | Humano pt-BR | EN/DE | Humano pt-BR 24/7 | Meta (en) |
| Multi-tenant | Nativo | Nativo | Nativo | Nativo | Manual |

---

## 6. Decisão Recomendada

**Recomendação:** **Meta WhatsApp Cloud API** como camada canônica de produção, com **Evolution API** em homologação/dev para iterações rápidas.

**Justificativa:**
1. **Zero risco de ban** — é o canal mais crítico do Agente CEO IA. Ban = operação parada. Não há custo de infra nem de SaaS que compense esse risco.
2. **Custo por conversa alinhado com volume real** — para negócios pequenos/médios, o modelo "pago por conversa" do Meta tende a ser mais barato que R$ 99+/mês fixo por instância (Z-API).
3. **Quality Rating gerenciado no Meta Business** — o dono do negócio tem visibilidade direta da saúde do número, sem depender de dashboard de terceiro.
4. **Alinhamento com arquitetura do plano** — o plano já prevê HTTP client nativo Laravel (sem SDK de terceiro) e webhook handler customizado, então o esforço incremental para ir direto ao Meta é pequeno.
5. **LGPD e auditoria** — webhooks assinados por SHA-256, dados na infra do Meta (padrão internacional), sem terceiro intermediando.

**Trade-off aceito:** Onboarding mais lento (2-4 semanas incluindo verificação de negócio). **Mitigação:** iniciar verificação **durante a Fase 1** do plano do Agente CEO IA, em paralelo com a implementação da infraestrutura.

**Alternativa fallback (escada de escalação):** Se a verificação do Meta for bloqueada ou demorar mais de 4 semanas, seguir esta ordem:
1. **360dialog (BSP Tier 1)** — onboarding ~1 semana, sem markup por mensagem, LGPD/GDPR-first, mesma tarifa oficial Meta. É a escolha com menor impacto de custo e sem lock-in técnico (API espelha Cloud API oficial).
2. **Gupshup (BSP Tier 1)** — se precisar suporte humano em pt-BR e onboarding ainda mais rápido (~3 dias). Aceitar markup moderado.
3. **Evolution API em modo Cloud API oficial** (não Baileys) — somente se nenhum BSP aceitar o negócio ou se houver exigência explícita de self-hosted por auditoria.

A interface `WhatsAppProvider` (definida na Fase 1B do plano) permite trocar o adapter sem impacto no resto do sistema — cada fallback é um adapter concreto implementado apenas quando acionado.

> **Decisão provisória ativa:** Este documento registra **Meta WhatsApp Cloud API** como escolha provisória. A implementação da interface `WhatsAppProvider` (Fase 1B do plano) já pode começar assumindo esta decisão. A ratificação formal do dono (com assinatura no changelog §13) desbloqueia o início efetivo da Fase 5 e a criação da conta Meta Business Manager. Se a ratificação for por uma alternativa da escada de fallback, apenas o adapter muda — a interface e o código do agente permanecem.

> **Onde isto vive no plano:** a ratificação + onboarding Meta é item explícito da subfase **1A.0** em `docs/plans/agente-ceo-ia.md` (não é "ação paralela" — é pré-requisito bloqueante da Fase 5, devendo iniciar na primeira semana da Fase 1A). Sem essa amarração no plano, o prazo de 2-4 semanas do Meta vira surpresa quando a Fase 5 for iniciar.

---

## 7. Arquitetura de Integração (Ports & Adapters)

### 7.1 Interface (port) — já definida na Fase 1B do plano

```php
namespace App\Contracts;

interface WhatsAppProvider
{
    /**
     * Envia mensagem livre (texto). SOMENTE permitido dentro da janela de 24h
     * (Customer Service Window) — fora dela, o adapter DEVE lançar
     * WhatsAppFreeMessageOutsideWindowException, sem chamar o provider remoto.
     *
     * O adapter consulta `agent_conversations.last_inbound_at` antes de enviar
     * (fail-closed). Quem chama (ToolExecutor) também valida — defesa em
     * profundidade, mas o adapter é a última linha.
     */
    public function sendText(
        string $to,
        string $message,
        ?string $idempotencyKey = null
    ): WhatsAppSendResult;

    /**
     * Envia documento (PDF/imagem). Mesma regra de janela 24h do sendText.
     */
    public function sendDocument(
        string $to,
        string $documentUrl,
        string $filename,
        ?string $caption = null,
        ?string $idempotencyKey = null
    ): WhatsAppSendResult;

    /**
     * Envia template HSM aprovado pelo Meta. Único método permitido fora da
     * janela de 24h. O adapter NÃO valida janela aqui (template é justamente
     * a saída para fora da janela), mas valida que `templateName` está no
     * catálogo aprovado em `config/whatsapp-templates.php`.
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $languageCode,
        array $parameters = [],
        ?string $idempotencyKey = null
    ): WhatsAppSendResult;

    public function getMessages(string $contactNumber, int $limit = 20): array;

    public function verifyWebhookSignature(string $payload, string $signature): bool;

    public function parseWebhookPayload(array $payload): WhatsAppInboundMessage;

    public function getQualityRating(): string; // 'green' | 'yellow' | 'red' | 'unknown'
}
```

**Regra inegociável (fail-closed em duas camadas):**

1. **ToolExecutor (camada 1)** — antes de invocar `sendText`/`sendDocument`, consulta `last_inbound_at` da conversa. Se `now - last_inbound_at >= 24h`, NÃO chama a tool de mensagem livre — retorna ao agente com erro `OUTSIDE_24H_WINDOW` indicando que a única saída é `EnviarWhatsAppTemplate` com um template HSM válido.
2. **Adapter (camada 2 — última linha)** — o `MetaCloudApiAdapter` (e demais adapters) **revalida** a janela dentro de `sendText`/`sendDocument`. Se a janela já fechou (race condition entre check e send), lança `WhatsAppFreeMessageOutsideWindowException` SEM chamar o provider remoto. Mensagem livre fora da janela é violação direta de Política Meta — o código tem que ser fisicamente incapaz de cometer.

A camada 2 existe porque entre o check do ToolExecutor e a chamada efetiva ao Meta podem se passar segundos; um cliente que enviou a última mensagem 23h59min atrás pode cruzar a janela no meio do envio. O adapter é o último portão.

### 7.2 Adapters concretos

Implementar um dos adapters abaixo conforme decisão final:

- `App\Services\WhatsApp\Adapters\MetaCloudApiAdapter` — recomendado (produção direta)
- `App\Services\WhatsApp\Adapters\ThreeSixtyDialogAdapter` — fallback BSP Tier 1 (sem markup, LGPD-first)
- `App\Services\WhatsApp\Adapters\GupshupAdapter` — fallback BSP Tier 1 com suporte pt-BR
- `App\Services\WhatsApp\Adapters\EvolutionApiAdapter` — fallback self-hosted (modo Cloud API oficial)
- `App\Services\WhatsApp\Adapters\ZApiAdapter` — apenas se decisão mudar para SaaS Baileys (não recomendado)

Cada adapter é um binding no `AgentServiceProvider`, selecionado via `config('whatsapp.provider')`.

### 7.3 Configuração (`config/whatsapp.php`)

```php
return [
    'provider' => env('WHATSAPP_PROVIDER', 'meta_cloud'), // meta_cloud | threesixty_dialog | gupshup | evolution | zapi

    'meta_cloud' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'webhook_app_secret' => env('WHATSAPP_WEBHOOK_APP_SECRET'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    ],

    'evolution' => [
        'base_url' => env('EVOLUTION_API_URL'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'instance' => env('EVOLUTION_INSTANCE'),
    ],

    'zapi' => [
        'instance_id' => env('ZAPI_INSTANCE_ID'),
        'token' => env('ZAPI_TOKEN'),
        'client_token' => env('ZAPI_CLIENT_TOKEN'),
    ],

    'threesixty_dialog' => [
        'api_key' => env('THREESIXTY_API_KEY'),
        'phone_number_id' => env('THREESIXTY_PHONE_NUMBER_ID'),
        'base_url' => env('THREESIXTY_BASE_URL', 'https://waba-v2.360dialog.io'),
    ],

    'gupshup' => [
        'api_key' => env('GUPSHUP_API_KEY'),
        'app_name' => env('GUPSHUP_APP_NAME'),
        'source_number' => env('GUPSHUP_SOURCE_NUMBER'),
        'base_url' => env('GUPSHUP_BASE_URL', 'https://api.gupshup.io/wa/api/v1'),
    ],

    'rate_limit' => [
        'messages_per_hour_per_contact' => 5,
        'inbound_messages_per_minute_per_contact' => 10,
        'flood_threshold_per_minute' => 50,
    ],

    'business_hours' => [
        'start' => env('WHATSAPP_BUSINESS_START', '08:00'),
        'end' => env('WHATSAPP_BUSINESS_END', '18:00'),
        'timezone' => env('WHATSAPP_BUSINESS_TZ', 'America/Sao_Paulo'),
    ],

    'quality_rating_check_interval_minutes' => 30,
];
```

---

## 8. Ações Imediatas (Fase 1 do plano do Agente)

Estas ações devem ser iniciadas **agora**, em paralelo à Fase 1 do plano do Agente CEO IA. Não esperar a Fase 5.

| # | Ação | Responsável | Prazo | Status |
|---|------|-------------|-------|--------|
| A1 | Criar conta Meta Business Manager (se não existir) | Dono | Semana 1 | PENDENTE |
| A2 | Iniciar verificação de negócio no Meta (Business Verification) | Dono | Semana 1-2 | PENDENTE |
| A3 | Registrar número comercial no WhatsApp Business Platform | Dono + Plataforma | Semana 2 | PENDENTE |
| A4 | Configurar display name e perfil business | Dono | Semana 2 | PENDENTE |
| A5 | Submeter catálogo inicial de templates HSM para aprovação | Plataforma | Semana 2-3 | PENDENTE |
| A6 | Configurar webhook handler com verify token | Plataforma | Semana 3 | PENDENTE |
| A7 | Testar envio sandbox com número de teste do Meta | Plataforma | Semana 3 | PENDENTE |
| A8 | Documentar compliance (`docs/compliance/whatsapp-business.md`) | Plataforma | Semana 1 | ✅ FEITO |
| A9 | Homologar Evolution API local para dev | Plataforma | Semana 2 | PENDENTE |

---

## 9. Catálogo Inicial de Templates HSM

Submeter ao Meta para aprovação (prazo Meta: 1-24h normalmente, mas pode levar dias em categorias marketing).

| Nome | Categoria | Uso | Idioma |
|------|-----------|-----|--------|
| `kalibrium_os_agendada` | UTILITY | Confirma agendamento de OS com data/hora/técnico | pt_BR |
| `kalibrium_os_concluida` | UTILITY | Notifica conclusão de OS com link do relatório | pt_BR |
| `kalibrium_certificado_pronto` | UTILITY | Envia certificado de calibração em PDF | pt_BR |
| `kalibrium_fatura_emitida` | UTILITY | Notifica emissão de fatura/NFS-e | pt_BR |
| `kalibrium_cobranca_vencimento` | UTILITY | Lembrete de vencimento D-3 | pt_BR |
| `kalibrium_cobranca_atraso` | UTILITY | Aviso de atraso D+1, D+7 | pt_BR |
| `kalibrium_recalibracao_lembrete` | UTILITY | 60d antes do vencimento da calibração | pt_BR |
| `kalibrium_proposta_enviada` | MARKETING | Envia proposta comercial | pt_BR |
| `kalibrium_reativacao_cliente` | MARKETING | Cliente inativo > 6 meses | pt_BR |
| `kalibrium_pesquisa_satisfacao` | UTILITY | Pesquisa NPS pós-atendimento | pt_BR |

> **Importante:** templates `MARKETING` têm custo maior e quality rating mais sensível. Usar `UTILITY` sempre que possível.

---

## 10. Plano B (se aprovação Meta atrasar)

Se a verificação de negócio ou aprovação de templates atrasar mais de 4 semanas, seguir **escada de escalação**:

1. **Continuar Fases 1-4 do plano normalmente** (não bloqueiam WhatsApp)
2. **Tentar BSP Tier 1 em ordem de preferência:**
   - **360dialog** primeiro (sem markup, LGPD-first, API quase idêntica ao Cloud API oficial). Onboarding ~1 semana.
   - **Gupshup** se precisar suporte pt-BR e onboarding mais rápido (~3 dias).
   - BSPs fazem intermediação oficial com Meta, então Quality Rating e templates HSM continuam válidos.
3. **Se BSPs recusarem o negócio OU auditoria exigir self-hosted:** implementar Fase 5 em modo parcial com **Evolution API (modo Cloud API oficial, não Baileys)**:
   - Apenas inbound (webhook do Evolution)
   - Outbound limitado à janela de 24h (respostas a mensagens recebidas, sem templates)
   - Coleta dados reais para validar fluxo de conversação
4. **Quando Meta aprovar (ou quando decidir ficar no BSP):** trocar adapter (zero impacto no resto do código graças à interface `WhatsAppProvider`) e habilitar outbound proativo
5. **Comunicar ao dono** semanalmente o status da aprovação Meta e da escolha de fallback ativa

---

## 11. Monitoramento e SLOs

| Métrica | Alvo | Alerta |
|---------|------|--------|
| Quality Rating | `green` | `yellow` → notificar dono; `red` → pausar envios proativos |
| Taxa de entrega | >= 98% | < 95% → investigar |
| Latência de envio P95 | < 2s | > 5s → investigar |
| Taxa de block/report | < 0.5% | >= 1% → pausar marketing |
| Templates rejeitados | 0 | >= 1 → revisar catálogo |
| Webhook failures | < 0.1% | >= 1% → investigar assinatura/conectividade |
| Custo por conversa | < R$ 0,10 | > R$ 0,15 → revisar distribuição utility/marketing |

Dashboards conforme **Fase 10.3** (Observabilidade de Latência) e **Fase 11** (Dashboard do Agente) do plano.

---

## 12. Referências

- Meta WhatsApp Business Cloud API: https://developers.facebook.com/docs/whatsapp/cloud-api
- Meta Business Verification: https://www.facebook.com/business/help/1095661473946872
- Catálogo oficial de categorias de mensagem: https://developers.facebook.com/docs/whatsapp/pricing
- LGPD e WhatsApp: Guia ANPD sobre tratamento de dados em canais de mensageria
- Evolution API: https://github.com/EvolutionAPI/evolution-api
- Z-API: https://z-api.io/docs
- 360dialog (BSP Tier 1): https://docs.360dialog.com/
- Gupshup (BSP Tier 1): https://docs.gupshup.io/
- Twilio WhatsApp: https://www.twilio.com/docs/whatsapp
- Infobip WhatsApp: https://www.infobip.com/docs/whatsapp

---

## 13. Histórico de Revisões

| Versão | Data | Autor | Mudanças |
|--------|------|-------|----------|
| v1 | 2026-04-10 | Plataforma | Documento inicial com comparativo e recomendação Meta Cloud API |
| v1.1 | 2026-04-10 | Plataforma | Adicionados BSPs Tier 1 (360dialog, Gupshup, Twilio, Infobip) como escada de fallback; escada de escalação reformulada (360dialog → Gupshup → Evolution) |
| v1.2 | 2026-04-10 | Plataforma | Status elevado de "Decisão pendente" para "Decisão provisória registrada". Meta Cloud API passa a ser a escolha assumida pela implementação da Fase 1B (interface `WhatsAppProvider`) enquanto a ratificação formal do dono é obtida. §6 reforça que a ratificação desbloqueia o onboarding Meta, não a implementação da interface. Elevado a pré-requisito (não ação paralela) da Fase 1 do plano do Agente CEO IA |
| **Ratificação formal** | _pendente_ | _pendente_ | Assinatura do dono confirmando Meta Cloud API como escolha de produção. Até esta linha ser preenchida, considerar a decisão como provisória. |
