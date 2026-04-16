# Auditoria: WorkOrder Controllers, Routes & Form Requests

**Data:** 2026-03-21

---

## 1. WorkOrderController (2349 linhas)

### Métodos Públicos (36 métodos)
| Método | FormRequest | Authorize | Descrição |
|--------|-------------|-----------|-----------|
| `index` | Request | `viewAny` | Lista OS com filtros (search, status, priority, assigned_to, customer_id, equipment_id, dates, pending_invoice) |
| `store` | StoreWorkOrderRequest | `create` | Cria OS com itens, técnicos, equipamentos |
| `show` | - | `view` | Detalhe da OS com relacionamentos |
| `update` | UpdateWorkOrderRequest | `update` | Atualiza OS (bloqueia campos em status final) |
| `destroy` | - | `delete` | Soft-delete da OS |
| `restore` | - | `create` | Restaura OS soft-deleted |
| `updateStatus` | UpdateWorkOrderStatusRequest | `changeStatus` | Muda status da OS |
| `reopen` | Request | `changeStatus` | Reabre OS finalizada |
| `uninvoice` | - | `changeStatus` | Remove faturamento da OS |
| `authorizeDispatch` | Request | `update` | Autoriza despacho (GAP-02) |
| `storeItem` | StoreWorkOrderItemRequest | `update` | Adiciona item (produto/serviço) |
| `updateItem` | UpdateWorkOrderItemRequest | `update` | Atualiza item |
| `destroyItem` | - | `update` | Remove item |
| `metadata` | - | `viewAny` | Retorna metadados (statuses, priorities, etc.) |
| `attachments` | - | `view` | Lista anexos |
| `storeAttachment` | StoreWorkOrderAttachmentRequest | `update` | Upload de anexo |
| `destroyAttachment` | - | `update` | Remove anexo |
| `storeSignature` | StoreWorkOrderSignatureRequest | `update` | Salva assinatura |
| `attachEquipment` | AttachWorkOrderEquipmentRequest | `update` | Vincula equipamento |
| `detachEquipment` | - | `update` | Desvincula equipamento |
| `duplicate` | Request | `create` | Clona OS existente |
| `exportCsv` | Request | `viewAny` | Exporta CSV |
| `importCsv` | ImportWorkOrderCsvRequest | `create` | Importa CSV |
| `importCsvTemplate` | - | `viewAny` | Baixa template CSV |
| `dashboardStats` | Request | `viewAny` | Estatísticas do dashboard |
| `auditTrail` | - | `view` | Histórico de auditoria |
| `satisfaction` | - | `view` | Dados NPS/satisfação |
| `costEstimate` | - | `view` | Estimativa de custo |
| `downloadPdf` | - | `view` | Gera PDF da OS |
| `uploadChecklistPhoto` | Request | `update` | Upload foto checklist |
| `items` | - | `view` | Lista itens |
| `comments` | - | `view` | Lista comentários |
| `storeComment` | StoreWorkOrderCommentRequest | `update` | Adiciona comentário |
| `photos` | - | `view` | Lista fotos |
| `statusHistoryAlias` | - | `view` | Histórico de status |

**Resource:** WorkOrderResource (usado em index, show, store, update, duplicate)
**Error handling:** 25 try/catch blocks, status codes: 200, 201, 403, 409, 422, 500
**Todas as 6 ações de Policy são usadas:** viewAny, view, create, update, delete, changeStatus

---

## 2. WorkOrderApprovalController (293 linhas)

| Método | FormRequest | Authorize | Descrição |
|--------|-------------|-----------|-----------|
| `index` | Request | `view` | Lista aprovações da OS |
| `request` | RequestWorkOrderApprovalRequest | `update` | Solicita aprovação |
| `respond` | RespondWorkOrderApprovalRequest | `update` | Responde aprovação (approve/reject) |

**Resource:** Nenhum (retorna dados direto)
**Authorize:** Usa Policy (view, update)

---

## 3. WorkOrderChatController (135 linhas)

| Método | FormRequest | Authorize | Descrição |
|--------|-------------|-----------|-----------|
| `index` | Request | `view` | Lista mensagens do chat |
| `store` | SendWorkOrderChatMessageRequest | `update` | Envia mensagem |
| `markAsRead` | Request | `view` | Marca como lida |

**Resource:** Nenhum
**Authorize:** Usa Policy (view, update)

---

## 4. WorkOrderDisplacementController (303 linhas)

| Método | FormRequest | Authorize | Descrição |
|--------|-------------|-----------|-----------|
| `index` | Request | `view` | Lista deslocamentos |
| `start` | StartDisplacementRequest | YES | Inicia deslocamento |
| `arrive` | ArriveDisplacementRequest | YES | Registra chegada |
| `recordLocation` | RecordDisplacementLocationRequest | YES | Registra localização GPS |
| `addStop` | AddDisplacementStopRequest | YES | Adiciona parada |
| `endStop` | Request | YES | Finaliza parada |

**Resource:** Nenhum
**Authorize:** Todos os métodos têm authorize

---

## 5. WorkOrderExecutionController (919 linhas)

| Método | FormRequest | Authorize | Descrição |
|--------|-------------|-----------|-----------|
| `startDisplacement` | WorkOrderLocationRequest | YES | Inicia deslocamento |
| `pauseDisplacement` | PauseDisplacementRequest | YES | Pausa deslocamento |
| `resumeDisplacement` | Request | YES | Retoma deslocamento |
| `arrive` | WorkOrderLocationRequest | YES | Registra chegada |
| `startService` | Request | YES | Inicia serviço |
| `pauseService` | PauseServiceRequest | YES | Pausa serviço |
| `resumeService` | Request | YES | Retoma serviço |
| `finalize` | FinalizeWorkOrderRequest | YES | Finaliza serviço |
| `startReturn` | StartReturnRequest | YES | Inicia retorno |
| `pauseReturn` | PauseDisplacementRequest | YES | Pausa retorno |
| `resumeReturn` | Request | YES | Retoma retorno |
| `arriveReturn` | WorkOrderLocationRequest | YES | Chegada do retorno |
| `closeWithoutReturn` | CloseWithoutReturnRequest | YES | Fecha sem retorno |
| `timeline` | Request | YES | Timeline de execução |

**Resource:** Nenhum
**Authorize:** Todos os métodos têm authorize

---

## 6. WorkOrderTemplateController (95 linhas)

| Método | FormRequest | Authorize | Descrição |
|--------|-------------|-----------|-----------|
| `index` | Request | **NAO** | Lista templates |
| `store` | StoreWorkOrderTemplateRequest | **NAO** | Cria template |
| `show` | - | **NAO** | Detalhe do template |
| `update` | UpdateWorkOrderTemplateRequest | **NAO** | Atualiza template |
| `destroy` | - | **NAO** | Exclui template |

**Resource:** Nenhum (retorna modelo direto via ApiResponse::data)
**PROBLEMA: Nenhum método usa $this->authorize(). Depende exclusivamente do middleware de rota.**
A verificação de tenant é feita manualmente (compara tenant_id), mas não há Policy.

---

## 7. ExpressWorkOrderController (74 linhas)

| Método | FormRequest | Authorize | Descrição |
|--------|-------------|-----------|-----------|
| `store` | StoreExpressWorkOrderRequest | **NAO** | Cria OS Express (rápida) |

**Resource:** WorkOrderResource
**PROBLEMA: Sem $this->authorize(). Depende exclusivamente do middleware de rota.**
Cria Customer automaticamente se não existir (via customer_name).

---

## 8. Rotas vs Controllers

### Rotas definidas (work-orders.php + advanced-lots.php):
- **Todas as rotas têm controller method correspondente** - OK
- **Todos os métodos públicos têm rotas correspondentes** - OK (alguns como `items`, `comments`, `photos`, `statusHistoryAlias` podem estar em outros arquivos de rota)

### Rotas sem método que precisam verificação:
- `comments`, `photos`, `statusHistoryAlias` — não encontrados em work-orders.php (provavelmente em missing-routes.php ou outro arquivo)

---

## 9. Checklist de Problemas Encontrados

### CRITICOS
1. **WorkOrderTemplateController: ZERO authorize()** — Sem Policy. Apenas middleware de rota protege. Se alguém acessar internamente, não há verificação.
2. **ExpressWorkOrderController: ZERO authorize()** — Mesmo problema. Sem Policy.
3. **Todos os FormRequests retornam `authorize(): return true`** — Nenhum FormRequest faz verificação de autorização. Toda autorização depende do controller ou middleware.

### MELHORIAS RECOMENDADAS
4. **FinalizeWorkOrderRequest:** Validação muito fraca — só `technical_report` e `resolution_notes` como nullable strings. Não valida se a OS está em status que permite finalização.
5. **StoreExpressWorkOrderRequest:** Validação mínima (4 campos). Não valida `customer_name` length nem formato.
6. **`duplicate` usa `Request` genérico** — Deveria ter um FormRequest dedicado para validar campos opcionais de override.
7. **`reopen` usa `Request` genérico** — Sem validação de campos.
8. **`authorizeDispatch` usa `Request` genérico** — Sem validação de campos.
9. **`uploadChecklistPhoto` usa `Request` genérico** — Sem validação de tipo/tamanho de arquivo via FormRequest (pode estar inline).
10. **WorkOrderDisplacementController e WorkOrderExecutionController:** Não usam Resources — retornam dados sem transformação padronizada.

### PDF/EXPORT
- **PDF:** `downloadPdf` existe e funciona via rota `GET work-orders/{work_order}/pdf`
- **CSV Export:** `exportCsv` via `GET work-orders-export`
- **CSV Import:** `importCsv` via `POST work-orders-import`
- **Batch Export:** `GET work-orders/export` via BatchExportController (rota separada)

### DUPLICATE/CLONE
- **`duplicate`** existe via `POST work-orders/{work_order}/duplicate` com permission `os.work_order.create`

---

## 10. Resumo de Permissions nas Rotas

| Permission | Endpoints |
|-----------|-----------|
| `os.work_order.view` | index, metadata, show, attachments, displacement/index, execution/timeline, chat/index, approvals/index, audit-trail, satisfaction, cost-estimate, pdf, dashboard-stats |
| `os.work_order.create` | store, express/store, duplicate, import, import-template |
| `os.work_order.update` | update, items CRUD, attachments CRUD, signature, equipments, chat/store, approvals/request+respond |
| `os.work_order.change_status` | updateStatus, reopen, uninvoice, displacement/start+arrive+location+stops, execution/* |
| `os.work_order.delete` | destroy, restore |
| `os.work_order.export` | exportCsv |
| `os.work_order.authorize_dispatch` | authorizeDispatch |
