<?php

namespace App\Http\Resources\Journey;

use App\Models\JourneyDay;
use App\Models\JourneyEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JourneyDay|JourneyEntry
 */
class JourneyDayResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->formatUser()),
            'reference_date' => $this->formatDateAttribute('reference_date', 'date'),
            'regime_type' => $this->regime_type,
            'total_minutes_worked' => $this->total_minutes_worked,
            'total_minutes_overtime' => $this->total_minutes_overtime,
            'total_minutes_travel' => $this->total_minutes_travel,
            'total_minutes_wait' => $this->total_minutes_wait,
            'total_minutes_break' => $this->total_minutes_break,
            'total_minutes_overnight' => $this->total_minutes_overnight,
            'total_minutes_oncall' => $this->total_minutes_oncall,
            'operational_approval_status' => $this->operational_approval_status,
            'operational_approver_id' => $this->operational_approver_id,
            'operational_approved_at' => $this->operational_approved_at?->toISOString(),
            'hr_approval_status' => $this->hr_approval_status,
            'hr_approver_id' => $this->hr_approver_id,
            'hr_approved_at' => $this->hr_approved_at?->toISOString(),
            'is_closed' => $this->is_closed,
            'is_pending_approval' => $this->isPendingApproval(),
            'is_fully_approved' => $this->isFullyApproved(),
            'notes' => $this->notes,
            'blocks' => JourneyBlockResource::collection($this->whenLoaded('blocks')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function formatDateAttribute(string ...$attributes): ?string
    {
        $resource = $this->resource;

        if (! $resource instanceof JourneyDay && ! $resource instanceof JourneyEntry) {
            return null;
        }

        $availableAttributes = $resource->getAttributes();

        foreach ($attributes as $attribute) {
            if (! array_key_exists($attribute, $availableAttributes)) {
                continue;
            }

            $value = $resource->getAttribute($attribute);

            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if (is_string($value) && $value !== '') {
                return substr($value, 0, 10);
            }
        }

        return null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function formatUser(): ?array
    {
        $resource = $this->resource;

        if (! $resource instanceof JourneyDay && ! $resource instanceof JourneyEntry) {
            return null;
        }

        $user = $resource->getRelation('user');

        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
