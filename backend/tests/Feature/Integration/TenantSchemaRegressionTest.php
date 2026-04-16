<?php

use Illuminate\Support\Facades\Schema;

test('tenants table keeps deleted_at required by soft deletes', function () {
    expect(Schema::hasColumn('tenants', 'deleted_at'))->toBeTrue();
});
