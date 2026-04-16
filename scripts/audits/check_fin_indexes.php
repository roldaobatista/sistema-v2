<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = ['cash_flows', 'accounts_payable', 'accounts_receivable'];

foreach ($tables as $t) {
    if (! Schema::hasTable($t)) {
        continue;
    }
    echo "Table: $t\n";
    $indexes = DB::select("SHOW INDEXES FROM `$t`");
    $byName = [];
    foreach ($indexes as $idx) {
        $name = $idx->Key_name ?? $idx->KEY_NAME;
        $byName[$name][] = $idx->Column_name ?? $idx->COLUMN_NAME;
    }

    foreach ($byName as $name => $cols) {
        if (count($cols) > 1) {
            echo "  Composite Index: $name (".implode(', ', $cols).")\n";
        }
    }
}
echo "Done.\n";
