<?php

namespace Tests\Feature\Security;

use App\Models\ClientPortalUser;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Tests\TestCase;

/**
 * sec-18 (Re-auditoria Camada 1 r3): ClientPortalUser::$fillable não
 * contém campos de hardening (lockout, 2FA secrets, password history).
 *
 * Esses campos são manipulados exclusivamente por código interno de
 * autenticação via forceFill() — nunca por payload HTTP ou mass-assign.
 * Impede que atacante force locked_until=null ou two_factor_confirmed_at
 * = now() via forma de edição de perfil.
 */
class ClientPortalUserFillableTest extends TestCase
{
    /** @return array<int, string> */
    private static function guarded(): array
    {
        return [
            'failed_login_attempts',
            'locked_until',
            'password_changed_at',
            'password_history',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
        ];
    }

    public function test_fillable_nao_contem_campos_de_hardening(): void
    {
        $fillable = (new ClientPortalUser)->getFillable();

        foreach (self::guarded() as $field) {
            $this->assertNotContains($field, $fillable, "ClientPortalUser.\$fillable não deve conter {$field} (sec-18)");
        }
    }

    public function test_mass_assignment_de_campo_hardening_rejeitado_em_strict_mode(): void
    {
        // Model::shouldBeStrict(true) em dev/test: tentar atribuir fora de
        // $fillable lança MassAssignmentException (exceção explícita > silent
        // discard para detectar bugs cedo).
        $this->expectException(MassAssignmentException::class);

        (new ClientPortalUser)->fill([
            'email' => 'attacker@example.com',
            'locked_until' => null, // tentativa de desbloquear
            'two_factor_confirmed_at' => now(), // tentativa de burlar 2FA setup
        ]);
    }
}
