<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data-fix para regressão de encryption (Onda 7.1 / SEC-RA-01, SEC-RA-02).
 *
 * CONTEXTO:
 *
 * 1) user_2fa — dupla criptografia:
 *    Antes: controller fazia encrypt() manual + Model tinha cast 'encrypted'.
 *    Resultado: valores no DB podem estar 1x ou 2x criptografados (depende do
 *    caminho: via DB::table = 1x, via Eloquent = 2x). Impossível recuperar
 *    determinísticamente — segurança exige invalidação.
 *    Ação: zerar secret/backup_codes e marcar is_enabled=false para todos
 *    os registros. Usuários precisam reconfigurar 2FA.
 *
 * 2) tenants.fiscal_nfse_token — legado plain text:
 *    Antes: gravado em plain text (cast 'encrypted' adicionado agora).
 *    Valores legacy falhariam ao decriptar via cast.
 *    Ação: tenta decriptar; se falhar, re-grava encriptado.
 *    Idempotente — roda sem dor em bancos sem dados afetados.
 *
 * 3) tenants.fiscal_certificate_password:
 *    Já era gravado via Crypt::encryptString (1x). Com cast 'encrypted' a
 *    leitura decripta a mesma camada. Nenhuma ação necessária.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. user_2fa: invalidar todos (dupla cripto impossível de recuperar com segurança)
        if (DB::getSchemaBuilder()->hasTable('user_2fa')) {
            $affected = DB::table('user_2fa')->update([
                'secret' => '',
                'backup_codes' => null,
                'is_enabled' => false,
                'verified_at' => null,
            ]);

            if ($affected > 0) {
                Log::warning("Encryption fix: {$affected} registros de user_2fa resetados. Usuários devem reconfigurar 2FA.");
            }
        }

        // 2. tenants.fiscal_nfse_token: encriptar valores legacy plain
        if (DB::getSchemaBuilder()->hasTable('tenants')
            && DB::getSchemaBuilder()->hasColumn('tenants', 'fiscal_nfse_token')) {
            $tenants = DB::table('tenants')
                ->whereNotNull('fiscal_nfse_token')
                ->where('fiscal_nfse_token', '!=', '')
                ->select('id', 'fiscal_nfse_token')
                ->get();

            foreach ($tenants as $tenant) {
                $value = $tenant->fiscal_nfse_token;

                try {
                    // Se decripta, já está encriptado — não tocar
                    decrypt($value);
                } catch (\Throwable) {
                    // Não decripta: está plain. Encriptar in-place.
                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update(['fiscal_nfse_token' => encrypt($value)]);
                }
            }
        }
    }

    public function down(): void
    {
        // Não há como reverter: secrets de 2FA foram zerados por segurança.
        // fiscal_nfse_token: idem — reverter para plain reintroduziria a vulnerabilidade.
    }
};
