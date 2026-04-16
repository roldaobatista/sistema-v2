---
type: architecture_pattern
id: 05
---
# 05. Ownership e Fronteiras (Database Fencing)

> **[AI_RULE]** Cada módulo é dono exclusivo de suas tabelas. Invasão de tabela é violação de DDD (Domain Driven Design).

## 1. Regra Oficial do Cão de Guarda (Owner) `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] Exclusividade do Eloquent Model**
> A pasta `App\Modules\Finance\Models\` não pode conter instâncias replicadas de Models do módulo `HR` (ex: `TimeClockEntry`). Somente o módulo dono pode fazer queries nativas baseadas no banco primário dessas tabelas.
> **Diretiva de Código:** Quando uma IA compilar um relatório que necessita dados estrangeiros, ela não injeta o Model estrangeiro de `HR`. Ela gera um repositório abstrato `ReadOnly` ou pede o DTO exportado pela interface do `HRService`.

## 2. Migrations e Foreign Keys Ocultas

Se `Finance` quiser associar uma nota fiscal a um `User`, como o `User` costuma ser do Core, fazemos a Foreign Key tradicional, mas o acesso reverso (relação via Eloquent `$user->invoices`) não deve existir polimorficamente forçado. O acoplamento vai sempre do mais específico para o genérico.

## 3. Mapa de Ownership por Módulo

| Módulo Owner | Tabelas Exclusivas (exemplos) | Quem Consome (via Interface) |
|-------------|------------------------------|------------------------------|
| **Core** | `users`, `tenants`, `roles`, `permissions` | Todos os módulos |
| **Finance** | `fin_invoices`, `fin_payments`, `fin_accounts_receivable` | WorkOrders, Commission, Reports |
| **WorkOrders** | `wo_work_orders`, `wo_schedules`, `wo_checklists` | Finance, Lab, Fleet |
| **HR** | `hr_time_clocks`, `hr_vacations`, `hr_clt_violations` | Finance (comissões), Reports |
| **Lab** | `lab_certificates`, `lab_instruments`, `lab_readings` | WorkOrders, Reports |
| **CRM** | `crm_customers`, `crm_deals`, `crm_contacts` | Quotes, WorkOrders, Finance |
| **Inventory** | `inv_products`, `inv_stock_movements`, `inv_warehouses` | WorkOrders, Quotes |
| **Fleet** | `flt_vehicles`, `flt_maintenances`, `flt_fuel_logs` | WorkOrders, Scheduling |

## 4. Padrão de Acesso a Dados Estrangeiros `[AI_RULE]`

> **[AI_RULE]** Existem APENAS 3 formas permitidas de acessar dados de outro módulo:

### 4.1 Via Service Interface (Síncrono)

```php
// CORRETO: Finance precisa do nome do cliente
class InvoiceService
{
    public function __construct(
        private CrmServiceInterface $crmService,
    ) {}

    public function generateInvoice(int $workOrderId): Invoice
    {
        $customer = $this->crmService->getCustomerDTO($customerId);
        // $customer é um DTO imutável, não um Model
    }
}
```

### 4.2 Via Evento Assíncrono (Side Effects)

```php
// CORRETO: WorkOrder concluída dispara geração de fatura
// Em WorkOrders\Actions\CompleteWorkOrderAction.php
event(new WorkOrderCompletedEvent(
    workOrderId: $workOrder->id,
    tenantId: $workOrder->tenant_id,
    totalAmount: $workOrder->total,
));

// Em Finance\Listeners\GenerateInvoiceFromWorkOrder.php
// O listener consome o evento e cria a fatura no SEU módulo
```

### 4.3 Via Read Model / View SQL (Relatórios)

```php
// CORRETO: Dashboard que cruza dados de múltiplos módulos
// Usa uma View SQL materializada, não joins diretos em Models
DB::table('vw_dashboard_operational')
    ->where('tenant_id', $tenantId)
    ->paginate(20);
```

## 5. Fronteiras de Migrations `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** Cada módulo cria e mantém SOMENTE as migrations das suas tabelas. Uma migration do módulo `Finance` jamais pode conter `Schema::table('wo_work_orders', ...)`.

```
database/migrations/
├── 2024_01_01_000001_create_users_table.php          # Core
├── 2024_01_01_000002_create_tenants_table.php         # Core
├── 2024_02_01_000001_create_fin_invoices_table.php    # Finance
├── 2024_02_01_000002_create_fin_payments_table.php    # Finance
├── 2024_03_01_000001_create_wo_work_orders_table.php  # WorkOrders
├── 2024_03_01_000002_create_wo_schedules_table.php    # WorkOrders
└── ...
```

## 6. Soft Deletes Cross-Context

Quando um registro "pai" de um contexto é deletado (ex: `Customer` no CRM), os módulos dependentes NÃO usam `ON DELETE CASCADE`. O fluxo correto:

1. CRM emite `CustomerDeletedEvent` com o `customer_id`
2. Finance escuta e faz soft-delete nas invoices associadas
3. WorkOrders escuta e cancela ordens de serviço pendentes
4. Cada módulo mantém controle sobre a integridade dos seus próprios dados
