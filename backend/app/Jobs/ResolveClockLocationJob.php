<?php

namespace App\Jobs;

use App\Models\TimeClockEntry;
use App\Services\ReverseGeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResolveClockLocationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $backoff = 10;

    public int $entryId;

    public string $type; // 'in', 'out', or 'break'

    public function __construct(int $entryId, string $type)
    {
        $this->entryId = $entryId;
        $this->type = $type;
    }

    public function handle(ReverseGeocodingService $geocodingService): void
    {
        $entry = TimeClockEntry::find($this->entryId);
        if (! $entry) {
            return;
        }

        $lat = null;
        $lng = null;

        if ($this->type === 'in') {
            $lat = $entry->latitude_in;
            $lng = $entry->longitude_in;
        } elseif ($this->type === 'out') {
            $lat = $entry->latitude_out;
            $lng = $entry->longitude_out;
        } elseif ($this->type === 'break') {
            $lat = $entry->break_latitude;
            $lng = $entry->break_longitude;
        }

        if (! $lat || ! $lng) {
            return;
        }

        $address = $geocodingService->resolve((float) $lat, (float) $lng);

        if ($this->type === 'in') {
            $entry->address_in = $address;
        } elseif ($this->type === 'out') {
            $entry->address_out = $address;
        } elseif ($this->type === 'break') {
            $entry->address_break = $address;
        }

        $entry->save();
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ResolveClockLocationJob failed permanently for entry #{$this->entryId} (type: {$this->type})", [
            'error' => $e->getMessage(),
        ]);
    }
}
