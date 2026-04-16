<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\QualityReferenceSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QualityReferenceSeederTest extends TestCase
{
    public function test_quality_reference_seeder_populates_core_quality_tables(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $this->seed(QualityReferenceSeeder::class);

        $this->assertGreaterThan(
            0,
            DB::table('quality_procedures')->where('tenant_id', $tenant->id)->count()
        );

        $this->assertGreaterThan(
            0,
            DB::table('quality_audits')->where('tenant_id', $tenant->id)->count()
        );

        $this->assertGreaterThan(
            0,
            DB::table('quality_audit_items')
                ->join('quality_audits', 'quality_audit_items.quality_audit_id', '=', 'quality_audits.id')
                ->where('quality_audits.tenant_id', $tenant->id)
                ->count()
        );
    }
}
