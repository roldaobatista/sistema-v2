<?php

/**
 * Routes: Melhorias Sistemicas, Operacoes, Central, APIs Externas, Tech Sync
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 1660-1862
 */

use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\CashFlowController;
use App\Http\Controllers\Api\V1\ExternalApiController;
use App\Http\Controllers\Api\V1\IntegrationHealthController;
use App\Http\Controllers\Api\V1\SlaDashboardController;
use App\Http\Controllers\Api\V1\SlaPolicyController;
use App\Http\Controllers\Api\V1\SystemImprovementsController;
use App\Http\Controllers\Api\V1\TechSyncController;
use Illuminate\Support\Facades\Route;

// ─── Melhorias Sistêmicas ────────────────────────
$si = SystemImprovementsController::class;
Route::prefix('system')->group(function () use ($si) {
    // Global Search
    Route::middleware('check.permission:platform.dashboard.view')->get('search', [$si, 'globalSearch']);

    // Skill Matrix
    Route::middleware('check.permission:rh.manage')->group(function () use ($si) {
        Route::get('technician-skills', [$si, 'technicianSkills']);
        Route::get('skill-matrix', [$si, 'skillMatrix']);
        Route::post('technician-skills', [$si, 'storeTechnicianSkill']);
        Route::put('technician-skills/{skill}', [$si, 'updateTechnicianSkill']);
        Route::delete('technician-skills/{skill}', [$si, 'destroyTechnicianSkill']);
        Route::post('recommend-technician', [$si, 'recommendTechnician']);
    });

    // Collection Rules
    Route::middleware('check.permission:financeiro.accounts_receivable.view')->group(function () use ($si) {
        Route::get('collection-rules', [$si, 'collectionRules']);
        Route::get('aging-report', [$si, 'agingReport']);
    });
    Route::middleware('check.permission:admin.settings.manage')->group(function () use ($si) {
        Route::post('collection-rules', [$si, 'storeCollectionRule']);
        Route::put('collection-rules/{rule}', [$si, 'updateCollectionRule']);
        Route::delete('collection-rules/{rule}', [$si, 'destroyCollectionRule']);
    });

    // Stock Demand
    Route::middleware('check.permission:estoque.view')->get('stock-demand', [$si, 'stockDemandForecast']);

    // Integration Health Monitor (Phase 6)
    Route::prefix('integrations/health')->middleware('check.permission:admin.settings.manage')->group(function () {
        Route::get('/', [IntegrationHealthController::class, 'index']);
        Route::get('/{service}', [IntegrationHealthController::class, 'show']);
        Route::post('/{service}/reset', [IntegrationHealthController::class, 'reset']);
    });

    // Quality / CAPA
    Route::middleware('check.permission:quality.procedure.view')->group(function () use ($si) {
        Route::get('capa', [$si, 'capaRecords']);
        Route::get('quality-dashboard', [$si, 'qualityDashboard']);
    });
    Route::middleware('check.permission:quality.procedure.create')->post('capa', [$si, 'storeCapaRecord']);
    Route::middleware('check.permission:quality.procedure.update')->put('capa/{record}', [$si, 'updateCapaRecord']);

    // WO Cost Estimate
    Route::middleware('check.permission:os.work_order.view')->get('work-orders/{workOrder}/cost-estimate', [$si, 'workOrderCostEstimate']);
});

// ─── Perfil do Usuário ───
// FIX-B5: Usar o controller de perfil (Api\V1\UserController), não o IAM
// Rota profile/change-password removida (duplicata da L1386)

// (Rotas reports/suppliers e reports/stock já registradas na seção de Relatórios â€” L474-475)

// ─── Operações (Fase 5) ────────────────────────────────────
use App\Http\Controllers\Api\V1\Operational\OperationalDashboardController;

Route::prefix('operational-dashboard')->middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('stats', [OperationalDashboardController::class, 'index']);
    Route::get('active-displacements', [OperationalDashboardController::class, 'activeDisplacements']);
});

// NOTA: Rotas de service-checklists, parts-kits, WO chats, audit-trail,
// checklist-responses e satisfaction foram removidas daqui pois já existem
// em work-orders.php (fonte canônica) com permissões corretas.

// SLA Policies (CRUD só existe aqui)
Route::middleware('check.permission:os.work_order.view')->group(function () {
    Route::apiResource('sla-policies', SlaPolicyController::class)->only(['index', 'show']);
});
Route::middleware('check.permission:os.work_order.create')->group(function () {
    Route::apiResource('sla-policies', SlaPolicyController::class)->only(['store', 'update', 'destroy']);
});

// Agendamento Técnico movido para work-orders.php (rotas devem ficar antes de schedules/{schedule})

// ?"?"?" Fusão de Clientes (Fase 6.2) ?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"
// ?"?"?" SLA Dashboard (Brainstorm #13) ?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"?"
Route::prefix('sla-dashboard')->middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('overview', [SlaDashboardController::class, 'overview']);
    Route::get('breached', [SlaDashboardController::class, 'breachedOrders']);
    Route::get('by-policy', [SlaDashboardController::class, 'byPolicy']);
    Route::get('by-technician', [SlaDashboardController::class, 'byTechnician']);
    Route::get('trends', [SlaDashboardController::class, 'trends']);
});

// â?,â?,â?, DRE Comparativo (Brainstorm #7) â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,
Route::middleware('check.permission:finance.dre.view')->get('cash-flow/dre-comparativo', [CashFlowController::class, 'dreComparativo']);

// â?,â?,â?, Agenda (Inbox de Trabalho) â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,
Route::prefix('agenda')->group(function () {
    Route::middleware('check.permission:agenda.item.view')->group(function () {
        Route::get('summary', [AgendaController::class, 'summary']);
        Route::get('constants', [AgendaController::class, 'constants']);
        Route::get('items', [AgendaController::class, 'index']);
        Route::get('items/{agendaItem}', [AgendaController::class, 'show']);
        Route::post('items/{agendaItem}/comments', [AgendaController::class, 'comment']);

        // Subtasks
        Route::get('items/{agendaItem}/subtasks', [AgendaController::class, 'subtasks']);
        Route::post('items/{agendaItem}/subtasks', [AgendaController::class, 'storeSubtask']);
        Route::patch('items/{agendaItem}/subtasks/{subtask}', [AgendaController::class, 'updateSubtask']);
        Route::delete('items/{agendaItem}/subtasks/{subtask}', [AgendaController::class, 'destroySubtask']);

        // Attachments
        Route::get('items/{agendaItem}/attachments', [AgendaController::class, 'attachments']);
        Route::post('items/{agendaItem}/attachments', [AgendaController::class, 'storeAttachment']);
        Route::delete('items/{agendaItem}/attachments/{attachment}', [AgendaController::class, 'destroyAttachment']);

        // Timer / Time Entries
        Route::get('items/{agendaItem}/time-entries', [AgendaController::class, 'timeEntries']);
        Route::post('items/{agendaItem}/timer/start', [AgendaController::class, 'startTimer']);
        Route::post('items/{agendaItem}/timer/stop', [AgendaController::class, 'stopTimer']);

        // Dependencies
        Route::post('items/{agendaItem}/dependencies', [AgendaController::class, 'addDependency']);
        Route::delete('items/{agendaItem}/dependencies/{dependsOnId}', [AgendaController::class, 'removeDependency']);

        // Watchers / Seguidores
        Route::get('items/{agendaItem}/watchers', [AgendaController::class, 'listWatchers']);
        Route::post('items/{agendaItem}/watchers', [AgendaController::class, 'addWatchers']);
        Route::patch('items/{agendaItem}/watchers/{watcher}', [AgendaController::class, 'updateWatcher']);
        Route::delete('items/{agendaItem}/watchers/{watcher}', [AgendaController::class, 'destroyWatcher']);
        Route::post('items/{agendaItem}/toggle-follow', [AgendaController::class, 'toggleFollow']);

        // Notification Preferences
        Route::get('notification-prefs', [AgendaController::class, 'getNotificationPrefs']);
        Route::patch('notification-prefs', [AgendaController::class, 'updateNotificationPrefs']);

        // iCal Feed
        Route::get('ical-feed', [AgendaController::class, 'icalFeed']);
    });

    Route::middleware('check.permission:agenda.create.task')->post('items', [AgendaController::class, 'store']);
    Route::middleware('check.permission:agenda.close.self')->patch('items/{agendaItem}', [AgendaController::class, 'update']);
    Route::middleware('check.permission:agenda.close.self')->delete('items/{agendaItem}', [AgendaController::class, 'destroy']);
    Route::middleware('check.permission:agenda.assign')->post('items/{agendaItem}/assign', [AgendaController::class, 'assign']);
    Route::middleware('check.permission:agenda.close.self')->post('items/bulk-update', [AgendaController::class, 'bulkUpdate']);
    Route::middleware('check.permission:agenda.item.view')->get('items-export', [AgendaController::class, 'export']);

    // Dashboard gerencial
    Route::middleware('check.permission:agenda.manage.kpis')->group(function () {
        Route::get('kpis', [AgendaController::class, 'kpis']);
        Route::get('workload', [AgendaController::class, 'workload']);
        Route::get('overdue-by-team', [AgendaController::class, 'overdueByTeam']);
    });

    // Templates
    Route::middleware('check.permission:agenda.item.view')->group(function () {
        Route::get('templates', [AgendaController::class, 'listTemplates']);
        Route::post('templates/{agendaTemplate}/use', [AgendaController::class, 'useTemplate']);
    });
    Route::middleware('check.permission:agenda.manage.rules')->group(function () {
        Route::post('templates', [AgendaController::class, 'storeTemplate']);
        Route::patch('templates/{agendaTemplate}', [AgendaController::class, 'updateTemplate']);
        Route::delete('templates/{agendaTemplate}', [AgendaController::class, 'destroyTemplate']);
    });

    // Regras de automacao (Fase 3)
    Route::middleware('check.permission:agenda.manage.rules')->group(function () {
        Route::get('rules', [AgendaController::class, 'rules']);
        Route::post('rules', [AgendaController::class, 'storeRule']);
        Route::patch('rules/{agendaRule}', [AgendaController::class, 'updateRule']);
        Route::delete('rules/{agendaRule}', [AgendaController::class, 'destroyRule']);
    });
});

// â”€â”€â”€ APIs Externas (CEP, CNPJ, IBGE, Feriados) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::prefix('external')->middleware('throttle:tenant-reads')->group(function () {
    Route::get('cep/{cep}', [ExternalApiController::class, 'cep']);
    Route::get('cnpj/{cnpj}', [ExternalApiController::class, 'cnpj']);
    Route::get('document/{document}', [ExternalApiController::class, 'document']);
    Route::get('holidays/{year}', [ExternalApiController::class, 'holidays']);
    Route::get('banks', [ExternalApiController::class, 'banks']);
    Route::get('ddd/{ddd}', [ExternalApiController::class, 'ddd']);
    Route::get('states', [ExternalApiController::class, 'states']);
    Route::get('states/{uf}/cities', [ExternalApiController::class, 'cities']);
});

// â”€â”€â”€ Tech Sync (PWA Mobile Offline) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::prefix('tech')->group(function () {
    Route::middleware('check.permission:os.work_order.view')->get('sync', [TechSyncController::class, 'pull']);
    Route::middleware('check.permission:os.work_order.update')->post('sync/batch', [TechSyncController::class, 'batchPush']);
    Route::middleware('check.permission:os.work_order.update')->post('sync/photo', [TechSyncController::class, 'uploadPhoto']);
});
