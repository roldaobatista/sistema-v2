# Plano: Calibração Normativa Completa (ISO 17025 / Portaria 157/2022)

> **Objetivo:** Tornar o fluxo de calibração de balanças 100% funcional ponta a ponta — da abertura da OS com análise crítica até a emissão do certificado PDF com checklist de verificação, passando por manutenção, rastreabilidade e portal do técnico.
>
> **Runner:** Host (Windows, sem Sail)
> **Branch:** `main` (commits atômicos)

---

## Documentos de Referência (LEITURA OBRIGATÓRIA antes de implementar)

| Documento | Caminho | Conteúdo |
|-----------|---------|----------|
| **Requisitos Normativos** | `docs/compliance/calibracao-balancas.md` | Checklist completo: campos obrigatórios da OS, registro técnico, certificado de calibração, rastreabilidade, declaração de conformidade, regra de decisão, manutenção — baseado em Portaria 157/2022, ISO 17025, RBC/Cgcre, Portaria 457/2021 |
| **PRD Kalibrium** | `docs/PRD-KALIBRIUM.md` | RFs, ACs e gaps conhecidos por módulo (v3.2+, verificado contra código em 2026-04-10). Grep no código antes de afirmar gap. |
| **Guia de Testes** | `backend/TESTING_GUIDE.md` | Como rodar testes, DB SQLite, schema dump |
| **Template de Testes** | `backend/tests/README.md` | Padrão de teste por controller |
| **Regras do Agente** | `.agent/rules/iron-protocol.md` | Iron Protocol — regras invioláveis de implementação |
| **Test Policy** | `.agent/rules/test-policy.md` | Política de testes: nunca mascarar, sempre criar faltantes |
| **Test Runner** | `.agent/rules/test-runner.md` | Comando e regras operacionais para rodar testes |

### Arquivos-chave existentes que serão modificados

| Arquivo | O que contém | O que vai mudar |
|---------|-------------|-----------------|
| `backend/resources/views/pdf/calibration-certificate.blade.php` | Template Blade do PDF do certificado | Adicionar 16+ campos obrigatórios ISO 17025 |
| `backend/app/Services/CalibrationCertificateService.php` | Geração e envio de PDF | Adicionar gate do checklist + dados normativos |
| `backend/database/seeders/PermissionsSeeder.php` | Permissões Spatie | Adicionar `calibration.certificate.manage`, `os.maintenance_report.*` |
| `frontend/src/pages/calibracao/CalibrationWizardPage.tsx` | Wizard 6 steps | Adicionar step 7 "Verificação" (checklist) |
| `frontend/src/pages/os/WorkOrderCreatePage.tsx` | Formulário de criação da OS | Adicionar seção "Análise Crítica" condicional |
| `frontend/src/pages/os/WorkOrderDetailPage.tsx` | Detalhe da OS | Adicionar tab "Manutenção" |
| `frontend/public/sw.js` | Service Worker PWA | Adicionar novos endpoints cacheáveis |
| `backend/app/Http/Resources/WorkOrderResource.php` | Serialização da OS | Incluir campos análise crítica + maintenance_reports |

### Models e controllers já criados nesta implementação

| Arquivo | Status |
|---------|--------|
| `backend/app/Models/MaintenanceReport.php` | ✅ Criado |
| `backend/app/Models/CertificateEmissionChecklist.php` | ✅ Criado |
| `backend/app/Http/Controllers/Api/V1/Os/MaintenanceReportController.php` | ✅ Criado |
| `backend/app/Http/Controllers/Api/V1/Os/CertificateEmissionChecklistController.php` | ✅ Criado |
| `backend/app/Http/Requests/MaintenanceReport/StoreMaintenanceReportRequest.php` | ✅ Criado |
| `backend/app/Http/Requests/MaintenanceReport/UpdateMaintenanceReportRequest.php` | ✅ Criado |
| `backend/app/Http/Requests/CertificateEmissionChecklist/StoreCertificateEmissionChecklistRequest.php` | ✅ Criado |
| `backend/database/factories/MaintenanceReportFactory.php` | ✅ Criado |
| `backend/database/factories/CertificateEmissionChecklistFactory.php` | ✅ Criado |
| `backend/tests/Feature/Api/V1/Os/MaintenanceReportControllerTest.php` | ✅ 10 testes passando |
| `backend/tests/Feature/Api/V1/Os/CertificateEmissionChecklistControllerTest.php` | ✅ 7 testes passando |
| `frontend/src/types/work-order.ts` | ✅ Atualizado com tipos novos |
| `frontend/src/types/calibration.ts` | ✅ Atualizado com rastreabilidade |

### Commit de referência

`80ed5e4f` — `feat(calibracao): implementa requisitos normativos ISO 17025 / Portaria 157/2022`

---

## Estado Atual (já implementado)

- [x] Migrations: campos análise crítica na `work_orders`
- [x] Migrations: rastreabilidade no `standard_weights`
- [x] Migrations: tabela `maintenance_reports`
- [x] Migrations: tabela `certificate_emission_checklists`
- [x] Models: MaintenanceReport, CertificateEmissionChecklist
- [x] Controllers: MaintenanceReportController, CertificateEmissionChecklistController
- [x] FormRequests: Store/Update MaintenanceReport, StoreCertificateEmissionChecklist
- [x] Rotas API registradas
- [x] Factories + 17 testes passando
- [x] Tipos TypeScript atualizados
- [x] Schema SQLite regenerado
- [x] Documento normativo completo

---

## Etapa 1 — Permissões e Seeder

**Objetivo:** Garantir que as novas rotas sejam acessíveis com permissões corretas.

- [x] 1.1 Adicionar permissão `calibration.certificate.manage` no PermissionsSeeder (não existe, mas é usada no FormRequest)
- [x] 1.2 Adicionar permissão `os.maintenance_report.view` e `os.maintenance_report.manage` no PermissionsSeeder
- [x] 1.3 Rodar seeder e verificar que permissões existem
- [x] 1.4 Teste: verificar que endpoints retornam 403 sem permissão

**Gate Final:** `php artisan db:seed --class=PermissionsSeeder` sem erros

---

## Etapa 2 — PDF do Certificado de Calibração (Normativo)

**Objetivo:** Adaptar o template Blade existente para incluir todos os campos obrigatórios da ISO 17025 e Portaria 157/2022.

- [x] 2.1 Ler template atual `resources/views/pdf/calibration-certificate.blade.php`
- [x] 2.2 Adicionar campos obrigatórios faltantes no template:
  - Título "Certificado de Calibração"
  - Identificação única do certificado
  - Identificação do laboratório emissor (nome, endereço, acreditação)
  - Identificação do cliente (nome, CNPJ, endereço)
  - Identificação inequívoca do instrumento (marca, modelo, nº série, capacidade, divisão, classe)
  - Data da calibração + data de emissão
  - Local da calibração
  - Procedimento/método utilizado
  - Resultados da calibração (tabela de leituras)
  - Unidade de medida
  - Incerteza de medição associada
  - Padrões de referência usados (com certificado e rastreabilidade)
  - Condição "como encontrado" / "como deixado"
  - Paginação "Página X de Y"
  - Regra de decisão e declaração de conformidade (quando aplicável)
  - Identificação de quem autorizou a emissão
  - Excentricidade e repetibilidade (quando executadas)
- [x] 2.3 Adicionar seção de rastreabilidade dos padrões (StandardWeights com certificado, laboratório, acreditação)
- [x] 2.4 Garantir que NÃO apareça "validade" ou "intervalo de calibração" automaticamente
- [x] 2.5 Atualizar `CalibrationCertificateService.generate()` para passar os dados novos ao template
- [x] 2.6 Teste: gerar PDF com dados completos e verificar campos presentes

**Gate Final:** PDF gerado contém todos os 16+ campos obrigatórios listados no doc normativo

---

## Etapa 3 — Validação de Emissão (Checklist como Gate)

**Objetivo:** Bloquear emissão do certificado se o checklist não estiver aprovado.

- [x] 3.1 Modificar `CalibrationCertificateService.generate()` para verificar `CertificateEmissionChecklist.approved === true`
- [x] 3.2 Se não aprovado, lançar exceção com mensagem clara dos itens pendentes
- [x] 3.3 Endpoint `POST /calibration/{id}/generate-certificate`: adicionar validação antes de gerar
- [x] 3.4 Teste: tentativa de gerar certificado sem checklist aprovado retorna 422
- [x] 3.5 Teste: tentativa com checklist aprovado gera PDF com sucesso

**Gate Final:** Testes passam, emissão bloqueada sem checklist

---

## Etapa 4 — API Resources (Serialização Padronizada)

**Objetivo:** Criar API Resources para os novos models e atualizar os existentes.

- [x] 4.1 Criar `MaintenanceReportResource` com campos + relações
- [x] 4.2 Criar `CertificateEmissionChecklistResource`
- [x] 4.3 Atualizar `WorkOrderResource` para incluir campos de análise crítica e `maintenance_reports`
- [x] 4.4 Atualizar `EquipmentCalibrationResource` (ou criar se não existir) para incluir `emission_checklist`
- [x] 4.5 Usar Resources nos controllers ao invés de `response()->json($model)`
- [x] 4.6 Teste: verificar que responses JSON mantêm estrutura esperada

**Gate Final:** Todos os endpoints retornam via API Resource com estrutura documentada

---

## Etapa 5 — Frontend: Análise Crítica no Formulário da OS

**Objetivo:** Mostrar os campos de análise crítica quando `service_type === 'calibracao'`.

- [x] 5.1 Ler `WorkOrderCreatePage.tsx` e `WorkOrderDetailPage.tsx`
- [x] 5.2 Criar componente `CalibrationCriticalAnalysis.tsx`:
  - Select: `service_modality` (calibração, inspeção, manutenção, ajuste, diagnóstico)
  - Toggle: `requires_adjustment`
  - Toggle: `requires_maintenance`
  - Toggle: `client_wants_conformity_declaration`
  - Select: `decision_rule_agreed` (simple, guard_band, shared_risk) — visível apenas se conformity=true
  - Toggle: `subject_to_legal_metrology`
  - Toggle: `needs_ipem_interaction` — visível apenas se legal_metrology=true
  - Textarea: `site_conditions`
  - Textarea: `calibration_scope_notes`
  - Toggle: `will_emit_complementary_report`
- [x] 5.3 Integrar componente na criação da OS (condicional: `service_type === 'calibracao'`)
- [x] 5.4 Integrar componente na edição da OS
- [x] 5.5 Atualizar `work-order-api.ts` para enviar os novos campos
- [x] 5.6 Teste visual: criar OS de calibração e verificar que campos aparecem e salvam

**Gate Final:** Campos visíveis, salvam no banco, aparecem na visualização da OS

---

## Etapa 6 — Frontend: Relatório de Manutenção

**Objetivo:** CRUD de relatórios de manutenção dentro do detalhe da OS.

- [x] 6.1 Criar `lib/maintenance-report-api.ts` com: list, create, update, delete, approve
- [x] 6.2 Criar hook `useMaintenanceReports.ts` (React Query)
- [x] 6.3 Criar componente `MaintenanceReportForm.tsx`:
  - Textarea: defeito encontrado (obrigatório)
  - Textarea: causa provável
  - Textarea: ação corretiva
  - Array dinâmico: peças trocadas (nome, part_number, origem, quantidade)
  - Select: seal_status (intacto, quebrado, substituído, não aplicável)
  - Input: new_seal_number (condicional: seal_status === 'replaced')
  - Select: condition_before / condition_after
  - Toggle: requires_calibration_after
  - Toggle: requires_ipem_verification
  - DatePicker: started_at / completed_at
  - Textarea: notes
- [x] 6.4 Criar componente `MaintenanceReportList.tsx` com cards/tabela e botão aprovar
- [x] 6.5 Criar tab `MaintenanceReportsTab.tsx` para integrar no `WorkOrderDetailPage`
- [x] 6.6 Adicionar tab no detalhe da OS (visível quando `requires_maintenance === true` OU quando existem reports)
- [x] 6.7 Teste visual: criar relatório, editar, aprovar, deletar

**Gate Final:** CRUD completo funciona na UI, dados persistem via API

---

## Etapa 7 — Frontend: Checklist de Emissão do Certificado

**Objetivo:** Interface de verificação pré-emissão integrada ao wizard de calibração.

- [x] 7.1 Criar `lib/certificate-checklist-api.ts`
- [x] 7.2 Criar componente `CertificateEmissionChecklistForm.tsx`:
  - 11 checkboxes com labels descritivos do documento normativo
  - Textarea: observações
  - Indicador visual: aprovado (verde) / pendente (amarelo)
  - Botão "Verificar e Aprovar"
- [x] 7.3 Integrar como step 7 do CalibrationWizardPage (após repeatability):
  - Nome: "Verificação" (icon: CheckCircle)
  - Só habilita botão "Gerar Certificado" quando todos os 11 itens estão ✅
- [x] 7.4 Integrar na `TechCertificatePage.tsx` (portal do técnico)
- [x] 7.5 Criar API call para buscar checklist existente ao abrir step
- [x] 7.6 Teste visual: completar wizard, preencher checklist, gerar certificado

**Gate Final:** Certificado só pode ser gerado após checklist 100% aprovado

---

## Etapa 8 — Frontend: Rastreabilidade dos Padrões Expandida

**Objetivo:** Mostrar campos de rastreabilidade (acreditação, cadeia) nos padrões.

- [x] 8.1 Atualizar formulário de StandardWeight (página existente) com:
  - Input: `laboratory_accreditation` (ex: "RBC/Cgcre CRL-0042")
  - Textarea: `traceability_chain` (cadeia resumida)
- [x] 8.2 Atualizar listagem de StandardWeights para mostrar status de acreditação
- [x] 8.3 No wizard de calibração (step "Padrões"), mostrar acreditação e validade de cada peso selecionado
- [x] 8.4 Alerta visual se peso selecionado tem certificado vencido ou sem acreditação
- [x] 8.5 Atualizar `standard-weight-api.ts` se necessário

**Gate Final:** Padrões mostram rastreabilidade completa no wizard e no CRUD

---

## Etapa 9 — PWA: Cache e Offline para Novos Endpoints

**Objetivo:** Garantir que os novos endpoints funcionam offline.

- [x] 9.1 Atualizar `public/sw.js` — adicionar patterns cacheáveis:
  - `maintenance-reports`
  - `certificate-emission-checklist`
- [x] 9.2 Garantir que `syncEngine.ts` processa corretamente POSTs para os novos endpoints
- [x] 9.3 Testar fluxo offline: criar relatório de manutenção sem conexão, sincronizar ao reconectar

**Gate Final:** Relatório criado offline aparece após sync

---

## Etapa 10 — Wizard: Integração Completa do Fluxo

**Objetivo:** Conectar todos os pontos no fluxo completo.

- [x] 10.1 No wizard step "Identificação", se OS vinculada tem `requires_maintenance`, mostrar alerta: "Relatório de manutenção pendente"
- [x] 10.2 No wizard step "Padrões", validar que todos os pesos têm certificado válido
- [x] 10.3 Após step "Verificação" (checklist), habilitar botão "Gerar Certificado PDF"
- [x] 10.4 Botão "Gerar PDF" chama endpoint, baixa PDF, e mostra preview
- [x] 10.5 Botão "Enviar por Email" chama endpoint de envio
- [x] 10.6 Se OS tem `client_wants_conformity_declaration`, incluir seção de declaração no PDF
- [x] 10.7 Se OS tem `subject_to_legal_metrology`, incluir aviso no certificado

**Gate Final:** Fluxo completo: OS → Wizard 7 steps → Checklist → PDF → Email

---

## Etapa 11 — Testes Automatizados Complementares

**Objetivo:** Cobrir todos os fluxos novos com testes backend.

- [x] 11.1 Testes do CalibrationCertificateService com checklist gate
- [x] 11.2 Testes do PDF: verificar que campos obrigatórios estão presentes no HTML gerado
- [x] 11.3 Testes de permissão: 403 para endpoints sem permissão adequada
- [x] 11.4 Testes de validação: campos condicionais (decision_rule só se conformity=true, etc.)
- [x] 11.5 Testes do WorkOrder com campos de análise crítica (store/update)
- [x] 11.6 Testes do StandardWeight com novos campos de rastreabilidade

**Gate Final:** `./vendor/bin/pest --parallel --processes=16 --no-coverage` — todos passam

---

## Etapa 12 — Quality Gates e Schema Final

**Objetivo:** Garantir qualidade e consistência antes de fechar.

- [x] 12.1 Rodar `./vendor/bin/pint` (code style)
- [x] 12.2 Regenerar SQLite schema dump
- [x] 12.3 Verificar que frontend compila sem erros: `npm run build`
- [x] 12.4 Verificar tipos TypeScript: `npm run typecheck` (se disponível)
- [x] 12.5 Revisar diff total: nenhum TODO, nenhum código morto, nenhum console.log

**Gate Final:** Build limpo, testes passam, schema atualizado

---

## Resumo de Arquivos por Etapa

### Etapa 1 (Permissões)
- `database/seeders/PermissionsSeeder.php`

### Etapa 2 (PDF)
- `resources/views/pdf/calibration-certificate.blade.php`
- `app/Services/CalibrationCertificateService.php`

### Etapa 3 (Gate de Emissão)
- `app/Services/CalibrationCertificateService.php`
- `tests/Feature/Api/V1/CalibrationCertificateGateTest.php`

### Etapa 4 (API Resources)
- `app/Http/Resources/MaintenanceReportResource.php`
- `app/Http/Resources/CertificateEmissionChecklistResource.php`
- `app/Http/Resources/WorkOrderResource.php` (update)

### Etapa 5 (Frontend: Análise Crítica)
- `frontend/src/components/os/CalibrationCriticalAnalysis.tsx`
- `frontend/src/pages/os/WorkOrderCreatePage.tsx` (update)
- `frontend/src/pages/os/WorkOrderDetailPage.tsx` (update)
- `frontend/src/lib/work-order-api.ts` (update)

### Etapa 6 (Frontend: Manutenção)
- `frontend/src/lib/maintenance-report-api.ts`
- `frontend/src/hooks/useMaintenanceReports.ts`
- `frontend/src/components/os/MaintenanceReportForm.tsx`
- `frontend/src/components/os/MaintenanceReportList.tsx`
- `frontend/src/components/os/MaintenanceReportsTab.tsx`

### Etapa 7 (Frontend: Checklist)
- `frontend/src/lib/certificate-checklist-api.ts`
- `frontend/src/components/calibracao/CertificateEmissionChecklistForm.tsx`
- `frontend/src/pages/calibracao/CalibrationWizardPage.tsx` (update)

### Etapa 8 (Frontend: Rastreabilidade)
- `frontend/src/pages/calibracao/CalibrationWizardPage.tsx` (update)
- Standard weight pages (update)

### Etapa 9 (PWA)
- `frontend/public/sw.js` (update)

### Etapa 10 (Integração)
- `frontend/src/pages/calibracao/CalibrationWizardPage.tsx` (update)
- `frontend/src/pages/tech/TechCertificatePage.tsx` (update)

### Etapa 11 (Testes)
- `tests/Feature/Api/V1/CalibrationCertificateGateTest.php`
- `tests/Feature/Api/V1/Os/MaintenanceReportControllerTest.php` (update)
- `tests/Feature/Api/V1/Os/WorkOrderCriticalAnalysisTest.php`
- `tests/Feature/Api/V1/StandardWeightTraceabilityTest.php`

### Etapa 12 (Quality)
- `database/schema/sqlite-schema.sql` (regenerate)

---

## Dependências entre Etapas

```
Etapa 1 (Permissões) ─────────────────────────────────────┐
Etapa 2 (PDF Template) ──┐                                │
Etapa 3 (Gate Emissão) ──┤ backend completo               │
Etapa 4 (API Resources) ─┘                                │
Etapa 5 (FE: Análise Crítica) ──┐                         │
Etapa 6 (FE: Manutenção) ──────┤ frontend independentes   │
Etapa 7 (FE: Checklist) ───────┤                          │
Etapa 8 (FE: Rastreabilidade) ─┘                          │
Etapa 9 (PWA) ── depende de 6                             │
Etapa 10 (Integração) ── depende de 2,3,5,6,7,8           │
Etapa 11 (Testes) ── depende de 1,2,3,4                   │
Etapa 12 (Quality) ── depende de todas                    │
```

## Estimativa de Commits

12 commits atômicos (1 por etapa), cada um com testes passando antes de avançar.
