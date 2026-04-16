# Frontend-Backend Contract Audit Report
**Date:** 2026-03-28
**Status:** Findings only — no code changes

---

## 1. MISSING API LIB FILE

### `frontend/src/lib/fleet-api.ts` — DOES NOT EXIST

The entire Fleet module frontend calls `api` directly from page components (inline in `FleetPage.tsx` and tab components). There is no centralized `fleet-api.ts` file like there is for all other modules.

- Affected files: `frontend/src/pages/fleet/FleetPage.tsx`, `FleetAccidentsTab.tsx`, `FleetDashboardTab.tsx`, `FleetFinesTab.tsx`, `FleetFuelTab.tsx`, `FleetInspectionsTab.tsx`, `FleetInsuranceTab.tsx`, `FleetPoolTab.tsx`, `FleetTiresTab.tsx`, `DriverScoreTab.tsx`
- All fleet API calls are made inline with no typed wrappers, no centralized error handling, and no TypeScript generics on responses.

---

## 2. FIELD NAME MISMATCHES — `FleetVehicle` Type vs Backend Model

**File:** `frontend/src/types/fleet.ts` vs `backend/app/Models/FleetVehicle.php`

| Frontend field | Backend field | Issue |
|---|---|---|
| `license_plate` | `plate` | Frontend has BOTH `plate` and `license_plate` as aliases — backend only has `plate` |
| `current_mileage_km` | does not exist | Frontend has this field — backend has only `odometer_km` |
| `assignedUser` | `assignedUser` (relation, user id is `assigned_user_id`) | Frontend uses `assignedUser` correctly but also has `assigned_driver` — no such relation in backend |
| `assigned_driver` | does not exist | Backend has `assignedUser` relation only |
| `tire_change_date` | `tire_change_date` | Missing in frontend type |
| `avg_fuel_consumption` | `avg_fuel_consumption` | Missing in frontend type |
| `cnh_expiry_driver` | `cnh_expiry_driver` | Missing in frontend type |
| `assigned_user_id` | `assigned_user_id` | Missing in frontend type (FK exposed) |

Backend `Fleet` model (the old one) has `mileage` field. Frontend type references both `odometer_km` and `current_mileage_km` — neither matches the old model's `mileage`. The current model (`FleetVehicle`) uses `odometer_km`, which the frontend also has, but `current_mileage_km` has no backend counterpart.

---

## 3. MISSING FIELDS IN FRONTEND TYPES vs BACKEND FormRequests/Resources

### `AccountReceivable` — `frontend/src/types/financial.ts`

Backend `StoreAccountReceivableRequest` validates these fields that are ABSENT from the frontend `AccountReceivable` interface and `AccountReceivableResource`:
- `quote_id` — in FormRequest, NOT in Resource output, NOT in frontend type
- `cost_center_id` — in FormRequest, NOT in Resource output, NOT in frontend type
- `penalty_amount` — in FormRequest, NOT in Resource output, NOT in frontend type
- `interest_amount` — in FormRequest, NOT in Resource output, NOT in frontend type
- `discount_amount` — in FormRequest, NOT in Resource output, NOT in frontend type

These fields can be submitted (backend accepts them) but the Resource never returns them, so frontend can't display them after creation.

### `AccountPayable` — `frontend/src/types/financial.ts`

Backend `StoreAccountPayableRequest` validates `cost_center_id` but:
- `AccountPayableResource` does NOT include `cost_center_id` in `toArray()`
- `frontend/src/types/financial.ts` `AccountPayable` interface does NOT have `cost_center_id`

### `AccountPayable` relation naming inconsistency

`AccountPayableResource` returns:
- `$arr['supplier']` (from `supplierRelation`)
- `$arr['category']` (from `categoryRelation`)

But `frontend/src/types/financial.ts` `AccountPayable` interface has BOTH:
- `supplier` (correct)
- `supplier_relation` (never returned by resource)
- `category_relation` (never returned by resource)

The resource returns `supplier` and `category`, but the frontend type keeps both the old `supplier_relation`/`category_relation` aliases and the correct `supplier`. This causes confusion and potentially dead code.

---

## 4. MISSING HR ROUTES — Most hr-api.ts Endpoints Have No Backend Route

**File:** `frontend/src/lib/hr-api.ts` calls many endpoints. After checking all backend route files, the following endpoints are confirmed missing:

| Frontend endpoint | Backend route found? |
|---|---|
| `POST /hr/advanced/clock-in` | NOT FOUND in any route file |
| `POST /hr/advanced/clock-out` | NOT FOUND |
| `GET /hr/advanced/clock/status` | NOT FOUND |
| `GET /hr/advanced/clock/history` | NOT FOUND |
| `GET /hr/advanced/clock/pending` | NOT FOUND |
| `POST /hr/advanced/clock/{id}/approve` | NOT FOUND |
| `POST /hr/advanced/clock/{id}/reject` | NOT FOUND |
| `GET /hr/adjustments` | NOT FOUND |
| `POST /hr/adjustments` | NOT FOUND |
| `POST /hr/adjustments/{id}/approve` | NOT FOUND |
| `POST /hr/adjustments/{id}/reject` | NOT FOUND |
| `GET /hr/geofences` | NOT FOUND |
| `POST /hr/geofences` | NOT FOUND |
| `PUT /hr/geofences/{id}` | NOT FOUND |
| `DELETE /hr/geofences/{id}` | NOT FOUND |
| `GET /hr/journey-rules` | NOT FOUND |
| `POST /hr/journey-rules` | NOT FOUND |
| `GET /hr/journey-entries` | NOT FOUND |
| `POST /hr/journey/calculate` | NOT FOUND |
| `GET /hr/hour-bank/balance` | NOT FOUND |
| `GET /hr/hour-bank/transactions` | NOT FOUND |
| `GET /hr/fiscal/afd` | NOT FOUND |
| `GET /hr/fiscal/aep/{userId}/{year}/{month}` | NOT FOUND |
| `GET /hr/fiscal/integrity` | NOT FOUND |
| `GET /hr/holidays` | NOT FOUND |
| `POST /hr/holidays` | NOT FOUND |
| `POST /hr/holidays/import-national` | NOT FOUND |
| `GET /hr/leaves` | NOT FOUND |
| `POST /hr/leaves` | NOT FOUND |
| `POST /hr/leaves/{id}/approve` | NOT FOUND |
| `POST /hr/leaves/{id}/reject` | NOT FOUND |
| `GET /hr/vacation-balances` | NOT FOUND |
| `GET /hr/documents` | NOT FOUND |
| `POST /hr/documents` | NOT FOUND |
| `GET /hr/onboarding/templates` | NOT FOUND |
| `POST /hr/onboarding/start` | NOT FOUND |
| `GET /hr/payroll` | NOT FOUND |
| `POST /hr/payroll` | NOT FOUND |
| `POST /hr/payroll/{id}/calculate` | NOT FOUND |
| `POST /hr/payroll/{id}/approve` | NOT FOUND |
| `POST /hr/payroll/{id}/mark-paid` | NOT FOUND |
| `GET /hr/my-payslips` | NOT FOUND |
| `GET /hr/payslips/{id}` | NOT FOUND |
| `GET /hr/rescissions` | NOT FOUND |
| `POST /hr/rescissions` | NOT FOUND |

Only `POST /hr/advanced/break-start` and `POST /hr/advanced/break-end` were confirmed in `routes/api/advanced-lots.php`. All other HR routes are missing. The audit note in `missing-routes.php` explicitly states these were "STUB removido (usar HRController)" — but no HRController routes exist in any route file.

---

## 5. PAGINATION HANDLING — Partial Coverage

`financial-api.ts` manually adds `current_page`, `last_page`, `total` to response type but does NOT use the `normalizeResponseData` utility from `api.ts` which already handles pagination normalization. This is inconsistent with other modules.

Example:
```typescript
// financial-api.ts line 7
api.get<{ data: AccountReceivable[]; current_page?: number; last_page?: number; total?: number }>
```

The `api.ts` base client has `normalizeResponseData<T>` that lifts pagination meta. Some API clients rely on it, others manually type it — no standard pattern enforced.

---

## 6. `Record<string, unknown>` in stockApi — No Typed Payloads

**File:** `frontend/src/lib/stock-api.ts`

All create/update functions use `Record<string, unknown>` for payloads:
```typescript
create: (data: Record<string, unknown>) => api.post('/warehouses', data)
update: (id: number, data: Record<string, unknown>) => api.put(`/warehouses/${id}`, data)
```

No TypeScript types for stock movement creation, warehouse creation/update, or transfer payloads. Backend FormRequests for these endpoints have defined validation rules that are not reflected in frontend types.

---

## 7. `any` Usage — None Found in Production lib/types Files

The grep for `: any`, `as any`, `any[]`, `Record<string, any>` in `frontend/src/lib/` and `frontend/src/types/` returned **no results**. All `Record<string, any>` occurrences are confined to test files (`__tests__/`) which is acceptable.

---

## 8. ERROR HANDLING — No Issues in lib Files

The base `api.ts` client has:
- Global Axios response interceptor with `toast` for errors
- Automatic retry for 502/503/504 (2 retries with 1s delay)
- Session clearing on 401

Individual API lib files (`financial-api.ts`, `work-order-api.ts`, etc.) properly delegate error handling to the base client. No missing try/catch issues found at the lib layer.

---

## 9. HARDCODED URLs — None Found

All API calls use the base `api` Axios instance from `api.ts`. No hardcoded `localhost` or direct URL strings found in lib files. The `normalizeRequestPath` utility strips `/api/v1` prefix if accidentally included.

---

## 10. ENDPOINT URL MISMATCHES

### `driver-score` DELETE in frontend
`DriverScoreTab.tsx` calls `api.delete('/driver-score/${id}')` but the backend only has:
- `GET /fleet/driver-score/{driverId}`
- `GET /fleet/driver-ranking`

There is no `DELETE /driver-score/{id}` route anywhere in the backend.

### `stock/movements` POST payload type
Frontend `stockApi.movements.create` accepts `Record<string, unknown>` but the backend `StockController::store` has specific validation. No TypeScript type for the stock movement creation payload.

### `stock/warehouses` vs `/warehouses`
Frontend uses both:
- `stockApi.warehouses.list` → `GET /warehouses` (correct, exists)
- `stockApi.warehousesOptions()` → `GET /stock/warehouses` (also exists as alias)

Both exist, no mismatch. But the duplication in the frontend is potentially confusing.

---

## 11. MISSING BACKEND RESOURCES

No `FleetVehicleResource` exists in `backend/app/Http/Resources/`. The `FleetController::showVehicle` returns raw Eloquent model via `ApiResponse::data($vehicle)` — no resource transformation, no consistent field naming guarantee.

---

## Summary of Critical Issues

| Priority | Issue | Files |
|---|---|---|
| CRITICAL | 40+ HR endpoints in hr-api.ts have no backend routes | `frontend/src/lib/hr-api.ts`, `backend/routes/api/hr-quality-automation.php` |
| HIGH | FleetVehicle type has 4 phantom fields and misses 3 real backend fields | `frontend/src/types/fleet.ts` vs `backend/app/Models/FleetVehicle.php` |
| HIGH | No `fleet-api.ts` lib file — fleet uses inline API calls with no types | `frontend/src/pages/fleet/` |
| HIGH | `DELETE /driver-score/{id}` endpoint does not exist in backend | `frontend/src/pages/fleet/components/DriverScoreTab.tsx` |
| MEDIUM | `AccountReceivable`/`AccountPayable` missing `cost_center_id`, `quote_id`, penalty/interest fields in Resource AND frontend type | `backend/app/Http/Resources/Account*Resource.php`, `frontend/src/types/financial.ts` |
| MEDIUM | `AccountPayable` frontend type has dead `supplier_relation`/`category_relation` fields that resource never returns | `frontend/src/types/financial.ts` |
| MEDIUM | No `FleetVehicleResource` — raw model returned by controller | `backend/app/Http/Controllers/Api/V1/FleetController.php` |
| LOW | `stockApi` create/update functions use `Record<string, unknown>` — no typed payloads | `frontend/src/lib/stock-api.ts` |
| LOW | Inconsistent pagination typing between `financial-api.ts` and other modules | `frontend/src/lib/financial-api.ts` |
