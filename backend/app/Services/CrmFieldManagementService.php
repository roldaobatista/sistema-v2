<?php

namespace App\Services;

use App\Actions\CrmFieldManagement\AccountPlanActionsUpdateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\AccountPlansIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\AccountPlansStoreCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\AccountPlansUpdateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\CheckinCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\CheckinsIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\CheckoutCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\ClientSummaryCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\CommercialProductivityCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\CommitmentsIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\CommitmentsStoreCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\CommitmentsUpdateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\ForgottenClientsCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\GamificationDashboardCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\GamificationRecalculateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\ImportantDatesDestroyCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\ImportantDatesIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\ImportantDatesStoreCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\ImportantDatesUpdateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\LatentOpportunitiesCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\NegotiationHistoryCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\PoliciesDestroyCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\PoliciesIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\PoliciesStoreCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\PoliciesUpdateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\PortfolioCoverageCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\PortfolioMapCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\QuickNotesDestroyCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\QuickNotesIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\QuickNotesStoreCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\QuickNotesUpdateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\ReportsIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\ReportsStoreCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\RfmIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\RfmRecalculateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\RoutesIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\RoutesStoreCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\RoutesUpdateCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\SmartAgendaCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\SurveysAnswerCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\SurveysIndexCrmFieldManagementAction;
use App\Actions\CrmFieldManagement\SurveysSendCrmFieldManagementAction;
use App\Models\AccountPlan;
use App\Models\AccountPlanAction;
use App\Models\Commitment;
use App\Models\ContactPolicy;
use App\Models\Customer;
use App\Models\ImportantDate;
use App\Models\QuickNote;
use App\Models\User;
use App\Models\VisitCheckin;
use App\Models\VisitRoute;

class CrmFieldManagementService
{
    public function checkinsIndex(array $data, User $user, int $tenantId)
    {
        return app(CheckinsIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function checkin(array $data, User $user, int $tenantId)
    {
        return app(CheckinCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function checkout(array $data, VisitCheckin $checkin, User $user, int $tenantId)
    {
        return app(CheckoutCrmFieldManagementAction::class)->execute($data, $checkin, $user, $tenantId);
    }

    public function routesIndex(array $data, User $user, int $tenantId)
    {
        return app(RoutesIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function routesStore(array $data, User $user, int $tenantId)
    {
        return app(RoutesStoreCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function routesUpdate(array $data, VisitRoute $route, User $user, int $tenantId)
    {
        return app(RoutesUpdateCrmFieldManagementAction::class)->execute($data, $route, $user, $tenantId);
    }

    public function reportsIndex(array $data, User $user, int $tenantId)
    {
        return app(ReportsIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function reportsStore(array $data, User $user, int $tenantId)
    {
        return app(ReportsStoreCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function portfolioMap(array $data, User $user, int $tenantId)
    {
        return app(PortfolioMapCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function forgottenClients(array $data, User $user, int $tenantId)
    {
        return app(ForgottenClientsCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function policiesIndex(array $data, User $user, int $tenantId)
    {
        return app(PoliciesIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function policiesStore(array $data, User $user, int $tenantId)
    {
        return app(PoliciesStoreCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function policiesUpdate(array $data, ContactPolicy $policy, User $user, int $tenantId)
    {
        return app(PoliciesUpdateCrmFieldManagementAction::class)->execute($data, $policy, $user, $tenantId);
    }

    public function policiesDestroy(array $data, ContactPolicy $policy, User $user, int $tenantId)
    {
        return app(PoliciesDestroyCrmFieldManagementAction::class)->execute($data, $policy, $user, $tenantId);
    }

    public function smartAgenda(array $data, User $user, int $tenantId)
    {
        return app(SmartAgendaCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function quickNotesIndex(array $data, User $user, int $tenantId)
    {
        return app(QuickNotesIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function quickNotesStore(array $data, User $user, int $tenantId)
    {
        return app(QuickNotesStoreCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function quickNotesUpdate(array $data, QuickNote $note, User $user, int $tenantId)
    {
        return app(QuickNotesUpdateCrmFieldManagementAction::class)->execute($data, $note, $user, $tenantId);
    }

    public function quickNotesDestroy(array $data, QuickNote $note, User $user, int $tenantId)
    {
        return app(QuickNotesDestroyCrmFieldManagementAction::class)->execute($data, $note, $user, $tenantId);
    }

    public function commitmentsIndex(array $data, User $user, int $tenantId)
    {
        return app(CommitmentsIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function commitmentsStore(array $data, User $user, int $tenantId)
    {
        return app(CommitmentsStoreCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function commitmentsUpdate(array $data, Commitment $commitment, User $user, int $tenantId)
    {
        return app(CommitmentsUpdateCrmFieldManagementAction::class)->execute($data, $commitment, $user, $tenantId);
    }

    public function negotiationHistory(array $data, Customer $customer, User $user, int $tenantId)
    {
        return app(NegotiationHistoryCrmFieldManagementAction::class)->execute($data, $customer, $user, $tenantId);
    }

    public function clientSummary(array $data, Customer $customer, User $user, int $tenantId)
    {
        return app(ClientSummaryCrmFieldManagementAction::class)->execute($data, $customer, $user, $tenantId);
    }

    public function rfmIndex(array $data, User $user, int $tenantId)
    {
        return app(RfmIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function rfmRecalculate(array $data, User $user, int $tenantId)
    {
        return app(RfmRecalculateCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function portfolioCoverage(array $data, User $user, int $tenantId)
    {
        return app(PortfolioCoverageCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function commercialProductivity(array $data, User $user, int $tenantId)
    {
        return app(CommercialProductivityCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function latentOpportunities(array $data, User $user, int $tenantId)
    {
        return app(LatentOpportunitiesCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function importantDatesIndex(array $data, User $user, int $tenantId)
    {
        return app(ImportantDatesIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function importantDatesStore(array $data, User $user, int $tenantId)
    {
        return app(ImportantDatesStoreCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function importantDatesUpdate(array $data, ImportantDate $date, User $user, int $tenantId)
    {
        return app(ImportantDatesUpdateCrmFieldManagementAction::class)->execute($data, $date, $user, $tenantId);
    }

    public function importantDatesDestroy(array $data, ImportantDate $date, User $user, int $tenantId)
    {
        return app(ImportantDatesDestroyCrmFieldManagementAction::class)->execute($data, $date, $user, $tenantId);
    }

    public function surveysIndex(array $data, User $user, int $tenantId)
    {
        return app(SurveysIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function surveysSend(array $data, User $user, int $tenantId)
    {
        return app(SurveysSendCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function surveysAnswer(array $data, string $token, User $user, int $tenantId)
    {
        return app(SurveysAnswerCrmFieldManagementAction::class)->execute($data, $token, $user, $tenantId);
    }

    public function accountPlansIndex(array $data, User $user, int $tenantId)
    {
        return app(AccountPlansIndexCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function accountPlansStore(array $data, User $user, int $tenantId)
    {
        return app(AccountPlansStoreCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function accountPlansUpdate(array $data, AccountPlan $plan, User $user, int $tenantId)
    {
        return app(AccountPlansUpdateCrmFieldManagementAction::class)->execute($data, $plan, $user, $tenantId);
    }

    public function accountPlanActionsUpdate(array $data, AccountPlanAction $action, User $user, int $tenantId)
    {
        return app(AccountPlanActionsUpdateCrmFieldManagementAction::class)->execute($data, $action, $user, $tenantId);
    }

    public function gamificationDashboard(array $data, User $user, int $tenantId)
    {
        return app(GamificationDashboardCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }

    public function gamificationRecalculate(array $data, User $user, int $tenantId)
    {
        return app(GamificationRecalculateCrmFieldManagementAction::class)->execute($data, $user, $tenantId);
    }
}
