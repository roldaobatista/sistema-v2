<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Customer $customer */
        $customer = $this->resource;

        $nearestCalibrationAt = $customer->getRawOriginal('nearest_calibration_at')
            ?? ($customer->relationLoaded('equipments')
                ? $customer->equipments
                    ->pluck('next_calibration_at')
                    ->filter()
                    ->min()
                : null);

        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'type' => $this->type,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone2' => $this->phone2,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'address_zip' => $this->address_zip,
            'address_street' => $this->address_street,
            'address_number' => $this->address_number,
            'address_complement' => $this->address_complement,
            'address_neighborhood' => $this->address_neighborhood,
            'address_city' => $this->address_city,
            'address_state' => $this->address_state,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'google_maps_link' => $this->google_maps_link,
            'state_registration' => $this->state_registration,
            'municipal_registration' => $this->municipal_registration,
            'cnae_code' => $this->cnae_code,
            'cnae_description' => $this->cnae_description,
            'legal_nature' => $this->legal_nature,
            'capital' => $this->capital,
            'simples_nacional' => $this->simples_nacional,
            'mei' => $this->mei,
            'company_status' => $this->company_status,
            'opened_at' => $this->opened_at?->toDateString(),
            'is_rural_producer' => $this->is_rural_producer,
            'partners' => $this->partners,
            'secondary_activities' => $this->secondary_activities,
            'enrichment_data' => $this->enrichment_data,
            'enriched_at' => $this->enriched_at?->toIso8601String(),
            'source' => $this->source,
            'segment' => $this->segment,
            'company_size' => $this->company_size,
            'contract_type' => $this->contract_type,
            'annual_revenue_estimate' => $this->annual_revenue_estimate,
            'contract_start' => $this->contract_start?->toDateString(),
            'contract_end' => $this->contract_end?->toDateString(),
            'health_score' => $this->health_score,
            'last_contact_at' => $this->last_contact_at?->toIso8601String(),
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'assigned_seller_id' => $this->assigned_seller_id,
            'rating' => $this->rating,
            'tags' => $this->tags,
            'nearest_calibration_at' => $nearestCalibrationAt,
            'documents_count' => $this->whenCounted('documents'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($customer->relationLoaded('contacts')) {
            $arr['contacts'] = $this->contacts;
        }
        if ($customer->relationLoaded('assignedSeller')) {
            $arr['assigned_seller'] = $this->assignedSeller;
        }
        if ($customer->relationLoaded('equipments')) {
            $arr['equipments'] = $customer->equipments;
        }

        return $arr;
    }
}
