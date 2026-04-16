# Compliance — WhatsApp Business no Kalibrium ERP

> **Status:** Ativo
> **Criado:** 2026-04-10
> **Owner:** Plataforma + Jurídico
> **Aplicação:** obrigatório antes de qualquer envio via WhatsApp em produção (Fase 5 do plano `docs/plans/agente-ceo-ia.md`)
> **Base legal:** LGPD (Lei 13.709/2018), Marco Civil da Internet (Lei 12.965/2014), Meta WhatsApp Business Policy, Meta Commerce Policy

---

## 1. Escopo

Este documento regula o uso do canal WhatsApp Business pelo Kalibrium ERP, tanto para mensagens humanas quanto para mensagens automatizadas enviadas pelo **Agente CEO IA**. Aplica-se a:

- Todos os tenants do Kalibrium ERP que utilizem o canal WhatsApp
- Todos os fluxos inbound (cliente → sistema) e outbound (sistema → cliente, cliente → cliente interno)
- Todas as mensagens enviadas por humanos, jobs, listeners e Agente IA

**Descumprimento implica:**
- Risco de banimento do número comercial pelo Meta (perda operacional imediata)
- Sanções administrativas da ANPD (até R$ 50 milhões ou 2% do faturamento)
- Ação judicial por parte do titular dos dados
- Perda de confiança do cliente

---

## 2. Princípios Inegociáveis

1. **Consentimento explícito** antes de qualquer mensagem proativa.
2. **Opt-out imediato** e gratuito, sem fricção, com confirmação ao titular.
3. **Minimização de dados** — não coletar além do necessário para a finalidade.
4. **Finalidade específica** — mensagem só pode ser usada para o propósito consentido.
5. **Segurança** — TLS em trânsito, acesso restrito por RBAC, logs auditáveis.
6. **Transparência** — cliente sabe quem está falando, por quê, e como parar.
7. **Retenção limitada** — conteúdo não persiste além do necessário.

---

## 3. Base Legal (LGPD)

### 3.1 Fundamento do tratamento

| Tipo de mensagem | Base legal (Art. 7 LGPD) | Observação |
|------------------|-------------------------|------------|
| Confirmação de OS agendada | Execução de contrato (V) | Cliente tem OS ativa |
| Envio de certificado/relatório | Execução de contrato (V) | Cumprimento de obrigação contratada |
| Cobrança de fatura | Legítimo interesse (IX) + execução de contrato (V) | Registrar avaliação de legítimo interesse |
| Lembrete de recalibração | Legítimo interesse (IX) | Cliente existente, sem caráter publicitário |
| Proposta comercial a lead | **Consentimento (I)** | Exige opt-in explícito |
| Reativação de cliente inativo | Legítimo interesse (IX) | Avaliar: balanceamento direito vs interesse |
| Pesquisa de satisfação NPS | Legítimo interesse (IX) | Sem dados sensíveis |
| Mensagem de marketing genérica | **Consentimento (I)** | Exige opt-in explícito |

### 3.2 Avaliação de Legítimo Interesse (LIA)

Para mensagens baseadas em legítimo interesse, registrar em `docs/compliance/lia-whatsapp.md` (a criar quando necessário):
- Finalidade legítima
- Necessidade do tratamento
- Balanceamento de direitos do titular
- Medidas de mitigação (opt-out, transparência)

### 3.3 Dados pessoais tratados

| Dado | Finalidade | Retenção |
|------|------------|----------|
| Telefone (contact_identifier) | Identificação no canal | Enquanto houver contrato ativo + 5 anos após rescisão (obrigação fiscal) |
| Nome do contato | Personalização | Idem |
| Conteúdo da mensagem | Atendimento/auditoria | **Máximo 90 dias** (mensagens operacionais) ou **5 anos** (se vinculada a decisão fiscal) |
| Metadados de entrega | Operação | 90 dias |
| Opt-in/opt-out timestamps | Prova de consentimento | Enquanto houver contrato + 5 anos |

---

## 4. Opt-in e Opt-out

### 4.1 Opt-in

**É obrigatório registrar consentimento antes de enviar qualquer mensagem proativa para contatos que não sejam clientes com contrato ativo.**

**Formas válidas de opt-in:**
1. Cliente envia mensagem iniciando conversa (aceita implicitamente janela 24h, mas **não** autoriza marketing)
2. Cliente assina contrato/ordem de serviço que inclui cláusula específica de comunicação via WhatsApp (clausula destacada, não genérica)
3. Cliente confirma opt-in em formulário web/app com checkbox não pré-marcado
4. Cliente responde "SIM" a um convite explícito para receber mensagens (registrado em log)

**Forma inválida:**
- Checkbox pré-marcado
- Consentimento genérico em termos de uso
- Opt-in "por padrão" ao criar cliente
- Opt-in via terceiros sem base legal para compartilhamento

**Registro obrigatório em `whatsapp_contacts`:**
```sql
opted_in_at TIMESTAMP NULL     -- momento do consentimento
opted_in_source VARCHAR(50)    -- contract | form | manual | reply | implicit_24h
opted_in_evidence JSON         -- { ip, user_agent, document_id, message_id }
```

### 4.2 Opt-out

**É obrigatório processar opt-out imediatamente.**

**Gatilhos de opt-out:**
- Palavras-chave (case-insensitive, qualquer posição na mensagem):
  - `SAIR`, `PARAR`, `STOP`, `CANCELAR`, `DESCADASTRAR`, `NÃO QUERO MAIS`, `UNSUBSCRIBE`
- Bloqueio do número comercial pelo cliente (detectado via status `failed: user_blocked`)
- Quality Rating do número cai para "red" (pausar todos os envios proativos do tenant)

**Ação imediata ao receber opt-out:**
1. Marcar `whatsapp_contacts.opted_out_at = NOW()` e `opted_out_reason`
2. Cessar **todos** os envios proativos (outbound) para aquele contato imediatamente
3. Enviar uma única mensagem de confirmação: "Recebemos sua solicitação. Você não receberá mais mensagens automáticas. Para retomar, envie INICIAR."
4. Permitir apenas respostas a mensagens **iniciadas pelo cliente** (janela 24h), sem caráter promocional
5. Log em `agent_decisions` com `action_type=opt_out_processed`

**Opt-in novamente:** cliente pode enviar `INICIAR` para retomar. Isso gera novo consentimento com timestamp atualizado.

### 4.3 Implementação técnica

- Listener: `WhatsAppInboundMessageReceived` → verifica palavras-chave de opt-out **antes** de passar ao Agente
- Tool do Agente: `EnviarWhatsApp` DEVE consultar `opted_out_at` antes de enviar. Se não nulo → rejeitar envio e logar
- Migration: adicionar `opted_out_at`, `opted_out_reason`, `opted_in_at`, `opted_in_source`, `opted_in_evidence` em `whatsapp_contacts`
- Testes obrigatórios:
  - Palavra-chave SAIR → opt-out processado + confirmação enviada
  - Envio após opt-out → rejeitado + logado
  - Opt-out não afeta respostas a mensagens do cliente na janela 24h
  - INICIAR após opt-out → novo opt-in registrado

---

## 5. Janela de 24h (Customer Service Window)

**Regra Meta:** após o cliente enviar uma mensagem, o negócio tem **24h** para responder livremente com texto, mídia e qualquer conteúdo relevante ao atendimento. Após 24h, só é possível enviar **templates HSM aprovados**.

### 5.1 Implementação

- Rastrear `last_inbound_at` em `agent_conversations`
- Antes de enviar mensagem outbound, verificar:
  - Se `now - last_inbound_at < 24h` → enviar livre
  - Se `now - last_inbound_at >= 24h` → obrigatório usar template HSM aprovado
- Nunca "dar um jeitinho" de enviar fora da janela sem template. É violação direta da política Meta.

### 5.2 Templates HSM

- Todos os templates de mensagens proativas (fora da janela 24h) devem ser aprovados previamente pelo Meta no Business Manager.
- Catálogo mantido em `config/whatsapp-templates.php` com nome, categoria, idioma, variáveis e versão aprovada.
- Categorias utilizadas:
  - `UTILITY` — confirmações, atualizações transacionais, status de OS/fatura/certificado
  - `MARKETING` — propostas, promoções, reativação (somente com opt-in explícito)
  - `AUTHENTICATION` — não utilizado pelo Kalibrium (não há fluxo de 2FA por WhatsApp)
- Templates rejeitados pelo Meta devem ser analisados e reescritos; nunca reenviar o mesmo texto.

### 5.3 Regras de conteúdo

- Não incluir links encurtados (bit.ly, tinyurl) — Meta penaliza
- Não prometer benefícios sem base real
- Não usar linguagem alarmista/pressão ("ÚLTIMA CHANCE!", "AGORA OU NUNCA")
- Respeitar o tom descritivo e informativo, especialmente em `UTILITY`
- Incluir sempre o nome do negócio e instrução de opt-out em mensagens marketing

---

## 6. Horário Comercial e Frequência

### 6.1 Horário permitido para envios proativos

- **Dias úteis (seg-sex):** 08:00 às 18:00 (horário local do cliente, quando conhecido; do tenant caso contrário)
- **Sábado:** 09:00 às 13:00 (somente utility, nunca marketing)
- **Domingo e feriados nacionais:** proibido envio proativo (exceto emergências críticas com aprovação humana)

Mensagens geradas pelo Agente CEO IA fora do horário devem ser **agendadas automaticamente** para o próximo horário válido.

### 6.2 Frequência

- **Máximo 5 mensagens proativas por hora por contato** (rate limit técnico)
- **Máximo 10 mensagens proativas por dia por contato**
- **Máximo 1 mensagem marketing por semana por contato**
- Se exceder: enfileirar para o próximo ciclo válido, nunca descartar

### 6.3 Proteção contra flood de entrada

- Webhook inbound: max 10 mensagens/minuto por contato (throttle)
- Se exceder: enfileirar com delay
- Se >50 msgs/minuto do mesmo contato: pausar processamento 5min + notificar dono (anomalia)

---

## 7. Quality Rating e Saúde do Número

### 7.1 Monitoramento

- Job `AgentWhatsAppQualityCheckJob` roda a cada 30min
- Lê quality rating via API do provider
- Registra histórico em `whatsapp_quality_history` (timestamp, rating, messaging_limit_tier)
- Alerta ao dono:
  - `yellow` → notificação no dashboard + email
  - `red` → pausar imediatamente todos os envios proativos + notificar dono por múltiplos canais

### 7.2 Ações corretivas quando rating degrada

1. Pausar mensagens marketing (manter apenas utility críticas)
2. Revisar templates recentes em busca de padrões problemáticos
3. Auditar logs de opt-out para detectar aumento anormal
4. Reduzir frequência global para 50% por 72h
5. Se `red` por mais de 7 dias: revisar todo o catálogo de templates e considerar criar novo número limpo

---

## 8. Segurança

### 8.1 Transporte
- Todas as chamadas à API do provider via HTTPS/TLS 1.2+
- Webhook com validação de assinatura (X-Hub-Signature-256 ou equivalente do provider)
- Rejeitar qualquer payload com assinatura inválida

### 8.2 Armazenamento
- `access_token` do Meta: variável de ambiente, nunca commitada
- Tabela `whatsapp_contacts` com índice em telefone (hash para busca, plain para exibição)
- Conteúdo de mensagens em `agent_messages` criptografado em repouso via criptografia do banco (se aplicável)

### 8.3 Acesso
- Apenas usuários com permissão `whatsapp.manage` podem configurar provider
- Apenas usuários com permissão `whatsapp.view_messages` podem visualizar histórico
- Todas as ações de configuração logadas em `audit_log`

---

## 9. Retenção e Descarte

| Dado | Retenção padrão | Retenção se vinculado a decisão fiscal | Local após retenção |
|------|-----------------|---------------------------------------|---------------------|
| Conteúdo de mensagem genérica | 90 dias | 5 anos | Cold storage (`agent_messages` com `is_fiscal=true`) |
| Mensagem de confirmação de OS | 90 dias | 5 anos (se vinculada a NFS-e) | Cold storage |
| Mensagem de cobrança | 2 anos | 5 anos | Cold storage |
| Opt-in/opt-out log | Enquanto houver contrato + 5 anos | Idem | Nunca descartar sem fim de contrato |
| Quality rating history | 1 ano | - | Deletar |
| Webhook raw payload | 30 dias | - | Deletar |

**Job de expurgo:** `PurgeExpiredWhatsAppDataJob` roda diariamente às 02:00, respeitando `is_fiscal` e retenções diferenciadas.

---

## 10. Direitos do Titular (LGPD Cap. III)

### 10.1 Procedimentos

| Direito | Prazo | Responsável | Procedimento |
|---------|-------|-------------|--------------|
| Acesso (Art. 9) | 15 dias | DPO | Exportar todas as mensagens e metadados do titular em CSV/JSON |
| Correção (Art. 18, III) | 15 dias | Suporte | Atualizar nome/telefone em `whatsapp_contacts` |
| Eliminação (Art. 18, VI) | 15 dias | DPO | Soft-delete em `whatsapp_contacts`, hard-delete em `agent_messages` (exceto fiscais) |
| Portabilidade (Art. 18, V) | 15 dias | DPO | Exportar em formato estruturado |
| Revogação de consentimento (Art. 8, §5) | Imediato | Automatizado | Via palavra-chave SAIR |
| Oposição ao tratamento (Art. 18, §2) | 15 dias | DPO | Análise caso a caso, registro em `lia-whatsapp.md` |

### 10.2 Canal de atendimento

- Email do DPO: `dpo@kalibrium.com.br` (a definir por tenant)
- Resposta em até 15 dias corridos
- Negativa fundamentada se a base legal permitir (ex: obrigação fiscal de retenção)

---

## 11. Auditoria

### 11.1 Logs obrigatórios

Toda mensagem enviada/recebida via WhatsApp DEVE ser logada em:
- `agent_conversations` — conversa (contato, canal, status)
- `agent_messages` — mensagem individual (conteúdo, role, tokens se aplicável, custo)
- `agent_decisions` — quando gerada pelo Agente (action_type, entities_affected, compensating_action)
- `whatsapp_delivery_events` — eventos de entrega (sent, delivered, read, failed)

### 11.2 Rastreabilidade

Cada mensagem enviada deve ter:
- `idempotency_key` (evita duplicatas)
- `template_name` + `template_version` (se HSM)
- `triggered_by` (user_id, job_name, listener_name, ou agent_decision_id)
- `legal_basis` (execução_contrato | consentimento | legítimo_interesse)

### 11.3 Relatórios periódicos

- Mensal: volume de mensagens por categoria, taxa de opt-out, quality rating, templates rejeitados
- Trimestral: auditoria LGPD (amostragem de 50 mensagens aleatórias verificando base legal e consentimento)
- Incidentes: comunicação à ANPD em até 48h se houver vazamento confirmado

---

## 12. Agente CEO IA — Regras Adicionais

O Agente CEO IA, ao operar o canal WhatsApp, fica sujeito a **todas** as regras acima, mais:

1. **Toda tool outbound (`EnviarWhatsApp`, `EnviarWhatsAppPDF`, `EnviarWhatsAppTemplate`)** DEVE:
   - Verificar `opted_out_at` antes de enviar (rejeita se não nulo)
   - Verificar janela 24h (usa template se fora)
   - Verificar horário comercial (agenda se fora)
   - Verificar quality rating (bloqueia se `red`)
   - Registrar `idempotency_key` e `compensating_action=null` (mensagem enviada não pode ser desfeita)
   - Registrar `legal_basis` na decisão

2. **Input Sanitizer** deve filtrar mensagens inbound antes de chegar ao AgentBrain (Fase 1C do plano)

3. **Aprovação humana obrigatória** em modo `shadow` e `approval` para:
   - Qualquer mensagem marketing
   - Qualquer mensagem fora do horário comercial
   - Qualquer mensagem para contato com `opted_in_source=implicit_24h` (só janela 24h vale)

4. **Nunca enviar dados sensíveis (CPF completo, valores de saldo, dados bancários) via WhatsApp** — usar email ou área logada.

5. **Escalação automática** se cliente demonstrar irritação, pedir humano, ou enviar conteúdo jurídico/reclamação formal. Heurística dividida em dois conjuntos para evitar falsos positivos:
   - **Jurídico/reclamação (escalar imediatamente):** `advogado`, `processo judicial`, `Procon`, `reclamação formal`, `reclame aqui`, `cancelar contrato`, `rescindir contrato`, `notificação extrajudicial`, `ação judicial`, `pequenas causas`.
   - **Operacional (NÃO escalar automaticamente):** `cancelar OS`, `cancelar agendamento`, `cancelar visita`, `cancelar pedido`, `remarcar`, `reagendar`. Esses fluxos devem ser tratados pelas tools do domínio Operacional (`CancelarOS`, `ReagendarOS`) com aprovação conforme policy, não como escalação jurídica.
   - **Irritação/pedido de humano:** `falar com humano`, `falar com atendente`, `quero uma pessoa`, `isso é um robô?`, `não está entendendo`, `atendente` — escalar sem tratar como incidente jurídico, apenas transferir conversa.
   - **Desambiguação obrigatória:** se a mensagem contém termo operacional (`cancelar OS`) E termo jurídico (`processo`), prevalece o conjunto jurídico (escalar).

---

## 13. Checklist Pré-Produção

Antes de ativar WhatsApp em produção para um tenant, validar:

- [ ] Conta Meta Business Manager verificada (business verification aprovada)
- [ ] Número comercial verificado e display name aprovado
- [ ] Pelo menos 5 templates utility aprovados pelo Meta
- [ ] Provider escolhido e configurado (ver `docs/architecture/whatsapp-provider.md`)
- [ ] Webhook respondendo com verify token correto
- [ ] Assinatura de webhook validada em testes
- [ ] `whatsapp_contacts` com campos opt-in/opt-out implementados
- [ ] Job `AgentWhatsAppQualityCheckJob` rodando
- [ ] Tool `EnviarWhatsApp` validando opt-out e janela 24h
- [ ] Testes de compliance passando (opt-out, janela, horário, quality rating)
- [ ] DPO ciente do fluxo e contatos configurados
- [ ] Catálogo de templates documentado em `config/whatsapp-templates.php`
- [ ] LIA registrada para mensagens de legítimo interesse
- [ ] Retenção e expurgo configurados (`PurgeExpiredWhatsAppDataJob`)
- [ ] Tenant com `agent_mode=shadow` nas primeiras 2 semanas (validação antes de aprovação total)

---

## 14. Referências Legais

- **LGPD** — Lei 13.709/2018 — https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm
- **Marco Civil da Internet** — Lei 12.965/2014
- **Código de Defesa do Consumidor** — Lei 8.078/1990 (publicidade e relações de consumo)
- **Meta WhatsApp Business Policy** — https://www.whatsapp.com/legal/business-policy
- **Meta WhatsApp Commerce Policy** — https://www.whatsapp.com/legal/commerce-policy
- **Guia da ANPD sobre Legítimo Interesse** — https://www.gov.br/anpd
- **CTN Art. 173** — prazo decadencial tributário (retenção fiscal)
- **LC 116/2003** — ISS e obrigações acessórias

---

## 15. Histórico de Revisões

| Versão | Data | Autor | Mudanças |
|--------|------|-------|----------|
| v1 | 2026-04-10 | Plataforma | Documento inicial cobrindo LGPD, opt-in/out, janela 24h, templates, retenção, auditoria, integração com Agente CEO IA |
