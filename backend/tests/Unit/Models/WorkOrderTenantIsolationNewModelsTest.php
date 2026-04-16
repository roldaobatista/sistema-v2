<?php

use App\Models\Concerns\BelongsToTenant;
use App\Models\WorkOrderDisplacementLocation;
use App\Models\WorkOrderDisplacementStop;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderRating;

test('WorkOrderEvent has BelongsToTenant trait', function () {
    $traits = class_uses_recursive(WorkOrderEvent::class);
    expect($traits)->toContain(BelongsToTenant::class);
});

test('WorkOrderRating has BelongsToTenant trait', function () {
    $traits = class_uses_recursive(WorkOrderRating::class);
    expect($traits)->toContain(BelongsToTenant::class);
});

test('WorkOrderDisplacementLocation has BelongsToTenant trait', function () {
    $traits = class_uses_recursive(WorkOrderDisplacementLocation::class);
    expect($traits)->toContain(BelongsToTenant::class);
});

test('WorkOrderDisplacementStop has BelongsToTenant trait', function () {
    $traits = class_uses_recursive(WorkOrderDisplacementStop::class);
    expect($traits)->toContain(BelongsToTenant::class);
});
