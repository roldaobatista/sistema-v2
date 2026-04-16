<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitCheckin;

abstract class BaseCrmFieldManagementAction
{
    protected function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function checkinsIndex(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = VisitCheckin::where('tenant_id', $tenantId)
            ->with(['customer:id,name,phone,address_city', 'user:id,name']);

        if (isset($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        }
        if (isset($data['customer_id'])) {
            $q->where('customer_id', $data['customer_id']);
        }
        if (isset($data['status'])) {
            $q->where('status', $data['status']);
        }
        if (isset($data['date_from'])) {
            $q->where('checkin_at', '>=', $data['date_from']);
        }
        if (isset($data['date_to'])) {
            $q->where('checkin_at', '<=', $data['date_to'].' 23:59:59');
        }

        return $q->orderByDesc('checkin_at')->paginate(min((int) ($data['per_page'] ?? 25), 100));
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, mixed>
     */
    protected function quintiles(array $values, bool $inverse = false): array
    {
        if (empty($values)) {
            return [0, 0, 0, 0, 0];
        }
        sort($values);
        $n = count($values);

        return [
            $values[(int) ($n * 0.2)] ?? 0,
            $values[(int) ($n * 0.4)] ?? 0,
            $values[(int) ($n * 0.6)] ?? 0,
            $values[(int) ($n * 0.8)] ?? 0,
            $values[$n - 1] ?? 0,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $quintiles
     */
    protected function scoreInQuintile(float $value, array $quintiles, bool $inverse = false): int
    {
        if ($inverse) {
            if ($value <= $quintiles[0]) {
                return 5;
            }
            if ($value <= $quintiles[1]) {
                return 4;
            }
            if ($value <= $quintiles[2]) {
                return 3;
            }
            if ($value <= $quintiles[3]) {
                return 2;
            }

            return 1;
        }
        if ($value >= $quintiles[3]) {
            return 5;
        }
        if ($value >= $quintiles[2]) {
            return 4;
        }
        if ($value >= $quintiles[1]) {
            return 3;
        }
        if ($value >= $quintiles[0]) {
            return 2;
        }

        return 1;
    }
}
