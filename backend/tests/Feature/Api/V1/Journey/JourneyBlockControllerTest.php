<?php

namespace Tests\Feature\Api\V1\Journey;

use App\Enums\TimeClassificationType;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyBlock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JourneyBlockControllerTest extends TestCase
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

    public function test_adjust_returns_404_for_nonexistent_block(): void
    {
        $response = $this->postJson('/api/v1/journey/blocks/99999/adjust', [
            'classification' => TimeClassificationType::JORNADA_NORMAL->value,
            'started_at' => now()->subHours(2)->toDateTimeString(),
            'ended_at' => now()->subHour()->toDateTimeString(),
            'adjustment_reason' => 'Correção de registro',
        ]);

        $response->assertNotFound();
    }

    public function test_adjust_validates_required_fields_for_existing_block(): void
    {
        $block = JourneyBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/journey/blocks/{$block->id}/adjust", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['classification', 'started_at', 'adjustment_reason']);
    }

    public function test_adjust_updates_existing_block(): void
    {
        $block = JourneyBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'classification' => TimeClassificationType::INTERVALO->value,
        ]);

        $startedAt = now()->subHours(3)->startOfMinute();
        $endedAt = now()->subHours(2)->startOfMinute();

        $response = $this->postJson("/api/v1/journey/blocks/{$block->id}/adjust", [
            'classification' => TimeClassificationType::JORNADA_NORMAL->value,
            'started_at' => $startedAt->toDateTimeString(),
            'ended_at' => $endedAt->toDateTimeString(),
            'adjustment_reason' => 'Correção manual auditável',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $block->id)
            ->assertJsonPath('data.classification', TimeClassificationType::JORNADA_NORMAL->value);

        $this->assertDatabaseHas('journey_blocks', [
            'id' => $block->id,
            'tenant_id' => $this->tenant->id,
            'is_auto_classified' => false,
            'is_manually_adjusted' => true,
            'adjusted_by' => $this->user->id,
            'adjustment_reason' => 'Correção manual auditável',
        ]);
    }

    public function test_adjust_rejects_invalid_classification(): void
    {
        $block = JourneyBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/journey/blocks/{$block->id}/adjust", [
            'classification' => 'valor_invalido',
            'started_at' => now()->subHours(2)->toDateTimeString(),
            'ended_at' => now()->subHour()->toDateTimeString(),
            'adjustment_reason' => 'Correção de registro',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['classification']);
    }

    public function test_adjust_rejects_end_before_start(): void
    {
        $block = JourneyBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/journey/blocks/{$block->id}/adjust", [
            'classification' => TimeClassificationType::JORNADA_NORMAL->value,
            'started_at' => now()->subHour()->toDateTimeString(),
            'ended_at' => now()->subHours(2)->toDateTimeString(),
            'adjustment_reason' => 'Correção de registro',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ended_at']);
    }

    public function test_adjust_returns_404_for_cross_tenant_block(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $block = JourneyBlock::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->postJson("/api/v1/journey/blocks/{$block->id}/adjust", [
            'classification' => TimeClassificationType::JORNADA_NORMAL->value,
            'started_at' => now()->subHours(2)->toDateTimeString(),
            'ended_at' => now()->subHour()->toDateTimeString(),
            'adjustment_reason' => 'Correção de registro',
        ]);

        $response->assertNotFound();
    }

    public function test_adjust_recalculates_duration_minutes(): void
    {
        $block = JourneyBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'duration_minutes' => 15,
        ]);

        $startedAt = now()->subHours(2)->startOfMinute();
        $endedAt = $startedAt->copy()->addMinutes(75);

        $response = $this->postJson("/api/v1/journey/blocks/{$block->id}/adjust", [
            'classification' => TimeClassificationType::JORNADA_NORMAL->value,
            'started_at' => $startedAt->toDateTimeString(),
            'ended_at' => $endedAt->toDateTimeString(),
            'adjustment_reason' => 'Recalculo de duracao',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('journey_blocks', [
            'id' => $block->id,
            'duration_minutes' => 75,
        ]);
    }

    public function test_adjust_allows_open_ended_block(): void
    {
        $block = JourneyBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'ended_at' => null,
            'duration_minutes' => 0,
        ]);

        $response = $this->postJson("/api/v1/journey/blocks/{$block->id}/adjust", [
            'classification' => TimeClassificationType::JORNADA_NORMAL->value,
            'started_at' => now()->subHour()->toDateTimeString(),
            'adjustment_reason' => 'Bloco em aberto',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $block->id);
    }
}
