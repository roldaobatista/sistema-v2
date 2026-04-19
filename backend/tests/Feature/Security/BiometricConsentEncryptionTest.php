<?php

namespace Tests\Feature\Security;

use App\Models\BiometricConsent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regressao sec-07 (re-auditoria Camada 1 2026-04-19):
 *
 * LGPD Art. 11 classifica biometria como dado pessoal sensivel.
 * BiometricConsent guarda descricoes livres (purpose / alternative_method)
 * que podem conter referencias ao dado biometrico coletado.
 *
 * Requisitos:
 *  A) purpose persistido NAO pode ser plaintext no banco (cast encrypted).
 *  B) alternative_method persistido NAO pode ser plaintext no banco.
 *  C) toArray()/toJson() do model NAO vaza purpose nem alternative_method
 *     por default (protected $hidden).
 *  D) makeVisible() continua permitindo exposicao explicita pelo endpoint
 *     que legitimamente precisa retornar o campo.
 */
class BiometricConsentEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    public function test_purpose_is_stored_encrypted_in_database(): void
    {
        $plaintext = 'Reconhecimento facial do colaborador Joao da Silva para ponto';

        BiometricConsent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'data_type' => 'facial',
            'purpose' => $plaintext,
        ]);

        $raw = DB::table('biometric_consents')->value('purpose');

        $this->assertNotNull($raw);
        $this->assertNotSame($plaintext, $raw, 'purpose nao pode estar em plaintext no DB');
        $this->assertStringNotContainsString('Joao da Silva', (string) $raw);

        // Deve ser decriptavel (Laravel encrypted cast)
        $this->assertSame($plaintext, Crypt::decryptString((string) $raw));
    }

    public function test_alternative_method_is_stored_encrypted_in_database(): void
    {
        $plaintext = 'Registro manual com supervisor Maria Souza (matricula 42)';

        BiometricConsent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'data_type' => 'facial',
            'alternative_method' => $plaintext,
        ]);

        $raw = DB::table('biometric_consents')->value('alternative_method');

        $this->assertNotNull($raw);
        $this->assertNotSame($plaintext, $raw, 'alternative_method nao pode estar em plaintext no DB');
        $this->assertStringNotContainsString('Maria Souza', (string) $raw);
        $this->assertSame($plaintext, Crypt::decryptString((string) $raw));
    }

    public function test_encrypted_cast_roundtrip_returns_plaintext_in_model(): void
    {
        $consent = BiometricConsent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'data_type' => 'fingerprint',
            'purpose' => 'Digital do polegar direito',
            'alternative_method' => 'Cartao magnetico',
        ]);

        $fresh = BiometricConsent::withoutGlobalScope('tenant')->find($consent->id);

        $this->assertSame('Digital do polegar direito', $fresh->purpose);
        $this->assertSame('Cartao magnetico', $fresh->alternative_method);
    }

    public function test_to_array_hides_sensitive_fields_by_default(): void
    {
        $consent = BiometricConsent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'purpose' => 'Reconhecimento facial para ponto',
            'alternative_method' => 'Registro manual',
        ]);

        $array = $consent->toArray();

        $this->assertArrayNotHasKey('purpose', $array);
        $this->assertArrayNotHasKey('alternative_method', $array);

        $json = $consent->toJson();
        $this->assertStringNotContainsString('Reconhecimento facial', $json);
        $this->assertStringNotContainsString('Registro manual', $json);
    }

    public function test_make_visible_allows_explicit_exposure(): void
    {
        $consent = BiometricConsent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'purpose' => 'Reconhecimento facial para ponto',
            'alternative_method' => 'Registro manual',
        ]);

        $array = $consent->makeVisible(['purpose', 'alternative_method'])->toArray();

        $this->assertSame('Reconhecimento facial para ponto', $array['purpose']);
        $this->assertSame('Registro manual', $array['alternative_method']);
    }

    public function test_backfill_migration_is_idempotent(): void
    {
        // Insere registro ja-encriptado diretamente no DB (simula estado pos-migration).
        $encrypted = Crypt::encryptString('Ja encriptado — backfill nao deve tocar');

        $id = DB::table('biometric_consents')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'data_type' => 'facial',
            'legal_basis' => 'consent',
            'purpose' => $encrypted,
            'consented_at' => now()->format('Y-m-d'),
            'retention_days' => 365,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_04_19_500001_encrypt_biometric_consent_sensitive_fields.php'
        );

        // Executa 2x — nao pode falhar nem reencriptar.
        $migration->up();
        $migration->up();

        $after = DB::table('biometric_consents')->where('id', $id)->value('purpose');
        $this->assertSame(
            $encrypted,
            $after,
            'backfill nao pode re-encriptar valores ja encriptados (duplicacao de cripto)'
        );
        // E continua decriptavel uma unica vez.
        $this->assertSame('Ja encriptado — backfill nao deve tocar', Crypt::decryptString($after));
    }

    public function test_backfill_migration_encrypts_plaintext_legacy_rows(): void
    {
        $plaintext = 'Consent legado salvo em plaintext antes da Wave sec-07';

        $id = DB::table('biometric_consents')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'data_type' => 'facial',
            'legal_basis' => 'consent',
            'purpose' => $plaintext,
            'alternative_method' => 'plaintext alt',
            'consented_at' => now()->format('Y-m-d'),
            'retention_days' => 365,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_04_19_500001_encrypt_biometric_consent_sensitive_fields.php'
        );

        $migration->up();

        $row = DB::table('biometric_consents')->where('id', $id)->first();

        $this->assertNotSame($plaintext, $row->purpose);
        $this->assertSame($plaintext, Crypt::decryptString($row->purpose));
        $this->assertSame('plaintext alt', Crypt::decryptString($row->alternative_method));

        // Idempotencia: rodar de novo nao muda.
        $firstCipher = $row->purpose;
        $migration->up();
        $row2 = DB::table('biometric_consents')->where('id', $id)->first();
        $this->assertSame($firstCipher, $row2->purpose);
    }
}
