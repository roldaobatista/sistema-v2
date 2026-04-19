<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sec-07 (re-auditoria Camada 1 2026-04-19):
 *
 * LGPD Art. 11 classifica biometria como dado pessoal sensivel. O model
 * BiometricConsent passou a criptografar `purpose` e `alternative_method`
 * via cast `encrypted`. Esta migration:
 *
 *   1) Amplia `alternative_method` de VARCHAR(255) para TEXT para acomodar
 *      o ciphertext do Laravel encrypter (que e ~3x maior que o plaintext
 *      + IV + MAC). `purpose` ja era TEXT desde a criacao.
 *
 *   2) Backfill idempotente: detecta registros legacy em plaintext (via
 *      try/catch em decrypt()) e reencripta in-place. Registros ja
 *      encriptados nao sao tocados — execucao repetida e segura.
 *
 * Idempotencia: guardada por try/catch em decrypt() por linha por coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('biometric_consents')) {
            return;
        }

        // (1) Alargar alternative_method (varchar(255) -> text) para acomodar ciphertext.
        //     hasColumn guard mantem a migration idempotente em bancos que ja rodaram.
        if (Schema::hasColumn('biometric_consents', 'alternative_method')) {
            Schema::table('biometric_consents', function (Blueprint $table) {
                $table->text('alternative_method')->nullable()->change();
            });
        }

        // (2) Backfill linha-a-linha das colunas sensiveis.
        if (! Schema::hasColumn('biometric_consents', 'purpose')) {
            return;
        }

        $rows = DB::table('biometric_consents')
            ->select('id', 'purpose', 'alternative_method')
            ->get();

        foreach ($rows as $row) {
            $update = [];

            $update = $this->backfillColumn($update, 'purpose', $row->purpose);
            $update = $this->backfillColumn($update, 'alternative_method', $row->alternative_method);

            if ($update !== []) {
                DB::table('biometric_consents')
                    ->where('id', $row->id)
                    ->update($update);
            }
        }
    }

    public function down(): void
    {
        // Sem reversao automatica: voltar para plaintext reintroduziria a
        // vulnerabilidade LGPD Art. 11. Se precisar ampliar o rollback,
        // fazer manualmente decriptando cada registro — nao acionar aqui.
    }

    /**
     * @param  array<string, string>  $update
     * @return array<string, string>
     */
    private function backfillColumn(array $update, string $column, ?string $value): array
    {
        if ($value === null || $value === '') {
            return $update;
        }

        try {
            // Se decripta, ja esta encriptado — nao tocar. Usa decryptString
            // para casar com o cast `encrypted` (que grava via Crypt::encryptString
            // — sem PHP serialize) aplicado a strings no model.
            Crypt::decryptString($value);

            return $update;
        } catch (\Throwable) {
            // Nao decripta: plaintext legado. Encriptar in-place com o mesmo
            // algoritmo que o cast usa na leitura.
            $update[$column] = Crypt::encryptString($value);

            return $update;
        }
    }
};
