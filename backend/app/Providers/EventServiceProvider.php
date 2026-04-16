<?php

namespace App\Providers;

use App\Events\CalibrationCompleted;
use App\Events\CalibrationExpiring;
use App\Events\ClockAdjustmentDecided;
use App\Events\ClockAdjustmentRequested;
use App\Events\ClockEntryFlagged;
use App\Events\ClockEntryRegistered;
use App\Events\CltViolationDetected;
use App\Events\CommissionGenerated;
use App\Events\ContractRenewing;
use App\Events\CustomerCreated;
use App\Events\DocumentExpiring;
use App\Events\FiscalNoteAuthorized;
use App\Events\HrActionAudited;
use App\Events\InvoiceCreated;
use App\Events\LeaveDecided;
use App\Events\LeaveRequested;
use App\Events\PaymentMade;
use App\Events\PaymentReceived;
use App\Events\QuoteApproved;
use App\Events\RepairSeal\SealAssignedToTechnician;
use App\Events\RepairSeal\SealBatchReceived;
use App\Events\RepairSeal\SealPseiSubmitted;
use App\Events\RepairSeal\SealUsedOnWorkOrder;
use App\Events\ServiceCallCreated;
use App\Events\VacationDeadlineApproaching;
use App\Events\WorkOrderCancelled;
use App\Events\WorkOrderCompleted;
use App\Events\WorkOrderInvoiced;
use App\Events\WorkOrderStarted;
use App\Events\WorkOrderStatusChanged;
use App\Listeners\AuditHrActionListener;
use App\Listeners\AutoEmitNFeOnInvoice;
use App\Listeners\CreateAgendaItemOnCalibration;
use App\Listeners\CreateAgendaItemOnContract;
use App\Listeners\CreateAgendaItemOnPayment;
use App\Listeners\CreateAgendaItemOnQuote;
use App\Listeners\CreateAgendaItemOnServiceCall;
use App\Listeners\CreateAgendaItemOnWorkOrder;
use App\Listeners\CreateWarrantyTrackingOnWorkOrderInvoiced;
use App\Listeners\GenerateCorrectiveQuoteOnCalibrationFailure;
use App\Listeners\HandleCalibrationExpiring;
use App\Listeners\HandleContractRenewing;
use App\Listeners\HandleCustomerCreated;
use App\Listeners\HandlePaymentMade;
use App\Listeners\HandlePaymentReceived;
use App\Listeners\HandleQuoteApproval;
use App\Listeners\HandleServiceCallCreated;
use App\Listeners\HandleWorkOrderCancellation;
use App\Listeners\HandleWorkOrderCompletion;
use App\Listeners\HandleWorkOrderInvoicing;
use App\Listeners\HandleWorkOrderStatusChanged;
use App\Listeners\LogWorkOrderStartActivity;
use App\Listeners\NotifyBeneficiaryOnCommission;
use App\Listeners\NotifyEmployeeOnAdjustmentDecision;
use App\Listeners\NotifyEmployeeOnLeaveDecision;
use App\Listeners\NotifyManagerOfViolation;
use App\Listeners\NotifyManagerOnAdjustment;
use App\Listeners\NotifyManagerOnClockFlag;
use App\Listeners\NotifyManagerOnLeave;
use App\Listeners\ReleaseWorkOrderOnFiscalNoteAuthorized;
use App\Listeners\RepairSeal\DispatchPseiSubmission;
use App\Listeners\RepairSeal\LogAssignment;
use App\Listeners\RepairSeal\LogBatchReceipt;
use App\Listeners\RepairSeal\ResolveDeadlineAlert;
use App\Listeners\SendClockComprovante;
use App\Listeners\SendDocumentExpiryAlert;
use App\Listeners\SendVacationDeadlineAlert;
use App\Listeners\TriggerCertificateGeneration;
use App\Listeners\TriggerNpsSurvey;
use App\Modules\Metrologia\Listeners\GerarRascunhoCertificadoListener;
use App\Modules\Metrologia\Listeners\SendCalibrationCertificateNotification;
use App\Modules\OrdemServico\Events\OrdemServicoFinalizadaEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        WorkOrderStarted::class => [
            LogWorkOrderStartActivity::class,
        ],
        WorkOrderCompleted::class => [
            HandleWorkOrderCompletion::class,
            TriggerNpsSurvey::class,
            [CreateWarrantyTrackingOnWorkOrderInvoiced::class, 'handleWorkOrderCompleted'],
        ],
        WorkOrderInvoiced::class => [
            HandleWorkOrderInvoicing::class,
            [CreateWarrantyTrackingOnWorkOrderInvoiced::class, 'handleWorkOrderInvoiced'],
        ],
        WorkOrderCancelled::class => [
            HandleWorkOrderCancellation::class,
        ],
        FiscalNoteAuthorized::class => [
            ReleaseWorkOrderOnFiscalNoteAuthorized::class,
            SendCalibrationCertificateNotification::class,
        ],
        InvoiceCreated::class => [
            AutoEmitNFeOnInvoice::class,
        ],
        QuoteApproved::class => [
            HandleQuoteApproval::class,
            CreateAgendaItemOnQuote::class,
        ],
        PaymentReceived::class => [
            HandlePaymentReceived::class,
            CreateAgendaItemOnPayment::class,
        ],
        PaymentMade::class => [
            HandlePaymentMade::class,
        ],
        CalibrationExpiring::class => [
            HandleCalibrationExpiring::class,
            CreateAgendaItemOnCalibration::class,
        ],
        ContractRenewing::class => [
            HandleContractRenewing::class,
            CreateAgendaItemOnContract::class,
        ],
        CustomerCreated::class => [
            HandleCustomerCreated::class,
        ],
        OrdemServicoFinalizadaEvent::class => [
            GerarRascunhoCertificadoListener::class,
        ],
        // NOTE: ExpenseApproved, ExpenseLimitExceeded, and StockEntryFromNF removed
        // from $listen — these events are never dispatched anywhere in the codebase.
        // ExpenseApproved/StockEntryFromNF are handled by their respective Observers.
        // Listener files preserved for potential future use.

        WorkOrderStatusChanged::class => [
            HandleWorkOrderStatusChanged::class,
        ],
        ServiceCallCreated::class => [
            HandleServiceCallCreated::class,
        ],

        // NOTE: The following events also have subscribers for agenda items:
        // - WorkOrderStatusChanged → also handled by CreateAgendaItemOnWorkOrder subscriber + WorkOrderObserver
        // - ServiceCallCreated, ServiceCallStatusChanged → also handled by CreateAgendaItemOnServiceCall subscriber
        // - NotificationSent → audit/logging only, no listener needed
        // - ReconciliationUpdated → handled inline in ReconciliationService
        // - TechnicianLocationUpdated → consumed by frontend via broadcasting only
        // - ExpenseLimitExceeded → never dispatched, preserved for future use

        CommissionGenerated::class => [
            NotifyBeneficiaryOnCommission::class,
        ],
        CalibrationCompleted::class => [
            TriggerCertificateGeneration::class,
            GenerateCorrectiveQuoteOnCalibrationFailure::class,
        ],

        // ─── HR Events ──────────────
        CltViolationDetected::class => [
            NotifyManagerOfViolation::class,
        ],
        ClockEntryRegistered::class => [
            SendClockComprovante::class,
        ],
        ClockEntryFlagged::class => [
            NotifyManagerOnClockFlag::class,
        ],
        ClockAdjustmentRequested::class => [
            NotifyManagerOnAdjustment::class,
        ],
        ClockAdjustmentDecided::class => [
            NotifyEmployeeOnAdjustmentDecision::class,
        ],
        LeaveRequested::class => [
            NotifyManagerOnLeave::class,
        ],
        LeaveDecided::class => [
            NotifyEmployeeOnLeaveDecision::class,
        ],
        DocumentExpiring::class => [
            SendDocumentExpiryAlert::class,
        ],
        VacationDeadlineApproaching::class => [
            SendVacationDeadlineAlert::class,
        ],
        HrActionAudited::class => [
            AuditHrActionListener::class,
        ],

        // ─── RepairSeals Events ──────────
        SealUsedOnWorkOrder::class => [
            DispatchPseiSubmission::class,
        ],
        SealPseiSubmitted::class => [
            ResolveDeadlineAlert::class,
        ],
        SealBatchReceived::class => [
            LogBatchReceipt::class,
        ],
        SealAssignedToTechnician::class => [
            LogAssignment::class,
        ],
    ];

    /**
     * Subscribers que escutam múltiplos eventos.
     */
    protected $subscribe = [
        CreateAgendaItemOnWorkOrder::class,
        CreateAgendaItemOnServiceCall::class,
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
