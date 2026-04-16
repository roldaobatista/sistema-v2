# Audit: Controllers & FormRequests — 2026-03-28

Scope: 795 FormRequests, ~300+ controllers in `backend/app/Http/`.

---

## 1. FormRequest `authorize()` with `return true` (NO permission check)

**Total affected files: 704 out of 795 FormRequests.**

This is the most pervasive violation. Distribution by module:

| Module | Count |
|--------|-------|
| Crm | 69 |
| Financial | 68 |
| HR | 51 |
| Os | 39 |
| Stock | 38 |
| Fiscal | 32 |
| Features | 28 |
| Inmetro | 25 |
| Quote | 23 |
| Fleet | 23 |
| Portal | 21 |
| RemainingModules | 19 |
| Quality | 19 |
| Email | 19 |
| Agenda | 18 |
| Iam | 16 |
| Technician | 14 |
| Equipment | 14 |
| ServiceCall | 12 |
| Security | 11 |
| Advanced | 11 |
| Integration | 9 |
| Lab | 8 |
| SystemImprovements | 7 |
| Customer | 7 |
| Finance | 6 |
| Catalog | 6 |
| Automation | 6 |
| Tenant | 5 |
| Product | 5 |
| Others | ~50 |

### Confirmed examples (spot-checked):

- `app/Http/Requests/Advanced/CompleteFollowUpRequest.php:11` — `return true`
- `app/Http/Requests/Advanced/StoreCollectionRuleAdvancedRequest.php:12` — `return true`
- `app/Http/Requests/Advanced/StoreCostCenterRequest.php:12` — `return true`
- `app/Http/Requests/Advanced/StoreCustomerDocumentRequest.php:11` — `return true`
- `app/Http/Requests/Advanced/StoreFollowUpRequest.php:18` — `return true`
- `app/Http/Requests/Advanced/StorePriceTableRequest.php:14` — `return true`
- `app/Http/Requests/Advanced/SubmitWorkOrderRatingRequest.php:11` — `return true`
- `app/Http/Requests/Advanced/UpdateCollectionRuleAdvancedRequest.php:12` — `return true`
- `app/Http/Requests/Advanced/UpdateCostCenterRequest.php` — `return true`
- `app/Http/Requests/Advanced/UpdateFollowUpRequest.php` — `return true`
- `app/Http/Requests/Advanced/UpdatePriceTableRequest.php` — `return true`
- `app/Http/Requests/Financial/StoreAccountPayableRequest.php:12` — `return true`
- `app/Http/Requests/Financial/UpdateAccountPayableRequest.php:12` — `return true`
- `app/Http/Requests/Financial/StoreAccountReceivableRequest.php:12` — `return true`
- `app/Http/Requests/Financial/UpdateAccountReceivableRequest.php:12` — `return true`
- `app/Http/Requests/Iam/ExportAuditLogRequest.php:11` — `return true`
- `app/Http/Requests/Iam/ListAuditLogRequest.php:11` — `return true`
- `app/Http/Requests/Os/DuplicateWorkOrderRequest.php:11` — `return true`
- `app/Http/Requests/Report/AccountingReportExportRequest.php:11` — `return true`
- `app/Http/Requests/Report/AccountingReportIndexRequest.php:11` — `return true`
- `app/Http/Requests/Report/BaseReportRequest.php:12` — `return true`
- `app/Http/Requests/Reports/TimesheetReportRequest.php:12` — `return true`

### Correctly implemented examples (for reference pattern):
- `app/Http/Requests/Advanced/StoreRoutePlanRequest.php` — `return $this->user()->can('route.plan.manage')`
- `app/Http/Requests/RepairSeal/*.php` — uses `$this->user()->can('repair_seals.use')` / `.manage`
- `app/Http/Requests/Analytics/StoreDataExportJobRequest.php` — `return (bool) $this->user()?->can('analytics.export.create')`

---

## 2. Index methods without pagination

Controllers whose `index()` does NOT use `paginate()` or `simplePaginate()`:

- `app/Http/Controllers/Api/V1/IntegrationHealthController.php` — returns non-paginated array (circuit breaker statuses; not a model list, acceptable)
- `app/Http/Controllers/Api/V1/ServiceCallController.php` — delegates entirely to `$this->service->index(...)`, pagination responsibility is in the service layer (needs service inspection)
- `app/Http/Controllers/Api/V1/UserFavoriteController.php` — uses `->pluck('favoritable_id')` (returns a flat array, no pagination)

### UserFavoriteController (confirmed violation):
```
// app/Http/Controllers/Api/V1/UserFavoriteController.php:15
$favorites = UserFavorite::where('user_id', $request->user()->id)
    ->where('favoritable_type', ...)
    ->pluck('favoritable_id'); // No pagination
return ApiResponse::data($favorites);
```

---

## 3. `exists` rules missing tenant_id scope (cross-tenant leakage risk)

These files use plain `'exists:table,id'` (string form) without a tenant constraint, on tenant-scoped tables:

| File | Line | Field | Table |
|------|------|-------|-------|
| `app/Http/Requests/Financial/StoreAccountPayableRequest.php` | 32 | `cost_center_id` | `cost_centers` |
| `app/Http/Requests/Financial/StoreAccountReceivableRequest.php` | 22 | `quote_id` | `quotes` |
| `app/Http/Requests/Financial/StoreAccountReceivableRequest.php` | 23 | `invoice_id` | `invoices` |
| `app/Http/Requests/Financial/StoreAccountReceivableRequest.php` | 35 | `cost_center_id` | `cost_centers` |
| `app/Http/Requests/Financial/UpdateAccountPayableRequest.php` | 32 | `cost_center_id` | `cost_centers` |
| `app/Http/Requests/Financial/UpdateAccountReceivableRequest.php` | 22 | `quote_id` | `quotes` |
| `app/Http/Requests/Financial/UpdateAccountReceivableRequest.php` | 23 | `invoice_id` | `invoices` |
| `app/Http/Requests/Financial/UpdateAccountReceivableRequest.php` | 35 | `cost_center_id` | `cost_centers` |
| `app/Http/Requests/Analytics/StoreDataExportJobRequest.php` | 20 | `analytics_dataset_id` | `analytics_datasets` |
| `app/Http/Requests/Iam/ExportAuditLogRequest.php` | 18 | `user_id` | `users` |
| `app/Http/Requests/Iam/ListAuditLogRequest.php` | 18 | `user_id` | `users` |
| `app/Http/Requests/Os/DuplicateWorkOrderRequest.php` | 19 | `new_customer_id` | `customers` |
| `app/Http/Requests/Os/ExportWorkOrderCsvRequest.php` | 21 | `assigned_to` | `users` |
| `app/Http/Requests/StoreUserCompetencyRequest.php` | 28 | `user_id` | `users` |
| `app/Http/Requests/StoreUserCompetencyRequest.php` | 29 | `equipment_id` | `equipments` |
| `app/Http/Requests/StoreUserCompetencyRequest.php` | 30 | `supervisor_id` | `users` |
| `app/Http/Requests/UpdateUserCompetencyRequest.php` | 29 | `equipment_id` | `equipments` |
| `app/Http/Requests/UpdateUserCompetencyRequest.php` | 30 | `supervisor_id` | `users` |
| `app/Http/Requests/Iam/StoreRoleRequest.php` | 39 | `permissions.*` | `permissions` |
| `app/Http/Requests/Iam/UpdateRoleRequest.php` | 38 | `permissions.*` | `permissions` |
| `app/Http/Requests/Tenant/BulkStatusTenantRequest.php` | 27 | `ids.*` | `tenants` |

Note: `users`, `permissions`, `tenants` tables may be intentionally global — evaluate case by case.

---

## 4. `tenant_id` / `created_by` exposed in FormRequest rules

**No violations found.** The grep for `'tenant_id'` inside `rules()` arrays returned nothing — tenant_id is not being exposed in FormRequest `rules()`. The occurrences of `tenant_id` in FormRequests are only inside `Rule::exists(...)->where('tenant_id', ...)` constraints (correct pattern).

`created_by` was also not found in any FormRequest `rules()` array.

---

## 5. Controllers using `$request->all()` (unvalidated input)

`CrmController` uses a pattern of passing `$request->all()` as fallback throughout all methods:

```php
// app/Http/Controllers/Api/V1/CrmController.php:67,83,90,105,111,123,132,139,159,169,179
method_exists($request, "validated") ? $request->validated() : $request->all()
```

This pattern allows unvalidated raw input to reach the service layer whenever the request does not have a `validated()` method (i.e., plain `Request` objects). This is effectively a bypass of validation.

---

## 6. UserCompetencyController — additional issues

File: `app/Http/Controllers/UserCompetencyController.php`

- **Wrong namespace**: Lives in `App\Http\Controllers` (not `Api\V1`) — inconsistent with the rest of the API
- **`clone` misuse on line 17**: `clone UserCompetency::with(...)` — `clone` is applied to a Builder/Collection, not a model instance; this is semantically wrong (likely harmless but incorrect)
- **Response format inconsistency**: Uses `response()->json()` directly instead of `ApiResponse::paginated()` / `ApiResponse::data()` (inconsistent with all other controllers)
- **FormRequest `exists` rules missing tenant scope**: `StoreUserCompetencyRequest` line 28-30 uses plain `exists:users,id`, `exists:equipments,id` without tenant filter

---

## 7. Summary of critical violations

| Category | Count | Severity |
|----------|-------|----------|
| `authorize()` returning `true` unconditionally | 704 FormRequests | CRITICAL |
| `exists` rules without tenant_id scope | 18+ fields | HIGH |
| Index without pagination | 1 confirmed (UserFavoriteController) | MEDIUM |
| `$request->all()` fallback bypassing validation | 1 controller (CrmController, 10+ methods) | HIGH |
| Inconsistent response format | 1 controller (UserCompetencyController) | LOW |
| Wrong namespace placement | 1 controller (UserCompetencyController) | LOW |

---

## 8. Recommended remediation priority

1. **[CRITICAL]** Fix `authorize()` across all 704 FormRequests — add `$this->user()->can('permission.name')` per module/action
2. **[HIGH]** Fix `exists:quotes,id`, `exists:invoices,id`, `exists:cost_centers,id`, `exists:customers,id` in Financial and Os FormRequests to use `Rule::exists(...)->where('tenant_id', $tenantId)`
3. **[HIGH]** Remove `$request->all()` fallback from `CrmController` — use proper typed FormRequests for every endpoint
4. **[MEDIUM]** Add pagination to `UserFavoriteController::index()`
5. **[LOW]** Move `UserCompetencyController` to `App\Http\Controllers\Api\V1`, fix response format to use `ApiResponse`
