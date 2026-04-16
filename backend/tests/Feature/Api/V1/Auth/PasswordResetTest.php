<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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
            'email' => 'reset-test@example.com',
            'password' => 'OldPassword1',
        ]);
    }

    // ───── sendResetLink ─────

    public function test_send_reset_link_returns_success_message(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $this->user->email,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.',
        ]);
    }

    public function test_send_reset_link_returns_same_message_for_nonexistent_email(): void
    {
        // Security: never reveal whether the email exists
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.',
        ]);
    }

    public function test_send_reset_link_validates_email_required(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_send_reset_link_validates_email_format(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // ───── reset ─────

    public function test_reset_password_with_valid_token_succeeds(): void
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'NewSecure1Pass',
            'password_confirmation' => 'NewSecure1Pass',
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'Senha redefinida com sucesso. Faça login com a nova senha.',
        ]);

        // Confirm password changed — the new password should work
        $this->user->refresh();
        $this->assertTrue(Hash::check('NewSecure1Pass', $this->user->password));
    }

    public function test_reset_password_revokes_all_existing_tokens(): void
    {
        // Create some personal access tokens
        $this->user->createToken('device-1');
        $this->user->createToken('device-2');
        $this->assertCount(2, $this->user->tokens);

        $token = Password::createToken($this->user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'NewSecure1Pass',
            'password_confirmation' => 'NewSecure1Pass',
        ]);

        $this->user->refresh();
        $this->assertCount(0, $this->user->tokens);
    }

    public function test_reset_password_with_invalid_token_fails(): void
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'invalid-token-12345',
            'email' => $this->user->email,
            'password' => 'NewSecure1Pass',
            'password_confirmation' => 'NewSecure1Pass',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Token de redefinição inválido ou expirado.',
        ]);
    }

    public function test_reset_password_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/reset-password', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'NewSecure1Pass',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_enforces_complexity_rules(): void
    {
        $token = Password::createToken($this->user);

        // Too short and no uppercase/numbers
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_with_wrong_email_fails(): void
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'wrong@example.com',
            'password' => 'NewSecure1Pass',
            'password_confirmation' => 'NewSecure1Pass',
        ]);

        $response->assertStatus(422);
        // Should return INVALID_USER message
        $response->assertJsonFragment([
            'message' => 'Usuário não encontrado.',
        ]);
    }

    public function test_reset_password_creates_audit_log(): void
    {
        $token = Password::createToken($this->user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'NewSecure1Pass',
            'password_confirmation' => 'NewSecure1Pass',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'password_reset',
        ]);
    }
}
