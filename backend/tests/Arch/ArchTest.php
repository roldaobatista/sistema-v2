<?php

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

function archPhpSymbol(string $filePath): ?array
{
    $contents = file_get_contents($filePath);
    if ($contents === false) {
        return null;
    }

    $tokens = token_get_all($contents);
    $namespace = '';
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];
        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE) {
            $namespace = '';

            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $part = $tokens[$cursor];
                if (is_string($part) && ($part === ';' || $part === '{')) {
                    break;
                }

                if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $namespace .= $part[1];
                }
            }

            continue;
        }

        if (! in_array($token[0], [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM], true)) {
            continue;
        }

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $nameToken = $tokens[$cursor];
            if (! is_array($nameToken) || $nameToken[0] !== T_STRING) {
                continue;
            }

            return [
                'type' => match ($token[0]) {
                    T_CLASS => 'class',
                    T_TRAIT => 'trait',
                    T_INTERFACE => 'interface',
                    T_ENUM => 'enum',
                },
                'short_name' => $nameToken[1],
                'fqcn' => ltrim($namespace.'\\'.$nameToken[1], '\\'),
            ];
        }
    }

    return null;
}

function archPhpFiles(string $relativePath): array
{
    $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (! is_dir($path)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}

function archAppClassFromFile(string $filePath, string $relativeRoot): string
{
    $rootPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeRoot).DIRECTORY_SEPARATOR;
    $relative = substr($filePath, strlen($rootPath));
    $class = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

    return 'App\\'.str_replace('/', '\\', trim($relativeRoot, '/\\')).'\\'.$class;
}

test('controllers tem suffix Controller', function (): void {
    foreach (archPhpFiles('app/Http/Controllers') as $file) {
        $symbol = archPhpSymbol($file);
        if (($symbol['type'] ?? null) !== 'class') {
            continue;
        }

        if (! class_exists($symbol['fqcn']) || (! is_subclass_of($symbol['fqcn'], BaseController::class) && $symbol['fqcn'] !== BaseController::class)) {
            continue;
        }

        expect($symbol['short_name'])->toEndWith('Controller');
    }
});

test('models estendem Eloquent Model', function (): void {
    foreach (archPhpFiles('app/Models') as $file) {
        $symbol = archPhpSymbol($file);
        if (($symbol['type'] ?? null) !== 'class') {
            continue;
        }

        expect(class_exists($symbol['fqcn']))->toBeTrue();
        expect(is_subclass_of($symbol['fqcn'], Model::class))->toBeTrue();
    }
});

test('sem dd() ou dump() em controllers', function (): void {
    foreach (archPhpFiles('app/Http/Controllers') as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toMatch('/\b(dd|dump)\s*\(/');
    }
});

test('sem dd() ou dump() em services', function (): void {
    foreach (archPhpFiles('app/Services') as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toMatch('/\b(dd|dump)\s*\(/');
    }
});

test('services nao acessam Request diretamente', function (): void {
    foreach (archPhpFiles('app/Services') as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toContain('Illuminate\Http\Request');
    }
});

test('form requests estendem FormRequest', function (): void {
    foreach (archPhpFiles('app/Http/Requests') as $file) {
        $symbol = archPhpSymbol($file);
        if (($symbol['type'] ?? null) !== 'class') {
            continue;
        }

        expect(class_exists($symbol['fqcn']))->toBeTrue();
        expect(is_subclass_of($symbol['fqcn'], FormRequest::class))->toBeTrue();
    }
});

// ============================================================
// CI/CD Pipeline Architecture Tests
// ============================================================

test('workflows obrigatorios existem', function (): void {
    $backendRoot = realpath(dirname(__DIR__, 2));
    $projectRoot = dirname($backendRoot);
    $workflowDir = $projectRoot.DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows';

    $requiredWorkflows = [
        'ci.yml',
        'security.yml',
        'performance.yml',
        'nightly.yml',
        'deploy.yml',
        'dast.yml',
    ];

    foreach ($requiredWorkflows as $workflow) {
        expect(file_exists($workflowDir.DIRECTORY_SEPARATOR.$workflow))
            ->toBeTrue("Workflow {$workflow} deve existir em .github/workflows/");
    }
});

test('pint.json existe e é válido', function (): void {
    $backendRoot = realpath(dirname(__DIR__, 2));
    $pintPath = $backendRoot.DIRECTORY_SEPARATOR.'pint.json';

    expect(file_exists($pintPath))->toBeTrue('pint.json deve existir no backend/');

    $content = file_get_contents($pintPath);
    $config = json_decode($content, true);

    expect($config)->not->toBeNull('pint.json deve ser JSON válido');
    expect($config)->toHaveKey('preset');
    expect($config['preset'])->toBe('laravel');
});

test('phpstan.neon está configurado com level 7+', function (): void {
    $backendRoot = realpath(dirname(__DIR__, 2));
    $neonPath = $backendRoot.DIRECTORY_SEPARATOR.'phpstan.neon';

    expect(file_exists($neonPath))->toBeTrue('phpstan.neon deve existir no backend/');

    $content = file_get_contents($neonPath);

    expect($content)->toMatch('/level:\s*[789]|level:\s*max/');
    expect($content)->toContain('phpstan-baseline.neon');
});

test('phpstan-baseline.neon existe', function (): void {
    $backendRoot = realpath(dirname(__DIR__, 2));
    $baselinePath = $backendRoot.DIRECTORY_SEPARATOR.'phpstan-baseline.neon';

    expect(file_exists($baselinePath))->toBeTrue('phpstan-baseline.neon deve existir no backend/');
});

test('ci.yml contém steps obrigatórios', function (): void {
    $backendRoot = realpath(dirname(__DIR__, 2));
    $projectRoot = dirname($backendRoot);
    $ciPath = $projectRoot.DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows'.DIRECTORY_SEPARATOR.'ci.yml';
    $content = file_get_contents($ciPath);

    expect($content)->toContain('pint --test');
    expect($content)->toContain('phpstan analyse');
    expect($content)->toContain('eslint');
    expect($content)->toContain('axe-core');
});

test('deploy.yml tem health check e rollback', function (): void {
    $backendRoot = realpath(dirname(__DIR__, 2));
    $projectRoot = dirname($backendRoot);
    $deployPath = $projectRoot.DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows'.DIRECTORY_SEPARATOR.'deploy.yml';
    $content = file_get_contents($deployPath);

    expect($content)->toContain('Health Check');
    expect($content)->toContain('Rollback');
    expect($content)->toContain('workflow_run');
    expect($content)->not->toContain('pull_request');
});

test('performance.yml tem lighthouse e bundle size', function (): void {
    $backendRoot = realpath(dirname(__DIR__, 2));
    $projectRoot = dirname($backendRoot);
    $perfPath = $projectRoot.DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows'.DIRECTORY_SEPARATOR.'performance.yml';
    $content = file_get_contents($perfPath);

    expect($content)->toContain('Lighthouse');
    expect($content)->toContain('Bundle Size');
    expect($content)->toContain('pull_request');
});

test('performance.yml usa budget de bundle configuravel e realista', function (): void {
    $backendRoot = realpath(dirname(__DIR__, 2));
    $projectRoot = dirname($backendRoot);
    $perfPath = $projectRoot.DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows'.DIRECTORY_SEPARATOR.'performance.yml';
    $content = file_get_contents($perfPath);

    expect($content)->toContain('MAX_BUNDLE_SIZE_KB');
    expect($content)->toMatch("/MAX_BUNDLE_SIZE_KB:\\s*['\"]2500['\"]/");
    expect($content)->not->toContain('400 * 1024');
});

test('enums sao backed por string', function (): void {
    foreach (archPhpFiles('app/Enums') as $file) {
        $symbol = archPhpSymbol($file);
        if (($symbol['type'] ?? null) !== 'enum') {
            continue;
        }

        expect(enum_exists($symbol['fqcn']))->toBeTrue();

        $reflection = new ReflectionEnum($symbol['fqcn']);

        expect($reflection->isBacked())->toBeTrue();
        expect($reflection->getBackingType()?->getName())->toBe('string');
    }
});
