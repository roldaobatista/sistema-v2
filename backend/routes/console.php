<?php

use App\Jobs\CalculateDailyJourney;
use App\Jobs\CheckDocumentVersionExpiry;
use App\Jobs\ClassifyEmailJob;
use App\Jobs\FleetDocExpirationAlertJob;
use App\Jobs\FleetMaintenanceAlertJob;
use App\Jobs\GenerateCrmSmartAlerts;
use App\Jobs\MonthlyEspelhoDeliveryJob;
use App\Jobs\ProcessCrmSequences;
use App\Jobs\QuoteExpirationAlertJob;
use App\Jobs\QuoteFollowUpJob;
use App\Jobs\RepairSeal\CheckSealDeadlinesJob;
use App\Jobs\RepairSeal\RetryFailedPseiSubmissionsJob;
use App\Jobs\RunMonthlyDepreciation;
use App\Jobs\SendScheduledEmails;
use App\Jobs\StockMinimumAlertJob;
use App\Jobs\SyncEmailAccountJob;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\Tenant;
use App\Notifications\SystemAlertNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

$alertOnFailure = function (): void {
    $email = config('app.system_alert_email');
    if ($email) {
        Notification::route('mail', $email)
            ->notify(new SystemAlertNotification(
                'Scheduled Job Failed',
                'Um job agendado falhou. Verificar logs.',
                'error'
            ));
    }
};

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── CRM Automations (diário às 7h) ────────
Schedule::command('crm:process-automations')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/crm-automations.log'));

// ─── Calibration Alerts (diário às 7:30h) ───
Schedule::command('calibration:alerts')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/calibration-alerts.log'));

// ─── Mark Overdue Receivables (diário às 6:00h) ───
Schedule::command('app:mark-overdue-receivables')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/overdue-receivables.log'));

// ─── Mark Overdue Payables (diário às 6:05h) ───
Schedule::command('app:mark-overdue-payables')
    ->dailyAt('06:05')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/overdue-payables.log'));

// ─── Recurring Work Orders (#24) (diário às 6:30h) ───
Schedule::command('app:generate-recurring-work-orders')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/recurring-work-orders.log'));

// ─── Expired Quotes (diário às 06:15) ───
Schedule::command('quotes:check-expired')
    ->dailyAt('06:15')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/expired-quotes.log'));

// ─── Quote Expiration Alerts (diário às 06:20) ───
Schedule::job(new QuoteExpirationAlertJob)
    ->dailyAt('06:20')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/quote-expiration-alerts.log'));

// ─── Quote Follow-up (diário às 08:20) ───
Schedule::job(new QuoteFollowUpJob)
    ->dailyAt('08:20')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/quote-followup.log'));

// ─── Low Stock Alerts (diário às 07:15) ───
Schedule::command('stock:check-low')
    ->dailyAt('07:15')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/stock-low-alerts.log'));

// ─── Upcoming Payments (diário às 08:00) ───
Schedule::command('notify:upcoming-payments')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/upcoming-payments.log'));

// ─── Auto Billing Recurring Contracts (mensal dia 1 às 06:00) ───
Schedule::command('contracts:bill-recurring')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping(60)
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/recurring-billing.log'));

// ─── Work Order Recurrences (#24b) (diário às 07:00) ───
Schedule::command('app:process-work-order-recurrences')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/work-order-recurrences.log'));

// ─── SLA Breach Detection (a cada 15 min) ───
Schedule::command('sla:check-breaches')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/sla-breaches.log'));

// ─── Central: Varredura de Financeiros Vencidos (diário às 06:30) ───
Schedule::command('central:scan-financials')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/central-scan-financials.log'));

// ─── Central: Lembretes (remind_at) a cada 5 min ───
Schedule::command('central:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/central-reminders.log'));

// ─── INMETRO Sync 4x/dia (06:00, 10:00, 14:00, 18:00) ───
foreach (['06:00', '10:00', '14:00', '18:00'] as $time) {
    Schedule::command('inmetro:sync')
        ->dailyAt($time)
        ->withoutOverlapping()
        ->onFailure($alertOnFailure)
        ->appendOutputTo(storage_path('logs/inmetro-sync.log'));
}

// ─── INMETRO Rejection Check (a cada 4h — detecção urgente de reprovados) ───
Schedule::command('inmetro:check-rejections')
    ->everyFourHours()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/inmetro-rejections.log'));

// ─── Cleanup: Arquivos Órfãos de OS (semanal domingo 03:30) ───
Schedule::command('cleanup:orphan-files')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/orphan-files-cleanup.log'));

// ─── Inventário PWA: lembrete semanal (técnico e motorista) ───
Schedule::command('inventory:weekly-reminder')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/inventory-weekly-reminder.log'));

// ─── Inventário central: lembrete mensal (estoquista) ───
Schedule::command('inventory:monthly-central-reminder')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/inventory-monthly-central.log'));

// ─── INMETRO Lead Generation (diário às 07:45) ───
Schedule::command('inmetro:generate-leads')
    ->dailyAt('07:45')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/inmetro-leads.log'));

// ─── GAP-12: Overdue Follow-ups (diário às 08:15) ───
Schedule::command('customers:check-overdue-follow-ups')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/overdue-follow-ups.log'));

// ─── Contratos Recorrentes Vencendo (diário às 07:00) ───
Schedule::command('contracts:check-expiring')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/expiring-contracts.log'));

// ─── 🔴 CRÍTICO: OS Concluída Sem Faturamento (diário às 08:30) ───
Schedule::command('work-orders:check-unbilled')
    ->dailyAt('08:30')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/unbilled-work-orders.log'));

// ─── Alert Engine (diário às 07:40) ───
Schedule::command('alerts:run')
    ->dailyAt('07:40')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/alert-engine.log'));

// ─── Collection Automation (diário às 09:00) ───
Schedule::command('collection:run')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/collection-automation.log'));

// ─── Satisfaction Survey Post-OS (diário às 10:00) ───
Schedule::command('surveys:send-post-os')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/post-os-surveys.log'));

// ─── Email IMAP Sync (a cada 2 min) ───
Schedule::call(function () {
    Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id')->each(function ($tenantId) {
        try {
            app()->instance('current_tenant_id', $tenantId);
            EmailAccount::where('is_active', true)->each(function ($account) {
                SyncEmailAccountJob::dispatch($account);
            });
        } catch (Throwable $e) {
            Log::error("Email sync failed for tenant {$tenantId}", ['error' => $e->getMessage()]);
        }
    });
})
    ->everyTwoMinutes()
    ->name('email-imap-sync')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/email-sync.log'));

// ─── Email AI Classification (a cada 5 min) ───
Schedule::call(function () {
    Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id')->each(function ($tenantId) {
        try {
            app()->instance('current_tenant_id', $tenantId);
            Email::whereNull('ai_classified_at')
                ->where('created_at', '>=', now()->subDay())
                ->each(function ($email) {
                    ClassifyEmailJob::dispatch($email);
                });
        } catch (Throwable $e) {
            Log::error("Email AI classify failed for tenant {$tenantId}", ['error' => $e->getMessage()]);
        }
    });
})
    ->everyFiveMinutes()
    ->name('email-ai-classify')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/email-classify.log'));

// ─── Send Scheduled Emails (a cada minuto) ───
Schedule::job(new SendScheduledEmails)
    ->everyMinute()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/email-scheduled-send.log'));

// ─── CRM: Processar Cadências de Prospecção (a cada hora) ───
Schedule::job(new ProcessCrmSequences)
    ->hourly()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/crm-sequences.log'));

// ─── Relatórios Agendados (diário às 07:10) ───
Schedule::command('reports:send-scheduled')
    ->dailyAt('07:10')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/scheduled-reports.log'));

// ─── CRM: Gerar Alertas Inteligentes (diário às 07:20) ───
Schedule::job(new GenerateCrmSmartAlerts)
    ->dailyAt('07:20')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/crm-smart-alerts.log'));

// ─── Audit log prune: export + delete registros > 6 meses (compliance + performance MySQL) ───
Schedule::command('audit:prune')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping(120)
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/audit-prune.log'));

// ─── Calibration Expiry Notifications (diário às 08:00) ───
Schedule::command('calibration:notify-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/calibration-expiry-notify.log'));

// ─── Financial Penalties Calculation (diário às 06:10, após mark-overdue-receivables das 06:00) ───
Schedule::command('financial:calculate-penalties')
    ->dailyAt('06:10')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/financial-penalties.log'));

// ─── Database Backup (diário às 02:00) ───
Schedule::command('db:backup --retention=30')
    ->dailyAt('02:00')
    ->withoutOverlapping(60)
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/db-backup.log'));

// ─── System Health Alerts (a cada 5 min) ───
Schedule::command('system:check-alerts')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/system-alerts.log'));

// ─── Redis Backup (diário às 02:30, após MySQL backup) ───
Schedule::command('redis:backup --retention=30')
    ->dailyAt('02:30')
    ->withoutOverlapping(30)
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/redis-backup.log'));

// ─── Peripheral Alerts: Fleet, HR, Quality (diário às 07:50) ───
Schedule::command('alerts:process-peripheral')
    ->dailyAt('07:50')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/peripheral-alerts.log'));

// ─── Fleet: Manutenção Preventiva por KM (diário às 07:55) ───
Schedule::job(new FleetMaintenanceAlertJob)
    ->dailyAt('07:55')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/fleet-maintenance-alerts.log'));

// ─── Fleet: Documentos Vencendo (diário às 08:05) ───
Schedule::job(new FleetDocExpirationAlertJob)
    ->dailyAt('08:05')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/fleet-doc-alerts.log'));

// ─── Estoque Mínimo (a cada hora) ───
Schedule::job(new StockMinimumAlertJob)
    ->hourly()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/stock-minimum-alerts.log'));

// ─── Integration Health Monitor (a cada 30 min) ───
Schedule::command('integrations:health-check')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/integration-health.log'));

Schedule::command('observability:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/observability-snapshots.log'));

Schedule::command('analytics:refresh-datasets')
    ->hourly()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/analytics-refresh-datasets.log'));

// ─── Quality: DocumentVersion Revisão Expirando (diário às 07:55) ───
Schedule::job(new CheckDocumentVersionExpiry)
    ->dailyAt('07:55')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/document-version-expiry.log'));

// ─── HR: Documentos Expirando (diário às 08:00) ───
Schedule::command('hr:check-expiring-documents')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/hr-expiring-documents.log'));

// ─── HR: Prazos de Férias (diário às 08:10) ───
Schedule::command('hr:check-expiring-vacations')
    ->dailyAt('08:10')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/hr-vacation-deadlines.log'));

// ─── HR: Cálculo de Jornada Diária (diário às 23:30) ───
Schedule::job(new CalculateDailyJourney)
    ->dailyAt('23:30')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/hr-daily-journey.log'));

// ─── HR: Expiração Banco de Horas — Art. 59 §§2,5 CLT (diário às 23:45) ───
Schedule::command('hr:check-hour-bank-expiry')
    ->dailyAt('23:45')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/hr-hour-bank-expiry.log'));

// ─── HR: Detecção de Violações CLT — Portaria 671 (diário às 23:50) ───
Schedule::command('hr:detect-clt-violations')
    ->dailyAt('23:50')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/hr-clt-violations.log'));

// ─── HR: Espelho de Ponto Mensal — Portaria 671 Art 94 (mensal dia 5 às 06:00) ───
Schedule::job(new MonthlyEspelhoDeliveryJob)
    ->monthlyOn(5, '06:00')
    ->withoutOverlapping(60)
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/hr-espelho-monthly.log'));

// ─── RepairSeals: Verificar prazos de selos PSEI (diário 08:00) ───
Schedule::job(new CheckSealDeadlinesJob)
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/repair-seal-deadlines.log'));

// ─── RepairSeals: Retentar submissões PSEI falhadas (a cada 2h) ───
Schedule::job(new RetryFailedPseiSubmissionsJob)
    ->everyTwoHours()
    ->withoutOverlapping()
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/repair-seal-psei-retry.log'));

// ─── Fixed Assets: Depreciação mensal (dia 1 às 05:00) ───
Schedule::job(new RunMonthlyDepreciation)
    ->monthlyOn(1, '05:00')
    ->withoutOverlapping(60)
    ->onFailure($alertOnFailure)
    ->appendOutputTo(storage_path('logs/fixed-assets-depreciation.log'));
