<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\FollowUp;
use App\Models\Lookups\FollowUpChannel;
use App\Models\Lookups\FollowUpStatus;
use App\Models\Lookups\PriceTableAdjustmentType;
use App\Models\PriceTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdvancedFeaturesLookupCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware(CheckPermission::class);
    }

    private function createUser(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
    }

    public function test_follow_up_update_and_delete_support_lookup_values(): void
    {
        $user = $this->createUser();

        FollowUpChannel::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'WhatsApp',
            'slug' => 'whatsapp',
            'is_active' => true,
        ]);

        FollowUpStatus::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Atrasado',
            'slug' => 'overdue',
            'is_active' => true,
        ]);

        $followUp = FollowUp::create([
            'tenant_id' => $user->current_tenant_id,
            'followable_type' => User::class,
            'followable_id' => $user->id,
            'assigned_to' => $user->id,
            'scheduled_at' => now()->addHour(),
            'channel' => 'phone',
            'status' => 'pending',
        ]);

        $updateResponse = $this->actingAs($user)->putJson("/api/v1/advanced/follow-ups/{$followUp->id}", [
            'type' => 'whatsapp',
            'status' => 'overdue',
            'notes' => 'Contato reagendado',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.type', 'whatsapp')
            ->assertJsonPath('data.status', 'overdue');

        $this->assertDatabaseHas('follow_ups', [
            'id' => $followUp->id,
            'channel' => 'whatsapp',
            'status' => 'overdue',
        ]);

        $deleteResponse = $this->actingAs($user)->deleteJson("/api/v1/advanced/follow-ups/{$followUp->id}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('follow_ups', ['id' => $followUp->id]);
    }

    public function test_price_table_store_accepts_adjustment_type_alias(): void
    {
        $user = $this->createUser();

        PriceTableAdjustmentType::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Desconto',
            'slug' => 'discount',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/advanced/price-tables', [
            'name' => 'Tabela Desconto',
            'type' => 'discount',
            'modifier_percent' => 10,
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'discount')
            ->assertJsonPath('data.modifier_percent', 10);

        $table = PriceTable::query()->where('tenant_id', $user->current_tenant_id)->where('name', 'Tabela Desconto')->first();
        $this->assertNotNull($table);
        $this->assertEqualsWithDelta(0.9, (float) $table->multiplier, 0.0001);
    }
}
