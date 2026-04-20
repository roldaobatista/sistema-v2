<?php

namespace App\Providers;

use App\Contracts\SmsProviderInterface;
use App\Models\AccountReceivable;
use App\Models\AssetRecord;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TimeClockAdjustment;
use App\Models\TimeClockEntry;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use App\Observers\AccountReceivableObserver;
use App\Observers\CrmDealAgendaObserver;
use App\Observers\CrmObserver;
use App\Observers\CustomerObserver;
use App\Observers\ExpenseObserver;
use App\Observers\PriceTrackingObserver;
use App\Observers\QuoteObserver;
use App\Observers\StockMovementObserver;
use App\Observers\TenantObserver;
use App\Observers\TimeClockAdjustmentObserver;
use App\Observers\TimeClockEntryObserver;
use App\Observers\TimeEntryObserver;
use App\Observers\WorkOrderObserver;
use App\Policies\AssetRecordPolicy;
use App\Sentinel\HorizonDriver;
use App\Services\CreditRiskAnalysisService;
use App\Services\Fiscal\Adapters\ExternalNFeAdapter;
use App\Services\Fiscal\Contracts\FiscalGatewayInterface;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\FocusNFeProvider;
use App\Services\Fiscal\NuvemFiscalProvider;
use App\Services\LogSmsProvider;
use App\Services\Payment\AsaasPaymentProvider;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\TwilioSmsProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Horizon\Horizon;
use Laravel\Sentinel\Sentinel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config([
            'permission.teams' => true,
            'permission.models.role' => Role::class,
            'permission.column_names.team_foreign_key' => 'tenant_id',
        ]);

        if ($this->app->runningUnitTests()) {
            $this->app->register(TestingSqliteServiceProvider::class);
        }

        $this->app->bind(
            FiscalProvider::class,
            function () {
                $provider = config('fiscal.provider', config('services.fiscal.provider', 'focusnfe'));

                return match ($provider) {
                    'nuvemfiscal' => new NuvemFiscalProvider,
                    default => new FocusNFeProvider,
                };
            }
        );

        $this->app->bind(
            FiscalGatewayInterface::class,
            ExternalNFeAdapter::class
        );

        $this->app->bind(
            PaymentGatewayInterface::class,
            function () {
                $provider = config('payment.provider', 'asaas');

                return match ($provider) {
                    default => new AsaasPaymentProvider,
                };
            }
        );

        $this->app->bind(
            SmsProviderInterface::class,
            function () {
                $driver = config('services.collection_sms.driver', 'log');

                return match ($driver) {
                    'twilio' => new TwilioSmsProvider(
                        config('services.collection_sms.twilio.sid'),
                        config('services.collection_sms.twilio.token'),
                        config('services.collection_sms.twilio.from')
                    ),
                    default => new LogSmsProvider,
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // sec-reverb-cors-wildcard (Camada 1 r4 Batch C — §14.33):
        // Em produção, allowed_origins VAZIO abre CSWSH para qualquer origem
        // que conheça o app_key público. Fail-closed: fallback para APP_URL
        // se existir, senão aborta o boot com mensagem clara. Em dev/testing
        // o comportamento é permissivo para não atrapalhar DX.
        $reverbOrigins = (array) config('reverb.apps.apps.0.allowed_origins', []);
        if (empty($reverbOrigins)) {
            if ($this->app->environment('production')) {
                $appUrl = (string) config('app.url', '');
                if ($appUrl !== '' && $appUrl !== 'http://localhost') {
                    config(['reverb.apps.apps.0.allowed_origins' => [$appUrl]]);
                } else {
                    throw new \RuntimeException(
                        'REVERB_ALLOWED_ORIGINS vazio em produção e APP_URL não configurada. '
                        .'Configure REVERB_ALLOWED_ORIGINS (CSV) para evitar CSWSH.'
                    );
                }
            }
            // dev/testing: ausência de origins mantém config vazia.
            // Não reintroduz '*' — quem precisar de wildcard em dev define env.
        }

        Gate::policy(AssetRecord::class, AssetRecordPolicy::class);

        // SUPER_ADMIN bypass — Spatie recommended pattern.
        // Grants all abilities to super admins at the Gate level,
        // so Policies don't need to check individual permissions.
        Gate::before(function ($user, $ability) {
            if (is_object($user) && method_exists($user, 'hasRole') && $user->hasRole(Role::SUPER_ADMIN)) {
                return true;
            }
        });

        Model::shouldBeStrict(! $this->app->isProduction());
        // Tenant-aware rate limiters — composite key tenant_id:user_id garante
        // isolamento entre tenants e granularidade por usuário.
        $tenantKey = fn (Request $request): string => $request->user()
            ? (($request->user()->current_tenant_id ?? 'no-tenant').':'.$request->user()->id)
            : $request->ip();

        RateLimiter::for('api', function (Request $request) use ($tenantKey) {
            return Limit::perMinute(120)->by($tenantKey($request));
        });

        RateLimiter::for('tenant-reads', function (Request $request) use ($tenantKey) {
            return Limit::perMinute(120)->by($tenantKey($request));
        });

        RateLimiter::for('tenant-mutations', function (Request $request) use ($tenantKey) {
            return Limit::perMinute(30)->by($tenantKey($request));
        });

        RateLimiter::for('tenant-uploads', function (Request $request) use ($tenantKey) {
            return Limit::perMinute(30)->by($tenantKey($request));
        });

        RateLimiter::for('tenant-bulk', function (Request $request) use ($tenantKey) {
            return Limit::perMinute(60)->by($tenantKey($request));
        });

        RateLimiter::for('tenant-exports', function (Request $request) use ($tenantKey) {
            return Limit::perMinute(10)->by($tenantKey($request));
        });

        RateLimiter::for('tenant-tracking', function (Request $request) {
            return Limit::perMinute(600)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(240)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
        $observer = CrmObserver::class;
        WorkOrder::updated(fn (WorkOrder $wo) => app($observer)->workOrderUpdated($wo));
        Quote::updated(fn (Quote $q) => app($observer)->quoteUpdated($q));

        // CRM & Financial triggers for Health Score / CRM Activity
        CrmActivity::created(fn ($activity) => $this->handleCrmActivity($activity));
        AccountReceivable::updated(fn ($ar) => $this->handleAccountReceivable($ar));

        AccountReceivable::observe(AccountReceivableObserver::class);

        WorkOrder::observe(WorkOrderObserver::class);
        Customer::observe(CustomerObserver::class);
        CrmDeal::observe(CrmDealAgendaObserver::class);
        Expense::observe(ExpenseObserver::class);

        // Price tracking
        Product::observe(PriceTrackingObserver::class);
        Service::observe(PriceTrackingObserver::class);

        // Tech Status Automation
        TimeEntry::observe(TimeEntryObserver::class);

        // Stock Entry → AccountPayable (NF de entrada)
        StockMovement::observe(StockMovementObserver::class);

        // Tenant cache invalidation on status change
        Tenant::observe(TenantObserver::class);

        // Geração automática de número do orçamento
        Quote::observe(QuoteObserver::class);

        // HR Audit Trail (Portaria 671/2021)
        TimeClockEntry::observe(TimeClockEntryObserver::class);
        TimeClockAdjustment::observe(TimeClockAdjustmentObserver::class);

        // URL de reset de senha aponta para o frontend
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

            return $frontendUrl.'/redefinir-senha?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });

        // sec-04 (Re-auditoria Camada 1): policy de senha elevada.
        // OWASP ASVS L1: min 12 chars + mixed case + numbers + symbols +
        // uncompromised (checa HaveIBeenPwned). Aplicada a toda Rule::Password::defaults().
        Password::defaults(function () {
            $rule = Password::min(12)->mixedCase()->letters()->numbers()->symbols();

            return app()->environment('production') ? $rule->uncompromised() : $rule;
        });

        Gate::define('viewHorizon', function ($user = null) {
            if (! $user) {
                return false;
            }

            return $user->hasRole('super_admin') || $user->hasPermissionTo('horizon.view');
        });

        if (class_exists(Sentinel::class)) {
            Sentinel::extend('horizon', fn () => new HorizonDriver(fn () => $this->app));
        }

        $alertEmail = config('app.system_alert_email');
        if ($alertEmail) {
            Horizon::routeMailNotificationsTo($alertEmail);
        }
    }

    private function handleCrmActivity($activity)
    {
        if ($activity->customer && $activity->completed_at) {
            try {
                $activity->customer->update(['last_contact_at' => $activity->completed_at]);
                $activity->customer->recalculateHealthScore();
            } catch (\Throwable $e) {
                Log::warning("handleCrmActivity: falha ao atualizar customer #{$activity->customer_id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function handleAccountReceivable($ar)
    {
        if (! $ar->wasChanged('status')) {
            return;
        }

        if ($ar->status === AccountReceivable::STATUS_PAID) {
            try {
                $ar->customer?->recalculateHealthScore();
            } catch (\Throwable $e) {
                Log::warning("handleAccountReceivable: health score falhou para customer #{$ar->customer_id}", ['error' => $e->getMessage()]);
            }
        }

        // Atualiza risk score do cliente quando status financeiro muda (paid ou overdue)
        $riskTriggerStatuses = [
            AccountReceivable::STATUS_PAID,
            AccountReceivable::STATUS_OVERDUE,
        ];
        if (in_array($ar->status, $riskTriggerStatuses) && $ar->customer_id && $ar->tenant_id) {
            try {
                app(CreditRiskAnalysisService::class)
                    ->analyzeCustomer($ar->tenant_id, $ar->customer_id, persist: true);
            } catch (\Throwable $e) {
                Log::warning("CreditRiskAnalysis falhou para customer #{$ar->customer_id}: {$e->getMessage()}");
            }
        }
    }
}
