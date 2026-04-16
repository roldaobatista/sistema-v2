<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\CheckSystemAlerts;
use App\Notifications\SystemAlertNotification;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckSystemAlertsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_command_exists(): void
    {
        $this->artisan('system:check-alerts', ['--help' => true])
            ->assertSuccessful();
    }

    public function test_no_alerts_when_all_healthy(): void
    {
        config([
            'health.alerts.queue_pending_threshold' => 100,
            'health.alerts.disk_usage_threshold' => 80,
            'health.alerts.error_count_threshold' => 5,
            'health.alerts.enabled' => true,
            'app.system_alert_email' => 'admin@test.com',
        ]);

        Queue::shouldReceive('size')->with('default')->andReturn(5);

        $this->artisan('system:check-alerts')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_alert_triggered_when_queue_exceeds_threshold(): void
    {
        config([
            'health.alerts.queue_pending_threshold' => 100,
            'health.alerts.disk_usage_threshold' => 80,
            'health.alerts.error_count_threshold' => 5,
            'health.alerts.enabled' => true,
            'app.system_alert_email' => 'admin@test.com',
        ]);

        Queue::shouldReceive('size')->with('default')->andReturn(150);

        $this->artisan('system:check-alerts')
            ->assertSuccessful();

        Notification::assertSentOnDemand(
            SystemAlertNotification::class,
            function (SystemAlertNotification $notification, array $channels, object $notifiable) {
                return in_array('mail', $channels);
            }
        );
    }

    public function test_alert_triggered_when_disk_exceeds_threshold(): void
    {
        config([
            'health.alerts.queue_pending_threshold' => 100,
            'health.alerts.disk_usage_threshold' => 80,
            'health.alerts.error_count_threshold' => 5,
            'health.alerts.enabled' => true,
            'app.system_alert_email' => 'admin@test.com',
        ]);

        Queue::shouldReceive('size')->with('default')->andReturn(0);

        // Register a subclass that overrides getDiskUsagePercent
        $command = new class extends CheckSystemAlerts
        {
            public function getDiskUsagePercent(): float
            {
                return 85.5;
            }
        };
        $this->app->make(Kernel::class)->registerCommand($command);

        $this->artisan('system:check-alerts')
            ->assertSuccessful();

        Notification::assertSentOnDemand(
            SystemAlertNotification::class,
            function (SystemAlertNotification $notification, array $channels, object $notifiable) {
                return in_array('mail', $channels);
            }
        );
    }

    public function test_alert_triggered_when_error_count_exceeds_threshold(): void
    {
        config([
            'health.alerts.queue_pending_threshold' => 100,
            'health.alerts.disk_usage_threshold' => 80,
            'health.alerts.error_count_threshold' => 2,
            'health.alerts.enabled' => true,
            'app.system_alert_email' => 'admin@test.com',
        ]);

        Queue::shouldReceive('size')->with('default')->andReturn(0);

        // Insert failed jobs to trigger error count alert
        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->insert([
                ['uuid' => fake()->uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Test', 'failed_at' => now()->subMinutes(5)],
                ['uuid' => fake()->uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Test', 'failed_at' => now()->subMinutes(3)],
                ['uuid' => fake()->uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Test', 'failed_at' => now()->subMinute()],
            ]);
        }

        $this->artisan('system:check-alerts')
            ->assertSuccessful();

        if (Schema::hasTable('failed_jobs')) {
            Notification::assertSentOnDemand(SystemAlertNotification::class);
        }
    }

    public function test_no_alerts_when_disabled(): void
    {
        config([
            'health.alerts.enabled' => false,
            'app.system_alert_email' => 'admin@test.com',
        ]);

        $this->artisan('system:check-alerts')
            ->assertSuccessful()
            ->expectsOutputToContain('alerts disabled');

        Notification::assertNothingSent();
    }

    public function test_no_alerts_when_no_alert_email_configured(): void
    {
        config([
            'health.alerts.enabled' => true,
            'app.system_alert_email' => null,
        ]);

        $this->artisan('system:check-alerts')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_config_health_file_has_correct_defaults(): void
    {
        $config = config('health');

        $this->assertNotNull($config);
        $this->assertArrayHasKey('alerts', $config);
        $this->assertEquals(100, $config['alerts']['queue_pending_threshold']);
        $this->assertEquals(80, $config['alerts']['disk_usage_threshold']);
        $this->assertEquals(5, $config['alerts']['error_count_threshold']);
        $this->assertTrue($config['alerts']['enabled']);
    }
}
