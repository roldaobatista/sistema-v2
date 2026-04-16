<?php

namespace Tests\Feature\Commands;

use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class HrScheduledCommandsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    public function test_check_expiring_documents_command_runs(): void
    {
        $this->artisan('hr:check-expiring-documents')
            ->assertExitCode(0);
    }

    public function test_check_expiring_vacations_command_runs(): void
    {
        $this->artisan('hr:check-expiring-vacations')
            ->assertExitCode(0);
    }

    public function test_check_hour_bank_expiry_command_runs(): void
    {
        $this->artisan('hr:check-hour-bank-expiry')
            ->assertExitCode(0);
    }
}
