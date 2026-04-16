<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class UserHrRelationshipsTest extends TestCase
{
    public function test_user_has_payroll_lines_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->payrollLines());
    }

    public function test_user_has_rescission_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasOne::class, $user->rescission());
    }

    public function test_user_has_hour_bank_transactions_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->hourBankTransactions());
    }

    public function test_user_has_vacation_balances_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->vacationBalances());
    }

    public function test_user_has_leave_requests_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->leaveRequests());
    }

    public function test_user_has_employee_documents_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->employeeDocuments());
    }

    public function test_user_has_employee_dependents_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->employeeDependents());
    }

    public function test_user_has_employee_benefits_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->employeeBenefits());
    }

    public function test_user_has_journey_entries_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->journeyEntries());
    }

    public function test_user_has_approved_leave_requests_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->approvedLeaveRequests());
    }

    public function test_user_has_calculated_payrolls_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->calculatedPayrolls());
    }
}
