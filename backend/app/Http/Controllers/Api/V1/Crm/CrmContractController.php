<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\UpdateCrmContractRenewalRequest;
use App\Models\CrmContractRenewal;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmContractController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function contractRenewals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.renewal.view'), 403);

        $renewals = CrmContractRenewal::where('tenant_id', $this->tenantId($request))
            ->with(['customer:id,name,contract_end,contract_type', 'deal:id,title,status'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('contract_end_date')
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return ApiResponse::paginated($renewals);
    }

    public function generateRenewals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.create'), 403);

        $tenantId = $this->tenantId($request);
        $generated = 0;

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('contract_end')
            ->where('contract_end', '<=', now()->addDays(90))
            ->where('contract_end', '>=', now())
            ->get();

        foreach ($customers as $customer) {
            $exists = CrmContractRenewal::where('tenant_id', $tenantId)
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'notified', 'in_negotiation'])
                ->exists();

            if (! $exists) {
                $lastDealValue = CrmDeal::where('customer_id', $customer->id)
                    ->won()
                    ->latest('won_at')
                    ->value('value') ?? 0;

                $renewal = CrmContractRenewal::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'contract_end_date' => $customer->contract_end,
                    'current_value' => $lastDealValue,
                    'status' => 'pending',
                ]);

                $defaultPipeline = CrmPipeline::where('tenant_id', $tenantId)->default()->first();
                if ($defaultPipeline) {
                    $firstStage = $defaultPipeline->stages()->orderBy('sort_order')->first();
                    if ($firstStage) {
                        $deal = CrmDeal::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'pipeline_id' => $defaultPipeline->id,
                            'stage_id' => $firstStage->id,
                            'title' => "Renovação - {$customer->name}",
                            'value' => $lastDealValue,
                            'source' => 'contrato_renovacao',
                            'assigned_to' => $customer->assigned_seller_id,
                            'expected_close_date' => $customer->contract_end,
                        ]);
                        $renewal->update(['deal_id' => $deal->id]);
                    }
                }

                $generated++;
            }
        }

        return ApiResponse::message("{$generated} renovações geradas");
    }

    public function updateRenewal(UpdateCrmContractRenewalRequest $request, CrmContractRenewal $renewal): JsonResponse
    {
        $renewal->update($request->validated());
        if ($request->input('status') === 'renewed') {
            $renewal->update(['renewed_at' => now()]);
        }

        return ApiResponse::data($renewal);
    }
}
