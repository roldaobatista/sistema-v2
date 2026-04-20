<?php

namespace Tests\Feature\Security;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Re-auditoria Camada 1 r3 — batch sec-Auth (sec-13, sec-17, sec-26).
 *
 *  - sec-13: switchTenant revoga TODOS os tokens do user (reautenticação
 *    forçada em troca de empresa) e emite novo token com ability do tenant
 *    alvo. Token antigo não é mais aceito.
 *  - sec-17: ResetPasswordRequest delega para Password::defaults() (política
 *    centralizada 12+ chars, mixedCase, letters, numbers, symbols).
 *  - sec-26: login rejeita usuário com email_verified_at=NULL (403),
 *    exceto quando AUTH_REQUIRE_EMAIL_VERIFIED=false.
 */
class SecAuthBatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ══════════════════════════════ sec-13 ══════════════════════════════

    public function test_sec13_switch_tenant_revoga_tokens_anteriores_no_db(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $user = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'current_tenant_id' => $tenantA->id,
            'email_verified_at' => now(),
        ]);
        $user->tenants()->attach([$tenantA->id, $tenantB->id]);

        // Cria a permission e atribui (Spatie Permission é tenant-aware —
        // setPermissionsTeamId antes de givePermissionTo).
        setPermissionsTeamId($tenantA->id);
        Permission::findOrCreate('platform.tenant.switch');
        $user->givePermissionTo('platform.tenant.switch');

        // Cria 2 tokens no tenantA — simula multiplas sessoes/devices do user.
        $oldTokenA = $user->createToken('api', ["tenant:{$tenantA->id}"])->accessToken;
        $oldTokenMobile = $user->createToken('mobile', ["tenant:{$tenantA->id}"])->accessToken;
        $oldTokenIds = [$oldTokenA->id, $oldTokenMobile->id];

        $oldBearer = $user->createToken('switch-request', ["tenant:{$tenantA->id}"])->plainTextToken;

        // Switch para tenantB — deve revogar TODOS os tokens anteriores.
        $this->withHeader('Authorization', 'Bearer '.$oldBearer)
            ->postJson('/api/v1/switch-tenant', ['tenant_id' => $tenantB->id])
            ->assertOk();

        // Contrato sec-13: tokens antigos nao existem mais no DB.
        foreach ($oldTokenIds as $oldId) {
            $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldId]);
        }

        // Novo token com ability do tenant alvo foi emitido.
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'abilities' => json_encode(["tenant:{$tenantB->id}"]),
        ]);

        // User::tokens() agora retorna apenas tokens alinhados ao tenantB.
        $remainingTokens = PersonalAccessToken::where('tokenable_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(1, $remainingTokens->count());
        foreach ($remainingTokens as $t) {
            $this->assertContains("tenant:{$tenantB->id}", $t->abilities ?? [],
                'token remanescente deve ter ability do tenant alvo, não do anterior');
        }
    }

    // ══════════════════════════════ sec-17 ══════════════════════════════

    public function test_sec17_reset_password_usa_password_defaults(): void
    {
        $request = new ResetPasswordRequest;
        $rules = $request->rules();

        // Rule é array; o 3º elemento do 'password' deve ser Password::defaults().
        $this->assertArrayHasKey('password', $rules);
        $passwordRule = $rules['password'];
        $this->assertContains('required', $passwordRule);
        $this->assertContains('confirmed', $passwordRule);

        // O último rule deve ser o Password::defaults() — instância de Password.
        $lastRule = end($passwordRule);
        $this->assertInstanceOf(Password::class, $lastRule);
    }

    public function test_sec17_reset_password_rejeita_senha_fraca_segundo_defaults(): void
    {
        // 12+ chars, mixedCase, letters, numbers, symbols (AppServiceProvider).
        $validator = Validator::make([
            'token' => 'abc',
            'email' => 'user@example.com',
            'password' => 'weak123',
            'password_confirmation' => 'weak123',
        ], (new ResetPasswordRequest)->rules());

        $this->assertTrue($validator->fails(), 'senha fraca deve ser rejeitada');
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_sec17_reset_password_aceita_senha_forte(): void
    {
        $validator = Validator::make([
            'token' => 'abc',
            'email' => 'user@example.com',
            'password' => 'Str0ng!Password#2026',
            'password_confirmation' => 'Str0ng!Password#2026',
        ], (new ResetPasswordRequest)->rules());

        // Pode falhar em 'uncompromised' se a política incluir (HaveIBeenPwned),
        // mas a senha sintética deve ser estruturalmente válida. Verificamos
        // que se falhar, é apenas pelo check uncompromised (network).
        // qa-01 (Re-auditoria Camada 1 r4): substituído assertTrue(true) por
        // assertion real sobre o campo `password`. Regra: se passou, o array
        // de erros não deve conter `password` (estrutura válida).
        if ($validator->fails()) {
            $errors = $validator->errors()->get('password');
            foreach ($errors as $error) {
                $this->assertStringContainsStringIgnoringCase('compromised', $error, "erro inesperado: {$error}");
            }
        } else {
            $this->assertArrayNotHasKey(
                'password',
                $validator->errors()->toArray(),
                'senha estruturalmente forte não deve gerar erros em password',
            );
            $this->assertSame([], $validator->errors()->get('password'));
        }
    }

    // ══════════════════════════════ sec-26 ══════════════════════════════

    public function test_sec26_login_rejeita_email_nao_verificado(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'email' => 'unverified@example.com',
            'password' => Hash::make('Correct!Pass#1234567'),
            'is_active' => true,
            'email_verified_at' => null, // legado / registration sem confirmação
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'Correct!Pass#1234567',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('verificado', (string) $response->json('message'));
    }

    public function test_sec26_login_permite_email_verificado(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'email' => 'verified@example.com',
            'password' => Hash::make('Correct!Pass#1234567'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'verified@example.com',
            'password' => 'Correct!Pass#1234567',
        ])->assertOk();
    }

    public function test_sec26_feature_flag_off_permite_email_null(): void
    {
        config(['auth.require_email_verified' => false]);

        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'email' => 'override@example.com',
            'password' => Hash::make('Correct!Pass#1234567'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'override@example.com',
            'password' => 'Correct!Pass#1234567',
        ])->assertOk();
    }
}
