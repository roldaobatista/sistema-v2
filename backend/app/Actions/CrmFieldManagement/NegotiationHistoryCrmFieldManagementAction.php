<?php

namespace App\Actions\CrmFieldManagement;

use App\Http\Resources\CrmActivityResource;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;

class NegotiationHistoryCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, Customer $customer, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        if ($customer->tenant_id !== $tenantId) {
            abort(404);
        }

        $quotes = Quote::where('customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->select('id', 'quote_number', 'total', 'status', 'created_at', 'approved_at', 'discount_amount')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($q) => [
                'entry_type' => 'quote',
                'type' => 'quote',
                ...$q->toArray(),
            ]);

        $workOrders = WorkOrder::where('customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->select('id', 'os_number', 'business_number', 'total', 'status', 'created_at', 'completed_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($w) => [
                'entry_type' => 'work_order',
                'type' => 'work_order',
                ...$w->toArray(),
            ]);

        $deals = CrmDeal::where('customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->select('id', 'title', 'value', 'status', 'created_at', 'won_at', 'lost_at', 'lost_reason')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($d) => [
                'entry_type' => 'deal',
                'type' => 'deal',
                ...$d->toArray(),
            ]);

        $activities = CrmActivity::where('customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->with(['user:id,name', 'contact:id,name', 'deal:id,title'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (CrmActivity $activity) {
                return [
                    'entry_type' => 'activity',
                    ...CrmActivityResource::make($activity)->resolve(),
                ];
            });

        $timeline = $quotes->concat($workOrders)->concat($deals)->concat($activities)
            ->sortByDesc('created_at')
            ->values();

        $totals = [
            'total_quoted' => $quotes->sum('total'),
            'total_os' => $workOrders->sum('total'),
            'total_deals_won' => $deals->where('status', 'won')->sum('value'),
            'quotes_count' => $quotes->count(),
            'os_count' => $workOrders->count(),
            'deals_count' => $deals->count(),
            'activities_count' => $activities->count(),
            'messages_count' => $activities->whereIn('type', ['email', 'whatsapp', 'sms'])->count(),
            'avg_discount' => $quotes->avg('discount_amount') ?? 0,
        ];

        return [
            'timeline' => $timeline,
            'totals' => $totals,
        ];
    }
}
