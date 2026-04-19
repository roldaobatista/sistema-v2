<?php

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

/**
 * Base class for unit tests that do NOT touch the database.
 *
 * IMPORTANT: This class still boots the full Laravel framework (container,
 * service providers, facades) because it extends {@see BaseTestCase}. It is
 * NOT a "pure" PHPUnit TestCase and it is NOT measurably faster than
 * {@see TestCase} for test cases that exercise framework services. The
 * single difference is that {@see TestCase} includes the
 * {@see LazilyRefreshDatabase} trait and this
 * class does not — so there is zero DB reset overhead between tests.
 *
 * Use this for: services with mocked dependencies, value objects, helpers,
 * and pure calculations that should never hit the database.
 * Use {@see TestCase} for: tests that need factories, queries, HTTP calls,
 * or any code path that reads/writes the database.
 */
abstract class UnitTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hash::driver('bcrypt')->setRounds(4);
    }
}
