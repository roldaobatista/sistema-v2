# Auditoria Profunda do PRD — Kalibrium ERP

**Data:** 2026-04-02
**Versao PRD Auditada:** v2.2
**Fontes Cruzadas:** PRD-KALIBRIUM.md, raio-x-sistema.md, codigo-fonte real (controllers, models, migrations, routes, frontend)
**Auditoria anterior (referencia):** Score de acuracia PRD vs Codigo ~72%

---

## 1. INCONSISTENCIAS DE STATUS (PRD diz 🟢, realidade e diferente)

### 1.1 Status Inflados — Marcados como 🟢 mas sao 🟡 ou 🔴

| RF | Requisito | Status PRD | Status Real | Evidencia |
|----|-----------|-----------|-------------|-----------|
| RF-07.4 | Offline para consulta OS, leituras, assinatura | 🟡 | 🟡/🔴 | Criacao de OS offline NAO funciona. Sync engine existe mas falta validacao E2E completa. Raio-X confirma: "Falta queue de acoes offline para tecnico em campo sem sinal" |
| RF-07.5 | Sincronizar fila ao reconectar | 🟡 | 🟡 | Sync engine implementado mas sem validacao E2E. Em campo real, tecnico pode perder dados |
| RF-10.1-10.3 | eSocial S-2200, S-2299, S-1200 | 🟡 | 🟡/🔴 | "Estrutura implementada, transmissao real ao governo NAO validada em producao". Controller e service existem, mas NINGUEM testou contra o web service SOAP real do governo. Certificado digital A1 necessario |
| RF-10.4 | eSocial S-2230, S-2206 | 🔴 | 🔴 | Correto — reconhece que falta |
| RF-11.4 | Portabilidade de dados LGPD | 🟡 | 🟡 | Request type existe, mas gerador de relatorio NAO implementado |
| RF-11.6 | Anonimizacao automatica | 🟡 | 🟡 | Model AnonymizationLog existe, job automatico NAO implementado |
| RF-11.9 | RIPD | 🔴 | 🔴 | Correto — reconhece que falta |
| RF-12.8 | Portal do fornecedor | 🟡 | 🟡 | Parcial — sem detalhes do que falta |
| RF-18.5 | Controle de modulos por plano | 🟡 | 🔴 | Schema existe (current_plan_id), mas enforcement via middleware NAO implementado. Tenant pode acessar modulos que nao pagou |
| RF-18.6 | Cobranca recorrente gateway | 🔴 | 🔴 | Correto |
| RF-18.7 | Dashboard billing MRR/churn | 🔴 | 🔴 | Correto |

### 1.2 Dependencias Externas Nao Diferenciadas

O PRD marca como 🟡 itens que dependem de **contrato comercial** (nao de codigo):

| Item | Dependencia Real | Risco |
|------|-----------------|-------|
| NFS-e (RF-03.4) | Contrato FocusNFe + credenciais producao | Codigo 100% pronto, bloqueio e COMERCIAL. PRD deveria ter status separado: "Codigo ✅ / Producao ⏳" |
| Boleto/PIX (RF-03.5-06) | Contrato Asaas + credenciais producao | Mesmo caso. CircuitBreaker implementado, mas sem teste em producao |
| eSocial (RF-10.x) | Certificado digital A1 (e-CNPJ) + ambiente producao | Estrutura pronta, zero validacao real |

**Recomendacao:** Criar coluna "Bloqueio" separada de "Status Tecnico" para distinguir impedimentos comerciais de gaps de codigo.

---

## 2. GAPS FUNCIONAIS — Funcionalidades que DEVERIAM Existir mas NAO Estao no PRD

### 2.1 Fluxo Ponta-a-Ponta: OS → Fatura → Cobranca → Recebimento

O PRD promete que "OS finalizada gera fatura + NFS-e + boleto automaticamente". Mas:

| Etapa do Fluxo | Existe no Codigo? | Existe no PRD? | Gap |
|----------------|-------------------|----------------|-----|
| OS concluida → gerar Invoice | ✅ RF-01.9 | ✅ | OK |
| Invoice → emitir NFS-e automaticamente | 🟡 Codigo pronto | ✅ RF-03.4 | Depende contrato. **Mas nao ha RF para o TRIGGER automatico** — quem dispara? Job? Observer? O PRD nao especifica |
| NFS-e emitida → gerar Boleto/PIX | 🟡 Codigo pronto | ✅ RF-03.5-06 | Mesmo gap: **nao ha RF descrevendo a automacao/trigger** entre NFS-e e cobranca |
| Boleto pago → baixa automatica | ❓ | ❌ NAO TEM RF | **GAP CRITICO**: Nao ha webhook de retorno do gateway para dar baixa automatica. O financeiro teria que marcar manualmente? |
| PIX pago → baixa automatica | ❓ | ❌ NAO TEM RF | Mesmo gap. Webhook de confirmacao de pagamento PIX nao especificado |
| Baixa → conciliacao bancaria | ✅ Controller existe | ✅ RF-03.7 | OK mas fluxo automatizado nao descrito |

**CRITICO:** O fluxo de **baixa automatica de pagamento** (webhook do gateway → marcar parcela como paga) e o coração do "ciclo de receita sem buracos" prometido no Resumo Executivo, mas NAO tem RF proprio.

### 2.2 Funcionalidades Faltantes no PRD

| Funcionalidade | Por que Faz Falta | Impacto |
|----------------|-------------------|---------|
| **Webhook de confirmacao de pagamento** | Sem isso, toda baixa e manual. Contradiz a promessa do produto | CRITICO — inviabiliza proposta de valor |
| **Nota de debito / credito** | Empresa precisa emitir credito quando cancela NFS-e parcialmente | MEDIO — workaround manual possivel |
| **Renegociacao de divida** | Cliente inadimplente precisa renegociar. Nao ha RF para parcelamento de divida | MEDIO — cenario real frequente |
| **Demonstrativo de pagamento ao tecnico** | Comissoes sao calculadas (RF-12.1) mas nao ha RF para o tecnico VER seu demonstrativo | BAIXO — mas gera reclamacao |
| **Recibo de pagamento para cliente** | Quando cliente paga boleto, precisa de recibo. Nao ha RF | BAIXO |
| **Log de alteracoes em OS** | Historico/audit trail de quem mudou o que na OS. O PRD menciona audit trail generico (RNF-02) mas nao para OS especificamente | MEDIO — compliance e disputas |
| **SLA por contrato** | Dashboard de SLA existe (RF-12.6) mas nao ha RF para CONFIGURAR SLA diferente por cliente/contrato | MEDIO |
| **Aprovacao de orcamento pelo cliente (via portal)** | O portal do cliente (RF-06) nao menciona aprovacao de orcamento. O cliente so "acompanha" | ALTO — fluxo comercial incompleto |

### 2.3 Modulos no Codigo que NAO Estao no PRD (ou estao sub-representados)

| Modulo no Codigo | Models/Controllers | Cobertura no PRD |
|------------------|-------------------|------------------|
| **Frota (Fleet)** | Vehicle, VehicleMaintenance, VehicleTire, VehicleInsurance, VehicleAccident, VehiclePoolRequest | RF-14 existe mas com pouco detalhe sobre integracao com OS (vinculo veiculo ↔ tecnico ↔ OS do dia) |
| **Automation** | AutomationPage.tsx no frontend | Mencionado superficialmente. Nao ha RF para regras de automacao configuravel pelo usuario |
| **Analytics/BI Preditivo** | PredictiveAnalytics.tsx, AnalyticsHubPage.tsx | RF-15 existe mas nao define quais previsoes sao uteis para o negocio |
| **Innovation (Theme, Referral, ROI)** | InnovationController com theme-config, referral, ROI, easter-egg | NAO TEM RF. Funcionalidades de gamificacao/referral existem no codigo sem especificacao |
| **Batch Export** | BatchExportPage.tsx | NAO TEM RF. Exportacao em lote de cadastros nao especificada |
| **Customer 360** | Customer360Page.tsx | NAO TEM RF. Visao unificada do cliente (OS + financeiro + calibracoes) |
| **Price History** | PriceHistoryPage.tsx | NAO TEM RF. Historico de precos nao especificado |
| **Customer Merge** | CustomerMergePage.tsx | NAO TEM RF. Deduplicacao de clientes |

---

## 3. FLUXOS INCOMPLETOS / QUE NAO FAZEM SENTIDO PARA O NEGOCIO

### 3.1 Portal do Cliente (RF-06) — Superficial demais

O PRD lista 5 sub-requisitos para o Portal do Cliente:
- RF-06.1: Consultar OS
- RF-06.2: Baixar certificados
- RF-06.3: Ver faturas e status de pagamento
- RF-06.4: Abrir chamado
- RF-06.5: Consultar historico de calibracoes

**O que falta para ser util de verdade:**

| Gap | Por que e Necessario |
|-----|---------------------|
| Aprovar/rejeitar orcamento | Cliente precisa aceitar orcamento antes da execucao. Sem isso, o fluxo comercial quebra |
| Assinar digitalmente documentos | Termos de servico, aceite de orcamento |
| Agendar visita tecnica | Cliente deveria poder solicitar agendamento |
| Avaliar servico (NPS) | Feedback do servico prestado. Oportunidade de melhoria |
| Ver boleto/PIX para pagamento | Se o portal mostra fatura, deveria mostrar o boleto/link PIX para pagar |
| Notificacoes por email/push | Quando OS muda de status, quando certificado fica pronto |

### 3.2 Jornada do Tecnico (J2) — Offline e critico mas subespecificado

O PRD descreve uma jornada linda onde o Joao abre o app, registra calibracao, coleta assinatura. Mas:

| Cenario Real | Cobertura PRD | Gap |
|-------------|---------------|-----|
| Tecnico em area sem sinal celular (subsolo, area rural) | RF-07.4 parcial | Criacao de OS offline NAO funciona. So consulta e leitura |
| Tecnico precisa registrar peca usada | Nao especificado em RF-07 | Como registrar consumo de estoque offline? |
| Tecnico precisa tirar foto do equipamento | RF-01.5 menciona fotos | Offline? Sync de imagens pesadas? Compressao? |
| Bateria do celular acaba no meio do servico | Nao abordado | Graceful save? Auto-save parcial? |
| Dois tecnicos na mesma OS | RF-01.3 menciona multiplos tecnicos | Conflito de edicao simultanea? Last-write-wins? |

### 3.3 Conciliacao Bancaria (RF-03.7) — Sem Origem do Extrato

O PRD diz "extrato bancario importado (OFX ou CSV)". Mas:
- NAO ha RF para importacao OFX. Quem faz o parse? Qual formato?
- NAO ha integracao com Open Banking / APIs bancarias
- O auto-match e descrito nos ACs mas nao ha RF para configurar regras de match
- **Cenario real:** O financeiro precisa ir no banco, baixar o OFX, subir no sistema. Em 2026, isso e arcaico. Deveria ter integracao via API bancaria (Pluggy, Belvo, ou Open Finance)

### 3.4 Contratos Recorrentes — Billing vs Contratos de Servico

Ha confusao entre dois conceitos diferentes:
1. **RF-18 (Billing SaaS):** Planos do proprio Kalibrium para seus clientes (o Kalibrium cobrando o tenant)
2. **RF-13 (Contratos):** Contratos de servico recorrente (o tenant cobrando SEU cliente)

O PRD nao deixa claro a relacao entre eles. Perguntas sem resposta:
- Quando um contrato recorrente gera OS automatica, quem define a frequencia?
- A OS gerada automaticamente ja vem com orcamento?
- O faturamento do contrato e automatico ou precisa de aprovacao?
- Reajuste anual de contrato — como funciona?

### 3.5 Folha de Pagamento — Excessivamente Ambiciosa

O Raio-X mostra Payroll completo (regular, 13o, ferias, bonus, INSS, IRRF, FGTS). Mas:
- O PRD NAO posiciona Folha como diferencial competitivo
- Empresas de 5-100 funcionarios geralmente usam contador externo para folha
- **Risco:** Manter calculo de INSS/IRRF/FGTS atualizado com legislacao e um fardo enorme. Uma tabela desatualizada = processo trabalhista
- **Recomendacao:** Avaliar se Folha completa faz sentido ou se deveria ser integracao com eSocial/contabilidade externa

---

## 4. INCONSISTENCIAS INTERNAS DO PRD

### 4.1 Maturidade Reportada vs Gaps Reais

| Claim do PRD | Realidade |
|-------------|-----------|
| "~89% de maturidade validada" (frontmatter) | Raio-X mostra 70% no Financeiro, 6.5/10 no CRM, 85% em Estoque. Media ponderada e mais proxima de 78-82% |
| "Zero bloqueadores criticos de go-live" | NFS-e e Boleto/PIX dependem de contrato. eSocial nao validado em producao. Offline incompleto. Esses SAO bloqueadores para go-live real |
| "8200+ testes" | Numero esta no PRD e no Raio-X, mas testes E2E para fluxos criticos (OS→Fatura→NFS-e→Boleto) NAO existem |
| "93% maturidade" (rodape) | Contraditorio com 89% do frontmatter |

### 4.2 Personas vs Funcionalidades

| Persona | Promessa | Gap |
|---------|---------|-----|
| **Tecnico (Joao)** | "Max 3 toques: abrir OS → registrar → coletar assinatura. Funciona offline" | Offline incompleto. 3 toques e aspiracional — nao ha RF que garanta essa UX |
| **Financeiro (Pedro)** | "Fechamento mensal em < 1 hora" | NAO ha RF para "fechamento mensal". O que exatamente Pedro faz? Reconcilia? Gera relatorio? Exporta para contabilidade? |
| **Cliente (Ana)** | "Portal com status da OS, certificado para download, NF disponivel" | Portal superficial. Nao pode aprovar orcamento, nao pode pagar, nao recebe notificacao |
| **Gestor (Marcia)** | "Dashboard carrega em < 3s com OS, tecnicos, pagamentos do dia" | Raio-X: "Falta visao consolidada do dia (OS + pagamentos + alertas)". O dashboard unificado NAO existe |

### 4.3 Anti-Personas Inconsistentes

O PRD diz que "Laboratorio que so faz calibracao" NAO e cliente. Mas o nicho de entrada e justamente calibracao. Muitos laboratorios de calibracao TEM campo + OS. A exclusao e vaga — deveria ser "laboratorio sem operacao de campo".

### 4.4 Numeracao de RFs com Gaps

- RF-01 a RF-12: OK, sequencial
- RF-13 (Contratos): OK
- RF-14 (Frota): OK
- RF-15 (Analytics): OK
- RF-16 (Qualidade): OK
- RF-17 (Estoque): OK
- RF-18 (Billing): OK
- **RF-19?** NAO EXISTE — pula para RF-20 (Transversais)
- **RF-20, RF-21:** Mencionados como existentes mas nao encontrados como secoes formais

---

## 5. FLUXOS DE NEGOCIO FALTANTES

### 5.1 Ciclo de Vida do Equipamento do CLIENTE

O Kalibrium calibra equipamentos dos clientes, mas nao ha fluxo completo de:
- Cadastro do equipamento DO CLIENTE (nao do Kalibrium)
- Historico de calibracoes por equipamento
- Alerta de recalibracao (quando vence a calibracao anterior)
- Rastreabilidade: qual tecnico calibrou, com qual padrao, quando

O RF-02 fala de certificados, mas o ciclo de vida do EQUIPAMENTO do cliente nao e abordado como entidade propria.

### 5.2 Orcamento → OS → Contrato

O fluxo comercial e:
1. Cliente solicita orcamento
2. Empresa envia proposta
3. Cliente aprova
4. OS e criada
5. (Opcionalmente) Contrato recorrente

O PRD tem Quotes (orcamentos) e Contratos separadamente, mas:
- NAO ha RF para "converter orcamento aprovado em OS"
- NAO ha RF para "converter orcamento em contrato recorrente"
- O CRM menciona Deal→Quote mas nao Quote→OS

### 5.3 Garantia de Servico

Apos executar uma OS, pode haver retorno por garantia. Nao ha:
- RF para OS de garantia (vinculada a OS original)
- Periodo de garantia configuravel por tipo de servico
- Controle de custo de garantia (o tecnico foi, mas nao cobra)

### 5.4 Notificacoes e Comunicacao com Cliente

O PRD menciona email esporadicamente, mas NAO ha um RF para:
- Template de email por evento (OS criada, OS concluida, certificado pronto, boleto gerado, boleto vencendo)
- Notificacao push para app do tecnico
- Notificacao WhatsApp (webhook existe no codigo, mas nao alimenta CRM — Raio-X confirma)
- Preferencia de canal por cliente (email vs WhatsApp vs SMS)

### 5.5 Onboarding de Novo Tenant

O Billing (RF-18) fala de planos e assinaturas, mas nao ha fluxo de:
- Cadastro self-service do tenant
- Wizard de configuracao inicial (logo, dados fiscais, conta bancaria, usuarios)
- Importacao de dados (clientes, equipamentos, produtos) de sistema anterior
- Trial experience (quais modulos? limite de uso?)

---

## 6. QUESTOES DE VIABILIDADE E COERENCIA

### 6.1 Escopo vs Equipe

O PRD descreve um sistema com 18+ RFs, 120+ sub-requisitos, cobrindo:
- OS + Calibracao + Certificados
- Financeiro completo (AP/AR/Conciliacao/NFS-e/Boleto/PIX)
- CRM + Pipeline + Automacao
- RH + Ponto + Folha + eSocial + Beneficios
- Estoque + Warehouse + Frota
- Portal do Cliente
- PWA Offline
- Analytics/BI
- Billing SaaS
- LGPD Compliance

**Questao:** Para uma empresa de calibracao com 5-100 funcionarios, isso e MUITO sistema. O risco de feature creep e alto. O PRD deveria priorizar mais agressivamente o que e MVP real vs "nice to have".

### 6.2 eSocial — Necessidade Real?

O publico-alvo (empresas de calibracao, 5-100 func) quase sempre usa escritorio de contabilidade para eSocial. Implementar transmissao eSocial propria e um investimento enorme com retorno duvidoso para o nicho. Ponto eletronico SIM (Portaria 671 exige registro). Mas transmissao eSocial? Deveria ser integracao com sistema contabil, nao substituicao.

### 6.3 Folha de Pagamento — Mesmo Questionamento

Calcular INSS, IRRF, FGTS exige atualizacao constante de tabelas. Um erro = processo trabalhista. Empresas deste porte usam contador. O PRD deveria considerar: gerar os dados para o contador exportar, nao calcular a folha inteira.

---

## 7. RESUMO DE ACHADOS

| Categoria | Quantidade | Severidade |
|-----------|-----------|------------|
| Status inflados (🟢 que sao 🟡/🔴) | 8 | ALTA |
| Dependencias externas nao diferenciadas | 3 | MEDIA |
| Funcionalidades CRITICAS faltando no PRD | 3 | CRITICA |
| Funcionalidades complementares faltando | 5+ | MEDIA |
| Modulos no codigo sem RF no PRD | 6 | MEDIA |
| Fluxos incompletos | 5 | ALTA |
| Fluxos que nao fazem sentido de negocio | 3 | MEDIA |
| Inconsistencias internas | 4 | MEDIA |
| Fluxos de negocio inteiramente faltantes | 5 | ALTA |
| Questoes de viabilidade/escopo | 3 | MEDIA |

### Top 5 Achados CRITICOS (Acao Imediata)

1. **Webhook de baixa automatica de pagamento** — Sem isso, a proposta de valor central do produto ("ciclo de receita sem buracos") nao funciona
2. **Portal do Cliente incompleto** — Cliente nao pode aprovar orcamento, nao pode pagar, nao recebe notificacao. Portal e "vitrine" mas nao e "ferramenta"
3. **Dashboard operacional unificado NAO existe** — Prometido para Persona 1 (Gestor), mas Raio-X confirma que falta
4. **Offline incompleto para tecnico** — Criacao de OS offline nao funciona. Em campo sem sinal, tecnico fica parado
5. **Fluxo Orcamento→OS nao especificado** — Como um orcamento aprovado vira OS? Nao ha RF para isso

### Recomendacoes

1. Criar RFs para: webhook de pagamento, baixa automatica, aprovacao de orcamento no portal, conversao orcamento→OS
2. Separar "Status Tecnico" de "Status de Producao" para itens que dependem de contrato
3. Redefinir maturidade real: ~78-82%, nao 89-93%
4. Avaliar necessidade real de Folha e eSocial completos para o publico-alvo
5. Priorizar: offline completo, dashboard unificado, portal funcional como MVP real
6. Formalizar como RFs os modulos que existem no codigo mas nao no PRD (Innovation, Customer360, BatchExport, etc.)
7. Adicionar secao de "Triggers e Automacoes" — o PRD descreve resultados mas nao os mecanismos que os produzem
