<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\User;
use App\Models\VisitCheckin;

class CheckinCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        /** @var Customer $customer */
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($data['customer_id']);

        $distanceMeters = null;
        if ($customer->latitude && $customer->longitude && isset($data['checkin_lat']) && isset($data['checkin_lng'])) {
            $distanceMeters = $this->haversineDistance(
                $data['checkin_lat'], $data['checkin_lng'],
                $customer->latitude, $customer->longitude
            );
        }

        $checkin = VisitCheckin::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'checkin_at' => now(),
            'checkin_lat' => $data['checkin_lat'] ?? null,
            'checkin_lng' => $data['checkin_lng'] ?? null,
            'checkin_address' => $data['checkin_address'] ?? null,
            'distance_from_client_meters' => $distanceMeters,
            'status' => 'checked_in',
            'notes' => $data['notes'] ?? null,
        ]);

        $activity = CrmActivity::create([
            'tenant_id' => $tenantId,
            'type' => 'visita',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'title' => 'Check-in: '.$customer->name,
            'scheduled_at' => now(),
            'channel' => 'in_person',
        ]);

        $checkin->update(['activity_id' => $activity->id]);
        $customer->update(['last_contact_at' => now()]);

        return $checkin->load(['customer:id,name', 'user:id,name']);
    }
}
