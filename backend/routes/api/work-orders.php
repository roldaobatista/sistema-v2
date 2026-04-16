<?php

/**
 * Routes: Push, Plano de Contas, OS, Tecnicos
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 367-559
 */

use App\Http\Controllers\Api\V1\BatchExportController;
use App\Http\Controllers\Api\V1\Calibration\CalibrationDecisionController;
use App\Http\Controllers\Api\V1\ChartOfAccountController;
use App\Http\Controllers\Api\V1\Operational\ChecklistController;
use App\Http\Controllers\Api\V1\Operational\ChecklistSubmissionController;
use App\Http\Controllers\Api\V1\Operational\ExpressWorkOrderController;
use App\Http\Controllers\Api\V1\Operational\NpsController;
use App\Http\Controllers\Api\V1\Os\CertificateEmissionChecklistController;
use App\Http\Controllers\Api\V1\Os\MaintenanceReportController;
use App\Http\Controllers\Api\V1\Os\PartsKitController;
use App\Http\Controllers\Api\V1\Os\RecurringContractController;
use App\Http\Controllers\Api\V1\Os\WorkOrderActionController;
use App\Http\Controllers\Api\V1\Os\WorkOrderApprovalController;
use App\Http\Controllers\Api\V1\Os\WorkOrderAttachmentController;
use App\Http\Controllers\Api\V1\Os\WorkOrderChatController;
use App\Http\Controllers\Api\V1\Os\WorkOrderController;
use App\Http\Controllers\Api\V1\Os\WorkOrderDashboardController;
use App\Http\Controllers\Api\V1\Os\WorkOrderDisplacementController;
use App\Http\Controllers\Api\V1\Os\WorkOrderEquipmentController;
use App\Http\Controllers\Api\V1\Os\WorkOrderExecutionController;
use App\Http\Controllers\Api\V1\Os\WorkOrderImportExportController;
use App\Http\Controllers\Api\V1\Os\WorkOrderIntegrationController;
use App\Http\Controllers\Api\V1\Os\WorkOrderItemController;
use App\Http\Controllers\Api\V1\Os\WorkOrderSignatureController;
use App\Http\Controllers\Api\V1\Os\WorkOrderTimeLogController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\ServiceChecklistController;
use App\Http\Controllers\Api\V1\Technician\CustomerLocationController;
use App\Http\Controllers\Api\V1\Technician\ScheduleController;
use App\Http\Controllers\Api\V1\Technician\TechnicianRecommendationController;
use App\Http\Controllers\Api\V1\Technician\TechQuickQuoteController;
use App\Http\Controllers\Api\V1\Technician\TimeEntryController;
use App\Http\Controllers\Api\V1\UserFavoriteController;
use App\Http\Controllers\Api\V1\WorkOrderChecklistResponseController;
use Illuminate\Support\Facades\Route;

// Push Notifications
Route::middleware('check.permission:admin.settings.view')->post('push/subscribe', [PushSubscriptionController::class, 'subscribe']);
Route::middleware('check.permission:admin.settings.view')->delete('push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);
Route::middleware('check.permission:admin.settings.view')->post('push/test', [PushSubscriptionController::class, 'test']);
Route::middleware('check.permission:admin.settings.view')->get('push/vapid-key', [PushSubscriptionController::class, 'vapidKey']);

// Plano de Contas
Route::middleware('check.permission:finance.chart.view')->get('chart-of-accounts', [ChartOfAccountController::class, 'index']);
Route::middleware('check.permission:finance.chart.create')->post('chart-of-accounts', [ChartOfAccountController::class, 'store']);
Route::middleware('check.permission:finance.chart.update')->put('chart-of-accounts/{account}', [ChartOfAccountController::class, 'update']);
Route::middleware('check.permission:finance.chart.delete')->delete('chart-of-accounts/{account}', [ChartOfAccountController::class, 'destroy']);

Route::middleware(['check.permission:os.work_order.view', 'throttle:tenant-reads'])->group(function () {
    Route::get('work-orders', [WorkOrderController::class, 'index']);
    Route::get('work-orders-metadata', [WorkOrderDashboardController::class, 'metadata']);
    // whereNumber impede que caminhos literais como "work-orders/export"
    // sejam absorvidos como {work_order} (retornando 404 por model binding).
    Route::get('work-orders/{work_order}', [WorkOrderController::class, 'show'])->whereNumber('work_order');
    Route::get('technician/work-orders', [WorkOrderController::class, 'index']);

    Route::get('tech/os/{work_order}', [WorkOrderController::class, 'show'])->whereNumber('work_order');
});
Route::middleware(['check.permission:os.work_order.create', 'throttle:tenant-mutations'])->group(function () {
    Route::post('work-orders', [WorkOrderController::class, 'store']);
    Route::post('operational/work-orders/express', [ExpressWorkOrderController::class, 'store']);
    Route::post('operational/nps', [NpsController::class, 'store']);
});
Route::middleware('check.permission:os.work_order.view')->get('operational/nps/stats', [NpsController::class, 'stats']);
Route::middleware(['check.permission:os.work_order.update', 'throttle:tenant-mutations'])->group(function () {
    Route::put('work-orders/{work_order}', [WorkOrderController::class, 'update']);
    Route::post('work-orders/{work_order}/items', [WorkOrderItemController::class, 'storeItem']);
    Route::put('work-orders/{work_order}/items/{item}', [WorkOrderItemController::class, 'updateItem']);
    Route::delete('work-orders/{work_order}/items/{item}', [WorkOrderItemController::class, 'destroyItem']);
});
Route::middleware('check.permission:os.work_order.change_status')->match(['put', 'post', 'patch'], 'work-orders/{work_order}/status', [WorkOrderActionController::class, 'updateStatus']);
Route::middleware('check.permission:os.work_order.create')->post('work-orders/{work_order}/duplicate', [WorkOrderActionController::class, 'duplicate']);
Route::middleware(['check.permission:os.work_order.export', 'throttle:tenant-exports'])->group(function () {
    Route::get('work-orders-export', [WorkOrderImportExportController::class, 'exportCsv']);
    Route::get('work-orders/export', [BatchExportController::class, 'exportWorkOrders']);
});
Route::middleware('check.permission:os.work_order.create')->post('work-orders-import', [WorkOrderImportExportController::class, 'importCsv']);
Route::middleware('check.permission:os.work_order.create')->get('work-orders-import-template', [WorkOrderImportExportController::class, 'importCsvTemplate']);
Route::middleware('check.permission:os.work_order.view')->get('work-orders-dashboard-stats', [WorkOrderDashboardController::class, 'dashboardStats']);
Route::middleware('check.permission:os.work_order.change_status')->post('work-orders/{work_order}/reopen', [WorkOrderActionController::class, 'reopen']);
Route::middleware('check.permission:os.work_order.change_status')->post('work-orders/{work_order}/uninvoice', [WorkOrderActionController::class, 'uninvoice']);
// GAP-02: Dispatch authorization
Route::middleware('check.permission:os.work_order.authorize_dispatch')->post('work-orders/{work_order}/authorize-dispatch', [WorkOrderActionController::class, 'authorizeDispatch']);
Route::middleware('check.permission:os.work_order.delete')->delete('work-orders/{work_order}', [WorkOrderController::class, 'destroy']);
Route::middleware('check.permission:os.work_order.delete')->post('work-orders/{id}/restore', [WorkOrderActionController::class, 'restore']);
// Anexos/Fotos da OS
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/attachments', [WorkOrderAttachmentController::class, 'attachments']);
Route::middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('work-orders/{work_order}/displacement', [WorkOrderDisplacementController::class, 'index']);
    Route::get('work-orders/{work_order}/execution/timeline', [WorkOrderExecutionController::class, 'timeline']);
});
Route::middleware('check.permission:os.work_order.change_status')->group(function () {
    Route::post('work-orders/{work_order}/displacement/start', [WorkOrderDisplacementController::class, 'start']);
    Route::post('work-orders/{work_order}/displacement/arrive', [WorkOrderDisplacementController::class, 'arrive']);
    Route::post('work-orders/{work_order}/displacement/location', [WorkOrderDisplacementController::class, 'recordLocation']);
    Route::post('work-orders/{work_order}/displacement/stops', [WorkOrderDisplacementController::class, 'addStop']);
    Route::patch('work-orders/{work_order}/displacement/stops/{stop}', [WorkOrderDisplacementController::class, 'endStop']);

    // Execution flow (field operations)
    Route::post('work-orders/{work_order}/execution/start-displacement', [WorkOrderExecutionController::class, 'startDisplacement']);
    Route::post('work-orders/{work_order}/execution/pause-displacement', [WorkOrderExecutionController::class, 'pauseDisplacement']);
    Route::post('work-orders/{work_order}/execution/resume-displacement', [WorkOrderExecutionController::class, 'resumeDisplacement']);
    Route::post('work-orders/{work_order}/execution/arrive', [WorkOrderExecutionController::class, 'arrive']);
    Route::post('work-orders/{work_order}/execution/start-service', [WorkOrderExecutionController::class, 'startService']);
    Route::post('work-orders/{work_order}/execution/pause-service', [WorkOrderExecutionController::class, 'pauseService']);
    Route::post('work-orders/{work_order}/execution/resume-service', [WorkOrderExecutionController::class, 'resumeService']);
    Route::post('work-orders/{work_order}/execution/finalize', [WorkOrderExecutionController::class, 'finalize']);
    Route::post('work-orders/{work_order}/execution/start-return', [WorkOrderExecutionController::class, 'startReturn']);
    Route::post('work-orders/{work_order}/execution/pause-return', [WorkOrderExecutionController::class, 'pauseReturn']);
    Route::post('work-orders/{work_order}/execution/resume-return', [WorkOrderExecutionController::class, 'resumeReturn']);
    Route::post('work-orders/{work_order}/execution/arrive-return', [WorkOrderExecutionController::class, 'arriveReturn']);
    Route::post('work-orders/{work_order}/execution/close-without-return', [WorkOrderExecutionController::class, 'closeWithoutReturn']);
});
Route::middleware('check.permission:os.work_order.update')->group(function () {
    Route::post('work-orders/{work_order}/attachments', [WorkOrderAttachmentController::class, 'storeAttachment']);
    Route::delete('work-orders/{work_order}/attachments/{attachment}', [WorkOrderAttachmentController::class, 'destroyAttachment']);
    Route::post('work-orders/{work_order}/signature', [WorkOrderActionController::class, 'storeSignature']);
    Route::post('work-orders/{work_order}/photo-checklist/upload', [WorkOrderAttachmentController::class, 'uploadChecklistPhoto']);
});

// Favoritos do Usuário
Route::get('favorites', [UserFavoriteController::class, 'index']);
Route::post('favorites', [UserFavoriteController::class, 'store']);
Route::delete('favorites', [UserFavoriteController::class, 'destroy']);

// Assinaturas de OS (WorkOrderSignature)
Route::middleware('check.permission:os.work_order.view')->get('work-order-signatures', [WorkOrderSignatureController::class, 'index']);
Route::middleware('check.permission:os.work_order.update')->post('work-order-signatures', [WorkOrderSignatureController::class, 'store']);

Route::middleware('check.permission:os.work_order.update')->group(function () {
    // Equipamentos da OS (múltiplos)
    Route::post('work-orders/{work_order}/equipments', [WorkOrderEquipmentController::class, 'attachEquipment']);
    Route::delete('work-orders/{work_order}/equipments/{equipment}', [WorkOrderEquipmentController::class, 'detachEquipment']);
});

// Chat interno da OS
Route::middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('work-orders/{work_order}/chats', [WorkOrderChatController::class, 'index']);
    Route::post('work-orders/{work_order}/chats/read', [WorkOrderChatController::class, 'markAsRead']);
});
Route::middleware('check.permission:os.work_order.update')->post('work-orders/{work_order}/chats', [WorkOrderChatController::class, 'store']);

// Checklist Responses
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/checklist-responses', [WorkOrderChecklistResponseController::class, 'index']);
Route::middleware('check.permission:os.work_order.update')->post('work-orders/{work_order}/checklist-responses', [WorkOrderChecklistResponseController::class, 'store']);

// Audit Trail
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/audit-trail', [WorkOrderIntegrationController::class, 'auditTrail']);
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/audits', [WorkOrderIntegrationController::class, 'auditTrail']); // Alias de /audit-trail — mantido por compatibilidade

// Time Logs (Timer de Execução)
Route::middleware('check.permission:os.work_order.view')->get('work-order-time-logs', [WorkOrderTimeLogController::class, 'index']);
Route::middleware('check.permission:os.work_order.update')->post('work-order-time-logs/start', [WorkOrderTimeLogController::class, 'start']);
Route::middleware('check.permission:os.work_order.update')->post('work-order-time-logs/{workOrderTimeLog}/stop', [WorkOrderTimeLogController::class, 'stop']);

// Satisfação do Cliente (NPS)
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/satisfaction', [WorkOrderIntegrationController::class, 'satisfaction']);

// AprovaÃ§Ã£o interna da OS
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/approvals', [WorkOrderApprovalController::class, 'index']);
Route::middleware('check.permission:os.work_order.update')->group(function () {
    Route::post('work-orders/{work_order}/approvals/request', [WorkOrderApprovalController::class, 'request']);
    Route::post('work-orders/{work_order}/approvals/{approverId}/{action}', [WorkOrderApprovalController::class, 'respond']);
});

// Estimativa de Custo
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/cost-estimate', [WorkOrderIntegrationController::class, 'costEstimate']);

// Notas Fiscais vinculadas à OS
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/fiscal-notes', [WorkOrderIntegrationController::class, 'fiscalNotes']);

// PDF da OS
Route::middleware('check.permission:os.work_order.view')->get('work-orders/{work_order}/pdf', [WorkOrderActionController::class, 'downloadPdf']);

// Kits de Peças (Parts Kits)
Route::middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('parts-kits', [PartsKitController::class, 'index']);
    Route::get('parts-kits/{parts_kit}', [PartsKitController::class, 'show']);
});
Route::middleware('check.permission:os.work_order.create')->post('parts-kits', [PartsKitController::class, 'store']);
Route::middleware('check.permission:os.work_order.update')->group(function () {
    Route::put('parts-kits/{parts_kit}', [PartsKitController::class, 'update']);
    Route::post('work-orders/{work_order}/apply-kit/{parts_kit}', [PartsKitController::class, 'applyToWorkOrder']);
});
Route::middleware('check.permission:os.work_order.delete')->delete('parts-kits/{parts_kit}', [PartsKitController::class, 'destroy']);

// Service Checklists
Route::middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('service-checklists', [ServiceChecklistController::class, 'index']);
    Route::get('service-checklists/{service_checklist}', [ServiceChecklistController::class, 'show']);
});
Route::middleware('check.permission:os.checklist.manage')->post('service-checklists', [ServiceChecklistController::class, 'store']);
Route::middleware('check.permission:os.checklist.manage')->put('service-checklists/{service_checklist}', [ServiceChecklistController::class, 'update']);
Route::middleware('check.permission:os.checklist.manage')->delete('service-checklists/{service_checklist}', [ServiceChecklistController::class, 'destroy']);

// Contratos Recorrentes (#24)
Route::middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('recurring-contracts', [RecurringContractController::class, 'index']);
    Route::get('recurring-contracts/{recurring_contract}', [RecurringContractController::class, 'show']);
});
Route::middleware('check.permission:os.work_order.create')->group(function () {
    Route::post('recurring-contracts', [RecurringContractController::class, 'store']);
    Route::post('recurring-contracts/{recurring_contract}/generate', [RecurringContractController::class, 'generate']);
});
Route::middleware('check.permission:os.work_order.update')->put('recurring-contracts/{recurring_contract}', [RecurringContractController::class, 'update']);
Route::middleware('check.permission:os.work_order.delete')->delete('recurring-contracts/{recurring_contract}', [RecurringContractController::class, 'destroy']);

// Técnicos / Campo
Route::middleware('check.permission:technicians.schedule.view')->group(function () {
    Route::get('schedules-unified', [ScheduleController::class, 'unified']);
    Route::get('schedules/conflicts', [ScheduleController::class, 'conflicts']);
    Route::get('technician/schedules/conflicts', [ScheduleController::class, 'conflicts']);
    Route::get('schedules/workload', [ScheduleController::class, 'workloadSummary']);
    Route::get('schedules/suggest-routing', [ScheduleController::class, 'suggestRouting']);
    Route::get('schedules', [ScheduleController::class, 'index']);
    Route::get('schedules/{schedule}', [ScheduleController::class, 'show']);
    Route::get('technicians/recommendation', [TechnicianRecommendationController::class, 'recommend']);
});
Route::middleware('check.permission:technicians.schedule.manage')->group(function () {
    Route::post('schedules', [ScheduleController::class, 'store']);
    Route::put('schedules/{schedule}', [ScheduleController::class, 'update']);
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy']);
});

// Atualização de Localização do Cliente pelo Técnico
Route::middleware('check.permission:technicians.schedule.view')->group(function () {
    Route::post('technicians/customers/{customer}/geolocation', [CustomerLocationController::class, 'update']);
});

// Checklists (Pré-Visita)
Route::middleware('check.permission:technicians.checklist.view')->group(function () {
    Route::get('checklists', [ChecklistController::class, 'index']);
    Route::get('checklists/{checklist}', [ChecklistController::class, 'show']);
    Route::get('checklist-submissions', [ChecklistSubmissionController::class, 'index']);
    Route::get('checklist-submissions/{checklistSubmission}', [ChecklistSubmissionController::class, 'show']);
});
Route::middleware('check.permission:technicians.checklist.manage')->group(function () {
    Route::post('checklists', [ChecklistController::class, 'store']);
    Route::put('checklists/{checklist}', [ChecklistController::class, 'update']);
    Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy']);
});
Route::middleware('check.permission:technicians.checklist.create')->group(function () {
    Route::post('checklist-submissions', [ChecklistSubmissionController::class, 'store']);
});
Route::middleware('check.permission:technicians.time_entry.view')->group(function () {
    Route::get('time-entries', [TimeEntryController::class, 'index']);
    Route::get('time-entries-summary', [TimeEntryController::class, 'summary']);
});
Route::middleware('check.permission:technicians.time_entry.create')->group(function () {
    Route::post('time-entries', [TimeEntryController::class, 'store']);
    Route::post('time-entries/start', [TimeEntryController::class, 'start']);
    Route::post('time-entries/{time_entry}/stop', [TimeEntryController::class, 'stop']);
});
Route::middleware('check.permission:technicians.time_entry.update')->put('time-entries/{time_entry}', [TimeEntryController::class, 'update']);
Route::middleware('check.permission:technicians.time_entry.delete')->delete('time-entries/{time_entry}', [TimeEntryController::class, 'destroy']);

// Orçamentos Rápidos (Técnico)
Route::middleware('check.permission:quotes.quote.create')->post('technician/quick-quotes', [TechQuickQuoteController::class, 'store']);
// Relatórios de Manutenção (Portaria Inmetro 457/2021)
Route::middleware('check.permission:os.work_order.view')->group(function () {
    Route::get('maintenance-reports', [MaintenanceReportController::class, 'index']);
    Route::get('maintenance-reports/{maintenance_report}', [MaintenanceReportController::class, 'show']);
});
Route::middleware('check.permission:os.work_order.update')->group(function () {
    Route::post('maintenance-reports', [MaintenanceReportController::class, 'store']);
    Route::put('maintenance-reports/{maintenance_report}', [MaintenanceReportController::class, 'update']);
    Route::post('maintenance-reports/{maintenance_report}/approve', [MaintenanceReportController::class, 'approve']);
});
Route::middleware('check.permission:os.work_order.delete')->delete('maintenance-reports/{maintenance_report}', [MaintenanceReportController::class, 'destroy']);

// Checklist de Emissão de Certificado (ISO 17025)
Route::middleware('check.permission:calibration.certificate.manage')->group(function () {
    Route::get('certificate-emission-checklist/{calibrationId}', [CertificateEmissionChecklistController::class, 'show']);
    Route::post('certificate-emission-checklist', [CertificateEmissionChecklistController::class, 'storeOrUpdate']);

    // Avaliação de regra de decisão (ISO 17025 §7.8.6, ILAC G8:09/2019)
    Route::post(
        'equipment-calibrations/{calibration}/evaluate-decision',
        [CalibrationDecisionController::class, 'evaluate']
    )->name('calibrations.evaluate-decision');
});

// Otimização de Rotas
