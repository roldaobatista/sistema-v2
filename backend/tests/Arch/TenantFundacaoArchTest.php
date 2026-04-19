<?php

use App\Models\Concerns\BelongsToTenant;
use App\Models\ESocialRubric;
use App\Models\ManagementReviewAction;
use App\Models\ProductKit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

require_once __DIR__.'/ArchTest.php';

/*
|--------------------------------------------------------------------------
| Guards de fundação multi-tenant (qa-05 — Camada 1 re-audit 2026-04-19)
|--------------------------------------------------------------------------
|
| Este arquivo adiciona checks mecânicos sobre as invariantes que a Camada 1
| estabeleceu:
|   1. Todo modelo que usa `BelongsToTenant` deve viver em uma tabela cuja
|      coluna `tenant_id` exista no schema (schema dump SQLite).
|   2. O modelo `User` deve ocultar credenciais sensíveis (password,
|      remember_token, two_factor_secret) via `$hidden`.
|   3. Nenhuma factory pode resolver `tenant_id` via `Tenant::first()`,
|      `Tenant::firstOrCreate()` ou `Tenant::find(...)` — isso reutiliza
|      tenants existentes e causa vazamento entre testes paralelos.
|      Sempre `Tenant::factory()`.
|   4. Nenhuma factory pode hardcodar `'tenant_id' => 1` (ou qualquer literal
|      inteiro) — mesma razão.
*/

/**
 * Retorna as tabelas que possuem coluna `tenant_id` segundo o schema dump.
 *
 * @return array<string, true> mapa ["table_name" => true] para lookup O(1).
 */
function tenantFundacaoTablesWithTenantId(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $schemaPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR
        .'database'.DIRECTORY_SEPARATOR
        .'schema'.DIRECTORY_SEPARATOR
        .'sqlite-schema.sql';

    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException("Schema dump not found: {$schemaPath}");
    }

    // Extrai cada bloco CREATE TABLE "name" ( ... );
    preg_match_all('/CREATE\s+TABLE\s+"([^"]+)"\s*\((.*?)\);/si', $sql, $matches, PREG_SET_ORDER);

    $tables = [];
    foreach ($matches as [$_, $tableName, $body]) {
        // Procura coluna tenant_id dentro do corpo (evita pegar só índices).
        if (preg_match('/"tenant_id"/', $body)) {
            $tables[$tableName] = true;
        }
    }

    return $cache = $tables;
}

test('modelos com BelongsToTenant vivem em tabelas com coluna tenant_id', function (): void {
    $tablesWithTenantId = tenantFundacaoTablesWithTenantId();

    /*
     * EXCEÇÕES CONHECIDAS (débito técnico detectado pela re-auditoria Camada 1
     * 2026-04-19, finding qa-05). Estes modelos declaram `BelongsToTenant` mas
     * a tabela correspondente não tem coluna `tenant_id` no schema dump —
     * isso é bug estrutural separado que deve virar finding próprio.
     *
     * Correto seria: (a) adicionar tenant_id na tabela, OU (b) remover o
     * trait se o modelo é global. Enquanto a decisão não é tomada, o guard
     * falharia contra estado de trabalho legítimo. As exceções ficam
     * explícitas e rastreáveis aqui — jamais silenciar com skip.
     */
    $knownExclusions = [
        ESocialRubric::class,         // tabela e_social_rubrics ausente do schema dump
        ManagementReviewAction::class, // tabela existe sem tenant_id
        ProductKit::class,             // tabela existe sem tenant_id (pivot parent/child)
    ];

    $violations = [];

    foreach (archPhpFiles('app/Models') as $file) {
        $symbol = archPhpSymbol($file);
        if (($symbol['type'] ?? null) !== 'class') {
            continue;
        }

        $fqcn = $symbol['fqcn'];
        if (! class_exists($fqcn)) {
            continue;
        }

        if (in_array($fqcn, $knownExclusions, true)) {
            continue;
        }

        $uses = class_uses_recursive($fqcn);
        if (! in_array(BelongsToTenant::class, $uses, true)) {
            continue;
        }

        $reflection = new ReflectionClass($fqcn);
        if ($reflection->isAbstract()) {
            continue;
        }

        /** @var Model $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        $table = $instance->getTable();

        if (! isset($tablesWithTenantId[$table])) {
            $violations[] = "{$fqcn} usa BelongsToTenant mas a tabela '{$table}' não tem coluna tenant_id no schema dump";
        }
    }

    expect($violations)->toBe([], "Modelos com BelongsToTenant em tabelas sem tenant_id:\n- ".implode("\n- ", $violations));
});

test('User oculta credenciais sensiveis via $hidden', function (): void {
    $user = new User;
    $hidden = $user->getHidden();

    expect($hidden)->toContain('password', 'remember_token', 'two_factor_secret');
});

test('factories com tenant_id nao reutilizam tenants existentes', function (): void {
    $factoriesDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR
        .'database'.DIRECTORY_SEPARATOR
        .'factories';

    if (! is_dir($factoriesDir)) {
        test()->markTestSkipped('Factories dir missing');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($factoriesDir, FilesystemIterator::SKIP_DOTS)
    );

    $violations = [];

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }

        // Só se a factory define tenant_id.
        if (! preg_match("/['\"]tenant_id['\"]\s*=>/", $contents)) {
            continue;
        }

        $relative = str_replace(dirname(__DIR__, 2).DIRECTORY_SEPARATOR, '', $file->getPathname());

        if (preg_match("/['\"]tenant_id['\"]\s*=>\s*Tenant::(first|firstOrCreate|find)\b/", $contents)) {
            $violations[] = "{$relative}: resolve tenant_id via Tenant::first/firstOrCreate/find — use Tenant::factory()";
        }

        if (preg_match("/['\"]tenant_id['\"]\s*=>\s*\d+\b/", $contents)) {
            $violations[] = "{$relative}: hardcoda tenant_id literal inteiro — use Tenant::factory()";
        }
    }

    expect($violations)->toBe([], "Factories com tenant_id inseguro:\n- ".implode("\n- ", $violations));
});
