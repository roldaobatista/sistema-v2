---
type: compliance_module
domain: hr
title: "Portaria 671 - Ponto"
---
# Conformidade Legal - Portaria 671 / e-Social

> **[AI_RULE]** O Sistema Kalibrium transaciona documentos públicos e impostos trabalhistas. O descumprimento pode fechar empresas.
> **[COMPLIANCE]** Ver documentação técnica do módulo: [HR](../modules/HR.md)

## 1. Controle de Ponto (REP-P Nativo via Web/App) `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] A Lei da Imutabilidade do Ponto**
> O módulo `HR` armazena o model `TimeClockEntry` (batidas de ponto geolocalizadas via biometria/totem em plataforma Web ou App Nativo). É um **CRIME** apagar, alterar o timestamp, ou ocultar uma batida real recebida no servidor.
> **Comportamento da IA Exigido:**
>
> - A Route/Controller de exclusão/update nativo é **BANIDA**.
> - Para corrigir um ponto errado (ex: bateu correndo duas vezes), o usuário submete um `TimeClockAdjustment` referenciando o `TimeClockEntry` com o campo ENUM `reason`. Ambos os registros permanecem vivos para auditoria do Fiscal do Trabalho.

## 2. E-Social (Consolidação em Bloco)

Eventos não podem ser fragmentados por edição manual do BD. Qualquer cálculo de pagamento, comissão de CRM ou insalubridade de um técnico no `WorkOrder` que caia no contracheque (`Payroll`) deve persistir nativamente o payload XML gerado, travando um Lock Digital (`ESocialEvent`). Se rejeitado pela Receita Federal, apenas estornos (`reversals`) são aplicáveis.

### 2.1 Eventos eSocial Suportados

| Evento | Descrição | Trigger no Sistema |
|---|---|---|
| **S-1000** | Informações do Empregador | Cadastro/atualização do tenant (dados da empresa) |
| **S-2200** | Cadastramento Inicial do Vínculo | Admissão de funcionário (`Employee.create()`) |
| **S-2230** | Afastamento Temporário | Registro de férias, licenças, atestados via `TimeClockAdjustment` com reason de afastamento |
| **S-2299** | Desligamento | Rescisão contratual (`Rescission.create()`) |
| **S-1200** | Remuneração | Fechamento de folha (`Payroll.close()`) |

- Cada evento gera um `ESocialEvent` com payload XML, status de envio e protocolo de retorno.
- Lote de eventos é consolidado mensalmente e enviado ao eSocial via API gov.br.
- Eventos rejeitados geram notificação ao RH e ficam em status `rejected` para correção e reenvio.

## 3. Registro Eletrônico de Ponto (REP-P) `[AI_RULE_CRITICAL]`

O Kalibrium implementa REP-P (Registrador Eletrônico de Ponto via Programa) conforme Art. 75-81 da Portaria 671/2021.

### 3.1 Requisitos de Captura (GPS + Selfie)

- Toda batida de ponto (`TimeClockEntry`) exige **obrigatoriamente**:
  - `latitude` e `longitude` — coordenadas GPS do dispositivo no momento da batida
  - `selfie_path` — foto do colaborador para validação biométrica/visual
  - `ip_address` — IP do dispositivo
  - `device_info` — user-agent / identificação do dispositivo
- Batidas sem GPS ou selfie são **rejeitadas** pelo `TimeClockService` com erro 422.

### 3.2 Validação de Geolocalização (`LocationValidationService`)

- O sistema valida se a batida ocorreu dentro do raio permitido do local de trabalho.
- Cada `Employee` possui `work_locations` cadastrados com coordenadas e raio de tolerância (metros).
- Cálculo via fórmula de Haversine: distância entre ponto GPS da batida e centro do local de trabalho.
- Batidas fora do raio são registradas com flag `location_valid = false` e geram alerta ao gestor.
- Raio padrão configurável em `config/hr.php` → `default_geofence_radius`.

### 3.3 Requisitos REP-P Detalhados (Portaria 671, Art. 75-81) `[AI_RULE_CRITICAL]`

| Requisito REP-P | Artigo | Campo/Feature no Sistema | Obrigatório |
|---|---|---|---|
| Identificação do empregador (CNPJ/CPF) | Art. 76, I | `Tenant.cnpj` | Sim |
| Identificação do trabalhador (CPF/PIS) | Art. 76, II | `Employee.cpf`, `Employee.pis` | Sim |
| Data e hora da marcação | Art. 76, III | `TimeClockEntry.clock_time` (UTC com timezone) | Sim |
| NSR (Número Sequencial de Registro) | Art. 76, IV | `TimeClockEntry.nsr` — auto-increment por tenant | Sim |
| Comprovante de registro ao trabalhador | Art. 78 | PDF/push notification com dados da batida | Sim |
| Impossibilidade de alteração de dados | Art. 77, I | Imutabilidade do `TimeClockEntry` — sem UPDATE/DELETE | Sim |
| Impossibilidade de restrição de horário | Art. 77, II | Sistema aceita batida 24h sem bloqueio de horário | Sim |
| Geração do AFD | Art. 79 | `AFDExportService` com hash chain | Sim |
| Disponibilidade do AFD ao fiscal | Art. 80 | Exportação sob demanda com verificação de integridade | Sim |
| Certificação no INPI | Art. 75, §2° | Registro do software no INPI (processo administrativo) | Sim |

> **[AI_RULE_CRITICAL]** O sistema NUNCA pode bloquear ou restringir o horário de uma batida. O Art. 77, II da Portaria 671 proíbe expressamente qualquer mecanismo que impeça o registro de ponto em determinado horário.

## 4. Detecção de Violações CLT (`CltViolationService`)

O sistema detecta automaticamente violações trabalhistas conforme a CLT:

| Violação | Regra CLT | Detecção |
|---|---|---|
| **Interjornada** | Art. 66 — mínimo 11h entre jornadas | Compara `clock_out` de um dia com `clock_in` do dia seguinte |
| **Intrajornada** | Art. 71 — intervalo mínimo de 1h para jornada > 6h | Verifica tempo entre batidas de saída e retorno do almoço |
| **Hora Extra excedida** | Art. 59 — máximo 2h extras/dia | Calcula horas trabalhadas além da jornada contratual |
| **Trabalho noturno irregular** | Art. 73 — período 22h-05h com adicional | Identifica batidas no período noturno sem adicional configurado |
| **Menor sem intervalo** | Art. 413 — proibido hora extra para menor | Verifica idade do colaborador e jornada |

- Violações detectadas são salvas em `CltViolation` com tipo, severidade e referência à `TimeClockEntry`.
- Dashboard de violações exibe resumo por período, colaborador e tipo.
- Violações geram notificações automáticas ao gestor de RH.

## 5. AFD — Arquivo Fonte de Dados `[AI_RULE_CRITICAL]`

### 5.1 Exportação com Hash Chain

- O AFD é o arquivo legal exigido pela Portaria 671 contendo todas as batidas de ponto.
- Cada linha do AFD recebe um hash SHA-256 encadeado (hash chain): `hash_n = SHA256(hash_n-1 + dados_linha_n)`.
- O `HashChainService` garante integridade e imutabilidade — qualquer adulteração quebra a cadeia.
- Formato de exportação conforme layout oficial do MTE (Ministério do Trabalho e Emprego).
- Exportação disponível por período, com validação de integridade antes do download.

### 5.2 Especificação Completa do Formato AFD `[AI_RULE_CRITICAL]`

O AFD segue o layout definido pela Portaria 671/2021 e Portaria 1.510/2009 (complementar). Cada linha representa um registro com campos de tamanho fixo:

#### Registro Tipo 1 — Cabeçalho (Identificação do Empregador)

| Posição | Tamanho | Tipo | Campo | Descrição |
|---|---|---|---|---|
| 01-01 | 1 | N | Tipo | Fixo "1" |
| 02-02 | 1 | N | Tipo Identificador | 1=CNPJ, 2=CPF |
| 03-16 | 14 | N | CNPJ/CPF | Número do documento (zeros à esquerda) |
| 17-30 | 14 | N | CEI | Número do CEI (zeros se não houver) |
| 31-80 | 50 | A | Razão Social | Nome do empregador |
| 81-89 | 9 | N | NSR Primeiro | NSR do primeiro registro no arquivo |
| 90-98 | 9 | N | NSR Último | NSR do último registro no arquivo |
| 99-106 | 8 | N | Data Início | Data inicial (DDMMAAAA) |
| 107-114 | 8 | N | Data Fim | Data final (DDMMAAAA) |
| 115-118 | 4 | N | Hora Geração | Hora de geração do AFD (HHMM) |
| 119-126 | 8 | N | Data Geração | Data de geração do AFD (DDMMAAAA) |

#### Registro Tipo 2 — Marcação de Ponto (Batida)

| Posição | Tamanho | Tipo | Campo | Descrição |
|---|---|---|---|---|
| 01-01 | 1 | N | Tipo | Fixo "2" |
| 02-10 | 9 | N | NSR | Número Sequencial de Registro |
| 11-18 | 8 | N | Data | Data da marcação (DDMMAAAA) |
| 19-22 | 4 | N | Hora | Hora da marcação (HHMM) |
| 23-34 | 12 | N | PIS | Número do PIS do trabalhador |
| 35-46 | 12 | N | CPF | CPF do trabalhador (zeros à esquerda) |
| 47-96 | 50 | A | Nome | Nome do trabalhador |
| 97-160 | 64 | A | Hash | Hash SHA-256 encadeado |

#### Registro Tipo 9 — Trailer (Fim do Arquivo)

| Posição | Tamanho | Tipo | Campo | Descrição |
|---|---|---|---|---|
| 01-01 | 1 | N | Tipo | Fixo "9" |
| 02-10 | 9 | N | Total Registros | Quantidade total de registros tipo 2 |

**Legenda:** N = Numérico, A = Alfanumérico. Campos alfanuméricos preenchidos à direita com espaços; numéricos à esquerda com zeros.

### 5.3 Campos do AFD por Linha (Resumo)

`NSR | Tipo | DataHora | PIS | CPF | NomeEmpregado | Hash`

## 6. Espelho de Ponto (Timesheet Mirror)

- Relatório mensal individual de cada colaborador com todas as batidas, horas trabalhadas, extras, faltas e justificativas.
- Cálculo via `JourneyCalculationService`:
  - Horas normais, horas extras (50%, 100%), adicional noturno (20%)
  - DSR (Descanso Semanal Remunerado) com reflexo conforme TST Súmula 172 e OJ 60
  - Faltas, atrasos, saídas antecipadas
- Espelho disponível para impressão/PDF e assinatura digital do colaborador.
- Após assinatura, o espelho é travado e não pode ser alterado.

## 7. Ajustes de Ponto (`TimeClockAdjustment`)

- Correções de ponto seguem fluxo de aprovação: `pending → approved → rejected`.
- Campos obrigatórios: `time_clock_entry_id`, `reason` (ENUM: forgot_to_clock, system_error, wrong_entry, other), `requested_time`, `justification`.
- O registro original **nunca é alterado** — o ajuste coexiste para auditoria.
- Aprovação exige gestor diferente do solicitante.

## 8. Concorrência e Integridade

- O `TimeClockService` utiliza locks otimistas para evitar batidas duplicadas (debounce de 60 segundos).
- Transactions garantem atomicidade entre criação da `TimeClockEntry` e cálculos derivados.
- Testes de concorrência validam que batidas simultâneas do mesmo colaborador são tratadas corretamente.

---

## 9. Mapeamento Artigo por Artigo: CLT + Portaria 671 → Sistema `[AI_RULE_CRITICAL]`

### 9.1 Artigos da CLT (Decreto-Lei 5.452/1943)

| Artigo CLT | Disposição | Feature no Sistema | Model/Service |
|---|---|---|---|
| **Art. 58** | Jornada normal de 8h diárias / 44h semanais | `JourneyCalculationService` calcula horas trabalhadas vs. jornada contratual | `JourneyCalculationService` |
| **Art. 59** | Hora extra limitada a 2h/dia | `CltViolationService` detecta excesso e gera `CltViolation` | `CltViolationService` |
| **Art. 59-A** | Banco de horas por acordo individual (até 6 meses) | Saldo de banco de horas no `JourneyCalculationService` | `Employee.overtime_bank` |
| **Art. 62** | Exceções ao controle de jornada (gerentes, externos) | Flag `Employee.exempt_from_time_clock` | `Employee` |
| **Art. 66** | Intervalo interjornada mínimo de 11h | `CltViolationService::checkInterJourney()` | `CltViolation` tipo `inter_journey` |
| **Art. 71** | Intervalo intrajornada: 1h-2h para jornada > 6h; 15min para 4h-6h | `CltViolationService::checkIntraJourney()` | `CltViolation` tipo `intra_journey` |
| **Art. 73** | Adicional noturno de 20% (22h-05h); hora noturna = 52min30s | `JourneyCalculationService::calculateNightShift()` | `TimeClockEntry` |
| **Art. 74** | Obrigatoriedade de registro de ponto para empresas > 20 empregados | Módulo HR com REP-P obrigatório | `TimeClockEntry` |
| **Art. 74, §2°** | Registro de ponto por exceção (acordo coletivo) | Flag `Tenant.time_clock_mode = 'exception'` | `TenantSetting` |
| **Art. 413** | Proibição de hora extra para menor de 18 anos | `CltViolationService::checkMinorOvertime()` | `CltViolation` tipo `minor_overtime` |
| **Art. 384** | Intervalo de 15min antes de hora extra para mulheres (revogado, mas monitorado) | Flag configurável no tenant | `TenantSetting` |

### 9.2 Artigos da Portaria 671/2021 (MTP)

| Artigo Portaria 671 | Disposição | Feature no Sistema | Model/Service |
|---|---|---|---|
| **Art. 73** | Definição dos tipos de REP (REP-C, REP-A, REP-P) | Sistema implementa REP-P (programa) | `TimeClockEntry` |
| **Art. 74** | REP-P deve emitir comprovante de registro | Push notification + PDF da batida | `TimeClockService` |
| **Art. 75** | REP-P deve ser registrado no INPI | Processo administrativo externo ao sistema | N/A |
| **Art. 75, §1°** | REP-P deve utilizar certificado digital (ICP-Brasil) | Assinatura digital no AFD exportado | `AFDExportService` |
| **Art. 76** | Dados obrigatórios do registro: empregador, trabalhador, data/hora, NSR | Todos os campos presentes no `TimeClockEntry` | `TimeClockEntry` |
| **Art. 77, I** | Proibido alterar ou eliminar dados registrados | `TimeClockEntry` é imutável — sem UPDATE/DELETE | `TimeClockService` |
| **Art. 77, II** | Proibido restringir horário de marcação | Sistema aceita batida a qualquer hora, 24/7 | `TimeClockService` |
| **Art. 77, III** | Proibido exigir autorização prévia para hora extra | Registro aceito independente de autorização | `TimeClockService` |
| **Art. 78** | Comprovante de registro com: empregador, local, data/hora, PIS/CPF, NSR | Comprovante gerado automaticamente com todos os campos | `TimeClockService` |
| **Art. 79** | Geração do AFD sob demanda | `AFDExportService::export()` com hash chain | `AFDExportService` |
| **Art. 80** | AFD disponível ao Auditor-Fiscal a qualquer momento | Endpoint de exportação sem restrição | `AFDExportController` |
| **Art. 81** | Prazo de guarda de 5 anos | Retenção configurável, padrão 5 anos, sem exclusão física | `TimeClockEntry` (soft delete disabled) |

## 10. Penalidades por Descumprimento `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** O descumprimento das normas de registro de ponto e jornada pode resultar em multas administrativas, ações trabalhistas e até responsabilização criminal.

### 10.1 Multas Administrativas (Atualização 2024/2025)

| Infração | Base Legal | Multa por Empregado | Multa por Reincidência |
|---|---|---|---|
| Falta de registro de ponto (empresa > 20 empregados) | Art. 74 CLT + Art. 75 Portaria 671 | R$ 800,00 a R$ 3.000,00 | Valor dobrado |
| Adulteração de registro de ponto | Art. 77 Portaria 671 + Art. 297 CP | R$ 3.000,00 a R$ 6.000,00 + responsabilização criminal | N/A (crime) |
| Não pagamento de horas extras | Art. 59 CLT | R$ 170,26 por empregado (NR-28) | Valor dobrado |
| Descumprimento de intervalo intrajornada | Art. 71 CLT | Pagamento do intervalo suprimido como hora extra + 50% | N/A |
| Descumprimento de intervalo interjornada | Art. 66 CLT | Pagamento como hora extra das horas suprimidas | N/A |
| Ausência de AFD disponível ao fiscal | Art. 79-80 Portaria 671 | R$ 1.500,00 a R$ 4.500,00 | Valor dobrado |
| Trabalho noturno sem adicional | Art. 73 CLT | R$ 170,26 por empregado | Valor dobrado |
| Hora extra para menor de idade | Art. 413 CLT | R$ 800,00 por infração + interdição | Valor dobrado |

### 10.2 Consequências Adicionais

- **Ação Civil Pública:** O Ministério Público do Trabalho pode ajuizar ação coletiva com danos morais coletivos (valores de R$ 50.000 a R$ 500.000+).
- **Reclamatória Trabalhista:** Empregados podem pleitear horas extras não pagas dos últimos 5 anos, com juros e correção monetária.
- **Responsabilização Criminal:** Adulteração de registro de ponto configura crime de falsidade ideológica (Art. 299 do Código Penal) — pena de 1 a 5 anos de reclusão.
- **Interdição:** Trabalho irregular de menores pode resultar em interdição da atividade pelo MTE.

## 11. Mapeamento de Implementação (Implementation Mapping)

### 11.1 S-2200 — Field Mapping e Retry `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** Todos os eventos eSocial DEVEM ter lógica de retry com backoff exponencial. Evento que falha na transmissão NUNCA pode ser silenciosamente descartado.

#### Mapeamento de Campos S-2200 (Cadastramento Inicial do Vínculo)

| Campo eSocial | Campo no Sistema | Tabela | Obrigatório |
|---|---|---|---|
| `cpfTrab` | `cpf` | `employees` | Sim |
| `nmTrab` | `name` | `employees` | Sim |
| `sexo` | `gender` | `employees` | Sim |
| `racaCor` | `ethnicity` | `employees` | Sim |
| `estCiv` | `marital_status` | `employees` | Sim |
| `grauInstr` | `education_level` | `employees` | Sim |
| `nmSoc` | `social_name` | `employees` | Não |
| `dtNascto` | `birth_date` | `employees` | Sim |
| `paisNascto` | `birth_country` | `employees` | Sim |
| `paisNac` | `nationality_country` | `employees` | Sim |
| `endereco` | `address_*` (street, number, city, state, zip) | `employees` | Sim |
| `matricula` | `registration_number` | `employees` | Sim |
| `dtAdm` | `admission_date` | `employees` | Sim |
| `tpRegJor` | `journey_type` | `employees` | Sim |
| `dtBase` | `base_date` | `employees` | Sim |
| `cnpjSindCategProf` | `union_cnpj` | `employees` | Sim |

#### Retry Logic

| Camada | Artefato | Detalhes |
|---|---|---|
| Job | `TransmitESocialEvent` | `$tries = 5`, backoff exponencial: `[30, 120, 480, 1920, 7680]` segundos. Usa `Illuminate\Bus\Queueable` com `$backoff` array |
| Status Flow | `ESocialEvent.status` | `pending → transmitting → accepted` (sucesso) ou `pending → transmitting → rejected` (falha tratável) ou `pending → transmitting → failed` (esgotou retries) |
| Fallback | Notificação ao RH | Após esgotar os 5 retries, dispara `ESocialTransmissionFailedNotification` para o gestor de RH com detalhes do erro |
| Teste | `ESocialTransmissionTest` | Valida: transmissão com sucesso, retry após falha, backoff exponencial, notificação após falha final |

### 11.2 S-2299 — Payment Guard `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** É PROIBIDO liberar pagamento de rescisão até que o evento S-2299 tenha sido aceito pelo eSocial (`status = 'accepted'`). Pagamento sem aceitação do S-2299 configura irregularidade fiscal.

| Camada | Artefato | Detalhes |
|---|---|---|
| Service | `TerminationPaymentService::canPay()` | Verifica se existe `ESocialEvent` do tipo `S-2299` vinculado à `Rescission` com `status = 'accepted'`. Retorna `{ can_pay: bool, reason: string, event_status: string }` |
| Guard | Middleware no pagamento | `PayTerminationController@store` chama `TerminationPaymentService::canPay()` antes de processar. Retorna 422 com mensagem explicativa se bloqueado |
| Fluxo | Rescisão → S-2299 → Pagamento | (1) `Rescission.create()` gera `ESocialEvent` S-2299, (2) `TransmitESocialEvent` job transmite, (3) Só após `accepted` o pagamento é liberado |
| Teste | `TerminationPaymentTest` | Valida: bloqueio de pagamento com S-2299 pendente, bloqueio com S-2299 rejected, liberação após accepted |

### 11.3 AFD Hash Chain `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** Registros AFD são IMUTÁVEIS. Qualquer tentativa de UPDATE ou DELETE em `AfdRecord` DEVE lançar exceção. A cadeia de hash garante que adulteração seja detectável.

| Camada | Artefato | Detalhes |
|---|---|---|
| Model | `AfdRecord` | Campos: `sequence_number` (int, auto-increment por tenant), `content` (text, dados da linha AFD), `hash` (string, SHA-256 calculado), `previous_hash` (string, hash do registro anterior) |
| Algoritmo | SHA-256 encadeado | `hash = SHA-256(sequence_number + content + previous_hash)`. O primeiro registro (genesis) usa `previous_hash = "GENESIS"` |
| Service | `AfdHashChainService::validate()` | Percorre todos os registros sequencialmente, recalcula cada hash e compara com o armazenado. Retorna `{ valid: bool, broken_at_sequence: int\|null, total_records: int }` |
| Scheduled Job | `ValidateAfdIntegrity` | Executa diariamente via `schedule:run`. Chama `AfdHashChainService::validate()` para cada tenant ativo. Dispara `AfdIntegrityViolationNotification` (crítica) se a cadeia estiver quebrada |
| Imutabilidade | Model boot | `AfdRecord::updating()` e `AfdRecord::deleting()` lançam `ImmutableRecordException` — registros AFD NUNCA podem ser alterados ou removidos |
| Teste | `AfdHashChainTest` | Valida: geração correta do hash genesis, encadeamento correto, detecção de adulteração, imutabilidade do model, validação diária |

### 11.4 Validação de Geofence `[AI_RULE]`

> **[AI_RULE]** Ponto registrado fora do geofence é SINALIZADO (flagged), nunca rejeitado — exceto em caso de spoofing detectado. O Art. 77, II da Portaria 671 proíbe rejeitar batida de ponto.

| Camada | Artefato | Detalhes |
|---|---|---|
| Config | `config/hr.php` → `geofence_radius_meters` | Valor padrão: 150 metros. Configurável por tenant via `TenantSetting::get('geofence_radius_meters')` |
| Service | `GeolocationService::isWithinGeofence()` | Calcula distância via fórmula de Haversine entre coordenadas da batida e `work_locations` do colaborador. Retorna `{ within: bool, distance_meters: float, nearest_location: string }` |
| Comportamento | Flag, não rejeição | Se `within = false`: registra a batida normalmente, seta `TimeClockEntry.location_valid = false`, gera alerta ao gestor. Se detectar spoofing (GPS mock): registra com `TimeClockEntry.spoofing_detected = true` e notifica RH imediatamente |
| Spoofing | Detecção | Verifica `device_info` por indicadores de GPS mock (mock location provider, velocidade impossível entre batidas consecutivas) |
| Teste | `GeofenceValidationTest` | Valida: batida dentro do raio aceita sem flag, batida fora do raio aceita com flag, detecção de spoofing, configuração de raio por tenant |

### 11.5 Tabela Completa de Eventos eSocial `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** Todos os eventos eSocial DEVEM implementar retry com backoff exponencial via `TransmitESocialEvent` job. Evento sem transmissão confirmada DEVE bloquear processos dependentes.

| Evento | Descrição | Trigger no Sistema | Service | Status Flow | Retry | Processo Bloqueado se Pendente |
|---|---|---|---|---|---|---|
| **S-1000** | Informações do Empregador | `Tenant.create()` ou `Tenant.update()` (dados fiscais) | `ESocialEmployerService::transmit()` | `pending → transmitting → accepted/rejected` | `$tries=5`, backoff `[30, 120, 480, 1920, 7680]`s | Todos os demais eventos (S-1000 é pré-requisito) |
| **S-2200** | Cadastramento Inicial do Vínculo | `Employee.create()` (admissão) | `ESocialAdmissionService::transmit()` | `pending → transmitting → accepted/rejected` | `$tries=5`, backoff `[30, 120, 480, 1920, 7680]`s | Folha de pagamento do empregado |
| **S-2230** | Afastamento Temporário | `TimeClockAdjustment.create()` com reason de afastamento (férias, licença, atestado) | `ESocialLeaveService::transmit()` | `pending → transmitting → accepted/rejected` | `$tries=5`, backoff `[30, 120, 480, 1920, 7680]`s | Cálculo de férias/benefícios do período |
| **S-2299** | Desligamento | `Rescission.create()` (rescisão contratual) | `ESocialTerminationService::transmit()` | `pending → transmitting → accepted/rejected` | `$tries=5`, backoff `[30, 120, 480, 1920, 7680]`s | **Pagamento de rescisão** (ver §11.2) |
| **S-2210** | Comunicação de Acidente de Trabalho (CAT) | `WorkAccident.create()` (registro de acidente) | `ESocialAccidentService::transmit()` | `pending → transmitting → accepted/rejected` | `$tries=5`, backoff `[30, 120, 480, 1920, 7680]`s | Benefícios previdenciários do acidentado |

**Observações gerais:**

- Todos os eventos geram um `ESocialEvent` com `payload` XML, `status`, `protocol` (protocolo de retorno do governo) e `response_xml`.
- Eventos rejeitados geram `ESocialRejectionNotification` ao RH com código de erro e orientação de correção.
- Após correção manual, o evento pode ser reenviado via `ESocialEvent::retry()`.
- Lote mensal consolidado via `ConsolidateESocialBatch` job antes do envio ao eSocial.

### 11.6 Contingência eSocial e Múltiplos Vínculos `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]**
> - **Falha Catastrófica do Governo:** Se a API do eSocial (gov.br) ficar fora do ar por >24h (retornando 503/Timeout constante), o sistema entra em modo "Contingência". Os eventos `ESocialEvent` ficam salvos com payload XML localmente, processos dependentes (como Folha e Pagamentos) são desbloqueados mediante override `esocial_contingency = true` (exige log via Admin RH), e o `TransmitESocialEvent` é pausado para não esgotar as tentativas desnecessariamente.
> - **Múltiplos Vínculos Empregatícios:** Colaboradores ou prestadores terceirizados que possuem vínculo simultâneo em outra empresa devem ter a retenção do Teto do INSS tratada proporcionalmente. A parametrização no sistema aceita declaração do valor já recolhido externamente.

---

## 12. Checklist de Verificação de Conformidade

### 12.1 REP-P — Requisitos Técnicos

- [ ] Sistema registra todas as batidas com NSR sequencial — **Onde:** `TimeClockEntry.nsr`
- [ ] Batidas são imutáveis (sem UPDATE/DELETE) — **Onde:** `TimeClockService` + migration sem soft delete
- [ ] Comprovante emitido ao trabalhador a cada registro — **Onde:** Push notification + PDF
- [ ] Sistema não restringe horário de marcação — **Onde:** `TimeClockService` aceita 24/7
- [ ] Sistema não exige autorização prévia para registro — **Onde:** Sem middleware de aprovação no clock-in
- [ ] AFD exportável sob demanda — **Onde:** Menu HR → Exportar AFD
- [ ] AFD com hash chain SHA-256 verificável — **Onde:** `HashChainService`
- [ ] Dados do empregador presentes no cabeçalho do AFD — **Onde:** `AFDExportService` tipo 1
- [ ] GPS e selfie obrigatórios em cada batida — **Onde:** `TimeClockService` validação 422

### 12.2 Jornada de Trabalho — Requisitos Legais

- [ ] Horas extras calculadas corretamente (50% dia útil, 100% domingo/feriado) — **Onde:** `JourneyCalculationService`
- [ ] Adicional noturno de 20% aplicado (22h-05h) — **Onde:** `JourneyCalculationService::calculateNightShift()`
- [ ] Hora noturna reduzida (52min30s) contabilizada — **Onde:** `JourneyCalculationService`
- [ ] Intervalo intrajornada monitorado — **Onde:** `CltViolationService::checkIntraJourney()`
- [ ] Intervalo interjornada monitorado — **Onde:** `CltViolationService::checkInterJourney()`
- [ ] DSR com reflexo de horas extras (TST Súmula 172) — **Onde:** `JourneyCalculationService`
- [ ] Banco de horas com saldo controlado — **Onde:** `Employee.overtime_bank`

### 12.3 eSocial — Eventos Obrigatórios

- [ ] S-1000 gerado no cadastro do empregador — **Onde:** Trigger em `Tenant.create/update`
- [ ] S-2200 gerado na admissão — **Onde:** Trigger em `Employee.create()`
- [ ] S-2230 gerado em afastamentos — **Onde:** Trigger em `TimeClockAdjustment` com reason de afastamento
- [ ] S-2299 gerado no desligamento — **Onde:** Trigger em `Rescission.create()`
- [ ] S-1200 gerado no fechamento de folha — **Onde:** Trigger em `Payroll.close()`
- [ ] Eventos rejeitados tratados com reenvio — **Onde:** `ESocialEvent.status = 'rejected'`
- [ ] XML dos eventos armazenado permanentemente — **Onde:** `ESocialEvent.payload`

### 12.3.1 Verificacao de Rotas eSocial (2026-03-25)

| Endpoint | Status | Detalhes |
|----------|--------|----------|
| `POST /api/v1/hr/esocial/batch/transmit` | `[IMPLEMENTADO]` | Controller: `ESocialBatchController@transmit` — FormRequest: `TransmitESocialBatchRequest` — Middleware: `auth:sanctum, tenant` — Recebe `{event_ids: [1,2,3]}`, consolida XML, envia ao eSocial gov.br, retorna protocolo. Permissao: `hr.esocial.manage`. |
| `GET /api/v1/hr/esocial/batch/{batchId}/status` | `[VERIFICADO: rota existe em routes/api/hr-quality-automation.php]` | Rota existente: `GET /api/v1/hr/esocial/batches/{batchId}` via `ESocialController::checkBatch()`. Nota: o path usa `batches` (plural) e nao `batch` (singular). Permissao: `hr.esocial.view`. |

### 12.3.2 Assinatura Digital ICP-Brasil

- **Extensão PHP:** `openssl` (já incluída no PHP 8.4)
- **Formato:** PKCS#7 (CMS) detached signature
- **Certificado:** Arquivo `.pfx` (PKCS#12) armazenado em `storage/app/certificates/{tenant_id}/`
- **Variáveis de ambiente:** `ESOCIAL_CERT_PATH`, `ESOCIAL_CERT_PASSWORD`
- **Service:** `App\Services\ESocial\DigitalSignatureService`
- **Método:** `sign(string $xmlContent): string` — retorna XML assinado
- **Validação:** Certificado deve ser tipo A1 ou A3, dentro da validade

### 12.3.3 eSocial Web Service (Gov.br)

- **Ambiente Produção:** `https://webservices.producaorestrita.esocial.gov.br/api/v1/`
- **Ambiente Homologação:** `https://webservices.producaorestrita.esocial.gov.br/api/v1/`
- **Autenticação:** Certificado digital ICP-Brasil (mTLS — mutual TLS)
- **Content-Type:** `application/xml`
- **Service:** `App\Services\ESocial\ESocialTransmissionService`
- **Métodos:**
  - `transmitBatch(Collection $events): TransmissionResult`
  - `queryStatus(string $protocolNumber): StatusResult`
  - `downloadReceipt(string $protocolNumber): string`
- **Retry:** 3 tentativas com backoff [30, 120, 600] segundos
- **Circuit Breaker:** Após 5 falhas consecutivas, pausar transmissão por 30 minutos

### 12.4 Ajustes de Ponto — Auditoria

- [ ] Ajustes seguem fluxo de aprovação — **Onde:** `TimeClockAdjustment.status`
- [ ] Registro original preservado junto com o ajuste — **Onde:** `TimeClockEntry` + `TimeClockAdjustment`
- [ ] Aprovador é diferente do solicitante — **Onde:** Validação no `TimeClockAdjustmentService`
- [ ] Motivo e justificativa obrigatórios — **Onde:** `TimeClockAdjustment.reason` + `justification`
- [ ] Trilha de auditoria completa para cada ajuste — **Onde:** `audit_logs`

### 12.5 Espelho de Ponto

- [ ] Espelho mensal gerado para cada colaborador — **Onde:** Menu HR → Espelho de Ponto
- [ ] Todas as batidas, extras, faltas e justificativas incluídas — **Onde:** `JourneyCalculationService`
- [ ] Assinatura digital do colaborador — **Onde:** Fluxo de assinatura no espelho
- [ ] Espelho travado após assinatura — **Onde:** Flag `is_signed` no espelho
- [ ] Disponível para impressão/PDF — **Onde:** Exportação PDF no espelho
