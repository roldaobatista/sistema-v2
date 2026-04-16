# Research Report: Quote Module - Comprehensive Fix Plan Data

## 1. QuoteController.php
**Path:** `C:\PROJETOS\sistema\backend\app\Http\Controllers\Api\V1\QuoteController.php`

### Method Line Numbers
| Method | Line | Notes |
|---|---|---|
| `items()` | 636 | Queries QuoteItem via quoteEquipment relationship |
| `storeNestedItem()` | 654 | Creates item under "Geral" equipment, type hardcoded 'service' |
| `updateItem()` | 697 | Loads quoteEquipment.quote, delegates to service->updateItem() |
| `removeItem()` | 722 | Loads quoteEquipment.quote, deletes + recalculateTotal |
| `addPhoto()` | 745 | Stores file in quotes/{id}/photos, creates QuotePhoto |
| `destroy()` | 237 | Checks ensureQuoteMutable + linked WorkOrders. Does NOT check linked ServiceCalls |
| `publicView()` | 936 | Uses matchesPublicAccessToken(), tracks client view |
| `publicApprove()` | 954 | Uses matchesPublicAccessToken(), delegates to service->publicApprove() |
| `destroyTag()` | 1056 | Uses `$this->authorize('delete', Quote::class)` - passes CLASS not instance |
| `destroyTemplate()` | 1102 | Uses `$this->authorize('delete', Quote::class)` - passes CLASS not instance |
| `internalApprove()` | 343 | Checks status in [DRAFT, PENDING_INTERNAL_APPROVAL], updates directly (not via service) |
| `ensureQuoteMutable()` | 71 | Allows: DRAFT, PENDING_INTERNAL_APPROVAL, REJECTED, RENEGOTIATION |

### Issues Found
1. **Line 237 `destroy()`**: Only checks WorkOrder links, NOT ServiceCall links. If quote was converted to ServiceCall, it can still be deleted.
2. **Line 1056 `destroyTag()`**: `$this->authorize('delete', Quote::class)` passes the class instead of a model instance. QuotePolicy::delete() expects `(User, Quote)` - this will call a non-existent policy method signature.
3. **Line 1102 `destroyTemplate()`**: Same issue as destroyTag - passes class to authorize('delete').
4. **Line 343 `internalApprove()`**: Logic duplicated - doesn't delegate to QuoteService, does DB::transaction directly in controller.

---

## 2. QuoteService.php
**Path:** `C:\PROJETOS\sistema\backend\app\Services\QuoteService.php`

### Method Line Numbers
| Method | Line | Notes |
|---|---|---|
| `approveAfterTest()` | 499 | INSTALLATION_TESTING -> APPROVED. Does NOT dispatch QuoteApproved event |
| `updateQuote()` (aka `update`) | 81 | Simple: update + increment revision + recalculateTotal |
| `requestInternalApproval()` | 94 | DRAFT -> PENDING_INTERNAL_APPROVAL. Checks equipment items exist |
| `resolvePostConversionStatus()` | 490 | Returns INSTALLATION_TESTING or IN_EXECUTION based on flag |
| `reopenQuote()` | 210 | REJECTED/EXPIRED -> DRAFT. Increments revision, clears rejection |
| `approveQuote()` | 130 | SENT -> APPROVED. Dispatches QuoteApproved event |
| `publicApprove()` | 174 | Delegates to approveQuote() with extra attributes |
| `sendQuote()` | 110 | INTERNALLY_APPROVED -> SENT. Generates magic_token |
| `rejectQuote()` | 192 | SENT -> REJECTED |
| `convertToWorkOrder()` | 327 | Uses isConvertible() = [APPROVED, INTERNALLY_APPROVED] |
| `convertToServiceCall()` | 438 | Uses isConvertible() = [APPROVED, INTERNALLY_APPROVED] |
| `updateItem()` | 305 | Updates item fields, comment says recalculateTotal is automatic via saved event |

### Issues Found
1. **Line 499 `approveAfterTest()`**: Does NOT dispatch `QuoteApproved` event. When quote goes from INSTALLATION_TESTING -> APPROVED, no notification/CRM activity is created. Compare with `approveQuote()` at line 164 which DOES dispatch.
2. **Line 490 `resolvePostConversionStatus()`**: Accepts `$conversionTarget` parameter but ignores it completely - always returns same result regardless of work_order or service_call target.

---

## 3. Quote.php (Model)
**Path:** `C:\PROJETOS\sistema\backend\app\Models\Quote.php`

### Key Line Numbers
| Element | Line | Notes |
|---|---|---|
| `$fillable` | 138-153 | Includes tenant_id, quote_number, status, all approval fields, custom_fields, etc. |
| `items()` relationship | 188-191 | `hasMany(QuoteItem::class)` - direct relationship to QuoteItem |
| `equipments()` | 213-216 | `hasMany(QuoteEquipment::class)->orderBy('sort_order')` |
| `recalculateTotal()` | 305-329 | Iterates equipments.items, applies discount, saves quietly |
| `expirableStatuses()` | 338-345 | [SENT, PENDING_INTERNAL_APPROVAL, INTERNALLY_APPROVED] |
| `isExpired()` | 331-336 | Checks valid_until + expirableStatuses |

### Notes
- The `items()` relationship at line 188 goes directly to QuoteItem (not through QuoteEquipment). But the controller's `items()` method at line 643 queries through `quoteEquipment` relationship instead. These are two different access paths.
- `$fillable` does NOT include `discount` standalone field, but model has `@property float|null $discount` in docblock (line 48).

---

## 4. QuoteStatus.php (Enum)
**Path:** `C:\PROJETOS\sistema\backend\app\Enums\QuoteStatus.php`

### All Cases (lines 7-17)
| Case | Value | Label |
|---|---|---|
| DRAFT | draft | Rascunho |
| PENDING_INTERNAL_APPROVAL | pending_internal_approval | Aguard. Aprovacao Interna |
| INTERNALLY_APPROVED | internally_approved | Aprovado Internamente |
| SENT | sent | Enviado |
| APPROVED | approved | Aprovado |
| REJECTED | rejected | Rejeitado |
| EXPIRED | expired | Expirado |
| IN_EXECUTION | in_execution | Em Execucao |
| INSTALLATION_TESTING | installation_testing | Instalacao p/ Teste |
| RENEGOTIATION | renegotiation | Em Renegociacao |
| INVOICED | invoiced | Faturado |

### Methods
| Method | Line | Statuses Included |
|---|---|---|
| `isMutable()` | 54-57 | DRAFT, PENDING_INTERNAL_APPROVAL, REJECTED, RENEGOTIATION |
| `isConvertible()` | 60-63 | APPROVED, INTERNALLY_APPROVED |
| `label()` | 19-34 | All cases mapped |
| `color()` | 36-51 | All cases mapped |

---

## 5. QuotePolicy.php
**Path:** `C:\PROJETOS\sistema\backend\app\Policies\QuotePolicy.php`

### All Methods
| Method | Line | Permission |
|---|---|---|
| `viewAny(User)` | 14 | quotes.quote.view |
| `view(User, Quote)` | 19 | quotes.quote.view + tenant check |
| `create(User)` | 27 | quotes.quote.create |
| `update(User, Quote)` | 32 | quotes.quote.update + tenant check |
| `delete(User, Quote)` | 40 | quotes.quote.delete + tenant check |
| `send(User, Quote)` | 48 | quotes.quote.send + tenant check |
| `approve(User, Quote)` | 57 | quotes.quote.approve + tenant check |
| `internalApprove(User, Quote)` | 66 | quotes.quote.internal_approve + tenant check |
| `convert(User, Quote)` | 75 | quotes.quote.convert + tenant check |

### Issue
- `destroyTag()` and `destroyTemplate()` in controller call `$this->authorize('delete', Quote::class)`. The `delete` policy method signature is `delete(User $user, Quote $model)` requiring a model instance. Passing the class will cause Laravel to try to resolve a "without model" variant, which doesn't exist. This likely falls through silently or throws an error.

---

## 6. EventServiceProvider
**Path:** `C:\PROJETOS\sistema\backend\app\Providers\EventServiceProvider.php`

### QuoteApproved Registration (lines 29-32)
```
\App\Events\QuoteApproved::class => [
    \App\Listeners\HandleQuoteApproval::class,
    \App\Listeners\CreateAgendaItemOnQuote::class,
],
```
- Two listeners registered: HandleQuoteApproval + CreateAgendaItemOnQuote
- Both are properly registered in $listen array

---

## 7. QuoteApproved Event
**Path:** `C:\PROJETOS\sistema\backend\app\Events\QuoteApproved.php`

- Simple event with `Dispatchable` and `SerializesModels` traits
- Constructor takes `Quote $quote` and `User $user`
- No broadcasting, no queue configuration

---

## 8. HandleQuoteApproval Listener
**Path:** `C:\PROJETOS\sistema\backend\app\Listeners\HandleQuoteApproval.php`

- Implements `ShouldQueueAfterCommit` (queued after DB commit)
- Creates CrmActivity (line 51-92) with type `quote_approved`
- Creates Notification (line 94-129) for seller
- Uses upsert pattern (checks existing before creating)
- Notification goes to `seller_id` or fallback to `$user->id`

---

## 9. Invoice/Faturamento References

### INVOICED enum usage found in:
- `QuoteStatus::INVOICED` (line 17) = 'invoiced' / 'Faturado'
- QuoteController `summary()` line 815: counts invoiced quotes
- QuoteController `getConversionRate()` lines 841-850: includes INVOICED in approved count
- QuoteService `advancedSummary()` lines 617-653: includes INVOICED alongside APPROVED for stats
- Quote model line 108-109: deprecated constant STATUS_INVOICED

### No transition TO invoiced status found:
- There is NO method in QuoteService that transitions a quote to INVOICED status
- The status exists in the enum but no code path sets it
- Likely needs to be set when a fiscal note / invoice is generated from the work order

---

## 10. Migrations with quote_id

**Base migration:** `C:\PROJETOS\sistema\backend\database\migrations\2026_02_08_100000_create_quotes_tables.php`

17 migration files reference quote_id, key ones:
- `2026_02_08_100000_create_quotes_tables.php` - Creates quotes, quote_equipments, quote_items tables
- `2026_02_08_200000_create_service_calls_tables.php` - Adds quote_id to service_calls
- `2026_02_08_300000_alter_work_orders_add_origin.php` - Adds quote_id to work_orders
- `2026_02_10_100000_add_tenant_to_quote_children.php` - Adds tenant_id to quote child tables
- `2026_02_18_100005_quote_module_improvements.php` - Quote module improvements

---

## Summary of All Issues Found

1. **`destroy()` (Controller:237)** - Does not check ServiceCall links before deleting
2. **`destroyTag()` (Controller:1056)** - Wrong authorize() call (class vs instance)
3. **`destroyTemplate()` (Controller:1102)** - Wrong authorize() call (class vs instance)
4. **`internalApprove()` (Controller:343)** - Logic in controller instead of service layer
5. **`approveAfterTest()` (Service:499)** - Missing QuoteApproved event dispatch
6. **`resolvePostConversionStatus()` (Service:490)** - $conversionTarget param ignored
7. **No INVOICED transition** - No code path sets quote status to INVOICED
8. **`items()` model vs controller** - Two different access paths (direct vs through equipment)
