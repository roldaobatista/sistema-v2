<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::with(['payable', 'receiver:id,name'])
            ->where('tenant_id', $this->tenantId());

        if ($from = $request->get('from')) {
            $query->whereDate('payment_date', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('payment_date', '<=', $to);
        }

        return ApiResponse::paginated($query->orderByDesc('payment_date')->paginate(min((int) $request->get('per_page', 20), 100)));
    }

    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', Payment::class);

        if ((int) $payment->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        return ApiResponse::data($payment->load(['payable', 'receiver:id,name']));
    }

    public function downloadPdf(Payment $payment)
    {
        $this->authorize('view', Payment::class);

        if ((int) $payment->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $payment->load(['payable.customer', 'receiver:id,name']);

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'payment' => $payment,
            'tenant' => auth()->user()->currentTenant,
        ]);

        return $pdf->download("recibo-{$payment->id}.pdf");
    }
}
