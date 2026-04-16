<?php

use Illuminate\Support\Facades\DB;

$cols = DB::select("
    SELECT TABLE_NAME as table_name, COLUMN_NAME as column_name, DATA_TYPE as data_type
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE IN ('float', 'double')
    AND (
        COLUMN_NAME LIKE '%value%' OR
        COLUMN_NAME LIKE '%amount%' OR
        COLUMN_NAME LIKE '%price%' OR
        COLUMN_NAME LIKE '%total%' OR
        COLUMN_NAME LIKE '%valor%' OR
        COLUMN_NAME LIKE '%preco%'
    )
");

if (empty($cols)) {
    echo "No float/double money columns found.\n";
} else {
    echo "Warning: Float/Double money columns found:\n";
    foreach ($cols as $c) {
        $t = $c->table_name ?? $c->TABLE_NAME ?? '?';
        $col = $c->column_name ?? $c->COLUMN_NAME ?? '?';
        $dt = $c->data_type ?? $c->DATA_TYPE ?? '?';
        echo "- Table: $t, Column: $col, Type: $dt\n";
    }
}
