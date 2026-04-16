<?php

namespace Tests\Feature;

use Tests\TestCase;

class BootstrapSecurityRegressionTest extends TestCase
{
    public function test_public_index_does_not_dump_env_or_force_runtime_env(): void
    {
        $index = file_get_contents(public_path('index.php'));

        $this->assertIsString($index);
        $this->assertStringNotContainsString('#region agent log', $index);
        $this->assertStringNotContainsString('file_put_contents', $index);
        $this->assertStringNotContainsString('putenv(', $index);
        $this->assertStringNotContainsString('$_ENV[', $index);
        $this->assertStringNotContainsString('$_SERVER[', $index);
    }
}
