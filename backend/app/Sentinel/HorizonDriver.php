<?php

namespace App\Sentinel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sentinel\Drivers\Driver;

class HorizonDriver extends Driver
{
    public function authorize(Request $request): bool
    {
        return Gate::forUser($request->user())->allows('viewHorizon');
    }
}
