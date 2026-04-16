# Auditoria PRD Kalibrium v3.0 — 2026-04-06

**Metodo:** Cruzamento PRD v3.0 (2026-04-03) vs Raio-X v2.0 (fonte de verdade, 2026-04-02) vs codigo real.

---

## 1. INCONSISTENCIAS DE DADOS (PRD vs Raio-X vs Codigo)

### 1.1 Versao do Laravel — CONFLITO
| Fonte | Valor |
|-------|-------|
| PRD (Classificacao) | Laravel 12 |
| Raio-X (Stack) | Laravel 12 (PHP 8.3+) |
| CLAUDE.md | **Laravel 13** |
| **Severidade** | **MEDIA** — se o sistema ja usa Laravel 13, o PRD esta desatualizado. Se CLAUDE.md esta errado, corrigir la. |

**Acao:** Verificar `composer.json` e alinhar todos os documentos com a versao real.

### 1.2 Contagem de Testes — DIVERGENCIA
| Fonte | Valor |
|-------|-------|
| PRD (Classificacao) | 8200+ |
| Raio-X (Numeros Gerais) | 7500+ |
| PRD (Risco #10) | 8200+ (8248 reais) |
| CLAUDE.md | 7500+ |

**Acao:** O PRD diz 8200+ baseado na auditoria v1.8. Se testes foram adicionados, o Raio-X e CLAUDE.md estao defasados. Rodar `pest` e atualizar TODOS os documentos com o numero real.

### 1.3 Maturidade Financeiro — CONTRADICAO INTERNA no Raio-X
| Local no Raio-X | Valor |
|-----------------|-------|
| Tabela Resumo Executivo | 🟡 Parcial — 70% |
| Secao Dominio 1 detalhada | 🟢 COMPLETO |

**Acao:** A tabela-resumo do Raio-X contradiz sua propria secao detalhada. Alinhar — provavelmente 🟡 (70%) e o correto, dado que NFS-e e Boleto/PIX nao estao em producao.

### 1.4 Maturidade Global — PRD vs Raio-X
| Fonte | Valor |
|-------|-------|
| PRD (contexto brownfield) | ~80% |
| Raio-X (media dos 8 dominios) | ~77% (media ponderada: 70+85+65+90+85+95+85+85 / 8) |

**Acao:** O PRD arredonda para cima. Com os bloqueadores de go-live (NFS-e, Boleto), a maturidade *operacional real* e menor que 80%. Sugestao: usar "~75-80%" com nota sobre bloqueadores.

---

## 2. FUNCIONALIDADES DOCUMENTADAS MAS NAO IMPLEMENTADAS (ou STUB)

### 2.1 BLOQUEADORES DE GO-LIVE (confirmados pelo Raio-X)

| # | Funcionalidade | Status PRD | Status Real | Gap |
|---|---------------|-----------|-------------|-----|
| 1 | **Emissao NFS-e em producao** | RF-03 documenta fluxo completo | Gateway FocusNFe existe no codigo, mas **requer contrato ativo** — nao funciona sem config | PRD nao explicita que depende de contrato externo |
| 2 | **Boleto Bancario** | RF-19 documenta geracao automatica | **NAO EXISTE integracao com banco/PSP** | PRD descreve como se funcionasse; deveria marcar como 🔴 |
| 3 | **PIX Automatizado** | RF-19 documenta QR code + webhook | **NAO EXISTE integracao com PSP** | Mesmo problema do boleto |

### 2.2 GAPS FUNCIONAIS REAIS (codigo incompleto/stub)

| # | Funcionalidade | PRD RF | Status Real |
|---|---------------|--------|-------------|
| 4 | Deal → Quote (CRM) | RF-21 Pipeline Comercial | **Pipeline desconectado de orcamentos** — Deal existe, Quote existe, mas nao ha conversao automatica |
| 5 | Recurring Billing | Mencionado no escopo | **Placeholder** — gera invoice com valor fixo R$1000.00 |
| 6 | WhatsApp → CRM | RF-21 menciona integracao | Webhook recebe mensagem mas **nao alimenta CrmMessage** |
| 7 | FIFO/FEFO (Estoque) | RF-10 Estoque | Lotes rastreados mas **sem logica de consumo por ordem** |
| 8 | PWA Offline funcional | RF-07 (AC-07.5) | **So cache estatico** — tecnico sem sinal NAO acessa dados/API |
| 9 | eSocial eventos S-2205+ | RF-05 eSocial | S-2200 e S-2299 reais, **S-2205, S-2206, S-2210+ retornam buildStubXml()** |
| 10 | Conciliacao inteligente | RF-03 Financeiro | Matching basico por valor/data, **sem ML/aprendizado** |

---

## 3. FLUXOS DE TRABALHO INCOMPLETOS

### 3.1 Ciclo de Receita Automatizado (RF-19) — FLUXO QUEBRADO
O PRD descreve o fluxo como:
```
OS_COMPLETED → INVOICED → NFSE_ISSUED → PAYMENT_GENERATED → PAID → RECONCILED
```

**Problemas:**
1. `OS_COMPLETED → INVOICED`: Existe no codigo? **Parcial** — precisa de acao manual
2. `INVOICED → NFSE_ISSUED`: **DEPENDE de contrato FocusNFe** — nao automatico hoje
3. `NFSE_ISSUED → PAYMENT_GENERATED`: **NAO IMPLEMENTADO** — nao ha integracao boleto/PIX
4. `PAYMENT_GENERATED → PAID`: **NAO IMPLEMENTADO** — nao ha webhook de gateway
5. `PAID → RECONCILED`: **NAO IMPLEMENTADO** — conciliacao e manual

**Conclusao:** O fluxo principal do produto (a proposta de valor central — "ciclo de receita em minutos") esta documentado no PRD como se funcionasse ponta a ponta, mas **3 dos 5 elos estao ausentes**. Isto e a inconsistencia mais grave do PRD.

### 3.2 Ciclo Comercial — FLUXO PARCIAL
```
Lead → Deal → Quote → Aprovacao → Contrato → Billing → AR
```
- Lead → Deal: 🟢 Funciona
- Deal → Quote: 🔴 **Desconectado** (gap #4)
- Quote → Contrato: 🟡 Existe mas sem automacao
- Contrato → Billing: 🔴 **Placeholder** (gap #5)
- Billing → AR: 🔴 **Depende do billing funcionar**

### 3.3 Portal do Cliente — FUNCIONALIDADE INCOMPLETA
O PRD descreve (AC-06.1 a AC-06.5):
- Ver equipamentos e certificados: 🟢
- Acompanhar OS em tempo real: 🟡 Parcial
- Download certificados: 🟢
- Ver NFs: 🔴 Depende de NFS-e funcionar
- Ver boletos/PIX: 🔴 Depende de integracao cobranca

### 3.4 Dashboard Operacional Unificado — NAO EXISTE
O PRD descreve como funcionalidade core para a persona "Gestor":
- Dashboard consolidado do dia (OS + pagamentos + alertas): **NAO EXISTE** como tela unica
- Existem dashboards por modulo, mas a visao unificada descrita no PRD nao foi implementada

---

## 4. ERROS E INCONSISTENCIAS TEXTUAIS NO PRD

### 4.1 Status de Modulos no Escopo MVP
O PRD marca varios modulos como "🟢 Pronto" na tabela de MVP, mas varios dependem de integracoes externas nao configuradas:

| Modulo | Status PRD | Realidade |
|--------|-----------|-----------|
| Financeiro (NFS-e) | "🟡 Falta integracao producao" | Deveria ser 🔴 — sem contrato, nao funciona |
| Financeiro (Boleto/PIX) | "🔴 Pendente" | Correto, mas PRD nao detalha *o que* precisa ser implementado |
| Ponto Eletronico | "🟢 Pronto" | 🟢 Correto para MVP |
| Portal do Cliente | "🟡" | Deveria notar dependencia de NFS-e e cobranca |

### 4.2 Persona "Financeiro (Pedro)" — Criterio Impossivel Hoje
> "OS faturada gera NFS-e + boleto/PIX automaticamente. Fechamento mensal em < 1 hora"

Este criterio de sucesso e impossivel sem as integracoes de NFS-e e boleto/PIX. O PRD deveria marcar como "meta pos-integracao" ou adicionar nota de dependencia.

### 4.3 "Cancelamento NFS-e completo" no Contexto
O campo Contexto diz: "cancelamento NFS-e completo". O Raio-X confirma que o codigo existe no FocusNFe, mas em producao **depende do contrato ativo**. Sem contrato, cancelamento tambem nao funciona. Sugestao: "cancelamento NFS-e implementado (pendente config producao)".

### 4.4 Tempo do Ciclo de Receita — Claim Nao Verificavel
> "reduzindo o ciclo de receita de dias para minutos"

Sem boleto/PIX automatizado + NFS-e em producao, este claim nao pode ser verificado nem entregue ao primeiro cliente. O resumo executivo promete algo que o sistema ainda nao faz.

---

## 5. FUNCIONALIDADES FALTANDO NO PRD (existem no codigo mas nao documentadas)

### 5.1 Modulos no codigo sem RF correspondente claro

| Funcionalidade no Codigo | Existe no PRD? | Observacao |
|--------------------------|---------------|------------|
| Caixa do Tecnico (TechnicianCashFund) | Mencionado superficialmente | Merece RF proprio — tecnico em campo com dinheiro e cenario real |
| Renegociacao de Dividas (DebtRenegotiation) | AC-03.10 | OK, mas sem fluxo detalhado de aprovacao no PRD |
| Fund Transfer (banco a banco) | Nao encontrado como RF | Funcionalidade existe com audit trail mas nao documentada |
| Webhook Config/Log | AC-19 menciona | OK |

---

## 6. RISCOS NAO DOCUMENTADOS OU SUBDIMENSIONADOS

### 6.1 Riscos Ausentes no PRD

| # | Risco | Severidade | Por que Importa |
|---|-------|-----------|-----------------|
| R13 | **Recurring billing placeholder em producao** | ALTO | Se um cliente configurar cobranca recorrente, vai gerar invoices de R$1000 |
| R14 | **WhatsApp webhook recebendo sem processar** | MEDIO | Mensagens de clientes estao sendo descartadas silenciosamente |
| R15 | **FIFO/FEFO ausente em estoque** | MEDIO | Lotes com validade podem ser consumidos fora de ordem, gerando perda |
| R16 | **Stubs de eSocial em producao** | ALTO | Se alguem tentar transmitir S-2205/S-2206, vai enviar XML vazio/invalido |

### 6.2 Riscos Subdimensionados

| Risco PRD | Severidade PRD | Severidade Real |
|-----------|---------------|-----------------|
| #3 "Tecnico perde dados offline" | Media probabilidade | **ALTA** — PWA offline so tem cache estatico, qualquer acao e perdida |
| #9 "Go-live sem LGPD" | Media probabilidade | OK (LGPD implementada v1.9), mas **RIPD e anonimizacao automatica ainda pendentes** |

---

## 7. REQUISITOS NAO-FUNCIONAIS — GAPS

| RNF | Descricao PRD | Status Real |
|-----|--------------|-------------|
| RNF-03 Infra | Deploy documentado | 🟢 OK (deploy/DEPLOY.md) |
| RNF-05 WCAG | Plano axe-core + NVDA | 🔴 **Nao implementado** — nenhuma evidencia de testes de acessibilidade |
| RNF-07 Observabilidade | 7 metricas definidas | 🟡 Parcial — RF-08.5 corrigido para 🟡, mas metricas nao tem dashboard unificado |
| Performance < 3s | Dashboard carrega em < 3s | 🟡 **Nao verificado** — sem load tests documentados |

---

## 8. SUMARIO EXECUTIVO

### Por Gravidade

| Severidade | Qtd | Itens |
|-----------|-----|-------|
| 🔴 CRITICO | 4 | Ciclo receita quebrado (RF-19), Boleto/PIX inexistente, eSocial stubs em producao, Recurring billing placeholder |
| 🟠 ALTO | 5 | NFS-e depende contrato, Deal→Quote desconectado, PWA offline incompleto, Dashboard unificado ausente, WCAG nao implementado |
| 🟡 MEDIO | 6 | Versao Laravel inconsistente, contagem testes divergente, maturidade financeiro contraditoria, WhatsApp→CRM, FIFO/FEFO, claim "minutos" inverificavel |
| 🔵 BAIXO | 3 | Fund Transfer nao documentado, Caixa Tecnico subdocumentado, maturidade arredondada |

### Top 3 Acoes Prioritarias

1. **Corrigir o PRD para refletir que o Ciclo de Receita (RF-19) NAO funciona ponta a ponta.** A proposta de valor central do produto depende de 3 integracoes ausentes. O resumo executivo promete "minutos" mas o sistema entrega "manual".

2. **Alinhar versoes e numeros em TODOS os documentos** (Laravel 12 vs 13, testes 7500 vs 8200, maturidade 70% vs Completo no financeiro). Documentos contraditórios geram confusao e decisoes erradas.

3. **Formalizar os stubs/placeholders como "NAO IMPLEMENTADO"** no PRD (eSocial S-2205+, Recurring Billing, WhatsApp→CRM). Marcar como 🔴 com nota "stub — NAO usar em producao" para evitar que um deploy acidental exponha funcionalidade quebrada.

---

---

## 9. STATUS DAS CORRECOES (aplicadas em 2026-04-06)

Todas as correcoes documentais foram aplicadas nesta mesma data:

| Correcao | Arquivo(s) | Status |
|----------|-----------|--------|
| Stack Laravel 12→13 | PRD, Raio-X, CLAUDE.md, ARQUITETURA.md, 00-introducao.md, CURRENT_STATE.md, deploy-completo.md, BLUEPRINT-AIDD.md | ✅ Corrigido |
| Testes 7500/8200→8385 | PRD (4 refs), Raio-X (2 refs), CLAUDE.md | ✅ Corrigido |
| Tempo testes <3min→<5min | PRD (2 refs), CLAUDE.md | ✅ Corrigido |
| Financeiro contradicao 🟢→🟡 70% | Raio-X secao Dominio 1 | ✅ Corrigido |
| Ciclo Receita status real | PRD RF-19.3-19.10, Raio-X fluxo | ✅ Corrigido |
| Ciclo Comercial status real | Raio-X fluxo | ✅ Corrigido |
| Boleto/PIX 🟡→🔴 | PRD tabela MVP, RF-19.4 | ✅ Corrigido |
| PWA offline status real | PRD tabela MVP | ✅ Corrigido |
| Dashboard unificado 🟢→🟡 | PRD tabela MVP | ✅ Corrigido |
| Portal dependencias | PRD tabela MVP | ✅ Corrigido |
| Resumo executivo claim realista | PRD resumo | ✅ Corrigido |
| Indicador chave com nota | PRD criterios sucesso | ✅ Corrigido |
| Momento "aha" como meta | PRD criterios sucesso | ✅ Corrigido |
| Compliance com notas de status | PRD sucesso tecnico | ✅ Corrigido |
| Maturidade ~80%→~75-80% | PRD contexto | ✅ Corrigido |
| eSocial stubs formalizados | PRD pos-MVP, Raio-X gaps | ✅ Corrigido |
| Recurring Billing placeholder | PRD pos-MVP, Raio-X gaps | ✅ Corrigido |
| +4 riscos (#17-20) | PRD analise de riscos | ✅ Corrigido |
| Risco #3 probabilidade corrigida | PRD analise de riscos | ✅ Corrigido |
| Risco #9 probabilidade corrigida | PRD analise de riscos | ✅ Corrigido |
| Numeros reais no Raio-X | Raio-X numeros gerais | ✅ Corrigido |
| Acoes imediatas atualizadas | Raio-X acoes | ✅ Corrigido |
| Changelog v3.1 | PRD, PRD-CHANGELOG | ✅ Corrigido |

**Itens que NAO sao correcao documental (requerem implementacao de codigo):**
- Boleto/PIX integracao com Asaas
- Webhook de baixa automatica
- Dashboard operacional unificado
- Deal→Quote conversion
- PWA offline funcional
- eSocial eventos reais (S-2205+)
- FIFO/FEFO logica de consumo
- WhatsApp→CRM integracao
- Recurring Billing valores reais
- WCAG testes de acessibilidade

*Auditoria realizada em 2026-04-06. Correcoes documentais aplicadas na mesma data. Proxima revisao recomendada: apos resolver os bloqueadores de go-live (NFS-e contrato + Boleto/PIX codigo).*
