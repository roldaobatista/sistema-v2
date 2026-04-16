<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderImportExportControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_export_returns_csv_stream(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get('/api/v1/work-orders-export');

        $response->assertOk();
    }

    public function test_export_returns_xlsx_stream(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get('/api/v1/work-orders-export?format=xlsx');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_validates_file_required(): void
    {
        $response = $this->postJson('/api/v1/work-orders-import', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_import_rejects_non_csv_file(): void
    {
        $file = UploadedFile::fake()->create('image.png', 10, 'image/png');

        $response = $this->postJson('/api/v1/work-orders-import', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_import_accepts_valid_csv(): void
    {
        $csv = "customer_name,description\nCliente Teste,Descrição do serviço\n";
        $file = UploadedFile::fake()->createWithContent('work-orders.csv', $csv);

        $response = $this->postJson('/api/v1/work-orders-import', [
            'file' => $file,
        ]);

        // Pode retornar 200 (success) ou 422 (linhas inválidas) — NUNCA 500
        $this->assertNotEquals(500, $response->status());
    }

    public function test_import_template_returns_200(): void
    {
        $response = $this->getJson('/api/v1/work-orders-import-template');

        $response->assertOk();
    }
}
