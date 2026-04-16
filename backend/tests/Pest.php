<?php

use Tests\Critical\CriticalTestCase;
use Tests\Performance\PerformanceTestCase;
use Tests\Smoke\SmokeTestCase;
use Tests\TestCase;

chdir(dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(SmokeTestCase::class)->in('Smoke');
uses(CriticalTestCase::class)->in('Critical');
uses(PerformanceTestCase::class)->in('Performance');
