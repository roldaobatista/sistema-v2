<?php

namespace App\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\SQLiteConnection as BaseSQLiteConnection;
use Illuminate\Support\Fluent;

/**
 * Custom SQLite connection for testing.
 *
 * Override getDefaultSchemaGrammar() so that the grammar is ALWAYS our
 * custom version — even when Laravel internally recreates it (e.g.
 * LazilyRefreshDatabase resets the connection).
 */
class TestingSQLiteConnection extends BaseSQLiteConnection
{
    protected function getDefaultSchemaGrammar()
    {
        $grammar = new TestingSQLiteSchemaGrammar($this);
        $grammar->setTablePrefix($this->getTablePrefix());

        return $grammar;
    }
}

/**
 * Schema Grammar that does NOT throw on dropForeign by name.
 * Also uses IF NOT EXISTS for CREATE INDEX to avoid rebuild conflicts.
 */
class TestingSQLiteSchemaGrammar extends SQLiteGrammar
{
    /**
     * Compile a drop foreign key command — no-op on SQLite.
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command): mixed
    {
        // SQLite does not support dropping foreign keys at all.
        // The original grammar throws RuntimeException when $command->columns is empty
        // (i.e. when called by FK name as string). We silently ignore it.
        return [];
    }

    /**
     * Use IF NOT EXISTS for CREATE INDEX to prevent conflicts during table rebuilds.
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        return str_replace(
            'create index',
            'create index if not exists',
            parent::compileIndex($blueprint, $command)
        );
    }
}
