<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\RemainingModules\EmitNfseRequest;
use App\Http\Requests\RemainingModules\GenerateBoletoRequest;
use App\Http\Requests\RemainingModules\ProcessOnlinePaymentRequest;
use App\Http\Requests\RemainingModules\ToggleCustomerBlockRequest;
use App\Models\AccountReceivable;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinancialExtraController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function generateBoleto(GenerateBoletoRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Geração de boleto não está configurada. Configure a integração bancária em Configurações → Financeiro.',
            'code' => 'BOLETO_NOT_CONFIGURED',
        ], 422);
    }

    public function emitNfse(EmitNfseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('nfse_emissions')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'work_order_id' => $validated['work_order_id'],
                'service_description' => $validated['service_description'],
                'amount' => $validated['amount'],
                'iss_rate' => $validated['iss_rate'] ?? 5.0,
                'iss_amount' => bcdiv(bcmul((string) $validated['amount'], (string) ($validated['iss_rate'] ?? 5.0), 4), '100', 2),
                'status' => 'pending',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'NFS-e em processamento']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('NFS-e emission failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao emitir NFS-e', 500);
        }
    }

    public function paymentGatewayConfig(): JsonResponse
    {
        $config = DB::table('payment_gateway_configs')
            ->where('tenant_id', $this->tenantId())
            ->first();

        return ApiResponse::data($config ?? ['gateway' => 'none', 'methods' => ['boleto', 'pix', 'credit_card']]);
    }

    public function processOnlinePayment(ProcessOnlinePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('online_payments')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'receivable_id' => $validated['receivable_id'],
                'method' => $validated['method'],
                'status' => 'processing',
                'created_at' => now(),
            ]);

            $paymentInfo = match ($validated['method']) {
                'pix' => ['pix_key' => 'kalibrium@pix.com', 'qr_code' => 'PIX_QR_'.$id],
                'credit_card' => ['checkout_url' => config('app.url').'/checkout/'.$id],
                'boleto' => ['boleto_url' => config('app.url').'/boleto/'.$id],
            };

            return ApiResponse::data(['id' => $id, 'data' => $paymentInfo], 201, ['message' => 'Pagamento iniciado']);
        } catch (\Exception $e) {
            Log::error('Online payment processing failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao processar pagamento', 500);
        }
    }

    public function financialPortalOverview(): JsonResponse
    {
        $tenantId = $this->tenantId();
        $now = now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $driver = DB::connection()->getDriverName();
        $receivablePayments = DB::table('payments')
            ->select('payable_id', DB::raw('MAX(payment_date) as last_payment_date'))
            ->where('payable_type', AccountReceivable::class)
            ->groupBy('payable_id');
        $daysToReceiveExpression = $driver === 'sqlite'
            ? 'julianday(COALESCE(receivable_payments.last_payment_date, accounts_receivable.paid_at)) - julianday(accounts_receivable.due_date)'
            : 'DATEDIFF(COALESCE(receivable_payments.last_payment_date, accounts_receivable.paid_at), accounts_receivable.due_date)';

        $overview = [
            'total_receivable' => DB::table('accounts_receivable')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
                ->sum(DB::raw('amount - amount_paid')),
            'total_overdue' => DB::table('accounts_receivable')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
                ->where('due_date', '<', now())
                ->sum(DB::raw('amount - amount_paid')),
            'total_received_month' => DB::table('payments')
                ->where('tenant_id', $tenantId)
                ->where('payable_type', AccountReceivable::class)
                ->whereBetween('payment_date', [$monthStart, $monthEnd.' 23:59:59'])
                ->sum('amount')
                + DB::table('accounts_receivable')
                    ->where('tenant_id', $tenantId)
                    ->where('amount_paid', '>', 0)
                    ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$monthStart, $monthEnd.' 23:59:59'])
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('payments')
                            ->whereColumn('payments.payable_id', 'accounts_receivable.id')
                            ->where('payments.payable_type', AccountReceivable::class);
                    })
                    ->sum('amount_paid'),
            'avg_days_to_receive' => DB::table('accounts_receivable')
                ->leftJoinSub($receivablePayments, 'receivable_payments', function ($join) {
                    $join->on('receivable_payments.payable_id', '=', 'accounts_receivable.id');
                })
                ->where('accounts_receivable.tenant_id', $tenantId)
                ->where(function ($query) {
                    $query->where('accounts_receivable.amount_paid', '>', 0)
                        ->orWhere('accounts_receivable.status', 'paid');
                })
                ->avg(DB::raw($daysToReceiveExpression)),
            'customers_overdue' => DB::table('accounts_receivable')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
                ->where('due_date', '<', now())
                ->whereRaw('(amount - amount_paid) > 0')
                ->distinct('customer_id')
                ->count('customer_id'),
        ];

        return ApiResponse::data($overview);
    }

    public function toggleCustomerBlock(ToggleCustomerBlockRequest $request, int $customerId): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('customers')
                ->where('id', $customerId)
                ->where('tenant_id', $this->tenantId())
                ->update([
                    'financial_blocked' => $validated['blocked'],
                    'block_reason' => $validated['reason'] ?? null,
                    'blocked_at' => $validated['blocked'] ? now() : null,
                    'updated_at' => now(),
                ]);

            $action = $validated['blocked'] ? 'bloqueado' : 'desbloqueado';

            return ApiResponse::message("Cliente {$action} financeiramente");
        } catch (\Exception $e) {
            Log::error('Customer block toggle failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao alterar bloqueio', 500);
        }
    }
}
