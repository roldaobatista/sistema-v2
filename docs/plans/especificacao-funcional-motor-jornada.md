# Especificacao Funcional Completa — Motor de Jornada Operacional

**Projeto:** Kalibrium ERP
**Data:** 2026-04-09
**Autor:** Rolda
**Status:** Especificacao aprovada pelo stakeholder
**Base Legal:** CLT (DL 5452), Portaria MTP 671/2021, eSocial v S-1.3, LGPD (L13709), ANPD

---

## 1) Objetivo do Modulo

O modulo NAO e um "relogio de ponto". E um **motor de jornada operacional** que une:

- RH
- Agenda tecnica
- OS
- Deslocamento
- Banco de horas
- Viagem
- Despesas
- Folha
- Comissao
- Auditoria

**Base legal:** Portaria 671 exige que o sistema reflita a jornada real, disponibilize comprovante ao trabalhador apos cada marcacao, mantenha espelho de ponto e gere saidas tecnicas proprias. CLT e eSocial exigem tratamento consistente de jornada contratual, compensacao e verbas medidas em horas.

---

## 2) Principios de Arquitetura

### 2.1 Regime por Colaborador

O sistema deve permitir enquadrar cada colaborador em um dos regimes:

- Controle integral de jornada
- Controle por excecao
- Atividade externa incompativel com fixacao de horario (art. 62 CLT)
- Plantao/sobreaviso
- 12x36
- Escala personalizada

**Base legal:** CLT nao trata todos os trabalhadores externos da mesma forma. Alguns permanecem sujeitos a controle, outros podem estar enquadrados na excecao do art. 62.

### 2.2 Jornada Contratual Separada da Jornada Realizada

O sistema deve manter dois blocos distintos:

- **Jornada contratual** (o que o contrato preve)
- **Jornada efetivamente realizada** (o que aconteceu de fato)

**Base legal:** eSocial exige informacao de horario contratual e tipo de jornada. Portaria 671 exige que o registro reflita a jornada real praticada.

### 2.3 Tudo Vinculado a Evidencia

Toda marcacao relevante deve poder se vincular a:

- OS
- Cliente
- Local
- Geolocalizacao
- Dispositivo
- Veiculo
- Comprovante
- Justificativa
- Aprovador
- Trilha de alteracao

**Base legal:** Espelho fiel da jornada, disponibilizacao de comprovantes, arquivos para fiscalizacao e preservacao de integridade/autoria dos documentos eletronicos.

---

## 3) Modulos Internos

### 3.1 Cadastro Funcional

Campos minimos:

- Matricula interna
- Nome, CPF, data de admissao
- Cargo/funcao
- CBO
- Centro de custo
- Gestor
- Filial/base
- Regime de trabalho
- Tipo de jornada
- Escala
- Salario base
- Tipo de comissao
- Sindicato/CCT
- Status do vinculo
- CNH e vencimento
- Dados de contato
- Dados para eSocial
- Observacoes contratuais

**Base legal:** eSocial usa dados cadastrais e contratuais do vinculo, inclusive jornada e salario contratual.

### 3.2 Escalas e Jornadas

Suportar:

- Segunda a sexta
- Segunda a sabado
- 12x36
- Folga variavel
- Turno ininterrupto
- Escalas personalizadas
- Plantao
- Sobreaviso
- Escala por equipe tecnica

**Base legal:** eSocial preve tipologias como 12x36, folga variavel, folga fixa e turno de revezamento.

### 3.3 Ponto Digital

Funcionalidades minimas:

- Entrada
- Saida
- Inicio de intervalo
- Fim de intervalo
- Marcacao extraordinaria
- Comprovante apos cada marcacao
- Consulta de marcacoes recentes
- Consulta do espelho
- Registro offline com sincronizacao posterior
- Assinatura e integridade dos registros

**Base legal:** Portaria 671 exige acesso ao comprovante apos cada marcacao. Disciplina espelho de ponto, AFD, AEJ e assinatura eletronica dos artefatos.

### 3.4 Deslocamento Operacional

Campos e eventos:

- Saida da base
- Inicio do deslocamento
- Chegada ao cliente
- Saida do cliente
- Deslocamento entre clientes
- Retorno a base
- Veiculo
- KM inicial/final
- Pedagio
- Combustivel
- Motorista
- Observacoes

**REGRA CRITICA:** Deslocamento NAO substitui ponto. Dialoga com ele. O objetivo e separar tempo de deslocamento operacional de marcacao formal de jornada. Politica parametrizavel.

### 3.5 Banco de Horas

- Saldo positivo e negativo
- Compensacao automatica e manual
- Vencimento do saldo
- Fechamento por competencia
- Trava por teto diario/semanal
- Historico de origem do saldo
- Aprovacao do gestor
- Baixa por folga
- Baixa por pagamento em folha

**Base legal:** CLT admite compensacao no mes, banco por acordo individual escrito com compensacao em ate 6 meses. Art. 59-B traz regra sobre nulidade. Parametrizar por regime juridico.

### 3.6 Viagens

- Roteiro
- Previsao de saida e retorno
- Pernoite
- Diaria
- Adiantamento
- Prestacao de contas
- Hotel
- Almoco/janta
- Autorizacao de extrapolacao de jornada
- Descanso compensatorio (se politica interna adotar)
- Reembolso

### 3.7 Ocorrencias Trabalhistas

- Falta
- Atraso
- Atestado
- Afastamento
- Ferias
- Folga compensatoria
- Licenca
- Acidente
- Retorno ao trabalho
- Advertencia
- Suspensao

**Base legal:** eSocial possui eventos proprios para afastamentos (S-2230), desligamentos (S-2299), alteracoes contratuais e remuneracao.

### 3.8 Treinamentos, EPI e Habilitacoes

- NR-10, NR-11, NR-12, NR-35 (conforme aplicavel)
- Treinamentos internos
- Reciclagens
- Validade
- Anexos/certificados
- Entrega de EPI
- Ferramenta sob responsabilidade
- **Bloqueio de agenda por habilitacao vencida**

**Base legal:** eSocial v S-1.3 preve informacoes de treinamentos, capacitacoes e exercicios simulados quando a obrigacao decorre das NRs.

---

## 4) Telas Principais

### 4.1 Para o Tecnico

1. Meu dia
2. Bater ponto
3. Iniciar deslocamento
4. Chegada no cliente
5. Pausa/refeicao
6. Retomar atividade
7. Encerrar OS
8. Solicitar correcao de ponto
9. Ver saldo de banco de horas
10. Ver espelho de ponto
11. Enviar comprovantes de despesa
12. Ver escala
13. Ver viagens
14. Ver treinamentos e vencimentos

### 4.2 Para o Gestor Operacional

1. Agenda da equipe
2. Mapa em tempo real
3. Tecnicos em rota
4. Tecnicos em atendimento
5. Pendencias de marcacao
6. Aprovacao de ajustes
7. Aprovacao de horas extras
8. Aprovacao de viagem e despesas
9. Produtividade por tecnico/OS/cliente

### 4.3 Para RH/DP

1. Cadastro do colaborador
2. Jornada contratual
3. Fechamento de ponto
4. Banco de horas
5. Ocorrencias
6. Integracao com folha
7. Exportacoes eSocial
8. Auditoria
9. Relatorios legais
10. Parametrizacao por sindicato/CCT

### 4.4 Para Diretoria

1. Dashboard de horas
2. Custo por OS
3. Rentabilidade por tecnico
4. Hora improdutiva
5. Tempo medio de deslocamento
6. Horas extras por unidade
7. Absenteismo
8. Saldo global de banco de horas

---

## 5) Regras de Negocio Obrigatorias

### 5.1 Marcacao NAO Pode Ser "Cega"

Toda marcacao deve ter contexto:

- Ponto simples
- Ponto vinculado a OS
- Ponto vinculado a viagem
- Ponto vinculado a base
- Ponto por excecao

### 5.2 Chegada em Cliente NAO Fecha Folha Sozinha

Eventos de telemetria, GPS ou check-in de OS podem **sugerir** marcacoes, mas a batida oficial segue a politica configurada da empresa. Evita transformar deslocamento ou check-in operacional em jornada automaticamente.

### 5.3 Ajustes Precisam de Fluxo Formal

Qualquer inclusao, exclusao ou desconsideracao precisa guardar:

- Usuario que alterou
- Data/hora
- Motivo
- Marcacao original
- Marcacao final
- Aprovador
- Evidencia

### 5.4 Intervalo

Suportar:

- Intervalo real
- Pre-assinalacao (quando politica juridica permitir)
- Reducao autorizada por ACT/CCT
- Alerta de nao fruicao

### 5.5 Excecao de Jornada

Parametro por colaborador:

- Jornada integral com marcacao completa
- Marcacao por excecao
- Sem controle de ponto por enquadramento juridico

### 5.6 Fechamento por Competencia

Todo mes congelar:

- Marcacoes
- Ajustes
- Horas aprovadas
- Banco gerado
- Verbas de folha
- Eventos eSocial

---

## 6) Permissoes e Perfis

### Perfis Minimos

- Tecnico
- Lider tecnico
- Supervisor operacional
- RH
- DP/folha
- Financeiro
- Auditor interno
- Administrador
- Diretor

### Permissoes Minimas

- Marcar ponto
- Justificar marcacao
- Aprovar marcacao
- Editar jornada contratual
- Editar banco de horas
- Fechar competencia
- Exportar AFD/AEJ
- Visualizar localizacao
- Ver salario/comissao
- Ver dados sensiveis
- Configurar escalas
- Administrar integracoes

---

## 7) Integracoes

### 7.1 Field Service / OS

- Agenda tecnica, OS, checklist, assinatura do cliente, fotos, pecas, SLA, status do atendimento

### 7.2 Frota e Deslocamento

- Veiculo, rastreador/GPS, KM, abastecimento, pedagio, multas, manutencao

### 7.3 Financeiro

- Adiantamento de viagem, prestacao de contas, reembolso, desconto em folha, integracao com contas a pagar/receber

### 7.4 Folha/eSocial

- Rubricas de HE, adicional noturno, faltas, DSR, banco de horas, comissoes, afastamentos, ferias, admissoes e alteracoes contratuais

### 7.5 Ponto Eletronico Oficial (REP-P)

Se Kalibrium for o proprio programa de ponto:

- Registro de programa no INPI
- Atestado Tecnico e Termo de Responsabilidade
- Comprovante eletronico assinado (PAdES)
- AFD
- AEJ
- Assinaturas CAdES destacado

**Base legal:** Portaria 671 e explicita: REP-P deve possuir certificado de registro no INPI; empregador so usa se possuir Atestado Tecnico e Termo de Responsabilidade.

---

## 8) Modelo de Dados Minimo

### Entidades Principais

1. `employee` — cadastro funcional
2. `employee_contract` — dados contratuais (jornada, salario, regime)
3. `employee_schedule` — escala do colaborador
4. `employee_shift` — turno especifico
5. `time_punch` — batida de ponto
6. `time_punch_event` — evento associado a batida
7. `time_adjustment_request` — solicitacao de ajuste
8. `time_approval` — aprovacao de marcacao/ajuste
9. `timesheet_month` — fechamento mensal (competencia)
10. `bank_hours_ledger` — ledger de banco de horas
11. `bank_hours_rule` — regras de banco por regime/CCT
12. `field_trip` — viagem de campo
13. `field_trip_expense` — despesa de viagem
14. `travel_advance` — adiantamento
15. `vehicle_usage` — uso de veiculo
16. `work_order_time_link` — vinculo OS ↔ tempo
17. `location_ping` — ping de geolocalizacao
18. `attendance_occurrence` — ocorrencia trabalhista
19. `leave_record` — registro de licenca/ferias
20. `training_record` — treinamento
21. `certificate_record` — certificacao/habilitacao
22. `equipment_assignment` — entrega de EPI/ferramenta
23. `payroll_export_item` — item de exportacao para folha
24. `esocial_contract_snapshot` — snapshot contratual eSocial
25. `esocial_remuneration_snapshot` — snapshot remuneracao eSocial
26. `audit_log` — log de auditoria
27. `digital_receipt` — comprovante digital

### Campos Criticos em `time_punch`

- employee_id
- company_id (tenant_id)
- branch_id
- occurred_at
- recorded_at
- timezone
- source (app/web/api/import)
- mode (online/offline)
- punch_type
- geo_lat / geo_lng / accuracy
- device_id
- work_order_id
- field_trip_id
- vehicle_id
- is_treated
- treated_reason
- original_hash
- signed_receipt_id

### Campos Criticos em `bank_hours_ledger`

- employee_id
- competence
- origin_type
- origin_ref_id
- minutes_delta
- legal_basis
- approval_status
- expires_at
- settled_by
- settled_at

---

## 9) Relatorios Obrigatorios

1. Espelho de ponto por periodo
2. Marcacoes brutas
3. Marcacoes tratadas
4. Banco de horas por colaborador
5. Banco de horas por centro de custo
6. Horas extras por OS
7. Horas em deslocamento
8. Tempo de espera em cliente
9. Produtividade por tecnico
10. Custo de mao de obra por OS
11. Inconsistencias de marcacao
12. Colaboradores sem batida
13. Treinamentos vencidos
14. Ocorrencias trabalhistas
15. Exportacoes para folha
16. AFD (Arquivo Fonte de Dados)
17. AEJ (Arquivo Eletronico de Jornada)

**Base legal:** Portaria 671 exige, no minimo, espelho de ponto eletronico, AFD, AEJ e disponibilizacao dos arquivos/relatorios a fiscalizacao.

---

## 10) Requisitos Mobile Obrigatorios

- Funcionar offline
- Guardar fila local criptografada
- Impedir perda de marcacao sem sincronizacao
- Mostrar comprovante apos cada registro
- Permitir justificativa com foto/anexo
- Capturar localizacao com precisao registrada
- Registrar versao do app e do dispositivo
- Suportar multiplas empresas/filiais por usuario (se houver)

**Base legal:** Comprovante apos cada marcacao e exigencia expressa da Portaria 671. Modo offline e requisito operacional para tecnico em campo.

---

## 11) LGPD e Biometria

Se usar selfie, reconhecimento facial, impressao digital ou voz:

- Base legal definida por funcionalidade
- Minimizacao de coleta
- Retencao configuravel
- Criptografia em repouso e transito
- Mascaramento
- Segregacao de acesso
- Logs de acesso
- Descarte seguro
- Opcao de desligar biometria por empresa/politica

**Base legal:** LGPD classifica dados biometricos como sensiveis. ANPD destaca que biometria exige cuidados especificos.

---

## 12) Fluxo Ideal do Tecnico em Campo

1. Tecnico abre o app e ve a agenda do dia
2. Bate entrada
3. Inicia deslocamento para a OS
4. Faz check-in no cliente
5. Executa servico
6. Registra pausas reais
7. Fecha OS
8. Retoma deslocamento ou encerra jornada
9. Envia despesas/adiantamentos
10. Gestor aprova excecoes
11. RH fecha competencia
12. Folha e eSocial recebem dados consolidados

---

## 13) Criterios de Aceite do Modulo

O modulo so e considerado pronto quando:

- [ ] Emitir comprovante por marcacao
- [ ] Gerar espelho de ponto
- [ ] Gerar AFD
- [ ] Gerar AEJ
- [ ] Manter trilha de ajustes
- [ ] Suportar banco de horas configuravel
- [ ] Separar jornada contratual de jornada realizada
- [ ] Integrar marcacoes com OS e deslocamento
- [ ] Exportar horas para folha
- [ ] Suportar enquadramento por regime de jornada
- [ ] Bloquear alocacao de tecnico com treinamento vencido (configuravel)
- [ ] Operar offline no mobile
- [ ] Manter logs auditaveis de ponta a ponta

---

## 14) Camadas de Implementacao (Recomendacao)

### Camada 1 — Nucleo Legal

Ponto, espelho, AFD/AEJ, banco de horas, fechamento, integracoes com folha/eSocial.

### Camada 2 — Operacao de Campo

Deslocamento, check-in em cliente, vinculo com OS, despesas, viagem, produtividade.

### Camada 3 — Inteligencia

Custo real por OS, ranking de produtividade, mapa de jornada, alertas de risco trabalhista, previsao de horas extras, desvio entre agenda e execucao.

---

## Referencias Legais

| Referencia | Link |
|-----------|------|
| CLT (DL 5452) | https://www.planalto.gov.br/ccivil_03/decreto-lei/del5452.htm |
| Lei 13874/2019 (Liberdade Economica) | https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2019/lei/l13874.htm |
| Portaria MTP 671/2021 (Compilada) | https://www.gov.br/trabalho-e-emprego/pt-br/assuntos/legislacao/portarias-1/portarias-vigentes-3/ |
| eSocial MOS v S-1.3 | https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/ |
| eSocial Manual WEB Geral | https://www.gov.br/esocial/pt-br/empresas/manual-web-geral |
| ANPD Biometria | https://www.gov.br/anpd/pt-br/assuntos/noticias/webinario-aborda-tratamento-de-dados-biometricos |
