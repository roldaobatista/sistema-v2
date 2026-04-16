<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\CustomerRfmScore;
use App\Models\User;

class RfmIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $bySegment = CustomerRfmScore::where('tenant_id', $tenantId)
            ->selectRaw('rfm_segment, COUNT(*) as count, SUM(total_revenue) as total_revenue')
            ->groupBy('rfm_segment')
            ->get()
            ->keyBy('rfm_segment')
            ->map(fn ($row) => [
                'count' => (int) $row->getAttribute('count'),
                'total_revenue' => (float) $row->getAttribute('total_revenue'),
            ]);

        $scores = CustomerRfmScore::where('tenant_id', $tenantId)
            ->with('customer:id,name,rating,health_score,segment')
            ->orderByDesc('total_score')
            ->paginate(min((int) ($data['per_page'] ?? 25), 100));

        return [
            'scores' => $scores,
            'by_segment' => $bySegment,
            'segments' => CustomerRfmScore::SEGMENTS,
        ];
    }
}
