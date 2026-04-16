# Módulo OrdemServico (Legacy)

## Status: ATIVO — Não remover

Este módulo **não é código morto**. É uma integração cross-domain ativa usada pelo módulo de Metrologia.

## Arquivos

- `Events/OrdemServicoFinalizadaEvent.php` — Evento disparado quando OS é finalizada
- `DTO/OrdemServicoFinalizadaPayload.php` — Payload com dados da OS finalizada

## Consumidores

- `App\Modules\Metrologia\Listeners\GerarRascunhoCertificadoListener` — Gera rascunho de certificado de calibração quando a OS é finalizada
- Registrado em `EventServiceProvider`

## Disparado por

- `WorkOrderController::updateStatus()` — quando OS entra em status COMPLETED
- `WorkOrderExecutionController::finalize()` — quando técnico finaliza execução

## Consideração futura

Considerar mover para `Modules/Metrologia/Events/` para maior clareza arquitetural, já que o evento existe primariamente para servir o módulo de Metrologia.
