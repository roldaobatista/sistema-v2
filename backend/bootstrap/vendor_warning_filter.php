<?php

declare(strict_types=1);

if (! function_exists('registerOptionalVendorWarningFilter')) {
    function registerOptionalVendorWarningFilter(): callable
    {
        $previousHandler = set_error_handler(
            static function (int $severity, string $message, string $file = '', int $line = 0) use (&$previousHandler): bool {
                $normalizedFile = str_replace('\\', '/', $file);
                $isOptionalOtelWarning = in_array($severity, [E_WARNING, E_USER_WARNING], true)
                    && str_contains($message, 'The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Laravel auto-instrumentation')
                    && str_contains($normalizedFile, '/open-telemetry/opentelemetry-auto-laravel/_register.php');

                if ($isOptionalOtelWarning) {
                    return true;
                }

                if ($previousHandler !== null) {
                    return (bool) $previousHandler($severity, $message, $file, $line);
                }

                return false;
            }
        );

        return static function (): void {
            restore_error_handler();
        };
    }
}
