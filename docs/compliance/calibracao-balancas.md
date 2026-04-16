# Calibração de Balanças (IPNA) — Requisitos Normativos e Operacionais

> Documento de referência para o módulo de Ordem de Serviço e Certificado de Calibração do Kalibrium ERP.
> Aplicável a: Balanças Solution e empresas do segmento de calibração de instrumentos de pesagem não automáticos (IPNA).

---

## 1. Base Legal e Normativa

### 1.1 Metrologia Legal (Instrumento e Uso Regulado)

| Documento | Descrição |
|-----------|-----------|
| Lei nº 5.966/1973 | Cria o Sinmetro e o Inmetro |
| Lei nº 9.933/1999 | Consolida competências do Conmetro e do Inmetro |
| Resolução Conmetro nº 8/2016 | Diretrizes para metrologia legal no país |
| **Portaria Inmetro nº 157/2022** | **RTM consolidado para IPNA — referência principal** |
| Portarias de aprovação de modelo | Atos específicos por modelo de balança |

### 1.2 Acreditação RBC/Cgcre (Laboratório e Certificado)

| Documento | Descrição |
|-----------|-----------|
| **ABNT NBR ISO/IEC 17025:2017** | **Competência de laboratórios de ensaio e calibração** |
| NIT-DICLA-021 | Expressão da incerteza de medição e CMC |
| NIT-DICLA-026 | Participação em ensaio de proficiência/comparações |
| NIT-DICLA-030 | Rastreabilidade metrológica na acreditação |
| NIT-DICLA-031 | Regulamento da acreditação de laboratórios |
| NIE-Cgcre-009 | Uso da marca, símbolo e referências à acreditação |
| Portaria Inmetro nº 274/2014 | Regula uso das marcas/símbolos do Inmetro/acreditação |
| Comunicado 002/DICLA/Cgcre/2018 | Regra de decisão e declaração de conformidade em certificados |

### 1.3 Manutenção/Reparo de Instrumento Regulamentado

| Documento | Descrição |
|-----------|-----------|
| Portaria Inmetro nº 457/2021 | Condições para autorização de reparo/manutenção |
| Portaria Inmetro nº 619/2023 | Altera a Portaria 457/2021 |
| NIT-DICOL-002, 003, 004 | Requisitos para oficina permissionária |

### 1.4 Referências Técnicas Internacionais

| Documento | Descrição |
|-----------|-----------|
| ILAC P10 | Política de rastreabilidade metrológica |
| ILAC P14 | Estimativa e declaração de incerteza em calibração |
| ILAC G8:09/2019 | Orientação sobre regras de decisão e conformidade |
| JCGM 100 (GUM) | Base internacional para incerteza de medição |
| JCGM 200 (VIM) | Vocabulário internacional de metrologia |
| JCGM 106 | Papel da incerteza na avaliação de conformidade |
| Portaria Inmetro 232/2012 e 150/2016 | VIM/VIML brasileiro (terminologia) |

---

## 2. Distinções Críticas

### Calibração vs. Verificação Metrológica Legal
- **Calibração**: relação entre valores indicados e valores de referência (com incerteza)
- **Verificação metrológica**: exame que pode resultar em marca/certificado de verificação pelo órgão competente
- Certificado de calibração **NÃO substitui** verificação metrológica legal
- Certificado de verificação **NÃO substitui** certificado de calibração

### Acreditado vs. Não Acreditado
- Empresa **acreditada RBC**: pode usar símbolo/marca, referência à acreditação, escopo oficial
- Empresa **não acreditada**: pode emitir documento técnico, MAS sem símbolo/marca/referência à acreditação
- Rastreabilidade deve ser sustentada tecnicamente em ambos os casos

---

## 3. Ordem de Serviço — Campos Obrigatórios

### 3.1 Identificação

| Campo | Obrigatório | Observação |
|-------|:-----------:|------------|
| Número único da OS | ✅ | Sequencial por tenant |
| Data de abertura | ✅ | |
| Identificação do cliente | ✅ | Nome/razão social |
| CNPJ/CPF | ✅ | |
| Endereço completo do local do serviço | ✅ | |
| Responsável do cliente | ✅ | |
| Contato (telefone/email) | ✅ | |

### 3.2 Instrumento

| Campo | Obrigatório | Observação |
|-------|:-----------:|------------|
| Marca | ✅ | |
| Modelo | ✅ | |
| Número de série | ✅ | |
| Capacidade máxima (Max) | ✅ | |
| Divisão (d/e) | ✅ | |
| Classe (I, II, III, IIII) | ✅ | Quando aplicável |
| Quantidade de células de carga | ✅ | |
| Identificação do indicador | ✅ | Marca, modelo, nº série |
| Local de instalação | ✅ | Descrição do ambiente |

### 3.3 Escopo do Serviço

| Campo | Obrigatório | Observação |
|-------|:-----------:|------------|
| Finalidade | ✅ | Calibração, inspeção, manutenção, ajuste, diagnóstico |
| Critério contratado | ✅ | Apenas calibração OU calibração + declaração de conformidade |
| Norma/procedimento técnico aplicável | ✅ | |
| Condição: campo ou laboratório | ✅ | |
| Condições especiais do local | ✅ | Que possam afetar resultado |
| Necessidade de desmontagem/limpeza/ajuste/reparo | ✅ | |
| Identificação dos padrões previstos | ✅ | |
| Equipe executora | ✅ | |
| Aceite do cliente | ✅ | Assinatura/aceite digital |

### 3.4 Definição Contratual (Análise Crítica — ISO 17025)

| Definição | Obrigatório | Observação |
|-----------|:-----------:|------------|
| Somente calibração? | ✅ | |
| Haverá ajuste antes da calibração? | ✅ | |
| Haverá manutenção/reparo? | ✅ | |
| Cliente quer declaração de conformidade? | ✅ | |
| Regra de decisão aplicável | ✅ | Definir ANTES da execução |
| Emissão de laudo técnico complementar? | ✅ | Além do certificado |
| Instrumento sujeito à metrologia legal? | ✅ | Sim/Não |
| Necessita interação com IPEM? | ✅ | Sim/Não |

---

## 4. Registro Técnico de Execução — Campos Obrigatórios

| Campo | Obrigatório | Observação |
|-------|:-----------:|------------|
| Data e hora de início | ✅ | |
| Data e hora de fim | ✅ | |
| Técnicos executores | ✅ | Nomes/identificação |
| Condição "como encontrado" | ✅ | Estado inicial |
| Condição "como deixado" | ✅ | Estado final |
| Padrões utilizados | ✅ | Identificação completa |
| Validade/situação metrológica dos padrões | ✅ | |
| Condições ambientais | ✅ | Temperatura, umidade, etc. |
| Pontos de ensaio/aplicação de carga | ✅ | |
| Resultados brutos | ✅ | |
| Erros encontrados | ✅ | |
| Repetibilidade | ✅ | |
| Excentricidade | ✅ | |
| Linearidade | ✅ | |
| Demais verificações do procedimento | ✅ | |
| Observações técnicas | ✅ | Interferências, instabilidade, etc. |
| Ajustes realizados | Condicional | Se houve ajuste |
| Componentes trocados | Condicional | Se houve troca |
| Evidências fotográficas | Recomendado | |
| Assinatura/identificação do responsável | ✅ | |

---

## 5. Certificado de Calibração — Campos Obrigatórios

### 5.1 Campos Exigidos (ISO 17025 / RBC)

| Campo | Obrigatório | Observação |
|-------|:-----------:|------------|
| Título: "Certificado de Calibração" | ✅ | |
| Identificação única do certificado | ✅ | Número sequencial |
| Identificação do laboratório emissor | ✅ | |
| Identificação do cliente | ✅ | |
| Identificação inequívoca do instrumento | ✅ | Marca, modelo, nº série, capacidade, divisão |
| Data da calibração | ✅ | |
| Data de emissão | ✅ | |
| Local da calibração | ✅ | Quando relevante |
| Procedimento/método utilizado | ✅ | |
| Resultados da calibração | ✅ | |
| Unidade de medida | ✅ | |
| Incerteza de medição associada | ✅ | Conforme NIT-DICLA-021 |
| Identificação de quem autorizou a emissão | ✅ | |
| Paginação | ✅ | "Página X de Y" |
| Indicação clara dos resultados por item | ✅ | |
| Condição "como encontrado" | ✅ | Quando aplicável |
| Condição "como deixado" | ✅ | Quando aplicável |

### 5.2 Campos Operacionais Recomendados

| Campo | Recomendado | Observação |
|-------|:----------:|------------|
| Padrões de referência usados | ✅ | Identificação + certificado |
| Referência à rastreabilidade metrológica | ✅ | |
| Condição de instalação observada | ✅ | |
| Observações técnicas sobre limitações | ✅ | |
| Indicação de ajuste antes da medição final | ✅ | |

### 5.3 O que NÃO deve constar (salvo acordo contratual)

- ❌ "Validade de 12 meses"
- ❌ "Recalibrar em 6 meses"
- ❌ "Próxima calibração em tal data"
- ❌ Recomendação de intervalo de calibração (salvo acordo ou exigência legal)

### 5.4 Declaração de Conformidade (quando aplicável)

| Requisito | Obrigatório |
|-----------|:-----------:|
| Regra de decisão definida ANTES da execução | ✅ |
| Resultado medido apresentado | ✅ |
| Incerteza associada apresentada | ✅ |
| Especificação/critério usado indicado | ✅ |
| Conclusão com referência à regra de decisão | ✅ |

---

## 6. Rastreabilidade dos Padrões

Vinculados à OS e ao certificado, manter:

| Informação | Obrigatório |
|------------|:-----------:|
| Identificação dos pesos padrão/padrões | ✅ |
| Número do certificado dos padrões | ✅ |
| Laboratório emissor dos padrões | ✅ |
| Situação metrológica/validade | ✅ |
| Evidência de rastreabilidade | ✅ |

---

## 7. Manutenção/Reparo — Documentação Complementar

Quando houver intervenção em instrumento regulamentado (Portaria 457/2021 + 619/2023):

| Documento | Conteúdo |
|-----------|----------|
| OS de manutenção/reparo | Escopo da intervenção |
| Registro da intervenção | Defeito, causa, ação corretiva |
| Peças trocadas | Identificação e origem |
| Lacres/selagem | Quando aplicável |
| Calibração pós-intervenção | Se executada |
| Fluxo perante IPEM | Se necessário |

---

## 8. Fluxo Documental Completo

```
1. Proposta/Contrato
   ├── Objeto, escopo, tipo de serviço
   ├── Regra de decisão (se houver)
   ├── Exclusões e critérios de aceitação
   │
2. Ordem de Serviço
   ├── Dados do cliente e da balança
   ├── Escopo contratado
   ├── Condição inicial
   ├── Equipe e padrões previstos
   │
3. Registro Técnico de Campo
   ├── Resultados brutos
   ├── Ambiente, fotos, observações
   ├── Intervenções realizadas
   │
4. Relatório de Manutenção/Ajuste (quando houver)
   ├── Defeito encontrado
   ├── Causa provável
   ├── Peças trocadas, selos/lacres
   │
5. Certificado de Calibração
   ├── Identificação, método, resultados
   ├── Incerteza, rastreabilidade
   ├── Declaração de conformidade (se contratada)
   └── Responsável pela emissão
```

---

## 9. Checklist Final — Antes de Emitir o Certificado

- [ ] Balança identificada sem ambiguidade (marca, modelo, nº série, capacidade, divisão)
- [ ] OS define claramente o escopo
- [ ] Análise crítica do pedido/contrato realizada
- [ ] Procedimento técnico aplicável definido
- [ ] Padrões usados têm rastreabilidade documentada
- [ ] Dados brutos registrados
- [ ] Incerteza determinada e lançada
- [ ] Claro se houve ajuste/manutenção
- [ ] Sem validade/recomendação de intervalo indevida
- [ ] Se declaração de conformidade: regra de decisão definida antes + resultados + incerteza presentes
- [ ] Uso de marca/símbolo de acreditação correto (ou ausente, se não acreditado)
