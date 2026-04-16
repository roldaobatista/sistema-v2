<?php

namespace App\Actions\ServiceCall;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

abstract class BaseServiceCallAction
{
    /**
     * @return mixed
     */
    protected function notFoundResponse()
    {
        return ApiResponse::message('Chamado nao encontrado', 404);
    }

    /**
     * @param  literal-string  $endExpr
     * @return literal-string
     */
    protected function slaBreachCondition(string $endExpr = 'NOW()'): string
    {
        $driver = DB::getDriverName();
        $slaCase = "CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 8 WHEN 'normal' THEN 24 WHEN 'low' THEN 48 ELSE 24 END";

        if ($driver === 'sqlite') {
            return "(julianday(COALESCE({$endExpr}, datetime('now'))) - julianday(created_at)) * 24 > {$slaCase}";
        }

        return "TIMESTAMPDIFF(HOUR, created_at, COALESCE({$endExpr}, NOW())) > {$slaCase}";
    }

    protected function canAssignTechnician(User $user): bool
    {
        return $user->hasRole(Role::SUPER_ADMIN) || $user->can('service_calls.service_call.assign');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function requestTouchesAssignmentFields(array $data): bool
    {
        return array_key_exists('technician_id', $data)
            || array_key_exists('driver_id', $data)
            || array_key_exists('scheduled_date', $data);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function syncLocationToCustomer(int $customerId, array $validated): void
    {
        $locationFields = ['latitude', 'longitude', 'google_maps_link', 'city', 'state', 'address'];
        $hasLocationData = false;
        foreach ($locationFields as $field) {
            if (! empty($validated[$field])) {
                $hasLocationData = true;
                break;
            }
        }
        if (! $hasLocationData) {
            return;
        }

        $customer = Customer::find($customerId);
        if (! $customer) {
            return;
        }

        $update = [];

        if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
            if (! $customer->latitude || ! $customer->longitude) {
                $update['latitude'] = $validated['latitude'];
                $update['longitude'] = $validated['longitude'];
            }
        }

        if (! empty($validated['google_maps_link']) && ! $customer->google_maps_link) {
            $update['google_maps_link'] = $validated['google_maps_link'];
        }

        if (! empty($validated['city']) && ! $customer->address_city) {
            $update['address_city'] = $validated['city'];
        }

        if (! empty($validated['state']) && ! $customer->address_state) {
            $update['address_state'] = $validated['state'];
        }

        if (! empty($validated['address']) && ! $customer->address_street) {
            $update['address_street'] = $validated['address'];
        }

        if (! empty($update)) {
            $customer->update($update);
        }
    }
}
