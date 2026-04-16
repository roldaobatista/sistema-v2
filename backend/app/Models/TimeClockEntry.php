<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\Immutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $clock_in
 * @property Carbon|null $clock_out
 * @property Carbon|null $break_start
 * @property Carbon|null $break_end
 * @property numeric-string|null $latitude_in
 * @property numeric-string|null $longitude_in
 * @property numeric-string|null $latitude_out
 * @property numeric-string|null $longitude_out
 * @property numeric-string|null $break_latitude
 * @property numeric-string|null $break_longitude
 * @property numeric-string|null $liveness_score
 * @property bool|null $liveness_passed
 * @property int|null $geofence_distance_meters
 * @property array<int|string, mixed>|null $device_info
 * @property Carbon|null $confirmed_at
 * @property numeric-string|null $accuracy_in
 * @property numeric-string|null $accuracy_out
 * @property numeric-string|null $accuracy_break
 * @property numeric-string|null $altitude_in
 * @property numeric-string|null $altitude_out
 * @property numeric-string|null $speed_in
 * @property bool|null $location_spoofing_detected
 */
class TimeClockEntry extends Model
{
    use BelongsToTenant, HasFactory, Immutable;

    protected $fillable = [
        'tenant_id', 'user_id', 'clock_in', 'clock_out',
        'latitude_in', 'longitude_in', 'latitude_out', 'longitude_out',
        'type', 'notes',
        'selfie_path', 'liveness_score', 'liveness_passed',
        'geofence_location_id', 'geofence_distance_meters',
        'device_info', 'ip_address', 'clock_method',
        'approval_status', 'approved_by', 'rejection_reason',
        'work_order_id',
        'break_start', 'break_end', 'break_latitude', 'break_longitude',
        'record_hash', 'previous_hash', 'hash_payload', 'nsr',
        'employee_confirmation_hash', 'confirmed_at', 'confirmation_method',
        'accuracy_in', 'accuracy_out', 'accuracy_break',
        'address_in', 'address_out', 'address_break',
        'altitude_in', 'altitude_out', 'speed_in', 'location_spoofing_detected',
    ];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'break_start' => 'datetime',
            'break_end' => 'datetime',
            'latitude_in' => 'decimal:7',
            'longitude_in' => 'decimal:7',
            'latitude_out' => 'decimal:7',
            'longitude_out' => 'decimal:7',
            'break_latitude' => 'decimal:7',
            'break_longitude' => 'decimal:7',
            'liveness_score' => 'decimal:2',
            'liveness_passed' => 'boolean',
            'geofence_distance_meters' => 'integer',
            'device_info' => 'array',
            'confirmed_at' => 'datetime',
            'accuracy_in' => 'decimal:2',
            'accuracy_out' => 'decimal:2',
            'accuracy_break' => 'decimal:2',
            'altitude_in' => 'decimal:2',
            'altitude_out' => 'decimal:2',
            'speed_in' => 'decimal:2',
            'location_spoofing_detected' => 'boolean',
        ];

    }

    // ─── Relationships ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function geofenceLocation(): BelongsTo
    {
        return $this->belongsTo(GeofenceLocation::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(TimeClockAdjustment::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->whereNull('clock_out');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeTodayForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->whereDate('clock_in', today());
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereDate('clock_in', '>=', $from)
            ->whereDate('clock_in', '<=', $to);
    }

    // ─── Accessors ──────────────────────────────────────────────

    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->clock_out) {
            return null;
        }

        return $this->clock_in->diffInMinutes($this->clock_out);
    }

    public function getDurationHoursAttribute(): ?float
    {
        if (! $this->clock_out) {
            return null;
        }

        return round($this->clock_in->diffInMinutes($this->clock_out) / 60, 2);
    }

    public function getIsWithinGeofenceAttribute(): bool
    {
        return $this->geofence_distance_meters !== null
            && $this->geofence_location_id !== null
            && $this->geofenceLocation
            && $this->geofence_distance_meters <= $this->geofenceLocation->radius_meters;
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->clock_out === null;
    }
}
