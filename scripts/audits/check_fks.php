<?php

use Illuminate\Support\Facades\DB;

$fks = DB::select('
    SELECT TABLE_NAME as table_name,
           COLUMN_NAME as column_name,
           CONSTRAINT_NAME as constraint_name,
           REFERENCED_TABLE_NAME as referenced_table_name,
           REFERENCED_COLUMN_NAME as referenced_column_name
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
');

$missing = [];
foreach ($fks as $fk) {
    $indexes = DB::select("SHOW INDEX FROM `{$fk->table_name}` WHERE Column_name = '{$fk->column_name}'");
    if (empty($indexes)) {
        $missing[] = "Table: {$fk->table_name}, Column: {$fk->column_name} (FK to {$fk->referenced_table_name})";
    }
}

if (empty($missing)) {
    echo "No missing indexes for foreign keys.\n";
} else {
    echo "Missing indexes found:\n";
    foreach ($missing as $m) {
        echo "- $m\n";
    }
}
