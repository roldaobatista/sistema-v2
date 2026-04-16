<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NginxPermissionsPolicyRegressionTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function nginxConfigProvider(): array
    {
        return [
            'default' => ['nginx/default.conf'],
            'http-only' => ['nginx/default-http.conf'],
            'ssl-reference' => ['nginx/default-ssl.conf'],
        ];
    }

    #[DataProvider('nginxConfigProvider')]
    public function test_camera_is_not_blocked_in_nginx_permissions_policy(string $relativePath): void
    {
        $contents = file_get_contents($this->projectPath($relativePath));

        $this->assertIsString($contents);
        $this->assertStringContainsString('Permissions-Policy', $contents);
        $this->assertStringContainsString('camera=(self)', $contents);
        $this->assertStringNotContainsString('camera=()', $contents);
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
