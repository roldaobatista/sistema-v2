<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guard mecânico sec-11 — garante que nenhum FormRequest valida `tenant_id`
 * no body exceto whitelist explicitamente justificada.
 *
 * Esse é o invariante operacional da Lei 4 (CLAUDE.md): `tenant_id` nunca
 * vem do request body. O auto-fill do trait BelongsToTenant só é bypassável
 * se um FormRequest validar `tenant_id` como input legítimo.
 *
 * Exceções whitelist (documentadas no próprio FormRequest):
 *  - SwitchTenantRequest — usuário explicita para qual tenant trocar (auth).
 *  - IndexConsolidatedFinancialRequest — filtro de leitura cross-tenant validado
 *    contra userTenantIds no controller.
 */
class TenantFillableSafetyTest extends TestCase
{
    /**
     * @var list<class-string>
     */
    private const WHITELIST = [
        'App\\Http\\Requests\\Auth\\SwitchTenantRequest',
        'App\\Http\\Requests\\Financial\\IndexConsolidatedFinancialRequest',
    ];

    public function test_no_form_request_validates_tenant_id_outside_whitelist(): void
    {
        $violations = [];

        foreach ($this->collectFormRequests() as $class) {
            if (in_array($class, self::WHITELIST, true)) {
                continue;
            }

            try {
                $rulesSource = $this->getRulesSource($class);
            } catch (\Throwable) {
                continue;
            }

            if ($rulesSource === null) {
                continue;
            }

            if (preg_match("/['\"]tenant_id['\"]\s*=>/", $rulesSource) === 1) {
                $violations[] = $class;
            }
        }

        $this->assertSame(
            [],
            $violations,
            "FormRequests abaixo validam 'tenant_id' no body fora da whitelist sec-11:\n".
                implode("\n", array_map(fn ($c) => "  - $c", $violations)).
                "\n\nLei 4 (CLAUDE.md): tenant_id jamais vem do body. Ler via ".
                '$request->user()->current_tenant_id. Se o caso for legítimo (auth switch, '.
                'cross-tenant reporting), adicionar à whitelist deste teste com justificativa.'
        );
    }

    /**
     * @return list<class-string<FormRequest>>
     */
    private function collectFormRequests(): array
    {
        $dir = app_path('Http/Requests');
        if (! is_dir($dir)) {
            return [];
        }

        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $result = [];

        foreach ($rii as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = Str::after($file->getPathname(), realpath($dir).DIRECTORY_SEPARATOR);
            $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
            $class = 'App\\Http\\Requests\\'.substr($relative, 0, -4);

            try {
                if (! class_exists($class)) {
                    continue;
                }
                $reflection = new ReflectionClass($class);
            } catch (ReflectionException) {
                continue;
            }

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(FormRequest::class)) {
                continue;
            }

            $result[] = $class;
        }

        return $result;
    }

    private function getRulesSource(string $class): ?string
    {
        $reflection = new ReflectionClass($class);
        if (! $reflection->hasMethod('rules')) {
            return null;
        }

        $method = new ReflectionMethod($class, 'rules');
        $file = $method->getFileName();
        if ($file === false || ! is_file($file)) {
            return null;
        }

        $lines = file($file);
        if ($lines === false) {
            return null;
        }

        $start = $method->getStartLine() - 1;
        $end = $method->getEndLine();

        return implode('', array_slice($lines, $start, $end - $start));
    }
}
