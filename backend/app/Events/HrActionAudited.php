<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HrActionAudited
{
    use Dispatchable, SerializesModels;

    public string $action;

    public string $modelType;

    public int $modelId;

    public ?array $oldValues;

    public ?array $newValues;

    public int $userId;

    public function __construct(
        string $action,
        string $modelType,
        int $modelId,
        ?array $oldValues,
        ?array $newValues,
        int $userId
    ) {
        $this->action = $action;
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->oldValues = $oldValues;
        $this->newValues = $newValues;
        $this->userId = $userId;
    }
}
