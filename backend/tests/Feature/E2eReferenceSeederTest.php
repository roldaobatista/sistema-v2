<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\E2eReferenceSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class E2eReferenceSeederTest extends TestCase
{
    public function test_restricted_user_is_updated_idempotently_and_can_login(): void
    {
        config(['seeding.user_password' => 'CHANGE_ME_E2E_RESTRICTED_PASSWORD']);

        $staleTenant = Tenant::factory()->create([
            'document' => '12.345.678/0001-90',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        User::factory()->create([
            'email' => 'ricardo@techassist.com.br',
            'password' => Hash::make('stale-password'),
            'is_active' => false,
            'tenant_id' => $staleTenant->id,
            'current_tenant_id' => $staleTenant->id,
        ]);

        $this->seed(E2eReferenceSeeder::class);
        $this->seed(E2eReferenceSeeder::class);

        $techAssist = Tenant::where('document', '98.765.432/0001-10')->firstOrFail();
        $user = User::where('email', 'ricardo@techassist.com.br')->firstOrFail();

        $this->assertSame('Ricardo Técnico', $user->name);
        $this->assertTrue(Hash::check('CHANGE_ME_E2E_RESTRICTED_PASSWORD', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertSame($techAssist->id, $user->tenant_id);
        $this->assertSame($techAssist->id, $user->current_tenant_id);
        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $techAssist->id,
            'is_default' => true,
        ]);

        setPermissionsTeamId($techAssist->id);
        $this->assertTrue($user->fresh()->hasRole('tecnico'));

        $response = $this->postJson('/api/v1/login', [
            'email' => 'ricardo@techassist.com.br',
            'password' => 'CHANGE_ME_E2E_RESTRICTED_PASSWORD',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'ricardo@techassist.com.br')
            ->assertJsonPath('data.user.tenant_id', $techAssist->id);

        $this->assertContains('tecnico', $response->json('data.user.roles'));
    }
}
