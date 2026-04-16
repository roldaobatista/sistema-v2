
<?php

use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$files = glob(app_path('Models/*.php'));
$ignore = [
    'Tenant', 'TenantDomain', 'PersonalAccessToken', 'User', 'Role', 'Permission',
];

$missing = [];
foreach ($files as $file) {
    if (is_dir($file)) {
        continue;
    }
    $basename = basename($file, '.php');
    if (in_array($basename, $ignore)) {
        continue;
    }

    $class = 'App\\Models\\'.$basename;
    if (! class_exists($class)) {
        continue;
    }

    $traits = class_uses_recursive($class);
    if (! isset($traits['App\\Models\\Concerns\\BelongsToTenant'])) {
        $missing[] = $basename;
    }
}
echo "MISSING_BELONGS_TO_TENANT:\n";
echo implode("\n", $missing)."\n";
