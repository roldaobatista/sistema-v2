<?php

use App\Models\CalibrationReading;

it('calculates correction as zero when error is exactly zero', function () {
    $reading = new CalibrationReading;
    $reading->reference_value = 100.0;
    $reading->indication_increasing = 100.0;

    $reading->calculateError();

    expect((float) $reading->error)->toBe(0.0);
    expect($reading->correction)->not->toBeNull('correction must not be null when error is zero');
    expect((float) $reading->correction)->toBe(0.0);
});

it('calculates correction as negative of error when error is nonzero', function () {
    $reading = new CalibrationReading;
    $reading->reference_value = 100.0;
    $reading->indication_increasing = 100.5;

    $reading->calculateError();

    expect((float) $reading->error)->toBe(0.5);
    expect((float) $reading->correction)->toBe(-0.5);
});

it('sets correction to null when error cannot be calculated', function () {
    $reading = new CalibrationReading;
    $reading->reference_value = 100.0;
    $reading->indication_increasing = null;
    $reading->error = null;

    $reading->calculateError();

    expect($reading->error)->toBeNull();
    expect($reading->correction)->toBeNull();
});

it('uses strict null check not truthy check for correction', function () {
    // Verify the source code uses !== null instead of truthy check.
    // The truthy check would fail for float 0.0 (falsy in PHP).
    // Even though the decimal:4 cast currently masks this,
    // the logic must be semantically correct.
    $source = file_get_contents(app_path('Models/CalibrationReading.php'));

    expect($source)->toContain('$this->error !== null')
        ->and($source)->not->toMatch('/\$this->error\s*\?\s*-\$this->error\s*:\s*null/');
});
