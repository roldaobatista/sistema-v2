<?php

/**
 * Rotas: Auth (me, logout, tenants), Dashboard, TV, Cash-flow, IAM (users, roles, permissions), Audit Logs.
 * Carregado de dentro do grupo auth:sanctum + check.tenant em routes/api.php.
 */

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CashFlowController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\Iam\PermissionController;
use App\Http\Controllers\Api\V1\Iam\RoleController;
use App\Http\Controllers\Api\V1\Iam\UserController;
use App\Http\Controllers\Api\V1\Iam\UserLocationController;
use App\Http\Controllers\Api\V1\Os\WorkOrderDashboardController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\TvDashboardConfigController;
use App\Http\Controllers\TvDashboardController;
use App\Http\Controllers\UserCompetencyController;
use Illuminate\Support\Facades\Route;

Route::get('/me', [AuthController::class, 'me']);
Route::get('/auth/user', [AuthController::class, 'me']); // compat: cliente legado
// INFO: POST /user/location é intencionalmente sem 'check.permission' pois o próprio usuário logado atualiza sua localização.
Route::post('/user/location', [UserLocationController::class, 'update']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/auth/logout', [AuthController::class, 'logout']); // compat: cliente legado
Route::get('/my-tenants', [AuthController::class, 'myTenants']);
Route::get('/my_tenants', [AuthController::class, 'myTenants']); // compat: cliente antigo ou cache
Route::post('/switch-tenant', [AuthController::class, 'switchTenant']);
Route::middleware('check.permission:platform.dashboard.view')->get('/dashboard-stats', [DashboardController::class, 'stats']);
Route::middleware('check.permission:platform.dashboard.view')->get('/dashboard/team-status', [DashboardController::class, 'teamStatus']);
Route::middleware('check.permission:os.work_order.view')->get('/dashboard/work-orders', [WorkOrderDashboardController::class, 'dashboardStats']);
Route::middleware('check.permission:platform.dashboard.view')->get('/dashboard/activities', [DashboardController::class, 'activities']);
Route::middleware('check.permission:tv.dashboard.view')->group(function () {
    Route::get('/tv/dashboard', [TvDashboardController::class, 'index']);
    Route::get('/tv/kpis', [TvDashboardController::class, 'kpis']);
    Route::get('/tv/kpis/trend', [TvDashboardController::class, 'kpisTrend']);
    Route::get('/tv/map-data', [TvDashboardController::class, 'mapData']);
    Route::get('/tv/alerts', [TvDashboardController::class, 'alerts']);
    Route::get('/tv/alerts/history', [TvDashboardController::class, 'alertsHistory']);
    Route::get('/tv/productivity', [TvDashboardController::class, 'productivity']);

    // Configurações da TV
    Route::get('/tv-dashboard-configs/current', [TvDashboardConfigController::class, 'current']);
    Route::apiResource('tv-dashboard-configs', TvDashboardConfigController::class);
});
Route::middleware('check.permission:tv.camera.manage')->group(function () {
    Route::get('/tv/cameras', [CameraController::class, 'index']);
    Route::post('/tv/cameras', [CameraController::class, 'store']);
    Route::put('/tv/cameras/{camera}', [CameraController::class, 'update']);
    Route::delete('/tv/cameras/{camera}', [CameraController::class, 'destroy']);
    Route::post('/tv/cameras/reorder', [CameraController::class, 'reorder']);
    Route::post('/tv/cameras/test-connection', [CameraController::class, 'testConnection']);
});
Route::middleware('check.permission:tv.dashboard.view')->get('/tv/cameras/health', [CameraController::class, 'health']);
Route::middleware('check.permission:finance.cashflow.view')->get('/cash-flow', [CashFlowController::class, 'cashFlow']);
Route::middleware('check.permission:finance.dre.view')->get('/dre', [CashFlowController::class, 'dre']);
Route::middleware('check.permission:finance.dre.view')->get('/reports/dre', [CashFlowController::class, 'dre']);
Route::middleware('check.permission:finance.cashflow.view')->get('/reports/cash-flow', [CashFlowController::class, 'cashFlow']);
Route::middleware('check.permission:iam.user.view')->get('users/stats', [UserController::class, 'stats']);
Route::middleware('check.permission:iam.user.view')->get('users/by-role/{role}', [UserController::class, 'byRole']);
Route::middleware('check.permission:iam.user.export')->get('users/export', [UserController::class, 'exportCsv']);
Route::middleware('check.permission:os.work_order.view|technicians.schedule.view|technicians.time_entry.view|technicians.cashbox.view|financial.fund_transfer.create')->get('technicians/options', [UserController::class, 'techniciansOptions']);
Route::middleware('check.permission:iam.user.view')->group(function () {
    Route::apiResource('users', UserController::class)->only(['index', 'show']);
});
Route::middleware('check.permission:iam.user.create')->post('users', [UserController::class, 'store']);
Route::apiResource('user-competencies', UserCompetencyController::class);
Route::middleware('check.permission:iam.user.update')->post('users/bulk-toggle-active', [UserController::class, 'bulkToggleActive']);
Route::middleware('check.permission:iam.user.update')->group(function () {
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive']);
    Route::post('users/{user}/roles', [UserController::class, 'assignRoles']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('users/{user}/force-logout', [UserController::class, 'forceLogout']);
    Route::get('users/{user}/sessions', [UserController::class, 'sessions']);
    Route::delete('users/{user}/sessions/{token}', [UserController::class, 'revokeSession']);
});
Route::middleware('check.permission:iam.audit_log.view')->get('users/{user}/audit-trail', [UserController::class, 'auditTrail']);
Route::middleware('check.permission:iam.permission.manage')->group(function () {
    Route::get('users/{user}/permissions', [UserController::class, 'directPermissions']);
    Route::post('users/{user}/permissions', [UserController::class, 'grantPermissions']);
    Route::put('users/{user}/permissions', [UserController::class, 'syncDirectPermissions']);
    Route::delete('users/{user}/permissions', [UserController::class, 'revokePermissions']);
    Route::get('users/{user}/denied-permissions', [UserController::class, 'deniedPermissions']);
    Route::put('users/{user}/denied-permissions', [UserController::class, 'syncDeniedPermissions']);
});
Route::middleware('check.permission:iam.user.delete')->delete('users/{user}', [UserController::class, 'destroy']);
Route::middleware('check.permission:iam.role.view')->group(function () {
    Route::apiResource('roles', RoleController::class)->only(['index', 'show']);
    Route::get('roles/{role}/users', [RoleController::class, 'users']);
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('permissions/matrix', [PermissionController::class, 'matrix']);
});
Route::middleware('check.permission:iam.role.create')->group(function () {
    Route::post('roles', [RoleController::class, 'store']);
    Route::post('roles/{role}/clone', [RoleController::class, 'clone']);
});
Route::middleware('check.permission:iam.role.update')->put('roles/{role}', [RoleController::class, 'update']);
Route::middleware('check.permission:iam.permission.manage')->post('permissions/toggle', [PermissionController::class, 'toggleRolePermission']);
Route::middleware('check.permission:iam.role.delete')->delete('roles/{role}', [RoleController::class, 'destroy']);
Route::middleware('check.permission:iam.audit_log.view')->group(function () {
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/actions', [AuditLogController::class, 'actions']);
    Route::get('audit-logs/entity-types', [AuditLogController::class, 'entityTypes']);
    Route::get('audit-logs/{id}', [AuditLogController::class, 'show']);
});
Route::middleware('check.permission:iam.audit_log.export')->post('audit-logs/export', [AuditLogController::class, 'export']);
