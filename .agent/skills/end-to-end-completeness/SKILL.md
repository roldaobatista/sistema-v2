# End-to-End Completeness

Skill always-on para impedir correcoes parciais no Kalibrium ERP.

## Principio

Toda mudanca deve ser completa no caminho afetado. Quando tocar regra de negocio,
verificar a cadeia migration, model, service, controller, rota, contrato
TypeScript, client, componente e teste conforme o escopo real da slice.

## Harness

Aplicar o Harness Engineering em toda resposta final: resumo do problema,
arquivos alterados, motivo tecnico, testes executados, resultado dos testes,
riscos remanescentes e como desfazer quando obrigatorio.

## Gate

Se algum elo necessario estiver ausente, criar ou corrigir antes de pedir
re-auditoria. Se o elo depender de outra camada ou outro agente, registrar issue
bloqueadora em PT-BR.
