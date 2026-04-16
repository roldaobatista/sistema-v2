<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientPortalCertificateSecurityTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Customer $otherCustomer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->forceFill(['customer_id' => $this->customer->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        // Ensure the calibration_certificates table exists for tests
        if (! Schema::hasTable('calibration_certificates')) {
            Schema::create('calibration_certificates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('equipment_id');
                $table->string('number')->nullable();
                $table->date('date')->nullable();
                $table->date('issued_at')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('file_path')->nullable();
                $table->string('verification_code', 36)->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_portal_user_cannot_download_certificate_from_another_customers_equipment(): void
    {
        // Equipment belonging to OTHER customer (same tenant)
        $otherEquipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->otherCustomer->id,
        ]);

        $certificateId = DB::table('calibration_certificates')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $otherEquipment->id,
            'number' => 'CERT-OTHER-001',
            'date' => now()->toDateString(),
            'file_path' => 'certificates/other.pdf',
            'verification_code' => 'verify-other-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/client-portal/calibration-certificates/{$certificateId}/download");

        // Must NOT return the certificate — it belongs to another customer
        $response->assertStatus(404);
    }

    public function test_portal_user_can_download_certificate_from_own_customers_equipment(): void
    {
        // Equipment belonging to the authenticated user's customer
        $ownEquipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $certificateId = DB::table('calibration_certificates')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $ownEquipment->id,
            'number' => 'CERT-OWN-001',
            'date' => now()->toDateString(),
            'file_path' => 'certificates/own.pdf',
            'verification_code' => 'verify-own-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/client-portal/calibration-certificates/{$certificateId}/download");

        $response->assertStatus(200);
        $response->assertJsonPath('data.certificate.id', $certificateId);
    }
}
