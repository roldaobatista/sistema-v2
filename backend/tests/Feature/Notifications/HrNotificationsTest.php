<?php

namespace Tests\Feature\Notifications;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationBalance;
use App\Notifications\DocumentExpiryNotification;
use App\Notifications\LeaveApprovalNotification;
use App\Notifications\VacationExpirationNotification;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrNotificationsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_document_expiry_notification_has_correct_data(): void
    {
        $document = EmployeeDocument::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'category' => 'aso',
            'name' => 'ASO Periódico',
            'file_path' => '/docs/aso.pdf',
            'expiry_date' => now()->addDays(7),
            'status' => 'valid',
        ]);

        $notification = new DocumentExpiryNotification($document, 7);
        $data = $notification->toArray($this->user);

        $this->assertEquals('document_expiry', $data['type']);
        $this->assertEquals($document->id, $data['document_id']);
        $this->assertEquals(7, $data['days_until_expiry']);
        $this->assertEquals('aso', $data['category']);
        $this->assertArrayHasKey('expiry_date', $data);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_leave_approval_notification_has_correct_data(): void
    {
        $leave = LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'medical',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(3),
            'days_count' => 3,
            'reason' => 'Consulta médica',
            'status' => 'approved',
        ]);

        $notification = new LeaveApprovalNotification($leave, 'approved');
        $data = $notification->toArray($this->user);

        $this->assertEquals('leave_approval', $data['type']);
        $this->assertEquals($leave->id, $data['leave_id']);
        $this->assertEquals('medical', $data['leave_type']);
        $this->assertEquals('approved', $data['action']);
        $this->assertArrayHasKey('start_date', $data);
        $this->assertArrayHasKey('end_date', $data);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_vacation_expiration_notification_has_correct_data(): void
    {
        $balance = VacationBalance::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'acquisition_start' => now()->subYear(),
            'acquisition_end' => now(),
            'total_days' => 30,
            'taken_days' => 0,
            'sold_days' => 0,
            'deadline' => now()->addDays(30),
            'status' => 'active',
        ]);

        $notification = new VacationExpirationNotification($balance, 30);
        $data = $notification->toArray($this->user);

        $this->assertEquals('vacation_expiration', $data['type']);
        $this->assertEquals($balance->id, $data['vacation_balance_id']);
        $this->assertEquals(30, $data['remaining_days']);
        $this->assertEquals(30, $data['days_until_deadline']);
        $this->assertArrayHasKey('deadline', $data);
        $this->assertArrayHasKey('message', $data);
    }
}
