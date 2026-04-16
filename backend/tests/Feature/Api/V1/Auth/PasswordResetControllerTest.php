<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'reset@test.com',
            'password' => Hash::make('OldPassword123'),
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
    }

    public function test_send_reset_link_validates_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_send_reset_link_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_send_reset_link_returns_generic_message_for_unknown_email(): void
    {
        // Endpoint deve retornar mensagem genérica para não vazar existência de emails
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'nonexistent@nowhere.com',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('Se o e-mail estiver cadastrado', $response->json('message'));
    }

    public function test_send_reset_link_sends_notification_for_valid_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200);
        Notification::assertSentTo($this->user, ResetPassword::class);
    }

    public function test_reset_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    public function test_reset_rejects_weak_password(): void
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'any-token',
            'email' => $this->user->email,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_rejects_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'invalid-token-xyz',
            'email' => $this->user->email,
            'password' => 'NewStrongPass123',
            'password_confirmation' => 'NewStrongPass123',
        ]);

        $response->assertStatus(422);
        // O texto varia mas deve indicar token inválido
        $this->assertStringContainsStringIgnoringCase('token', strtolower((string) $response->json('message')));
    }

    public function test_reset_updates_password_with_valid_token_and_revokes_tokens(): void
    {
        Event::fake([PasswordReset::class]);

        // Gera token real via broker
        $token = Password::broker()->createToken($this->user);

        // Cria um token Sanctum para depois validar revogação
        $this->user->createToken('existing-device');
        $this->assertSame(1, $this->user->tokens()->count());

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'BrandNewPass123',
        ]);

        $response->assertStatus(200);

        $fresh = $this->user->fresh();
        $this->assertTrue(Hash::check('BrandNewPass123', $fresh->password));
        $this->assertFalse(Hash::check('OldPassword123', $fresh->password));

        // Todos os tokens devem ter sido revogados
        $this->assertSame(0, $fresh->tokens()->count(), 'Tokens antigos deveriam ser invalidados após reset');
    }
}
