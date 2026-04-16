# Protocolo de Revisão e Correções (Bugfixing)

A metodologia de correção exige obediência à **Lei 2 e Lei 5** do Iron Protocol. Problemas (Bugs) não recebem band-aids ou isolamentos silenciosos. A correção ocorre em escopo amplo de Raiz.

## 1. Identificando a Causa Raiz (RCA)

Nenhuma linha de código fonte será alterada sem antes o Agente declarar formalmente (via plano de ação ou output mental) sua teoria de Causa Principal.

- Erro é Sintaxe? (ex: PHP Typo).
- Erro é Regra Evasiva? (Faltou isolar `where('tenant_id')`).
- Erro é Frontend assumindo dados que a API omite sob certas roles?

## 2. Red-Green-Refactor Estrito

1. Encontrado o bug.
2. Escreve-se ou altera-se um `Feature Test` (Pest/PHPUnit) que expressamente **FALHA** ao rodar comprovando a existência e comportamento do erro.
3. Implanta-se a solução na lógica (Controller/Service).
4. Afere-se se o teste passou garantindo a cura retroativa.

## 3. Commits Corretivos

Mudanças oriundas de correção exigem o prefixo convencional atrelado. Exemplo `fix: Resolve quebra de isolamento de Tenant no envio de faturas`. Nunca suprimir o tracking do erro consertado.
