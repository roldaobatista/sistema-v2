<?php

use App\Models\EquipmentCalibration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a calibration with a valid decision_rule enum value', function () {
    $calibration = EquipmentCalibration::factory()->withWizardFields()->create();

    expect($calibration->decision_rule)->toBeIn(['simple', 'guard_band', 'shared_risk']);
});
