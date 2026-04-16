---
type: audit
domain: compliance
title: "Gap Analysis — ISO/IEC 17025:2017 + ISO 9001:2015 vs Sistema KALIBRIUM"
status: active
created: 2026-03-26
author: AI Agent (Claude)
---

# Gap Analysis — ISO/IEC 17025:2017 + ISO 9001:2015

> **Objetivo:** Mapear CADA cláusula normativa contra a implementação atual do KALIBRIUM ERP, identificar gaps e definir ações corretivas prioritárias.
>
> **Legenda de Status:**
> - ✅ **Conforme** — Implementado e funcional no código
> - ⚠️ **Parcial** — Existe estrutura mas faltam campos, validações ou integrações
> - ❌ **Não-conforme** — Não implementado ou com falha crítica
> - ➖ **N/A** — Não aplicável ao contexto do sistema

---

## PARTE 1 — ISO/IEC 17025:2017 (Requisitos Gerais para Competência de Laboratórios)

### §4 — Requisitos Gerais

| Cláusula | Requisito | Status | Implementação Atual | Gap | Ação Necessária | Prioridade |
|----------|-----------|--------|---------------------|-----|-----------------|------------|
| §4.1.1 | Atividades do laboratório realizadas com imparcialidade | ⚠️ | Feature flag `strict_iso_17025` com dual sign-off (`CalibrationCertificateService::approve()`) | Imparcialidade só é obrigatória quando flag está habilitada; não há declaração formal de imparcialidade no sistema | Tornar dual sign-off padrão (não opcional) quando tenant busca conformidade ISO; adicionar campo `impartiality_declaration` no tenant settings | Alta |
| §4.1.2 | Gestão comprometida com imparcialidade | ⚠️ | Tenant isolation via `BelongsToTenant` | Não há registro formal do compromisso da gestão | Incluir `QualityDocument` obrigatório tipo "Declaração de Imparcialidade" no setup do tenant | Média |
| §4.1.3 | Identificação de riscos à imparcialidade | ❌ | Não implementado | Sem mecanismo de identificação e mitigação de riscos à imparcialidade | Criar modelo `ImpartialityRiskAssessment` ou integrar ao `QualityAudit` | Média |
| §4.1.4 | Ações para mitigar riscos à imparcialidade | ❌ | Não implementado | Sem registro de ações de mitigação | Vincular ao CAPA quando risco é identificado | Média |
| §4.1.5 | Imparcialidade contínua | ⚠️ | Audit trail registra todas ações | Não há análise periódica de imparcialidade | Incluir no checklist de auditoria interna (já existe parcialmente em `QualityAudit`) | Baixa |
| §4.2.1 | Confidencialidade de informações | ✅ | Multi-tenancy com `BelongsToTenant` global scope; dados isolados por tenant | — | — | — |
| §4.2.2 | Informações ao público são confiáveis | ⚠️ | Portal do cliente (`PortalCertificatesPage.tsx`) | Certificados no portal não exibem declaração de confidencialidade | Adicionar declaração no template do certificado e no portal | Baixa |

### §5 — Requisitos Estruturais

| Cláusula | Requisito | Status | Implementação Atual | Gap | Ação Necessária | Prioridade |
|----------|-----------|--------|---------------------|-----|-----------------|------------|
| §5.1 | Entidade legal identificável | ✅ | Tenant com CNPJ, razão social, endereço completo | — | — | — |
| §5.2 | Gerência com responsabilidade global | ⚠️ | Roles e Permissions definidos | Não há role específico "Gerente Técnico do Laboratório" com responsabilidades formais documentadas | Criar role `lab_manager` com permissões específicas e descrição de responsabilidades | Média |
| §5.3 | Escopo de atividades definido | ⚠️ | `scope_declaration` no `EquipmentCalibration` | Escopo é por calibração, não por laboratório como um todo | Criar `lab_scope` no tenant settings com tipos de calibração acreditados | Alta |
| §5.4 | Requisitos atendidos consistentemente | ✅ | Validações obrigatórias no wizard, feature flags | — | — | — |
| §5.5 | Estrutura organizacional documentada | ⚠️ | `QualityDocument` genérico | Não há organograma específico do laboratório vinculado ao sistema | Criar template de organograma no `QualityDocument` com tipo `lab_org_chart` | Baixa |
| §5.6 | Pessoal com autoridade e recursos | ⚠️ | Roles/Permissions genéricos | Falta mapeamento específico de autoridades laboratoriais (quem aprova método, quem valida resultado) | Mapear permissions específicas: `lab.method.approve`, `lab.result.validate`, `lab.certificate.issue` | Alta |
| §5.7 | Comunicação adequada | ✅ | Notificações por email/push, audit trail | — | — | — |

### §6 — Requisitos de Recursos

| Cláusula | Requisito | Status | Implementação Atual | Gap | Ação Necessária | Prioridade |
|----------|-----------|--------|---------------------|-----|-----------------|------------|
| §6.1 | Disponibilidade de pessoal | ✅ | `User` com roles, scheduling via `Agenda` | — | — | — |
| **§6.2.1** | **Competência de pessoal documentada** | **❌** | **`UserCompetency` NÃO EXISTE como model** | **Gap crítico: não há registro formal de competência metrológica por tipo de calibração** | **CRIAR model `UserCompetency` com: user_id, calibration_type, qualification_level, valid_from, valid_until, evidence_file, assessed_by, assessment_date** | **Crítica** |
| §6.2.2 | Critérios de competência definidos | ❌ | Não implementado | Sem critérios formais por tipo de calibração | Definir critérios por `CalibrationType` e vincular a `UserCompetency` | Crítica |
| §6.2.3 | Autorização de pessoal para atividades | ❌ | Sem bloqueio por competência | Técnico pode ser atribuído a calibração sem competência verificada | Implementar `PreFlightCheckService` que valida competência antes de iniciar wizard | Crítica |
| §6.2.4 | Supervisão de pessoal em treinamento | ❌ | Não implementado | Sem mecanismo de supervisão obrigatória para pessoal em treinamento | Adicionar campo `supervision_required` no `UserCompetency` e obrigar co-assinatura | Média |
| §6.2.5 | Registros de treinamento atualizados | ⚠️ | HR module tem `UserSkill` (genérico) | `UserSkill` não é específico para metrologia, falta validade e evidência | Migrar para `UserCompetency` específico ou enriquecer `UserSkill` | Alta |
| §6.2.6 | Monitoramento de competência | ❌ | Não implementado | Sem reavaliação periódica de competência | Adicionar `revalidation_interval_months` e cron job para alertas | Alta |
| **§6.3.1** | **Instalações e condições ambientais adequadas** | **✅** | **`LabLogbookEntry` com T/U/P + validação de faixa** | — | — | — |
| §6.3.2 | Requisitos de instalações documentados | ⚠️ | Faixas configuráveis por tenant | Não há documento formal dos requisitos de instalação | Criar `QualityDocument` tipo `lab_facility_requirements` | Baixa |
| §6.3.3 | Monitoramento e controle de condições | ✅ | `LabLogbookEntry` com validação automática, bloqueio se fora da faixa | — | — | — |
| §6.3.4 | Medidas para prevenir contaminação cruzada | ➖ | N/A para calibração de massa/dimensional | — | — | — |
| **§6.4.1** | **Equipamentos adequados e acessíveis** | **✅** | **`Equipment` com campos completos: serial, modelo, fabricante, faixa, resolução** | — | — | — |
| §6.4.2 | Equipamentos fora do controle permanente | ⚠️ | Campo `calibration_location` existe | Não há controle formal para calibrações on-site vs lab | Adicionar enum `location_type` (laboratory/on_site/customer_site) com regras específicas | Média |
| §6.4.3 | Procedimentos para manuseio e transporte | ❌ | Não implementado | Sem checklist de recebimento/manuseio de equipamentos | Integrar ao checklist da OS (WorkOrder) — Step 1 do wizard | Alta |
| §6.4.4 | Verificação de equipamentos antes do uso | ⚠️ | `is_expired` flag no Equipment | Não há verificação intermediária (somente check de validade de calibração) | Adicionar `daily_check` ou `pre-use_check` vinculado ao logbook | Média |
| §6.4.5 | Equipamentos precisos e calibrados | ✅ | `next_calibration_at`, alertas 30/15/7 dias, bloqueio automático | — | — | — |
| §6.4.6 | Equipamento de referência calibrado | ⚠️ | `StandardWeight` model existe com `certificate_expiry` | Sem bloqueio hard quando padrão vence; cascade de falha não implementado | **Implementar `StandardWeightLifecycleService` com bloqueio hard + cascade** | **Crítica** |
| §6.4.7 | Correções aplicadas quando necessário | ⚠️ | Campos `before_adjustment_data` e `after_adjustment_data` | Dados de ajuste são JSON livres, sem validação de completude | Definir schema JSON obrigatório para dados de ajuste | Média |
| §6.4.8 | Proteção contra ajustes que invalidem | ⚠️ | Imutabilidade pós-emissão do certificado | Não há proteção contra alteração de configuração do equipamento durante calibração | Adicionar lock no equipment durante calibração ativa | Baixa |
| §6.4.9 | Identificação única de equipamentos | ✅ | `serial_number`, `tag`, `qr_code` no Equipment | — | — | — |
| §6.4.10 | Registros de equipamento atualizados | ✅ | CRUD completo com audit trail | — | — | — |
| §6.4.11 | Equipamentos defeituosos identificados | ⚠️ | Flag `is_active` no Equipment | Sem status específico "defeituoso" ou "quarentena" | Adicionar status enum: active/defective/quarantine/retired | Média |
| §6.4.12 | Efeitos de defeitos investigados | ❌ | Não implementado | Sem rastreabilidade reversa quando equipamento/padrão falha | **Implementar cascade de falha: identificar certificados afetados → suspender → notificar → CAPA** | **Crítica** |
| §6.4.13 | Dados de equipamento mantidos | ✅ | Audit trail + soft delete | — | — | — |
| **§6.5.1** | **Rastreabilidade metrológica estabelecida** | **⚠️** | **`StandardWeight` com `certificate_number`, `laboratory`, `certificate_date`** | **Faltam campos: `traceability_lab` (lab RBC/Cgcre), `traceability_certificate` (cert. do lab acreditado), `uncertainty`, `coverage_factor`** | **Enriquecer StandardWeight com campos de rastreabilidade completa** | **Crítica** |
| §6.5.2 | Rastreabilidade a padrões nacionais/internacionais | ⚠️ | Campo `laboratory` existe | Não há validação se o laboratório é acreditado RBC/Cgcre | Adicionar campo `is_accredited_lab` e validação | Alta |
| §6.5.3 | Calibração de padrões por laboratório competente | ⚠️ | Campo `certificate_number` existe | Sem verificação de validade do certificado do padrão | Vincular `certificate_expiry` ao bloqueio de uso | Alta |
| §6.6.1 | Produtos e serviços providos externamente | ⚠️ | `Supplier` model existe | Sem avaliação formal de fornecedores de serviços de calibração | Vincular ao `QualityAudit` tipo supplier | Média |

### §7 — Requisitos de Processo

| Cláusula | Requisito | Status | Implementação Atual | Gap | Ação Necessária | Prioridade |
|----------|-----------|--------|---------------------|-----|-----------------|------------|
| **§7.1.1** | **Análise crítica de pedidos, propostas e contratos** | **⚠️** | **`CalibrationViabilityService::check()` documenta verificação** | **Existe no doc mas implementação real é parcial — não verifica competência do técnico nem disponibilidade de padrões** | **Implementar PreFlightCheckService completo com TODAS as verificações** | **Crítica** |
| §7.1.2 | Informações ao cliente antes do início | ⚠️ | Quote → WorkOrder | Não há comunicação formal sobre método, incerteza esperada, prazo | Adicionar campos no Quote/WorkOrder: `estimated_uncertainty`, `method_reference`, `expected_duration` | Média |
| §7.1.3 | Desvios de pedido comunicados | ⚠️ | Notifications existem | Não há notificação específica para desvios de método | Criar evento `MethodDeviationDetected` com notification | Média |
| §7.1.4 | Emendas a contratos comunicadas | ✅ | Audit trail em WorkOrder | — | — | — |
| §7.1.5 | Subcontratação comunicada ao cliente | ❌ | Não implementado | Sem controle de subcontratação de calibrações | Criar modelo `SubcontractedCalibration` se necessário | Baixa |
| §7.1.6 | Cliente informado sobre desvios | ⚠️ | Notifications genéricos | Sem template específico para desvios de calibração | Criar notification template `CalibrationDeviationNotification` | Média |
| §7.1.7 | Registros de análise crítica | ⚠️ | Audit trail genérico | Sem registro específico da análise de viabilidade | Persistir resultado do `PreFlightCheckService` como registro | Alta |
| §7.1.8 | Cooperação com clientes | ✅ | Portal do cliente com acesso a certificados | — | — | — |
| **§7.2.1** | **Seleção, verificação e validação de métodos** | **❌** | **`CalibrationMethod` model NÃO EXISTE** | **Gap crítico: métodos de calibração não são formalizados no sistema** | **CRIAR model `CalibrationMethod` com: name, code, instrument_type_id, description, uncertainty_formula (JSON), range_min/max, unit, is_accredited, revision, approved_by, is_active** | **Crítica** |
| §7.2.2 | Métodos adequados ao uso pretendido | ❌ | Sem validação de adequação do método | Método não é vinculado ao tipo de equipamento/faixa | Vincular `CalibrationMethod` a `EquipmentType` e `measurement_range` | Crítica |
| §7.2.3 | Instruções de métodos atualizadas e acessíveis | ❌ | Sem repositório de procedimentos operacionais | Técnico não tem acesso digital ao procedimento durante calibração | Integrar `CalibrationMethod.description` ao wizard (Step 3) como guia contextual | Alta |
| §7.2.4 | Desvios de métodos documentados | ⚠️ | Campo `technician_notes` existe | Não há campo específico para desvios do método | **Adicionar campo `method_deviations` no `EquipmentCalibration`** | **Alta** |
| §7.2.5 | Validação de métodos | ❌ | Não implementado | Sem registro de validação de métodos não-normalizados | Adicionar workflow de validação no `CalibrationMethod` | Média |
| §7.3 | Amostragem | ⚠️ | Não formalmente implementado | Para calibração, amostragem se aplica a seleção de pontos de medição | **Adicionar campo `sampling_info` com justificativa dos pontos escolhidos** | **Média** |
| §7.4 | Manuseio de itens de ensaio/calibração | ⚠️ | Checklist na WorkOrder | Sem checklist específico para recebimento de itens de calibração | Criar checklist obrigatório: identificação, condição visual, acessórios, danos | Alta |
| **§7.5.1** | **Registros técnicos rastreáveis** | **✅** | **`CalibrationReading` com leituras, EMA, conformidade; `EquipmentCalibration` com todos campos ISO** | — | — | — |
| §7.5.2 | Emendas em registros rastreáveis | ✅ | `CalibrationCertificateRevision` com reason, changed_by, changed_at | — | — | — |
| **§7.6.1** | **Avaliação da incerteza de medição** | **✅** | **`UncertaintyCalculationService` com GUM, bcmath, Type A + B, combined + expanded** | — | — | — |
| §7.6.2 | Fontes de incerteza identificadas | ✅ | `uncertainty_budget` (JSON) com fontes detalhadas | — | — | — |
| §7.6.3 | Incerteza expandida com fator k | ✅ | `expanded_uncertainty` + `coverage_factor` nos campos | — | — | — |
| **§7.7.1** | **Garantia da validade dos resultados** | **⚠️** | **Validação ambiental existe, rastreabilidade parcial** | **Falta verificação intermediária (cartas de controle obrigatórias, ensaios de proficiência)** | **Integrar `CalibrationControlChartController` como check obrigatório; criar registro de participação em ensaios interlaboratoriais** | **Alta** |
| §7.7.2 | Monitoramento do desempenho | ⚠️ | SPC charts existem (`CalibrationControlChartController`) | Não é obrigatório antes de emitir certificado | Tornar análise de tendência obrigatória no wizard | Média |
| §7.7.3 | Participação em ensaios de proficiência | ❌ | Não implementado | Sem registro de participação em comparações interlaboratoriais | Criar model `ProficiencyTest` com participações e resultados | Média |
| **§7.8.1** | **Relato de resultados — Generalidades** | **✅** | **`CalibrationCertificateService::generate()` gera PDF com campos obrigatórios** | — | — | — |
| §7.8.2.1 | Título do certificado | ✅ | Template com "Certificado de Calibração" | — | — | — |
| §7.8.2.2 | Nome e endereço do laboratório | ✅ | `laboratory_address` | — | — | — |
| §7.8.2.3 | Local da calibração (se diferente) | ✅ | `calibration_location` | — | — | — |
| §7.8.2.4 | Identificação única | ✅ | `certificate_number` sequencial | — | — | — |
| §7.8.2.5 | Nome e informações do cliente | ⚠️ | Via relationship Equipment → Customer | **Falta endereço completo do cliente no certificado** | Adicionar `customer_address` ao contexto do certificado | Alta |
| §7.8.2.6 | Identificação do método | ⚠️ | Campo `technician_notes` pode conter referência | **Sem referência formal ao método (CalibrationMethod não existe)** | Vincular `calibration_method_id` FK ao certificado | Crítica |
| §7.8.2.7 | Descrição do item calibrado | ✅ | Via Equipment: manufacturer, model, serial_number | — | — | — |
| §7.8.2.8 | Data de recebimento | ✅ | `received_date` no wizard | — | — | — |
| §7.8.2.9 | Data da calibração | ✅ | `calibration_date` | — | — | — |
| §7.8.2.10 | Data de emissão | ✅ | `issued_date` | — | — | — |
| §7.8.2.11 | Referência ao plano de amostragem | ⚠️ | Pontos de medição sugeridos pelo wizard | **Sem referência formal (sampling_info)** | Adicionar campo | Média |
| §7.8.2.12 | Resultados com unidades | ✅ | `CalibrationReading` com values + unit | — | — | — |
| §7.8.2.13 | Condições ambientais | ✅ | T/U/P registrados | — | — | — |
| §7.8.2.14 | Incerteza de medição | ✅ | `expanded_uncertainty` + `coverage_factor` + `uncertainty_budget` | — | — | — |
| §7.8.2.15 | Assinaturas | ⚠️ | Campos `performer_id` e `approver_id` | **Assinatura não é digital real (apenas user_id)** | Implementar assinatura digital com hash ou canvas signature | Alta |
| §7.8.2.16 | Identificação da acreditação | ⚠️ | `scope_declaration` | Não vinculado ao escopo formal do laboratório | Vincular ao `lab_scope` do tenant | Média |
| **§7.8.3** | **Declaração de conformidade** | **⚠️** | **Campo `decision_rule` existe (simples/guard band)** | **`ConformityAssessmentService` NÃO EXISTE — regra é aplicada manualmente** | **CRIAR service com: simple acceptance, guard band, shared risk; automação da declaração** | **Crítica** |
| §7.8.4 | Opiniões e interpretações | ❌ | Não implementado | Sem campo para opinião/interpretação quando solicitada pelo cliente | Adicionar campo `interpretation_opinion` (nullable) | Baixa |
| §7.8.5 | Emendas a certificados | ✅ | `CalibrationCertificateRevision` com versionamento completo | — | — | — |
| §7.8.6 | Declaração de reprodução | ❌ | Não implementado | Sem declaração de que reprodução parcial requer aprovação do laboratório | Adicionar no template PDF | Baixa |
| §7.9 | Reclamações | ✅ | `QualityNonConformance` com source = 'cliente' | — | — | — |
| §7.10 | Trabalho não conforme | ✅ | `QualityCorrectiveAction` com CAPA obrigatório | — | — | — |
| §7.11 | Controle de dados e informação | ✅ | Audit trail imutável, backup, hash SHA-256 | — | — | — |

### §8 — Requisitos do Sistema de Gestão

| Cláusula | Requisito | Status | Implementação Atual | Gap | Ação Necessária | Prioridade |
|----------|-----------|--------|---------------------|-----|-----------------|------------|
| §8.1 | Opção de SGQ (A ou B) | ✅ | Opção A implementada (SGQ próprio via módulo Quality) | — | — | — |
| §8.2 | Documentação do SGQ | ✅ | `QualityDocument` com versionamento e aprovação | — | — | — |
| §8.3 | Controle de documentos | ✅ | Workflow: draft → pending_review → approved → obsolete | — | — | — |
| §8.4 | Controle de registros | ✅ | Retenção 5 anos, soft delete, imutabilidade | — | — | — |
| §8.5 | Ações para riscos e oportunidades | ✅ | Análise de tendência de RNCs, CAPAs preventivos | — | — | — |
| §8.6 | Melhoria | ✅ | Ciclo PDCA via RNC → CAPA → verificação de eficácia | — | — | — |
| §8.7 | Ações corretivas | ✅ | `QualityCorrectiveAction` com 5 Porquês obrigatório | — | — | — |
| §8.8 | Auditorias internas | ✅ | `QualityAudit` com checklist configurável | — | — | — |
| §8.9 | Análise crítica pela direção | ✅ | Dashboard consolidado com KPIs de todos os módulos | — | — | — |

---

## PARTE 2 — ISO 9001:2015 (Aplicável ao Contexto de Calibração)

### §4 — Contexto da Organização

| Cláusula | Requisito | Status | Gap | Ação |
|----------|-----------|--------|-----|------|
| §4.1 | Contexto da organização | ✅ | — | — |
| §4.2 | Partes interessadas | ✅ | — | — |
| §4.3 | Escopo do SGQ | ✅ | — | — |
| §4.4 | SGQ e seus processos | ✅ | — | — |

### §5 — Liderança

| Cláusula | Requisito | Status | Gap | Ação |
|----------|-----------|--------|-----|------|
| §5.1 | Compromisso da liderança | ✅ | — | — |
| §5.2 | Política da qualidade | ✅ | — | — |
| §5.3 | Papéis e responsabilidades | ⚠️ | Falta role específico `lab_manager` | Criar role com permissões laboratoriais específicas |

### §6 — Planejamento

| Cláusula | Requisito | Status | Gap | Ação |
|----------|-----------|--------|-----|------|
| §6.1 | Riscos e oportunidades | ✅ | — | — |
| §6.2 | Objetivos da qualidade | ✅ | — | — |
| §6.3 | Planejamento de mudanças | ✅ | — | — |

### §7 — Apoio

| Cláusula | Requisito | Status | Gap | Ação |
|----------|-----------|--------|-----|------|
| §7.1 | Recursos | ✅ | — | — |
| §7.2 | Competência | ❌ | `UserCompetency` não existe | Criar model (ver §6.2 da 17025) |
| §7.3 | Conscientização | ✅ | — | — |
| §7.4 | Comunicação | ✅ | — | — |
| §7.5 | Informação documentada | ✅ | — | — |

### §8 — Operação

| Cláusula | Requisito | Status | Gap | Ação |
|----------|-----------|--------|-----|------|
| §8.1 | Planejamento operacional | ⚠️ | OS não vinculada diretamente ao certificado | Criar FK `work_order_id` no `EquipmentCalibration` |
| §8.2 | Requisitos de produtos/serviços | ✅ | — | — |
| §8.4 | Controle de fornecedores | ✅ | — | — |
| §8.5 | Produção e provisão de serviço | ⚠️ | Wizard sem guia contextual; técnico pode errar | Redesenhar wizard com 10 steps guiados |
| §8.7 | Controle de saídas não conformes | ✅ | — | — |

### §9 — Avaliação de Desempenho

| Cláusula | Requisito | Status | Gap | Ação |
|----------|-----------|--------|-----|------|
| §9.1 | Monitoramento e medição | ✅ | — | — |
| §9.2 | Auditoria interna | ✅ | — | — |
| §9.3 | Análise crítica | ✅ | — | — |

### §10 — Melhoria

| Cláusula | Requisito | Status | Gap | Ação |
|----------|-----------|--------|-----|------|
| §10.1 | Melhoria contínua | ✅ | — | — |
| §10.2 | Não conformidade e ação corretiva | ✅ | — | — |
| §10.3 | Melhoria contínua (dados) | ✅ | — | — |

---

## PARTE 3 — RESUMO DE GAPS CRÍTICOS (PRIORIDADE MÁXIMA)

### Gaps Críticos — Requerem Implementação Imediata

| # | Gap | Cláusula ISO | Impacto | Esforço | Fase do Plano |
|---|-----|-------------|---------|---------|---------------|
| **G01** | `UserCompetency` model não existe | 17025 §6.2 / 9001 §7.2 | Técnico pode calibrar sem competência válida — **invalida certificados** | Alto | FASE 8 |
| **G02** | `CalibrationMethod` model não existe | 17025 §7.2 | Métodos não são formalizados — **certificado sem referência ao método** | Alto | FASE 3 |
| **G03** | `ConformityAssessmentService` não existe | 17025 §7.8.3 | Declaração de conformidade manual/inconsistente — **risco legal** | Médio | FASE 3 |
| **G04** | `StandardWeight` sem lifecycle completo | 17025 §6.4/§6.5 | Padrão vencido pode ser usado — **invalida rastreabilidade** | Alto | FASE 2 |
| **G05** | Cascade de falha não implementado | 17025 §6.4.12 | Padrão falha mas certificados afetados não são suspensos — **risco grave** | Alto | FASE 2 |
| **G06** | `work_order_id` FK ausente no certificado | 9001 §8.1 | Certificado desconectado da OS — **sem rastreabilidade operacional** | Baixo | FASE 5 |
| **G07** | `PreFlightCheckService` incompleto | 17025 §7.1 | Calibração pode iniciar sem pré-requisitos validados | Médio | FASE 3 |

### Gaps Importantes — Requerem Implementação a Curto Prazo

| # | Gap | Cláusula ISO | Impacto | Fase |
|---|-----|-------------|---------|------|
| G08 | Assinatura digital real (não apenas user_id) | 17025 §7.8.2.15 | Autenticidade do certificado questionável | FASE 4 |
| G09 | Campo `method_deviations` faltante | 17025 §7.2.4 | Desvios não documentados formalmente | FASE 4 |
| G10 | Campo `sampling_info` faltante | 17025 §7.3/§7.8.2.11 | Sem justificativa de pontos de medição | FASE 4 |
| G11 | Endereço completo do cliente no certificado | 17025 §7.8.2.5 | Certificado incompleto | FASE 4 |
| G12 | Checklist de recebimento de itens | 17025 §7.4 | Sem registro formal de condição do item | FASE 5 |
| G13 | Escopo formal do laboratório | 17025 §5.3 | Sem declaração de escopo no tenant | FASE 9 |
| G14 | Ensaios de proficiência | 17025 §7.7.3 | Sem registro de comparações interlaboratoriais | FASE 7 |
| G15 | Wizard sem guia contextual | 9001 §8.5 | Técnico pode errar por falta de orientação | FASE 3 |

### Gaps Menores — Melhorias de Conformidade

| # | Gap | Cláusula ISO | Fase |
|---|-----|-------------|------|
| G16 | Campo `interpretation_opinion` | 17025 §7.8.4 | FASE 4 |
| G17 | Declaração de reprodução parcial no PDF | 17025 §7.8.6 | FASE 4 |
| G18 | Paginação "Página X de Y" no PDF | 17025 §7.8.2 | FASE 4 |
| G19 | Declaração de imparcialidade formal | 17025 §4.1 | FASE 9 |
| G20 | Organograma do laboratório | 17025 §5.5 | FASE 9 |

---

## PARTE 4 — ESTATÍSTICAS DE CONFORMIDADE

### ISO/IEC 17025:2017

| Seção | Total Cláusulas | ✅ Conforme | ⚠️ Parcial | ❌ Não-conforme | ➖ N/A |
|-------|----------------|------------|-----------|-----------------|--------|
| §4 Requisitos Gerais | 7 | 1 | 5 | 1 | 0 |
| §5 Requisitos Estruturais | 7 | 3 | 4 | 0 | 0 |
| §6 Requisitos de Recursos | 22 | 8 | 8 | 5 | 1 |
| §7 Requisitos de Processo | 30 | 16 | 9 | 5 | 0 |
| §8 Requisitos do SGQ | 9 | 9 | 0 | 0 | 0 |
| **TOTAL** | **75** | **37 (49%)** | **26 (35%)** | **11 (15%)** | **1 (1%)** |

### ISO 9001:2015 (Contexto Calibração)

| Seção | Total | ✅ | ⚠️ | ❌ |
|-------|-------|---|---|---|
| §4-6 | 10 | 10 | 0 | 0 |
| §7 | 5 | 3 | 0 | 2 |
| §8 | 5 | 3 | 2 | 0 |
| §9 | 3 | 3 | 0 | 0 |
| §10 | 3 | 3 | 0 | 0 |
| **TOTAL** | **26** | **22 (85%)** | **2 (8%)** | **2 (8%)** |

### Resumo Geral

- **ISO 17025:** 49% conforme, 35% parcial, 15% não-conforme → **Meta: 100% conforme após execução do plano**
- **ISO 9001:** 85% conforme, 8% parcial, 8% não-conforme → **Meta: 100% conforme**
- **Gaps críticos:** 7 (requerem implementação imediata)
- **Gaps importantes:** 8 (curto prazo)
- **Gaps menores:** 5 (melhorias)
- **Total de ações necessárias:** 20

---

## PARTE 5 — ROADMAP DE RESOLUÇÃO

```
SEMANA 1-2: Gaps Críticos (G01-G07)
├── G01: Criar UserCompetency model + migration + service + bloqueio
├── G02: Criar CalibrationMethod model + migration + CRUD
├── G03: Criar ConformityAssessmentService (decision rules)
├── G04: Enriquecer StandardWeight + lifecycle service + bloqueio hard
├── G05: Implementar cascade de falha + suspensão de certificados
├── G06: Migration work_order_id FK + relationship
└── G07: Implementar PreFlightCheckService completo

SEMANA 3-4: Gaps Importantes (G08-G15)
├── G08: Assinatura digital (canvas/hash)
├── G09-G11: Migration campos faltantes no certificado
├── G12: Checklist de recebimento na OS
├── G13: Escopo formal do laboratório
├── G14: Model ProficiencyTest
└── G15: Redesenhar wizard com guia contextual (10 steps)

SEMANA 5: Gaps Menores (G16-G20) + Documentação
├── G16-G18: Campos opcionais + paginação PDF
├── G19-G20: Declarações formais
└── Atualização de toda documentação
```

---

> **Próximos passos:** Este gap analysis deve ser revisado pela gestão do laboratório e usado como input para as Fases 2-8 do plano de implementação. Cada gap resolvido deve ser marcado como "Conforme" nesta matriz e verificado por teste automatizado.
