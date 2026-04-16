<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderCsvImportTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_import_csv_creates_work_orders(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Importação',
        ]);

        $csv = "cliente;descricao;valor_total\nCliente Importação;Serviço de teste;150.00\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $response = $this->postJson('/api/v1/work-orders-import', ['file' => $file]);

        $response->assertOk();
    }

    public function test_import_csv_validates_required_columns(): void
    {
        $csv = "nome;valor\nTeste;100\n";
        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $response = $this->postJson('/api/v1/work-orders-import', ['file' => $file]);

        $response->assertStatus(422);

        $this->assertStringContainsString('Colunas obrigat', $response->getContent());
    }

    public function test_import_csv_rejects_empty_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('empty.csv', '');

        $response = $this->postJson('/api/v1/work-orders-import', ['file' => $file]);

        $response->assertStatus(422);
    }

    public function test_import_csv_requires_file(): void
    {
        $response = $this->postJson('/api/v1/work-orders-import', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }
}
