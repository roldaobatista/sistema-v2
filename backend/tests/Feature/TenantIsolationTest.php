<?php

namespace Tests\Feature;

use App\Jobs\RunDataExportJob;
use App\Models\AccountReceivable;
use App\Models\AnalyticsDataset;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DataExportJob;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Services\Analytics\DatasetQueryBuilder;
use App\Support\TenantSafeQuery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        $this->userA = $this->createUserForTenant($this->tenantA, 'a@example.test');
        $this->userB = $this->createUserForTenant($this->tenantB, 'b@example.test');

        $this->grantTenantIsolationPermissions($this->userA, $this->tenantA);
        $this->grantTenantIsolationPermissions($this->userB, $this->tenantB);

        $this->customerA = Customer::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Customer',
        ]);
        $this->customerB = Customer::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Confidential Customer',
        ]);
    }

    public function test_user_only_sees_customers_from_token_tenant(): void
    {
        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->getJson('/api/v1/customers');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Tenant A Customer'));
        $this->assertFalse($names->contains('Tenant B Confidential Customer'));
    }

    public function test_direct_read_of_other_tenant_customer_returns_not_found(): void
    {
        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->getJson("/api/v1/customers/{$this->customerB->id}");

        $response->assertNotFound();
    }

    public function test_body_tenant_id_cannot_override_token_tenant_on_create(): void
    {
        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->postJson('/api/v1/customers', [
            'tenant_id' => $this->tenantB->id,
            'type' => 'PJ',
            'name' => 'Tenant A Created Customer',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('customers', [
            'name' => 'Tenant A Created Customer',
            'tenant_id' => $this->tenantA->id,
        ]);
        $this->assertDatabaseMissing('customers', [
            'name' => 'Tenant A Created Customer',
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    public function test_direct_update_of_other_tenant_customer_returns_not_found(): void
    {
        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->putJson("/api/v1/customers/{$this->customerB->id}", [
            'name' => 'Cross Tenant Mutation',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('customers', ['name' => 'Cross Tenant Mutation']);
    }

    public function test_direct_delete_of_other_tenant_customer_returns_not_found(): void
    {
        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->deleteJson("/api/v1/customers/{$this->customerB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('customers', ['id' => $this->customerB->id]);
    }

    public function test_relationship_join_query_is_filtered_by_current_tenant(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'description' => 'Tenant B relationship secret',
        ]);

        $this->setTenantContext($this->tenantA->id);

        $rows = Customer::query()
            ->join('work_orders', 'work_orders.customer_id', '=', 'customers.id')
            ->select('customers.name', 'work_orders.description')
            ->get();

        $this->assertTrue($rows->contains('name', 'Tenant A Customer'));
        $this->assertFalse($rows->contains('description', 'Tenant B relationship secret'));
    }

    public function test_one_hundred_interleaved_requests_do_not_bleed_tenant_context(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $tenant = $i % 2 === 0 ? $this->tenantA : $this->tenantB;
            $user = $i % 2 === 0 ? $this->userA : $this->userB;
            $expected = $i % 2 === 0 ? 'Tenant A Customer' : 'Tenant B Confidential Customer';
            $forbidden = $i % 2 === 0 ? 'Tenant B Confidential Customer' : 'Tenant A Customer';

            $this->actingAsTenant($user, $tenant);
            $names = collect($this->getJson('/api/v1/customers')->assertOk()->json('data'))->pluck('name');

            $this->assertTrue($names->contains($expected));
            $this->assertFalse($names->contains($forbidden));
        }
    }

    public function test_payment_webhook_rejects_false_tenant_reference(): void
    {
        config(['services.payment.webhook_secret' => 'tenant-webhook-secret']);

        $receivableB = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'created_by' => $this->userB->id,
            'amount' => 250,
            'amount_paid' => 0,
        ]);

        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay-cross-tenant-attempt',
                'tenant_id' => $this->tenantA->id,
                'externalReference' => 'AccountReceivable:'.$receivableB->id,
                'value' => 250,
                'billingType' => 'PIX',
            ],
        ];

        $this->postJson('/api/v1/webhooks/payment', $payload, [
            'X-Webhook-Secret' => 'tenant-webhook-secret',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('payments', [
            'external_id' => 'pay-cross-tenant-attempt',
        ]);
        $this->assertEquals('0.00', AccountReceivable::withoutGlobalScopes()->findOrFail($receivableB->id)->amount_paid);
    }

    public function test_payment_webhook_rejects_existing_payment_without_trusted_tenant(): void
    {
        config(['services.payment.webhook_secret' => 'tenant-webhook-secret']);

        $receivableB = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'created_by' => $this->userB->id,
            'amount' => 250,
            'amount_paid' => 0,
        ]);

        $paymentB = Payment::create([
            'tenant_id' => $this->tenantB->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivableB->id,
            'received_by' => $this->userB->id,
            'amount' => 250,
            'payment_method' => 'pix',
            'payment_date' => now(),
            'external_id' => 'pay-existing-cross-tenant-attempt',
            'status' => 'pending',
        ]);

        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay-existing-cross-tenant-attempt',
                'value' => 250,
                'billingType' => 'PIX',
            ],
        ];

        $this->postJson('/api/v1/webhooks/payment', $payload, [
            'X-Webhook-Secret' => 'tenant-webhook-secret',
        ])->assertStatus(422);

        $this->assertDatabaseHas('payments', [
            'id' => $paymentB->id,
            'tenant_id' => $this->tenantB->id,
            'external_id' => 'pay-existing-cross-tenant-attempt',
            'status' => 'pending',
        ]);
    }

    public function test_payment_webhook_does_not_process_soft_deleted_payment_by_external_id(): void
    {
        config(['services.payment.webhook_secret' => 'tenant-webhook-secret']);

        $receivableA = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
            'created_by' => $this->userA->id,
            'amount' => 125,
            'amount_paid' => 0,
        ]);

        $payment = Payment::create([
            'tenant_id' => $this->tenantA->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivableA->id,
            'received_by' => $this->userA->id,
            'amount' => 125,
            'payment_method' => 'pix',
            'payment_date' => now(),
            'external_id' => 'pay-soft-deleted',
            'status' => 'pending',
        ]);
        $payment->delete();

        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay-soft-deleted',
                'tenant_id' => $this->tenantA->id,
                'value' => 125,
                'billingType' => 'PIX',
            ],
        ];

        $this->postJson('/api/v1/webhooks/payment', $payload, [
            'X-Webhook-Secret' => 'tenant-webhook-secret',
        ])->assertNotFound();

        $this->assertSame('pending', Payment::withoutGlobalScopes()->withTrashed()->findOrFail($payment->id)->status);
        $this->assertEquals('0.00', AccountReceivable::withoutGlobalScopes()->findOrFail($receivableA->id)->amount_paid);
    }

    public function test_storage_path_from_other_tenant_is_not_reachable_through_work_order_route(): void
    {
        Storage::fake('public');
        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);
        WorkOrderAttachment::create([
            'tenant_id' => $this->tenantB->id,
            'work_order_id' => $workOrderB->id,
            'uploaded_by' => $this->userB->id,
            'file_name' => 'secret.pdf',
            'file_path' => "tenants/{$this->tenantB->id}/files/secret.pdf",
            'file_size' => 128,
        ]);
        Storage::disk('public')->put("tenants/{$this->tenantB->id}/files/secret.pdf", 'secret');

        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->getJson("/api/v1/work-orders/{$workOrderB->id}/attachments");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_queue_job_payload_from_other_tenant_is_blocked_by_worker_context(): void
    {
        $datasetA = AnalyticsDataset::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->userA->id,
        ]);
        $jobA = DataExportJob::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'analytics_dataset_id' => $datasetA->id,
            'created_by' => $this->userA->id,
        ]);

        $this->setTenantContext($this->tenantB->id);
        app(RunDataExportJob::class, ['dataExportJobId' => $jobA->id])->handle(app(DatasetQueryBuilder::class));

        $this->assertDatabaseHas('data_export_jobs', [
            'id' => $jobA->id,
            'status' => DataExportJob::STATUS_PENDING,
            'output_path' => null,
        ]);
    }

    public function test_console_command_in_tenant_context_does_not_see_other_tenant_models(): void
    {
        $this->setTenantContext($this->tenantA->id);

        $this->assertSame(0, Artisan::call('about', ['--only' => 'environment']));
        $names = Customer::query()->pluck('name');

        $this->assertTrue($names->contains('Tenant A Customer'));
        $this->assertFalse($names->contains('Tenant B Confidential Customer'));
    }

    public function test_raw_tenant_query_requires_explicit_tenant_context(): void
    {
        app()->forgetInstance('current_tenant_id');

        $this->expectException(InvalidArgumentException::class);

        TenantSafeQuery::table('customers')->get();
    }

    public function test_raw_tenant_query_helper_applies_current_tenant_filter(): void
    {
        $this->setTenantContext($this->tenantA->id);

        $names = TenantSafeQuery::table('customers')->pluck('name');

        $this->assertTrue($names->contains('Tenant A Customer'));
        $this->assertFalse($names->contains('Tenant B Confidential Customer'));
    }

    public function test_user_cannot_switch_or_impersonate_tenant_without_membership(): void
    {
        $this->actingAsTenant($this->userA, $this->tenantA);

        $this->postJson('/api/v1/switch-tenant', [
            'tenant_id' => $this->tenantB->id,
        ])->assertForbidden();

        $this->assertSame($this->tenantA->id, $this->userA->refresh()->current_tenant_id);
    }

    public function test_export_report_for_tenant_a_does_not_include_tenant_b_customers(): void
    {
        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->get('/api/v1/customers/export');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Tenant A Customer', $content);
        $this->assertStringNotContainsString('Tenant B Confidential Customer', $content);
    }

    public function test_api_resource_for_export_jobs_does_not_leak_other_tenant_rows(): void
    {
        $datasetA = AnalyticsDataset::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->userA->id,
        ]);
        $datasetB = AnalyticsDataset::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'created_by' => $this->userB->id,
        ]);
        DataExportJob::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'analytics_dataset_id' => $datasetA->id,
            'created_by' => $this->userA->id,
            'name' => 'Tenant A Export',
        ]);
        DataExportJob::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'analytics_dataset_id' => $datasetB->id,
            'created_by' => $this->userB->id,
            'name' => 'Tenant B Secret Export',
        ]);

        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->getJson('/api/v1/analytics/export-jobs');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Tenant A Export'));
        $this->assertFalse($names->contains('Tenant B Secret Export'));
    }

    public function test_search_does_not_return_other_tenant_match(): void
    {
        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->getJson('/api/v1/customers?search=Confidential');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Tenant B Confidential Customer'));
    }

    public function test_audit_log_endpoint_does_not_leak_other_tenant_entries(): void
    {
        AuditLog::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'description' => 'Tenant A audit entry',
        ]);
        AuditLog::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
            'description' => 'Tenant B secret audit entry',
        ]);

        $this->actingAsTenant($this->userA, $this->tenantA);

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertOk();
        $descriptions = collect($response->json('data'))->pluck('description');
        $this->assertTrue($descriptions->contains('Tenant A audit entry'));
        $this->assertFalse($descriptions->contains('Tenant B secret audit entry'));
    }

    public function test_api_routes_do_not_accept_bearer_token_from_cookie(): void
    {
        $token = $this->userA->createToken('cookie-token', ["tenant:{$this->tenantA->id}"])->plainTextToken;

        $this->withCookie('auth_token', $token)
            ->getJson('/api/v1/customers')
            ->assertUnauthorized();
    }

    private function createUserForTenant(Tenant $tenant, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $user->tenants()->attach($tenant->id, ['is_default' => true]);

        return $user;
    }

    private function actingAsTenant(User $user, Tenant $tenant): void
    {
        Sanctum::actingAs($user, ["tenant:{$tenant->id}"]);
    }

    private function grantTenantIsolationPermissions(User $user, Tenant $tenant): void
    {
        $permissions = [
            'analytics.export.create',
            'analytics.export.download',
            'analytics.export.view',
            'cadastros.customer.create',
            'cadastros.customer.delete',
            'cadastros.customer.update',
            'cadastros.customer.view',
            'cadastros.supplier.view',
            'equipments.equipment.view',
            'finance.receivable.create',
            'finance.receivable.delete',
            'finance.receivable.settle',
            'finance.receivable.update',
            'finance.receivable.view',
            'iam.audit_log.export',
            'iam.audit_log.view',
            'os.work_order.export',
            'os.work_order.update',
            'os.work_order.view',
            'quotes.quote.view',
            'reports.customers_report.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->setTenantContext($tenant->id);

        $role = Role::firstOrCreate([
            'name' => 'tenant_isolation_admin',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);
        $role->syncPermissions($permissions);
        $user->assignRole($role);
    }
}
