<?php

/**
 * Routes: SaaS Billing
 * Planos e assinaturas por tenant
 */

use App\Http\Controllers\Api\V1\Billing\SaasPlanController;
use App\Http\Controllers\Api\V1\Billing\SaasSubscriptionController;
use Illuminate\Support\Facades\Route;

// Planos SaaS (admin-only)
Route::middleware('check.permission:billing.plan.view')->group(function () {
    Route::get('billing/plans', [SaasPlanController::class, 'index']);
    Route::get('billing/plans/{id}', [SaasPlanController::class, 'show']);
});
Route::middleware('check.permission:billing.plan.manage')->group(function () {
    Route::post('billing/plans', [SaasPlanController::class, 'store']);
    Route::put('billing/plans/{id}', [SaasPlanController::class, 'update']);
    Route::delete('billing/plans/{id}', [SaasPlanController::class, 'destroy']);
});

// Assinaturas
Route::middleware('check.permission:billing.subscription.view')->group(function () {
    Route::get('billing/subscriptions', [SaasSubscriptionController::class, 'index']);
    Route::get('billing/subscriptions/{id}', [SaasSubscriptionController::class, 'show']);
});
Route::middleware('check.permission:billing.subscription.manage')->group(function () {
    Route::post('billing/subscriptions', [SaasSubscriptionController::class, 'store']);
    Route::post('billing/subscriptions/{id}/cancel', [SaasSubscriptionController::class, 'cancel']);
    Route::post('billing/subscriptions/{id}/renew', [SaasSubscriptionController::class, 'renew']);
});
