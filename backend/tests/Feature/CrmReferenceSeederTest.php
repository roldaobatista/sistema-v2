<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\CrmReferenceSeeder;
use Database\Seeders\CrmSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmReferenceSeederTest extends TestCase
{
    public function test_crm_reference_seeder_populates_core_crm_tables(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        Customer::factory()->count(4)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $this->seed(CrmSeeder::class);
        $this->seed(CrmReferenceSeeder::class);

        $this->assertGreaterThan(
            0,
            DB::table('crm_deals')->where('tenant_id', $tenant->id)->count()
        );

        $this->assertGreaterThan(
            0,
            DB::table('crm_web_forms')->where('tenant_id', $tenant->id)->count()
        );

        $this->assertGreaterThan(
            0,
            DB::table('crm_referrals')->where('tenant_id', $tenant->id)->count()
        );

        $this->assertGreaterThan(
            0,
            DB::table('crm_deal_competitors')
                ->join('crm_deals', 'crm_deal_competitors.deal_id', '=', 'crm_deals.id')
                ->where('crm_deals.tenant_id', $tenant->id)
                ->count()
        );
    }
}
