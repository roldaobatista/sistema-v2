---
stepsCompleted: [step-01-init, step-02-discovery, step-02b-vision, step-02c-executive-summary, step-03-success, step-04-journeys, step-05-domain, step-06-innovation, step-07-project-type, step-08-scoping, step-09-functional, step-10-nonfunctional, step-11-polish, step-12-complete]
vision:
  statement: Unificar operacao, calibracao e financeiro em uma plataforma so para empresas de servico tecnico e metrologia
  differentiator: Unico sistema que combina calibracao INMETRO com formulas ISO GUM reais + gestao operacional + financeiro
  coreInsight: Produtividade do tecnico em campo = receita da empresa
  timing: Onda de digitalizacao forcada por Portaria 671 e eSocial
inputDocuments:
  - backend/app (código-fonte — fonte definitiva)
  - frontend/src (código-fonte — fonte definitiva)
  - docs/architecture/ARQUITETURA.md
  - docs/compliance/ISO-17025.md
  - docs/compliance/ISO-9001.md
  - docs/compliance/PORTARIA-671.md
  - docs/audits/RELATORIO-AUDITORIA-SISTEMA.md
documentCounts:
  briefs: 0
  research: 0
  brainstorming: 0
  projectDocs: 5
workflowType: 'prd'
projectType: 'brownfield'
version: '3.1'
lastUpdated: '2026-04-06'
classification:
  projectType: Plataforma Operacional SaaS
  domain: Gestao de operacoes de campo + Laboratorio de calibracao
  complexity: alta
  projectContext: brownfield
  positioning: Unica plataforma que unifica OS + Calibracao INMETRO + Financeiro
  marketEntry: Calibracao/metrologia (nicho de entrada) -> Servico tecnico geral (expansao)
  primaryPersonas:
    - Gestor operacional (visao do dia)
    - Tecnico de campo (execucao rapida)
    - Financeiro (ciclo de receita)
    - Cliente final (portal/acompanhamento)
  coreJourneys:
    - Dashboard operacional do dia
    - Execucao de OS em campo
    - Ciclo completo de receita
    - Portal do cliente
  mvpModules:
    - Ordens de Servico
    - Calibracao/Certificados
    - Financeiro (AP/AR)
    - Clientes/CRM basico
    - Ponto Eletronico
    - Portal do Cliente
---

# Product Requirements Document - Kalibrium

**Autor:** Rolda
**Data:** 2026-04-06 (v3.1 — auditoria 2026-04-06: correcoes de dados, status realistas, gaps formalizados)

## Resumo Executivo

O Kalibrium e uma plataforma operacional SaaS multi-tenant para empresas de servico tecnico, calibracao e manutencao industrial no Brasil. O sistema unifica em uma unica interface os processos que hoje estao espalhados em 3-4 ferramentas desconectadas: gestao de ordens de servico, laboratorio de calibracao com certificados INMETRO, financeiro completo (faturamento, cobranca, conciliacao), RH com ponto eletronico digital (Portaria 671/2021), e CRM com pipeline de vendas.

O problema central que resolve: empresas deste segmento perdem 15-25% da receita potencial por atraso no faturamento e cobranca. O ciclo chamado → OS → execucao → certificado → fatura → cobranca e fragmentado — media de 3-5 dias entre OS concluida e boleto gerado. OS finalizada que nao vira fatura por dias. Certificado que atrasa porque o sistema de calibracao nao conversa com o de OS. Tecnico em campo que anota no papel porque o app nao funciona offline. O Kalibrium foi projetado para eliminar cada um desses vazamentos conectando o fluxo ponta a ponta. Quando as integracoes fiscais e de cobranca estiverem em producao (NFS-e + Boleto/PIX — bloqueadores de go-live), o ciclo de receita sera reduzido de dias para minutos.

O publico-alvo primario sao empresas de calibracao e metrologia (nicho de entrada, ~1000 empresas no Brasil) com expansao para servico tecnico em geral (ar condicionado, elevadores, automacao industrial — mercado 10x maior). Quatro personas guiam todas as decisoes de produto: o gestor operacional (visao do dia), o tecnico de campo (execucao rapida no celular), o financeiro (ciclo de receita sem buracos), e o cliente final (acompanhamento via portal).

### O Que Torna Este Produto Especial

O Kalibrium e o unico sistema no mercado brasileiro que combina calibracao INMETRO com formulas ISO GUM reais (calculo de incerteza de medicao, classes de precisao, Portaria INMETRO 157/2022) dentro da mesma plataforma que gerencia ordens de servico, financeiro e RH. ERPs grandes como TOTVS e Senior nao oferecem calibracao nativa — o cliente precisa de sistema separado. Sistemas de calibracao nao fazem gestao operacional. O Kalibrium e o ponto de encontro.

O insight central do produto: **produtividade do tecnico em campo = receita da empresa**. Se o tecnico executa a OS mais rapido, fecha o certificado no celular, coleta assinatura digital e o sistema fatura automaticamente, a empresa recebe mais rapido. Cada minuto economizado no campo e dinheiro no caixa.

O timing e favoravel: a Portaria 671/2021 (ponto eletronico digital) e o eSocial forcaram a primeira onda de digitalizacao nestas empresas. Quem ja digitalizou RH quer digitalizar o resto. O Kalibrium captura esse cliente no momento em que ele esta pronto para sair do papel.

### Quem NAO E Cliente (Anti-Personas)

| Perfil | Por que NAO serve | Alternativa para eles |
|--------|------------------|----------------------|
| **Empresa com +200 funcionarios** que precisa de ERP fiscal completo (SPED, EFD, Bloco K) | Kalibrium nao faz apuracao fiscal nem substitui ERP contabil. Complexidade tributaria exige TOTVS/Senior | TOTVS Protheus, Senior |
| **Laboratorio fixo sem operacao de campo** (so bancada, sem tecnico externo, sem OS) | Nao precisa de gestao de campo, PWA, GPS, frota. Pagaria por modulos que nao usa | Calibre (sistema de calibracao puro), Excel avancado |
| **Prestador autonomo** (1 pessoa, sem funcionarios) | Overhead de sistema multi-tenant e excessivo. Nao precisa de ponto, RH, multi-usuario | Planilha + app de OS simples (Field Service Lightning) |
| **Empresa que exige instalacao on-premise** | Kalibrium e SaaS cloud-only. Nao oferece deploy local | ERPs tradicionais com licenca perpetua |
| **Industria de manufatura** buscando MES/MRP | Kalibrium e para servico tecnico, nao para producao industrial. Sem BOM, sem MRP, sem chao de fabrica | TOTVS Manufatura, Nomus |

> O Kalibrium atende o "meio": empresas de 5-100 funcionarios que fazem servico tecnico em campo e/ou calibracao, com necessidade de financeiro integrado. Pequenas demais para um ERP enterprise, grandes demais para planilha.

## Classificacao do Projeto

| Campo | Valor |
|-------|-------|
| **Tipo** | Plataforma Operacional SaaS (multi-tenant, multi-modulo) |
| **Dominio** | Gestao de operacoes de campo + Laboratorio de calibracao |
| **Complexidade** | Alta (regulatorio INMETRO + Portaria 671 + eSocial + NFS-e) |
| **Contexto** | Brownfield — sistema existente com ~75-80% de maturidade validada (LGPD implementada v1.9, EMA persistido, cancelamento NFS-e implementado mas dependente de contrato FocusNFe. **Bloqueadores MVP:** integracao fiscal em producao (contrato), integracao cobranca Boleto/PIX (nao implementado), dashboard unificado (nao implementado), offline funcional (so cache estatico). **Pendentes pos-MVP:** RIPD, anonimizacao automatica, eSocial producao, FIFO/FEFO) |
| **Stack** | Laravel 13 + React 19 + MySQL 8 + Redis (PHP 8.2+) |
| **Testes** | 8385+ testes automatizados (Pest + Vitest) |
| **Mercado de entrada** | Calibracao/metrologia (~1000 empresas BR) |
| **Mercado de expansao** | Servico tecnico geral (10x maior) |

## Criterios de Sucesso

### Sucesso do Usuario

| Persona | Criterio | Meta |
|---------|----------|------|
| **Gestor (Dona Marcia)** | Ver status da operacao em 1 tela, sem navegar | Dashboard carrega em < 3s com OS, tecnicos, pagamentos do dia |
| **Tecnico (Joao)** | Fechar OS completa no celular em campo | Max 3 toques: abrir OS → registrar → coletar assinatura. Funciona offline |
| **Financeiro (Pedro)** | Ciclo receita sem buraco | OS faturada gera NFS-e + boleto/PIX automaticamente. Fechamento mensal em < 1 hora. **⚠️ Depende de:** integracao NFS-e (contrato FocusNFe) + integracao Boleto/PIX (gateway Asaas — nao implementado) |
| **Cliente final** | Acompanhar servico sem ligar na empresa | Portal com status da OS, certificado para download, NF disponivel. **⚠️ NF e boleto dependem das integracoes fiscais/cobranca** |

**Momento "aha!" (meta pos-integracao):** O gestor percebe que a OS fechada pelo tecnico as 15h ja gerou a fatura e o boleto as 15h01 — sem ninguem do financeiro intervir. **Status atual:** fluxo implementado ate a fatura; NFS-e e boleto/PIX pendentes de integracao em producao.

### Sucesso do Negocio

| Metrica | 3 meses | 12 meses |
|---------|---------|----------|
| **Clientes pagantes** | 1 empresa piloto operando 100% (meta: ate 2026-06-01) | 5-10 empresas de calibracao |
| **Receita recorrente (MRR)** | R$ 500-1.500 (piloto) | R$ 15.000-50.000 |
| **Retencao** | 100% (piloto nao pode sair no primeiro trimestre) | > 90% retencao anual (renovacao de contrato) |
| **Ciclo de venda** | Demonstracao → piloto em 2 semanas | < 30 dias para novos clientes |
| **NPS** | > 40 (piloto, medido ao final do mes 3) | > 50 (medido trimestralmente via survey no portal) |

**Indicador chave:** Tempo medio entre "OS concluida" e "boleto gerado" — deve ser < 5 minutos (automatico), versus horas/dias no processo manual. **⚠️ Status atual: este fluxo NAO funciona ponta a ponta. Depende de: (1) NFS-e em producao (contrato), (2) Boleto/PIX implementado (codigo + contrato), (3) webhook de baixa automatica (codigo).**

### Sucesso Tecnico

| Metrica | Meta |
|---------|------|
| **Disponibilidade** | 99.5% uptime (max ~3.6h downtime/mes) |
| **Performance** | API: p95 < 500ms. Dashboard: < 3s. PWA: funciona offline |
| **Seguranca** | Zero vazamento cross-tenant. Audit trail completo. Hash chain Portaria 671 |
| **Testes** | 8385+ passando. Cobertura dos 4 ciclos criticos E2E |
| **Deploy** | Zero downtime deploy. Rollback em < 5 minutos |
| **Compliance** | NFS-e emitindo via gateway fiscal **(pendente contrato)**. Boleto/PIX via gateway de pagamento **(NAO implementado)**. eSocial eventos reais **(S-2205+ ainda stub)** |

### Resultados Mensuraveis

| Indicador | Antes (manual) | Meta (Kalibrium) | Como Medir | Baseline Fonte | Impacto |
|-----------|---------------|------------------|-----------|----------------|---------|
| Ciclo de receita (OS concluida → boleto gerado) | 3-5 dias | < 5 minutos | Timestamp da transicao de status no sistema | Entrevista cliente-piloto (2026-03) | Recupera 15-25% da receita atrasada |
| Retrabalho do tecnico (redigitacao) | 30 min/OS | 0 min | Comparar: dados entram 1 vez no campo, nao sao redigitados | Observacao de campo com 3 prospects | 2.5h/dia liberadas por tecnico |
| Certificados entregues no prazo | ~70% | > 95% | (certificados emitidos no prazo / total) por mes | Relatorio manual do prospect principal | Reducao de reclamacoes e retencao de clientes |
| Conciliacao bancaria mensal | 2-3 dias | < 2 horas | Tempo entre import do extrato e conciliacao 100% | Entrevista financeiro prospect (2026-03) | Fechamento financeiro 10x mais rapido |
| Tempo de onboarding (novo cliente) | 1-2 semanas | < 1 dia | Tempo entre criacao do tenant e primeiro login do cliente | Estimativa baseada em setup manual atual | Escala comercial mais rapida |

> **Metodo de medicao por metrica:**
> | Metrica | Before (estimado) | After (meta) | Como Medir |
> |---------|-------------------|-------------|------------|
> | Tempo emissao certificado | 2h (manual) | < 15min | Timestamp inicio wizard → PDF gerado |
> | Papel por OS | 3-5 folhas | 0 | Flag `printed` nos PDFs, meta: 0 impressoes |
> | Tempo fechamento financeiro | 3 dias | < 4h | Timestamp inicio → aprovacao folha |
> | Acesso a certificado pelo cliente | Liga para empresa | Self-service via portal | Tickets de suporte tipo "certificado" → 0 |
> | Cliques para visao do dia (gestor) | N/A | < 3 cliques | Analytics de navegacao (click depth) |
> | LGPD — dados pessoais mapeados | 0% | 100% | Audit: tabelas com dados pessoais vs mapeadas |
> | eSocial — eventos transmitidos sem rejeicao | N/A | 100% obrigatorios | Log de transmissao: aceitos / total |

## Escopo do Produto

### MVP — Minimo Produto Viavel

O que DEVE funcionar para o primeiro cliente pagar:

| Modulo | Funcionalidades MVP | Status Atual |
|--------|--------------------|-----------|
| **Ordens de Servico** | CRUD, 17 status, atribuicao tecnico, items, PDF, assinatura digital | 🟢 Pronto |
| **Calibracao** | Wizard, leituras, EMA, certificado PDF INMETRO | 🟢 Pronto |
| **Financeiro** | AP/AR, parcelas, despesas, conciliacao basica | 🟢 Pronto |
| **NFS-e** | Emissao via FocusNFe | 🟡 Gateway implementado (FocusNFe + NuvemFiscal fallback + contingencia). **Bloqueador:** contrato comercial + credenciais de producao. Nao funciona sem config externa |
| **Boleto/PIX** | Geracao via Asaas | 🔴 **NAO IMPLEMENTADO em producao.** Interface PaymentGateway existe, mas integracao real com Asaas (boleto + PIX + webhook de baixa) requer implementacao de codigo + contrato comercial |
| **Clientes** | CRUD, contatos, documentos, 360 graus | 🟢 Pronto |
| **Ponto Eletronico** | Clock-in/out, GPS, biometria, Portaria 671 | 🟢 Pronto |
| **Portal do Cliente** | Login, acompanhar OS, baixar certificado/NF | 🟡 Parcial (8 controllers funcionais). **⚠️ NF e boleto no portal dependem das integracoes NFS-e e Boleto/PIX** |
| **PWA Mobile** | Tecnico: abrir OS, registrar, assinatura, offline | 🟡 Offline: **so cache estatico (HTML/CSS/JS)**. API NAO cacheada — tecnico sem sinal perde acesso a dados. Consulta/leituras/assinatura funcionam COM sinal |
| **Dashboard Gestor** | Visao do dia: OS, tecnicos, alertas | 🟡 9 dashboards por modulo existem, mas **dashboard operacional UNIFICADO do dia (OS + pagamentos + alertas em 1 tela) NAO existe** |
| **LGPD** | Base legal, direitos do titular, consentimento | 🟢 Implementado (v1.9: 6 tabelas, 6 models, 5 controllers, 14 permissoes, DPO por tenant, consentimento, incidentes ANPD) |

### Crescimento (Pos-MVP)

| Modulo | Valor |
|--------|-------|
| **CRM Pipeline** | Deal→Quote→OS. **Deal→Quote IMPLEMENTADO** (`ConvertDealToQuoteAction`, rota `POST deals/{deal}/convert-to-quote`, botao no DealDetailDrawer, teste `CrmDealConvertToQuoteTest`). Quote→OS automatico ainda pendente (RF-21.2). |
| **Estoque avancado** | FIFO/FEFO IMPLEMENTADO em `StockService::selectBatches()` (usado por 8 controllers + 2 listeners, 4 arquivos de teste). **Gap real:** todos os callers hardcodam `strategy='FIFO'` — falta coluna `stock_strategy` em products/tenant_settings para ativar FEFO sem tocar codigo. |
| **Folha de Pagamento** | Calculo completo INSS/IRRF/FGTS (ja funciona) |
| **eSocial** | Eventos completos S-1000 a S-3000. **⚠️ S-2200 e S-2299 reais; S-2205, S-2206, S-2210+ retornam buildStubXml() — NAO usar em producao** |
| **WhatsApp** | Mensagens integradas ao CRM (webhook recebe, mas nao alimenta CrmMessage — integracao pendente) |
| **Relatorios Gerenciais** | DRE, produtividade, SLA compliance |
| **Contratos Recorrentes** | Billing automatico. **⚠️ PLACEHOLDER:** infra SaasPlan/SaasSubscription existe, mas gera invoice com valor fixo R$1000.00 — NAO usar em producao ate implementar integracao real com gateway |

### Modulos Implementados Nao-Documentados

> Os modulos anteriormente listados nesta secao foram formalizados como RFs: RF-20 (Funcionalidades Transversais), RF-21 (Pipeline Comercial), e sub-RFs dos grupos existentes. Consultar as respectivas secoes de RF para detalhes.

### Visao (Futuro)

| Feature | Descricao | Gatilho de Decisao | Pre-requisito |
|---------|-----------|-------------------|---------------|
| **IA Preditiva** | Previsao de manutencao, churn, pricing otimizado | > 5 clientes com 6+ meses de dados historicos | Data pipeline estavel, read replica |
| **IoT/Sensores** | Monitoramento remoto de equipamentos calibrados | Cliente com equipamentos IoT-ready solicita integracao | API de ingestao de dados, MQTT broker |
| **Marketplace** | Conectar empresas de calibracao com clientes finais | > 20 clientes ativos e demanda comprovada de cross-selling | Billing maduro, rating system, moderacao |
| **App Nativo** | iOS/Android dedicado (hoje e PWA) | > 50% dos tecnicos relatando limitacoes PWA (camera, push, background) | PWA estavel com metricas de uso por 6 meses |
| **Multi-pais** | Expansao para LATAM (compliance por pais) | Cliente fora do Brasil ou parceiro de distribuicao internacional | i18n completo, compliance por jurisdicao |
| **Integracao Contabil** | Export direto para sistemas de contabilidade | > 3 clientes pedindo integracao com o mesmo sistema contabil | API REST publica documentada |

## Analise de Inovacao e Diferenciacao Competitiva

### Landscape Competitivo

| Concorrente | Tipo | Calibracao INMETRO | ERP Integrado | Ponto Eletronico | Multi-tenant |
|-------------|------|-------------------|---------------|------------------|-------------|
| Sigavi | Software de calibracao | ✅ | ❌ (precisa ERP separado) | ❌ | ❌ |
| Metrolab | LIMS laboratorial | ✅ Parcial | ❌ | ❌ | ❌ |
| Omie/ContaAzul | ERP generico | ❌ | ✅ | ❌ | ✅ |
| Pontomais | Ponto eletronico | ❌ | ❌ | ✅ | ✅ |
| **Kalibrium** | **Plataforma unificada** | **✅ ISO GUM + OIML R76** | **✅ OS+Financeiro+CRM** | **✅ Portaria 671** | **✅** |

### Diferenciadores Unicos

1. **Unico sistema que combina calibracao INMETRO com gestao operacional** — concorrentes exigem 2-3 sistemas separados
2. **Formulas ISO GUM reais** (incerteza de medicao, EMA, Xbar-R) — nao e calculadora generica
3. **Compliance regulatorio built-in** — Portaria 671 (ponto), LGPD, eSocial no mesmo sistema
4. **Ciclo de receita automatizado** — OS→Certificado→NFS-e→Boleto/PIX sem intervencao manual

### Competitive Moat

- **Complexidade regulatoria como barreira** — implementar calibracao INMETRO + Portaria 671 + LGPD + eSocial exige conhecimento de dominio profundo
- **Integracao vertical** — cada modulo alimenta o outro (calibracao gera certificado que gera NFS-e que gera cobranca)
- **Dados acumulados** — historico de calibracoes, cartas de controle e rastreabilidade criam lock-in positivo

## Jornadas de Usuario

### Jornada 1: Dona Marcia — "O Dia Que Parei de Apagar Incendio"

**Quem e:** Marcia, 45 anos, dona de empresa de calibracao com 20 tecnicos em Campinas-SP. Acorda as 5h30, chega na empresa as 7h. Gerencia tudo no WhatsApp e planilha Excel.

**Cena de abertura:** Segunda-feira, 7h15. Marcia abre o WhatsApp e tem 23 mensagens: cliente cobrando certificado atrasado, tecnico dizendo que pegou a peca errada, financeiro pedindo lista de OS para faturar. Ela suspira e pensa "preciso de um sistema".

**Acao crescente:** Marcia acessa o Kalibrium no notebook. O dashboard (RF-01.1) mostra: 12 OS abertas hoje (RF-01.2), 3 tecnicos ja em deslocamento — GPS ativo (RF-01.7), 2 pagamentos vencendo, 1 certificado pendente de revisao (RF-02.5). Os KPIs operacionais do dia aparecem consolidados (RF-12.6). Tudo em UMA tela. Ela clica na OS do certificado atrasado — ve que o tecnico ja fez a calibracao, as leituras estao no sistema, so falta o revisor aprovar. Aprova ali mesmo.

**Climax:** As 10h, Marcia percebe algo: o tecnico Carlos fechou 2 OS antes do almoco. O sistema ja gerou as faturas (RF-01.9), emitiu as NFS-e, e os boletos estao no email do cliente. Ela nao precisou pedir nada pro financeiro. Olha pro relogio e pensa "sao 10h e eu ja fiz o que antes levava o dia inteiro".

**Resolucao:** No fim do mes, o fechamento financeiro que levava 3 dias levou 2 horas. A conciliacao bancaria bateu automatica. Marcia finalmente tem tempo para pensar em crescer a empresa em vez de apagar incendio.

**Modulos complementares:** Marcia tambem consulta o dashboard de comissoes para validar pagamentos aos tecnicos (RF-12.1-12.2), monitora o SLA no dashboard operacional (RF-12.6), configura o TV Dashboard do escritorio para acompanhar OS em tempo real (RF-12.4-12.5), verifica o estoque de pecas no armazem (RF-17.1) e acompanha o inventario mensal (RF-17.7).

### Jornada 2: Tecnico Joao — "Sem Papel, Sem Retrabalho"

**Quem e:** Joao, 32 anos, tecnico de calibracao de balancas. Faz 5 visitas por dia, dirige 120km. Antes: anotava tudo em papel, perdia formulario, redigitava no escritorio as 18h.

**Cena de abertura:** 7h30, Joao abre o app no celular (RF-07.1). Ve a lista de OS do dia (RF-01.1): 5 atribuicoes, enderecos no mapa, horarios estimados. Registra o ponto de entrada (RF-05.1) antes de sair. A primeira e uma calibracao de balanca analitica na Farmacia Sao Paulo.

**Acao crescente:** Chega no cliente. Abre a OS no celular — ve o equipamento (balanca Mettler XP205, ultima calibracao ha 6 meses), o checklist do que fazer, os pesos padrao necessarios. O wizard de calibracao guia o fluxo (RF-02.1). Coloca os pesos, registra as leituras direto no app (RF-02.2). O sistema calcula a incerteza de medicao automaticamente (EMA). Verde: balanca conforme. Registra fotos do equipamento para evidencia (RF-07.2).

**Climax:** Joao mostra a tela pro cliente, o cliente assina na tela do celular (RF-01.6). O certificado de calibracao e gerado na hora (RF-02.5) — PDF com logo, leituras, incerteza, referencia INMETRO. O cliente recebe por email antes do Joao sair da sala. Total: 15 minutos. Antes: 15 minutos em campo + 30 minutos redigitando no escritorio.

**Resolucao:** As 17h, Joao esta em casa. Nao precisa ir ao escritorio. As 5 OS estao fechadas, 5 certificados emitidos, 5 faturas geradas. Ele abriu o app 5 vezes, fez o trabalho, e foi embora. Zero papel. Zero retrabalho.

**Modulos complementares:** Antes de sair, Joao faz o checkin do veiculo (RF-14.2): quilometragem, combustivel, condicao geral. Registra o abastecimento feito no caminho (RF-14.3). Consulta suas comissoes do mes no app (RF-12.1, read-only offline).

**Edge case — Sem internet:** Joao entra num galpao industrial sem sinal. O app funciona offline (RF-07.3) — mostra os dados da OS cacheados, ele registra as leituras. O deslocamento e rastreado por GPS mesmo sem conectividade (RF-01.7). Quando sai e pega sinal, o app sincroniza automaticamente e o certificado e gerado.

**Edge case — Sem dados cacheados:** Joao recebe uma OS de emergencia enquanto esta sem sinal. O app mostra aviso "OS nao disponivel offline — sera carregada ao reconectar" e oferece registrar leitura avulsa que sera vinculada a OS depois.

### Jornada 3: Pedro — "O Fechamento Que Nao Doi"

**Quem e:** Pedro, 38 anos, analista financeiro da empresa da Dona Marcia. Antes: coletava OS em papel, digitava faturas uma a uma, gerava boletos manualmente, conciliava no Excel.

**Cena de abertura:** Dia 25 do mes. Pedro olha a pilha de 87 OS concluidas no mes. Precisa: verificar quais foram faturadas, emitir NFS-e para cada uma, gerar boletos, enviar pros clientes, e fechar o caixa. Normalmente leva 3 dias.

**Acao crescente:** Pedro abre o Kalibrium. No painel financeiro (RF-03.9): 87 OS concluidas, 85 ja faturadas automaticamente (RF-01.9). As NFS-e ja foram emitidas automaticamente (RF-03.4). Os boletos (RF-03.5) e links PIX (RF-03.6) ja estao no email do cliente. Pedro so precisa verificar e aprovar.

**Climax:** Pedro abre a conciliacao bancaria (RF-03.7). Importa o extrato do banco (OFX). O sistema faz auto-match: 62 pagamentos batem automaticamente. Pedro revisa os 8 que nao bateram — 5 sao depositos PIX com valor arredondado, ele ajusta manualmente. Pronto. Conciliacao feita em 40 minutos. Confere as despesas lancadas no mes (RF-03.8).

**Edge case — NFS-e rejeitada:** Uma NFS-e e rejeitada pela prefeitura (dados do tomador incorretos). O sistema notifica Pedro com o motivo da rejeicao. Pedro corrige os dados do cliente e reemite. A NFS-e vai novamente. Se o gateway fiscal estiver fora do ar, o sistema entra em modo contingencia e tenta via fallback.

**Resolucao:** O que levava 3 dias agora levou meio dia. Pedro executa o fechamento mensal (RF-03.16): gera o relatorio consolidado de receitas e despesas, exporta para o contador em CSV, e confere o DRE (RF-03.9). Envia pra Dona Marcia. Ela ve: receita do mes, despesas, lucro liquido. Tudo batendo. Pedro sai as 18h pela primeira vez no dia de fechamento.

**Modulos complementares:** Pedro acompanha o contas a pagar (RF-03.2) e programa pagamentos a fornecedores direto no sistema. Consulta o relatorio de inadimplencia (RF-03.9) para cobrar clientes em atraso. Fornecedores da empresa consultam seus pagamentos e documentos fiscais no Portal do Fornecedor (RF-12.8), reduzindo ligacoes ao financeiro.

### Jornada 4: Cliente Final — "Eu Sei Onde Esta Meu Certificado"

**Quem e:** Farmacia Sao Paulo, cliente da empresa da Dona Marcia. Precisa de certificados de calibracao das balancas para auditoria da ANVISA.

**Cena de abertura:** A farmaceutica responsavel, Ana, recebe email da ANVISA: auditoria em 15 dias. Precisa dos certificados de calibracao atualizados de 3 balancas. Antes: ligava pra Marcia, pedia por email, esperava dias.

**Acao crescente:** Ana acessa o portal do cliente (RF-06.1) com seu login. Ve: 3 balancas cadastradas, ultima calibracao de cada uma, proxima calibracao prevista. Clica em "Certificados" — os 3 PDFs estao la, com selo INMETRO, leituras, incerteza (RF-06.2).

**Climax:** Ana baixa os 3 certificados em 30 segundos. Ve que uma balanca tem calibracao vencendo em 20 dias. Clica em "Solicitar Recalibracao" — um chamado e aberto automaticamente (RF-06.3) para a equipe da Marcia.

**Resolucao:** Ana responde a ANVISA no mesmo dia. Marcia recebe o chamado de recalibracao e ja agenda o tecnico. O cliente esta satisfeito, renovara o contrato.

### Jornada 5: Admin do Sistema — "Novo Cliente em 10 Minutos"

**Quem e:** Rolda, administrador do Kalibrium. Configura novos tenants, gerencia permissoes, monitora o sistema.

**Cena de abertura:** Nova empresa de calibracao quer usar o Kalibrium. Rolda precisa: criar tenant, configurar usuarios, definir permissoes, ativar modulos.

**Acao crescente:** Rolda cria o tenant no painel admin (RF-08.1). Define: nome da empresa, CNPJ, plano (modulos ativos). Cria o usuario admin do cliente e configura as permissoes de acesso (RF-08.3). Configura: credenciais de emissao fiscal do cliente, credenciais do gateway de cobranca, modelo de certificado com logo do cliente.

**Climax:** Em 10 minutos, o tenant esta pronto. Rolda envia o link de acesso pro cliente. O cliente loga, importa seus equipamentos via CSV, cadastra os tecnicos, e comeca a criar OS no mesmo dia.

**Resolucao:** Zero intervencao tecnica apos o setup. O cliente opera de forma autonoma. Rolda monitora via dashboard admin: health check dos tenants, uso de storage, erros. Todas as acoes ficam registradas no audit log (RF-08.4).

**Modulos complementares:** Rolda gerencia os planos SaaS (RF-18.1), cria e cancela assinaturas (RF-18.2-18.3), monitora a saude dos tenants no dashboard de observabilidade (RF-12.7), e usa o assistente IA para consultas rapidas sobre metricas operacionais (RF-15.1).

### Jornada 6: Integrador API — "Conectei Meu Sistema em 1 Hora"

**Quem e:** Desenvolvedor de uma empresa que quer integrar dados do Kalibrium com seu sistema de contabilidade.

**Cena de abertura:** O contador da empresa da Dona Marcia quer receber automaticamente as faturas e pagamentos para lancar na contabilidade.

**Acao crescente:** O desenvolvedor acessa a API REST documentada (RF-09.1) — a documentacao Swagger facilita a exploracao dos endpoints (RF-09.5). Usa autenticacao por token. Chama GET /api/v1/financial/receivables?month=2026-03 — recebe todas as contas a receber do mes em JSON.

**Climax:** Em 1 hora, o integrador configura um webhook (RF-09.3) para receber notificacoes quando um pagamento e confirmado. O sistema contabil recebe o evento e lanca automaticamente.

**Resolucao:** A contabilidade da empresa esta sincronizada em tempo real. Zero digitacao manual. O contador e feliz.

### Jornada 7: Titular de Dados — "Quero Saber O Que Voces Tem Sobre Mim"

**Quem e:** Qualquer pessoa cujos dados pessoais estao no Kalibrium — funcionario, cliente PF, contato de cliente PJ.

**Cena de abertura:** Carlos, ex-funcionario de uma empresa cliente do Kalibrium, recebe email de marketing da empresa 6 meses apos sair. Ele quer saber que dados ainda guardam dele e solicitar eliminacao do que nao e obrigatorio manter.

**Acao crescente:** Carlos acessa o Portal do Cliente e encontra a secao "Meus Dados (LGPD)". Clica em "Solicitar acesso aos meus dados" (RF-11.2). O sistema registra a solicitacao com protocolo, data e prazo de 15 dias uteis. O DPO da empresa (configurado no tenant) recebe notificacao automatica (RF-11.7). Carlos tambem verifica seu historico de consentimentos (RF-11.5).

**Climax:** Em 5 dias, o sistema gera automaticamente o relatorio com todos os dados pessoais de Carlos: nome, CPF, registros de ponto (retidos por obrigacao legal — CLT), email, telefone, geolocalizacao de deslocamentos. Carlos recebe o PDF e solicita eliminacao dos dados de contato e geolocalizacao (RF-11.3). O sistema elimina o que pode e mantém, com justificativa legal explicita, o que e obrigatorio (ponto = 5 anos CLT, dados fiscais = 5 anos CTN).

**Edge case — Titular pede eliminacao total:** Carlos insiste que quer TODOS os dados apagados, incluindo registros de ponto. O sistema exibe justificativa legal para cada dado retido: "Registros de ponto: retencao obrigatoria por 5 anos (CLT art. 11). Dados fiscais: retencao obrigatoria por 5 anos (CTN art. 173). Estes dados serao anonimizados automaticamente apos o periodo legal." Carlos aceita — a transparencia gera confianca.

**Resolucao:** Carlos recebe confirmacao: "Dados de contato e geolocalizacao eliminados. Registros de ponto e dados fiscais mantidos por obrigacao legal (CLT art. 11, CTN art. 173)." A empresa esta em conformidade. Toda a interacao esta registrada com audit trail.

### Jornada 8: Carla, Gestora de RH — "Fechamento do Mes Sem Drama"

**Contexto:** Carla gerencia o RH de uma empresa de servicos tecnicos com 25 funcionarios. Todo dia 25, precisa fechar a folha de pagamento e transmitir eventos ao eSocial. Antes do Kalibrium, usava planilhas Excel para conferir ponto, calculava horas extras manualmente e vivia com medo de errar o calculo do 13o.

**Setup:** Dia 25 do mes. Carla abre o Kalibrium no computador. O dashboard de RH mostra: 25 funcionarios ativos, 2 admissoes no mes, 1 ferias agendada, 3 funcionarios com banco de horas negativo (RF-05.1, RF-05.3).

**Acao crescente:** Carla inicia o processamento da folha (RF-05.4). O sistema puxa automaticamente: registros de ponto do mes, horas extras calculadas (50% e 100%), faltas, abonos e banco de horas. Confere os 3 funcionarios com banco negativo — o sistema ja aplicou o desconto proporcional conforme a regra configurada. Revisa a admissao do novo tecnico: Carla cadastrou os dados na semana passada e o sistema ja gerou o evento S-2200 para o eSocial (RF-10.1). Verifica os beneficios: vale-transporte e vale-refeicao estao calculados proporcionalmente para o novo funcionario. O 13o esta correto — primeira parcela paga em novembro, segunda com descontos em dezembro, tudo automatico.

**Climax:** Carla aprova a folha. O sistema gera os holerites individuais, calcula INSS, IRRF e FGTS automaticamente, e prepara os eventos eSocial do mes (S-1200 para remuneracao, S-1210 para pagamento). Exporta o AFDT (RF-05.6) e o ACJEF (RF-05.7) do mes para arquivo — caso a fiscalizacao solicite. O tecnico Marcos pediu ferias para o proximo mes: Carla aprova no sistema, que calcula o adicional de 1/3, agenda o pagamento 2 dias antes do inicio e gera o evento S-2230.

**Resolucao:** Em 45 minutos, Carla fechou o que antes levava 2 dias. A folha esta processada, os holerites enviados por email, os eventos eSocial prontos para transmissao, e o banco de horas atualizado. Sem planilha, sem calculadora, sem drama.

**Edge case — Ponto inconsistente:** O tecnico Joao registrou entrada as 7h mas nao registrou saida. O sistema marca como "jornada incompleta" e bloqueia o calculo daquele dia ate que o gestor aprove um ajuste de ponto com justificativa (RF-05.2). Carla nao precisa descobrir na hora de fechar a folha — o alerta apareceu no dia seguinte.

**Edge case — Demissao no meio do mes:** Funcionario pede demissao dia 15. Carla registra no sistema, que calcula automaticamente: saldo de salario proporcional, ferias vencidas + proporcionais + 1/3, 13o proporcional, aviso previo, multa FGTS se aplicavel. Gera evento S-2299 para o eSocial com data de desligamento.

**Modulos complementares:** Carla tambem gerencia as avaliacoes de performance trimestrais dos tecnicos, registra feedback continuo no sistema, controla EPIs entregues e treinamentos obrigatorios de seguranca. Consulta o dashboard de performance para identificar tecnicos que precisam de capacitacao.

### Resumo de Capacidades Reveladas pelas Jornadas

| Jornada | Capacidades Necessarias |
|---------|------------------------|
| **Gestor** | Dashboard unificado, aprovacoes rapidas, visao financeira do dia |
| **Tecnico** | PWA mobile, offline, calibracao no celular, assinatura digital, GPS |
| **Financeiro** | Faturamento automatico, NFS-e, boleto/PIX, conciliacao, DRE |
| **Cliente** | Portal self-service, download certificados, solicitar servico |
| **Admin** | Multi-tenant setup, config por tenant, monitoring, CSV import |
| **API** | REST API documentada, autenticacao token, webhooks, JSON |
| **Titular LGPD** | Portal de direitos, acesso a dados, eliminacao, consentimento, DPO, audit trail |
| **RH/DP (Carla)** | Folha de pagamento automatizada, banco de horas, eSocial, holerites, ferias, 13o, rescisao |

### Limites Explicitos de Escopo (Anti-Requisitos)

> Decisoes deliberadas do que o Kalibrium NAO implementa. Evita scope creep e orienta dev/suporte.

| ID | O Sistema NAO... | Motivo |
|----|------------------|--------|
| NR-01 | Processa folha para empresas com mais de 200 funcionarios por tenant | Foco em PMEs de servico tecnico, nao RH enterprise |
| NR-02 | Emite NF-e de produto (apenas NFS-e de servico) | Foco em prestacao de servico, nao comercio/industria |
| NR-03 | Suporta mais de 1 CNPJ por tenant | Cada CNPJ = tenant separado. Grupo economico usa multi-tenant |
| NR-04 | Integra com ERPs de terceiros (SAP, TOTVS) via importacao automatica | MVP foca em operacao standalone. Integracao via API REST e responsabilidade do integrador |
| NR-05 | Armazena dados de cartao de credito | Pagamentos via gateway (Asaas). PCI compliance delegado ao gateway |
| NR-06 | Funciona como marketplace conectando prestadores a clientes | E ferramenta interna de gestao, nao plataforma de matchmaking |
| NR-07 | Suporta idiomas alem de pt-BR | MVP 100% Brasil. i18n planejado para Visao futura |
| NR-08 | Substitui ERP contabil (apuracao DAS, IRPJ, CSLL) | Sistema exporta dados de receita para contabilidade. Calculo tributario e do contador |

## Requisitos de Dominio — Compliance Regulatorio

### ISO/IEC 17025:2017 — Laboratorio de Calibracao

| Clausula | Requisito | Mapeamento no Sistema |
|----------|-----------|----------------------|
| 4.1 | Imparcialidade — laboratorio deve identificar riscos a imparcialidade | Audit trail em calibracoes, segregacao de funcoes (tecnico ≠ revisor) |
| 4.2 | Confidencialidade — proteger dados do cliente | Multi-tenant com isolamento, HTTPS, $hidden em dados sensiveis |
| 6.2 | Competencia pessoal — registrar qualificacao dos metrologistas | Model Training com validade, skill areas, certificacoes |
| 6.4 | Equipamentos — calibrar e manter equipamentos de medicao | Equipment lifecycle (ativo → calibracao → manutencao → descarte) |
| 7.2 | Selecao de metodo — usar metodos validados | Checklist por tipo de equipamento, procedimentos registrados |
| 7.5 | Registros tecnicos — manter registros completos e rastreaveis | Leituras, incerteza, certificado PDF, retencao 5 anos |
| 7.6 | Incerteza de medicao — estimar e registrar | EmaCalculator com ISO GUM, BCMath para precisao |
| 7.7 | Manuseio de itens — rastrear equipamentos do cliente | Equipment com serial_number, QR code, movement logs |
| 7.8 | Relatorios — certificados com conteudo minimo obrigatorio | Certificado PDF com: lab, cliente, equipamento, leituras, incerteza, conformidade, data, responsavel |
| 8.5 | Acoes para riscos — acoes corretivas (CAPA) | QualityAudit + QualityCorrectiveAction |
| 8.7 | Nao conformidades — registrar e tratar | Quality Audit com findings, corrective actions workflow |
| 8.9 | Auditorias internas — programar e executar | QualityAudit com status planned → executed → completed |

### ISO 9001:2015 — Gestao da Qualidade

| Clausula | Requisito | Mapeamento no Sistema |
|----------|-----------|----------------------|
| 4.4 | SGQ e seus processos — documentar processos criticos | Fluxos documentados no PRD, maquinas de estado nos models |
| 7.1.5 | Recursos de monitoramento — rastreabilidade metrologica | Cadeia de rastreabilidade ate padrao nacional via pesos padrao |
| 7.5 | Informacao documentada — controlar documentos e registros | Audit trail, versionamento de certificados, soft deletes |
| 8.5.2 | Identificacao e rastreabilidade — rastrear produtos/servicos | OS com numero sequencial, equipamentos com serial e QR |
| 8.7 | Controle de saidas nao conformes — tratar nao conformidades | Quality Audit workflow + CAPA |
| 9.1 | Monitoramento e medicao — medir satisfacao do cliente | NPS nos criterios de sucesso, portal do cliente para feedback |
| 9.2 | Auditoria interna — planejar e conduzir | QualityAudit com agenda, findings, corrective actions |
| 10.2 | Nao conformidade e acao corretiva — eliminar causas | CAPA workflow: nao conformidade → causa raiz → acao → verificacao |

### Portaria 671/2021 — Ponto Eletronico Digital

| Artigo/Clausula | Requisito | Mapeamento no Sistema |
|-----------------|-----------|----------------------|
| Art. 74-78 | Registro eletronico de ponto — registrar horarios de trabalho | RF-05.1: Clock-in/out com GPS e biometria |
| Art. 79 | Imutabilidade — registros nao podem ser alterados ou excluidos | RF-05.2: Cadeia criptografica verificavel, ajustes apenas via registro separado |
| Art. 80 | NSR (Numero Sequencial de Registro) — cada marcacao com numero unico sequencial | Implementado no TimeClockEntry com auto-incremento por tenant |
| Art. 81 | Espelho de ponto — disponibilizar ao trabalhador | RF-05.3: Espelho de ponto mensal com totais |
| Art. 84 | Comprovante — emitir comprovante a cada marcacao | Comprovante digital via notificacao push/email |
| Art. 87 | Exportacao AFDT — Arquivo Fonte de Dados Tratados | Implementado: AFDExportService gera formato fixed-width Portaria 671 (tipos 1-9) com verificacao de hash chain |
| Art. 87 | Exportacao ACJEF — Arquivo de Controle de Jornada para Efeitos Fiscais | Implementado: ACJEFExportService gera formato padrao MTE via FiscalAccessController |
| Art. 75 | Geofence — controle de localizacao da marcacao | RF-05.5: Geofence com deteccao de spoofing GPS |
| Art. 82 | Audit trail — rastrear acessos e alteracoes | RF-05.4: Ajustes com audit trail completo |

### LGPD (Lei Geral de Protecao de Dados — Lei 13.709/2018)

| Artigo | Requisito | Mapeamento no Sistema |
|--------|-----------|----------------------|
| Art. 6 | Principios — finalidade, adequacao, necessidade | Coleta apenas de dados necessarios para operacao |
| Art. 7 | Bases legais — consentimento ou obrigacao legal | RF-11.1: registrar base legal por tipo de tratamento |
| Art. 9 | Direito de acesso | RF-11.2: titular pode solicitar seus dados |
| Art. 15-16 | Direito de eliminacao | RF-11.3: eliminacao de dados nao obrigatorios |
| Art. 18 | Portabilidade | RF-11.4: exportacao em formato estruturado |
| Art. 18, §5 | Prazo de resposta ao titular | 15 dias uteis a partir da solicitacao |
| Art. 37 | Registro de tratamento | RF-11.5: log de consentimento |
| Art. 16 | Anonimizacao pos-retencao | RF-11.6: anonimizar apos periodo legal |
| Art. 41 | DPO (Encarregado de Dados) | RF-11.7: configurar DPO por tenant (nome, email publico) |
| Art. 48 | Notificacao de incidentes a ANPD e titulares | RF-11.8: registrar e notificar incidentes de seguranca em prazo razoavel |
| Art. 38 | RIPD (Relatorio de Impacto a Protecao de Dados) | RF-11.9: gerar relatorio de impacto sob demanda da ANPD |

#### Mapeamento de Dados Pessoais Tratados

| Categoria de Dado | Exemplos | Base Legal | Retencao |
|-------------------|----------|-----------|----------|
| Dados de identificacao (funcionarios) | Nome, CPF, RG, endereco, telefone | Execucao de contrato (Art. 7, V) + Obrigacao legal (CLT/eSocial) | Vigencia do contrato + 5 anos (CLT art. 11) |
| Dados de identificacao (clientes PJ) | CNPJ, inscricao estadual/municipal, razao social | Execucao de contrato (Art. 7, V) | Vigencia do contrato + 5 anos (CTN art. 173) |
| Dados de contato (clientes PF) | Nome, CPF, email, telefone | Execucao de contrato (Art. 7, V) | Vigencia do contrato + eliminacao sob pedido |
| Dados biometricos (ponto) | Selfie/liveness para marcacao | Obrigacao legal (Portaria 671) | 5 anos (Portaria 671) |
| Dados de geolocalizacao | GPS do tecnico, geofence | Obrigacao legal (Portaria 671) + Legitimo interesse (rastreamento operacional) | Vigencia do contrato |
| Dados financeiros | Conta bancaria (folha), dados de cobranca | Execucao de contrato (Art. 7, V) | 5 anos (CTN) |
| Dados de navegacao/uso | IP, user-agent, logs de acesso | Legitimo interesse (seguranca/auditoria) | 6 meses |

#### Compartilhamento com Terceiros

| Terceiro | Dados Compartilhados | Finalidade | Base Legal |
|----------|---------------------|-----------|-----------|
| FocusNFe / NuvemFiscal | CNPJ, inscricao municipal, valores | Emissao NFS-e | Obrigacao legal (legislacao fiscal) |
| Asaas | CPF/CNPJ, nome, valores, email | Geracao boleto/PIX | Execucao de contrato |
| SMTP Provider | Email do destinatario | Envio de certificados, boletos, notificacoes | Execucao de contrato |
| eSocial (Governo) | Dados trabalhistas completos | Transmissao de eventos obrigatorios | Obrigacao legal |

#### DPO (Encarregado de Dados)

Cada tenant deve configurar o DPO responsavel. Para o Kalibrium como operador/processador, o DPO e o administrador do sistema. O email do DPO deve ser publico e acessivel via Portal do Cliente.

### Obrigacoes Fiscais e Tributarias

| Obrigacao | Requisito | Mapeamento no Sistema |
|-----------|-----------|----------------------|
| ISS (Imposto Sobre Servicos) | Recolhimento municipal sobre servicos prestados. Aliquota varia por municipio (2% a 5%) | NFS-e emitida com codigo de servico (CNAE→item LC 116/2003). Aliquota configurada por tenant conforme municipio de inscricao |
| PIS/COFINS | Contribuicoes federais sobre receita bruta | Calculo nao e escopo do sistema (feito pelo contador). Sistema exporta dados de receita para contabilidade |
| Retencoes na fonte | IRRF, CSLL, ISS retido por clientes PJ que exigem retencao | Registrar retencao no contas a receber. NFS-e deve indicar se ha retencao de ISS |
| NFS-e — Emissao | Nota Fiscal de Servico Eletronica obrigatoria para toda prestacao de servico | RF-03.4: emissao automatica via gateway fiscal com fallback |
| NFS-e — Cancelamento | Cancelar NFS-e emitida com erro (prazo varia por municipio, geralmente 24-72h) | RF-03.11: solicitar cancelamento via gateway fiscal com motivo obrigatorio |
| NFS-e — Carta de correcao | Corrigir dados complementares sem cancelar a NFS-e | RF-03.12: emitir carta de correcao vinculada a NFS-e original |
| Codigo de servico municipal | Cada municipio tem tabela propria de codigos baseada na LC 116/2003 | Config por tenant: codigo de servico padrao + override por tipo de OS |

> **Nota:** O Kalibrium nao substitui o contador. O sistema emite NFS-e e registra receitas/despesas. Apuracao de impostos (DAS, IRPJ, CSLL) e responsabilidade do escritorio contabil. O sistema exporta dados em formato compativel (CSV/API).

## Inovacao e Diferenciais

### Inovacao Detectada

O Kalibrium nao e um ERP que adicionou calibracao como modulo. E um sistema de calibracao que cresceu para ser plataforma operacional. Essa inversao de origem cria diferenciais que concorrentes nao conseguem replicar facilmente:

| Inovacao | Descricao | Por que e dificil copiar |
|----------|-----------|--------------------------|
| **EMA nativo** | Calculo de incerteza com precisao decimal e formulas OIML R76 dentro da OS | ERPs precisariam de especialista em metrologia para implementar |
| **Carta de controle SPC** | Xbar-R com 3-sigma integrado ao equipamento | Requer conhecimento de controle estatistico de processo |
| **Cadeia criptografica trabalhista** | Registros de ponto encadeados com hash verificavel | Implementacao juridicamente vinculante, nao e feature — e compliance |
| **Ciclo receita automatico** | OS → Certificado → NFS-e → Boleto em < 5 min | Exige integracao real de 4 dominios diferentes |
| **PWA com offline** | Tecnico opera sem internet, sincroniza depois | Requer arquitetura de queue offline com resolucao de conflitos |

### Matriz Competitiva

| Capacidade | **Kalibrium** | **TOTVS Protheus** | **Senior** | **Calibre/LabControl** | **Planilha + App OS** |
|-----------|:---:|:---:|:---:|:---:|:---:|
| Gestao de OS com status | ✅ 17 status | ✅ | ✅ | ❌ | 🟡 Manual |
| Calibracao INMETRO (EMA, ISO GUM) | ✅ Nativo | ❌ Modulo separado | ❌ | ✅ Nativo | ❌ |
| Certificado PDF com incerteza | ✅ Auto | ❌ | ❌ | ✅ | ❌ |
| Carta de controle SPC (Xbar-R) | ✅ | ❌ | ❌ | 🟡 Basico | ❌ |
| NFS-e automatica | ✅ | ✅ | ✅ | ❌ | ❌ |
| Boleto/PIX integrado | ✅ | ✅ | ✅ | ❌ | ❌ |
| Conciliacao bancaria | ✅ Auto-match | ✅ | ✅ | ❌ | ❌ |
| Ponto eletronico Portaria 671 | ✅ Hash chain | 🟡 Modulo extra | 🟡 Modulo extra | ❌ | ❌ |
| PWA offline para tecnico | ✅ | ❌ | ❌ | ❌ | 🟡 App terceiro |
| Portal do cliente | ✅ 8 controllers | 🟡 Basico | 🟡 Basico | ❌ | ❌ |
| Multi-tenant SaaS | ✅ Nativo | ❌ On-premise | 🟡 | ❌ | ❌ |
| Ciclo receita automatico (OS→NFS-e→Boleto) | ✅ < 5 min | 🟡 Manual | 🟡 Manual | ❌ | ❌ |
| Preco estimado (PME) | R$ 300-800/mes | R$ 3.000-15.000/mes | R$ 2.000-10.000/mes | R$ 200-500/mes | R$ 0-100/mes |
| **Fit para calibracao + servico tecnico** | **⭐ Projetado para isso** | Generico demais | Generico demais | So calibracao | Muito limitado |

> **Posicionamento:** Kalibrium ocupa o espaco entre "sistema de calibracao puro" (que nao faz gestao operacional) e "ERP enterprise" (que custa 10x mais e nao faz calibracao). O unico que conecta OS + Calibracao INMETRO + Financeiro + RH em uma unica plataforma acessivel para PMEs.

### Risco de Inovacao

| Risco | Mitigacao |
|-------|----------|
| EMA pode ter erro de calculo | Validar com laboratorio acreditado RBC antes do go-live |
| Hash chain pode nao satisfazer auditor | Implementar exportacao do log para verificacao independente |
| Offline pode gerar conflito de dados | Estrategia last-write-wins com notificacao de conflito ao gestor |

## Requisitos por Tipo de Projeto — SaaS B2B

### Multi-Tenancy

| Requisito | Implementacao | Status |
|-----------|--------------|--------|
| Isolamento de dados | BelongsToTenant trait com global scope | 🟢 Implementado |
| Auto-assign tenant | tenant_id preenchido automaticamente na criacao | 🟢 Implementado |
| Config por tenant | Settings, logo, modelo de certificado por empresa | 🟡 Parcial |
| Billing por tenant | Controle de plano/modulos ativos por tenant | 🟢 Implementado (v2.0: SaasPlan + SaasSubscription + current_plan_id no Tenant. Planos com modulos, limites de usuarios e OS/mes. Ciclos monthly/annual, trial, cancel/renew). Nota: primeiro cliente sera piloto — ativacao de cobranca automatica pendente integracao gateway |

### Modelo de Permissoes (RBAC)

| Requisito | Implementacao | Status |
|-----------|--------------|--------|
| Permissoes granulares | Spatie Permission com 200+ permissoes | 🟢 Implementado |
| Roles pre-definidos | Admin, Gestor, Tecnico, Financeiro, Visualizador | 🟢 Implementado |
| Roles customizaveis | Cliente pode criar roles proprios | 🟡 Parcial |
| Super admin | Acesso total cross-tenant para operador | 🟢 Implementado |

### Integracao e API

| Requisito | Implementacao | Status |
|-----------|--------------|--------|
| REST API completa | Endpoints REST para todos os modulos com CRUD, filtros e paginacao. Documentacao OpenAPI auto-gerada via Laravel Scramble (`docs:openapi`) | 🟢 Implementado |
| Autenticacao | Laravel Sanctum (token-based) | 🟢 Implementado |
| Rate limiting | Throttle por rota (30-600 req/min) | 🟢 Implementado |
| Webhooks outbound | Eventos de pagamento, status OS, fiscal, INMETRO | 🟢 Implementado (5 models, middleware de verificacao, retry com log) |
| API versioning | /api/v1/ com suporte a evolucao | 🟢 Implementado |

## Principios de UX

| Principio | Descricao | Impacto |
|-----------|-----------|---------|
| **Mobile-first** | Toda interface e desenhada primeiro para 360px e adaptada para desktop | Tecnico em campo usa celular 90% do tempo |
| **3-toques max** | Acoes criticas (abrir OS, registrar leitura, coletar assinatura) em max 3 toques | Produtividade do tecnico = receita |
| **Dashboard-driven** | Gestor ve tudo em 1 tela, sem navegar entre modulos | Reducao de tempo de decisao |
| **Progressive disclosure** | Informacao detalhada sob demanda, nao na tela principal | Evitar sobrecarga cognitiva |
| **Offline-tolerant** | UI nunca mostra erro quando offline — mostra estado local e sincroniza depois | Confianca do tecnico no app |
| **Consistent patterns** | Tabelas paginadas, filtros em drawer, acoes em dropdown, formularios em modal/drawer | Curva de aprendizado curta |

### Hierarquia de Navegacao

```
├── Dashboard (home — visao do dia)
├── Operacional
│   ├── Ordens de Servico
│   ├── Agenda/Calendario
│   └── Service Calls
├── Calibracao
│   ├── Certificados
│   ├── Equipamentos
│   └── Pesos Padrao
├── Financeiro
│   ├── Contas a Receber
│   ├── Contas a Pagar
│   ├── Despesas
│   └── Conciliacao
├── CRM
│   ├── Clientes
│   ├── Pipeline
│   └── Orcamentos
├── RH
│   ├── Ponto Eletronico
│   ├── Funcionarios
│   └── Folha
├── Relatorios
├── Portal do Cliente (acesso separado)
└── Admin (super admin)
    ├── Tenants
    ├── Usuarios
    └── Configuracoes
```

### Componentes Reutilizaveis

| Componente | Uso | Padrao |
|-----------|-----|--------|
| DataTable | Listagens com paginacao, filtro, sort, export | Todas as telas de listagem |
| FormDrawer | Criar/editar registros | Formularios simples |
| StatusBadge | Exibir estado (OS, pagamento, ponto) | Cores consistentes por dominio |
| Timeline | Historico de mudancas de status | OS, pagamentos, audits |
| SignaturePad | Captura de assinatura digital | OS em campo |
| OfflineIndicator | Estado de conectividade | Header do PWA |

### Design Tokens

Valores canonicos que garantem consistencia visual em todo o sistema:

| Token | Valor | Uso |
|-------|-------|-----|
| `color-primary` | `#2563EB` (azul) | CTA, links, botoes primarios |
| `color-success` | `#16A34A` (verde) | Status concluido, pagamento confirmado |
| `color-warning` | `#D97706` (amarelo) | Status pendente, alertas nao-criticos |
| `color-danger` | `#DC2626` (vermelho) | Erros, cancelamentos, alertas criticos |
| `color-neutral` | `#6B7280` (cinza) | Texto secundario, bordas, placeholders |
| `font-base` | `Inter, sans-serif` | Todo o sistema |
| `font-mono` | `JetBrains Mono, monospace` | Numeros de serie, NSR, codigos |
| `radius-sm` | `4px` | Badges, inputs |
| `radius-md` | `8px` | Cards, modais, drawers |
| `shadow-card` | `0 1px 3px rgba(0,0,0,0.12)` | Elevacao de cards |
| `spacing-unit` | `4px` | Base do sistema de espacamento (multiplos de 4) |
| `breakpoint-mobile` | `< 768px` | Layout coluna unica, navegacao bottom tab |
| `breakpoint-tablet` | `768px – 1279px` | Layout 2 colunas, sidebar colapsada |
| `breakpoint-desktop` | `>= 1280px` | Layout completo, sidebar expandida |

### Acessibilidade

| Requisito | Meta | Ferramenta de Verificacao |
|-----------|------|--------------------------|
| Contraste de cor texto/fundo | WCAG AA (4.5:1 normal, 3:1 grande) | axe-core no CI |
| Navegacao por teclado | 100% das acoes alcancaveis sem mouse | Teste manual + axe |
| Labels em formularios | Todos os inputs com `<label>` associado | axe-core |
| Alt text em imagens funcionais | Obrigatorio (exceto decorativas com `alt=""`) | axe-core |
| Foco visivel | Outline visivel em todos os elementos interativos | Teste manual |
| ARIA roles | Componentes customizados com role e aria-* corretos | NVDA + Chrome |

## Definicao de Escopo Detalhada

### Criterios de Decisao MVP vs Pos-MVP

| Pergunta | Se SIM → MVP | Se NAO → Pos-MVP |
|----------|-------------|-------------------|
| O primeiro cliente PRECISA disso para operar? | MVP | Pos-MVP |
| Sem isso, o ciclo de receita quebra? | MVP | Pos-MVP |
| E obrigacao legal/regulatoria? | MVP | Pos-MVP |
| O tecnico em campo precisa disso? | MVP | Pos-MVP |

### Decisoes de Escopo

| Feature | Decisao | Justificativa |
|---------|---------|---------------|
| CRM Pipeline completo | Pos-MVP | Primeiro cliente ja tem carteira, nao precisa prospectar |
| Folha de Pagamento | Pos-MVP | Empresa pequena usa contador externo |
| Frota | Pos-MVP | Nao e core do negocio de calibracao |
| Estoque avancado | Pos-MVP | MVP precisa so de consumo basico na OS |
| eSocial completo | Pos-MVP | Empresa pequena delega pro contador |
| Relatorios gerenciais | Pos-MVP | Primeiro mes e sobrevivencia, nao analise |
| IA Preditiva | Visao | Precisa de dados historicos que ainda nao existem |
| IoT/Sensores | Visao | Mercado nao esta pronto |

### Roadmap de Implementacao MVP

> Ordem de implementacao dos itens bloqueadores para go-live. Baseada em dependencias tecnicas e risco legal.

| Fase | Semanas | Entregas | Bloqueador? | Dependencia |
|------|---------|----------|-------------|-------------|
| **F0 — LGPD** | 1-3 | RF-11.1 (base legal), RF-11.2 (acesso titular), RF-11.3 (eliminacao), RF-11.5 (consentimento), RF-11.7 (DPO), RF-11.8 (incidentes) | Sim — risco legal (multa ate 2% faturamento) | Nenhuma |
| **F1 — Integracao Fiscal** | 3-4 | Contrato FocusNFe, credenciais producao, RF-03.4 (NFS-e), RF-03.11 (cancelamento NFS-e) | Sim — ciclo receita incompleto sem NFS-e | F0 nao bloqueia, mas deve estar em andamento |
| **F2 — Integracao Cobranca** | 4-5 | Contrato Asaas, credenciais producao, RF-03.5 (boleto), RF-03.6 (PIX) | Sim — ciclo receita incompleto sem cobranca | F1 (NFS-e emitida antes do boleto) |
| **F3 — Compliance Ponto** | ~~5-6~~ | ~~RF-05.6 (AFDT), RF-05.7 (ACJEF)~~ | ✅ COMPLETO — AFDExportService + ACJEFExportService ja implementados | N/A |
| **F3.5 — Webhook Pagamento** | 5-6 | RF-19.5 (webhook Asaas), RF-19.6 (parcial), RF-19.7 (duplicado), RF-19.10 (maquina de estados) | Sim — sem baixa automatica, ciclo receita quebra | F2 |
| **F3.6 — Dashboard Unificado** | 6 | RF-01.11 (dashboard do dia consolidado) | Sim — prometido para persona 1 (Gestor) | Nenhuma |
| **F4 — Testes E2E Ciclo Receita** | 6-7 | Teste ponta a ponta: OS → Certificado → NFS-e → Boleto/PIX → Webhook → Baixa → Conciliacao | Sim — validacao do "momento aha!" | F1 + F2 + F3.5 completas |
| **F5 — Piloto** | 8-10 | Onboarding primeiro cliente (RF-08.7), acompanhamento 4 semanas, coleta NPS | Meta: ate 2026-06-01 | F0 + F4 completas |

**Criterio de avancamento:** cada fase so inicia quando a anterior tem testes passando e Gate Final verificado (Lei 7 do Iron Protocol).

> **Status atualizado em 2026-04-03 (v3.0):**
> - F0 LGPD: ✅ COMPLETO (v1.9 — 6 bloqueadores resolvidos)
> - F1 NFS-e: 🟡 Codigo pronto, pendente contrato comercial FocusNFe
> - F2 Cobranca: 🟡 Codigo pronto, pendente contrato comercial Asaas
> - F3 Compliance Ponto: ✅ COMPLETO (AFDT + ACJEF implementados)
> - F3.5 Webhook Pagamento: 🔴 Nao iniciado (RF-19.5-19.7 — bloqueador critico de go-live)
> - F3.6 Dashboard Unificado: 🔴 Nao iniciado (RF-01.11 — prometido a persona Gestor)
> - F4 Testes E2E: 🔴 Nao iniciado (depende F1+F2+F3.5)
> - F5 Piloto: 🔴 Nao iniciado (meta: 2026-06-01)

### Roadmap Pos-MVP

> Fases de evolucao apos go-live do primeiro cliente piloto. Datas sao metas, nao compromissos.

| Fase | Periodo | Entregas | Objetivo |
|------|---------|----------|----------|
| **P1 — Estabilizacao** | 2026-06 a 2026-07 | Correcoes do piloto, NPS > 40, SLA < 24h | Validar que o sistema funciona em producao real |
| **P2 — Gaps Criticos** | 2026-07 a 2026-08 | G-01 Quote→OS automatico (RF-21.2), G-02 FEFO configuravel por tenant, G-03 WhatsApp→CRM, RF-22 (equipamentos cliente), RF-23 (contratos recorrentes), RF-24 (garantias) | Completar fluxos desconectados antes de escalar |
| **P3 — Escala** | 2026-08 a 2026-10 | Onboarding 5-10 clientes (RF-08.7), docs API publica, Portal expandido (RF-06.7-06.12), CNAB para AR, notificacoes multi-canal (RF-13.9-13.15) | Provar modelo SaaS com receita recorrente |
| **P4 — Compliance** | 2026-10 a 2026-12 | RF-11.9 RIPD, RF-11.6 anonimizacao, RF-10.7 (exportacao contabil), RF-18.5 (enforcement de planos) | Conformidade regulatoria completa |
| **P5 — Diferenciacao** | 2027-Q1 | RF-15 IA, RF-16 Projetos, RF-03.15 (conciliacao inteligente), RF-20.16 (automacao configuravel) | Features competitivas para expansao de mercado |

**Marco critico:** ate 2026-06-01 o primeiro cliente piloto deve estar em producao (F5 do roadmap MVP).

## Requisitos Funcionais

### RF-01: Gestao de Ordens de Servico

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-01.1 | Criar OS com cliente, equipamento, tecnico, itens | MVP | 🟢 |
| RF-01.2 | Maquina de estados com 17 transicoes validadas | MVP | 🟢 |
| RF-01.3 | Atribuir multiplos tecnicos a uma OS | MVP | 🟢 |
| RF-01.4 | Registrar materiais/pecas usados com custo | MVP | 🟢 |
| RF-01.5 | Gerar PDF da OS com dados completos | MVP | 🟢 |
| RF-01.6 | Coletar assinatura digital do cliente no dispositivo | MVP | 🟢 |
| RF-01.7 | Rastrear deslocamento GPS com paradas | MVP | 🟢 |
| RF-01.8 | Calcular profitabilidade (receita - custos) | MVP | 🟢 |
| RF-01.9 | Faturar OS automaticamente (gerar AR) | MVP | 🟢 |
| RF-01.10 | Chat interno por OS | Pos-MVP | 🟢 |
| RF-01.11 | Dashboard operacional UNIFICADO do dia: OS abertas, tecnicos em campo (GPS), pagamentos vencendo, certificados pendentes, alertas — tudo em UMA tela em < 3s | MVP | 🔴 Dashboards por modulo existem (9 dashboards), visao consolidada do dia NAO existe |
| RF-01.12 | Log de alteracoes por OS: quem mudou o que, quando, com valores old/new (audit trail especifico, nao generico) | MVP | 🟡 Auditable trait existe, view de timeline de alteracoes pendente |
| RF-01.13 | Criar OS de garantia vinculada a OS original (ver RF-24) | MVP | 🔴 |

### RF-02: Calibracao e Certificados

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-02.1 | Wizard de calibracao guiado | MVP | 🟢 |
| RF-02.2 | Registrar leituras por ponto de calibracao | MVP | 🟢 |
| RF-02.3 | Calcular EMA conforme classe de precisao | MVP | 🟢 Implementado (OIML R76 + Portaria 157/2022) |
| RF-02.4 | Calcular incerteza de medicao (ISO GUM) | MVP | 🟢 |
| RF-02.5 | Gerar certificado PDF com dados INMETRO | MVP | 🟢 |
| RF-02.6 | Carta de controle Xbar-R com limites 3-sigma | MVP | 🟢 |
| RF-02.7 | Gerenciar pesos padrao com validade | MVP | 🟢 |
| RF-02.8 | Enviar certificado por email ao cliente | MVP | 🟢 |

### RF-03: Financeiro

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-03.1 | Contas a Receber com parcelas | MVP | 🟢 |
| RF-03.2 | Contas a Pagar com parcelas | MVP | 🟢 |
| RF-03.3 | Registrar pagamentos (parcial/total) | MVP | 🟢 |
| RF-03.4 | Emitir NFS-e automaticamente ao faturar OS | MVP | 🟢 Codigo ¹ | 🟡 Producao ² |
| RF-03.5 | Gerar boleto bancario para parcelas de contas a receber | MVP | 🟢 Codigo ¹ | 🟡 Producao ³ |
| RF-03.6 | Gerar QR code PIX para pagamento instantaneo | MVP | 🟢 Codigo ¹ | 🟡 Producao ³ |

> ¹ Codigo 100% implementado com testes: gateway fiscal (FocusNFe + NuvemFiscal fallback + contingencia + circuit breaker), gateway pagamento (Asaas PIX + Boleto).
> ² Pendente producao: contrato comercial FocusNFe + credenciais de producao.
> ³ Pendente producao: contrato comercial Asaas + credenciais de producao.
| RF-03.7 | Conciliacao bancaria (import OFX/CSV + auto-match) | MVP | 🟢 |
| RF-03.8 | Gestao de despesas com aprovacao | MVP | 🟢 |
| RF-03.9 | DRE e fluxo de caixa | Pos-MVP | 🟢 |
| RF-03.10 | Renegociacao de dividas (parcelar debitos vencidos com juros configuravel) | Pos-MVP | 🟢 |
| RF-03.11 | Cancelar NFS-e emitida com erro, com motivo obrigatorio e prazo municipal | MVP | 🟢 Implementado |
| RF-03.12 | Emitir carta de correcao vinculada a NFS-e original | Pos-MVP | 🟢 Implementado |
| RF-03.13 | Emitir nota de credito quando NFS-e e cancelada parcialmente ou servico tem desconto retroativo | Pos-MVP | 🔴 |
| RF-03.14 | Importar extrato bancario via OFX e CSV com parser automatico (campos: data, valor, descricao, tipo) | MVP | 🟡 Controller existe, parser OFX a validar |
| RF-03.15 | Configurar regras de auto-match para conciliacao (tolerancia de valor, janela de data, descricao regex) | Pos-MVP | 🔴 |
| RF-03.16 | Fechamento mensal: gerar relatorio consolidado de receitas, despesas, conciliacao e exportar para contabilidade (CSV) | MVP | 🔴 |
| RF-03.17 | Gerar demonstrativo de comissao para tecnico visualizar (read-only) com detalhamento por OS | Pos-MVP | 🔴 |

### RF-04: Clientes

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-04.1 | CRUD de clientes PF/PJ | MVP | 🟢 |
| RF-04.2 | Contatos, enderecos, documentos por cliente | MVP | 🟢 |
| RF-04.3 | Visao 360 graus (OS, financeiro, equipamentos) | MVP | 🟢 |
| RF-04.4 | Importacao CSV de clientes | MVP | 🟢 |

### RF-05: Ponto Eletronico

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-05.1 | Clock-in/out com GPS e biometria | MVP | 🟢 |
| RF-05.2 | Registros de ponto imutaveis com cadeia criptografica verificavel | MVP | 🟢 |
| RF-05.3 | Espelho de ponto mensal | MVP | 🟢 |
| RF-05.4 | Ajustes com audit trail | MVP | 🟢 |
| RF-05.5 | Geofence com deteccao de spoofing | MVP | 🟢 |
| RF-05.6 | Exportar AFDT (Arquivo Fonte de Dados Tratados) no formato padrao MTE | MVP | 🟢 Implementado |
| RF-05.7 | Exportar ACJEF (Arquivo de Controle de Jornada para Efeitos Fiscais) no formato padrao MTE | MVP | 🟢 Implementado |

### RF-06: Portal do Cliente

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-06.1 | Login separado para clientes | MVP | 🟢 |
| RF-06.2 | Visualizar OS e status em tempo real | MVP | 🟢 |
| RF-06.3 | Baixar certificados de calibracao filtrados por validade | MVP | 🟢 |
| RF-06.4 | Solicitar novo servico/recalibracao pelo portal | MVP | 🟢 |
| RF-06.5 | Visualizar notas fiscais e documentos financeiros | MVP | 🟡 Mostra contas a receber, NFS-e pendente de integracao no portal |
| RF-06.6 | Aprovar ou rejeitar orcamento enviado pela empresa, com assinatura digital e registro de aceite | MVP | 🟢 QuoteApprovalController implementado |
| RF-06.7 | Visualizar boleto/link PIX para pagamento direto no portal | MVP | 🔴 |
| RF-06.8 | Solicitar agendamento de visita tecnica com datas preferenciais | Pos-MVP | 🔴 |
| RF-06.9 | Avaliar servico prestado (NPS/rating) apos conclusao de OS | Pos-MVP | 🟡 SurveyController existe, integracao com conclusao de OS pendente |
| RF-06.10 | Receber notificacoes por email quando OS muda de status ou certificado fica pronto | MVP | 🔴 |
| RF-06.11 | Visualizar equipamentos cadastrados com historico de calibracoes e proxima recalibracao | MVP | 🔴 |
| RF-06.12 | Acompanhar historico de chamados/tickets abertos pelo portal | Pos-MVP | 🟢 TicketController implementado |

### RF-07: PWA Mobile

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-07.1 | Acesso a OS do dia no celular | MVP | 🟢 |
| RF-07.2 | Registrar leituras de calibracao mobile | MVP | 🟢 |
| RF-07.3 | Coletar assinatura no celular | MVP | 🟢 |
| RF-07.4 | Funcionar offline para consulta de OS, registro de leituras e assinatura | MVP | 🟡 Codigo ¹ | 🔴 Validacao ² |
| RF-07.5 | Sincronizar fila de acoes pendentes ao reconectar, em ordem cronologica | MVP | 🟡 Codigo ¹ | 🔴 Validacao ² |
| RF-07.6 | Registrar consumo de estoque/pecas em OS offline | MVP | 🔴 |
| RF-07.7 | Auto-save parcial em caso de perda de conexao ou bateria | MVP | 🔴 |
| RF-07.8 | Resolver conflitos de edicao simultanea (2+ tecnicos na mesma OS) com notificacao ao gestor | Pos-MVP | 🔴 |
| RF-07.9 | Comprimir e sincronizar imagens/fotos ao reconectar (max 2MB por foto) | MVP | 🟡 Camera funciona, upload em fila, compressao pendente |

> ¹ Codigo: syncEngine.ts + useOfflineMutation + IndexedDB existem. Matrix de funcionalidades abaixo.
> ² Validacao: NENHUMA funcionalidade offline foi testada E2E em cenario real (campo sem sinal). Status real diverge da matrix teorica — ver "Status real das capacidades offline" abaixo.

> **Matrix de funcionalidades offline (validada contra codigo):**
>
> | Feature | Leitura Offline | Escrita Offline | Cache | Conflito |
> |---------|:-:|:-:|---------|----------|
> | Consulta OS | ✅ | — | 7d individual, 24h listas | N/A |
> | Leituras calibracao | ✅ | ✅ | IndexedDB | Last-write-wins |
> | Assinatura cliente | ✅ | ✅ | Blob + queue | Last-write-wins |
> | Checklists | ✅ | ✅ | IndexedDB | Last-write-wins |
> | Despesas tecnico | ✅ | ✅ | IndexedDB | Last-write-wins |
> | Deslocamento/GPS | ✅ | ✅ | Queue | Last-write-wins |
> | Fotos/anexos | ✅ | ✅ | Blob queue | Upload ao reconectar |
> | Inventario ativos | ✅ | ✅ | IndexedDB | Last-write-wins |
> | Comissoes (read-only) | ✅ | ❌ | 24h cache | N/A |
> | Criacao de OS | ❌ | ❌ | — | — |
> | Login/auth | ❌ | ❌ | — | — |

> **Status real das capacidades offline (2026-04-02):**
>
> | Operacao | Status | Detalhes |
> |----------|--------|----------|
> | Consultar OS (cache) | 🔴 | Cache apenas estatico (HTML/CSS/JS) |
> | Criar OS offline | 🔴 | Nao implementado |
> | Registrar ponto offline | 🔴 | Nao implementado |
> | Leituras de calibracao | 🔴 | Nao implementado |
> | Capturar fotos | 🟡 | Camera funciona, upload em fila |
> | Assinatura digital | 🟡 | Captura funciona, sync pendente |
> | Consultar agenda | 🔴 | Nao implementado |
>
> **Estrategia de conflitos:** A definir (last-write-wins vs merge manual).
> **Cache maximo:** A definir (sugestao: 50MB por tenant).

### RF-08: Administracao do Sistema

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-08.1 | Criar e configurar novos tenants com CNPJ, plano e modulos ativos | MVP | 🟡 Tenant CRUD existe. Gap: vinculo automatico com plano SaaS (ver RF-18) e ativacao de modulos por plano |
| RF-08.2 | Criar usuarios e atribuir roles/permissoes por tenant | MVP | 🟢 |
| RF-08.3 | Configurar credenciais de emissao fiscal por tenant | MVP | 🟡 |
| RF-08.4 | Configurar credenciais de cobranca por tenant | MVP | 🟡 |
| RF-08.5 | Monitorar saude dos tenants (storage, erros, uso) | Pos-MVP | 🟡 Dashboard basico, sem drill-down por tenant |
| RF-08.6 | Importar dados em lote via CSV (clientes, equipamentos) | MVP | 🟢 |
| RF-08.7 | Wizard de onboarding para novo tenant: dados da empresa (CNPJ, razao social, inscricao municipal), logo, conta bancaria, usuarios iniciais, plano SaaS | MVP | 🔴 |
| RF-08.8 | Importacao de dados do sistema anterior: clientes, equipamentos, produtos (CSV com mapeamento de colunas) | MVP | 🟡 CSV import generico existe, wizard de mapeamento pendente |
| RF-08.9 | Trial experience: tenant novo recebe acesso a todos os modulos por periodo configuravel, com banner "Trial — X dias restantes" e emails de alerta 3 dias antes do fim | MVP | 🔴 |
| RF-08.10 | Checklist de configuracao inicial por tenant com progresso visivel (fiscal, cobranca, usuarios, logo, certificado modelo) | Pos-MVP | 🔴 |

### RF-09: API e Integracoes

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-09.1 | Autenticacao via token com expiracao configuravel | MVP | 🟢 |
| RF-09.2 | Endpoints REST para todos os modulos MVP com paginacao | MVP | 🟢 |
| RF-09.3 | Webhooks de saida para eventos de pagamento e mudanca de status | Pos-MVP | 🟢 5 models de webhook, middleware de verificacao, retry com log |
| RF-09.4 | Rate limiting por rota (min 30 req/min para mutacoes) | MVP | 🟢 |
| RF-09.5 | Documentacao interativa da API | Pos-MVP | 🟢 Laravel Scramble auto-gera OpenAPI specs (`docs:openapi` no composer.json) |
| RF-09.6 | Integrar mensagens WhatsApp ao CRM: receber via webhook, vincular a customer por telefone, criar CrmMessage, permitir resposta pelo sistema | Pos-MVP | 🟡 Webhook recebe, vinculo CRM pendente |

> **Nota de Versionamento de API:** Todos os endpoints sao prefixados com `/api/v1/`. Evolucao de versao segue as regras: (1) novas propriedades opcionais em respostas nao incrementam versao; (2) remocao ou renomeacao de campos requer nova versao `/api/v2/`; (3) versao antiga fica ativa por minimo 6 meses apos deprecacao anunciada via header `Deprecation`; (4) breaking changes sao comunicados com 30 dias de antecedencia via email e Webhook de tipo `api.deprecation`.

### RF-10: eSocial

> Eventos eSocial conforme layout v.S-1.2 (vigente). Transmissao via Web Service SOAP do governo federal. Requer certificado digital A1 (e-CNPJ) por tenant.

| ID | Requisito | Evento eSocial | Prioridade | Status |
|----|-----------|---------------|-----------|--------|
| RF-10.1 | Transmitir evento de admissao (cadastro do trabalhador) | S-2200 (Cadastramento Inicial / Admissao) | Pos-MVP | 🟡 Codigo ¹ | 🔴 Producao ² |
| RF-10.2 | Transmitir evento de desligamento | S-2299 (Desligamento) | Pos-MVP | 🟡 Codigo ¹ | 🔴 Producao ² |
| RF-10.3 | Transmitir evento de remuneracao mensal | S-1200 (Remuneracao do Trabalhador) | Pos-MVP | 🔴 Stub (XML vazio) |
| RF-10.4 | Transmitir eventos complementares (afastamento, alteracao contratual) | S-2230 (Afastamento), S-2206 (Alteracao Contratual) | Pos-MVP | 🔴 |
| RF-10.5 | Retry automatico com backoff exponencial em caso de falha | N/A (infraestrutura) | Pos-MVP | 🟡 Codigo existe, nao validado |
| RF-10.6 | Registrar protocolo de recebimento do governo | N/A (infraestrutura) | Pos-MVP | 🟡 Codigo existe, nao validado |

> ¹ Codigo: controller, service, jobs existem. XML gerado conforme layout v.S-1.2.
> ² Producao: transmissao real ao governo NUNCA validada. Requer certificado digital A1 (e-CNPJ) + ambiente de producao eSocial.
> ⚠ **Decisao pendente:** Avaliar se transmissao eSocial propria e necessaria para o publico-alvo (5-100 func). Alternativa: exportar dados para sistema contabil do cliente (ver RF-10.7).

**Eventos de tabela (pre-requisito para eventos periodicos):**

| Evento | Descricao | Status |
|--------|-----------|--------|
| S-1000 | Informacoes do Empregador (cadastro inicial do tenant no eSocial) | 🟡 Estrutura pronta |
| S-1010 | Tabela de Rubricas (tipos de verba: salario, horas extras, descontos) | 🟡 Estrutura pronta |
| S-1020 | Tabela de Lotacoes Tributarias | 🔴 |
| S-1070 | Tabela de Processos Administrativos/Judiciais | 🔴 |
| S-3000 | Exclusao de Eventos (correcao de envios errados) | 🟡 Estrutura pronta |

> **Fluxo de transmissao:** S-1000 (empresa) → S-1010 (rubricas) → S-2200 (admissao) → S-1200 (remuneracao mensal) → S-2299 (desligamento). Cada evento retorna protocolo + recibo que deve ser armazenado.

### RF-11: LGPD (Protecao de Dados)

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-11.1 | Registrar base legal para cada tipo de tratamento de dados pessoais | MVP | 🟢 Implementado |
| RF-11.2 | Permitir titular solicitar acesso aos seus dados pessoais | MVP | 🟢 Implementado |
| RF-11.3 | Permitir titular solicitar eliminacao de dados pessoais nao obrigatorios | MVP | 🟢 Implementado |
| RF-11.4 | Exportar dados pessoais do titular em formato estruturado (portabilidade) | Pos-MVP | 🟡 Request type portability existe, gerador de relatorio pendente |
| RF-11.5 | Registrar log de consentimento com data, finalidade e base legal | MVP | 🟢 Implementado |
| RF-11.6 | Anonimizar dados pessoais apos periodo de retencao legal | Pos-MVP | 🟡 Model existe, job automatico pendente |
| RF-11.7 | Configurar DPO (Encarregado de Dados) por tenant com nome e email publico | MVP | 🟢 Implementado |
| RF-11.8 | Registrar incidentes de seguranca e gerar relatorio de notificacao ANPD | MVP | 🟢 Implementado |
| RF-11.9 | Gerar RIPD (Relatorio de Impacto a Protecao de Dados) sob demanda | Pos-MVP | 🔴 |

> **✅ LGPD IMPLEMENTADA (v1.9):** 6 bloqueadores de go-live resolvidos (RF-11.1, 11.2, 11.3, 11.5, 11.7, 11.8). Modulo completo: 6 tabelas, 6 models (BelongsToTenant + Auditable), 5 controllers, 6 FormRequests, 14 permissoes Spatie, rotas com check.permission, notificacao automatica ao DPO. Pendentes pos-MVP: RF-11.4 (gerador portabilidade), RF-11.6 (job anonimizacao), RF-11.9 (RIPD).

### RF-12: Comissoes e Dashboards Operacionais

> Modulos transversais de visibilidade operacional e compensacao. RF-12 agrupa modulos operacionais transversais que compartilham contexto de gestao e baixa complexidade individual. Modulos com complexidade suficiente para RF proprio foram extraidos: RF-14 (Frota — 11 sub-req), RF-15 (IA), RF-16 (Projetos/Indicacoes).

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-12.1 | Calcular e exibir comissoes por tecnico com base em OS concluidas | Pos-MVP | 🟢 |
| RF-12.2 | Dashboard de comissoes com filtros por periodo e tecnico | Pos-MVP | 🟢 |
| RF-12.3 | Cadastrar e gerenciar ativos fixos da empresa com depreciacao | Pos-MVP | 🟢 |
| RF-12.4 | Exibir dashboard em tela TV para acompanhamento operacional em tempo real | Pos-MVP | 🟢 |
| RF-12.5 | Configurar layout e widgets do TV Dashboard por tenant | Pos-MVP | 🟢 |
| RF-12.6 | Dashboard de SLA com metricas de cumprimento de prazos por cliente e tipo | Pos-MVP | 🟢 |
| RF-12.7 | Dashboard de observabilidade com health check, erros e latencia | Pos-MVP | 🟢 |
| RF-12.8 | Portal do fornecedor para consulta de pagamentos e documentos | Pos-MVP | 🟡 |

### RF-14: Gestao de Frota

> Modulo enterprise-grade de gestao de veiculos, implementado com 11 controllers, 14 models e 9 paginas frontend.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-14.1 | CRUD de veiculos com dados completos (placa, modelo, ano, RENAVAM) | Pos-MVP | 🟢 |
| RF-14.2 | Checkin/inspecao de veiculo com checklist configuravel | Pos-MVP | 🟢 |
| RF-14.3 | Registro de abastecimento com calculo de consumo (km/l) | Pos-MVP | 🟢 |
| RF-14.4 | Gestao de manutencao preventiva e corretiva | Pos-MVP | 🟢 |
| RF-14.5 | Controle de pneus com vida util e rodizio | Pos-MVP | 🟢 |
| RF-14.6 | Registro de acidentes e sinistros | Pos-MVP | 🟢 |
| RF-14.7 | Gestao de seguros (vigencia, apolice, sinistro) | Pos-MVP | 🟢 |
| RF-14.8 | Rastreamento GPS com trips e historico de rotas | Pos-MVP | 🟢 |
| RF-14.9 | Pool de veiculos compartilhados entre tecnicos | Pos-MVP | 🟢 |
| RF-14.10 | Integracao de multas de transito | Pos-MVP | 🟢 |
| RF-14.11 | Analytics de frota com driver scoring | Pos-MVP | 🟢 |

### RF-15: Inteligencia Artificial

> Modulo de IA para consultas em linguagem natural e analytics preditivo.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-15.1 | Assistente IA: responder consultas sobre OS, financeiro e agenda do tenant em linguagem natural, com taxa de relevancia > 70% (medido por feedback thumbs up/down). Escopo limitado a dados do tenant autenticado | Visao | 🟡 |
| RF-15.2 | Deteccao de anomalias em metricas operacionais e financeiras | Visao | 🟡 |

### RF-16: Projetos e Indicacoes

> Modulos complementares de gestao de projetos e programa de referrals.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-16.1 | Gerenciar projetos com milestones e vinculo a OS | Pos-MVP | 🟡 |
| RF-16.2 | Programa de indicacoes (referrals) no CRM | Pos-MVP | 🟡 |

### RF-13: Notificacoes (Transversal)

> Sistema de notificacoes multi-canal que conecta todos os modulos. Ja existe no codigo (NotificationService, push, email, in-app) mas sem RF formal.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-13.1 | Enviar notificacao ao tecnico quando OS e atribuida ou alterada | MVP | 🟢 |
| RF-13.2 | Enviar notificacao ao gestor quando OS muda de status critico (concluida, cancelada) | MVP | 🟢 |
| RF-13.3 | Enviar notificacao ao financeiro quando pagamento e confirmado (webhook PIX/boleto) | MVP | 🟡 Depende de gateway ativo |
| RF-13.4 | Enviar email ao cliente com certificado de calibracao quando aprovado | MVP | 🟢 |
| RF-13.5 | Enviar email ao cliente com boleto/PIX quando gerado | MVP | 🟡 Depende de gateway ativo |
| RF-13.6 | Notificar gestor quando peso padrao do laboratorio esta vencendo (30 dias antes) | MVP | 🟢 |
| RF-13.7 | Notificar DPO quando solicitacao LGPD do titular e registrada | MVP | 🟢 Implementado |
| RF-13.8 | Permitir configuracao de canais por tipo de notificacao (email, push, in-app) por tenant | Pos-MVP | 🟡 |
| RF-13.9 | Enviar lembrete de pagamento ao cliente X dias antes do vencimento do boleto (configuravel por tenant) | MVP | 🔴 |
| RF-13.10 | Notificar cliente via portal/email quando certificado de calibracao fica pronto para download | MVP | 🔴 |
| RF-13.11 | Notificar gestor quando contrato recorrente esta vencendo (30 dias antes) | MVP | 🔴 |
| RF-13.12 | Notificar gestor quando equipamento do cliente tem calibracao vencendo | MVP | 🟡 GenerateCalibrationAlerts existe, falta notificacao ao cliente |
| RF-13.13 | Permitir cliente configurar preferencia de canal de comunicacao (email vs WhatsApp vs SMS) | Pos-MVP | 🔴 |
| RF-13.14 | Templates de email editaveis por tenant para cada tipo de evento (OS criada, boleto gerado, certificado pronto, etc.) | Pos-MVP | 🔴 |
| RF-13.15 | Integrar WhatsApp como canal de notificacao: enviar mensagens via API oficial (webhook recebe, envio pendente) | Pos-MVP | 🟡 Webhook recebe, vinculo CRM pendente (ver RF-09.6). Envio de mensagens nao implementado |

### RF-17: Estoque e Produtos

> Gestao completa de estoque multi-armazem com rastreamento de lotes, movimentacoes e inventario fisico. 15+ models implementados (StockMovement, Warehouse, InventoryItem, Product, Service, Lot, StockTransfer, ProductPricing, etc).

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-17.1 | Gerenciar multiplos armazens com localizacoes e responsaveis | MVP | 🟢 Implementado |
| RF-17.2 | Registrar movimentacoes de estoque (entrada, saida, transferencia, ajuste, devolucao) | MVP | 🟢 Implementado |
| RF-17.3 | Catalogo de produtos com precificacao por tier e historico de precos | MVP | 🟢 Implementado |
| RF-17.4 | Catalogo de servicos com composicao (ServiceCatalog + items) | MVP | 🟢 Implementado |
| RF-17.5 | Rastreamento de lotes (batch/lot) para manufatura e origem | MVP | 🟢 Implementado |
| RF-17.6 | Transferencia entre armazens com fluxo de aprovacao | MVP | 🟢 Implementado |
| RF-17.7 | Inventario fisico com contagem e reconciliacao automatica | MVP | 🟢 Implementado |
| RF-17.8 | Registro de descarte com motivo e certificado | MVP | 🟢 |
| RF-17.9 | Alertas de estoque minimo configuravel por produto | MVP | 🟢 Implementado |
| RF-17.10 | Montagem/desmontagem de kits de produtos | Pos-MVP | 🟢 Implementado |
| RF-17.11 | Kardex (historico de movimentacao por produto) | MVP | 🟢 Implementado |
| RF-17.12 | Inteligencia de estoque (sugestao de compra baseada em consumo) | Pos-MVP | 🟡 Parcial |

### RF-18: Billing SaaS

> Gestao de planos e assinaturas para modelo SaaS multi-tenant. Implementado em v2.0 (SaasPlan + SaasSubscription).

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-18.1 | CRUD de planos SaaS com nome, preco, ciclo (mensal/anual) e modulos incluidos | MVP | 🟢 Implementado |
| RF-18.2 | Criar assinatura vinculando tenant a plano com periodo de trial | MVP | 🟢 Implementado |
| RF-18.3 | Cancelar assinatura com motivo obrigatorio e data efetiva | MVP | 🟢 Implementado |
| RF-18.4 | Renovar assinatura com novo periodo e preco atualizado | MVP | 🟢 Implementado |
| RF-18.5 | Controlar acesso a modulos por tenant baseado no plano contratado via middleware, com comportamento definido para trial, downgrade, expiracao e upsell | MVP | 🔴 Schema existe (current_plan_id no Tenant), mas enforcement via middleware NAO implementado — tenant pode acessar modulos que nao pagou |
| RF-18.6 | Cobranca recorrente: gerar fatura D-5, retry D+1/D+3/D+7, dunning (3 emails), suspensao apos 3 falhas, proration em mudanca de plano, via gateway Asaas | Pos-MVP | 🔴 |
| RF-18.7 | Dashboard de billing para admin com MRR, churn e assinaturas ativas | Pos-MVP | 🔴 |

### RF-19: Ciclo de Receita End-to-End

> Orquestracao automatica do fluxo completo de receita: OS concluida ate pagamento conciliado. Conecta RF-01 (OS), RF-02 (Certificado), RF-03 (Financeiro) em uma maquina de estados unificada.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-19.1 | Disparar faturamento automatico quando OS muda para status "completed" com valor > 0 | MVP | 🟡 |
| RF-19.2 | Pular NFS-e e cobranca para OS com valor = 0 (garantia/cortesia) e marcar como "invoiced_exempt" | MVP | 🟡 |
| RF-19.3 | Emitir NFS-e automaticamente apos faturamento com fallback (FocusNFe → NuvemFiscal → contingencia offline) | MVP | 🟡 Gateway implementado. **Bloqueador:** contrato comercial + credenciais producao |
| RF-19.4 | Gerar boleto/PIX automaticamente apos NFS-e emitida e enviar ao cliente via email | MVP | 🔴 **NAO IMPLEMENTADO.** Interface existe, integracao real com Asaas pendente (codigo + contrato) |
| RF-19.5 | Receber webhook de confirmacao de pagamento (Boleto/PIX) do gateway Asaas e dar baixa automatica na parcela | MVP | 🔴 **NAO IMPLEMENTADO.** Depende de RF-19.4 |
| RF-19.6 | Tratar pagamento parcial via webhook: marcar parcela como "partial", registrar valor recebido, manter saldo devedor | MVP | 🔴 **NAO IMPLEMENTADO.** Depende de RF-19.5 |
| RF-19.7 | Detectar e rejeitar pagamento duplicado via idempotency key do webhook | MVP | 🔴 **NAO IMPLEMENTADO.** Depende de RF-19.5 |
| RF-19.8 | Alertar financeiro quando OS faturada ha mais de 48h sem NFS-e emitida | MVP | 🟡 Logica parcial |
| RF-19.9 | Cancelar cobranca vinculada quando NFS-e e cancelada (RF-03.11) | MVP | 🔴 **NAO IMPLEMENTADO.** Depende de cobranca funcionar (RF-19.4) |
| RF-19.10 | Maquina de estados do ciclo: OS_COMPLETED → INVOICED → NFSE_ISSUED → PAYMENT_GENERATED → PAID → RECONCILED | MVP | 🔴 **PARCIAL.** OS_COMPLETED→INVOICED funciona. INVOICED→NFSE_ISSUED depende de contrato. Demais elos NAO implementados |
| RF-19.11 | Gerar recibo de pagamento (PDF) automaticamente apos confirmacao de pagamento | Pos-MVP | 🔴 |
| RF-19.12 | Configurar por tenant se cada etapa e automatica ou requer aprovacao manual | Pos-MVP | 🔴 |

### RF-20: Funcionalidades Transversais

> Funcionalidades implementadas que suportam multiplos modulos. Formalizadas para garantir rastreabilidade e cobertura de testes.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-20.1 | Integrar Google Calendar para sincronizar agenda de OS e compromissos do tecnico | Pos-MVP | 🟢 Implementado |
| RF-20.2 | Gerenciar auditorias de qualidade ISO 17025 com checklist e findings | MVP | 🟢 Implementado |
| RF-20.3 | Planejar rotas de visitas tecnicos com otimizacao por proximidade | Pos-MVP | 🟢 Implementado |
| RF-20.4 | Coletar avaliacoes (ratings) de clientes apos conclusao de OS | Pos-MVP | 🟢 Implementado |
| RF-20.5 | Gerenciar tabelas de preco por cliente, servico e volume | MVP | 🟢 Implementado |
| RF-20.6 | Gerenciar centros de custo para alocacao de despesas e receitas | MVP | 🟢 Implementado |
| RF-20.7 | Configurar regras de cobranca automatica (dunning, prazos, juros) | Pos-MVP | 🟢 Implementado |
| RF-20.8 | Registrar e acompanhar follow-ups de clientes com prazos e responsaveis | MVP | 🟢 Implementado |
| RF-20.9 | Gerenciar documentos de clientes com upload, versionamento e categorias | MVP | 🟢 Implementado |
| RF-20.10 | Visao 360 do cliente: consolidar OS, financeiro, calibracoes, equipamentos, tickets e interacoes em uma unica tela | MVP | 🟢 Customer360Page.tsx implementado |
| RF-20.11 | Exportacao em lote de cadastros (clientes, produtos, equipamentos) em CSV | Pos-MVP | 🟢 BatchExportPage.tsx implementado |
| RF-20.12 | Historico de precos por produto/servico com grafico de evolucao | Pos-MVP | 🟢 PriceHistoryPage.tsx implementado |
| RF-20.13 | Merge/deduplicacao de clientes duplicados com selecao de dados a manter | Pos-MVP | 🟢 CustomerMergePage.tsx implementado |
| RF-20.14 | Configurar tema visual (cores, logo) por tenant | Pos-MVP | 🟢 InnovationController::themeConfig implementado |
| RF-20.15 | Programa de indicacoes (referrals): rastrear indicador, indicado, status, recompensa | Pos-MVP | 🟢 InnovationController::referral implementado |
| RF-20.16 | Motor de regras de automacao configuravel pelo usuario (trigger → condicao → acao) | Pos-MVP | 🟡 AutomationPage.tsx existe, backend de execucao de regras parcial |

### RF-21: Pipeline Comercial Integrado (Deal→Quote→OS)

> Fluxo integrado do pipeline de vendas ate execucao. Deal→Quote resolvido em 2026-04-10 (verificacao de codigo). Falta apenas Quote→OS automatico.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-21.1 | Converter Deal aprovado em Quote com dados pre-preenchidos (cliente, contato, itens da Price Table) | Pos-MVP | 🟢 **IMPLEMENTADO.** `ConvertDealToQuoteAction` (transacao, valida tenant/cliente/status, cria Quote+QuoteEquipment+QuoteItem). Rota `POST deals/{deal}/convert-to-quote` (crm.php:53). Frontend: `DealDetailDrawer.tsx:137`. Teste: `CrmDealConvertToQuoteTest.php` |
| RF-21.2 | Aprovar Quote e gerar OS automaticamente com itens e valores do orcamento | MVP | 🔴 Nao implementado — Quote→OS ainda requer criacao manual da OS |
| RF-21.3 | Dashboard de conversao do pipeline: Deal→Quote→OS→Receita com taxas por etapa | Pos-MVP | 🟡 Dashboard parcial |
| RF-21.4 | Converter Quote aprovado em Contrato Recorrente (RecurringContract) com frequencia e vigencia | Pos-MVP | 🔴 |
| RF-21.5 | Aprovacao de Quote pelo cliente via Portal (RF-06.6) atualiza status do Deal no CRM automaticamente | MVP | 🟡 QuoteApprovalController existe, vinculo com CRM pendente |
| RF-21.6 | Quote expirado (prazo de validade) muda status automaticamente e notifica vendedor | Pos-MVP | 🔴 |
| RF-21.7 | Quote com desconto acima do limite requer aprovacao gerencial antes de enviar ao cliente | Pos-MVP | 🔴 |

### RF-22: Equipamentos do Cliente (Ciclo de Vida)

> Gestao completa dos equipamentos pertencentes ao CLIENTE (nao ao Kalibrium). Rastreabilidade de calibracoes, alertas de vencimento e historico.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-22.1 | Cadastrar equipamento do cliente com: numero de serie, modelo, fabricante, localizacao, foto | MVP | 🟡 Equipment model existe, mas como entidade interna. Falta separacao "equipamento do cliente" vs "equipamento do lab" |
| RF-22.2 | Manter historico completo de calibracoes por equipamento (datas, resultados, tecnicos, padroes usados) | MVP | 🟡 Calibracoes vinculam a Equipment, mas historico consolidado nao existe como view |
| RF-22.3 | Alertar gestor e cliente quando calibracao esta vencendo (30, 15, 7 dias antes) | MVP | 🟡 GenerateCalibrationAlerts command existe, notificacao ao cliente pendente |
| RF-22.4 | Gerar QR code por equipamento para consulta rapida de historico em campo | Pos-MVP | 🟡 QR code existe, landing page de consulta pendente |
| RF-22.5 | Permitir cliente visualizar seus equipamentos e historico no Portal (RF-06.11) | MVP | 🔴 |
| RF-22.6 | Registrar transferencia de propriedade de equipamento (troca de cliente) | Pos-MVP | 🔴 |
| RF-22.7 | Calcular e exibir taxa de conformidade por equipamento (% calibracoes aprovadas) | Pos-MVP | 🔴 |

### RF-23: Contratos de Servico Recorrente

> Contratos entre o TENANT e SEU CLIENTE para servicos recorrentes (calibracao periodica, manutencao preventiva). NAO confundir com RF-18 (Billing SaaS = Kalibrium cobrando tenant).

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-23.1 | CRUD de contratos com: cliente, servicos, frequencia (mensal/trimestral/semestral/anual), valor, vigencia | MVP | 🟢 RecurringContractController implementado |
| RF-23.2 | Gerar OS automaticamente conforme frequencia do contrato com itens e valores pre-definidos | MVP | 🟡 Contrato existe, geracao automatica de OS pendente |
| RF-23.3 | Faturar automaticamente servicos do contrato conforme periodicidade acordada | Pos-MVP | 🔴 |
| RF-23.4 | Alertar gestor quando contrato esta vencendo (30 dias antes) para renovacao | MVP | 🔴 |
| RF-23.5 | Aplicar reajuste anual automatico (IGPM/IPCA) com notificacao ao cliente | Pos-MVP | 🔴 |
| RF-23.6 | Configurar SLA por contrato (tempo de resposta, tempo de resolucao) vinculado ao dashboard RF-12.6 | Pos-MVP | 🔴 |
| RF-23.7 | Calcular rentabilidade por contrato (receita vs custos das OS geradas) | Pos-MVP | 🔴 |

### RF-24: Garantia de Servico

> Controle de retornos por garantia. OS de garantia vinculada a OS original com custo zero para o cliente.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-24.1 | Configurar periodo de garantia por tipo de servico (dias) | MVP | 🔴 |
| RF-24.2 | Criar OS de garantia vinculada a OS original, com custo zero para o cliente | MVP | 🔴 |
| RF-24.3 | Bloquear criacao de OS de garantia quando periodo expirado (exibir alerta) | MVP | 🔴 |
| RF-24.4 | Dashboard de garantias: taxa de retorno por tipo de servico, custo total de garantia por periodo | Pos-MVP | 🔴 |
| RF-24.5 | Registrar motivo do retorno (defeito, mau uso, reclamacao) para analise de qualidade | MVP | 🔴 |

### RF-10.7: Exportacao de Dados para Sistema Contabil (Alternativa eSocial)

> Alternativa pragmatica a transmissao eSocial propria: exportar dados trabalhistas em formato compativel com os sistemas contabeis mais usados pelo publico-alvo.

| ID | Requisito | Prioridade | Status |
|----|-----------|-----------|--------|
| RF-10.7 | Exportar dados de folha, admissao e desligamento em CSV/XML compativel com Dominio Sistemas, Fortes e Questor | Pos-MVP | 🔴 |

> **Decisao de escopo (eSocial):** O publico-alvo do Kalibrium (empresas 5-100 func) quase sempre usa escritorio de contabilidade para eSocial. A estrategia recomendada e: (1) Manter ponto eletronico completo (Portaria 671 — obrigatorio). (2) Manter folha de pagamento como ferramenta interna de gestao. (3) Exportar dados para o contador em vez de transmitir diretamente ao governo. (4) Transmissao eSocial propria como feature premium opcional para empresas maiores. Isto reduz risco regulatorio (erro na transmissao = multa) e custo de manutencao (tabelas INSS/IRRF mudam anualmente).

## Requisitos Nao-Funcionais

### Padrao de Exportacao de Dados

Toda funcionalidade de exportacao no sistema DEVE seguir este padrao:

| Formato | Uso | Campos Obrigatorios | Encoding | Limite |
|---------|-----|-------------------|---------|--------|
| **CSV** | Listagens, relatorios financeiros, exportacao contabil | Cabecalho na 1a linha, separador `;`, datas em `YYYY-MM-DD` | UTF-8 com BOM | 100k linhas |
| **PDF** | Certificados de calibracao, boletos, NFS-e, relatorios formais | Logo do tenant, numero de pagina, data de geracao, rodape legal | PDF/A-1b (arquivavel) | 50MB |
| **JSON** | Exportacao via API, portabilidade LGPD (Art. 18 LGPD) | Envelope `{data, meta, tenant_id, generated_at}` | UTF-8 | Sem limite (paginado) |
| **XML** | eSocial, NFS-e (ABRASF), CNAB 240 | Schema validado contra XSD oficial | UTF-8 | Conforme spec do orgao |
| **AFDT/ACJEF** | Exportacao ponto eletronico (Portaria 671 MTE) | NSR sequencial, CNPJ, CPF, hash de integridade | ASCII | Conforme Portaria 671 |

> **Regra:** DataTable em toda listagem DEVE oferecer botao de exportacao CSV. Relatorios formais DEVEM oferecer PDF. Dados pessoais para LGPD DEVEM ser exportaveis em JSON. Formatos regulatorios (eSocial, NFS-e, AFDT) DEVEM seguir spec do orgao sem desvio.

### RNF-01: Performance

| Metrica | Meta | Metodo de Verificacao | Justificativa |
|---------|------|----------------------|---------------|
| Tempo de resposta API (p95) | < 500ms | Load test com k6: 100 concurrent users, 1000 requests. APM monitoring em producao | Tecnico em campo nao pode esperar |
| Carregamento dashboard | < 3s | Lighthouse CI no pipeline. Teste manual em 3G throttled | Gestor precisa de visao instantanea |
| Geracao de certificado PDF | < 5s | Teste automatizado com fixture de certificado completo | Fluxo do tecnico nao pode travar |
| Import CSV (1000 linhas) | < 30s | Teste com fixture CSV 1000 linhas. Job queue com progress | Onboarding de cliente novo |
| Conciliacao auto-match (500 lancamentos) | < 10s | Teste com dataset de 500 lancamentos + extrato OFX | Fechamento mensal nao pode demorar |

### RNF-02: Seguranca

| Requisito | Implementacao | Metodo de Verificacao |
|-----------|--------------|----------------------|
| Isolamento cross-tenant | BelongsToTenant em TODOS os models com tenant_id | Testes automatizados: criar recurso tenant A, tentar acessar de tenant B = 404. Minimo 1 teste cross-tenant por controller |
| Autenticacao | Sanctum com token expiravel | Teste: token expirado retorna 401. Teste: request sem token retorna 401 |
| Autorizacao | Spatie Permission com 200+ permissoes granulares | Teste: usuario sem permissao retorna 403. Cobertura: todos os endpoints protegidos |
| Audit trail | Auditable trait com old/new values em models criticos | Teste: alterar registro e verificar que audit log contem old/new values |
| Dados sensiveis | password, tokens escondidos via $hidden | Teste: serializar model e verificar que campos $hidden nao aparecem no JSON |
| SQL injection | Bindings parametrizados, zero interpolacao | PHPStan rule + grep por DB::raw sem bindings no CI. Zero ocorrencias |
| Mass assignment | $fillable ou $guarded em 100% dos models | PHPStan rule: todo model deve ter $fillable ou $guarded. Teste: enviar campo nao-fillable e verificar que e ignorado |
| HTTPS | Obrigatorio em producao | Verificacao: redirect HTTP→HTTPS. Header Strict-Transport-Security presente |
| Rate limiting | Throttle por endpoint | Teste: exceder limite e verificar 429 com Retry-After header |
| OWASP Top 10 | Protecao contra XSS, CSRF, injection | Scan trimestral com OWASP ZAP ou similar antes de releases major. Meta: 0 findings High/Critical |

### RNF-03: Disponibilidade e Resiliencia

| Metrica | Meta |
|---------|------|
| Uptime | 99.5% (max 3.6h downtime/mes) |
| RPO (Recovery Point Objective) | < 1 hora (backup automatico) |
| RTO (Recovery Time Objective) | < 30 minutos (rollback via deploy.sh) |
| Deploy | Zero downtime com rolling update (sem interrupcao de servico) |
| Fallback fiscal | NuvemFiscal como backup quando FocusNFe cai |

> **Playbook de DR detalhado:** Ver `docs/operacional/DISASTER-RECOVERY-PLAYBOOK.md` para procedimentos passo-a-passo, responsaveis, checklist de teste mensal e template de comunicacao a tenants.

### RNF-04: Escalabilidade

| Dimensao | Meta Inicial | Meta 12 meses | Metodo de Verificacao |
|----------|-------------|---------------|----------------------|
| Tenants simultaneos | 1-5 | 50 | Load test com 50 tenants ativos |
| Usuarios concorrentes (total) | 10-30 | 500 | Load test com 500 sessoes simultaneas |
| Usuarios por tenant | 1-30 | 1-100 | Teste funcional com 100 usuarios em 1 tenant |
| OS por mes (total) | 500 | 10.000 | Teste de carga com 10k registros/mes |
| Certificados por mes | 200 | 5.000 | Batch generation test |
| Storage por tenant | 1 GB | 10 GB | Monitoramento de disco por tenant |
| Tempo de resposta sob carga | p95 < 500ms | p95 < 1s com 500 usuarios | APM monitoring em producao |

### RNF-05: Compatibilidade

| Plataforma | Requisito |
|------------|-----------|
| Desktop | Chrome, Firefox, Edge (ultimas 2 versoes) |
| Mobile | Chrome Android, Safari iOS (PWA) |
| Resolucao minima | 360px (mobile), 1024px (desktop) |
| Acessibilidade | WCAG 2.1 nivel AA. Estado atual: ~51 componentes com aria attrs de ~200+. Metodo de verificacao: axe-core integrado ao CI via @axe-core/react (pre-escala). Meta: 0 violacoes critical/serious nas 10 telas mais usadas (Dashboard, OS List, OS Detail, Calibracao, Financeiro, Ponto, Portal Cliente, Clientes, Agenda, Login). Testes manuais com leitor de tela (NVDA) nas 3 jornadas criticas antes do go-live |
| Idioma | Portugues brasileiro (PT-BR) |

### RNF-06: Manutenibilidade

| Requisito | Meta | Metodo de Verificacao |
|-----------|------|----------------------|
| Cobertura de testes | > 80% dos 5 ciclos criticos (receita, despesa, operacional, RH, comercial) | Mapeamento de testes por ciclo no TESTING_GUIDE.md |
| Testes automatizados | 8385+ (Pest + Vitest) | `pest --parallel` + `vitest run` no CI |
| Tempo de execucao de testes | < 5 min (paralelo, 16 processos) | CI pipeline metric |
| Complexidade ciclomatica | < 15 por metodo em controllers e services | PHPStan level 6+ / ESLint complexity rule |
| Documentacao | PRD + TECHNICAL-DECISIONS sincronizados com codigo a cada release major; validacao por grep direto antes de atualizar status de RFs | Checklist de release |
| Debt ratio | < 10% do backlog em tech debt | Review trimestral |

### RNF-07: Observabilidade

| Requisito | Meta | Metodo de Verificacao |
|-----------|------|----------------------|
| Health check | Endpoint /api/health retorna status de DB, Redis, storage, queue em < 1s | Monitoramento externo a cada 5 min (UptimeRobot ou similar) |
| Logs estruturados | Formato JSON com: timestamp, level, message, context (user_id, tenant_id, request_id) | Grep nos logs de producao — 100% em formato JSON |
| Latencia por endpoint | Metricas de p50, p95, p99 por rota registradas | ObservabilityDashboardController com dados do ultimo dia/semana/mes |
| Taxa de erros | < 0.1% de requests retornando 5xx em operacao normal | Monitoramento de logs + alerta quando taxa excede threshold |
| Alertas | Notificacao automatica quando: health check falha, taxa de erro > 1%, latencia p95 > 2s, disco > 90% | Email + webhook para admin |
| Dashboard operacional | Painel com: requests/min, erros/min, latencia, jobs na fila, uso de recursos | ObservabilityDashboardController ja implementado |
| Request tracing | Cada request tem ID unico (X-Request-Id) propagado entre servicos e logs | Header presente em 100% das responses |

## Rastreabilidade — Mapeamento FR ↔ Jornada

| Jornada | FRs Relacionados |
|---------|-----------------|
| **J1 — Gestor (Dona Marcia)** | RF-01.1, RF-01.2, RF-01.8, RF-01.9, RF-01.10, RF-01.11, RF-03.1, RF-03.7, RF-03.9, RF-03.16, RF-22.3, RF-23.1–RF-23.7, RF-24.4 |
| **J2 — Tecnico (Joao)** | RF-01.1–RF-01.8, RF-02.1–RF-02.8, RF-05.1–RF-05.7, RF-07.1–RF-07.5, RF-13.1 |
| **J3 — Financeiro (Pedro)** | RF-03.1–RF-03.17, RF-01.9, RF-13.3, RF-13.5, RF-19.1–RF-19.12 |
| **J4 — Cliente (Ana)** | RF-06.1–RF-06.12, RF-02.5, RF-02.8, RF-09.6, RF-22.5 |
| **J5 — Admin (Rolda)** | RF-08.1–RF-08.6, RF-04.4, RF-15.1 |
| **J6 — Integrador API** | RF-09.1–RF-09.6 |
| **J7 — Titular LGPD (qualquer pessoa)** | RF-11.1–RF-11.9, RF-13.7 |
| **J8 — RH (Carla)** | RF-05.1–RF-05.7, RF-10.1–RF-10.6, RF-13.6 |
| **Cross-domain (Compliance)** | RF-10.1–RF-10.6 (eSocial), RF-11.1–RF-11.9 (LGPD), RF-03.11–RF-03.12 (Fiscal) |
| **Cross-domain (Complementar)** | RF-12.1–RF-12.2 (J1-Gestor), RF-12.3 (J3-Financeiro), RF-12.4–RF-12.7 (J1-Gestor), RF-14.2 (J2-Tecnico), RF-12.8 (J3-Financeiro) |
| **Cross-domain (Notificacoes)** | RF-13.1–RF-13.8 (todas as jornadas) |
| **J1 — Gestor (Estoque)** | RF-17.1 (armazens), RF-17.7 (inventario), RF-17.9 (alertas estoque minimo) |
| **J2 — Tecnico (Frota + Estoque)** | RF-14.1–RF-14.2 (checkin veiculo), RF-17.2 (movimentacao em campo) |
| **J5 — Admin (Billing + IA)** | RF-18.1–RF-18.5 (planos e assinaturas), RF-15.1–RF-15.2 (IA consultas) |
| **J1 — Gestor (Complementar)** | RF-12.1–RF-12.2 (comissoes), RF-12.4–RF-12.6 (dashboards TV/SLA), RF-16.1 (projetos) |
| **J1 — Gestor (Ciclo de Receita)** | RF-19.1, RF-19.2, RF-19.6, RF-19.8 (faturamento automatico e maquina de estados) |
| **J3 — Financeiro (Ciclo de Receita)** | RF-19.3, RF-19.4, RF-19.5, RF-19.7 (NFS-e, cobranca, conciliacao e cancelamento) |
| **J1 — Gestor (Transversais)** | RF-20.2–RF-20.16, RF-21.1–RF-21.7 |
| **J4 — Cliente (Rating + Portal)** | RF-20.4, RF-06.6–RF-06.12, RF-22.5 |
| **J1 — Gestor (Equipamentos)** | RF-22.1–RF-22.7 |
| **J1 — Gestor (Contratos)** | RF-23.1–RF-23.7 |
| **J1 — Gestor (Garantias)** | RF-24.1–RF-24.5 |
| **Cross-domain (Notificacoes Expandidas)** | RF-13.9–RF-13.15 |

### Rastreabilidade NFR ↔ Criterio de Sucesso

| NFR | Criterio de Sucesso Relacionado |
|-----|-------------------------------|
| RNF-01 Performance | Sucesso Tecnico: API p95 < 500ms, Dashboard < 3s |
| RNF-02 Seguranca | Sucesso Tecnico: Zero vazamento cross-tenant, audit trail |
| RNF-03 Disponibilidade | Sucesso Tecnico: 99.5% uptime, rollback < 5 min |
| RNF-04 Escalabilidade | Sucesso Negocio: suportar 5-10 clientes em 12 meses |
| RNF-05 Compatibilidade | Sucesso Usuario: tecnico opera no celular, gestor no desktop |
| RNF-06 Manutenibilidade | Sucesso Tecnico: 8385+ testes, < 5 min CI |
| RNF-07 Observabilidade | Sucesso Tecnico: deploy zero downtime, monitoramento proativo |

## Criterios de Aceitacao

> Todos os Criterios de Aceitacao em formato Gherkin (Dado/Quando/Entao). Organizados por grupo de RF.

### RF-01: Ordens de Servico

##### AC-01.1: Criar OS

- **Dado** um usuario com permissao de criacao de OS
- **Quando** preenche cliente, equipamento, tecnico e itens e submete
- **Entao** a OS e criada com status inicial (open), numero sequencial unico por tenant
- **E** o tecnico atribuido recebe notificacao
- **E** os itens com custo sao vinculados a OS

##### AC-01.2: Maquina de estados da OS

- **Dado** uma OS em qualquer status valido
- **Quando** o usuario tenta transicionar para outro status
- **Entao** apenas transicoes validas (17 definidas) sao permitidas
- **E** transicoes invalidas retornam erro 422 com motivo
- **E** cada transicao registra timestamp e usuario responsavel

#### AC-01.3: Atribuir multiplos tecnicos

- **Dado** uma OS existente
- **Quando** o gestor atribui 2+ tecnicos
- **Entao** todos os tecnicos veem a OS na sua lista
- **E** cada tecnico pode registrar atividades independentemente

#### AC-01.4: Registrar materiais/pecas

- **Dado** uma OS em andamento
- **Quando** o tecnico adiciona material com quantidade e custo unitario
- **Entao** o custo total da OS e recalculado automaticamente
- **E** o material e registrado com referencia ao estoque (se aplicavel)

#### AC-01.5: Gerar PDF da OS

- **Dado** uma OS com dados completos
- **Quando** o usuario solicita geracao do PDF
- **Entao** o PDF contem: dados do cliente, equipamento, itens, tecnico, status, valores, assinatura (se houver)
- **E** e gerado em < 5 segundos

#### AC-01.6: Coletar assinatura digital

- **Dado** uma OS em campo com o tecnico no dispositivo movel
- **Quando** o cliente assina na tela do dispositivo
- **Entao** a assinatura e armazenada vinculada a OS com timestamp
- **E** a assinatura e incluida no PDF da OS

#### AC-01.7: Rastrear deslocamento GPS

- **Dado** um tecnico com app aberto e GPS ativo
- **Quando** se desloca entre clientes
- **Entao** o sistema registra coordenadas com timestamps
- **E** calcula distancia total e tempo de deslocamento entre paradas

#### AC-01.8: Calcular profitabilidade

- **Dado** uma OS com itens (receita) e materiais/horas (custos)
- **Quando** a OS e finalizada
- **Entao** o sistema calcula margem = receita total - custos totais
- **E** a profitabilidade e visivel no dashboard e no detalhe da OS

#### AC-01.9: Faturar OS automaticamente

- **Dado** uma OS com status COMPLETED e itens com valor > 0
- **Quando** o usuario altera o status para INVOICED
- **Entao** o sistema cria uma Account Receivable com valor total da OS, gera parcelas conforme prazo do cliente, e emite NFS-e se credenciais configuradas
- **E** o tempo entre mudanca de status e criacao do AR e < 5 segundos

#### AC-01.10: Aprovar orcamento vinculado a OS

- **Dado** uma OS com orcamento (Quote) pendente de aprovacao
- **Quando** o cliente aprova o orcamento (via portal ou assinatura)
- **Entao** a OS muda de status para liberar execucao
- **E** os itens do orcamento sao confirmados como itens da OS
- **E** o tecnico e notificado que pode iniciar o servico

### RF-02: Calibracao e Certificados

##### AC-02.1: Wizard de calibracao guiado

- **Dado** um tecnico abrindo calibracao de um equipamento
- **Quando** inicia o wizard
- **Entao** o sistema exibe passo a passo: selecao de procedimento, pontos de calibracao, registro de leituras
- **E** nao permite avancar sem preencher campos obrigatorios do passo atual

#### AC-02.2: Registrar leituras por ponto

- **Dado** uma calibracao em andamento com pontos de medicao definidos
- **Quando** o tecnico registra leituras para cada ponto
- **Entao** os valores sao armazenados com precisao decimal adequada (BCMath)
- **E** o sistema valida limites minimos/maximos por tipo de equipamento

#### AC-02.3: Calcular EMA

- **Dado** leituras registradas e classe de precisao do equipamento
- **Quando** o sistema calcula o EMA
- **Entao** o resultado segue formulas OIML R76 para a classe de precisao
- **E** indica conformidade (dentro/fora do EMA) para cada ponto

#### AC-02.4: Calcular incerteza de medicao

- **Dado** leituras de calibracao completas
- **Quando** o sistema calcula a incerteza
- **Entao** aplica metodo ISO GUM (tipo A + tipo B) com fator de abrangencia k=2
- **E** o resultado e expresso com unidade e numero de casas decimais adequado

#### AC-02.5: Gerar certificado de calibracao

- **Dado** uma calibracao com todas as leituras registradas e checklist completo
- **Quando** o usuario solicita geracao do certificado
- **Entao** o sistema gera PDF com: dados do equipamento, leituras, incerteza calculada, conformidade, numero sequencial, dados do laboratorio
- **E** o certificado e gerado em < 5 segundos
- **E** o numero do certificado e unico e sequencial por tenant

#### AC-02.6: Carta de controle Xbar-R

- **Dado** um equipamento com historico de calibracoes (minimo 5)
- **Quando** o usuario acessa a carta de controle
- **Entao** exibe grafico Xbar-R com limites 3-sigma calculados
- **E** pontos fora de controle sao destacados visualmente

#### AC-02.7: Gerenciar pesos padrao

- **Dado** pesos padrao cadastrados no laboratorio
- **Quando** a data de validade se aproxima (30 dias)
- **Entao** o sistema gera alerta automatico ao gestor
- **E** calibracoes usando peso vencido sao bloqueadas ou geram advertencia

#### AC-02.8: Enviar certificado por email

- **Dado** um certificado gerado e aprovado
- **Quando** o sistema dispara envio
- **Entao** o email e enviado ao cliente com PDF anexo em < 1 minuto
- **E** o status de envio (enviado/falha) e registrado

### RF-03: Financeiro

##### AC-03.1: Contas a Receber com parcelas

- **Dado** uma fatura criada (manual ou via OS)
- **Quando** o usuario define parcelas
- **Entao** o sistema gera parcelas com datas de vencimento e valores proporcionais
- **E** cada parcela pode ser baixada individualmente (parcial/total)

#### AC-03.2: Contas a Pagar com parcelas

- **Dado** uma despesa ou compra registrada
- **Quando** o usuario cria conta a pagar com parcelas
- **Entao** parcelas sao geradas com datas e valores
- **E** o sistema controla status (pendente/pago/vencido) por parcela

#### AC-03.3: Registrar pagamentos

- **Dado** uma parcela em aberto (a receber ou a pagar)
- **Quando** um pagamento e registrado
- **Entao** aceita pagamento parcial (atualiza saldo) ou total (baixa parcela)
- **E** registra metodo de pagamento, data e comprovante

#### AC-03.4: Emitir NFS-e

- **Dado** uma fatura aprovada com dados fiscais do cliente (CNPJ, inscricao municipal)
- **Quando** o sistema dispara emissao da NFS-e
- **Entao** a nota e transmitida ao gateway fiscal, o status e atualizado (autorizada/rejeitada), e o PDF da nota e armazenado
- **E** em caso de rejeicao, o motivo e registrado e o usuario e notificado
- **E** em caso de indisponibilidade do gateway, o sistema entra em modo contingencia

#### AC-03.5: Gerar boleto bancario

- **Dado** uma parcela de conta a receber aprovada com dados do cliente (CNPJ/CPF)
- **Quando** o sistema gera o boleto via gateway de pagamento
- **Entao** o boleto e criado com codigo de barras, vencimento e valor
- **E** o link de pagamento e enviado ao cliente por email
- **E** em caso de falha no gateway, o erro e registrado e o usuario notificado

#### AC-03.6: Gerar QR code PIX

- **Dado** uma parcela de conta a receber
- **Quando** o sistema gera cobranca PIX via gateway
- **Entao** um QR code e gerado com valor e vencimento
- **E** o pagamento confirmado pelo gateway atualiza automaticamente a parcela
- **E** o tempo entre confirmacao PIX e baixa da parcela e < 5 minutos

#### AC-03.7: Conciliacao bancaria

- **Dado** um extrato bancario importado (OFX ou CSV) com N lancamentos
- **Quando** o sistema executa auto-match
- **Entao** lancamentos com valor exato e data proxima sao vinculados automaticamente a parcelas existentes
- **E** lancamentos nao conciliados ficam disponiveis para conciliacao manual
- **E** regras de match customizaveis sao aplicadas antes do match padrao

> **Regras de matching automatico (a implementar):**
> 1. Match por valor exato + data (tolerancia ±2 dias) — confianca 90%
> 2. Match por referencia do boleto no extrato — confianca 95%
> 3. Match por nome do pagador (fuzzy) + valor — confianca 80%
> 4. Tolerancia de valor: diferenca < R$0.10 aceita (taxas bancarias)
> 5. Meta: >80% dos lancamentos conciliados automaticamente
> 6. Itens nao conciliados: fila de revisao manual com sugestoes rankeadas

#### AC-03.8: Gestao de despesas com aprovacao

- **Dado** uma despesa registrada por um usuario
- **Quando** o valor excede limite configurado ou requer aprovacao
- **Entao** a despesa fica em status pendente ate aprovacao do gestor
- **E** aprovacao/rejeicao gera registro com justificativa e responsavel

#### AC-03.9: DRE e fluxo de caixa

- **Dado** um periodo selecionado (mes/trimestre/ano)
- **Quando** o gestor ou financeiro solicita o DRE
- **Entao** o sistema gera demonstrativo com: receita bruta, deducoes (impostos), receita liquida, custos diretos (materiais, mao de obra), despesas operacionais, resultado liquido
- **E** o fluxo de caixa exibe entradas e saidas realizadas por categoria
- **E** ambos sao exportaveis em PDF e CSV

#### AC-03.10: Renegociacao de dividas

- **Dado** uma conta a receber com parcelas vencidas
- **Quando** o financeiro inicia renegociacao
- **Entao** o sistema permite gerar novas parcelas com novos valores e datas, vinculadas a divida original
- **E** as parcelas originais sao marcadas como renegociadas (nao excluidas)
- **E** o historico de renegociacao e mantido com audit trail

#### AC-03.11: Cancelar NFS-e

- **Dado** uma NFS-e emitida com erro (dados incorretos do tomador, valor errado)
- **Quando** o financeiro solicita cancelamento dentro do prazo municipal
- **Entao** o sistema envia pedido de cancelamento ao gateway fiscal com motivo obrigatorio
- **E** o status da NFS-e e atualizado (cancelada/rejeitada)
- **E** a fatura vinculada volta ao status anterior para reemissao

#### AC-03.12: Obrigacoes tributarias

- **Dado** notas fiscais emitidas no periodo
- **Quando** o financeiro acessa o relatorio de obrigacoes tributarias
- **Entao** exibe consolidado de impostos por tipo (ISS, PIS, COFINS, IRPJ, CSLL)
- **E** calcula valores devidos por regime tributario do tenant (Simples/Lucro Presumido/Lucro Real)
- **E** permite exportar para integracao com software contabil

### RF-04: Clientes

##### AC-04.1: CRUD de clientes

- **Dado** um usuario com permissao de gestao de clientes
- **Quando** cria/edita cliente PF ou PJ
- **Entao** valida CNPJ/CPF (formato e digito verificador)
- **E** nao permite duplicatas de CNPJ/CPF no mesmo tenant

#### AC-04.2: Contatos e documentos

- **Dado** um cliente cadastrado
- **Quando** o usuario adiciona contatos, enderecos ou documentos
- **Entao** aceita multiplos contatos com tipos (principal, financeiro, tecnico)
- **E** documentos sao armazenados com tipo, validade e upload de arquivo

#### AC-04.3: Visao 360 graus

- **Dado** um cliente selecionado
- **Quando** o usuario acessa a visao 360
- **Entao** exibe em uma tela: OS (abertas/concluidas), financeiro (a receber/pago), equipamentos, certificados, historico
- **E** carrega em < 3 segundos

#### AC-04.4: Importacao CSV

- **Dado** um arquivo CSV com clientes (colunas: nome, CNPJ/CPF, email, telefone)
- **Quando** o usuario faz upload e confirma mapeamento de colunas
- **Entao** valida cada linha e importa as validas
- **E** retorna relatorio com linhas importadas e linhas rejeitadas com motivo
- **E** processa 1000 linhas em < 30 segundos

### RF-05: Ponto Eletronico

##### AC-05.1: Clock-in/out com GPS e biometria

- **Dado** um funcionario com app aberto
- **Quando** faz marcacao de ponto
- **Entao** registra timestamp, coordenadas GPS e verificacao biometrica
- **E** verifica se esta dentro do geofence configurado
- **E** registra NSR (Numero Sequencial de Registro) unico

#### AC-05.2: Imutabilidade do ponto

- **Dado** um registro de ponto criado
- **Quando** qualquer tentativa de alteracao direta e feita
- **Entao** o sistema rejeita a alteracao com erro
- **E** qualquer correcao so e possivel via registro de ajuste separado com justificativa e responsavel

#### AC-05.3: Espelho de ponto mensal

- **Dado** um funcionario com registros de ponto no mes
- **Quando** o gestor ou funcionario acessa o espelho
- **Entao** exibe todos os registros do mes com: entrada, saida, total diario, horas extras, faltas
- **E** calcula totais mensais automaticamente

#### AC-05.4: Ajustes com audit trail

- **Dado** um registro de ponto que precisa de correcao
- **Quando** o gestor cria um ajuste
- **Entao** o registro original permanece imutavel
- **E** o ajuste e registrado com: motivo, valor anterior, valor novo, responsavel, timestamp
- **E** o espelho reflete o valor ajustado

#### AC-05.5: Geofence com deteccao de spoofing

- **Dado** um perimetro configurado para o local de trabalho
- **Quando** o funcionario marca ponto fora do perimetro
- **Entao** a marcacao e registrada com flag "fora do geofence"
- **E** o gestor e notificado
- **E** tentativas de spoofing GPS sao detectadas e logadas

#### AC-05.6: Exportar AFDT

- **Dado** registros de ponto de um periodo selecionado
- **Quando** o gestor solicita exportacao AFDT
- **Entao** o sistema gera arquivo no formato padrao MTE (Portaria 671, art. 87)
- **E** o arquivo contem: NSR, data/hora, PIS do funcionario, tipo de marcacao
- **E** o arquivo e validavel por auditor do Ministerio do Trabalho

#### AC-05.7: Exportar ACJEF

- **Dado** registros de ponto e jornadas calculadas de um periodo
- **Quando** o gestor solicita exportacao ACJEF
- **Entao** o sistema gera arquivo no formato padrao MTE para efeitos fiscais
- **E** o arquivo contem: jornadas diarias, horas extras, faltas, abonos
- **E** totais conferem com o espelho de ponto do mesmo periodo

### RF-06: Portal do Cliente

##### AC-06.1: Login do Portal do Cliente

- **Dado** um ClientPortalUser com credenciais validas
- **Quando** faz login no portal
- **Entao** recebe token Sanctum com scope `portal:access`
- **E** so consegue ver dados do proprio customer_id
- **E** nao tem acesso a rotas internas do sistema

#### AC-06.2: Visualizar OS e status

- **Dado** um cliente logado no portal
- **Quando** acessa a lista de OS
- **Entao** ve apenas OS do seu customer_id
- **E** cada OS mostra status atual, tecnico atribuido e previsao
- **E** atualizacoes de status sao refletidas em tempo real

### RF-07: PWA Mobile

##### AC-07.1: Acesso a OS do dia no celular

- **Dado** um tecnico logado no app mobile
- **Quando** acessa a tela inicial
- **Entao** ve lista de OS do dia ordenadas por horario previsto
- **E** cada OS mostra endereco, cliente, tipo de servico

#### AC-07.2: Registrar leituras mobile

- **Dado** um tecnico com OS aberta no celular
- **Quando** registra leituras de calibracao
- **Entao** os campos aceitam entrada numerica com precisao decimal
- **E** o calculo de EMA e executado localmente

#### AC-07.3: Coletar assinatura no celular

- **Dado** uma OS finalizada no celular
- **Quando** o cliente assina na tela touch
- **Entao** a assinatura e capturada e vinculada a OS
- **E** funciona mesmo em telas pequenas (minimo 360px)

#### AC-07.4: Funcionamento offline

- **Dado** um tecnico com app aberto e dados cacheados
- **Quando** a conexao de internet e perdida
- **Entao** o tecnico consegue visualizar OS, registrar leituras e coletar assinatura
- **E** as acoes sao salvas em fila local
- **E** ao reconectar, a fila e sincronizada automaticamente em ordem cronologica

#### AC-07.5: Sincronizacao offline

- **Dado** um tecnico que realizou acoes offline (leituras, assinaturas)
- **Quando** a conexao de internet e restabelecida
- **Entao** o syncEngine processa a fila em ordem cronologica
- **E** conflitos sao resolvidos com estrategia last-write-wins
- **E** o gestor e notificado se houver conflitos detectados

#### AC-06.3: Download de certificados no portal

- **Dado** um cliente logado no portal com equipamentos calibrados
- **Quando** acessa a lista de certificados
- **Entao** ve certificados filtrados por validade (validos, vencendo em 30 dias, vencidos)
- **E** consegue baixar o PDF de cada certificado
- **E** so ve certificados do proprio tenant/customer

#### AC-06.4: Solicitar servico pelo portal

- **Dado** um cliente logado no portal
- **Quando** solicita novo servico/recalibracao
- **Entao** um ServiceCall e criado automaticamente para a equipe
- **E** o cliente recebe confirmacao com numero do chamado
- **E** pode acompanhar status via PortalTicketController

#### AC-06.5: Visualizar notas fiscais no portal

- **Dado** um cliente logado no portal com faturas emitidas
- **Quando** acessa a secao financeira do portal
- **Entao** ve lista de contas a receber com status (pendente/pago/vencido)
- **E** consegue visualizar e baixar NFS-e vinculadas (quando emitidas)
- **E** ve boletos/PIX pendentes com link de pagamento

### RF-08: Administracao do Sistema

##### AC-08.2: Criar usuarios e atribuir roles

- **Dado** um admin de tenant logado
- **Quando** cria novo usuario com email e role
- **Entao** o usuario recebe convite por email
- **E** as permissoes do role sao aplicadas automaticamente
- **E** o usuario so acessa dados do proprio tenant

#### AC-08.3: Configurar credenciais fiscais

- **Dado** um tenant com contrato fiscal ativo
- **Quando** o admin configura credenciais do gateway fiscal
- **Entao** o sistema valida as credenciais com chamada de teste
- **E** armazena de forma segura (criptografado)
- **E** emissoes futuras usam as credenciais do tenant

#### AC-08.4: Configurar credenciais de cobranca

- **Dado** um tenant com contrato de gateway de pagamento
- **Quando** o admin configura credenciais
- **Entao** o sistema valida com chamada de teste ao gateway
- **E** armazena de forma segura
- **E** boletos/PIX futuros usam as credenciais do tenant

#### AC-08.5: Monitorar saude dos tenants

- **Dado** um super admin logado no painel de administracao
- **Quando** acessa o monitor de tenants
- **Entao** ve para cada tenant: uso de storage, quantidade de usuarios ativos, taxa de erros nas ultimas 24h, ultimo login
- **E** tenants com anomalias (storage > 80%, erros > threshold, inativo > 30 dias) sao destacados
- **E** permite drill-down para ver detalhes de cada tenant

#### AC-08.6: Importar dados em lote

- **Dado** um admin com arquivo CSV (clientes ou equipamentos)
- **Quando** faz upload com mapeamento de colunas
- **Entao** importa registros validos com tenant_id do admin
- **E** rejeita registros invalidos com motivo detalhado por linha
- **E** processa 1000 linhas em < 30 segundos

#### AC-08.1: Criar tenant

- **Dado** um super admin logado
- **Quando** cria novo tenant com CNPJ, nome e plano
- **Entao** o tenant e criado com isolamento de dados (BelongsToTenant)
- **E** um usuario admin e criado para o tenant
- **E** permissoes padrao sao atribuidas conforme o plano

### RF-09: API e Integracoes

##### AC-09.1: Autenticacao via token

- **Dado** um usuario ou sistema externo com credenciais validas
- **Quando** solicita token via endpoint de autenticacao
- **Entao** recebe token com expiracao configuravel
- **E** tokens expirados retornam 401
- **E** cada token e associado a um tenant e respeita permissoes do usuario

#### AC-09.2: Endpoints REST com paginacao

- **Dado** um consumidor autenticado da API
- **Quando** consulta qualquer endpoint de listagem
- **Entao** a resposta e paginada (default 15 itens, configuravel via ?per_page)
- **E** inclui metadados de paginacao (total, current_page, last_page)
- **E** dados retornados sao apenas do tenant do token

#### AC-09.4: Rate limiting

- **Dado** um consumidor da API fazendo requisicoes
- **Quando** excede o limite configurado por rota (min 30 req/min para mutacoes)
- **Entao** retorna 429 Too Many Requests com header Retry-After
- **E** o limite e aplicado por usuario/token, nao por IP

#### AC-09.5: Documentacao interativa da API

- **Dado** a API REST do Kalibrium
- **Quando** um integrador acessa o endpoint de documentacao
- **Entao** exibe documentacao Swagger/OpenAPI com todos os endpoints, parametros, exemplos de request/response
- **E** permite testar endpoints diretamente na interface (sandbox)
- **E** a documentacao e atualizada automaticamente quando rotas mudam

#### AC-09.6: Integracao WhatsApp→CRM

- **Dado** uma mensagem WhatsApp recebida via webhook
- **Quando** o numero do remetente corresponde a um cliente cadastrado (por telefone)
- **Entao** a mensagem e criada como CrmMessage vinculada ao customer
- **E** o atendente responsavel recebe notificacao in-app
- **Dado** o numero NAO corresponde a nenhum cliente
- **Quando** a mensagem e recebida
- **Entao** a mensagem e criada como CrmMessage tipo "lead" sem customer vinculado
- **E** aparece na fila de triagem do CRM

### RF-10: eSocial

##### AC-10.1: Transmitir evento de admissao

- **Dado** um funcionario recem-admitido com dados completos (CPF, CTPS, cargo, salario)
- **Quando** o gestor confirma a admissao no sistema
- **Entao** o sistema gera XML do evento S-2200 no formato eSocial v.S-1.2
- **E** transmite ao webservice do governo com certificado digital A1
- **E** registra o protocolo de recebimento ou erro de rejeicao
- **E** permite retry automatico com backoff em caso de falha

#### AC-10.2: Transmitir evento de desligamento

- **Dado** um funcionario com desligamento registrado
- **Quando** o RH confirma o desligamento
- **Entao** o sistema gera XML do evento S-2299
- **E** transmite ao governo e registra protocolo
- **E** vincula ao calculo de rescisao (se existir)

#### AC-10.3: Transmissao de evento de alteracao contratual

- **Dado** uma alteracao contratual registrada (cargo, salario, jornada)
- **Quando** o gestor confirma a alteracao no sistema
- **Entao** o evento S-2206 e gerado com os dados novos e anteriores
- **E** o XML e validado contra o schema oficial do eSocial antes de envio
- **E** o status do evento e rastreado (pendente, transmitido, aceito, rejeitado)

#### AC-10.4: Transmissao de evento de afastamento

- **Dado** um afastamento registrado (ferias, licenca, acidente)
- **Quando** o gestor confirma o afastamento com datas e motivo
- **Entao** o evento S-2230 e gerado com codigo de afastamento correto (tabela 18 eSocial)
- **E** o retorno do afastamento gera evento complementar automaticamente
- **E** o status do evento e rastreado ate aceitacao pelo governo

#### AC-10.5: Retry com backoff exponencial

- **Dado** uma transmissao eSocial que falhou por indisponibilidade do governo
- **Quando** o sistema detecta o erro
- **Entao** agenda retry com backoff exponencial (1min, 5min, 15min, 1h)
- **E** notifica o RH apos 3 falhas consecutivas
- **E** registra cada tentativa no log de transmissao

#### AC-10.6: Registrar protocolo de recebimento

- **Dado** uma transmissao eSocial bem-sucedida
- **Quando** o governo retorna o protocolo
- **Entao** o sistema armazena numero do protocolo, data/hora, tipo de evento
- **E** vincula ao registro do funcionario
- **E** permite consulta futura por protocolo

### RF-14: Gestao de Frota (Complementar)

##### AC-14.1: CRUD de veiculos

- **Dado** um admin ou gestor de frota
- **Quando** cadastra um veiculo
- **Entao** registra placa, modelo, ano, RENAVAM, quilometragem atual
- **E** o veiculo e vinculado ao tenant e pode ser atribuido a tecnicos

#### AC-14.3: Registro de abastecimento

- **Dado** um tecnico com veiculo atribuido
- **Quando** registra abastecimento
- **Entao** o sistema calcula consumo medio (km/l) com base na quilometragem anterior
- **E** armazena local, valor, litros e tipo de combustivel

#### AC-14.4: Manutencao preventiva

- **Dado** um veiculo com plano de manutencao configurado
- **Quando** a quilometragem ou data limite e atingida
- **Entao** o sistema gera alerta ao gestor de frota
- **E** permite registrar a manutencao realizada com custo e fornecedor

#### AC-14.5: Controle de pneus

- **Dado** um veiculo com pneus cadastrados e vida util configurada
- **Quando** a quilometragem acumulada atinge 80% da vida util
- **Entao** o sistema gera alerta de troca preventiva
- **E** sugere rodizio baseado na posicao atual e historico

#### AC-14.6: Registro de acidentes

- **Dado** um veiculo da frota envolvido em acidente
- **Quando** o gestor registra o sinistro com data, local e descricao
- **Entao** o veiculo e marcado como "em sinistro" e removido do pool disponivel
- **E** o seguro vinculado (se houver) e notificado automaticamente

#### AC-14.7: Gestao de seguros

- **Dado** um veiculo com apolice de seguro cadastrada
- **Quando** faltam 30 dias para vencimento da apolice
- **Entao** o sistema notifica o gestor de frota
- **E** exibe alerta visual no dashboard de frota

#### AC-14.8: Rastreamento GPS

- **Dado** um veiculo com GPS ativo em deslocamento
- **Quando** o sistema recebe coordenadas de posicao
- **Entao** a trip e registrada com: origem, destino, distancia, duracao
- **E** o historico de rotas fica disponivel para consulta por periodo

#### AC-14.9: Pool de veiculos

- **Dado** veiculos marcados como "compartilhados" no pool
- **Quando** um tecnico solicita veiculo para OS
- **Entao** o sistema mostra apenas veiculos disponiveis (nao reservados, nao em manutencao)
- **E** a reserva e vinculada a OS com periodo definido

#### AC-14.10: Multas de transito

- **Dado** uma multa de transito vinculada a placa de veiculo da frota
- **Quando** o gestor registra a multa
- **Entao** o sistema identifica o motorista responsavel pela data/hora
- **E** vincula o custo ao centro de custo do veiculo

#### AC-14.11: Analytics de frota

- **Dado** dados acumulados de abastecimento, manutencao e viagens
- **Quando** o gestor acessa o dashboard de analytics de frota
- **Entao** exibe: consumo medio por veiculo (km/l), custo total por veiculo, driver scoring baseado em consumo e multas
- **E** permite comparativo entre periodos e entre veiculos

### RF-17: Estoque e Produtos

##### AC-17.1: Gerenciar armazens

- **Dado** um gestor de estoque autenticado
- **Quando** cria um novo armazem
- **Entao** define nome, localizacao, responsavel e status (ativo/inativo)
- **E** o armazem e isolado por tenant

#### AC-17.2: Registrar movimentacao

- **Dado** um produto em estoque
- **Quando** uma movimentacao e registrada (entrada, saida, transferencia, ajuste, devolucao)
- **Entao** o saldo e atualizado automaticamente
- **E** o historico (kardex) e registrado com usuario, data/hora, quantidade anterior e nova
- **E** movimentacoes de saida NAO permitem saldo negativo (exceto se config permitir)

#### AC-17.5: Rastreamento de lotes

- **Dado** um produto com controle de lote ativo
- **Quando** uma entrada e registrada
- **Entao** exige numero de lote, data de fabricacao e validade
- **E** saidas seguem regra FIFO ou FEFO conforme configuracao do tenant

#### AC-17.3: Catalogo de produtos com precificacao

- **Dado** um produto cadastrado com precificacao por tier
- **Quando** o usuario consulta o preco para um volume especifico
- **Entao** o sistema aplica o tier correto automaticamente
- **E** exibe historico de precos anteriores

#### AC-17.4: Catalogo de servicos

- **Dado** um servico cadastrado com itens de composicao
- **Quando** o servico e vinculado a uma OS
- **Entao** os itens de composicao sao automaticamente adicionados a OS
- **E** o custo total do servico e calculado pela soma dos itens

#### AC-17.6: Transferencia entre armazens

- **Dado** uma solicitacao de transferencia de itens entre armazens
- **Quando** o gestor do armazem destino aprova a transferencia
- **Entao** os itens sao debitados do armazem origem e creditados no destino
- **E** a movimentacao gera registro no kardex de ambos os armazens
- **E** transferencias pendentes aparecem em fila de aprovacao

#### AC-17.7: Inventario fisico

- **Dado** um armazem selecionado para inventario
- **Quando** o gestor inicia contagem fisica
- **Entao** o sistema gera lista de produtos esperados com quantidades do sistema
- **E** permite registrar quantidade contada por item
- **E** calcula divergencia e permite ajuste com justificativa obrigatoria

#### AC-17.8: Descarte com certificado

- **Dado** itens marcados para descarte com motivo registrado
- **Quando** o descarte e confirmado pelo responsavel
- **Entao** os itens sao baixados do estoque com movimentacao tipo "descarte"
- **E** um certificado de descarte e gerado em PDF com: itens, quantidades, motivo, responsavel, data

#### AC-17.9: Alertas de estoque minimo

- **Dado** um produto com estoque minimo configurado (ex: 10 unidades)
- **Quando** o saldo atinge ou fica abaixo do minimo
- **Entao** o sistema envia notificacao ao gestor de estoque
- **E** o produto aparece destacado na listagem com badge "estoque baixo"

#### AC-17.10: Montagem/desmontagem de kits

- **Dado** um kit definido com lista de componentes e quantidades
- **Quando** o gestor solicita montagem de N kits
- **Entao** os componentes sao debitados do estoque proporcionalmente
- **E** os kits montados sao creditados como novo item
- **E** o processo inverso (desmontagem) credita os componentes e debita o kit

#### AC-17.11: Kardex

- **Dado** um produto com historico de movimentacoes
- **Quando** o usuario consulta o kardex do produto
- **Entao** exibe todas as movimentacoes em ordem cronologica: tipo, quantidade, saldo, origem/destino, responsavel, data
- **E** permite filtrar por periodo e tipo de movimentacao

#### AC-17.12: Inteligencia de estoque

- **Dado** historico de consumo de um produto nos ultimos 3 meses
- **Quando** o gestor acessa sugestoes de compra
- **Entao** o sistema calcula consumo medio e projeta necessidade para proximo periodo
- **E** sugere quantidade e data ideal de compra baseado em lead time do fornecedor

### RF-18: Billing SaaS

##### AC-18.1: CRUD de planos

- **Dado** um admin do sistema
- **Quando** cria um plano SaaS
- **Entao** define nome, preco, ciclo (mensal/anual), modulos incluidos e limites (usuarios, OS/mes)
- **E** o plano fica disponivel para atribuicao a tenants

#### AC-18.2: Criar assinatura

- **Dado** um tenant sem assinatura ativa
- **Quando** o admin atribui um plano
- **Entao** a assinatura e criada com data de inicio, periodo de trial (se configurado) e ciclo
- **E** o status inicia como 'trial' ou 'active'

#### AC-18.3: Cancelar assinatura

- **Dado** uma assinatura ativa
- **Quando** o admin solicita cancelamento
- **Entao** exige motivo obrigatorio (cancellation_reason)
- **E** registra data de cancelamento
- **E** o acesso permanece ate o fim do periodo pago (current_period_end)

#### AC-18.5: Bloqueio de modulo nao incluido no plano

- **Dado** um tenant com plano que NAO inclui o modulo de Frota (RF-14)
- **Quando** o usuario tenta acessar qualquer rota de Frota
- **Entao** o sistema retorna 403 com mensagem "Modulo nao disponivel no seu plano"
- **E** a resposta inclui informacao do plano que inclui o modulo (upsell)
- **E** o acesso bloqueado e registrado em log para analytics de conversao

#### AC-18.5b: Grace period apos downgrade

- **Dado** um tenant que fez downgrade de plano perdendo acesso a um modulo
- **Quando** o downgrade e efetivado
- **Entao** o tenant mantem acesso read-only ao modulo por 7 dias corridos
- **E** operacoes de escrita retornam 403 com mensagem "Modulo em periodo de transicao"
- **E** apos 7 dias, o acesso e completamente removido
- **E** dados do modulo sao preservados (nao deletados) para caso de upgrade futuro

#### AC-18.5c: Comportamento com assinatura expirada

- **Dado** um tenant cuja assinatura expirou (status = expired)
- **Quando** qualquer usuario do tenant tenta acessar o sistema
- **Entao** o sistema permite acesso read-only a todos os modulos por 15 dias
- **E** operacoes de escrita retornam 403 com mensagem "Assinatura expirada — renove para continuar operando"
- **E** exportacao de dados (CSV/PDF) permanece disponivel durante todo o periodo de graca
- **E** apos 15 dias sem renovacao, acesso e completamente bloqueado exceto login e tela de renovacao

#### AC-18.5d: Tenant em trial

- **Dado** um tenant com assinatura tipo "trial"
- **Quando** o trial esta ativo (dentro do periodo configurado)
- **Entao** o tenant tem acesso a todos os modulos sem restricao
- **E** um banner permanente mostra "Trial — X dias restantes"
- **E** 3 dias antes do fim do trial, emails de alerta sao enviados ao admin do tenant

#### AC-18.4: Renovacao de assinatura

- **Dado** uma assinatura ativa proximo do vencimento
- **Quando** o admin renova com novo periodo
- **Entao** a assinatura e estendida com preco atualizado (se houver reajuste)
- **E** o historico de renovacoes e preservado
- **E** a data de vencimento e atualizada

#### AC-18.6: Cobranca recorrente

- **Dado** uma assinatura ativa com cobranca automatica habilitada
- **Quando** chega a data de cobranca (D-5 do vencimento)
- **Entao** o sistema gera fatura e envia ao gateway (Asaas) para cobranca
- **E** em caso de falha, retenta em D+1, D+3 e D+7
- **E** apos 3 falhas, a assinatura e marcada como "suspended" e o tenant entra em modo read-only

#### AC-18.7: Dashboard de billing

- **Dado** o admin acessando o dashboard de billing
- **Quando** a pagina carrega
- **Entao** exibe: MRR atual, churn rate mensal, assinaturas ativas vs canceladas, receita por plano
- **E** permite filtrar por periodo e exportar para CSV

### RF-11: LGPD (Protecao de Dados)

##### AC-11.1: Base legal LGPD

- **Dado** o sistema coletando dados pessoais
- **Quando** um novo tipo de tratamento e configurado
- **Entao** a base legal (consentimento, obrigacao legal, execucao de contrato) e registrada
- **E** o registro inclui finalidade, tipo de dados e responsavel
- **E** o registro e auditavel e imutavel

#### AC-11.2: Direito de acesso do titular

- **Dado** um titular de dados pessoais
- **Quando** solicita acesso aos seus dados via portal ou formulario
- **Entao** o sistema gera relatorio com todos os dados pessoais armazenados
- **E** o relatorio e entregue em formato estruturado (JSON/CSV) em ate 15 dias
- **E** a solicitacao e registrada com data, tipo e resolucao

#### AC-11.3: Direito de eliminacao

- **Dado** um titular de dados pessoais solicitando eliminacao
- **Quando** a solicitacao e processada
- **Entao** dados pessoais nao obrigatorios sao eliminados ou anonimizados
- **E** dados com retencao legal obrigatoria (certificados, ponto, fiscal) sao mantidos com justificativa
- **E** a solicitacao e registrada com data, tipo, dados afetados e resolucao

#### AC-11.4: Portabilidade de dados

- **Dado** um titular de dados que solicita portabilidade
- **Quando** o DPO processa a solicitacao
- **Entao** o sistema gera arquivo estruturado (JSON) com todos os dados pessoais do titular
- **E** o arquivo e disponibilizado para download seguro com link temporario (24h)
- **E** o formato segue o padrao legivel por maquina conforme LGPD art. 18, V

#### AC-11.5: Log de consentimento

- **Dado** um usuario fornecendo consentimento para tratamento de dados
- **Quando** o consentimento e registrado
- **Entao** o log inclui: data/hora, finalidade, base legal, IP, user-agent
- **E** o consentimento pode ser revogado pelo titular a qualquer momento
- **E** a revogacao nao afeta tratamentos anteriores com base legal valida

#### AC-11.6: Anonimizacao de dados

- **Dado** dados pessoais que atingiram o periodo de retencao configurado
- **Quando** o job de anonimizacao roda (diario)
- **Entao** dados pessoais identificaveis sao substituidos por hash irreversivel
- **E** dados agregados/estatisticos sao preservados para analytics
- **E** log de anonimizacao registra: quantidade de registros, data, motivo

#### AC-09.3: Webhooks outbound

- **Dado** um evento de pagamento confirmado ou mudanca de status de OS
- **Quando** o evento ocorre no sistema
- **Entao** o webhook e disparado para URLs configuradas no WebhookConfig
- **E** a tentativa e registrada no WebhookLog com status, response e retry count
- **E** em caso de falha, retry automatico com backoff exponencial

### RF-12: Comissoes e Dashboards Operacionais

##### AC-12.1: Calcular comissoes por tecnico

- **Dado** OS concluidas no periodo selecionado
- **Quando** o gestor acessa o dashboard de comissoes
- **Entao** exibe valor de comissao por tecnico baseado em regras configuradas (% sobre OS ou valor fixo)
- **E** permite filtrar por periodo, tecnico e tipo de OS

#### AC-12.2: Dashboard de comissoes

- **Dado** comissoes calculadas no mes
- **Quando** o gestor acessa o painel
- **Entao** exibe ranking de tecnicos, totais por periodo e comparativo com meses anteriores
- **E** permite exportar para CSV/PDF

#### AC-12.3: Gestao de ativos fixos

- **Dado** um ativo fixo cadastrado (equipamento, veiculo, mobiliario)
- **Quando** o sistema calcula depreciacao
- **Entao** aplica metodo linear conforme vida util configurada
- **E** o valor residual e atualizado mensalmente

#### AC-12.4: TV Dashboard em tempo real

- **Dado** um display configurado para TV Dashboard
- **Quando** conectado a URL do dashboard
- **Entao** exibe OS ativas, tecnicos em campo, alertas e metricas em tempo real
- **E** atualiza automaticamente sem refresh manual (polling ou websocket)

#### AC-12.5: Configurar TV Dashboard por tenant

- **Dado** um admin de tenant
- **Quando** configura o layout do TV Dashboard
- **Entao** pode selecionar widgets, ordem e tamanho
- **E** a configuracao e persistida por tenant

#### AC-12.6: Dashboard de SLA

- **Dado** OS concluidas com prazo de SLA definido
- **Quando** o gestor acessa o dashboard de SLA
- **Entao** exibe % de cumprimento por cliente, tipo de servico e periodo
- **E** destaca OS que violaram o SLA com detalhes

#### AC-12.7: Dashboard de observabilidade

- **Dado** o sistema em operacao
- **Quando** o admin acessa o painel de observabilidade
- **Entao** exibe: health check de servicos, taxa de erros, latencia p95, uso de disco/memoria
- **E** alertas para servicos degradados sao destacados

#### AC-12.8: Portal do fornecedor

- **Dado** um fornecedor com credenciais de acesso
- **Quando** acessa o portal
- **Entao** ve pagamentos recebidos e pendentes do seu CNPJ
- **E** so acessa dados do proprio fornecedor (isolamento por supplier_id)

### RF-14 (cont.): Gestao de Frota

#### AC-14.2: Checkin de veiculos

- **Dado** um tecnico com veiculo atribuido
- **Quando** faz checkin diario
- **Entao** registra quilometragem, nivel de combustivel e condicao visual
- **E** anomalias (quilometragem excessiva, dano reportado) geram alerta ao gestor

### RF-15: Assistente IA

#### AC-15.1: Assistente IA

- **Dado** um usuario autenticado com dados do tenant carregados
- **Quando** faz pergunta em linguagem natural (ex: "quantas OS abertas hoje?")
- **Entao** o assistente responde com dados reais do tenant em < 5 segundos
- **E** a resposta inclui fonte dos dados (tabela/modulo consultado)
- **E** o escopo e limitado ao tenant autenticado (zero cross-tenant)
- **E** o usuario pode dar feedback (thumbs up/down) para medicao de relevancia

#### AC-15.2: Deteccao de anomalias

- **Dado** metricas operacionais e financeiras acumuladas por 30+ dias
- **Quando** o sistema detecta desvio > 2 sigma da media historica
- **Entao** gera alerta ao gestor com: metrica, valor atual, media historica, desvio
- **E** sugere possivel causa baseada em padroes conhecidos

### RF-16: Projetos e Indicacoes

#### AC-16.1: Gestao de projetos com milestones

- **Dado** um projeto criado com milestones definidos
- **Quando** OS vinculadas ao projeto sao concluidas
- **Entao** o progresso do milestone e atualizado automaticamente (% conclusao)
- **E** ao atingir 100%, o milestone e marcado como concluido
- **E** o gestor ve timeline visual do projeto com milestones e status

#### AC-16.2: Programa de indicacoes

- **Dado** um cliente cadastrado no programa de referrals
- **Quando** indica outro cliente que se torna ativo (primeira OS paga)
- **Entao** o indicador recebe credito configuravel (desconto ou cashback)
- **E** o historico de indicacoes e rastreado com: indicador, indicado, data, status, recompensa

### RF-11 (cont.): LGPD Expandida

##### AC-11.7: Configurar DPO por tenant

- **Dado** um admin de tenant
- **Quando** configura o DPO (Encarregado de Dados)
- **Entao** o nome e email do DPO ficam visiveis no Portal do Cliente
- **E** solicitacoes de titulares sao encaminhadas automaticamente ao email do DPO

#### AC-11.8: Registrar e notificar incidentes de seguranca

- **Dado** um incidente de seguranca detectado ou reportado
- **Quando** o admin registra o incidente
- **Entao** o sistema registra: data, descricao, dados afetados, titulares impactados, medidas tomadas
- **E** gera relatorio formatado para notificacao a ANPD
- **E** notifica titulares afetados via email quando aplicavel

#### AC-11.9: Gerar RIPD (Relatorio de Impacto)

- **Dado** solicitacao da ANPD ou necessidade interna
- **Quando** o admin solicita geracao do RIPD
- **Entao** o sistema gera relatorio com: categorias de dados tratados, bases legais, compartilhamentos com terceiros, medidas de seguranca, riscos identificados
- **E** o relatorio e exportavel em PDF

### RF-13: Notificacoes

#### AC-13.1: Notificar tecnico na atribuicao de OS

- **Dado** uma OS atribuida a um tecnico
- **Quando** a atribuicao e confirmada
- **Entao** o tecnico recebe notificacao push e/ou in-app em < 1 minuto
- **E** a notificacao contem: numero da OS, cliente, endereco, horario previsto

#### AC-13.4: Email com certificado ao cliente

- **Dado** um certificado de calibracao aprovado
- **Quando** o sistema dispara envio
- **Entao** o email e enviado ao contato principal do cliente com PDF anexo
- **E** o status de envio (enviado/falha/bounce) e registrado
- **E** o certificado tambem fica disponivel no Portal do Cliente

#### AC-13.6: Alerta de peso padrao vencendo

- **Dado** um peso padrao com data de validade configurada
- **Quando** faltam 30 dias para o vencimento
- **Entao** o gestor do laboratorio recebe notificacao automatica
- **E** a notificacao inclui: identificacao do peso, data de vencimento, calibracoes que dependem dele

#### AC-13.2: Notificacao de status critico de OS

- **Dado** uma OS que muda para status "completed" ou "cancelled"
- **Quando** a transicao de status ocorre
- **Entao** o gestor responsavel recebe notificacao in-app e email
- **E** a notificacao inclui: numero da OS, cliente, tecnico, motivo (se cancelada)

#### AC-13.3: Notificacao de pagamento confirmado

- **Dado** um pagamento confirmado via webhook do gateway (Asaas)
- **Quando** o webhook e processado com sucesso
- **Entao** o usuario com perfil financeiro recebe notificacao in-app
- **E** a notificacao inclui: valor, cliente, metodo de pagamento, numero da fatura

#### AC-13.5: Envio de cobranca ao cliente

- **Dado** um boleto ou QR code PIX gerado para o cliente
- **Quando** a cobranca e criada no gateway
- **Entao** o cliente recebe email com: valor, vencimento, link do boleto e QR code PIX
- **E** o envio e registrado com status (enviado, entregue, bounced)

#### AC-13.7: Notificar DPO em solicitacao LGPD

- **Dado** um titular registrando solicitacao LGPD (acesso, eliminacao, portabilidade)
- **Quando** a solicitacao e criada no sistema
- **Entao** o DPO configurado no tenant recebe email automatico com detalhes da solicitacao
- **E** a solicitacao inclui prazo legal de 15 dias uteis para resposta

#### AC-13.8: Configuracao de canais por tenant

- **Dado** um admin do tenant na tela de configuracao de notificacoes
- **Quando** habilita/desabilita canais (email, push, in-app) por tipo de notificacao
- **Entao** as preferencias sao salvas e aplicadas imediatamente
- **E** pelo menos um canal deve permanecer ativo por tipo de notificacao (nao permite desativar todos)

#### Canais de Notificacao por Tipo de Evento

| Tipo de Evento | Email | Push (PWA) | In-App | WhatsApp | Canal Default |
|---------------|:-----:|:----------:|:------:|:--------:|---------------|
| OS atribuida ao tecnico (RF-13.1) | opcional | obrigatorio | obrigatorio | opcional | Push + In-App |
| OS concluida/cancelada — gestor (RF-13.2) | obrigatorio | opcional | obrigatorio | — | Email + In-App |
| Pagamento confirmado (RF-13.3) | opcional | — | obrigatorio | — | In-App |
| Certificado disponivel — cliente (RF-13.4) | obrigatorio | — | — | opcional | Email |
| Boleto/PIX gerado — cliente (RF-13.5) | obrigatorio | — | — | opcional | Email |
| Peso padrao vencendo (RF-13.6) | obrigatorio | opcional | obrigatorio | — | Email + In-App |
| Solicitacao LGPD — DPO (RF-13.7) | obrigatorio | — | obrigatorio | — | Email + In-App |
| Alerta de sistema (critico) | obrigatorio | — | obrigatorio | — | Email + In-App |

> **Regras:** (1) Canal "obrigatorio" nao pode ser desativado pelo tenant. (2) Canal "opcional" pode ser habilitado/desabilitado via RF-13.8. (3) WhatsApp requer integracao ativa configurada no tenant. (4) Push requer service worker registrado no navegador do usuario. (5) Toda notificacao e registrada em `notification_logs` independente do canal.

### RF-19: Ciclo de Receita End-to-End

#### AC-19.1: Faturamento automatico de OS concluida

- **Dado** uma OS com status "completed" e valor total > 0
- **Quando** a transicao de status ocorre
- **Entao** o sistema gera automaticamente um AccountReceivable com parcelas conforme regra do tenant
- **E** o status do ciclo muda para INVOICED
- **E** o financeiro recebe notificacao

#### AC-19.2: OS de cortesia/garantia (valor zero)

- **Dado** uma OS com status "completed" e valor total = 0
- **Quando** a transicao de status ocorre
- **Entao** o sistema marca a OS como "invoiced_exempt" e NAO gera AR, NFS-e ou cobranca
- **E** o registro de isencao inclui motivo (garantia, cortesia, contrato)

#### AC-19.5: Webhook de baixa automatica de pagamento

- **Dado** um boleto/PIX gerado via gateway Asaas vinculado a uma parcela de AR
- **Quando** o gateway envia webhook de confirmacao de pagamento (POST /api/v1/webhooks/payment)
- **Entao** a parcela e marcada como "paid" com data de pagamento e valor recebido
- **E** o status do ciclo muda para PAID
- **E** o financeiro recebe notificacao (RF-13.3)
- **E** o webhook e validado via assinatura HMAC do gateway

#### AC-19.6: Pagamento parcial via webhook

- **Dado** um pagamento recebido com valor inferior ao valor da parcela
- **Quando** o webhook e processado
- **Entao** a parcela e marcada como "partial" com saldo devedor calculado
- **E** o financeiro recebe alerta de pagamento parcial

#### AC-19.7: Pagamento duplicado

- **Dado** dois webhooks com mesma idempotency key do gateway
- **Quando** o segundo webhook chega
- **Entao** o sistema rejeita silenciosamente (200 OK sem processar) e registra log
- **E** NAO faz baixa duplicada

#### AC-19.10: Maquina de estados do ciclo

- **Dado** uma OS que percorre o ciclo completo
- **Quando** cada etapa e concluida
- **Entao** o status transiciona: OS_COMPLETED → INVOICED → NFSE_ISSUED → PAYMENT_GENERATED → PAID → RECONCILED
- **E** cada transicao e registrada com timestamp e ator (sistema ou usuario)
- **E** transicoes invalidas (ex: PAID sem passar por NFSE_ISSUED) sao bloqueadas

### RF-01 (cont.): Dashboard e Garantias

#### AC-01.11: Dashboard operacional unificado

- **Dado** um gestor logado no sistema
- **Quando** acessa o dashboard principal
- **Entao** ve em UMA UNICA TELA: OS do dia (abertas, em andamento, concluidas), tecnicos em campo com GPS, pagamentos vencendo hoje, certificados pendentes de revisao, alertas criticos (estoque minimo, equipamento vencendo)
- **E** o dashboard carrega em < 3 segundos
- **E** os dados atualizam automaticamente a cada 60 segundos (polling ou WebSocket)
- **E** cada widget e clicavel e leva ao detalhe do item

#### AC-01.13: OS de garantia

- **Dado** uma OS concluida com servico que tem periodo de garantia configurado
- **Quando** o cliente solicita retorno dentro do periodo de garantia
- **Entao** o sistema cria OS de garantia vinculada a OS original
- **E** o valor da OS de garantia e R$ 0,00 (sem cobranca ao cliente)
- **E** o custo real (deslocamento, material) e registrado para analise de qualidade

### RF-06 (cont.): Portal do Cliente Expandido

#### AC-06.6: Aprovacao de orcamento no portal

- **Dado** um orcamento (Quote) enviado ao cliente com link de acesso
- **Quando** o cliente acessa o portal e visualiza o orcamento
- **Entao** pode aprovar (com assinatura digital) ou rejeitar (com motivo obrigatorio)
- **E** a aprovacao dispara criacao de OS automaticamente (RF-21.2)
- **E** a rejeicao notifica o vendedor responsavel

#### AC-06.7: Pagamento via portal

- **Dado** uma fatura com boleto/PIX gerado
- **Quando** o cliente acessa a area financeira do portal
- **Entao** ve a fatura com: valor, vencimento, status, link do boleto PDF e QR code PIX
- **E** pode copiar a linha digitavel do boleto ou o codigo PIX copia-e-cola

#### AC-06.10: Notificacoes ao cliente

- **Dado** um cliente com email cadastrado e eventos habilitados
- **Quando** OS muda de status (criada, em andamento, concluida) ou certificado fica pronto
- **Entao** email e enviado automaticamente com: resumo do evento, link para o portal, dados relevantes
- **E** o cliente pode acessar o portal diretamente pelo link do email

#### AC-06.11: Equipamentos e recalibracao

- **Dado** um cliente logado no portal
- **Quando** acessa a secao "Meus Equipamentos"
- **Entao** ve lista de equipamentos com: nome, numero de serie, ultima calibracao, proxima prevista, status (conforme/vencido)
- **E** pode clicar em cada equipamento para ver historico completo de calibracoes
- **E** equipamentos com calibracao vencendo aparecem destacados

### RF-22: Equipamentos do Cliente

#### AC-22.1: Cadastro de equipamento do cliente

- **Dado** um usuario com permissao de cadastro de equipamentos
- **Quando** registra equipamento com numero de serie, modelo, fabricante e cliente proprietario
- **Entao** o equipamento e vinculado ao cliente e aparece na visao 360 e no Portal
- **E** QR code e gerado automaticamente para identificacao rapida

#### AC-22.3: Alerta de recalibracao

- **Dado** um equipamento com ultima calibracao ha 11 meses e frequencia anual
- **Quando** faltam 30 dias para vencimento
- **Entao** o gestor recebe notificacao automatica
- **E** o cliente recebe email (se habilitado) com link para solicitar recalibracao no portal
- **E** o equipamento aparece na lista "vencendo em breve" no dashboard

### RF-23: Contratos Recorrentes

#### AC-23.1: Criar contrato recorrente

- **Dado** um usuario com permissao de criacao de contratos
- **Quando** cria contrato com: cliente, servicos, frequencia (mensal/trimestral/semestral/anual), valor, vigencia
- **Entao** o contrato e registrado com status "active" e proxima execucao calculada
- **E** os servicos e valores sao pre-definidos para as OS que serao geradas

#### AC-23.2: Geracao automatica de OS

- **Dado** um contrato ativo com frequencia trimestral e proxima execucao = hoje
- **Quando** o job diario de contratos executa
- **Entao** uma OS e criada com itens, valores e cliente do contrato
- **E** o tecnico padrao (se configurado) e atribuido automaticamente
- **E** a proxima execucao e recalculada (+3 meses)
- **E** o gestor recebe notificacao da OS gerada

#### AC-23.4: Alerta de vencimento de contrato

- **Dado** um contrato com vigencia encerrando em 30 dias
- **Quando** o job diario de alertas executa
- **Entao** o gestor recebe notificacao com: cliente, contrato, data de vencimento, valor
- **E** o cliente recebe email (se habilitado) com opcao de solicitar renovacao

#### AC-23.6: SLA por contrato

- **Dado** um contrato com SLA configurado (resposta: 4h, resolucao: 24h)
- **Quando** OS vinculada ao contrato e criada
- **Entao** o SLA do contrato e aplicado automaticamente a OS
- **E** dashboard de SLA (RF-12.6) exibe metricas por contrato
- **E** violacoes de SLA geram alerta ao gestor

### RF-24: Garantia de Servico

#### AC-24.1: Configurar garantia por tipo de servico

- **Dado** um admin configurando tipos de servico
- **Quando** define periodo de garantia (ex: 90 dias para calibracao, 180 dias para manutencao)
- **Entao** toda OS concluida desse tipo tem garantia ate data calculada
- **E** a garantia aparece no PDF da OS e no Portal do Cliente

#### AC-24.2: Criar OS de garantia

- **Dado** uma OS concluida ha menos de 90 dias (dentro da garantia)
- **Quando** o cliente solicita retorno por problema relacionado
- **Entao** o sistema cria OS de garantia com: referencia a OS original, custo zero ao cliente, motivo do retorno
- **E** o custo real e contabilizado separadamente para analise

#### AC-24.3: Garantia expirada

- **Dado** uma OS concluida ha mais de 90 dias (fora da garantia)
- **Quando** o gestor tenta criar OS de garantia
- **Entao** o sistema exibe alerta: "Garantia expirada em DD/MM/YYYY. Deseja criar OS com cobranca normal?"
- **E** permite criar OS normal com cobranca, vinculada a OS original como referencia

### RF-08 (cont.): Onboarding de Tenant

#### AC-08.7: Wizard de onboarding

- **Dado** um novo tenant recem-criado
- **Quando** o admin do tenant faz primeiro login
- **Entao** o wizard guia em etapas: (1) Dados da empresa (CNPJ, razao social), (2) Logo e identidade visual, (3) Configuracao fiscal (inscricao municipal, codigo servico), (4) Conta bancaria, (5) Usuarios e permissoes, (6) Importacao de dados (opcional)
- **E** cada etapa pode ser pulada e retomada depois
- **E** checklist de progresso e visivel no dashboard ate 100% concluido

#### AC-08.9: Trial experience

- **Dado** um tenant com assinatura tipo "trial"
- **Quando** o trial esta ativo (dentro do periodo configurado)
- **Entao** o tenant tem acesso a todos os modulos sem restricao
- **E** um banner permanente mostra "Trial — X dias restantes"
- **E** 3 dias antes do fim do trial, emails de alerta sao enviados ao admin do tenant
- **E** apos expiracao, acesso e restrito ao plano contratado (ou bloqueado se nao houver plano)

### RF-19: Ciclo de Receita End-to-End

#### AC-19.1: Faturamento automatico de OS concluida

- **Dado** uma OS com status "completed" e valor total > 0
- **Quando** o status transiciona para "completed"
- **Entao** o sistema cria automaticamente um AccountReceivable com os dados da OS
- **E** o status do ciclo de receita muda para INVOICED
- **E** o proximo passo (NFS-e) e enfileirado automaticamente

#### AC-19.2: OS de garantia sem cobranca

- **Dado** uma OS com status "completed" e valor total = 0 (garantia)
- **Quando** o status transiciona para "completed"
- **Entao** o sistema marca a OS como "invoiced_exempt"
- **E** nao gera AccountReceivable, NFS-e nem cobranca
- **E** registra o motivo da isencao no historico

#### AC-19.3: Emissao NFS-e com fallback

- **Dado** uma OS faturada (status INVOICED)
- **Quando** o sistema tenta emitir NFS-e
- **Entao** tenta FocusNFe primeiro, se falha tenta NuvemFiscal, se falha entra em contingencia offline
- **E** registra qual provider emitiu a nota
- **E** atualiza status do ciclo para NFSE_ISSUED

#### AC-19.4: Geracao de cobranca automatica

- **Dado** NFS-e emitida com sucesso (status NFSE_ISSUED)
- **Quando** o sistema processa a proxima etapa
- **Entao** gera boleto bancario E QR code PIX via Asaas
- **E** envia email ao cliente com links de pagamento
- **E** atualiza status do ciclo para PAYMENT_GENERATED

#### AC-19.5: Conciliacao automatica de pagamento

- **Dado** cobranca gerada (status PAYMENT_GENERATED)
- **Quando** webhook do Asaas confirma pagamento
- **Entao** o AccountReceivable e marcado como "paid"
- **E** o status do ciclo muda para PAID
- **E** a conciliacao bancaria registra o match automaticamente

#### AC-19.6: Alerta de NFS-e pendente

- **Dado** uma OS faturada ha mais de 48 horas sem NFS-e emitida
- **Quando** o job de verificacao roda (a cada 1h)
- **Entao** envia notificacao ao usuario com perfil financeiro
- **E** a OS aparece em destaque no dashboard financeiro

#### AC-19.7: Cancelamento em cascata

- **Dado** uma NFS-e vinculada a uma cobranca ativa
- **Quando** a NFS-e e cancelada (RF-03.11)
- **Entao** o boleto/PIX vinculado e cancelado no gateway
- **E** o AccountReceivable retorna para status "pending"
- **E** o ciclo de receita retorna para status INVOICED

#### AC-19.8: Maquina de estados do ciclo

- **Dado** uma OS em qualquer estado do ciclo de receita
- **Quando** o usuario ou sistema tenta transicionar para outro estado
- **Entao** apenas transicoes validas sao permitidas: COMPLETED→INVOICED, INVOICED→NFSE_ISSUED, NFSE_ISSUED→PAYMENT_GENERATED, PAYMENT_GENERATED→PAID, PAID→RECONCILED
- **E** transicoes retroativas sao permitidas apenas para: cancelamento (qualquer→INVOICED) e reprocessamento (NFSE_ISSUED→INVOICED)
- **E** cada transicao registra timestamp, usuario e motivo

### RF-20: Funcionalidades Transversais

#### AC-20.1: Sincronizacao Google Calendar

- **Dado** um tecnico com agenda Google Calendar conectada
- **Quando** uma OS e atribuida a ele com data/hora agendada
- **Entao** o evento e criado automaticamente no Google Calendar do tecnico
- **E** alteracoes de data/hora na OS atualizam o evento no Calendar
- **E** a desconexao do Calendar nao impede o funcionamento das OS

#### AC-20.2: Auditoria de qualidade

- **Dado** uma auditoria de qualidade ISO 17025 agendada
- **Quando** o auditor preenche o checklist com findings
- **Entao** cada finding e classificado por severidade (critico, maior, menor, observacao)
- **E** findings criticos geram acao corretiva obrigatoria com prazo

#### AC-20.4: Rating de OS pelo cliente

- **Dado** uma OS concluida e entregue ao cliente
- **Quando** o cliente acessa o link de avaliacao (enviado por email)
- **Entao** pode avaliar de 1 a 5 estrelas com comentario opcional
- **E** a avaliacao e vinculada a OS e ao tecnico responsavel

#### AC-20.3: Planejamento de rotas

- **Dado** multiplas OS agendadas para o mesmo dia em diferentes enderecos
- **Quando** o gestor acessa o planejador de rotas
- **Entao** o sistema sugere ordem de visitas otimizada por proximidade geografica
- **E** exibe tempo estimado de deslocamento entre cada ponto

#### AC-20.5: Tabela de precos

- **Dado** tabelas de preco configuradas por tier de volume
- **Quando** uma OS e criada para um cliente
- **Entao** os precos sao aplicados automaticamente baseado no tier do cliente
- **E** o gestor pode sobrescrever o preco com justificativa

#### AC-20.6: Centros de custo

- **Dado** centros de custo configurados (ex: operacional, administrativo, frota)
- **Quando** uma despesa ou receita e registrada
- **Entao** o usuario seleciona o centro de custo obrigatoriamente
- **E** relatorios financeiros podem ser filtrados por centro de custo

#### AC-20.7: Regras de cobranca

- **Dado** regras de cobranca configuradas (juros, multa, prazo de tolerancia)
- **Quando** uma fatura vence sem pagamento
- **Entao** o sistema aplica juros e multa conforme configurado
- **E** dispara sequencia de dunning (emails de cobranca) conforme regras

#### AC-20.8: Follow-ups de clientes

- **Dado** um follow-up criado com prazo e responsavel
- **Quando** o prazo se aproxima (24h antes)
- **Entao** o responsavel recebe notificacao de lembrete
- **E** follow-ups vencidos aparecem em destaque no dashboard do gestor

#### AC-20.9: Documentos de clientes

- **Dado** documentos de um cliente (contratos, laudos, certidoes)
- **Quando** o usuario faz upload com categoria e descricao
- **Entao** o documento e armazenado com versionamento automatico
- **E** versoes anteriores ficam acessiveis no historico
- **E** a visao 360 do cliente exibe todos os documentos vinculados

### RF-21: Pipeline Comercial Integrado

#### AC-21.1: Conversao Deal→Quote

- **Dado** um Deal com status "won" no pipeline CRM
- **Quando** o gestor clica em "Gerar Orcamento"
- **Entao** o sistema cria Quote pre-preenchida com: cliente, contato, itens sugeridos (Price Table do cliente)
- **E** o gestor pode editar antes de enviar ao cliente

#### AC-21.2: Aprovacao Quote→OS

- **Dado** uma Quote aprovada pelo cliente (via portal ou assinatura)
- **Quando** a aprovacao e registrada
- **Entao** o sistema cria OS automaticamente com: cliente, itens, valores, tecnico sugerido
- **E** a OS aparece no dashboard do gestor para atribuicao
- **E** a Quote e marcada como "converted" com link para a OS

#### AC-21.3: Dashboard de conversao

- **Dado** dados acumulados do pipeline comercial
- **Quando** o gestor acessa o dashboard de conversao
- **Entao** exibe funil: Deals criados → Quotes enviadas → Quotes aprovadas → OS criadas → OS pagas
- **E** mostra taxa de conversao entre cada etapa e valor medio por etapa

## Premissas e Restricoes

### Premissas

1. O primeiro cliente tera entre 5-30 usuarios (empresa de calibracao de medio porte)
2. O cliente ja possui certificado digital A1 para emissao de NF-e (ou contratara)
3. O cliente tera conexao de internet no escritorio (campo pode ser intermitente)
4. Tecnicos usam smartphone Android ou iOS com camera (para selfie/liveness)
5. O cliente aceita modelo SaaS com dados em nuvem (nao exige instalacao local)
6. Pesos padrao do laboratorio ja possuem certificados de calibracao validos
7. LGPD (RF-11) sera implementada ANTES do go-live com primeiro cliente — e bloqueador legal
8. Certificado digital A1 (e-CNPJ) do cliente estara disponivel e valido para emissao NFS-e e eSocial

### Restricoes

1. **Orcamento**: Desenvolvimento feito por 1 pessoa (Rolda) com assistencia de IA
2. **Prazo**: MVP precisa estar operacional para piloto em 2-3 meses
3. **Infraestrutura**: Servidor unico Hetzner (escala vertical antes de horizontal)
4. **Regulatorio**: Qualquer mudanca na Portaria 671 ou normas INMETRO exige atualizacao imediata
5. **Integracao fiscal**: Depende de contrato ativo com FocusNFe (custo mensal)
6. **Integracao cobranca**: Depende de contrato ativo com Asaas (custo por transacao)

## Dependencias Externas

| Dependencia | Tipo | SLA Esperado | RTO Aceitavel | Fallback | Alerting | Impacto se Indisponivel |
|-------------|------|-------------|---------------|----------|----------|------------------------|
| FocusNFe | Emissao fiscal | 99.5% | 4h | NuvemFiscal (automatico via ResilientFiscalProvider) | Circuit breaker + alerta quando fallback ativado | NFS-e nao emite pelo primario |
| Asaas | Pagamento | 99.9% | 24h | Cobranca manual via email | Health check a cada 5min + alerta | Boletos e PIX nao sao gerados |
| Let's Encrypt | SSL | 99.99% | 30 dias | Certbot auto-renova 60 dias antes | Alerta 7 dias antes de expirar | HTTPS nao renova |
| SEFAZ | Receita Federal | Variavel | 8h | Fila de contingencia com retry | Monitoramento de status + retry | NF-e nao autoriza |
| eSocial | Governo Federal | Variavel | 72h (prazo legal) | Fila com retry exponencial | Alerta apos 3 falhas + dashboard pendencias | Eventos nao transmitem |
| Google Maps API | Geolocalizacao | 99.9% | Indefinido | GPS nativo sem mapa visual | Alerta quando quota > 80% | Geofence degradado |
| SMTP (email) | Envio de emails | 99.9% | 4h | Portal do cliente como alternativa | Bounce rate + alerta fila > 100 | Certificados/boletos nao enviados |
| Certificado Digital A1 | NFS-e e eSocial | N/A | N/A | Nenhum — cada tenant precisa do seu A1 | Alerta 30 dias antes de expirar | NFS-e e eSocial bloqueados |
| Servidor Hetzner | Hospedagem | 99.9% | < 2h | Backup diario + deploy documentado | UptimeRobot + alerta email/SMS | Sistema inteiro fora do ar |

> **SLA e RTO por integracao:**
>
> | Servico | SLA Estimado | RTO sem ele | Fallback |
> |---------|-------------|-------------|----------|
> | FocusNFe | 99.5% | 0 (fallback NuvemFiscal) | Automatico via ResilientFiscalProvider |
> | NuvemFiscal | 99.5% | 24h (contingencia offline) | ContingencyService |
> | Asaas | 99.9% | 48h (cobranca manual) | Sem fallback automatico |
> | Google Calendar | 99.9% | Infinito (nao-critico) | Agenda local |
> | SMTP (email) | 99.9% | 4h (notificacoes atrasam) | Fila de retry |

## Requisitos de Dados

### Retencao

| Tipo de Dado | Retencao Minima | Base Legal |
|-------------|----------------|-----------|
| Certificados de calibracao | 5 anos | ISO 17025:2017, clausula 7.5 |
| Registros de ponto | 5 anos | CLT art. 11 + Portaria 671 |
| Notas fiscais | 5 anos | Codigo Tributario Nacional art. 173 |
| Dados de OS | 3 anos | Codigo Civil art. 206 (garantia) |
| Audit logs | 5 anos | Compliance geral |
| Dados pessoais (LGPD) | Enquanto necessario + eliminacao sob pedido | LGPD art. 16 |

### Backup e Disaster Recovery

| Metrica | Meta | Metodo de Verificacao |
|---------|------|----------------------|
| Frequencia | Backup automatico diario | Cron job verificavel via logs |
| RPO | < 1 hora (binlog MySQL) | Point-in-time recovery testado mensalmente |
| Retencao de backup | 30 dias | Rotacao automatica de backups antigos |
| Teste de restore | Mensal | Restore em ambiente staging + verificacao de integridade |
| RTO (Disaster Recovery) | < 4h | Playbook documentado: 1) Restore DB do backup 2) Deploy app via deploy.sh 3) Verificar integridade (health check + smoke tests) 4) DNS switch |
| Comunicacao de incidente | < 1h apos deteccao | Template de email para tenants afetados com: descricao, impacto, ETA de resolucao |

## Gaps Conhecidos e Limitacoes Tecnicas

> Itens verificados contra codigo em 2026-04-10 (v3.2). Quatro gaps historicos foram REMOVIDOS nesta versao apos confirmacao de que existiam no codigo — PRD anterior (v3.1 e anteriores) reportava falsos negativos. Ver CHANGELOG v3.2 para lista.

| # | Gap | Severidade | Modulo | Descricao |
|---|-----|-----------|--------|-----------|
| ~~G-01~~ | ~~Deal→Quote desconectado~~ | — | — | **REMOVIDO v3.2** — verificacao 2026-04-10: `ConvertDealToQuoteAction` + rota + frontend + teste existem. Era falso negativo |
| G-02 | FEFO nao configuravel por tenant | BAIXA | Estoque | `StockService::selectBatches()` implementa FIFO e FEFO, mas todos os callers hardcodam `'FIFO'`. Falta coluna `stock_strategy` em products/tenant_settings |
| G-03 | WhatsApp→CRM desconectado | MEDIA | CRM | Webhook recebe mensagens WhatsApp, mas nao alimenta CrmMessage. Dados nao aparecem na timeline do cliente |
| G-04 | CNAB apenas para payroll | MEDIA | Financeiro | Exportacao CNAB existe para folha de pagamento, mas nao para cobranca (AR). Boleto bancario depende 100% de gateway API |
| G-05 | Conciliacao bancaria basica | BAIXA | Financeiro | Auto-match usa heuristica simples (valor ±0.05, data ±5 dias). Sem aprendizado por historico |
| G-06 | eSocial eventos complementares stub | MEDIA | RH | Eventos S-2205, S-2206, S-2210+ retornam XML vazio. Apenas S-1000, S-1010, S-1200, S-2200, S-2299, S-3000 funcionais |
| G-07 | Documentacao interativa API | BAIXA | Infra | Swagger/OpenAPI nao exposto publicamente. Scramble instalado mas endpoint nao publicado |
| G-08 | Dashboard operacional unificado | CRITICA | Operacional | 9 dashboards por modulo existem. **`DashboardPage.tsx` operacional** consumindo `/dashboard-stats`, OS recentes, Tecnicos, NPS, Alertas e RH widgets — verificado 2026-04-10. Falta refinamento final conforme RF-01.11 (criterios de "consolidado" ainda subjetivos) |
| G-09 | Webhook baixa automatica pagamento | CRITICA | Financeiro | `AsaasPaymentProvider` com createPixCharge/createBoletoCharge/checkPaymentStatus **ja escrito**. Falta orquestrar webhook → FSM → baixa automatica (`accounts_receivable`). Formalizado como RF-19.5-19.7. **Bloqueador principal de go-live.** |
| G-10 | Portal do Cliente incompleto | ALTA | Portal | Cliente nao pode pagar, nao recebe notificacao, nao ve equipamentos. Formalizado como RF-06.6-06.12 |
| ~~G-11~~ | ~~Offline incompleto/nao validado~~ | BAIXA | PWA | **PARCIALMENTE REMOVIDO v3.2.** `ConflictResolver.ts` implementa LWW (`local_wins` default), `server_wins`, `manual`. `useSyncEngine.test.ts` valida LWW comparando `updated_at`. Falta apenas E2E de campo (2 sessoes + rede intermitente) — cenario Playwright opcional |
| ~~G-12~~ | ~~Quote→OS desconectado~~ | ALTA | CRM | **Renomeado.** Deal→Quote ja existe (ver acima). Gap restante e apenas Quote→OS automatico — formalizado em RF-21.2 |
| G-13 | Camada AI sem LLM provider | MEDIA | Analytics/AI | `AiAssistantService` tem tool-calling definido (5 tools: predictive_maintenance, expense_analysis, triage, sentiment, dynamic_pricing). **Zero referencias a `anthropic`/`claude` no backend** — falta instanciar provider LLM real. Ver `docs/plans/agente-ceo-ia.md` |
| G-14 | `offlineDb.ts` marcado deprecated mas em uso | BAIXA | PWA | `TECHNICAL-DECISIONS` afirmava deprecated, mas 16 arquivos ainda importam (inclusive o proprio `syncEngine.ts:17`). Decidir: concluir migracao ou remover nota de deprecated |

> **Nota v3.2:** G-09 e bloqueador critico real de go-live (nao o codigo do provider, mas a orquestracao do webhook). G-08 e G-11 passam de "critico/alta" para estado mais realista apos verificacao. G-13 e G-14 sao novos, identificados na re-auditoria de 2026-04-10.

---

## Analise de Riscos

| # | Risco | Probabilidade | Impacto | Mitigacao |
|---|-------|--------------|---------|----------|
| 1 | FocusNFe fora do ar no fechamento | Media | Alto | Fallback automatico para NuvemFiscal + modo contingencia |
| 2 | Erro no calculo de EMA invalida certificado | Baixa | Critico | Validacao com laboratorio RBC antes do go-live |
| 3 | Tecnico perde dados offline | **Alta** | Alto | **Status atual: PWA so tem cache estatico — API NAO cacheada. Tecnico sem sinal perde acesso a dados.** Mitigacao planejada: queue persistente em IndexedDB + retry automatico (nao implementado) |
| 4 | Vazamento cross-tenant | Baixa | Critico | BelongsToTenant em 100% dos models + testes de isolamento |
| 5 | Cliente piloto desiste no mes 1 | Media | Alto | Onboarding acompanhado + suporte dedicado nas 4 primeiras semanas |
| 6 | Mudanca regulatoria (Portaria 671) | Baixa | Medio | Monitorar DOU + arquitetura permite atualizar sem reescrever |
| 7 | Performance degrada com volume | Media | Medio | Load test antes do go-live + cache + eager loading validado |
| 8 | Asaas muda API/precos | Baixa | Medio | Gateway abstrato (PaymentGatewayInterface) permite trocar provedor |
| 9 | Go-live sem LGPD implementada | Baixa | Critico | LGPD base implementada (v1.9: 6 tabelas, DPO, consentimento, incidentes ANPD). **Pendentes pos-MVP:** RIPD e anonimizacao automatica. Risco residual: multa de ate 2% do faturamento (Lei 13.709, art. 52) |
| 10 | Bus factor = 1 (unico desenvolvedor) | Alta | Critico | Documentacao exaustiva (PRD, TECHNICAL-DECISIONS, TESTING_GUIDE, docs/audits/), codigo testado (8720+ testes), CI/CD automatizado, deploy documentado. Plano: segundo desenvolvedor antes de 10 clientes |
| 11 | Certificado digital A1 vencido bloqueia eSocial e NFS-e | Media | Alto | Alerta automatico 30 dias antes do vencimento. Config por tenant com data de validade |
| 12 | Regulamentacao INMETRO muda formulas de calculo | Baixa | Alto | EmaCalculator isolado em service, facil de atualizar. Monitorar publicacoes INMETRO trimestralmente |
| 13 | Webhook de pagamento falha silenciosamente | Media | Critico | Idempotency key obrigatoria, validacao HMAC, log de todos webhooks recebidos, alerta quando webhook esperado nao chega em 24h |
| 14 | Offline gera dados inconsistentes em producao | Alta | Alto | Validacao E2E obrigatoria antes do go-live. Cenarios: campo sem sinal, bateria baixa, 2 tecnicos mesma OS. Testes com throttle de rede |
| 15 | Escopo excessivo atrasa go-live | Alta | Critico | Priorizar brutalmente: webhook + dashboard + portal > contratos + garantia + frota. Cada semana de atraso = receita perdida |
| 16 | Tabelas INSS/IRRF desatualizadas geram erro na folha | Media | Alto | Se mantiver folha propria: job que verifica tabelas vigentes trimestralmente. Alternativa: exportar para contador (RF-10.7) |
| 17 | **Recurring billing placeholder em producao** | Alta | Critico | SaasPlan/SaasSubscription gera invoice com valor fixo R$1000.00. Se cliente configurar cobranca recorrente, gera valores errados. **Acao:** desabilitar UI de billing ate implementar integracao real com gateway |
| 18 | **eSocial stubs transmitidos como XML real** | Media | Critico | S-2205, S-2206, S-2210+ retornam buildStubXml(). Se transmitidos ao eSocial, serao rejeitados ou pior — aceitos com dados invalidos. **Acao:** bloquear transmissao de eventos stub na UI e API |
| 19 | **WhatsApp webhook descartando mensagens** | Media | Alto | Webhook recebe mensagens mas NAO alimenta CrmMessage. Mensagens de clientes estao sendo perdidas silenciosamente. **Acao:** implementar integracao ou desabilitar webhook |
| 20 | **FIFO/FEFO ausente gera consumo desordenado** | Media | Medio | Lotes com validade rastreados, mas sem logica de consumo por ordem. Risco de perda de material vencido. **Acao:** implementar antes de clientes com estoque regulado |

---

## Glossario de Negocio

| Termo Tecnico | Termo do Cliente | Descricao |
|---------------|-----------------|-----------|
| WorkOrder / OS | Ordem de Servico | Documento que descreve o servico a ser executado |
| ServiceCall | Chamado | Solicitacao do cliente que gera uma OS |
| AccountReceivable | Conta a Receber | Valor que o cliente deve pagar |
| AccountPayable | Conta a Pagar | Valor que a empresa deve pagar |
| Quote | Orcamento | Proposta de preco enviada ao cliente |
| Equipment | Equipamento | Instrumento a ser calibrado/reparado |
| Calibration Certificate | Certificado de Calibracao | Documento com resultado da calibracao |
| EMA | Erro Maximo Admissivel | Limite de erro aceito para o equipamento |
| Tenant | Empresa/Cliente | Cada empresa que usa o Kalibrium |
| TimeClockEntry | Registro de Ponto | Marcacao de entrada/saida do funcionario |
| Batch/Lot | Lote | Grupo de pecas/materiais com mesma origem |
| FIFO | Primeiro a Entrar, Primeiro a Sair | Estrategia de consumo de estoque |
| NSR | Numero Sequencial de Registro | Identificador unico e sequencial de cada marcacao de ponto (Portaria 671) |
| AFDT | Arquivo Fonte de Dados Tratados | Formato de exportacao de ponto exigido pelo MTE |
| ACJEF | Arquivo de Controle de Jornada para Efeitos Fiscais | Formato de exportacao fiscal de jornada exigido pelo MTE |
| SPC | Controle Estatistico de Processo | Metodo de monitoramento de qualidade via cartas de controle |
| OIML | Organizacao Internacional de Metrologia Legal | Entidade que define recomendacoes para instrumentos de medicao (ex: R76 para balancas) |
| RBC | Rede Brasileira de Calibracao | Rede de laboratorios acreditados pelo INMETRO |
| PWA | Progressive Web App | Aplicacao web que funciona como app nativo no celular |
| CAPA | Acao Corretiva e Acao Preventiva | Processo formal de tratamento de nao conformidades (ISO 17025/9001) |
| Circuit Breaker | Disjuntor | Padrao de resiliencia que interrompe chamadas a servico externo apos N falhas consecutivas |
| Fallback | Alternativa | Servico secundario acionado quando o primario falha (ex: NuvemFiscal quando FocusNFe indisponivel) |
| Contingencia | Modo Degradado | Operacao com funcionalidade reduzida quando dependencia externa esta indisponivel |
| Global Scope | Escopo Global | Filtro automatico aplicado a toda query de um model, garantindo isolamento por tenant (BelongsToTenant) |
| Sync Engine | Motor de Sincronizacao | Componente PWA que gerencia fila de operacoes offline e sincroniza ao reconectar |
| MRR | Receita Recorrente Mensal | Metrica SaaS: soma de todas assinaturas ativas multiplicada por preco mensal |
| Churn | Taxa de Cancelamento | Percentual de clientes que cancelam assinatura em dado periodo |
| RTO | Recovery Time Objective | Tempo maximo aceitavel para restaurar servico apos falha |
| RPO | Recovery Point Objective | Quantidade maxima de dados que se aceita perder (ex: 1h = backup horario) |
| Dunning | Cobranca Automatica | Sequencia automatica de tentativas de cobranca apos falha de pagamento (ex: cartao recusado → retry D+1, D+3, D+7 → cancelamento) |
| Hash Chain | Cadeia de Hash | Sequencia de registros onde cada entrada contem hash da anterior, garantindo imutabilidade do log de auditoria (NSR no ponto eletronico, audit trail) |
| Proration | Proporcional / Rateio | Calculo de valor parcial proporcional ao periodo usado (ex: assinatura iniciada no dia 15 paga so metade do mes) |
| Webhook | Chamada de Retorno | Notificacao HTTP disparada automaticamente pelo sistema para URL externa quando um evento ocorre (pagamento confirmado, OS concluida) |
| Idempotency Key | Chave de Idempotencia | Identificador unico que garante que uma operacao seja processada apenas uma vez, mesmo se recebida multiplas vezes (ex: webhook duplicado) |
| HMAC | Hash-based Message Authentication Code | Mecanismo de verificacao de autenticidade e integridade de mensagens. Usado para validar que webhooks vieram do gateway real |
| NPS | Net Promoter Score | Metrica de satisfacao do cliente. Pergunta: "De 0 a 10, quanto recomendaria?" Promotores (9-10) - Detratores (0-6) = NPS |
| SLA | Acordo de Nivel de Servico | Compromisso de tempo de resposta e resolucao entre prestador e cliente. Ex: resposta em 4h, resolucao em 24h |
| IGPM/IPCA | Indices de Reajuste | Indices economicos usados para reajuste anual de contratos. IGPM (FGV), IPCA (IBGE) |
| RecurringContract | Contrato Recorrente | Acordo entre tenant e seu cliente para servicos periodicos (calibracao anual, manutencao trimestral) |
| Trial | Periodo de Avaliacao | Acesso temporario gratuito a todos os modulos do sistema para avaliacao antes da contratacao

---

---

## Changelog

> Historico completo de versoes em [`docs/PRD-KALIBRIUM-CHANGELOG.md`](PRD-KALIBRIUM-CHANGELOG.md).
>
> Versao atual: **v3.2** (2026-04-10) — Re-auditoria com verificacao direta no codigo. Remocao do `docs/raio-x-sistema.md` (gerava falsos negativos). PRD promovido a fonte de verdade funcional, com regra: **codigo vence sempre — grep antes de afirmar gap**.
>
> v3.1 (2026-04-06) — Auditoria cruzada PRD vs Raio-X (entao ativo) vs codigo real.
>
> v3.0 (2026-04-03) — Auditoria profunda: 45+ correcoes, 6 novos RFs (RF-19 a RF-24).

*Stack real: Laravel 13 + React 19 + MySQL 8 (PHP 8.3+). Testes: 8720+ (740 arquivos). Validacao BMAD 12-step: PASS com ressalvas. 24 RFs (RF-01 a RF-24) cobrindo 160+ sub-requisitos. **Bloqueadores reais de go-live (Deep Audit 10/04 confirmou):** (1) webhook Asaas → FSM → baixa automatica (RF-19.5-19.7 — codigo do provider existe, falta orquestracao do listener), (2) Seguranca P0.1 reaberta (`docs/plans/seguranca-remediacao-auditoria.md` v3: CSP unsafe-inline/eval, tokens localStorage vs HttpOnly, FormRequests `authorize=true` sem logica, lookups publicos de tenant, baseline de permissoes), (3) CNAB para AR/AP (so existe para payroll), (4) NFS-e dependente de contrato FocusNFe/NuvemFiscal. **Falsos negativos removidos em v3.2:** Deal→Quote (existe e testado), FIFO/FEFO (implementado, so falta flag por tenant), PWA ConflictResolver LWW (implementado e testado). Roadmap: piloto ate 2026-06-01, escala 5-10 clientes ate 2026-10. Relatorios: docs/audits/RELATORIO-AUDITORIA-SISTEMA.md, docs/auditoria/AUDITORIA-CONSOLIDADA-2026-04-10.md*
