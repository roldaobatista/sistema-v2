<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DebtRenegotiationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Features\GeneratePaymentReceiptRequest;
use App\Http\Requests\Features\StoreRenegotiationRequest;
use App\Mail\RenegotiationApprovedMail;
use App\Models\AccountReceivable;
use App\Models\CollectionAction;
use App\Models\DebtRenegotiation;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\CollectionAutomationService;
use App\Services\DebtRenegotiationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RenegotiationController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function indexRenegotiations(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            DebtRenegotiation::where('tenant_id', $this->tenantId($request))
                ->with(['customer:id,name', 'creator:id,name'])
                ->orderByDesc('created_at')
                ->paginate(min((int) $request->input('per_page', 25), 100))
        );
    }

    public function storeRenegotiation(StoreRenegotiationRequest $request, DebtRenegotiationService $service): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->tenantId($request);
        $renegotiation = $service->create($data, $data['receivable_ids'], $request->user()->id);

        return ApiResponse::data($renegotiation, 201);
    }

    public function approveRenegotiation(Request $request, DebtRenegotiation $renegotiation, DebtRenegotiationService $service): JsonResponse
    {
        if ($renegotiation->status !== DebtRenegotiationStatus::PENDING) {
            return ApiResponse::message('Renegociação não está pendente.', 422);
        }

        $result = $service->approve($renegotiation, $request->user()->id);

        if ($renegotiation->customer_id) {
            try {
                $customer = $renegotiation->customer;
                if ($customer?->email) {
                    Mail::to($customer->email)->queue(
                        new RenegotiationApprovedMail($renegotiation->fresh(), $customer)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao notificar cliente sobre renegociação aprovada', [
                    'renegotiation_id' => $renegotiation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ApiResponse::data(['renegotiation' => $result], 200, ['message' => 'Renegociação aprovada. Novas parcelas geradas.']);
    }

    public function rejectRenegotiation(DebtRenegotiation $renegotiation, DebtRenegotiationService $service): JsonResponse
    {
        $result = $service->reject($renegotiation);

        return ApiResponse::data(['renegotiation' => $result], 200, ['message' => 'Renegociação rejeitada.']);
    }

    public function generateReceipt(GeneratePaymentReceiptRequest $request, int $paymentId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $payment = Payment::where('tenant_id', $tenantId)->findOrFail($paymentId);
        $receipt = PaymentReceipt::create([
            'tenant_id' => $tenantId,
            'payment_id' => $payment->id,
            'receipt_number' => 'REC-'.str_pad((string) ((int) PaymentReceipt::where('tenant_id', $tenantId)->lockForUpdate()->max('id') + 1), 6, '0', STR_PAD_LEFT),
            'generated_by' => $request->user()->id,
        ]);

        return ApiResponse::data($receipt, 201);
    }

    public function runCollectionEngine(Request $request, CollectionAutomationService $service): JsonResponse
    {
        $results = $service->processForTenant($this->tenantId($request));

        return ApiResponse::data(['results' => $results], 200, ['message' => 'Régua de cobrança executada.']);
    }

    public function collectionSummary(Request $request): JsonResponse
    {
        $tid = $this->tenantId($request);
        // Include both 'overdue' and 'partial' statuses with past due_date as overdue
        $overdue = AccountReceivable::where('tenant_id', $tid)
            ->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
            ->where('due_date', '<', now()->toDateString())
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount - amount_paid), 0) as total')
            ->first();

        $recentActions = CollectionAction::where('tenant_id', $tid)
            ->with('receivable:id,description,amount,due_date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $overdueCount = (int) ($overdue->count ?? 0);
        $overdueTotal = (float) ($overdue->total ?? 0);

        return ApiResponse::data([
            'overdue_count' => $overdueCount,
            'overdue_total' => $overdueTotal,
            // Aliases expected by tests
            'total_overdue' => $overdueCount,
            'total_overdue_amount' => $overdueTotal,
            'recent_actions' => $recentActions,
        ]);
    }

    public function collectionActions(Request $request): JsonResponse
    {
        $tid = $this->tenantId($request);
        $query = CollectionAction::where('tenant_id', $tid)
            ->with('receivable:id,description,amount,due_date,customer_id');

        if ($type = $request->input('type')) {
            $query->where('action_type', $type);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 25), 100))
        );
    }
}
