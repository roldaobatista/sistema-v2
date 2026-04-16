# Adversarial Audit: Models ↔ Migrations ↔ Schema
**Kalibrium ERP (Laravel 13, multi-tenant)**
**Date:** 2026-04-10 | **Schema Generated:** 2026-04-10 17:18:43

---

## Executive Summary

Comprehensive audit of 383 models against 524 schema tables and 450 migrations revealed **47 real inconsistencies** across two severity tiers. Primary issue: **SoftDeletes trait-schema mismatch** (45 instances) and **missing BelongsToTenant trait** (2 critical models).

**Severity Breakdown:**
- **P0 (Critical):** 2 findings
- **P1 (High):** 45 findings
- **P2 (Medium):** 0 findings

---

## P0 - Critical Issues

### 1. User.php: Missing BelongsToTenant Trait
- **File:** `C:\PROJETOS\sistema\backend\app\Models\User.php`
- **Issue:** Model has `tenant_id` in fillable array but does **not** use `BelongsToTenant` trait
- **Evidence:**
  - Extends `Authenticatable`
  - Fillable contains `'tenant_id'`
  - Missing: `use App\Concerns\BelongsToTenant;`
  - No tenant scoping queries
- **Impact:** Multi-tenant isolation not enforced; users can access cross-tenant data
- **Fix:** Add `use BelongsToTenant;` to User class definition

### 2. Role.php: Missing BelongsToTenant Trait
- **File:** `C:\PROJETOS\sistema\backend\app\Models\Role.php`
- **Issue:** Extends SpatieRole with `tenant_id` fillable but no `BelongsToTenant` trait
- **Evidence:**
  - Extends `Spatie\Permission\Models\Role`
  - Contains `'tenant_id'` in fillable
  - No `BelongsToTenant` import
- **Impact:** RBAC not scoped to tenant; admin roles bypass tenant boundaries
- **Fix:** Add `use BelongsToTenant;` trait to Role definition

---

## P1 - High Priority Issues (Top 8 of 45)

### SoftDeletes Trait ↔ Schema Mismatch

Models declaring `SoftDeletes` trait but schema missing `deleted_at` column:

1. **AccountPayable.php** — SoftDeletes declared; schema has NO `deleted_at`
2. **AccountPayableCategory.php** — SoftDeletes declared; schema missing
3. **AccountReceivable.php** — SoftDeletes declared; schema missing
4. **AgendaItem.php** — SoftDeletes declared; schema missing (central_items table)
5. **Batch.php** — SoftDeletes declared; schema missing
6. **CrmTerritory.php** — SoftDeletes declared; schema missing
7. **ExpenseCategory.php** — SoftDeletes declared; schema missing
8. **Fleet.php** — INVERSE: Schema has `deleted_at` but model has NO `SoftDeletes` trait

**Root Cause:** Likely incomplete migration during phase deployments (e.g., 2026_02_08_100001_add_soft_deletes_to_schedules_and_time_entries.php only targeted some models).

**Impact:**
- Soft-delete queries will silently exclude "deleted" records that were never marked
- Restore operations impossible for deleted soft-delete records
- Data integrity loss if delete operations are retried

---

## Additional Findings

### Models with Non-Standard Tenant Usage
7 models use `current_tenant_id` property instead of relying on trait scoping:
- AgendaItem.php
- AuditLog.php
- Equipment.php
- (4 others)

**Impact:** Inconsistent multi-tenant behavior; increases audit complexity.

### Schema vs Migration Alignment
- **Models:** 383
- **Schema Tables:** 524
- **Ratio:** 1:1.37 (141 more tables than models — likely pivot/intermediate tables)
- **Latest Migration:** 2026_04_10_500000_fix_production_schema_drifts.php
- **Schema Generated:** 2026-04-10 17:18:43 (same day; relatively fresh)

---

## Detailed Findings by Category

### 1. BelongsToTenant Compliance
- **Total models scanned:** 383
- **Missing trait:** 2 (User, Role)
- **Compliance rate:** 99.5% ✓

### 2. SoftDeletes Compliance
- **Trait-Schema mismatches:** 45
- **Trait without column:** 44 models
- **Column without trait:** 1 model (Fleet.php)
- **Compliance rate:** 88.3%

### 3. Fillable vs Migration
- **Sample spot-check:** AccountPayable, AccountReceivable, Schedule
- **Mismatches detected:** 0
- **Status:** ✓ OK (fully compliant in sample)

### 4. Convention Adherence
- **Checked:** `created_by` vs `user_id` (expenses), `technician_id` vs `user_id` (schedules)
- **Violations:** 0
- **Status:** ✓ Conventions observed

### 5. Foreign Keys & Indexes
- **Total foreign keys defined in schema:** 0 (parsed constraint format differs in SQLite dump)
- **Status:** Unable to parse; requires manual schema.sql review

---

## Recommendations

### Immediate (P0)
1. **User.php:** Add `use BelongsToTenant;` to class definition
2. **Role.php:** Add `use BelongsToTenant;` trait

### Short-term (P1)
3. **SoftDeletes audit:** Create migration to:
   - Add `deleted_at` columns to 44 models with SoftDeletes trait
   - OR remove SoftDeletes trait from models that don't need soft-delete (re-evaluate Fleet.php)
4. **Refactor:** Consolidate tenant scoping — remove `current_tenant_id` properties, rely entirely on BelongsToTenant trait

### Ongoing
5. **CI/CD:** Add pre-deployment audit step checking:
   - All models with `tenant_id` fillable MUST use BelongsToTenant
   - All models with SoftDeletes MUST have `deleted_at` in migration
   - Schema dump regenerated after every migration run

---

## Files for Review

- `C:\PROJETOS\sistema\backend\app\Models\User.php` (Line ~1-50)
- `C:\PROJETOS\sistema\backend\app\Models\Role.php` (Line ~1-50)
- `C:\PROJETOS\sistema\backend\database\migrations\2026_02_08_100001_add_soft_deletes_to_schedules_and_time_entries.php`
- `C:\PROJETOS\sistema\backend\database\schema\sqlite-schema.sql` (verify foreign key syntax)

---

**Audit Completed:** 2026-04-10 | **Scope:** Full codebase audit with spot-check validation
