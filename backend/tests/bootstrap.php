<?php

require __DIR__.'/../bootstrap/vendor_warning_filter.php';

$restoreVendorWarningFilter = registerOptionalVendorWarningFilter();

putenv('OTEL_PHP_DISABLED_INSTRUMENTATIONS=laravel');
$_ENV['OTEL_PHP_DISABLED_INSTRUMENTATIONS'] = 'laravel';
$_SERVER['OTEL_PHP_DISABLED_INSTRUMENTATIONS'] = 'laravel';

require __DIR__.'/../vendor/autoload.php';

$restoreVendorWarningFilter();
unset($restoreVendorWarningFilter);
