<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AgendaService;
use App\Services\AlertEngineService;
use App\Services\ClientNotificationService;
use App\Services\CommissionService;
use App\Services\ExpenseService;
use App\Services\HolidayService;
use App\Services\ImportService;
use App\Services\InvoicingService;
use App\Services\PdfGeneratorService;
use App\Services\QuoteService;
use App\Services\ServiceCallService;
use App\Services\StockService;
use App\Services\TenantService;
use App\Services\WorkOrderInvoicingService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MiscServicesTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    public function test_alert_engine_service_instantiation(): void
    {
        $service = app(AlertEngineService::class);
        $this->assertInstanceOf(AlertEngineService::class, $service);
    }

    public function test_import_service_instantiation(): void
    {
        $service = app(ImportService::class);
        $this->assertInstanceOf(ImportService::class, $service);
    }

    public function test_expense_service_instantiation(): void
    {
        $service = app(ExpenseService::class);
        $this->assertInstanceOf(ExpenseService::class, $service);
    }

    public function test_commission_service_instantiation(): void
    {
        $service = app(CommissionService::class);
        $this->assertInstanceOf(CommissionService::class, $service);
    }

    public function test_stock_service_instantiation(): void
    {
        $service = app(StockService::class);
        $this->assertInstanceOf(StockService::class, $service);
    }

    public function test_holiday_service_instantiation(): void
    {
        $service = app(HolidayService::class);
        $this->assertInstanceOf(HolidayService::class, $service);
    }

    public function test_agenda_service_instantiation(): void
    {
        $service = app(AgendaService::class);
        $this->assertInstanceOf(AgendaService::class, $service);
    }

    public function test_quote_service_instantiation(): void
    {
        $service = app(QuoteService::class);
        $this->assertInstanceOf(QuoteService::class, $service);
    }

    public function test_service_call_service_instantiation(): void
    {
        $service = app(ServiceCallService::class);
        $this->assertInstanceOf(ServiceCallService::class, $service);
    }

    public function test_client_notification_service_instantiation(): void
    {
        $service = app(ClientNotificationService::class);
        $this->assertInstanceOf(ClientNotificationService::class, $service);
    }

    public function test_pdf_generator_service_instantiation(): void
    {
        $service = app(PdfGeneratorService::class);
        $this->assertInstanceOf(PdfGeneratorService::class, $service);
    }

    public function test_tenant_service_instantiation(): void
    {
        $service = app(TenantService::class);
        $this->assertInstanceOf(TenantService::class, $service);
    }

    public function test_invoicing_service_instantiation(): void
    {
        $service = app(InvoicingService::class);
        $this->assertInstanceOf(InvoicingService::class, $service);
    }

    public function test_work_order_invoicing_service_instantiation(): void
    {
        $service = app(WorkOrderInvoicingService::class);
        $this->assertInstanceOf(WorkOrderInvoicingService::class, $service);
    }
}
