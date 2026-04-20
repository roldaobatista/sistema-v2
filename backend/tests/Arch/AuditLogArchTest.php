<?php

/**
 * Arch test (sec-16.3): produto so pode escrever em audit_logs via AuditLog::log(...).
 *
 * Usos de AuditLog::create([...]) ou new AuditLog(...) + save() fora do proprio
 * model AuditLog sao proibidos no app/ — permitem backdate/forjamento bypassando
 * a pipeline centralizada (tenant resolution, user fallback, UA sanitization).
 *
 * Exceptions explicitas:
 *  - backend/app/Models/AuditLog.php (o proprio metodo log() usa forceFill/create)
 *  - tests/** e database/seeders/** podem usar factory/create para fixtures.
 */
test('nenhum arquivo em app/ usa AuditLog::create ou new AuditLog fora do model', function (): void {
    $appPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS)
    );

    $violations = [];

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();

        // Exception: o proprio model AuditLog e a unica classe autorizada a escrever
        // diretamente — ele e o ponto legitimo de gravacao.
        if (str_ends_with($path, 'Models'.DIRECTORY_SEPARATOR.'AuditLog.php')) {
            continue;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            continue;
        }

        // AuditLog::create( — mass-assignment direto
        if (preg_match('/\bAuditLog::create\s*\(/', $contents)) {
            $violations[] = $path.' — usa AuditLog::create(). Use AuditLog::log(...) em vez disso.';
        }

        // new AuditLog( — instancia direta (seguida de ->save() em algum lugar)
        if (preg_match('/\bnew\s+AuditLog\s*\(/', $contents)) {
            $violations[] = $path.' — usa new AuditLog(). Use AuditLog::log(...) em vez disso.';
        }

        // use full qualified tambem
        if (preg_match('/\bApp\\\\Models\\\\AuditLog::create\s*\(/', $contents)) {
            $violations[] = $path.' — usa \\App\\Models\\AuditLog::create(). Use AuditLog::log(...).';
        }
    }

    expect($violations)->toBe(
        [],
        'Encontradas escritas diretas em audit_logs fora do AuditLog::log(). '.
        'Isso bypassa resolucao de tenant/user/UA e permite forjar evidencia. '.
        "Violacoes:\n - ".implode("\n - ", $violations)
    );
});
