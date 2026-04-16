<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RemainingModules\RoiCalculatorRequest;
use App\Http\Requests\RemainingModules\UpdateThemeConfigRequest;
use App\Models\AccountReceivable;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InnovationController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function themeConfig(): JsonResponse
    {
        $theme = DB::table('custom_themes')
            ->where('tenant_id', $this->tenantId())
            ->first();

        return ApiResponse::data($theme ?? [
            'primary_color' => '#3B82F6', 'secondary_color' => '#10B981', 'accent_color' => '#F59E0B',
            'dark_mode' => false, 'sidebar_style' => 'default', 'font_family' => 'Inter',
        ]);
    }

    public function updateThemeConfig(UpdateThemeConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('custom_themes')->updateOrInsert(
                ['tenant_id' => $this->tenantId()],
                array_merge($validated, ['updated_at' => now()])
            );

            return ApiResponse::message('Tema atualizado');
        } catch (\Exception $e) {
            Log::error('Theme config update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar tema', 500);
        }
    }

    public function referralProgram(): JsonResponse
    {
        $tenantId = $this->tenantId();

        $referrals = DB::table('referral_codes')
            ->where('tenant_id', $tenantId)
            ->where('referrer_id', (int) auth()->id())
            ->get();

        return ApiResponse::data($referrals);
    }

    public function generateReferralCode(): JsonResponse
    {
        try {
            DB::beginTransaction();

            $code = strtoupper(Str::random(8));
            $userId = auth()->id();

            $existing = DB::table('referral_codes')
                ->where('referrer_id', $userId)
                ->where('tenant_id', $this->tenantId())
                ->first();

            if ($existing) {
                DB::commit();

                return ApiResponse::message('Código já existente', 422, ['code' => $existing->code]);
            }

            DB::table('referral_codes')->insert([
                'tenant_id' => $this->tenantId(),
                'referrer_id' => $userId,
                'code' => $code,
                'reward_type' => 'discount',
                'reward_value' => 10,
                'uses' => 0,
                'is_active' => true,
                'created_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['code' => $code], 201, ['message' => 'Código de indicação gerado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Referral code generation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar código de indicação', 500);
        }
    }

    public function roiCalculator(RoiCalculatorRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $monthlyRevenue = $validated['monthly_os_count'] * $validated['avg_os_value'];
        $timeSaved = $validated['time_saved_percent'] ?? 30;
        $additionalCapacity = $validated['monthly_os_count'] * ($timeSaved / 100);
        $additionalRevenue = $additionalCapacity * $validated['avg_os_value'];
        $monthlySavings = $validated['current_monthly_cost'] - $validated['system_monthly_cost'] + $additionalRevenue;
        $annualRoi = ($monthlySavings * 12) / max($validated['system_monthly_cost'] * 12, 1) * 100;
        $paybackMonths = $validated['system_monthly_cost'] > 0 ? ceil($validated['system_monthly_cost'] / max($monthlySavings, 1)) : 0;

        return ApiResponse::data([
            'current_monthly_revenue' => $monthlyRevenue,
            'additional_os_capacity' => round($additionalCapacity),
            'additional_monthly_revenue' => round($additionalRevenue, 2),
            'monthly_savings' => round($monthlySavings, 2),
            'annual_roi_percent' => round($annualRoi, 1),
            'payback_months' => $paybackMonths,
            'time_saved_percent' => $timeSaved,
        ]);
    }

    public function presentationData(): JsonResponse
    {
        $tenantId = $this->tenantId();
        $yearStart = now()->copy()->startOfYear()->toDateString();
        $yearEnd = now()->copy()->endOfYear()->toDateString();
        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';

        $safeCount = function (string $table, array $extra = []) use ($tenantId) {
            try {
                $q = DB::table($table)->where('tenant_id', $tenantId);
                foreach ($extra as $k => $v) {
                    $q->where($k, $v);
                }

                return $q->count();
            } catch (\Throwable) {
                return 0;
            }
        };

        $safeAvg = function (string $table, string $col) use ($tenantId) {
            try {
                return DB::table($table)->where('tenant_id', $tenantId)->avg($col);
            } catch (\Throwable) {
                return null;
            }
        };

        $data = [
            'company' => DB::table('tenants')->where('id', $tenantId)->first(['name', 'document']),
            'kpis' => [
                'total_customers' => DB::table('customers')->where('tenant_id', $tenantId)->count(),
                'total_os_year' => DB::table('work_orders')->where('tenant_id', $tenantId)->whereYear('created_at', now()->year)->count(),
                'revenue_year' => DB::table('payments')
                    ->where('tenant_id', $tenantId)
                    ->where('payable_type', AccountReceivable::class)
                    ->whereBetween('payment_date', [$yearStart, $yearEnd.' 23:59:59'])
                    ->sum('amount')
                    + DB::table('accounts_receivable')
                        ->where('tenant_id', $tenantId)
                        ->where('amount_paid', '>', 0)
                        ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$yearStart, $yearEnd.' 23:59:59'])
                        ->whereNotExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('payments')
                                ->whereColumn('payments.payable_id', 'accounts_receivable.id')
                                ->where('payments.payable_type', AccountReceivable::class);
                        })
                        ->sum('amount_paid'),
                'nps_score' => $safeAvg('nps_surveys', 'score'),
                'certificates_issued' => $safeCount('calibration_certificates'),
            ],
            'monthly_trend' => DB::table('work_orders')
                ->where('tenant_id', $tenantId)
                ->whereYear('created_at', now()->year)
                ->select(DB::raw("{$monthExpression} as month"), DB::raw('COUNT(*) as total'))
                ->groupByRaw($monthExpression)
                ->get(),
        ];

        return ApiResponse::data($data);
    }

    public function easterEgg(string $code): JsonResponse
    {
        $eggs = [
            'konami' => '🎮 ↑↑↓↓←→←→BA - Você encontrou o Konami Code! +1000 pontos de experiência!',
            'matrix' => '💊 Red pill or blue pill? Você escolheu ver a verdade do código...',
            'rocket' => '🚀 To infinity and beyond! O Kalibrium está pronto para o espaço!',
            'coffee' => '☕ Error 418: I\'m a teapot... mas fazemos café também!',
            'calibrium' => '⚖️ Precisão é a nossa paixão! Obrigado por fazer parte da nossa história.',
        ];

        $message = $eggs[$code] ?? '🔍 Hmm... não encontrei nada aqui. Continue explorando!';

        return ApiResponse::data(['found' => isset($eggs[$code])], 200, ['message' => $message]);
    }
}
