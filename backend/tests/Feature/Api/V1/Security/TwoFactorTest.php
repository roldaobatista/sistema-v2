<?php

namespace Tests\Feature\Api\V1\Security;

use App\Http\Controllers\Api\V1\Security\TwoFactorController;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Http\Requests\Security\DisableTwoFactorRequest;
use App\Http\Requests\Security\EnableTwoFactorRequest;
use App\Http\Requests\Security\VerifyTwoFactorRequest;
use App\Models\Tenant;
use App\Models\TwoFactorAuth;
use App\Models\User;
use App\Notifications\TwoFactorVerificationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorTest extends TestCase
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
            'password' => 'Password1',
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    /**
     * Helper to create a properly validated FormRequest for controller testing.
     */
    private function makeValidatedRequest(string $requestClass, array $data): object
    {
        $request = new $requestClass($data);
        $request->setUserResolver(fn () => $this->user);
        $request->setContainer(app());

        // Manually create and set the validator so validated() works
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);
        $request->setValidator($validator);

        return $request;
    }

    // ───── status ─────

    public function test_status_returns_disabled_when_no_2fa_configured(): void
    {
        $response = $this->getJson('/api/v1/security/2fa/status');

        $response->assertOk();
        $response->assertJsonPath('data.enabled', false);
    }

    public function test_status_returns_real_contract_fields(): void
    {
        $twoFa = TwoFactorAuth::forceCreate([
            'user_id' => $this->user->id,
            'secret' => encrypt('test-secret'),
            'method' => 'email',
            'is_enabled' => true,
            'verified_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/security/2fa/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['enabled', 'method', 'verified_at'],
        ]);
        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonPath('data.method', 'email');
        $response->assertJsonPath('data.verified_at', $twoFa->verified_at?->toJSON());
    }

    // ───── enable ─────

    public function test_enable_requires_correct_password(): void
    {
        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            EnableTwoFactorRequest::class,
            ['method' => 'email', 'password' => 'WrongPassword']
        );

        $response = $controller->enable($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Senha incorreta', $data['message']);
    }

    public function test_enable_with_email_method_creates_2fa_record(): void
    {
        Notification::fake();

        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            EnableTwoFactorRequest::class,
            ['method' => 'email', 'password' => 'Password1']
        );

        $response = $controller->enable($request);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('user_2fa', [
            'user_id' => $this->user->id,
            'method' => 'email',
            'is_enabled' => false,
        ]);

        $this->assertNotNull(Cache::get("2fa_verify_{$this->user->id}"));
    }

    public function test_enable_with_app_method_returns_secret(): void
    {
        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            EnableTwoFactorRequest::class,
            ['method' => 'app', 'password' => 'Password1']
        );

        $response = $controller->enable($request);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($data['secret']);
        $this->assertEquals('app', $data['method']);
    }

    public function test_enable_with_email_does_not_return_secret(): void
    {
        Notification::fake();

        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            EnableTwoFactorRequest::class,
            ['method' => 'email', 'password' => 'Password1']
        );

        $response = $controller->enable($request);
        $data = json_decode($response->getContent(), true);

        $this->assertNull($data['secret']);
    }

    // ───── verify ─────

    public function test_verify_with_correct_code_activates_2fa(): void
    {
        $twoFa = TwoFactorAuth::forceCreate([
            'user_id' => $this->user->id,
            'secret' => encrypt('test-secret'),
            'method' => 'email',
            'is_enabled' => false,
            'tenant_id' => $this->tenant->id,
        ]);

        Cache::put("2fa_verify_{$this->user->id}", '123456', now()->addMinutes(10));

        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            VerifyTwoFactorRequest::class,
            ['code' => '123456']
        );

        $response = $controller->verify($request);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());

        $twoFa->refresh();
        $this->assertTrue($twoFa->is_enabled);
        $this->assertNotNull($twoFa->verified_at);

        // Should return 8 backup codes
        $this->assertCount(8, $data['backup_codes']);
    }

    public function test_verify_with_wrong_code_fails(): void
    {
        TwoFactorAuth::forceCreate([
            'user_id' => $this->user->id,
            'secret' => encrypt('test-secret'),
            'method' => 'email',
            'is_enabled' => false,
            'tenant_id' => $this->tenant->id,
        ]);

        Cache::put("2fa_verify_{$this->user->id}", '123456', now()->addMinutes(10));

        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            VerifyTwoFactorRequest::class,
            ['code' => '999999']
        );

        $response = $controller->verify($request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Código inválido', $data['message']);
    }

    public function test_verify_without_2fa_record_fails(): void
    {
        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            VerifyTwoFactorRequest::class,
            ['code' => '123456']
        );

        $response = $controller->verify($request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('2FA não configurado', $data['message']);
    }

    // ───── disable ─────

    public function test_disable_with_correct_password_deactivates_2fa(): void
    {
        TwoFactorAuth::forceCreate([
            'user_id' => $this->user->id,
            'secret' => encrypt('test-secret'),
            'method' => 'email',
            'is_enabled' => true,
            'verified_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            DisableTwoFactorRequest::class,
            ['password' => 'Password1']
        );

        $response = $controller->disable($request);

        $this->assertEquals(200, $response->getStatusCode());

        $twoFa = TwoFactorAuth::where('user_id', $this->user->id)->first();
        $this->assertFalse($twoFa->is_enabled);
    }

    public function test_disable_with_wrong_password_fails(): void
    {
        TwoFactorAuth::forceCreate([
            'user_id' => $this->user->id,
            'secret' => encrypt('test-secret'),
            'method' => 'email',
            'is_enabled' => true,
            'verified_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            DisableTwoFactorRequest::class,
            ['password' => 'WrongPassword']
        );

        $response = $controller->disable($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Senha incorreta', $data['message']);
    }

    public function test_verify_clears_cache_code_after_success(): void
    {
        TwoFactorAuth::forceCreate([
            'user_id' => $this->user->id,
            'secret' => encrypt('test-secret'),
            'method' => 'email',
            'is_enabled' => false,
            'tenant_id' => $this->tenant->id,
        ]);

        Cache::put("2fa_verify_{$this->user->id}", '123456', now()->addMinutes(10));

        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            VerifyTwoFactorRequest::class,
            ['code' => '123456']
        );

        $controller->verify($request);

        $this->assertNull(Cache::get("2fa_verify_{$this->user->id}"));
    }

    public function test_enable_sends_notification_for_email_method(): void
    {
        Notification::fake();

        $controller = new TwoFactorController;
        $request = $this->makeValidatedRequest(
            EnableTwoFactorRequest::class,
            ['method' => 'email', 'password' => 'Password1']
        );

        $controller->enable($request);

        Notification::assertSentTo($this->user, TwoFactorVerificationCode::class);
    }
}
