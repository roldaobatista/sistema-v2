<?php

namespace Tests\Unit\Http\Requests\Hr;

use App\Http\Requests\HR\ConfirmEspelhoRequest;
use Tests\TestCase;

class ConfirmEspelhoRequestAuthorizationTest extends TestCase
{
    public function test_confirm_espelho_request_accepts_clock_view_permission(): void
    {
        $request = ConfirmEspelhoRequest::create('/api/v1/hr/clock/espelho/confirm', 'POST');
        $request->setUserResolver(fn () => new class
        {
            public function can(string $ability): bool
            {
                return $ability === 'hr.clock.view';
            }
        });

        $this->assertTrue($request->authorize());
    }

    public function test_confirm_espelho_request_accepts_clock_manage_permission(): void
    {
        $request = ConfirmEspelhoRequest::create('/api/v1/hr/clock/espelho/confirm', 'POST');
        $request->setUserResolver(fn () => new class
        {
            public function can(string $ability): bool
            {
                return $ability === 'hr.clock.manage';
            }
        });

        $this->assertTrue($request->authorize());
    }

    public function test_confirm_espelho_request_rejects_user_without_clock_permission(): void
    {
        $request = ConfirmEspelhoRequest::create('/api/v1/hr/clock/espelho/confirm', 'POST');
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
