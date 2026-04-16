# Backend Models & Migrations Audit Report
Generated: 2026-03-28

## Summary
- Total models audited: ~360 PHP files in `app/Models/`
- BelongsToTenant trait lives in: `app/Models/Concerns/BelongsToTenant.php` (NOT `app/Traits/`)
- `app/Traits/BelongsToTenant.php` does NOT exist (correct — trait is in Concerns)

---

## CATEGORY 1: Models WITH tenant_id in fillable but MISSING BelongsToTenant trait

These models store tenant-scoped data but lack the global scope that auto-filters by tenant, causing **potential cross-tenant data leaks**.

| Model | File | Issue |
|-------|------|-------|
| `AnalyticsDataset` | `app/Models/AnalyticsDataset.php` | Has `tenant_id` in fillable, has `tenant()` relation, missing `use BelongsToTenant` |
| `BusinessHour` | `app/Models/BusinessHour.php` | Has `tenant_id` in fillable, missing `use BelongsToTenant` |
| `DataExportJob` | `app/Models/DataExportJob.php` | Has `tenant_id` in fillable, has `tenant()` relation, missing `use BelongsToTenant` |
| `EmbeddedDashboard` | `app/Models/EmbeddedDashboard.php` | Has `tenant_id` in fillable, has `tenant()` relation, missing `use BelongsToTenant` |
| `TenantHoliday` | `app/Models/TenantHoliday.php` | Has `tenant_id` in fillable, missing `use BelongsToTenant` |

**Fix for each:** Add `use App\Models\Concerns\BelongsToTenant;` import + `use BelongsToTenant;` in class body.

---

## CATEGORY 2: Models Missing $casts for Date/Amount Fields

These models have date fields in `$fillable` but no `$casts` array, so dates return as raw strings.

| Model | File | Missing Casts |
|-------|------|---------------|
| `AccountPayableInstallment` | `app/Models/AccountPayableInstallment.php` | `due_date` → `'date'`, `paid_at` → `'datetime'`, `amount`/`paid_amount` → `'decimal:2'` |
| `AccountReceivableInstallment` | `app/Models/AccountReceivableInstallment.php` | Same as above: `due_date`, `paid_at`, `amount`, `paid_amount` |

---

## CATEGORY 3: Child/Detail Models - No BelongsToTenant (Intentional, but verify)

These models are child/detail records of a tenant-scoped parent. They have no `tenant_id` column themselves; tenant isolation flows through the parent FK. **This is acceptable**, but verify queries don't bypass parent scope.

| Model | Parent FK | Notes |
|-------|-----------|-------|
| `AccountPlanAction` | `account_plan_id` | Child of AccountPlan (which has BelongsToTenant) |
| `AgendaItemComment` | `agenda_item_id` → `central_item_comments` table | Child of AgendaItem |
| `AgendaItemHistory` | `agenda_item_id` → `central_item_history` table | Child of AgendaItem, `$timestamps = false` |
| `AssetTagScan` | `asset_tag_id` | Child of AssetTag |
| `CompetitorInstrumentRepair` | `instrument_id`, `competitor_id` | Child of InmetroInstrument |
| `CrmDealCompetitor` | `deal_id` | Child of CrmDeal |
| `CrmSequenceStep` | `sequence_id` | Child of CrmSequence |
| `CrmTerritoryMember` | `territory_id` | Child of CrmTerritory |
| `CrmWebFormSubmission` | `form_id` | Child of CrmWebForm |
| `InmetroHistory` | `instrument_id` | Child of InmetroInstrument |
| `ManagementReviewAction` | `management_review_id` | Child of ManagementReview |
| `MaterialRequestItem` | `material_request_id` | Child of MaterialRequest |
| `OperationalSnapshot` | No parent FK | **SYSTEM-WIDE** snapshot (no tenant scope — by design?) |
| `PartsKitItem` | `parts_kit_id` | Child of PartsKit |
| `PriceTableItem` | `price_table_id` | Child of PriceTable |
| `ProductKit` | `parent_id`/`child_id` | Self-referential product kit pivot |
| `PurchaseQuotationItem` | `quotation_id` | Child of PurchaseQuotation |
| `PurchaseQuoteItem` | `purchase_quote_id` | Child of PurchaseQuote |
| `PurchaseQuoteSupplier` | `purchase_quote_id` | Child of PurchaseQuote |
| `QualityAuditItem` | `quality_audit_id` | Child of QualityAudit |
| `ReturnedUsedItemDisposition` | `used_stock_item_id` | Child of UsedStockItem |
| `RmaItem` | `rma_request_id` | Child of RmaRequest |
| `ServiceCatalogItem` | `service_catalog_id` | Child of ServiceCatalog |
| `ServiceChecklistItem` | `service_checklist_id` | Child of ServiceChecklist |
| `StockDisposalItem` | `stock_disposal_id` | Child of StockDisposal |
| `StockTransferItem` | `stock_transfer_id` | Child of StockTransfer |
| `VisitRouteStop` | `visit_route_id` | Child of VisitRoute |
| `WebhookLog` | `webhook_id` | Child of Webhook |

---

## CATEGORY 4: System/Global Models (No Tenant Scope - Correct by Design)

These models are intentionally global and should NOT have `BelongsToTenant`:

| Model | Reason |
|-------|--------|
| `User` | Auth model, has `current_tenant_id` but is not tenant-scoped |
| `Tenant` | The tenant itself |
| `Role` | Spatie permission role (extends SpatieRole), has tenant_id FK but managed via Spatie |
| `PermissionGroup` | Global permission groupings |
| `InssBracket` | Brazilian INSS tax brackets — government data, no tenant |
| `IrrfBracket` | Brazilian IRRF tax brackets — government data, no tenant |
| `MinimumWage` | Brazilian minimum wage table — no tenant |
| `TwoFactorAuth` | User-level 2FA config, scoped by user_id not tenant |
| `UserSession` | User-level sessions |
| `UserFavorite` | User-level favorites (`user_id` FK) |
| `InmetroInstrument` | Scoped via `InmetroLocation` → `InmetroOwner` chain, no direct tenant |
| `InmetroHistory` | Child of InmetroInstrument |
| `InmetroLocation` | Parent of InmetroInstrument chain |
| `WarehouseStock` | Scoped via `warehouse_id` → Warehouse (which has BelongsToTenant) |

---

## CATEGORY 5: Lookup Models (app/Models/Lookups/) - No Tenant Scope

All ~25 models in `app/Models/Lookups/` correctly omit `BelongsToTenant` as they are global reference data tables.

**Exception — POTENTIAL ISSUE:**
- `CancellationReason` has `tenant_id` referenced but no `BelongsToTenant` trait — needs investigation
- `MeasurementUnit` also references `tenant_id` — needs investigation

---

## CATEGORY 6: Field Naming - CORRECT

- `Expense` model: correctly uses `created_by` (not `user_id`) ✓
- `Schedule` model: correctly uses `technician_id` (not `user_id`) ✓
- No `company_id` found anywhere — all tenant references use `tenant_id` ✓

---

## CATEGORY 7: Missing Relationships

| Model | File | Missing |
|-------|------|---------|
| `WebhookLog` | `app/Models/WebhookLog.php` | Missing `webhook(): BelongsTo` relationship to `Webhook` model |
| `AgendaItemComment` | `app/Models/AgendaItemComment.php` | Missing `$casts` for `created_at`/date fields; uses `$guarded = ['id']` which is acceptable |

---

## CATEGORY 8: Migrations - Recent Issues Found

**2026_03 migrations with new `Schema::create` tables — all have tenant_id where required** ✓

No new create-table migrations are missing `tenant_id` in March 2026.

Notable migration series:
- `2026_03_23_200000_add_tenant_id_to_hr_models_missing_tenant.php` — retroactively added tenant_id to HR models (11 refs) — already applied
- `2026_03_21_200000_add_tenant_id_to_work_order_event_tables.php` — already applied

---

## PRIORITY FIXES REQUIRED

### P0 - Cross-Tenant Data Leak Risk (fix immediately)

1. **`app/Models/AnalyticsDataset.php`** — Add `use BelongsToTenant;`
2. **`app/Models/BusinessHour.php`** — Add `use BelongsToTenant;`
3. **`app/Models/DataExportJob.php`** — Add `use BelongsToTenant;`
4. **`app/Models/EmbeddedDashboard.php`** — Add `use BelongsToTenant;`
5. **`app/Models/TenantHoliday.php`** — Add `use BelongsToTenant;`

### P1 - Missing Date Casts (causes silent type errors)

6. **`app/Models/AccountPayableInstallment.php`** — Add casts for `due_date`, `paid_at`, `amount`, `paid_amount`
7. **`app/Models/AccountReceivableInstallment.php`** — Add casts for `due_date`, `paid_at`, `amount`, `paid_amount`

### P2 - Missing Relationship

8. **`app/Models/WebhookLog.php`** — Add `webhook(): BelongsTo` to `Webhook::class`

### P3 - Lookup Models (RESOLVED - no action needed)

`CancellationReason` and `MeasurementUnit` both extend `BaseLookup`, which already uses `BelongsToTenant`. All 25+ Lookup models inherit tenant scoping via `BaseLookup`. ✓

---

## FIXES APPLIED

The following P0 and P1 issues were fixed in this audit session:

| File | Fix Applied |
|------|-------------|
| `app/Models/AnalyticsDataset.php` | Added `use BelongsToTenant` |
| `app/Models/BusinessHour.php` | Added `use BelongsToTenant` |
| `app/Models/DataExportJob.php` | Added `use BelongsToTenant` |
| `app/Models/EmbeddedDashboard.php` | Added `use BelongsToTenant` |
| `app/Models/TenantHoliday.php` | Added `use BelongsToTenant` |
| `app/Models/AccountPayableInstallment.php` | Added `$casts` for `due_date`, `paid_at`, `amount`, `paid_amount` |
| `app/Models/AccountReceivableInstallment.php` | Added `$casts` for `due_date`, `paid_at`, `amount`, `paid_amount` |

P2 (WebhookLog missing relationship) was a false alarm — `webhook(): BelongsTo` already exists on line 21.
