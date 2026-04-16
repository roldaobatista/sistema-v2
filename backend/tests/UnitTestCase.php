<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

/**
 * Base class for pure Unit tests that do NOT need database access.
 * Uses no database trait — tests run 10x faster.
 *
 * Use this for: Services with mocks, value objects, helpers, calculations.
 * Use TestCase (with DB) for: Tests that need factories, queries, HTTP calls.
 */
abstract class UnitTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hash::driver('bcrypt')->setRounds(4);
    }
}
