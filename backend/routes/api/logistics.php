<?php

use App\Http\Controllers\Api\V1\Logistics\DispatchController;
use App\Http\Controllers\Api\V1\Logistics\RouteOptimizationController;
use App\Http\Controllers\Api\V1\Logistics\RoutePlanController;
use App\Http\Controllers\Api\V1\Logistics\RoutingController;
use App\Http\Controllers\Api\V1\Operational\RouteOptimizationController as LegacyRouteOptimizationController;
use App\Http\Controllers\Api\V1\RoutingController as LegacyRoutingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'check.tenant'])->group(function () {

    Route::prefix('logistics')->group(function () {
        Route::prefix('dispatch')->group(function () {
            Route::middleware('check.permission:os.work_order.view')->get('rules', [DispatchController::class, 'autoAssignRules']);
            Route::middleware('check.permission:os.work_order.create')->post('rules', [DispatchController::class, 'storeAutoAssignRule']);
            Route::middleware('check.permission:os.work_order.update')->put('rules/{rule}', [DispatchController::class, 'updateAutoAssignRule']);
            Route::middleware('check.permission:os.work_order.delete')->delete('rules/{rule}', [DispatchController::class, 'deleteAutoAssignRule']);
            Route::middleware('check.permission:os.work_order.update')->post('auto-assign/{workOrder}', [DispatchController::class, 'triggerAutoAssign']);
        });

        Route::prefix('routes')->group(function () {
            Route::middleware('check.permission:os.work_order.view')->post('optimize', [RouteOptimizationController::class, 'optimize']);
        });

        Route::prefix('routing')->group(function () {
            Route::middleware('check.permission:os.work_order.view')->get('daily-plan', [RoutingController::class, 'dailyPlan']);
        });

        Route::middleware('check.permission:route.plan.view')->get('route-plans', [RoutePlanController::class, 'indexRoutePlans']);
        Route::middleware('check.permission:route.plan.manage')->post('route-plans', [RoutePlanController::class, 'storeRoutePlan']);
    });

    // Aliases legados preservados durante a promocao para bounded context proprio
    Route::middleware('check.permission:os.work_order.view')->post('operational/route-optimization', [LegacyRouteOptimizationController::class, 'optimize']);
    Route::middleware('check.permission:os.work_order.view')->get('routing/daily-plan', [LegacyRoutingController::class, 'dailyPlan']);
    Route::middleware('check.permission:route.plan.view')->get('advanced/route-plans', [RoutePlanController::class, 'indexRoutePlans']);
    Route::middleware('check.permission:route.plan.manage')->post('advanced/route-plans', [RoutePlanController::class, 'storeRoutePlan']);

});
