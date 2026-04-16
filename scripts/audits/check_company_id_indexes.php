<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = DB::select("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
$missing = [];

foreach ($tables as $t) {
    // Handling case-insensitivity of PDO returns
    $table = isset($t->table_name) ? $t->table_name : $t->TABLE_NAME;
    if (Schema::hasColumn($table, 'company_id')) {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Column_name = 'company_id' AND Seq_in_index = 1");
        if (empty($indexes)) {
            $missing[] = $table;
        }
    }
}

if (empty($missing)) {
    echo "No tables missing company_id index.\n";
} else {
    echo "Tables missing company_id index: \n";
    foreach ($missing as $m) {
        echo "- $m\n";
    }
}
