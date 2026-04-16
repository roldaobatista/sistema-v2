# Auditoria do Modulo WorkOrder - Models & Relationships

**Data:** 2026-03-21

---

## 1. WorkOrder (model principal)

**Traits:** BelongsToTenant, HasFactory, SoftDeletes, Auditable, SyncsWithAgenda

**Fillable (55+ campos):** tenant_id, os_number, number, customer_id, equipment_id, quote_id, service_call_id, recurring_contract_id, seller_id, driver_id, origin_type, lead_source, branch_id, created_by, assigned_to, status, priority, description, internal_notes, technical_report, scheduled_date, received_at, started_at, completed_at, delivered_at, discount, discount_percentage, discount_amount, displacement_value, total, signature_path, signature_signer, signature_at, signature_ip, checklist_id, sla_policy_id, sla_due_at, sla_responded_at, dispatch_authorized_by, dispatch_authorized_at, parent_id, is_master, is_warranty, displacement_started_at, displacement_arrived_at, displacement_duration_minutes, service_started_at, wait_time_minutes, service_duration_minutes, total_duration_minutes, arrival_latitude, arrival_longitude, return_started_at, return_arrived_at, return_duration_minutes, return_destination, checkin_at, checkin_lat, checkin_lng, checkout_at, checkout_lat, checkout_lng, auto_km_calculated, service_type, manual_justification, cancelled_at, cancellation_reason, agreed_payment_method, agreed_payment_notes, delivery_forecast, tags, photo_checklist

**Casts (30+):** scheduled_date(datetime), received_at(datetime), started_at(datetime), completed_at(datetime), delivered_at(datetime), signature_at(datetime), sla_due_at(datetime), sla_responded_at(datetime), displacement_started_at(datetime), displacement_arrived_at(datetime), service_started_at(datetime), return_started_at(datetime), return_arrived_at(datetime), cancelled_at(datetime), dispatch_authorized_at(datetime), delivery_forecast(date), discount(decimal:2), discount_percentage(decimal:2), discount_amount(decimal:2), displacement_value(decimal:2), total(decimal:2), tags(array), photo_checklist(array), arrival_latitude(float), arrival_longitude(float), checkin_at(datetime), checkin_lat(float), checkin_lng(float), checkout_at(datetime), checkout_lat(float), checkout_lng(float), displacement_duration_minutes(integer), wait_time_minutes(integer), service_duration_minutes(integer), total_duration_minutes(integer), return_duration_minutes(integer), is_master(boolean), is_warranty(boolean)

**Relationships (24 total):**
| Tipo | Nome | Model Alvo |
|------|------|-----------|
| belongsTo | customer() | Customer |
| belongsTo | equipment() | Equipment |
| belongsTo | branch() | Branch |
| belongsTo | creator() | User (created_by) |
| belongsTo | assignee() | User (assigned_to) |
| belongsTo | quote() | Quote |
| belongsTo | serviceCall() | ServiceCall |
| belongsTo | recurringContract() | RecurringContract |
| belongsTo | seller() | User (seller_id) |
| belongsTo | driver() | User (driver_id) |
| belongsTo | dispatchAuthorizer() | User (dispatch_authorized_by) |
| belongsTo | checklist() | ServiceChecklist |
| belongsTo | slaPolicy() | SlaPolicy |
| hasMany | items() | WorkOrderItem |
| hasMany | statusHistory() | WorkOrderStatusHistory |
| hasMany | checklistResponses() | WorkOrderChecklistResponse |
| hasMany | chats() | WorkOrderChat |
| hasMany | invoices() | Invoice |
| hasMany | displacementStops() | WorkOrderDisplacementStop |
| hasMany | displacementLocations() | WorkOrderDisplacementLocation |
| hasMany | events() | WorkOrderEvent |
| hasMany | attachments() | WorkOrderAttachment |
| hasMany | signatures() | WorkOrderSignature |
| hasMany | calibrations() | EquipmentCalibration |
| hasOne | satisfactionSurvey() | SatisfactionSurvey |
| belongsToMany | technicians() | User (pivot: work_order_technicians) |
| belongsToMany | equipmentsList() | Equipment (pivot: work_order_equipments) |

**Accessors:** businessNumber, wazeLink, googleMapsLink

**Boot/Events:** creating (sanitiza os_number, auto-atribui SLA), updating (sanitiza os_number em dirty), deleting (limpa arquivos de photo_checklist do Storage)

**Scopes:** Nenhum scope nomeado encontrado (usa withoutGlobalScopes internamente)

---

## 2. WorkOrderItem

**Traits:** BelongsToTenant, HasFactory

**Fillable:** tenant_id, work_order_id, type, reference_id, description, quantity, unit_price, cost_price, discount, total, warehouse_id (e possivelmente outros)

**Casts:** quantity(decimal:2), unit_price(decimal:2), cost_price(decimal:2), discount(decimal:2), total(decimal:2)

**Relationships:**
- belongsTo workOrder() -> WorkOrder
- belongsTo product() -> Product (via reference_id)
- belongsTo service() -> Service (via reference_id)

**Boot/Events:** saving (auto-calcula total), creating (auto-popula cost_price do Product), created (recalculateTotal na OS + reserva estoque), updated (recalculateTotal + sync estoque), deleted (recalculateTotal + estorna estoque)

---

## 3. WorkOrderAttachment

**Traits:** BelongsToTenant

**Fillable:** tenant_id, work_order_id, uploaded_by, file_name, file_path, file_type, file_size, description

**Relationships:**
- belongsTo workOrder() -> WorkOrder
- belongsTo uploader() -> User (uploaded_by)

---

## 4. WorkOrderChat

**Traits:** BelongsToTenant

**Fillable:** tenant_id, work_order_id, user_id, message, type, file_path, read_at

**Casts:** read_at(datetime)

**Relationships:**
- belongsTo workOrder() -> WorkOrder
- belongsTo user() -> User

---

## 5. WorkOrderEvent

**Sem trait BelongsToTenant**

**Fillable:** work_order_id, event_type, user_id, latitude, longitude, metadata

**Casts:** metadata(array), latitude(decimal:8), longitude(decimal:8)

**Relationships:**
- belongsTo workOrder() -> WorkOrder
- belongsTo user() -> User

**Constantes:** 16 tipos de evento (TYPE_DISPLACEMENT_STARTED, TYPE_SERVICE_STARTED, etc.) com labels em PT-BR

---

## 6. WorkOrderRating

**Sem trait BelongsToTenant** | **Sem tenant_id no fillable**

**Fillable:** work_order_id, customer_id, overall_rating, quality_rating, punctuality_rating, comment, channel

**Casts:** overall_rating(integer), quality_rating(integer), punctuality_rating(integer)

**Relationships:**
- belongsTo workOrder() -> WorkOrder
- belongsTo customer() -> Customer

---

## 7. WorkOrderRecurrence

**Traits:** HasFactory, BelongsToTenant, SoftDeletes

**Fillable:** tenant_id, customer_id, service_id, name, description, frequency, interval, day_of_month, day_of_week, start_date, end_date, last_generated_at, next_generation_date, is_active, metadata

**Casts:** start_date(date), end_date(date), next_generation_date(date), last_generated_at(datetime), is_active(boolean), metadata(array), day_of_month(integer), day_of_week(integer), interval(integer)

**Relationships:**
- belongsTo customer() -> Customer
- belongsTo service() -> Service

---

## 8. WorkOrderSignature

**Traits:** HasFactory, BelongsToTenant

**Fillable:** tenant_id, work_order_id, signer_name, signer_document, signer_type, signature_data, signed_at, ip_address, user_agent

**Casts:** signed_at(datetime)

**Relationships:**
- belongsTo workOrder() -> WorkOrder (sem type hint)

---

## 9. WorkOrderStatusHistory

**Traits:** BelongsToTenant, HasFactory

**Table customizada:** work_order_status_history

**Fillable:** tenant_id, work_order_id, user_id, from_status, to_status, notes

**Casts:** Nenhum

**Relationships:**
- belongsTo workOrder() -> WorkOrder
- belongsTo user() -> User

---

## 10. WorkOrderTemplate

**Traits:** BelongsToTenant, SoftDeletes

**Fillable:** tenant_id, name, description, default_items, checklist_id, priority, created_by

**Casts:** default_items(array)

**Relationships:**
- belongsTo checklist() -> ServiceChecklist (checklist_id)
- belongsTo creator() -> User (created_by)

---

## 11. WorkOrderTimeLog

**Traits:** HasFactory, BelongsToTenant

**Fillable:** tenant_id, work_order_id, user_id, started_at, ended_at, duration_seconds, activity_type, description, latitude, longitude

**Casts:** started_at(datetime), ended_at(datetime), duration_seconds(integer), latitude(float), longitude(float)

**Relationships:**
- belongsTo workOrder() -> WorkOrder (sem type hint)
- belongsTo user() -> User (sem type hint)

---

## 12. WorkOrderChecklistResponse

**Traits:** BelongsToTenant

**Fillable:** tenant_id, work_order_id, checklist_item_id, value, notes

**Relationships:**
- belongsTo workOrder() -> WorkOrder
- belongsTo item() -> ServiceChecklistItem (checklist_item_id)

---

## 13. WorkOrderDisplacementLocation

**Sem trait BelongsToTenant** | **Sem tenant_id no fillable**

**Fillable:** work_order_id, user_id, latitude, longitude, recorded_at

**Casts:** latitude(float), longitude(float), recorded_at(datetime)

**Relationships:**
- belongsTo workOrder() -> WorkOrder
- belongsTo user() -> User

---

## 14. WorkOrderDisplacementStop

**Sem trait BelongsToTenant** | **Sem tenant_id no fillable**

**Fillable:** work_order_id, type, started_at, ended_at, notes, location_lat, location_lng

**Casts:** started_at(datetime), ended_at(datetime), location_lat(float), location_lng(float)

**Relationships:**
- belongsTo workOrder() -> WorkOrder

**Accessors:** durationMinutes (computed)

**Constantes:** TYPE_LUNCH, TYPE_HOTEL, TYPE_BR_STOP, TYPE_OTHER

---

## 15. WorkOrderStatus (Enum)

**17 cases:** OPEN, AWAITING_DISPATCH, IN_DISPLACEMENT, DISPLACEMENT_PAUSED, AT_CLIENT, IN_SERVICE, SERVICE_PAUSED, AWAITING_RETURN, IN_RETURN, RETURN_PAUSED, WAITING_PARTS, WAITING_APPROVAL, COMPLETED, DELIVERED, INVOICED, CANCELLED, IN_PROGRESS (deprecated)

**Metodos:** label(), color(), allowedTransitions(), canTransitionTo()

---

## PROBLEMAS ENCONTRADOS

### A) Relacionamentos Inversos Faltantes no WorkOrder

| Relationship | Status |
|---|---|
| ratings() -> WorkOrderRating | **FALTANDO** no WorkOrder |
| timeLogs() -> WorkOrderTimeLog | **FALTANDO** no WorkOrder |
| recurrence() -> WorkOrderRecurrence | **FALTANDO** (WorkOrderRecurrence nao tem work_order_id - e um model independente de agendamento) |
| parent() -> WorkOrder (self-referencing via parent_id) | **FALTANDO** |
| children() -> WorkOrder (self-referencing hasMany) | **FALTANDO** |

### B) Multi-Tenancy Inconsistente

Os seguintes models NAO usam BelongsToTenant nem tem tenant_id no fillable:

| Model | Problema |
|---|---|
| **WorkOrderEvent** | Sem BelongsToTenant, sem tenant_id |
| **WorkOrderRating** | Sem BelongsToTenant, sem tenant_id |
| **WorkOrderDisplacementLocation** | Sem BelongsToTenant, sem tenant_id |
| **WorkOrderDisplacementStop** | Sem BelongsToTenant, sem tenant_id |

Estes 4 models dependem exclusivamente do work_order_id para isolamento via tenant. Pode ser intencional (herda via join), mas e um risco se consultados diretamente sem join.

### C) Relacionamentos que Deveriam Existir mas Nao Existem

| No Model | Relationship | Justificativa |
|---|---|---|
| WorkOrder | ratings() | WorkOrderRating existe com work_order_id mas WorkOrder nao tem hasMany para ele |
| WorkOrder | timeLogs() | WorkOrderTimeLog existe com work_order_id mas WorkOrder nao tem hasMany para ele |
| WorkOrder | parent() | parent_id esta no fillable mas nao ha belongsTo parent |
| WorkOrder | children() | is_master esta no fillable mas nao ha hasMany children |
| WorkOrderRecurrence | workOrders() | Nao tem relacao com as OS geradas |
| WorkOrderTemplate | Nenhuma relacao com WorkOrder | Template nao referencia quais OS foram criadas a partir dele |

### D) Scopes Ausentes

Nenhum model do modulo WorkOrder possui scopes nomeados (`scopeXxx`). Considerar adicionar:
- `scopeActive()` - OS nao canceladas
- `scopeByStatus($status)` - filtra por status
- `scopeOverdue()` - OS com SLA vencido
- `scopeByTechnician($userId)` - via pivot technicians

### E) Casts Faltantes

| Model | Campo | Cast Sugerido |
|---|---|---|
| WorkOrderStatusHistory | from_status / to_status | WorkOrderStatus enum cast |
| WorkOrderSignature | $casts em property ao inves de method | Inconsistencia de estilo (todos os outros usam `casts()` method) |

### F) Type Hints Faltantes

| Model | Metodo | Problema |
|---|---|---|
| WorkOrderSignature | workOrder() | Sem return type BelongsTo |
| WorkOrderTimeLog | workOrder(), user() | Sem return type BelongsTo |
| WorkOrderRecurrence | customer(), service() | Sem return type BelongsTo |

### G) Model Potencialmente Orfao

- **WorkOrderRecurrence**: Nao tem `work_order_id`. Parece ser um agendador independente (customer_id + service_id), mas nao tem relacao direta com WorkOrder. Verificar se e realmente usado ou se foi substituido por RecurringContract (que WorkOrder ja referencia).
