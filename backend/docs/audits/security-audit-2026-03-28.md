# Security Audit Report — Kalibrium ERP Backend
**Date:** 2026-03-28
**Auditor:** Claude (automated static analysis)
**Scope:** Routes, Middleware, Permissions, Policies, SQL Injection, Mass Assignment

---

## SUMMARY

| Severity | Count |
|----------|-------|
| CRITICAL | 2 |
| HIGH     | 5 |
| MEDIUM   | 4 |
| LOW/INFO | 5 |

---

## CRITICAL

### [C-1] SQL Injection via `DB::raw` with unescaped user-supplied value
**File:** `backend/app/Http/Controllers/Api/V1/SupplierPortal/SupplierPortalController.php` **line 81**

```php
'total' => DB::raw("quantity * {$itemData['unit_price']}"),
```

`unit_price` comes from `$request->validated()` and is validated as `numeric|min:0`. However, PHP's `numeric` validator **does not prevent SQL injection** — values like `1 OR 1=1` are not numeric but edge cases like expressions or very large floats can produce unexpected SQL. More critically, **interpolating any variable directly into `DB::raw()` is a SQL injection pattern** — if validation is ever relaxed or bypassed upstream, this is a direct injection vector.

**Fix:** Use a binding or do the multiplication in PHP:
```php
'total' => $itemData['unit_price'] * $item->quantity,
// OR:
'total' => DB::raw('quantity * ?', [(float) $itemData['unit_price']]),
// NOTE: DB::raw does not accept bindings — use a raw expression with cast:
'total' => DB::raw('quantity * ' . (float) $itemData['unit_price']),
```
The correct approach is to compute `total` in PHP:
```php
'total' => round((float) $itemData['unit_price'] * $item->quantity, 4),
```

---

### [C-2] `modules-extra.php` — Three resource groups registered with NO `check.permission` middleware
**File:** `backend/routes/api/modules-extra.php` **lines 11–30**

```php
Route::prefix('helpdesk')->group(function () {
    Route::apiResource('ticket-categories', TicketCategoryController::class);      // NO permission
    Route::apiResource('escalation-rules', EscalationRuleController::class);        // NO permission
    Route::apiResource('sla-violations', SlaViolationController::class)->only([...]); // NO permission
});

Route::prefix('contracts')->group(function () {
    Route::apiResource('measurements', ContractMeasurementController::class);       // NO permission
    Route::apiResource('addendums', ContractAddendumController::class);             // NO permission
});

Route::prefix('procurement')->group(function () {
    Route::apiResource('suppliers', SupplierController::class);                     // NO permission
    Route::apiResource('material-requests', MaterialRequestController::class);      // NO permission
    Route::apiResource('purchase-quotations', PurchaseQuotationController::class);  // NO permission
});
```

These routes inherit `auth:sanctum` and `check.tenant` (via bootstrap/app.php), so they require a valid authenticated user. However, **any authenticated user of any role can perform full CRUD** on these resources — no granular permission check exists at the route level, and there is no indication the controllers themselves enforce permissions internally.

**Fix:** Wrap each group with `Route::middleware('check.permission:...')` matching the appropriate permissions already defined in PermissionsSeeder.

---

## HIGH

### [H-1] WhatsApp internal webhook routes have NO signature verification
**File:** `backend/routes/api.php` **lines 81–84**

```php
Route::prefix('webhooks')->middleware('throttle:120,1')->group(function () {
    Route::post('whatsapp/status', [WhatsAppWebhookController::class, 'handleStatus']);
    Route::post('whatsapp/messages', [WhatsAppWebhookController::class, 'handleMessage']);
});
```

These routes are fully public (inside `Route::prefix('v1')` but outside any `auth:sanctum` or `verify.webhook` group). The controller does perform its own HMAC validation internally (using `X-Hub-Signature-256`), which partially mitigates this. However, **the verification is controller-internal, not middleware-enforced** — if the controller logic is refactored or a new method added, signature checks can be forgotten. The `verify.webhook` middleware already exists and should be applied here.

Compare with line 96 where the same `whatsapp` route IS protected:
```php
Route::prefix('webhooks')->middleware(['verify.webhook', 'throttle:240,1'])->group(function () {
    Route::post('whatsapp', [CrmMessageController::class, 'webhookWhatsApp']); // ✓ protected
});
```

**Fix:** Apply `verify.webhook` or a dedicated WhatsApp HMAC middleware to lines 81–84.

---

### [H-2] `supplier_portal.php` loaded with `['api']` middleware only — NO `auth:sanctum`, NO `check.tenant`
**File:** `backend/bootstrap/app.php` **lines 29–31**; `backend/routes/api/supplier_portal.php`

```php
$middleware = basename($file) === 'supplier_portal.php'
    ? ['api']                                         // intentional bypass
    : ['api', 'auth:sanctum', 'check.tenant'];
```

This is intentional (token-based public portal), but creates a **duplicate route problem**: `supplier_portal.php` registers both:
- `GET api/v1/supplier-portal/quotations/{token}` (lines 9–10)
- `GET api/v1/portal/supplier/quotations/{token}` (lines 15–16)

Both point to the exact same controller methods. The duplicate creates confusion and an unnecessary attack surface. The controller correctly validates the `PortalGuestLink` token, but the duplication should be documented clearly or one removed.

---

### [H-3] 704 FormRequest files with `return true` in `authorize()` — permission delegated entirely to route middleware
**File:** `backend/app/Http/Requests/**` (704 occurrences)

This is a **systemic pattern**: FormRequests are used purely for validation, with permission checks done at the route middleware level via `check.permission`. This is acceptable when every route has `check.permission` applied. However:

1. It is fragile — if any route is added without `check.permission` (see C-2 above), the FormRequest provides zero protection.
2. Any `return true` FormRequest used by a route in `modules-extra.php` (C-2) means **zero authorization at any layer**.

**Fix:** At minimum, FormRequests for non-public routes should verify `$this->user() !== null`. For sensitive write operations, add explicit `$this->user()->can(...)` checks as a defense-in-depth layer.

---

### [H-4] `periodExpression()` interpolates column names into raw SQL without sanitization
**File:** `backend/app/Http/Controllers/Api/V1/CashFlowController.php` **line 29–32**

```php
private function periodExpression(string $column): string
{
    return DB::getDriverName() === 'sqlite'
        ? "strftime('%Y-%m', {$column})"
        : "DATE_FORMAT({$column}, '%Y-%m')";
}
```

The `$column` parameter is passed from 14 internal call sites in the same controller, all with hardcoded string literals (e.g., `'payment_date'`, `'due_date'`). No user input reaches this function directly, so this is **not currently exploitable**. However, it is an unsafe pattern — if a future developer passes a user-controlled value (e.g., a sortable column name from a query parameter), it becomes SQL injection.

**Fix:** Add an allowlist: `$allowed = ['payment_date', 'due_date', 'expense_date', ...]; if (!in_array($column, $allowed)) throw new \InvalidArgumentException(...)`.

---

### [H-5] `VerifyWebhookSignature` and `VerifyFiscalWebhookSecret` bypass in non-production environments
**Files:** `backend/app/Http/Middleware/VerifyWebhookSignature.php` **line 24**; `backend/app/Http/Middleware/VerifyFiscalWebhookSecret.php` **line 24**

```php
if (!$expected) {
    if (app()->environment('production')) {
        return response()->json(['message' => 'Webhook secret not configured'], 500);
    }
    return $next($request);  // passes through unchecked in staging/local
}
```

If `WHATSAPP_WEBHOOK_SECRET` / `FISCAL_WEBHOOK_SECRET` is missing in staging or CI, webhooks are accepted without any verification. This is a security risk if staging shares tenant data with production, or if CI is Internet-exposed.

**Fix:** Block in all environments when secret is not configured, or at least in `staging`:
```php
if (!app()->environment('local', 'testing')) {
    return response()->json(['message' => 'Webhook secret not configured'], 500);
}
```

---

## MEDIUM

### [M-1] `WorkOrderSignatureController` lives outside the `Api/V1` namespace
**File:** `backend/app/Http/Controllers/WorkOrderSignatureController.php`
**Route reference:** `backend/routes/api/work-orders.php` **lines 106–107**

```php
use App\Http\Controllers\WorkOrderSignatureController;  // root Controllers namespace
```

All other controllers use `App\Http\Controllers\Api\V1\*`. This controller was placed in the wrong directory. It works functionally, but breaks namespace consistency and may be missed in security reviews or middleware audits.

**Fix:** Move to `backend/app/Http/Controllers/Api/V1/Os/WorkOrderSignatureController.php` and update the route.

---

### [M-2] `EnsureTenantScope`: when `tenantId <= 0`, safe endpoints bypass ALL tenant validation
**File:** `backend/app/Http/Middleware/EnsureTenantScope.php` **lines 30–34**

```php
if ($tenantId <= 0) {
    if ($isMeOrMyTenants) {
        app()->instance('current_tenant_id', 0);  // sets tenant to 0
        return $next($request);                    // passes through
    }
    return response()->json(['message' => 'Nenhuma empresa selecionada.'], 403);
}
```

Setting `current_tenant_id` to `0` and then calling `$next($request)` means that controllers on "safe" endpoints receive a tenant context of `0`. If any of those controllers query models with `BelongsToTenant` scope and `tenant_id = 0`, unexpected data leakage (across tenants with ID=0) is theoretically possible. The `isTenantSelectionSafeEndpoint()` method determines which endpoints are "safe" — this method should be audited carefully to ensure it only covers `/me` and `/my-tenants` type routes.

---

### [M-3] `AgendaItem` and related models use `$guarded = ['id']` instead of explicit `$fillable`
**File:** `backend/app/Models/AgendaItem.php` **line 75** and related models:
- `AgendaItemComment`, `AgendaItemHistory`, `AgendaItemWatcher`, `AgendaNotificationPreference`, `AgendaTemplate`

`$guarded = ['id']` means all other fields are mass-assignable by default. If `tenant_id` or `created_by` is in the model's table and not explicitly excluded, a malicious client could submit these via a FormRequest that uses `$request->all()` or `$request->validated()` without manually stripping them.

Per project rules: "tenant_id and created_by MUST be assigned in the controller — PROHIBITED to expose as FormRequest fields."
Review each controller that uses these models to confirm `tenant_id` is never taken from request input.

---

### [M-4] Duplicate login routes with different paths
**File:** `backend/routes/api.php` **lines 47–48**

```php
Route::middleware('throttle:10,1')->post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:10,1')->post('/auth/login', [AuthController::class, 'login']); // compat: legacy client
```

Two public login endpoints exist (`/api/v1/login` and `/api/v1/auth/login`). Rate limiting is applied independently to each path, effectively **doubling the brute-force allowance** (20 attempts/minute instead of 10). An attacker aware of both paths can abuse both.

**Fix:** Apply a shared rate limiter keyed on IP (not path), or remove the legacy alias once no longer needed.

---

## LOW / INFORMATIONAL

### [L-1] `Gate::before` grants all abilities to `super_admin` at Gate level
**File:** `backend/app/Providers/AppServiceProvider.php` **line 82**

```php
Gate::before(function ($user, $ability) {
    if ($user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
        return true;
    }
});
```

This is the Spatie-recommended pattern. Documented and intentional. However, it means that if a `super_admin` token is compromised, the attacker has full system access. Ensure `super_admin` accounts require additional authentication factors and are tightly controlled.

---

### [L-2] `InjectBearerFromCookie` reads auth token from cookie for ALL `/api/*` routes
**File:** `backend/app/Http/Middleware/InjectBearerFromCookie.php**

This runs as a global prepend middleware. It injects a Bearer token from a cookie named `auth_token` into the Authorization header for any unauthenticated API request. While this supports httpOnly cookie auth, it means **public webhook endpoints also go through this injection**, potentially allowing cookie-authenticated access to webhook routes that are meant to be signature-only. Low risk in practice since the Sanctum guard will still reject invalid tokens, but worth noting.

---

### [L-3] `Security-Content-Policy` (CSP) header is missing from `SecurityHeaders` middleware
**File:** `backend/app/Http/Middleware/SecurityHeaders.php**

The middleware sets `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and `HSTS` — but **no `Content-Security-Policy` header**. For an API-only backend this is lower risk, but if any HTML is served (e.g., Telescope, Horizon), CSP should be set.

---

### [L-4] `health` route and `/up` route are publicly accessible with no auth
**File:** `backend/routes/api.php` **line 42**; `backend/bootstrap/app.php` **line 24**

```php
Route::get('/health', HealthCheckController::class);  // public
health: '/up',                                         // Laravel built-in, public
```

Both are intentionally public but expose system uptime. Ensure `HealthCheckController` does not leak sensitive information (DB credentials, internal IPs, stack traces).

---

### [L-5] Permission naming inconsistency across modules
**File:** `backend/database/seeders/PermissionsSeeder.php`

Multiple naming conventions are used for essentially the same concept:
- `cadastros.customer.view` vs `customer.document.view` (mixed `module.entity.action` formats)
- `estoque.view` / `estoque.manage` (aggregate) vs `estoque.movement.view` / `estoque.used_stock.view` (granular) — bridged via `PERMISSION_ALIASES` in `CheckPermission`
- `financeiro.*` vs `finance.*` (Portuguese vs English prefix for the same domain)
- `os.*` vs `service_calls.*` vs `chamados.*` — three prefixes for work order/service call concepts

This causes the `PERMISSION_ALIASES` map in `CheckPermission.php` to grow indefinitely. Recommend a formal permission naming standard and a migration plan to consolidate.

---

## FILES AUDITED

| File | Status |
|------|--------|
| `backend/routes/api.php` | Audited |
| `backend/routes/api/*.php` (26 files) | Audited |
| `backend/bootstrap/app.php` | Audited |
| `backend/app/Http/Middleware/EnsureTenantScope.php` | Audited |
| `backend/app/Http/Middleware/CheckPermission.php` | Audited |
| `backend/app/Http/Middleware/EnsurePortalAccess.php` | Audited |
| `backend/app/Http/Middleware/VerifyWebhookSignature.php` | Audited |
| `backend/app/Http/Middleware/VerifyFiscalWebhookSecret.php` | Audited |
| `backend/app/Http/Middleware/InjectBearerFromCookie.php` | Audited |
| `backend/app/Http/Middleware/SecurityHeaders.php` | Audited |
| `backend/app/Http/Middleware/CheckReportExportPermission.php` | Audited |
| `backend/database/seeders/PermissionsSeeder.php` | Audited |
| `backend/app/Policies/*.php` (all) | Audited |
| `backend/app/Providers/AppServiceProvider.php` (Gate section) | Audited |
| `backend/app/Http/Controllers/Api/V1/SupplierPortal/SupplierPortalController.php` | Audited |
| `backend/app/Http/Controllers/Api/V1/CashFlowController.php` | Audited |
| `backend/app/Http/Controllers/Api/V1/Webhook/WhatsAppWebhookController.php` | Audited |
| `backend/app/Http/Requests/SupplierPortal/AnswerSupplierQuotationRequest.php` | Audited |
| `backend/app/Models/User.php` (tenant fields) | Audited |

---

## WHAT IS WORKING WELL

- `EnsureTenantScope` correctly validates tenant access via `hasTenantAccess()` and caches tenant status.
- `EnsurePortalAccess` correctly checks `instanceof ClientPortalUser`, token ability, `is_active`, and sets `current_tenant_id`.
- All Policies consistently check `$user->current_tenant_id !== $model->tenant_id` for cross-tenant isolation.
- `VerifyWebhookSignature` uses `hash_equals` (timing-safe) correctly.
- `BelongsToTenant` global scope is used consistently across models.
- The bootstrap `then` hook correctly applies `['api', 'auth:sanctum', 'check.tenant']` to all modular route files (except the intentional `supplier_portal.php`).
- `CheckPermission` middleware handles missing permissions gracefully and has an aliases system for legacy backward compatibility.
- All models define either `$fillable` or `$guarded` — no unprotected models found.
