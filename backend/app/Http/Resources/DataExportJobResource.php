<?php

namespace App\Http\Resources;

use App\Models\DataExportJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DataExportJob
 */
class DataExportJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'analytics_dataset_id' => $this->analytics_dataset_id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'status' => $this->status,
            'source_modules' => $this->source_modules,
            'filters' => $this->filters,
            'output_format' => $this->output_format,
            'output_path' => $this->output_path,
            'file_size_bytes' => $this->file_size_bytes,
            'rows_exported' => $this->rows_exported,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'scheduled_cron' => $this->scheduled_cron,
            'last_scheduled_at' => $this->last_scheduled_at?->toIso8601String(),
            'dataset' => $this->whenLoaded('dataset'),
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
