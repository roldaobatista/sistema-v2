<?php

namespace Tests\Feature\Notifications;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Mail\WorkOrderStatusMail;
use App\Models\Customer;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ClientNotificationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderNotificationTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'cliente@teste.com',
            'phone' => '+5511999990000',
        ]);

        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_notify_os_created_sends_email_when_enabled(): void
    {
        Mail::fake();

        SystemSetting::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'key' => 'notify_client_os_created'],
            ['value' => '1']
        );

        $service = app(ClientNotificationService::class);
        $service->notifyOsCreated($this->workOrder);

        Mail::assertQueued(WorkOrderStatusMail::class, function ($mail) {
            return $mail->hasTo('cliente@teste.com');
        });
    }

    public function test_notify_os_created_skipped_when_disabled(): void
    {
        Mail::fake();

        SystemSetting::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'key' => 'notify_client_os_created'],
            ['value' => '0']
        );

        $service = app(ClientNotificationService::class);
        $service->notifyOsCreated($this->workOrder);

        Mail::assertNothingQueued();
    }

    public function test_notify_os_completed_sends_email_when_enabled(): void
    {
        Mail::fake();

        SystemSetting::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'key' => 'notify_client_os_completed'],
            ['value' => '1']
        );

        $service = app(ClientNotificationService::class);
        $service->notifyOsCompleted($this->workOrder);

        Mail::assertQueued(WorkOrderStatusMail::class);
    }

    public function test_notify_skipped_when_customer_has_no_email(): void
    {
        Mail::fake();

        $this->customer->update(['email' => null]);
        $this->workOrder->refresh();
        $this->workOrder->load('customer');

        SystemSetting::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'key' => 'notify_client_os_created'],
            ['value' => '1']
        );

        $service = app(ClientNotificationService::class);
        $service->notifyOsCreated($this->workOrder);

        Mail::assertNothingQueued();
    }
}
