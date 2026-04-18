<?php

use App\Models\MarketingIntegration;
use App\Models\SsoConfig;
use App\Models\Tenant;
use App\Models\TwoFactorAuth;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Regressão Onda 7.1 — SEC-RA-01, SEC-RA-02, SEC-RA-03.
 *
 * Invariantes validados para cada Model com cast `encrypted`:
 *  (1) Valor bruto no DB é ciphertext (getRawOriginal != plain).
 *  (2) Ciphertext é reversível uma vez (decrypt(raw) == plain) — ou seja,
 *      NÃO é dupla criptografia.
 *  (3) Acesso via atributo ($model->campo) retorna plain (cast decripta).
 *
 * Este teste é o que faltava para pegar a regressão que deixou o 2FA
 * quebrado passar pela suite verde.
 */
uses()->group('critical', 'encryption');

function assertEncryptedField(object $model, string $field, string $plain): void
{
    $raw = $model->getRawOriginal($field);

    expect($raw)
        ->not->toBe($plain, "Campo {$field} foi gravado em plain text no DB (encryption-at-rest violada).")
        ->and($raw)
        ->not->toBeNull("Campo {$field} ficou null após persistência.");

    // Invariante chave: ciphertext decripta UMA VEZ para o plain esperado
    // (cast 'encrypted' usa Crypt::encryptString — sem serialize).
    // Se fosse dupla cripto, decryptString() retornaria ciphertext, não plain.
    $decrypted = Crypt::decryptString($raw);
    expect($decrypted)->toBe($plain, "Campo {$field} parece duplamente criptografado (decryptString(raw) não bate com plain).");

    // E o cast do Model retorna plain transparentemente.
    expect($model->{$field})->toBe($plain, "Cast encrypted falhou em decriptar {$field} ao acessar via atributo.");
}

it('TwoFactorAuth.secret é encrypted-at-rest, 1x, reversível via cast', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $plain = 'TOTP_SECRET_ABCDEFGHIJ1234567890';

    $twoFa = TwoFactorAuth::create([
        'user_id' => $user->id,
        'tenant_id' => $this->tenant->id,
        'secret' => $plain,
        'method' => 'app',
        'is_enabled' => false,
    ]);

    $twoFa->refresh();

    assertEncryptedField($twoFa, 'secret', $plain);
});

it('TwoFactorAuth.backup_codes é hash-at-rest (bcrypt), irreversível, verificável via Hash::check', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $plainCodes = ['CODE1', 'CODE2', 'CODE3', 'CODE4'];
    $hashedCodes = array_map(fn (string $c) => Hash::make($c), $plainCodes);

    $twoFa = TwoFactorAuth::create([
        'user_id' => $user->id,
        'tenant_id' => $this->tenant->id,
        'secret' => 'any',
        'method' => 'app',
        'backup_codes' => $hashedCodes,
    ]);

    $twoFa->refresh();

    $stored = $twoFa->backup_codes;

    expect($stored)
        ->toBeArray()
        ->toHaveCount(count($plainCodes), 'Quantidade de hashes nao bate com quantidade de codes.')
        ->each->toStartWith('$2y$', 'backup_codes deveria ser array de hashes bcrypt ($2y$...).');

    foreach ($plainCodes as $index => $plain) {
        expect(Hash::check($plain, $stored[$index]))->toBeTrue("Hash::check falhou para code {$plain}");
        expect($stored[$index])->not->toBe($plain, 'backup_code[]' . $index . ' armazenado em plain text.');
    }
});

it('Tenant.fiscal_certificate_password é encrypted-at-rest, 1x, reversível via cast', function () {
    $tenant = Tenant::factory()->create();

    $plain = 'certificado-senha-123';

    $tenant->fiscal_certificate_password = $plain;
    $tenant->save();
    $tenant->refresh();

    assertEncryptedField($tenant, 'fiscal_certificate_password', $plain);
});

it('Tenant.fiscal_nfse_token é encrypted-at-rest, 1x, reversível via cast', function () {
    $tenant = Tenant::factory()->create();

    $plain = 'nfse-token-XYZ-9876543210';

    $tenant->fiscal_nfse_token = $plain;
    $tenant->save();
    $tenant->refresh();

    assertEncryptedField($tenant, 'fiscal_nfse_token', $plain);
});

it('MarketingIntegration.api_key é encrypted-at-rest, 1x, reversível via cast', function () {
    $plain = 'mc-api-key-ABCDEF1234567890';

    $integration = MarketingIntegration::create([
        'provider' => 'mailchimp',
        'api_key' => $plain,
        'sync_contacts' => true,
        'sync_events' => false,
    ]);

    $integration->refresh();

    assertEncryptedField($integration, 'api_key', $plain);
});

it('SsoConfig.client_id e client_secret são encrypted-at-rest, 1x, reversível via cast', function () {
    $plainId = 'sso-client-id-ABCDEF';
    $plainSecret = 'sso-client-secret-XYZ-7654321';

    $sso = SsoConfig::create([
        'provider' => 'google',
        'client_id' => $plainId,
        'client_secret' => $plainSecret,
        'is_active' => true,
    ]);

    $sso->refresh();

    assertEncryptedField($sso, 'client_id', $plainId);
    assertEncryptedField($sso, 'client_secret', $plainSecret);
});

it('raw DB row de user_2fa não é decriptável duas vezes (regressão dupla cripto)', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    TwoFactorAuth::create([
        'user_id' => $user->id,
        'tenant_id' => $this->tenant->id,
        'secret' => 'TOTP_SECRET_32_CHARS_XXXXXXXXXXXX',
        'method' => 'app',
        'is_enabled' => false,
    ]);

    $row = DB::table('user_2fa')->where('user_id', $user->id)->first();

    expect($row)->not->toBeNull();

    $decryptedOnce = Crypt::decryptString($row->secret);

    // Se fosse dupla cripto, decryptString() voltaria outro ciphertext decriptável.
    // Com correção, decryptString() retorna o secret plain e uma nova tentativa falha.
    expect(fn () => Crypt::decryptString($decryptedOnce))
        ->toThrow(DecryptException::class, null, 'user_2fa.secret está duplamente criptografado — dupla cripto da regressão ainda presente.');
});
