<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ObservabilityDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('check.permission:platform.settings.view')
    ->get('observability/dashboard', ObservabilityDashboardController::class);
