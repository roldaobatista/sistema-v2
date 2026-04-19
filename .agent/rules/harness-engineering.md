# HARNESS ENGINEERING

Regra operacional canonica para agentes do Kalibrium ERP. Este arquivo define o
modo de trabalho que complementa o Iron Protocol e deve ser carregado por todo
entrypoint de boot.

## H1 - Evidencia antes de afirmacao

Nenhum agente pode afirmar que uma correcao esta concluida, validada ou segura
sem evidenciar o comando executado no mesmo turno e seu resultado real.

## H2 - Causa raiz, nunca sintoma

Falhas de teste, lint, build ou CI devem ser tratadas como sinal de defeito no
sistema ou no ambiente. E proibido mascarar a falha com skip artificial,
assertiva relaxada, `--no-verify`, `--ignore-platform-reqs` ou remocao de gate.

## H3 - Fluxo Harness de 7 passos

Toda execucao deve seguir a sequencia:

1. entender
2. localizar
3. propor
4. implementar
5. verificar
6. corrigir
7. evidenciar

O agente deve avancar de uma etapa para a proxima apenas quando a anterior tiver
informacao suficiente para reduzir risco de regressao.

## H4 - Escopo minimo e preservacao

Corrigir o menor conjunto de arquivos que resolve a causa raiz. Se a mudanca
exigir cascata fora do escopo original, registrar o risco antes de continuar.

## H5 - Formato de resposta 6+1

Toda resposta final que envolva alteracao de codigo, CI, deploy, docs
operacionais ou governanca deve conter, nesta ordem:

1. Resumo do problema
2. Arquivos alterados
3. Motivo técnico
4. Testes executados
5. Resultado dos testes
6. Riscos remanescentes
7. Como desfazer, obrigatorio para deploy, infraestrutura, migracoes, rotas
   publicas, contrato de API, remocao de feature ou risco alto

## H6 - Piramide de verificacao

Verificar do menor escopo para o maior: teste especifico, grupo relacionado,
testsuite, build e suite completa. Falha em qualquer nivel bloqueia escalada.

## H7 - Linguagem proibida sem evidencia

Nao usar "pronto", "funcionando", "validado", "testes passando" ou equivalentes
sem output real do comando executado no mesmo turno.

## H8 - Falha bloqueante

Qualquer falha em teste, lint, build, typecheck, migration ou CI bloqueia merge,
push e conclusao do trabalho ate ser corrigida ou explicitamente marcada como
blocker com responsavel.
