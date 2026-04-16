<?php

namespace Tests\Unit\Http\Requests\Hr;

use App\Http\Requests\HR\JourneyUserMonthRequest;
use Tests\TestCase;

class JourneyUserMonthRequestAuthorizationTest extends TestCase
{
    public function test_journey_user_month_request_accepts_view_permission(): void
    {
        $request = JourneyUserMonthRequest::create('/api/v1/hr/journey-entries', 'GET');
        $request->setUserResolver(fn () => new class
        {
            public function can(string $ability): bool
            {
                return $ability === 'hr.journey.view';
            }
        });

        $this->assertTrue($request->authorize());
    }

    public function test_journey_user_month_request_accepts_manage_permission(): void
    {
        $request = JourneyUserMonthRequest::create('/api/v1/hr/journey/calculate', 'POST');
        $request->setUserResolver(fn () => new class
        {
            public function can(string $ability): bool
            {
                return $ability === 'hr.journey.manage';
            }
        });

        $this->assertTrue($request->authorize());
    }

    public function test_journey_user_month_request_rejects_user_without_permissions(): void
    {
        $request = JourneyUserMonthRequest::create('/api/v1/hr/journey-entries', 'GET');
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
