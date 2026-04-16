<?php

declare(strict_types=1);

$autoloadPath = __DIR__.'/../vendor/autoload.php';

if (! file_exists($autoloadPath)) {
    exit(0);
}

$contents = file_get_contents($autoloadPath);

if (! is_string($contents) || str_contains($contents, 'registerOptionalVendorWarningFilter')) {
    exit(0);
}

// Use regex to match any ComposerAutoloaderInit hash
$pattern = '/require_once __DIR__ \. \'\/composer\/autoload_real\.php\';\s*\n\s*\n\s*return (ComposerAutoloaderInit[0-9a-f]+)::getLoader\(\);/';

if (! preg_match($pattern, $contents, $matches)) {
    fwrite(STDERR, "vendor/autoload.php has an unexpected structure.\n");
    exit(1);
}

$initClass = $matches[1];

$search = $matches[0];

$replace = <<<PHP
if (file_exists(__DIR__ . '/../bootstrap/vendor_warning_filter.php')) {
    require_once __DIR__ . '/../bootstrap/vendor_warning_filter.php';
    \$_vendorWarningFilterRestoreCallback = registerOptionalVendorWarningFilter();
}

require_once __DIR__ . '/composer/autoload_real.php';
if (isset(\$_vendorWarningFilterRestoreCallback)) {
\$_vendorAutoloadLoader = {$initClass}::getLoader();
    \$_vendorWarningFilterRestoreCallback();
    unset(\$_vendorWarningFilterRestoreCallback);
    return \$_vendorAutoloadLoader;
}

return {$initClass}::getLoader();

PHP;

$result = str_replace($search, $replace, $contents, $count);

if ($count !== 1) {
    fwrite(STDERR, "Failed to patch vendor/autoload.php.\n");
    exit(1);
}

file_put_contents($autoloadPath, $result);

exit(0);
