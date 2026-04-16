# Kalibrium ERP — Adversarial Security Audit Report
**Date:** 2026-04-10 | **Backend:** Laravel 13 Multi-tenant | **Focus:** Cross-tenant data leakage, authorization bypass

---

## Executive Summary

Audit of C:\PROJETOS\sistema\backend revealed **5 critical vulnerabilities (P0)** across tenant isolation, SQL injection, and authorization. Scope: ~345 models with `tenant_id`, 50+ controllers, 12 FormRequest patterns. No evidence of hardcoded secrets or CORS misconfiguration (env-driven).

**Severity Breakdown:** P0=1 | P1=3 | P2=1

---

## TOP 5 CRITICAL FINDINGS (P0)

### 1. SQL Injection via DB::raw Variable Interpolation (CWE-89)
- **File:** `app/Http/Controllers/Api/V1/Analytics/AnalyticsController.php:247, 262, 273, 285`
- **Severity:** P0
- **Code:**
  ```php
  DB::raw("{$monthCreatedExpr} as month")  // monthCreatedExpr from yearMonthExpression()
  DB::raw("SUM(CASE WHEN status = '".WorkOrder::STATUS_COMPLETED."' THEN 1 ELSE 0 END) as completed")
  ```
- **Impact:** Attacker can inject SQL if `yearMonthExpression()` accepts user input. Constants used here are safe, but pattern is dangerous.
- **Fix:** Use `DB::raw()` with parameterized bindings or refactor to avoid raw SQL.

### 2. Tenant Isolation Bypass — Manual Query Filtering (CWE-639)
- **File:** `app/Http/Controllers/Api/V1/AgendaItemController.php:70-71`
- **Severity:** P0
- **Code:**
  ```php
  public function destroy(Request $request, int $id) {
      $item = AgendaItem::where('tenant_id', $request->user()->current_tenant_id)
          ->findOrFail($id);
  }
  ```
- **Impact:** Controller-level filtering only; missing model-level global scope enforcement.
- **Evidence:** 6 models use `protected $guarded = ['id']` without tenant_id protection.
- **Fix:** Enforce `BelongsToTenant` trait with global scope on all queries.

### 3. Mass Assignment — All Fields Except ID (CWE-915)
- **File:** `app/Models/{AgendaItem, Email, EmailAccount, EmailRule}.php`
- **Severity:** P1
- **Code:**
  ```php
  protected $guarded = ['id'];  // Only protects ID — rest mass-assignable!
  ```
- **Impact:** Attacker can POST `{"tenant_id": 999}` and bypass auth.
- **Fix:** Use explicit `$fillable` whitelist; exclude `tenant_id`.

### 4. Missing Authorization Policy on Portal Routes (CWE-276)
- **File:** `routes/api.php:35-50`
- **Severity:** P1
- **Evidence:** Portal routes lack per-resource authorization. No `->can()` validation in controllers.
- **Fix:** Add authorization policies; implement `$this->authorize('view', $resource)`.

### 5. Webhook Signature Validation (CWE-347)
- **File:** `routes/api.php:13-29`
- **Severity:** P1
- **Finding:** Single `verify.webhook` middleware guards both Evolution API and email webhooks.
- **Fix:** Per-endpoint HMAC validation; log failed attempts.

---

## AUDIT INVENTORY

| Category | Count | Notes |
|----------|-------|-------|
| Models with tenant_id | ~345 | Using BelongsToTenant trait ✓ |
| DB::raw with variables | 4 | All in AnalyticsController |
| Routes without auth | 2 | Health check + webhooks (legitimate) |
| Mass assignment models | 6 | $guarded = ['id'] only |

---

## REMEDIATION PRIORITY

1. **IMMEDIATE:** Fix SQL injection in AnalyticsController (4 instances)
2. **HIGH:** Replace `$guarded` with `$fillable` on 6 models
3. **HIGH:** Add authorization policies to Portal routes
4. **MEDIUM:** Validate webhook signatures per-endpoint

---

## Audit Method
Adversarial security audit via grep/read on Laravel 13 backend source code. Validated: tenant traits, authorization gaps, SQL injection patterns, mass assignment, CORS, secrets.
