# Work Order UI Completeness Audit Report

## 1. WorkOrderCreatePage.tsx

### Form Fields Present
- **customer_id** - CustomerAsyncSelect (async search component)
- **equipment_id** - Select (single equipment)
- **assigned_to** - Select (primary technician/assignee)
- **technician_ids** - Multi-select (additional technicians)
- **seller_id** - Select (seller/vendedor)
- **driver_id** - Select (driver/motorista)
- **priority** - Select (low/normal/high/urgent)
- **description** - Textarea
- **internal_notes** - Textarea
- **discount** / **discount_percentage** - Input
- **displacement_value** - Input
- **is_warranty** - Checkbox/toggle
- **os_number** - Input (custom OS number)
- **origin_type** - Derived from URL params (manual/quote/service_call)
- **quote_id** / **service_call_id** - From URL params
- **service_type** - Select
- **lead_source** - Select (present in form state)
- **initial_status** - Select (for retroactive creation)
- **Items section** - Add products/services with quantity, unit_price, discount, warehouse_id

### Missing Fields vs Backend Model
- **scheduled_date** - NOT visible in form state shown; needs verification
- **address / city / state** - NOT in create form (inherited from customer)
- **branch_id** - NOT in create form state
- **checklist_id** - NOT in create form
- **sla_policy_id** - NOT in create form
- **delivery_forecast** - NOT in create form
- **tags** - NOT in create form
- **photo_checklist** - NOT in create form

### Buttons & Actions
- Submit/Save button to create OS
- Add Item button (products/services)
- Remove Item button (trash icon per item)
- Cancel/back navigation

### API Calls
- POST /work-orders (create)
- GET /products (catalog)
- GET /services (catalog)
- GET technicians list
- GET all users list
- Customer async search

### Validations
- Client-side: basic required checks before submit
- No schema validation (no zod/yup)
- Toast notifications for success/error

---

## 2. WorkOrderDetailPage.tsx

### Information Displayed
- OS number, status badge, priority badge
- Customer info (name, phone, address)
- Assignee / Technicians
- Description, internal notes, technical report
- Equipment info
- Items list with totals
- Financial: total, discount, displacement value
- Status history / timeline
- SLA info (due date, responded at)
- Timestamps (created, started, completed, delivered)
- Displacement tracking times
- Service tracking times

### Buttons & Actions
- **Status transitions** - Dynamic buttons based on current status (Iniciar, Concluir, Entregar, Faturar, Cancelar, Reabrir, Desfaturar)
- **Reopen** (from cancelled)
- **Uninvoice** (from invoiced, with window.confirm)
- **GeoCheckinButton** - Checkin/checkout with geolocation
- **ExecutionActions** component - Full field execution flow
- **Edit fields** - Inline editing for description, priority, technical_report, internal_notes, displacement_value, is_warranty, assigned_to, seller_id, driver_id, technician_ids

### Missing/Gaps
- **Print/PDF button** - Reference found but implementation appears minimal/empty ("PDF/Print" section was empty in index)
- **Duplicate button** - workOrderApi has duplicate endpoint; button existence needs confirmation
- **Signature capture** - Exists as separate TechSignaturePage (canvas-based), NOT embedded in detail page
- **Chat component** - Backend API exists (WorkOrderChatController); detail page likely has a chat tab/section
- **Attachments/Photos** - Backend endpoints exist (photos, attachments); upload capability present
- **Approval flow** - Backend has full approval controller; detail page shows waiting_approval status transitions
- **Payment method fields** - agreed_payment_method and agreed_payment_notes sent during delivery/invoice transitions

### Financial Fields Visible
- total, discount, discount_percentage, discount_amount, displacement_value
- agreed_payment_method (on status transition to delivered/invoiced)
- cost_price per item (in backend model, shown in items)
- **Profit calculation** - NOT explicitly shown in UI (backend has cost_price but no profit display)

---

## 3. WorkOrdersListPage.tsx

### Filters Present
- **search** - Text input (free search)
- **statusFilter** - Select by status
- **priorityFilter** - Select by priority
- **technicianFilter** - Select by assigned technician
- **dateFrom / dateTo** - Date range filters
- **pendingInvoiceFilter** - Toggle for pending invoice

### Columns/Display
- OS number (business_number / os_number)
- Customer name
- Status badge
- Priority badge
- Total value (formatted BRL)
- Created date
- Assignee name

### Buttons & Actions
- **Create new OS** button (navigates to create page)
- **Import CSV** button (modal with file upload)
- **Export CSV** button (downloads CSV)
- **Delete** button per row (with permission check os.work_order.delete)
- **Batch status change** - Select multiple OS, choose common allowed transition
- **Pagination** - 20 per page with page controls
- **Row click** - Navigates to detail page

### Batch Operations
- Multi-select checkboxes
- Batch status update (filters to common allowed transitions across selected items)
- Blocked statuses list (BATCH_STATUS_BLOCKLIST)

---

## 4. WorkOrderDashboardPage.tsx

### KPI Cards
- Status counts per status (open, in_progress, completed, etc.)
- Average completion time (hours)
- Monthly revenue (invoiced total)
- SLA compliance percentage

### Charts/Visuals
- KpiCard components with icons and color coding
- Status distribution with color-coded labels
- All 16+ statuses represented with individual colors

### API Calls
- Dashboard stats endpoint (dashboardStats on backend)
- Status counts, avg completion, revenue, SLA metrics

---

## 5. WorkOrderKanbanPage.tsx

### Features
- **Drag & drop** - Full @dnd-kit integration (PointerSensor, KeyboardSensor)
- **Columns** - One per status from statusConfig
- **Cards** - Show OS number, description, customer, total, assignee, priority badge
- **Status transitions** - Via drag between columns (calls workOrderApi.updateStatus)
- **Search** - Text filter
- **Permission check** - canChangeStatus controls drag ability
- **Navigation** - Click card goes to detail page

### Missing
- No filter by technician/priority/date in Kanban view

---

## 6. WorkOrderTypes (work-order.ts)

### Types Defined
- WorkOrderStatus (17 statuses including deprecated in_progress)
- WorkOrderPriority (low/normal/high/urgent)
- WorkOrderCustomerRef, WorkOrderAssigneeRef, WorkOrderEquipmentRef
- WorkOrderOriginType, WorkOrderLeadSource, WorkOrderServiceType
- WorkOrderAgreedPaymentMethod
- WorkOrder (main interface - comprehensive, includes all tracking fields)
- WorkOrderItem (product/service with quantity, prices, discount)
- ItemFormPayload, EditFormPayload
- ChecklistResponsePayload, ChecklistTemplateItem
- ProductOrService

### Coverage
- Types are comprehensive and match backend model well
- Includes displacement, service, return tracking fields
- Includes signature, approval, SLA fields
- Includes discount, payment method fields

---

## 7. WorkOrderAPI (work-order-api.ts)

### Endpoints Covered
- list, detail, create, destroy
- updateStatus, updateAssignee
- update (general edit)
- storeItem, updateItem, deleteItem
- duplicate
- exportCsv, importCsv
- Execution flow endpoints (via workOrderApi object)

---

## 8. ExecutionActions.tsx

### Buttons by Status
- **open/awaiting_dispatch** -> "Iniciar Deslocamento" (start-displacement)
- **in_displacement** -> "Pausar Deslocamento" (pause-displacement) + "Cheguei no Cliente" (arrive)
- **displacement_paused** -> "Retomar Deslocamento" (resume-displacement)
- **at_client** -> "Iniciar Serviço" (start-service)
- **in_service** -> "Pausar Serviço" (pause-service) + "Finalizar Serviço" (finalize)
- **service_paused** -> "Retomar Serviço" (resume-service)
- **awaiting_return** -> "Iniciar Retorno" (start-return) + "Fechar sem Retorno" (close-without-return)
- **in_return** -> "Pausar Retorno" (pause-return) + "Cheguei na Base" (arrive-return)
- **return_paused** -> "Retomar Retorno" (resume-return)

### Features
- Geolocation capture on actions (latitude/longitude)
- Loading states (disabled while pending)
- Toast notifications for success/error
- Color-coded buttons (primary/warning/success/danger)

---

## SUMMARY OF GAPS

| Feature | Status |
|---------|--------|
| Create form - all core fields | PRESENT (customer, technician, items, priority, description, etc.) |
| Create form - scheduled_date | NEEDS VERIFICATION |
| Create form - address fields | NOT IN FORM (uses customer address) |
| Create form - branch_id | MISSING |
| Create form - checklist_id | MISSING |
| Create form - delivery_forecast | MISSING |
| Create form - tags | MISSING |
| Detail - all info sections | PRESENT |
| Detail - status transitions | PRESENT with confirmation |
| Detail - financial fields | PARTIAL (no profit calculation display) |
| Detail - Print/PDF | WEAK (endpoint exists but button implementation unclear) |
| Detail - Duplicate | API EXISTS (workOrderApi.duplicate) |
| Detail - Signature | SEPARATE PAGE (TechSignaturePage, not embedded) |
| Detail - Chat | BACKEND READY (controller exists, frontend integration likely present) |
| Detail - Attachments/Photos | BACKEND READY (endpoints exist) |
| Detail - Approval flow | PRESENT (status transitions + backend controller) |
| Detail - Equipment association | PRESENT |
| List - filters & search | COMPREHENSIVE (6 filter types) |
| List - batch operations | PRESENT (multi-select + batch status) |
| List - import/export CSV | PRESENT |
| Kanban - drag & drop | PRESENT |
| Kanban - filters | MINIMAL (search only) |
| Dashboard - KPIs | PRESENT (4 metrics) |
| Execution flow buttons | COMPLETE (all 10+ actions covered) |
