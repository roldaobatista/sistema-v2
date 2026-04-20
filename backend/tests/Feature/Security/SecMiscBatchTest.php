<?php

namespace Tests\Feature\Security;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
use App\Models\Tenant;
use App\Models\TwoFactorAuth;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Re-auditoria Camada 1 r3 — batch sec-Misc (sec-19/20/21/22/23).
 *
 *  - sec-19: TwoFactorAuth::backup_codes hash-at-rest via mutator
 *    (idempotente, ignora códigos já hasheados).
 *  - sec-20: Tenant.fiscal_certificate_password NÃO é mass-assignable.
 *  - sec-21: SendPasswordResetLinkRequest e ResetPasswordRequest
 *    normalizam email em lowercase (consistente com LoginRequest).
 *  - sec-22: rate limiter de login usa key composta ip+email
 *    (regressão — já implementado em AuthController).
 *  - sec-23: User.denied_permissions NÃO aparece em toArray()/toJson().
 */
class SecMiscBatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ══════════════════════════════ sec-19 ══════════════════════════════

    public function test_sec19_backup_codes_armazenados_hasheados_via_mutator(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $plainCodes = ['CODE-A1B2', 'CODE-C3D4', 'CODE-E5F6'];

        $twoFa = new TwoFactorAuth;
        $twoFa->tenant_id = $tenant->id;
        $twoFa->user_id = $user->id;
        $twoFa->method = 'totp';
        $twoFa->secret = 'JBSWY3DPEHPK3PXP'; // TOTP secret (NOT NULL no schema)
        $twoFa->backup_codes = $plainCodes; // setter hasheia automaticamente
        $twoFa->save();

        $fresh = TwoFactorAuth::find($twoFa->id);
        $storedCodes = $fresh->backup_codes;

        $this->assertCount(3, $storedCodes);
        foreach ($storedCodes as $stored) {
            $this->assertStringStartsWith('$2y$', $stored, "backup code deveria estar bcrypt-hashado, veio: {$stored}");
        }

        // Hash::check deve bater com o plaintext original.
        foreach ($plainCodes as $i => $plain) {
            $this->assertTrue(Hash::check($plain, $storedCodes[$i]));
        }
    }

    public function test_sec19_mutator_idempotente_para_codes_ja_hasheados(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $preHashed = [Hash::make('already-hashed-1'), Hash::make('already-hashed-2')];

        $twoFa = new TwoFactorAuth;
        $twoFa->tenant_id = $tenant->id;
        $twoFa->user_id = $user->id;
        $twoFa->method = 'totp';
        $twoFa->secret = 'JBSWY3DPEHPK3PXP';
        $twoFa->backup_codes = $preHashed;
        $twoFa->save();

        // Hashes pré-existentes devem ser preservados — não re-hasheados.
        $this->assertSame($preHashed, $twoFa->fresh()->backup_codes);
    }

    // ══════════════════════════════ sec-20 ══════════════════════════════

    public function test_sec20_fiscal_certificate_password_fora_de_fillable(): void
    {
        $fillable = (new Tenant)->getFillable();

        $this->assertNotContains('fiscal_certificate_password', $fillable,
            'sec-20: fiscal_certificate_password não deve ser mass-assignable');
    }

    public function test_sec20_mass_assignment_de_fiscal_password_rejeitado(): void
    {
        $this->expectException(MassAssignmentException::class);

        (new Tenant)->fill([
            'name' => 'Test',
            'document' => '00000000000000',
            'fiscal_certificate_password' => 'forge-attempt',
        ]);
    }

    // ══════════════════════════════ sec-21 ══════════════════════════════

    public function test_sec21_send_password_reset_link_normaliza_email(): void
    {
        $request = SendPasswordResetLinkRequest::create(
            '/', 'POST', ['email' => '  User@EXAMPLE.COM  ']
        );

        // prepareForValidation é invocado via validateResolved; chamar manualmente
        // o prepare via reflection (mesma técnica do Laravel core em testes).
        $ref = new \ReflectionClass($request);
        $method = $ref->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('user@example.com', $request->input('email'));
    }

    public function test_sec21_reset_password_normaliza_email(): void
    {
        $request = ResetPasswordRequest::create(
            '/', 'POST',
            ['email' => 'Victim@EX.COM', 'token' => 'abc', 'password' => 'x', 'password_confirmation' => 'x']
        );

        $ref = new \ReflectionClass($request);
        $method = $ref->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('victim@ex.com', $request->input('email'));
    }

    // ══════════════════════════════ sec-22 ══════════════════════════════

    public function test_sec22_rate_limiter_usa_key_composta_ip_e_email(): void
    {
        Cache::flush();

        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'email' => 'victim@example.com',
            'password' => Hash::make('CorrectPass!1234567'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Dispara 1 tentativa falha do IP 1.1.1.1 contra email=victim.
        $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'victim@example.com',
                'password' => 'wrong',
            ])->assertStatus(422);

        // Key do throttle deve incluir IP + email.
        $this->assertTrue(
            Cache::has('login_attempts:1.1.1.1:victim@example.com'),
            'throttleKey deve combinar IP e email (sec-22)'
        );

        // Mesmo IP com email DIFERENTE não compartilha o contador.
        $this->assertFalse(
            Cache::has('login_attempts:1.1.1.1:outro@example.com'),
            'keys de emails distintos no mesmo IP devem ser independentes'
        );
    }

    // ══════════════════════════════ sec-23 ══════════════════════════════

    public function test_sec23_denied_permissions_em_hidden_nao_vaza_em_array(): void
    {
        $this->assertContains('denied_permissions', (new User)->getHidden(),
            'sec-23: denied_permissions deve estar em $hidden');
    }

    public function test_sec23_denied_permissions_ausente_em_user_serializado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        // Seta denied_permissions via forceFill (campo fora de fillable).
        $user->forceFill(['denied_permissions' => ['some.permission']])->save();

        $array = $user->fresh()->toArray();

        $this->assertArrayNotHasKey('denied_permissions', $array,
            'sec-23: denied_permissions não deve aparecer em toArray()');
        $this->assertStringNotContainsString(
            'denied_permissions',
            $user->fresh()->toJson(),
            'sec-23: denied_permissions não deve aparecer em toJson()'
        );
    }
}
