<?php

namespace App\Services;

use App\Actions\ServiceCall\AddCommentServiceCallAction;
use App\Actions\ServiceCall\AgendaServiceCallAction;
use App\Actions\ServiceCall\AssigneesServiceCallAction;
use App\Actions\ServiceCall\AssignTechnicianServiceCallAction;
use App\Actions\ServiceCall\AuditTrailServiceCallAction;
use App\Actions\ServiceCall\BulkActionServiceCallAction;
use App\Actions\ServiceCall\CheckDuplicateServiceCallAction;
use App\Actions\ServiceCall\CommentsServiceCallAction;
use App\Actions\ServiceCall\ConvertToWorkOrderServiceCallAction;
use App\Actions\ServiceCall\DashboardKpiServiceCallAction;
use App\Actions\ServiceCall\DestroyServiceCallAction;
use App\Actions\ServiceCall\ExportCsvServiceCallAction;
use App\Actions\ServiceCall\IndexServiceCallAction;
use App\Actions\ServiceCall\MapDataServiceCallAction;
use App\Actions\ServiceCall\RescheduleServiceCallAction;
use App\Actions\ServiceCall\ShowServiceCallAction;
use App\Actions\ServiceCall\StoreServiceCallAction;
use App\Actions\ServiceCall\SummaryServiceCallAction;
use App\Actions\ServiceCall\UpdateServiceCallAction;
use App\Actions\ServiceCall\UpdateStatusServiceCallAction;
use App\Actions\ServiceCall\WebhookCreateServiceCallAction;
use App\Models\ServiceCall;
use App\Models\User;

class ServiceCallService
{
    public function index(array $data, User $user, int $tenantId, bool $isScoped = false)
    {
        return app(IndexServiceCallAction::class)->execute($data, $user, $tenantId, $isScoped);
    }

    public function store(array $data, User $user, int $tenantId)
    {
        return app(StoreServiceCallAction::class)->execute($data, $user, $tenantId);
    }

    public function show(ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(ShowServiceCallAction::class)->execute($serviceCall, $user, $tenantId);
    }

    public function update(array $data, ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(UpdateServiceCallAction::class)->execute($data, $serviceCall, $user, $tenantId);
    }

    public function destroy(ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(DestroyServiceCallAction::class)->execute($serviceCall, $user, $tenantId);
    }

    public function updateStatus(array $data, ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(UpdateStatusServiceCallAction::class)->execute($data, $serviceCall, $user, $tenantId);
    }

    public function assignTechnician(array $data, ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(AssignTechnicianServiceCallAction::class)->execute($data, $serviceCall, $user, $tenantId);
    }

    public function convertToWorkOrder(ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(ConvertToWorkOrderServiceCallAction::class)->execute($serviceCall, $user, $tenantId);
    }

    public function comments(ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(CommentsServiceCallAction::class)->execute($serviceCall, $user, $tenantId);
    }

    public function addComment(array $data, ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(AddCommentServiceCallAction::class)->execute($data, $serviceCall, $user, $tenantId);
    }

    public function exportCsv(array $data, User $user, int $tenantId)
    {
        return app(ExportCsvServiceCallAction::class)->execute($data, $user, $tenantId);
    }

    public function mapData(array $data, User $user, int $tenantId)
    {
        return app(MapDataServiceCallAction::class)->execute($data, $user, $tenantId);
    }

    public function agenda(array $data, User $user, int $tenantId)
    {
        return app(AgendaServiceCallAction::class)->execute($data, $user, $tenantId);
    }

    public function auditTrail(ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(AuditTrailServiceCallAction::class)->execute($serviceCall, $user, $tenantId);
    }

    public function summary(User $user, int $tenantId)
    {
        return app(SummaryServiceCallAction::class)->execute($user, $tenantId);
    }

    public function dashboardKpi(array $data, User $user, int $tenantId)
    {
        return app(DashboardKpiServiceCallAction::class)->execute($data, $user, $tenantId);
    }

    public function bulkAction(array $data, User $user, int $tenantId)
    {
        return app(BulkActionServiceCallAction::class)->execute($data, $user, $tenantId);
    }

    public function reschedule(array $data, ServiceCall $serviceCall, User $user, int $tenantId)
    {
        return app(RescheduleServiceCallAction::class)->execute($data, $serviceCall, $user, $tenantId);
    }

    public function checkDuplicate(array $data, User $user, int $tenantId)
    {
        return app(CheckDuplicateServiceCallAction::class)->execute($data, $user, $tenantId);
    }

    public function webhookCreate(array $data, User $user, int $tenantId)
    {
        return app(WebhookCreateServiceCallAction::class)->execute($data, $user, $tenantId);
    }

    public function assignees(User $user, int $tenantId)
    {
        return app(AssigneesServiceCallAction::class)->execute($user, $tenantId);
    }
}
