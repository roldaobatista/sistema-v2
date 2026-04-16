<?php

namespace Tests\Unit\Http\Requests\Hr;

use App\Http\Requests\HR\RejectLeaveRequest;
use App\Http\Requests\HR\StoreLeaveRequest;
use Tests\TestCase;

class LeaveRequestAuthorizationTest extends TestCase
{
    public function test_store_leave_request_requires_create_permission(): void
    {
        $request = StoreLeaveRequest::create('/api/v1/hr/leaves', 'POST');
        $request->setUserResolver(fn () => new class
        {
            public function can(string $ability): bool
            {
                return $ability === 'hr.leave.create';
            }
        });

        $this->assertTrue($request->authorize());

        $request->setUserResolver(fn () => new class
        {
            public function can(string $ability): bool
            {
                return false;
            }
        });

        $this->assertFalse($request->authorize());
    }

    public function test_reject_leave_request_requires_approve_permission(): void
    {
        $request = RejectLeaveRequest::create('/api/v1/hr/leaves/1/reject', 'POST');
        $request->setUserResolver(fn () => new class
        {
            public function can(string $ability): bool
            {
                return $ability === 'hr.leave.approve';
            }
        });

        $this->assertTrue($request->authorize());

        $request->setUserResolver(fn () => new class
        {
            public function can(string $ability): bool
            {
                return false;
            }
        });

        $this->assertFalse($request->authorize());
    }
}
